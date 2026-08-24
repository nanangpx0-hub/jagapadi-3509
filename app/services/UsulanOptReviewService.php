<?php

declare(strict_types=1);

final class UsulanOptReviewService
{
    public const STATUS_PENDING = UsulanOpt::STATUS_PENDING;
    public const STATUS_REVISION = UsulanOpt::STATUS_REVISION;
    public const STATUS_APPROVED = UsulanOpt::STATUS_APPROVED;
    public const STATUS_MERGED = UsulanOpt::STATUS_MERGED;
    public const STATUS_REJECTED = UsulanOpt::STATUS_REJECTED;

    public const NOTIF_RECEIVED = 'usulan_diterima';
    public const NOTIF_REVISION_REQUESTED = 'usulan_perlu_perbaikan';
    public const NOTIF_RESUBMITTED = 'usulan_dikirim_ulang';
    public const NOTIF_APPROVED = 'usulan_disetujui';
    public const NOTIF_MERGED = 'usulan_digabungkan';
    public const NOTIF_REJECTED = 'usulan_ditolak';

    public const REASON_NOT_FOUND = 'not_found';
    public const REASON_ALREADY_REVIEWED = 'already_reviewed';
    public const REASON_MASTER_INVALID = 'master_invalid';
    public const REASON_JENIS_MISMATCH = 'jenis_mismatch';
    public const REASON_DUPLICATE = 'duplicate';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Minta perbaikan: Menunggu Review â†’ Perlu Perbaikan (Admin).
     *
     * @return array{ok:bool,reason?:string}
     */
    public function requestRevision(int $proposalId, int $reviewerId, string $catatan): array
    {
        $catatan = trim($catatan);
        if (mb_strlen($catatan) < 10) {
            throw new InvalidArgumentException('Catatan perbaikan minimal 10 karakter.');
        }
        if (mb_strlen($catatan) > 2000) {
            throw new InvalidArgumentException('Catatan perbaikan maksimal 2000 karakter.');
        }

        $this->db->beginTransaction();
        try {
            $proposal = $this->lockProposalWithStatus($proposalId, self::STATUS_PENDING);
            if ($proposal === null) {
                $result = $this->abortWithLockCheck($proposalId);
                return $result;
            }

            $affected = $this->transitionStatus(
                $proposalId,
                self::STATUS_PENDING,
                self::STATUS_REVISION,
                $reviewerId,
                $catatan
            );
            if ($affected === 0) {
                $this->db->rollBack();

                return ['ok' => false, 'reason' => self::REASON_ALREADY_REVIEWED];
            }

            $this->addHistory($proposalId, self::STATUS_PENDING, self::STATUS_REVISION, $reviewerId, $catatan);
            $this->updateReviewerStamp($proposalId, $reviewerId);

            $this->writeAudit($reviewerId, 'request_revision', $proposalId, sprintf('Admin meminta perbaikan usulan OPT #%d.', $proposalId));
            $this->notifyOwner(
                (int) $proposal['user_id'],
                $proposalId,
                self::NOTIF_REVISION_REQUESTED,
                'Perbaikan usulan diminta',
                sprintf('Admin meminta perbaikan untuk usulan "%s". Periksa catatan Admin.', $this->displayName($proposal))
            );

            $this->db->commit();

            return ['ok' => true];
        } catch (Throwable $e) {
            $this->rollBackQuietly();
            error_log('UsulanOptReviewService::requestRevision failed');
            throw new RuntimeException('Gagal memproses permintaan perbaikan.');
        }
    }

    /**
     * Setujui usulan dengan membuat master OPT final dalam satu transaksi.
     *
     * @param array<string,mixed> $masterData hasil MasterOptService::normalize()
     * @return array{ok:bool,reason?:string,duplicates?:array,master_opt_id?:int,relinked?:int}
     */
    public function approveNew(int $proposalId, int $reviewerId, array $masterData, string $catatan): array
    {
        $masterService = new MasterOptService($this->db);
        $automaticCode = trim((string) ($masterData['kode_opt'] ?? '')) === '';
        if ($automaticCode) {
            $lock = $this->db->prepare("SELECT GET_LOCK('master_opt_code_generation', 10)");
            $lock->execute();
            if ((int) $lock->fetchColumn() !== 1) {
                throw new RuntimeException('Generator kode OPT sedang digunakan. Silakan coba kembali.');
            }
            $masterData['kode_opt'] = $masterService->nextAutomaticCode((string) ($masterData['jenis'] ?? ''));
        }
        $errors = $masterService->validate($masterData);
        if ($errors !== []) {
            if ($automaticCode) {
                $this->db->query("SELECT RELEASE_LOCK('master_opt_code_generation')");
            }
            throw new InvalidArgumentException('Data master tidak valid.');
        }

        $this->db->beginTransaction();
        try {
            $proposal = $this->lockProposalWithStatus($proposalId, self::STATUS_PENDING);
            if ($proposal === null) {
                $result = $this->abortWithLockCheck($proposalId);
                if ($automaticCode) {
                    $this->db->query("SELECT RELEASE_LOCK('master_opt_code_generation')");
                }
                return $result;
            }

            $existing = $masterService->findSameNameForUpdate((string) $masterData['nama_opt']);
            $replaced = $existing !== null;
            if ($replaced) {
                $masterId = (int) $existing['id'];
                $masterData['kode_opt'] = (string) $existing['kode_opt'];
                $masterService->replaceKeepingIdentity($masterId, $masterData);
            } else {
                try {
                    $masterId = $masterService->insert($masterData);
                } catch (DuplicateMasterException $e) {
                    $this->db->rollBack();
                    $result = [
                        'ok' => false,
                        'reason' => self::REASON_DUPLICATE,
                        'duplicates' => $masterService->findDuplicates($masterData),
                    ];
                    if ($automaticCode) {
                        $this->db->query("SELECT RELEASE_LOCK('master_opt_code_generation')");
                    }
                    return $result;
                }
            }

            $relinked = $this->relinkReports($proposalId, $masterId);
            $this->applyDecision($proposalId, self::STATUS_APPROVED, $masterId, $reviewerId, $catatan);

            $this->writeAudit(
                $reviewerId,
                $replaced ? 'approve_replace' : 'approve',
                $proposalId,
                sprintf(
                    'Usulan OPT #%d disetujui dan %s master #%d (%s); %d laporan terhubung.',
                    $proposalId,
                    $replaced ? 'menggantikan data pada' : 'membuat',
                    $masterId,
                    (string) $masterData['nama_opt'],
                    $relinked
                )
            );
            $this->notifyOwner(
                (int) $proposal['user_id'],
                $proposalId,
                self::NOTIF_APPROVED,
                'Usulan OPT disetujui',
                sprintf(
                    'Usulan "%s" Anda disetujui dan %s.',
                    $this->displayName($proposal),
                    $replaced ? 'memperbarui master OPT yang sama' : 'menjadi master OPT baru'
                )
            );

            $this->db->commit();
            MasterOptService::clearMasterOptCache();

            if ($automaticCode) {
                $this->db->query("SELECT RELEASE_LOCK('master_opt_code_generation')");
            }

            return [
                'ok' => true,
                'master_opt_id' => $masterId,
                'relinked' => $relinked,
                'kode_opt' => $masterData['kode_opt'],
                'replaced' => $replaced,
            ];
        } catch (Throwable $e) {
            $this->rollBackQuietly();
            if ($automaticCode) {
                $this->db->query("SELECT RELEASE_LOCK('master_opt_code_generation')");
            }
            error_log('UsulanOptReviewService::approveNew failed');

            throw new RuntimeException('Gagal memproses persetujuan usulan OPT.');
        }
    }

    /**
     * Gabungkan usulan ke master aktif yang sudah ada (jenis sama).
     *
     * @return array{ok:bool,reason?:string,relinked?:int}
     */
    public function merge(int $proposalId, int $masterOptId, int $reviewerId, string $catatan): array
    {
        if ($masterOptId <= 0) {
            return ['ok' => false, 'reason' => self::REASON_MASTER_INVALID];
        }

        $this->db->beginTransaction();
        try {
            $proposal = $this->lockProposalWithStatus($proposalId, self::STATUS_PENDING);
            if ($proposal === null) {
                return $this->abortWithLockCheck($proposalId);
            }

            $stmt = $this->db->prepare('SELECT id, nama_opt, jenis, aktif FROM master_opt WHERE id = ? FOR UPDATE');
            $stmt->execute([$masterOptId]);
            $master = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$master || (int) $master['aktif'] !== 1) {
                $this->db->rollBack();

                return ['ok' => false, 'reason' => self::REASON_MASTER_INVALID];
            }

            if ((string) $master['jenis'] !== (string) $proposal['jenis']) {
                $this->db->rollBack();

                return ['ok' => false, 'reason' => self::REASON_JENIS_MISMATCH];
            }

            $relinked = $this->relinkReports($proposalId, $masterOptId);
            $this->applyDecision($proposalId, self::STATUS_MERGED, $masterOptId, $reviewerId, $catatan);

            $this->writeAudit( $reviewerId, 'merge', $proposalId, sprintf( 'Usulan OPT #%d digabungkan ke master #%d (%s); %d laporan terhubung.', $proposalId, $masterOptId, (string) $master['nama_opt'], $relinked ) );
            $this->notifyOwner(
                (int) $proposal['user_id'],
                $proposalId,
                self::NOTIF_MERGED,
                'Usulan OPT digabungkan',
                sprintf(
                    'Usulan "%s" Anda digabungkan ke master OPT "%s".',
                    $this->displayName($proposal),
                    (string) $master['nama_opt']
                )
            );

            $this->db->commit();

            return ['ok' => true, 'relinked' => $relinked];
        } catch (Throwable $e) {
            $this->rollBackQuietly();
            error_log('UsulanOptReviewService::merge failed');

            throw new RuntimeException('Gagal memproses penggabungan usulan OPT.');
        }
    }

    /**
     * Tolak permanen dengan alasan minimal 10 karakter.
     *
     * @return array{ok:bool,reason?:string}
     */
    public function rejectPermanent(int $proposalId, int $reviewerId, string $alasan): array
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 10) {
            throw new InvalidArgumentException('Alasan penolakan minimal 10 karakter.');
        }

        $this->db->beginTransaction();
        try {
            $proposal = $this->lockProposalWithStatus($proposalId, self::STATUS_PENDING);
            if ($proposal === null) {
                return $this->abortWithLockCheck($proposalId);
            }

            $this->applyDecision($proposalId, self::STATUS_REJECTED, null, $reviewerId, $alasan);

 $this->writeAudit( $reviewerId, 'permanent_reject', $proposalId, sprintf('Usulan OPT #%d ditolak permanen.', $proposalId) );
            $this->notifyOwner(
                (int) $proposal['user_id'],
                $proposalId,
                self::NOTIF_REJECTED,
                'Usulan OPT ditolak permanen',
                sprintf('Usulan "%s" Anda ditolak permanen. Alasan: %s', $this->displayName($proposal), mb_substr($alasan, 0, 200))
            );

            $this->db->commit();

            return ['ok' => true];
        } catch (Throwable $e) {
            $this->rollBackQuietly();
            error_log('UsulanOptReviewService::rejectPermanent failed');

            throw new RuntimeException('Gagal memproses penolakan usulan OPT.');
        }
    }

    /**
     * Kompatibilitas pemanggil lama.
     *
     * @return array{ok:bool,reason?:string}
     */
    public function reject(int $proposalId, int $reviewerId, string $alasan): array
    {
        return $this->rejectPermanent($proposalId, $reviewerId, $alasan);
    }

    private function lockProposalWithStatus(int $proposalId, string $expectedStatus): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usulan_opt WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$proposalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (string) $row['status'] !== $expectedStatus) {
            return null;
        }

        return $row;
    }

    /**
     * Rollback transaksi terbuka lalu tentukan alasan gagal tanpa mengubah apa pun.
     *
     * @return array{ok:bool,reason:string}
     */
    private function abortWithLockCheck(int $proposalId): array
    {
        $this->rollBackQuietly();

        $stmt = $this->db->prepare('SELECT status FROM usulan_opt WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$proposalId]);
        $status = $stmt->fetchColumn();

        return [
            'ok' => false,
            'reason' => $status === false ? self::REASON_NOT_FOUND : self::REASON_ALREADY_REVIEWED,
        ];
    }

    private function relinkReports(int $proposalId, int $masterOptId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE laporan_hama SET master_opt_id = ? WHERE usulan_opt_id = ?'
        );
        $stmt->execute([$masterOptId, $proposalId]);

        return $stmt->rowCount();
    }

    /**
     * Terapkan keputusan akhir: status + catatan + reviewer + stamp waktu,
     * plus baris riwayat transisi. Dipanggil di dalam transaksi terbuka.
     */
    private function applyDecision(int $proposalId, string $toStatus, ?int $masterOptId, int $reviewerId, string $catatan): void
    {
        $fromStmt = $this->db->prepare('SELECT status FROM usulan_opt WHERE id = ? AND deleted_at IS NULL');
        $fromStmt->execute([$proposalId]);
        $fromStatus = $fromStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'UPDATE usulan_opt
             SET status = ?, master_opt_id = ?, catatan_review = ?, reviewed_by = ?, reviewed_at = ?
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $toStatus,
            $masterOptId,
            $catatan !== '' ? $catatan : null,
            $reviewerId,
            date('Y-m-d H:i:s'),
            $proposalId,
        ]);

        $this->addHistory(
            $proposalId,
            $fromStatus !== false ? (string) $fromStatus : null,
            $toStatus,
            $reviewerId,
            $catatan !== '' ? $catatan : null
        );
    }

    private function transitionStatus(int $proposalId, string $fromStatus, string $toStatus, int $reviewerId, ?string $catatan): int
    {
        $stmt = $this->db->prepare(
            'UPDATE usulan_opt SET status = ?, catatan_review = ?, reviewed_by = ?, reviewed_at = ?
             WHERE id = ? AND status = ? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $toStatus,
            $catatan,
            $reviewerId,
            date('Y-m-d H:i:s'),
            $proposalId,
            $fromStatus,
        ]);

        return $stmt->rowCount();
    }

    private function updateReviewerStamp(int $proposalId, int $reviewerId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usulan_opt SET reviewed_by = ?, reviewed_at = ? WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $reviewerId,
            date('Y-m-d H:i:s'),
            $proposalId,
        ]);
    }

    private function addHistory(int $proposalId, ?string $fromStatus, string $toStatus, int $changedBy, ?string $catatan): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usulan_opt_status_history (usulan_opt_id, from_status, to_status, changed_by, catatan)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$proposalId, $fromStatus, $toStatus, $changedBy, $catatan]);
    }

    private function writeAudit(int $userId, string $action, int $recordId, string $description): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $userId,
                $action,
                'usulan_opt',
                $recordId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('UsulanOptReviewService audit log failed');
        }
    }

    private function notifyOwner(int $ownerUserId, int $proposalId, string $type, string $title, string $body): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO notifications (user_id, title, body, type, data_json) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $ownerUserId,
                mb_substr($title, 0, 200),
                mb_substr($body, 0, 500),
                $type,
                json_encode([
                    'entity' => 'usulan_opt',
                    'usulan_opt_id' => $proposalId,
                    'web_path' => '/usulan-opt/detail/' . $proposalId,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            error_log('UsulanOptReviewService notification failed');
        }
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private function displayName(array $proposal): string
    {
        $name = trim((string) ($proposal['nama_nasional'] ?? ''));
        if ($name === '') {
            $name = (string) ($proposal['nama_lokal'] ?? '');
        }

        return mb_substr($name, 0, 100);
    }

    private function rollBackQuietly(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
