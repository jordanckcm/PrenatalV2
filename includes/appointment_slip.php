<?php
/**
 * Shared Appointment Slip (modal + print)
 *
 * Include ONCE at the bottom of a page, before footer.php:
 *     include __DIR__ . '/../includes/appointment_slip.php';
 *
 * Any button with the class "js-slip-btn" and a data-appt attribute opens it.
 * Use slipButtonAttr() to build that attribute safely.
 *
 * This component is self-contained on purpose: it does not depend on
 * openModal() / printElement() from footer.php or main.js, so it keeps
 * working even if those helpers change or load in a different order.
 */

if (!function_exists('slipButtonAttr')) {
    /**
     * Returns a safe  data-appt='...'  attribute for a slip button.
     * Only the fields the slip needs are sent to the browser.
     */
    function slipButtonAttr(array $appt, string $patientName, string $staffLabel): string
    {
        $payload = [
            'code'         => (string)($appt['appointment_code'] ?? ''),
            'status'       => (string)($appt['status'] ?? ''),
            'service'      => (string)($appt['service_name'] ?? ''),
            'date'         => (string)($appt['appointment_date'] ?? ''),
            'time'         => (string)($appt['appointment_time'] ?? ''),
            'patient_name' => $patientName,
            'staff_label'  => $staffLabel,
        ];
        $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        return 'data-appt="' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '"';
    }
}
?>
<style id="slipStyles">
.slip-card {
    border-radius: 16px;
    overflow: hidden;
    background: #ffffff;
    border: 1.5px solid #bae6fd;
    box-shadow: 0 10px 30px rgba(14, 165, 233, 0.15);
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #0f172a;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.slip-head {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 50%, #7dd3fc 100%);
    padding: 1.25rem 1.5rem;
    display: flex; align-items: center; gap: 0.85rem;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.slip-logo {
    width: 46px; height: 46px; background: #0ea5e9; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #ffffff; font-size: 1.4rem;
    box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3); flex-shrink: 0;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.slip-head h3 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.2rem; font-weight: 800; color: #0f172a;
    letter-spacing: -0.4px; line-height: 1.15; margin: 0;
}
.slip-head small { font-size: 0.68rem; font-weight: 700; color: #0369a1; letter-spacing: 0.8px; text-transform: uppercase; }
.slip-codebar {
    display: flex; justify-content: space-between; align-items: center; gap: 1rem;
    padding: 1rem 1.5rem; border-bottom: 2px dashed #bae6fd;
}
.slip-label { display: block; font-size: 0.68rem; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase; color: #64748b; margin-bottom: 0.15rem; }
.slip-code { font-family: 'Bricolage Grotesque', sans-serif; font-size: 1.25rem; font-weight: 800; color: #0284c7; letter-spacing: 0.5px; }
.slip-status {
    display: inline-block; padding: 0.3rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 800; letter-spacing: 0.6px;
    background: #e0f2fe; color: #0369a1;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.slip-status.s-confirmed, .slip-status.s-completed { background: #dcfce7; color: #166534; }
.slip-status.s-pending { background: #fef3c7; color: #92400e; }
.slip-status.s-cancelled, .slip-status.s-missed { background: #fee2e2; color: #991b1b; }
.slip-body { padding: 1.1rem 1.5rem 0.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem 1.25rem; }
.slip-body .full { grid-column: 1 / -1; }
.slip-value { font-size: 0.98rem; font-weight: 700; color: #0f172a; }
.slip-foot {
    margin: 0.75rem 1.5rem 1.25rem; padding: 0.75rem 1rem;
    background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 8px;
    font-size: 0.78rem; color: #334155; line-height: 1.5;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
@media (max-width: 480px) { .slip-body { grid-template-columns: 1fr; } }

/* The slip modal manages its own visibility */
#voucherModal { display: none; }
#voucherModal.is-open { display: flex !important; }
</style>

<div id="voucherModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="slipModalTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="slipModalTitle"><i class="fa-solid fa-ticket" style="color:#0ea5e9;"></i> Appointment Slip</h3>
            <button type="button" id="slipCloseX" aria-label="Close" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:inherit;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="printableVoucher">
            <div class="slip-card" id="slipCard">
                <div class="slip-head">
                    <div class="slip-logo"><i class="fa-solid fa-baby-carriage"></i></div>
                    <div>
                        <h3>MaternalCare Prenatal Center</h3>
                        <small>Appointment Confirmation Slip</small>
                    </div>
                </div>
                <div class="slip-codebar">
                    <div>
                        <span class="slip-label">Appointment Code</span>
                        <span class="slip-code" id="vCode">-</span>
                    </div>
                    <span class="slip-status" id="vStatus">-</span>
                </div>
                <div class="slip-body">
                    <div class="full">
                        <span class="slip-label">Patient</span>
                        <span class="slip-value" id="vPatient">-</span>
                    </div>
                    <div class="full">
                        <span class="slip-label">Service</span>
                        <span class="slip-value" id="vService">-</span>
                    </div>
                    <div>
                        <span class="slip-label">Date</span>
                        <span class="slip-value" id="vDate">-</span>
                    </div>
                    <div>
                        <span class="slip-label">Time</span>
                        <span class="slip-value" id="vTime">-</span>
                    </div>
                    <div class="full">
                        <span class="slip-label">Assigned Staff</span>
                        <span class="slip-value" id="vStaff">-</span>
                    </div>
                </div>
                <div class="slip-foot">
                    Please arrive a few minutes before your scheduled time and present this slip to the clinic staff.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-sm" id="slipCloseBtn">Close</button>
            <button type="button" class="btn btn-sm" id="slipPrintBtn" style="background:#0ea5e9; color:#fff; border:none;">
                <i class="fa-solid fa-print"></i> Print Slip
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal = document.getElementById('voucherModal');
    if (!modal) return;

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function openSlip() {
        modal.classList.add('is-open');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeSlip() {
        modal.classList.remove('is-open');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    function showSlip(appt) {
        var d = new Date((appt.date || '') + 'T00:00:00');
        var dateText = isNaN(d) ? (appt.date || '-') : d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

        var tp = String(appt.time || '').split(':');
        var hh = parseInt(tp[0], 10), mm = tp[1] || '00';
        var timeText = isNaN(hh) ? (appt.time || '-') : ((hh % 12) || 12) + ':' + mm + ' ' + (hh >= 12 ? 'PM' : 'AM');

        var status = String(appt.status || '').toLowerCase();
        var statusEl = document.getElementById('vStatus');
        statusEl.textContent = status.toUpperCase();
        statusEl.className = 'slip-status s-' + status;

        setText('vCode', appt.code || '-');
        setText('vPatient', appt.patient_name || '-');
        setText('vService', appt.service || '-');
        setText('vDate', dateText);
        setText('vTime', timeText);
        setText('vStaff', appt.staff_label || 'To be assigned by the clinic');
        openSlip();
    }

    // Delegated click: works for every .js-slip-btn on the page, even rows added later
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.js-slip-btn') : null;
        if (!btn) return;
        e.preventDefault();
        var raw = btn.getAttribute('data-appt');
        if (!raw) return;
        var appt;
        try { appt = JSON.parse(raw); } catch (err) { window.alert('Could not read this appointment.'); return; }
        showSlip(appt);
    });

    document.getElementById('slipCloseX').addEventListener('click', closeSlip);
    document.getElementById('slipCloseBtn').addEventListener('click', closeSlip);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeSlip(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSlip(); });

    // Print only the slip, in a clean window with just the slip styles
    document.getElementById('slipPrintBtn').addEventListener('click', function () {
        var card = document.getElementById('slipCard');
        var css = document.getElementById('slipStyles').textContent;
        var fa = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
        var fonts = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@800&family=Figtree:wght@400;600;700;800&display=swap">';
        var win = window.open('', '_blank', 'width=760,height=900');
        if (!win) { window.alert('Please allow pop-ups for this site to print the slip.'); return; }
        win.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Appointment Slip</title>' + fonts + fa +
            '<style>' + css + 'body{margin:0;padding:2rem;background:#fff;} .slip-card{max-width:560px;margin:0 auto;box-shadow:none;} @page{margin:12mm;}</style>' +
            '</head><body>' + card.outerHTML + '</body></html>'
        );
        win.document.close();
        win.focus();
        // Wait for fonts/icons to load before the print dialog opens
        var printed = false;
        function doPrint() {
            if (printed) return;
            printed = true;
            try { win.print(); } catch (err) {}
        }
        win.onload = function () { setTimeout(doPrint, 300); };
        setTimeout(doPrint, 1500); // fallback if onload never fires
    });
})();
</script>
