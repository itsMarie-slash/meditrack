<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class AuditLogRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string,mixed> $details */
    public function log(?int $userId, string $action, string $entityType, ?int $entityId, array $details, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => json_encode($details, JSON_UNESCAPED_SLASHES),
            'ip' => $ip,
        ]);
    }

    public function countRecentFailedLogins(string $username, string $ip, int $minutes): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE action = 'login_failed'
               AND created_at >= (NOW() - INTERVAL :minutes MINUTE)
               AND (JSON_UNQUOTE(JSON_EXTRACT(details, '$.username')) = :username OR ip_address = :ip)"
        );
        $stmt->execute(['minutes' => $minutes, 'username' => $username, 'ip' => $ip]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array{user_id?:int,action?:string,date_from?:string,date_to?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs a {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT a.id, a.user_id, u.full_name AS user_name, a.action, a.entity_type, a.entity_id,
                    a.details, a.ip_address, a.created_at
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             {$whereSql}
             ORDER BY a.created_at DESC
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
}
