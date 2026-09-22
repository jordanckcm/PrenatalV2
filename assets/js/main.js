/**
 * MaternalCare Prenatal Health System — Global site behaviors
 * Loaded on every page via includes/footer.php
 *
 * NOTE: Dark/Light theme toggle is handled in includes/footer.php
 *       (gikuha dinhi aron malikayan ang duplicate/conflict)
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     * Sidebar toggle (mobile drawer / desktop collapse)
     * ------------------------------------------------------------- */
    function initSidebarToggle() {
        var btn = document.getElementById('sidebarToggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var isMobile = window.matchMedia('(max-width: 900px)').matches;
            document.body.classList.toggle(isMobile ? 'sidebar-open' : 'sidebar-collapsed');
        });
    }

    /* ---------------------------------------------------------------
     * Modal system: openModal(id) / closeModal(id)
     * ------------------------------------------------------------- */
    window.openModal = function (id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function (id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    function initModalDismissal() {
        document.addEventListener('click', function (e) {
            if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
                window.closeModal(e.target.id);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.is-open, .modal-backdrop[style*="flex"]').forEach(function (m) {
                    window.closeModal(m.id);
                });
            }
        });
    }

    /* ---------------------------------------------------------------
     * Generic appointment status updater
     * ------------------------------------------------------------- */
    window.updateAppointmentStatus = function (appointmentId, status, csrfToken, extra) {
        var labels = {
            cancelled: 'cancel this appointment',
            missed: 'mark this appointment as missed',
            completed: 'mark this appointment as completed'
        };
        var confirmMsg = 'Are you sure you want to ' + (labels[status] || ('set this appointment to "' + status + '"')) + '?';
        if (!window.confirm(confirmMsg)) return;

        var fd = new FormData();
        fd.append('appointment_id', appointmentId);
        fd.append('status', status);
        fd.append('csrf_token', csrfToken);
        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }

        var base = (window.BASE_URL || (document.querySelector('meta[name="base-url"]') || {}).content || '');
        fetch(base + 'api/update_status.php', { method: 'POST', body: fd })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    window.alert(data.message || 'Could not update this appointment.');
                }
            })
            .catch(function () {
                window.alert('Network error. Please try again.');
            });
    };

    /* ---------------------------------------------------------------
     * Print a specific element
     * ------------------------------------------------------------- */
    window.printElement = function (elementId) {
        var el = document.getElementById(elementId);
        if (!el) return;

        var styles = '';
        document.querySelectorAll('link[rel="stylesheet"], style').forEach(function (node) {
            styles += node.outerHTML;
        });

        var win = window.open('', '_blank', 'width=720,height=900');
        win.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print</title>' + styles +
            '<style>body{padding:2rem;background:#fff;} @media print{a,button{display:none!important;}}</style>' +
            '</head><body>' + el.innerHTML + '</body></html>'
        );
        win.document.close();
        win.focus();
        setTimeout(function () { win.print(); }, 350);
    };

    /* ---------------------------------------------------------------
     * Auto-dismiss server-rendered alerts
     * ------------------------------------------------------------- */
    function initAlertAutoDismiss() {
        document.querySelectorAll('.alert-success, .alert-danger').forEach(function (alertBox) {
            setTimeout(function () {
                alertBox.classList.add('is-fading');
                setTimeout(function () { alertBox.remove(); }, 400);
            }, 6000);
        });
    }

    /* ---------------------------------------------------------------
     * Initialize all behaviors on DOM ready
     * ------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initSidebarToggle();
        initModalDismissal();
        initAlertAutoDismiss();
    });
})();