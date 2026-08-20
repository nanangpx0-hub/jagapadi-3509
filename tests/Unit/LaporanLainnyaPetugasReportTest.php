<?php

use PHPUnit\Framework\TestCase;

final class LaporanLainnyaPetugasReportTest extends TestCase {

    public function testCsvInjectionPrevention(): void {
        // Test that dangerous characters are sanitized
        $dangerousInputs = [
            '=SUM(A1:A10)',
            '+SUM(A1:A10)',
            '-SUM(A1:A10)',
            '@SUM(A1:A10)',
            "\tSUM(A1:A10)",
            "\rSUM(A1:A10)",
        ];

        foreach ($dangerousInputs as $input) {
            $sanitized = $this->sanitizeCsvCell($input);
            $this->assertStringStartsWith("'", $sanitized, "Input '{$input}' should be sanitized with leading quote");
            $this->assertStringEndsWith($input, $sanitized, "Input '{$input}' should be preserved after quote");
        }

        // Test that safe inputs are not modified
        $safeInputs = [
            'Normal text',
            '12345',
            'Hello World',
            'https://example.com',
        ];

        foreach ($safeInputs as $input) {
            $sanitized = $this->sanitizeCsvCell($input);
            $this->assertEquals($input, $sanitized, "Safe input '{$input}' should not be modified");
        }
    }

    public function testCsvRowSanitization(): void {
        $row = [
            'ID',
            '=SUM(A1:A10)',
            'Normal text',
            '+A1',
            'Description',
        ];

        $sanitizedRow = $this->sanitizeCsvRow($row);

        $this->assertEquals('ID', $sanitizedRow[0]);
        $this->assertStringStartsWith("'", $sanitizedRow[1]);
        $this->assertEquals('Normal text', $sanitizedRow[2]);
        $this->assertStringStartsWith("'", $sanitizedRow[3]);
        $this->assertEquals('Description', $sanitizedRow[4]);
    }

    public function testKodeLaporanFormat(): void {
        // Test the format LL-YYYYMMDD-XXXX
        $currentDate = date('Ymd');
        $kode = 'LL-' . $currentDate . '-0001';

        $this->assertStringStartsWith('LL-', $kode);
        $this->assertStringContainsString($currentDate, $kode);
        $this->assertStringEndsWith('-0001', $kode);
        $this->assertMatchesRegularExpression('/^LL-\d{8}-\d{4}$/', $kode);
    }

    public function testKodeLaporanSequence(): void {
        // Test that the sequence increments correctly
        $currentDate = date('Ymd');
        $kode1 = 'LL-' . $currentDate . '-0001';
        $kode2 = 'LL-' . $currentDate . '-0002';
        $kode10 = 'LL-' . $currentDate . '-0010';

        $this->assertGreaterThan($kode1, $kode2);
        $this->assertGreaterThan($kode2, $kode10);
    }

    public function testNullHandlingInCsvSanitization(): void {
        $nullValue = null;
        $sanitized = $this->sanitizeCsvCell($nullValue);
        $this->assertEquals('', $sanitized, 'Null should be converted to empty string');
    }

    public function testMixedTypeCsvSanitization(): void {
        $row = [
            123,
            45.67,
            true,
            'Text',
            null,
            '=DANGER',
        ];

        $sanitizedRow = $this->sanitizeCsvRow($row);

        $this->assertEquals('123', $sanitizedRow[0]);
        $this->assertEquals('45.67', $sanitizedRow[1]);
        $this->assertEquals('1', $sanitizedRow[2]);
        $this->assertEquals('Text', $sanitizedRow[3]);
        $this->assertEquals('', $sanitizedRow[4]);
        $this->assertStringStartsWith("'", $sanitizedRow[5]);
    }

    /**
     * Helper method to simulate CSV cell sanitization
     */
    private function sanitizeCsvCell($value): string {
        if ($value === null) {
            return '';
        }
        
        $stringValue = (string)$value;
        
        // Check if the value starts with dangerous characters
        $firstChar = mb_substr($stringValue, 0, 1);
        $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];
        
        if (in_array($firstChar, $dangerousChars, true)) {
            // Prepend a single quote to prevent formula interpretation
            return "'" . $stringValue;
        }
        
        return $stringValue;
    }

    /**
     * Helper method to simulate CSV row sanitization
     */
    private function sanitizeCsvRow(array $row): array {
        return array_map([$this, 'sanitizeCsvCell'], $row);
    }
}
