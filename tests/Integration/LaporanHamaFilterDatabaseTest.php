<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LaporanHamaFilterDatabaseTest extends TestCase
{
    private PDO $db;
    private int $adminId;
    private int $petugasId;
    private string $marker;
    private array $reportIds = [];

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $this->adminId = $this->findUserId('admin');
        $this->petugasId = $this->findUserId('petugas');

        if ($this->adminId <= 0 || $this->petugasId <= 0) {
            self::markTestSkipped('Akun admin dan petugas diperlukan untuk pengujian filter.');
        }

        $this->marker = 'CODEX-FILTER-' . bin2hex(random_bytes(6));
        $this->db->beginTransaction();

        foreach ([$this->adminId, $this->petugasId] as $userId) {
            foreach (['Draf', 'Submitted', 'Diverifikasi', 'Ditolak'] as $status) {
                $this->reportIds[$userId][$status] = $this->insertReport($userId, $status);
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testAdminActiveFilterReturnsOnlySubmittedAndVerifiedReports(): void
    {
        $result = (new LaporanHama())->fetchPaginated([
            'status' => 'Aktif',
            'search' => $this->marker,
        ], 1, 100);

        self::assertSame(4, $result['total']);
        self::assertEqualsCanonicalizing(
            ['Diverifikasi', 'Submitted'],
            array_values(array_unique(array_column($result['rows'], 'status')))
        );
    }

    public function testPetugasActiveFilterKeepsOwnershipRestriction(): void
    {
        $result = (new LaporanHama())->fetchPaginated([
            'status' => 'active',
            'search' => $this->marker,
        ], 1, 100, $this->petugasId);

        self::assertSame(2, $result['total']);
        self::assertEqualsCanonicalizing([
            $this->reportIds[$this->petugasId]['Submitted'],
            $this->reportIds[$this->petugasId]['Diverifikasi'],
        ], array_map('intval', array_column($result['rows'], 'id')));
    }

    public function testDraftFilterAndServerRenderedFallbackUseTheSameContract(): void
    {
        $model = new LaporanHama();
        $result = $model->fetchPaginated([
            'status' => 'draft',
            'search' => $this->marker,
        ], 1, 100, $this->petugasId);

        self::assertSame(1, $result['total']);
        self::assertSame('Draf', $result['rows'][0]['status']);

        $fallbackRows = $model->getByStatusAndUser('Aktif', $this->petugasId);
        $fallbackIds = array_map('intval', array_column($fallbackRows, 'id'));
        self::assertContains($this->reportIds[$this->petugasId]['Submitted'], $fallbackIds);
        self::assertContains($this->reportIds[$this->petugasId]['Diverifikasi'], $fallbackIds);
        self::assertNotContains($this->reportIds[$this->petugasId]['Ditolak'], $fallbackIds);
    }

    public function testInvalidStatusIsRejectedBeforeQueryExecution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filter status laporan tidak valid.');

        (new LaporanHama())->fetchPaginated([
            'status' => 'status-tidak-dikenal',
            'search' => $this->marker,
        ], 1, 100, $this->petugasId);
    }

    private function insertReport(int $userId, string $status): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO laporan_hama
                (user_id, master_opt_id, tanggal, lokasi, kabupaten_id, kecamatan_id,
                 desa_id, tingkat_keparahan, luas_serangan, populasi, status)
             VALUES (?, 1, CURDATE(), ?, 1, 1, 1, ?, 1.00, 1.00, ?)'
        );
        $severity = $status === 'Ditolak' ? 'Berat' : 'Ringan';
        $stmt->execute([$userId, $this->marker . '-' . $status, $severity, $status]);

        return (int) $this->db->lastInsertId();
    }

    private function findUserId(string $role): int
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1');
        $stmt->execute([$role]);

        return (int) $stmt->fetchColumn();
    }

    private function loadEnvironment(): void
    {
        foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $path) {
            if (!is_file($path)) {
                continue;
            }

            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $value = trim($value, "\"'");
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
