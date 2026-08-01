<?php

use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase {
    private string $logFile;

    protected function setUp(): void {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jagapadi-logger-tests';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->logFile = $dir . DIRECTORY_SEPARATOR . uniqid('app-', true) . '.log';
        Logger::setLogFile($this->logFile);
    }

    protected function tearDown(): void {
        Logger::reset();

        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testInfoWritesStructuredJsonLog(): void {
        Logger::info('Test log entry', ['module' => 'unit']);

        self::assertFileExists($this->logFile);

        $line = trim((string)file_get_contents($this->logFile));
        $entry = json_decode($line, true);

        self::assertIsArray($entry);
        self::assertSame('INFO', $entry['level']);
        self::assertSame('Test log entry', $entry['message']);
        self::assertSame('unit', $entry['context']['module']);
        self::assertArrayHasKey('timestamp', $entry);
    }

    public function testGetRecentCanFilterByLevel(): void {
        Logger::info('Info entry');
        Logger::warning('Warning entry', ['code' => 'W01']);

        $warnings = Logger::getRecent(10, 'WARNING');

        self::assertCount(1, $warnings);
        self::assertSame('WARNING', $warnings[0]['level']);
        self::assertSame('W01', $warnings[0]['context']['code']);
    }

    public function testClearEmptiesLogFile(): void {
        Logger::debug('Debug entry');

        self::assertNotSame('', file_get_contents($this->logFile));
        self::assertTrue(Logger::clear());
        self::assertSame('', file_get_contents($this->logFile));
    }
}
