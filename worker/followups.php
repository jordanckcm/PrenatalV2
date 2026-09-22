<?php
/**
 * Follow-Up Visit Monitoring & Reminder Dispatcher
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Follow-Up Monitoring";
$activePage = "followup";

$msg = '';

try {
    $db = getDB();

    // Handle Manual Reminder Dispatch
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
        $patientUserId = (int)$_POST['user_id'];
        $nextDate = $_POST['next_date'];

        createNotification($patientUserId, "Follow-Up Reminder", "Reminder: You are scheduled for your next prenatal follow-up visit on {$nextDate}. Please book or confirm your slot.", "reminder");
        logAudit("SEND_REMINDER", "Follow-up reminder sent to User ID {$patientUserId}");
        $msg = "Follow-up reminder notification dispatched successfully!";
    }

    // Fetch Patients with upcoming or overdue follow-up visit dates from latest checkups
    $sql = "SELECT pr.id, pr.visit_date, pr.next_visit_date, pr.gestational_age_weeks, pr.risk_assessment, p.id as patient_id, p.user_id, p.patient_code, u.full_name, u.phone FROM prenatal_records pr JOIN (SELECT patient_id, MAX(id) as max_id FROM prenatal_records GROUP BY patient_id) latest ON pr.id = latest.max_id JOIN patients p ON pr.patient_id = p.id JOIN users u ON p.user_id = u.id WHERE pr.next_visit_date IS NOT NULL ORDER BY pr.next_visit_date ASC";

    $stmt = $db->query($sql);
    $followUps = $stmt->fetchAll();

} catch (Exception $e) {
    die("Error loading follow-up monitor: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Follow-Up Visit Monitoring</h1>
        <p>Monitor patient follow-up schedules and send automated reminders</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check"></i> <div><?php echo sanitize($msg); ?></div></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Patient Code</th>
                <th>Patient Name</th>
                <th>Phone</th>
                <th>Last Checkup</th>
                <th>Target Follow-Up Date</th>
                <th>Risk Level</th>
                <th>Status Alert</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($followUps) > 0): ?>
                <?php foreach ($followUps as $f): ?>
                    <?php
                        $today = strtotime(date('Y-m-d'));
                        $target = strtotime($f['next_visit_date']);
                        $diffDays = floor(($target - $today) / (60 * 60 * 24));
                        
                        $alertBadge = '<span class="badge badge-confirmed">Upcoming (' . $diffDays . ' days)</span>';
                        if ($diffDays < 0) {
                            $alertBadge = '<span class="badge badge-cancelled">Overdue (' . abs($diffDays) . ' days ago)</span>';
                        } elseif ($diffDays == 0) {
                            $alertBadge = '<span class="badge badge-pending">Due Today!</span>';
                        }
                    ?>
                    <tr>
                        <td><strong><code><?php echo sanitize($f['patient_code']); ?></code></strong></td>
                        <td><strong><?php echo sanitize($f['full_name']); ?></strong></td>
                        <td><?php echo sanitize($f['phone']); ?></td>
                        <td><?php echo formatDate($f['visit_date']); ?></td>
                        <td><strong class="text-primary"><?php echo formatDate($f['next_visit_date']); ?></strong></td>
                        <td><span class="badge badge-<?php echo $f['risk_assessment']; ?>"><?php echo str_replace('_', ' ', ucfirst($f['risk_assessment'])); ?></span></td>
                        <td><?php echo $alertBadge; ?></td>
                        <td>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="send_reminder" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $f['user_id']; ?>">
                                <input type="hidden" name="next_date" value="<?php echo formatDate($f['next_visit_date']); ?>">
                                <button type="submit" class="btn btn-outline btn-sm" title="Send Reminder Notification">
                                    <i class="fa-solid fa-paper-plane"></i> Remind
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center p-4 text-muted">No scheduled follow-up visits found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
