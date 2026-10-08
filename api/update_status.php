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
    $stmt = $db->prepare("SELECT a.id, a.status, a.appointment_code, a.appointment_date, a.appointment_time, a.healthcare_worker_id, p.user_id AS patient_user_id FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?");
    $stmt->execute([$appointmentId]);
    $apptRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$apptRow) {
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

    // Notify the patient (and the assigned staff member) when the status actually changed
    if ($apptRow['status'] !== $status) {
        $when = date('M d, Y', strtotime($apptRow['appointment_date'])) . ' at ' . date('g:i A', strtotime($apptRow['appointment_time']));
        $code = $apptRow['appointment_code'];
        $patientMsgs = [
            'confirmed' => ['Appointment Confirmed', "Your appointment ($code) on $when has been confirmed.", 'appointment'],
            'cancelled' => ['Appointment Cancelled', "Your appointment ($code) on $when was cancelled by the clinic.", 'cancellation'],
            'missed'    => ['Appointment Marked Missed', "Your appointment ($code) on $when was marked as missed. You can book a new visit anytime.", 'appointment'],
            'completed' => ['Appointment Completed', "Your appointment ($code) on $when is now marked completed.", 'appointment'],
            'pending'   => ['Appointment Pending', "Your appointment ($code) on $when is pending review.", 'appointment'],
        ];
        if (isset($patientMsgs[$status])) {
            createNotification($apptRow['patient_user_id'], $patientMsgs[$status][0], $patientMsgs[$status][1], $patientMsgs[$status][2], 'patient/my_appointments.php');
        }
        $assignedWorker = ($status === 'confirmed' && $staffId > 0) ? $staffId : (int)$apptRow['healthcare_worker_id'];
        if ($assignedWorker && in_array($status, ['confirmed', 'cancelled'], true)) {
            createNotification($assignedWorker, $status === 'confirmed' ? 'Appointment Assigned to You' : 'Assigned Appointment Cancelled', "Appointment $code on $when is now " . $status . '.', $status === 'cancelled' ? 'cancellation' : 'appointment', 'worker/appointments.php');
        }
    }

    echo json_encode(['success' => true, 'message' => 'Appointment updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}