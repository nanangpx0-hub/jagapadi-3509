<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/helpers/SidebarState.php';

final class UsulanOptMenuContractTest extends TestCase
{
    public function testAdminSeesReviewMenuLabel(): void
    {
        self::assertSame('Usulan OPT', SidebarState::usulanOptMenuLabel('admin'));
    }

    public function testPetugasSeesPersonalHistoryMenuLabel(): void
    {
        self::assertSame('Usulan OPT Saya', SidebarState::usulanOptMenuLabel('petugas'));
    }

    public function testOtherRolesReceiveNoUsulanMenu(): void
    {
        self::assertNull(SidebarState::usulanOptMenuLabel('operator'));
        self::assertNull(SidebarState::usulanOptMenuLabel('statistisi'));
        self::assertNull(SidebarState::usulanOptMenuLabel('viewer'));
        self::assertNull(SidebarState::usulanOptMenuLabel(null));
        self::assertNull(SidebarState::usulanOptMenuLabel('unknown-role'));
    }

    public function testHeaderNoLongerNestsUsulanMenuInsideAdminOperatorGuard(): void
    {
        $header = file_get_contents(ROOT_PATH . '/app/views/layouts/header.php');

        self::assertNotFalse($header, 'header.php harus dapat dibaca');
        self::assertStringContainsString('usulanOptMenuLabel(', $header,
            'header.php harus memanggil helper label menu usulan');

        $compact = preg_replace('/\s+/', '', $header) ?? '';
        self::assertStringContainsString(
            "\$_SESSION['role']??'')==='petugas'",
            $compact,
            'Sanity: pola referensi role petugas masih ada di header (blok lain)'
        );
        self::assertStringNotContainsString(
            "elseif(\$_SESSION['role']??'')==='petugas')",
            $compact,
            'Menu usulan tidak boleh lagi berupa elseif petugas bersarang di guard admin/operator'
        );
        self::assertSame(
            1,
            substr_count($compact, 'usulanOptMenuLabel('),
            'Label menu usulan hanya boleh ditentukan lewat helper SidebarState'
        );
        self::assertStringContainsString(
            "\$usulanMenuActive = SidebarState::matches(\$sidebarRoute, 'usulan-opt');",
            $header,
            'Active state harus mengikuti semua route /usulan-opt'
        );
    }

    public function testAllExpectedRoutesAreExplicitlyRegistered(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';

        $expected = [
            'usulan-opt' => 'UsulanOpt@index',
            'usulan-opt/create' => 'UsulanOpt@create',
            'usulan-opt/store' => 'UsulanOpt@store',
            'usulan-opt/update' => 'UsulanOpt@update',
            'usulan-opt/submit' => 'UsulanOpt@submit',
            'usulan-opt/resubmit' => 'UsulanOpt@resubmit',
            'usulan-opt/delete-draft' => 'UsulanOpt@deleteDraft',
            'usulan-opt/delete-photo' => 'UsulanOpt@deletePhoto',
            'usulan-opt/request-revision' => 'UsulanOpt@requestRevision',
            'usulan-opt/review' => 'UsulanOpt@review',
            'usulan-opt/approve-new' => 'UsulanOpt@approveNew',
            'usulan-opt/bulk-approve' => 'UsulanOpt@bulkApprove',
            'usulan-opt/search-master' => 'UsulanOpt@searchMaster',
        ];

        foreach ($expected as $path => $handler) {
            self::assertSame($handler, $routes[$path] ?? null, "Route {$path} wajib terdaftar eksplisit");
        }
    }

    public function testControllerEnforcesCsrfOnEveryMutation(): void
    {
        $controller = file_get_contents(ROOT_PATH . '/app/controllers/UsulanOptController.php');
        $mutations = ['store', 'update', 'submit', 'resubmit', 'deleteDraft', 'deletePhoto', 'requestRevision', 'review', 'approveNew', 'bulkApprove'];

        foreach ($mutations as $method) {
            $start = strpos($controller, "public function {$method}(");
            self::assertNotFalse($start, "Method {$method} wajib ada");
            $nextMethod = strpos($controller, 'public function ', $start + 10);
            $body = substr($controller, $start, ($nextMethod ?: $start + 2000) - $start);
            self::assertStringContainsString(
                'requireStateChangingRequest([\'POST\'])',
                $body,
                "{$method} wajib memvalidasi POST + CSRF di controller, bukan hanya allowlist global"
            );
        }
    }

    public function testPetugasIndexViewHasCreateCtaAndEmptyStateGuidance(): void
    {
        $index = file_get_contents(ROOT_PATH . '/app/views/usulan-opt/index.php');

        self::assertStringContainsString('Buat Usulan OPT', $index, 'Petugas harus melihat CTA buat usulan');
        self::assertStringContainsString('Buat melalui Laporan Hama', $index, 'CTA sekunder via Laporan Hama wajib ada');
        self::assertStringContainsString('Belum ada usulan OPT.', $index, 'Empty state wajib menjelaskan cara mengajukan');
        self::assertStringContainsString('usulan-opt/delete-draft', $index, 'Tombol Hapus Draf wajib ada');
        self::assertStringContainsString('usulan-opt/submit', $index, 'Tombol Kirim wajib ada');
        self::assertStringContainsString('usulan-opt/resubmit', $index, 'Tombol Resubmit wajib ada');
        self::assertStringContainsString('Minta Perbaikan', $index, 'Aksi Admin Minta Perbaikan wajib ada');
        self::assertStringContainsString('Tolak Permanen', $index, 'Label Tolak Permanen wajib dipakai');
    }

    public function testPerPageSelectionAutomaticallySubmitsCurrentFilters(): void
    {
        $index = file_get_contents(ROOT_PATH . '/app/views/usulan-opt/index.php');

        self::assertStringContainsString('id="usulanOptFilterForm"', $index);
        self::assertStringContainsString('id="filter_per_page"', $index);
        self::assertStringContainsString("perPage.addEventListener('change'", $index);
        self::assertStringContainsString('filterForm.submit()', $index);
        self::assertStringNotContainsString('perPage.disabled = true', $index);
    }

    public function testAdminHasBulkApprovalControlsWithAutomaticCodes(): void
    {
        $index = file_get_contents(ROOT_PATH . '/app/views/usulan-opt/index.php');
        self::assertStringContainsString('id="bulk_approve_selected_btn"', $index);
        self::assertStringContainsString('id="bulk_approve_all_btn"', $index);
        self::assertStringContainsString('usulan-opt/bulk-approve', $index);
        self::assertStringContainsString("input.name = 'ids[]'", $index);

        $review = file_get_contents(ROOT_PATH . '/app/services/UsulanOptReviewService.php');
        self::assertStringContainsString("GET_LOCK('master_opt_code_generation'", $review);
        self::assertStringContainsString('nextAutomaticCode(', $review);

        $migration = file_get_contents(ROOT_PATH . '/database/migrations/2026_08_24_add_unique_master_opt_code.sql');
        self::assertStringContainsString('UNIQUE KEY uk_master_opt_kode_opt', $migration);
    }
}
