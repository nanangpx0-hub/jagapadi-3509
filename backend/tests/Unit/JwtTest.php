<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Jwt;
use PHPUnit\Framework\TestCase;

class JwtTest extends TestCase
{
    private const VALID_SECRET = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2'; // 40 chars

    protected function tearDown(): void
    {
        putenv('JWT_SECRET');
    }

    public function testEncodeWithValidSecret(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['sub' => 1, 'role' => 'petugas']);
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
    }

    public function testDecodeWithValidSecret(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['sub' => 1, 'role' => 'admin', 'username' => 'test']);
        $payload = Jwt::decode($token);
        $this->assertIsArray($payload);
        $this->assertEquals(1, $payload['sub']);
        $this->assertEquals('admin', $payload['role']);
    }

    public function testEncodeThrowsWithEmptySecret(): void
    {
        putenv('JWT_SECRET=');
        $this->expectException(\RuntimeException::class);
        Jwt::encode(['sub' => 1]);
    }

    public function testEncodeThrowsWithShortSecret(): void
    {
        putenv('JWT_SECRET=short'); // only 5 chars
        $this->expectException(\RuntimeException::class);
        Jwt::encode(['sub' => 1]);
    }

    public function testEncodeThrowsWithPlaceholderSecret(): void
    {
        putenv('JWT_SECRET=GANTI_DENGAN_SECRET_MINIMAL_64_KARAKTER_ACAK');
        $this->expectException(\RuntimeException::class);
        Jwt::encode(['sub' => 1]);
    }

    public function testDecodeThrowsWithShortSecret(): void
    {
        putenv('JWT_SECRET=short');
        $this->expectException(\RuntimeException::class);
        Jwt::decode('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjF9.fake');
    }

    public function testDecodeReturnsNullForInvalidToken(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $result = Jwt::decode('invalid.token.here');
        $this->assertNull($result);
    }

    public function testRefreshWithValidToken(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['sub' => 1, 'role' => 'petugas']);
        $refreshed = Jwt::refresh($token);
        $this->assertIsString($refreshed);
    }

    public function testRefreshThrowsWithShortSecret(): void
    {
        putenv('JWT_SECRET=short');
        $this->expectException(\RuntimeException::class);
        Jwt::refresh('some.token.here');
    }
}
