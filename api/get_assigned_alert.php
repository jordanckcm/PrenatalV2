<?php
/**
 * API Endpoint: Assigned Patient Examination Alert Checker for Healthcare Staff
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

if (getCurrentUserRole() !== 'healthcare_worker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

$db = getDB();
$workerId = getCurrentUserId();

// worker_notified / worker_confirmed_at columns are created once by ensureSchemaUpgrades() in config.php
// (no ALTER TABLE here: this endpoint is polled every few seconds).

// ACTION: Staff confirms duty and acceptance to examine patient
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    if ($appointmentId > 0) {
        $stmt = $db->prepare("UPDATE appointments SET worker_notified = 1, worker_confirmed_at = NOW() WHERE id = ? AND healthcare_worker_id = ?");
        $stmt->execute([$appointmentId, $workerId]);
        logAudit("STAFF_CONFIRMED_ASSIGNMENT", "Healthcare worker ID {$workerId} confirmed duty and examination for Appointment ID {$appointmentId}");
        echo json_encode(['success' => true, 'message' => 'Duty and examination confirmed successfully!']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

// GET: Check if there is an unacknowledged confirmed appointment assigned to this worker
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE healthcare_worker_id = ? AND status = 'confirmed' AND worker_notified = 0");
    $countStmt->execute([$workerId]);
    $totalPending = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT a.id, a.appointment_code, a.appointment_date, a.appointment_time, a.notes,
                                 s.service_name,
                                 u.full_name as patient_name, u.phone as patient_phone,
                                 p.patient_code, p.id as patient_id
                          FROM appointments a
                          JOIN patients p ON a.patient_id = p.id
                          JOIN users u ON p.user_id = u.id
                          JOIN services s ON a.service_id = s.id
                          WHERE a.healthcare_worker_id = ? AND a.status = 'confirmed' AND a.worker_notified = 0
                          ORDER BY a.updated_at DESC, a.id DESC LIMIT 1");
    $stmt->execute([$workerId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment) {
        // Format dates nicely
        $appointment['formatted_date'] = date('M d, Y', strtotime($appointment['appointment_date']));
        $appointment['formatted_time'] = date('g:i A', strtotime($appointment['appointment_time']));
        $appointment['is_today'] = ($appointment['appointment_date'] === date('Y-m-d'));

        echo json_encode([
            'success' => true,
            'has_alert' => true,
            'count' => $totalPending,
            'appointment' => $appointment
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_alert' => false,
            'count' => 0
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
