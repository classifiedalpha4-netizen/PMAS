/* ============================================================
   PMAS Portal — Application JavaScript
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ================================================================
    // SIDEBAR — Mobile toggle with overlay + scroll lock
    // ================================================================
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('pmas-sidebar');
    const overlay   = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar && sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
    }

    // Close on overlay click
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar when a nav link is clicked (mobile nav)
    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) closeSidebar();
            });
        });
    }

    // Close sidebar on resize to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeSidebar();
    });

    // ================================================================
    // AUTO-DISMISS ALERTS
    // ================================================================
    document.querySelectorAll('.pmas-alert[data-auto-dismiss]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity    = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // ================================================================
    // CONFIRM DANGEROUS ACTIONS
    // ================================================================
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // ================================================================
    // GRADE BAR ANIMATION
    // ================================================================
    document.querySelectorAll('.grade-bar-fill[data-width]').forEach(function (bar) {
        const target = parseFloat(bar.getAttribute('data-width'));
        if (!isNaN(target)) {
            setTimeout(() => { bar.style.width = Math.min(target, 100) + '%'; }, 200);
        }
    });

    // ================================================================
    // PRINT BUTTON
    // ================================================================
    document.querySelectorAll('.print-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // ================================================================
    // TABLE SEARCH FILTER
    // ================================================================
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.pmas-table tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    }

    // ================================================================
    // BOOTSTRAP TOOLTIPS
    // ================================================================
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // ================================================================
    // STICKY TABLE HEADER on mobile scroll
    // ================================================================
    document.querySelectorAll('.table-responsive').forEach(function (wrapper) {
        wrapper.style.overflowX = 'auto';
        wrapper.style.webkitOverflowScrolling = 'touch';
    });

    // ================================================================
    // MOBILE: Collapse Bootstrap navbar after link click
    // ================================================================
    document.querySelectorAll('#navbarMain .nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            const collapse = document.getElementById('navbarMain');
            if (collapse && collapse.classList.contains('show')) {
                const bsCollapse = bootstrap.Collapse.getInstance(collapse);
                if (bsCollapse) bsCollapse.hide();
            }
        });
    });

});
