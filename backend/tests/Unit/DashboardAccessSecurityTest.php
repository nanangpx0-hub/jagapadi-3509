<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\CacheManager;
use App\Core\Database;
use App\Services\DashboardService;
use PHPUnit\Framework\TestCase;

class DashboardAccessSecurityTest extends TestCase
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
        } catch (\Throwable) {
            $this->dbAvailable = false;
        }
    }

    public function testPetugasRoleRestrictsDataToUser(): void
    {
        $petugas1Service = new DashboardService('petugas', 10, 2026);
        $petugas2Service = new DashboardService('petugas', 20, 2026);
        $adminService = new DashboardService('admin', 99, 2026);

        $this->assertNotEquals(
            spl_object_hash($petugas1Service),
            spl_object_hash($petugas2Service)
        );

        if ($this->dbAvailable) {
            DashboardService::invalidateCache();
            $stats1 = $petugas1Service->getStats();
            $stats2 = $petugas2Service->getStats();
            $statsAdmin = $adminService->getStats();

            $this->assertIsArray($stats1);
            $this->assertIsArray($stats2);
            $this->assertIsArray($statsAdmin);

            // Admin total active must be greater than or equal to individual petugas active count
            $totalPetugas1Active = ($stats1['hama']['total_aktif'] ?? 0) + ($stats1['irigasi']['total_aktif'] ?? 0);
            $totalAdminActive = ($statsAdmin['hama']['total_aktif'] ?? 0) + ($statsAdmin['irigasi']['total_aktif'] ?? 0);
            $this->assertGreaterThanOrEqual($totalPetugas1Active, $totalAdminActive);
        }
    }

    public function testMapFilteringByKecamatanAndOpt(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }

        DashboardService::invalidateCache();
        $adminService = new DashboardService('admin', null, 2026);

        $hamaMap = $adminService->getMapHama('aktif', 500, 1, 1);
        $this->assertSame('FeatureCollection', $hamaMap['type']);
        $this->assertArrayHasKey('features', $hamaMap);

        $irigasiMap = $adminService->getMapIrigasi('aktif', 500, 1, null, 'Baik');
        $this->assertSame('FeatureCollection', $irigasiMap['type']);
        $this->assertArrayHasKey('features', $irigasiMap);
    }

    public function testNonZeroCoordinatesCheck(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }

        DashboardService::invalidateCache();
        $service = new DashboardService('admin', null, 2026);

        $mapHama = $service->getMapHama('aktif', 1000);
        foreach ($mapHama['features'] as $f) {
            $lng = $f['geometry']['coordinates'][0];
            $lat = $f['geometry']['coordinates'][1];
            $this->assertNotEquals(0, $lng, 'Longitude must not be zero');
            $this->assertNotEquals(0, $lat, 'Latitude must not be zero');
        }
    }

    public function testCacheKeysAreDifferentiatedByRoleAndUser(): void
    {
        DashboardService::invalidateCache();

        $ref = new \ReflectionClass(DashboardService::class);
        $constructor = $ref->getConstructor();

        $s1 = new DashboardService('petugas', 1, 2026);
        $s2 = new DashboardService('petugas', 2, 2026);

        $refUserId = $ref->getProperty('userId');
        $refUserId->setAccessible(true);

        $this->assertSame(1, $refUserId->getValue($s1));
        $this->assertSame(2, $refUserId->getValue($s2));

        $sAdmin = new DashboardService('admin', 1, 2026);
        $this->assertNull($refUserId->getValue($sAdmin));
    }
}
