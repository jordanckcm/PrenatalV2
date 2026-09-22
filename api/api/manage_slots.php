<?php
/**
 * Manage Slots API
 * Handles schedule updates, slot overrides, and blocked slots
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slot_helper.php';

header('Content-Type: application/json');

// Check admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Verify CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $db = getDB();
    ensureSlotOverridesTable($db);

    // ============================================
    // ACTION: Update Day Schedule
    // ============================================
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

        // I-check kung naa nay existing schedule
        $stmt = $db->prepare("SELECT id FROM schedules WHERE day_of_week = ?");
        $stmt->execute([$day]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $stmt = $db->prepare("
                UPDATE schedules 
                SET start_time = ?, end_time = ?, max_patients_per_slot = ?, is_active = ?
                WHERE day_of_week = ?
            ");
            $stmt->execute([$start, $end, $capacity, $isActive, $day]);
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO schedules (day_of_week, start_time, end_time, max_patients_per_slot, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$day, $start, $end, $capacity, $isActive]);
        }

        logAudit('SCHEDULE_UPDATE', "Updated schedule for $day");

        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        exit;
    }

    // ============================================
    // ACTION: Block/Unblock Slot
    // ============================================
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

        $stmt = $db->prepare("
            INSERT INTO slot_overrides (override_date, start_time, end_time, is_blocked, reason)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_blocked = ?, reason = ?
        ");
        $stmt->execute([$date, $startTime, $endTime, $isBlocked, $reason, $isBlocked, $reason]);

        logAudit('SLOT_BLOCK', "Slot $startTime on $date " . ($isBlocked ? 'blocked' : 'unblocked'));

        echo json_encode(['success' => true, 'message' => 'Slot updated successfully']);
        exit;
    }

    // ============================================
    // ACTION: Delete Slot Override
    // ============================================
    if ($action === 'delete_override') {
        $id = (int)($_POST['override_id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing override ID']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM slot_overrides WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Override removed']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}