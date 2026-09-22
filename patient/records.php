<?php
/**
 * Prenatal Records (Patient) - Premium Design
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('patient');

$pageTitle = "Prenatal Records";
$activePage = "records";
$userId = getCurrentUserId();

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$userId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        $patient = ensurePatientProfile($db, $userId);
    }

    $patientId = $patient['id'] ?? 0;

    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as examiner_name
        FROM prenatal_records pr
        LEFT JOIN users u ON pr.healthcare_worker_id = u.id
        WHERE pr.patient_id = ?
        ORDER BY pr.visit_date DESC, pr.created_at DESC
    ");
    $stmt->execute([$patientId]);
    $records = $stmt->fetchAll();

} catch (Exception $e) {
    die("Error loading records: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   PREMIUM PRENATAL RECORDS DESIGN
   ============================================ */

/* Hero Banner */
.records-hero-premium {
    background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 50%, #3b82f6 100%);
    border-radius: 24px;
    padding: 2.5rem 2rem;
    color: #fff;
    margin-bottom: 1.75rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px -12px rgba(139, 92, 246, 0.35);
}

.records-hero-premium::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.records-hero-premium::after {
    content: '';
    position: absolute;
    bottom: -50%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.records-hero-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.records-hero-text h1 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    letter-spacing: -1px;
    color: #fff;
}

.records-hero-text p {
    margin: 0;
    opacity: 0.95;
    font-size: 0.95rem;
    line-height: 1.5;
}

.records-hero-stats {
    display: flex;
    gap: 1.5rem;
    margin-top: 1.25rem;
    flex-wrap: wrap;
}

.records-hero-stat {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.records-hero-stat-icon {
    font-size: 1.1rem;
    opacity: 0.9;
}

.records-hero-stat-text small {
    display: block;
    font-size: 0.62rem;
    opacity: 0.85;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.records-hero-stat-text strong {
    font-size: 1rem;
    font-weight: 800;
    font-family: 'Bricolage Grotesque', sans-serif;
}

.records-hero-illustration {
    width: 110px;
    height: 110px;
    border-radius: 30px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    flex-shrink: 0;
    animation: floatIllustration 4s ease-in-out infinite;
}

@keyframes floatIllustration {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

/* Timeline */
.records-timeline {
    position: relative;
    padding-left: 2.5rem;
}

.records-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 20px;
    bottom: 20px;
    width: 3px;
    background: linear-gradient(180deg, 
        #f97316 0%, 
        #ec4899 25%, 
        #8b5cf6 50%, 
        #3b82f6 75%, 
        #10b981 100%);
    border-radius: 2px;
    opacity: 0.3;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-dot {
    position: absolute;
    left: -2.5rem;
    top: 1.5rem;
    width: 33px;
    height: 33px;
    border-radius: 50%;
    background: var(--surface);
    border: 3px solid var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.timeline-dot-inner {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f97316, #ea580c);
}

.timeline-item:nth-child(2) .timeline-dot { border-color: #ec4899; }
.timeline-item:nth-child(2) .timeline-dot-inner { background: linear-gradient(135deg, #ec4899, #db2777); }
.timeline-item:nth-child(3) .timeline-dot { border-color: #8b5cf6; }
.timeline-item:nth-child(3) .timeline-dot-inner { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.timeline-item:nth-child(4) .timeline-dot { border-color: #3b82f6; }
.timeline-item:nth-child(4) .timeline-dot-inner { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.timeline-item:nth-child(5) .timeline-dot { border-color: #10b981; }
.timeline-item:nth-child(5) .timeline-dot-inner { background: linear-gradient(135deg, #10b981, #059669); }

/* Record Card */
.record-card-premium {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.record-card-premium::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #f97316, #ea580c);
    opacity: 0;
    transition: opacity 0.3s;
}

.record-card-premium:hover::before {
    opacity: 1;
}

.record-card-premium:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px -8px rgba(0, 0, 0, 0.12);
    border-color: var(--primary);
}

/* Record Header */
.record-head {
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.record-head-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.record-date-badge {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: 'Bricolage Grotesque', sans-serif;
    box-shadow: 0 8px 20px rgba(249, 115, 22, 0.35);
    flex-shrink: 0;
}

.record-date-day {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1;
}

.record-date-month {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.95;
    margin-top: 0.15rem;
}

.record-head-text h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.05rem;
    font-weight: 700;
}

.record-head-text small {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.record-head-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Risk Badges */
.risk-badge-premium {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.9rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.risk-badge-premium.low {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.risk-badge-premium.moderate {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
}

.risk-badge-premium.high {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
}

:root[data-theme="dark"] .risk-badge-premium.low { background: linear-gradient(135deg, #064e3b, #065f46); color: #6ee7b7; }
:root[data-theme="dark"] .risk-badge-premium.moderate { background: linear-gradient(135deg, #78350f, #92400e); color: #fcd34d; }
:root[data-theme="dark"] .risk-badge-premium.high { background: linear-gradient(135deg, #7f1d1d, #991b1b); color: #fca5a5; }

/* Record Body */
.record-body-premium {
    padding: 1.75rem;
}

.record-group {
    margin-bottom: 1.5rem;
}

.record-group:last-child {
    margin-bottom: 0;
}

.record-group-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--text-muted);
    font-weight: 800;
    margin-bottom: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.record-group-title i {
    color: var(--primary);
    font-size: 0.85rem;
}

/* Data Grid */
.record-data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.85rem;
}

.record-data-item {
    background: var(--bg-body);
    border-radius: 12px;
    padding: 0.9rem 1rem;
    border-left: 3px solid var(--primary);
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}

.record-data-item::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 40px;
    height: 40px;
    background: radial-gradient(circle, rgba(249, 115, 22, 0.08) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.record-data-item:hover {
    transform: translateX(4px);
    background: var(--surface);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.record-data-item.blue { border-left-color: #3b82f6; }
.record-data-item.blue::after { background: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, transparent 70%); }

.record-data-item.green { border-left-color: #10b981; }
.record-data-item.green::after { background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, transparent 70%); }

.record-data-item.pink { border-left-color: #ec4899; }
.record-data-item.pink::after { background: radial-gradient(circle, rgba(236, 72, 153, 0.08) 0%, transparent 70%); }

.record-data-item.purple { border-left-color: #8b5cf6; }
.record-data-item.purple::after { background: radial-gradient(circle, rgba(139, 92, 246, 0.08) 0%, transparent 70%); }

.record-data-label {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted);
    font-weight: 800;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.record-data-label i {
    font-size: 0.72rem;
    color: var(--primary);
}

.record-data-item.blue .record-data-label i { color: #3b82f6; }
.record-data-item.green .record-data-label i { color: #10b981; }
.record-data-item.pink .record-data-label i { color: #ec4899; }
.record-data-item.purple .record-data-label i { color: #8b5cf6; }

.record-data-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1.2;
    position: relative;
    z-index: 1;
}

.record-data-value.na {
    color: var(--text-muted);
    font-size: 0.88rem;
    font-family: 'Figtree', sans-serif;
    font-weight: 600;
    font-style: italic;
}

/* Notes */
.record-notes-premium {
    padding: 1.15rem 1.35rem;
    background: linear-gradient(135deg, #fff5ed 0%, #ffe8dc 100%);
    border-radius: 14px;
    border: 1px solid rgba(249, 115, 22, 0.2);
    position: relative;
    overflow: hidden;
}

.record-notes-premium::before {
    content: '\201C';
    position: absolute;
    top: -10px;
    left: 15px;
    font-size: 4rem;
    font-family: Georgia, serif;
    color: var(--primary);
    opacity: 0.15;
    line-height: 1;
}

:root[data-theme="dark"] .record-notes-premium {
    background: linear-gradient(135deg, #2a1a0f 0%, #3a1a0f 100%);
    border-color: rgba(249, 115, 22, 0.3);
}

.record-notes-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--primary);
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    position: relative;
    z-index: 1;
}

.record-notes-text {
    font-size: 0.88rem;
    color: var(--text-dark);
    line-height: 1.6;
    position: relative;
    z-index: 1;
    font-style: italic;
}

/* Next Visit Premium */
.next-visit-premium {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.15rem 1.35rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 14px;
    color: #fff;
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25);
}

.next-visit-premium-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.next-visit-premium-text small {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 800;
    opacity: 0.9;
    margin-bottom: 0.2rem;
}

.next-visit-premium-text strong {
    font-size: 1.05rem;
    font-weight: 800;
    font-family: 'Bricolage Grotesque', sans-serif;
}

/* Empty State */
.empty-records-premium {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--surface);
    border: 2px dashed var(--border-color);
    border-radius: 24px;
    position: relative;
    overflow: hidden;
}

.empty-records-premium::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(249, 115, 22, 0.03) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.empty-records-icon-premium {
    width: 110px;
    height: 110px;
    border-radius: 30px;
    background: linear-gradient(135deg, #fff5ed 0%, #ffe8dc 100%);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    margin: 0 auto 1.5rem;
    box-shadow: 0 12px 30px rgba(249, 115, 22, 0.2);
    position: relative;
    z-index: 1;
}

:root[data-theme="dark"] .empty-records-icon-premium {
    background: linear-gradient(135deg, #2a1a0f 0%, #3a1a0f 100%);
}

.empty-records-premium h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-dark);
    position: relative;
    z-index: 1;
}

.empty-records-premium p {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin: 0 0 1.5rem 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
    z-index: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .records-hero-premium { padding: 1.75rem 1.5rem; border-radius: 20px; }
    .records-hero-text h1 { font-size: 1.5rem; }
    .records-hero-illustration { width: 80px; height: 80px; font-size: 2.2rem; border-radius: 22px; }
    .records-hero-stats { gap: 0.75rem; }
    .records-hero-stat { padding: 0.4rem 0.75rem; }
    .records-timeline { padding-left: 2rem; }
    .records-timeline::before { left: 12px; }
    .timeline-dot { left: -2rem; width: 28px; height: 28px; top: 1rem; }
    .timeline-dot-inner { width: 12px; height: 12px; }
    .record-head { padding: 1.15rem 1.25rem; }
    .record-date-badge { width: 52px; height: 52px; border-radius: 14px; }
    .record-date-day { font-size: 1.3rem; }
    .record-body-premium { padding: 1.25rem; }
    .record-data-grid { grid-template-columns: 1fr 1fr; gap: 0.65rem; }
}

@media (max-width: 480px) {
    .records-hero-premium { padding: 1.5rem 1.25rem; }
    .records-hero-text h1 { font-size: 1.25rem; }
    .records-hero-illustration { display: none; }
    .records-hero-stats { display: none; }
    .records-timeline { padding-left: 1.5rem; }
    .records-timeline::before { left: 8px; }
    .timeline-dot { left: -1.5rem; width: 24px; height: 24px; top: 0.85rem; }
    .timeline-dot-inner { width: 10px; height: 10px; }
    .record-data-grid { grid-template-columns: 1fr; }
    .record-head { flex-direction: column; align-items: flex-start; }
    .record-head-right { width: 100%; }
}
</style>

<!-- Hero Banner -->
<div class="records-hero-premium">
    <div class="records-hero-content">
        <div class="records-hero-text">
            <h1>Prenatal Records</h1>
            <p>Your complete prenatal health journey, tracked visit by visit.</p>

            <div class="records-hero-stats">
                <div class="records-hero-stat">
                    <i class="fa-solid fa-notes-medical records-hero-stat-icon"></i>
                    <div class="records-hero-stat-text">
                        <small>Total Records</small>
                        <strong><?php echo count($records); ?></strong>
                    </div>
                </div>
                <?php if (!empty($records)): ?>
                    <div class="records-hero-stat">
                        <i class="fa-solid fa-calendar-check records-hero-stat-icon"></i>
                        <div class="records-hero-stat-text">
                            <small>Latest Visit</small>
                            <strong><?php echo formatDate($records[0]['visit_date'] ?? $records[0]['created_at']); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($patient['gestational_age_weeks'])): ?>
                    <div class="records-hero-stat">
                        <i class="fa-solid fa-baby records-hero-stat-icon"></i>
                        <div class="records-hero-stat-text">
                            <small>Gest. Age</small>
                            <strong>Week <?php echo (int)$patient['gestational_age_weeks']; ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="records-hero-illustration">
            <i class="fa-solid fa-baby-carriage"></i>
        </div>
    </div>
</div>

<!-- Records Timeline -->
<?php if (count($records) > 0): ?>

    <div class="records-timeline">
        <?php foreach ($records as $index => $record): ?>

            <?php
            $visitDate = $record['visit_date'] ?? $record['created_at'];
            $dayNum = date('d', strtotime($visitDate));
            $monthName = date('M', strtotime($visitDate));
            $yearNum = date('Y', strtotime($visitDate));

            $risk = strtolower($record['risk_assessment'] ?? 'low_risk');
            $riskClass = 'low';
            $riskLabel = 'Low Risk';
            if (strpos($risk, 'high') !== false) {
                $riskClass = 'high';
                $riskLabel = 'High Risk';
            } elseif (strpos($risk, 'moderate') !== false || strpos($risk, 'medium') !== false) {
                $riskClass = 'moderate';
                $riskLabel = 'Moderate Risk';
            }
            ?>

            <div class="timeline-item">
                <div class="timeline-dot">
                    <div class="timeline-dot-inner"></div>
                </div>

                <div class="record-card-premium">
                    <!-- Header -->
                    <div class="record-head">
                        <div class="record-head-left">
                            <div class="record-date-badge">
                                <span class="record-date-day"><?php echo $dayNum; ?></span>
                                <span class="record-date-month"><?php echo $monthName; ?></span>
                            </div>
                            <div class="record-head-text">
                                <h3>Visit #<?php echo count($records) - $index; ?></h3>
                                <small>
                                    <i class="fa-regular fa-calendar"></i>
                                    <?php echo date('F d, Y', strtotime($visitDate)); ?>
                                </small>
                            </div>
                        </div>
                        <div class="record-head-right">
                            <span class="risk-badge-premium <?php echo $riskClass; ?>">
                                <i class="fa-solid fa-shield-halved"></i>
                                <?php echo $riskLabel; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="record-body-premium">

                        <!-- Vital Signs -->
                        <div class="record-group">
                            <div class="record-group-title">
                                <i class="fa-solid fa-heart-pulse"></i> Vital Signs
                            </div>
                            <div class="record-data-grid">
                                <div class="record-data-item blue">
                                    <div class="record-data-label">
                                        <i class="fa-solid fa-weight-scale"></i> Weight
                                    </div>
                                    <div class="record-data-value <?php echo empty($record['weight_kg']) ? 'na' : ''; ?>">
                                        <?php echo !empty($record['weight_kg']) ? sanitize($record['weight_kg']) . ' kg' : 'Not recorded'; ?>
                                    </div>
                                </div>

                                <div class="record-data-item pink">
                                    <div class="record-data-label">
                                        <i class="fa-solid fa-heart"></i> Blood Pressure
                                    </div>
                                    <div class="record-data-value <?php echo (empty($record['systolic_bp']) || empty($record['diastolic_bp'])) ? 'na' : ''; ?>">
                                        <?php 
                                        $sys = $record['systolic_bp'] ?? null;
                                        $dia = $record['diastolic_bp'] ?? null;
                                        echo ($sys && $dia) ? "$sys/$dia" : 'Not recorded';
                                        ?>
                                    </div>
                                </div>

                                <div class="record-data-item green">
                                    <div class="record-data-label">
                                        <i class="fa-solid fa-baby"></i> Fetal Heart Rate
                                    </div>
                                    <div class="record-data-value <?php echo empty($record['fetal_heart_rate']) ? 'na' : ''; ?>">
                                        <?php echo !empty($record['fetal_heart_rate']) ? sanitize($record['fetal_heart_rate']) . ' bpm' : 'Not recorded'; ?>
                                    </div>
                                </div>

                                <div class="record-data-item purple">
                                    <div class="record-data-label">
                                        <i class="fa-solid fa-ruler-vertical"></i> Fundal Height
                                    </div>
                                    <div class="record-data-value <?php echo empty($record['fundal_height_cm']) ? 'na' : ''; ?>">
                                        <?php echo !empty($record['fundal_height_cm']) ? sanitize($record['fundal_height_cm']) . ' cm' : 'Not recorded'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fetal Assessment -->
                        <?php if (!empty($record['fetal_presentation']) || !empty($record['gestational_age_weeks']) || !empty($record['edema'])): ?>
                            <div class="record-group">
                                <div class="record-group-title">
                                    <i class="fa-solid fa-baby-carriage"></i> Fetal Assessment
                                </div>
                                <div class="record-data-grid">
                                    <?php if (!empty($record['fetal_presentation'])): ?>
                                        <div class="record-data-item blue">
                                            <div class="record-data-label">
                                                <i class="fa-solid fa-baby-carriage"></i> Fetal Position
                                            </div>
                                            <div class="record-data-value"><?php echo sanitize($record['fetal_presentation']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($record['gestational_age_weeks'])): ?>
                                        <div class="record-data-item green">
                                            <div class="record-data-label">
                                                <i class="fa-solid fa-calendar-days"></i> Gestational Age
                                            </div>
                                            <div class="record-data-value">Week <?php echo (int)$record['gestational_age_weeks']; ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($record['edema'])): ?>
                                        <div class="record-data-item pink">
                                            <div class="record-data-label">
                                                <i class="fa-solid fa-droplet"></i> Edema
                                            </div>
                                            <div class="record-data-value"><?php echo sanitize(ucfirst($record['edema'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Lab Results -->
                        <?php if (!empty($record['urine_protein']) || !empty($record['urine_sugar']) || !empty($record['examiner_name'])): ?>
                            <div class="record-group">
                                <div class="record-group-title">
                                    <i class="fa-solid fa-flask-vial"></i> Examination Details
                                </div>
                                <div class="record-data-grid">
                                    <?php if (!empty($record['urine_protein'])): ?>
                                        <div class="record-data-item blue">
                                            <div class="record-data-label">
                                                <i class="fa-solid fa-flask"></i> Urine Protein
                                            </div>
                                            <div class="record-data-value"><?php echo sanitize($record['urine_protein']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($record['urine_sugar'])): ?>
                                        <div class="record-data-item green">
                                            <div class="record-data-label">
                                                <i class="fa-solid fa-flask-vial"></i> Urine Sugar
                                            </div>
                                            <div class="record-data-value"><?php echo sanitize($record['urine_sugar']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($record['examiner_name'])): ?>
                                        <div class="record-data-item purple">
                                            <div class="record-data-label">
                                                <i class="fa-solid fa-user-doctor"></i> Examined By
                                            </div>
                                            <div class="record-data-value"><?php echo sanitize($record['examiner_name']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Clinical Notes -->
                        <?php if (!empty($record['clinical_notes'])): ?>
                            <div class="record-notes-premium" style="margin-bottom:1rem;">
                                <div class="record-notes-label">
                                    <i class="fa-solid fa-notes-medical"></i> Clinical Notes
                                </div>
                                <div class="record-notes-text">
                                    <?php echo nl2br(sanitize($record['clinical_notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Next Visit -->
                        <?php if (!empty($record['next_visit_date'])): ?>
                            <div class="next-visit-premium">
                                <div class="next-visit-premium-icon">
                                    <i class="fa-solid fa-calendar-check"></i>
                                </div>
                                <div class="next-visit-premium-text">
                                    <small>Next Visit Scheduled</small>
                                    <strong><?php echo formatDate($record['next_visit_date']); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        <?php endforeach; ?>
    </div>

<?php else: ?>

    <div class="empty-records-premium">
        <div class="empty-records-icon-premium">
            <i class="fa-solid fa-folder-open"></i>
        </div>
        <h3>No Prenatal Records Yet</h3>
        <p>Wala pa kay prenatal records. Pag-book og appointment para makasugod sa imong prenatal journey.</p>
        <a href="book_appointment.php" class="btn btn-primary btn-lg">
            <i class="fa-solid fa-calendar-plus"></i> Book Your First Appointment
        </a>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>