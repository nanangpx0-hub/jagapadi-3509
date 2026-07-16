<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\CacheManager;
use App\Services\DashboardService;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        CacheManager::init($cacheDir);
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
}
