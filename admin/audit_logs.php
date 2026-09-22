<?php
/**
 * System Audit Trail & Security Logs Viewer (Administrator)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "System Audit Logs";
$activePage = "audit";

try {
    $db = getDB();

    $stmt = $db->query("SELECT a.*, u.username, u.full_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 100");
    $logs = $stmt->fetchAll();

} catch (Exception $e) {
    die("Audit log error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>System Audit Trail & Security Logs</h1>
        <p>Monitor system operations, login events, record additions, and administrative changes</p>
    </div>
</div>

<div class="dashboard-card" style="padding: 1.25rem;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small class="text-muted"><?php echo formatDate($log['created_at'], 'M d, Y H:i:s'); ?></small></td>
                            <td><strong><?php echo sanitize($log['username'] ?? 'System Guest'); ?></strong></td>
                            <td><span class="badge badge-confirmed"><?php echo sanitize($log['user_role']); ?></span></td>
                            <td><code><?php echo sanitize($log['action']); ?></code></td>
                            <td><small><?php echo sanitize($log['details']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center p-4 text-muted">No audit logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
