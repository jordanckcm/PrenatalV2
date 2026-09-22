<?php
/**
 * Prenatal Records (Healthcare Worker)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Prenatal Records";
$activePage = "records";
$userId = getCurrentUserId();

$selectedPatientId = (int)($_GET['patient_id'] ?? 0);

try {
    $db = getDB();

    // Kuhaon ang tanan patients nga naay records
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.patient_code, u.full_name
        FROM patients p
        JOIN users u ON p.user_id = u.id
        JOIN prenatal_records pr ON pr.patient_id = p.id
        ORDER BY u.full_name ASC
    ");
    $stmt->execute();
    $patients = $stmt->fetchAll();

    // Kuhaon ang records sa selected patient
    $records = [];
    $selectedPatient = null;

    if ($selectedPatientId > 0) {
        $stmt = $db->prepare("
            SELECT p.*, u.full_name, u.phone
            FROM patients p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$selectedPatientId]);
        $selectedPatient = $stmt->fetch();

        if ($selectedPatient) {
            $stmt = $db->prepare("
                SELECT pr.*, u.full_name as examiner_name
                FROM prenatal_records pr
                LEFT JOIN users u ON pr.healthcare_worker_id = u.id
                WHERE pr.patient_id = ?
                ORDER BY pr.visit_date DESC, pr.created_at DESC
            ");
            $stmt->execute([$selectedPatientId]);
            $records = $stmt->fetchAll();
        }
    }

} catch (Exception $e) {
    die("Error loading prenatal records: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Prenatal Records</h1>
        <p>View patient prenatal health records</p>
    </div>
</div>

<!-- Patient Selector -->
<div class="dashboard-card" style="padding:1.5rem; margin-bottom:1.5rem;">
    <form method="GET" action="" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
        <label style="font-weight:700; font-size:0.88rem;">Select Patient:</label>
        <select name="patient_id" class="form-control" style="max-width:400px;" onchange="this.form.submit()">
            <option value="">-- Choose a patient --</option>
            <?php foreach ($patients as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>" <?php echo $selectedPatientId === (int)$p['id'] ? 'selected' : ''; ?>>
                    <?php echo sanitize($p['full_name']); ?> (<?php echo sanitize($p['patient_code']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($selectedPatient): ?>

    <!-- Patient Info Card -->
    <div class="dashboard-card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <div style="display:flex; align-items:center; gap:1rem;">
            <div style="
                width:56px; height:56px; border-radius:50%;
                background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
                color:#fff; display:flex; align-items:center; justify-content:center;
                font-family:'Bricolage Grotesque', sans-serif;
                font-size:1.4rem; font-weight:800;
                flex-shrink:0;
            ">
                <?php echo strtoupper(substr($selectedPatient['full_name'], 0, 1)); ?>
            </div>
            <div>
                <h3 style="margin:0; font-size:1.1rem;"><?php echo sanitize($selectedPatient['full_name']); ?></h3>
                <small class="text-muted">
                    Code: <strong><?php echo sanitize($selectedPatient['patient_code']); ?></strong> &bull;
                    Phone: <?php echo sanitize($selectedPatient['phone'] ?? 'N/A'); ?>
                </small>
            </div>
            <span class="badge badge-confirmed" style="margin-left:auto; font-size:0.75rem;">
                <?php echo count($records); ?> record<?php echo count($records) != 1 ? 's' : ''; ?>
            </span>
        </div>
    </div>

    <?php if (count($records) > 0): ?>
        <?php foreach ($records as $index => $record): ?>
            <div class="dashboard-card" style="padding:1.5rem; margin-bottom:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid var(--border-color);">
                    <h4 style="margin:0; font-size:1rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="fa-solid fa-notes-medical text-primary"></i>
                        Visit: <?php echo formatDate($record['visit_date'] ?? $record['created_at']); ?>
                    </h4>
                    <span class="badge badge-pending" style="font-size:0.7rem;">#<?php echo count($records) - $index; ?></span>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:1rem;">
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Weight</small>
                        <p style="margin:0.25rem 0 0 0; font-weight:700;"><?php echo sanitize($record['weight_kg'] ?? 'N/A'); ?> kg</p>
                    </div>
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Blood Pressure</small>
                        <p style="margin:0.25rem 0 0 0; font-weight:700;">
                            <?php 
                            $systolic = $record['systolic_bp'] ?? null;
                            $diastolic = $record['diastolic_bp'] ?? null;
                            echo ($systolic && $diastolic) ? "$systolic/$diastolic" : 'N/A';
                            ?>
                        </p>
                    </div>
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Fetal Heart Rate</small>
                        <p style="margin:0.25rem 0 0 0; font-weight:700;"><?php echo sanitize($record['fetal_heart_rate'] ?? 'N/A'); ?> bpm</p>
                    </div>
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Fundal Height</small>
                        <p style="margin:0.25rem 0 0 0; font-weight:700;"><?php echo sanitize($record['fundal_height_cm'] ?? 'N/A'); ?> cm</p>
                    </div>
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Fetal Position</small>
                        <p style="margin:0.25rem 0 0 0; font-weight:700;"><?php echo sanitize($record['fetal_presentation'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Gestational Age</small>
                        <p style="margin:0.25rem 0 0 0; font-weight:700;">Week <?php echo sanitize($record['gestational_age_weeks'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Risk Level</small>
                        <p style="margin:0.25rem 0 0 0;">
                            <?php 
                            $risk = strtolower($record['risk_assessment'] ?? 'low_risk');
                            $badgeClass = 'badge-confirmed';
                            if (strpos($risk, 'high') !== false) $badgeClass = 'badge-cancelled';
                            elseif (strpos($risk, 'moderate') !== false) $badgeClass = 'badge-pending';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>" style="font-size:0.7rem;">
                                <?php echo strtoupper(str_replace('_', ' ', $risk)); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Examined By</small>
                        <p style="margin:0.25rem 0 0 0; font-weight:700;"><?php echo sanitize($record['examiner_name'] ?? 'N/A'); ?></p>
                    </div>
                </div>

                <?php if (!empty($record['clinical_notes'])): ?>
                    <div style="margin-top:1rem; padding:1rem; background:var(--bg-body); border-radius:10px; border-left:3px solid var(--primary);">
                        <small class="text-muted" style="font-size:0.72rem; font-weight:700; text-transform:uppercase;">Clinical Notes</small>
                        <p style="margin:0.35rem 0 0 0; font-size:0.88rem;"><?php echo nl2br(sanitize($record['clinical_notes'])); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['next_visit_date'])): ?>
                    <div style="margin-top:0.75rem; padding:0.75rem 1rem; background:#fff5ed; border-radius:8px; display:inline-flex; align-items:center; gap:0.5rem;">
                        <i class="fa-solid fa-calendar-check text-primary"></i>
                        <span style="font-size:0.85rem; font-weight:700;">
                            Next Visit: <?php echo formatDate($record['next_visit_date']); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="dashboard-card" style="padding:3rem; text-align:center;">
            <i class="fa-solid fa-folder-open text-muted" style="font-size:3rem;"></i>
            <p class="text-muted" style="margin-top:1rem;">No prenatal records found for this patient.</p>
        </div>
    <?php endif; ?>

<?php elseif ($selectedPatientId > 0): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div>Patient not found.</div>
    </div>
<?php else: ?>
    <div class="dashboard-card" style="padding:3rem; text-align:center;">
        <i class="fa-solid fa-user-doctor text-muted" style="font-size:3rem;"></i>
        <p class="text-muted" style="margin-top:1rem;">Please select a patient to view their prenatal records.</p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>