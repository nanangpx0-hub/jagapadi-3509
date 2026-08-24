<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OtherReportImportServiceTest extends TestCase
{
    private OtherReportImportService $service;

    protected function setUp(): void
    {
        $this->service = new OtherReportImportService();
    }

    public function testRecognizesOnlyRoutableWorkbookTypes(): void
    {
        self::assertTrue($this->service->supports('gangguan_sosial'));
        self::assertTrue($this->service->supports('faktor_abiotik'));
        self::assertFalse($this->service->supports('hama'));
        self::assertFalse($this->service->supports('kategori_tidak_dikenal'));
    }

    /** @dataProvider categoryProvider */
    public function testMapsRowsToCorrectOtherReportCategory(string $jenis, string $name, string $expected): void
    {
        self::assertSame($expected, $this->service->categoryCode([
            'jenis' => $jenis,
            'nama_lokal' => $name,
        ]));
    }

    public static function categoryProvider(): array
    {
        return [
            ['gangguan_sosial', 'Penggembalaan Liar', 'gangguan_sosial'],
            ['faktor_abiotik', 'Kekeringan / Puso', 'faktor_abiotik'],
            ['faktor_abiotik', 'Banjir / Rendaman Air', 'bencana_cuaca'],
            ['faktor_abiotik', 'Rebah Angin / Puting Beliung', 'bencana_cuaca'],
            ['faktor_abiotik', 'Asam-asaman / Keracunan Besi', 'gangguan_fisiologis'],
        ];
    }
}
