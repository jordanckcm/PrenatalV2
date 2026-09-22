<?php
/**
 * API Endpoint: Live Clinic Load (public, counts only - no patient data)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 *
 * GET api/clinic_load.php
 *      ?date=YYYY-MM-DD     (optional, default today in clinic time)
 *      &service_id=N&strip=14   (optional: also return N days of availability for one service)
 *
 * Used by: login page, register page and the patient booking wizard.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/slot_helper.php';

try {
    $db = getDB();
    ensureSlotOverridesTable($db);

    $date = normalizeYmd($_GET['date'] ?? date('Y-m-d'));
    $load = getClinicLoadForDate($db, $date);

    $response = ['success' => true] + $load + [
        'server_time' => date('g:i:s A'),
        'server_ts'   => time(),
        'timezone'    => APP_TIMEZONE
    ];

    $serviceId = (int)($_GET['service_id'] ?? 0);
    $stripDays = (int)($_GET['strip'] ?? 0);
    if ($serviceId > 0 && $stripDays > 0) {
        $response['strip'] = getClinicDayStrip($db, $serviceId, $date, $stripDays);
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not read the clinic schedule right now.']);
}
