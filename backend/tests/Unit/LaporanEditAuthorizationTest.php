<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\CacheManager;
use App\Core\Database;
use App\Helpers\LaporanPolicy;
use App\Services\LaporanHamaService;
use PHPUnit\Framework\TestCase;

/**
 * Otorisasi edit laporan (Persyaratan fitur edit):
 * - hanya petugas pemilik yang dapat mengubah;
 * - ID peminta dibandingkan dengan user_id tersimpan di database;
 * - manipulasi ID/status/user_id dari client tidak berpengaruh;
 * - lintas petugas menghasilkan 404 anti-enumeration.
 */
class LaporanEditAuthorizationTest extends TestCase
{
    private const ADMIN_ID = 1;      // seeded admin
    private const PETUGAS_A = 2;     // seeded petugas01 (pemilik)
    private const PETUGAS_B = 3;     // seeded petugas02 (bukan pemilik)

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

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        CacheManager::init($cacheDir);

        try {
            Database::connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database tidak tersedia: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdIds !== []) {
            $pdo = Database::connect();
            $in = implode(',', array_fill(0, count($this->createdIds), '?'));
            $pdo->prepare("DELETE FROM activity_log WHERE table_name = 'laporan_hama' AND record_id IN ($in)")
                ->execute($this->createdIds);
            foreach ($this->createdIds as $id) {
                $pdo->prepare(
                    "DELETE FROM notifications
                     WHERE JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.laporan_id')) = ?"
                )->execute([(string) (int) $id]);
            }
            $pdo->prepare("DELETE FROM laporan_hama WHERE id IN ($in)")->execute($this->createdIds);
            $this->createdIds = [];
        }
    }

    // ── Policy murni (tanpa DB) ─────────────────────────────────────────

    public function testPolicyAllowsOwnerOnEditableStatus(): void
    {
        foreach (['Draf', 'Ditolak'] as $status) {
            $this->assertNull(LaporanPolicy::editDenial([
                'user_id' => self::PETUGAS_A,
                'status' => $status,
            ], self::PETUGAS_A));
        }
    }

    public function testPolicyDeniesForeignOwnerWithNotFound(): void
    {
        $denial = LaporanPolicy::editDenial([
            'user_id' => self::PETUGAS_A,
            'status' => 'Draf',
        ], self::PETUGAS_B);

        $this->assertNotNull($denial);
        $this->assertSame(404, $denial['code']);
        $this->assertSame('NotFound', $denial['error']);
    }

    public function testPolicyDeniesNonEditableStatus(): void
    {
        foreach (['Submitted', 'Diverifikasi', 'Diarsipkan'] as $status) {
            $denial = LaporanPolicy::editDenial([
                'user_id' => self::PETUGAS_A,
                'status' => $status,
            ], self::PETUGAS_A);

            $this->assertNotNull($denial);
            $this->assertSame(409, $denial['code']);
        }
    }

    // ── Service dengan DB ───────────────────────────────────────────────

    public function testOwnerCanUpdateOwnDraft(): void
    {
        $id = $this->insertLaporan('Draf');
        $result = LaporanHamaService::updateDraft(
            $id,
            self::PETUGAS_A,
            ['tanggal' => '2026-08-20', 'lokasi' => 'Blok Uji Owner'],
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['code']);
        $row = $this->fetchRow($id);
        $this->assertSame('Blok Uji Owner', $row['lokasi']);
        $this->assertSame((string) self::PETUGAS_A, (string) $row['user_id']);
    }

    public function testCrossPetugasUpdateIsForbidden(): void
    {
        $id = $this->insertLaporan('Draf');
        $before = $this->fetchRow($id);

        $result = LaporanHamaService::updateDraft(
            $id,
            self::PETUGAS_B,
            ['tanggal' => '2026-08-20', 'lokasi' => 'Perubahan Ilegal B'],
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['code']);
        $this->assertSame('NotFound', $result['error']);

        $after = $this->fetchRow($id);
        $this->assertSame($before['lokasi'], $after['lokasi']);
        $this->assertSame($before['updated_at'], $after['updated_at']);
    }

    public function testAdminCannotEditViaPetugasEndpoint(): void
    {
        $id = $this->insertLaporan('Draf');

        $result = LaporanHamaService::updateDraft(
            $id,
            self::ADMIN_ID,
            ['tanggal' => '2026-08-20', 'lokasi' => 'Admin Ilegal'],
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['code']);
    }

    public function testSubmittedReportIsNotEditableByOwner(): void
    {
        $id = $this->insertLaporan('Submitted');

        $result = LaporanHamaService::updateDraft(
            $id,
            self::PETUGAS_A,
            ['tanggal' => '2026-08-20'],
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(409, $result['code']);
        $this->assertSame('Conflict', $result['error']);
    }

    public function testClientSuppliedUserIdAndStatusAreIgnored(): void
    {
        $id = $this->insertLaporan('Draf');

        $result = LaporanHamaService::updateDraft(
            $id,
            self::PETUGAS_A,
            [
                'tanggal' => '2026-08-21',
                'user_id' => self::PETUGAS_B,   // manipulasi kepemilikan
                'status' => 'Diverifikasi',      // manipulasi status
            ],
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertTrue($result['success']);
        $row = $this->fetchRow($id);
        $this->assertSame((string) self::PETUGAS_A, (string) $row['user_id']);
        $this->assertSame('Draf', $row['status']);
    }

    public function testInvalidIdsReturnNotFoundWithoutError(): void
    {
        foreach ([0, -5] as $badId) {
            $result = LaporanHamaService::updateDraft(
                $badId,
                self::PETUGAS_A,
                ['tanggal' => '2026-08-20'],
                '127.0.0.1',
                'PHPUnit'
            );
            $this->assertFalse($result['success']);
            $this->assertSame(404, $result['code']);
        }
    }

    // ── Helper ──────────────────────────────────────────────────────────

    private function insertLaporan(string $status, int $userId = self::PETUGAS_A): int
    {
        $pdo = Database::connect();
        $nomor = $status === 'Draf' ? null : 'LH-' . substr(uniqid(), -14);
        $stmt = $pdo->prepare(
            "INSERT INTO laporan_hama
             (user_id, master_opt_id, tanggal, kabupaten_id, kecamatan_id, desa_id,
              tingkat_keparahan, luas_serangan, populasi, foto_url, nomor_laporan, status, created_at, updated_at)
             VALUES (?, 1, CURDATE(), 1, 1, 1, 'Ringan', 1.00, 1.00, 'assets/uploads/test.jpg', ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$userId, $nomor, $status]);
        $id = (int) $pdo->lastInsertId();
        $this->createdIds[] = $id;
        return $id;
    }

    private function fetchRow(int $id): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM laporan_hama WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        return $row;
    }
}
