<?php
/**
 * Users & Staff Management (Administrator)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Users & Staff";
$activePage = "users";
$userId = getCurrentUserId();

$successMsg = '';
$errorMsg = '';

// ============================================
// HANDLE ADD USER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error. Please refresh and try again.";
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = 'healthcare_worker';

        if ($fullName === '' || $username === '' || $email === '' || $password === '') {
            $errorMsg = "Please fill in all required fields.";
        } elseif (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            $errorMsg = "Username must be 3-30 characters (letters, numbers, dot, dash, underscore).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $errorMsg = "Password must be at least 6 characters.";
        } else {
            try {
                $db = getDB();
                $check = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $check->execute([$username, $email]);

                if ($check->fetch()) {
                    $errorMsg = "That username or email is already registered.";
                } else {
                    $ins = $db->prepare("INSERT INTO users (username, email, password, role, full_name, phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
                    $ins->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT), $role, $fullName, $phone ?: null]);

                    logAudit('USER_CREATED', "Admin created healthcare worker '$username'");
                    $successMsg = "Healthcare Worker added successfully!";
                }
            } catch (Exception $e) {
                $errorMsg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// HANDLE EDIT USER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_user') {
    $csrf = $_POST['csrf_token'] ?? '';
    $editId = (int)($_POST['user_id'] ?? 0);
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error.";
    } elseif (!$editId) {
        $errorMsg = "Invalid user ID.";
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '' || $email === '') {
            $errorMsg = "Full name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please enter a valid email address.";
        } else {
            try {
                $db = getDB();
                $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $check->execute([$email, $editId]);

                if ($check->fetch()) {
                    $errorMsg = "That email is already in use by another account.";
                } else {
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$fullName, $email, $phone ?: null, $editId]);

                    logAudit('USER_UPDATED', "Admin updated user ID $editId");
                    $successMsg = "User updated successfully!";
                }
            } catch (Exception $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// HANDLE RESET PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $csrf = $_POST['csrf_token'] ?? '';
    $resetId = (int)($_POST['user_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error.";
    } elseif (!$resetId) {
        $errorMsg = "Invalid user ID.";
    } elseif (strlen($newPassword) < 6) {
        $errorMsg = "Password must be at least 6 characters.";
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $resetId]);

            logAudit('PASSWORD_RESET', "Admin reset password for user ID $resetId");
            $successMsg = "Password reset successfully! Ang bag-ong password kay: " . htmlspecialchars($newPassword);
        } catch (Exception $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// ============================================
// HANDLE ARCHIVE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'archive_user') {
    $csrf = $_POST['csrf_token'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    if (verifyCsrfToken($csrf) && $targetId && $targetId !== $userId) {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET status = 'archived' WHERE id = ?");
            $stmt->execute([$targetId]);
            logAudit('USER_ARCHIVED', "Admin archived user ID $targetId");
            $successMsg = "User archived successfully!";
        } catch (Exception $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// ============================================
// KUHAON ANG TANAN USERS
// ============================================
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM users 
        WHERE role IN ('healthcare_worker', 'patient') AND status != 'archived'
        ORDER BY FIELD(role, 'healthcare_worker', 'patient'), full_name ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Users & Staff</h1>
        <p>Manage healthcare workers, patient accounts, roles, and security access</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" id="openAddModalBtn">
            <i class="fa-solid fa-plus"></i> Add Staff Member
        </button>
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

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Full Name</th>
                <th>Username / Email</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><span class="badge badge-pending" style="font-size:0.7rem;">#<?php echo (int)$u['id']; ?></span></td>
                        <td><strong><?php echo sanitize($u['full_name']); ?></strong></td>
                        <td>
                            <?php echo sanitize($u['username']); ?><br>
                            <small class="text-muted"><?php echo sanitize($u['email']); ?></small>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'healthcare_worker'): ?>
                                <span class="badge badge-confirmed" style="font-size:0.7rem;">
                                    <i class="fa-solid fa-user-doctor"></i> Healthcare Worker
                                </span>
                            <?php else: ?>
                                <span class="badge badge-completed" style="font-size:0.7rem;">
                                    <i class="fa-solid fa-user"></i> Patient
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                                <button type="button" class="btn btn-outline btn-sm js-edit-btn"
                                    data-user-id="<?php echo (int)$u['id']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-user-username="<?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-user-email="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-user-phone="<?php echo htmlspecialchars($u['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Edit user details">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                                <button type="button" class="btn btn-primary btn-sm js-reset-btn"
                                    data-user-id="<?php echo (int)$u['id']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Reset password">
                                    <i class="fa-solid fa-key"></i> Reset Password
                                </button>
                                <button type="button" class="btn btn-outline btn-sm js-archive-btn" style="color:var(--danger);"
                                    data-user-id="<?php echo (int)$u['id']; ?>"
                                    title="Archive user">
                                    <i class="fa-solid fa-box-archive"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center p-4 text-muted">
                        No users found. Click "Add Staff Member" to add one.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus text-primary"></i> Add Staff Member</h3>
            <button type="button" class="js-close-modal" data-modal="addUserModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="addUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="role" value="healthcare_worker">

                <div class="form-group">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. Maria Santos" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. mariasantos" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. maria@clinic.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. 09171234567">
                </div>

                <div class="form-group">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="text" name="password" class="form-control" value="password123" minlength="6" required>
                    <small class="text-muted" style="display:block; margin-top:0.35rem; font-size:0.72rem;">
                        <i class="fa-solid fa-info-circle"></i> Tell this password to the user.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" value="Healthcare Worker (Doctor / Nurse)" readonly style="background:var(--bg-body); cursor:not-allowed;">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="addUserModal">Cancel</button>
            <button type="submit" form="addUserForm" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Add Staff Member
            </button>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen text-primary"></i> Edit User</h3>
            <button type="button" class="js-close-modal" data-modal="editUserModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="form-group">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="editFullName" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" id="editUsername" class="form-control" readonly style="background:var(--bg-body); cursor:not-allowed;">
                    <small class="text-muted" style="display:block; margin-top:0.35rem; font-size:0.72rem;">
                        <i class="fa-solid fa-info-circle"></i> Username cannot be changed
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="editEmail" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="editPhone" class="form-control">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="editUserModal">Cancel</button>
            <button type="submit" form="editUserForm" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-key text-primary"></i> Reset Password</h3>
            <button type="button" class="js-close-modal" data-modal="resetPasswordModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="resetPasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">

                <div class="alert alert-info mb-4" style="font-size:0.85rem;">
                    <i class="fa-solid fa-user"></i>
                    <div>
                        Reset password para kay: <strong id="resetUserName">-</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password <span class="text-danger">*</span></label>
                    <input type="text" name="new_password" id="resetNewPassword" class="form-control" value="password123" minlength="6" required>
                    <small class="text-muted" style="display:block; margin-top:0.35rem; font-size:0.72rem;">
                        <i class="fa-solid fa-info-circle"></i> Minimum 6 characters. Isulti kini sa user human ma-reset.
                    </small>
                </div>

                <div class="alert alert-warning" style="font-size:0.8rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>Make sure to tell the user the new password after it has been reset..</div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="resetPasswordModal">Cancel</button>
            <button type="submit" form="resetPasswordForm" class="btn btn-primary">
                <i class="fa-solid fa-key"></i> Reset Password
            </button>
        </div>
    </div>
</div>

<!-- Archive Form -->
<form id="archiveForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input type="hidden" name="action" value="archive_user">
    <input type="hidden" name="user_id" id="archiveUserId">
</form>

<script>
(function() {
    'use strict';

    function showModal(id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.display = 'flex';
            el.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function hideModal(id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.display = 'none';
            el.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    function openAddModal() {
        var form = document.getElementById('addUserForm');
        if (form) {
            form.reset();
            var pwField = form.querySelector('input[name="password"]');
            if (pwField) pwField.value = 'password123';
        }
        showModal('addUserModal');
    }

    function openEditModal(userId, fullName, username, email, phone) {
        document.getElementById('editUserId').value = userId;
        document.getElementById('editFullName').value = fullName || '';
        document.getElementById('editUsername').value = username || '';
        document.getElementById('editEmail').value = email || '';
        document.getElementById('editPhone').value = phone || '';
        showModal('editUserModal');
    }

    function openResetPasswordModal(userId, userName) {
        document.getElementById('resetUserId').value = userId;
        document.getElementById('resetUserName').textContent = userName;
        document.getElementById('resetNewPassword').value = 'password123';
        showModal('resetPasswordModal');
    }

    document.addEventListener('click', function(e) {
        if (e.target.closest('#openAddModalBtn')) {
            e.preventDefault();
            openAddModal();
            return;
        }

        var editBtn = e.target.closest('.js-edit-btn');
        if (editBtn) {
            e.preventDefault();
            openEditModal(
                editBtn.getAttribute('data-user-id'),
                editBtn.getAttribute('data-user-name'),
                editBtn.getAttribute('data-user-username'),
                editBtn.getAttribute('data-user-email'),
                editBtn.getAttribute('data-user-phone')
            );
            return;
        }

        var resetBtn = e.target.closest('.js-reset-btn');
        if (resetBtn) {
            e.preventDefault();
            openResetPasswordModal(
                resetBtn.getAttribute('data-user-id'),
                resetBtn.getAttribute('data-user-name')
            );
            return;
        }

        var archiveBtn = e.target.closest('.js-archive-btn');
        if (archiveBtn) {
            e.preventDefault();
            var archiveId = archiveBtn.getAttribute('data-user-id');
            if (confirm('Archive this user?')) {
                document.getElementById('archiveUserId').value = archiveId;
                document.getElementById('archiveForm').submit();
            }
            return;
        }

        var closeBtn = e.target.closest('.js-close-modal');
        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            var modalId = closeBtn.getAttribute('data-modal');
            if (modalId) hideModal(modalId);
            return;
        }

        if (e.target.classList.contains('modal-backdrop')) {
            hideModal(e.target.id);
            return;
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop').forEach(function(m) {
                hideModal(m.id);
            });
        }
    });

})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>