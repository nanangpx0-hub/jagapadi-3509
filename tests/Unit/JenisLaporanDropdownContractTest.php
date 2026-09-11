<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Kontrak dropdown dinamis Jenis Laporan + OPT.
 *
 * Memastikan:
 * - modul JS terpusat di namespace window.OPT (BAGIAN OPT);
 * - fungsi CRUD/read/validasi/event/error tersedia;
 * - view create memakai pengisian realtime tanpa reload + fallback server;
 * - tidak ada SQL mentah / secret di modul frontend.
 */
final class JenisLaporanDropdownContractTest extends TestCase
{
    private function jsSource(): string
    {
        $path = ROOT_PATH . '/public/js/jenis-laporan-dropdown.js';
        self::assertFileExists($path, 'Modul dropdown dinamis wajib ada.');
        $source = file_get_contents($path);
        self::assertIsString($source);
        return $source;
    }

    public function testOptNamespaceOrganizesAllDropdownFunctions(): void
    {
        $source = $this->jsSource();
        self::assertStringContainsString('BAGIAN OPT', $source);
        self::assertStringContainsString('window.OPT', $source);
        self::assertStringContainsString('JenisLaporanDropdown', $source);
        self::assertStringContainsString('OptDropdown', $source);
    }

    public function testJenisDropdownExposesCrudReadValidationAndEvents(): void
    {
        $source = $this->jsSource();
        foreach (['load:', 'refresh:', 'render:', 'getAll:', 'getById:', 'createItem:', 'updateItem:', 'deleteItem:'] as $fn) {
            self::assertStringContainsString($fn, $source, "Fungsi {$fn} wajib ada.");
        }
        foreach (['validateItem:', 'validateSelection:', 'onSelect:', 'bind:', 'handleError:', 'clearError:'] as $fn) {
            self::assertStringContainsString($fn, $source, "Fungsi {$fn} wajib ada.");
        }
        self::assertStringContainsString('_listeners', $source);
        self::assertStringContainsString('change', $source);
        self::assertStringContainsString('jenis-laporan:change', $source);
        self::assertStringContainsString('opt:change', $source);
    }

    public function testRealtimeFetchWithoutReloadAndErrorHandling(): void
    {
        $source = $this->jsSource();
        self::assertStringContainsString("cache: 'no-store'", $source);
        self::assertStringContainsString('AbortController', $source);
        self::assertStringContainsString('api/v1/jenis-laporan', $source);
        self::assertStringContainsString('api/opt', $source);
        self::assertStringContainsString('data-jenis-error', $source);
        self::assertStringContainsString('data-opt-error', $source);
        self::assertStringContainsString('escapeHtml', $source);
    }

    public function testHydrateFromServerRenderAndSilentBackgroundRefresh(): void
    {
        $source = $this->jsSource();
        // State awal diambil dari <option> server-render agar form tetap
        // berfungsi walau fetch API gagal (fallback diam, tanpa box error).
        self::assertStringContainsString('hydrateFromSelect', $source);
        self::assertStringContainsString('silent', $source);
        self::assertStringContainsString('keptServerList', $source);
        self::assertStringContainsString('server-render', $source);
    }

    public function testNoRawSqlOrSecretsInFrontendModule(): void
    {
        $source = $this->jsSource();
        self::assertStringNotContainsString('SELECT ', $source);
        self::assertStringNotContainsString('INSERT INTO', $source);
        self::assertStringNotContainsString('Bearer eyJ', $source);
        self::assertStringNotContainsString('PRIVATE KEY', $source);
    }

    public function testLaporanLainnyaCreateUsesDynamicJenisDropdown(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/views/laporan-lainnya/create.php');
        self::assertStringContainsString('data-jenis-laporan-dropdown', $source);
        self::assertStringContainsString('data-jenis-error', $source);
        self::assertStringContainsString('data-jenis-refresh', $source);
        self::assertStringContainsString('jenis-laporan-dropdown.js', $source);
        self::assertStringContainsString('OPT.JenisLaporanDropdown', $source);
        self::assertStringContainsString('validateSelection', $source);
        // Fallback server-render tetap ada untuk no-JS.
        self::assertStringContainsString('foreach($jenisList as $jl)', $source);
    }

    public function testLaporanHamaCreateUsesDynamicOptDropdown(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/views/laporan/create.php');
        self::assertStringContainsString('data-opt-dropdown', $source);
        self::assertStringContainsString('data-opt-error', $source);
        self::assertStringContainsString('data-opt-refresh', $source);
        self::assertStringContainsString('jenis-laporan-dropdown.js', $source);
        self::assertStringContainsString('OPT.OptDropdown', $source);
        // Fallback server-render tetap ada untuk no-JS.
        self::assertStringContainsString('foreach($data_opt as $opt)', $source);
    }

    public function testResponsiveCssExists(): void
    {
        $path = ROOT_PATH . '/public/css/jenis-laporan-dropdown.css';
        self::assertFileExists($path);
        $css = file_get_contents($path);
        self::assertStringContainsString('@media', $css);
        self::assertStringContainsString('min-height: 48px', $css);
    }

    public function testWebJsonRoutesAreRegistered(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';
        self::assertSame('LaporanLainnya@jenisList', $routes['laporan-lainnya/jenis-list'] ?? null);
        self::assertSame('Opt@listJson', $routes['opt/list-json'] ?? null);
    }

    public function testWebJsonControllersEnforceSessionAuthAndJsonEnvelope(): void
    {
        foreach ([
            'app/controllers/LaporanLainnyaController.php' => 'function jenisList',
            'app/controllers/OptController.php' => 'function listJson',
        ] as $file => $marker) {
            $source = file_get_contents(ROOT_PATH . '/' . $file);
            $start = strpos($source, $marker);
            self::assertNotFalse($start, "{$marker} wajib ada di {$file}");
            $next = strpos($source, 'public function ', $start + 10);
            // Fallback: akhir method terdekat (method berikutnya atau akhir file).
            $body = substr($source, $start, ($next ?: strlen($source)) - $start);
            self::assertStringContainsString("empty(\$_SESSION['user_id'])", $body, 'Guest wajib ditolak');
            self::assertStringContainsString("'status' => 'error'", $body, 'Guest/forbidden wajib JSON error');
            self::assertStringContainsString("'status' => 'success'", $body, 'Sukses wajib envelope web JSON');
            self::assertStringContainsString('catch (Throwable', $body, 'Kegagalan server wajib 500 JSON');
            self::assertStringNotContainsString('password', $body, 'Log tidak boleh memuat secret');
            self::assertStringNotContainsString('$_SESSION[\'csrf_token\']', $body, 'GET JSON tidak butuh CSRF');
        }

        // Role mirror halaman form: jenis-list khusus pembuat laporan.
        $lainnya = file_get_contents(ROOT_PATH . '/app/controllers/LaporanLainnyaController.php');
        $start = strpos($lainnya, 'function jenisList');
        $body = substr($lainnya, $start, (strpos($lainnya, 'public function ', $start + 10) ?: strlen($lainnya)) - $start);
        foreach (['admin', 'operator', 'petugas'] as $role) {
            self::assertStringContainsString($role, $body, "Role {$role} wajib diizinkan pada jenisList");
        }
    }

    public function testJsTriesWebEndpointBeforeApiFallback(): void
    {
        $source = file_get_contents(ROOT_PATH . '/public/js/jenis-laporan-dropdown.js');
        self::assertStringContainsString('laporan-lainnya/jenis-list', $source);
        self::assertStringContainsString('opt/list-json', $source);
        $arrayStart = strpos($source, 'jenisEndpoints:');
        self::assertNotFalse($arrayStart, 'Daftar fallback jenisEndpoints wajib ada');
        $arrayEnd = strpos($source, ']', $arrayStart);
        $arrayLiteral = substr($source, $arrayStart, $arrayEnd - $arrayStart);
        $webJenis = strpos($arrayLiteral, 'laporan-lainnya/jenis-list');
        $apiJenis = strpos($arrayLiteral, 'api/v1/jenis-laporan');
        self::assertNotFalse($webJenis);
        self::assertNotFalse($apiJenis);
        self::assertLessThan($apiJenis, $webJenis, 'Endpoint web primer wajib dicoba sebelum cadangan /api');
        self::assertStringContainsString('fetchJsonFromUrls', $source);
        self::assertStringContainsString('_listUrls', $source);
    }
}
