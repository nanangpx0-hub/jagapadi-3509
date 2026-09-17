<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PestDataCollectorTest extends TestCase
{
    private PestDataCollectorService $service;

    protected function setUp(): void
    {
        require_once ROOT_PATH . '/app/services/PestDataCollectorService.php';
        $this->service = new PestDataCollectorService();
    }

    public function testSanitizeTemperatureClampsOutliers(): void
    {
        // Outlier ekstrem di atas 50°C dikembalikan ke default baseline
        self::assertSame(PestDataCollectorService::DEFAULT_TEMP, $this->service->sanitizeTemperature(88.0));
        // Outlier di bawah 10°C dikembalikan ke default baseline
        self::assertSame(PestDataCollectorService::DEFAULT_TEMP, $this->service->sanitizeTemperature(5.0));
        // Null dikembalikan ke default baseline
        self::assertSame(PestDataCollectorService::DEFAULT_TEMP, $this->service->sanitizeTemperature(null));
        // Nilai valid tetap dipertahankan
        self::assertSame(28.45, $this->service->sanitizeTemperature(28.452));
    }

    public function testSanitizeHumidityClampsOutliers(): void
    {
        self::assertSame(PestDataCollectorService::DEFAULT_HUMIDITY, $this->service->sanitizeHumidity(120.0));
        self::assertSame(PestDataCollectorService::DEFAULT_HUMIDITY, $this->service->sanitizeHumidity(10.0));
        self::assertSame(PestDataCollectorService::DEFAULT_HUMIDITY, $this->service->sanitizeHumidity(null));
        self::assertSame(85.5, $this->service->sanitizeHumidity(85.5));
    }

    public function testSanitizeRainfallHandlesNegativeAndExtreme(): void
    {
        self::assertSame(0.0, $this->service->sanitizeRainfall(-15.0));
        self::assertSame(0.0, $this->service->sanitizeRainfall(null));
        self::assertSame(600.0, $this->service->sanitizeRainfall(999.0));
        self::assertSame(45.2, $this->service->sanitizeRainfall(45.2));
    }

    public function testSanitizeWindSpeedHandlesBoundaries(): void
    {
        self::assertSame(PestDataCollectorService::DEFAULT_WIND_SPEED, $this->service->sanitizeWindSpeed(-5.0));
        self::assertSame(PestDataCollectorService::DEFAULT_WIND_SPEED, $this->service->sanitizeWindSpeed(null));
        self::assertSame(120.0, $this->service->sanitizeWindSpeed(150.0));
        self::assertSame(14.2, $this->service->sanitizeWindSpeed(14.2));
    }

    public function testImputeMissingWeatherDataProvidesValidProfile(): void
    {
        $imputed = $this->service->imputeMissingWeatherData(99999, 7);

        self::assertTrue($imputed['is_imputed']);
        self::assertArrayHasKey('current', $imputed);
        self::assertArrayHasKey('averages', $imputed);
        self::assertGreaterThanOrEqual(15.0, $imputed['averages']['suhu']);
        self::assertGreaterThanOrEqual(30.0, $imputed['averages']['kelembaban']);
    }

    public function testGetPestHistoryStructure(): void
    {
        $history = $this->service->getPestHistory(1, null, 30);

        self::assertArrayHasKey('total_reports', $history);
        self::assertArrayHasKey('total_luas_ha', $history);
        self::assertArrayHasKey('max_populasi', $history);
        self::assertArrayHasKey('keparahan_breakdown', $history);
    }
}
