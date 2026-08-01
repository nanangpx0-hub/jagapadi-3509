<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use App\Helpers\JwtBlacklist;
use PHPUnit\Framework\TestCase;

class JwtBlacklistTest extends TestCase
{
    private const VALID_SECRET = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    public static function setUpBeforeClass(): void
    {
        // Set DB connection vars via putenv (bukan Env::load) agar cache Env::$vars
        // tidak mengganggu test lain yang menguji JWT_SECRET via putenv.
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with($line, 'DB_') && str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    putenv(trim($k) . '=' . trim($v));
                }
            }
        }
    }

    protected function setUp(): void
    {
        putenv('JWT_SECRET=' . self::VALID_SECRET);
    }

    protected function tearDown(): void
    {
        putenv('JWT_SECRET');
    }

    private function ensureDb(): bool
    {
        try {
            JwtBlacklist::purgeExpired();
            return true;
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database tidak tersedia untuk JwtBlacklistTest: ' . $e->getMessage());
        }
        return false;
    }

    public function testJtiIsAutoAssignedOnEncode(): void
    {
        $token = \App\Core\Jwt::encode(['sub' => 1, 'role' => 'petugas']);
        $payload = \App\Core\Jwt::decode($token);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $payload['jti']);
    }

    public function testRevokedTokenIsDetected(): void
    {
        if (!$this->ensureDb()) {
            return;
        }

        $jti = bin2hex(random_bytes(16));
        $expiresAt = time() + 3600;

        $this->assertFalse(JwtBlacklist::isRevoked($jti));
        $this->assertTrue(JwtBlacklist::revoke($jti, $expiresAt, 1));
        $this->assertTrue(JwtBlacklist::isRevoked($jti));

        // Cleanup
        $pdo = \App\Core\Database::connect();
        $pdo->prepare("DELETE FROM jwt_blacklist WHERE jti = ?")->execute([$jti]);
    }

    public function testExpiredEntriesArePurged(): void
    {
        if (!$this->ensureDb()) {
            return;
        }

        $pdo = \App\Core\Database::connect();
        $jti = bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO jwt_blacklist (jti, user_id, expires_at) VALUES (?, ?, NOW() - INTERVAL 1 DAY)")
            ->execute([$jti, 1]);

        $deleted = JwtBlacklist::purgeExpired();
        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertFalse(JwtBlacklist::isRevoked($jti));
    }
}
