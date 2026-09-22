<?php
/**
 * Healthcare Worker Notifications
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Worker Notifications";
$activePage = "notifications";
$workerId = getCurrentUserId();

try {
    $db = getDB();
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$workerId]);

    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$workerId]);
    $notifications = $stmt->fetchAll();

} catch (Exception $e) {
    die("Notifications error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Notifications & Staff Alerts</h1>
        <p>System alerts and appointment notifications</p>
    </div>
</div>

<div class="dashboard-card" style="padding: 1.5rem;">
    <?php if (count($notifications) > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($notifications as $notif): ?>
                <div style="background: var(--bg-body); padding: 1.25rem; border-radius: var(--radius-sm); border-left: 4px solid var(--primary); display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="font-size: 1.5rem; color: var(--primary);"><i class="fa-solid fa-bell"></i></div>
                    <div style="flex: 1;">
                        <h4 style="font-size: 1.05rem; margin-bottom: 0.25rem;"><?php echo sanitize($notif['title']); ?></h4>
                        <p class="text-muted" style="font-size: 0.9rem;"><?php echo sanitize($notif['message']); ?></p>
                        <small class="text-muted" style="font-size: 0.75rem;"><i class="fa-regular fa-clock"></i> <?php echo formatDate($notif['created_at'], 'M d, Y g:i A'); ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center p-4 text-muted">
            <i class="fa-regular fa-bell-slash fa-3x mb-2"></i>
            <p>No notifications available.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
