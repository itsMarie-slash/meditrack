<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class MedicineRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array{search?:string,category?:string,low_stock_only?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $where = ['is_active = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = 'name LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category'])) {
            $where[] = 'category = :category';
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['low_stock_only'])) {
            $where[] = 'stock_quantity <= low_stock_threshold';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM medicines {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT id, name, category, description, unit, stock_quantity, low_stock_threshold,
                    (stock_quantity <= low_stock_threshold) AS is_low_stock,
                    (SELECT MIN(expiration_date) FROM medicine_batches b WHERE b.medicine_id = medicines.id AND b.quantity_remaining > 0) AS nearest_expiration
             FROM medicines
             {$whereSql}
             ORDER BY name ASC
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

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM medicines WHERE id = :id AND is_active = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $batchStmt = $this->db->prepare(
            'SELECT id, batch_number, quantity, quantity_remaining, expiration_date, date_received
             FROM medicine_batches WHERE medicine_id = :id ORDER BY expiration_date ASC'
        );
        $batchStmt->execute(['id' => $id]);
        $row['batches'] = $batchStmt->fetchAll();

        return $row;
    }

    public function nameExists(string $name, ?int $excludingId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM medicines WHERE name = :name';
        $params = ['name' => $name];
        if ($excludingId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludingId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medicines (name, category, description, unit, low_stock_threshold)
             VALUES (:name, :category, :description, :unit, :low_stock_threshold)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'],
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 20,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE medicines SET name = :name, category = :category, description = :description,
                unit = :unit, low_stock_threshold = :low_stock_threshold
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'],
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 20,
            'id' => $id,
        ]);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE medicines SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Records a stock-in batch and updates the running total inside one
     * transaction. Returns the before/after quantity so the caller can
     * decide whether this stock-in should trigger a distribution schedule
     * (see docs/02-architecture.md §3 and docs/01-requirements-analysis.md §13.5).
     *
     * @return array{old_quantity:int,new_quantity:int,threshold:int,batch_id:int}
     */
    public function addStock(int $medicineId, int $quantity, string $batchNumber, string $expirationDate, string $dateReceived): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT stock_quantity, low_stock_threshold FROM medicines WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $medicineId]);
            $medicine = $stmt->fetch();
            if (!$medicine) {
                throw new \RuntimeException('Medicine not found.');
            }
            $oldQuantity = (int) $medicine['stock_quantity'];
            $threshold = (int) $medicine['low_stock_threshold'];

            $batchStmt = $this->db->prepare(
                'INSERT INTO medicine_batches (medicine_id, batch_number, quantity, quantity_remaining, expiration_date, date_received)
                 VALUES (:medicine_id, :batch_number, :quantity, :quantity_remaining, :expiration_date, :date_received)'
            );
            $batchStmt->execute([
                'medicine_id' => $medicineId,
                'batch_number' => $batchNumber,
                'quantity' => $quantity,
                'quantity_remaining' => $quantity,
                'expiration_date' => $expirationDate,
                'date_received' => $dateReceived,
            ]);
            $batchId = (int) $this->db->lastInsertId();

            $newQuantity = $oldQuantity + $quantity;
            $updateStmt = $this->db->prepare('UPDATE medicines SET stock_quantity = :qty WHERE id = :id');
            $updateStmt->execute(['qty' => $newQuantity, 'id' => $medicineId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'threshold' => $threshold,
            'batch_id' => $batchId,
        ];
    }

    /** Earliest-expiring batch with remaining stock, for FEFO dispensing. */
    public function nextAvailableBatch(int $medicineId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, quantity_remaining, expiration_date FROM medicine_batches
             WHERE medicine_id = :medicine_id AND quantity_remaining > 0
             ORDER BY expiration_date ASC LIMIT 1'
        );
        $stmt->execute(['medicine_id' => $medicineId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deductBatch(int $batchId, int $quantity): void
    {
        $stmt = $this->db->prepare(
            'UPDATE medicine_batches SET quantity_remaining = quantity_remaining - :qty1
             WHERE id = :id AND quantity_remaining >= :qty2'
        );
        $stmt->execute(['qty1' => $quantity, 'qty2' => $quantity, 'id' => $batchId]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Insufficient batch quantity remaining.');
        }

        $medStmt = $this->db->prepare(
            'UPDATE medicines m
             JOIN medicine_batches b ON b.medicine_id = m.id
             SET m.stock_quantity = m.stock_quantity - :qty
             WHERE b.id = :id'
        );
        $medStmt->execute(['qty' => $quantity, 'id' => $batchId]);
    }

    /** @return list<array{id:int,name:string,category:string}> */
    public function categories(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT category FROM medicines WHERE is_active = 1 ORDER BY category');
        return array_column($stmt->fetchAll(), 'category');
    }
}
