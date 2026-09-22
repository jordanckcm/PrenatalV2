<?php
/**
 * Book New Appointment (Patient)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slot_helper.php';

requireRole('patient');

$pageTitle = "Book Appointment";
$activePage = "book";
$userId = getCurrentUserId();

$errorMsg = '';
$selectedDate = $_GET['date'] ?? date('Y-m-d');

try {
    $db = getDB();
    ensureSlotOverridesTable($db);

    $stmt = $db->query("SELECT id, service_name, duration_minutes FROM services WHERE is_active = 1 ORDER BY service_name ASC");
    $services = $stmt->fetchAll();

    $schedules = getClinicWeeklySchedule($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrf)) {
            $errorMsg = "Security token error. Please refresh and try again.";
        } else {
            $serviceId = (int)($_POST['service_id'] ?? 0);
            $appointmentDate = $_POST['appointment_date'] ?? '';
            $appointmentTime = $_POST['appointment_time'] ?? '';
            $notes = trim($_POST['notes'] ?? '');

            if (!$serviceId || !$appointmentDate || !$appointmentTime) {
                $errorMsg = "Please fill in all required fields.";
            } elseif ($appointmentDate < date('Y-m-d')) {
                $errorMsg = "Cannot book a date in the past.";
            } else {
                if (!isSlotAvailable($db, $appointmentDate, $appointmentTime)) {
                    $errorMsg = "Sorry, that slot is no longer available. Please choose another.";
                } else {
                    $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $patient = $stmt->fetch();

                    if (!$patient) {
                        $patient = ensurePatientProfile($db, $userId);
                    }

                    if (!$patient) {
                        $errorMsg = "Could not find your patient profile. Please contact admin.";
                    } else {
                        $appointmentCode = "APT-" . date('Ymd') . "-" . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);

                        $stmt = $db->prepare("
                            INSERT INTO appointments 
                            (appointment_code, patient_id, service_id, appointment_date, appointment_time, status, notes, created_at)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
                        ");
                        $stmt->execute([
                            $appointmentCode,
                            $patient['id'],
                            $serviceId,
                            $appointmentDate,
                            $appointmentTime,
                            $notes ?: null
                        ]);

                        logAudit('APPOINTMENT_BOOKED', "Patient booked appointment $appointmentCode");

                        header("Location: my_appointments.php?success=" . urlencode("Appointment booked successfully! Code: $appointmentCode"));
                        exit;
                    }
                }
            }
        }
    }

} catch (Exception $e) {
    $errorMsg = "Error: " . $e->getMessage();
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================
   SLOT BUTTONS — CLEAN DESIGN
   ============================================ */
.slot-btn {
    padding: 0.75rem 0.5rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    border: 2px solid var(--border-color);
    background: var(--surface);
    color: var(--text-dark);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    min-height: 72px;
    justify-content: center;
    font-family: inherit;
    text-align: center;
}

.slot-btn .slot-time {
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.1;
}

.slot-btn .slot-status {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

/* AVAILABLE */
.slot-btn.available .slot-status {
    color: #10b981;
}

.slot-btn.available:hover {
    border-color: #f97316;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15);
}

/* FULL */
.slot-btn.full {
    opacity: 0.55;
    cursor: not-allowed;
    background: #fef3c7;
    border-color: #fde68a;
}

.slot-btn.full .slot-status {
    color: #b45309;
}

/* BLOCKED */
.slot-btn.blocked {
    opacity: 0.55;
    cursor: not-allowed;
    background: #f3f4f6;
    border-color: #e5e7eb;
}

.slot-btn.blocked .slot-time {
    color: #9ca3af;
    text-decoration: line-through;
}

.slot-btn.blocked .slot-status {
    color: #9ca3af;
}

/* SELECTED */
.slot-btn.selected-slot {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;
    color: #ffffff !important;
    border-color: #f97316 !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(249, 115, 22, 0.4);
}

.slot-btn.selected-slot .slot-time {
    color: #ffffff !important;
}

.slot-btn.selected-slot .slot-status {
    color: #fff5ed !important;
}

/* Grid */
.slot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.65rem;
}

/* Dark theme adjustments */
:root[data-theme="dark"] .slot-btn.full {
    background: #422006;
    border-color: #78350f;
}

:root[data-theme="dark"] .slot-btn.full .slot-status {
    color: #fbbf24;
}

:root[data-theme="dark"] .slot-btn.blocked {
    background: #1f2937;
    border-color: #374151;
}
</style>

<div class="page-header">
    <div class="page-title">
        <h1>Book New Appointment</h1>
        <p>Schedule your prenatal checkup in just a few clicks</p>
    </div>
    <div>
        <a href="my_appointments.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to My Appointments
        </a>
    </div>
</div>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger mb-4">
        <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($errorMsg); ?>
    </div>
<?php endif; ?>

<!-- Weekly Clinic Schedule -->
<div class="dashboard-card mb-4" style="padding: 1.5rem;">
    <h3 style="margin-bottom: 1rem;">
        <i class="fa-solid fa-clock text-primary"></i> Weekly Clinic Schedule
    </h3>

    <div style="display: flex; flex-wrap: wrap; gap: 0.6rem;">
        <?php
        $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $schedMap = [];
        foreach ($schedules as $s) { $schedMap[$s['day_of_week']] = $s; }
        foreach ($allDays as $day):
            $sched = $schedMap[$day] ?? ['day_of_week' => $day, 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'max_patients_per_slot' => 2, 'is_active' => 0];
            $isClosed = !$sched['is_active'];
        ?>
            <div style="
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.6rem 1rem;
                border-radius: 12px;
                background: <?php echo $isClosed ? 'var(--bg-body)' : '#e8f5e9'; ?>;
                color: <?php echo $isClosed ? 'var(--text-muted)' : '#2e7d32'; ?>;
                font-size: 0.82rem;
                font-weight: 700;
                border: 1px solid <?php echo $isClosed ? 'var(--border-color)' : '#a5d6a7'; ?>;
            ">
                <span><?php echo strtoupper(substr($day, 0, 3)); ?>:</span>
                <span><?php echo $isClosed ? 'CLOSED' : date('g:i A', strtotime($sched['start_time'])) . ' - ' . date('g:i A', strtotime($sched['end_time'])); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Next 14 Days Quick-Pick -->
<div class="dashboard-card mb-4" style="padding: 1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
        <h3 style="margin:0; font-size:1rem;">
            <i class="fa-solid fa-calendar-days text-primary"></i> Next 14 Days Quick-Pick
        </h3>
        <span style="color:#10b981; font-size:0.8rem; font-weight:700;">
            <i class="fa-solid fa-circle" style="font-size:0.5rem;"></i> Live availability
        </span>
    </div>

    <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
        <?php
        $today = new DateTime();
        for ($i = 0; $i < 14; $i++):
            $dayDate = clone $today;
            $dayDate->modify("+$i days");
            $dayName = $dayDate->format('D');
            $dayNum = $dayDate->format('j');
            $dateStr = $dayDate->format('Y-m-d');
            $dayOfWeekFull = $dayDate->format('l');

            $daySched = $schedMap[$dayOfWeekFull] ?? null;
            $isOpen = $daySched && (int)$daySched['is_active'] === 1;
            $isSelected = ($dateStr === $selectedDate);
        ?>
            <div class="quick-pick-date" data-date="<?php echo $dateStr; ?>" style="
                min-width: 85px;
                padding: 0.75rem 0.5rem;
                border-radius: 12px;
                text-align: center;
                background: <?php echo $isSelected ? '#fff5ed' : 'var(--surface)'; ?>;
                border: 2px solid <?php echo $isSelected ? '#f97316' : 'var(--border-color)'; ?>;
                cursor: pointer;
                transition: all 0.2s;
                opacity: <?php echo $isOpen ? '1' : '0.5'; ?>;
            " onclick="selectDate('<?php echo $dateStr; ?>')">
                <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                    <?php echo strtoupper($dayName); ?>
                </div>
                <div class="qp-day-num" style="font-size: 1.5rem; font-weight: 800; color: <?php echo $isSelected ? '#f97316' : 'var(--text-dark)'; ?>; margin: 0.25rem 0; font-family: 'Bricolage Grotesque', sans-serif;">
                    <?php echo $dayNum; ?>
                </div>
                <div style="font-size: 0.7rem; font-weight: 700; color: <?php echo $isOpen ? '#10b981' : '#dc3545'; ?>;">
                    <?php echo $isOpen ? 'Open' : 'Closed'; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Booking Form -->
<div class="dashboard-card" style="padding: 2rem;">
    <h3 style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-calendar-plus text-primary"></i> Booking Details
    </h3>

    <form method="POST" action="" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

        <div class="form-group mb-4">
            <label class="form-label">Select Service <span class="text-danger">*</span></label>
            <select name="service_id" id="service_id" class="form-control" required>
                <option value="">-- Choose a service --</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>">
                        <?php echo sanitize($s['service_name']); ?>
                        (<?php echo (int)$s['duration_minutes']; ?> mins)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-4">
            <label class="form-label">Select Date <span class="text-danger">*</span></label>
            <input type="date" 
                   id="booking_date" 
                   name="appointment_date" 
                   class="form-control" 
                   min="<?php echo date('Y-m-d'); ?>"
                   value="<?php echo sanitize($selectedDate); ?>"
                   required>
        </div>

        <div class="form-group mb-4">
            <label class="form-label">Available Consultation Slots <span class="text-danger">*</span></label>
            <div id="slotContainer" class="slot-grid" style="min-height: 80px;">
                <p class="text-muted" style="grid-column: 1 / -1;">Loading slots...</p>
            </div>
            <small class="text-muted" style="display:block; margin-top:0.75rem; font-size:0.78rem;">
                <i class="fa-solid fa-circle-info"></i> 
                <span style="color:#10b981; font-weight:700;">Green</span> = Available · 
                <span style="color:#b45309; font-weight:700;">Yellow</span> = Full · 
                <span style="color:#9ca3af; font-weight:700;">Gray</span> = Blocked
            </small>
        </div>

        <input type="hidden" name="appointment_time" id="selected_slot" required>

        <div id="selectedSlotPreview" style="display:none; padding:1rem; background:linear-gradient(135deg, #fff5ed 0%, #ffe8dc 100%); border:2px solid #f97316; border-radius:12px; margin-bottom: 1.25rem; align-items: center; gap:0.75rem;">
            <i class="fa-solid fa-check-circle" style="color:#f97316; font-size:1.5rem;"></i>
            <div>
                <small style="color:#9ca3af; font-weight:700; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px;">Selected Slot</small>
                <div style="font-size:1.1rem; font-weight:800; color:#ea580c;" id="selectedSlotText">—</div>
            </div>
        </div>

        <div class="form-group mb-4">
            <label class="form-label">Additional Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="e.g., First pregnancy, may allergies, etc."></textarea>
        </div>

        <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
            <a href="my_appointments.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                <i class="fa-solid fa-calendar-plus"></i> Book Appointment
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dateInput = document.getElementById('booking_date');
    const slotContainer = document.getElementById('slotContainer');
    const selectedSlotInput = document.getElementById('selected_slot');
    const selectedSlotPreview = document.getElementById('selectedSlotPreview');
    const selectedSlotText = document.getElementById('selectedSlotText');
    const submitBtn = document.getElementById('submitBtn');

    // ============================================
    // LOAD SLOTS
    // ============================================
    async function loadSlots() {
        const date = dateInput.value;
        selectedSlotInput.value = '';
        selectedSlotPreview.style.display = 'none';
        submitBtn.disabled = true;

        if (!date) {
            slotContainer.innerHTML = '<p class="text-muted" style="grid-column: 1 / -1;">Please select a date first.</p>';
            return;
        }

        slotContainer.innerHTML = '<p class="text-muted" style="grid-column: 1 / -1;"><i class="fa-solid fa-spinner fa-spin"></i> Loading slots...</p>';

        try {
            const res = await fetch(`<?php echo BASE_URL; ?>api/get_slots.php?date=${date}`);
            const data = await res.json();

            if (!data.success) {
                slotContainer.innerHTML = `<p class="text-danger" style="grid-column: 1 / -1;">${data.message || 'Could not load slots.'}</p>`;
                return;
            }

            if (!data.slots || data.slots.length === 0) {
                slotContainer.innerHTML = '<p class="text-muted" style="grid-column: 1 / -1; text-align:center; padding:2rem;"><i class="fa-solid fa-calendar-xmark" style="font-size:2rem;"></i><br>No slots available for this date.</p>';
                return;
            }

            slotContainer.innerHTML = '';

            data.slots.forEach(slot => {
                const btn = document.createElement('button');
                btn.type = 'button';

                // Determine state
                let state = 'available';
                let statusText = slot.available + (slot.available === 1 ? ' left' : ' left');

                if (slot.is_blocked) {
                    state = 'blocked';
                    statusText = 'Blocked';
                } else if (slot.is_full || slot.available <= 0) {
                    state = 'full';
                    statusText = 'Full';
                }

                btn.className = 'slot-btn ' + state;

                // Time label
                const timeLabel = document.createElement('div');
                timeLabel.className = 'slot-time';
                timeLabel.textContent = slot.time_formatted;

                // Status label
                const statusLabel = document.createElement('div');
                statusLabel.className = 'slot-status';
                statusLabel.textContent = statusText;

                btn.appendChild(timeLabel);
                btn.appendChild(statusLabel);

                // Disable kung full o blocked
                if (state !== 'available') {
                    btn.disabled = true;
                } else {
                    // Click handler
                    btn.onclick = function () {
                        // Reset tanan buttons
                        slotContainer.querySelectorAll('.slot-btn').forEach(b => {
                            b.classList.remove('selected-slot');
                        });

                        // Highlight selected
                        btn.classList.add('selected-slot');

                        // Set hidden input
                        selectedSlotInput.value = slot.time;
                        selectedSlotText.textContent = slot.time_formatted;
                        selectedSlotPreview.style.display = 'flex';
                        submitBtn.disabled = false;
                    };
                }

                slotContainer.appendChild(btn);
            });

        } catch (e) {
            console.error('Error loading slots:', e);
            slotContainer.innerHTML = '<p class="text-danger" style="grid-column: 1 / -1;">Network error. Please try again.</p>';
        }
    }

    // ============================================
    // SELECT DATE (Quick-Pick)
    // ============================================
    window.selectDate = function(dateStr) {
        dateInput.value = dateStr;
        
        // Update quick-pick highlight
        document.querySelectorAll('.quick-pick-date').forEach(el => {
            const dayNumEl = el.querySelector('.qp-day-num');
            if (el.dataset.date === dateStr) {
                el.style.background = '#fff5ed';
                el.style.borderColor = '#f97316';
                if (dayNumEl) dayNumEl.style.color = '#f97316';
            } else {
                el.style.background = 'var(--surface)';
                el.style.borderColor = 'var(--border-color)';
                if (dayNumEl) dayNumEl.style.color = 'var(--text-dark)';
            }
        });
        
        loadSlots();
    };

    dateInput.addEventListener('change', function() {
        // Update quick-pick highlight sa manual date change
        const dateStr = this.value;
        document.querySelectorAll('.quick-pick-date').forEach(el => {
            const dayNumEl = el.querySelector('.qp-day-num');
            if (el.dataset.date === dateStr) {
                el.style.background = '#fff5ed';
                el.style.borderColor = '#f97316';
                if (dayNumEl) dayNumEl.style.color = '#f97316';
            } else {
                el.style.background = 'var(--surface)';
                el.style.borderColor = 'var(--border-color)';
                if (dayNumEl) dayNumEl.style.color = 'var(--text-dark)';
            }
        });
        loadSlots();
    });

    // Initial load
    if (dateInput.value) {
        loadSlots();
    }

    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function (e) {
        if (!selectedSlotInput.value) {
            e.preventDefault();
            alert('Please select a time slot.');
            return false;
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>