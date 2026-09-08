<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Response;

final class RoleMiddleware
{
    /**
     * @param array{id:int,username:string,role:string,full_name:string} $user
     * @param list<string> $allowedRoles
     */
    public static function check(array $user, array $allowedRoles): void
    {
        if (!in_array($user['role'], $allowedRoles, true)) {
            Response::error('You are not authorized to perform this action', 403);
        }
    }
}
