<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LaporanHamaArchiveDatabaseTest extends TestCase
{
    private PDO $db;
    private array $createdIds = [];

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        if ($this->createdIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($this->createdIds), '?'));
        $this->db->prepare(
            "DELETE FROM activity_log
             WHERE table_name = 'laporan_hama' AND record_id IN ($placeholders)"
        )->execute($this->createdIds);
        $this->db->prepare(
            "DELETE FROM laporan_hama WHERE id IN ($placeholders)"
        )->execute($this->createdIds);
    }

    public function testSubmittedReportArchivesAtomicallyWithAuditTrail(): void
    {
        $id = $this->insertReport('Submitted');

        $changed = (new LaporanHama())->archive(
            $id,
            1,
            'Arsip regression test',
            '127.0.0.1',
            'phpunit'
        );

        self::assertTrue($changed);
        self::assertSame('Diarsipkan', $this->reportStatus($id));

        $history = $this->db->prepare(
            'SELECT old_status, new_status, changed_by, komentar
             FROM laporan_status_history WHERE laporan_id = ?'
        );
        $history->execute([$id]);
        self::assertSame([
            'old_status' => 'Submitted',
            'new_status' => 'Diarsipkan',
            'changed_by' => 1,
            'komentar' => 'Arsip regression test',
        ], $history->fetch(PDO::FETCH_ASSOC));

        $activity = $this->db->prepare(
            "SELECT action, user_id FROM activity_log
             WHERE table_name = 'laporan_hama' AND record_id = ?"
        );
        $activity->execute([$id]);
        self::assertSame([
            'action' => 'laporan_hama_archived',
            'user_id' => 1,
        ], $activity->fetch(PDO::FETCH_ASSOC));
    }

    public function testLegacyVerifiedReportCanStillBeArchived(): void
    {
        $id = $this->insertReport('Diverifikasi');

        self::assertTrue((new LaporanHama())->archive($id, 1));
        self::assertSame('Diarsipkan', $this->reportStatus($id));
    }

    #[DataProvider('invalidStatusProvider')]
    public function testInactiveStatusesAreRejectedWithoutPartialWrites(string $status): void
    {
        $id = $this->insertReport($status);

        try {
            (new LaporanHama())->archive($id, 1);
            self::fail("Status {$status} seharusnya ditolak");
        } catch (LogicException $e) {
            self::assertStringContainsString('tidak dapat diarsipkan', $e->getMessage());
        }

        self::assertSame($status, $this->reportStatus($id));
        self::assertSame(0, $this->auditCount($id));
    }

    public function testAuditConstraintFailureRollsBackStatusChange(): void
    {
        $id = $this->insertReport('Submitted');

        try {
            (new LaporanHama())->archive($id, 999999999);
            self::fail('Foreign key changed_by seharusnya menolak pengguna yang tidak ada');
        } catch (PDOException) {
            self::assertSame('Submitted', $this->reportStatus($id));
            self::assertSame(0, $this->auditCount($id));
        }
    }

    public function testRepeatedArchiveIsIdempotent(): void
    {
        $id = $this->insertReport('Diarsipkan');

        self::assertFalse((new LaporanHama())->archive($id, 1));
        self::assertSame('Diarsipkan', $this->reportStatus($id));
        self::assertSame(0, $this->auditCount($id));
    }

    public static function invalidStatusProvider(): array
    {
        return [
            'draft' => ['Draf'],
            'rejected' => ['Ditolak'],
        ];
    }

    private function insertReport(string $status): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO laporan_hama
                (user_id, master_opt_id, tanggal, kabupaten_id, kecamatan_id, desa_id,
                 tingkat_keparahan, luas_serangan, populasi, status)
             VALUES (2, 1, CURDATE(), 1, 1, 1, 'Ringan', 1.00, 1.00, ?)"
        );
        $stmt->execute([$status]);
        $id = (int) $this->db->lastInsertId();
        $this->createdIds[] = $id;
        return $id;
    }

    private function reportStatus(int $id): string
    {
        $stmt = $this->db->prepare('SELECT status FROM laporan_hama WHERE id = ?');
        $stmt->execute([$id]);
        return (string) $stmt->fetchColumn();
    }

    private function auditCount(int $id): int
    {
        $history = $this->db->prepare(
            'SELECT COUNT(*) FROM laporan_status_history WHERE laporan_id = ?'
        );
        $history->execute([$id]);
        $activity = $this->db->prepare(
            "SELECT COUNT(*) FROM activity_log
             WHERE table_name = 'laporan_hama' AND record_id = ?"
        );
        $activity->execute([$id]);
        return (int) $history->fetchColumn() + (int) $activity->fetchColumn();
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
