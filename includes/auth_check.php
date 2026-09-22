<?php
/**
 * Authentication & Role Authorization Guard Middleware
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "login.php?error=" . urlencode("Please log in to access this page."));
        exit;
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $currentRole = getCurrentUserRole();
    if (!in_array($currentRole, $allowedRoles)) {
        // Redirect to appropriate dashboard according to current role
        if ($currentRole === 'patient') {
            header("Location: " . BASE_URL . "patient/dashboard.php");
        } elseif ($currentRole === 'healthcare_worker') {
            header("Location: " . BASE_URL . "worker/dashboard.php");
        } elseif ($currentRole === 'admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "login.php");
        }
        exit;
    }
}
