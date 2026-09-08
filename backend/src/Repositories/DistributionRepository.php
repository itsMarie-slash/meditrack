<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class DistributionRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $bhwId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO distributions
                (schedule_id, senior_citizen_id, medicine_batch_id, quantity_issued, date_released, receiver_name, bhw_id, status)
             VALUES
                (:schedule_id, :senior_citizen_id, :medicine_batch_id, :quantity_issued, NOW(), :receiver_name, :bhw_id, :status)'
        );
        $stmt->execute([
            'schedule_id' => $data['schedule_id'],
            'senior_citizen_id' => $data['senior_citizen_id'],
            'medicine_batch_id' => $data['medicine_batch_id'],
            'quantity_issued' => $data['quantity_issued'],
            'receiver_name' => $data['receiver_name'],
            'bhw_id' => $bhwId,
            'status' => $data['status'] ?? 'completed',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE distributions SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM distributions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array{senior_citizen_id?:string,medicine_id?:string,status?:string,date_from?:string,date_to?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['senior_citizen_id'])) {
            $where[] = 'd.senior_citizen_id = :senior_citizen_id';
            $params['senior_citizen_id'] = $filters['senior_citizen_id'];
        }
        if (!empty($filters['medicine_id'])) {
            $where[] = 'mb.medicine_id = :medicine_id';
            $params['medicine_id'] = $filters['medicine_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'd.date_released >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'd.date_released <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $joins = 'JOIN medicine_batches mb ON mb.id = d.medicine_batch_id
                  JOIN medicines m ON m.id = mb.medicine_id
                  JOIN senior_citizens sc ON sc.id = d.senior_citizen_id
                  JOIN users u ON u.id = d.bhw_id';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM distributions d {$joins} {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT d.id, d.schedule_id, d.senior_citizen_id, sc.full_name AS senior_citizen_name,
                    m.name AS medicine_name, d.quantity_issued, d.date_released, d.receiver_name,
                    u.full_name AS bhw_name, d.status
             FROM distributions d
             {$joins}
             {$whereSql}
             ORDER BY d.date_released DESC
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
