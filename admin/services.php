<?php
/**
 * Clinic Services Management (Administrator)
 * With Editable Examination Form per Service
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Clinic Services";
$activePage = "services";
$userId = getCurrentUserId();

$successMsg = '';
$errorMsg = '';

// ============================================
// DEFAULT FORM FIELDS TEMPLATES PER SERVICE TYPE
// ============================================
$defaultForms = [
    'routine' => [
        ['key' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 62.50'],
        ['key' => 'systolic_bp', 'label' => 'Systolic BP (mmHg)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 118'],
        ['key' => 'diastolic_bp', 'label' => 'Diastolic BP (mmHg)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 76'],
        ['key' => 'temperature', 'label' => 'Temperature (°C)', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 36.8'],
        ['key' => 'pulse_rate', 'label' => 'Pulse Rate (bpm)', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 80'],
        ['key' => 'fetal_heart_rate', 'label' => 'Fetal Heart Rate (bpm)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 140'],
        ['key' => 'fundal_height_cm', 'label' => 'Fundal Height (cm)', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 26'],
        ['key' => 'gestational_age_weeks', 'label' => 'Gestational Age (weeks)', 'type' => 'number', 'required' => false, 'placeholder' => 'e.g. 26'],
        ['key' => 'fetal_presentation', 'label' => 'Fetal Presentation', 'type' => 'select', 'required' => false, 'options' => ['Cephalic', 'Breech', 'Transverse', 'Oblique']],
        ['key' => 'edema', 'label' => 'Edema', 'type' => 'select', 'required' => false, 'options' => ['none', 'mild', 'moderate', 'severe']],
    ],
    'ultrasound' => [
        ['key' => 'fetal_heart_rate', 'label' => 'Fetal Heart Rate (bpm)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 140'],
        ['key' => 'fetal_presentation', 'label' => 'Fetal Presentation', 'type' => 'select', 'required' => false, 'options' => ['Cephalic', 'Breech', 'Transverse', 'Oblique']],
        ['key' => 'fundal_height_cm', 'label' => 'Fundal Height (cm)', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 26'],
        ['key' => 'gestational_age_weeks', 'label' => 'Gestational Age (weeks)', 'type' => 'number', 'required' => false, 'placeholder' => 'e.g. 26'],
    ],
    'lab' => [
        ['key' => 'urine_protein', 'label' => 'Urine Protein', 'type' => 'select', 'required' => true, 'options' => ['Negative', 'Trace', '1+', '2+', '3+', '4+']],
        ['key' => 'urine_sugar', 'label' => 'Urine Sugar', 'type' => 'select', 'required' => true, 'options' => ['Negative', 'Trace', '1+', '2+', '3+', '4+']],
        ['key' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 62.50'],
        ['key' => 'systolic_bp', 'label' => 'Systolic BP (mmHg)', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 118'],
        ['key' => 'diastolic_bp', 'label' => 'Diastolic BP (mmHg)', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 76'],
    ],
    'vaccine' => [
        ['key' => 'tetanus_vaccine_given', 'label' => 'Tetanus Vaccine Given', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. TT2'],
        ['key' => 'vitamins_prescribed', 'label' => 'Vitamins Prescribed', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. Prenatal Multivitamins'],
        ['key' => 'iron_folic_given', 'label' => 'Iron / Folic Acid Given', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 1 tablet daily'],
    ],
];

// Get default form based on service name
function getDefaultFormForService($serviceName, $defaultForms) {
    $name = strtolower($serviceName);
    if (strpos($name, 'ultrasound') !== false || strpos($name, 'pelvic') !== false || strpos($name, '3d') !== false) {
        return $defaultForms['ultrasound'];
    }
    if (strpos($name, 'lab') !== false || strpos($name, 'blood') !== false || strpos($name, 'urine') !== false) {
        return $defaultForms['lab'];
    }
    if (strpos($name, 'tetanus') !== false || strpos($name, 'immunization') !== false || strpos($name, 'vaccine') !== false) {
        return $defaultForms['vaccine'];
    }
    return $defaultForms['routine'];
}

// ============================================
// HANDLE UPDATE SERVICE (with form fields)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_service') {
    $csrf = $_POST['csrf_token'] ?? '';
    $serviceId = (int)($_POST['service_id'] ?? 0);

    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error.";
    } elseif (!$serviceId) {
        $errorMsg = "Invalid service ID.";
    } else {
        $serviceName = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 30);
        $maxDaily = (int)($_POST['max_daily_booking'] ?? 20);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $formFieldsJson = $_POST['form_fields_json'] ?? '[]';

        // Validate JSON
        $decoded = json_decode($formFieldsJson, true);
        if (!is_array($decoded)) {
            $formFieldsJson = '[]';
        }

        if ($serviceName === '') {
            $errorMsg = "Service name is required.";
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    UPDATE services 
                    SET service_name = ?, description = ?, duration_minutes = ?, 
                        is_active = ?, form_fields = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$serviceName, $description ?: null, $duration, $isActive, $formFieldsJson, $serviceId]);

                logAudit('SERVICE_UPDATED', "Admin updated service ID $serviceId");
                $successMsg = "Service updated successfully!";
            } catch (Exception $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// HANDLE ADD SERVICE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_service') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error.";
    } else {
        $serviceName = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 30);
        $isActive = 1;

        if ($serviceName === '') {
            $errorMsg = "Service name is required.";
        } else {
            try {
                $db = getDB();
                $defaultForm = json_encode(getDefaultFormForService($serviceName, $defaultForms));
                $stmt = $db->prepare("
                    INSERT INTO services (service_name, description, duration_minutes, is_active, form_fields, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$serviceName, $description ?: null, $duration, $isActive, $defaultForm]);

                logAudit('SERVICE_CREATED', "Admin created service '$serviceName'");
                $successMsg = "Service added successfully!";
            } catch (Exception $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// HANDLE DEACTIVATE SERVICE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate_service') {
    $csrf = $_POST['csrf_token'] ?? '';
    $serviceId = (int)($_POST['service_id'] ?? 0);
    if (verifyCsrfToken($csrf) && $serviceId) {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE services SET is_active = 0 WHERE id = ?");
            $stmt->execute([$serviceId]);
            logAudit('SERVICE_DEACTIVATED', "Admin deactivated service ID $serviceId");
            $successMsg = "Service deactivated successfully!";
        } catch (Exception $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// ============================================
// KUHAON ANG TANAN SERVICES
// ============================================
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM services ORDER BY is_active DESC, service_name ASC");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Clinic Services</h1>
        <p>Manage clinic services, examination forms, and booking settings</p>
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
        <strong>Note:</strong> Each service has a **custom examination form**. Click "Edit" to view and edit the fields displayed on the Examine page.
    </div>
</div>

<!-- Services Table -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Service Name</th>
                <th>Description</th>
                <th>Duration</th>
                <th>Form Fields</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($services) > 0): ?>
                <?php foreach ($services as $s): ?>
                    <?php
                    $formFields = json_decode($s['form_fields'] ?? '[]', true);
                    $fieldCount = is_array($formFields) ? count($formFields) : 0;
                    ?>
                    <tr>
                        <td><span class="badge badge-pending" style="font-size:0.7rem;">#<?php echo (int)$s['id']; ?></span></td>
                        <td><strong><?php echo sanitize($s['service_name']); ?></strong></td>
                        <td><small class="text-muted"><?php echo sanitize($s['description'] ?? '—'); ?></small></td>
                        <td><?php echo (int)($s['duration_minutes'] ?? 30); ?> mins</td>
                        <td>
                            <span class="badge badge-confirmed" style="font-size:0.7rem;">
                                <i class="fa-solid fa-list-check"></i> <?php echo $fieldCount; ?> fields
                            </span>
                        </td>
                        <td>
                            <?php if ($s['is_active']): ?>
                                <span class="badge badge-confirmed">Active</span>
                            <?php else: ?>
                                <span class="badge badge-cancelled">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                                <button type="button" class="btn btn-primary btn-sm js-edit-service"
                                    data-service='<?php echo htmlspecialchars(json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>'>
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                                <?php if ($s['is_active']): ?>
                                    <button type="button" class="btn btn-outline btn-sm js-deactivate-service" style="color:var(--danger);"
                                        data-service-id="<?php echo (int)$s['id']; ?>"
                                        data-service-name="<?php echo htmlspecialchars($s['service_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center p-4 text-muted">
                        No services found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ============================================ -->
<!-- EDIT SERVICE MODAL (WITH FORM FIELDS EDITOR) -->
<!-- ============================================ -->
<div id="editServiceModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen text-primary"></i> Edit Healthcare Service</h3>
            <button type="button" class="js-close-modal" data-modal="editServiceModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="editServiceForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_service">
                <input type="hidden" name="service_id" id="es_id">
                <input type="hidden" name="form_fields_json" id="es_form_fields_json" value="[]">

                <!-- Service Details -->
                <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:800; margin-bottom:0.75rem; padding-bottom:0.35rem; border-bottom:2px solid var(--primary);">
                    <i class="fa-solid fa-stethoscope text-primary"></i> Service Details
                </h4>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Service Name <span class="text-danger">*</span></label>
                    <input type="text" name="service_name" id="es_name" class="form-control" required>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="es_description" class="form-control" rows="2"></textarea>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">Duration (Minutes)</label>
                        <input type="number" name="duration_minutes" id="es_duration" class="form-control" min="5" max="300">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Daily Booking Limit</label>
                        <input type="number" name="max_daily_booking" id="es_max_daily" class="form-control" min="1" max="100">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="is_active" id="es_active" style="width:18px; height:18px;">
                        <span class="form-label" style="margin:0;">Service Active for Booking</span>
                    </label>
                </div>

                <!-- Examination Form Fields Editor -->
                <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:800; margin-bottom:0.75rem; padding-bottom:0.35rem; border-bottom:2px solid var(--primary);">
                    <i class="fa-solid fa-list-check text-primary"></i> Examination Form Fields
                </h4>
                <p class="text-muted" style="font-size:0.8rem; margin-bottom:1rem;">
                    These are the fields that appear on the Examine page for that service.
                </p>

                <div id="formFieldsContainer" style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1rem;">
                    <!-- Fields ma-populate via JavaScript -->
                </div>

                <button type="button" class="btn btn-outline btn-sm" onclick="addFormField()" style="width:100%;">
                    <i class="fa-solid fa-plus"></i> Add Field
                </button>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="editServiceModal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitEditService()">
                <i class="fa-solid fa-floppy-disk"></i> Save Service
            </button>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- ADD SERVICE MODAL -->
<!-- ============================================ -->
<div id="addServiceModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus text-primary"></i> Add New Service</h3>
            <button type="button" class="js-close-modal" data-modal="addServiceModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="addServiceForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" value="add_service">

                <div class="form-group">
                    <label class="form-label">Service Name <span class="text-danger">*</span></label>
                    <input type="text" name="service_name" class="form-control" placeholder="e.g. Dental Checkup" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" class="form-control" min="5" max="300" value="30">
                </div>

                <div class="alert alert-info" style="font-size:0.8rem;">
                    <i class="fa-solid fa-info-circle"></i>
                    <div>Pagkahuman ma-create, i-click ang "Edit" para ma-customize ang examination form fields.</div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="addServiceModal">Cancel</button>
            <button type="submit" form="addServiceForm" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Add Service
            </button>
        </div>
    </div>
</div>

<!-- Deactivate Form -->
<form id="deactivateForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input type="hidden" name="action" value="deactivate_service">
    <input type="hidden" name="service_id" id="deactivateServiceId">
</form>

<style>
.form-field-row {
    display: grid;
    grid-template-columns: 2fr 2fr 1fr 0.7fr 0.5fr;
    gap: 0.5rem;
    align-items: center;
    padding: 0.6rem;
    background: var(--bg-body);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.form-field-row input,
.form-field-row select {
    padding: 0.4rem 0.6rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--surface);
    color: var(--text-dark);
    font-size: 0.8rem;
    font-family: inherit;
    width: 100%;
    box-sizing: border-box;
}
.form-field-row .delete-field-btn {
    background: transparent;
    border: none;
    color: var(--danger);
    cursor: pointer;
    font-size: 1rem;
    padding: 0.3rem;
}
@media (max-width: 768px) {
    .form-field-row {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<script>
(function() {
    'use strict';

    var _formFields = [];

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

    function renderFormFields() {
        var container = document.getElementById('formFieldsContainer');
        if (!container) return;

        container.innerHTML = '';

        if (_formFields.length === 0) {
            container.innerHTML = '<p class="text-muted text-center" style="font-size:0.82rem; padding:1rem; border:1px dashed var(--border-color); border-radius:8px;">No fields. Click "Add Field" to add one.</p>';
            return;
        }

        _formFields.forEach(function(field, index) {
            var row = document.createElement('div');
            row.className = 'form-field-row';
            row.innerHTML = 
                '<input type="text" value="' + (field.label || '') + '" placeholder="Label" onchange="_updateField(' + index + ', \'label\', this.value)">' +
                '<input type="text" value="' + (field.key || '') + '" placeholder="Key (e.g. weight_kg)" onchange="_updateField(' + index + ', \'key\', this.value)">' +
                '<select onchange="_updateField(' + index + ', \'type\', this.value)">' +
                    '<option value="text"' + (field.type === 'text' ? ' selected' : '') + '>Text</option>' +
                    '<option value="number"' + (field.type === 'number' ? ' selected' : '') + '>Number</option>' +
                    '<option value="select"' + (field.type === 'select' ? ' selected' : '') + '>Select</option>' +
                    '<option value="textarea"' + (field.type === 'textarea' ? ' selected' : '') + '>Textarea</option>' +
                '</select>' +
                '<select onchange="_updateField(' + index + ', \'required\', this.value === \'1\')">' +
                    '<option value="1"' + (field.required ? ' selected' : '') + '>Required</option>' +
                    '<option value="0"' + (!field.required ? ' selected' : '') + '>Optional</option>' +
                '</select>' +
                '<button type="button" class="delete-field-btn" onclick="_removeField(' + index + ')">' +
                    '<i class="fa-solid fa-trash"></i>' +
                '</button>';

            container.appendChild(row);
        });

        document.getElementById('es_form_fields_json').value = JSON.stringify(_formFields);
    }

    window._updateField = function(index, key, value) {
        if (_formFields[index]) {
            _formFields[index][key] = value;
            document.getElementById('es_form_fields_json').value = JSON.stringify(_formFields);
        }
    };

    window._removeField = function(index) {
        if (confirm('Remove this field?')) {
            _formFields.splice(index, 1);
            renderFormFields();
        }
    };

    window.addFormField = function() {
        _formFields.push({
            key: 'field_' + Date.now(),
            label: 'New Field',
            type: 'text',
            required: false,
            placeholder: ''
        });
        renderFormFields();
    };

    window.submitEditService = function() {
        document.getElementById('es_form_fields_json').value = JSON.stringify(_formFields);
        document.getElementById('editServiceForm').submit();
    };

    function openEditModal(service) {
        document.getElementById('es_id').value = service.id;
        document.getElementById('es_name').value = service.service_name || '';
        document.getElementById('es_description').value = service.description || '';
        document.getElementById('es_duration').value = service.duration_minutes || 30;
        document.getElementById('es_max_daily').value = service.max_daily_booking || 20;
        document.getElementById('es_active').checked = parseInt(service.is_active) === 1;

        // Parse form fields
        try {
            _formFields = JSON.parse(service.form_fields || '[]');
            if (!Array.isArray(_formFields)) _formFields = [];
        } catch (e) {
            _formFields = [];
        }

        renderFormFields();
        showModal('editServiceModal');
    }

    document.addEventListener('click', function(e) {
        var editBtn = e.target.closest('.js-edit-service');
        if (editBtn) {
            e.preventDefault();
            try {
                var service = JSON.parse(editBtn.getAttribute('data-service'));
                openEditModal(service);
            } catch (err) {
                console.error('Failed to parse service:', err);
                alert('Error loading service data.');
            }
            return;
        }

        var deactivateBtn = e.target.closest('.js-deactivate-service');
        if (deactivateBtn) {
            e.preventDefault();
            if (confirm('Deactivate "' + deactivateBtn.getAttribute('data-service-name') + '"?')) {
                document.getElementById('deactivateServiceId').value = deactivateBtn.getAttribute('data-service-id');
                document.getElementById('deactivateForm').submit();
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