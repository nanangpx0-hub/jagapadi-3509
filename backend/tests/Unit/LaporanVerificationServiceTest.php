<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\CacheManager;
use App\Core\Database;
use App\Models\LaporanHama;
use App\Services\LaporanHamaService;
use PHPUnit\Framework\TestCase;

class LaporanVerificationServiceTest extends TestCase
{
    private array $createdIds = [];
    private const ADMIN_ID = 1;   // seeded admin
    private const PETUGAS_ID = 2; // seeded petugas01

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

    private function insertLaporan(string $status, int $userId = self::PETUGAS_ID): int
    {
        $pdo = Database::connect();
        $nomor = $status === 'Draf' ? null : 'LH-' . substr(uniqid(), -14);
        $stmt = $pdo->prepare(
            "INSERT INTO laporan_hama
             (user_id, master_opt_id, tanggal, kabupaten_id, kecamatan_id, desa_id,
              tingkat_keparahan, luas_serangan, populasi, nomor_laporan, status, created_at, updated_at)
             VALUES (?, 1, CURDATE(), 1, 1, 1, 'Ringan', 1.00, 1.00, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$userId, $nomor, $status]);
        return (int) $pdo->lastInsertId();
    }

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        CacheManager::init($cacheDir);
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

    public function testVerifySubmittedSetsDiverifikasiAndPreservesNomor(): void
    {
        $id = $this->insertLaporan('Submitted');
        $this->createdIds = [$id];

        $result = LaporanHamaService::verify($id, self::ADMIN_ID, 'OK layak', '127.0.0.1', 'test');

        $this->assertTrue($result['success']);
        $laporan = LaporanHama::find($id);
        $this->assertEquals('Diverifikasi', $laporan['status']);
        $this->assertEquals(self::ADMIN_ID, $laporan['verified_by']);
        $this->assertNotNull($laporan['verified_at']);
        $this->assertEquals('OK layak', $laporan['catatan_verifikasi']);
        $this->assertNotEmpty($laporan['nomor_laporan']);
    }

    public function testVerifyNonSubmittedReturnsConflict(): void
    {
        $id = $this->insertLaporan('Draf');
        $this->createdIds = [$id];

        $result = LaporanHamaService::verify($id, self::ADMIN_ID, null, '127.0.0.1', 'test');

        $this->assertFalse($result['success']);
        $this->assertEquals(409, $result['code']);
    }

    public function testRejectSubmittedSetsDitolakWithAlasan(): void
    {
        $id = $this->insertLaporan('Submitted');
        $this->createdIds = [$id];

        $result = LaporanHamaService::reject($id, self::ADMIN_ID, 'Data kurang lengkap, lengkapi', '127.0.0.1', 'test');

        $this->assertTrue($result['success']);
        $laporan = LaporanHama::find($id);
        $this->assertEquals('Ditolak', $laporan['status']);
        $this->assertEquals('Data kurang lengkap, lengkapi', $laporan['catatan_verifikasi']);
    }

    public function testRejectRequiresMinimumAlasan(): void
    {
        $id = $this->insertLaporan('Submitted');
        $this->createdIds = [$id];

        $result = LaporanHamaService::reject($id, self::ADMIN_ID, 'pendek', '127.0.0.1', 'test');

        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['code']);
    }

    public function testArchiveDiverifikasiSetsDiarsipkan(): void
    {
        $id = $this->insertLaporan('Diverifikasi');
        $this->createdIds = [$id];

        $result = LaporanHamaService::archive($id, self::ADMIN_ID, null, '127.0.0.1', 'test');

        $this->assertTrue($result['success']);
        $laporan = LaporanHama::find($id);
        $this->assertEquals('Diarsipkan', $laporan['status']);
    }

    public function testResubmitFromDitolakPreservesNomor(): void
    {
        $id = $this->insertLaporan('Ditolak');
        $this->createdIds = [$id];
        $before = LaporanHama::find($id);
        $nomorBefore = $before['nomor_laporan'];

        $result = LaporanHamaService::resubmit($id, self::PETUGAS_ID, [
            'tanggal' => date('Y-m-d'),
            'master_opt_id' => 1,
            'kabupaten_id' => 1,
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'tingkat_keparahan' => 'Ringan',
            'luas_serangan' => '1.00',
            'populasi' => '1.00',
        ], '127.0.0.1', 'test');

        $this->assertTrue($result['success']);
        $laporan = LaporanHama::find($id);
        $this->assertEquals('Submitted', $laporan['status']);
        $this->assertNull($laporan['verified_by']);
        $this->assertEquals($nomorBefore, $laporan['nomor_laporan']);
    }

    public function testVerifyMissingReportReturnsNotFound(): void
    {
        // Service trusts the caller role; it only fails on a non-existent report.
        $result = LaporanHamaService::verify(99999999, self::ADMIN_ID, null, '127.0.0.1', 'test');

        $this->assertFalse($result['success']);
        $this->assertEquals(404, $result['code']);
    }
}
