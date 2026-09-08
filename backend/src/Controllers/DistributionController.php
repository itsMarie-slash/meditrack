<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\DistributionRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Services\AuditLogService;
use MediTrack\Validation\Validator;

final class DistributionController
{
    public static function index(Request $request): void
    {
        $repo = new DistributionRepository(Database::connection());
        $result = $repo->paginate([
            'senior_citizen_id' => $request->query['senior_citizen_id'] ?? null,
            'medicine_id' => $request->query['medicine_id'] ?? null,
            'status' => $request->query['status'] ?? null,
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

    public static function store(Request $request): void
    {
        $errors = (new Validator($request->body, [
            'schedule_id' => 'required|integer',
            'senior_citizen_id' => 'required|integer',
            'medicine_batch_id' => 'required|integer',
            'quantity_issued' => 'required|integer|min:1',
            'receiver_name' => 'required|string|max:150',
            'status' => 'in:pending,completed,cancelled',
        ]))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $db = Database::connection();
        $medicineRepo = new MedicineRepository($db);
        $distributionRepo = new DistributionRepository($db);

        try {
            $medicineRepo->deductBatch((int) $request->body['medicine_batch_id'], (int) $request->body['quantity_issued']);
        } catch (\RuntimeException) {
            Response::error('The selected batch does not have enough remaining stock.', 409);
        }

        $id = $distributionRepo->create($request->body, $request->user['id']);

        (new AuditLogService(new AuditLogRepository($db)))
            ->record($request, 'create', 'distribution', $id, [
                'senior_citizen_id' => $request->body['senior_citizen_id'],
                'quantity_issued' => $request->body['quantity_issued'],
            ]);

        Response::success(['id' => $id], 'Distribution recorded successfully.', 201);
    }

    public static function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $errors = (new Validator($request->body, [
            'status' => 'required|in:pending,completed,cancelled',
        ]))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $db = Database::connection();
        $repo = new DistributionRepository($db);
        if (!$repo->find($id)) {
            Response::error('Distribution record not found.', 404);
        }
        $repo->updateStatus($id, (string) $request->body['status']);

        (new AuditLogService(new AuditLogRepository($db)))
            ->record($request, 'update', 'distribution', $id, ['status' => $request->body['status']]);

        Response::success([], 'Distribution status updated successfully.');
    }
}
