/**
 * Slot Tracker - With Edit Time Modal
 */

function initSlotTracker(containerId, options) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const role = options.role || 'admin';
    const apiUrl = options.apiUrl || '';
    const manageApiUrl = options.manageApiUrl || '';
    const csrfToken = options.csrfToken || '';

    let currentDate = new Date().toISOString().split('T')[0];

    /* ============================================
       LOAD SLOTS
       ============================================ */
    async function loadSlots(date) {
        if (!date) return;
        container.innerHTML = '<p class="text-muted text-center"><i class="fa-solid fa-spinner fa-spin"></i> Loading slots...</p>';

        try {
            const res = await fetch(apiUrl + '?date=' + date + '&t=' + Date.now());
            const data = await res.json();

            if (!data.success) {
                container.innerHTML = '<p class="text-danger text-center">' + (data.message || 'Failed to load') + '</p>';
                return;
            }

            renderSlots(data.slots, date);
        } catch (e) {
            container.innerHTML = '<p class="text-danger text-center">Network error</p>';
        }
    }

    /* ============================================
       RENDER SLOTS (with Edit button)
       ============================================ */
    function renderSlots(slots, date) {
        if (!slots || slots.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">No slots available for this date.</p>';
            return;
        }

        let html = '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap:1rem;">';

        slots.forEach(function(slot) {
            const isBlocked = slot.is_blocked;
            const isFull = slot.is_full;

            let bgColor = 'var(--surface)';
            let borderColor = 'var(--border-color)';
            let statusColor = '#10b981';
            let statusText = slot.available + ' left';

            if (isBlocked) {
                bgColor = '#fee2e2';
                borderColor = '#dc3545';
                statusColor = '#dc3545';
                statusText = 'Blocked';
            } else if (isFull) {
                bgColor = '#fef3c7';
                borderColor = '#f59e0b';
                statusColor = '#b45309';
                statusText = 'Full';
            }

            html += '<div style="background:' + bgColor + '; border: 2px solid ' + borderColor + '; border-radius: 14px; padding: 1rem; text-align: center;">';

            /* TIME DISPLAY */
            html += '<div style="font-family:Bricolage Grotesque, sans-serif; font-size:1.15rem; font-weight:800; margin-bottom:0.5rem; color:var(--text-dark);">' + slot.time_formatted + '</div>';

            /* STATUS */
            html += '<div style="font-size:0.72rem; font-weight:700; color:' + statusColor + '; margin-bottom:0.5rem; text-transform:uppercase;">' + statusText + '</div>';
            html += '<div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:0.75rem;">' + slot.booked + ' / ' + slot.capacity + ' booked</div>';

            /* ADMIN CONTROLS */
            if (role === 'admin') {
                html += '<div style="display:flex; gap:0.35rem; justify-content:center; align-items:center; flex-wrap:wrap;">';

                // Edit Time button (opens modal)
                html += '<button type="button" class="js-edit-time-btn" ' +
                    'data-date="' + date + '" ' +
                    'data-time="' + slot.time + '" ' +
                    'data-time-formatted="' + slot.time_formatted + '" ' +
                    'data-capacity="' + slot.capacity + '" ' +
                    'style="padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #0ea5e9; background:#e0f2fe; color:#0369a1; font-size:0.68rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:0.25rem;">' +
                    '<i class="fa-solid fa-clock"></i> Time' +
                    '</button>';

                // Capacity input
                html += '<input type="number" min="1" max="20" value="' + slot.capacity + '" ' +
                    'style="width:45px; padding:0.3rem; border-radius:6px; border:1px solid var(--border-color); background:var(--surface); color:var(--text-dark); font-size:0.75rem; text-align:center; font-weight:700;" ' +
                    'onchange="updateSlotCapacity(\'' + date + '\', \'' + slot.time + '\', this.value)" title="Capacity">';

                // Block button
                html += '<button type="button" onclick="toggleSlotBlock(\'' + date + '\', \'' + slot.time + '\', ' + (isBlocked ? 0 : 1) + ')" ' +
                    'style="padding:0.35rem 0.5rem; border-radius:6px; border:1px solid ' + (isBlocked ? '#10b981' : '#dc3545') + '; background:' + (isBlocked ? '#d1fae5' : '#fee2e2') + '; color:' + (isBlocked ? '#065f46' : '#991b1b') + '; font-size:0.68rem; font-weight:700; cursor:pointer;" title="' + (isBlocked ? 'Unblock' : 'Block') + '">' +
                    '<i class="fa-solid fa-' + (isBlocked ? 'unlock' : 'lock') + '"></i>' +
                    '</button>';

                html += '</div>';
            }

            html += '</div>';
        });

        html += '</div>';
        container.innerHTML = html;
    }

    /* ============================================
       EDIT TIME MODAL (created once)
       ============================================ */
    function ensureEditTimeModal() {
        if (document.getElementById('editTimeModal')) return;

        var modal = document.createElement('div');
        modal.id = 'editTimeModal';
        modal.className = 'modal-backdrop';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="modal-card" style="max-width: 450px;">
                <div class="modal-header">
                    <h3><i class="fa-solid fa-clock text-primary"></i> Edit Slot Time</h3>
                    <button type="button" onclick="closeEditTimeModal()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-dark);">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="font-size:0.82rem;">
                        <i class="fa-solid fa-info-circle"></i>
                        <div>
                            <strong>Note:</strong> Ang bag-ong time kay ma-save isip <strong>custom override</strong> sa maong slot.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Current Time</label>
                        <input type="text" id="etm_current" class="form-control" readonly style="background:var(--bg-body); cursor:not-allowed;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Time <span class="text-danger">*</span></label>
                        <input type="time" id="etm_new_time" class="form-control" required>
                        <small class="text-muted" style="display:block; margin-top:0.35rem; font-size:0.72rem;">
                            <i class="fa-solid fa-info-circle"></i> Pilia ang bag-ong oras para sa maong slot.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Capacity</label>
                        <input type="number" id="etm_capacity" class="form-control" min="1" max="20" value="2">
                    </div>

                    <input type="hidden" id="etm_date">
                    <input type="hidden" id="etm_old_time">

                    <div id="etm_error" class="alert alert-danger" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditTimeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditTimeModal()">
                        <i class="fa-solid fa-floppy-disk"></i> Save Time
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeEditTimeModal();
        });

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeEditTimeModal();
            }
        });
    }

    /* ============================================
       OPEN EDIT TIME MODAL
       ============================================ */
    window.openEditTimeModal = function(date, time, timeFormatted, capacity) {
        ensureEditTimeModal();

        document.getElementById('etm_current').value = timeFormatted;
        document.getElementById('etm_new_time').value = timeTo24(time);
        document.getElementById('etm_capacity').value = capacity;
        document.getElementById('etm_date').value = date;
        document.getElementById('etm_old_time').value = time;
        document.getElementById('etm_error').style.display = 'none';

        var modal = document.getElementById('editTimeModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    /* ============================================
       CLOSE EDIT TIME MODAL
       ============================================ */
    window.closeEditTimeModal = function() {
        var modal = document.getElementById('editTimeModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    /* ============================================
       SUBMIT EDIT TIME MODAL
       ============================================
       FIX: manage_slots.php (action=update_slot_time) expects
       old_start_time / old_end_time / new_start_time / new_end_time
       in strict "HH:MM" format (no seconds). The original code sent
       old_time / new_time, sometimes with seconds attached, which
       PHP's preg_match('/^\d{2}:\d{2}$/') rejected or silently
       ignored — causing the "Network error" / failed save.
       ============================================ */
    window.submitEditTimeModal = async function() {
        var date = document.getElementById('etm_date').value;
        var oldTime = document.getElementById('etm_old_time').value;
        var newTime = document.getElementById('etm_new_time').value;
        var capacity = document.getElementById('etm_capacity').value;
        var errBox = document.getElementById('etm_error');

        errBox.style.display = 'none';

        if (!newTime) {
            errBox.textContent = 'Please select a new time.';
            errBox.style.display = 'block';
            return;
        }

        // oldTime comes from data-time (often "HH:MM:SS") -> trim to "HH:MM"
        var oldTimeShort = oldTime.length >= 5 ? oldTime.substring(0, 5) : oldTime;
        // newTime comes from <input type="time"> which is already "HH:MM"
        var newTimeShort = newTime.length >= 5 ? newTime.substring(0, 5) : newTime;

        var fd = new FormData();
        fd.append('action', 'update_slot_time');
        fd.append('override_date', date);
        fd.append('old_start_time', oldTimeShort);
        fd.append('old_end_time', oldTimeShort);
        fd.append('new_start_time', newTimeShort);
        fd.append('new_end_time', newTimeShort);
        fd.append('csrf_token', csrfToken);

        try {
            const res = await fetch(manageApiUrl, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                closeEditTimeModal();
                loadSlots(date);
                alert('✅ Time updated successfully!');
            } else {
                errBox.textContent = data.message || 'Could not update time.';
                errBox.style.display = 'block';
            }
        } catch (e) {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        }
    };

    /* ============================================
       TIME FORMAT HELPERS
       ============================================ */
    function timeTo24(time) {
        if (!time) return '08:00';
        // If already "HH:MM:SS", return "HH:MM"
        if (time.match(/^\d{2}:\d{2}/)) {
            return time.substring(0, 5);
        }
        // If "8:00 AM", convert
        var match = time.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (!match) return '08:00';
        var hours = parseInt(match[1]);
        var minutes = match[2];
        var period = match[3].toUpperCase();
        if (period === 'PM' && hours !== 12) hours += 12;
        if (period === 'AM' && hours === 12) hours = 0;
        return String(hours).padStart(2, '0') + ':' + minutes;
    }

    /* ============================================
       UPDATE SLOT CAPACITY
       ============================================ */
    window.updateSlotCapacity = async function(date, time, capacity) {
        const fd = new FormData();
        fd.append('action', 'update_slot_capacity');
        fd.append('override_date', date);
        fd.append('start_time', time);
        fd.append('end_time', time);
        fd.append('max_patients', capacity);
        fd.append('csrf_token', csrfToken);

        try {
            const res = await fetch(manageApiUrl, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Could not update.');
            }
        } catch (e) {
            alert('Network error.');
        }
    };

    /* ============================================
       TOGGLE SLOT BLOCK
       ============================================ */
    window.toggleSlotBlock = async function(date, time, isBlocked) {
        const action = isBlocked ? 'Block' : 'Unblock';
        if (!confirm(action + ' this slot?')) return;

        const fd = new FormData();
        fd.append('action', 'block_slot');
        fd.append('override_date', date);
        fd.append('start_time', time);
        fd.append('end_time', time);
        fd.append('is_blocked', isBlocked);
        fd.append('csrf_token', csrfToken);

        try {
            const res = await fetch(manageApiUrl, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                loadSlots(date);
            } else {
                alert(data.message || 'Could not update.');
            }
        } catch (e) {
            alert('Network error.');
        }
    };

    /* ============================================
       EVENT DELEGATION - EDIT TIME BUTTON
       ============================================ */
    container.addEventListener('click', function(e) {
        var editBtn = e.target.closest('.js-edit-time-btn');
        if (editBtn) {
            e.preventDefault();
            openEditTimeModal(
                editBtn.getAttribute('data-date'),
                editBtn.getAttribute('data-time'),
                editBtn.getAttribute('data-time-formatted'),
                editBtn.getAttribute('data-capacity')
            );
            return;
        }
    });

    /* ============================================
       DATE PICKER WATCHER
       ============================================ */
    var dateInput = document.getElementById('slotDateSelect');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            currentDate = this.value;
            loadSlots(currentDate);
        });
        currentDate = dateInput.value;
    }

    loadSlots(currentDate);
}