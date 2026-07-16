<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\NotificationService;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private ?NotificationService $service;
    private static bool $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        try {
            Database::connect();
            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            self::$dbAvailable = false;
        }
    }

    protected function setUp(): void
    {
        if (self::$dbAvailable) {
            $this->service = new NotificationService();
        } else {
            $this->service = null;
        }
    }

    public static function truncateBodyProvider(): array
    {
        return [
            'short string unchanged' => ['Hello world', 'Hello world'],
            'exactly 120 chars' => [str_repeat('a', 120), str_repeat('a', 120)],
            '121 chars truncated' => [str_repeat('a', 121), str_repeat('a', 117) . '...'],
            '200 chars truncated' => [str_repeat('b', 200), str_repeat('b', 117) . '...'],
            'empty string' => ['', ''],
            'unicode short' => ['Laporan diverifikasi', 'Laporan diverifikasi'],
            'unicode truncated' => ['ñ' . str_repeat('ü', 121), 'ñ' . str_repeat('ü', 116) . '...'],
        ];
    }

    /** @dataProvider truncateBodyProvider */
    public function testTruncateBody(string $input, string $expected): void
    {
        $instance = (new \ReflectionClass(NotificationService::class))->newInstanceWithoutConstructor();
        $result = $instance->truncateBody($input);
        $this->assertSame($expected, $result);
    }

    public function testTruncateBodyDefaultLength(): void
    {
        $instance = (new \ReflectionClass(NotificationService::class))->newInstanceWithoutConstructor();
        $result = $instance->truncateBody(str_repeat('x', 150));
        $this->assertSame(120, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testTruncateBodyMethodIsPublic(): void
    {
        $ref = new \ReflectionMethod(NotificationService::class, 'truncateBody');
        $this->assertTrue($ref->isPublic());
    }

    public function testListForUserReturnsCorrectStructure(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $result = $this->service->listForUser(0, 1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('page', $result['meta']);
        $this->assertArrayHasKey('limit', $result['meta']);
        $this->assertArrayHasKey('total', $result['meta']);
        $this->assertArrayHasKey('unread', $result['meta']);
    }

    public function testUnreadCountReturnsInteger(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $count = $this->service->unreadCount(0);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testMarkReadWithNonexistentIdReturnsFalse(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $result = $this->service->markRead(0, 999999);
        $this->assertFalse($result);
    }

    public function testMarkAllReadReturnsInteger(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $count = $this->service->markAllRead(0);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testDeleteForUserWithNonexistentIdReturnsFalse(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $result = $this->service->deleteForUser(0, 999999);
        $this->assertFalse($result);
    }

    public function testGetRecentForUserReturnsArray(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $items = $this->service->getRecentForUser(0, 5);
        $this->assertIsArray($items);
    }

    public function testPruneOlderThanReturnsInteger(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $count = $this->service->pruneOlderThan(90);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testNotifyUserDoesNotThrow(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $this->service->notifyUser(1, 'test_type', 'Test Title', 'Test body body body.');
        $this->assertTrue(true);
    }

    public function testNotifyUserWithDataJson(): void
    {
        if ($this->service === null) {
            $this->markTestSkipped('Database not available');
        }

        $this->service->notifyUser(1, 'test_type', 'Title', 'Body', [
            'entity' => 'hama',
            'laporan_id' => 1,
            'nomor_laporan' => 'LH-2026-0001',
            'status' => 'Submitted',
            'web_path' => '/laporan-hama/1',
            'api_path' => '/api/v1/laporan-hama/1',
        ]);
        $this->assertTrue(true);
    }
}
