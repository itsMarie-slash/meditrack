<?php
/**
 * index.php
 * -----------------------------------------------------------------
 * Application entry point. Simply routes the visitor to the correct
 * place based on whether they already have a session.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to('/meditrack/modules/dashboard/index.php');
} else {
    redirect_to('/meditrack/modules/auth/login.php');
}
