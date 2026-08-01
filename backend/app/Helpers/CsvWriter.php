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
            $safe[] = \App\Core\Security::sanitizeCell($cell);
        }
        fputcsv($this->handle, $safe, $this->separator);
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
