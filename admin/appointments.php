<?php
/**
 * Booking Requests & Appointment Management (Administrator)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Booking Requests";
$activePage = "appointments";
$userId = getCurrentUserId();

$successMsg = '';
$errorMsg = '';

/* ============================================
   HANDLE DELETE APPOINTMENT
   ============================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_appointment') {
    $csrf = $_POST['csrf_token'] ?? '';
    $deleteId = (int)($_POST['appointment_id'] ?? 0);

    if (!verifyCsrfToken($csrf)) {
        $errorMsg = "Security token error.";
    } elseif (!$deleteId) {
        $errorMsg = "Invalid appointment ID.";
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT appointment_code FROM appointments WHERE id = ?");
            $stmt->execute([$deleteId]);
            $appointmentCode = $stmt->fetchColumn();

            if (!$appointmentCode) {
                $errorMsg = "Appointment not found.";
            } else {
                $db->beginTransaction();
                $stmt = $db->prepare("DELETE FROM prenatal_records WHERE appointment_id = ?");
                $stmt->execute([$deleteId]);
                $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
                $stmt->execute([$deleteId]);
                $db->commit();

                logAudit('APPOINTMENT_DELETED', "Admin deleted appointment $appointmentCode");
                $successMsg = "Appointment deleted successfully!";
            }
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $errorMsg = "Error deleting appointment: " . $e->getMessage();
        }
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? '';

try {
    $db = getDB();
    $sql = "SELECT a.*, s.service_name, u.full_name as patient_name, u.phone as patient_phone, p.patient_code, hw.full_name as worker_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u ON p.user_id = u.id
            JOIN services s ON a.service_id = s.id
            LEFT JOIN users hw ON a.healthcare_worker_id = hw.id
            WHERE 1=1";
    $params = [];

    if ($statusFilter !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $statusFilter;
    }
    if (!empty($dateFilter)) {
        $sql .= " AND a.appointment_date = ?";
        $params[] = $dateFilter;
    }

    $sql .= " ORDER BY FIELD(a.status, 'pending', 'confirmed', 'completed', 'missed', 'cancelled'), a.appointment_date ASC, a.appointment_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error loading booking requests: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Booking Requests & Appointments</h1>
        <p>Confirm bookings, assign or reassign healthcare staff, and manage appointment status</p>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
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

<div class="dashboard-card" style="padding: 1.25rem;">
    <form method="GET" action="" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
        <select name="status" class="form-control" style="max-width:200px;">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            <option value="missed" <?php echo $statusFilter === 'missed' ? 'selected' : ''; ?>>Missed</option>
        </select>
        <input type="date" name="date" class="form-control" style="max-width:200px;" value="<?php echo sanitize($dateFilter); ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($statusFilter !== 'all' || !empty($dateFilter)): ?>
            <a href="appointments.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Patient</th>
                <th>Service</th>
                <th>Date & Time</th>
                <th>Status</th>
                <th>Assigned Staff</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($appointments) > 0): ?>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><strong><code><?php echo sanitize($a['appointment_code']); ?></code></strong></td>
                        <td>
                            <strong><?php echo sanitize($a['patient_name']); ?></strong><br>
                            <small class="text-muted"><?php echo sanitize($a['patient_code']); ?> &bull; <?php echo sanitize($a['patient_phone']); ?></small>
                        </td>
                        <td><?php echo sanitize($a['service_name']); ?></td>
                        <td>
                            <i class="fa-regular fa-calendar"></i> <?php echo formatDate($a['appointment_date']); ?><br>
                            <small class="text-muted"><i class="fa-regular fa-clock"></i> <?php echo date('g:i A', strtotime($a['appointment_time'])); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span>
                        </td>
                        <td>
                            <?php if (!empty($a['worker_name'])): ?>
                                <i class="fa-solid fa-user-doctor text-primary"></i> <?php echo sanitize($a['worker_name']); ?>
                                <?php if (!empty($a['room'])): ?>
                                    <br><small class="text-muted"><i class="fa-solid fa-door-open"></i> <?php echo sanitize($a['room']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-pending" style="font-size:0.75rem;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                                <?php if ($a['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-primary btn-sm js-assign-btn"
                                        data-appt='<?php echo htmlspecialchars(json_encode([
                                            "id" => (int)$a["id"], "code" => $a["appointment_code"],
                                            "patient_name" => $a["patient_name"], "service_name" => $a["service_name"],
                                            "date" => $a["appointment_date"], "time" => $a["appointment_time"],
                                            "worker_id" => (int)($a["healthcare_worker_id"] ?? 0),
                                            "room" => $a["room"] ?? '',
                                            "date_formatted" => date("M d, Y", strtotime($a["appointment_date"])),
                                            "time_formatted" => date("g:i A", strtotime($a["appointment_time"]))
                                        ]), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <i class="fa-solid fa-user-check"></i> Confirm & Assign
                                    </button>
                                <?php elseif ($a['status'] === 'confirmed'): ?>
                                    <button type="button" class="btn btn-outline btn-sm js-assign-btn"
                                        data-appt='<?php echo htmlspecialchars(json_encode([
                                            "id" => (int)$a["id"], "code" => $a["appointment_code"],
                                            "patient_name" => $a["patient_name"], "service_name" => $a["service_name"],
                                            "date" => $a["appointment_date"], "time" => $a["appointment_time"],
                                            "worker_id" => (int)($a["healthcare_worker_id"] ?? 0),
                                            "room" => $a["room"] ?? '',
                                            "date_formatted" => date("M d, Y", strtotime($a["appointment_date"])),
                                            "time_formatted" => date("g:i A", strtotime($a["appointment_time"])),
                                            "reassign" => true
                                        ]), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <i class="fa-solid fa-user-pen"></i> Reassign
                                    </button>
                                    <button type="button" class="btn btn-outline btn-sm js-missed-btn" style="color:var(--warning);"
                                        data-appt-id="<?php echo (int)$a['id']; ?>">
                                        <i class="fa-solid fa-user-slash"></i> Missed
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="btn btn-outline btn-sm js-delete-btn" style="color:var(--danger);"
                                    data-appt-id="<?php echo (int)$a['id']; ?>"
                                    data-appt-code="<?php echo htmlspecialchars($a['appointment_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-appt-name="<?php echo htmlspecialchars($a['patient_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Delete appointment">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center p-4 text-muted">No appointments found matching your filters.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Confirm / Reassign Modal -->
<div id="assignModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="am_title"><i class="fa-solid fa-user-check text-primary"></i> Confirm Booking & Assign Staff</h3>
            <button type="button" class="js-close-modal" data-modal="assignModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="background:var(--bg-body); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1rem;">
                <p style="margin:0;"><strong id="am_patient">-</strong> &bull; <span id="am_code" class="text-muted"></span></p>
                <p style="margin:0;" class="text-muted"><span id="am_service"></span> &mdash; <span id="am_datetime"></span></p>
            </div>

            <div class="form-group">
                <label class="form-label">Healthcare Staff *</label>
                <select id="am_staff_select" class="form-control">
                    <option value="">Loading staff availability...</option>
                </select>
            </div>

            <div class="form-group" id="am_room_group">
                <label class="form-label">Room / Service Area</label>
                <select id="am_room" class="form-control">
                    <option value="">-- Select Room / Service --</option>
                    <option value="Room 1 - High-Risk Prenatal Screening">Room 1 - High-Risk Prenatal Screening</option>
                    <option value="Room 2 - Laboratory Test (Blood & Urine)">Room 2 - Laboratory Test (Blood & Urine)</option>
                    <option value="Room 3 - Obstetric Ultrasound (Pelvic/3D)">Room 3 - Obstetric Ultrasound (Pelvic/3D)</option>
                    <option value="Room 4 - Postnatal Checkup & Family Planning">Room 4 - Postnatal Checkup & Family Planning</option>
                    <option value="Room 5 - Routine Prenatal Consultation">Room 5 - Routine Prenatal Consultation</option>
                    <option value="Room 6 - Tetanus Toxoid & Maternal Immunization">Room 6 - Tetanus Toxoid & Maternal Immunization</option>
                </select>
            </div>

            <div id="am_error" class="alert alert-danger" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="assignModal">Cancel</button>
            <button type="button" id="am_submit_btn" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> <span id="am_submit_label">Confirm Booking</span>
            </button>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card" style="max-width: 450px;">
        <div class="modal-header" style="border-bottom: 2px solid var(--danger);">
            <h3 style="color: var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Delete Appointment</h3>
            <button type="button" class="js-close-modal" data-modal="deleteModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="text-align:center; padding: 1rem 0;">
                <div style="width:80px; height:80px; border-radius:50%; background:#fee2e2; color:#dc3545; display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; font-size:2rem;">
                    <i class="fa-solid fa-trash"></i>
                </div>
                <h3 style="margin:0 0 0.5rem 0; font-size:1.1rem;">Delete this appointment?</h3>
                <p class="text-muted" style="margin:0; font-size:0.85rem;">
                     action is <strong>permanent</strong> and cannot be undone.

                </p>
            </div>

            <div style="background:var(--bg-body); padding:1reKinim; border-radius:10px; margin-top:1rem;">
                <p style="margin:0 0 0.35rem 0; font-size:0.82rem;"><strong>Code:</strong> <span id="deleteCode">-</span></p>
                <p style="margin:0; font-size:0.82rem;"><strong>Patient:</strong> <span id="deleteName">-</span></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="deleteModal">Cancel</button>
            <button type="button" id="confirmDeleteBtn" class="btn btn-danger">
                <i class="fa-solid fa-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
    <input type="hidden" name="action" value="delete_appointment">
    <input type="hidden" name="appointment_id" id="deleteApptId">
</form>

<script>
(function() {
    'use strict';

    var _adminCsrf = '<?php echo getCsrfToken(); ?>';
    var _baseUrl = '<?php echo BASE_URL; ?>';
    var _currentAssignAppt = null;
    var _isSubmitting = false;
    var _deleteTargetId = null;

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

    function openAssignModal(appt) {
        _currentAssignAppt = appt;
        _isSubmitting = false;

        var elPatient = document.getElementById('am_patient');
        var elCode = document.getElementById('am_code');
        var elService = document.getElementById('am_service');
        var elDatetime = document.getElementById('am_datetime');
        var elError = document.getElementById('am_error');
        var elTitle = document.getElementById('am_title');
        var btn = document.getElementById('am_submit_btn');
        var select = document.getElementById('am_staff_select');
        var roomSelect = document.getElementById('am_room');

        if (elPatient) elPatient.innerText = appt.patient_name;
        if (elCode) elCode.innerText = appt.code;
        if (elService) elService.innerText = appt.service_name;
        if (elDatetime) elDatetime.innerText = appt.date_formatted + ' at ' + appt.time_formatted;
        if (elError) elError.style.display = 'none';

        if (elTitle) {
            elTitle.innerHTML = appt.reassign
                ? '<i class="fa-solid fa-user-pen text-primary"></i> Reassign Staff & Room'
                : '<i class="fa-solid fa-user-check text-primary"></i> Confirm Booking & Assign Staff';
        }

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <span id="am_submit_label">' +
                (appt.reassign ? 'Save Assignment' : 'Confirm Booking') + '</span>';
        }

        if (roomSelect) roomSelect.value = appt.room || '';

        if (select) {
            select.disabled = false;
            select.innerHTML = '<option value="">Loading staff availability...</option>';
        }

        showModal('assignModal');

        var url = _baseUrl + 'api/get_staff_availability.php?date=' + appt.date + '&time=' + appt.time + '&appointment_id=' + appt.id;

        fetch(url)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!select) return;
                select.innerHTML = '<option value="">-- Select Staff --</option>';
                if (data.success && data.staff_roster && data.staff_roster.length > 0) {
                    var preselected = false;
                    var firstVacantId = null;

                    data.staff_roster.forEach(function(s) {
                        var opt = document.createElement('option');
                        opt.value = s.staff_id;
                        var marker = s.is_vacant ? '✓ Vacant' : (s.is_on_duty ? '⚠ ' + s.status_text : '✕ Off Duty');
                        opt.text = s.full_name + ' (' + marker + ')';

                        if (s.is_vacant && !firstVacantId) firstVacantId = s.staff_id;
                        if (appt.worker_id && s.staff_id == appt.worker_id) {
                            opt.selected = true;
                            preselected = true;
                        }
                        select.appendChild(opt);
                    });

                    if (!preselected) {
                        if (appt.worker_id) select.value = appt.worker_id;
                        else if (firstVacantId) select.value = firstVacantId;
                        else if (data.staff_roster[0]) select.value = data.staff_roster[0].staff_id;
                    }
                } else {
                    select.innerHTML = '<option value="">No active staff members found</option>';
                }
            })
            .catch(function() {
                if (select) select.innerHTML = '<option value="">Could not load staff list</option>';
            });
    }

    function submitAssignModal() {
        if (_isSubmitting) return;
        if (!_currentAssignAppt) {
            alert('No appointment selected.');
            return;
        }

        var staffSelect = document.getElementById('am_staff_select');
        var staffId = staffSelect ? staffSelect.value : '';
        var roomSelect = document.getElementById('am_room');
        var roomValue = roomSelect ? roomSelect.value : '';
        var errBox = document.getElementById('am_error');
        var btn = document.getElementById('am_submit_btn');

        if (errBox) errBox.style.display = 'none';

        if (!staffId) {
            var msg = 'Please select a healthcare staff member.';
            if (errBox) { errBox.textContent = msg; errBox.style.display = 'block'; }
            else alert(msg);
            return;
        }

        _isSubmitting = true;

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        }

        var useReassign = !!_currentAssignAppt.reassign;
        var fd = new FormData();
        fd.append('appointment_id', _currentAssignAppt.id);
        fd.append('staff_id', staffId);
        fd.append('csrf_token', _adminCsrf);

        var endpoint = _baseUrl + 'api/update_status.php';
        if (useReassign) {
            endpoint = _baseUrl + 'api/assign_staff.php';
        } else {
            fd.append('status', 'confirmed');
            fd.append('room', roomValue);
        }

        fetch(endpoint, { method: 'POST', body: fd })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    _isSubmitting = false;
                    _currentAssignAppt = null;
                    hideModal('assignModal');
                    window.location.reload();
                } else {
                    var msg = data.message || 'Could not save this assignment.';
                    if (errBox) { errBox.textContent = msg; errBox.style.display = 'block'; }
                    else alert(msg);
                    _isSubmitting = false;
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + (_currentAssignAppt.reassign ? 'Save Assignment' : 'Confirm Booking');
                    }
                }
            })
            .catch(function() {
                var msg = 'Network error. Please try again.';
                if (errBox) { errBox.textContent = msg; errBox.style.display = 'block'; }
                else alert(msg);
                _isSubmitting = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + (_currentAssignAppt.reassign ? 'Save Assignment' : 'Confirm Booking');
                }
            });
    }

    function updateAppointmentStatus(apptId, status) {
        if (!confirm('Are you sure you want to mark this appointment as ' + status + '?')) return;

        var fd = new FormData();
        fd.append('appointment_id', apptId);
        fd.append('status', status);
        fd.append('csrf_token', _adminCsrf);

        fetch(_baseUrl + 'api/update_status.php', { method: 'POST', body: fd })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not update appointment.');
                }
            })
            .catch(function() {
                alert('Network error. Please try again.');
            });
    }

    function openDeleteModal(apptId, apptCode, apptName) {
        _deleteTargetId = apptId;
        var elCode = document.getElementById('deleteCode');
        var elName = document.getElementById('deleteName');
        if (elCode) elCode.textContent = apptCode;
        if (elName) elName.textContent = apptName;

        var btn = document.getElementById('confirmDeleteBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash"></i> Yes, Delete';
        }
        showModal('deleteModal');
    }

    function confirmDelete() {
        if (!_deleteTargetId) return;
        var btn = document.getElementById('confirmDeleteBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
        }
        var input = document.getElementById('deleteApptId');
        if (input) input.value = _deleteTargetId;
        var form = document.getElementById('deleteForm');
        if (form) form.submit();
    }

    document.addEventListener('click', function(e) {
        var closeBtn = e.target.closest('.js-close-modal');
        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            var modalId = closeBtn.getAttribute('data-modal');
            if (modalId) hideModal(modalId);
            return;
        }

        var assignBtn = e.target.closest('.js-assign-btn');
        if (assignBtn) {
            e.preventDefault();
            try {
                var appt = JSON.parse(assignBtn.getAttribute('data-appt'));
                openAssignModal(appt);
            } catch (err) {
                console.error('Failed to parse appointment:', err);
                alert('Error loading appointment data.');
            }
            return;
        }

        var missedBtn = e.target.closest('.js-missed-btn');
        if (missedBtn) {
            e.preventDefault();
            updateAppointmentStatus(missedBtn.getAttribute('data-appt-id'), 'missed');
            return;
        }

        var deleteBtn = e.target.closest('.js-delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            openDeleteModal(
                deleteBtn.getAttribute('data-appt-id'),
                deleteBtn.getAttribute('data-appt-code'),
                deleteBtn.getAttribute('data-appt-name')
            );
            return;
        }

        if (e.target.closest('#confirmDeleteBtn')) {
            e.preventDefault();
            confirmDelete();
            return;
        }

        if (e.target.closest('#am_submit_btn')) {
            e.preventDefault();
            submitAssignModal();
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