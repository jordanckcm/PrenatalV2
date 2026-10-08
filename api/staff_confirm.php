<?php
/**
 * API: Healthcare staff confirms a pending appointment.
 * The confirming staff member is automatically assigned as the examiner,
 * so the admin no longer has to assign anyone.
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
$workerId = (int)getCurrentUserId();

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT a.id, a.status, a.appointment_code, a.appointment_date, a.appointment_time, p.user_id AS patient_user_id FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?");
    $stmt->execute([$appointmentId]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appt) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    if ($appt['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending appointments can be confirmed.']);
        exit;
    }

    // Staff cannot take two bookings at the same date + time
    $busy = $db->prepare("SELECT appointment_code FROM appointments WHERE healthcare_worker_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending','confirmed') AND id != ? LIMIT 1");
    $busy->execute([$workerId, $appt['appointment_date'], $appt['appointment_time'], $appointmentId]);
    if ($busyCode = $busy->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'You already have another booking at that time (' . $busyCode . ').']);
        exit;
    }

    // Confirm + self-assign. worker_notified = 1 so the "new assignment" popup does not fire for self-confirmed bookings.
    $upd = $db->prepare("UPDATE appointments SET status = 'confirmed', healthcare_worker_id = ?, worker_notified = 1, worker_confirmed_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'");
    $upd->execute([$workerId, $appointmentId]);

    if ($upd->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'This appointment was just handled by someone else.']);
        exit;
    }

    createNotification(
        $appt['patient_user_id'],
        'Appointment Confirmed',
        "Your appointment ({$appt['appointment_code']}) on " . date('M d, Y', strtotime($appt['appointment_date'])) . " at " . date('g:i A', strtotime($appt['appointment_time'])) . " has been confirmed by the clinic staff.",
        'appointment',
        'patient/my_appointments.php'
    );
    notifyAdmins('Appointment Confirmed', "{$appt['appointment_code']} was confirmed by " . getCurrentUserName() . ".", 'appointment', 'admin/appointments.php');

    logAudit("STAFF_CONFIRMED_APPOINTMENT", "Staff ID {$workerId} confirmed Appointment {$appt['appointment_code']} and took it for examination");
    echo json_encode(['success' => true, 'message' => 'Appointment confirmed. You can now examine the patient.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
