<?php
/**
 * User Login Redirect Route
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === 'admin') { header("Location: admin/dashboard.php"); }
    elseif ($role === 'healthcare_worker') { header("Location: worker/dashboard.php"); }
    else { header("Location: patient/dashboard.php"); }
    exit;
}

header("Location: index.php?auth=login");
exit;
