<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit;

use MediTrack\Services\ForecastService;
use PHPUnit\Framework\TestCase;

final class ForecastServiceTest extends TestCase
{
    public function testEmptyHistoryForecastsZero(): void
    {
        $this->assertSame(0, ForecastService::exponentialSmoothing([]));
    }

    public function testSingleMonthForecastsThatMonth(): void
    {
        $this->assertSame(100, ForecastService::exponentialSmoothing([100]));
    }

    public function testWeightsRecentMonthMoreHeavily(): void
    {
        // A late spike should pull the forecast above the plain average (150),
        // proving recent months carry more weight than older ones.
        $forecast = ForecastService::exponentialSmoothing([100, 100, 250]);
        $this->assertGreaterThan(150, $forecast);
    }

    public function testFlatHistoryForecastsTheSameValue(): void
    {
        $this->assertSame(80, ForecastService::exponentialSmoothing([80, 80, 80, 80]));
    }
}
