<?php

declare(strict_types=1);

/**
 * Compatibility guard untuk runtime root (frozen).
 * Menjamin perilaku kritis tidak berubah selama strangler migration ke Backend v1.
 * Jika test ini merah, update harus via ADR dan disetujui.
 */

use PHPUnit\Framework\TestCase;

final class RootCompatibilityTest extends TestCase
{
    private array $routes;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 2) . '/config/web_routes.php';
        $this->assertFileExists($path, 'config/web_routes.php harus ada');
        $routes = require $path;
        $this->assertIsArray($routes, 'web_routes.php harus return array');
        $this->routes = $routes;
    }

    public function testRootRouteCountFrozen(): void
    {
        // Baseline 2026-08-30: 115 route unik eksplisit (setelah deduplikasi key). Penambahan rute root wajib ADR-011.
        // Rentang toleransi nol — setiap penambahan/penghapusan harus mengupdate test ini + ADR.
        $expectedCount = 115;
        // Hitung unik key (case-insensitive fallback di index.php)
        $count = count($this->routes);
        $this->assertSame(
            $expectedCount,
            $count,
            "Jumlah route root berubah ($count vs $expectedCount). Tambah fitur di Backend v1, bukan root. Jika perubahan disengaja, update ADR-011 & test ini."
        );
    }

    public function testCriticalRootRoutesExist(): void
    {
        $critical = [
            'dashboard' => 'Dashboard@index',
            'laporan' => 'Laporan@index',
            'laporan-lainnya' => 'LaporanLainnya@index',
            'irigasi' => 'Irigasi@index',
            'recycle-bin' => 'RecycleBin@index',
            'usulan-opt' => 'UsulanOpt@index',
            'optsaya' => 'UsulanOpt@index',
            'feedback' => 'Feedback@index',
            'kecepatanAngin' => 'KecepatanAngin@index',
            'curahHujan' => 'CurahHujan@index',
            'hargaKomoditas' => 'HargaKomoditas@index',
            'bpsScraper' => 'BpsScraper@index',
            'storytelling' => 'Storytelling@index',
            'opt' => 'Opt@index',
        ];
        foreach ($critical as $path => $handler) {
            $this->assertArrayHasKey($path, $this->routes, "Route kritis root hilang: $path");
            // Handler boleh case-insensitive, cek mengandung Controller name
            $this->assertStringContainsString(
                explode('@', $handler)[0],
                $this->routes[$path],
                "Handler untuk $path berubah"
            );
        }
    }

    public function testNoNewRootRoutesWithoutApproval(): void
    {
        // Allowlist eksplisit — jika key tidak di allowlist, dianggap penambahan fitur baru yang dilarang.
        $allowlist = [
            'login','logout','auth/login','auth/do-login','auth/logout','auth/change-password','auth/update-password','auth/forgot-password',
            'dashboard','dashboard/charts-lainnya','dashboard/map','dashboard/charts','admin/health','dashboard-padi','dashboardPadi',
            'laporan','laporan/create','laporan/store','laporan/detail','laporan/fetch','laporan/bulk-delete','laporan/hama',
            'laporan-lainnya','laporan-lainnya/create','laporan-lainnya/store','laporan-lainnya/summary','laporan-lainnya/report','laporan-lainnya/export','laporan-lainnya/bulk-delete','laporan-lainnya/delete-all',
            'irigasi','irigasi/create','irigasi/store','irigasi/monitoring','irigasi/bulk-delete',
            'recycle-bin','recycle-bin/restore','recycle-bin/bulk-restore','recycle-bin/bulk-delete',
            'irigasiScraper','irigasiScraper/runScraper','irigasiScraper/export',
            'curahHujan','curahHujan/runScraper','curahHujan/getChartData','curahHujan/getStatistics','curahHujan/export',
            'kecepatanAngin','kecepatanAngin/runScraper','kecepatanAngin/getChartData','kecepatanAngin/getStatistics','kecepatanAngin/export',
            'hargaKomoditas','hargaKomoditas/runScraper','hargaKomoditas/getChartData','hargaKomoditas/getStatistics','hargaKomoditas/export',
            'bpsScraper','bpsScraper/runScraper','bpsScraper/runScraperBackground','bpsScraper/getScraperStatus','bpsScraper/getChartData','bpsScraper/getStatistics','bpsScraper/getMonthlyHarvestArea','bpsScraper/getMonthlyHarvestChart','bpsScraper/export',
            'evaluasi','storytelling','storytelling/generateAnalysis','storytelling/store','storytelling/getChartData','storytelling/runMethod','storytelling/getRecent','storytelling/getAnalysis','storytelling/publish',
            'feedback','feedback/create','feedback/admin-summary','feedback/report',
            'opt','opttambahkan','opt/bulk-delete','opt/delete-all','opt/auto-fill-photos',
            'usulan-opt','usulan-opt/create','usulan-opt/store','usulan-opt/update','usulan-opt/submit','usulan-opt/resubmit','usulan-opt/delete-draft','usulan-opt/delete-photo','usulan-opt/request-revision','usulan-opt/review','usulan-opt/approve-new','usulan-opt/bulk-approve','usulan-opt/search-master','usulan-opt/bulk-delete','usulan-opt/import','usulan-opt/export','usulan-opt/template',
            'optsaya','optsaya/import','optsaya/export','optsaya/template',
            'user','user/exportCsv','user/exportExcel',
            'adminWilayah/kabupaten','adminWilayah/kecamatan','adminWilayah/desa',
            'export/csv','export/excel','export/pdf',
            'laporan-hama/analytics',
        ];
        $allowlistLower = array_map('strtolower', $allowlist);
        foreach (array_keys($this->routes) as $key) {
            $this->assertContains(
                strtolower($key),
                $allowlistLower,
                "Route root baru terdeteksi: $key — fitur baru harus di Backend v1 (ADR-011). Jika memang perbaikan keamanan, tambahkan ke allowlist & ADR."
            );
        }
    }

    public function testWebRoutesHandlersAreValidIdentifiers(): void
    {
        foreach ($this->routes as $path => $handler) {
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9_]+\s*@\s*[A-Za-z0-9_]+$/',
                $handler,
                "Handler untuk $path tidak valid: $handler"
            );
        }
    }

    public function testBackendLogoutIsPostOnly(): void
    {
        $backendRoutes = file_get_contents(dirname(__DIR__, 2) . '/backend/config/routes.php');
        $this->assertIsString($backendRoutes);
        $this->assertStringContainsString("post('/logout'", strtolower($backendRoutes), 'Backend v1 harus POST-only untuk /logout');
        $this->assertDoesNotMatchRegularExpression(
            '/\$router->get\s*\(\s*[\'"]\/logout[\'"]/i',
            $backendRoutes,
            'Backend v1 tidak boleh memiliki GET /logout (CSRF risk)'
        );
    }

    public function testBackendCsrfDoesNotExemptLogout(): void
    {
        $csrf = file_get_contents(dirname(__DIR__, 2) . '/backend/app/Middleware/CsrfMiddleware.php');
        $this->assertIsString($csrf);
        // EXEMPT_PATHS harus kosong atau tidak mengandung /logout
        if (preg_match('/EXEMPT_PATHS\s*=\s*\[(.*?)\]/s', $csrf, $m)) {
            $exempt = strtolower($m[1]);
            $this->assertStringNotContainsString('/logout', $exempt, 'CsrfMiddleware tidak boleh exempt /logout');
        }
    }
}
