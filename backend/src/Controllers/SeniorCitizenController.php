<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\SeniorCitizenRepository;
use MediTrack\Services\AuditLogService;
use MediTrack\Validation\Validator;

final class SeniorCitizenController
{
    private const RULES = [
        'full_name' => 'required|string|max:150',
        'birthdate' => 'required|date|date_not_future',
        'gender' => 'required|in:male,female,other',
        'purok_id' => 'required|integer',
        'address_detail' => 'string|max:255',
        'mobile_number' => 'required|ph_mobile',
        'guardian_name' => 'string|max:150',
        'guardian_contact' => 'ph_mobile',
        'medical_condition' => 'required|in:hypertension,diabetes,both',
        'assigned_medicine_id' => 'integer',
        'distribution_status' => 'in:active,pending,inactive',
    ];

    public static function index(Request $request): void
    {
        $repo = new SeniorCitizenRepository(Database::connection());
        $result = $repo->paginate([
            'search' => $request->query['search'] ?? null,
            'purok_id' => $request->query['purok_id'] ?? null,
            'medical_condition' => $request->query['medical_condition'] ?? null,
            'distribution_status' => $request->query['distribution_status'] ?? null,
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
        $repo = new SeniorCitizenRepository(Database::connection());
        $record = $repo->find((int) $request->params['id']);
        if (!$record) {
            Response::error('Senior citizen record not found.', 404);
        }
        Response::success(['senior_citizen' => $record]);
    }

    public static function store(Request $request): void
    {
        $errors = (new Validator($request->body, self::RULES))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $repo = new SeniorCitizenRepository(Database::connection());
        $id = $repo->create($request->body);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'create', 'senior_citizen', $id, ['full_name' => $request->body['full_name']]);

        Response::success(['id' => $id], 'Beneficiary added successfully.', 201);
    }

    public static function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $errors = (new Validator($request->body, self::RULES))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $repo = new SeniorCitizenRepository(Database::connection());
        if (!$repo->find($id)) {
            Response::error('Senior citizen record not found.', 404);
        }
        $repo->update($id, $request->body);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'update', 'senior_citizen', $id);

        Response::success([], 'Beneficiary updated successfully.');
    }

    public static function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $repo = new SeniorCitizenRepository(Database::connection());
        if (!$repo->find($id)) {
            Response::error('Senior citizen record not found.', 404);
        }
        $repo->deactivate($id);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'delete', 'senior_citizen', $id);

        Response::success([], 'Beneficiary record deactivated successfully.');
    }
}
