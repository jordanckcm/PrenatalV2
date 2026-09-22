<?php
/**
 * Examine Patient (Healthcare Worker) - Dynamic Form by Service
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Examine Patient";
$activePage = "appointments";
$userId = getCurrentUserId();

$appointmentId = (int)($_GET['id'] ?? 0);

if (!$appointmentId) {
    die("Error: No appointment selected. <a href='appointments.php'>Back to Appointments</a>");
}

$errorMsg = '';

try {
    $db = getDB();

    // Kuhaon ang appointment + service info
    $stmt = $db->prepare("
        SELECT a.*, s.service_name, u.full_name as patient_name, u.phone as patient_phone,
               p.patient_code, p.id as patient_id, p.gravida, p.para, p.blood_type, p.dob, p.lmp,
               p.address, p.emergency_contact_name, p.emergency_contact_phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN services s ON a.service_id = s.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        die("Error: Appointment not found. <a href='appointments.php'>Back to Appointments</a>");
    }

    if ($appointment['status'] !== 'confirmed') {
        die("Error: Only confirmed appointments can be examined. <a href='appointments.php'>Back</a>");
    }

    // ============================================
    // DETERMINE SERVICE TYPE
    // ============================================
    $serviceName = strtolower($appointment['service_name']);

    $isUltrasound = (
        strpos($serviceName, 'ultrasound') !== false ||
        strpos($serviceName, 'pelvic') !== false ||
        strpos($serviceName, '3d') !== false ||
        strpos($serviceName, 'sonograph') !== false
    );

    $isLab = (
        strpos($serviceName, 'lab') !== false ||
        strpos($serviceName, 'blood') !== false ||
        strpos($serviceName, 'urine') !== false ||
        strpos($serviceName, 'test') !== false
    );

    $isVaccine = (
        strpos($serviceName, 'tetanus') !== false ||
        strpos($serviceName, 'immunization') !== false ||
        strpos($serviceName, 'vaccine') !== false ||
        strpos($serviceName, 'toxoid') !== false
    );

    $isScreening = (
        strpos($serviceName, 'screening') !== false ||
        strpos($serviceName, 'high-risk') !== false ||
        strpos($serviceName, 'high risk') !== false
    );

    $isPostnatal = (
        strpos($serviceName, 'postnatal') !== false ||
        strpos($serviceName, 'family planning') !== false ||
        strpos($serviceName, 'postnatal') !== false
    );

    $isRoutine = !$isUltrasound && !$isLab && !$isVaccine && !$isScreening && !$isPostnatal;

    // ============================================
    // HANDLE POST SUBMISSION
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrf)) {
            $errorMsg = "Security token error. Please refresh and try again.";
        } else {
            // Common fields
            $riskAssessment = $_POST['risk_assessment'] ?? 'low_risk';
            $clinicalNotes = trim($_POST['clinical_notes'] ?? '');
            $nextVisitDate = $_POST['next_visit_date'] ?? null;

            // Initialize all fields as null
            $weight = null;
            $systolic = null;
            $diastolic = null;
            $temperature = null;
            $pulseRate = null;
            $respiratoryRate = null;
            $fetalHeartRate = null;
            $fundalHeight = null;
            $fetalPresentation = null;
            $edema = 'none';
            $urineProtein = 'Negative';
            $urineSugar = 'Negative';
            $gestationalAge = null;
            $vitaminsPrescribed = null;
            $ironFolicGiven = null;
            $tetanusVaccineGiven = null;

            $hasError = false;

            // ============================================
            // ROUTINE PRENATAL CONSULTATION
            // ============================================
            if ($isRoutine) {
                $weight = trim($_POST['weight_kg'] ?? '');
                $systolic = trim($_POST['systolic_bp'] ?? '');
                $diastolic = trim($_POST['diastolic_bp'] ?? '');
                $temperature = trim($_POST['temperature'] ?? '');
                $pulseRate = trim($_POST['pulse_rate'] ?? '');
                $respiratoryRate = trim($_POST['respiratory_rate'] ?? '');
                $fetalHeartRate = trim($_POST['fetal_heart_rate'] ?? '');
                $fundalHeight = trim($_POST['fundal_height_cm'] ?? '');
                $fetalPresentation = trim($_POST['fetal_presentation'] ?? '');
                $edema = trim($_POST['edema'] ?? 'none');
                $urineProtein = trim($_POST['urine_protein'] ?? 'Negative');
                $urineSugar = trim($_POST['urine_sugar'] ?? 'Negative');
                $gestationalAge = trim($_POST['gestational_age_weeks'] ?? '');

                if (empty($weight) || empty($systolic) || empty($diastolic) || empty($fetalHeartRate)) {
                    $errorMsg = "Please fill in all required fields (Weight, BP, Fetal HR).";
                    $hasError = true;
                }
            }

            // ============================================
            // OBSTETRIC ULTRASOUND
            // ============================================
            if ($isUltrasound) {
                $fetalHeartRate = trim($_POST['fetal_heart_rate'] ?? '');
                $fundalHeight = trim($_POST['fundal_height_cm'] ?? '');
                $fetalPresentation = trim($_POST['fetal_presentation'] ?? '');
                $gestationalAge = trim($_POST['gestational_age_weeks'] ?? '');

                if (empty($fetalHeartRate)) {
                    $errorMsg = "Please fill in Fetal Heart Rate.";
                    $hasError = true;
                }
            }

            // ============================================
            // LABORATORY TESTS
            // ============================================
            if ($isLab) {
                $urineProtein = trim($_POST['urine_protein'] ?? 'Negative');
                $urineSugar = trim($_POST['urine_sugar'] ?? 'Negative');
                $weight = trim($_POST['weight_kg'] ?? '');
                $systolic = trim($_POST['systolic_bp'] ?? '');
                $diastolic = trim($_POST['diastolic_bp'] ?? '');

                if (empty($urineProtein) || empty($urineSugar)) {
                    $errorMsg = "Please fill in Urine Protein and Urine Sugar.";
                    $hasError = true;
                }
            }

            // ============================================
            // VACCINE / IMMUNIZATION
            // ============================================
            if ($isVaccine) {
                $tetanusVaccineGiven = trim($_POST['tetanus_vaccine_given'] ?? '');
                $vitaminsPrescribed = trim($_POST['vitamins_prescribed'] ?? '');
                $ironFolicGiven = trim($_POST['iron_folic_given'] ?? '');

                if (empty($tetanusVaccineGiven)) {
                    $errorMsg = "Please specify the vaccine given.";
                    $hasError = true;
                }
            }

            // ============================================
            // HIGH-RISK SCREENING
            // ============================================
            if ($isScreening) {
                $weight = trim($_POST['weight_kg'] ?? '');
                $systolic = trim($_POST['systolic_bp'] ?? '');
                $diastolic = trim($_POST['diastolic_bp'] ?? '');
                $fetalHeartRate = trim($_POST['fetal_heart_rate'] ?? '');
                $fundalHeight = trim($_POST['fundal_height_cm'] ?? '');
                $edema = trim($_POST['edema'] ?? 'none');
                $urineProtein = trim($_POST['urine_protein'] ?? 'Negative');
                $urineSugar = trim($_POST['urine_sugar'] ?? 'Negative');
                $gestationalAge = trim($_POST['gestational_age_weeks'] ?? '');
                $riskAssessment = $_POST['risk_assessment'] ?? 'high_risk';

                if (empty($weight) || empty($systolic) || empty($diastolic)) {
                    $errorMsg = "Please fill in Weight and Blood Pressure.";
                    $hasError = true;
                }
            }

            // ============================================
            // POSTNATAL CHECKUP
            // ============================================
            if ($isPostnatal) {
                $weight = trim($_POST['weight_kg'] ?? '');
                $systolic = trim($_POST['systolic_bp'] ?? '');
                $diastolic = trim($_POST['diastolic_bp'] ?? '');
                $temperature = trim($_POST['temperature'] ?? '');
                $pulseRate = trim($_POST['pulse_rate'] ?? '');

                if (empty($weight) || empty($systolic) || empty($diastolic)) {
                    $errorMsg = "Please fill in Weight and Blood Pressure.";
                    $hasError = true;
                }
            }

            // ============================================
            // SAVE TO DATABASE
            // ============================================
            if (!$hasError) {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("
                        INSERT INTO prenatal_records 
                        (patient_id, appointment_id, healthcare_worker_id, visit_date, 
                         weight_kg, systolic_bp, diastolic_bp, temperature, pulse_rate, respiratory_rate,
                         fetal_heart_rate, fundal_height_cm, fetal_presentation,
                         edema, urine_protein, urine_sugar,
                         gestational_age_weeks, risk_assessment, clinical_notes, 
                         next_visit_date, vitamins_prescribed, iron_folic_given, tetanus_vaccine_given,
                         created_at)
                        VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $appointment['patient_id'],
                        $appointmentId,
                        $userId,
                        $weight ?: null,
                        $systolic ?: null,
                        $diastolic ?: null,
                        $temperature ?: null,
                        $pulseRate ?: null,
                        $respiratoryRate ?: null,
                        $fetalHeartRate ?: null,
                        $fundalHeight ?: null,
                        $fetalPresentation ?: null,
                        $edema,
                        $urineProtein,
                        $urineSugar,
                        $gestationalAge ?: null,
                        $riskAssessment,
                        $clinicalNotes ?: null,
                        $nextVisitDate ?: null,
                        $vitaminsPrescribed ?: null,
                        $ironFolicGiven ?: null,
                        $tetanusVaccineGiven ?: null
                    ]);

                    // Update appointment status
                    $stmt = $db->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$appointmentId]);

                    // Notify patient
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        SELECT u.id, ?, ?, 'system'
                        FROM users u
                        JOIN patients p ON p.user_id = u.id
                        WHERE p.id = ?
                    ");
                    $stmt->execute([
                        "Examination Completed",
                        "Your {$appointment['service_name']} examination has been completed. Please check your prenatal records.",
                        $appointment['patient_id']
                    ]);

                    $db->commit();
                    logAudit('EXAMINE_COMPLETED', "Completed {$appointment['service_name']} for {$appointment['appointment_code']}");

                    header("Location: appointments.php?success=" . urlencode("Examination saved successfully!"));
                    exit;

                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $errorMsg = "Error saving examination: " . $e->getMessage();
                }
            }
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.service-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 0.5rem;
}
.service-badge.routine { background: #fff5ed; color: #ea580c; }
.service-badge.ultrasound { background: #e0f2fe; color: #0369a1; }
.service-badge.lab { background: #fef3c7; color: #92400e; }
.service-badge.vaccine { background: #f3e5f5; color: #7b1fa2; }
.service-badge.screening { background: #fee2e2; color: #991b1b; }
.service-badge.postnatal { background: #d1fae5; color: #065f46; }
</style>

<div class="page-header">
    <div class="page-title">
        <h1>
            Examine Patient
            <?php if ($isRoutine): ?><span class="service-badge routine">Prenatal</span><?php endif; ?>
            <?php if ($isUltrasound): ?><span class="service-badge ultrasound">Ultrasound</span><?php endif; ?>
            <?php if ($isLab): ?><span class="service-badge lab">Laboratory</span><?php endif; ?>
            <?php if ($isVaccine): ?><span class="service-badge vaccine">Vaccine</span><?php endif; ?>
            <?php if ($isScreening): ?><span class="service-badge screening">High-Risk Screening</span><?php endif; ?>
            <?php if ($isPostnatal): ?><span class="service-badge postnatal">Postnatal</span><?php endif; ?>
        </h1>
        <p><?php echo sanitize($appointment['service_name']); ?></p>
    </div>
    <div>
        <a href="appointments.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger mb-4">
        <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($errorMsg); ?>
    </div>
<?php endif; ?>

<!-- Patient Info -->
<div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
    <h3 style="margin-bottom: 1rem;"><i class="fa-solid fa-user text-primary"></i> Patient Information</h3>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem;">
        <div><small class="text-muted">Patient Name</small><p style="margin:0;"><strong><?php echo sanitize($appointment['patient_name']); ?></strong></p></div>
        <div><small class="text-muted">Patient Code</small><p style="margin:0;"><strong><?php echo sanitize($appointment['patient_code']); ?></strong></p></div>
        <div><small class="text-muted">Phone</small><p style="margin:0;"><strong><?php echo sanitize($appointment['patient_phone']); ?></strong></p></div>
        <div><small class="text-muted">Service</small><p style="margin:0;"><strong><?php echo sanitize($appointment['service_name']); ?></strong></p></div>
        <div><small class="text-muted">Date</small><p style="margin:0;"><strong><?php echo formatDate($appointment['appointment_date']); ?></strong></p></div>
        <div><small class="text-muted">Time</small><p style="margin:0;"><strong><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></strong></p></div>
    </div>
</div>

<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

    <!-- ============================================ -->
    <!-- ROUTINE PRENATAL CONSULTATION -->
    <!-- ============================================ -->
    <?php if ($isRoutine): ?>
    <div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-stethoscope text-primary"></i> Prenatal Consultation Findings
        </h3>
        <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">
            Complete examination para sa routine prenatal checkup.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Weight (kg) <span class="text-danger">*</span></label>
                <input type="text" name="weight_kg" class="form-control" placeholder="e.g. 62.50" required>
            </div>
            <div class="form-group">
                <label class="form-label">Systolic BP (mmHg) <span class="text-danger">*</span></label>
                <input type="text" name="systolic_bp" class="form-control" placeholder="e.g. 118" required>
            </div>
            <div class="form-group">
                <label class="form-label">Diastolic BP (mmHg) <span class="text-danger">*</span></label>
                <input type="text" name="diastolic_bp" class="form-control" placeholder="e.g. 76" required>
            </div>
            <div class="form-group">
                <label class="form-label">Temperature (°C)</label>
                <input type="text" name="temperature" class="form-control" placeholder="e.g. 36.8">
            </div>
            <div class="form-group">
                <label class="form-label">Pulse Rate (bpm)</label>
                <input type="text" name="pulse_rate" class="form-control" placeholder="e.g. 80">
            </div>
            <div class="form-group">
                <label class="form-label">Respiratory Rate (bpm)</label>
                <input type="text" name="respiratory_rate" class="form-control" placeholder="e.g. 18">
            </div>
            <div class="form-group">
                <label class="form-label">Fetal Heart Rate (bpm) <span class="text-danger">*</span></label>
                <input type="text" name="fetal_heart_rate" class="form-control" placeholder="e.g. 140" required>
            </div>
            <div class="form-group">
                <label class="form-label">Fundal Height (cm)</label>
                <input type="text" name="fundal_height_cm" class="form-control" placeholder="e.g. 26">
            </div>
            <div class="form-group">
                <label class="form-label">Gestational Age (weeks)</label>
                <input type="number" name="gestational_age_weeks" class="form-control" placeholder="e.g. 26" min="1" max="45">
            </div>
            <div class="form-group">
                <label class="form-label">Fetal Presentation</label>
                <select name="fetal_presentation" class="form-control">
                    <option value="">-- Select --</option>
                    <option value="Cephalic">Cephalic</option>
                    <option value="Breech">Breech</option>
                    <option value="Transverse">Transverse</option>
                    <option value="Oblique">Oblique</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Edema</label>
                <select name="edema" class="form-control">
                    <option value="none">None</option>
                    <option value="mild">Mild</option>
                    <option value="moderate">Moderate</option>
                    <option value="severe">Severe</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Urine Protein</label>
                <select name="urine_protein" class="form-control">
                    <option value="Negative">Negative</option>
                    <option value="Trace">Trace</option>
                    <option value="1+">1+</option>
                    <option value="2+">2+</option>
                    <option value="3+">3+</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Urine Sugar</label>
                <select name="urine_sugar" class="form-control">
                    <option value="Negative">Negative</option>
                    <option value="Trace">Trace</option>
                    <option value="1+">1+</option>
                    <option value="2+">2+</option>
                    <option value="3+">3+</option>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- OBSTETRIC ULTRASOUND -->
    <!-- ============================================ -->
    <?php if ($isUltrasound): ?>
    <div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-wave-square text-primary"></i> Ultrasound Findings
        </h3>
        <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">
            Ultrasound scan results ug fetal measurements.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Fetal Heart Rate (bpm) <span class="text-danger">*</span></label>
                <input type="text" name="fetal_heart_rate" class="form-control" placeholder="e.g. 140" required>
            </div>
            <div class="form-group">
                <label class="form-label">Fetal Presentation</label>
                <select name="fetal_presentation" class="form-control">
                    <option value="">-- Select --</option>
                    <option value="Cephalic">Cephalic</option>
                    <option value="Breech">Breech</option>
                    <option value="Transverse">Transverse</option>
                    <option value="Oblique">Oblique</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Fundal Height (cm)</label>
                <input type="text" name="fundal_height_cm" class="form-control" placeholder="e.g. 26">
            </div>
            <div class="form-group">
                <label class="form-label">Gestational Age (weeks)</label>
                <input type="number" name="gestational_age_weeks" class="form-control" placeholder="e.g. 26" min="1" max="45">
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- LABORATORY TESTS -->
    <!-- ============================================ -->
    <?php if ($isLab): ?>
    <div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-vials text-primary"></i> Laboratory Test Results
        </h3>
        <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">
            Blood ug urine test results.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Urine Protein <span class="text-danger">*</span></label>
                <select name="urine_protein" class="form-control" required>
                    <option value="Negative">Negative</option>
                    <option value="Trace">Trace</option>
                    <option value="1+">1+</option>
                    <option value="2+">2+</option>
                    <option value="3+">3+</option>
                    <option value="4+">4+</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Urine Sugar <span class="text-danger">*</span></label>
                <select name="urine_sugar" class="form-control" required>
                    <option value="Negative">Negative</option>
                    <option value="Trace">Trace</option>
                    <option value="1+">1+</option>
                    <option value="2+">2+</option>
                    <option value="3+">3+</option>
                    <option value="4+">4+</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Weight (kg)</label>
                <input type="text" name="weight_kg" class="form-control" placeholder="e.g. 62.50">
            </div>
            <div class="form-group">
                <label class="form-label">Systolic BP (mmHg)</label>
                <input type="text" name="systolic_bp" class="form-control" placeholder="e.g. 118">
            </div>
            <div class="form-group">
                <label class="form-label">Diastolic BP (mmHg)</label>
                <input type="text" name="diastolic_bp" class="form-control" placeholder="e.g. 76">
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- VACCINE / IMMUNIZATION -->
    <!-- ============================================ -->
    <?php if ($isVaccine): ?>
    <div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-syringe text-primary"></i> Immunization Details
        </h3>
        <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">
            Vaccine administration ug supplements.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Tetanus Vaccine Given <span class="text-danger">*</span></label>
                <input type="text" name="tetanus_vaccine_given" class="form-control" placeholder="e.g. TT2" required>
            </div>
            <div class="form-group">
                <label class="form-label">Vitamins Prescribed</label>
                <input type="text" name="vitamins_prescribed" class="form-control" placeholder="e.g. Prenatal Multivitamins & Calcium">
            </div>
            <div class="form-group">
                <label class="form-label">Iron / Folic Acid Given</label>
                <input type="text" name="iron_folic_given" class="form-control" placeholder="e.g. 1 tablet daily">
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- HIGH-RISK PRENATAL SCREENING -->
    <!-- ============================================ -->
    <?php if ($isScreening): ?>
    <div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-shield-halved text-primary"></i> High-Risk Prenatal Screening
        </h3>
        <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">
            Detailed screening para sa high-risk pregnancies.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Weight (kg) <span class="text-danger">*</span></label>
                <input type="text" name="weight_kg" class="form-control" placeholder="e.g. 62.50" required>
            </div>
            <div class="form-group">
                <label class="form-label">Systolic BP (mmHg) <span class="text-danger">*</span></label>
                <input type="text" name="systolic_bp" class="form-control" placeholder="e.g. 140" required>
            </div>
            <div class="form-group">
                <label class="form-label">Diastolic BP (mmHg) <span class="text-danger">*</span></label>
                <input type="text" name="diastolic_bp" class="form-control" placeholder="e.g. 90" required>
            </div>
            <div class="form-group">
                <label class="form-label">Fetal Heart Rate (bpm)</label>
                <input type="text" name="fetal_heart_rate" class="form-control" placeholder="e.g. 140">
            </div>
            <div class="form-group">
                <label class="form-label">Fundal Height (cm)</label>
                <input type="text" name="fundal_height_cm" class="form-control" placeholder="e.g. 26">
            </div>
            <div class="form-group">
                <label class="form-label">Gestational Age (weeks)</label>
                <input type="number" name="gestational_age_weeks" class="form-control" placeholder="e.g. 26" min="1" max="45">
            </div>
            <div class="form-group">
                <label class="form-label">Edema</label>
                <select name="edema" class="form-control">
                    <option value="none">None</option>
                    <option value="mild">Mild</option>
                    <option value="moderate">Moderate</option>
                    <option value="severe">Severe</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Urine Protein</label>
                <select name="urine_protein" class="form-control">
                    <option value="Negative">Negative</option>
                    <option value="Trace">Trace</option>
                    <option value="1+">1+</option>
                    <option value="2+">2+</option>
                    <option value="3+">3+</option>
                    <option value="4+">4+</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Urine Sugar</label>
                <select name="urine_sugar" class="form-control">
                    <option value="Negative">Negative</option>
                    <option value="Trace">Trace</option>
                    <option value="1+">1+</option>
                    <option value="2+">2+</option>
                    <option value="3+">3+</option>
                    <option value="4+">4+</option>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- POSTNATAL CHECKUP -->
    <!-- ============================================ -->
    <?php if ($isPostnatal): ?>
    <div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-baby text-primary"></i> Postnatal Checkup & Family Planning
        </h3>
        <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">
            Post-delivery checkup ug family planning assessment.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label">Weight (kg) <span class="text-danger">*</span></label>
                <input type="text" name="weight_kg" class="form-control" placeholder="e.g. 55.00" required>
            </div>
            <div class="form-group">
                <label class="form-label">Systolic BP (mmHg) <span class="text-danger">*</span></label>
                <input type="text" name="systolic_bp" class="form-control" placeholder="e.g. 110" required>
            </div>
            <div class="form-group">
                <label class="form-label">Diastolic BP (mmHg) <span class="text-danger">*</span></label>
                <input type="text" name="diastolic_bp" class="form-control" placeholder="e.g. 70" required>
            </div>
            <div class="form-group">
                <label class="form-label">Temperature (°C)</label>
                <input type="text" name="temperature" class="form-control" placeholder="e.g. 36.6">
            </div>
            <div class="form-group">
                <label class="form-label">Pulse Rate (bpm)</label>
                <input type="text" name="pulse_rate" class="form-control" placeholder="e.g. 76">
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- COMMON: Assessment & Follow-up -->
    <!-- ============================================ -->
    <div class="dashboard-card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-notes-medical text-primary"></i> Assessment & Follow-up
        </h3>
        <p class="text-muted" style="font-size:0.85rem; margin-bottom:1rem;">
            Risk assessment, clinical notes, ug next visit schedule.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1rem;">
            <div class="form-group">
                <label class="form-label">Risk Assessment <span class="text-danger">*</span></label>
                <select name="risk_assessment" class="form-control" required>
                    <option value="low_risk" <?php echo $isScreening ? '' : 'selected'; ?>>Low Risk</option>
                    <option value="moderate_risk">Moderate Risk</option>
                    <option value="high_risk" <?php echo $isScreening ? 'selected' : ''; ?>>High Risk</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Next Visit Date</label>
                <input type="date" name="next_visit_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Clinical Notes</label>
            <textarea name="clinical_notes" class="form-control" rows="4" placeholder="Observations, findings, recommendations..."></textarea>
        </div>
    </div>

    <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-bottom: 2rem;">
        <a href="appointments.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fa-solid fa-floppy-disk"></i> Save Examination
        </button>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
