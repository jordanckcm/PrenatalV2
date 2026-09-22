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
$room = trim($_POST['room'] ?? '');

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
        $stmt = $db->prepare("UPDATE appointments SET status = ?, healthcare_worker_id = ?, room = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $staffId, $room, $appointmentId]);
    } else {
        $stmt = $db->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $appointmentId]);
    }

    echo json_encode(['success' => true, 'message' => 'Appointment updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}