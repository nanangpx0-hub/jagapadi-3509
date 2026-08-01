<?php

declare(strict_types=1);

namespace App\Helpers;

class XlsxWriter
{
    private string $tmpDir;
    private string $outputPath;
    private array $sheets = [];
    private array $headers = [];
    private array $rows = [];

    public function __construct(string $outputPath)
    {
        $this->outputPath = $outputPath;
        $tmp = dirname($outputPath) . '/xlsx_tmp_' . bin2hex(random_bytes(8));
        if (!is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }
        $this->tmpDir = $tmp;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function addRow(array $row): void
    {
        $this->rows[] = $row;
    }

    public function save(): void
    {
        $this->writeContentTypes();
        $this->writeRels();
        $this->writeWorkbook();
        $this->writeWorkbookRels();
        $this->writeStyles();
        $this->writeSheet();
        $this->zipAndCleanup();
    }

    private function writeContentTypes(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
        file_put_contents($this->tmpDir . '/[Content_Types].xml', $xml);
    }

    private function writeRels(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $relsDir = $this->tmpDir . '/_rels';
        if (!is_dir($relsDir)) {
            mkdir($relsDir, 0755, true);
        }
        file_put_contents($relsDir . '/.rels', $xml);
    }

    private function writeWorkbook(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Data" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
        $xlDir = $this->tmpDir . '/xl';
        if (!is_dir($xlDir)) {
            mkdir($xlDir, 0755, true);
        }
        file_put_contents($xlDir . '/workbook.xml', $xml);
    }

    private function writeWorkbookRels(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
        $relsDir = $this->tmpDir . '/xl/_rels';
        if (!is_dir($relsDir)) {
            mkdir($relsDir, 0755, true);
        }
        file_put_contents($relsDir . '/workbook.xml.rels', $xml);
    }

    private function writeStyles(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/></patternFill></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
  </cellXfs>
</styleSheet>';
        file_put_contents($this->tmpDir . '/xl/styles.xml', $xml);
    }

    private function writeSheet(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>' . "\n";

        $sharedStrings = [];
        $stringIndex = 0;
        $stringMap = [];

        if (!empty($this->headers)) {
            $xml .= '    <row r="1">';
            $col = 'A';
            foreach ($this->headers as $header) {
                $safe = \App\Core\Security::sanitizeCell($header);
                $xml .= '<c r="' . $col . '1" t="s" s="1"><v>' . $stringIndex . '</v></c>';
                $sharedStrings[] = $this->escapeXml($safe);
                $stringMap[$safe] = $stringIndex;
                $stringIndex++;
                $col++;
            }
            $xml .= '</row>' . "\n";
        }

        foreach ($this->rows as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;
            $xml .= '    <row r="' . $rowNum . '">';
            $col = 'A';
            foreach ($row as $cellValue) {
                $cellStr = (string) \App\Core\Security::sanitizeCell($cellValue ?? '');
                if (!isset($stringMap[$cellStr])) {
                    $sharedStrings[] = $this->escapeXml($cellStr);
                    $stringMap[$cellStr] = $stringIndex;
                    $stringIndex++;
                }
                $xml .= '<c r="' . $col . $rowNum . '" t="s"><v>' . $stringMap[$cellStr] . '</v></c>';
                $col++;
            }
            $xml .= '</row>' . "\n";
        }

        $xml .= '  </sheetData>
</worksheet>';

        $sheetsDir = $this->tmpDir . '/xl/worksheets';
        if (!is_dir($sheetsDir)) {
            mkdir($sheetsDir, 0755, true);
        }
        file_put_contents($sheetsDir . '/sheet1.xml', $xml);

        $this->writeSharedStrings($sharedStrings);
    }

    private function writeSharedStrings(array $strings): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $xml .= '<si><t>' . $s . '</t></si>';
        }
        $xml .= '</sst>';
        file_put_contents($this->tmpDir . '/xl/sharedStrings.xml', $xml);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function zipAndCleanup(): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ZIP archive: ' . $this->outputPath);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        // Normalize both base and entry to forward slashes so the prefix strip
        // works regardless of OS directory separator (Windows mixes both).
        $baseNorm = rtrim(str_replace('\\', '/', $this->tmpDir), '/');
        foreach ($iterator as $file) {
            $full = $file->getPathname();
            $fullNorm = str_replace('\\', '/', $full);
            $localPath = $fullNorm;
            if (str_starts_with($fullNorm, $baseNorm . '/')) {
                $localPath = substr($fullNorm, strlen($baseNorm . '/'));
            }
            $localPath = ltrim($localPath, '/');
            $zip->addFile($full, $localPath);
        }

        $zip->close();

        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        $files = [];
        $dirs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $dirs[] = $file->getPathname();
            } else {
                $files[] = $file->getPathname();
            }
        }
        // Tutup iterator sebelum menghapus agar handle direktori dilepas (Windows).
        unset($iterator);

        foreach ($files as $f) {
            @unlink($f);
        }
        foreach ($dirs as $d) {
            @rmdir($d);
        }
        @rmdir($dir);
    }

    /**
     * Pastikan direktori tmp sementara selalu dibersihkan meski save() melempar
     * exception sebelum zipAndCleanup() sempat berjalan.
     */
    public function __destruct()
    {
        if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
            try {
                $this->removeDir($this->tmpDir);
            } catch (\Throwable $e) {
                // abaikan kegagalan cleanup
            }
        }
    }
}
