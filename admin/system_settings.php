<?php
/**
 * System Settings (Administrator)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "System Settings";
$activePage = "system";
$userId = getCurrentUserId();

$successMsg = '';
$errorMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_settings') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error. Please refresh and try again.";
    } else {
        try {
            $db = getDB();

            $settings = [
                'site_name' => trim($_POST['site_name'] ?? ''),
                'site_description' => trim($_POST['site_description'] ?? ''),
                'contact_email' => trim($_POST['contact_email'] ?? ''),
                'contact_phone' => trim($_POST['contact_phone'] ?? ''),
                'clinic_address' => trim($_POST['clinic_address'] ?? ''),
                'appointment_duration' => (int)($_POST['appointment_duration'] ?? 30),
                'max_advance_booking_days' => (int)($_POST['max_advance_booking_days'] ?? 30),
                'enable_notifications' => isset($_POST['enable_notifications']) ? '1' : '0',
                'enable_patient_registration' => isset($_POST['enable_patient_registration']) ? '1' : '0',
            ];

            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");

            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            logAudit('SETTINGS_UPDATE', "Admin updated system settings");
            $successMsg = "System settings updated successfully!";
        } catch (Exception $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// Kuhaon ang settings
try {
    $db = getDB();
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    $settingsData = [];
    while ($row = $stmt->fetch()) {
        $settingsData[$row['setting_key']] = $row['setting_value'];
    }

    // Defaults
    $defaults = [
        'site_name' => APP_NAME,
        'site_description' => 'Web-Based Prenatal Health Center Booking Appointment and Record Management System',
        'contact_email' => 'info@maternalcare.com',
        'contact_phone' => '+63 912 345 6789',
        'clinic_address' => '123 Health Street, Barangay San Isidro, Metro Manila',
        'appointment_duration' => '30',
        'max_advance_booking_days' => '30',
        'enable_notifications' => '1',
        'enable_patient_registration' => '1',
    ];

    foreach ($defaults as $key => $value) {
        if (!isset($settingsData[$key])) $settingsData[$key] = $value;
    }

} catch (Exception $e) {
    die("Error loading settings: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>System Settings</h1>
        <p>Configure clinic information and system preferences</p>
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

<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input type="hidden" name="action" value="update_settings">

    <!-- Clinic Information -->
    <div class="dashboard-card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h3 style="margin-bottom:0.5rem; font-size:1rem;">
            <i class="fa-solid fa-hospital text-primary"></i> Clinic Information
        </h3>
        <p class="text-muted" style="font-size:0.82rem; margin-bottom:1.25rem;">
            Basic information about your clinic.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Clinic Name</label>
                <input type="text" name="site_name" class="form-control" value="<?php echo sanitize($settingsData['site_name']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Contact Email</label>
                <input type="email" name="contact_email" class="form-control" value="<?php echo sanitize($settingsData['contact_email']); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Contact Phone</label>
                <input type="text" name="contact_phone" class="form-control" value="<?php echo sanitize($settingsData['contact_phone']); ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="site_description" class="form-control" rows="2"><?php echo sanitize($settingsData['site_description']); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Clinic Address</label>
            <textarea name="clinic_address" class="form-control" rows="2"><?php echo sanitize($settingsData['clinic_address']); ?></textarea>
        </div>
    </div>

    <!-- Appointment Settings -->
    <div class="dashboard-card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h3 style="margin-bottom:0.5rem; font-size:1rem;">
            <i class="fa-solid fa-calendar-check text-primary"></i> Appointment Settings
        </h3>
        <p class="text-muted" style="font-size:0.82rem; margin-bottom:1.25rem;">
            Configure default appointment behavior.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Default Appointment Duration (minutes)</label>
                <input type="number" name="appointment_duration" class="form-control" value="<?php echo (int)$settingsData['appointment_duration']; ?>" min="15" max="120">
            </div>

            <div class="form-group">
                <label class="form-label">Max Advance Booking (days)</label>
                <input type="number" name="max_advance_booking_days" class="form-control" value="<?php echo (int)$settingsData['max_advance_booking_days']; ?>" min="1" max="365">
            </div>
        </div>
    </div>

    <!-- System Preferences -->
    <div class="dashboard-card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h3 style="margin-bottom:0.5rem; font-size:1rem;">
            <i class="fa-solid fa-sliders text-primary"></i> System Preferences
        </h3>
        <p class="text-muted" style="font-size:0.82rem; margin-bottom:1.25rem;">
            Toggle system features on or off.
        </p>

        <div class="form-group">
            <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer;">
                <input type="checkbox" name="enable_notifications" value="1" <?php echo $settingsData['enable_notifications'] === '1' ? 'checked' : ''; ?> style="width:18px; height:18px;">
                <div>
                    <strong style="font-size:0.9rem;">Enable Notifications</strong>
                    <p class="text-muted" style="font-size:0.75rem; margin:0;">Send notifications sa patients kung naay appointment updates.</p>
                </div>
            </label>
        </div>

        <div class="form-group">
            <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer;">
                <input type="checkbox" name="enable_patient_registration" value="1" <?php echo $settingsData['enable_patient_registration'] === '1' ? 'checked' : ''; ?> style="width:18px; height:18px;">
                <div>
                    <strong style="font-size:0.9rem;">Enable Patient Registration</strong>
                    <p class="text-muted" style="font-size:0.75rem; margin:0;">Allow patients to register themselves sa landing page.</p>
                </div>
            </label>
        </div>
    </div>

    <!-- Submit -->
    <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-bottom:2rem;">
        <button type="reset" class="btn btn-outline">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fa-solid fa-floppy-disk"></i> Save Settings
        </button>
    </div>

</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>