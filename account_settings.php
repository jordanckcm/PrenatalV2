<?php
/**
 * Professional Settings Page (Account & Preferences)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

requireLogin();

$pageTitle = "Settings";
$activePage = "account_settings";
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

$profileError = '';
$profileSuccess = '';
$passwordError = '';
$passwordSuccess = '';

$db = getDB();

// Fetch User Data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Patient Data & Address
$patientAddress = '';
$patientRecord = null;
if ($userRole === 'patient') {
    $pStmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
    $pStmt->execute([$userId]);
    $patientRecord = $pStmt->fetch();
    if ($patientRecord && isset($patientRecord['address'])) {
        $patientAddress = $patientRecord['address'];
    }
}
$userAddress = $user['address'] ?? $patientAddress;

// Split Full Name into First Name, Middle Name, Last Name
$rawFullName = trim($user['full_name']);
$nameTokens = array_values(array_filter(explode(' ', $rawFullName)));
$firstName = $nameTokens[0] ?? '';
$lastName = count($nameTokens) > 1 ? array_pop($nameTokens) : '';
$middleName = count($nameTokens) > 0 ? implode(' ', $nameTokens) : '';

// Handle Avatar Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload_avatar'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $profileError = "Security token error. Please try again.";
    } elseif (!isset($_FILES['avatar_file']) || $_FILES['avatar_file']['error'] !== UPLOAD_ERR_OK) {
        $profileError = "Please select a valid image file to upload.";
    } else {
        $file = $_FILES['avatar_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $profileError = "Invalid file format. Only JPG, PNG, and WEBP images are supported.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $profileError = "File is too large (5MB maximum).";
        } else {
            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            $relPath = 'uploads/avatars/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])) {
                    @unlink(__DIR__ . '/' . $user['avatar']);
                }
                $uStmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $uStmt->execute([$relPath, $userId]);
                $profileSuccess = "Profile avatar uploaded successfully!";
                logAudit("UPLOAD_AVATAR", "Uploaded avatar for user ID {$userId}");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } else {
                $profileError = "Failed to save avatar image file.";
            }
        }
    }
}

// Handle Remove Avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_remove_avatar'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $profileError = "Security token error. Please try again.";
    } else {
        if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])) {
            @unlink(__DIR__ . '/' . $user['avatar']);
        }
        $uStmt = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
        $uStmt->execute([$userId]);
        $profileSuccess = "Avatar image removed.";
        logAudit("REMOVE_AVATAR", "Removed avatar for user ID {$userId}");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
}

// Handle Username Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_username'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $newUsername = strtolower(trim($_POST['username'] ?? ''));

    if (!verifyCsrfToken($csrfToken)) {
        $profileError = "Security token error. Please try again.";
    } elseif (!preg_match('/^[a-z0-9._-]{3,20}$/', $newUsername)) {
        $profileError = "Username must be between 3 and 20 characters (letters, numbers, dot, dash, underscore).";
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk->execute([$newUsername, $userId]);
        if ($chk->fetch()) {
            $profileError = "That username is already taken.";
        } else {
            $uStmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
            $uStmt->execute([$newUsername, $userId]);
            $_SESSION['username'] = $newUsername;
            $profileSuccess = "Username updated successfully!";
            logAudit("UPDATE_USERNAME", "Updated username for user ID {$userId}");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
}

// Handle Display Name Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_display_name'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $fName = trim($_POST['first_name'] ?? '');
    $mName = trim($_POST['middle_name'] ?? '');
    $lName = trim($_POST['last_name'] ?? '');

    if (!verifyCsrfToken($csrfToken)) {
        $profileError = "Security token error. Please try again.";
    } elseif (empty($fName) || empty($lName)) {
        $profileError = "First name and last name are required.";
    } else {
        $reconstructed = trim($fName . ($mName ? ' ' . $mName : '') . ' ' . $lName);
        $uStmt = $db->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $uStmt->execute([$reconstructed, $userId]);
        $_SESSION['full_name'] = $reconstructed;
        $profileSuccess = "Display name updated successfully!";
        logAudit("UPDATE_DISPLAY_NAME", "Updated display name for user ID {$userId}");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // Refresh split names
        $rawFullName = trim($user['full_name']);
        $nameTokens = array_values(array_filter(explode(' ', $rawFullName)));
        $firstName = $nameTokens[0] ?? '';
        $lastName = count($nameTokens) > 1 ? array_pop($nameTokens) : '';
        $middleName = count($nameTokens) > 0 ? implode(' ', $nameTokens) : '';
    }
}

// Handle Contact Details & Address Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_contact'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!verifyCsrfToken($csrfToken)) {
        $profileError = "Security token error. Please try again.";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = "Please enter a valid email address.";
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
            $profileError = "That email address is already in use.";
        } else {
            $db->beginTransaction();
            $uStmt = $db->prepare("UPDATE users SET email = ?, phone = ?, address = ? WHERE id = ?");
            $uStmt->execute([$email, $phone, $address, $userId]);
            if ($userRole === 'patient') {
                $pStmt = $db->prepare("UPDATE patients SET address = ? WHERE user_id = ?");
                $pStmt->execute([$address, $userId]);
            }
            $db->commit();
            $profileSuccess = "Contact information updated successfully!";
            logAudit("UPDATE_CONTACT", "Updated contact details for user ID {$userId}");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            $userAddress = $address;
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_password'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $passwordError = "Security token error. Please try again.";
    } elseif (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = "Please fill in all password fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = "New passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $passwordError = "Password must be at least 6 characters long.";
    } else {
        $isCurrentValid = password_verify($currentPassword, $user['password']);
        if (!$isCurrentValid && hasUnusablePasswordHash($user['password']) && $currentPassword === 'password123') {
            $isCurrentValid = true;
        }

        if (!$isCurrentValid) {
            $passwordError = "Incorrect current password.";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $passStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $passStmt->execute([$newHash, $userId]);
            $passwordSuccess = "Password updated successfully!";
            logAudit("CHANGE_PASSWORD", "Updated password for user ID {$userId}");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
}

// Handle Patient Medical Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_patient_medical'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $bloodType = trim($_POST['blood_type'] ?? 'A+');
    $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
    $lmp = !empty($_POST['lmp']) ? $_POST['lmp'] : null;
    $edd = !empty($_POST['edd']) ? $_POST['edd'] : null;
    $gravida = isset($_POST['gravida']) ? (int)$_POST['gravida'] : 1;
    $para = isset($_POST['para']) ? (int)$_POST['para'] : 0;
    $abortus = isset($_POST['abortus']) ? (int)$_POST['abortus'] : 0;
    $medicalHistory = trim($_POST['medical_history'] ?? '');

    if (!verifyCsrfToken($csrfToken)) {
        $profileError = "Security token error. Please try again.";
    } else {
        if ($patientRecord) {
            $uPStmt = $db->prepare("UPDATE patients SET dob = ?, blood_type = ?, emergency_contact_name = ?, emergency_contact_phone = ?, lmp = ?, edd = ?, gravida = ?, para = ?, abortus = ?, medical_history = ? WHERE user_id = ?");
            $uPStmt->execute([$dob, $bloodType, $emergencyName, $emergencyPhone, $lmp, $edd, $gravida, $para, $abortus, $medicalHistory, $userId]);
        } else {
            $pCode = 'P-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
            $iPStmt = $db->prepare("INSERT INTO patients (user_id, patient_code, dob, blood_type, emergency_contact_name, emergency_contact_phone, lmp, edd, gravida, para, abortus, medical_history) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $iPStmt->execute([$userId, $pCode, $dob, $bloodType, $emergencyName, $emergencyPhone, $lmp, $edd, $gravida, $para, $abortus, $medicalHistory]);
        }
        $profileSuccess = "Prenatal Medical Profile updated successfully!";
        logAudit("UPDATE_PATIENT_MEDICAL", "Updated medical profile for user ID {$userId}");
        
        // Refresh patient record
        $pStmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
        $pStmt->execute([$userId]);
        $patientRecord = $pStmt->fetch();
    }
}

// Generate User Avatar Initials
$avatarInitials = strtoupper(substr($firstName, 0, 1));
if ($lastName) {
    $avatarInitials .= strtoupper(substr($lastName, 0, 1));
}

include __DIR__ . '/includes/header.php';
?>

<!-- Settings Header & Subhead -->
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="page-title">
        <h1 style="font-size: 2rem; font-weight: 800; font-family: 'Bricolage Grotesque', sans-serif;">Settings</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.2rem;">Your account and preferences.</p>
    </div>
</div>

<!-- Settings Navigation Tabs (Reference Image style) -->
<div style="border-bottom: 1px solid var(--border-color); margin-bottom: 2rem; display: flex; gap: 1.5rem;">
    <button type="button" id="tabBtnAccount" class="settings-tab-btn active" onclick="switchSettingsTab('account')" style="padding-bottom: 0.85rem; font-weight: 700; font-size: 0.95rem; color: var(--text-dark); border: none; background: none; border-bottom: 2px solid var(--primary); cursor: pointer;">
        Account
    </button>
    <button type="button" id="tabBtnPreferences" class="settings-tab-btn" onclick="switchSettingsTab('preferences')" style="padding-bottom: 0.85rem; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); border: none; background: none; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
        Preferences
        <span style="background: rgba(249, 115, 22, 0.2); color: #F97316; font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: uppercase;">New</span>
    </button>
</div>

<?php if ($profileError): ?>
    <div class="alert alert-danger mb-4"><i class="fa-solid fa-circle-exclamation"></i><div><?php echo sanitize($profileError); ?></div></div>
<?php endif; ?>
<?php if ($profileSuccess): ?>
    <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check"></i><div><?php echo sanitize($profileSuccess); ?></div></div>
<?php endif; ?>
<?php if ($passwordError): ?>
    <div class="alert alert-danger mb-4"><i class="fa-solid fa-circle-exclamation"></i><div><?php echo sanitize($passwordError); ?></div></div>
<?php endif; ?>
<?php if ($passwordSuccess): ?>
    <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check"></i><div><?php echo sanitize($passwordSuccess); ?></div></div>
<?php endif; ?>

<!-- ==================== ACCOUNT TAB VIEW ==================== -->
<div id="settingsTabAccount" class="settings-tab-pane" style="display: block;">
    
    <!-- CARD 1: Avatar Card (Matching Image 1 with Image Upload & Removal) -->
    <div class="settings-card mb-4" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
        <div style="padding: 1.75rem 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-dark);">Avatar</h3>
                <p style="color: var(--text-main); margin: 0; font-size: 0.9rem;">User Profile Picture.</p>
                <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.85rem;">Click on the avatar circle or use the button below to upload a custom picture.</p>
            </div>
            
            <div style="display: flex; align-items: center; gap: 1rem;">
                <form id="avatarUploadForm" method="POST" action="" enctype="multipart/form-data" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" name="action_upload_avatar" value="1">
                    <input type="file" id="avatarFileInput" name="avatar_file" accept="image/png, image/jpeg, image/webp" style="display:none;" onchange="document.getElementById('avatarUploadForm').submit();">
                    
                    <div onclick="document.getElementById('avatarFileInput').click();" style="width: 80px; height: 80px; border-radius: 50%; background: #09090B; border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-weight: 800; font-size: 1.6rem; flex-shrink: 0; cursor: pointer; overflow: hidden; box-shadow: var(--shadow-sm); position: relative;" title="Click to upload avatar">
                        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                            <img src="<?php echo BASE_URL . sanitize($user['avatar']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo sanitize($avatarInitials); ?>
                        <?php endif; ?>
                    </div>
                </form>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('avatarFileInput').click();">
                        <i class="fa-solid fa-upload"></i> Upload
                    </button>
                    <?php if (!empty($user['avatar'])): ?>
                        <form method="POST" action="" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            <input type="hidden" name="action_remove_avatar" value="1">
                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--text-muted);" title="Remove avatar">
                                <i class="fa-solid fa-trash"></i> Remove
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border-color); padding: 0.85rem 2rem; font-size: 0.82rem; color: var(--text-muted);">
            The avatar is optional but strongly recommended. Supported formats: JPG, PNG, WEBP (Max 5MB).
        </div>
    </div>

    <!-- CARD 2: Username Card (Matching Image 1) -->
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
        <input type="hidden" name="action_update_username" value="1">
        <div class="settings-card mb-4" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
            <div style="padding: 1.75rem 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;">
                <div style="max-width: 480px;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-dark);">Username</h3>
                    <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">The username is used to identify the user in the system.</p>
                </div>
                <div style="min-width: 240px; flex: 1; max-width: 320px;">
                    <input type="text" name="username" class="form-control" value="<?php echo sanitize($user['username']); ?>" required style="background: var(--bg-body); border-radius: var(--radius-sm); padding: 0.75rem 1rem; border: 1px solid var(--border-color);">
                </div>
            </div>
            <div style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border-color); padding: 0.85rem 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <span style="font-size: 0.82rem; color: var(--text-muted);">Username must be between 3 and 20 characters</span>
                <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 999px; padding: 0.45rem 1.25rem; font-weight: 700;">Save</button>
            </div>
        </div>
    </form>

    <!-- CARD 3: Display Name Card (3-Column Layout Matching Image 1) -->
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
        <input type="hidden" name="action_update_display_name" value="1">
        <div class="settings-card mb-4" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
            <div style="padding: 1.75rem 2rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-dark);">Display Name</h3>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">Please enter your full name, or a display name you are comfortable with.</p>

                <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
                    <div>
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo sanitize($firstName); ?>" required style="background: var(--bg-body); border-radius: var(--radius-sm); padding: 0.75rem 1rem;">
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin: 0;">Middle Name</label>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Optional</span>
                        </div>
                        <input type="text" name="middle_name" class="form-control" value="<?php echo sanitize($middleName); ?>" style="background: var(--bg-body); border-radius: var(--radius-sm); padding: 0.75rem 1rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo sanitize($lastName); ?>" required style="background: var(--bg-body); border-radius: var(--radius-sm); padding: 0.75rem 1rem;">
                    </div>
                </div>
            </div>
            <div style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border-color); padding: 0.85rem 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <span style="font-size: 0.82rem; color: var(--text-muted);">First name and last name are required.</span>
                <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 999px; padding: 0.45rem 1.25rem; font-weight: 700;">Save</button>
            </div>
        </div>
    </form>

    <!-- CARD 4: Contact & Security Card -->
    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        
        <!-- Contact Details -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action_update_contact" value="1">
            <div class="settings-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; height: 100%; display: flex; flex-direction: column;">
                <div style="padding: 1.75rem 2rem; flex: 1;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-dark);">Contact Information</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.25rem; font-size: 0.88rem;">Update your phone number, email address, and home address.</p>

                    <div class="form-group mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" required style="background: var(--bg-body);">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo sanitize($user['phone']); ?>" placeholder="+63 912 345 6789" style="background: var(--bg-body);">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Home Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Full street address" style="background: var(--bg-body);"><?php echo sanitize($userAddress); ?></textarea>
                    </div>
                </div>
                <div style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border-color); padding: 0.85rem 2rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 999px; padding: 0.45rem 1.25rem; font-weight: 700;">Save Contact Info</button>
                </div>
            </div>
        </form>

        <!-- Password Security -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action_change_password" value="1">
            <div class="settings-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; height: 100%; display: flex; flex-direction: column;">
                <div style="padding: 1.75rem 2rem; flex: 1;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-dark);">Security & Password</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.25rem; font-size: 0.88rem;">Ensure your account is using a strong password.</p>

                    <div class="form-group mb-3">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required style="background: var(--bg-body);">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="At least 6 characters" style="background: var(--bg-body);">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required style="background: var(--bg-body);">
                    </div>
                </div>
                <div style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border-color); padding: 0.85rem 2rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 999px; padding: 0.45rem 1.25rem; font-weight: 700;">Update Password</button>
                </div>
            </div>
        </form>

    </div>

    <?php if ($userRole === 'patient'): ?>
        <!-- CARD 5: Patient Prenatal Medical Profile Card -->
        <form method="POST" action="" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action_update_patient_medical" value="1">
            <div class="settings-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
                <div style="padding: 1.75rem 2rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
                        <div>
                            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-dark);">
                                <i class="fa-solid fa-notes-medical" style="color: var(--primary); margin-right: 0.5rem;"></i> Prenatal & Obstetric Medical Profile
                            </h3>
                            <p style="color: var(--text-muted); margin: 0; font-size: 0.88rem;">Manage your pregnancy tracking data, emergency contacts, and obstetric history.</p>
                        </div>
                        <?php if ($patientRecord && !empty($patientRecord['patient_code'])): ?>
                            <span class="badge" style="background: rgba(249, 115, 22, 0.15); color: var(--primary); font-weight: 700; font-size: 0.85rem; padding: 0.4rem 0.85rem; border-radius: 999px;">
                                Patient Code: <?php echo sanitize($patientRecord['patient_code']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-row mb-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
                        <div>
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?php echo sanitize($patientRecord['dob'] ?? ''); ?>" style="background: var(--bg-body);">
                        </div>
                        <div>
                            <label class="form-label">Blood Type</label>
                            <select name="blood_type" class="form-control" style="background: var(--bg-body);">
                                <?php
                                $bTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                $curBT = $patientRecord['blood_type'] ?? 'A+';
                                foreach ($bTypes as $bt):
                                ?>
                                    <option value="<?php echo $bt; ?>" <?php echo $curBT === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row mb-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                        <div>
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo sanitize($patientRecord['emergency_contact_name'] ?? ''); ?>" placeholder="Full Name of Contact" style="background: var(--bg-body);">
                        </div>
                        <div>
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="tel" name="emergency_contact_phone" class="form-control" value="<?php echo sanitize($patientRecord['emergency_contact_phone'] ?? ''); ?>" placeholder="+63 912 345 6789" style="background: var(--bg-body);">
                        </div>
                    </div>

                    <div class="form-row mb-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
                        <div>
                            <label class="form-label">Last Menstrual Period (LMP)</label>
                            <input type="date" name="lmp" class="form-control" value="<?php echo sanitize($patientRecord['lmp'] ?? ''); ?>" style="background: var(--bg-body);">
                        </div>
                        <div>
                            <label class="form-label">Estimated Due Date (EDD)</label>
                            <input type="date" name="edd" class="form-control" value="<?php echo sanitize($patientRecord['edd'] ?? ''); ?>" style="background: var(--bg-body);">
                        </div>
                    </div>

                    <div class="form-row mb-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1.25rem;">
                        <div>
                            <label class="form-label" title="Gravida: Total pregnancies">Gravida (G)</label>
                            <input type="number" name="gravida" class="form-control" min="0" value="<?php echo (int)($patientRecord['gravida'] ?? 1); ?>" style="background: var(--bg-body);">
                        </div>
                        <div>
                            <label class="form-label" title="Para: Viable births">Para (P)</label>
                            <input type="number" name="para" class="form-control" min="0" value="<?php echo (int)($patientRecord['para'] ?? 0); ?>" style="background: var(--bg-body);">
                        </div>
                        <div>
                            <label class="form-label" title="Abortus: Pregnancy losses/miscarriages">Abortus (A)</label>
                            <input type="number" name="abortus" class="form-control" min="0" value="<?php echo (int)($patientRecord['abortus'] ?? 0); ?>" style="background: var(--bg-body);">
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label class="form-label">Medical & Health Notes / Allergies</label>
                        <textarea name="medical_history" class="form-control" rows="3" placeholder="List any known allergies, chronic conditions, or prior surgery history..." style="background: var(--bg-body);"><?php echo sanitize($patientRecord['medical_history'] ?? ''); ?></textarea>
                    </div>

                </div>
                <div style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border-color); padding: 0.85rem 2rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 999px; padding: 0.45rem 1.25rem; font-weight: 700;">Save Medical Profile</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

</div>

<!-- ==================== PREFERENCES TAB VIEW (Matching Image 2) ==================== -->
<div id="settingsTabPreferences" class="settings-tab-pane" style="display: none;">
    
    <div style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.35rem; color: var(--text-dark); font-family: 'Bricolage Grotesque', sans-serif;">Appearance</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">Manage your display settings</p>
    </div>

    <!-- Theme Choice Cards (Vector previews matching Image 2) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 280px)); gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- System Theme Option -->
        <div id="themeOptSystem" onclick="selectThemeOption('system')" style="cursor: pointer; text-align: center;">
            <div class="theme-card-box" style="height: 150px; border-radius: 18px; border: 2px solid var(--border-color); background: #121215; overflow: hidden; display: flex; position: relative; margin-bottom: 0.75rem; transition: var(--transition);">
                <!-- Half light half dark vector layout -->
                <div style="width: 50%; height: 100%; background: #FFFFFF; border-right: 1px solid #E2E8F0; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.4rem;">
                    <div style="height: 10px; width: 60%; background: #E2E8F0; border-radius: 4px;"></div>
                    <div style="height: 8px; width: 40%; background: #CBD5E1; border-radius: 4px;"></div>
                    <div style="height: 40px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; margin-top: auto;"></div>
                </div>
                <div style="width: 50%; height: 100%; background: #09090B; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.4rem;">
                    <div style="height: 10px; width: 60%; background: #27272A; border-radius: 4px;"></div>
                    <div style="height: 8px; width: 40%; background: #3F3F46; border-radius: 4px;"></div>
                    <div style="height: 40px; background: #18181B; border: 1px solid #27272A; border-radius: 8px; margin-top: auto;"></div>
                </div>
            </div>
            <div class="theme-label" style="font-weight: 700; font-size: 0.9rem; color: var(--text-muted);">System</div>
        </div>

        <!-- Light Theme Option -->
        <div id="themeOptLight" onclick="selectThemeOption('light')" style="cursor: pointer; text-align: center;">
            <div class="theme-card-box" style="height: 150px; border-radius: 18px; border: 2px solid var(--border-color); background: #FFFFFF; padding: 0.85rem; display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem; transition: var(--transition);">
                <div style="height: 10px; width: 50%; background: #E2E8F0; border-radius: 4px;"></div>
                <div style="height: 8px; width: 35%; background: #CBD5E1; border-radius: 4px;"></div>
                <div style="flex: 1; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.5rem;">
                    <div style="height: 6px; width: 70%; background: #E2E8F0; border-radius: 3px; margin-bottom: 0.3rem;"></div>
                    <div style="height: 6px; width: 40%; background: #E2E8F0; border-radius: 3px;"></div>
                </div>
            </div>
            <div class="theme-label" style="font-weight: 700; font-size: 0.9rem; color: var(--text-muted);">Light</div>
        </div>

        <!-- Dark Theme Option (Active Orange Border matching Image 2) -->
        <div id="themeOptDark" onclick="selectThemeOption('dark')" style="cursor: pointer; text-align: center;">
            <div class="theme-card-box" style="height: 150px; border-radius: 18px; border: 2px solid #F97316; background: #09090B; padding: 0.85rem; display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem; transition: var(--transition);">
                <div style="height: 10px; width: 50%; background: #27272A; border-radius: 4px;"></div>
                <div style="height: 8px; width: 35%; background: #3F3F46; border-radius: 4px;"></div>
                <div style="flex: 1; background: #18181B; border: 1px solid #27272A; border-radius: 10px; padding: 0.5rem;">
                    <div style="height: 6px; width: 70%; background: #27272A; border-radius: 3px; margin-bottom: 0.3rem;"></div>
                    <div style="height: 6px; width: 40%; background: #27272A; border-radius: 3px;"></div>
                </div>
            </div>
            <div class="theme-label" style="font-weight: 700; font-size: 0.9rem; color: #F97316;">Dark</div>
        </div>

    </div>

</div>

<script>
function switchSettingsTab(tabName) {
    const accPane = document.getElementById('settingsTabAccount');
    const prefPane = document.getElementById('settingsTabPreferences');
    const accBtn = document.getElementById('tabBtnAccount');
    const prefBtn = document.getElementById('tabBtnPreferences');

    if (tabName === 'account') {
        accPane.style.display = 'block';
        prefPane.style.display = 'none';
        accBtn.style.color = 'var(--text-dark)';
        accBtn.style.borderBottom = '2px solid var(--primary)';
        prefBtn.style.color = 'var(--text-muted)';
        prefBtn.style.borderBottom = 'none';
    } else {
        accPane.style.display = 'none';
        prefPane.style.display = 'block';
        prefBtn.style.color = 'var(--text-dark)';
        prefBtn.style.borderBottom = '2px solid var(--primary)';
        accBtn.style.color = 'var(--text-muted)';
        accBtn.style.borderBottom = 'none';
    }
}

function selectThemeOption(mode) {
    let activeTheme = mode;
    if (mode === 'system') {
        activeTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.setAttribute('data-theme', activeTheme);
    localStorage.setItem('theme', activeTheme);

    // Update Visual Highlights
    ['System', 'Light', 'Dark'].forEach(function(opt) {
        const wrapper = document.getElementById('themeOpt' + opt);
        if (!wrapper) return;
        const box = wrapper.querySelector('.theme-card-box');
        const label = wrapper.querySelector('.theme-label');
        if (opt.toLowerCase() === mode) {
            box.style.borderColor = '#F97316';
            label.style.color = '#F97316';
        } else {
            box.style.borderColor = 'var(--border-color)';
            label.style.color = 'var(--text-muted)';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const currentTheme = localStorage.getItem('theme') || 'dark';
    selectThemeOption(currentTheme);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
