<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Repositories\ForecastRepository;

/**
 * Demand forecasting (FR-11/FR-12). Uses exponential smoothing over the
 * requested lookback window of completed months — simple and explainable
 * to non-technical health workers (see docs/01-requirements-analysis.md
 * §13.7), while still weighting recent months more heavily than a plain
 * average.
 */
final class ForecastService
{
    private const SMOOTHING_ALPHA = 0.5;

    public function __construct(private readonly ForecastRepository $repository)
    {
    }

    /**
     * @return array{medicine_id:int,historical:list<array{month:string,total_quantity:int}>,
     *               predicted_quantity:int,forecast_month:string,window_months:int}
     */
    public function forecastForMedicine(int $medicineId, int $monthsBack): array
    {
        $history = $this->repository->monthlyTotals($medicineId, $monthsBack);
        $predicted = self::exponentialSmoothing(array_column($history, 'total_quantity'));
        $forecastMonth = (new \DateTimeImmutable('first day of next month'))->format('Y-m-d');

        $this->repository->upsertForecast($medicineId, $forecastMonth, $predicted, $monthsBack);

        return [
            'medicine_id' => $medicineId,
            'historical' => $history,
            'predicted_quantity' => $predicted,
            'forecast_month' => $forecastMonth,
            'window_months' => $monthsBack,
        ];
    }

    /** @param list<int> $values chronological order (oldest first) */
    public static function exponentialSmoothing(array $values): int
    {
        if ($values === []) {
            return 0;
        }
        if (count($values) === 1) {
            return $values[0];
        }

        $smoothed = (float) $values[0];
        foreach (array_slice($values, 1) as $value) {
            $smoothed = self::SMOOTHING_ALPHA * $value + (1 - self::SMOOTHING_ALPHA) * $smoothed;
        }

        return (int) round($smoothed);
    }
}
