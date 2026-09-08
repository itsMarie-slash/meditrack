<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

class SystemSettingRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :value1)
             ON DUPLICATE KEY UPDATE setting_value = :value2'
        );
        $stmt->execute(['key' => $key, 'value1' => $value, 'value2' => $value]);
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT setting_key, setting_value FROM system_settings');
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }
}
