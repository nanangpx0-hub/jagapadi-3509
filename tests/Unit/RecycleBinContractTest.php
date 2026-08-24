<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RecycleBinContractTest extends TestCase
{
    public function testAllBulkDeleteAndRecycleRoutesAreExplicit(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';
        self::assertSame('UsulanOpt@bulkDelete', $routes['usulan-opt/bulk-delete'] ?? null);
        self::assertSame('Laporan@bulkDelete', $routes['laporan/bulk-delete'] ?? null);
        self::assertSame('Irigasi@bulkDelete', $routes['irigasi/bulk-delete'] ?? null);
        self::assertSame('LaporanLainnya@bulkDelete', $routes['laporan-lainnya/bulk-delete'] ?? null);
        self::assertSame('RecycleBin@index', $routes['recycle-bin'] ?? null);
        self::assertSame('RecycleBin@restore', $routes['recycle-bin/restore'] ?? null);
        self::assertSame('RecycleBin@bulkRestore', $routes['recycle-bin/bulk-restore'] ?? null);
        self::assertSame('RecycleBin@bulkDelete', $routes['recycle-bin/bulk-delete'] ?? null);
    }

    public function testBulkEndpointsAreAdminOnlyAndStateChanging(): void
    {
        foreach ([
            'UsulanOptController.php',
            'LaporanController.php',
            'IrigasiController.php',
            'LaporanLainnyaController.php',
        ] as $file) {
            $source = file_get_contents(ROOT_PATH . '/app/controllers/' . $file);
            $start = strpos($source, 'public function bulkDelete(');
            self::assertNotFalse($start, "{$file} harus mempunyai bulkDelete");
            $next = strpos($source, 'public function ', $start + 15);
            $body = substr($source, $start, ($next ?: strlen($source)) - $start);
            self::assertStringContainsString("checkRole(['admin']", $body);
            self::assertTrue(
                str_contains($body, 'requireStateChangingRequest')
                || str_contains($body, 'validateCsrfTokenAjax'),
                "{$file} harus memvalidasi method/CSRF"
            );
        }
    }

    public function testViewsExposeAdminSelectionAndRecycleBinControls(): void
    {
        foreach ([
            'usulan-opt/index.php' => 'bulk_select_all',
            'laporan/index.php' => 'checkAll',
            'irigasi/index.php' => 'irigasiSelectAll',
            'laporan-lainnya/index.php' => 'lainnyaSelectAll',
        ] as $view => $selectAllId) {
            $source = file_get_contents(ROOT_PATH . '/app/views/' . $view);
            self::assertStringContainsString($selectAllId, $source);
            self::assertStringContainsString('recycle-bin', $source);
            self::assertTrue(
                str_contains($source, "'admin'") || str_contains($source, '$is_admin'),
                "{$view} harus membatasi kontrol pada admin"
            );
        }
    }

    public function testMigrationCoversEveryManagedTable(): void
    {
        $sql = file_get_contents(
            ROOT_PATH . '/database/migrations/2026_08_24_add_recycle_bin_to_report_modules.sql'
        );
        foreach (['usulan_opt', 'laporan_hama', 'laporan_irigasi', 'laporan_lainnya'] as $table) {
            self::assertStringContainsString("ALTER TABLE {$table}", $sql);
        }
        self::assertSame(4, substr_count($sql, 'ADD COLUMN deleted_at'));
        self::assertSame(4, substr_count($sql, 'ADD COLUMN deleted_by'));
    }

    public function testRecycleBinUsesIrigasiLayoutAndResponsiveInteractions(): void
    {
        $view = file_get_contents(ROOT_PATH . '/app/views/recycle-bin/index.php');
        self::assertStringContainsString("layouts/header.php", $view);
        self::assertStringContainsString("layouts/footer.php", $view);
        self::assertStringContainsString('container-fluid recycle-page', $view);
        self::assertStringContainsString('card shadow', $view);
        self::assertStringContainsString('filter-panel', $view);
        self::assertStringContainsString('table table-bordered table-hover', $view);
        self::assertStringContainsString('@media(max-width:767.98px)', $view);
        self::assertStringContainsString('mobile-item', $view);
        self::assertStringContainsString('restoreModal', $view);
        self::assertStringContainsString('csrf_token', $view);
        self::assertStringContainsString('.recycle-page *,.recycle-page *::before,.recycle-page *::after', $view);
        self::assertStringContainsString('transform:none!important', $view);
        self::assertStringContainsString('recycleSelectAll', $view);
        self::assertStringContainsString('recycleRestoreSelected', $view);
        self::assertStringContainsString('recycleRestoreAll', $view);
        self::assertStringContainsString('recycleDeleteSelected', $view);
        self::assertStringContainsString('recycleDeleteAll', $view);
        self::assertStringContainsString('bulkDeleteForm', $view);
        self::assertStringContainsString('id="recycleFilterForm"', $view);
        self::assertStringContainsString('id="perPage"', $view);
        self::assertStringContainsString("perPageSelect.addEventListener('change'", $view);
        self::assertStringContainsString('filterForm.submit()', $view);
        self::assertStringContainsString("name='items[]'", $view);

        $header = file_get_contents(ROOT_PATH . '/app/views/layouts/header.php');
        self::assertStringContainsString('recycleBinMenuActive', $header);
        self::assertStringContainsString('data-sidebar-menu="recycle-bin"', $header);
    }

    public function testRecycleBinControllerProvidesFilteringAndPagination(): void
    {
        $controller = file_get_contents(ROOT_PATH . '/app/controllers/RecycleBinController.php');
        self::assertStringContainsString("checkRole(['admin'])", $controller);
        self::assertStringContainsString("\$_GET['module']", $controller);
        self::assertStringContainsString("\$_GET['search']", $controller);
        self::assertStringContainsString("\$_GET['per_page']", $controller);
        self::assertStringContainsString('UNION ALL', $controller);
        self::assertStringContainsString('LIMIT :limit OFFSET :offset', $controller);
        self::assertStringContainsString('totalPages', $controller);
        self::assertStringContainsString('beginTransaction', $controller);
        self::assertStringContainsString('rollBack', $controller);
        self::assertStringContainsString('clearReportCaches', $controller);
        self::assertStringContainsString('public function bulkRestore()', $controller);
        self::assertStringContainsString('public function bulkDelete()', $controller);
        self::assertStringContainsString("'permanent_delete'", $controller);
        self::assertStringContainsString("checkRole(['admin'])", $controller);
        self::assertStringContainsString("requireStateChangingRequest(['POST'])", $controller);
    }

    public function testAnalyticsAndApiExcludeSoftDeletedRows(): void
    {
        foreach ([
            'app/services/DashboardDataAggregator.php' => ['lh.deleted_at IS NULL', 'deleted_at IS NULL'],
            'app/models/LaporanHama.php' => ['deleted_at IS NULL'],
            'app/models/LaporanIrigasi.php' => ['deleted_at IS NULL'],
            'app/models/LaporanLainnya.php' => ['deleted_at IS NULL'],
            'app/controllers/Api/IrigasiController.php' => ['deleted_at IS NULL'],
            'app/services/DataStoryService.php' => ['deleted_at IS NULL'],
        ] as $file => $needles) {
            $source = file_get_contents(ROOT_PATH . '/' . $file);
            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $source, "{$file} harus mengecualikan recycle bin");
            }
        }
    }
}
