<?php
/**
 * Global Configuration & Helper Functions
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// App Information
define('APP_NAME', 'MaternalCare Prenatal Health System');
define('APP_VERSION', '1.0.0');

// Clinic timezone. XAMPP ships with a Europe/Berlin default, which would make
// "today" and slot times wrong by ~6 hours for a Philippine clinic.
define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

// Base URL Determination
// Railway (and most PaaS hosts) terminate TLS at a reverse proxy, so the app
// itself sees a plain HTTP connection. HTTPS is signaled via the
// X-Forwarded-Proto header instead of $_SERVER['HTTPS'] in that case.
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $isHttps ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$dir = str_replace('\\', '/', dirname($script_name));
// Ensure root relative URL path
$base_path = rtrim(preg_replace('#/(config|admin|worker|patient|api|includes)$#', '', $dir), '/');
define('BASE_URL', $protocol . "://" . $host . ($base_path ? $base_path : '') . "/");

require_once __DIR__ . '/database.php';

// Generate or fetch CSRF token
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Security: XSS Sanitization
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// Audit Logger
function logAudit($action, $details = '') {
    try {
        $db = getDB();
        $user_id = $_SESSION['user_id'] ?? null;
        $user_role = $_SESSION['user_role'] ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, user_role, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $user_role, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently fail to avoid breaking user operations if audit log table fails
    }
}

// Notification Helper
function createNotification($user_id, $title, $message, $type = 'system') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type]);
    } catch (Exception $e) {
        // Fail silently
    }
}

function notifyStaff($title, $message, $type = 'system') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE role IN ('healthcare_worker', 'admin')");
        $stmt->execute();
        $staffIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $insert = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        foreach ($staffIds as $uid) {
            $insert->execute([$uid, $title, $message, $type]);
        }
    } catch (Exception $e) {
        // Fail silently
    }
}

// Format Date nicely
function formatDate($dateStr, $format = 'M d, Y') {
    if (!$dateStr) return 'N/A';
    return date($format, strtotime($dateStr));
}

// Calculate Estimated Due Date (EDD) using Naegele's Rule (LMP + 280 days / + 9 months + 7 days)
function calculateEDD($lmpDateStr) {
    if (!$lmpDateStr) return null;
    $lmp = new DateTime($lmpDateStr);
    $edd = clone $lmp;
    $edd->modify('+280 days');
    return $edd->format('Y-m-d');
}

// Calculate Gestational Age in Weeks from LMP
function calculateGestationalAgeWeeks($lmpDateStr, $targetDateStr = 'now') {
    if (!$lmpDateStr) return 0;
    $lmp = new DateTime($lmpDateStr);
    $target = new DateTime($targetDateStr);
    $interval = $lmp->diff($target);
    $days = $interval->days;
    return floor($days / 7);
}

// Current User Accessors
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserName() {
    return $_SESSION['full_name'] ?? 'User';
}


// True when a stored password is not a usable bcrypt hash (e.g. the placeholder hash
// shipped in the old database.sql). Used only to let untouched demo accounts sign in once.
function hasUnusablePasswordHash($hash) {
    $info = password_get_info((string)$hash);
    return empty($info['algo']);
}

/**
 * Brings an older database up to the schema the code expects (adds missing columns/indexes,
 * and relaxes columns that older installs may have created as NOT NULL).
 * Runs once per browser session, so existing installs keep working without re-importing database.sql.
 */
function ensureSchemaUpgrades() {
    // Bumped to schema-7: examine-patient.php submits many prenatal_records columns
    // that are genuinely optional per service type (a Lab visit doesn't collect
    // fetal_heart_rate, a Vaccine visit doesn't collect weight, etc). On installs
    // where database.sql created these as NOT NULL with no default, saving any
    // examination that doesn't populate one of them throws a 1048 "cannot be null"
    // integrity-constraint error. Every column examine-patient.php can leave empty
    // is now force-relaxed to nullable below, not just added if missing.
    $marker = APP_VERSION . '-schema-7';
    if (($_SESSION['schema_ok'] ?? '') === $marker) return;

    try {
        $db = getDB();
        if ($db->query("SHOW TABLES LIKE 'users'")->rowCount() === 0) return; // not installed yet

        $hasColumn = function ($table, $column) use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $q->execute([$table, $column]);
            return (int)$q->fetchColumn() > 0;
        };
        $add = function ($table, $column, $definition) use ($db, $hasColumn) {
            if (!$hasColumn($table, $column)) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        };
        // Forces an existing column to allow NULL, keeping its current type/size but
        // dropping any NOT NULL constraint from an older schema. Safe to re-run.
        $makeNullable = function ($table, $column, $nullableDefinition) use ($db, $hasColumn) {
            if (!$hasColumn($table, $column)) return; // $add() above will have created it nullable already
            $isNullable = $db->prepare("
                SELECT IS_NULLABLE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $isNullable->execute([$table, $column]);
            if ($isNullable->fetchColumn() === 'NO') {
                $db->exec("ALTER TABLE `$table` MODIFY COLUMN `$column` $nullableDefinition");
            }
        };

        $add('users', 'address', 'TEXT DEFAULT NULL');
        $add('users', 'avatar', 'VARCHAR(255) DEFAULT NULL');
        $add('users', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
        $add('users', 'room', 'VARCHAR(100) DEFAULT NULL');
        $add('appointments', 'room', 'VARCHAR(100) DEFAULT NULL');
        $add('appointments', 'worker_notified', 'TINYINT(1) NOT NULL DEFAULT 0');
        $add('appointments', 'worker_confirmed_at', 'DATETIME DEFAULT NULL');
        $add('prenatal_records', 'weight_kg', 'DECIMAL(5,2) DEFAULT NULL');
        $add('prenatal_records', 'systolic_bp', 'SMALLINT DEFAULT NULL');
        $add('prenatal_records', 'diastolic_bp', 'SMALLINT DEFAULT NULL');
        $add('prenatal_records', 'temperature', 'DECIMAL(4,1) DEFAULT NULL');
        $add('prenatal_records', 'pulse_rate', 'SMALLINT DEFAULT NULL');
        $add('prenatal_records', 'respiratory_rate', 'SMALLINT DEFAULT NULL');
        $add('prenatal_records', 'fetal_heart_rate', 'SMALLINT DEFAULT NULL');
        $add('prenatal_records', 'fundal_height_cm', 'DECIMAL(4,1) DEFAULT NULL');
        $add('prenatal_records', 'fetal_presentation', 'VARCHAR(50) DEFAULT NULL');
        $add('prenatal_records', 'edema', "VARCHAR(20) DEFAULT 'none'");
        $add('prenatal_records', 'urine_protein', "VARCHAR(20) DEFAULT 'Negative'");
        $add('prenatal_records', 'urine_sugar', "VARCHAR(20) DEFAULT 'Negative'");
        $add('prenatal_records', 'gestational_age_weeks', 'SMALLINT DEFAULT NULL');
        $add('prenatal_records', 'risk_assessment', "VARCHAR(20) DEFAULT 'low_risk'");
        $add('prenatal_records', 'clinical_notes', 'TEXT DEFAULT NULL');
        $add('prenatal_records', 'next_visit_date', 'DATE DEFAULT NULL');
        $add('prenatal_records', 'vitamins_prescribed', 'VARCHAR(255) DEFAULT NULL');
        $add('prenatal_records', 'iron_folic_given', 'VARCHAR(255) DEFAULT NULL');
        $add('prenatal_records', 'tetanus_vaccine_given', 'VARCHAR(100) DEFAULT NULL');

        // Every prenatal_records column that examine-patient.php may submit as empty
        // depending on service type. Column type/size below matches what $add() uses
        // above, so re-running this never shrinks or changes an existing column's type.
        $makeNullable('prenatal_records', 'weight_kg', 'DECIMAL(5,2) DEFAULT NULL');
        $makeNullable('prenatal_records', 'systolic_bp', 'SMALLINT DEFAULT NULL');
        $makeNullable('prenatal_records', 'diastolic_bp', 'SMALLINT DEFAULT NULL');
        $makeNullable('prenatal_records', 'temperature', 'DECIMAL(4,1) DEFAULT NULL');
        $makeNullable('prenatal_records', 'pulse_rate', 'SMALLINT DEFAULT NULL');
        $makeNullable('prenatal_records', 'respiratory_rate', 'SMALLINT DEFAULT NULL');
        $makeNullable('prenatal_records', 'fetal_heart_rate', 'SMALLINT DEFAULT NULL');
        $makeNullable('prenatal_records', 'fundal_height_cm', 'DECIMAL(4,1) DEFAULT NULL');
        $makeNullable('prenatal_records', 'fetal_presentation', 'VARCHAR(50) DEFAULT NULL');
        $makeNullable('prenatal_records', 'gestational_age_weeks', 'SMALLINT DEFAULT NULL');
        $makeNullable('prenatal_records', 'clinical_notes', 'TEXT DEFAULT NULL');
        $makeNullable('prenatal_records', 'next_visit_date', 'DATE DEFAULT NULL');
        $makeNullable('prenatal_records', 'vitamins_prescribed', 'VARCHAR(255) DEFAULT NULL');
        $makeNullable('prenatal_records', 'iron_folic_given', 'VARCHAR(255) DEFAULT NULL');
        $makeNullable('prenatal_records', 'tetanus_vaccine_given', 'VARCHAR(100) DEFAULT NULL');
        // edema / urine_protein / urine_sugar / risk_assessment are intentionally left
        // alone here: examine-patient.php always supplies a value for these (it falls
        // back to 'none' / 'Negative' / 'low_risk' rather than sending empty), so they
        // don't need to be nullable.

        $type = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'")->fetchColumn();
        if ($type && stripos($type, 'archived') === false) {
            $db->exec("ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active'");
        }

        // Speeds up the live slot counters (they read this table every few seconds)
        $idx = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'idx_appt_date_service'")->fetchColumn();
        if ((int)$idx === 0) {
            $db->exec("ALTER TABLE appointments ADD INDEX idx_appt_date_service (appointment_date, service_id, status)");
        }

        $_SESSION['schema_ok'] = $marker;
    } catch (Exception $e) {
        // Never break a page because a migration could not run
    }
}

// Returns the patients row for a user, creating an empty profile if a patient account was
// created without one (e.g. by an admin) so booking never fails on a missing profile.
function ensurePatientProfile($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$userId]);
    $patient = $stmt->fetch();
    if ($patient) return $patient;

    $code = "PN-" . date('Y') . "-" . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
    $ins = $db->prepare("INSERT INTO patients (user_id, patient_code, address, blood_type, gravida, para) VALUES (?, ?, '', 'A+', 1, 0)");
    $ins->execute([$userId, $code]);
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// UTF-8 safe text helpers (work even if the mbstring extension is switched off)
function clipText($text, $max) {
    return preg_match('/^.{0,' . (int)$max . '}/us', (string)$text, $m) ? $m[0] : substr((string)$text, 0, (int)$max);
}
function textLength($text) {
    return (int)preg_match_all('/./us', (string)$text);
}

// System Logo Image Helper
function getSystemLogoUrl() {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_logo' LIMIT 1");
        $stmt->execute();
        $logo = $stmt->fetchColumn();
        if ($logo && file_exists(__DIR__ . '/../' . $logo)) {
            return BASE_URL . $logo;
        }
    } catch (Exception $e) {}
    return null;
}

ensureSchemaUpgrades();
