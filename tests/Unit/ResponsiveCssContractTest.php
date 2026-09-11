<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Kontrak CSS responsif (mobile 375px):
 * - Tidak ada aturan generik ".btn { width: 100% }" yang membuat tombol
 *   aksi tabel/pagination melar di layar kecil.
 * - Full-width hanya via utilitas opt-in .btn-block-xs.
 * - Tombol tabel/pagination/close mempertahankan width: auto.
 * - .table-responsive-card mengubah baris menjadi kartu < 768px.
 */
final class ResponsiveCssContractTest extends TestCase
{
    private function css(): string
    {
        $path = ROOT_PATH . '/public/css/responsive.css';
        self::assertFileExists($path);
        $css = file_get_contents($path);
        self::assertIsString($css);
        return $css;
    }

    public function testTidakAdaAturanBtnFullWidthGenerik(): void
    {
        $css = $this->css();
        $matches = [];
        preg_match_all('/(^|[\r\n}])\s*\.btn\s*\{[^}]*width\s*:\s*100%/i', $css, $matches);
        self::assertCount(0, $matches[0], 'Aturan generik .btn{width:100%} tidak boleh ada');
    }

    public function testFullWidthHanyaViaBtnBlockXs(): void
    {
        $css = $this->css();
        self::assertStringContainsString('.btn-block-xs', $css);
        $pos = strpos($css, '.btn-block-xs');
        $block = substr($css, $pos, (int) strpos($css, '}', $pos) - $pos);
        self::assertStringContainsString('width: 100%', $block);
        self::assertStringContainsString('display: block', $block);
    }

    public function testTombolTabelPaginationCloseWidthAuto(): void
    {
        $css = $this->css();
        foreach (['.table .btn', '.btn-group .btn', '.pagination .page-link', '.close'] as $selector) {
            self::assertStringContainsString($selector, $css, "Selektor {$selector} wajib ada");
        }
        // Blok pengecualian width:auto wajib ada (menetralkan warisan lama).
        self::assertMatchesRegularExpression(
            '/\.table\s+\.btn[\s\S]{0,300}?width\s*:\s*auto/',
            $css,
            '.table .btn wajib width:auto'
        );
    }

    public function testTableResponsiveCard(): void
    {
        $css = $this->css();
        self::assertStringContainsString('.table-responsive-card', $css);
        self::assertStringContainsString('@media (max-width: 767.98px)', $css);
        self::assertMatchesRegularExpression(
            '/\.table-responsive-card tbody td::before\s*\{[^}]*content\s*:\s*attr\(data-label\)/',
            $css,
            'Sel kartu wajib menampilkan label via attr(data-label)'
        );
        self::assertMatchesRegularExpression(
            '/\.table-responsive-card tbody tr\s*\{[^}]*display\s*:\s*block/',
            $css,
            'Baris wajib menjadi kartu (display:block)'
        );
        self::assertMatchesRegularExpression(
            '/\.table-responsive-card tbody tr\s*\{[^}]*border-radius\s*:\s*8px/',
            $css,
            'Kartu wajib border-radius 8px'
        );
    }
}
