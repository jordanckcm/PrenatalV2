<?php
/**
 * Notifications (admin) — uses the shared notifications center.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Notifications";
$activePage = "notifications";
$notifSubtitle = "New booking requests, new accounts, and system activity.";

require __DIR__ . '/../includes/notifications_view.php';
