<?php
/**
 * Manage Slots API
 * File: api/manage_slots.php
 * Compatible sa slot_helper.php schema (override_date, start_time, max_patients, reason)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slot_helper.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || $userRole !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (function_exists('verifyCsrfToken') && !verifyCsrfToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $db = getDB();
    ensureSlotOverridesTable($db);

    /* ============================================
       UPDATE DAY SCHEDULE
       ============================================ */
    if ($action === 'update_day_schedule') {
        $day = $_POST['day_of_week'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $capacity = (int)($_POST['max_patients_per_slot'] ?? 2);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!$day || !$start || !$end) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM schedules WHERE day_of_week = ? AND staff_id IS NULL");
        $stmt->execute([$day]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE schedules SET start_time = ?, end_time = ?, max_patients_per_slot = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$start, $end, $capacity, $isActive, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO schedules (day_of_week, start_time, end_time, max_patients_per_slot, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$day, $start, $end, $capacity, $isActive]);
        }

        if (function_exists('logAudit')) logAudit('SCHEDULE_UPDATE', "Updated schedule for $day");
        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        exit;
    }

    /* ============================================
       UPDATE SLOT CAPACITY
       ============================================ */
    if ($action === 'update_slot_capacity') {
        $date = $_POST['override_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? $startTime;
        $capacity = (int)($_POST['max_patients'] ?? 2);

        if (!$date || !$startTime) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM slot_overrides WHERE override_date = ? AND start_time = ?");
        $stmt->execute([$date, $startTime]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE slot_overrides SET max_patients = ? WHERE id = ?");
            $stmt->execute([$capacity, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO slot_overrides (override_date, start_time, end_time, max_patients, is_blocked, reason) VALUES (?, ?, ?, ?, 0, 'Capacity adjusted')");
            $stmt->execute([$date, $startTime, $endTime, $capacity]);
        }

        echo json_encode(['success' => true, 'message' => 'Capacity updated']);
        exit;
    }

    /* ============================================
       UPDATE SLOT TIME
       ============================================ */
    if ($action === 'update_slot_time') {
        $overrideDate = $_POST['override_date'] ?? '';
        $oldStart = $_POST['old_start_time'] ?? '';
        $newStart = $_POST['new_start_time'] ?? '';
        $newEnd = $_POST['new_end_time'] ?? $newStart;

        if (!$overrideDate || !$oldStart || !$newStart) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $newStart)) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            exit;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status NOT IN ('cancelled', 'rejected')");
        $stmt->execute([$overrideDate, $oldStart]);
        if ((int)$stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot edit. There is an existing booking in this slot.']);
            exit;
        }

        $stmt = $db->prepare("SELECT max_patients FROM slot_overrides WHERE override_date = ? AND start_time = ? LIMIT 1");
        $stmt->execute([$overrideDate, $oldStart]);
        $existingCapacity = $stmt->fetchColumn();
        $capacity = $existingCapacity !== false ? (int)$existingCapacity : 2;

        $stmt = $db->prepare("DELETE FROM slot_overrides WHERE override_date = ? AND start_time = ?");
        $stmt->execute([$overrideDate, $oldStart]);

        $stmt = $db->prepare("INSERT INTO slot_overrides (override_date, start_time, end_time, max_patients, is_blocked, reason) VALUES (?, ?, ?, ?, 0, 'Time updated')");
        $stmt->execute([$overrideDate, $newStart, $newEnd, $capacity]);

        if (function_exists('logAudit')) logAudit('SLOT_TIME_UPDATE', "Updated slot from $oldStart to $newStart on $overrideDate");
        echo json_encode(['success' => true, 'message' => 'Time updated successfully']);
        exit;
    }

    /* ============================================
       DELETE SLOT
       ============================================ */
    if ($action === 'delete_slot') {
        $date = $_POST['override_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';

        if (!$date || !$startTime) {
            echo json_encode(['success' => false, 'message' => 'Missing date or time']);
            exit;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status NOT IN ('cancelled', 'rejected')");
        $stmt->execute([$date, $startTime]);
        $bookedCount = (int)$stmt->fetchColumn();

        if ($bookedCount > 0) {
            echo json_encode(['success' => false, 'message' => "Cannot delete. There are $bookedCount existing booking(s) in this slot."]);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM slot_overrides WHERE override_date = ? AND start_time = ?");
        $stmt->execute([$date, $startTime]);

        if (function_exists('logAudit')) logAudit('SLOT_DELETED', "Deleted slot $startTime on $date");
        echo json_encode(['success' => true, 'message' => 'Slot deleted successfully']);
        exit;
    }

    /* ============================================
       BLOCK SLOT
       ============================================ */
    if ($action === 'block_slot') {
        $date = $_POST['override_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? $startTime;
        $isBlocked = (int)($_POST['is_blocked'] ?? 1);
        $reason = trim($_POST['reason'] ?? '');

        if (!$date || !$startTime) {
            echo json_encode(['success' => false, 'message' => 'Missing date or time']);
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM slot_overrides WHERE override_date = ? AND start_time = ?");
        $stmt->execute([$date, $startTime]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE slot_overrides SET is_blocked = ?, reason = ? WHERE id = ?");
            $stmt->execute([$isBlocked, $reason, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO slot_overrides (override_date, start_time, end_time, is_blocked, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$date, $startTime, $endTime, $isBlocked, $reason]);
        }

        if (function_exists('logAudit')) logAudit('SLOT_BLOCK', "Slot $startTime on $date " . ($isBlocked ? 'blocked' : 'unblocked'));
        echo json_encode(['success' => true, 'message' => 'Slot updated successfully']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);

} catch (PDOException $e) {
    error_log("DB Error in manage_slots.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in manage_slots.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}