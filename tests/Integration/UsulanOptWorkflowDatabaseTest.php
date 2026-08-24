<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/DuplicateMasterException.php';
require_once ROOT_PATH . '/app/services/MasterOptService.php';
require_once ROOT_PATH . '/app/services/UsulanOptReviewService.php';
require_once ROOT_PATH . '/app/services/UsulanOptService.php';

/**
 * UsulanOptWorkflowDatabaseTest
 *
 * Workflow Draf â†’ Menunggu Review â†’ Perlu Perbaikan/Disetujui/Digabungkan/
 * Ditolak Permanen pada database nyata: transisi status, riwayat, notifikasi,
 * audit, idempotensi/konkurensi, rollback penuh, dan ownership di lapisan service.
 */
final class UsulanOptWorkflowDatabaseTest extends TestCase
{
    private PDO $db;
    private UsulanOptService $service;
    private UsulanOptReviewService $review;
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
            self::markTestSkipped('Akun admin dan dua petugas diperlukan.');
        }

        $this->marker = 'CODEX-USLW-' . bin2hex(random_bytes(5));
        $this->service = new UsulanOptService($this->db);
        $this->review = new UsulanOptReviewService($this->db);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        $like = '%' . $this->marker . '%';

        $this->db->prepare('DELETE FROM notifications WHERE body LIKE ? OR data_json LIKE ?')
            ->execute([$like, $like]);
        $this->db->prepare('DELETE FROM activity_log WHERE description LIKE ?')->execute([$like]);
        $this->db->prepare('DELETE FROM laporan_hama WHERE lokasi LIKE ?')->execute([$like]);
        $this->db->prepare('DELETE FROM usulan_opt WHERE nama_lokal LIKE ?')->execute([$like]);
        $this->db->prepare('DELETE FROM master_opt WHERE nama_opt LIKE ?')->execute([$like]);
    }

    public function testCreateDraftWritesInitialHistory(): void
    {
        $id = $this->createDraft($this->petugasAId);

        $proposal = $this->fetch('SELECT * FROM usulan_opt WHERE id = ?', [$id]);
        self::assertSame(UsulanOpt::STATUS_DRAFT, $proposal['status']);
        self::assertSame((string) $this->marker, substr((string) $proposal['nama_lokal'], 0, strlen($this->marker)));
        self::assertNull($proposal['submitted_at']);

        $history = $this->fetch(
            'SELECT from_status, to_status FROM usulan_opt_status_history WHERE usulan_opt_id = ?',
            [$id]
        );
        self::assertNotNull($history);
        self::assertNull($history['from_status']);
        self::assertSame(UsulanOpt::STATUS_DRAFT, $history['to_status']);

        $audit = $this->countRows('SELECT COUNT(*) FROM activity_log WHERE table_name=? AND record_id=? AND action=?', ['usulan_opt', $id, 'create_draft']);
        self::assertSame(1, $audit);
    }

    public function testSubmitWithoutWilayahOrPhotoFailsValidationAndStaysDraft(): void
    {
        $id = $this->createDraft($this->petugasAId);

        $result = $this->service->submitDraft($id, $this->petugasAId, $this->petugasAId);

        self::assertFalse($result['ok']);
        self::assertSame(UsulanOptService::REASON_INVALID, $result['reason']);
        self::assertContains('Kabupaten wajib dipilih saat mengirim review', $result['errors'] ?? []);
        self::assertContains('Minimal satu foto bukti wajib dilampirkan saat mengirim review', $result['errors'] ?? []);

        $status = $this->fetch('SELECT status FROM usulan_opt WHERE id = ?', [$id])['status'];
        self::assertSame(UsulanOpt::STATUS_DRAFT, $status);
    }

    public function testSubmitSuccessTransitionsNotifiesAndAudits(): void
    {
        $id = $this->createCompletePendingReadyDraft();

        $result = $this->service->submitDraft($id, $this->petugasAId, $this->petugasAId);

        self::assertTrue($result['ok'], json_encode($result['errors'] ?? []));

        $proposal = $this->fetch('SELECT * FROM usulan_opt WHERE id = ?', [$id]);
        self::assertSame(UsulanOpt::STATUS_PENDING, $proposal['status']);
        self::assertNotNull($proposal['submitted_at']);

        self::assertSame(1, $this->countRows(
            'SELECT COUNT(*) FROM notifications WHERE user_id=? AND type=? AND body LIKE ?',
            [$this->petugasAId, 'usulan_diterima', '%' . $this->marker . '%']
        ));

        self::assertSame(1, $this->countRows(
            'SELECT COUNT(*) FROM activity_log WHERE table_name=? AND record_id=? AND action=?',
            ['usulan_opt', $id, 'submit']
        ));

        $historyCount = (int) $this->fetch(
            'SELECT COUNT(*) c FROM usulan_opt_status_history WHERE usulan_opt_id = ? AND to_status = ?',
            [$id, UsulanOpt::STATUS_PENDING]
        )['c'];
        self::assertSame(1, $historyCount);
    }

    public function testUpdateAfterStatusChangedReturnsConflictAndNeverOverwritesDecision(): void
    {
        $id = $this->createDraft($this->petugasAId);

        $okUpdate = $this->service->updateProposal(
            $id,
            $this->petugasAId,
            UsulanOpt::STATUS_DRAFT,
            $this->payload(['nama_lokal' => $this->marker . '-revisi']),
            $this->petugasAId
        );
        self::assertTrue($okUpdate['ok'], json_encode($okUpdate));

        $stmt = $this->db->prepare("UPDATE usulan_opt SET status = ? WHERE id = ?");
        $stmt->execute([UsulanOpt::STATUS_APPROVED, $id]);

        $conflict = $this->service->updateProposal(
            $id,
            $this->petugasAId,
            UsulanOpt::STATUS_DRAFT,
            $this->payload(),
            $this->petugasAId
        );

        self::assertFalse($conflict['ok']);
        self::assertSame(UsulanOptService::REASON_STATUS_CONFLICT, $conflict['reason']);
        self::assertSame(UsulanOpt::STATUS_APPROVED, $this->fetch('SELECT status FROM usulan_opt WHERE id=?', [$id])['status'],
            'Keputusan Admin tidak boleh tertimpa update Petugas');
    }

    public function testRequestRevisionFlowWithEditAndResubmit(): void
    {
        $id = $this->createSubmittedPendingProposal();

        try {
            $this->review->requestRevision($id, $this->adminId, 'singkat');
            self::fail('Catatan <10 karakter harus ditolak');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('minimal 10 karakter', $e->getMessage());
        }

        $draftAttempt = $this->review->requestRevision($this->createDraft($this->petugasAId), $this->adminId, 'Catatan cukup panjang.');
        self::assertFalse($draftAttempt['ok'], 'Draf tidak boleh direview');

        $revision = $this->review->requestRevision($id, $this->adminId, 'Lengkapi foto dan keterangan lokasi.');
        self::assertTrue($revision['ok']);

        $proposal = $this->fetch('SELECT * FROM usulan_opt WHERE id = ?', [$id]);
        self::assertSame(UsulanOpt::STATUS_REVISION, $proposal['status']);
        self::assertSame($this->adminId, (int) $proposal['reviewed_by']);
        self::assertSame('Lengkapi foto dan keterangan lokasi.', $proposal['catatan_review']);

        self::assertSame(1, $this->countRows(
            'SELECT COUNT(*) FROM notifications WHERE user_id=? AND type=? AND body LIKE ?',
            [$this->petugasAId, 'usulan_perlu_perbaikan', '%' . $this->marker . '%']
        ));

        $editAllowed = $this->service->updateProposal(
            $id,
            $this->petugasAId,
            UsulanOpt::STATUS_REVISION,
            $this->payload(array_merge($this->firstWilayahIds(), [
                'alamat_lokasi' => 'Blo sawah timur sudah dilengkapi',
            ])),
            $this->petugasAId
        );
        self::assertTrue($editAllowed['ok'], 'Petugas boleh mengedit saat Perlu Perbaikan');

        $resubmit = $this->service->resubmit($id, $this->petugasAId, $this->petugasAId);
        self::assertTrue($resubmit['ok'], json_encode($resubmit['errors'] ?? []));

        $afterResubmit = $this->fetch('SELECT * FROM usulan_opt WHERE id = ?', [$id]);
        self::assertSame(UsulanOpt::STATUS_PENDING, $afterResubmit['status']);
        self::assertNull($afterResubmit['catatan_review'], 'Catatan revision dibersihkan setelah resubmit');

        self::assertSame(1, $this->countRows(
            'SELECT COUNT(*) FROM notifications n JOIN users u ON u.role=\'admin\' WHERE n.type=? AND n.body LIKE ? LIMIT 1',
            ['usulan_dikirim_ulang', '%' . $this->marker . '%']
        ) > 0 ? 1 : 0);

        $revisions = (int) $this->fetch(
            'SELECT COUNT(*) c FROM usulan_opt_status_history WHERE usulan_opt_id=? AND to_status=?',
            [$id, UsulanOpt::STATUS_PENDING]
        )['c'];
        self::assertSame(2, $revisions, 'Riwayat submit awal + resubmit tercatat terpisah');

        $secondResubmit = $this->service->resubmit($id, $this->petugasAId, $this->petugasAId);
        self::assertFalse($secondResubmit['ok']);
        self::assertSame(UsulanOptService::REASON_STATUS_CONFLICT, $secondResubmit['reason']);
    }

    public function testApproveRelinksReportsAndWritesHistoryThenPermanentRejectPath(): void
    {
        $id = $this->createSubmittedPendingProposal();
        $laporanId = $this->attachReportToUsulan($id);

        $masterData = (new MasterOptService($this->db))->normalize([
            'kode_opt' => $this->marker . '-K',
            'nama_opt' => strtoupper($this->marker) . ' Wereng Master',
            'jenis' => 'hama',
            'aktif' => '1',
        ]);

        $result = $this->review->approveNew($id, $this->adminId, $masterData, 'Setujui via workflow test');

        self::assertTrue($result['ok'], json_encode(array_diff_key($result, [])));
        $masterId = (int) $result['master_opt_id'];
        self::assertSame(1, $result['relinked']);

        $laporan = $this->fetch('SELECT master_opt_id, usulan_opt_id FROM laporan_hama WHERE id = ?', [$laporanId]);
        self::assertSame($masterId, (int) $laporan['master_opt_id']);
        self::assertSame($id, (int) $laporan['usulan_opt_id'], 'usulan_opt_id dipertahankan sebagai audit reference');

        $finalHistory = $this->fetch(
            'SELECT to_status FROM usulan_opt_status_history WHERE usulan_opt_id=? ORDER BY id DESC LIMIT 1',
            [$id]
        );
        self::assertSame(UsulanOpt::STATUS_APPROVED, $finalHistory['to_status']);

        $retry = $this->review->approveNew($id, $this->adminId, $masterData, 'retry');
        self::assertFalse($retry['ok']);
        self::assertSame(UsulanOptReviewService::REASON_ALREADY_REVIEWED, $retry['reason']);
        self::assertSame(1, $this->countRows(
            'SELECT COUNT(*) FROM master_opt WHERE LOWER(nama_opt)=LOWER(?)',
            [strtoupper($this->marker) . ' Wereng Master']
        ));
    }

    public function testDeleteDraftOnlyOwnDraft(): void
    {
        $ownDraft = $this->createDraft($this->petugasAId);

        $forbidden = $this->service->deleteDraft($ownDraft, $this->petugasBId, $this->petugasBId);
        self::assertFalse($forbidden['ok']);
        self::assertSame(UsulanOptService::REASON_FORBIDDEN, $forbidden['reason']);

        $deleted = $this->service->deleteDraft($ownDraft, $this->petugasAId, $this->petugasAId);
        self::assertTrue($deleted['ok']);
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM usulan_opt WHERE id=?', [$ownDraft]));

        $pendingId = $this->createSubmittedPendingProposal();
        $notDeletable = $this->service->deleteDraft($pendingId, $this->petugasAId, $this->petugasAId);
        self::assertFalse($notDeletable['ok']);
        self::assertSame(UsulanOptService::REASON_STATUS_CONFLICT, $notDeletable['reason']);
    }

    public function testOwnershipGuardsOnServiceActions(): void
    {
        $id = $this->createDraft($this->petugasAId);

        foreach ([
            ['updateProposal', [$id, $this->petugasBId, UsulanOpt::STATUS_DRAFT, $this->payload(), $this->petugasBId]],
            ['submitDraft', [$id, $this->petugasBId, $this->petugasBId]],
            ['resubmit', [$id, $this->petugasBId, $this->petugasBId]],
        ] as [$method, $args]) {
            $result = $this->service->{$method}(...$args);
            self::assertFalse($result['ok'], "{$method} lintas pemilik harus gagal");
            self::assertSame(UsulanOptService::REASON_FORBIDDEN, $result['reason']);
        }
    }

    public function testFullTransactionRollbackLeavesNoOrphanUsulan(): void
    {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $usulanId = $this->createDraft($this->petugasAId);
            self::assertGreaterThan(0, $usulanId);

            $stmt = $db->prepare('INSERT INTO usulan_opt_photos (usulan_opt_id, file_path) VALUES (?, ?)');
            $stmt->execute([999999999, 'public/uploads/usulan-opt/x/y.jpg']);
            self::fail('FK violation harus terjadi untuk usulan yang tidak ada');
        } catch (PDOException $expected) {
            $db->rollBack();
        } catch (Throwable $unexpected) {
            $db->rollBack();
            throw $unexpected;
        }

        $leftover = $this->countRows('SELECT COUNT(*) FROM usulan_opt WHERE user_id=? AND nama_lokal LIKE ?', [$this->petugasAId, '%' . $this->marker . '%']);
        self::assertSame(0, $leftover, 'Rollback penuh tidak meninggalkan usulan yatim');
    }

    // ==================== helpers ====================

    private function payload(array $overrides = []): array
    {
        return $this->service->normalize(array_merge([
            'nama_lokal' => $this->marker . '-' . bin2hex(random_bytes(3)),
            'nama_nasional' => strtoupper($this->marker),
            'jenis' => 'hama',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Ciri uji otomatis ' . $this->marker,
            'wilayah' => 'Jember',
            'tanggal_ditemukan' => date('Y-m-d'),
        ], $overrides));
    }

    private function createDraft(int $ownerId): int
    {
        return $this->service->createDraft($ownerId, $this->payload(), $ownerId);
    }

    private function createCompletePendingReadyDraft(): int
    {
        $ids = $this->firstWilayahIds();
        $data = $this->payload(array_merge($ids, [
            'latitude' => '-8.172',
            'longitude' => '113.7',
            'estimasi_terdampak' => '1.5',
            'satuan_terdampak' => 'hektare',
            'tingkat_keyakinan' => 'Tinggi',
            'alamat_lokasi' => 'SMOKE alamat uji ' . $this->marker,
        ]));

        $id = $this->service->createDraft($this->petugasAId, $data, $this->petugasAId);
        $this->addPhotoRow($id);

        return $id;
    }

    private function createSubmittedPendingProposal(): int
    {
        $id = $this->createCompletePendingReadyDraft();
        $result = $this->service->submitDraft($id, $this->petugasAId, $this->petugasAId);
        self::assertTrue($result['ok'], 'Fixture proposal pending harus valid: ' . json_encode($result['errors'] ?? []));

        return $id;
    }

    private function addPhotoRow(int $proposalId): void
    {
        $this->db->prepare('INSERT INTO usulan_opt_photos (usulan_opt_id, file_path, mime_type, size_bytes, created_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$proposalId, 'public/uploads/usulan-opt/test/' . bin2hex(random_bytes(4)) . '.jpg', 'image/jpeg', 1024, $this->petugasAId]);
    }

    private function attachReportToUsulan(int $usulanId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO laporan_hama (user_id, tanggal, lokasi, tingkat_keparahan, status, usulan_opt_id)
             VALUES (?, CURDATE(), ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->petugasAId,
            $this->marker . '-lokasi-' . bin2hex(random_bytes(3)),
            'Ringan',
            'Submitted',
            $usulanId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function firstWilayahIds(): array
    {
        $row = $this->fetch(
            'SELECT k.id kab_id, kec.id kec_id, d.id desa_id
             FROM master_kabupaten k
             JOIN master_kecamatan kec ON kec.kabupaten_id = k.id
             JOIN master_desa d ON d.kecamatan_id = kec.id
             ORDER BY k.id, kec.id, d.id LIMIT 1',
            []
        );

        return [
            'kabupaten_id' => (int) $row['kab_id'],
            'kecamatan_id' => (int) $row['kec_id'],
            'desa_id' => (int) $row['desa_id'],
        ];
    }

    private function fetch(string $sql, array $params): ?array
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

