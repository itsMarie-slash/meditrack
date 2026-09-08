<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AuditLogRepository;

final class AuditLogController
{
    public static function index(Request $request): void
    {
        $repo = new AuditLogRepository(Database::connection());
        $result = $repo->paginate([
            'user_id' => $request->query['user_id'] ?? null,
            'action' => $request->query['action'] ?? null,
            'date_from' => $request->query['date_from'] ?? null,
            'date_to' => $request->query['date_to'] ?? null,
        ], $request->paginationLimit(), $request->paginationOffset());

        Response::success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $request->paginationPage(),
            'per_page' => $request->paginationLimit(),
        ]);
    }
}
