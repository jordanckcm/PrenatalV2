    </div><!-- /.page-content -->
</main><!-- /.main-content -->

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<script src="<?php echo BASE_URL; ?>assets/js/main.js?v=<?php echo APP_VERSION; ?>"></script>

<?php if (isset($extraJs) && !empty($extraJs)): ?>
    <script src="<?php echo BASE_URL; ?>assets/js/<?php echo htmlspecialchars($extraJs); ?>?v=<?php echo APP_VERSION; ?>"></script>
<?php endif; ?>

<script>
// ============================================
// THEME TOGGLE (DARK MODE) — FIXED
// ============================================
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try {
        localStorage.setItem('theme', theme);
    } catch (e) {}

    var icon = document.querySelector('.theme-toggle-icon');
    if (icon) {
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }
}

function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme') || 'light';
    var next = (current === 'dark') ? 'light' : 'dark';
    applyTheme(next);
}

// Initialize theme on load
document.addEventListener('DOMContentLoaded', function () {
    var currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    applyTheme(currentTheme);

    var btn = document.getElementById('themeToggleBtn');
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleTheme();
        });
    }
});

// ============================================
// SIDEBAR TOGGLE
// ============================================
function toggleSidebar() {
    if (window.innerWidth > 1024) {
        document.body.classList.toggle('sidebar-closed');
    } else {
        document.body.classList.toggle('sidebar-open');
    }
}

document.addEventListener('click', function(e) {
    var navLink = e.target.closest('.nav-link');
    if (navLink && window.innerWidth <= 1024) {
        document.body.classList.remove('sidebar-open');
    }
});

window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
        document.body.classList.remove('sidebar-open');
    } else {
        document.body.classList.remove('sidebar-closed');
    }
});

// ============================================
// MODAL HELPERS
// ============================================
function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop').forEach(function(m) {
            m.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
});

// ============================================
// PRINT HELPER
// ============================================
function printElement(elementId) {
    var el = document.getElementById(elementId);
    if (!el) return;

    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write('<html><head><title>Print</title>' +
        '<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">' +
        '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' +
        '<style>body{padding:2rem;font-family:Figtree,sans-serif;}</style>' +
        '</head><body>' + el.innerHTML + '</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// ============================================
// AUTO-DISMISS ALERTS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alert-success').forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 5000);
    });
});
</script>

<script>
// ============================================
// FORCE THEME TOGGLE — BULLETPROOF
// ============================================
(function() {
    'use strict';

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem('theme', theme); } catch(e) {}
    }

    function updateIcon(theme) {
        var icon = document.querySelector('.theme-toggle-icon');
        if (!icon) return;
        icon.className = theme === 'dark'
            ? 'theme-toggle-icon fa-solid fa-sun'
            : 'theme-toggle-icon fa-solid fa-moon';
    }

    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        setTheme(next);
        updateIcon(next);
    }

    // Event delegation — works bisan unsa pa ang element sa ibabaw
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.theme-toggle-btn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            toggleTheme();
        }
    }, true);

    // Set initial icon
    document.addEventListener('DOMContentLoaded', function() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        updateIcon(current);
    });

    window.toggleTheme = toggleTheme;
    window.setTheme = setTheme;
})();
</script>

</body>
</html>