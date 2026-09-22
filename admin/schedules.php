<?php
/**
 * Clinic Schedules & Slot Management (Administrator)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slot_helper.php';

requireRole('admin');

$pageTitle = "Schedules & Slots";
$activePage = "schedules";

$selectedDate = $_GET['date'] ?? date('Y-m-d');

try {
    $db = getDB();
    ensureSlotOverridesTable($db);
    $schedules = getClinicWeeklySchedule($db);
} catch (Exception $e) {
    die("Schedules error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Clinic Schedules & Live Slots</h1>
        <p>Manage clinic operating hours and individual time slots</p>
    </div>
    <div>
        <span style="display:inline-flex; align-items:center; gap:0.5rem; padding:0.5rem 1rem; border-radius:20px; background:#e8f5e9; color:#2e7d32; font-size:0.8rem; font-weight:700;">
            <i class="fa-solid fa-circle" style="font-size:0.5rem;"></i> LIVE
        </span>
    </div>
</div>

<!-- Weekly Clinic Schedule -->
<div class="dashboard-card mb-4" style="padding: 1.5rem;">
    <h3 style="margin-bottom: 1rem;">
        <i class="fa-solid fa-clock text-primary"></i> Weekly Clinic Schedule:
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
                cursor: pointer;
            " onclick='openDayModal(<?php echo json_encode($sched, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                <span><?php echo strtoupper(substr($day, 0, 3)); ?>:</span>
                <span><?php echo $isClosed ? 'CLOSED' : date('g:i A', strtotime($sched['start_time'])) . ' - ' . date('g:i A', strtotime($sched['end_time'])); ?></span>
                <i class="fa-solid fa-pen" style="font-size:0.7rem; opacity:0.6;"></i>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Quick-Pick Dates -->
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
                opacity: <?php echo $isOpen ? '1' : '0.5'; ?>;
            " onclick="selectDate('<?php echo $dateStr; ?>')">
                <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">
                    <?php echo strtoupper($dayName); ?>
                </div>
                <div style="font-size: 1.5rem; font-weight: 800; color: <?php echo $isSelected ? '#f97316' : 'var(--text-dark)'; ?>; margin: 0.25rem 0;">
                    <?php echo $dayNum; ?>
                </div>
                <div style="font-size: 0.7rem; font-weight: 700; color: <?php echo $isOpen ? '#10b981' : '#dc3545'; ?>;">
                    <?php echo $isOpen ? 'Open' : 'Closed'; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Slots Grid -->
<div class="dashboard-card mb-4" style="padding: 1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <h3 style="margin:0;">
                <i class="fa-solid fa-clock text-primary"></i> Slots for <span id="selectedDateLabel"><?php echo date('M d, Y', strtotime($selectedDate)); ?></span>
            </h3>
            <small class="text-muted" id="lastUpdated" style="font-size:0.72rem;"></small>
        </div>
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <input type="date" id="datePicker" value="<?php echo sanitize($selectedDate); ?>" 
                   style="padding:0.5rem 0.75rem; border-radius:8px; border:1px solid var(--border-color); background:var(--surface); color:var(--text-dark); font-size:0.85rem;"
                   onchange="selectDate(this.value)">
            <button type="button" class="btn btn-outline btn-sm" onclick="loadSlots(_currentSelectedDate)">
                <i class="fa-solid fa-rotate"></i> Refresh
            </button>
        </div>
    </div>

    <div id="slotsContainer">
        <p class="text-muted text-center"><i class="fa-solid fa-spinner fa-spin"></i> Loading slots...</p>
    </div>
</div>

<!-- Edit Day Schedule Modal -->
<div id="dayModal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="dm_title"><i class="fa-solid fa-calendar-day text-primary"></i> Edit Day Schedule</h3>
            <button type="button" onclick="closeModal('dayModal')" style="background:none;border:none;cursor:pointer;font-size:1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="dm_day">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Opening Time</label>
                    <input type="time" id="dm_start" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Closing Time</label>
                    <input type="time" id="dm_end" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Max Patients per Slot</label>
                <input type="number" id="dm_capacity" class="form-control" min="1" max="20" value="2">
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem;">
                <input type="checkbox" id="dm_active" style="width:18px; height:18px;">
                <label class="form-label" style="margin-bottom:0;">Clinic Open This Day</label>
            </div>
            <div id="dm_error" class="alert alert-danger" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('dayModal')">Cancel</button>
            <button type="button" id="dm_submit_btn" class="btn btn-primary" onclick="submitDayModal()">
                <i class="fa-solid fa-floppy-disk"></i> Save Day Schedule
            </button>
        </div>
    </div>
</div>

<script>
const _adminCsrf = '<?php echo getCsrfToken(); ?>';
const _baseUrl = '<?php echo BASE_URL; ?>';
let _currentSelectedDate = '<?php echo $selectedDate; ?>';
let _liveInterval = null;
let _lastSlotsHash = '';

/* ============================================
   MODAL HELPERS
   ============================================ */
function openModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) {
        el.style.display = 'flex';
        el.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) {
        el.style.display = 'none';
        el.classList.remove('is-open');
        document.body.style.overflow = '';
    }
}

/* ============================================
   DATE SELECTION
   ============================================ */
function selectDate(dateStr) {
    _currentSelectedDate = dateStr;
    document.getElementById('datePicker').value = dateStr;
    
    const d = new Date(dateStr + 'T00:00:00');
    const options = { year: 'numeric', month: 'short', day: '2-digit' };
    document.getElementById('selectedDateLabel').textContent = d.toLocaleDateString('en-US', options);
    
    document.querySelectorAll('.quick-pick-date').forEach(el => {
        if (el.dataset.date === dateStr) {
            el.style.background = '#fff5ed';
            el.style.borderColor = '#f97316';
        } else {
            el.style.background = 'var(--surface)';
            el.style.borderColor = 'var(--border-color)';
        }
    });
    
    _lastSlotsHash = '';
    loadSlots(dateStr);
}

/* ============================================
   LOAD SLOTS
   ============================================ */
async function loadSlots(dateStr, isAutoRefresh = false) {
    const container = document.getElementById('slotsContainer');
    
    if (!isAutoRefresh) {
        container.innerHTML = '<p class="text-muted text-center"><i class="fa-solid fa-spinner fa-spin"></i> Loading slots...</p>';
    }

    try {
        const res = await fetch(_baseUrl + 'api/get_slots.php?date=' + dateStr);
        const data = await res.json();

        if (!data.success || !data.slots || data.slots.length === 0) {
            container.innerHTML = '<p class="text-muted text-center"><i class="fa-solid fa-calendar-xmark"></i> No slots available for this date.</p>';
            return;
        }

        const currentHash = JSON.stringify(data.slots);
        if (isAutoRefresh && currentHash === _lastSlotsHash) {
            document.getElementById('lastUpdated').textContent = 'Last updated: ' + new Date().toLocaleTimeString();
            return;
        }
        _lastSlotsHash = currentHash;

        renderSlotsGrid(data.slots, dateStr);
        document.getElementById('lastUpdated').textContent = 'Last updated: ' + new Date().toLocaleTimeString();
    } catch (e) {
        if (!isAutoRefresh) {
            container.innerHTML = '<p class="text-danger text-center">Could not load slots.</p>';
        }
    }
}

/* ============================================
   RENDER SLOTS GRID (TIME, CAPACITY, LOCK, DELETE)
   ============================================ */
function renderSlotsGrid(slots, dateStr) {
    let html = '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:0.75rem;">';

    let totalAvailable = 0, totalBooked = 0, totalCapacity = 0;

    slots.forEach(slot => {
        const bookedVal = slot.booked || 0;
        const availVal = slot.available || 0;
        const capVal = slot.capacity || 0;

        totalAvailable += availVal;
        totalBooked += bookedVal;
        totalCapacity += capVal;

        let cardBg, borderColor, statusColor, statusText;
        if (slot.is_blocked) {
            cardBg = '#fee2e2'; borderColor = '#dc3545'; statusColor = '#dc3545'; statusText = 'Blocked';
        } else if (slot.is_full || availVal <= 0) {
            cardBg = '#fef3c7'; borderColor = '#f59e0b'; statusColor = '#f59e0b'; statusText = 'Full';
        } else {
            cardBg = 'var(--surface)'; borderColor = 'var(--border-color)'; statusColor = '#10b981'; statusText = availVal + ' left';
        }

        html += '<div style="border: 2px solid ' + borderColor + '; border-radius: 10px; padding: 0.75rem; background: ' + cardBg + '; text-align: center;">';

        // TIME INPUT
        html += '<input type="time" value="' + (slot.time || '').substring(0,5) + '" ';
        html += 'style="width:100%; padding:0.35rem; border-radius:6px; border:1px solid var(--border-color); background:#fff; color:var(--text-dark); font-size:0.85rem; text-align:center; font-weight:700; margin-bottom:0.35rem;" ';
        html += 'onchange="updateSlotTime(\'' + dateStr + '\', \'' + slot.time + '\', this.value)">';

        html += '<div style="font-size: 0.72rem; color: ' + statusColor + '; font-weight: 700; margin-bottom: 0.35rem;">' + statusText + '</div>';
        html += '<div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 0.5rem;">' + bookedVal + ' / ' + capVal + ' booked</div>';
        html += '<div style="display:flex; gap:0.25rem; justify-content:center;">';

        // CAPACITY INPUT
        html += '<input type="number" min="1" max="20" value="' + capVal + '" title="Capacity" style="width:45px; padding:0.2rem 0.35rem; border-radius:5px; border:1px solid var(--border-color); background:var(--surface); color:var(--text-dark); font-size:0.72rem; text-align:center;" onchange="updateCapacity(\'' + dateStr + '\', \'' + slot.time + '\', this.value)">';

        // LOCK/UNLOCK BUTTON
        html += '<button type="button" class="btn btn-outline btn-sm" title="' + (slot.is_blocked ? 'Unblock' : 'Block') + '" style="color:' + (slot.is_blocked ? '#10b981' : '#dc3545') + '; font-size:0.65rem; padding:0.25rem 0.4rem;" onclick="toggleBlock(\'' + dateStr + '\', \'' + slot.time + '\', ' + (slot.is_blocked ? 0 : 1) + ')">';
        html += '<i class="fa-solid fa-' + (slot.is_blocked ? 'unlock' : 'lock') + '"></i>';
        html += '</button>';

        // DELETE BUTTON
        html += '<button type="button" class="btn btn-outline btn-sm" title="Delete this slot" style="color:#dc3545; font-size:0.65rem; padding:0.25rem 0.4rem;" onclick="deleteSlot(\'' + dateStr + '\', \'' + slot.time + '\', ' + bookedVal + ')">';
        html += '<i class="fa-solid fa-trash"></i>';
        html += '</button>';

        html += '</div></div>';
    });

    html += '</div>';
    html += '<div style="margin-top: 1rem; padding: 0.75rem 1rem; background: var(--bg-body); border-radius: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">';
    html += '<span><strong>' + totalAvailable + '</strong> available / <strong>' + totalCapacity + '</strong> total</span>';
    html += '<span class="text-muted">' + totalBooked + ' booked</span>';
    html += '</div>';

    document.getElementById('slotsContainer').innerHTML = html;
}

/* ============================================
   UPDATE SLOT TIME
   ============================================ */
async function updateSlotTime(date, oldTime, newTime) {
    if (!newTime || newTime === oldTime) return;

    if (!confirm('Change time from ' + oldTime + ' to ' + newTime + '?')) {
        loadSlots(date);
        return;
    }

    const fd = new FormData();
    fd.append('action', 'update_slot_time');
    fd.append('override_date', date);
    fd.append('old_start_time', oldTime);
    fd.append('old_end_time', oldTime);
    fd.append('new_start_time', newTime);
    fd.append('new_end_time', newTime);
    fd.append('csrf_token', _adminCsrf);

    try {
        const res = await fetch(_baseUrl + 'api/manage_slots.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            _lastSlotsHash = '';
            loadSlots(date);
        } else {
            alert(data.message || 'Could not update time.');
            loadSlots(date);
        }
    } catch (e) {
        alert('Network error.');
        loadSlots(date);
    }
}

/* ============================================
   DELETE SLOT
   ============================================ */
async function deleteSlot(date, time, bookedCount) {
    if (bookedCount > 0) {
        alert('CANNOT DELETE!\n\nThere are ' + bookedCount + ' existing booking(s) in this slot.\n\nYou need to cancel or reschedule the appointments first before deleting this slot.');
        return;
    }

    if (!confirm('DELETE SLOT?\n\nDate: ' + date + '\nTime: ' + time + '\n\nThis action is permanent and cannot be undone.')) {
        return;
    }

    const fd = new FormData();
    fd.append('action', 'delete_slot');
    fd.append('override_date', date);
    fd.append('start_time', time);
    fd.append('csrf_token', _adminCsrf);

    try {
        const res = await fetch(_baseUrl + 'api/manage_slots.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            _lastSlotsHash = '';
            loadSlots(date);
        } else {
            alert(data.message || 'Could not delete slot.');
        }
    } catch (e) {
        alert('Network error.');
    }
}

/* ============================================
   UPDATE CAPACITY
   ============================================ */
async function updateCapacity(date, time, capacity) {
    const fd = new FormData();
    fd.append('action', 'update_slot_capacity');
    fd.append('override_date', date);
    fd.append('start_time', time);
    fd.append('end_time', time);
    fd.append('max_patients', capacity);
    fd.append('csrf_token', _adminCsrf);

    try {
        const res = await fetch(_baseUrl + 'api/manage_slots.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            _lastSlotsHash = '';
            loadSlots(date);
        } else {
            alert(data.message || 'Could not update capacity.');
        }
    } catch (e) {
        alert('Network error.');
    }
}

/* ============================================
   TOGGLE BLOCK
   ============================================ */
async function toggleBlock(date, time, isBlocked) {
    const action = isBlocked ? 'Block' : 'Unblock';
    if (!confirm(action + ' this slot?')) return;

    const fd = new FormData();
    fd.append('action', 'block_slot');
    fd.append('override_date', date);
    fd.append('start_time', time);
    fd.append('end_time', time);
    fd.append('is_blocked', isBlocked);
    fd.append('csrf_token', _adminCsrf);

    try {
        const res = await fetch(_baseUrl + 'api/manage_slots.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            _lastSlotsHash = '';
            loadSlots(date);
        } else {
            alert(data.message || 'Could not update slot.');
        }
    } catch (e) {
        alert('Network error.');
    }
}

/* ============================================
   DAY MODAL (WEEKLY SCHEDULE)
   ============================================ */
function openDayModal(sched) {
    document.getElementById('dm_day').value = sched.day_of_week;
    document.getElementById('dm_title').innerHTML = '<i class="fa-solid fa-calendar-day text-primary"></i> Edit ' + sched.day_of_week + ' Schedule';
    document.getElementById('dm_start').value = sched.start_time.substring(0, 5);
    document.getElementById('dm_end').value = sched.end_time.substring(0, 5);
    document.getElementById('dm_capacity').value = sched.max_patients_per_slot;
    document.getElementById('dm_active').checked = !!(parseInt(sched.is_active) === 1);
    document.getElementById('dm_error').style.display = 'none';
    openModal('dayModal');
}

async function submitDayModal() {
    const errBox = document.getElementById('dm_error');
    errBox.style.display = 'none';
    const btn = document.getElementById('dm_submit_btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const fd = new FormData();
    fd.append('action', 'update_day_schedule');
    fd.append('day_of_week', document.getElementById('dm_day').value);
    fd.append('start_time', document.getElementById('dm_start').value);
    fd.append('end_time', document.getElementById('dm_end').value);
    fd.append('max_patients_per_slot', document.getElementById('dm_capacity').value);
    if (document.getElementById('dm_active').checked) fd.append('is_active', '1');
    fd.append('csrf_token', _adminCsrf);

    try {
        const res = await fetch(_baseUrl + 'api/manage_slots.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            errBox.textContent = data.message || 'Could not save.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Day Schedule';
        }
    } catch (e) {
        errBox.textContent = 'Network error.';
        errBox.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Day Schedule';
    }
}

/* ============================================
   INIT
   ============================================ */
document.addEventListener('DOMContentLoaded', function () {
    loadSlots(_currentSelectedDate);
    _liveInterval = setInterval(function () {
        if (_currentSelectedDate) loadSlots(_currentSelectedDate, true);
    }, 5000);

    document.querySelectorAll('.modal-backdrop').forEach(function(m) {
        m.addEventListener('click', function(e) {
            if (e.target === m) closeModal(m.id);
        });
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop').forEach(function(m) {
            closeModal(m.id);
        });
    }
});

window.addEventListener('beforeunload', function() {
    if (_liveInterval) clearInterval(_liveInterval);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>