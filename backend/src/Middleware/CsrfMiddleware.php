<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;

/**
 * Synchronizer-token CSRF protection. A token is minted on login (and
 * available via GET /auth/me) and must be echoed back in the
 * X-CSRF-Token header on every state-changing request. Session cookies
 * alone do not protect a cross-origin form/script from triggering a
 * mutating request, so this header check is required in addition to
 * SameSite cookies (defense in depth).
 */
final class CsrfMiddleware
{
    public static function issueToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function verify(Request $request, bool $isPublic): void
    {
        if ($isPublic) {
            return;
        }

        $token = $request->header('X-CSRF-Token', '');
        $expected = $_SESSION['csrf_token'] ?? null;

        if (!$expected || !$token || !hash_equals($expected, $token)) {
            Response::error('Invalid or missing CSRF token', 403);
        }
    }
}
