<?php
/**
 * Account Settings (Administrator)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Account Settings";
$activePage = "settings";
$userId = getCurrentUserId();

$successMsg = '';
$errorMsg = '';

// ============================================
// HANDLE PROFILE UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error. Please refresh and try again.";
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
                $check->execute([$email, $userId]);

                if ($check->fetch()) {
                    $errorMsg = "That email is already in use by another account.";
                } else {
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$fullName, $email, $phone ?: null, $userId]);

                    $_SESSION['full_name'] = $fullName;

                    logAudit('PROFILE_UPDATE', "Admin updated profile");
                    $successMsg = "Profile updated successfully!";
                }
            } catch (Exception $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// HANDLE PASSWORD CHANGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error.";
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMsg = "Please fill in all password fields.";
        } elseif (strlen($newPassword) < 6) {
            $errorMsg = "New password must be at least 6 characters.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMsg = "New passwords do not match.";
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($currentPassword, $user['password'])) {
                    $errorMsg = "Current password is incorrect.";
                } else {
                    $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $userId]);

                    logAudit('PASSWORD_CHANGE', "Admin changed password");
                    $successMsg = "Password changed successfully!";
                }
            } catch (Exception $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// KUHAON ANG USER INFO
// ============================================
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Error: User not found.");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Account Settings</h1>
        <p>Manage your account information and password</p>
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

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap:1.5rem;">

    <!-- ============================================ -->
    <!-- PROFILE CARD WITH AVATAR UPLOAD -->
    <!-- ============================================ -->
    <div class="dashboard-card" style="padding:1.5rem;">
        <div style="display:flex; align-items:center; gap:1.25rem; margin-bottom:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid var(--border-color); flex-wrap:wrap;">
            <!-- Avatar with Upload -->
            <div style="position:relative;">
                <div id="avatarPreview" style="
                    width:90px; height:90px; border-radius:50%;
                    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
                    color:#fff; display:flex; align-items:center; justify-content:center;
                    font-family:'Bricolage Grotesque', sans-serif;
                    font-size:2.2rem; font-weight:800;
                    box-shadow: 0 8px 20px rgba(249,115,22,0.35);
                    overflow:hidden;
                    border: 4px solid var(--surface);
                ">
                    <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])): ?>
                        <img src="<?php echo BASE_URL . htmlspecialchars($user['avatar']); ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <label for="avatarFileInput" style="
                    position:absolute; bottom:-4px; right:-4px;
                    width:34px; height:34px; border-radius:50%;
                    background: var(--surface);
                    border: 2px solid var(--primary);
                    color: var(--primary);
                    display:flex; align-items:center; justify-content:center;
                    cursor:pointer; font-size:0.85rem;
                    transition: all 0.25s ease;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
                " title="Upload new picture">
                    <i class="fa-solid fa-camera"></i>
                </label>
                <input type="file" id="avatarFileInput" accept="image/*" style="display:none;">
            </div>

            <!-- User Info -->
            <div style="flex:1; min-width:180px;">
                <h3 style="margin:0 0 0.35rem 0; font-size:1.15rem;"><?php echo sanitize($user['full_name']); ?></h3>
                <span class="badge badge-confirmed" style="font-size:0.7rem;">
                    <i class="fa-solid fa-shield-halved"></i> Administrator
                </span>
                <p class="text-muted" style="margin:0.5rem 0 0 0; font-size:0.75rem;">
                    <i class="fa-solid fa-info-circle"></i> Click the camera to select a new photo. Max 5MB..
                </p>
                <div id="avatarStatus" style="margin-top:0.5rem; font-size:0.82rem; font-weight:700; display:none;"></div>
            </div>

            <?php if (!empty($user['avatar'])): ?>
                <button type="button" id="removeAvatarBtn" class="btn btn-outline btn-sm" style="color:var(--danger);">
                    <i class="fa-solid fa-trash"></i> Remove
                </button>
            <?php endif; ?>
        </div>

        <h4 style="margin-bottom:1rem; font-size:0.95rem; display:flex; align-items:center; gap:0.5rem;">
            <i class="fa-solid fa-user text-primary"></i> Profile Information
        </h4>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo sanitize($user['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?php echo sanitize($user['username']); ?>" readonly style="background:var(--bg-body); cursor:not-allowed;">
                <small class="text-muted" style="font-size:0.72rem;">Username cannot be changed</small>
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo sanitize($user['phone'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </form>
    </div>

    <!-- ============================================ -->
    <!-- PASSWORD CARD -->
    <!-- ============================================ -->
    <div class="dashboard-card" style="padding:1.5rem;">
        <h4 style="margin-bottom:1rem; font-size:0.95rem; display:flex; align-items:center; gap:0.5rem;">
            <i class="fa-solid fa-lock text-primary"></i> Change Password
        </h4>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
            </div>

            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-key"></i> Change Password
            </button>
        </form>

        <div class="alert alert-info mt-4" style="font-size:0.8rem;">
            <i class="fa-solid fa-info-circle"></i>
            <div>Please use a strong password (letters, numbers, symbols).</div>
        </div>
    </div>

</div>

<script>
(function() {
    'use strict';

    var _csrf = '<?php echo getCsrfToken(); ?>';
    var _baseUrl = '<?php echo BASE_URL; ?>';
    var _avatarInput = document.getElementById('avatarFileInput');
    var _avatarPreview = document.getElementById('avatarPreview');
    var _avatarStatus = document.getElementById('avatarStatus');

    function showStatus(msg, type) {
        if (!_avatarStatus) return;
        _avatarStatus.style.display = 'block';
        _avatarStatus.textContent = msg;
        if (type === 'success') _avatarStatus.style.color = '#10b981';
        else if (type === 'error') _avatarStatus.style.color = '#dc3545';
        else _avatarStatus.style.color = '#6b7280';
    }

    if (_avatarInput) {
        _avatarInput.addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                showStatus('❌ File is too large (max 5MB)', 'error');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                _avatarPreview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%; height:100%; object-fit:cover;">';
            };
            reader.readAsDataURL(file);

            showStatus('⏳ Uploading...', 'info');

            var fd = new FormData();
            fd.append('avatar', file);
            fd.append('csrf_token', _csrf);

            fetch(_baseUrl + 'api/upload_avatar.php', { method: 'POST', body: fd })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        showStatus('✅ ' + data.message, 'success');
                        setTimeout(function() { window.location.reload(); }, 1200);
                    } else {
                        showStatus('❌ ' + data.message, 'error');
                    }
                })
                .catch(function() {
                    showStatus('❌ Network error. Please try again.', 'error');
                });
        });
    }

    var removeBtn = document.getElementById('removeAvatarBtn');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            if (!confirm('Remove your profile picture?')) return;

            var fd = new FormData();
            fd.append('csrf_token', _csrf);

            fetch(_baseUrl + 'api/remove_avatar.php', { method: 'POST', body: fd })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert('Profile picture removed!');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Could not remove.');
                    }
                })
                .catch(function() {
                    alert('Network error. Please try again.');
                });
        });
    }

})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>