/**
 * sidebar_toggle.js
 * Controls show/hide behavior of the sidebar navigation.
 * Remembers the user's preference across page loads using localStorage.
 */
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const body = document.body;

    if (!sidebar || !toggleBtn) return;

    const isHidden = localStorage.getItem('meditrack_sidebar_hidden') === 'true';
    if (isHidden) {
        sidebar.classList.add('sidebar-hidden');
        body.classList.add('sidebar-collapsed');
    }

    toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('sidebar-hidden');
        const nowHidden = sidebar.classList.contains('sidebar-hidden');
        body.classList.toggle('sidebar-collapsed', nowHidden);
        localStorage.setItem('meditrack_sidebar_hidden', nowHidden);
    });
});
