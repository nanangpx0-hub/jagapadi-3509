<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BulkSelectionContractTest extends TestCase
{
    public function testEveryManagedPageProvidesMasterAndIndividualSelection(): void
    {
        foreach ([
            'usulan-opt/index.php' => ['bulk_select_all', 'js-bulk-id', 'bulk_delete_btn'],
            'laporan/index.php' => ['checkAll', 'checkbox-item', 'btnBulkDelete'],
            'irigasi/index.php' => ['irigasiSelectAll', 'irigasi-row-check', 'irigasiBulkDelete'],
            'laporan-lainnya/index.php' => ['lainnyaSelectAll', 'lainnya-row-check', 'lainnyaBulkDelete'],
        ] as $view => [$master, $item, $button]) {
            $source = file_get_contents(ROOT_PATH . '/app/views/' . $view);
            self::assertStringContainsString($master, $source);
            self::assertStringContainsString($item, $source);
            self::assertStringContainsString($button, $source);
            self::assertStringContainsString('ids[]', $source);
            self::assertStringContainsString('confirm', $source);
        }
    }

    public function testDynamicHamaTableBindsIndividualAndMasterCheckboxesOnce(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/views/laporan/index.php');
        self::assertStringContainsString("checkAll.dataset.bulkBound", $source);
        self::assertStringContainsString("body.dataset.bulkBound", $source);
        self::assertStringContainsString("body.addEventListener('change'", $source);
        self::assertStringContainsString("classList.contains('checkbox-item')", $source);
    }

    public function testIrigasiUsesSingleServerPaginationLayerSoCheckboxesAreNotRedrawn(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/views/irigasi/index.php');
        self::assertStringNotContainsString("$('#dataTable').DataTable", $source);
        self::assertStringNotContainsString('dataTables-1.13.7.min.js', $source);
        self::assertStringContainsString("all.addEventListener('change'", $source);
        self::assertStringContainsString('getBoxes().forEach', $source);
        self::assertStringContainsString("box.checked = all.checked", $source);
        self::assertStringContainsString('irigasiSelectAllButton', $source);
        self::assertStringContainsString('window.irigasiSyncBulkSelection', $source);
        self::assertStringContainsString("document.querySelectorAll('.irigasi-row-check').forEach", $source);
        self::assertStringContainsString('form.requestSubmit ? form.requestSubmit(this) : form.submit()', $source);
        self::assertStringNotContainsString("modal-backdrop fade show", $source);
        self::assertStringNotContainsString('alert-dismissible fade show', $source);
        self::assertStringContainsString('.container-fluid table tbody tr:hover', $source);
        self::assertStringContainsString('transform: none !important', $source);
    }

    public function testUsulanSelectionDeduplicatesDesktopAndMobileCheckboxes(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/views/usulan-opt/index.php');
        self::assertStringContainsString('var selected = {};', $source);
        self::assertStringContainsString('if (box.checked && !selected[box.value])', $source);
        self::assertStringContainsString('if (box.value === id)', $source);
    }

    public function testBulkDeleteEndpointsRemainAdminOnlyAndCsrfProtected(): void
    {
        foreach ([
            'UsulanOptController.php',
            'LaporanController.php',
            'IrigasiController.php',
            'LaporanLainnyaController.php',
        ] as $file) {
            $source = file_get_contents(ROOT_PATH . '/app/controllers/' . $file);
            $start = strpos($source, 'public function bulkDelete(');
            self::assertNotFalse($start);
            $next = strpos($source, 'public function ', $start + 15);
            $body = substr($source, $start, ($next ?: strlen($source)) - $start);
            self::assertStringContainsString("checkRole(['admin']", $body);
            self::assertTrue(
                str_contains($body, 'requireStateChangingRequest')
                || str_contains($body, 'validateCsrfTokenAjax')
            );
        }
    }
}
