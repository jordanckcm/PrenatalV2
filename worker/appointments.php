<?php
/**
 * Appointment Management (Healthcare Worker)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slip_helper.php';

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
            p.patient_code
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u ON p.user_id = u.id
            JOIN services s ON a.service_id = s.id
            WHERE (a.healthcare_worker_id = ?
                   OR (a.healthcare_worker_id IS NULL AND a.status = 'pending'))";
    // PRIVACY: a staff member only ever sees (1) appointments assigned to them and
    // (2) open pending bookings nobody has taken yet. Other staff's patients and
    // names are never loaded, so they cannot appear in the page or its HTML.
    $params = [$userId];

    if ($statusFilter !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $statusFilter;
    }

    if ($staffFilter === 'mine') {
        $sql .= " AND a.healthcare_worker_id = ?";
        $params[] = $userId;
    } elseif ($staffFilter === 'open') {
        $sql .= " AND a.healthcare_worker_id IS NULL";
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
            <option value="all" <?php echo $staffFilter === 'all' ? 'selected' : ''; ?>>Mine + Open Bookings</option>
            <option value="mine" <?php echo $staffFilter === 'mine' ? 'selected' : ''; ?>>Assigned to Me</option>
            <option value="open" <?php echo $staffFilter === 'open' ? 'selected' : ''; ?>>Open (Unassigned)</option>
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
                            <?php if (!empty($a['healthcare_worker_id'])): ?>
                                <span class="badge badge-confirmed" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-user-check"></i> ASSIGNED TO YOU
                                </span>
                            <?php else: ?>
                                <span class="badge badge-pending" style="font-size:0.75rem;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-outline btn-sm js-slip-btn" title="View / Print Appointment Slip"
                                <?php echo slipButtonAttr($a, $a['patient_name'], !empty($a['healthcare_worker_id']) ? getCurrentUserName() : 'To be assigned by the clinic'); ?>>
                                <i class="fa-solid fa-receipt"></i> Slip
                            </button>
                            <?php if ($a['status'] === 'confirmed' && (int)$a['healthcare_worker_id'] === (int)$userId): ?>
                                <a href="examine.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-stethoscope"></i> Examine
                                </a>
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

<?php include __DIR__ . '/../includes/appointment_slip.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>