<?php

declare(strict_types=1);

namespace App\Helpers;

class CsvWriter
{
    private $handle;
    private string $separator;

    public function __construct(string $separator = ',')
    {
        $this->separator = $separator;
    }

    public function open(): void
    {
        $this->handle = fopen('php://output', 'wb');
        if ($this->handle === false) {
            throw new \RuntimeException('Cannot open php://output for CSV writing');
        }
        fwrite($this->handle, "\xEF\xBB\xBF");
    }

    public function writeRow(array $row): void
    {
        $safe = [];
        foreach ($row as $cell) {
            $safe[] = $this->sanitizeCell($cell);
        }
        fputcsv($this->handle, $safe, $this->separator);
    }

    /**
     * Sanitize CSV cell value to prevent CSV injection attacks
     * Prevents cells starting with =, +, -, @, \t, \r from being interpreted as formulas
     * 
     * @param mixed $value The value to sanitize
     * @return string Safe CSV cell value
     */
    private function sanitizeCell($value): string {
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

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
