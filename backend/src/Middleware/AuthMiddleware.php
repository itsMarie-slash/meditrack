<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Response;

final class AuthMiddleware
{
    /** @return array{id:int,username:string,role:string,full_name:string} */
    public static function handle(): array
    {
        if (empty($_SESSION['user_id'])) {
            Response::error('Authentication required', 401);
        }

        return [
            'id' => (int) $_SESSION['user_id'],
            'username' => (string) $_SESSION['username'],
            'role' => (string) $_SESSION['role'],
            'full_name' => (string) $_SESSION['full_name'],
        ];
    }
}
