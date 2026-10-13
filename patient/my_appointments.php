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

    $sql = "SELECT a.*, s.service_name, s.duration_minutes, u.full_name as doctor_name FROM appointments a JOIN services s ON a.service_id = s.id LEFT JOIN users u ON a.healthcare_worker_id = u.id WHERE a.patient_id = ?";
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
        <p>Manage your booked visits and print your appointment slip</p>
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

<!-- Modal Printable Appointment Slip (styled to match the login page: sky blue) -->
<style>
.slip-card {
    border-radius: 16px;
    overflow: hidden;
    background: #ffffff;
    border: 1.5px solid #bae6fd;
    box-shadow: 0 10px 30px rgba(14, 165, 233, 0.15);
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #0f172a;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.slip-head {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 50%, #7dd3fc 100%);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.slip-logo {
    width: 46px; height: 46px;
    background: #0ea5e9;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #ffffff; font-size: 1.4rem;
    box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3);
    flex-shrink: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.slip-head h3 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.2rem; font-weight: 800; color: #0f172a;
    letter-spacing: -0.4px; line-height: 1.15; margin: 0;
}
.slip-head small {
    font-size: 0.68rem; font-weight: 700; color: #0369a1;
    letter-spacing: 0.8px; text-transform: uppercase;
}
.slip-codebar {
    display: flex; justify-content: space-between; align-items: center; gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 2px dashed #bae6fd;
}
.slip-label {
    display: block;
    font-size: 0.68rem; font-weight: 800; letter-spacing: 0.8px;
    text-transform: uppercase; color: #64748b; margin-bottom: 0.15rem;
}
.slip-code {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.25rem; font-weight: 800; color: #0284c7; letter-spacing: 0.5px;
}
.slip-status {
    display: inline-block;
    padding: 0.3rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 800; letter-spacing: 0.6px;
    background: #e0f2fe; color: #0369a1;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.slip-status.s-confirmed, .slip-status.s-completed { background: #dcfce7; color: #166534; }
.slip-status.s-pending { background: #fef3c7; color: #92400e; }
.slip-status.s-cancelled, .slip-status.s-missed { background: #fee2e2; color: #991b1b; }
.slip-body { padding: 1.1rem 1.5rem 0.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem 1.25rem; }
.slip-body .full { grid-column: 1 / -1; }
.slip-value { font-size: 0.98rem; font-weight: 700; color: #0f172a; }
.slip-foot {
    margin: 0.75rem 1.5rem 1.25rem;
    padding: 0.75rem 1rem;
    background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 8px;
    font-size: 0.78rem; color: #334155; line-height: 1.5;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
@media (max-width: 480px) { .slip-body { grid-template-columns: 1fr; } }
</style>

<div id="voucherModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-ticket" style="color:#0ea5e9;"></i> Appointment Slip</h3>
            <button type="button" onclick="closeModal('voucherModal')" style="background:none; border:none; cursor:pointer; font-size:1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="printableVoucher">
            <div class="slip-card">
                <div class="slip-head">
                    <div class="slip-logo"><i class="fa-solid fa-baby-carriage"></i></div>
                    <div>
                        <h3>MaternalCare Prenatal Center</h3>
                        <small>Appointment Confirmation Slip</small>
                    </div>
                </div>
                <div class="slip-codebar">
                    <div>
                        <span class="slip-label">Appointment Code</span>
                        <span class="slip-code" id="vCode">-</span>
                    </div>
                    <span class="slip-status" id="vStatus">-</span>
                </div>
                <div class="slip-body">
                    <div class="full">
                        <span class="slip-label">Patient</span>
                        <span class="slip-value"><?php echo sanitize(getCurrentUserName()); ?></span>
                    </div>
                    <div class="full">
                        <span class="slip-label">Service</span>
                        <span class="slip-value" id="vService">-</span>
                    </div>
                    <div>
                        <span class="slip-label">Date</span>
                        <span class="slip-value" id="vDate">-</span>
                    </div>
                    <div>
                        <span class="slip-label">Time</span>
                        <span class="slip-value" id="vTime">-</span>
                    </div>
                    <div class="full">
                        <span class="slip-label">Assigned Staff</span>
                        <span class="slip-value" id="vStaff">-</span>
                    </div>
                </div>
                <div class="slip-foot">
                    Please arrive a few minutes before your scheduled time and present this slip to the clinic staff.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('voucherModal')">Close</button>
            <button type="button" class="btn btn-sm" style="background:#0ea5e9; color:#fff; border:none;" onclick="printElement('printableVoucher')">
                <i class="fa-solid fa-print"></i> Print Slip
            </button>
        </div>
    </div>
</div>

<script>
function showVoucherModal(appt) {
    // Friendly date / time (the database gives 2026-10-14 and 08:00:00)
    var d = new Date((appt.appointment_date || '') + 'T00:00:00');
    var dateText = isNaN(d) ? appt.appointment_date : d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    var tp = String(appt.appointment_time || '').split(':');
    var hh = parseInt(tp[0], 10), mm = tp[1] || '00';
    var timeText = isNaN(hh) ? appt.appointment_time : ((hh % 12) || 12) + ':' + mm + ' ' + (hh >= 12 ? 'PM' : 'AM');

    var status = String(appt.status || '').toLowerCase();
    var statusEl = document.getElementById('vStatus');
    statusEl.innerText = status.toUpperCase();
    statusEl.className = 'slip-status s-' + status;

    document.getElementById('vCode').innerText = appt.appointment_code;
    document.getElementById('vService').innerText = appt.service_name;
    document.getElementById('vDate').innerText = dateText;
    document.getElementById('vTime').innerText = timeText;
    document.getElementById('vStaff').innerText = appt.doctor_name || 'To be assigned by the clinic';

    openModal('voucherModal');
}
</script>
