<?php
/**
 * Healthcare Worker Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Worker Dashboard";
$activePage = "dashboard";
$userId = getCurrentUserId();

try {
    $db = getDB();

    // Today's consultations
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE healthcare_worker_id = ? 
        AND appointment_date = CURDATE() 
        AND status = 'confirmed'
    ");
    $stmt->execute([$userId]);
    $todayConsultations = (int)$stmt->fetchColumn();

    // Awaiting admin confirmation
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
    $stmt->execute();
    $awaitingConfirmation = (int)$stmt->fetchColumn();

    // Registered patients
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE healthcare_worker_id = ?");
    $stmt->execute([$userId]);
    $registeredPatients = (int)$stmt->fetchColumn();

    // Follow-ups due
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM prenatal_records 
        WHERE healthcare_worker_id = ? 
        AND next_visit_date <= CURDATE()
        AND next_visit_date IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $followupsDue = (int)$stmt->fetchColumn();

    // Upcoming appointments
    $stmt = $db->prepare("
        SELECT a.*, s.service_name, u.full_name as patient_name, p.patient_code
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN services s ON a.service_id = s.id
        WHERE a.healthcare_worker_id = ?
        AND a.appointment_date >= CURDATE()
        AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $upcomingAppointments = $stmt->fetchAll();

} catch (Exception $e) {
    die("Dashboard error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   WORKER DASHBOARD MODERN DESIGN
   ============================================ */
.worker-welcome {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    border-radius: 20px;
    padding: 2rem;
    color: #fff;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.worker-welcome::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
}

.worker-welcome-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.worker-welcome h1 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.85rem;
    font-weight: 800;
    margin: 0 0 0.35rem 0;
    letter-spacing: -0.8px;
    color: #fff;
}

.worker-welcome p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.95rem;
}

.worker-welcome-icon {
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
.worker-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.worker-stat-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.35rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.worker-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.worker-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}

.worker-stat-card.orange::before { background: linear-gradient(180deg, #f97316, #ea580c); }
.worker-stat-card.yellow::before { background: linear-gradient(180deg, #f59e0b, #d97706); }
.worker-stat-card.blue::before { background: linear-gradient(180deg, #0d6efd, #0a58ca); }
.worker-stat-card.purple::before { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }

.worker-stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.worker-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    flex-shrink: 0;
}

.worker-stat-card.orange .worker-stat-icon { background: linear-gradient(135deg, #f97316, #ea580c); box-shadow: 0 6px 16px rgba(249, 115, 22, 0.3); }
.worker-stat-card.yellow .worker-stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 6px 16px rgba(245, 158, 11, 0.3); }
.worker-stat-card.blue .worker-stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); box-shadow: 0 6px 16px rgba(13, 110, 253, 0.3); }
.worker-stat-card.purple .worker-stat-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 6px 16px rgba(139, 92, 246, 0.3); }

.worker-stat-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted);
    font-weight: 800;
    margin-bottom: 0.25rem;
}

.worker-stat-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.worker-stat-desc {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
}

/* Quick Actions */
.worker-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.85rem;
    margin-bottom: 1.5rem;
}

.worker-action-tile {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    background: var(--surface);
    border: 1.5px solid var(--border-color);
    border-radius: 14px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.25s ease;
}

.worker-action-tile:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: 0 8px 20px rgba(249, 115, 22, 0.15);
}

.worker-action-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    transition: all 0.25s ease;
}

.worker-action-tile:hover .worker-action-icon {
    background: var(--primary);
    color: #fff;
    transform: scale(1.05);
}

.worker-action-text strong {
    display: block;
    font-size: 0.88rem;
    font-weight: 700;
    margin-bottom: 0.15rem;
}

.worker-action-text small {
    font-size: 0.72rem;
    color: var(--text-muted);
}

.worker-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.worker-section-header h3 {
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

@media (max-width: 768px) {
    .worker-welcome { padding: 1.5rem; border-radius: 16px; }
    .worker-welcome h1 { font-size: 1.35rem; }
    .worker-welcome p { font-size: 0.85rem; }
    .worker-welcome-icon { width: 60px; height: 60px; font-size: 1.6rem; border-radius: 16px; }
    .worker-stats { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .worker-stat-card { padding: 1rem; }
    .worker-stat-value { font-size: 1.5rem; }
    .worker-stat-icon { width: 40px; height: 40px; font-size: 1rem; }
    .worker-actions { grid-template-columns: 1fr 1fr; gap: 0.65rem; }
}

@media (max-width: 480px) {
    .worker-welcome { padding: 1.25rem; }
    .worker-welcome h1 { font-size: 1.15rem; }
    .worker-welcome-icon { display: none; }
    .worker-stats { grid-template-columns: 1fr; }
    .worker-actions { grid-template-columns: 1fr; }
}
</style>

<!-- Welcome Banner -->
<div class="worker-welcome">
    <div class="worker-welcome-content">
        <div>
            <h1>Welcome, <?php echo sanitize(getCurrentUserName()); ?>!</h1>
            <p>Manage today's consultation queue, attend to confirmed patients, and record clinical checkups</p>
        </div>
        <div class="worker-welcome-icon">
            <i class="fa-solid fa-user-doctor"></i>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="worker-stats">
    <div class="worker-stat-card orange">
        <div class="worker-stat-header">
            <div>
                <div class="worker-stat-label">Today's Consultations</div>
                <div class="worker-stat-value"><?php echo $todayConsultations; ?></div>
                <div class="worker-stat-desc">
                    <i class="fa-solid fa-calendar-day"></i> <?php echo date('M d, Y'); ?>
                </div>
            </div>
            <div class="worker-stat-icon">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>
    </div>

    <div class="worker-stat-card yellow">
        <div class="worker-stat-header">
            <div>
                <div class="worker-stat-label">Awaiting Confirmation</div>
                <div class="worker-stat-value"><?php echo $awaitingConfirmation; ?></div>
                <div class="worker-stat-desc">
                    <i class="fa-solid fa-clock"></i> Pending admin
                </div>
            </div>
            <div class="worker-stat-icon">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
        </div>
    </div>

    <div class="worker-stat-card blue">
        <div class="worker-stat-header">
            <div>
                <div class="worker-stat-label">Registered Patients</div>
                <div class="worker-stat-value"><?php echo $registeredPatients; ?></div>
                <div class="worker-stat-desc">
                    <i class="fa-solid fa-users"></i> Under your care
                </div>
            </div>
            <div class="worker-stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>

    <div class="worker-stat-card purple">
        <div class="worker-stat-header">
            <div>
                <div class="worker-stat-label">Follow-Ups Due</div>
                <div class="worker-stat-value"><?php echo $followupsDue; ?></div>
                <div class="worker-stat-desc">
                    <i class="fa-solid fa-clock-rotate-left"></i> Needs attention
                </div>
            </div>
            <div class="worker-stat-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="worker-section-header">
    <h3><i class="fa-solid fa-bolt text-primary"></i> Quick Actions</h3>
</div>

<div class="worker-actions">
    <a href="appointments.php" class="worker-action-tile">
        <div class="worker-action-icon">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="worker-action-text">
            <strong>Appointments</strong>
            <small>View today's schedule</small>
        </div>
    </a>

    <a href="patients.php" class="worker-action-tile">
        <div class="worker-action-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="worker-action-text">
            <strong>Patients Directory</strong>
            <small>View patient list</small>
        </div>
    </a>

    <a href="prenatal_records.php" class="worker-action-tile">
        <div class="worker-action-icon">
            <i class="fa-solid fa-notes-medical"></i>
        </div>
        <div class="worker-action-text">
            <strong>Prenatal Records</strong>
            <small>View examination history</small>
        </div>
    </a>

    <a href="followups.php" class="worker-action-tile">
        <div class="worker-action-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="worker-action-text">
            <strong>Follow-Up Visits</strong>
            <small>Upcoming checkups</small>
        </div>
    </a>

    <a href="calendar.php" class="worker-action-tile">
        <div class="worker-action-icon">
            <i class="fa-solid fa-calendar-days"></i>
        </div>
        <div class="worker-action-text">
            <strong>Calendar</strong>
            <small>Monthly view</small>
        </div>
    </a>

    <a href="schedules.php" class="worker-action-tile">
        <div class="worker-action-icon">
            <i class="fa-solid fa-calendar-week"></i>
        </div>
        <div class="worker-action-text">
            <strong>Schedules & Slots</strong>
            <small>Clinic availability</small>
        </div>
    </a>
</div>

<!-- Upcoming Appointments (walay Status ug Action columns) -->
<div class="worker-section-header">
    <h3><i class="fa-solid fa-clock text-primary"></i> Upcoming Appointments</h3>
    <a href="appointments.php" class="btn btn-outline btn-sm">
        View All <i class="fa-solid fa-arrow-right"></i>
    </a>
</div>

<div class="dashboard-card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Patient</th>
                    <th>Service</th>
                    <th>Date & Time</th>
                    <th>Assigned Staff</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($upcomingAppointments) > 0): ?>
                    <?php foreach ($upcomingAppointments as $a): ?>
                        <tr>
                            <td><strong><code><?php echo sanitize($a['appointment_code']); ?></code></strong></td>
                            <td>
                                <strong><?php echo sanitize($a['patient_name']); ?></strong><br>
                                <small class="text-muted"><?php echo sanitize($a['patient_code']); ?></small>
                            </td>
                            <td><?php echo sanitize($a['service_name']); ?></td>
                            <td>
                                <i class="fa-regular fa-calendar"></i> <?php echo formatDate($a['appointment_date']); ?><br>
                                <small class="text-muted"><i class="fa-regular fa-clock"></i> <?php echo date('g:i A', strtotime($a['appointment_time'])); ?></small>
                            </td>
                            <td>
                                <?php if ((int)$a['healthcare_worker_id'] === (int)$userId): ?>
                                    <span class="badge badge-confirmed" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-user-check"></i> You
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-pending" style="font-size:0.75rem;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center p-4 text-muted">
                            <i class="fa-solid fa-calendar-xmark" style="font-size:2rem; display:block; margin-bottom:0.5rem; opacity:0.5;"></i>
                            No upcoming appointments.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>