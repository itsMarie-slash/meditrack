<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\PurokRepository;

final class PurokController
{
    public static function index(Request $request): void
    {
        $repo = new PurokRepository(Database::connection());
        Response::success(['items' => $repo->all()]);
    }
}
