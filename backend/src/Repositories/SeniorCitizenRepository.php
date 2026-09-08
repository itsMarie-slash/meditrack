<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class SeniorCitizenRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array{search?:string,purok_id?:string,medical_condition?:string,distribution_status?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $where = ['sc.is_active = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(sc.full_name LIKE :search OR sc.mobile_number LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['purok_id'])) {
            $where[] = 'sc.purok_id = :purok_id';
            $params['purok_id'] = $filters['purok_id'];
        }
        if (!empty($filters['medical_condition'])) {
            $where[] = 'sc.medical_condition = :medical_condition';
            $params['medical_condition'] = $filters['medical_condition'];
        }
        if (!empty($filters['distribution_status'])) {
            $where[] = 'sc.distribution_status = :distribution_status';
            $params['distribution_status'] = $filters['distribution_status'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM senior_citizens sc {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT sc.id, sc.full_name, sc.birthdate, sc.gender, sc.purok_id, p.name AS purok_name,
                    sc.address_detail, sc.mobile_number, sc.guardian_name, sc.guardian_contact,
                    sc.medical_condition, sc.assigned_medicine_id, m.name AS assigned_medicine_name,
                    sc.distribution_status, sc.latitude, sc.longitude
             FROM senior_citizens sc
             JOIN puroks p ON p.id = sc.purok_id
             LEFT JOIN medicines m ON m.id = sc.assigned_medicine_id
             {$whereSql}
             ORDER BY sc.full_name ASC
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
        $stmt = $this->db->prepare(
            'SELECT sc.*, p.name AS purok_name, m.name AS assigned_medicine_name
             FROM senior_citizens sc
             JOIN puroks p ON p.id = sc.purok_id
             LEFT JOIN medicines m ON m.id = sc.assigned_medicine_id
             WHERE sc.id = :id AND sc.is_active = 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO senior_citizens
                (full_name, birthdate, gender, purok_id, address_detail, latitude, longitude,
                 mobile_number, guardian_name, guardian_contact, medical_condition,
                 assigned_medicine_id, distribution_status)
             VALUES
                (:full_name, :birthdate, :gender, :purok_id, :address_detail, :latitude, :longitude,
                 :mobile_number, :guardian_name, :guardian_contact, :medical_condition,
                 :assigned_medicine_id, :distribution_status)'
        );
        $stmt->execute($this->bindable($data));
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE senior_citizens SET
                full_name = :full_name, birthdate = :birthdate, gender = :gender, purok_id = :purok_id,
                address_detail = :address_detail, latitude = :latitude, longitude = :longitude,
                mobile_number = :mobile_number, guardian_name = :guardian_name,
                guardian_contact = :guardian_contact, medical_condition = :medical_condition,
                assigned_medicine_id = :assigned_medicine_id, distribution_status = :distribution_status
             WHERE id = :id'
        );
        $stmt->execute($this->bindable($data) + ['id' => $id]);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE senior_citizens SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array{purok_id?:string,medical_condition?:string,distribution_status?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function forMap(array $filters): array
    {
        $where = ['sc.is_active = 1'];
        $params = [];

        if (!empty($filters['purok_id'])) {
            $where[] = 'sc.purok_id = :purok_id';
            $params['purok_id'] = $filters['purok_id'];
        }
        if (!empty($filters['medical_condition'])) {
            $where[] = 'sc.medical_condition = :medical_condition';
            $params['medical_condition'] = $filters['medical_condition'];
        }
        if (!empty($filters['distribution_status'])) {
            $where[] = 'sc.distribution_status = :distribution_status';
            $params['distribution_status'] = $filters['distribution_status'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare(
            "SELECT sc.id, sc.full_name, sc.medical_condition, sc.distribution_status,
                    m.name AS assigned_medicine_name, p.name AS purok_name,
                    COALESCE(sc.latitude, p.latitude) AS latitude,
                    COALESCE(sc.longitude, p.longitude) AS longitude
             FROM senior_citizens sc
             JOIN puroks p ON p.id = sc.purok_id
             LEFT JOIN medicines m ON m.id = sc.assigned_medicine_id
             {$whereSql}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<array{medicine_id:int,senior_citizen_id:int}> beneficiaries assigned to a medicine */
    public function findByAssignedMedicine(int $medicineId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, full_name, mobile_number FROM senior_citizens
             WHERE assigned_medicine_id = :medicine_id AND is_active = 1'
        );
        $stmt->execute(['medicine_id' => $medicineId]);
        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function bindable(array $data): array
    {
        return [
            'full_name' => $data['full_name'],
            'birthdate' => $data['birthdate'],
            'gender' => $data['gender'],
            'purok_id' => $data['purok_id'],
            'address_detail' => self::nullIfBlank($data['address_detail'] ?? null),
            'latitude' => self::nullIfBlank($data['latitude'] ?? null),
            'longitude' => self::nullIfBlank($data['longitude'] ?? null),
            'mobile_number' => $data['mobile_number'],
            'guardian_name' => self::nullIfBlank($data['guardian_name'] ?? null),
            'guardian_contact' => self::nullIfBlank($data['guardian_contact'] ?? null),
            'medical_condition' => $data['medical_condition'],
            'assigned_medicine_id' => self::nullIfBlank($data['assigned_medicine_id'] ?? null),
            'distribution_status' => $data['distribution_status'] ?: 'active',
        ];
    }

    /**
     * HTML <select>/<input> fields submit an empty string for "no value",
     * which is invalid for nullable numeric/FK columns — normalize to null.
     */
    private static function nullIfBlank(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
