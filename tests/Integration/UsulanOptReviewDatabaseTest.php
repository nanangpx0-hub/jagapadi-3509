<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/DuplicateMasterException.php';
require_once ROOT_PATH . '/app/services/MasterOptService.php';
require_once ROOT_PATH . '/app/services/UsulanOptReviewService.php';

/**
 * UsulanOptReviewDatabaseTest
 *
 * Pengujian perilaku nyata service review Usulan OPT pada database target:
 *  - Persetujuan membuat master aktif + menghubungkan laporan + notifikasi + audit log
 *  - Retry/idempotensi: keputusan kedua tidak menggandakan apa pun
 *  - Duplikat kode/nama tidak menghasilkan master baru maupun error 500
 *  - Merge hanya ke master aktif dengan jenis sama; usulan_opt_id dipertahankan
 *  - Penolakan menuntut alasan >= 10 karakter dan tidak menyentuh master/laporan
 *  - Payload XSS/SQL injection tersimpan apa adanya tanpa membentuk SQL mentah
 */
final class UsulanOptReviewDatabaseTest extends TestCase
{
    private PDO $db;
    private UsulanOptReviewService $service;
    private MasterOptService $masterService;
    private int $adminId;
    private int $petugasAId;
    private int $petugasBId;
    private string $marker;

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $this->adminId = $this->findUserId('admin');
        $this->petugasAId = $this->findUserId('petugas');
        $this->petugasBId = $this->findUserId('petugas', $this->petugasAId);

        if ($this->adminId <= 0 || $this->petugasAId <= 0 || $this->petugasBId <= 0) {
            self::markTestSkipped('Akun admin dan dua petugas diperlukan untuk pengujian usulan OPT.');
        }

        $this->marker = 'CODEX-USLOPT-' . bin2hex(random_bytes(5));
        $this->service = new UsulanOptReviewService($this->db);
        $this->masterService = new MasterOptService($this->db);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        $like = '%' . $this->marker . '%';

        $this->db->prepare('DELETE FROM notifications WHERE body LIKE ?')->execute([$like]);
        $this->db->prepare('DELETE FROM activity_log WHERE description LIKE ?')->execute([$like]);
        $this->db->prepare(
            'DELETE FROM laporan_hama WHERE lokasi LIKE ? OR usulan_opt_id IN (
                SELECT id FROM usulan_opt WHERE nama_lokal LIKE ? OR nama_nasional LIKE ?)'
        )->execute([$like, $like, $like]);
        $this->db->prepare('DELETE FROM usulan_opt WHERE nama_lokal LIKE ? OR nama_nasional LIKE ?')
            ->execute([$like, $like]);
        $this->db->prepare('DELETE FROM master_opt WHERE nama_opt LIKE ? OR nama_lokal LIKE ?')
            ->execute([$like, $like]);
    }

    public function testApproveCreatesActiveMasterRelinksReportsAndIsIdempotent(): void
    {
        $usulanId = $this->createUsulan($this->petugasAId);
        $laporanA = $this->createLaporan($usulanId, $this->petugasAId);
        $laporanB = $this->createLaporan($usulanId, $this->petugasBId);

        $masterData = $this->validMasterData();

        $result = $this->service->approveNew($usulanId, $this->adminId, $masterData, 'Disetujui via test');

        self::assertTrue($result['ok'], 'approveNew harus berhasil');
        $masterId = (int) $result['master_opt_id'];
        self::assertGreaterThan(0, $masterId);
        self::assertSame(2, $result['relinked']);

        $master = $this->fetchRow('SELECT * FROM master_opt WHERE id = ?', [$masterId]);
        self::assertNotNull($master);
        self::assertSame(1, (int) $master['aktif']);
        self::assertSame($masterData['nama_opt'], $master['nama_opt']);
        self::assertSame($masterData['kode_opt'], $master['kode_opt']);
        self::assertSame($masterData['satuan_etl'], $master['satuan_etl']);

        foreach ([$laporanA, $laporanB] as $laporanId) {
            $row = $this->fetchRow('SELECT master_opt_id, usulan_opt_id FROM laporan_hama WHERE id = ?', [$laporanId]);
            self::assertSame($masterId, (int) $row['master_opt_id'], 'Laporan harus terhubung ke master baru');
            self::assertSame($usulanId, (int) $row['usulan_opt_id'], 'usulan_opt_id dipertahankan sebagai audit reference');
        }

        $proposal = $this->fetchRow('SELECT * FROM usulan_opt WHERE id = ?', [$usulanId]);
        self::assertSame(UsulanOptReviewService::STATUS_APPROVED, $proposal['status']);
        self::assertSame($masterId, (int) $proposal['master_opt_id']);
        self::assertSame($this->adminId, (int) $proposal['reviewed_by']);
        self::assertNotNull($proposal['reviewed_at']);

        $notifCount = $this->countRows(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ? AND body LIKE ?',
            [$this->petugasAId, 'usulan_disetujui', '%' . $this->marker . '%']
        );
        self::assertSame(1, $notifCount, 'Notifikasi persetujuan harus tepat satu dan merujuk usulan ini');

        $auditCount = $this->countRows(
            'SELECT COUNT(*) FROM activity_log WHERE table_name = ? AND record_id = ? AND action = ?',
            ['usulan_opt', $usulanId, 'approve']
        );
        self::assertSame(1, $auditCount, 'Audit log approval harus tepat satu');

        $retry = $this->service->approveNew($usulanId, $this->adminId, $masterData, 'Retry');
        self::assertFalse($retry['ok']);
        self::assertSame(UsulanOptReviewService::REASON_ALREADY_REVIEWED, $retry['reason']);

        $mastersWithName = $this->countRows(
            'SELECT COUNT(*) FROM master_opt WHERE LOWER(nama_opt) = LOWER(?)',
            [$masterData['nama_opt']]
        );
        self::assertSame(1, $mastersWithName, 'Retry tidak boleh membuat master duplikat');

        $notifAfterRetry = $this->countRows(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ? AND body LIKE ?',
            [$this->petugasAId, 'usulan_disetujui', '%' . $this->marker . '%']
        );
        self::assertSame(1, $notifAfterRetry, 'Retry tidak boleh menggandakan notifikasi');
    }

    public function testApproveWithDuplicateNameReplacesMasterAndApprovesProposal(): void
    {
        $existingName = $this->marker . '-Wereng';
        $stmt = $this->db->prepare(
            'INSERT INTO master_opt (nama_opt, nama_lokal, jenis, aktif) VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([strtoupper($existingName), null, 'hama']);
        $existingId = (int) $this->db->lastInsertId();
        unset($stmt);

        $usulanId = $this->createUsulan($this->petugasAId);

        $result = $this->service->approveNew(
            $usulanId,
            $this->adminId,
            $this->validMasterData(strtolower($existingName)),
            ''
        );

        self::assertTrue($result['ok']);
        self::assertTrue($result['replaced']);
        self::assertSame($existingId, (int) $result['master_opt_id']);

        $proposal = $this->fetchRow('SELECT status, master_opt_id FROM usulan_opt WHERE id = ?', [$usulanId]);
        self::assertSame(UsulanOptReviewService::STATUS_APPROVED, $proposal['status']);
        self::assertSame($existingId, (int) $proposal['master_opt_id']);

        $master = $this->fetchRow('SELECT nama_opt, nama_lokal FROM master_opt WHERE id = ?', [$existingId]);
        self::assertSame(strtolower($existingName), $master['nama_opt']);
        self::assertSame($this->marker . '-lokal', $master['nama_lokal']);

        $totalMasters = $this->countRows(
            'SELECT COUNT(*) FROM master_opt WHERE nama_opt LIKE ?',
            ['%' . $this->marker . '%']
        );
        self::assertSame(1, $totalMasters, 'Replace harus mempertahankan satu master dan ID yang sama');
    }

    public function testMergeValidatesActiveStatusAndJenis(): void
    {
        $usulanId = $this->createUsulan($this->petugasAId, 'gulma');
        $laporanId = $this->createLaporan($usulanId, $this->petugasAId);

        $inactiveId = $this->insertMaster(['jenis' => 'gulma', 'aktif' => 0]);
        $resultInactive = $this->service->merge($usulanId, $inactiveId, $this->adminId, '');
        self::assertFalse($resultInactive['ok']);
        self::assertSame(UsulanOptReviewService::REASON_MASTER_INVALID, $resultInactive['reason']);

        $wrongJenisId = $this->insertMaster(['jenis' => 'hama', 'aktif' => 1]);
        $resultMismatch = $this->service->merge($usulanId, $wrongJenisId, $this->adminId, '');
        self::assertFalse($resultMismatch['ok']);
        self::assertSame(UsulanOptReviewService::REASON_JENIS_MISMATCH, $resultMismatch['reason']);

        $targetId = $this->insertMaster(['jenis' => 'gulma', 'aktif' => 1]);
        $resultOk = $this->service->merge($usulanId, $targetId, $this->adminId, 'Digabung via test');
        self::assertTrue($resultOk['ok']);
        self::assertSame(1, $resultOk['relinked']);

        $laporan = $this->fetchRow('SELECT master_opt_id, usulan_opt_id FROM laporan_hama WHERE id = ?', [$laporanId]);
        self::assertSame($targetId, (int) $laporan['master_opt_id']);
        self::assertSame($usulanId, (int) $laporan['usulan_opt_id']);

        $proposal = $this->fetchRow('SELECT status FROM usulan_opt WHERE id = ?', [$usulanId]);
        self::assertSame(UsulanOptReviewService::STATUS_MERGED, $proposal['status']);

        $notifCount = $this->countRows(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ? AND body LIKE ?',
            [$this->petugasAId, 'usulan_digabungkan', '%' . $this->marker . '%']
        );
        self::assertSame(1, $notifCount);

        $secondAttempt = $this->service->merge($usulanId, $targetId, $this->adminId, 'double');
        self::assertFalse($secondAttempt['ok']);
        self::assertSame(UsulanOptReviewService::REASON_ALREADY_REVIEWED, $secondAttempt['reason']);
    }

    public function testMergeToMissingMasterFailsSafely(): void
    {
        $usulanId = $this->createUsulan($this->petugasAId);

        $result = $this->service->merge($usulanId, 99999999, $this->adminId, '');

        self::assertFalse($result['ok']);
        self::assertSame(UsulanOptReviewService::REASON_MASTER_INVALID, $result['reason']);
        $status = $this->fetchRow('SELECT status FROM usulan_opt WHERE id = ?', [$usulanId])['status'];
        self::assertSame(UsulanOptReviewService::STATUS_PENDING, $status);
    }

    public function testRejectRequiresMinimumTenCharactersAndLeavesReportsUntouched(): void
    {
        $usulanId = $this->createUsulan($this->petugasAId);
        $laporanId = $this->createLaporan($usulanId, $this->petugasAId);

        try {
            $this->service->reject($usulanId, $this->adminId, 'kurang');
            self::fail('Alasan < 10 karakter harus ditolak');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('minimal 10 karakter', $e->getMessage());
        }

        $statusAfterShort = $this->fetchRow('SELECT status FROM usulan_opt WHERE id = ?', [$usulanId])['status'];
        self::assertSame(UsulanOptReviewService::STATUS_PENDING, $statusAfterShort);

        $result = $this->service->reject($usulanId, $this->adminId, 'Nama terlalu mirip dengan master yang sudah ada.');

        self::assertTrue($result['ok']);

        $proposal = $this->fetchRow('SELECT * FROM usulan_opt WHERE id = ?', [$usulanId]);
        self::assertSame(UsulanOptReviewService::STATUS_REJECTED, $proposal['status']);
        self::assertNull($proposal['master_opt_id']);
        self::assertSame('Nama terlalu mirip dengan master yang sudah ada.', $proposal['catatan_review']);

        $history = $this->fetchRow(
            'SELECT from_status, to_status FROM usulan_opt_status_history WHERE usulan_opt_id = ? ORDER BY id DESC LIMIT 1',
            [$usulanId]
        );
        self::assertNotNull($history, 'Keputusan review harus menulis riwayat status');
        self::assertSame(UsulanOptReviewService::STATUS_PENDING, $history['from_status']);
        self::assertSame(UsulanOptReviewService::STATUS_REJECTED, $history['to_status']);

        $laporan = $this->fetchRow('SELECT master_opt_id, usulan_opt_id FROM laporan_hama WHERE id = ?', [$laporanId]);
        self::assertNull($laporan['master_opt_id'], 'Penolakan tidak boleh mengubah relasi laporan');
        self::assertSame($usulanId, (int) $laporan['usulan_opt_id']);

        $notifCount = $this->countRows(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ? AND body LIKE ?',
            [$this->petugasAId, 'usulan_ditolak', '%' . $this->marker . '%']
        );
        self::assertSame(1, $notifCount);
    }

    public function testHostilePayloadsAreStoredRawWithoutSqlSideEffects(): void
    {
        $payload = "'; DROP TABLE usulan_opt;--<script>alert(1)</script>";
        $usulanId = $this->createUsulan($this->petugasAId, 'hama', $payload);

        $result = $this->service->reject($usulanId, $this->adminId, 'Alasan penolakan uji payload aman.');

        self::assertTrue($result['ok']);
        $proposal = $this->fetchRow('SELECT nama_lokal FROM usulan_opt WHERE id = ?', [$usulanId]);
        self::assertSame($payload, $proposal['nama_lokal'],
            'Payload harus tersimpan mentah (escaping dilakukan saat render)');
        $tableStillExists = $this->countRows(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            ['usulan_opt']
        );
        self::assertSame(1, $tableStillExists, 'Payload SQL injection tidak boleh merusak tabel');
    }

    public function testControllerGuardsAdminOnlyAndPostCsrf(): void
    {
        $controller = file_get_contents(ROOT_PATH . '/app/controllers/UsulanOptController.php');

        self::assertStringContainsString("checkRole(['admin'])", $controller,
            'Semua aksi review wajib admin-only');
        self::assertStringContainsString('requireStateChangingRequest([\'POST\'])', $controller,
            'Mutasi wajib POST + CSRF');
        self::assertStringContainsString('(int) $_SESSION[\'user_id\']', $controller,
            'reviewer diambil dari sesi, bukan input client');

        $routes = require ROOT_PATH . '/config/web_routes.php';
        self::assertSame('UsulanOpt@index', $routes['usulan-opt'] ?? null);
        self::assertSame('UsulanOpt@review', $routes['usulan-opt/review'] ?? null);
        self::assertSame('UsulanOpt@approveNew', $routes['usulan-opt/approve-new'] ?? null);
        self::assertSame('UsulanOpt@searchMaster', $routes['usulan-opt/search-master'] ?? null);
    }

    public function testOwnershipScopingInModelQuery(): void
    {
        $idA = $this->createUsulan($this->petugasAId);
        $idB = $this->createUsulan($this->petugasBId);

        $model = new UsulanOpt();

        $scopedA = $model->paginateFiltered(['q' => $this->marker], 1, 100, $this->petugasAId);
        $idsA = array_map(static fn ($r) => (int) $r['id'], $scopedA['data']);
        self::assertContains($idA, $idsA);
        self::assertNotContains($idB, $idsA, 'Petugas A tidak boleh melihat usulan Petugas B');

        $adminView = $model->paginateFiltered(['q' => $this->marker], 1, 100, null);
        $idsAdmin = array_map(static fn ($r) => (int) $r['id'], $adminView['data']);
        self::assertContains($idA, $idsAdmin);
        self::assertContains($idB, $idsAdmin);

        $statsA = $model->getStats($this->petugasAId);
        $statsB = $model->getStats($this->petugasBId);
        $pendingScopedA = $model->paginateFiltered(
            ['status' => UsulanOptReviewService::STATUS_PENDING, 'q' => $this->marker],
            1, 100, $this->petugasAId
        )['total'];
        $pendingScopedB = $model->paginateFiltered(
            ['status' => UsulanOptReviewService::STATUS_PENDING, 'q' => $this->marker],
            1, 100, $this->petugasBId
        )['total'];

        self::assertSame(1, $pendingScopedA);
        self::assertSame(1, $pendingScopedB);

        $scopedPendingFirst = $model->paginateFiltered(['q' => $this->marker], 1, 2, null);
        if (count($scopedPendingFirst['data']) > 1) {
            self::assertSame(
                UsulanOptReviewService::STATUS_PENDING,
                $scopedPendingFirst['data'][0]['status'],
                'Urutan default memprioritaskan Menunggu Review'
            );
        }
    }

    private function createUsulan(int $userId, string $jenis = 'hama', ?string $customName = null): int
    {
        $name = $customName ?? ($this->marker . '-' . bin2hex(random_bytes(3)));
        $id = (new UsulanOpt())->create([
            'user_id' => $userId,
            'nama_nasional' => $customName ? null : strtoupper($name),
            'nama_lokal' => $name,
            'jenis' => $jenis,
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Ciri pengujian otomatis ' . $this->marker,
            'wilayah' => 'Jember',
            'status' => 'Menunggu Review',
        ]);

        return (int) $id;
    }

    private function createLaporan(int $usulanId, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO laporan_hama (user_id, tanggal, lokasi, tingkat_keparahan, populasi, luas_serangan, status, usulan_opt_id)
             VALUES (?, CURDATE(), ?, ?, 5, 0.5, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $this->marker . '-' . bin2hex(random_bytes(4)),
            'Ringan',
            'Submitted',
            $usulanId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function insertMaster(array $overrides = []): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO master_opt (nama_opt, jenis, aktif) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $this->marker . '-MASTER-' . bin2hex(random_bytes(3)) . ($overrides['suffix'] ?? ''),
            $overrides['jenis'] ?? 'hama',
            $overrides['aktif'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function validMasterData(?string $overrideName = null): array
    {
        return $this->masterService->normalize([
            'kode_opt' => $this->marker . '-K',
            'nama_opt' => $overrideName ?? ($this->marker . ' Wereng Uji'),
            'nama_lokal' => $this->marker . '-lokal',
            'jenis' => 'hama',
            'status_karantina' => 'Tidak',
            'tingkat_bahaya' => 'Sedang',
            'etl_acuan' => '10',
            'satuan_etl' => 'ekor/rumpun',
            'deskripsi' => 'Deskripsi uji',
            'aktif' => '1',
        ]);
    }

    private function fetchRow(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function countRows(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function findUserId(string $role, int $excludeId = 0): int
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE role = ? AND id != ? ORDER BY id LIMIT 1');
        $stmt->execute([$role, $excludeId]);

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
