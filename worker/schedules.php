<?php
/**
 * Clinic Schedules & Live Slots Availability Overview (Healthcare Worker)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slot_helper.php';

requireRole('healthcare_worker');

$pageTitle = "Clinic Schedules & Slots";
$activePage = "schedules";

try {
    $db = getDB();
    ensureSlotOverridesTable($db);

    // Fetch weekly schedules
    $schedules = getClinicWeeklySchedule($db);

} catch (Exception $e) {
    die("Schedules error: " . $e->getMessage());
}

$extraJs = "slot_tracker.js";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Clinic Schedules & Live Slots</h1>
        <p>Overview of clinic weekly operating hours, slot capacities, and real-time appointment availability</p>
    </div>
</div>

<!-- Weekly Schedule Overview Table -->
<div class="dashboard-card mb-4" style="padding: 1.5rem;">
    <div class="card-header-flex mb-3">
        <div>
            <h3 style="margin-bottom: 0.25rem;"><i class="fa-solid fa-calendar-week text-primary"></i> Weekly Clinic Operating Hours</h3>
            <p class="text-muted" style="margin: 0; font-size: 0.85rem;">Standard clinic consultation schedule established by administration</p>
        </div>
        <span class="badge badge-confirmed"><i class="fa-solid fa-circle-info"></i> Managed by Admin</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Day of Week</th>
                    <th>Opening Time</th>
                    <th>Closing Time</th>
                    <th>Max Patients / Slot</th>
                    <th>Clinic Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $sched): ?>
                    <tr>
                        <td><strong><?php echo $sched['day_of_week']; ?></strong></td>
                        <td><?php echo date('g:i A', strtotime($sched['start_time'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($sched['end_time'])); ?></td>
                        <td><span class="badge badge-confirmed"><?php echo $sched['max_patients_per_slot']; ?> patients</span></td>
                        <td><span class="badge badge-<?php echo $sched['is_active'] ? 'completed' : 'cancelled'; ?>"><?php echo $sched['is_active'] ? 'Open' : 'Closed'; ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Interactive Live Slot Availability & Capacity Tracker -->
<div id="workerSchedulesSlotTrackerContainer" class="mt-4"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initSlotTracker('workerSchedulesSlotTrackerContainer', {
        role: 'healthcare_worker',
        apiUrl: '<?php echo BASE_URL; ?>api/get_slots.php',
        manageApiUrl: '<?php echo BASE_URL; ?>api/manage_slots.php',
        csrfToken: '<?php echo getCsrfToken(); ?>'
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
