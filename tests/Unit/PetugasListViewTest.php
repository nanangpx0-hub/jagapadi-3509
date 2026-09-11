<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Render app/views/reports/petugas_list.php dengan data dummy:
 * - filter memiliki aria-label (WCAG),
 * - pagination memakai sliding window + elipsis + aria-current,
 * - badge Submitted berlabel "Dikirim" (nilai resmi tidak berubah),
 * - tabel memakai .table-responsive-card + data-label per sel.
 */
final class PetugasListViewTest extends TestCase
{
    private function render(array $vars): string
    {
        if (!defined('BASE_URL')) {
            define('BASE_URL', 'http://localhost/jagapadi-3509/');
        }
        extract($vars);
        ob_start();
        require ROOT_PATH . '/app/views/reports/petugas_list.php';
        return (string) ob_get_clean();
    }

    private function baseVars(): array
    {
        return [
            'petugasReportType' => 'hama',
            'status' => '',
            'search' => '',
            'dateFrom' => '',
            'dateTo' => '',
            'perPage' => 20,
            'page' => 25,
            'total' => 1000,
            'totalPages' => 50,
            'laporan' => [
                [
                    'id' => 7,
                    'nomor_laporan' => 'JH-2026-0007',
                    'tanggal' => '2026-08-01',
                    'foto_url' => '',
                    'video_url' => '',
                    'nama_opt' => 'Wereng',
                    'nama_desa' => 'Antirogo',
                    'nama_kecamatan' => 'Sumbersari',
                    'tingkat_keparahan' => 'Ringan',
                    'status' => 'Submitted',
                ],
            ],
        ];
    }

    public function testFilterMemilikiAriaLabel(): void
    {
        $html = $this->render($this->baseVars());
        self::assertStringContainsString('aria-label="Filter Status Laporan"', $html);
        self::assertStringContainsString('aria-label="Filter Tanggal Mulai"', $html);
        self::assertStringContainsString('aria-label="Filter Tanggal Akhir"', $html);
        self::assertStringContainsString('aria-label="Cari kata kunci laporan"', $html);
    }

    public function testPaginationSlidingWindowDenganElipsisDanAriaCurrent(): void
    {
        $html = $this->render($this->baseVars());

        // 50 halaman, posisi 25 -> 1 ... 23 24 25 26 27 ... 50
        self::assertStringContainsString('<li class="page-item disabled"><span class="page-link">...</span></li>', $html);
        self::assertStringContainsString('aria-current="page"', $html);

        preg_match_all('/<li class="page-item[^>]*><a class="page-link"[^>]*>(\d+)<\/a>/', $html, $m);
        $numbers = array_map('intval', $m[1]);
        self::assertLessThanOrEqual(7, count($numbers), 'Maksimal 7 nomor halaman');
        self::assertContains(1, $numbers);
        self::assertContains(50, $numbers);
        self::assertContains(25, $numbers);
    }

    public function testPaginationSedikitHalamanTanpaElipsis(): void
    {
        $vars = $this->baseVars();
        $vars['page'] = 1;
        $vars['totalPages'] = 3;
        $html = $this->render($vars);
        self::assertStringNotContainsString('<span class="page-link">...</span>', $html);
    }

    public function testBadgeSubmittedBerlabelDikirim(): void
    {
        $html = $this->render($this->baseVars());
        self::assertStringContainsString('>Dikirim<', $html);
        self::assertStringNotContainsString('>Submitted<', $html);
        // Nilai resmi untuk logika/filter tidak berubah.
        self::assertStringContainsString('value="Submitted"', $html);
    }

    public function testTabelKartuDenganDataLabel(): void
    {
        $html = $this->render($this->baseVars());
        self::assertStringContainsString('table-responsive-card', $html);
        foreach (['No', 'Nomor Laporan', 'Tanggal', 'Preview Media', 'OPT', 'Lokasi', 'Keparahan', 'Status', 'Aksi'] as $label) {
            self::assertStringContainsString('data-label="' . $label . '"', $html);
        }
    }
}
