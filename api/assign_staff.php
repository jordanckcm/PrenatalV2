<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

// FIX: user_role, dili role
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$staffId = (int)($_POST['staff_id'] ?? 0);

if (!$appointmentId || !$staffId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, appointment_code, appointment_date, appointment_time FROM appointments WHERE id = ?");
    $stmt->execute([$appointmentId]);
    $apptRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$apptRow) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'healthcare_worker' AND status = 'active'");
    $stmt->execute([$staffId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Staff member not found']);
        exit;
    }


    // Busy staff cannot be assigned: another pending/confirmed booking at the same date + time
    $busy = $db->prepare("SELECT appointment_code FROM appointments WHERE healthcare_worker_id = ? AND appointment_date = (SELECT appointment_date FROM appointments WHERE id = ?) AND appointment_time = (SELECT appointment_time FROM appointments WHERE id = ?) AND status IN ('pending','confirmed') AND id != ? LIMIT 1");
    $busy->execute([$staffId, $appointmentId, $appointmentId, $appointmentId]);
    if ($busyCode = $busy->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'This staff member is busy at that time (already assigned to ' . $busyCode . '). Please choose a vacant staff member.']);
        exit;
    }

    $stmt = $db->prepare("UPDATE appointments SET healthcare_worker_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$staffId, $appointmentId]);

    createNotification($staffId, 'New Patient Assigned', "You were assigned appointment {$apptRow['appointment_code']} on " . date('M d, Y', strtotime($apptRow['appointment_date'])) . ' at ' . date('g:i A', strtotime($apptRow['appointment_time'])) . '.', 'appointment', 'worker/appointments.php');

    echo json_encode(['success' => true, 'message' => 'Staff assigned successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}