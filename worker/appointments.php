<?php
/**
 * Appointment Management (Healthcare Worker)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Appointment Management";
$activePage = "appointments";
$userId = getCurrentUserId();

$statusFilter = $_GET['status'] ?? 'all';
$staffFilter = $_GET['staff'] ?? 'all';
$dateFilter = $_GET['date'] ?? '';

try {
    $db = getDB();

    $sql = "SELECT a.*, s.service_name, u.full_name as patient_name, u.phone as patient_phone, 
            p.patient_code, hw.full_name as worker_name
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

    if ($staffFilter === 'mine') {
        $sql .= " AND a.healthcare_worker_id = ?";
        $params[] = $userId;
    }

    if (!empty($dateFilter)) {
        $sql .= " AND a.appointment_date = ?";
        $params[] = $dateFilter;
    }

    $sql .= " ORDER BY FIELD(a.status, 'confirmed', 'pending', 'completed', 'missed', 'cancelled'), a.appointment_date ASC, a.appointment_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

} catch (Exception $e) {
    die("Error loading appointments: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Appointment Management</h1>
        <p>View clinic appointments, attend to confirmed patients, and record examinations</p>
    </div>
</div>

<!-- Filters -->
<div class="dashboard-card" style="padding: 1.25rem;">
    <form method="GET" action="" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
        <select name="status" class="form-control" style="max-width:200px;">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="missed" <?php echo $statusFilter === 'missed' ? 'selected' : ''; ?>>Missed</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>

        <select name="staff" class="form-control" style="max-width:220px;">
            <option value="all" <?php echo $staffFilter === 'all' ? 'selected' : ''; ?>>All Staff Appointments</option>
            <option value="mine" <?php echo $staffFilter === 'mine' ? 'selected' : ''; ?>>Assigned to Me</option>
        </select>

        <input type="date" name="date" class="form-control" style="max-width:200px;" value="<?php echo sanitize($dateFilter); ?>">

        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        <?php if ($statusFilter !== 'all' || $staffFilter !== 'all' || !empty($dateFilter)): ?>
            <a href="appointments.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Patient Details</th>
                <th>Service Requested</th>
                <th>Date & Time</th>
                <th>Status</th>
                <th>Assigned Staff</th>
                <th>Management Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($appointments) > 0): ?>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><strong><code><?php echo sanitize($a['appointment_code']); ?></code></strong></td>
                        <td>
                            <strong><?php echo sanitize($a['patient_name']); ?></strong><br>
                            <small class="text-muted"><?php echo sanitize($a['patient_code']); ?> | <?php echo sanitize($a['patient_phone']); ?></small>
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
                                <?php if ((int)$a['healthcare_worker_id'] === (int)$userId): ?>
                                    <span class="badge badge-confirmed" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-user-check"></i> ASSIGNED TO YOU
                                    </span>
                                <?php else: ?>
                                    <i class="fa-solid fa-user-doctor text-primary"></i> <?php echo sanitize($a['worker_name']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-pending" style="font-size:0.75rem;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['status'] === 'confirmed'): ?>
                                <a href="examine.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-stethoscope"></i> Examine
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:0.82rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center p-4 text-muted">
                        No appointments found matching your filters.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>