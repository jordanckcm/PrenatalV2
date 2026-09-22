<?php
/**
 * Patients Directory (Healthcare Worker)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Patients Directory";
$activePage = "patients";
$userId = getCurrentUserId();

$searchQuery = trim($_GET['search'] ?? '');
$successMsg = '';
$errorMsg = '';

/* ============================================
   HANDLE UPDATE PATIENT
   ============================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_patient') {
    $csrf = $_POST['csrf_token'] ?? '';
    $patientId = (int)($_POST['patient_id'] ?? 0);

    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error.";
    } elseif (!$patientId) {
        $errorMsg = "Invalid patient ID.";
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $bloodType = trim($_POST['blood_type'] ?? '');
        $dob = $_POST['dob'] ?? null;
        $lmp = $_POST['lmp'] ?? null;
        $gravida = (int)($_POST['gravida'] ?? 0);
        $para = (int)($_POST['para'] ?? 0);
        $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');

        if ($fullName === '') {
            $errorMsg = "Full name is required.";
        } else {
            try {
                $db = getDB();
                $db->beginTransaction();

                // Update users table
                $stmt = $db->prepare("
                    UPDATE users u
                    JOIN patients p ON p.user_id = u.id
                    SET u.full_name = ?, u.phone = ?, u.email = ?
                    WHERE p.id = ?
                ");
                $stmt->execute([$fullName, $phone ?: null, $email ?: null, $patientId]);

                // Update patients table
                $stmt = $db->prepare("
                    UPDATE patients
                    SET address = ?, blood_type = ?, dob = ?, lmp = ?, gravida = ?, para = ?,
                        emergency_contact_name = ?, emergency_contact_phone = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $address ?: null, $bloodType ?: null, $dob ?: null, $lmp ?: null,
                    $gravida, $para,
                    $emergencyName ?: null, $emergencyPhone ?: null,
                    $patientId
                ]);

                $db->commit();

                logAudit('PATIENT_UPDATED', "Worker updated patient ID $patientId");
                $successMsg = "Patient updated successfully!";
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) $db->rollBack();
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

/* ============================================
   KUHAON ANG MGA PATIENTS
   ============================================ */
try {
    $db = getDB();

    $sql = "SELECT p.*, u.full_name as patient_name, u.phone, u.email
            FROM patients p
            JOIN users u ON p.user_id = u.id
            WHERE 1=1";
    $params = [];

    if (!empty($searchQuery)) {
        $sql .= " AND (u.full_name LIKE ? OR p.patient_code LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
        $like = '%' . $searchQuery . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY u.full_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();

} catch (Exception $e) {
    die("Error loading patients: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Patients Directory</h1>
        <p>View and manage all registered patients</p>
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

<!-- Search -->
<div class="dashboard-card" style="padding: 1.25rem;">
    <form method="GET" action="" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
        <input type="text" name="search" class="form-control" style="max-width:400px;" 
               placeholder="Search by name, code, phone, or email..." 
               value="<?php echo sanitize($searchQuery); ?>">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-search"></i> Search
        </button>
        <?php if (!empty($searchQuery)): ?>
            <a href="patients.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Patients List -->
<div class="dashboard-card" style="padding: 0; overflow: hidden; margin-top: 1rem;">
    <?php if (count($patients) > 0): ?>
        <?php foreach ($patients as $p): ?>
            <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">

                <div style="min-width: 90px;">
                    <span style="background: var(--bg-body); padding: 0.35rem 0.6rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700;">
                        <?php echo sanitize($p['patient_code']); ?>
                    </span>
                </div>

                <div style="min-width: 200px; flex: 1;">
                    <strong style="font-size: 0.95rem; display: block;">
                        <?php echo sanitize($p['patient_name']); ?>
                    </strong>
                    <small class="text-muted" style="font-size: 0.75rem;">
                        Gravida <?php echo (int)($p['gravida'] ?? 1); ?> / 
                        Para <?php echo (int)($p['para'] ?? 0); ?> / 
                        Abortus <?php echo (int)($p['abortus'] ?? 0); ?>
                    </small>
                </div>

                <div style="min-width: 200px;">
                    <small style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;">
                        <i class="fa-solid fa-phone text-primary"></i>
                        <?php echo sanitize($p['phone'] ?? 'N/A'); ?>
                    </small>
                    <small class="text-muted" style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; margin-top: 0.2rem;">
                        <i class="fa-solid fa-envelope"></i>
                        <?php echo sanitize($p['email'] ?? 'N/A'); ?>
                    </small>
                </div>

                <div style="min-width: 150px;">
                    <strong style="color: var(--primary); font-size: 0.88rem; display: block;">
                        <?php echo !empty($p['edd']) ? formatDate($p['edd']) : 'N/A'; ?>
                    </strong>
                    <?php if (!empty($p['gestational_age_weeks'])): ?>
                        <span style="background: #e8f5e9; color: #2e7d32; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.7rem; font-weight: 700; display: inline-block; margin-top: 0.25rem;">
                            <?php echo (int)$p['gestational_age_weeks']; ?> WEEKS GA
                        </span>
                    <?php endif; ?>
                </div>

                <div style="min-width: 60px;">
                    <span style="background: #fff8e1; color: #f57c00; padding: 0.35rem 0.7rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">
                        <?php echo sanitize($p['blood_type'] ?? 'N/A'); ?>
                    </span>
                </div>

                <div style="min-width: 180px;">
                    <small style="display: block; font-size: 0.82rem;">
                        <?php echo sanitize($p['emergency_contact_name'] ?? 'N/A'); ?>
                    </small>
                    <small class="text-muted" style="font-size: 0.75rem;">
                        <?php echo sanitize($p['emergency_contact_phone'] ?? ''); ?>
                    </small>
                </div>

                <!-- Actions: Edit Button Only -->
                <div style="margin-left: auto;">
                    <button type="button" class="btn btn-outline btn-sm js-edit-patient" style="min-width: 100px;"
                        data-patient='<?php echo htmlspecialchars(json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>'>
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                </div>

            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding: 3rem; text-align: center;">
            <i class="fa-solid fa-users text-muted" style="font-size: 2.5rem;"></i>
            <p class="text-muted" style="margin-top: 1rem;">
                <?php echo !empty($searchQuery) ? 'No patients found matching your search.' : 'No patients registered yet.'; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- EDIT PATIENT MODAL -->
<!-- ============================================ -->
<div id="editPatientModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen text-primary"></i> Edit Patient Information</h3>
            <button type="button" class="js-close-modal" data-modal="editPatientModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="editPatientForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_patient">
                <input type="hidden" name="patient_id" id="ep_id">

                <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:800; margin-bottom:0.75rem; padding-bottom:0.35rem; border-bottom:2px solid var(--primary);">
                    <i class="fa-solid fa-user text-primary"></i> Personal Information
                </h4>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="ep_full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Patient Code</label>
                        <input type="text" id="ep_patient_code" class="form-control" readonly style="background:var(--bg-body); cursor:not-allowed;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="ep_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="ep_email" class="form-control">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" id="ep_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" id="ep_blood_type" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                </div>

                <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:800; margin:1.25rem 0 0.75rem 0; padding-bottom:0.35rem; border-bottom:2px solid var(--primary);">
                    <i class="fa-solid fa-baby text-primary"></i> Pregnancy Information
                </h4>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">LMP</label>
                        <input type="date" name="lmp" id="ep_lmp" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gravida</label>
                        <input type="number" name="gravida" id="ep_gravida" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Para</label>
                        <input type="number" name="para" id="ep_para" class="form-control" min="0">
                    </div>
                </div>

                <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:800; margin:1.25rem 0 0.75rem 0; padding-bottom:0.35rem; border-bottom:2px solid var(--primary);">
                    <i class="fa-solid fa-address-book text-primary"></i> Contact & Emergency
                </h4>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Home Address</label>
                    <textarea name="address" id="ep_address" class="form-control" rows="2"></textarea>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" id="ep_emergency_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" id="ep_emergency_phone" class="form-control">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="editPatientModal">Cancel</button>
            <button type="submit" form="editPatientForm" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

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

    function openEditModal(p) {
        var setVal = function(id, val) {
            var el = document.getElementById(id);
            if (el) el.value = val || '';
        };

        setVal('ep_id', p.id);
        setVal('ep_full_name', p.patient_name);
        setVal('ep_patient_code', p.patient_code);
        setVal('ep_phone', p.phone);
        setVal('ep_email', p.email);
        setVal('ep_dob', p.dob);
        setVal('ep_blood_type', p.blood_type);
        setVal('ep_lmp', p.lmp);
        setVal('ep_gravida', p.gravida || 0);
        setVal('ep_para', p.para || 0);
        setVal('ep_address', p.address);
        setVal('ep_emergency_name', p.emergency_contact_name);
        setVal('ep_emergency_phone', p.emergency_contact_phone);

        showModal('editPatientModal');
    }

    document.addEventListener('click', function(e) {
        var editBtn = e.target.closest('.js-edit-patient');
        if (editBtn) {
            e.preventDefault();
            try {
                var p = JSON.parse(editBtn.getAttribute('data-patient'));
                openEditModal(p);
            } catch (err) {
                console.error('Failed to parse patient:', err);
                alert('Error loading patient data.');
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