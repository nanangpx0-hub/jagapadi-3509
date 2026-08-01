<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER = [];
        Env::reset();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        putenv('TRUSTED_PROXIES');
        Env::reset();
        parent::tearDown();
    }

    public function testIpUsesRemoteAddressWhenTrustedProxyListIsNotConfigured(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20, 10.0.0.1';

        $this->assertSame('203.0.113.10', Request::ip());
    }

    public function testIpUsesFirstForwardedAddressOnlyForTrustedProxy(): void
    {
        putenv('TRUSTED_PROXIES=127.0.0.1,::1');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20, 10.0.0.1';

        $this->assertSame('198.51.100.20', Request::ip());
    }

    public function testIpFallsBackToRemoteAddressForMalformedForwardedHeader(): void
    {
        putenv('TRUSTED_PROXIES=127.0.0.1,::1');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';

        $this->assertSame('127.0.0.1', Request::ip());
    }
}
