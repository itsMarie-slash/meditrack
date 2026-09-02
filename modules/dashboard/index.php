<?php
/**
 * modules/dashboard/index.php
 * -----------------------------------------------------------------
 * TEMPORARY STUB - Module 2 (Dashboard) will replace this file with
 * the full role-based dashboard (stats cards, charts, alerts, etc).
 *
 * This stub exists only so Module 1 (Login) has a real destination
 * to redirect to and can be tested end-to-end. It confirms that:
 *   - require_login() correctly blocks guests
 *   - session data (name/role) was stored correctly at login
 *   - logout.php correctly ends the session
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MediTrack</title>
    <link rel="stylesheet" href="/meditrack/assets/css/style.css">
</head>
<body>

<div class="topbar">
    <div class="brand">MediTrack</div>
    <div class="user-info">
        <?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?>
        (<?php echo htmlspecialchars(role_label($_SESSION['role']), ENT_QUOTES, 'UTF-8'); ?>)
        <a href="/meditrack/modules/auth/logout.php">Logout</a>
    </div>
</div>

<div class="page-content">
    <h2>Login successful</h2>
    <p>This is a placeholder page. The full Dashboard module (stats, charts, alerts) will be built in Module 2.</p>
</div>

</body>
</html>
