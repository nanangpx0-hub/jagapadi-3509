<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Security;
use App\Helpers\CsvWriter;
use App\Helpers\XlsxWriter;
use PHPUnit\Framework\TestCase;

class ExportWriterTest extends TestCase
{
    public function testSanitizeCellPrefixesFormulaInjection(): void
    {
        $this->assertSame("'=cmd|/c calc", Security::sanitizeCell('=cmd|/c calc'));
        $this->assertSame("'+SUM(1)", Security::sanitizeCell('+SUM(1)'));
        $this->assertSame("'-2", Security::sanitizeCell('-2'));
        $this->assertSame("'@A1", Security::sanitizeCell('@A1'));
        $this->assertSame("'\tX", Security::sanitizeCell("\tX"));
        // '=' not at the start is safe
        $this->assertSame("'=not leading", Security::sanitizeCell('=not leading'));
    }

    public function testSanitizeCellLeavesSafeValuesUntouched(): void
    {
        $this->assertSame('Normal text', Security::sanitizeCell('Normal text'));
        $this->assertSame('123', Security::sanitizeCell('123'));
        $this->assertSame('', Security::sanitizeCell(''));
        // '=' not at the start is safe
        $this->assertSame('a=1', Security::sanitizeCell('a=1'));
    }

    public function testSanitizeCellPrefixesLeadingEquals(): void
    {
        $this->assertSame("'=not leading", Security::sanitizeCell('=not leading'));
    }

    public function testCsvWriterEscapesFormulaInjection(): void
    {
        $writer = new CsvWriter();
        ob_start();
        $writer->open();
        $writer->writeRow(['Nomor', 'Catatan']);
        $writer->writeRow(['LH-1', '=HYPERLINK("http://evil","klik")']);
        $writer->close();
        $csv = ob_get_clean();

        // BOM + header + data row
        $this->assertStringContainsString("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Nomor', $csv);
    }

    public function testCsvWriterQuotesCommas(): void
    {
        $writer = new CsvWriter();
        ob_start();
        $writer->open();
        $writer->writeRow(['a,b', 'c']);
        $writer->close();
        $csv = ob_get_clean();
        $this->assertStringContainsString('"a,b"', $csv);
    }

    public function testXlsxWriterProducesValidZip(): void
    {
        $tmp = sys_get_temp_dir() . '/jagapadi_xlsx_test_' . bin2hex(random_bytes(4)) . '.xlsx';
        if (file_exists($tmp)) {
            unlink($tmp);
        }

        $writer = new XlsxWriter($tmp);
        $writer->setHeaders(['Nomor', 'Catatan']);
        $writer->addRow(['LH-1', '=HYPERLINK("http://evil","x")']);
        $writer->addRow(['LH-2', 'aman']);
        $writer->save();

        $this->assertFileExists($tmp);

        $zip = new \ZipArchive();
        $this->assertSame(true, $zip->open($tmp) === true);
        $sheetXml = $zip->getFromName('xl/sharedStrings.xml');
        $this->assertNotFalse($sheetXml, 'sharedStrings.xml must exist with relative path');
        $zip->close();

        // Formula-injection cell must be prefixed with single quote (escaped as &apos;)
        // so spreadsheet apps treat it as text, not a formula.
        $this->assertStringContainsString("&apos;=HYPERLINK", $sheetXml);
        $this->assertStringNotContainsString('<t>=HYPERLINK', $sheetXml);

        unlink($tmp);
    }

    public function testXlsxWriterUsesRelativeZipPaths(): void
    {
        $tmp = sys_get_temp_dir() . '/jagapadi_xlsx_test_' . bin2hex(random_bytes(4)) . '.xlsx';
        if (file_exists($tmp)) {
            unlink($tmp);
        }

        $writer = new XlsxWriter($tmp);
        $writer->setHeaders(['A']);
        $writer->addRow(['1']);
        $writer->save();

        $zip = new \ZipArchive();
        $zip->open($tmp);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $joined = implode("\n", $names);
        // Entries must be relative with forward slashes (valid OOXML), not absolute.
        $this->assertStringContainsString('xl/worksheets/sheet1.xml', $joined);
        $this->assertStringNotContainsString(realpath(sys_get_temp_dir()), $joined);
        $this->assertStringNotContainsString('\\', $joined);

        unlink($tmp);
    }

    public function testXlsxWriterCleansUpTempDir(): void
    {
        $tmp = sys_get_temp_dir() . '/jagapadi_xlsx_test_' . bin2hex(random_bytes(4)) . '.xlsx';
        if (file_exists($tmp)) {
            unlink($tmp);
        }

        $writer = new XlsxWriter($tmp);
        $writer->setHeaders(['A']);
        $writer->addRow(['1']);
        $tmpDir = (new \ReflectionObject($writer))->getProperty('tmpDir');
        $tmpDir->setAccessible(true);
        $tmpDirPath = $tmpDir->getValue($writer);
        $this->assertTrue(is_dir($tmpDirPath));

        $writer->save();

        // After save, tmp dir removed
        $this->assertFalse(is_dir($tmpDirPath), 'xlsx_tmp dir should be cleaned after save');

        unlink($tmp);
    }
}
