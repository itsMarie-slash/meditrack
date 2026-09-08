<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\DistributionScheduleRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SeniorCitizenRepository;
use MediTrack\Repositories\SmsNotificationRepository;
use MediTrack\Repositories\SystemSettingRepository;
use MediTrack\Services\AuditLogService;
use MediTrack\Services\MedicineService;
use MediTrack\Services\SmsService;
use MediTrack\Validation\Validator;

final class MedicineController
{
    private const RULES = [
        'name' => 'required|string|max:120',
        'category' => 'required|string|max:60',
        'description' => 'string',
        'unit' => 'required|string|max:20',
        'low_stock_threshold' => 'integer|min:0',
    ];

    public static function index(Request $request): void
    {
        $repo = new MedicineRepository(Database::connection());
        $result = $repo->paginate([
            'search' => $request->query['search'] ?? null,
            'category' => $request->query['category'] ?? null,
            'low_stock_only' => $request->query['low_stock_only'] ?? null,
        ], $request->paginationLimit(), $request->paginationOffset());

        Response::success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $request->paginationPage(),
            'per_page' => $request->paginationLimit(),
        ]);
    }

    public static function show(Request $request): void
    {
        $repo = new MedicineRepository(Database::connection());
        $medicine = $repo->find((int) $request->params['id']);
        if (!$medicine) {
            Response::error('Medicine not found.', 404);
        }
        Response::success(['medicine' => $medicine]);
    }

    public static function store(Request $request): void
    {
        $errors = (new Validator($request->body, self::RULES))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $repo = new MedicineRepository(Database::connection());
        if ($repo->nameExists((string) $request->body['name'])) {
            Response::error('A medicine with that name already exists.', 409, ['name' => 'Already exists.']);
        }

        $id = $repo->create($request->body);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'create', 'medicine', $id, ['name' => $request->body['name']]);

        Response::success(['id' => $id], 'Medicine added successfully.', 201);
    }

    public static function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $errors = (new Validator($request->body, self::RULES))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $repo = new MedicineRepository(Database::connection());
        if (!$repo->find($id)) {
            Response::error('Medicine not found.', 404);
        }
        if ($repo->nameExists((string) $request->body['name'], $id)) {
            Response::error('A medicine with that name already exists.', 409, ['name' => 'Already exists.']);
        }

        $repo->update($id, $request->body);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'update', 'medicine', $id);

        Response::success([], 'Medicine updated successfully.');
    }

    public static function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $repo = new MedicineRepository(Database::connection());
        if (!$repo->find($id)) {
            Response::error('Medicine not found.', 404);
        }
        $repo->deactivate($id);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'delete', 'medicine', $id);

        Response::success([], 'Medicine deactivated successfully.');
    }

    public static function addStock(Request $request): void
    {
        $id = (int) $request->params['id'];
        $errors = (new Validator($request->body, [
            'quantity' => 'required|integer|min:1',
            'batch_number' => 'required|string|max:60',
            'expiration_date' => 'required|date',
            'date_received' => 'required|date|date_not_future',
        ]))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $db = Database::connection();
        $medicineRepo = new MedicineRepository($db);
        $medicine = $medicineRepo->find($id);
        if (!$medicine) {
            Response::error('Medicine not found.', 404);
        }

        if ((string) $request->body['expiration_date'] <= (string) $request->body['date_received']) {
            Response::error('Please correct the highlighted fields.', 400, ['expiration_date' => 'Expiration date must be after the date received.']);
        }

        $service = new MedicineService(
            $medicineRepo,
            new DistributionScheduleRepository($db),
            new SeniorCitizenRepository($db),
            new SmsNotificationRepository($db),
            new SystemSettingRepository($db),
            new SmsService(),
        );

        $result = $service->receiveStock(
            $id,
            $medicine['name'],
            (int) $request->body['quantity'],
            (string) $request->body['batch_number'],
            (string) $request->body['expiration_date'],
            (string) $request->body['date_received'],
        );

        (new AuditLogService(new AuditLogRepository($db)))
            ->record($request, 'stock_update', 'medicine', $id, [
                'quantity' => $request->body['quantity'],
                'batch_number' => $request->body['batch_number'],
                'schedule_created' => $result['schedule_created'],
            ]);

        $message = $result['schedule_created']
            ? "Stock updated. A distribution schedule was created and {$result['sms_sent']} beneficiaries notified."
            : 'Stock updated successfully.';

        Response::success($result, $message);
    }
}
