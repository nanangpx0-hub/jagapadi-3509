<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SidebarStateTest extends TestCase
{
    #[DataProvider('routeProvider')]
    public function testRouteIsResolvedRelativeToApplicationBase(string $requestUri, string $expected): void
    {
        self::assertSame(
            $expected,
            SidebarState::routeFromRequest($requestUri, 'http://localhost/jagapadi-3509/')
        );
    }

    public static function routeProvider(): array
    {
        return [
            'submenu' => ['/jagapadi-3509/adminWilayah/kabupaten', 'adminWilayah/kabupaten'],
            'query string' => ['/jagapadi-3509/adminWilayah/desa?page=2', 'adminWilayah/desa'],
            'duplicate slash' => ['/jagapadi-3509//adminWilayah/kecamatan/', 'adminWilayah/kecamatan'],
            'application root' => ['/jagapadi-3509/', ''],
        ];
    }

    public function testParentRouteMatchesAllOfItsSubmenuPages(): void
    {
        self::assertTrue(SidebarState::matches('adminWilayah/kabupaten', 'adminWilayah'));
        self::assertTrue(SidebarState::matches('adminWilayah/kecamatan/edit/10', 'adminWilayah'));
        self::assertTrue(SidebarState::matches('ADMINWILAYAH/DESA', 'adminWilayah'));
    }

    public function testSiblingOrSimilarPrefixDoesNotActivateParent(): void
    {
        self::assertFalse(SidebarState::matches('dashboard', 'adminWilayah'));
        self::assertFalse(SidebarState::matches('adminWilayahBackup/kabupaten', 'adminWilayah'));
    }

    public function testChildRouteCanBeMatchedExactlyWhenRequired(): void
    {
        self::assertTrue(SidebarState::matches('adminWilayah/desa', 'adminWilayah/desa', false));
        self::assertFalse(SidebarState::matches('adminWilayah/desa/edit/1', 'adminWilayah/desa', false));
    }

    public function testDashboardRootDoesNotActivateMapOrChartsAndViceVersa(): void
    {
        self::assertTrue(SidebarState::matches('dashboard', 'dashboard', false));
        self::assertFalse(SidebarState::matches('dashboard/map', 'dashboard', false));
        self::assertTrue(SidebarState::matches('dashboard/map', 'dashboard/map'));
        self::assertFalse(SidebarState::matches('dashboard/charts', 'dashboard/map'));
    }

    public function testReportRoutesDoNotCollideOnSimilarPrefix(): void
    {
        self::assertTrue(SidebarState::matches('laporan/detail/10', 'laporan'));
        self::assertFalse(SidebarState::matches('laporan-lainnya', 'laporan'));
        self::assertTrue(SidebarState::matches('laporan-lainnya/detail/10', 'laporan-lainnya'));
    }
}
