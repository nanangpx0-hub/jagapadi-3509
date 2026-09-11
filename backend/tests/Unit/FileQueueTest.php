<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\FileQueue;
use PHPUnit\Framework\TestCase;

/**
 * Murni file-based: tanpa socket database, tanpa network.
 */
final class FileQueueTest extends TestCase
{
    private string $dir;
    private FileQueue $queue;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/jagapadi-queue-test-' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0755, true);
        $this->queue = new FileQueue($this->dir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function testPushPopAckRoundTrip(): void
    {
        $id = $this->queue->push('scraper', ['type' => 'nasa_wind', 'year' => 2026, 'month' => 1]);
        self::assertNotSame('', $id);
        self::assertSame(1, $this->queue->size('scraper'));

        $job = $this->queue->pop('scraper');
        self::assertNotNull($job);
        self::assertSame($id, $job['id']);
        self::assertSame('nasa_wind', $job['payload']['type']);

        $this->queue->ack('scraper', $id);
        self::assertSame(0, $this->queue->size('scraper'));
        self::assertNull($this->queue->pop('scraper'));
    }

    public function testIdempotencyKeyPreventsDuplicates(): void
    {
        $first = $this->queue->push('scraper', ['type' => 'bps'], 'key-123');
        $second = $this->queue->push('scraper', ['type' => 'bps-changed'], 'key-123');
        self::assertSame($first, $second);
        self::assertSame(1, $this->queue->size('scraper'));
    }

    public function testRetryAndDeadLetterAfterMaxAttempts(): void
    {
        $id = $this->queue->push('scraper', ['type' => 'bps']);
        // Simulasi 5x pop (attempts bertambah) + retry hingga DLQ.
        for ($i = 0; $i < 5; $i++) {
            $job = $this->queue->pop('scraper');
            self::assertNotNull($job);
            $this->queue->retry('scraper', $id, 'simulated failure ' . $i);
            // Paksa available_at ke masa lalu agar pop berikutnya langsung dapat.
            $file = $this->dir . '/scraper/' . $id . '.json';
            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);
                $data['available_at'] = time() - 1;
                file_put_contents($file, json_encode($data), LOCK_EX);
            }
        }
        // Attempt ke-6: retry harus memindahkan ke DLQ.
        $job = $this->queue->pop('scraper');
        if ($job !== null) {
            $this->queue->retry('scraper', $id, 'final failure');
        }
        self::assertSame(0, $this->queue->size('scraper'));
        self::assertFileExists($this->dir . '/scraper_dlq/' . $id . '.json');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
