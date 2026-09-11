<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Kontrak halaman admin Master Jenis Laporan (runtime root, session+CSRF).
 *
 * Memastikan: route terdaftar, controller admin-only + CSRF + anti
 * double-submit, view ter-escape + ber-CSRF, menu sidebar admin-only,
 * dan mutasi membersihkan cache dropdown.
 */
final class JenisLaporanAdminContractTest extends TestCase
{
    public function testRoutesAreRegistered(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';
        foreach ([
            'jenis-laporan' => 'JenisLaporan@index',
            'jenis-laporan/create' => 'JenisLaporan@create',
            'jenis-laporan/store' => 'JenisLaporan@store',
            'jenis-laporan/edit' => 'JenisLaporan@edit',
            'jenis-laporan/update' => 'JenisLaporan@update',
            'jenis-laporan/toggle' => 'JenisLaporan@toggle',
            'jenis-laporan/delete' => 'JenisLaporan@delete',
        ] as $path => $handler) {
            self::assertSame($handler, $routes[$path] ?? null, "Route {$path} wajib terdaftar");
        }
    }

    public function testControllerIsAdminOnlyAndCsrfProtected(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/controllers/JenisLaporanController.php');
        foreach (['index', 'create', 'store', 'edit', 'update', 'toggle', 'delete'] as $method) {
            $start = strpos($source, "function {$method}(");
            self::assertNotFalse($start, "Method {$method} wajib ada");
            $next = strpos($source, 'public function ', $start + 10);
            $private = strpos($source, 'private function ', $start + 10);
            $end = $next === false ? $private : ($private === false ? $next : min($next, $private));
            $body = substr($source, $start, ($end ?: strlen($source)) - $start);
            self::assertStringContainsString("checkRole(['admin']", $body, "{$method} wajib admin-only");
        }

        foreach (['store', 'update', 'toggle', 'delete'] as $method) {
            $start = strpos($source, "function {$method}(");
            $next = strpos($source, 'public function ', $start + 10);
            $private = strpos($source, 'private function ', $start + 10);
            $end = $next === false ? $private : ($private === false ? $next : min($next, $private));
            $body = substr($source, $start, ($end ?: strlen($source)) - $start);
            self::assertStringContainsString('requireStateChangingRequest', $body, "{$method} wajib POST+CSRF");
        }
    }

    public function testMutationsClearDropdownCacheAndLogActivity(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/controllers/JenisLaporanController.php');
        self::assertStringContainsString("delete('jenis_laporan:active')", $source);
        self::assertStringContainsString('logActivity', $source);
        self::assertStringContainsString('countUsage', $source, 'Hapus wajib pre-check pemakaian (FK RESTRICT guard)');
        self::assertStringContainsString('softDelete($id', $source, 'Hapus wajib lunak (recycle bin), bukan hard delete');
        self::assertStringContainsString('dipindahkan ke recycle bin', $source);
    }

    public function testJenisLaporanTerdaftarDiRecycleBin(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/controllers/RecycleBinController.php');
        self::assertStringContainsString("'jenis-laporan' => ['table' => 'master_jenis_laporan'", $source);
        self::assertStringContainsString("'name' => 'Jenis Laporan'", $source);
    }

    public function testMigrasiSoftDeleteAda(): void
    {
        $path = ROOT_PATH . '/migrations/2026_09_05_add_soft_delete_master_jenis_laporan.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertStringContainsString('deleted_at', $source);
        self::assertStringContainsString('deleted_by', $source);
        self::assertStringContainsString('idx_mjl_deleted_at', $source);
    }

    public function testModelMenyembunyikanDataRecycleBin(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/models/JenisLaporan.php');
        self::assertGreaterThanOrEqual(
            4,
            substr_count($source, "whereNull('deleted_at')"),
            'Query baca wajib mengecualikan baris terhapus lunak'
        );
        self::assertStringContainsString('m.deleted_at IS NULL', $source);
    }

    public function testModelGuardsMassAssignmentAndUsesPreparedStatements(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/models/JenisLaporan.php');
        self::assertStringContainsString("'kode', 'nama', 'deskripsi', 'fields_json', 'is_active'", $source);
        self::assertStringContainsString('WHERE jenis_id = ?', $source);
        self::assertStringNotContainsString('$_GET', $source);
        self::assertStringNotContainsString('$_POST', $source);
    }

    public function testViewsEscapeOutputAndCarryCsrf(): void
    {
        foreach (['index', 'form'] as $view) {
            $source = file_get_contents(ROOT_PATH . "/app/views/jenis-laporan/{$view}.php");
            self::assertStringContainsString('htmlspecialchars', $source, "{$view} wajib escape output");
            self::assertStringContainsString('getCsrfField', $source, "{$view} wajib CSRF pada form mutasi");
            self::assertStringNotContainsString('$_SESSION', $source, "{$view} tidak boleh membaca session langsung");
            self::assertStringContainsString('jenis-no-motion', $source, "{$view} wajib mematikan efek timbul-tenggelam");
            self::assertStringContainsString('transform: none !important', $source, "{$view} wajib menonaktifkan transform");
        }
    }

    public function testSidebarMenuIsAdminOnly(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/views/layouts/header.php');
        self::assertStringContainsString('jenis-laporan', $source);
        // Menu berada di dalam blok admin (sebelum penutup blok, sesudah Master Wilayah).
        $menuPos = strpos($source, '<?= BASE_URL ?>jenis-laporan');
        $adminBlock = strpos($source, "in_array(\$_SESSION['role'] ?? '', ['admin'])");
        self::assertNotFalse($menuPos);
        self::assertNotFalse($adminBlock);
        self::assertLessThan($menuPos, $adminBlock, 'Menu Jenis Laporan wajib di dalam blok admin');
    }

    public function testStateChangingToggleIsGuardedGlobally(): void
    {
        $source = file_get_contents(ROOT_PATH . '/index.php');
        self::assertStringContainsString("'toggle',", $source);
    }

    public function testCreateFormAutoFillsKode(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/views/jenis-laporan/form.php');
        self::assertStringContainsString('data-autokode="1"', $source);
        self::assertStringContainsString('slugifyNama', $source);
        self::assertStringContainsString('syncKodeFromNama', $source);
        $service = file_get_contents(ROOT_PATH . '/app/services/JenisLaporanService.php');
        self::assertStringContainsString('function slugify', $service);
    }
}
