<?php
/**
 * Prenatal Records (Administrator) - View and Print All Prenatal Records
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Prenatal Records";
$activePage = "prenatal_records";
$userId = getCurrentUserId();

$searchQuery = trim($_GET['search'] ?? '');
$patientFilter = (int)($_GET['patient_id'] ?? 0);

try {
    $db = getDB();

    // Kuhaon ang listahan sa patients
    $stmt = $db->query("
        SELECT p.id, p.patient_code, u.full_name
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE p.id IN (SELECT DISTINCT patient_id FROM prenatal_records)
        ORDER BY u.full_name ASC
    ");
    $patients = $stmt->fetchAll();

    // Kuhaon ang prenatal records
    $sql = "
        SELECT pr.*, 
               p.patient_code, 
               u.full_name as patient_name, 
               u.phone as patient_phone,
               u.email as patient_email,
               hw.full_name as examiner_name,
               a.appointment_code,
               s.service_name
        FROM prenatal_records pr
        LEFT JOIN patients p ON pr.patient_id = p.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN users hw ON pr.healthcare_worker_id = hw.id
        LEFT JOIN appointments a ON pr.appointment_id = a.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE 1=1
    ";
    $params = [];

    if ($patientFilter > 0) {
        $sql .= " AND pr.patient_id = ?";
        $params[] = $patientFilter;
    }

    if ($searchQuery !== '') {
        $sql .= " AND (u.full_name LIKE ? OR p.patient_code LIKE ? OR a.appointment_code LIKE ?)";
        $like = '%' . $searchQuery . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY pr.visit_date DESC, pr.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Stats
    $stmt = $db->query("SELECT COUNT(*) FROM prenatal_records");
    $totalRecords = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(DISTINCT patient_id) FROM prenatal_records");
    $totalPatients = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM prenatal_records WHERE visit_date = CURDATE()");
    $todayRecords = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    die("Error loading prenatal records: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   ADMIN PRENATAL RECORDS
   ============================================ */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.admin-stat-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.admin-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.admin-stat-card.blue::before { background: linear-gradient(180deg, #0d6efd, #0a58ca); }
.admin-stat-card.green::before { background: linear-gradient(180deg, #10b981, #059669); }
.admin-stat-card.orange::before { background: linear-gradient(180deg, #f97316, #ea580c); }

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    flex-shrink: 0;
}

.admin-stat-card.blue .admin-stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); box-shadow: 0 6px 16px rgba(13, 110, 253, 0.3); }
.admin-stat-card.green .admin-stat-icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3); }
.admin-stat-card.orange .admin-stat-icon { background: linear-gradient(135deg, #f97316, #ea580c); box-shadow: 0 6px 16px rgba(249, 115, 22, 0.3); }

.admin-stat-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
    margin-bottom: 0.2rem;
}

.admin-stat-label {
    font-size: 0.68rem;
    color: var(--text-muted);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Bar */
.filter-bar {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
    padding: 1.25rem;
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    margin-bottom: 1.5rem;
}

.filter-bar input,
.filter-bar select {
    padding: 0.7rem 1rem;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text-dark);
    font-family: inherit;
    font-size: 0.88rem;
    font-weight: 500;
    outline: none;
    transition: all 0.2s;
}

.filter-bar input:focus,
.filter-bar select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}

/* Records List */
.records-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.admin-record-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.25s ease;
}

.admin-record-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border-color: var(--primary);
}

.record-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.15rem 1.5rem;
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.05) 0%, var(--surface) 100%);
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
    gap: 1rem;
}

.record-patient-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.patient-avatar {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.3rem;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(14, 165, 233, 0.3);
}

.patient-info-text h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    font-weight: 700;
}

.patient-info-text small {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
}

.record-meta {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    font-size: 0.78rem;
    color: var(--text-muted);
}

.record-meta span {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.record-meta i {
    color: var(--primary);
}

.record-card-body {
    padding: 1.5rem;
}

.record-data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.85rem;
    margin-bottom: 1rem;
}

.record-data-item {
    background: var(--bg-body);
    border-radius: 10px;
    padding: 0.75rem 0.85rem;
    border-left: 3px solid var(--primary);
}

.record-data-item.blue { border-left-color: #3b82f6; }
.record-data-item.green { border-left-color: #10b981; }
.record-data-item.pink { border-left-color: #ec4899; }
.record-data-item.purple { border-left-color: #8b5cf6; }

.record-data-label {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    font-weight: 800;
    margin-bottom: 0.3rem;
}

.record-data-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-dark);
}

.record-data-value.na {
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 600;
    font-style: italic;
    font-family: 'Figtree', sans-serif;
}

.record-notes {
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, #fff5ed 0%, #ffe8dc 100%);
    border-left: 4px solid var(--primary);
    border-radius: 10px;
    margin-top: 0.5rem;
}

:root[data-theme="dark"] .record-notes {
    background: linear-gradient(135deg, #2a1a0f 0%, #3a1a0f 100%);
}

.record-notes-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--primary);
    font-weight: 800;
    margin-bottom: 0.35rem;
}

.record-notes-text {
    font-size: 0.85rem;
    color: var(--text-dark);
    line-height: 1.5;
}

.record-card-actions {
    display: flex;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    background: var(--bg-body);
    border-top: 1px solid var(--border-color);
    flex-wrap: wrap;
}

/* ============================================
   VIEW RECORD MODAL
   ============================================ */
#viewRecordModal .modal-card {
    max-width: 700px;
}

.view-record-body {
    padding: 1.5rem;
}

.view-patient-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 1.5rem;
}

.view-patient-avatar {
    width: 70px;
    height: 70px;
    border-radius: 18px;
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.8rem;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3);
}

.view-patient-info h2 {
    margin: 0 0 0.35rem 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-dark);
}

.view-patient-info small {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
    display: block;
    margin-bottom: 0.15rem;
}

.view-patient-info small i {
    color: var(--primary);
    width: 14px;
}

/* View Record Section */
.view-section {
    margin-bottom: 1.5rem;
}

.view-section-title {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
    font-weight: 800;
    margin-bottom: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.view-section-title i {
    color: var(--primary);
}

.view-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-color);
}

.view-info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.6rem 0;
    border-bottom: 1px dashed var(--border-color);
    font-size: 0.88rem;
}

.view-info-row:last-child {
    border-bottom: none;
}

.view-info-label {
    color: var(--text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.view-info-label i {
    color: var(--primary);
    width: 16px;
    text-align: center;
}

.view-info-value {
    font-weight: 700;
    color: var(--text-dark);
    text-align: right;
}

.view-info-value.na {
    color: var(--text-muted);
    font-weight: 600;
    font-style: italic;
}

/* Print Styles */
@media print {
    .page-header,
    .filter-bar,
    .admin-stats-grid,
    .record-card-actions,
    .sidebar,
    .topbar,
    .mobile-menu-btn,
    .modal-backdrop {
        display: none !important;
    }
}
</style>

<div class="page-header">
    <div class="page-title">
        <h1>Prenatal Records</h1>
        <p>View and print all patient prenatal examination records</p>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Stats -->
<div class="admin-stats-grid">
    <div class="admin-stat-card blue">
        <div class="admin-stat-icon"><i class="fa-solid fa-notes-medical"></i></div>
        <div>
            <div class="admin-stat-value"><?php echo $totalRecords; ?></div>
            <div class="admin-stat-label">Total Records</div>
        </div>
    </div>
    <div class="admin-stat-card green">
        <div class="admin-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div>
            <div class="admin-stat-value"><?php echo $totalPatients; ?></div>
            <div class="admin-stat-label">Patients with Records</div>
        </div>
    </div>
    <div class="admin-stat-card orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <div>
            <div class="admin-stat-value"><?php echo $todayRecords; ?></div>
            <div class="admin-stat-label">Today's Records</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-bar">
    <form method="GET" action="" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center; width:100%;">
        <input type="text" name="search" placeholder="Search by patient name, code, or appointment code..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="flex:1; min-width:250px;">

        <select name="patient_id" style="max-width:250px;">
            <option value="0">-- All Patients --</option>
            <?php foreach ($patients as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>" <?php echo $patientFilter === (int)$p['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['full_name'] . ' (' . $p['patient_code'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-search"></i> Search
        </button>

        <?php if ($searchQuery !== '' || $patientFilter > 0): ?>
            <a href="prenatal_records.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>

        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()" style="margin-left:auto;">
            <i class="fa-solid fa-print"></i> Print All
        </button>
    </form>
</div>

<!-- Records List -->
<?php if (count($records) > 0): ?>
    <div class="records-list">
        <?php foreach ($records as $r): ?>
            <?php
            $visitDate = $r['visit_date'] ?? $r['created_at'];
            $risk = strtolower($r['risk_assessment'] ?? 'low_risk');
            $riskClass = 'badge-confirmed';
            $riskLabel = 'Low Risk';
            if (strpos($risk, 'high') !== false) {
                $riskClass = 'badge-cancelled';
                $riskLabel = 'High Risk';
            } elseif (strpos($risk, 'moderate') !== false || strpos($risk, 'medium') !== false) {
                $riskClass = 'badge-pending';
                $riskLabel = 'Moderate Risk';
            }
            ?>
            <div class="admin-record-card" id="record-<?php echo (int)$r['id']; ?>">
                <!-- Header -->
                <div class="record-card-header">
                    <div class="record-patient-info">
                        <div class="patient-avatar">
                            <?php echo strtoupper(substr($r['patient_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div class="patient-info-text">
                            <h3><?php echo htmlspecialchars($r['patient_name'] ?? 'Unknown Patient'); ?></h3>
                            <small>
                                <i class="fa-solid fa-id-card"></i> <?php echo htmlspecialchars($r['patient_code'] ?? 'N/A'); ?>
                                &bull;
                                <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($r['patient_phone'] ?? 'N/A'); ?>
                            </small>
                        </div>
                    </div>
                    <div>
                        <span class="badge <?php echo $riskClass; ?>" style="font-size:0.7rem;">
                            <i class="fa-solid fa-shield-halved"></i> <?php echo strtoupper($riskLabel); ?>
                        </span>
                    </div>
                </div>

                <!-- Body -->
                <div class="record-card-body">

                    <!-- Visit Info -->
                    <div class="record-meta" style="margin-bottom:1rem;">
                        <span><i class="fa-regular fa-calendar"></i> Visit: <strong><?php echo date('F d, Y', strtotime($visitDate)); ?></strong></span>
                        <?php if (!empty($r['appointment_code'])): ?>
                            <span><i class="fa-solid fa-hashtag"></i> Appt: <strong><?php echo htmlspecialchars($r['appointment_code']); ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($r['service_name'])): ?>
                            <span><i class="fa-solid fa-stethoscope"></i> <strong><?php echo htmlspecialchars($r['service_name']); ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($r['examiner_name'])): ?>
                            <span><i class="fa-solid fa-user-doctor"></i> Examined by: <strong><?php echo htmlspecialchars($r['examiner_name']); ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <!-- Vital Signs -->
                    <div class="record-data-grid">
                        <div class="record-data-item blue">
                            <div class="record-data-label">Weight</div>
                            <div class="record-data-value <?php echo empty($r['weight_kg']) ? 'na' : ''; ?>">
                                <?php echo !empty($r['weight_kg']) ? htmlspecialchars($r['weight_kg']) . ' kg' : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="record-data-item pink">
                            <div class="record-data-label">Blood Pressure</div>
                            <div class="record-data-value <?php echo (empty($r['systolic_bp']) || empty($r['diastolic_bp'])) ? 'na' : ''; ?>">
                                <?php 
                                $sys = $r['systolic_bp'] ?? null;
                                $dia = $r['diastolic_bp'] ?? null;
                                echo ($sys && $dia) ? htmlspecialchars("$sys/$dia") : 'N/A';
                                ?>
                            </div>
                        </div>
                        <div class="record-data-item green">
                            <div class="record-data-label">Fetal Heart Rate</div>
                            <div class="record-data-value <?php echo empty($r['fetal_heart_rate']) ? 'na' : ''; ?>">
                                <?php echo !empty($r['fetal_heart_rate']) ? htmlspecialchars($r['fetal_heart_rate']) . ' bpm' : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="record-data-item purple">
                            <div class="record-data-label">Fundal Height</div>
                            <div class="record-data-value <?php echo empty($r['fundal_height_cm']) ? 'na' : ''; ?>">
                                <?php echo !empty($r['fundal_height_cm']) ? htmlspecialchars($r['fundal_height_cm']) . ' cm' : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="record-data-item blue">
                            <div class="record-data-label">Fetal Presentation</div>
                            <div class="record-data-value <?php echo empty($r['fetal_presentation']) ? 'na' : ''; ?>">
                                <?php echo !empty($r['fetal_presentation']) ? htmlspecialchars($r['fetal_presentation']) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="record-data-item green">
                            <div class="record-data-label">Gestational Age</div>
                            <div class="record-data-value <?php echo empty($r['gestational_age_weeks']) ? 'na' : ''; ?>">
                                <?php echo !empty($r['gestational_age_weeks']) ? 'Week ' . (int)$r['gestational_age_weeks'] : 'N/A'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <?php if (!empty($r['clinical_notes'])): ?>
                        <div class="record-notes">
                            <div class="record-notes-label">
                                <i class="fa-solid fa-notes-medical"></i> Clinical Notes
                            </div>
                            <div class="record-notes-text">
                                <?php echo nl2br(htmlspecialchars($r['clinical_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Next Visit -->
                    <?php if (!empty($r['next_visit_date'])): ?>
                        <div style="margin-top:0.75rem; padding:0.65rem 0.85rem; background:#f0fdf4; border-radius:8px; display:inline-flex; align-items:center; gap:0.5rem; font-size:0.82rem; font-weight:700; color:#065f46;">
                            <i class="fa-solid fa-calendar-check"></i> Next Visit: <?php echo date('F d, Y', strtotime($r['next_visit_date'])); ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Actions -->
                <div class="record-card-actions">
                    <button type="button" class="btn btn-outline btn-sm js-view-record"
                        data-record='<?php echo htmlspecialchars(json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>'>
                        <i class="fa-solid fa-eye"></i> View Full Details
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="printRecord(<?php echo (int)$r['id']; ?>)">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <div class="dashboard-card" style="padding:3rem; text-align:center;">
        <i class="fa-solid fa-folder-open text-muted" style="font-size:3rem; opacity:0.4;"></i>
        <p class="text-muted" style="margin-top:1rem;">No prenatal records found.</p>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- VIEW RECORD MODAL -->
<!-- ============================================ -->
<div id="viewRecordModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-notes-medical text-primary"></i> Prenatal Record Details</h3>
            <button type="button" class="js-close-modal" data-modal="viewRecordModal" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body view-record-body" id="viewRecordContent">
            <!-- Content ma-populate via JavaScript -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline js-close-modal" data-modal="viewRecordModal">Close</button>
            <button type="button" class="btn btn-primary" id="printFromModalBtn">
                <i class="fa-solid fa-print"></i> Print This Record
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    /* ============================================
       MODAL HELPERS
       ============================================ */
    function showModal(id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.display = 'flex';
            el.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function hideModal(id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.display = 'none';
            el.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    /* ============================================
       FORMAT FUNCTIONS
       ============================================ */
    function esc(str) {
        if (str === null || str === undefined || str === '') return 'N/A';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function dateFmt(str) {
        if (!str) return 'N/A';
        var d = new Date(str);
        if (isNaN(d.getTime())) return str;
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        return months[d.getMonth()] + ' ' + String(d.getDate()).padStart(2, '0') + ', ' + d.getFullYear();
    }

    /* ============================================
       OPEN VIEW MODAL
       ============================================ */
    function openViewRecordModal(record) {
        var container = document.getElementById('viewRecordContent');

        var bp = 'N/A';
        if (record.systolic_bp && record.diastolic_bp) {
            bp = record.systolic_bp + '/' + record.diastolic_bp;
        }

        var riskLabel = 'Low Risk';
        var riskClass = 'badge-confirmed';
        var risk = String(record.risk_assessment || 'low_risk').toLowerCase();
        if (risk.indexOf('high') !== -1) {
            riskLabel = 'High Risk';
            riskClass = 'badge-cancelled';
        } else if (risk.indexOf('moderate') !== -1 || risk.indexOf('medium') !== -1) {
            riskLabel = 'Moderate Risk';
            riskClass = 'badge-pending';
        }

        var html = '';

        // Patient Header
        html += '<div class="view-patient-header">';
        html += '  <div class="view-patient-avatar">' + esc(record.patient_name).charAt(0).toUpperCase() + '</div>';
        html += '  <div class="view-patient-info">';
        html += '    <h2>' + esc(record.patient_name) + '</h2>';
        html += '    <small><i class="fa-solid fa-id-card"></i> ' + esc(record.patient_code) + '</small>';
        html += '    <small><i class="fa-solid fa-phone"></i> ' + esc(record.patient_phone) + '</small>';
        if (record.patient_email) {
            html += '    <small><i class="fa-solid fa-envelope"></i> ' + esc(record.patient_email) + '</small>';
        }
        html += '  </div>';
        html += '  <div style="margin-left:auto;"><span class="badge ' + riskClass + '" style="font-size:0.72rem;">' + riskLabel + '</span></div>';
        html += '</div>';

        // Visit Info
        html += '<div class="view-section">';
        html += '  <div class="view-section-title"><i class="fa-solid fa-calendar-day"></i> Visit Information</div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-regular fa-calendar"></i> Visit Date</span><span class="view-info-value">' + dateFmt(record.visit_date || record.created_at) + '</span></div>';
        if (record.appointment_code) {
            html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-hashtag"></i> Appointment Code</span><span class="view-info-value">' + esc(record.appointment_code) + '</span></div>';
        }
        if (record.service_name) {
            html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-stethoscope"></i> Service</span><span class="view-info-value">' + esc(record.service_name) + '</span></div>';
        }
        if (record.examiner_name) {
            html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-user-doctor"></i> Examined By</span><span class="view-info-value">' + esc(record.examiner_name) + '</span></div>';
        }
        html += '</div>';

        // Vital Signs
        html += '<div class="view-section">';
        html += '  <div class="view-section-title"><i class="fa-solid fa-heart-pulse"></i> Vital Signs</div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-weight-scale"></i> Weight</span><span class="view-info-value ' + (record.weight_kg ? '' : 'na') + '">' + (record.weight_kg ? esc(record.weight_kg) + ' kg' : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-heart"></i> Blood Pressure</span><span class="view-info-value ' + (bp !== 'N/A' ? '' : 'na') + '">' + esc(bp) + (bp !== 'N/A' ? ' mmHg' : '') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-temperature-half"></i> Temperature</span><span class="view-info-value ' + (record.temperature ? '' : 'na') + '">' + (record.temperature ? esc(record.temperature) + ' °C' : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-heartbeat"></i> Pulse Rate</span><span class="view-info-value ' + (record.pulse_rate ? '' : 'na') + '">' + (record.pulse_rate ? esc(record.pulse_rate) + ' bpm' : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-lungs"></i> Respiratory Rate</span><span class="view-info-value ' + (record.respiratory_rate ? '' : 'na') + '">' + (record.respiratory_rate ? esc(record.respiratory_rate) + ' bpm' : 'N/A') + '</span></div>';
        html += '</div>';

        // Fetal Assessment
        html += '<div class="view-section">';
        html += '  <div class="view-section-title"><i class="fa-solid fa-baby"></i> Fetal Assessment</div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-heart-pulse"></i> Fetal Heart Rate</span><span class="view-info-value ' + (record.fetal_heart_rate ? '' : 'na') + '">' + (record.fetal_heart_rate ? esc(record.fetal_heart_rate) + ' bpm' : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-ruler-vertical"></i> Fundal Height</span><span class="view-info-value ' + (record.fundal_height_cm ? '' : 'na') + '">' + (record.fundal_height_cm ? esc(record.fundal_height_cm) + ' cm' : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-baby-carriage"></i> Fetal Presentation</span><span class="view-info-value ' + (record.fetal_presentation ? '' : 'na') + '">' + (record.fetal_presentation ? esc(record.fetal_presentation) : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-calendar-days"></i> Gestational Age</span><span class="view-info-value ' + (record.gestational_age_weeks ? '' : 'na') + '">' + (record.gestational_age_weeks ? 'Week ' + esc(record.gestational_age_weeks) : 'N/A') + '</span></div>';
        html += '</div>';

        // Examination Details
        html += '<div class="view-section">';
        html += '  <div class="view-section-title"><i class="fa-solid fa-clipboard-check"></i> Examination Details</div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-droplet"></i> Edema</span><span class="view-info-value ' + (record.edema && record.edema !== 'none' ? '' : 'na') + '">' + (record.edema ? esc(record.edema.charAt(0).toUpperCase() + record.edema.slice(1)) : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-flask"></i> Urine Protein</span><span class="view-info-value ' + (record.urine_protein && record.urine_protein !== 'Negative' ? '' : 'na') + '">' + (record.urine_protein ? esc(record.urine_protein) : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-flask-vial"></i> Urine Sugar</span><span class="view-info-value ' + (record.urine_sugar && record.urine_sugar !== 'Negative' ? '' : 'na') + '">' + (record.urine_sugar ? esc(record.urine_sugar) : 'N/A') + '</span></div>';
        html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-shield-halved"></i> Risk Assessment</span><span class="view-info-value"><span class="badge ' + riskClass + '">' + riskLabel + '</span></span></div>';
        html += '</div>';

        // Supplements
        if (record.vitamins_prescribed || record.iron_folic_given || record.tetanus_vaccine_given) {
            html += '<div class="view-section">';
            html += '  <div class="view-section-title"><i class="fa-solid fa-syringe"></i> Vaccines & Supplements</div>';
            if (record.vitamins_prescribed) {
                html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-pills"></i> Vitamins</span><span class="view-info-value">' + esc(record.vitamins_prescribed) + '</span></div>';
            }
            if (record.iron_folic_given) {
                html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-tablets"></i> Iron / Folic Acid</span><span class="view-info-value">' + esc(record.iron_folic_given) + '</span></div>';
            }
            if (record.tetanus_vaccine_given) {
                html += '  <div class="view-info-row"><span class="view-info-label"><i class="fa-solid fa-syringe"></i> Tetanus Vaccine</span><span class="view-info-value">' + esc(record.tetanus_vaccine_given) + '</span></div>';
            }
            html += '</div>';
        }

        // Clinical Notes
        if (record.clinical_notes) {
            html += '<div class="view-section">';
            html += '  <div class="view-section-title"><i class="fa-solid fa-notes-medical"></i> Clinical Notes</div>';
            html += '  <div class="record-notes">';
            html += '    <div class="record-notes-text">' + esc(record.clinical_notes).replace(/\n/g, '<br>') + '</div>';
            html += '  </div>';
            html += '</div>';
        }

        // Recommendations
        if (record.recommendations) {
            html += '<div class="view-section">';
            html += '  <div class="view-section-title"><i class="fa-solid fa-comment-medical"></i> Recommendations</div>';
            html += '  <div class="record-notes" style="background:linear-gradient(135deg,#e0f2fe 0%,#bae6fd 100%); border-left-color:#0ea5e9;">';
            html += '    <div class="record-notes-text">' + esc(record.recommendations).replace(/\n/g, '<br>') + '</div>';
            html += '  </div>';
            html += '</div>';
        }

        // Next Visit
        if (record.next_visit_date) {
            html += '<div class="view-section">';
            html += '  <div class="view-info-row" style="background:#f0fdf4; padding:0.85rem 1rem; border-radius:10px; border:none;">';
            html += '    <span class="view-info-label" style="color:#065f46;"><i class="fa-solid fa-calendar-check" style="color:#10b981;"></i> Next Visit Scheduled</span>';
            html += '    <span class="view-info-value" style="color:#065f46;">' + dateFmt(record.next_visit_date) + '</span>';
            html += '  </div>';
            html += '</div>';
        }

        container.innerHTML = html;

        // Store record ID sa print button
        document.getElementById('printFromModalBtn').setAttribute('data-record-id', record.id);

        showModal('viewRecordModal');
    }

    /* ============================================
       EVENT DELEGATION
       ============================================ */
    document.addEventListener('click', function(e) {
        // View record button
        var viewBtn = e.target.closest('.js-view-record');
        if (viewBtn) {
            e.preventDefault();
            try {
                var record = JSON.parse(viewBtn.getAttribute('data-record'));
                openViewRecordModal(record);
            } catch (err) {
                console.error('Failed to parse record:', err);
                alert('Error loading record details.');
            }
            return;
        }

        // Print from modal
        if (e.target.closest('#printFromModalBtn')) {
            e.preventDefault();
            var recordId = e.target.closest('#printFromModalBtn').getAttribute('data-record-id');
            if (recordId) printRecord(recordId);
            return;
        }

        // Close modal
        var closeBtn = e.target.closest('.js-close-modal');
        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            var modalId = closeBtn.getAttribute('data-modal');
            if (modalId) hideModal(modalId);
            return;
        }

        // Click overlay
        if (e.target.classList.contains('modal-backdrop')) {
            hideModal(e.target.id);
            return;
        }
    });

    // ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop').forEach(function(m) {
                hideModal(m.id);
            });
        }
    });

})();

/* ============================================
   PRINT RECORD (Global function)
   ============================================ */
function printRecord(recordId) {
    var record = document.getElementById('record-' + recordId);
    if (!record) {
        alert('Record not found.');
        return;
    }

    var printWindow = window.open('', '_blank', 'width=800,height=900');
    printWindow.document.write('<!DOCTYPE html><html><head><title>Prenatal Record #' + recordId + '</title>' +
        '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' +
        '<style>' +
        '* { box-sizing: border-box; }' +
        'body { font-family: Arial, sans-serif; padding: 2rem; color: #1a1a1a; background: #fff; }' +
        '.print-header { text-align: center; padding-bottom: 1rem; border-bottom: 3px solid #f97316; margin-bottom: 1.5rem; }' +
        '.print-header h1 { margin: 0 0 0.25rem 0; color: #f97316; font-size: 1.5rem; }' +
        '.print-header p { margin: 0; color: #666; font-size: 0.85rem; }' +
        '.record-patient-info { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }' +
        '.patient-avatar { width: 50px; height: 50px; border-radius: 12px; background: #0ea5e9; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 800; }' +
        '.patient-info-text h3 { margin: 0 0 0.25rem 0; font-size: 1.1rem; }' +
        '.patient-info-text small { font-size: 0.78rem; color: #888; }' +
        '.record-meta { display: flex; gap: 1rem; flex-wrap: wrap; padding: 0.75rem 0; border-bottom: 1px solid #eee; margin-bottom: 1rem; font-size: 0.82rem; }' +
        '.record-meta span { color: #555; }' +
        '.record-meta strong { color: #1a1a1a; }' +
        '.record-data-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin: 1rem 0; }' +
        '.record-data-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem 0.75rem; background: #f9fafb; }' +
        '.record-data-label { font-size: 0.65rem; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.25rem; }' +
        '.record-data-value { font-size: 0.95rem; font-weight: 800; color: #1a1a1a; }' +
        '.record-notes { border-left: 4px solid #f97316; background: #fff5ed; padding: 0.75rem 1rem; border-radius: 8px; margin-top: 1rem; }' +
        '.record-notes-label { font-size: 0.7rem; text-transform: uppercase; color: #ea580c; font-weight: 800; margin-bottom: 0.4rem; }' +
        '.record-notes-text { font-size: 0.88rem; line-height: 1.5; color: #1a1a1a; }' +
        '.print-footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee; text-align: center; font-size: 0.72rem; color: #888; }' +
        '.badge { display: inline-block; padding: 0.3rem 0.7rem; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }' +
        '.badge-confirmed { background: #d1fae5; color: #065f46; }' +
        '.badge-pending { background: #fef3c7; color: #92400e; }' +
        '.badge-cancelled { background: #fee2e2; color: #991b1b; }' +
        '</style></head><body>' +
        '<div class="print-header"><h1>MaternalCare Prenatal Health System</h1><p>Official Prenatal Examination Record</p></div>' +
        record.outerHTML.replace(/<button[^>]*>.*?<\/button>/g, '') +
        '<div class="print-footer">Printed on: ' + new Date().toLocaleString() + '<br>MaternalCare Prenatal Health Center — Official Document</div>' +
        '<script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };<\/script>' +
        '</body></html>');
    printWindow.document.close();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>