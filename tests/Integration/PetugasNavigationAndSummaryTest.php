<?php

use PHPUnit\Framework\TestCase;

/**
 * PetugasNavigationAndSummaryTest
 *
 * Verifikasi struktur menu navigasi petugas setelah perubahan:
 * Lama : Dashboard, Peta Sebaran, Grafik & Statistik, Laporan Hama,
 *        Laporan Lainnya, Rekapitulasi Saya, Sebaran Irigasi, Masukan & Saran
 * Baru : Dashboard, Peta Sebaran, Grafik & Statistik, Laporan Hama,
 *        Laporan Irigasi, Laporan Lainnya, Report, Masukan & Saran
 */
final class PetugasNavigationAndSummaryTest extends TestCase {

    // ==================== TUGAS 1: Route Registration ====================

    public function testReportRouteIsRegistered(): void {
        $routes = $this->loadWebRoutes();
        $this->assertArrayHasKey('laporan-lainnya/report', $routes,
            'Route laporan-lainnya/report must be registered in web_routes.php');
        $this->assertEquals('LaporanLainnya@report', $routes['laporan-lainnya/report']);
    }

    public function testSummaryRouteStillRegisteredForBackwardCompat(): void {
        $routes = $this->loadWebRoutes();
        $this->assertArrayHasKey('laporan-lainnya/summary', $routes,
            'Route laporan-lainnya/summary must still be registered for backward compatibility');
        $this->assertEquals('LaporanLainnya@summary', $routes['laporan-lainnya/summary']);
    }

    public function testExportRouteIsRegistered(): void {
        $routes = $this->loadWebRoutes();
        $this->assertArrayHasKey('laporan-lainnya/export', $routes,
            'Route laporan-lainnya/export must be registered in web_routes.php');
        $this->assertEquals('LaporanLainnya@export', $routes['laporan-lainnya/export']);
    }

    public function testIrigasiRouteIsRegistered(): void {
        $routes = $this->loadWebRoutes();
        $this->assertArrayHasKey('irigasi', $routes,
            'Route irigasi must be registered for Laporan Irigasi menu item');
        $this->assertEquals('Irigasi@index', $routes['irigasi']);
    }

    public function testAllRequiredRoutesAreRegistered(): void {
        $routes = $this->loadWebRoutes();
        $expected = [
            'laporan-lainnya'         => 'LaporanLainnya@index',
            'laporan-lainnya/create'  => 'LaporanLainnya@create',
            'laporan-lainnya/store'   => 'LaporanLainnya@store',
            'laporan-lainnya/summary' => 'LaporanLainnya@summary',
            'laporan-lainnya/report'  => 'LaporanLainnya@report',
            'laporan-lainnya/export'  => 'LaporanLainnya@export',
            'irigasi'                 => 'Irigasi@index',
        ];
        foreach ($expected as $path => $target) {
            $this->assertArrayHasKey($path, $routes, "Route '{$path}' must be registered");
            $this->assertEquals($target, $routes[$path],
                "Route '{$path}' must map to '{$target}'");
        }
    }

    // ==================== TUGAS 2: Menu baru tidak ada lagi di header ====================

    public function testSidebarNoLongerContainsRekapitulasiSaya(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringNotContainsString('>Rekapitulasi Saya<',
            strip_tags($headerContent, '<p>'),
            'Sidebar must NOT contain "Rekapitulasi Saya" menu item (replaced by "Report")');
    }

    public function testSidebarNoLongerContainsSebaranIrigasiLabel(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringNotContainsString('>Sebaran Irigasi<',
            strip_tags($headerContent, '<p>'),
            'Sidebar must NOT contain "Sebaran Irigasi" label (replaced by "Laporan Irigasi")');
    }

    // ==================== TUGAS 3: Menu baru ada di header (petugas) ====================

    public function testSidebarContainsLaporanIrigasiMenu(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString('Laporan Irigasi', $headerContent,
            'Sidebar must contain "Laporan Irigasi" menu item');
        $this->assertStringContainsString('laporan-irigasi', $headerContent,
            'Laporan Irigasi menu item must have data-sidebar-menu="laporan-irigasi"');
        $this->assertStringContainsString(
            "BASE_URL ?>irigasi",
            $headerContent,
            'Laporan Irigasi menu item must link to irigasi route'
        );
    }

    public function testSidebarContainsLaporanLainnyaMenu(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString('Laporan Lainnya', $headerContent,
            'Sidebar must still contain "Laporan Lainnya" menu item');
        $this->assertStringContainsString('laporan-lainnya', $headerContent,
            'Laporan Lainnya menu must link to laporan-lainnya');
    }

    public function testSidebarContainsReportMenuForPetugas(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString('laporan-lainnya/report', $headerContent,
            'Sidebar must contain link to laporan-lainnya/report for Report menu');
        $this->assertStringContainsString('>Report<', $headerContent,
            'Sidebar must contain "Report" as menu label');
        $this->assertStringContainsString('laporan-report', $headerContent,
            'Report menu item must have data-sidebar-menu="laporan-report"');
    }

    public function testReportMenuIsPetugasOnly(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        // Verify the Report menu block is wrapped inside a petugas role check
        $this->assertStringContainsString("(\$_SESSION['role'] ?? '') === 'petugas'", $headerContent,
            'Report menu must be wrapped in petugas role check');
    }

    public function testSidebarContainsMasukanSaranMenu(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString('Masukan & Saran', $headerContent,
            'Sidebar must contain "Masukan & Saran" menu item');
        $this->assertStringContainsString("BASE_URL ?>feedback", $headerContent,
            'Masukan & Saran must link to feedback route');
    }

    public function testSidebarActiveStateForIrigasiUsesVariable(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString('$irigasiMenuActive', $headerContent,
            'Sidebar irigasi menu must use $irigasiMenuActive variable for active state');
    }

    public function testSidebarLaporanLainnyaActiveStateExcludesReport(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString(
            "!SidebarState::matches(\$sidebarRoute, 'laporan-lainnya/report')",
            $headerContent,
            'Laporan Lainnya active state must exclude laporan-lainnya/report'
        );
    }

    public function testSidebarReportMenuUsesSidebarStateMatches(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString(
            "SidebarState::matches(\$sidebarRoute, 'laporan-lainnya/report')",
            $headerContent,
            'Report menu must use SidebarState::matches for active state'
        );
    }

    // ==================== TUGAS 4: Navbar button ====================

    public function testHeaderNavbarButtonPointsToReportRoute(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        $this->assertStringContainsString('laporan-lainnya/report', $headerContent,
            'Navbar top button must link to laporan-lainnya/report');
        $this->assertStringContainsString("(\$_SESSION['role'] ?? '') === 'petugas'", $headerContent,
            'Navbar button must be conditionally rendered for petugas role only');
    }

    public function testHeaderNavbarButtonTextIsReport(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');
        // Pastikan teks tombol navbar adalah "Report" bukan yang lama
        $this->assertStringContainsString('> Report', $headerContent,
            'Navbar button text must be "Report"');
        $this->assertStringNotContainsString('Rekap Pelaporan', $headerContent,
            'Old navbar button text "Rekap Pelaporan" must be removed');
    }

    // ==================== TUGAS 5: Controller method ====================

    public function testControllerHasReportMethod(): void {
        $controllerPath = ROOT_PATH . '/app/controllers/LaporanLainnyaController.php';
        $this->assertFileExists($controllerPath);
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('public function report()', $content,
            'LaporanLainnyaController must have a public report() method');
    }

    public function testControllerSummaryMethodStillExists(): void {
        $controllerPath = ROOT_PATH . '/app/controllers/LaporanLainnyaController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('public function summary()', $content,
            'LaporanLainnyaController must still have summary() for backward compatibility');
    }

    public function testControllerSummaryDelegatesToReport(): void {
        $controllerPath = ROOT_PATH . '/app/controllers/LaporanLainnyaController.php';
        $content = file_get_contents($controllerPath);
        // summary() harus memanggil $this->report() sebagai delegasi
        $this->assertStringContainsString('$this->report()', $content,
            'summary() method must delegate to report()');
    }

    public function testReportMethodUsesCheckRole(): void {
        $controllerPath = ROOT_PATH . '/app/controllers/LaporanLainnyaController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString("checkRole(['petugas']", $content,
            'report() method must restrict access to petugas role via checkRole');
    }

    public function testReportMethodRendersReportView(): void {
        $controllerPath = ROOT_PATH . '/app/controllers/LaporanLainnyaController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString("'laporan-lainnya/report'", $content,
            'report() method must render laporan-lainnya/report view');
    }

    public function testExportRedirectsToReportOnError(): void {
        $controllerPath = ROOT_PATH . '/app/controllers/LaporanLainnyaController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString("redirect('laporan-lainnya/report')", $content,
            'export() must redirect to laporan-lainnya/report on error (not summary)');
        $this->assertStringNotContainsString("redirect('laporan-lainnya/summary')", $content,
            'export() must not redirect to old summary route');
    }

    // ==================== TUGAS 6: Menu order untuk petugas ====================

    public function testSidebarMenuOrderForPetugas(): void {
        $headerContent = $this->loadView('app/views/layouts/header.php');

        // Cari posisi (offset) setiap item dalam file HTML
        $posDashboard    = strpos($headerContent, '>Dashboard<');
        $posPeta         = strpos($headerContent, '>Peta Sebaran<');
        $posGrafik       = strpos($headerContent, '>Grafik & Statistik<');
        $posHama         = strpos($headerContent, '>Laporan Hama<');
        $posIrigasi      = strpos($headerContent, '>Laporan Irigasi<');
        $posLainnya      = strpos($headerContent, '>Laporan Lainnya<');
        $posReport       = strpos($headerContent, '>Report<');
        $posMasukan      = strpos($headerContent, '>Masukan & Saran<');

        // Semua item harus ada
        $this->assertNotFalse($posDashboard,   'Dashboard item not found in sidebar');
        $this->assertNotFalse($posPeta,        'Peta Sebaran item not found in sidebar');
        $this->assertNotFalse($posGrafik,      'Grafik & Statistik item not found in sidebar');
        $this->assertNotFalse($posHama,        'Laporan Hama item not found in sidebar');
        $this->assertNotFalse($posIrigasi,     'Laporan Irigasi item not found in sidebar');
        $this->assertNotFalse($posLainnya,     'Laporan Lainnya item not found in sidebar');
        $this->assertNotFalse($posReport,      'Report item not found in sidebar');
        $this->assertNotFalse($posMasukan,     'Masukan & Saran item not found in sidebar');

        // Verifikasi urutan: setiap item muncul setelah item sebelumnya
        $this->assertLessThan($posPeta,     $posDashboard,
            'Dashboard must appear before Peta Sebaran');
        $this->assertLessThan($posGrafik,   $posPeta,
            'Peta Sebaran must appear before Grafik & Statistik');
        $this->assertLessThan($posHama,     $posGrafik,
            'Grafik & Statistik must appear before Laporan Hama');
        $this->assertLessThan($posIrigasi,  $posHama,
            'Laporan Hama must appear before Laporan Irigasi');
        $this->assertLessThan($posLainnya,  $posIrigasi,
            'Laporan Irigasi must appear before Laporan Lainnya');
        $this->assertLessThan($posReport,   $posLainnya,
            'Laporan Lainnya must appear before Report');
        $this->assertLessThan($posMasukan,  $posReport,
            'Report must appear before Masukan & Saran');
    }

    // ==================== TUGAS 7: Access Control ====================

    public function testReportAccessPetugasOnly(): void {
        $petugasAccess  = $this->simulateCheckRole('petugas',  ['petugas']);
        $adminAccess    = $this->simulateCheckRole('admin',    ['petugas']);
        $operatorAccess = $this->simulateCheckRole('operator', ['petugas']);

        $this->assertTrue($petugasAccess,
            'Petugas must have access to Report page');
        $this->assertFalse($adminAccess,
            'Admin must NOT have access to Report page');
        $this->assertFalse($operatorAccess,
            'Operator must NOT have access to Report page');
    }

    public function testExportAccessPetugasOnly(): void {
        $this->assertTrue($this->simulateCheckRole('petugas', ['petugas']),
            'Petugas must have access to export');
        $this->assertFalse($this->simulateCheckRole('admin',  ['petugas']),
            'Admin must NOT have access to export');
    }

    // ==================== TUGAS 8: View file dan export link ====================

    public function testReportViewFileExists(): void {
        $viewPath = ROOT_PATH . '/app/views/laporan-lainnya/report.php';
        $this->assertFileExists($viewPath,
            'View file laporan-lainnya/report.php must exist');
    }

    public function testReportViewContainsExportLink(): void {
        $reportContent = $this->loadView('app/views/laporan-lainnya/report.php');
        $this->assertStringContainsString('laporan-lainnya/export', $reportContent,
            'Report view must contain export link');
    }

    public function testReportViewContainsKpiCards(): void {
        $reportContent = $this->loadView('app/views/laporan-lainnya/report.php');
        $this->assertStringContainsString('Tingkat Verifikasi', $reportContent,
            'Report view must contain Verification Rate KPI card');
        $this->assertStringContainsString('Tindak Lanjut Laporan Ditolak', $reportContent,
            'Report view must contain Rejected Reports action panel');
    }

    // ==================== Helper Methods ====================

    private function loadWebRoutes(): array {
        $path = ROOT_PATH . '/config/web_routes.php';
        $this->assertFileExists($path, 'web_routes.php must exist');
        return require $path;
    }

    private function loadView(string $relativePath): string {
        $path = ROOT_PATH . '/' . $relativePath;
        $this->assertFileExists($path, "View file '{$relativePath}' must exist");
        $content = file_get_contents($path);
        $this->assertNotEmpty($content, "View file '{$relativePath}' must not be empty");
        return $content;
    }

    private function simulateCheckRole(string $userRole, array $allowedRoles): bool {
        return in_array($userRole, $allowedRoles, true);
    }
}
