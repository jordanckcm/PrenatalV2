<?php
/**
 * Comprehensive Prenatal Checkup Form & Record Management
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Record Prenatal Examination";
$activePage = "records";
$workerId = getCurrentUserId();

$error = '';
$selectedPatientId = (int)($_GET['patient_id'] ?? 0);
$selectedApptId = (int)($_GET['appointment_id'] ?? 0);

try {
    $db = getDB();

    // Fetch Patients for Dropdown
    $patientsStmt = $db->query("SELECT p.id, p.patient_code, p.lmp, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name ASC");
    $patientsList = $patientsStmt->fetchAll();

    // Fetch Pre-selected Patient Details if any
    $patientInfo = null;
    $calculatedGA = 12;
    if ($selectedPatientId) {
        $pInfoStmt = $db->prepare("SELECT p.*, u.full_name, u.phone, u.email FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $pInfoStmt->execute([$selectedPatientId]);
        $patientInfo = $pInfoStmt->fetch();
        if ($patientInfo && $patientInfo['lmp']) {
            $calculatedGA = calculateGestationalAgeWeeks($patientInfo['lmp']);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $appointmentId = (int)($_POST['appointment_id'] ?? 0) ?: null;
        $visitDate = $_POST['visit_date'] ?? date('Y-m-d');
        $gestationalAge = (int)($_POST['gestational_age_weeks'] ?? 12);
        $weight = (float)($_POST['weight_kg'] ?? 0);
        $systolic = (int)($_POST['systolic_bp'] ?? 120);
        $diastolic = (int)($_POST['diastolic_bp'] ?? 80);
        $fundalHeight = (float)($_POST['fundal_height_cm'] ?? 0);
        $fetalHeartRate = (int)($_POST['fetal_heart_rate'] ?? 140);
        $fetalPresentation = sanitize($_POST['fetal_presentation'] ?? 'Cephalic');
        $edema = sanitize($_POST['edema'] ?? 'none');
        $urineProtein = sanitize($_POST['urine_protein'] ?? 'Negative');
        $urineSugar = sanitize($_POST['urine_sugar'] ?? 'Negative');
        $hemoglobin = (float)($_POST['hemoglobin_level'] ?? 12.0);
        $vitamins = sanitize($_POST['vitamins_prescribed'] ?? '');
        $ironFolic = isset($_POST['iron_folic_given']) ? 1 : 0;
        $tetanusVaccine = sanitize($_POST['tetanus_vaccine_given'] ?? 'None');
        $riskAssessment = sanitize($_POST['risk_assessment'] ?? 'low_risk');
        $clinicalNotes = sanitize($_POST['clinical_notes'] ?? '');
        $nextVisitDate = $_POST['next_visit_date'] ?? null;

        // Baseline Prenatal Profile fields examined by staff
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $bloodType = sanitize($_POST['blood_type'] ?? 'A+');
        $lmp = !empty($_POST['lmp']) ? $_POST['lmp'] : null;
        $edd = $lmp ? calculateEDD($lmp) : null;
        $gravida = isset($_POST['gravida']) ? (int)$_POST['gravida'] : 1;
        $para = isset($_POST['para']) ? (int)$_POST['para'] : 0;
        $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (!verifyCsrfToken($csrfToken)) {
            $error = "Security validation error.";
        } elseif (!$patientId) {
            $error = "Please select a patient.";
        } else {
            $db->beginTransaction();

            // Update Patient Baseline & Prenatal Profile during examination
            $pUpdateStmt = $db->prepare("UPDATE patients SET dob = ?, blood_type = ?, lmp = ?, edd = ?, gravida = ?, para = ?, emergency_contact_name = ?, emergency_contact_phone = ?, address = ? WHERE id = ?");
            $pUpdateStmt->execute([
                $dob, $bloodType, $lmp, $edd, $gravida, $para, $emergencyName, $emergencyPhone, $address, $patientId
            ]);

            // Insert Prenatal Record
            $stmt = $db->prepare("INSERT INTO prenatal_records (patient_id, appointment_id, healthcare_worker_id, visit_date, gestational_age_weeks, weight_kg, systolic_bp, diastolic_bp, fundal_height_cm, fetal_heart_rate, fetal_presentation, edema, urine_protein, urine_sugar, hemoglobin_level, vitamins_prescribed, iron_folic_given, tetanus_vaccine_given, risk_assessment, clinical_notes, next_visit_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId, $appointmentId, $workerId, $visitDate, $gestationalAge, $weight, $systolic, $diastolic, $fundalHeight, $fetalHeartRate, $fetalPresentation, $edema, $urineProtein, $urineSugar, $hemoglobin, $vitamins, $ironFolic, $tetanusVaccine, $riskAssessment, $clinicalNotes, $nextVisitDate ?: null
            ]);

            // Update Appointment Status to completed if linked
            if ($appointmentId) {
                $appUpdate = $db->prepare("UPDATE appointments SET status = 'completed', healthcare_worker_id = ? WHERE id = ?");
                $appUpdate->execute([$workerId, $appointmentId]);
            }

            // Get Patient User ID for notification
            $puStmt = $db->prepare("SELECT user_id FROM patients WHERE id = ?");
            $puStmt->execute([$patientId]);
            $patientUserId = $puStmt->fetchColumn();

            if ($patientUserId) {
                createNotification($patientUserId, "New Prenatal Record Added", "Your prenatal checkup results from {$visitDate} are now available in your records portal.", "system");
                if ($nextVisitDate) {
                    createNotification($patientUserId, "Follow-Up Visit Scheduled", "Your next recommended prenatal follow-up visit is scheduled for {$nextVisitDate}.", "followup");
                }
            }

            logAudit("RECORD_PRENATAL_CHECKUP", "Prenatal checkup recorded for Patient ID {$patientId}");

            $db->commit();

            header("Location: prenatal_records.php?patient_id={$patientId}&success=" . urlencode("Prenatal checkup recorded successfully!"));
            exit;
        }
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $error = "Error saving record: " . $e->getMessage();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Record Prenatal Checkup Results</h1>
        <p>Input examination vitals, fetal heart rate, lab results, and risk assessment</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4"><i class="fa-solid fa-circle-exclamation"></i> <div><?php echo sanitize($error); ?></div></div>
<?php endif; ?>

<div class="dashboard-card" style="max-width: 900px; margin: 0 auto;">
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
        <input type="hidden" name="appointment_id" value="<?php echo $selectedApptId; ?>">

        <h4 class="mb-3 text-primary"><i class="fa-solid fa-user-nurse"></i> Patient Selection & Visit Date</h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Select Patient *</label>
                <select name="patient_id" class="form-control" onchange="window.location.href='record_checkup.php?patient_id=' + this.value + '&appointment_id=<?php echo $selectedApptId; ?>'" required>
                    <option value="">-- Choose Patient --</option>
                    <?php foreach ($patientsList as $pl): ?>
                        <option value="<?php echo $pl['id']; ?>" <?php echo $selectedPatientId == $pl['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($pl['full_name']); ?> (<?php echo sanitize($pl['patient_code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Examination Date *</label>
                <input type="date" name="visit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Gestational Age (Weeks) *</label>
                <input type="number" name="gestational_age_weeks" id="ga_weeks_input" class="form-control" value="<?php echo $calculatedGA; ?>" min="1" max="44" required>
            </div>
        </div>

        <!-- Section: Patient Prenatal & Clinical Baseline Information (Examine) -->
        <h4 class="mt-4 mb-2 text-primary"><i class="fa-solid fa-baby"></i> Prenatal & Patient Baseline Information</h4>
        <p class="text-muted mb-3" style="font-size: 0.85rem;">
            Clinical baseline details verified &amp; recorded by healthcare staff during examination.
        </p>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-control" value="<?php echo sanitize($patientInfo['dob'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Blood Type</label>
                <select name="blood_type" class="form-control">
                    <?php 
                    $currBt = $patientInfo['blood_type'] ?? 'A+';
                    foreach (['O+','A+','B+','AB+','O-','A-','B-','AB-'] as $bt): 
                    ?>
                        <option value="<?php echo $bt; ?>" <?php echo $currBt === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Last Menstrual Period (LMP)</label>
                <input type="date" name="lmp" id="lmp_input" class="form-control" value="<?php echo sanitize($patientInfo['lmp'] ?? ''); ?>" onchange="calcGAFromLMP(this.value)">
                <small class="text-muted"><i class="fa-solid fa-circle-info"></i> Auto-updates Estimated Due Date &amp; GA Weeks</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Pregnancy History (Gravida / Para)</label>
                <div style="display: flex; gap: 0.6rem;">
                    <div style="flex: 1;">
                        <input type="number" name="gravida" class="form-control" placeholder="Gravida" min="1" value="<?php echo (int)($patientInfo['gravida'] ?? 1); ?>">
                        <small class="text-muted">Gravida (Total Pregnancies)</small>
                    </div>
                    <div style="flex: 1;">
                        <input type="number" name="para" class="form-control" placeholder="Para" min="0" value="<?php echo (int)($patientInfo['para'] ?? 0); ?>">
                        <small class="text-muted">Para (Viable Births)</small>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control" placeholder="Spouse or relative" value="<?php echo sanitize($patientInfo['emergency_contact_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone" class="form-control" placeholder="+63 999 999 9999" value="<?php echo sanitize($patientInfo['emergency_contact_phone'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group mb-4">
            <label class="form-label">Home Address</label>
            <textarea name="address" class="form-control" rows="2" placeholder="Street, Barangay, City, Province"><?php echo sanitize($patientInfo['address'] ?? ''); ?></textarea>
        </div>

        <h4 class="mt-4 mb-3 text-primary"><i class="fa-solid fa-heart-pulse"></i> Maternal Vitals & Examinations</h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Maternal Weight (kg) *</label>
                <input type="number" step="0.1" name="weight_kg" class="form-control" placeholder="e.g. 62.5" required>
            </div>
            <div class="form-group">
                <label class="form-label">Blood Pressure (mmHg) *</label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="number" name="systolic_bp" class="form-control" placeholder="Systolic (120)" required>
                    <span>/</span>
                    <input type="number" name="diastolic_bp" class="form-control" placeholder="Diastolic (80)" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Fundal Height (cm)</label>
                <input type="number" step="0.5" name="fundal_height_cm" class="form-control" placeholder="e.g. 24.0">
            </div>
        </div>

        <h4 class="mt-4 mb-3 text-primary"><i class="fa-solid fa-baby"></i> Fetal Assessment</h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Fetal Heart Rate (bpm)</label>
                <input type="number" name="fetal_heart_rate" class="form-control" placeholder="Normal: 120-160 bpm" value="140">
            </div>
            <div class="form-group">
                <label class="form-label">Fetal Presentation</label>
                <select name="fetal_presentation" class="form-control">
                    <option value="Cephalic">Cephalic (Head Down)</option>
                    <option value="Breech">Breech</option>
                    <option value="Transverse">Transverse</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Edema Check</label>
                <select name="edema" class="form-control">
                    <option value="none">None</option>
                    <option value="mild">Mild (+1)</option>
                    <option value="moderate">Moderate (+2)</option>
                    <option value="severe">Severe (+3)</option>
                </select>
            </div>
        </div>

        <h4 class="mt-4 mb-3 text-primary"><i class="fa-solid fa-flask"></i> Laboratory Tests & Immunizations</h4>
        <div class="form-row">
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
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Hemoglobin Level (g/dL)</label>
                <input type="number" step="0.1" name="hemoglobin_level" class="form-control" placeholder="Normal: 11.0-14.0" value="12.0">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Tetanus Vaccine Administered</label>
                <select name="tetanus_vaccine_given" class="form-control">
                    <option value="None">None this visit</option>
                    <option value="TT1">TT1 (1st Dose)</option>
                    <option value="TT2">TT2 (2nd Dose)</option>
                    <option value="TT3">TT3 (3rd Dose)</option>
                    <option value="TT4">TT4 (4th Dose)</option>
                    <option value="TT5">TT5 (5th Dose)</option>
                </select>
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 0.75rem; margin-top: 1.8rem;">
                <input type="checkbox" id="iron_folic" name="iron_folic_given" value="1" checked style="width: 20px; height: 20px;">
                <label for="iron_folic" class="form-label" style="margin-bottom:0;">Iron & Folic Acid Tablets Dispensed</label>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Prescribed Medications / Supplements</label>
            <input type="text" name="vitamins_prescribed" class="form-control" placeholder="e.g. Prenatal Multivitamins, Calcium 500mg daily" value="Prenatal Multivitamins & Calcium 500mg daily">
        </div>

        <h4 class="mt-4 mb-3 text-primary"><i class="fa-solid fa-clipboard-check"></i> Assessment & Follow-Up</h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Overall Risk Assessment *</label>
                <select name="risk_assessment" class="form-control" required>
                    <option value="low_risk">Low Risk (Normal Routine Care)</option>
                    <option value="moderate_risk">Moderate Risk (Requires Monitoring)</option>
                    <option value="high_risk">High Risk (Requires Obstetrician Referral)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Schedule Next Follow-Up Visit Date</label>
                <?php
                    $defaultNext = date('Y-m-d', strtotime('+4 weeks'));
                    if ($calculatedGA >= 28 && $calculatedGA < 36) $defaultNext = date('Y-m-d', strtotime('+2 weeks'));
                    if ($calculatedGA >= 36) $defaultNext = date('Y-m-d', strtotime('+1 week'));
                ?>
                <input type="date" name="next_visit_date" class="form-control" value="<?php echo $defaultNext; ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Clinical Notes & Recommendations *</label>
            <textarea name="clinical_notes" class="form-control" rows="3" placeholder="Enter findings, patient instructions, and follow-up advice..." required>Mother and fetus progressing normally. Advised balanced nutrition and adequate rest.</textarea>
        </div>

        <button type="submit" class="btn btn-accent btn-lg btn-block mt-4">
            <i class="fa-solid fa-floppy-disk"></i> Save Prenatal Checkup Record
        </button>
    </form>
</div>

<script>
function calcGAFromLMP(lmp) {
    if (!lmp) return;
    const lmpDate = new Date(lmp);
    const now = new Date();
    const diffMs = now - lmpDate;
    if (diffMs > 0) {
        const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const weeks = Math.round(days / 7);
        const gaInput = document.getElementById('ga_weeks_input');
        if (gaInput && weeks >= 1 && weeks <= 44) {
            gaInput.value = weeks;
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
