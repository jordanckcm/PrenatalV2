<?php
/**
 * Archived Accounts (Administrator)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Archived Accounts";
$activePage = "archived";
$userId = getCurrentUserId();

$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';

// Handle Restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_user') {
    $csrf = $_POST['csrf_token'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error. Please refresh and try again.";
    } elseif (!$targetId) {
        $errorMsg = "Invalid user ID.";
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'archived'");
            $stmt->execute([$targetId]);
            
            logAudit('USER_RESTORED', "Admin restored user ID $targetId");
            header("Location: archived.php?success=" . urlencode("Account restored successfully!"));
            exit;
        } catch (Exception $e) {
            $errorMsg = "Error restoring account: " . $e->getMessage();
        }
    }
}

// Handle Permanent Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $csrf = $_POST['csrf_token'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error. Please refresh and try again.";
    } elseif (!$targetId) {
        $errorMsg = "Invalid user ID.";
    } elseif ($targetId === $userId) {
        $errorMsg = "You cannot delete your own account.";
    } else {
        try {
            $db = getDB();
            $db->beginTransaction();
            
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND status = 'archived'");
            $stmt->execute([$targetId]);
            
            $db->commit();
            
            logAudit('USER_DELETED', "Admin permanently deleted user ID $targetId");
            header("Location: archived.php?success=" . urlencode("Account permanently deleted!"));
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errorMsg = "Error deleting account: " . $e->getMessage();
        }
    }
}

// Kuhaon ang archived users
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM users 
        WHERE status = 'archived'
        ORDER BY full_name ASC
    ");
    $stmt->execute();
    $archivedUsers = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error loading archived accounts: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Archived Accounts</h1>
        <p>View, restore, or permanently delete archived user accounts</p>
    </div>
    <div>
        <a href="users.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to Users & Staff
        </a>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success mb-4">
        <i class="fa-solid fa-circle-check"></i> <?php echo sanitize($successMsg); ?>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger mb-4">
        <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($errorMsg); ?>
    </div>
<?php endif; ?>

<div class="alert alert-info mb-4" style="font-size:0.85rem;">
    <i class="fa-solid fa-info-circle"></i>
    <div>
        <strong>Note:</strong> Archived accounts are no longer able to login. You can restore or delete permanently.
    </div>
</div>

<!-- Archived Users Table -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Archived Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($archivedUsers) > 0): ?>
                <?php foreach ($archivedUsers as $u): ?>
                    <tr>
                        <td><strong><?php echo sanitize($u['full_name']); ?></strong></td>
                        <td><?php echo sanitize($u['username']); ?></td>
                        <td><?php echo sanitize($u['email']); ?></td>
                        <td><?php echo sanitize($u['phone'] ?? '—'); ?></td>
                        <td>
                            <?php
                            $roleLabel = 'Unknown';
                            $roleIcon = 'fa-user';
                            if ($u['role'] === 'admin') { $roleLabel = 'Administrator'; $roleIcon = 'fa-shield-halved'; }
                            elseif ($u['role'] === 'healthcare_worker') { $roleLabel = 'Healthcare Worker'; $roleIcon = 'fa-user-doctor'; }
                            elseif ($u['role'] === 'patient') { $roleLabel = 'Patient'; $roleIcon = 'fa-user'; }
                            ?>
                            <span class="badge badge-cancelled" style="font-size:0.7rem;">
                                <i class="fa-solid <?php echo $roleIcon; ?>"></i> <?php echo $roleLabel; ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo !empty($u['updated_at']) ? formatDate($u['updated_at']) : '—'; ?>
                            </small>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                                <button type="button" class="btn btn-outline btn-sm" style="color:var(--success);"
                                    onclick="if(confirm('Restore this account?')){ document.getElementById('restoreUserId').value = <?php echo (int)$u['id']; ?>; document.getElementById('restoreForm').submit(); }">
                                    <i class="fa-solid fa-rotate-left"></i> Restore
                                </button>
                                <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);"
                                    onclick="if(confirm('Permanently DELETE this account? This cannot be undone!')){ document.getElementById('deleteUserId').value = <?php echo (int)$u['id']; ?>; document.getElementById('deleteForm').submit(); }">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center p-4 text-muted">
                        <i class="fa-solid fa-box-archive" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                        No archived accounts found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Hidden Forms -->
<form id="restoreForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input type="hidden" name="action" value="restore_user">
    <input type="hidden" name="user_id" id="restoreUserId">
</form>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>