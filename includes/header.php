<?php
/**
 * Global Header
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

if (!isset($pageTitle)) $pageTitle = APP_NAME;
if (!isset($activePage)) $activePage = '';

$currentRole = getCurrentUserRole();
$currentName = getCurrentUserName();
$currentUserId = getCurrentUserId();
$userInitial = strtoupper(substr($currentName ?: 'U', 0, 1));

// Role configuration
$roleConfig = [
    'admin' => ['label' => 'ADMIN', 'color' => '#dc3545', 'icon' => 'fa-shield-halved'],
    'healthcare_worker' => ['label' => 'STAFF', 'color' => '#0d6efd', 'icon' => 'fa-user-doctor'],
    'patient' => ['label' => 'PATIENT', 'color' => '#198754', 'icon' => 'fa-user'],
];
$role = $roleConfig[$currentRole] ?? ['label' => 'GUEST', 'color' => '#6c757d', 'icon' => 'fa-user'];

// Dashboard URL
$dashboardUrl = BASE_URL . 'index.php';
if ($currentRole === 'admin') $dashboardUrl = BASE_URL . 'admin/dashboard.php';
elseif ($currentRole === 'healthcare_worker') $dashboardUrl = BASE_URL . 'worker/dashboard.php';
elseif ($currentRole === 'patient') $dashboardUrl = BASE_URL . 'patient/dashboard.php';

// Nav items base sa role
$navItems = [];
if ($currentRole === 'admin') {
    $navItems = [
        ['section' => 'System Core'],
        ['label' => 'Admin Dashboard', 'icon' => 'fa-gauge', 'url' => 'admin/dashboard.php', 'key' => 'dashboard'],
        ['label' => 'Booking Requests', 'icon' => 'fa-calendar-check', 'url' => 'admin/appointments.php', 'key' => 'appointments'],
        ['label' => 'Prenatal Records', 'icon' => 'fa-notes-medical', 'url' => 'admin/prenatal_records.php', 'key' => 'prenatal_records'],

        ['section' => 'User Management'],
        ['label' => 'Users & Staff', 'icon' => 'fa-users', 'url' => 'admin/users.php', 'key' => 'users'],
        ['label' => 'Archived Accounts', 'icon' => 'fa-box-archive', 'url' => 'admin/archived.php', 'key' => 'archived'],
        ['label' => 'Clinic Services', 'icon' => 'fa-stethoscope', 'url' => 'admin/services.php', 'key' => 'services'],
        ['label' => 'Schedules & Slots', 'icon' => 'fa-calendar-days', 'url' => 'admin/schedules.php', 'key' => 'schedules'],

        ['section' => 'System Logs'],
        ['label' => 'Reports & Stats', 'icon' => 'fa-chart-line', 'url' => 'admin/reports.php', 'key' => 'reports'],
        ['label' => 'Audit Logs', 'icon' => 'fa-shield-halved', 'url' => 'admin/audit_logs.php', 'key' => 'audit'],
        ['label' => 'Account Settings', 'icon' => 'fa-gear', 'url' => 'admin/settings.php', 'key' => 'settings'],
        ['label' => 'System Settings', 'icon' => 'fa-sliders', 'url' => 'admin/system_settings.php', 'key' => 'system'],
    ];
} elseif ($currentRole === 'healthcare_worker') {
    $navItems = [
        ['section' => 'Clinical Dashboard'],
        ['label' => 'Worker Dashboard', 'icon' => 'fa-gauge', 'url' => 'worker/dashboard.php', 'key' => 'dashboard'],

        ['section' => 'Patient Management'],
        ['label' => 'Patients Directory', 'icon' => 'fa-users', 'url' => 'worker/patients.php', 'key' => 'patients'],
        ['label' => 'Appointments', 'icon' => 'fa-calendar-check', 'url' => 'worker/appointments.php', 'key' => 'appointments'],
        ['label' => 'Prenatal Records', 'icon' => 'fa-notes-medical', 'url' => 'worker/prenatal_records.php', 'key' => 'records'],
        ['label' => 'Follow-Up Visits', 'icon' => 'fa-clock-rotate-left', 'url' => 'worker/followups.php', 'key' => 'followups'],
        ['label' => 'Calendar', 'icon' => 'fa-calendar-days', 'url' => 'worker/calendar.php', 'key' => 'calendar'],
        ['label' => 'Schedules & Slots', 'icon' => 'fa-calendar-week', 'url' => 'worker/schedules.php', 'key' => 'schedules'],
        ['label' => 'Account Settings', 'icon' => 'fa-gear', 'url' => 'worker/settings.php', 'key' => 'settings'],
    ];
} elseif ($currentRole === 'patient') {
    $navItems = [
        ['section' => 'Main Navigation'],
        ['label' => 'Dashboard', 'icon' => 'fa-gauge', 'url' => 'patient/dashboard.php', 'key' => 'dashboard'],
        ['label' => 'Book Visit', 'icon' => 'fa-calendar-plus', 'url' => 'patient/book_appointment.php', 'key' => 'book', 'badge' => 'NEW'],

        ['section' => 'Patient Care'],
        ['label' => 'My Appointments', 'icon' => 'fa-calendar-check', 'url' => 'patient/my_appointments.php', 'key' => 'appointments'],
        ['label' => 'Prenatal Records', 'icon' => 'fa-notes-medical', 'url' => 'patient/records.php', 'key' => 'records'],
        ['label' => 'Notifications', 'icon' => 'fa-bell', 'url' => 'patient/notifications.php', 'key' => 'notifications'],

        ['section' => 'Account'],
        ['label' => 'Account Settings', 'icon' => 'fa-user-gear', 'url' => 'patient/settings.php', 'key' => 'settings'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?> — <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Apply theme BEFORE page renders (prevents flash)
        (function () {
            try {
                var saved = localStorage.getItem('theme');
                if (saved === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (saved === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body>

<!-- SIDEBAR OVERLAY (Mobile) -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <?php $logoUrl = getSystemLogoUrl(); if ($logoUrl): ?>
                <img src="<?php echo $logoUrl; ?>" alt="Logo">
            <?php else: ?>
                <i class="fa-solid fa-baby-carriage"></i>
            <?php endif; ?>
        </div>
        <div class="brand-text">
            <strong>MaternalCare</strong>
            <small>Prenatal Health System</small>
        </div>
    </div>

    <!-- ROLE INDICATOR -->
    <div class="sidebar-role-indicator" style="
        margin: 0.5rem 1rem 1rem;
        padding: 0.6rem 0.85rem;
        background: <?php echo $role['color']; ?>15;
        border-left: 3px solid <?php echo $role['color']; ?>;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    ">
        <i class="fa-solid <?php echo $role['icon']; ?>" style="color: <?php echo $role['color']; ?>; font-size: 1.1rem;"></i>
        <div style="line-height: 1.2;">
            <small style="color: var(--text-muted); font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.5px; display: block;">Logged in as</small>
            <strong style="color: <?php echo $role['color']; ?>; font-size: 0.82rem;"><?php echo $role['label']; ?></strong>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="nav-section"><?php echo $item['section']; ?></div>
            <?php else: ?>
                <a href="<?php echo BASE_URL . $item['url']; ?>"
                   class="nav-link <?php echo ($activePage === $item['key']) ? 'active' : ''; ?>">
                    <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['label']; ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="nav-badge"><?php echo $item['badge']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline btn-block">
            <i class="fa-solid fa-right-from-bracket"></i> Sign Out
        </a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-left">
            <!-- HAMBURGER MENU -->
            <button type="button" class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Toggle Menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <nav class="breadcrumb">
                <a href="<?php echo $dashboardUrl; ?>">
                    <i class="fa-solid fa-house"></i> MaternalCare
                </a>
                <span class="sep">/</span>
                <span class="current"><?php echo sanitize($pageTitle); ?></span>
            </nav>
        </div>

        <div class="topbar-actions">
            <!-- ROLE BADGE -->
            <span class="role-badge" style="
                background: <?php echo $role['color']; ?>;
                color: #fff;
                padding: 0.35rem 0.75rem;
                border-radius: 20px;
                font-size: 0.68rem;
                font-weight: 800;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                white-space: nowrap;
            ">
                <i class="fa-solid <?php echo $role['icon']; ?>"></i>
                <?php echo $role['label']; ?>
            </span>

            <!-- THEME TOGGLE -->
            <button type="button" class="btn btn-outline btn-icon theme-toggle-btn" aria-label="Switch Theme">
                <i class="theme-toggle-icon fa-solid fa-moon"></i>
            </button>

            <!-- USER INFO -->
            <div class="topbar-user">
                <span class="topbar-user-name"><?php echo sanitize($currentName); ?></span>
                <div class="topbar-avatar" style="
                    width: 32px; height: 32px; border-radius: 50%;
                    background: <?php echo $role['color']; ?>; color: #fff;
                    display: flex; align-items: center; justify-content: center;
                    font-weight: 800; font-size: 0.85rem;
                ">
                    <?php echo $userInitial; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- PAGE CONTENT -->
    <div class="page-content">