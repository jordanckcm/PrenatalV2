/**
 * ClinicBoard — small live "how busy is the clinic today" widget
 * used on login.php and register.php's info pane.
 *
 * new ClinicBoard(el, { api: 'api/clinic_load.php', compact: false })
 */
function ClinicBoard(el, options) {
    if (!el) return;
    this.el = el;
    this.opts = Object.assign({ api: 'api/clinic_load.php', compact: false, refreshMs: 45000 }, options || {});
    this.el.innerHTML = '<div class="cb-loading"><i class="fa-solid fa-spinner fa-spin"></i> Checking today\'s clinic availability...</div>';
    this._load();
    var self = this;
    this._timer = setInterval(function () { self._load(); }, this.opts.refreshMs);
}

ClinicBoard.prototype._load = function () {
    var self = this;
    fetch(this.opts.api)
        .then(function (res) { return res.json(); })
        .then(function (data) { self._render(data); })
        .catch(function () {
            self.el.innerHTML = '<div class="cb-empty"><i class="fa-solid fa-triangle-exclamation"></i> Could not load live clinic status right now.</div>';
        });
};

ClinicBoard.prototype._statusText = function (status) {
    return {
        open: 'Open &bull; seats available',
        busy: 'Open &bull; filling up',
        almost_full: 'Open &bull; almost full',
        full: 'Fully booked today',
        ended: 'Booking window ended for today',
        closed: 'Clinic closed today'
    }[status] || status;
};

ClinicBoard.prototype._render = function (data) {
    if (!data || !data.success) {
        this.el.innerHTML = '<div class="cb-empty">Live clinic status is unavailable right now.</div>';
        return;
    }

    var html = '<div class="cb-head">' +
        '<span><span class="cb-dot ' + data.status + '"></span>' + this._statusText(data.status) + '</span>' +
        '<span style="font-weight:400; opacity:0.8;">' + data.day_label + '</span>' +
        '</div>';

    if (data.is_closed) {
        html += '<div class="cb-empty">The health center is closed today.';
        if (data.next_open) {
            html += ' Next open day: <strong>' + data.next_open.day_label + '</strong> (' + data.next_open.hours + ').';
        }
        html += '</div>';
        this.el.innerHTML = html;
        return;
    }

    if (data.hours) {
        html += '<div class="cb-row"><span>Clinic hours</span><span>' + data.hours.open + ' - ' + data.hours.close + '</span></div>';
    }
    if (data.next_slot) {
        html += '<div class="cb-row"><span>Next open slot</span><span>' + data.next_slot.time_formatted + ' &middot; ' + data.next_slot.service_name + '</span></div>';
    }

    var maxServices = this.opts.compact ? 3 : (data.services || []).length;
    (data.services || []).slice(0, maxServices).forEach(function (svc) {
        html += '<div class="cb-row" style="flex-direction:column; align-items:stretch; gap:0.25rem;">' +
            '<div style="display:flex; justify-content:space-between;"><span>' + svc.name + '</span><span>' + svc.remaining + '/' + svc.capacity + ' open</span></div>' +
            '<div class="cb-bar"><div class="cb-bar-fill" style="width:' + Math.min(100, svc.utilization) + '%;"></div></div>' +
            '</div>';
    });

    this.el.innerHTML = html;
};

ClinicBoard.prototype.destroy = function () {
    if (this._timer) clearInterval(this._timer);
};
