<?php
/**
 * Calendar View (Healthcare Worker) - Modern Design
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('healthcare_worker');

$pageTitle = "Calendar";
$activePage = "calendar";
$userId = getCurrentUserId();

$currentMonth = (int)($_GET['month'] ?? date('n'));
$currentYear = (int)($_GET['year'] ?? date('Y'));

if ($currentMonth < 1) $currentMonth = 1;
if ($currentMonth > 12) $currentMonth = 12;
if ($currentYear < 2020) $currentYear = 2020;
if ($currentYear > 2100) $currentYear = 2100;

$firstDayOfMonth = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$daysInMonth = (int)date('t', $firstDayOfMonth);
$firstWeekday = (int)date('w', $firstDayOfMonth);

$monthName = date('F Y', $firstDayOfMonth);
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$today = date('Y-m-d');

try {
    $db = getDB();

    $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
    $endDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $daysInMonth);

    $stmt = $db->prepare("
        SELECT a.*, s.service_name, u.full_name as patient_name, p.patient_code
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN services s ON a.service_id = s.id
        WHERE a.appointment_date BETWEEN ? AND ?
        AND (a.healthcare_worker_id = ? OR a.healthcare_worker_id IS NULL)
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([$startDate, $endDate, $userId]);
    $appointments = $stmt->fetchAll();

    $appointmentsByDate = [];
    foreach ($appointments as $appt) {
        $appointmentsByDate[$appt['appointment_date']][] = $appt;
    }

    $totalAppts = count($appointments);
    $totalPatients = count(array_unique(array_column($appointments, 'patient_id')));
    $busiestDay = 0;
    $busiestDate = '';
    foreach ($appointmentsByDate as $date => $appts) {
        if (count($appts) > $busiestDay) {
            $busiestDay = count($appts);
            $busiestDate = $date;
        }
    }

    $stmt = $db->query("SELECT * FROM schedules");
    $schedules = [];
    while ($row = $stmt->fetch()) {
        $schedules[$row['day_of_week']] = $row;
    }

} catch (Exception $e) {
    die("Calendar error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   MODERN CALENDAR DESIGN
   ============================================ */

:root {
    --gradient-primary: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --gradient-info: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    --gradient-purple: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    --gradient-pink: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
}

/* ---------- STATS BAR ---------- */
.modern-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card-modern {
    position: relative;
    background: var(--surface);
    border-radius: 20px;
    padding: 1.5rem;
    overflow: hidden;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--border-color);
}

.stat-card-modern::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    opacity: 0.1;
    transition: all 0.35s ease;
}

.stat-card-modern.orange::before { background: #f97316; }
.stat-card-modern.blue::before { background: #3b82f6; }
.stat-card-modern.green::before { background: #10b981; }
.stat-card-modern.purple::before { background: #8b5cf6; }

.stat-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
}

.stat-card-modern:hover::before {
    transform: scale(1.5);
    opacity: 0.15;
}

.stat-icon-modern {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: #fff;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.stat-card-modern.orange .stat-icon-modern { background: var(--gradient-primary); box-shadow: 0 8px 20px rgba(249, 115, 22, 0.35); }
.stat-card-modern.blue .stat-icon-modern { background: var(--gradient-info); box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35); }
.stat-card-modern.green .stat-icon-modern { background: var(--gradient-success); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35); }
.stat-card-modern.purple .stat-icon-modern { background: var(--gradient-purple); box-shadow: 0 8px 20px rgba(139, 92, 246, 0.35); }

.stat-label-modern {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
    font-weight: 800;
    margin-bottom: 0.35rem;
    position: relative;
    z-index: 1;
}

.stat-value-modern {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-dark);
    font-family: 'Bricolage Grotesque', sans-serif;
    line-height: 1;
    position: relative;
    z-index: 1;
}

.stat-value-modern small {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
    font-family: 'Figtree', sans-serif;
    margin-left: 0.25rem;
}

/* ---------- CALENDAR WRAPPER ---------- */
.calendar-wrapper-modern {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.calendar-wrapper-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-primary);
    border-radius: 24px 24px 0 0;
}

/* ---------- CALENDAR HEADER ---------- */
.cal-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.cal-title-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.cal-icon-modern {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    background: var(--gradient-primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 10px 25px rgba(249, 115, 22, 0.35);
    position: relative;
}

.cal-icon-modern::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 22px;
    background: var(--gradient-primary);
    opacity: 0.2;
    z-index: -1;
    filter: blur(8px);
}

.cal-title-text-modern h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-dark);
    margin: 0;
    font-family: 'Bricolage Grotesque', sans-serif;
    letter-spacing: -0.8px;
    line-height: 1.1;
}

.cal-title-text-modern small {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
}

/* ---------- NAVIGATION ---------- */
.cal-nav-modern {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: var(--bg-body);
    padding: 0.4rem;
    border-radius: 14px;
    border: 1px solid var(--border-color);
}

.cal-nav-btn-modern {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: var(--text-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.25s ease;
    text-decoration: none;
    font-size: 0.95rem;
}

.cal-nav-btn-modern:hover {
    background: var(--primary);
    color: #fff;
    transform: scale(1.05);
}

.cal-today-btn-modern {
    padding: 0 1.1rem;
    height: 40px;
    border-radius: 10px;
    border: none;
    background: var(--gradient-primary);
    color: #fff;
    font-weight: 800;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cal-today-btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(249, 115, 22, 0.45);
}

/* ---------- LEGEND ---------- */
.cal-legend-modern {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.legend-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    background: var(--bg-body);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-dark);
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}

.legend-chip:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.legend-dot-modern {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.legend-dot-modern.open { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
.legend-dot-modern.closed { background: #dc3545; box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2); }
.legend-dot-modern.today { background: #f97316; box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2); }

/* ---------- WEEKDAYS ---------- */
.cal-weekdays-modern {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}

.weekday-modern {
    text-align: center;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--text-muted);
    padding: 0.75rem 0;
}

.weekday-modern.weekend {
    color: var(--primary);
}

/* ---------- GRID ---------- */
.cal-grid-modern {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
}

.cal-day-modern {
    background: var(--surface);
    border: 1.5px solid var(--border-color);
    border-radius: 16px;
    min-height: 140px;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.cal-day-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: transparent;
    transition: all 0.3s;
}

.cal-day-modern:hover {
    border-color: var(--primary);
    box-shadow: 0 12px 30px rgba(249, 115, 22, 0.15);
    transform: translateY(-4px);
}

.cal-day-modern:hover::before {
    background: var(--gradient-primary);
}

.cal-day-modern.empty {
    background: transparent;
    border: 1.5px dashed var(--border-color);
    opacity: 0.2;
    cursor: default;
}

.cal-day-modern.empty:hover {
    transform: none;
    box-shadow: none;
    border-color: var(--border-color);
}

.cal-day-modern.today {
    border-color: var(--primary);
    border-width: 2px;
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.06) 0%, var(--surface) 100%);
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1), 0 8px 24px rgba(249, 115, 22, 0.15);
}

.cal-day-modern.today::before {
    background: var(--gradient-primary);
    height: 4px;
}

.cal-day-modern.closed {
    background: repeating-linear-gradient(
        45deg,
        var(--surface),
        var(--surface) 12px,
        rgba(220, 53, 69, 0.02) 12px,
        rgba(220, 53, 69, 0.02) 24px
    );
    border-color: rgba(220, 53, 69, 0.15);
}

/* ---------- DAY HEADER ---------- */
.day-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
}

.day-num-modern {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-dark);
    font-family: 'Bricolage Grotesque', sans-serif;
    line-height: 1;
    letter-spacing: -0.5px;
}

.cal-day-modern.today .day-num-modern {
    color: var(--primary);
    font-size: 1.25rem;
}

.cal-day-modern.closed .day-num-modern {
    color: var(--text-muted);
}

/* ---------- BADGES ---------- */
.today-chip {
    font-size: 0.55rem;
    background: var(--gradient-primary);
    color: #fff;
    padding: 0.25rem 0.55rem;
    border-radius: 8px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    box-shadow: 0 3px 8px rgba(249, 115, 22, 0.35);
    animation: pulseGlow 2s ease-in-out infinite;
}

@keyframes pulseGlow {
    0%, 100% { box-shadow: 0 3px 8px rgba(249, 115, 22, 0.35); }
    50% { box-shadow: 0 3px 16px rgba(249, 115, 22, 0.6); }
}

.appt-chip-modern {
    font-size: 0.6rem;
    color: var(--primary);
    font-weight: 800;
    background: var(--primary-light);
    padding: 0.2rem 0.5rem;
    border-radius: 8px;
    letter-spacing: 0.3px;
}

.closed-chip-modern {
    font-size: 0.55rem;
    color: #dc3545;
    font-weight: 800;
    background: rgba(220, 53, 69, 0.1);
    padding: 0.2rem 0.5rem;
    border-radius: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ---------- APPOINTMENTS ---------- */
.appt-list-modern {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
    overflow: hidden;
}

.appt-item-modern {
    background: linear-gradient(135deg, #fff5ed 0%, #ffe8dc 100%);
    border-radius: 10px;
    padding: 0.45rem 0.6rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: #7c2d12;
    cursor: pointer;
    transition: all 0.25s ease;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    border-left: 3px solid var(--primary);
    position: relative;
}

:root[data-theme="dark"] .appt-item-modern {
    background: linear-gradient(135deg, #2a1a0f 0%, #3a1a0f 100%);
    color: #fdba74;
}

.appt-item-modern:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25);
}

.appt-time-modern {
    font-weight: 800;
    color: var(--primary);
    font-size: 0.6rem;
    background: rgba(249, 115, 22, 0.15);
    padding: 0.15rem 0.4rem;
    border-radius: 6px;
    flex-shrink: 0;
}

:root[data-theme="dark"] .appt-time-modern {
    color: #fb923c;
    background: rgba(251, 146, 60, 0.2);
}

.appt-name-modern {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 700;
}

.appt-more-modern {
    font-size: 0.6rem;
    color: var(--primary);
    font-weight: 800;
    text-align: center;
    padding: 0.3rem;
    background: var(--primary-light);
    border-radius: 8px;
    margin-top: 0.2rem;
    border: 1px dashed rgba(249, 115, 22, 0.3);
    transition: all 0.2s;
}

.appt-more-modern:hover {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 1024px) {
    .calendar-wrapper-modern { padding: 1.5rem; }
    .cal-day-modern { min-height: 120px; }
    .modern-stats { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .calendar-wrapper-modern { padding: 1rem; border-radius: 18px; }
    .cal-title-text-modern h2 { font-size: 1.25rem; }
    .cal-icon-modern { width: 46px; height: 46px; font-size: 1.2rem; border-radius: 14px; }
    .cal-day-modern { min-height: 90px; padding: 0.5rem; border-radius: 12px; }
    .day-num-modern { font-size: 0.88rem; }
    .cal-day-modern.today .day-num-modern { font-size: 1rem; }
    .appt-item-modern { font-size: 0.6rem; padding: 0.3rem 0.45rem; }
    .today-chip, .closed-chip-modern { display: none; }
    .weekday-modern { font-size: 0.6rem; padding: 0.4rem 0; letter-spacing: 0.8px; }
    .cal-grid-modern, .cal-weekdays-modern { gap: 5px; }
    .cal-legend-modern { gap: 0.5rem; }
    .legend-chip { padding: 0.4rem 0.7rem; font-size: 0.68rem; }
    .cal-nav-btn-modern, .cal-today-btn-modern { height: 36px; }
    .cal-nav-btn-modern { width: 36px; }
    .cal-today-btn-modern { padding: 0 0.85rem; font-size: 0.75rem; }
    .modern-stats { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .stat-card-modern { padding: 1rem; border-radius: 16px; }
    .stat-value-modern { font-size: 1.5rem; }
    .stat-icon-modern { width: 44px; height: 44px; font-size: 1.15rem; }
}

@media (max-width: 480px) {
    .cal-day-modern { min-height: 72px; padding: 0.4rem; }
    .day-num-modern { font-size: 0.78rem; }
    .appt-item-modern { display: none; }
    .appt-chip-modern { font-size: 0.55rem; padding: 0.15rem 0.35rem; }
    .cal-day-modern.has-appts::after {
        content: '';
        position: absolute;
        bottom: 8px;
        left: 50%;
        transform: translateX(-50%);
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--gradient-primary);
        box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
    }
    .modern-stats { grid-template-columns: 1fr; }
    .cal-header-modern { gap: 0.75rem; }
    .cal-title-modern { gap: 0.75rem; }
    .cal-nav-modern { padding: 0.3rem; }
}
</style>

<div class="page-header">
    <div class="page-title">
        <h1>Calendar</h1>
        <p>View your appointment schedule</p>
    </div>
</div>

<!-- Stats -->
<div class="modern-stats">
    <div class="stat-card-modern orange">
        <div class="stat-icon-modern"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-label-modern">Total Appointments</div>
        <div class="stat-value-modern"><?php echo $totalAppts; ?> <small>this month</small></div>
    </div>

    <div class="stat-card-modern blue">
        <div class="stat-icon-modern"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label-modern">Unique Patients</div>
        <div class="stat-value-modern"><?php echo $totalPatients; ?> <small>patients</small></div>
    </div>

    <div class="stat-card-modern green">
        <div class="stat-icon-modern"><i class="fa-solid fa-chart-line"></i></div>
        <div class="stat-label-modern">Busiest Day</div>
        <div class="stat-value-modern"><?php echo $busiestDay; ?> <small><?php echo $busiestDate ? date('M j', strtotime($busiestDate)) : 'N/A'; ?></small></div>
    </div>

    <div class="stat-card-modern purple">
        <div class="stat-icon-modern"><i class="fa-solid fa-percent"></i></div>
        <div class="stat-label-modern">Avg / Day</div>
        <div class="stat-value-modern"><?php echo $daysInMonth > 0 ? round($totalAppts / $daysInMonth, 1) : 0; ?> <small>appts</small></div>
    </div>
</div>

<!-- Calendar -->
<div class="calendar-wrapper-modern">

    <div class="cal-header-modern">
        <div class="cal-title-modern">
            <div class="cal-icon-modern">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div class="cal-title-text-modern">
                <h2><?php echo $monthName; ?></h2>
                <small><?php echo $totalAppts; ?> appointments this month</small>
            </div>
        </div>

        <div class="cal-nav-modern">
            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="cal-nav-btn-modern" title="Previous Month">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="cal-today-btn-modern">
                <i class="fa-solid fa-calendar-day"></i> Today
            </a>
            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="cal-nav-btn-modern" title="Next Month">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <div class="cal-legend-modern">
        <div class="legend-chip">
            <span class="legend-dot-modern open"></span> Clinic Open
        </div>
        <div class="legend-chip">
            <span class="legend-dot-modern closed"></span> Clinic Closed
        </div>
        <div class="legend-chip">
            <span class="legend-dot-modern today"></span> Today
        </div>
    </div>

    <div class="cal-weekdays-modern">
        <div class="weekday-modern weekend">Sun</div>
        <div class="weekday-modern">Mon</div>
        <div class="weekday-modern">Tue</div>
        <div class="weekday-modern">Wed</div>
        <div class="weekday-modern">Thu</div>
        <div class="weekday-modern">Fri</div>
        <div class="weekday-modern weekend">Sat</div>
    </div>

    <div class="cal-grid-modern">
        <?php
        for ($i = 0; $i < $firstWeekday; $i++) {
            echo '<div class="cal-day-modern empty"></div>';
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
            $dayOfWeek = date('l', strtotime($dateStr));
            $isToday = ($dateStr === $today);
            $dayAppts = $appointmentsByDate[$dateStr] ?? [];
            $apptCount = count($dayAppts);
            $isClosed = isset($schedules[$dayOfWeek]) && !$schedules[$dayOfWeek]['is_active'];

            $classes = 'cal-day-modern';
            if ($isToday) $classes .= ' today';
            if ($isClosed) $classes .= ' closed';
            if ($apptCount > 0) $classes .= ' has-appts';

            echo '<div class="' . $classes . '">';

            echo '<div class="day-header-modern">';
            echo '<span class="day-num-modern">' . $day . '</span>';
            if ($isToday) {
                echo '<span class="today-chip">Today</span>';
            } elseif ($isClosed && $apptCount === 0) {
                echo '<span class="closed-chip-modern">Closed</span>';
            } elseif ($apptCount > 0) {
                echo '<span class="appt-chip-modern">' . $apptCount . ' appt' . ($apptCount > 1 ? 's' : '') . '</span>';
            }
            echo '</div>';

            if ($apptCount > 0) {
                echo '<div class="appt-list-modern">';
                $shown = 0;
                foreach ($dayAppts as $appt) {
                    if ($shown >= 3) break;
                    $time = date('g:i A', strtotime($appt['appointment_time']));
                    echo '<div class="appt-item-modern" title="' . sanitize($appt['patient_name']) . ' - ' . sanitize($appt['service_name']) . '">';
                    echo '<span class="appt-time-modern">' . $time . '</span>';
                    echo '<span class="appt-name-modern">' . sanitize($appt['patient_name']) . '</span>';
                    echo '</div>';
                    $shown++;
                }
                if ($apptCount > 3) {
                    echo '<div class="appt-more-modern">+' . ($apptCount - 3) . ' more</div>';
                }
                echo '</div>';
            }

            echo '</div>';
        }

        $totalCells = $firstWeekday + $daysInMonth;
        $remaining = (7 - ($totalCells % 7)) % 7;
        for ($i = 0; $i < $remaining; $i++) {
            echo '<div class="cal-day-modern empty"></div>';
        }
        ?>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>