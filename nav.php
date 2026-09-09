<?php
/**
 * nav.php
 * Shared sidebar navigation — included on every protected page.
 * White sidebar, icon badge logo, soft-highlighted active item —
 * matching the reference design. Menu items shown depend on role.
 */

require_once 'icons.php';

$currentRole = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);

function nav_active(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'active' : '';
}
?>
<button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Toggle navigation menu">&#9776;</button>

<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="logo-badge"><?php echo icon('logo'); ?></span>
        <span class="brand-name">MEDITRACK</span>
    </div>

    <div class="sidebar-user">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></div>
        <span class="role-badge"><?php echo htmlspecialchars($currentRole); ?></span>
    </div>

    <div class="sidebar-section-label">Menu</div>

    <nav class="sidebar-menu">
        <a href="dashboard.php" class="<?php echo nav_active('dashboard.php', $currentPage); ?>">
            <span class="nav-icon"><?php echo icon('dashboard'); ?></span> Dashboard
        </a>

        <?php if ($currentRole === 'BHW'): ?>
            <a href="users.php" class="<?php echo nav_active('users.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('users'); ?></span> User Management
            </a>
        <?php endif; ?>

        <?php if (in_array($currentRole, ['BHW', 'Midwife'], true)): ?>
            <a href="seniors.php" class="<?php echo nav_active('seniors.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('senior'); ?></span> Senior Citizens
            </a>
            <a href="inventory.php" class="<?php echo nav_active('inventory.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('pill'); ?></span> Medicine Inventory
            </a>
            <a href="schedules.php" class="<?php echo nav_active('schedules.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('calendar'); ?></span> Distribution Schedules
            </a>
            <a href="distribution_log.php" class="<?php echo nav_active('distribution_log.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('list'); ?></span> Distribution Log
            </a>
            <a href="gis_map.php" class="<?php echo nav_active('gis_map.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('map'); ?></span> GIS Mapping
            </a>
        <?php endif; ?>

        <?php if (in_array($currentRole, ['BHW', 'Midwife', 'IPHO'], true)): ?>
            <a href="forecasting.php" class="<?php echo nav_active('forecasting.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('trending'); ?></span> Demand Forecasting
            </a>
            <a href="reports.php" class="<?php echo nav_active('reports.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('report'); ?></span> Reports
            </a>
        <?php endif; ?>

        <a href="profile.php" class="<?php echo nav_active('profile.php', $currentPage); ?>">
            <span class="nav-icon"><?php echo icon('settings'); ?></span> Profile Settings
        </a>

        <?php if ($currentRole === 'BHW'): ?>
            <a href="audit_logs.php" class="<?php echo nav_active('audit_logs.php', $currentPage); ?>">
                <span class="nav-icon"><?php echo icon('shield'); ?></span> Audit Logs
            </a>
        <?php endif; ?>

        <a href="logout.php" class="logout-link">
            <span class="nav-icon"><?php echo icon('logout'); ?></span> Logout
        </a>
    </nav>
</aside>
