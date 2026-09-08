<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.password_hash, u.full_name, u.is_active, r.name AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.full_name, u.is_active, r.name AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function usernameExists(string $username, ?int $excludingId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $params = ['username' => $username];
        if ($excludingId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludingId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array{search?:string,role?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(u.username LIKE :search OR u.full_name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['role'])) {
            $where[] = 'r.name = :role';
            $params['role'] = $filters['role'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.full_name, u.is_active, u.created_at, r.name AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             {$whereSql}
             ORDER BY u.full_name ASC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function create(string $username, string $passwordHash, string $fullName, string $role): int
    {
        $roleId = $this->roleIdFor($role);
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, full_name, role_id) VALUES (:username, :password_hash, :full_name, :role_id)'
        );
        $stmt->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'full_name' => $fullName,
            'role_id' => $roleId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $fullName, string $role, bool $isActive): void
    {
        $roleId = $this->roleIdFor($role);
        $stmt = $this->db->prepare(
            'UPDATE users SET full_name = :full_name, role_id = :role_id, is_active = :is_active WHERE id = :id'
        );
        $stmt->execute([
            'full_name' => $fullName,
            'role_id' => $roleId,
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function roleIdFor(string $role): int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE name = :name');
        $stmt->execute(['name' => $role]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \InvalidArgumentException("Unknown role: {$role}");
        }
        return (int) $id;
    }
}
