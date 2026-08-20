<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Jwt;
use App\Models\ActivityLog;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class PasswordResetTest extends TestCase
{
    private const TEMP_PASSWORD = 'Jember3509';
    private bool $dbAvailable = true;

    public static function setUpBeforeClass(): void
    {
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
        try {
            Database::connect();
        } catch (\Throwable $e) {
            $this->dbAvailable = false;
        }
    }

    public function testHashPasswordProducesBcryptHash(): void
    {
        $hash = User::hashPassword(self::TEMP_PASSWORD);
        $this->assertStringStartsWith('$2y$12$', $hash);
        $this->assertTrue(password_verify(self::TEMP_PASSWORD, $hash));
    }

    public function testVerifyPasswordSucceedsWithCorrectPassword(): void
    {
        $hash = User::hashPassword(self::TEMP_PASSWORD);
        $this->assertTrue(User::verifyPassword(self::TEMP_PASSWORD, $hash));
    }

    public function testVerifyPasswordFailsWithWrongPassword(): void
    {
        $hash = User::hashPassword(self::TEMP_PASSWORD);
        $this->assertFalse(User::verifyPassword('wrong_password', $hash));
    }

    public function testTempPasswordMeetsMinimumLength(): void
    {
        $this->assertGreaterThanOrEqual(8, strlen(self::TEMP_PASSWORD));
    }

    public function testTempPasswordHasUppercase(): void
    {
        $this->assertMatchesRegularExpression('/[A-Z]/', self::TEMP_PASSWORD);
    }

    public function testTempPasswordHasLowercase(): void
    {
        $this->assertMatchesRegularExpression('/[a-z]/', self::TEMP_PASSWORD);
    }

    public function testTempPasswordHasDigit(): void
    {
        $this->assertMatchesRegularExpression('/[0-9]/', self::TEMP_PASSWORD);
    }

    public function testTempPasswordHasNoSpecialChar(): void
    {
        // Jember3509 does not contain a special char, so it would fail
        // PasswordValidator. This is intentional for a temporary password
        // set by admin — users must change it to a compliant password.
        $this->assertDoesNotMatchRegularExpression('/[^a-zA-Z0-9]/', self::TEMP_PASSWORD);
    }

    public function testJwtIncludesMustChangePasswordClaim(): void
    {
        putenv('JWT_SECRET=test_secret_minimal_64_chars_aaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $token = Jwt::encode([
            'sub' => 1,
            'role' => 'admin',
            'username' => 'admin',
            'must_change_password' => true,
        ]);

        $payload = Jwt::decode($token);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('must_change_password', $payload);
        $this->assertTrue($payload['must_change_password']);

        putenv('JWT_SECRET');
    }

    public function testToPublicArrayIncludesMustChangePassword(): void
    {
        $user = [
            'id' => 1,
            'username' => 'admin',
            'password' => '$2y$12$hash',
            'email' => 'admin@test.local',
            'nama_lengkap' => 'Admin',
            'role' => 'admin',
            'aktif' => 1,
            'must_change_password' => 1,
            'last_password_change_at' => '2026-08-05 10:00:00',
            'created_at' => '2026-01-01 00:00:00',
        ];

        $public = User::toPublicArray($user);
        $this->assertArrayHasKey('must_change_password', $public);
        $this->assertTrue($public['must_change_password']);
    }

    public function testToPublicArrayExcludesPassword(): void
    {
        $user = [
            'id' => 1,
            'username' => 'admin',
            'password' => '$2y$12$secret_hash',
            'email' => 'admin@test.local',
            'nama_lengkap' => 'Admin',
            'role' => 'admin',
            'aktif' => 1,
            'must_change_password' => 1,
            'last_password_change_at' => null,
            'created_at' => '2026-01-01 00:00:00',
        ];

        $public = User::toPublicArray($user);
        $this->assertArrayNotHasKey('password', $public);
    }

    public function testResetPasswordSetsMustChangePasswordFlag(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia untuk test ini');
        }

        // Create a temporary user for testing
        $hash = User::hashPassword('OriginalPass!123');
        $pdo = Database::connect();
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, nama_lengkap, role, aktif, must_change_password) VALUES (?, ?, ?, ?, ?, 1, 0)");
        $stmt->execute(['test_reset_' . bin2hex(random_bytes(4)), $hash, 'test_reset@test.local', 'Test Reset User', 'petugas']);
        $userId = (int) $pdo->lastInsertId();

        try {
            // Verify current state
            $user = User::find($userId);
            $this->assertNotNull($user);
            $this->assertEquals(0, $user['must_change_password']);

            // Reset password
            $newHash = User::hashPassword(self::TEMP_PASSWORD);
            $result = User::resetPassword($userId, $newHash);
            $this->assertTrue($result);

            // Verify new state
            $user = User::find($userId);
            $this->assertEquals(1, $user['must_change_password']);
            $this->assertNotNull($user['last_password_change_at']);
            $this->assertTrue(User::verifyPassword(self::TEMP_PASSWORD, $user['password']));
            $this->assertFalse(User::verifyPassword('OriginalPass!123', $user['password']));

            // Log the password reset
            ActivityLog::log($userId, 'password_reset', 'users', $userId, 'Password direset ke password sementara');
        } finally {
            // Cleanup
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        }
    }

    public function testGetAllByRoleReturnsAllUsers(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia untuk test ini');
        }

        $users = User::getAllByRole();
        $this->assertIsArray($users);
        $this->assertGreaterThan(0, count($users));

        $roles = array_unique(array_column($users, 'role'));
        foreach ($roles as $role) {
            $this->assertContains($role, ['admin', 'petugas', 'operator', 'statistisi', 'viewer']);
        }
    }
}
