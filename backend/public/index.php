<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MediTrack\Config\Env;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Router;

Env::load(__DIR__ . '/../.env');

$origin = Env::get('FRONTEND_ORIGIN', '');
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_name('meditrack_session');
session_set_cookie_params([
    'lifetime' => Env::int('SESSION_LIFETIME_SECONDS', 3600),
    'path' => '/',
    'secure' => Env::bool('SESSION_COOKIE_SECURE', true),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$router = new Router();
require __DIR__ . '/../routes/api.php';

$request = Request::fromGlobals();

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    error_log('[MediTrack] Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error('An unexpected error occurred. Please try again later.', 500);
}
