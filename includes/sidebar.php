<?php
/**
 * Dynamic Sidebar Navigation Component per Role
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

$currentRole = getCurrentUserRole();
$activePage = $activePage ?? '';
?>
<?php
$systemLogoUrl = getSystemLogoUrl();
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand-title">
            <div style="width:34px; height:34px; border-radius:10px; background:rgba(249, 115, 22, 0.15); border:1px solid rgba(249, 115, 22, 0.3); color:#F97316; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden;">
                <?php if ($systemLogoUrl): ?>
                    <img src="<?php echo $systemLogoUrl; ?>" alt="Logo" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="fa-solid fa-baby-carriage"></i>
                <?php endif; ?>
            </div>
            <div>
                <div>MaternalCare</div>
                <div class="sidebar-brand-subtitle">Prenatal Health System</div>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <?php if ($currentRole === 'patient'): ?>
            <li class="sidebar-subhead">Main Nav</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>patient/dashboard.php" class="sidebar-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>patient/book_appointment.php" class="sidebar-link <?php echo $activePage === 'book' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>Book Visit</span>
                    <span class="sidebar-badge-new">New</span>
                </a>
            </li>

            <?php
            $unreadPatientNotifCount = 0;
            try {
                $pNotifDb = getDB();
                $pUserId = getCurrentUserId();
                $pNotifStmt = $pNotifDb->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $pNotifStmt->execute([$pUserId]);
                $unreadPatientNotifCount = (int)$pNotifStmt->fetchColumn();
            } catch (Exception $e) {}
            ?>
            <li class="sidebar-subhead">Patient Care</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>patient/my_appointments.php" class="sidebar-link <?php echo $activePage === 'appointments' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>My Appointments</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>patient/my_records.php" class="sidebar-link <?php echo $activePage === 'records' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-notes-medical"></i>
                    <span>Prenatal Records</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>patient/notifications.php" class="sidebar-link <?php echo $activePage === 'notifications' ? 'active' : ''; ?>" style="position:relative;">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($unreadPatientNotifCount > 0): ?>
                        <span style="background:var(--primary,#F97316);color:#fff;font-size:.65rem;font-weight:800;border-radius:999px;padding:.15rem .45rem;min-width:1.3rem;text-align:center;margin-left:auto;line-height:1.4;">
                            <?php echo $unreadPatientNotifCount; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-subhead">Account</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>account_settings.php" class="sidebar-link <?php echo $activePage === 'account_settings' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Account Settings</span>
                </a>
            </li>

        <?php elseif ($currentRole === 'healthcare_worker'): ?>
            <?php
            $workerAssignedPendingCount = 0;
            try {
                $wDb = getDB();
                $wUserId = getCurrentUserId();
                $wStmt = $wDb->prepare("SELECT COUNT(*) FROM appointments WHERE healthcare_worker_id = ? AND status = 'confirmed' AND worker_notified = 0");
                $wStmt->execute([$wUserId]);
                $workerAssignedPendingCount = (int)$wStmt->fetchColumn();
            } catch (Exception $e) {
                $workerAssignedPendingCount = 0;
            }
            ?>
            <li class="sidebar-subhead">Clinical Dashboard</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>worker/dashboard.php" class="sidebar-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-stethoscope"></i>
                    <span>Worker Dashboard</span>
                </a>
            </li>

            <li class="sidebar-subhead">Patient Management</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>worker/patients.php" class="sidebar-link <?php echo $activePage === 'patients' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Patients Directory</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>worker/appointments.php" class="sidebar-link <?php echo $activePage === 'appointments' ? 'active' : ''; ?>" style="position:relative;">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Appointments</span>
                    <span id="worker-assigned-sidebar-badge" class="worker-assigned-badge" style="<?php echo $workerAssignedPendingCount > 0 ? 'display:inline-flex;' : 'display:none;'; ?>" title="<?php echo $workerAssignedPendingCount; ?> new patient assigned!">
                        <i class="fa-solid fa-bell" style="font-size:0.65rem;width:auto;"></i>
                        <span id="worker-assigned-badge-count"><?php echo $workerAssignedPendingCount; ?></span>
                    </span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>worker/prenatal_records.php" class="sidebar-link <?php echo $activePage === 'records' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-medical"></i>
                    <span>Prenatal Records</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>worker/follow_up.php" class="sidebar-link <?php echo $activePage === 'followup' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Follow-Up Visits</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>worker/calendar.php" class="sidebar-link <?php echo $activePage === 'calendar' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>worker/schedules.php" class="sidebar-link <?php echo $activePage === 'schedules' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-week"></i>
                    <span>Schedules & Slots</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>account_settings.php" class="sidebar-link <?php echo $activePage === 'account_settings' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Account Settings</span>
                </a>
            </li>

        <?php elseif ($currentRole === 'admin'): ?>
            <?php
            $pendingSidebarCount = 0;
            try { $dbSb = getDB(); $pendingSidebarCount = (int)$dbSb->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn(); } catch (Exception $e) {}
            ?>
            <li class="sidebar-subhead">System Core</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="sidebar-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Admin Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="sidebar-link <?php echo $activePage === 'appointments' ? 'active' : ''; ?>" style="position:relative;">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Booking Requests</span>
                    <?php if ($pendingSidebarCount > 0): ?>
                        <span style="background:var(--primary,#F97316);color:#fff;font-size:.65rem;font-weight:800;border-radius:999px;padding:.15rem .45rem;min-width:1.3rem;text-align:center;margin-left:auto;line-height:1.4;">
                            <?php echo $pendingSidebarCount; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-subhead">User Management</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/users.php" class="sidebar-link <?php echo $activePage === 'users' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Users & Staff</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/archived_users.php" class="sidebar-link <?php echo $activePage === 'archived_users' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-box-archive"></i>
                    <span>Archived Accounts</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/services.php" class="sidebar-link <?php echo $activePage === 'services' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-hand-holding-medical"></i>
                    <span>Clinic Services</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/schedules.php" class="sidebar-link <?php echo $activePage === 'schedules' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-week"></i>
                    <span>Schedules & Slots</span>
                </a>
            </li>

            <li class="sidebar-subhead">System Logs</li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/reports.php" class="sidebar-link <?php echo $activePage === 'reports' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Reports & Stats</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/audit_logs.php" class="sidebar-link <?php echo $activePage === 'audit' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-shield-cat"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>account_settings.php" class="sidebar-link <?php echo $activePage === 'account_settings' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Account Settings</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/settings.php" class="sidebar-link <?php echo $activePage === 'settings' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-sliders"></i>
                    <span>System Settings</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline btn-block btn-sm" style="color: #A1A1AA; border-color: #27272A; background: rgba(255, 255, 255, 0.02);">
            <i class="fa-solid fa-right-from-bracket"></i> Sign Out
        </a>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const switchEl = document.getElementById('sidebarThemeSwitch');
    if (switchEl) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        switchEl.checked = isDark;
        switchEl.addEventListener('change', function() {
            const newTheme = this.checked ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            const topbarIcon = document.getElementById('themeToggleIcon');
            if (topbarIcon) {
                topbarIcon.className = newTheme === 'dark' ? 'theme-toggle-icon fa-solid fa-sun' : 'theme-toggle-icon fa-solid fa-moon';
            }
        });
    }
});
</script>


