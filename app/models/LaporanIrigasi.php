<?php
/**
 * LaporanIrigasi Model
 * * Menangani operasi database untuk fitur sebaran irigasi.
 * Menggunakan QueryBuilder dan JOIN untuk performa optimal.
 * * @package app/models
 */
class LaporanIrigasi extends Model {
    protected $table = 'laporan_irigasi';

    /**
     * Get all reports with details (User, Wilayah)
     * Menggunakan JOIN untuk menghindari N+1 Query Problem
     * * @param int|null $userId Optional: Filter by User ID (untuk Petugas)
     * @return array
     */
    public function getAllWithDetails(
        ?int $userId = null,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $qb = new QueryBuilder();
        $qb->table('laporan_irigasi li')
           ->select([
               'li.*',
               'u.nama_lengkap as pelapor_nama',
               'u.role as pelapor_role',
               'kab.nama_kabupaten',
               'kec.nama_kecamatan',
               'des.nama_desa',
               'v.nama_lengkap as verifikator_nama'
           ])
           ->leftJoin('users u', 'li.user_id = u.id')
           ->leftJoin('master_kabupaten kab', 'li.kabupaten_id = kab.id')
           ->leftJoin('master_kecamatan kec', 'li.kecamatan_id = kec.id')
           ->leftJoin('master_desa des', 'li.desa_id = des.id')
           ->leftJoin('users v', 'li.verified_by = v.id');

        if ($userId !== null) {
            $qb->where('li.user_id', $userId);
        }

        $qb->orderBy('li.tanggal', 'DESC')
           ->orderBy('li.created_at', 'DESC');

        if ($limit !== null) {
            $qb->limit(min(100, max(1, $limit)))
               ->offset(max(0, $offset));
        }

        return $qb->get();
    }

    public function countAll(?int $userId = null): int {
        $sql = 'SELECT COUNT(*) FROM laporan_irigasi';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE user_id = ?';
            $params[] = $userId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Verify laporan (approve atau reject)
     * * @param int $id ID Laporan
     * @param string $status Status baru
     * @param int $verifiedBy User ID verifikator
     * @param string|null $catatan Catatan verifikasi
     * @return bool
     */
    public function verify(int $id, string $status, int $verifiedBy, ?string $catatan = null): bool {
        if (!in_array($status, ['Diverifikasi', 'Ditolak'], true)) {
            throw new InvalidArgumentException('Status tidak valid');
        }

        $report = $this->find($id);
        if (!$report) {
            throw new InvalidArgumentException('Laporan irigasi tidak ditemukan');
        }
        if (($report['status'] ?? null) !== 'Submitted') {
            throw new LogicException('Hanya laporan berstatus Submitted yang dapat diverifikasi atau ditolak');
        }

        $data = [
            'status' => $status,
            'verified_by' => $verifiedBy,
            'verified_at' => date('Y-m-d H:i:s'),
            'catatan_verifikasi' => $catatan
        ];

        return $this->update($id, $data);
    }

    public function getWithFilters(array $filters, ?int $userId, int $limit, int $offset): array {
        [$where, $params] = $this->buildListWhere($filters, $userId);
        $sql = "SELECT li.*, u.nama_lengkap AS pelapor_nama, u.role AS pelapor_role,
                       kab.nama_kabupaten, kec.nama_kecamatan, des.nama_desa,
                       v.nama_lengkap AS verifikator_nama
                FROM laporan_irigasi li
                LEFT JOIN users u ON li.user_id = u.id
                LEFT JOIN master_kabupaten kab ON li.kabupaten_id = kab.id
                LEFT JOIN master_kecamatan kec ON li.kecamatan_id = kec.id
                LEFT JOIN master_desa des ON li.desa_id = des.id
                LEFT JOIN users v ON li.verified_by = v.id
                WHERE {$where}
                ORDER BY li.tanggal DESC, li.created_at DESC
                LIMIT " . min(100, max(1, $limit)) . ' OFFSET ' . max(0, $offset);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countWithFilters(array $filters, ?int $userId): int {
        [$where, $params] = $this->buildListWhere($filters, $userId);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM laporan_irigasi li WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getRecentForDashboard(int $userId, int $limit = 3): array {
        $limit = min(10, max(1, $limit));
        $sql = "SELECT li.*, kec.nama_kecamatan, des.nama_desa
                FROM laporan_irigasi li
                LEFT JOIN master_kecamatan kec ON li.kecamatan_id = kec.id
                LEFT JOIN master_desa des ON li.desa_id = des.id
                WHERE li.user_id = :user_id
                ORDER BY li.updated_at DESC, li.id DESC
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusSummary(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS total FROM laporan_irigasi WHERE user_id = ? GROUP BY status'
        );
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'status');
    }

    private function buildListWhere(array $filters, ?int $userId): array {
        $where = ['1=1'];
        $params = [];
        if ($userId !== null) {
            $where[] = 'li.user_id = ?';
            $params[] = $userId;
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['Draf', 'Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'], true)) {
            $where[] = 'li.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(li.nomor_laporan LIKE ? OR li.nama_saluran LIKE ? OR li.daerah_irigasi LIKE ? OR li.catatan LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'li.tanggal >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'li.tanggal <= ?';
            $params[] = $filters['date_to'];
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * Generate a collision-safe number only when a report is submitted.
     */
    public function generateNomorLaporan(): string {
        return $this->withNomorLaporanLock(fn(): string => $this->nextNomorLaporan());
    }

    public function createSubmitted(array $data): int {
        return $this->withNomorLaporanLock(function () use ($data): int {
            $data['status'] = 'Submitted';
            $data['nomor_laporan'] = $this->nextNomorLaporan();

            return (int) $this->create($data);
        });
    }

    public function resubmit(int $id, array $data): bool {
        return $this->withNomorLaporanLock(function () use ($id, $data): bool {
            $data['status'] = 'Submitted';
            $data['nomor_laporan'] = $this->nextNomorLaporan();
            $data['verified_by'] = null;
            $data['verified_at'] = null;
            $data['catatan_verifikasi'] = null;

            return $this->update($id, $data);
        });
    }

    private function withNomorLaporanLock(callable $callback) {
        $lockName = 'nomor_laporan_irigasi_' . date('Ymd');
        $lockStmt = $this->db->prepare('SELECT GET_LOCK(?, 10)');
        $lockStmt->execute([$lockName]);

        if ((int) $lockStmt->fetchColumn() !== 1) {
            throw new RuntimeException('Gagal mengunci pembuatan nomor laporan irigasi');
        }

        try {
            return $callback();
        } finally {
            $releaseStmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $releaseStmt->execute([$lockName]);
        }
    }

    private function nextNomorLaporan(): string {
        $prefix = 'LI-' . date('Ymd') . '-';
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(CAST(RIGHT(nomor_laporan, 4) AS UNSIGNED)), 0)
             FROM laporan_irigasi
             WHERE nomor_laporan LIKE ?'
        );
        $stmt->execute([$prefix . '%']);
        $sequence = (int) $stmt->fetchColumn() + 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get single report with all details
     * 
     * @param int $id Report ID
     * @return array|null
     */
    public function getDetailById(int $id): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('laporan_irigasi li')
           ->select([
               'li.*',
               'u.nama_lengkap as pelapor_nama',
               'u.role as pelapor_role',
               'u.email as pelapor_email',
               'kab.nama_kabupaten',
               'kec.nama_kecamatan',
               'des.nama_desa',
               'v.nama_lengkap as verifikator_nama'
           ])
           ->leftJoin('users u', 'li.user_id = u.id')
           ->leftJoin('master_kabupaten kab', 'li.kabupaten_id = kab.id')
           ->leftJoin('master_kecamatan kec', 'li.kecamatan_id = kec.id')
           ->leftJoin('master_desa des', 'li.desa_id = des.id')
           ->leftJoin('users v', 'li.verified_by = v.id')
           ->where('li.id', $id)
           ->limit(1)
           ->get();
        
        return !empty($result) ? $result[0] : null;
    }
}

