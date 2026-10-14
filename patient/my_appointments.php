<?php
/**
 * My Appointments Management & Voucher Generator
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slip_helper.php';

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
                            <button type="button" class="btn btn-outline btn-sm js-slip-btn" title="View Appointment Slip"
                                <?php echo slipButtonAttr($appt, getCurrentUserName(), $appt['doctor_name'] ?? 'To be assigned by the clinic'); ?>>
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

<?php include __DIR__ . '/../includes/appointment_slip.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
