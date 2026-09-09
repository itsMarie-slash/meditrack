<?php
/**
 * dashboard.php
 * Main landing page after login. Uses the icon-badge header
 * pattern and soft pastel stat cards from the reference design.
 */

require_once 'db.php';
require_once 'auth_check.php';
require_role(['BHW', 'Midwife', 'IPHO']);
require_once 'icons.php';

$fullName = $_SESSION['full_name'];
$role     = $_SESSION['role'];

// TEMPORARY placeholders — replaced with real queries once
// senior_citizens, medicines, and distributions tables exist.
$totalMedicines    = 0;
$totalSeniors      = 0;
$lowStockCount     = 0;
$upcomingSchedules = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEDITRACK | Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-body">

    <?php include 'nav.php'; ?>

    <main class="main-content">
       <div class="page-header">
    <h1>Welcome, <?php echo htmlspecialchars($fullName); ?></h1>
    <p>New Bulatukan Barangay Health Center</p>
</div>

        <div class="stats-grid">
            <?php if (in_array($role, ['BHW', 'Midwife'], true)): ?>
                <div class="stat-card soft-blue">
                    <div class="stat-label">Total Medicines</div>
                    <div class="stat-value"><?php echo $totalMedicines; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Registered Seniors</div>
                    <div class="stat-value"><?php echo $totalSeniors; ?></div>
                </div>
                <div class="stat-card soft-warning">
                    <div class="stat-label">Low Stock Alerts</div>
                    <div class="stat-value"><?php echo $lowStockCount; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Upcoming Schedules</div>
                    <div class="stat-value"><?php echo $upcomingSchedules; ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>System Overview</h3>
            <?php if ($role === 'BHW'): ?>
                <p>You have full access to manage medicine inventory, register senior citizens, dispense medicines, manage user accounts, view distribution schedules, and access reports and GIS mapping.</p>
            <?php elseif ($role === 'Midwife'): ?>
                <p>You can monitor inventory levels, view senior citizen records, check distribution schedules and logs, view GIS mapping, and access demand forecasting reports.</p>
            <?php else: ?>
                <p>You have access to demand forecasting data and reports to support provincial health planning and supply decisions.</p>
            <?php endif; ?>
        </div>
    </main>

    <script src="sidebar_toggle.js"></script>
</body>
</html>
