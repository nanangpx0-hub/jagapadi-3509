<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\LaporanHama;
use App\Services\LaporanHamaService;
use PHPUnit\Framework\TestCase;

class LaporanListFilterTest extends TestCase
{
    private array $createdIds = [];

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

    private function ensureDb(): void
    {
        try {
            Database::connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database tidak tersedia: ' . $e->getMessage());
        }
    }

    private function insertLaporan(string $status, int $userId = 1): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            "INSERT INTO laporan_hama
             (user_id, master_opt_id, tanggal, kabupaten_id, kecamatan_id, desa_id,
              tingkat_keparahan, luas_serangan, populasi, status, created_at, updated_at)
             VALUES (?, 1, CURDATE(), 1, 1, 1, 'Ringan', 1.00, 1.00, ?, NOW(), NOW())"
        );
        $stmt->execute([$userId, $status]);
        return (int) $pdo->lastInsertId();
    }

    protected function setUp(): void
    {
        $this->ensureDb();
    }

    protected function tearDown(): void
    {
        if ($this->createdIds !== []) {
            $pdo = Database::connect();
            $in = implode(',', array_fill(0, count($this->createdIds), '?'));
            $pdo->prepare("DELETE FROM laporan_hama WHERE id IN ($in)")->execute($this->createdIds);
            $this->createdIds = [];
        }
    }

    public function testListForAdminNoFilterReturnsAllStatuses(): void
    {
        $draf = $this->insertLaporan('Draf');
        $this->createdIds = [$draf];

        $result = LaporanHama::listForAdmin([], 1, 100);
        $statuses = array_column($result['data'], 'status');

        $this->assertContains('Draf', $statuses);
    }

    public function testCommaStatusFilterUsesInClause(): void
    {
        $sub = $this->insertLaporan('Submitted');
        $div = $this->insertLaporan('Diverifikasi');
        $draf = $this->insertLaporan('Draf');
        $this->createdIds = [$sub, $div, $draf];

        $result = LaporanHama::listForAdmin(['status' => 'Submitted,Diverifikasi'], 1, 100);
        $statuses = array_column($result['data'], 'status');

        $this->assertContains('Submitted', $statuses);
        $this->assertContains('Diverifikasi', $statuses);
        $this->assertNotContains('Draf', $statuses);
    }

    public function testServiceDefaultExcludesDraftsForAdmin(): void
    {
        $draf = $this->insertLaporan('Draf');
        $sub = $this->insertLaporan('Submitted');
        $this->createdIds = [$draf, $sub];

        $admin = ['id' => 1, 'role' => 'admin', 'username' => 'admin'];
        $result = LaporanHamaService::listForCurrentUser($admin, ['include_draft' => false]);
        $statuses = array_column($result['data'], 'status');

        $this->assertContains('Submitted', $statuses);
        $this->assertNotContains('Draf', $statuses);
    }

    public function testServiceIncludeDraftShowsDrafts(): void
    {
        $draf = $this->insertLaporan('Draf');
        $this->createdIds = [$draf];

        $admin = ['id' => 1, 'role' => 'admin', 'username' => 'admin'];
        $result = LaporanHamaService::listForCurrentUser($admin, ['include_draft' => true]);
        $statuses = array_column($result['data'], 'status');

        $this->assertContains('Draf', $statuses);
    }

    public function testPetugasListDefaultsToIncludingAllStatuses(): void
    {
        $draf = $this->insertLaporan('Draf', 5);
        $sub = $this->insertLaporan('Submitted', 5);
        $this->createdIds = [$draf, $sub];

        $petugas = ['id' => 5, 'role' => 'petugas', 'username' => 'petugas_test'];
        $result = LaporanHamaService::listForCurrentUser($petugas, []);
        $statuses = array_column($result['data'], 'status');

        $this->assertContains('Draf', $statuses);
        $this->assertContains('Submitted', $statuses);
    }

    public function testPetugasScopingEnforced(): void
    {
        // petugas01 is user id 2 (seeded). Query as admin (id 1) via petugas scope.
        $own = $this->insertLaporan('Submitted', 2);
        $this->createdIds = [$own];

        $result = LaporanHama::listForPetugas(1, [], 1, 100);
        $ids = array_column($result['data'], 'id');
        $this->assertNotContains($own, $ids);
    }
}
