<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\SeniorCitizenRepository;

final class MapController
{
    public static function beneficiaries(Request $request): void
    {
        $repo = new SeniorCitizenRepository(Database::connection());
        $points = $repo->forMap([
            'purok_id' => $request->query['purok_id'] ?? null,
            'medical_condition' => $request->query['medical_condition'] ?? null,
            'distribution_status' => $request->query['distribution_status'] ?? null,
        ]);

        Response::success(['points' => $points]);
    }
}
