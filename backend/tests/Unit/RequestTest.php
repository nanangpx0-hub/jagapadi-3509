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

    public function testUriNormalizesDeploymentPaths(): void
    {
        $cases = [
            ['/index.php', '/api/v1/health?probe=1', '/api/v1/health'],
            ['/app/index.php', '/app/api/v1/health?probe=1', '/api/v1/health'],
            ['/app/index.php', '/application/api/v1/health', '/application/api/v1/health'],
            ['/app/index.php', '/app', '/'],
            ['/app/index.php', '/app/', '/'],
            ['/jagapadi-3509/backend/public/index.php',
                '/jagapadi-3509/backend/public/api/v1/auth/login', '/api/v1/auth/login'],
            ['', '/api/v1/health', '/api/v1/health'],
        ];
        foreach ($cases as [$script, $uri, $expected]) {
            $_SERVER['SCRIPT_NAME'] = $script;
            $_SERVER['REQUEST_URI'] = $uri;
            $this->assertSame($expected, Request::uri());
            $this->assertSame(str_starts_with($expected, '/api/'), Request::isApi());
        }
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
