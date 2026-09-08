<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class PurokRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array{id:int,name:string}> */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT id, name, latitude, longitude FROM puroks ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
