<?php
/**
 * Patient Profile Redirect Route to Consolidated Account Settings Page
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('patient');

header("Location: " . BASE_URL . "account_settings.php");
exit;
