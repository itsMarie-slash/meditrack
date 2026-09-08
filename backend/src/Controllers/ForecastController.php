<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use Dompdf\Dompdf;
use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\ForecastRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Services\ForecastService;

final class ForecastController
{
    public static function index(Request $request): void
    {
        $monthsBack = max(1, min(12, (int) ($request->query['months_back'] ?? 2)));
        $db = Database::connection();
        $forecastRepo = new ForecastRepository($db);
        $service = new ForecastService($forecastRepo);

        if (!empty($request->query['medicine_id'])) {
            $medicineId = (int) $request->query['medicine_id'];
            $medicine = (new MedicineRepository($db))->find($medicineId);
            if (!$medicine) {
                Response::error('Medicine not found.', 404);
            }
            $forecast = $service->forecastForMedicine($medicineId, $monthsBack);
            $forecast['medicine_name'] = $medicine['name'];
            Response::success(['forecasts' => [$forecast]]);
        }

        $forecasts = [];
        foreach ($forecastRepo->activeMedicines() as $medicine) {
            $forecast = $service->forecastForMedicine((int) $medicine['id'], $monthsBack);
            $forecast['medicine_name'] = $medicine['name'];
            $forecasts[] = $forecast;
        }

        Response::success(['forecasts' => $forecasts]);
    }

    public static function export(Request $request): void
    {
        $monthsBack = max(1, min(12, (int) ($request->query['months_back'] ?? 2)));
        $db = Database::connection();
        $forecastRepo = new ForecastRepository($db);
        $service = new ForecastService($forecastRepo);

        $forecasts = [];
        foreach ($forecastRepo->activeMedicines() as $medicine) {
            $forecast = $service->forecastForMedicine((int) $medicine['id'], $monthsBack);
            $forecast['medicine_name'] = $medicine['name'];
            $forecasts[] = $forecast;
        }

        $html = '<h2>MediTrack — Demand Forecast Report</h2>';
        $html .= '<p>Generated: ' . htmlspecialchars(date('Y-m-d H:i')) . ' | Historical window: ' . $monthsBack . ' month(s)</p>';
        $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
        $html .= '<tr><th>Medicine</th><th>Forecast Month</th><th>Predicted Quantity</th></tr>';
        foreach ($forecasts as $forecast) {
            $html .= '<tr>'
                . '<td>' . htmlspecialchars($forecast['medicine_name']) . '</td>'
                . '<td>' . htmlspecialchars($forecast['forecast_month']) . '</td>'
                . '<td>' . (int) $forecast['predicted_quantity'] . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="demand-forecast.pdf"');
        echo $dompdf->output();
        exit;
    }
}
