<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class UsulanOptExcelServiceTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testReadsXlsxAndNormalizesNonstandardDateAndDecimalComma(): void
    {
        $file = $this->makeWorkbook('Xlsx', [
            UsulanOptExcelService::IMPORT_HEADERS,
            ['Wereng', '', 'hama', 'Padi', '24/08/2026', 'Daun menguning', '', '', '', '', '-8,168', '113,702'],
        ]);

        $rows = (new UsulanOptExcelService())->readImportRows($file, 'usulan.xlsx');

        self::assertCount(1, $rows);
        self::assertSame('2026-08-24', $rows[0]['tanggal_ditemukan']);
        self::assertSame('-8.168', $rows[0]['latitude']);
        self::assertSame('113.702', $rows[0]['longitude']);
        self::assertSame(2, $rows[0]['_excel_row']);
    }

    public function testReadsLegacyXls(): void
    {
        $file = $this->makeWorkbook('Xls', [
            UsulanOptExcelService::IMPORT_HEADERS,
            ['Wereng', '', 'hama', 'Padi', '2026-08-24', 'Daun menguning'],
        ]);

        $rows = (new UsulanOptExcelService())->readImportRows($file, 'usulan.xls');
        self::assertCount(1, $rows);
        self::assertSame('Wereng', $rows[0]['nama_lokal']);
    }

    public function testRejectsSpoofedExtensionAndWrongHeaders(): void
    {
        $spoofed = $this->tempFile();
        file_put_contents($spoofed, 'not an excel workbook');
        try {
            (new UsulanOptExcelService())->readImportRows($spoofed, 'malicious.xlsx');
            self::fail('Spoofed workbook should fail.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('ekstensi', $e->getMessage());
        }

        $badHeader = $this->makeWorkbook('Xlsx', [['nama_salah'], ['value']]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Struktur kolom tidak sesuai');
        (new UsulanOptExcelService())->readImportRows($badHeader, 'bad.xlsx');
    }

    public function testCreatesTypedFilteredExportWorkbook(): void
    {
        $output = $this->tempFile();
        (new UsulanOptExcelService())->createExportFile([[
            'id' => 7,
            'nama_pengusul' => 'Petugas A',
            'nama_lokal' => '=HYPERLINK("https://example.invalid","Wereng")',
            'nama_nasional' => '',
            'jenis' => 'hama',
            'komoditas' => 'Padi',
            'tanggal_ditemukan' => '2026-08-24',
            'latitude' => '-8.168',
            'longitude' => '113.702',
            'estimasi_terdampak' => '1.50',
            'status' => 'Draf',
            'created_at' => '2026-08-24 10:00:00',
            'updated_at' => '2026-08-24 10:00:00',
        ]], $output);

        $book = IOFactory::load($output);
        $sheet = $book->getActiveSheet();
        self::assertSame('ID', $sheet->getCell('A1')->getValue());
        self::assertSame(7, $sheet->getCell('A2')->getValue());
        self::assertSame('s', $sheet->getCell('C2')->getDataType());
        self::assertSame('=HYPERLINK("https://example.invalid","Wereng")', $sheet->getCell('C2')->getValue());
        self::assertIsFloat($sheet->getCell('L2')->getValue());
        self::assertSame('yyyy-mm-dd', $sheet->getStyle('G2')->getNumberFormat()->getFormatCode());
        self::assertSame('A1:W2', $sheet->getAutoFilter()->getRange());
        $book->disconnectWorksheets();
    }

    public function testTemplateRestrictsJenisToSupportedOptCategories(): void
    {
        $output = $this->tempFile();
        (new UsulanOptExcelService())->createTemplateFile($output);

        $book = IOFactory::load($output);
        $validation = $book->getActiveSheet()->getDataValidation('C55');
        self::assertSame('list', $validation->getType());
        self::assertSame('"hama,penyakit,gulma"', $validation->getFormula1());
        self::assertTrue($validation->getShowErrorMessage());
        $book->disconnectWorksheets();
    }

    public function testRejectsOversizedImportAndExportBeforeProcessing(): void
    {
        $large = $this->tempFile();
        $handle = fopen($large, 'wb');
        self::assertIsResource($handle);
        fwrite($handle, "PK\x03\x04");
        fseek($handle, UsulanOptExcelService::MAX_FILE_SIZE);
        fwrite($handle, 'x');
        fclose($handle);

        try {
            (new UsulanOptExcelService())->readImportRows($large, 'large.xlsx');
            self::fail('Oversized import should fail.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('maksimal 10 MB', $e->getMessage());
        }

        $this->expectException(LengthException::class);
        (new UsulanOptExcelService())->createExportFile(
            array_fill(0, UsulanOptExcelService::MAX_EXPORT_ROWS + 1, []),
            $this->tempFile()
        );
    }

    /** @param array<int,array<int,mixed>> $rows */
    private function makeWorkbook(string $writerType, array $rows): string
    {
        $file = $this->tempFile();
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($rows, null, 'A1');
        IOFactory::createWriter($book, $writerType)->save($file);
        $book->disconnectWorksheets();
        return $file;
    }

    private function tempFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'jagapadi_excel_test_');
        self::assertNotFalse($file);
        $this->files[] = $file;
        return $file;
    }
}
