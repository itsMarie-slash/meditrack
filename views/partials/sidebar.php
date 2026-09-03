<?php
/**
 * Left navigation. Menu items shown depend on $user['role'].
 * "bhw" and "midwife" share the /bhw/ operational pages (full CRUD);
 * "ipho" gets the read-only /ipho/ reporting pages.
 * $activeMenu (set by the including page) controls the highlighted item.
 */
$active = $activeMenu ?? '';
$role = $user['role'];

function navItem(string $key, string $active, string $href, string $icon, string $label): void
{
    $isActive = $key === $active ? ' active' : '';
    echo '<a class="nav-link' . $isActive . '" href="' . h($href) . '"><i class="bi ' . h($icon) . '"></i> ' . h($label) . '</a>';
}
?>
<nav class="app-sidebar">
    <?php if (in_array($role, ['bhw', 'midwife'], true)): ?>
        <div class="nav-section-label">Operations</div>
        <?php
        navItem('dashboard', $active, '/bhw/dashboard.php', 'bi-speedometer2', 'Dashboard');
        navItem('beneficiaries', $active, '/bhw/beneficiaries.php', 'bi-people', 'Beneficiaries');
        navItem('inventory', $active, '/bhw/inventory.php', 'bi-capsule', 'Medicine Inventory');
        navItem('distribution', $active, '/bhw/distribution.php', 'bi-clipboard2-pulse', 'Distribution');
        ?>
        <div class="nav-section-label">Planning</div>
        <?php
        navItem('forecast', $active, '/bhw/forecast.php', 'bi-graph-up-arrow', 'Demand Forecast');
        navItem('map', $active, '/bhw/map.php', 'bi-geo-alt', 'GIS Map');
        ?>
        <div class="nav-section-label">Communication</div>
        <?php
        navItem('sms', $active, '/bhw/sms_broadcast.php', 'bi-chat-dots', 'SMS Broadcast');
        navItem('reports', $active, '/bhw/reports.php', 'bi-file-earmark-spreadsheet', 'Reports / Export');
        ?>
    <?php elseif ($role === 'ipho'): ?>
        <div class="nav-section-label">Overview</div>
        <?php
        navItem('dashboard', $active, '/ipho/dashboard.php', 'bi-speedometer2', 'Dashboard');
        navItem('beneficiaries', $active, '/ipho/beneficiaries.php', 'bi-people', 'Beneficiaries');
        navItem('inventory', $active, '/ipho/inventory.php', 'bi-capsule', 'Medicine Inventory');
        navItem('distribution', $active, '/ipho/distribution.php', 'bi-clipboard2-pulse', 'Distribution History');
        ?>
        <div class="nav-section-label">Planning</div>
        <?php
        navItem('forecast', $active, '/ipho/forecast.php', 'bi-graph-up-arrow', 'Demand Forecast');
        navItem('map', $active, '/ipho/map.php', 'bi-geo-alt', 'GIS Map');
        navItem('reports', $active, '/ipho/reports.php', 'bi-file-earmark-spreadsheet', 'Reports / Export');
        ?>
    <?php endif; ?>
</nav>
