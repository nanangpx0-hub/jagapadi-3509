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

    public function testDecodeRejectsNoneAlgorithm(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $payload = ['sub' => 1, 'jti' => 'abc', 'iat' => time(), 'exp' => time() + 60];
        $token = self::craftsigned(['alg' => 'none', 'typ' => 'JWT'], $payload);
        $this->assertNull(Jwt::decode($token));
    }

    public function testDecodeRejectsAlgorithmDowngradeFromHS256toNone(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        // Token header HS256 tapi payload kosong tanpa sub/jti.
        $token = Jwt::encode(['sub' => 1, 'role' => 'petugas']);
        $parts = explode('.', $token);
        $payload = ['sub' => 1, 'role' => 'admin', 'exp' => time() + 3600];
        $forged = $parts[0] . '.'
            . rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=')
            . '.' . $parts[2];
        // Signature asli tidak cocok dengan payload baru, harap null.
        $this->assertNull(Jwt::decode($forged));
    }

    public function testDecodeRejectsExpiredToken(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['sub' => 1, 'exp' => time() - 100]);
        $this->assertNull(Jwt::decode($token));
    }

    public function testDecodeRejectsMissingExp(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = self::craftsigned(['alg' => 'HS256', 'typ' => 'JWT'], ['sub' => 1, 'jti' => 'abc', 'iat' => time()]);
        $this->assertNull(Jwt::decode($token));
    }

    public function testDecodeRejectsFutureIat(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['sub' => 1, 'iat' => time() + 99999]);
        $this->assertNull(Jwt::decode($token));
    }

    public function testDecodeRejectsMissingSub(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['role' => 'admin']);
        $this->assertNull(Jwt::decode($token));
    }

    public function testDecodeRejectsMissingJti(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = self::craftsigned(
            ['alg' => 'HS256', 'typ' => 'JWT'],
            ['sub' => 1, 'iat' => time(), 'exp' => time() + 3600]
        );
        $this->assertNull(Jwt::decode($token));
    }

    public function testRefreshIssuesNewJti(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['sub' => 1, 'role' => 'petugas']);
        $old = Jwt::decode($token);
        $this->assertIsArray($old);

        $refreshed = Jwt::refresh($token);
        $this->assertIsString($refreshed);

        $new = Jwt::decode($refreshed);
        $this->assertIsArray($new);
        $this->assertNotSame($old['jti'], $new['jti']);
        $this->assertSame($old['sub'], $new['sub']);
    }

    public function testEncodeCarriesVersionClaim(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
        $token = Jwt::encode(['sub' => 1, 'ver' => 3]);
        $payload = Jwt::decode($token);
        $this->assertIsArray($payload);
        $this->assertSame(3, $payload['ver']);
    }

    private function craftsigned(array $header, array $payload): string
    {
        $segments = [];
        $segments[] = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $segments[] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', implode('.', $segments), self::VALID_SECRET, true);
        $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        return implode('.', $segments);
    }
}
