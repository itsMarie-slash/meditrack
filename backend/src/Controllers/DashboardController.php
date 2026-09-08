<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\ForecastRepository;
use MediTrack\Services\ForecastService;
use PDO;

final class DashboardController
{
    public static function index(Request $request): void
    {
        $db = Database::connection();
        $role = $request->user['role'];

        if ($role === 'ipho') {
            Response::success(self::forecastSummary($db));
        }

        $data = [
            'active_beneficiaries' => (int) $db->query('SELECT COUNT(*) FROM senior_citizens WHERE is_active = 1')->fetchColumn(),
            'low_stock_medicines' => (int) $db->query('SELECT COUNT(*) FROM medicines WHERE is_active = 1 AND stock_quantity <= low_stock_threshold')->fetchColumn(),
            'upcoming_distributions_this_week' => (int) self::upcomingThisWeek($db),
        ];

        if ($role === 'bhw') {
            $data['pending_sms_failures'] = (int) $db->query("SELECT COUNT(*) FROM sms_notifications WHERE status = 'failed'")->fetchColumn();
            $recent = (new AuditLogRepository($db))->paginate([], 10, 0);
            $data['recent_activity'] = $recent['items'];
        }

        Response::success($data);
    }

    private static function upcomingThisWeek(PDO $db): int
    {
        $stmt = $db->query(
            "SELECT COUNT(*) FROM distribution_schedules
             WHERE status = 'upcoming' AND scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        );
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,mixed> */
    private static function forecastSummary(PDO $db): array
    {
        $forecastRepo = new ForecastRepository($db);
        $service = new ForecastService($forecastRepo);
        $summary = [];
        foreach ($forecastRepo->activeMedicines() as $medicine) {
            $forecast = $service->forecastForMedicine((int) $medicine['id'], 2);
            $summary[] = [
                'medicine_name' => $medicine['name'],
                'predicted_quantity' => $forecast['predicted_quantity'],
                'forecast_month' => $forecast['forecast_month'],
            ];
        }
        return ['forecast_summary' => $summary];
    }
}
