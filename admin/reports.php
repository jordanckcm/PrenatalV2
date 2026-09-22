<?php
/**
 * Reports & Statistics Center (Administrator & Healthcare Worker)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'healthcare_worker']);

$pageTitle = "Reports & Analytics";
$activePage = "reports";

try {
    $db = getDB();

    $totalAppts = (int)$db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $completedCount = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn();
    $missedCount = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'missed'")->fetchColumn();
    $cancelledCount = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'")->fetchColumn();
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();

    $completionRate = $totalAppts > 0 ? round(($completedCount / $totalAppts) * 100, 1) : 0;

    // Service Popularity Breakdown
    $servicesStmt = $db->query("SELECT s.service_name, s.duration_minutes, COUNT(a.id) as total_booked 
                                FROM services s 
                                LEFT JOIN appointments a ON s.id = a.service_id 
                                GROUP BY s.id 
                                ORDER BY total_booked DESC");
    $serviceStats = $servicesStmt->fetchAll();

} catch (Exception $e) {
    die("Reports error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="page-title">
        <h1 style="font-size: 2rem; font-weight: 800; font-family: 'Bricolage Grotesque', sans-serif;">Reports & Analytics</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.2rem;">Overview of clinical metrics, service demand, and exportable data files.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="../api/export_report.php?type=appointments" class="btn btn-primary btn-sm" style="border-radius: 999px; padding: 0.5rem 1.25rem;">
            <i class="fa-solid fa-file-csv"></i> Export Appointments (CSV)
        </a>
        <a href="../api/export_report.php?type=records" class="btn btn-outline btn-sm" style="border-radius: 999px; padding: 0.5rem 1.25rem;">
            <i class="fa-solid fa-file-csv"></i> Export Records (CSV)
        </a>
    </div>
</div>

<!-- Metrics Cards Grid -->
<div class="metrics-grid mb-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
    
    <div class="metric-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.35rem;">
        <div class="metric-info">
            <p style="color: var(--text-muted); font-size: 0.82rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem;">Total Booked</p>
            <h3 style="color: #F97316; font-size: 1.8rem; font-weight: 800; margin: 0;"><?php echo $totalAppts; ?></h3>
        </div>
        <div class="metric-icon" style="background: rgba(249, 115, 22, 0.15); color: #F97316; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </div>

    <div class="metric-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.35rem;">
        <div class="metric-info">
            <p style="color: var(--text-muted); font-size: 0.82rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem;">Completion Rate</p>
            <h3 style="color: #34D399; font-size: 1.8rem; font-weight: 800; margin: 0;"><?php echo $completionRate; ?>%</h3>
        </div>
        <div class="metric-icon" style="background: rgba(52, 211, 153, 0.15); color: #34D399; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>

    <div class="metric-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.35rem;">
        <div class="metric-info">
            <p style="color: var(--text-muted); font-size: 0.82rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem;">Missed Visits</p>
            <h3 style="color: #FBBF24; font-size: 1.8rem; font-weight: 800; margin: 0;"><?php echo $missedCount; ?></h3>
        </div>
        <div class="metric-icon" style="background: rgba(251, 191, 36, 0.15); color: #FBBF24; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
            <i class="fa-solid fa-user-slash"></i>
        </div>
    </div>

    <div class="metric-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.35rem;">
        <div class="metric-info">
            <p style="color: var(--text-muted); font-size: 0.82rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem;">Cancelled</p>
            <h3 style="color: #F87171; font-size: 1.8rem; font-weight: 800; margin: 0;"><?php echo $cancelledCount; ?></h3>
        </div>
        <div class="metric-icon" style="background: rgba(248, 113, 113, 0.15); color: #F87171; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
            <i class="fa-solid fa-ban"></i>
        </div>
    </div>

</div>

<!-- Service Breakdown Section -->
<div class="settings-card mb-4" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
    <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--text-dark); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-chart-line" style="color: #F97316;"></i> Service Utilization Distribution
            </h3>
            <p style="color: var(--text-muted); margin: 0; font-size: 0.88rem;">Popularity and appointment volume per clinic service</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Est. Duration</th>
                    <th>Total Bookings</th>
                    <th>Utilization Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($serviceStats) > 0): ?>
                    <?php foreach ($serviceStats as $ss): ?>
                        <?php $pct = $totalAppts > 0 ? round(($ss['total_booked'] / $totalAppts) * 100, 1) : 0; ?>
                        <tr>
                            <td><strong style="color: var(--text-dark); font-size: 0.95rem;"><?php echo sanitize($ss['service_name']); ?></strong></td>
                            <td><span style="color: var(--text-muted);"><?php echo (int)($ss['duration_minutes'] ?: 30); ?> mins</span></td>
                            <td><strong style="color: #F97316; font-size: 0.95rem;"><?php echo $ss['total_booked']; ?></strong> visits</td>
                            <td style="min-width: 200px;">
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <div style="flex:1; background: var(--bg-body); border: 1px solid var(--border-color); height: 10px; border-radius: 999px; overflow: hidden;">
                                        <div style="width:<?php echo $pct; ?>%; background: #F97316; height:100%; border-radius: 999px;"></div>
                                    </div>
                                    <span style="font-size: 0.82rem; font-weight: 800; color: var(--text-dark); min-width: 45px; text-align: right;"><?php echo $pct; ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center p-4 text-muted">No service data recorded yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
