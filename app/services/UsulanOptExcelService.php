<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class UsulanOptExcelService
{
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;
    public const MAX_IMPORT_ROWS = 5000;
    public const MAX_EXPORT_ROWS = 10000;

    public const IMPORT_HEADERS = [
        'nama_lokal', 'nama_nasional', 'jenis', 'komoditas', 'tanggal_ditemukan',
        'ciri_ciri', 'kabupaten_id', 'kecamatan_id', 'desa_id', 'alamat_lokasi',
        'latitude', 'longitude', 'bagian_terserang', 'pola_gejala',
        'estimasi_terdampak', 'satuan_terdampak', 'tingkat_keyakinan',
        'sumber_identifikasi',
    ];

    /** @return array<int,array<string,mixed>> */
    public function readImportRows(string $path, string $originalName): array
    {
        $this->assertSupportedFile($path, $originalName);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        if ($highestRow < 2) {
            $spreadsheet->disconnectWorksheets();
            throw new InvalidArgumentException('File Excel tidak berisi baris data.');
        }
        if (($highestRow - 1) > self::MAX_IMPORT_ROWS) {
            $spreadsheet->disconnectWorksheets();
            throw new InvalidArgumentException('File melebihi batas ' . self::MAX_IMPORT_ROWS . ' baris data.');
        }

        $headers = [];
        for ($column = 1; $column <= $highestColumn; $column++) {
            $headers[] = $this->normalizeHeader((string) $sheet->getCell([$column, 1])->getValue());
        }
        $this->assertHeaders($headers);

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $row = [];
            $hasValue = false;
            foreach (self::IMPORT_HEADERS as $index => $header) {
                $value = $sheet->getCell([$index + 1, $rowNumber])->getValue();
                if ($value !== null && trim((string) $value) !== '') {
                    $hasValue = true;
                }
                $row[$header] = $header === 'tanggal_ditemukan'
                    ? $this->normalizeDateValue($value)
                    : $this->normalizeCellValue($header, $value);
            }
            if ($hasValue) {
                $row['_excel_row'] = $rowNumber;
                $rows[] = $row;
            }
        }

        $spreadsheet->disconnectWorksheets();
        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function createExportFile(array $rows, string $path): void
    {
        if (count($rows) > self::MAX_EXPORT_ROWS) {
            throw new LengthException('Data ekspor melebihi batas ' . self::MAX_EXPORT_ROWS . ' baris.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usulan OPT');
        $headers = [
            'ID', 'Pengusul', 'Nama Lokal', 'Nama Nasional', 'Jenis', 'Komoditas',
            'Tanggal Ditemukan', 'Kabupaten', 'Kecamatan', 'Desa', 'Alamat',
            'Latitude', 'Longitude', 'Bagian Terserang', 'Pola Gejala',
            'Estimasi Terdampak', 'Satuan', 'Keyakinan', 'Sumber Identifikasi',
            'Ciri-ciri', 'Status', 'Dibuat', 'Diperbarui',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $line = 2;
        foreach ($rows as $row) {
            $values = [
                (int) $row['id'], (string) ($row['nama_pengusul'] ?? ''),
                (string) ($row['nama_lokal'] ?? ''), (string) ($row['nama_nasional'] ?? ''),
                (string) ($row['jenis'] ?? ''), (string) ($row['komoditas'] ?? ''),
                $this->dateForExport($row['tanggal_ditemukan'] ?? null),
                (string) ($row['nama_kabupaten'] ?? ''), (string) ($row['nama_kecamatan'] ?? ''),
                (string) ($row['nama_desa'] ?? ''), (string) ($row['alamat_lokasi'] ?? ''),
                ($row['latitude'] ?? null) !== null ? (float) $row['latitude'] : null,
                ($row['longitude'] ?? null) !== null ? (float) $row['longitude'] : null,
                (string) ($row['bagian_terserang'] ?? ''), (string) ($row['pola_gejala'] ?? ''),
                ($row['estimasi_terdampak'] ?? null) !== null ? (float) $row['estimasi_terdampak'] : null,
                (string) ($row['satuan_terdampak'] ?? ''),
                (string) ($row['tingkat_keyakinan'] ?? ''),
                (string) ($row['sumber_identifikasi'] ?? ''),
                (string) ($row['ciri_ciri'] ?? ''), (string) ($row['status'] ?? ''),
                $this->dateForExport($row['created_at'] ?? null),
                $this->dateForExport($row['updated_at'] ?? null),
            ];
            $sheet->fromArray([$values], null, 'A' . $line);
            foreach ([2, 3, 4, 5, 6, 8, 9, 10, 11, 14, 15, 17, 18, 19, 20, 21] as $column) {
                $sheet->getCell([$column, $line])->setValueExplicit(
                    (string) ($values[$column - 1] ?? ''),
                    DataType::TYPE_STRING
                );
            }
            $line++;
        }

        $lastRow = max(2, $line - 1);
        $sheet->getStyle('A1:W1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:W1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF198754');
        $sheet->getStyle('A1:W1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setAutoFilter('A1:W' . $lastRow);
        $sheet->freezePane('A2');
        foreach (range('A', 'W') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getColumnDimension('K')->setAutoSize(false)->setWidth(30);
        $sheet->getColumnDimension('T')->setAutoSize(false)->setWidth(45);
        $sheet->getStyle('G2:G' . $lastRow)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('L2:M' . $lastRow)->getNumberFormat()->setFormatCode('0.0000000');
        $sheet->getStyle('P2:P' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('V2:W' . $lastRow)->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    public function createTemplateFile(string $path): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import');
        $sheet->fromArray($this->templateRows(), null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count(self::IMPORT_HEADERS));
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF198754');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $lastColumn . '2');
        foreach (range(1, count(self::IMPORT_HEADERS)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(20);
        }
        $sheet->getColumnDimension('F')->setWidth(42);
        $jenisValidation = new DataValidation();
        $jenisValidation->setType(DataValidation::TYPE_LIST);
        $jenisValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $jenisValidation->setAllowBlank(false);
        $jenisValidation->setShowErrorMessage(true);
        $jenisValidation->setShowDropDown(true);
        $jenisValidation->setErrorTitle('Jenis usulan tidak valid');
        $jenisValidation->setError('Gunakan salah satu nilai: hama, penyakit, atau gulma.');
        $jenisValidation->setPromptTitle('Jenis Usulan OPT');
        $jenisValidation->setPrompt('Pilih hama, penyakit, atau gulma.');
        $jenisValidation->setShowInputMessage(true);
        $jenisValidation->setFormula1('"hama,penyakit,gulma"');
        $sheet->setDataValidation('C2:C5001', $jenisValidation);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /** @return array<int,array<int,string>> */
    public function templateRows(): array
    {
        return [
            self::IMPORT_HEADERS,
            ['Wereng cokelat lokal', '', 'hama', 'Padi', date('Y-m-d'),
                'Daun menguning dan tanaman mengering', '', '', '', 'Lokasi pengamatan',
                '-8.1680000', '113.7020000', 'Batang', 'Menguning merata', '1.5',
                'hektare', 'Sedang', 'Pengamatan lapangan'],
        ];
    }

    private function assertSupportedFile(string $path, string $originalName): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('File unggahan tidak dapat dibaca.');
        }
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('Ukuran file harus lebih dari 0 dan maksimal 10 MB.');
        }
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new InvalidArgumentException('Format file harus .xlsx atau .xls.');
        }
        $signature = (string) file_get_contents($path, false, null, 0, 8);
        $isXlsx = str_starts_with($signature, "PK\x03\x04");
        $isXls = str_starts_with($signature, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
        if (($extension === 'xlsx' && !$isXlsx) || ($extension === 'xls' && !$isXls)) {
            throw new InvalidArgumentException('Isi file tidak sesuai dengan ekstensi Excel.');
        }
    }

    /** @param string[] $headers */
    private function assertHeaders(array $headers): void
    {
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }
        if ($headers !== self::IMPORT_HEADERS) {
            throw new InvalidArgumentException(
                'Struktur kolom tidak sesuai. Gunakan template Excel tanpa mengubah urutan header.'
            );
        }
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value)) ?? trim($value);
        return strtolower(preg_replace('/[\s-]+/', '_', $value) ?? $value);
    }

    private function normalizeCellValue(string $header, mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }
        if (in_array($header, ['latitude', 'longitude', 'estimasi_terdampak'], true)) {
            $text = trim((string) $value);
            if (preg_match('/^-?\d+,\d+$/', $text) === 1) {
                return str_replace(',', '.', $text);
            }
        }
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeDateValue(mixed $value): string
    {
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $text = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $text);
            if ($date && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }
        return $text;
    }

    private function dateForExport(mixed $value): mixed
    {
        return $value ? ExcelDate::PHPToExcel(new DateTimeImmutable((string) $value)) : null;
    }
}
