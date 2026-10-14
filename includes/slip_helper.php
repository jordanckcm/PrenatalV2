<?php
/**
 * Appointment slip helper (function only, no output).
 *
 * Must be require_once'd at the TOP of any page that calls slipButtonAttr().
 * PHP does not hoist functions out of a file that is included later, so
 * defining it only inside includes/appointment_slip.php (included at the
 * bottom of the page) caused "Call to undefined function slipButtonAttr()".
 */
if (!function_exists('slipButtonAttr')) {
    /**
     * Returns a safe  data-appt="..."  attribute for a slip button.
     * Only the fields the slip needs are sent to the browser.
     */
    function slipButtonAttr(array $appt, string $patientName, string $staffLabel): string
    {
        $payload = [
            'code'         => (string)($appt['appointment_code'] ?? ''),
            'status'       => (string)($appt['status'] ?? ''),
            'service'      => (string)($appt['service_name'] ?? ''),
            'date'         => (string)($appt['appointment_date'] ?? ''),
            'time'         => (string)($appt['appointment_time'] ?? ''),
            'patient_name' => $patientName,
            'staff_label'  => $staffLabel,
        ];
        $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        return 'data-appt="' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '"';
    }
}
