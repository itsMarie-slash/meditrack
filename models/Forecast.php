<?php
/**
 * forecast_results table access - stores every forecast run so accuracy
 * can be reviewed historically (see services/ForecastService.php for the
 * actual math).
 */

function saveForecastResult(mysqli $conn, int $medicineId, string $forecastPeriod, float $predictedQuantity, string $methodUsed): int
{
    $sql = 'INSERT INTO forecast_results (medicine_id, forecast_period, predicted_quantity, method_used)
            VALUES (?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isds', $medicineId, $forecastPeriod, $predictedQuantity, $methodUsed);
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $newId;
}

/** Most recent forecast per medicine (one row each), for the dashboard table. */
function getLatestForecastPerMedicine(mysqli $conn): array
{
    $sql = 'SELECT fr1.*, m.name AS medicine_name, m.unit, m.current_stock
            FROM forecast_results fr1
            JOIN medicines m ON m.id = fr1.medicine_id
            INNER JOIN (
                SELECT medicine_id, MAX(generated_at) AS max_gen
                FROM forecast_results
                GROUP BY medicine_id
            ) latest ON latest.medicine_id = fr1.medicine_id AND latest.max_gen = fr1.generated_at
            ORDER BY m.name';
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/** Forecast history for one medicine, oldest first, for the Chart.js line chart. */
function getForecastHistoryByMedicine(mysqli $conn, int $medicineId, int $limit = 12): array
{
    $sql = 'SELECT * FROM forecast_results WHERE medicine_id = ? ORDER BY generated_at DESC LIMIT ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $medicineId, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array_reverse($rows);
}
