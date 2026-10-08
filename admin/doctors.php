<?php
/**
 * Doctors Management (Administrator)
 * Admin adds, edits and removes the clinic's doctors.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Doctors";
$activePage = "doctors";

$successMsg = '';
$errorMsg = '';

try {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS `doctors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `full_name` VARCHAR(150) NOT NULL,
        `specialization` VARCHAR(150) DEFAULT NULL,
        `license_no` VARCHAR(60) DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    die("Error preparing doctors table: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = "Security token error. Please refresh and try again.";
    } else {
        $name   = trim($_POST['full_name'] ?? '');
        $spec   = trim($_POST['specialization'] ?? '');
        $lic    = trim($_POST['license_no'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $docId  = (int)($_POST['doctor_id'] ?? 0);

        try {
            if ($action === 'add_doctor' || $action === 'edit_doctor') {
                if ($name === '') {
                    $errorMsg = "Doctor name is required.";
                } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errorMsg = "Please enter a valid email address.";
                } elseif ($action === 'add_doctor') {
                    $stmt = $db->prepare("INSERT INTO doctors (full_name, specialization, license_no, phone, email) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $spec ?: null, $lic ?: null, $phone ?: null, $email ?: null]);
                    logAudit('DOCTOR_CREATED', "Admin added doctor '$name'");
                    $successMsg = "Doctor added successfully!";
                } elseif ($docId) {
                    $status = isset($_POST['is_active']) ? 'active' : 'inactive';
                    $stmt = $db->prepare("UPDATE doctors SET full_name = ?, specialization = ?, license_no = ?, phone = ?, email = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $spec ?: null, $lic ?: null, $phone ?: null, $email ?: null, $status, $docId]);
                    logAudit('DOCTOR_UPDATED', "Admin updated doctor ID $docId");
                    $successMsg = "Doctor updated successfully!";
                }
            } elseif ($action === 'delete_doctor' && $docId) {
                $stmt = $db->prepare("DELETE FROM doctors WHERE id = ?");
                $stmt->execute([$docId]);
                logAudit('DOCTOR_DELETED', "Admin deleted doctor ID $docId");
                $successMsg = "Doctor removed.";
            }
        } catch (Exception $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

$doctors = $db->query("SELECT * FROM doctors ORDER BY status ASC, full_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Doctors</h1>
        <p>Add and manage the clinic's doctors</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" id="openAddDoctor">
            <i class="fa-solid fa-plus"></i> Add Doctor
        </button>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($successMsg); ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger mb-4"><i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($errorMsg); ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Doctor</th>
                <th>Specialization</th>
                <th>License No.</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($doctors) > 0): foreach ($doctors as $d): ?>
            <tr>
                <td><strong><i class="fa-solid fa-user-doctor text-primary"></i> Dr. <?php echo sanitize(preg_replace('/^dr\.?\s+/i', '', $d['full_name'])); ?></strong></td>
                <td><?php echo sanitize($d['specialization'] ?? '—'); ?></td>
                <td><?php echo sanitize($d['license_no'] ?? '—'); ?></td>
                <td>
                    <?php echo sanitize($d['phone'] ?? '—'); ?>
                    <?php if (!empty($d['email'])): ?><br><small class="text-muted"><?php echo sanitize($d['email']); ?></small><?php endif; ?>
                </td>
                <td><span class="badge badge-<?php echo $d['status'] === 'active' ? 'confirmed' : 'cancelled'; ?>"><?php echo ucfirst($d['status']); ?></span></td>
                <td>
                    <div style="display:flex; gap:0.35rem;">
                        <button type="button" class="btn btn-primary btn-sm js-edit-doctor"
                            data-doctor='<?php echo htmlspecialchars(json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>'>
                            <i class="fa-solid fa-pen"></i> Edit
                        </button>
                        <button type="button" class="btn btn-outline btn-sm js-delete-doctor" style="color:var(--danger);"
                            data-id="<?php echo (int)$d['id']; ?>"
                            data-name="<?php echo htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center p-4 text-muted">No doctors yet. Click "Add Doctor" to create one.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit Doctor Modal -->
<div id="doctorModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="dm_title"><i class="fa-solid fa-user-doctor text-primary"></i> Add Doctor</h3>
            <button type="button" class="js-close-modal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="doctorForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" id="dm_action" value="add_doctor">
                <input type="hidden" name="doctor_id" id="dm_id" value="">

                <div class="form-group">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="dm_name" class="form-control" placeholder="e.g. Maria Santos" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Specialization</label>
                    <input type="text" name="specialization" id="dm_spec" class="form-control" placeholder="e.g. Obstetrician-Gynecologist">
                </div>
                <div class="form-group">
                    <label class="form-label">License No.</label>
                    <input type="text" name="license_no" id="dm_license" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="dm_phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="dm_email" class="form-control">
                </div>
                <div class="form-group" id="dm_active_group" style="display:none;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="is_active" id="dm_active" style="width:18px; height:18px;" checked>
                        <span class="form-label" style="margin:0;">Active</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal">Cancel</button>
            <button type="submit" form="doctorForm" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Doctor</button>
        </div>
    </div>
</div>

<form id="deleteDoctorForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input type="hidden" name="action" value="delete_doctor">
    <input type="hidden" name="doctor_id" id="dd_id">
</form>

<script>
(function () {
    'use strict';
    var modal = document.getElementById('doctorModal');
    function show() { modal.style.display = 'flex'; modal.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    function hide() { modal.style.display = 'none'; modal.classList.remove('is-open'); document.body.style.overflow = ''; }
    function val(id, v) { document.getElementById(id).value = v || ''; }

    document.getElementById('openAddDoctor').addEventListener('click', function () {
        document.getElementById('doctorForm').reset();
        document.getElementById('dm_action').value = 'add_doctor';
        val('dm_id', '');
        document.getElementById('dm_title').innerHTML = '<i class="fa-solid fa-user-doctor text-primary"></i> Add Doctor';
        document.getElementById('dm_active_group').style.display = 'none';
        show();
    });

    document.addEventListener('click', function (e) {
        var edit = e.target.closest('.js-edit-doctor');
        if (edit) {
            var d = JSON.parse(edit.getAttribute('data-doctor'));
            document.getElementById('dm_action').value = 'edit_doctor';
            val('dm_id', d.id); val('dm_name', d.full_name); val('dm_spec', d.specialization);
            val('dm_license', d.license_no); val('dm_phone', d.phone); val('dm_email', d.email);
            document.getElementById('dm_active').checked = d.status === 'active';
            document.getElementById('dm_active_group').style.display = 'block';
            document.getElementById('dm_title').innerHTML = '<i class="fa-solid fa-pen text-primary"></i> Edit Doctor';
            show();
            return;
        }
        var del = e.target.closest('.js-delete-doctor');
        if (del) {
            if (confirm('Remove Dr. ' + del.getAttribute('data-name') + '?')) {
                document.getElementById('dd_id').value = del.getAttribute('data-id');
                document.getElementById('deleteDoctorForm').submit();
            }
            return;
        }
        if (e.target.closest('.js-close-modal') || e.target === modal) hide();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
