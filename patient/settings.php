<?php
/**
 * Account Settings (Patient)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('patient');

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

                    logAudit('PROFILE_UPDATE', "Patient updated profile");
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

                    logAudit('PASSWORD_CHANGE', "Patient changed password");
                    $successMsg = "Password changed successfully!";
                }
            } catch (Exception $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// KUHAON ANG USER + PATIENT INFO
// ============================================
try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Error: User not found.");
    }

    $stmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$userId]);
    $patient = $stmt->fetch();

    // Stats
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $stmt->execute([$patient['id'] ?? 0]);
    $totalAppointments = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM prenatal_records WHERE patient_id = ?");
    $stmt->execute([$patient['id'] ?? 0]);
    $totalRecords = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   PATIENT SETTINGS MODERN DESIGN
   ============================================ */

.settings-hero {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 20px;
    padding: 2rem;
    color: #fff;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.settings-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.settings-hero-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.settings-avatar {
    width: 90px;
    height: 90px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 2.5rem;
    font-weight: 800;
    flex-shrink: 0;
}

.settings-hero-text {
    flex: 1;
    min-width: 200px;
}

.settings-hero-text h1 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0 0 0.35rem 0;
    color: #fff;
    letter-spacing: -0.8px;
}

.settings-hero-text p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.9rem;
}

.settings-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    margin-top: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Settings Grid */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.settings-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.25s ease;
}

.settings-card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

.settings-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.settings-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.settings-card-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}

.settings-card-header small {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
}

/* Form Controls */
.form-group {
    margin-bottom: 1.1rem;
}

.form-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 0.45rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text-dark);
    font-family: inherit;
    font-size: 0.88rem;
    font-weight: 500;
    transition: all 0.2s ease;
    outline: none;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.form-control[readonly] {
    background: var(--bg-body);
    cursor: not-allowed;
    color: var(--text-muted);
}

/* Info Row */
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px dashed var(--border-color);
    font-size: 0.85rem;
}

.info-row:last-child {
    border-bottom: none;
}

.info-row-label {
    color: var(--text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-row-label i {
    color: var(--primary);
    width: 16px;
    text-align: center;
}

.info-row-value {
    color: var(--text-dark);
    font-weight: 700;
    text-align: right;
    word-break: break-word;
}

/* Stats Grid */
.patient-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.patient-stat-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.35rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.patient-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}

.patient-stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
    flex-shrink: 0;
}

.patient-stat-card.orange .patient-stat-icon { background: linear-gradient(135deg, #f97316, #ea580c); box-shadow: 0 6px 16px rgba(249, 115, 22, 0.3); }
.patient-stat-card.green .patient-stat-icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3); }
.patient-stat-card.blue .patient-stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); box-shadow: 0 6px 16px rgba(13, 110, 253, 0.3); }

.patient-stat-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.patient-stat-label {
    font-size: 0.68rem;
    color: var(--text-muted);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Responsive */
@media (max-width: 768px) {
    .settings-hero { padding: 1.5rem; border-radius: 16px; }
    .settings-hero-text h1 { font-size: 1.35rem; }
    .settings-avatar { width: 70px; height: 70px; font-size: 1.8rem; border-radius: 18px; }
    .settings-grid { grid-template-columns: 1fr; }
    .patient-stats-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .patient-stat-card { padding: 1rem; }
    .patient-stat-value { font-size: 1.35rem; }
    .patient-stat-icon { width: 42px; height: 42px; font-size: 1.05rem; }
}

@media (max-width: 480px) {
    .settings-hero { padding: 1.25rem; }
    .settings-hero-text h1 { font-size: 1.15rem; }
    .settings-avatar { display: none; }
    .patient-stats-grid { grid-template-columns: 1fr; }
    .info-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
    .info-row-value { text-align: left; }
}
</style>

<!-- Hero -->
<div class="settings-hero">
    <div class="settings-hero-content">
        <div class="settings-avatar">
            <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
        </div>
        <div class="settings-hero-text">
            <h1><?php echo sanitize($user['full_name']); ?></h1>
            <p>Manage your account information and password</p>
            <span class="settings-hero-badge">
                <i class="fa-solid fa-user"></i> Patient Account
            </span>
        </div>
    </div>
</div>

<!-- Alerts -->
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

<!-- Stats -->
<div class="patient-stats-grid">
    <div class="patient-stat-card orange">
        <div class="patient-stat-icon">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div>
            <div class="patient-stat-value"><?php echo $totalAppointments; ?></div>
            <div class="patient-stat-label">Total Appointments</div>
        </div>
    </div>

    <div class="patient-stat-card green">
        <div class="patient-stat-icon">
            <i class="fa-solid fa-notes-medical"></i>
        </div>
        <div>
            <div class="patient-stat-value"><?php echo $totalRecords; ?></div>
            <div class="patient-stat-label">Prenatal Records</div>
        </div>
    </div>

    <div class="patient-stat-card blue">
        <div class="patient-stat-icon">
            <i class="fa-solid fa-user-clock"></i>
        </div>
        <div>
            <div class="patient-stat-value">
                <?php echo !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A'; ?>
            </div>
            <div class="patient-stat-label">Member Since</div>
        </div>
    </div>
</div>

<!-- Settings Grid -->
<div class="settings-grid">

    <!-- Profile Card -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">
                <i class="fa-solid fa-user"></i>
            </div>
            <div>
                <h3>Profile Information</h3>
                <small>Update your personal details</small>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" value="<?php echo sanitize($user['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?php echo sanitize($user['username']); ?>" readonly>
                <small class="text-muted" style="font-size:0.72rem;">Username cannot be changed</small>
            </div>

            <div class="form-group">
                <label class="form-label">Email <span class="text-danger">*</span></label>
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

    <!-- Password Card -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div>
                <h3>Change Password</h3>
                <small>Keep your account secure</small>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
            </div>

            <div class="form-group">
                <label class="form-label">New Password <span class="text-danger">*</span></label>
                <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-key"></i> Change Password
            </button>
        </form>

        <div class="alert alert-info" style="font-size:0.78rem; margin-top:1rem; margin-bottom:0;">
            <i class="fa-solid fa-info-circle"></i>
            <div>Please use a strong password (letters, numbers, symbols).</div>
        </div>
    </div>

</div>

<!-- Account Info Card -->
<div class="settings-card" style="margin-bottom:1.5rem;">
    <div class="settings-card-header">
        <div class="settings-card-icon">
            <i class="fa-solid fa-circle-info"></i>
        </div>
        <div>
            <h3>Account Information</h3>
            <small>Read-only account details</small>
        </div>
    </div>

    <div class="info-row">
        <div class="info-row-label">
            <i class="fa-solid fa-id-card"></i> Patient Code
        </div>
        <div class="info-row-value"><?php echo sanitize($patient['patient_code'] ?? 'N/A'); ?></div>
    </div>

    <div class="info-row">
        <div class="info-row-label">
            <i class="fa-solid fa-at"></i> Username
        </div>
        <div class="info-row-value"><?php echo sanitize($user['username']); ?></div>
    </div>

    <div class="info-row">
        <div class="info-row-label">
            <i class="fa-solid fa-envelope"></i> Email
        </div>
        <div class="info-row-value"><?php echo sanitize($user['email']); ?></div>
    </div>

    <div class="info-row">
        <div class="info-row-label">
            <i class="fa-solid fa-calendar"></i> Member Since
        </div>
        <div class="info-row-value">
            <?php echo !empty($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'N/A'; ?>
        </div>
    </div>

    <div class="info-row">
        <div class="info-row-label">
            <i class="fa-solid fa-circle-check"></i> Account Status
        </div>
        <div class="info-row-value">
            <span class="badge badge-confirmed" style="font-size:0.7rem;">
                <?php echo ucfirst($user['status'] ?? 'Active'); ?>
            </span>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>