<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class ForecastRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Completed-month distribution totals for one medicine, most recent
     * first, limited to the requested lookback window (current, in-progress
     * month excluded so partial data doesn't skew the average).
     *
     * @return list<array{month:string,total_quantity:int}>
     */
    public function monthlyTotals(int $medicineId, int $monthsBack): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(d.date_released, '%Y-%m-01') AS month, SUM(d.quantity_issued) AS total_quantity
             FROM distributions d
             JOIN medicine_batches mb ON mb.id = d.medicine_batch_id
             WHERE mb.medicine_id = :medicine_id
               AND d.status = 'completed'
               AND d.date_released >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL :months MONTH)
               AND d.date_released < DATE_FORMAT(CURDATE(), '%Y-%m-01')
             GROUP BY month
             ORDER BY month ASC"
        );
        $stmt->bindValue('medicine_id', $medicineId, PDO::PARAM_INT);
        $stmt->bindValue('months', $monthsBack, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn(array $row) => ['month' => $row['month'], 'total_quantity' => (int) $row['total_quantity']],
            $stmt->fetchAll()
        );
    }

    public function upsertForecast(int $medicineId, string $forecastMonth, int $predictedQuantity, int $windowMonths): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO demand_forecasts (medicine_id, forecast_month, predicted_quantity, historical_window_months)
             VALUES (:medicine_id, :forecast_month, :predicted_quantity1, :window_months1)
             ON DUPLICATE KEY UPDATE predicted_quantity = :predicted_quantity2, historical_window_months = :window_months2, generated_at = NOW()'
        );
        $stmt->execute([
            'medicine_id' => $medicineId,
            'forecast_month' => $forecastMonth,
            'predicted_quantity1' => $predictedQuantity,
            'predicted_quantity2' => $predictedQuantity,
            'window_months1' => $windowMonths,
            'window_months2' => $windowMonths,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function activeMedicines(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM medicines WHERE is_active = 1 ORDER BY name');
        return $stmt->fetchAll();
    }
}
