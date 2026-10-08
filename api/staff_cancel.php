<?php
/**
 * API: Healthcare staff cancels an appointment (reason required).
 * The patient is notified and sees the reason on their dashboard.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'healthcare_worker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$workerId = (int)getCurrentUserId();

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}
if ($reason === '') {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for cancellation.']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, p.user_id AS patient_user_id FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?");
    $stmt->execute([$appointmentId]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appt) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    if (!in_array($appt['status'], ['pending', 'confirmed'], true)) {
        echo json_encode(['success' => false, 'message' => 'Only pending or confirmed appointments can be cancelled.']);
        exit;
    }
    if (!empty($appt['healthcare_worker_id']) && (int)$appt['healthcare_worker_id'] !== $workerId) {
        echo json_encode(['success' => false, 'message' => 'This appointment is handled by another staff member.']);
        exit;
    }

    $upd = $db->prepare("UPDATE appointments SET status = 'cancelled', cancel_reason = ?, cancelled_at = NOW(), cancelled_by = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$reason, $workerId, $appointmentId]);

    createNotification(
        $appt['patient_user_id'],
        'Appointment Cancelled',
        "Your appointment ({$appt['appointment_code']}) on " . date('M d, Y', strtotime($appt['appointment_date'])) . " at " . date('g:i A', strtotime($appt['appointment_time'])) . " was cancelled by the clinic staff.\n\nReason: " . $reason,
        'cancellation',
        'patient/my_appointments.php'
    );
    notifyAdmins('Appointment Cancelled', "{$appt['appointment_code']} was cancelled by " . getCurrentUserName() . ". Reason: " . $reason, 'cancellation', 'admin/appointments.php');

    logAudit('BOOKING_CANCELLED', "Staff ID {$workerId} cancelled appointment {$appt['appointment_code']}: {$reason}");
    echo json_encode(['success' => true, 'message' => 'Appointment cancelled. The patient has been notified.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
