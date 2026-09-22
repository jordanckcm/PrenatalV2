/**
 * Booking Wizard controller for patient/book_appointment.php
 * Binds to the exact markup rendered by that page:
 *   .step-item[data-step], .wizard-content-step[data-step]
 *   .service-card-option[data-service-id][data-service-name][data-duration]
 *   #dayStrip, #appointmentDateInput, #dayLoad, #slotLiveNotice,
 *   #slotsSectionContainer, #timeSlotsContainer,
 *   #hiddenServiceId, #hiddenDate, #hiddenTime,
 *   #summaryService, #summaryDate, #summaryTime,
 *   #btnNext1, #btnPrev2, #btnNext2, #btnPrev3,
 *   #clinicSchedulesData (JSON of the weekly schedule)
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('bookingWizardForm');
        if (!form) return; // not on the booking page

        var BASE_URL = (document.querySelector('meta[name="base-url"]') || {}).content || guessBaseUrl();
        function guessBaseUrl() {
            var path = window.location.pathname.replace(/patient\/book_appointment\.php.*$/, '');
            return window.location.origin + path;
        }

        var clinicSchedules = [];
        try {
            clinicSchedules = JSON.parse(document.getElementById('clinicSchedulesData').textContent || '[]');
        } catch (e) { clinicSchedules = []; }

        var state = { serviceId: null, serviceName: '', date: '', time: '', timeFormatted: '', currentStep: 1 };
        var liveRefreshTimer = null;

        var stepItems = document.querySelectorAll('.step-item');
        var stepPanels = document.querySelectorAll('.wizard-content-step');
        var btnNext1 = document.getElementById('btnNext1');
        var btnPrev2 = document.getElementById('btnPrev2');
        var btnNext2 = document.getElementById('btnNext2');
        var btnPrev3 = document.getElementById('btnPrev3');
        var dateInput = document.getElementById('appointmentDateInput');
        var dayStrip = document.getElementById('dayStrip');
        var dayLoad = document.getElementById('dayLoad');
        var slotNotice = document.getElementById('slotLiveNotice');
        var slotsSection = document.getElementById('slotsSectionContainer');
        var timeSlotsContainer = document.getElementById('timeSlotsContainer');

        /* ---------------- Step navigation ---------------- */
        function goToStep(n) {
            state.currentStep = n;
            stepPanels.forEach(function (p) {
                p.classList.toggle('active', parseInt(p.dataset.step, 10) === n);
            });
            stepItems.forEach(function (s) {
                var stepNum = parseInt(s.dataset.step, 10);
                s.classList.toggle('active', stepNum === n);
                s.classList.toggle('completed', stepNum < n);
            });
            window.scrollTo({ top: form.offsetTop - 90, behavior: 'smooth' });

            if (n !== 2) {
                stopLiveSlotPolling();
            } else if (state.date) {
                startLiveSlotPolling();
            }
        }

        /* ---------------- Live Slot Polling ---------------- */
        function startLiveSlotPolling() {
            stopLiveSlotPolling();
            liveRefreshTimer = setInterval(function () {
                if (state.currentStep === 2 && state.serviceId && state.date) {
                    refreshSlotsLive(state.date);
                }
            }, 10000); // Poll every 10s for real-time slot occupancy updates
        }

        function stopLiveSlotPolling() {
            if (liveRefreshTimer) {
                clearInterval(liveRefreshTimer);
                liveRefreshTimer = null;
            }
        }

        /* ---------------- Step 1: service selection ---------------- */
        var serviceCards = document.querySelectorAll('.service-card-option');
        serviceCards.forEach(function (card) {
            card.addEventListener('click', function () {
                serviceCards.forEach(function (c) { c.classList.remove('selected'); });
                card.classList.add('selected');
                state.serviceId = card.dataset.serviceId;
                state.serviceName = card.dataset.serviceName;
                btnNext1.disabled = false;
            });
        });

        fetch(BASE_URL + 'api/clinic_load.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                (data.services || []).forEach(function (svc) {
                    var el = document.querySelector('[data-service-live="' + svc.id + '"]');
                    if (!el) return;
                    el.classList.remove('is-muted');
                    if (data.is_closed || svc.status === 'closed') {
                        el.classList.add('is-muted');
                        el.innerHTML = '<i class="dot"></i> Clinic closed today';
                    } else if (svc.status === 'full' || svc.status === 'ended') {
                        el.classList.add('is-full');
                        el.innerHTML = '<i class="dot"></i> Fully booked today';
                    } else if (svc.status === 'busy' || svc.status === 'almost_full') {
                        el.classList.add('is-busy');
                        el.innerHTML = '<i class="dot"></i> ' + svc.bookable_remaining + ' seat(s) left today';
                    } else {
                        el.classList.add('is-open');
                        el.innerHTML = '<i class="dot"></i> ' + svc.bookable_remaining + ' seat(s) open today';
                    }
                });
            })
            .catch(function () {});

        btnNext1.addEventListener('click', function () {
            if (!state.serviceId) return;
            renderDayStrip();
            goToStep(2);
        });

        /* ---------------- Step 2: date & time ---------------- */
        function isDayClosed(dateStr) {
            var weekday = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
            var sched = clinicSchedules.find(function (s) { return s.day_of_week === weekday; });
            return !!sched && parseInt(sched.is_active, 10) === 0;
        }

        function renderDayStrip() {
            dayStrip.innerHTML = '<div class="cl-day" style="opacity:.6;">Loading...</div>';
            fetch(BASE_URL + 'api/clinic_load.php?service_id=' + encodeURIComponent(state.serviceId) + '&strip=14')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var strip = (data && data.strip) || [];
                    dayStrip.innerHTML = '';
                    strip.forEach(function (day) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'cl-day s-' + day.status + (day.is_today ? ' is-today' : '');
                        var disabled = (day.status === 'closed' || day.status === 'ended' || day.status === 'full');
                        if (disabled) btn.disabled = true;
                        btn.innerHTML =
                            '<span class="wd">' + day.weekday + '</span>' +
                            '<span class="dn">' + day.day + '</span>' +
                            '<span class="st">' + statusShort(day.status) + '</span>' +
                            '<span class="bar"><span style="width:' + Math.min(100, day.utilization) + '%;"></span></span>';
                        btn.addEventListener('click', function () {
                            dateInput.value = day.date;
                            selectDate(day.date);
                        });
                        dayStrip.appendChild(btn);
                    });
                })
                .catch(function () { dayStrip.innerHTML = '<div class="cl-day">Unable to load</div>'; });
        }

        function statusShort(status) {
            return { open: 'Open', busy: 'Busy', almost_full: 'Nearly full', full: 'Full', ended: 'Ended', closed: 'Closed' }[status] || status;
        }

        function selectDate(dateStr) {
            state.date = dateStr;
            state.time = '';
            state.timeFormatted = '';
            btnNext2.disabled = true;
            timeSlotsContainer.innerHTML = '';
            slotsSection.style.display = 'none';
            dayLoad.style.display = 'flex';
            dayLoad.innerHTML = '<span class="lbl">Checking live slot availability...</span>';

            if (isDayClosed(dateStr)) {
                dayLoad.className = 'cl-dayload s-closed';
                dayLoad.innerHTML = '<span class="lbl">The clinic is closed on this day. Please pick another date.</span>';
                return;
            }

            refreshSlotsLive(dateStr);
            startLiveSlotPolling();
        }

        function refreshSlotsLive(dateStr) {
            fetch(BASE_URL + 'api/get_slots.php?service_id=' + encodeURIComponent(state.serviceId) + '&date=' + encodeURIComponent(dateStr))
                .then(function (r) { return r.json(); })
                .then(renderSlotsForDate)
                .catch(function () {
                    dayLoad.innerHTML = '<span class="lbl">Could not load slots. Please try again.</span>';
                });
        }

        function renderSlotsForDate(data) {
            if (!data.success) {
                dayLoad.className = 'cl-dayload s-closed';
                dayLoad.innerHTML = '<span class="lbl">' + (data.message || 'This service is unavailable.') + '</span>';
                return;
            }
            if (data.is_closed) {
                dayLoad.className = 'cl-dayload s-closed';
                dayLoad.innerHTML = '<span class="lbl">' + (data.message || 'The clinic is closed on this day.') + '</span>';
                return;
            }

            var m = data.metrics;
            var statusClass = m.bookable_remaining <= 0 ? 's-ended' : (m.utilization_rate >= 85 ? 's-busy' : '');
            dayLoad.className = 'cl-dayload ' + statusClass;
            dayLoad.innerHTML =
                '<div><div class="lbl">Open seats remaining today</div><div class="big">' + m.bookable_remaining + ' <span>/ ' + m.total_capacity + '</span></div></div>' +
                '<div class="side"><div class="meta">' + data.schedule.start_time + ' - ' + data.schedule.end_time + ' &bull; ' + m.utilization_rate + '% booked</div>' +
                '<div class="cl-bar"><span style="width:' + Math.min(100, m.utilization_rate) + '%;"></span></div></div>';

            var bookableSlots = data.slots.filter(function (s) { return !s.is_blocked; });

            if (!bookableSlots.length) {
                slotsSection.style.display = 'block';
                timeSlotsContainer.innerHTML = '<p class="text-muted text-center">No time slots configured for this day.</p>';
                return;
            }

            slotsSection.style.display = 'block';
            timeSlotsContainer.innerHTML = '';
            bookableSlots.forEach(function (slot) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'time-slot-btn' + (state.time === slot.time_raw ? ' selected' : '');
                btn.disabled = !slot.is_bookable;
                btn.innerHTML = slot.time_formatted + '<small>' + slot.remaining_capacity + ' left</small>';
                if (!slot.is_bookable) {
                    btn.querySelector('small').textContent = slot.is_past ? 'Passed' : 'Full';
                }
                btn.addEventListener('click', function () {
                    timeSlotsContainer.querySelectorAll('.time-slot-btn').forEach(function (b) { b.classList.remove('selected'); });
                    btn.classList.add('selected');
                    state.time = slot.time_raw;
                    state.timeFormatted = slot.time_formatted;
                    btnNext2.disabled = false;
                });
                timeSlotsContainer.appendChild(btn);
            });
        }

        dateInput.addEventListener('change', function () {
            if (this.value) selectDate(this.value);
        });

        btnPrev2.addEventListener('click', function () { goToStep(1); });

        btnNext2.addEventListener('click', function () {
            if (!state.date || !state.time) return;
            document.getElementById('hiddenServiceId').value = state.serviceId;
            document.getElementById('hiddenDate').value = state.date;
            document.getElementById('hiddenTime').value = state.time;

            document.getElementById('summaryService').textContent = state.serviceName;
            document.getElementById('summaryDate').textContent = new Date(state.date + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('summaryTime').textContent = state.timeFormatted;

            goToStep(3);
        });

        btnPrev3.addEventListener('click', function () { goToStep(2); });

        form.addEventListener('submit', function () {
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
            }
        });
    });
})();
