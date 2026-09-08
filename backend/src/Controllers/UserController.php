<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\AuditLogService;
use MediTrack\Validation\Validator;

final class UserController
{
    private const ROLES = ['bhw', 'midwife', 'ipho'];

    public static function index(Request $request): void
    {
        $repo = new UserRepository(Database::connection());
        $result = $repo->paginate(
            ['search' => $request->query['search'] ?? null, 'role' => $request->query['role'] ?? null],
            $request->paginationLimit(),
            $request->paginationOffset()
        );

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
            'username' => 'required|string|min:3|max:50',
            'password' => 'required|string|min:8',
            'full_name' => 'required|string|max:120',
            'role' => 'required|in:' . implode(',', self::ROLES),
        ]))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $repo = new UserRepository(Database::connection());
        if ($repo->usernameExists((string) $request->body['username'])) {
            Response::error('That username is already taken.', 409, ['username' => 'Username already exists.']);
        }

        $hash = password_hash((string) $request->body['password'], PASSWORD_DEFAULT);
        $id = $repo->create(
            (string) $request->body['username'],
            $hash,
            (string) $request->body['full_name'],
            (string) $request->body['role']
        );

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'create', 'user', $id, ['username' => $request->body['username']]);

        Response::success(['id' => $id], 'User created successfully.', 201);
    }

    public static function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $errors = (new Validator($request->body, [
            'full_name' => 'required|string|max:120',
            'role' => 'required|in:' . implode(',', self::ROLES),
            'is_active' => 'required',
        ]))->validate();
        if ($errors) {
            Response::error('Please correct the highlighted fields.', 400, $errors);
        }

        $repo = new UserRepository(Database::connection());
        if (!$repo->findById($id)) {
            Response::error('User not found.', 404);
        }

        $repo->update($id, (string) $request->body['full_name'], (string) $request->body['role'], (bool) $request->body['is_active']);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'update', 'user', $id);

        Response::success([], 'User updated successfully.');
    }

    public static function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $repo = new UserRepository(Database::connection());
        if (!$repo->findById($id)) {
            Response::error('User not found.', 404);
        }
        if ($id === $request->user['id']) {
            Response::error('You cannot deactivate your own account.', 400);
        }

        $repo->deactivate($id);

        (new AuditLogService(new AuditLogRepository(Database::connection())))
            ->record($request, 'deactivate', 'user', $id);

        Response::success([], 'User deactivated successfully.');
    }
}
