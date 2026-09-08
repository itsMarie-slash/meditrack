<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Middleware\AuthMiddleware;
use MediTrack\Middleware\CsrfMiddleware;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\AuditLogService;
use MediTrack\Validation\Validator;

final class AuthController
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public static function login(Request $request): void
    {
        $errors = (new Validator($request->body, [
            'username' => 'required|string|max:50',
            'password' => 'required|string',
        ]))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $db = Database::connection();
        $users = new UserRepository($db);
        $auditRepo = new AuditLogRepository($db);
        $audit = new AuditLogService($auditRepo);

        $username = (string) $request->body['username'];
        $password = (string) $request->body['password'];

        if ($auditRepo->countRecentFailedLogins($username, $request->ip, self::WINDOW_MINUTES) >= self::MAX_ATTEMPTS) {
            Response::error('Too many failed attempts. Please try again in a few minutes.', 429);
        }

        $user = $users->findByUsername($username);
        $valid = $user && (bool) $user['is_active'] && password_verify($password, $user['password_hash']);

        if (!$valid) {
            $audit->record($request, 'login_failed', 'user', $user['id'] ?? null, ['username' => $username], $user['id'] ?? null);
            Response::error('Invalid username or password.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $csrfToken = CsrfMiddleware::issueToken();

        $audit->record($request, 'login_success', 'user', (int) $user['id'], ['username' => $username], (int) $user['id']);

        Response::success([
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
            ],
            'csrf_token' => $csrfToken,
        ], 'Signed in successfully.');
    }

    public static function logout(Request $request): void
    {
        $db = Database::connection();
        (new AuditLogService(new AuditLogRepository($db)))->record($request, 'logout', 'user', $request->user['id'] ?? null);

        $_SESSION = [];
        session_destroy();

        Response::success([], 'Signed out successfully.');
    }

    public static function me(Request $request): void
    {
        $user = AuthMiddleware::handle();
        $csrfToken = $_SESSION['csrf_token'] ?? CsrfMiddleware::issueToken();

        Response::success([
            'user' => $user,
            'csrf_token' => $csrfToken,
        ]);
    }
}
