<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

// FIX: Gamita ang 'user_role' (dili 'role')
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';
$appointmentId = (int)($_GET['appointment_id'] ?? 0);

if (!$date || !$time) {
    echo json_encode(['success' => false, 'message' => 'Missing date or time']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id AS staff_id, full_name FROM users WHERE role = 'healthcare_worker' AND status = 'active' ORDER BY full_name ASC");
    $stmt->execute();
    $staffList = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT healthcare_worker_id FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status IN ('pending','confirmed') AND healthcare_worker_id IS NOT NULL AND id != ?");
    $stmt->execute([$date, $time, $appointmentId]);
    $busyStaff = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $roster = [];
    foreach ($staffList as $s) {
        $isVacant = !in_array($s['staff_id'], $busyStaff);
        $roster[] = [
            'staff_id' => (int)$s['staff_id'],
            'full_name' => $s['full_name'],
            'is_vacant' => $isVacant,
            'is_on_duty' => true,
            'status_text' => $isVacant ? 'Available' : 'Busy'
        ];
    }

    echo json_encode(['success' => true, 'staff_roster' => $roster]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}