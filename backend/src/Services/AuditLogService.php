<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Request;
use MediTrack\Repositories\AuditLogRepository;

final class AuditLogService
{
    public function __construct(private readonly AuditLogRepository $repository)
    {
    }

    /**
     * @param array<string,mixed> $details
     * @param int|null $actorId override the acting user id — needed for login_failed/login_success,
     *   which happen before AuthMiddleware has populated $request->user.
     */
    public function record(Request $request, string $action, string $entityType, ?int $entityId, array $details = [], ?int $actorId = null): void
    {
        $userId = $actorId ?? $request->user['id'] ?? null;
        $this->repository->log($userId, $action, $entityType, $entityId, $details, $request->ip);
    }
}
