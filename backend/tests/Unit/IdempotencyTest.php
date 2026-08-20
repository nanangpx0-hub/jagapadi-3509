<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Idempotency;
use PHPUnit\Framework\TestCase;

class IdempotencyTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
    }

    public function testEligibleForApiMutations(): void
    {
        $this->assertTrue(Idempotency::isEligible('POST', '/api/v1/laporan-hama'));
        $this->assertTrue(Idempotency::isEligible('PUT', '/api/v1/laporan-hama/5'));
        $this->assertTrue(Idempotency::isEligible('POST', '/api/v1/laporan-hama/5/submit'));
    }

    public function testNotEligibleForGetsAndAuth(): void
    {
        $this->assertFalse(Idempotency::isEligible('GET', '/api/v1/laporan-hama'));
        $this->assertFalse(Idempotency::isEligible('POST', '/api/v1/auth/login'));
        $this->assertFalse(Idempotency::isEligible('POST', '/api/v1/auth/refresh'));
        $this->assertFalse(Idempotency::isEligible('POST', '/api/v1/device-tokens'));
        $this->assertFalse(Idempotency::isEligible('POST', '/web/dashboard'));
    }

    public function testKeyReturnsNullWithoutHeader(): void
    {
        $this->assertNull(Idempotency::key());
    }

    public function testKeyParsesHeader(): void
    {
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'draft-001';
        $this->assertSame('draft-001', Idempotency::key());
    }

    public function testKeyRejectsWeirdInput(): void
    {
        unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
        $this->assertNull(Idempotency::key());
    }

    public function testHashIsDeterministic(): void
    {
        $payload = ['tanggal' => '2026-08-16', 'master_opt_id' => 3, 'catatan' => 'Hama wereng'];
        $a = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-hama', $payload);
        $b = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-hama', $payload);
        $this->assertSame($a, $b);
    }

    public function testHashIsOrderInsensitive(): void
    {
        $a = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-hama', ['tanggal' => '2026-08-16', 'master_opt_id' => 3]);
        $b = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-hama', ['master_opt_id' => 3, 'tanggal' => '2026-08-16']);
        $this->assertSame($a, $b);
    }

    public function testHashDiffersOnPayloadChange(): void
    {
        $a = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-hama', ['tanggal' => '2026-08-16']);
        $b = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-hama', ['tanggal' => '2026-08-17']);
        $this->assertNotSame($a, $b);
    }

    public function testHashBoundToUserAndPath(): void
    {
        $base = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-hama', ['tanggal' => '2026-08-16']);
        $otherUser = Idempotency::hashRequest(2, 'POST', '/api/v1/laporan-hama', ['tanggal' => '2026-08-16']);
        $otherPath = Idempotency::hashRequest(1, 'POST', '/api/v1/laporan-irigasi', ['tanggal' => '2026-08-16']);
        $this->assertNotSame($base, $otherUser);
        $this->assertNotSame($base, $otherPath);
    }
}