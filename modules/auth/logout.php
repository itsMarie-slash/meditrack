<?php
/**
 * modules/auth/logout.php
 * -----------------------------------------------------------------
 * Destroys the current session and returns the user to the login
 * page. Linked from the topbar "Logout" button on every protected page.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

logout_user();

redirect_to('/meditrack/modules/auth/login.php');
