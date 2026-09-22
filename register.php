<?php
/**
 * Patient Registration Redirect Route
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

header("Location: index.php?auth=register");
exit;
