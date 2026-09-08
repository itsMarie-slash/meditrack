<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use Dompdf\Dompdf;
use MediTrack\Config\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\DistributionRepository;
use MediTrack\Repositories\MedicineRepository;

final class ReportController
{
    public static function inventory(Request $request): void
    {
        $repo = new MedicineRepository(Database::connection());
        $result = $repo->paginate([
            'search' => $request->query['search'] ?? null,
            'category' => $request->query['category'] ?? null,
            'low_stock_only' => $request->query['low_stock_only'] ?? null,
        ], $request->paginationLimit(), $request->paginationOffset());

        Response::success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $request->paginationPage(),
            'per_page' => $request->paginationLimit(),
        ]);
    }

    public static function distribution(Request $request): void
    {
        $repo = new DistributionRepository(Database::connection());
        $result = $repo->paginate([
            'date_from' => $request->query['date_from'] ?? null,
            'date_to' => $request->query['date_to'] ?? null,
            'status' => $request->query['status'] ?? null,
        ], $request->paginationLimit(), $request->paginationOffset());

        Response::success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $request->paginationPage(),
            'per_page' => $request->paginationLimit(),
        ]);
    }

    /**
     * Export scope (per docs/02-architecture.md §5): BHW may export any
     * report type; Midwife is view-only and may not export; IPHO may only
     * export the forecast report (via ForecastController::export instead —
     * this endpoint covers inventory/distribution).
     */
    public static function export(Request $request): void
    {
        $type = $request->params['type'];
        if (!in_array($type, ['inventory', 'distribution'], true)) {
            Response::error('Unknown report type.', 404);
        }
        if ($request->user['role'] !== 'bhw') {
            Response::error('You are not authorized to export this report.', 403);
        }

        $format = $request->query['format'] ?? 'csv';
        $db = Database::connection();

        if ($type === 'inventory') {
            $rows = (new MedicineRepository($db))->paginate([], 10000, 0)['items'];
            $headers = ['Name', 'Category', 'Stock Qty', 'Unit', 'Low Stock', 'Nearest Expiration'];
            $mapRow = static fn(array $r) => [$r['name'], $r['category'], $r['stock_quantity'], $r['unit'], $r['is_low_stock'] ? 'Yes' : 'No', $r['nearest_expiration'] ?? ''];
        } else {
            $rows = (new DistributionRepository($db))->paginate([
                'date_from' => $request->query['date_from'] ?? null,
                'date_to' => $request->query['date_to'] ?? null,
            ], 10000, 0)['items'];
            $headers = ['Date', 'Beneficiary', 'Medicine', 'Quantity', 'Receiver', 'BHW', 'Status'];
            $mapRow = static fn(array $r) => [$r['date_released'], $r['senior_citizen_name'], $r['medicine_name'], $r['quantity_issued'], $r['receiver_name'], $r['bhw_name'], $r['status']];
        }

        if ($format === 'csv') {
            self::streamCsv("{$type}-report.csv", $headers, array_map($mapRow, $rows));
        }

        self::streamPdf("{$type}-report.pdf", ucfirst($type) . ' Report', $headers, array_map($mapRow, $rows));
    }

    /** @param list<string> $headers @param list<list<scalar>> $rows */
    private static function streamCsv(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    /** @param list<string> $headers @param list<list<scalar>> $rows */
    private static function streamPdf(string $filename, string $title, array $headers, array $rows): never
    {
        $html = '<h2>MediTrack — ' . htmlspecialchars($title) . '</h2>';
        $html .= '<p>Generated: ' . htmlspecialchars(date('Y-m-d H:i')) . '</p>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%"><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars((string) $cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo $dompdf->output();
        exit;
    }
}
