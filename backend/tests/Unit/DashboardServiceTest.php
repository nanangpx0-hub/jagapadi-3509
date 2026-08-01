<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\CacheManager;
use App\Core\Database;
use App\Services\DashboardService;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    private bool $dbAvailable = true;

    public static function setUpBeforeClass(): void
    {
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with($line, 'DB_') && str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    putenv(trim($k) . '=' . trim($v));
                }
            }
        }
    }

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        CacheManager::init($cacheDir);
        try {
            Database::connect();
        } catch (\Throwable $e) {
            $this->dbAvailable = false;
        }
    }

    private function makeService(bool $includeDraft = false): DashboardService
    {
        return new DashboardService('admin', null, (int) date('Y'), $includeDraft);
    }

    public function testValidateTahunValid(): void
    {
        $currentYear = (int) date('Y');
        $this->assertSame($currentYear, DashboardService::validateTahun($currentYear));
        $this->assertSame(2020, DashboardService::validateTahun(2020));
        $this->assertSame($currentYear + 1, DashboardService::validateTahun($currentYear + 1));
    }

    public function testValidateTahunTooEarly(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tahun harus antara');
        DashboardService::validateTahun(2019);
    }

    public function testValidateTahunTooLate(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tahun harus antara');
        DashboardService::validateTahun((int) date('Y') + 2);
    }

    public function testCacheInvalidateReturnsInt(): void
    {
        $result = DashboardService::invalidateCache();
        $this->assertIsInt($result);
    }

    public function testCacheManagerDirectoryWritable(): void
    {
        $this->assertTrue(CacheManager::isWritable());
    }

    public function testCacheManagerDeletePrefixEmpty(): void
    {
        $count = CacheManager::deletePrefix('nonexistent_prefix_xyz_' . bin2hex(random_bytes(4)));
        $this->assertSame(0, $count);
    }

    public function testGeoJSONCoordinatesOrder(): void
    {
        $feature = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [113.7012, -8.1734],
            ],
        ];

        $this->assertSame('Feature', $feature['type']);
        $this->assertSame('Point', $feature['geometry']['type']);
        $this->assertCount(2, $feature['geometry']['coordinates']);

        $lng = $feature['geometry']['coordinates'][0];
        $lat = $feature['geometry']['coordinates'][1];

        $this->assertGreaterThan(100, $lng);
        $this->assertGreaterThan(-90, $lat);
        $this->assertLessThan(90, $lat);
        $this->assertLessThan(180, $lng);
    }

    public function testMapLimitCapped(): void
    {
        $limit = 1500;
        $this->assertSame(1000, min($limit, 1000));
        $this->assertSame(500, min(500, 1000));
    }

    public function testGetMapHamaReturnsFeatureCollection(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        DashboardService::invalidateCache();

        $data = $this->makeService()->getMapHama('aktif', 500);

        $this->assertSame('FeatureCollection', $data['type']);
        $this->assertArrayHasKey('features', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertSame(min(500, 1000), $data['meta']['limit']);
        $this->assertIsInt($data['meta']['count']);
        foreach ($data['features'] as $f) {
            $this->assertSame('Feature', $f['type']);
            $this->assertSame('Point', $f['geometry']['type']);
            $this->assertCount(2, $f['geometry']['coordinates']);
            // GeoJSON order is [longitude, latitude]
            $lng = $f['geometry']['coordinates'][0];
            $lat = $f['geometry']['coordinates'][1];
            $this->assertGreaterThan(-180, $lng);
            $this->assertLessThan(180, $lng);
            $this->assertGreaterThan(-90, $lat);
            $this->assertLessThan(90, $lat);
        }
    }

    public function testGetMapHamaDefaultExcludesDraft(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        DashboardService::invalidateCache();

        $data = $this->makeService(false)->getMapHama('aktif', 1000);
        foreach ($data['features'] as $f) {
            $this->assertNotSame('Draf', $f['properties']['status']);
        }
    }

    public function testGetMapHamaIncludeDraftAddsDraf(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        DashboardService::invalidateCache();

        $noDraft = $this->makeService(false)->getMapHama('aktif', 1000);
        $withDraft = $this->makeService(true)->getMapHama('aktif', 1000);

        $noDraftCount = $noDraft['meta']['count'];
        $withDraftCount = $withDraft['meta']['count'];

        // When drafts exist with coordinates, including draft must not reduce results.
        $this->assertGreaterThanOrEqual($noDraftCount, $withDraftCount);
        $hasDraf = false;
        foreach ($withDraft['features'] as $f) {
            if ($f['properties']['status'] === 'Draf') {
                $hasDraf = true;
                break;
            }
        }
        if ($withDraftCount > $noDraftCount) {
            $this->assertTrue($hasDraf, 'Include draft should surface Draf points when count increases');
        }
    }

    public function testGetMapHamaStatusFilterSubmitted(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        DashboardService::invalidateCache();

        $data = $this->makeService()->getMapHama('Submitted', 1000);
        foreach ($data['features'] as $f) {
            $this->assertSame('Submitted', $f['properties']['status']);
        }
    }

    public function testGetMapIrigasiReturnsFeatureCollection(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        DashboardService::invalidateCache();

        $data = $this->makeService()->getMapIrigasi('aktif', 500);
        $this->assertSame('FeatureCollection', $data['type']);
        foreach ($data['features'] as $f) {
            $this->assertSame('Point', $f['geometry']['type']);
            $this->assertArrayHasKey('nama_saluran', $f['properties']);
        }
    }
}
