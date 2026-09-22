/**
 * Renders a bar chart above the "Service Utilization Distribution" table
 * on admin/reports.php by reading the table Chart.js is already loaded for
 * (via includes/header.php's CDN script tag). Safe no-op on any other page.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;

        // Find the utilization table: the one whose header row starts with "Service Name"
        var table = null;
        document.querySelectorAll('table.table').forEach(function (t) {
            var firstHeader = t.querySelector('thead th');
            if (firstHeader && /service name/i.test(firstHeader.textContent) && !table) {
                table = t;
            }
        });
        if (!table) return;

        var labels = [];
        var values = [];
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var cells = row.querySelectorAll('td');
            if (cells.length < 3) return;
            var name = cells[0].textContent.trim();
            var bookingsMatch = cells[2].textContent.match(/(\d+)/);
            if (!name || !bookingsMatch) return;
            labels.push(name);
            values.push(parseInt(bookingsMatch[1], 10));
        });
        if (!labels.length) return;

        var canvasWrap = document.createElement('div');
        canvasWrap.style.marginBottom = '1.5rem';
        canvasWrap.style.height = '280px';
        var canvas = document.createElement('canvas');
        canvasWrap.appendChild(canvas);
        table.closest('.table-responsive').before(canvasWrap);

        var styles = getComputedStyle(document.documentElement);
        var primary = styles.getPropertyValue('--primary').trim() || '#4F46E5';

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Bookings',
                    data: values,
                    backgroundColor: primary,
                    borderRadius: 6,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    });
})();
