<?php
/**
 * Account Settings (Healthcare Worker)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

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

                // Check kung naay laing user nga nag-gamit sa email
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    $errorMsg = "That email is already in use by another account.";
                } else {
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$fullName, $email, $phone ?: null, $userId]);

                    $_SESSION['full_name'] = $fullName;

                    logAudit('PROFILE_UPDATE', "Worker updated profile");
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

                    logAudit('PASSWORD_CHANGE', "Worker changed password");
                    $successMsg = "Password changed successfully!";
                }
            } catch (Exception $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// KUHAON ANG USER INFO + STATS
// ============================================
try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Error: User not found.");
    }

    // Total appointments handled
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE healthcare_worker_id = ?");
    $stmt->execute([$userId]);
    $totalAppointments = (int)$stmt->fetchColumn();

    // Total examinations done
    $stmt = $db->prepare("SELECT COUNT(*) FROM prenatal_records WHERE healthcare_worker_id = ?");
    $stmt->execute([$userId]);
    $totalRecords = (int)$stmt->fetchColumn();

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

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">

    <!-- ============================================ -->
    <!-- PROFILE CARD -->
    <!-- ============================================ -->
    <div class="dashboard-card" style="padding:1.5rem;">
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
            <div style="
                width:70px; height:70px; border-radius:50%;
                background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
                color:#fff; display:flex; align-items:center; justify-content:center;
                font-family:'Bricolage Grotesque', sans-serif;
                font-size:1.8rem; font-weight:800;
                box-shadow: 0 4px 12px rgba(13,110,253,0.3);
                flex-shrink: 0;
            ">
                <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div>
                <h3 style="margin:0; font-size:1.1rem;"><?php echo sanitize($user['full_name']); ?></h3>
                <span class="badge badge-confirmed" style="font-size:0.7rem; margin-top:0.35rem; display:inline-block;">
                    <i class="fa-solid fa-user-doctor"></i> Healthcare Worker
                </span>
            </div>
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
                <input type="text" name="phone" class="form-control" value="<?php echo sanitize($user['phone'] ?? ''); ?>" placeholder="e.g. 09171234567">
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

<!-- ============================================ -->
<!-- STATS -->
<!-- ============================================ -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-top:1.5rem;">

    <div class="dashboard-card" style="padding:1.5rem; text-align:center;">
        <div style="
            width:52px; height:52px; border-radius:14px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color:#fff; display:flex; align-items:center; justify-content:center;
            font-size:1.3rem; margin:0 auto 0.75rem;
            box-shadow: 0 4px 12px rgba(249,115,22,0.3);
        ">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <h2 style="margin:0; font-size:1.75rem; font-family:'Bricolage Grotesque',sans-serif;"><?php echo $totalAppointments; ?></h2>
        <small class="text-muted" style="font-size:0.78rem; font-weight:600;">Total Appointments</small>
    </div>

    <div class="dashboard-card" style="padding:1.5rem; text-align:center;">
        <div style="
            width:52px; height:52px; border-radius:14px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color:#fff; display:flex; align-items:center; justify-content:center;
            font-size:1.3rem; margin:0 auto 0.75rem;
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        ">
            <i class="fa-solid fa-notes-medical"></i>
        </div>
        <h2 style="margin:0; font-size:1.75rem; font-family:'Bricolage Grotesque',sans-serif;"><?php echo $totalRecords; ?></h2>
        <small class="text-muted" style="font-size:0.78rem; font-weight:600;">Examinations Done</small>
    </div>

    <div class="dashboard-card" style="padding:1.5rem; text-align:center;">
        <div style="
            width:52px; height:52px; border-radius:14px;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color:#fff; display:flex; align-items:center; justify-content:center;
            font-size:1.3rem; margin:0 auto 0.75rem;
            box-shadow: 0 4px 12px rgba(139,92,246,0.3);
        ">
            <i class="fa-solid fa-user-clock"></i>
        </div>
        <h2 style="margin:0; font-size:1.75rem; font-family:'Bricolage Grotesque',sans-serif;">
            <?php echo !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A'; ?>
        </h2>
        <small class="text-muted" style="font-size:0.78rem; font-weight:600;">Member Since</small>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>