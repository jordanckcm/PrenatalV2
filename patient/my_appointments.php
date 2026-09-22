<?php
/**
 * My Appointments Management & Voucher Generator
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('patient');

$pageTitle = "My Appointments";
$activePage = "appointments";
$userId = getCurrentUserId();

$successMsg = $_GET['success'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$userId]);
    $patient = $stmt->fetch();

    // Kung wala'y patient profile, mag-create og bag-o
    if (!$patient) {
        $patientCode = "PN-" . date('Y') . "-" . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
        $insert = $db->prepare("INSERT INTO patients (user_id, patient_code, address, blood_type, gravida, para) VALUES (?, ?, '', 'A+', 1, 0)");
        $insert->execute([$userId, $patientCode]);

        $stmt->execute([$userId]);
        $patient = $stmt->fetch();
    }

    $patientId = $patient['id'] ?? 0;

    $sql = "SELECT a.*, s.service_name, s.duration_minutes, u.full_name as doctor_name, COALESCE(NULLIF(a.room, ''), NULLIF(u.room, ''), 'Room 2 - Prenatal Consultation Room') as room_name FROM appointments a JOIN services s ON a.service_id = s.id LEFT JOIN users u ON a.healthcare_worker_id = u.id WHERE a.patient_id = ?";
    $params = [$patientId];

    if ($statusFilter !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

    $apptStmt = $db->prepare($sql);
    $apptStmt->execute($params);
    $appointments = $apptStmt->fetchAll();

} catch (Exception $e) {
    die("Error loading appointments: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>My Appointments</h1>
        <p>Manage your booked visits and download printable vouchers</p>
    </div>
    <div>
        <a href="book_appointment.php" class="btn btn-accent">
            <i class="fa-solid fa-plus"></i> Book New Appointment
        </a>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success mb-4">
        <i class="fa-solid fa-circle-check"></i>
        <div><?php echo sanitize($successMsg); ?></div>
    </div>
<?php endif; ?>

<!-- Status Filter Tabs -->
<div class="dashboard-card" style="padding: 1rem;">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="my_appointments.php?status=all" class="btn btn-sm <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline'; ?>">All</a>
        <a href="my_appointments.php?status=pending" class="btn btn-sm <?php echo $statusFilter === 'pending' ? 'btn-primary' : 'btn-outline'; ?>">Pending</a>
        <a href="my_appointments.php?status=confirmed" class="btn btn-sm <?php echo $statusFilter === 'confirmed' ? 'btn-primary' : 'btn-outline'; ?>">Confirmed</a>
        <a href="my_appointments.php?status=completed" class="btn btn-sm <?php echo $statusFilter === 'completed' ? 'btn-primary' : 'btn-outline'; ?>">Completed</a>
        <a href="my_appointments.php?status=cancelled" class="btn btn-sm <?php echo $statusFilter === 'cancelled' ? 'btn-primary' : 'btn-outline'; ?>">Cancelled</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Appt Code</th>
                <th>Service Name</th>
                <th>Date & Time</th>
                <th>Assigned Staff</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($appointments) > 0): ?>
                <?php foreach ($appointments as $appt): ?>
                    <tr>
                        <td><strong><code><?php echo sanitize($appt['appointment_code']); ?></code></strong></td>
                        <td><?php echo sanitize($appt['service_name']); ?></td>
                        <td>
                            <i class="fa-regular fa-calendar"></i> <?php echo formatDate($appt['appointment_date']); ?><br>
                            <small class="text-muted"><i class="fa-regular fa-clock"></i> <?php echo date('g:i A', strtotime($appt['appointment_time'])); ?></small>
                        </td>
                        <td><?php echo sanitize($appt['doctor_name'] ?? 'Pending Assignment'); ?></td>
                        <td><span class="badge badge-<?php echo $appt['status']; ?>"><?php echo ucfirst($appt['status']); ?></span></td>
                        <td>
                            <button type="button" onclick="showVoucherModal(<?php echo htmlspecialchars(json_encode($appt)); ?>)" class="btn btn-outline btn-sm" title="View Appointment Slip">
                                <i class="fa-solid fa-receipt"></i> Slip
                            </button>

                            <?php if (in_array($appt['status'], ['pending', 'confirmed'])): ?>
                                <button type="button" onclick="updateAppointmentStatus(<?php echo $appt['id']; ?>, 'cancelled', '<?php echo getCsrfToken(); ?>')" class="btn btn-danger btn-sm" title="Cancel Booking">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center p-4 text-muted">
                        No appointments found under this filter.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Printable Appointment Voucher -->
<div id="voucherModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-ticket text-primary"></i> Appointment Slip Voucher</h3>
            <button type="button" onclick="closeModal('voucherModal')" style="background:none; border:none; cursor:pointer; font-size:1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="printableVoucher">
            <div style="border: 2px dashed var(--primary); padding: 1.5rem; border-radius: var(--radius-md); background: var(--surface);">
                <div class="text-center mb-3">
                    <i class="fa-solid fa-baby-carriage brand-icon" style="font-size: 2rem;"></i>
                    <h3 style="margin-top: 0.25rem;">MaternalCare Prenatal Center</h3>
                    <p class="text-muted" style="font-size:0.8rem;">Official Appointment Confirmation Voucher</p>
                </div>
                <hr style="margin: 1rem 0; border: 0; border-top: 1px solid var(--border-color);">
                <div class="form-row">
                    <div>
                        <small class="text-muted">Voucher Code</small>
                        <h4 id="vCode" class="text-primary">-</h4>
                    </div>
                    <div>
                        <small class="text-muted">Status</small>
                        <h4 id="vStatus" class="text-success">-</h4>
                    </div>
                </div>
                <div class="mt-3">
                    <p><strong>Patient Name:</strong> <?php echo sanitize(getCurrentUserName()); ?></p>
                    <p><strong>Service:</strong> <span id="vService">-</span></p>
                    <p><strong>Date:</strong> <span id="vDate">-</span></p>
                    <p><strong>Time:</strong> <span id="vTime">-</span></p>
                    <p><strong>Staff Assigned:</strong> <span id="vStaff">-</span></p>
                    <p><strong>Assigned Room:</strong> <span id="vRoom" style="font-weight:700; color:var(--primary);">-</span></p>
                </div>

                <hr style="margin: 1rem 0; border: 0; border-top: 1px solid var(--border-color);">
                <p class="text-muted" style="font-size:0.75rem; text-align:center;">Pag maabot sa Clinic, palihug proceed na dayon sa assigned room sa staff sa eksaktong oras sa imong schedule.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('voucherModal')">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="printElement('printableVoucher')">
                <i class="fa-solid fa-print"></i> Print Slip
            </button>
        </div>
    </div>
</div>

<script>
function showVoucherModal(appt) {
    document.getElementById('vCode').innerText = appt.appointment_code;
    document.getElementById('vStatus').innerText = appt.status.toUpperCase();
    document.getElementById('vService').innerText = appt.service_name;
    document.getElementById('vDate').innerText = appt.appointment_date;
    document.getElementById('vTime').innerText = appt.appointment_time;
    
    const staff = appt.doctor_name || 'Assigned Staff';
    const room = appt.room_name || appt.room || 'Room 2 - Prenatal Consultation Room';

    document.getElementById('vStaff').innerText = staff;
    document.getElementById('vRoom').innerText = room;

    openModal('voucherModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>