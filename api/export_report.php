<?php
/**
 * Report Exporter Handler (CSV Export)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'healthcare_worker']);

$type = $_GET['type'] ?? 'appointments';
$format = $_GET['format'] ?? 'csv';

try {
    $db = getDB();

    if ($type === 'appointments') {
        $filename = "Prenatal_Appointments_Report_" . date('Ymd') . ".csv";

        $stmt = $db->query("SELECT a.appointment_code, p.patient_code, u.full_name as patient_name, s.service_name, a.appointment_date, a.appointment_time, a.status, a.created_at FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id JOIN services s ON a.service_id = s.id ORDER BY a.appointment_date DESC");
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Appointment Code', 'Patient Code', 'Patient Name', 'Service Name', 'Date', 'Time', 'Status', 'Booked At']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;

    } elseif ($type === 'records') {
        $filename = "Prenatal_Records_Report_" . date('Ymd') . ".csv";

        $stmt = $db->query("SELECT pr.id, p.patient_code, u.full_name as patient_name, pr.visit_date, pr.gestational_age_weeks, pr.weight_kg, CONCAT(pr.systolic_bp, '/', pr.diastolic_bp) as bp, pr.fundal_height_cm, pr.fetal_heart_rate, pr.risk_assessment, pr.next_visit_date FROM prenatal_records pr JOIN patients p ON pr.patient_id = p.id JOIN users u ON p.user_id = u.id ORDER BY pr.visit_date DESC");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Record ID', 'Patient Code', 'Patient Name', 'Visit Date', 'Gestational Age (Weeks)', 'Weight (kg)', 'BP (mmHg)', 'Fundal Height (cm)', 'Fetal Heart Rate (bpm)', 'Risk Assessment', 'Next Visit Date']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
} catch (Exception $e) {
    die("Export Error: " . $e->getMessage());
}
