<?php
/**
 * Patient Dashboard (Modern Design)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('patient');

$pageTitle = "Patient Dashboard";
$activePage = "dashboard";
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

    // Stats
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $totalAppointments = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed'");
    $stmt->execute([$patientId]);
    $completedVisits = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM prenatal_records WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $prenatalRecords = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadNotifications = (int)$stmt->fetchColumn();

    // Next appointment
    $stmt = $db->prepare("
        SELECT a.*, s.service_name, u.full_name as doctor_name,
               COALESCE(NULLIF(a.room, ''), NULLIF(u.room, ''), 'Room 2 - Prenatal Consultation Room') as room_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        LEFT JOIN users u ON a.healthcare_worker_id = u.id
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() 
        AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $nextAppointment = $stmt->fetch();

    // Latest prenatal record
    $stmt = $db->prepare("
        SELECT * FROM prenatal_records 
        WHERE patient_id = ? 
        ORDER BY visit_date DESC, created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $latestRecord = $stmt->fetch();

} catch (Exception $e) {
    die("Dashboard error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   PATIENT DASHBOARD MODERN DESIGN
   ============================================ */

.patient-hero {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    border-radius: 20px;
    padding: 2rem;
    color: #fff;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.patient-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.patient-hero::after {
    content: '';
    position: absolute;
    bottom: -60%;
    right: 20%;
    width: 250px;
    height: 250px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 50%;
}

.patient-hero-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.patient-hero-text h1 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.85rem;
    font-weight: 800;
    margin: 0 0 0.35rem 0;
    letter-spacing: -0.8px;
    color: #fff;
}

.patient-hero-text p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.95rem;
}

.patient-hero-code {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.patient-hero-icon {
    width: 80px;
    height: 80px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    flex-shrink: 0;
}

/* Stats Grid */
.patient-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.patient-stat-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.35rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.patient-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    transition: width 0.3s ease;
}

.patient-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}

.patient-stat-card:hover::before {
    width: 6px;
}

.patient-stat-card.orange::before { background: linear-gradient(180deg, #f97316, #ea580c); }
.patient-stat-card.green::before { background: linear-gradient(180deg, #10b981, #059669); }
.patient-stat-card.blue::before { background: linear-gradient(180deg, #0d6efd, #0a58ca); }
.patient-stat-card.purple::before { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }

.patient-stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
    flex-shrink: 0;
}

.patient-stat-card.orange .patient-stat-icon { background: linear-gradient(135deg, #f97316, #ea580c); box-shadow: 0 6px 16px rgba(249, 115, 22, 0.3); }
.patient-stat-card.green .patient-stat-icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3); }
.patient-stat-card.blue .patient-stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); box-shadow: 0 6px 16px rgba(13, 110, 253, 0.3); }
.patient-stat-card.purple .patient-stat-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 6px 16px rgba(139, 92, 246, 0.3); }

.patient-stat-info {
    flex: 1;
    min-width: 0;
}

.patient-stat-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.patient-stat-label {
    font-size: 0.68rem;
    color: var(--text-muted);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Two-Column Cards */
.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.patient-info-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.25s ease;
}

.patient-info-card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

.patient-info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.patient-info-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.patient-info-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}

.patient-info-header small {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
}

/* Pregnancy Info Row */
.pregnancy-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.pregnancy-stat {
    text-align: center;
    padding: 0.85rem;
    background: var(--bg-body);
    border-radius: 12px;
}

.pregnancy-stat-label {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    font-weight: 800;
    margin-bottom: 0.35rem;
}

.pregnancy-stat-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-dark);
}

.pregnancy-stat-value.na {
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Next Appointment */
.next-appt-item {
    padding: 1rem;
    background: linear-gradient(135deg, #fff5ed 0%, #ffe8dc 100%);
    border-left: 4px solid var(--primary);
    border-radius: 12px;
    margin-bottom: 1rem;
}

:root[data-theme="dark"] .next-appt-item {
    background: linear-gradient(135deg, #2a1a0f 0%, #3a1a0f 100%);
}

.next-appt-service {
    font-weight: 800;
    font-size: 1rem;
    color: var(--text-dark);
    margin-bottom: 0.6rem;
}

.next-appt-detail {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--text-dark);
    margin-bottom: 0.35rem;
}

.next-appt-detail i {
    color: var(--primary);
    width: 16px;
    text-align: center;
    flex-shrink: 0;
}

/* Latest Checkup */
.checkup-header {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
}

.checkup-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.75rem;
}

.checkup-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-body);
    border-radius: 10px;
}

.checkup-item i {
    color: var(--primary);
    font-size: 0.95rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.checkup-item-text small {
    display: block;
    font-size: 0.62rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.checkup-item-text strong {
    font-size: 0.85rem;
    color: var(--text-dark);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
}

.empty-state i {
    font-size: 2.5rem;
    color: var(--text-muted);
    opacity: 0.4;
    margin-bottom: 0.75rem;
    display: block;
}

.empty-state p {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0 0 1rem 0;
}

/* Quick Actions */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.quick-action-tile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.95rem 1rem;
    background: var(--surface);
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.25s ease;
}

.quick-action-tile:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: 0 8px 20px rgba(249, 115, 22, 0.15);
}

.quick-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    transition: all 0.25s ease;
}

.quick-action-tile:hover .quick-action-icon {
    background: var(--primary);
    color: #fff;
    transform: scale(1.05);
}

.quick-action-text strong {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.1rem;
}

.quick-action-text small {
    font-size: 0.68rem;
    color: var(--text-muted);
}

/* Section Header */
.section-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-header-modern h3 {
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .patient-hero { padding: 1.5rem; border-radius: 16px; }
    .patient-hero-text h1 { font-size: 1.35rem; }
    .patient-hero-text p { font-size: 0.85rem; }
    .patient-hero-icon { width: 60px; height: 60px; font-size: 1.6rem; border-radius: 16px; }
    .patient-stats-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .patient-stat-card { padding: 1rem; }
    .patient-stat-value { font-size: 1.35rem; }
    .patient-stat-icon { width: 42px; height: 42px; font-size: 1.05rem; }
    .patient-info-grid { grid-template-columns: 1fr; }
    .quick-actions-grid { grid-template-columns: 1fr 1fr; gap: 0.65rem; }
    .quick-action-tile { padding: 0.75rem; }
    .quick-action-icon { width: 36px; height: 36px; font-size: 0.9rem; }
    .quick-action-text strong { font-size: 0.75rem; }
    .quick-action-text small { font-size: 0.62rem; }
    .pregnancy-stats { gap: 0.6rem; }
    .pregnancy-stat { padding: 0.65rem 0.5rem; }
}

@media (max-width: 480px) {
    .patient-hero { padding: 1.25rem; }
    .patient-hero-text h1 { font-size: 1.15rem; }
    .patient-hero-icon { display: none; }
    .patient-stats-grid { grid-template-columns: 1fr; }
    .quick-actions-grid { grid-template-columns: 1fr; }
    .checkup-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Welcome Hero -->
<div class="patient-hero">
    <div class="patient-hero-content">
        <div class="patient-hero-text">
            <span class="patient-hero-code">
                <i class="fa-solid fa-id-card"></i> <?php echo sanitize($patient['patient_code'] ?? 'N/A'); ?>
            </span>
            <h1>Welcome, <?php echo sanitize(getCurrentUserName()); ?>!</h1>
            <p>Your prenatal care dashboard</p>
        </div>
        <div class="patient-hero-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="patient-stats-grid">
    <div class="patient-stat-card orange">
        <div class="patient-stat-icon">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="patient-stat-info">
            <div class="patient-stat-value"><?php echo $totalAppointments; ?></div>
            <div class="patient-stat-label">Total Appointments</div>
        </div>
    </div>

    <div class="patient-stat-card green">
        <div class="patient-stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="patient-stat-info">
            <div class="patient-stat-value"><?php echo $completedVisits; ?></div>
            <div class="patient-stat-label">Completed Visits</div>
        </div>
    </div>

    <div class="patient-stat-card blue">
        <div class="patient-stat-icon">
            <i class="fa-solid fa-notes-medical"></i>
        </div>
        <div class="patient-stat-info">
            <div class="patient-stat-value"><?php echo $prenatalRecords; ?></div>
            <div class="patient-stat-label">Prenatal Records</div>
        </div>
    </div>

    <div class="patient-stat-card purple">
        <div class="patient-stat-icon">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div class="patient-stat-info">
            <div class="patient-stat-value"><?php echo $unreadNotifications; ?></div>
            <div class="patient-stat-label">Unread Notifications</div>
        </div>
    </div>
</div>

<!-- Two-Column Info Grid -->
<div class="patient-info-grid">

    <!-- Next Appointment -->
    <div class="patient-info-card">
        <div class="patient-info-header">
            <div class="patient-info-header-icon">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div>
                <h3>Your Next Appointment</h3>
                <small>Upcoming scheduled visit</small>
            </div>
        </div>

        <?php if ($nextAppointment): ?>
            <div class="next-appt-item">
                <div class="next-appt-service"><?php echo sanitize($nextAppointment['service_name']); ?></div>
                <div class="next-appt-detail">
                    <i class="fa-regular fa-calendar"></i>
                    <?php echo formatDate($nextAppointment['appointment_date']); ?>
                    &bull;
                    <?php echo date('g:i A', strtotime($nextAppointment['appointment_time'])); ?>
                </div>
                <div class="next-appt-detail">
                    <i class="fa-solid fa-user-doctor"></i>
                    <?php echo sanitize($nextAppointment['doctor_name'] ?? 'Assigned Staff'); ?>
                </div>
                <div class="next-appt-detail">
                    <i class="fa-solid fa-door-open"></i>
                    <?php echo sanitize($nextAppointment['room_name'] ?? 'Room 2 - Prenatal Consultation Room'); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-calendar-xmark"></i>
                <p>No upcoming appointments.</p>
                <a href="book_appointment.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-calendar-plus"></i> Book Now
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Latest Checkup -->
    <div class="patient-info-card">
        <div class="patient-info-header">
            <div class="patient-info-header-icon">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
            <div>
                <h3>Latest Checkup Snapshot</h3>
                <small>Most recent examination</small>
            </div>
        </div>

        <?php if ($latestRecord): ?>
            <div class="checkup-header">
                Visit Date: <?php echo formatDate($latestRecord['visit_date'] ?? $latestRecord['created_at']); ?>
            </div>

            <div class="checkup-grid">
                <div class="checkup-item">
                    <i class="fa-solid fa-weight-scale"></i>
                    <div class="checkup-item-text">
                        <small>Weight</small>
                        <strong><?php echo sanitize($latestRecord['weight_kg'] ?? 'N/A'); ?> kg</strong>
                    </div>
                </div>

                <div class="checkup-item">
                    <i class="fa-solid fa-heart"></i>
                    <div class="checkup-item-text">
                        <small>Blood Pressure</small>
                        <strong>
                            <?php 
                            $sys = $latestRecord['systolic_bp'] ?? null;
                            $dia = $latestRecord['diastolic_bp'] ?? null;
                            echo ($sys && $dia) ? "$sys/$dia mmHg" : 'N/A';
                            ?>
                        </strong>
                    </div>
                </div>

                <div class="checkup-item">
                    <i class="fa-solid fa-baby"></i>
                    <div class="checkup-item-text">
                        <small>Fetal HR</small>
                        <strong><?php echo sanitize($latestRecord['fetal_heart_rate'] ?? 'N/A'); ?> bpm</strong>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-notes-medical"></i>
                <p>No prenatal records yet.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Pregnancy Info -->
<div class="patient-info-card" style="margin-bottom:1.5rem;">
    <div class="patient-info-header">
        <div class="patient-info-header-icon">
            <i class="fa-solid fa-baby-carriage"></i>
        </div>
        <div>
            <h3>Pregnancy Information</h3>
            <small>Your prenatal details</small>
        </div>
    </div>

    <div class="pregnancy-stats">
        <div class="pregnancy-stat">
            <div class="pregnancy-stat-label">Last Menstrual Period</div>
            <div class="pregnancy-stat-value <?php echo empty($patient['lmp']) ? 'na' : ''; ?>">
                <?php echo !empty($patient['lmp']) ? formatDate($patient['lmp']) : 'N/A'; ?>
            </div>
        </div>
        <div class="pregnancy-stat">
            <div class="pregnancy-stat-label">Estimated Due Date</div>
            <div class="pregnancy-stat-value <?php echo empty($patient['edd']) ? 'na' : ''; ?>">
                <?php echo !empty($patient['edd']) ? formatDate($patient['edd']) : 'N/A'; ?>
            </div>
        </div>
        <div class="pregnancy-stat">
            <div class="pregnancy-stat-label">Gestational Age</div>
            <div class="pregnancy-stat-value <?php echo empty($patient['gestational_age_weeks']) ? 'na' : ''; ?>">
                <?php echo !empty($patient['gestational_age_weeks']) ? 'Week ' . (int)$patient['gestational_age_weeks'] : 'N/A'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="section-header-modern">
    <h3><i class="fa-solid fa-bolt text-primary"></i> Quick Actions</h3>
</div>

<div class="quick-actions-grid">
    <a href="book_appointment.php" class="quick-action-tile">
        <div class="quick-action-icon">
            <i class="fa-solid fa-calendar-plus"></i>
        </div>
        <div class="quick-action-text">
            <strong>Book Visit</strong>
            <small>Schedule appointment</small>
        </div>
    </a>

    <a href="my_appointments.php" class="quick-action-tile">
        <div class="quick-action-icon">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="quick-action-text">
            <strong>My Appointments</strong>
            <small>View all bookings</small>
        </div>
    </a>

    <a href="records.php" class="quick-action-tile">
        <div class="quick-action-icon">
            <i class="fa-solid fa-notes-medical"></i>
        </div>
        <div class="quick-action-text">
            <strong>Prenatal Records</strong>
            <small>View history</small>
        </div>
    </a>

    <a href="notifications.php" class="quick-action-tile">
        <div class="quick-action-icon">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div class="quick-action-text">
            <strong>Notifications</strong>
            <small><?php echo $unreadNotifications; ?> unread</small>
        </div>
    </a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>