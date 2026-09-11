<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ScraperJobService;
use PHPUnit\Framework\TestCase;

/**
 * Murni in-memory/file-temp: tanpa socket database, tanpa network
 * (selalu force_simulation). Mengunci kontrak worker P1.
 */
final class ScraperJobServiceTest extends TestCase
{
    private string $cacheDir;
    private ScraperJobService $service;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/jagapadi-scraper-test-' . bin2hex(random_bytes(4));
        @mkdir($this->cacheDir, 0755, true);
        $this->service = new ScraperJobService($this->cacheDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->cacheDir);
    }

    public function testNasaWindForcedSimulationIsDeterministic(): void
    {
        $params = ['year' => 2026, 'month' => 1, 'force_simulation' => true];
        $first = $this->service->handleNasaWind($params);
        $second = $this->service->handleNasaWind($params);

        self::assertTrue($first['success']);
        self::assertTrue($first['fallback_used']);
        self::assertTrue($first['stale']);
        self::assertSame('Simulasi', $first['source']);
        self::assertSame($first['records'], $second['records'], 'Fallback harus deterministik');
        self::assertGreaterThan(0, $first['records_count']);
    }

    public function testBpsSyncFallsBackToSimulation(): void
    {
        $result = $this->service->handleBpsSync(['year' => 2025, 'force_simulation' => true]);
        self::assertTrue($result['success']);
        self::assertTrue($result['fallback_used']);
        self::assertSame(2, $result['records_count']);
    }

    public function testDispatchRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->dispatch(['type' => 'unknown_source']);
    }

    public function testDispatchWritesLastSuccessFile(): void
    {
        $result = $this->service->dispatch(
            ['type' => ScraperJobService::TYPE_NASA_WIND, 'year' => 2026, 'month' => 2, 'force_simulation' => true]
        );
        self::assertTrue($result['success']);
        $file = $this->cacheDir . '/scraper_nasa_wind_last_success.json';
        self::assertFileExists($file);
        $payload = json_decode((string) file_get_contents($file), true);
        self::assertTrue($payload['success']);
        self::assertTrue($payload['fallback_used']);
        self::assertTrue($payload['stale']);
        self::assertSame($result['records_count'], $payload['records_count']);
    }

    public function testDispatchRejectsInvalidPeriod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->dispatch(
            ['type' => ScraperJobService::TYPE_NASA_WIND, 'year' => 1999, 'month' => 13, 'force_simulation' => true]
        );
    }
}
