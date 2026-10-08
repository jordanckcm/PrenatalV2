<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

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
$status = $_POST['status'] ?? '';
$staffId = (int)($_POST['staff_id'] ?? 0);

if (!$appointmentId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$allowedStatuses = ['pending', 'confirmed', 'completed', 'missed', 'cancelled'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM appointments WHERE id = ?");
    $stmt->execute([$appointmentId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    if ($status === 'confirmed' && $staffId > 0) {
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
        $stmt = $db->prepare("UPDATE appointments SET status = ?, healthcare_worker_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $staffId, $appointmentId]);
    } else {
        $stmt = $db->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $appointmentId]);
    }

    echo json_encode(['success' => true, 'message' => 'Appointment updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}