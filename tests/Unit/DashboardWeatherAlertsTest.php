<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DashboardWeatherAlertsTest extends TestCase
{
    public function testChartsDoesNotRenderOrRequestWeatherAlerts(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/dashboard/charts.php');

        self::assertStringNotContainsString('Peringatan Cuaca', $view);
        self::assertStringNotContainsString('<div class="label">Peringatan Cuaca</div>', $view);
        self::assertStringNotContainsString("api/dashboard/charts/weather?days=7", $view);
    }

    public function testWeatherSummaryExposesCountAndFreshnessPeriod(): void
    {
        $service = file_get_contents(__DIR__ . '/../../app/services/DashboardDataAggregator.php');

        self::assertStringContainsString("'alert_count' => count(\$alerts)", $service);
        self::assertStringContainsString("'alert_period' => \$period", $service);
        self::assertStringContainsString('DATE_SUB(CURDATE(), INTERVAL :days DAY)', $service);
        self::assertStringContainsString("'is_stale' =>", $service);
    }

    public function testWeatherEndpointAcceptsRollingPeriod(): void
    {
        $controller = file_get_contents(
            __DIR__ . '/../../app/controllers/Api/DashboardChartsApiController.php'
        );

        self::assertStringContainsString("'days' => \$_GET['days'] ?? 7", $controller);
    }
}
