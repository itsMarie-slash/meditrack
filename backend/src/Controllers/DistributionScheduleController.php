<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\DistributionScheduleRepository;
use MediTrack\Services\AuditLogService;
use MediTrack\Validation\Validator;

final class DistributionScheduleController
{
    public static function index(Request $request): void
    {
        $repo = new DistributionScheduleRepository(Database::connection());
        $result = $repo->paginate([
            'status' => $request->query['status'] ?? null,
            'medicine_id' => $request->query['medicine_id'] ?? null,
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

    public static function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $errors = (new Validator($request->body, [
            'scheduled_date' => 'required|date',
            'time_slot' => 'required|string|max:40',
            'venue' => 'required|string|max:150',
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
        ]))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $repo = new DistributionScheduleRepository(Database::connection());
        if (!$repo->find($id)) {
            Response::error('Distribution schedule not found.', 404);
        }
        $repo->update($id, $request->body);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'update', 'distribution_schedule', $id, ['status' => $request->body['status']]);

        Response::success([], 'Distribution schedule updated successfully.');
    }
}
