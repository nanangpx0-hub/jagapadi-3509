<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testWithoutSensitiveFieldsRemovesPassword(): void
    {
        $user = [
            'id' => 1,
            'username' => 'test',
            'password' => '$2y$12$hashedpassword123',
            'role' => 'petugas',
            'aktif' => 1,
        ];

        $cleaned = User::withoutSensitiveFields($user);

        $this->assertArrayNotHasKey('password', $cleaned);
        $this->assertEquals(1, $cleaned['id']);
        $this->assertEquals('test', $cleaned['username']);
        $this->assertEquals('petugas', $cleaned['role']);
    }

    public function testWithoutSensitiveFieldsKeepsOtherFields(): void
    {
        $user = [
            'id' => 2,
            'username' => 'admin',
            'password' => '$2y$12$anotherhash',
            'email' => 'admin@test.local',
            'nama_lengkap' => 'Admin',
            'role' => 'admin',
            'aktif' => 1,
            'must_change_password' => 0,
        ];

        $cleaned = User::withoutSensitiveFields($user);

        $this->assertArrayNotHasKey('password', $cleaned);
        $this->assertArrayHasKey('email', $cleaned);
        $this->assertArrayHasKey('nama_lengkap', $cleaned);
        $this->assertArrayHasKey('must_change_password', $cleaned);
    }

    public function testToPublicArrayNeverContainsPassword(): void
    {
        $user = [
            'id' => 3,
            'username' => 'petugas',
            'password' => '$2y$12$shouldnotappear',
            'email' => 'petugas@test.local',
            'nama_lengkap' => 'Petugas',
            'role' => 'petugas',
            'aktif' => 1,
            'must_change_password' => 0,
            'last_password_change_at' => null,
            'created_at' => '2026-01-01 00:00:00',
        ];

        $public = User::toPublicArray($user);

        $this->assertArrayNotHasKey('password', $public);
    }
}
