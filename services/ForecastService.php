<?php
/**
 * ForecastService
 * ================
 * Predicts how much of a medicine will be needed for the NEXT distribution
 * cycle, based only on this system's own dispensed-distribution history
 * (requirement 4.5). No external data sources or ML libraries are used -
 * this is deliberately a simple, explainable statistical method suitable
 * for a capstone defense: Simple Exponential Smoothing (SES), with a
 * moving-average fallback when there isn't enough history yet.
 *
 * IMPORTANT LIMITATION (documented per project scope): forecast quality
 * depends entirely on how much accurate distribution data BHWs have
 * already entered into the system. A medicine with only one or two past
 * distribution cycles will produce a low-confidence forecast.
 *
 * calculateForecast() is meant to be called automatically right after a
 * distribution is marked "Dispensed" (see models/Distribution.php ->
 * recordDistributionDispensed(), wired up in bhw/distribution_dispense.php),
 * not only from a manual "recompute" button.
 */

require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../models/Forecast.php';
require_once __DIR__ . '/../models/Medicine.php';

class ForecastService
{
    /** How many past distribution cycles (scheduled_date groups) to look at. */
    private const CYCLES_TO_CONSIDER = 6;

    /** Smoothing factor for SES: higher = more weight on recent cycles. */
    private const SMOOTHING_ALPHA = 0.4;

    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Recompute the forecast for one medicine and persist it to
     * forecast_results. Returns the forecast row that was saved, or null
     * if there isn't enough distribution history yet to forecast from.
     */
    public function calculateForecast(int $medicineId): ?array
    {
        $history = getDispensedHistoryByMedicine($this->conn, $medicineId, self::CYCLES_TO_CONSIDER);

        if (count($history) === 0) {
            return null; // no dispensed distributions recorded yet - nothing to forecast from
        }

        $quantities = array_map(fn($row) => (float) $row['total_quantity'], $history);

        if (count($quantities) === 1) {
            // Not enough history for smoothing yet - use the single known cycle as-is.
            $predicted = $quantities[0];
            $method = 'Insufficient history (single cycle carried forward)';
        } else {
            $predicted = $this->simpleExponentialSmoothing($quantities);
            $method = 'Simple Exponential Smoothing (alpha=' . self::SMOOTHING_ALPHA . ', ' . count($quantities) . ' cycles)';
        }

        $predicted = round($predicted, 2);
        $period = 'Next cycle after ' . date('M Y'); // human-readable label for the upcoming period

        $forecastId = saveForecastResult($this->conn, $medicineId, $period, $predicted, $method);

        return [
            'id' => $forecastId,
            'medicine_id' => $medicineId,
            'forecast_period' => $period,
            'predicted_quantity' => $predicted,
            'method_used' => $method,
        ];
    }

    /** Recompute forecasts for every active medicine (used by a "recompute all" admin action). */
    public function calculateForecastForAllMedicines(): array
    {
        $results = [];
        foreach (getAllMedicines($this->conn) as $medicine) {
            $result = $this->calculateForecast((int) $medicine['id']);
            if ($result !== null) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * Simple Exponential Smoothing.
     *
     * The forecast for the next period is a weighted average of all past
     * actual values, where more recent values get exponentially more
     * weight. This adapts faster to recent trends than a flat moving
     * average, while still smoothing out one-off spikes/dips - a good
     * middle ground for irregular barangay distribution schedules.
     *
     * Formula, applied iteratively over the ordered history A_1..A_n:
     *   F_1 = A_1                              (seed the smoothed series with the first actual)
     *   F_t = alpha * A_(t-1) + (1 - alpha) * F_(t-1)   for t = 2..n
     * The forecast for the NEXT (unseen) period is then:
     *   F_(n+1) = alpha * A_n + (1 - alpha) * F_n
     */
    private function simpleExponentialSmoothing(array $actuals): float
    {
        $alpha = self::SMOOTHING_ALPHA;
        $forecast = $actuals[0]; // F_1 = A_1

        for ($t = 1; $t < count($actuals); $t++) {
            $forecast = $alpha * $actuals[$t - 1] + (1 - $alpha) * $forecast;
        }

        // One more step projects the forecast into the next, not-yet-observed cycle.
        $lastActual = $actuals[count($actuals) - 1];
        $nextForecast = $alpha * $lastActual + (1 - $alpha) * $forecast;

        return $nextForecast;
    }

    /**
     * Plain moving average over the last N cycles, kept as a simpler
     * alternative method for comparison/reporting purposes.
     */
    public function movingAverage(array $actuals): float
    {
        if (count($actuals) === 0) {
            return 0.0;
        }
        return array_sum($actuals) / count($actuals);
    }
}
