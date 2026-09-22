<?php
/**
 * Administrator Dashboard
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pageTitle = "Administrator Dashboard";
$activePage = "dashboard";
$userId = getCurrentUserId();

try {
    $db = getDB();

    // Total appointments
    $stmt = $db->query("SELECT COUNT(*) FROM appointments");
    $totalAppointments = (int)$stmt->fetchColumn();

    // Pending confirmation
    $stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
    $pendingCount = (int)$stmt->fetchColumn();

    // Registered patients
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active'");
    $totalPatients = (int)$stmt->fetchColumn();

    // Active healthcare staff
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'healthcare_worker' AND status = 'active'");
    $totalStaff = (int)$stmt->fetchColumn();

    // Today's appointments
    $stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
    $todayAppointments = (int)$stmt->fetchColumn();

    // Confirmed appointments
    $stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'confirmed'");
    $confirmedCount = (int)$stmt->fetchColumn();

    // Completed appointments
    $stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'");
    $completedCount = (int)$stmt->fetchColumn();

    // Recent appointments
    $stmt = $db->query("
        SELECT a.*, s.service_name, u.full_name as patient_name, 
               hw.full_name as worker_name, p.patient_code
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN services s ON a.service_id = s.id
        LEFT JOIN users hw ON a.healthcare_worker_id = hw.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $recentAppointments = $stmt->fetchAll();

} catch (Exception $e) {
    die("Dashboard error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   DASHBOARD STATS CARDS
   ============================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.25rem;
    position: relative;
    overflow: hidden;
    transition: all 0.25s ease;
    cursor: default;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border-color: var(--primary);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
}

.stat-card.orange::before { background: #f97316; }
.stat-card.yellow::before { background: #f59e0b; }
.stat-card.blue::before { background: #0d6efd; }
.stat-card.green::before { background: #10b981; }
.stat-card.purple::before { background: #8b5cf6; }
.stat-card.red::before { background: #dc3545; }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #fff;
}

.stat-icon.orange { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
.stat-icon.yellow { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.stat-icon.blue { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
.stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
.stat-icon.red { background: linear-gradient(135deg, #dc3545 0%, #b91c1c 100%); }

.stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
}

.stat-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-sub {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.stat-sub.positive { color: #10b981; }
.stat-sub.negative { color: #dc3545; }

/* Quick Action Tiles */
.action-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.action-tile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.25s ease;
}

.action-tile:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
    box-shadow: 0 6px 16px rgba(249, 115, 22, 0.12);
}

.action-tile-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.action-tile-text strong {
    display: block;
    font-size: 0.88rem;
    font-weight: 700;
    margin-bottom: 0.15rem;
}

.action-tile-text small {
    font-size: 0.72rem;
    color: var(--text-muted);
}

/* Section Header */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-header h3 {
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }

    .stat-card {
        padding: 1rem;
    }

    .stat-value {
        font-size: 1.5rem;
    }

    .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 0.95rem;
    }

    .action-tiles {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .action-tile {
        padding: 0.75rem;
    }

    .action-tile-icon {
        width: 38px;
        height: 38px;
        font-size: 0.95rem;
    }

    .action-tile-text strong {
        font-size: 0.78rem;
    }

    .action-tile-text small {
        font-size: 0.65rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }

    .stat-value {
        font-size: 1.35rem;
    }

    .action-tiles {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <div class="page-title">
        <h1>Administrator Dashboard</h1>
        <p>Confirm booking requests, assign healthcare staff, and monitor clinic activity</p>
    </div>
    <div>
        <a href="appointments.php" class="btn btn-primary">
            <i class="fa-solid fa-calendar-check"></i> View Booking Requests
        </a>
    </div>
</div>

<!-- STATS CARDS -->
<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-header">
            <div>
                <div class="stat-label">Total Appointments</div>
                <div class="stat-value"><?php echo $totalAppointments; ?></div>
                <div class="stat-sub">
                    <i class="fa-solid fa-calendar"></i> All time
                </div>
            </div>
            <div class="stat-icon orange">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>
    </div>

    <div class="stat-card yellow">
        <div class="stat-header">
            <div>
                <div class="stat-label">Pending Confirmation</div>
                <div class="stat-value"><?php echo $pendingCount; ?></div>
                <div class="stat-sub negative">
                    <i class="fa-solid fa-clock"></i> Needs action
                </div>
            </div>
            <div class="stat-icon yellow">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
        </div>
    </div>

    <div class="stat-card blue">
        <div class="stat-header">
            <div>
                <div class="stat-label">Registered Patients</div>
                <div class="stat-value"><?php echo $totalPatients; ?></div>
                <div class="stat-sub">
                    <i class="fa-solid fa-users"></i> Active accounts
                </div>
            </div>
            <div class="stat-icon blue">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>

    <div class="stat-card green">
        <div class="stat-header">
            <div>
                <div class="stat-label">Active Healthcare Staff</div>
                <div class="stat-value"><?php echo $totalStaff; ?></div>
                <div class="stat-sub positive">
                    <i class="fa-solid fa-user-doctor"></i> Available
                </div>
            </div>
            <div class="stat-icon green">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
        </div>
    </div>

    <div class="stat-card purple">
        <div class="stat-header">
            <div>
                <div class="stat-label">Today's Appointments</div>
                <div class="stat-value"><?php echo $todayAppointments; ?></div>
                <div class="stat-sub">
                    <i class="fa-solid fa-calendar-day"></i> <?php echo date('M d, Y'); ?>
                </div>
            </div>
            <div class="stat-icon purple">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
        </div>
    </div>

    <div class="stat-card red">
        <div class="stat-header">
            <div>
                <div class="stat-label">Confirmed Today</div>
                <div class="stat-value"><?php echo $confirmedCount; ?></div>
                <div class="stat-sub">
                    <i class="fa-solid fa-circle-check"></i> Ready for visit
                </div>
            </div>
            <div class="stat-icon red">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="section-header">
    <h3><i class="fa-solid fa-bolt text-primary"></i> Quick Actions</h3>
</div>

<div class="action-tiles">
    <a href="appointments.php" class="action-tile">
        <div class="action-tile-icon">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="action-tile-text">
            <strong>Booking Requests</strong>
            <small>Confirm & assign staff</small>
        </div>
    </a>

    <a href="users.php" class="action-tile">
        <div class="action-tile-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="action-tile-text">
            <strong>Users & Staff</strong>
            <small>Manage accounts</small>
        </div>
    </a>

    <a href="services.php" class="action-tile">
        <div class="action-tile-icon">
            <i class="fa-solid fa-stethoscope"></i>
        </div>
        <div class="action-tile-text">
            <strong>Clinic Services</strong>
            <small>Manage services</small>
        </div>
    </a>

    <a href="schedules.php" class="action-tile">
        <div class="action-tile-icon">
            <i class="fa-solid fa-calendar-days"></i>
        </div>
        <div class="action-tile-text">
            <strong>Schedules & Slots</strong>
            <small>Manage clinic hours</small>
        </div>
    </a>

    <a href="reports.php" class="action-tile">
        <div class="action-tile-icon">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="action-tile-text">
            <strong>Reports & Stats</strong>
            <small>View analytics</small>
        </div>
    </a>

    <a href="audit_logs.php" class="action-tile">
        <div class="action-tile-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="action-tile-text">
            <strong>Audit Logs</strong>
            <small>System activity</small>
        </div>
    </a>
</div>

<!-- RECENT APPOINTMENTS -->
<div class="section-header">
    <h3><i class="fa-solid fa-clock-rotate-left text-primary"></i> Recent Appointments</h3>
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
                    <th>Status</th>
                    <th>Assigned Staff</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentAppointments) > 0): ?>
                    <?php foreach ($recentAppointments as $a): ?>
                        <tr>
                            <td><strong><code><?php echo sanitize($a['appointment_code']); ?></code></strong></td>
                            <td>
                                <strong><?php echo sanitize($a['patient_name']); ?></strong><br>
                                <small class="text-muted"><?php echo sanitize($a['patient_code']); ?></small>
                            </td>
                            <td><?php echo sanitize($a['service_name']); ?></td>
                            <td>
                                <?php echo formatDate($a['appointment_date']); ?><br>
                                <small class="text-muted"><?php echo date('g:i A', strtotime($a['appointment_time'])); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $a['status']; ?>">
                                    <?php echo ucfirst($a['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($a['worker_name'])): ?>
                                    <i class="fa-solid fa-user-doctor text-primary"></i>
                                    <?php echo sanitize($a['worker_name']); ?>
                                <?php else: ?>
                                    <span class="badge badge-pending" style="font-size:0.7rem;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center p-4 text-muted">
                            No appointments yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>