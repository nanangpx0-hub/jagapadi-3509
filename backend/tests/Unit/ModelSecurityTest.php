<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

final class ModelSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        // ensure DB connection available for Model db()
        try {
            Database::connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testFindByRejectsInjectionColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::findBy('username` OR `1`=`1', 'admin');
    }

    public function testFindByRejectsInvalidIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::findBy('id; DROP TABLE users; --', '1');
    }

    public function testAllRejectsInjectionOrderBy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::all('id; DROP TABLE users', 'ASC');
    }

    public function testCountRejectsRawWhereFragment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::count("1=1; DROP TABLE users");
    }

    public function testCountAllowsEmptyWhere(): void
    {
        $cnt = User::count();
        $this->assertIsInt($cnt);
        $this->assertGreaterThanOrEqual(0, $cnt);
    }

    public function testCountByWithAllowlistedColumns(): void
    {
        $cnt = User::countBy(['role' => 'admin']);
        $this->assertIsInt($cnt);
    }

    public function testCountByRejectsInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::countBy(['role` OR 1=1 --' => 'admin']);
    }

    public function testInsertRejectsInvalidColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::insert(['username` OR 1=1 --' => 'hacker', 'password' => 'x', 'email' => 'a@b.c', 'nama_lengkap' => 'x', 'role' => 'petugas']);
    }

    public function testUpdateRejectsInvalidColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::update(1, ['username; DROP TABLE users; --' => 'hacker']);
    }

    public function testDashboardCountByFieldRejectsInvalidTable(): void
    {
        $svc = new \App\Services\DashboardService('admin', null, (int)date('Y'));
        $ref = new \ReflectionMethod($svc, 'countByField');
        $ref->setAccessible(true);
        $this->expectException(\InvalidArgumentException::class);
        $ref->invoke($svc, 'users; DROP TABLE users', 'kondisi_fisik');
    }

    public function testDashboardCountByFieldRejectsInvalidField(): void
    {
        $svc = new \App\Services\DashboardService('admin', null, (int)date('Y'));
        $ref = new \ReflectionMethod($svc, 'countByField');
        $ref->setAccessible(true);
        $this->expectException(\InvalidArgumentException::class);
        $ref->invoke($svc, 'laporan_irigasi', 'password');
    }
}
