<?php
/**
 * Patient Notifications Center
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('patient');

$pageTitle = "Notifications";
$activePage = "notifications";
$userId = getCurrentUserId();

$successMsg = '';
$errorMsg = '';
$db = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $errorMsg = "Security token validation failed. Please try again.";
    } elseif (isset($_POST['action_mark_all_read'])) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $successMsg = "All notifications marked as read.";
        logAudit("NOTIFICATIONS_MARK_ALL_READ", "Marked all notifications read for user ID {$userId}");
    } elseif (isset($_POST['action_delete_all'])) {
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        $successMsg = "All notifications cleared successfully.";
        logAudit("NOTIFICATIONS_CLEAR_ALL", "Cleared all notifications for user ID {$userId}");
    } elseif (isset($_POST['action_delete_single'])) {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notifId, $userId]);
            $successMsg = "Notification removed.";
            logAudit("NOTIFICATION_DELETE", "Deleted notification ID {$notifId}");
        }
    }
}

// Fetch Notifications
try {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

    $unreadCount = 0;
    foreach ($notifications as $n) {
        if (empty($n['is_read'])) {
            $unreadCount++;
        }
    }
} catch (Exception $e) {
    die("Notifications error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
    <div class="page-title">
        <h1 style="font-size: 2rem; font-weight: 800; font-family: 'Bricolage Grotesque', sans-serif;">Notifications</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.2rem;">Appointment updates, reminders, and system alerts.</p>
    </div>

    <?php if (count($notifications) > 0): ?>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" name="action_mark_all_read" value="1">
                    <button type="submit" class="btn btn-outline btn-sm" style="border-radius: 999px; font-weight: 700;">
                        <i class="fa-solid fa-check-double" style="color: var(--primary);"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>

            <form method="POST" action="" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete all notifications?');">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action_delete_all" value="1">
                <button type="submit" class="btn btn-outline btn-sm" style="border-radius: 999px; color: var(--text-muted); font-weight: 600;">
                    <i class="fa-solid fa-trash-can"></i> Clear All
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger mb-4"><i class="fa-solid fa-circle-exclamation"></i><div><?php echo sanitize($errorMsg); ?></div></div>
<?php endif; ?>
<?php if ($successMsg): ?>
    <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check"></i><div><?php echo sanitize($successMsg); ?></div></div>
<?php endif; ?>

<div class="dashboard-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem;">
    <?php if (count($notifications) > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($notifications as $notif): ?>
                <?php
                    $isUnread = empty($notif['is_read']);
                    $iconMap = [
                        'appointment' => 'fa-calendar-check',
                        'reminder'    => 'fa-bell',
                        'followup'    => 'fa-clock-rotate-left',
                        'system'      => 'fa-circle-info'
                    ];
                    $icon = $iconMap[$notif['type']] ?? 'fa-bell';
                ?>
                <div style="background: <?php echo $isUnread ? 'rgba(249, 115, 22, 0.04)' : 'var(--bg-body)'; ?>; padding: 1.25rem 1.5rem; border-radius: var(--radius-sm); border: 1px solid <?php echo $isUnread ? 'rgba(249, 115, 22, 0.3)' : 'var(--border-color)'; ?>; border-left: 4px solid <?php echo $isUnread ? 'var(--primary)' : 'var(--border-color)'; ?>; display: flex; gap: 1.25rem; align-items: flex-start; justify-content: space-between; transition: var(--transition);">
                    
                    <div style="display: flex; gap: 1.25rem; align-items: flex-start; flex: 1;">
                        <div style="width: 42px; height: 42px; border-radius: 50%; background: <?php echo $isUnread ? 'rgba(249, 115, 22, 0.15)' : 'var(--surface)'; ?>; color: <?php echo $isUnread ? 'var(--primary)' : 'var(--text-muted)'; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid <?php echo $icon; ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.3rem;">
                                <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0;"><?php echo sanitize($notif['title']); ?></h4>
                                <?php if ($isUnread): ?>
                                    <span style="background: var(--primary); color: #FFF; font-size: 0.65rem; font-weight: 800; padding: 0.1rem 0.5rem; border-radius: 999px; text-transform: uppercase;">New</span>
                                <?php endif; ?>
                            </div>
                            <p style="color: var(--text-main); font-size: 0.92rem; margin: 0 0 0.5rem 0; line-height: 1.5;"><?php echo sanitize($notif['message']); ?></p>
                            <small style="color: var(--text-muted); font-size: 0.78rem; display: flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-regular fa-clock"></i> <?php echo formatDate($notif['created_at'], 'M d, Y g:i A'); ?>
                            </small>
                        </div>
                    </div>

                    <div>
                        <form method="POST" action="" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            <input type="hidden" name="action_delete_single" value="1">
                            <input type="hidden" name="notification_id" value="<?php echo (int)$notif['id']; ?>">
                            <button type="submit" class="btn-icon" style="color: var(--text-muted); width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--border-color); background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Delete notification">
                                <i class="fa-solid fa-trash-can" style="font-size: 0.85rem;"></i>
                            </button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center p-5" style="color: var(--text-muted);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: var(--bg-body); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2rem;">
                <i class="fa-regular fa-bell-slash"></i>
            </div>
            <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.35rem;">No Notifications</h4>
            <p style="font-size: 0.9rem; margin: 0;">You're all caught up! There are no notifications to display at this time.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
