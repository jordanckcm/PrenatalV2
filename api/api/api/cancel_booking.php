<?php
/**
 * Cancel Booking API (Admin)
 * Admin mo-cancel sa booking ug magbutang og reason
 */

// TEMPORARY DEBUG — i-remove pagkahuman mo-gana
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$reason = trim($_POST['reason'] ?? '');

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for cancellation']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT a.*, p.user_id as patient_user_id FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    if ($appointment['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Appointment is already cancelled']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE appointments 
        SET status = 'cancelled', 
            cancel_reason = ?, 
            cancelled_at = NOW(),
            cancelled_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$reason, $_SESSION['user_id'], $appointmentId]);

    $notificationTitle = "Appointment Cancelled";
    $notificationMsg = "Ang imong appointment (" . $appointment['appointment_code'] . ") gi-cancel sa admin.\n\nReason: " . $reason;

    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'cancellation')");
    $stmt->execute([$appointment['patient_user_id'], $notificationTitle, $notificationMsg]);

    logAudit('BOOKING_CANCELLED', "Admin cancelled appointment {$appointment['appointment_code']}: $reason");

    echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}