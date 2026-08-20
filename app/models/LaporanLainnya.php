<?php
declare(strict_types=1);

class LaporanLainnya extends Model {
    protected $table = 'laporan_lainnya';

    protected $fillable = [
        'user_id',
        'jenis_id',
        'kabupaten_id',
        'kecamatan_id',
        'kode_laporan',
        'desa_id',
        'alamat_lengkap',
        'foto_url',
        'tanggal_kejadian',
        'data_json',
        'deskripsi',
        'latitude',
        'longitude',
        'status',
        'catatan_verifikasi',
        'verified_by',
        'verified_at',
        'created_at',
        'updated_at',
    ];

    public function getAllWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array {
        $qb = new QueryBuilder();
        $qb->table('laporan_lainnya ll')
           ->select([
               'll.*',
               'u.nama_lengkap as pelapor_nama',
               'u.role as pelapor_role',
               'mjl.nama as jenis_nama',
               'mjl.kode as jenis_kode',
               'md.nama_desa',
               'mk.nama_kecamatan',
               'kab.nama_kabupaten',
               'v.nama_lengkap as verifikator_nama',
           ])
           ->leftJoin('users u', 'll.user_id = u.id')
           ->leftJoin('master_jenis_laporan mjl', 'll.jenis_id = mjl.id')
           ->leftJoin('master_desa md', 'll.desa_id = md.id')
           ->leftJoin('master_kecamatan mk', 'md.kecamatan_id = mk.id')
           ->leftJoin('master_kabupaten kab', 'mk.kabupaten_id = kab.id')
           ->leftJoin('users v', 'll.verified_by = v.id');

        if (!empty($filters['jenis_id'])) {
            $qb->where('ll.jenis_id', $filters['jenis_id']);
        }

        if (!empty($filters['status'])) {
            $qb->where('ll.status', $filters['status']);
        }

        $includeDraft = !empty($filters['include_draft']);
        $userId = isset($filters['user_id']) ? (int)$filters['user_id'] : null;

        if (!$includeDraft) {
            if ($userId !== null && ($filters['show_own_draft'] ?? false)) {
                // Tampilkan non-draft SEMUA + draft milik user ini
                $qb->whereRaw("(ll.status != 'draft' OR ll.user_id = ?)", [$userId]);
            } else {
                $qb->where('ll.status', 'draft', '!=');
            }
        }

        if (!empty($filters['desa_id'])) {
            $qb->where('ll.desa_id', $filters['desa_id']);
        }

        if (!empty($filters['user_id'])) {
            $qb->where('ll.user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $qb->where('ll.tanggal_kejadian', $filters['date_from'], '>=');
        }

        if (!empty($filters['date_to'])) {
            $qb->where('ll.tanggal_kejadian', $filters['date_to'], '<=');
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $qb->whereRaw("(ll.deskripsi LIKE ? OR mjl.nama LIKE ? OR u.nama_lengkap LIKE ?)", [
                $search, $search, $search
            ]);
        }

        return $qb->orderBy('ll.created_at', 'DESC')
                  ->limit($limit)
                  ->offset($offset)
                  ->get();
    }

    public function getCountWithFilters(array $filters = []): int {
        $qb = new QueryBuilder();
        $qb->table('laporan_lainnya ll')
            ->leftJoin('users u', 'll.user_id = u.id')
            ->leftJoin('master_jenis_laporan mjl', 'll.jenis_id = mjl.id');

        if (!empty($filters['jenis_id'])) {
            $qb->where('ll.jenis_id', $filters['jenis_id']);
        }
        if (!empty($filters['status'])) {
            $qb->where('ll.status', $filters['status']);
        }
        $includeDraft = !empty($filters['include_draft']);
        $userId = isset($filters['user_id']) ? (int)$filters['user_id'] : null;

        if (!$includeDraft) {
            if ($userId !== null && ($filters['show_own_draft'] ?? false)) {
                // Tampilkan non-draft SEMUA + draft milik user ini
                $qb->whereRaw("(ll.status != 'draft' OR ll.user_id = ?)", [$userId]);
            } else {
                $qb->where('ll.status', 'draft', '!=');
            }
        }
        if (!empty($filters['desa_id'])) {
            $qb->where('ll.desa_id', $filters['desa_id']);
        }
        if (!empty($filters['user_id'])) {
            $qb->where('ll.user_id', $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $qb->where('ll.tanggal_kejadian', $filters['date_from'], '>=');
        }
        if (!empty($filters['date_to'])) {
            $qb->where('ll.tanggal_kejadian', $filters['date_to'], '<=');
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $qb->whereRaw("(ll.deskripsi LIKE ? OR mjl.nama LIKE ? OR u.nama_lengkap LIKE ?)", [
                $search, $search, $search
            ]);
        }

        return $qb->count();
    }

    public function getRecentForDashboard(int $userId, int $limit = 3): array {
        return $this->getAllWithFilters(
            ['user_id' => $userId, 'include_draft' => true],
            min(10, max(1, $limit)),
            0
        );
    }

    public function getStatusSummary(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS total FROM laporan_lainnya WHERE user_id = ? GROUP BY status'
        );
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'status');
    }

    public function getChartSummary(int $userId, int $year, bool $includeDraft = false): array {
        $statusSql = $includeDraft ? '' : " AND ll.status <> 'draft'";
        $params = [':user_id' => $userId, ':year' => $year];
        $trend = $this->db->prepare(
            "SELECT MONTH(ll.tanggal_kejadian) AS bulan, COUNT(*) AS total
             FROM laporan_lainnya ll
             WHERE ll.user_id = :user_id AND YEAR(ll.tanggal_kejadian) = :year{$statusSql}
             GROUP BY MONTH(ll.tanggal_kejadian) ORDER BY bulan"
        );
        $trend->execute($params);
        $types = $this->db->prepare(
            "SELECT COALESCE(mjl.nama, 'Tanpa Jenis') AS label, COUNT(*) AS total
             FROM laporan_lainnya ll
             LEFT JOIN master_jenis_laporan mjl ON ll.jenis_id = mjl.id
             WHERE ll.user_id = :user_id AND YEAR(ll.tanggal_kejadian) = :year{$statusSql}
             GROUP BY ll.jenis_id, mjl.nama ORDER BY total DESC"
        );
        $types->execute($params);
        return ['trend' => $trend->fetchAll(PDO::FETCH_ASSOC), 'by_type' => $types->fetchAll(PDO::FETCH_ASSOC)];
    }

public function getById(int $id): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('laporan_lainnya ll')
                      ->select([
                          'll.*',
                          'u.nama_lengkap as pelapor_nama',
                          'u.role as pelapor_role',
                          'mjl.nama as jenis_nama',
                          'mjl.kode as jenis_kode',
                          'mjl.fields_json as jenis_fields',
                          'md.nama_desa',
                          'mk.nama_kecamatan',
                          'kab.nama_kabupaten',
                          'v.nama_lengkap as verifikator_nama',
                      ])
                      ->leftJoin('users u', 'll.user_id = u.id')
                      ->leftJoin('master_jenis_laporan mjl', 'll.jenis_id = mjl.id')
                      ->leftJoin('master_desa md', 'll.desa_id = md.id')
                      ->leftJoin('master_kecamatan mk', 'md.kecamatan_id = mk.id')
                      ->leftJoin('master_kabupaten kab', 'mk.kabupaten_id = kab.id')
                      ->leftJoin('users v', 'll.verified_by = v.id')
                      ->where('ll.id', $id)
                      ->limit(1)
                      ->get();
        return !empty($result) ? $result[0] : null;
    }

    public function getByKodeLaporan(string $kode): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('laporan_lainnya')
                     ->where('kode_laporan', $kode)
                     ->limit(1)
                     ->get();
        return !empty($result) ? $result[0] : null;
    }

    public function createReport(array $data): int {
        return (int)$this->create($data);
    }

    public function updateReport(int $id, array $data): bool {
        return $this->update($id, $data);
    }

    public function submitReport(int $id, int $callerUserId, string $callerRole): bool {
        $report = $this->find($id);
        if (!$report) {
            throw new InvalidArgumentException('Laporan tidak ditemukan');
        }
        if (!in_array($report['status'] ?? null, ['draft', 'rejected'], true)) {
            throw new LogicException('Hanya laporan berstatus draft atau rejected yang dapat disubmit');
        }

        // Verifikasi ownership kecuali admin (defense-in-depth,
        // terlepas dari cek ownership di controller)
        if ($callerRole !== 'admin' && (int)$report['user_id'] !== $callerUserId) {
            throw new LogicException('Anda tidak berwenang submit laporan ini');
        }

        return $this->withKodeLaporanLock(function () use ($id, $report): bool {
            return $this->update($id, [
                'kode_laporan' => !empty($report['kode_laporan'])
                    ? $report['kode_laporan']
                    : $this->nextKodeLaporan(),
                'status' => 'submitted',
                'verified_by' => null,
                'verified_at' => null,
                'catatan_verifikasi' => null,
            ]);
        });
    }

    public function verifyReport(int $id, int $adminId, string $catatan = ''): bool {
        $this->assertStatus($id, ['submitted']);
        return $this->update($id, [
            'status' => 'verified',
            'verified_by' => $adminId,
            'verified_at' => date('Y-m-d H:i:s'),
            'catatan_verifikasi' => $catatan !== '' ? $catatan : null,
        ]);
    }

    public function rejectReport(int $id, int $adminId, string $catatan): bool {
        $this->assertStatus($id, ['submitted']);
        return $this->update($id, [
            'status' => 'rejected',
            'verified_by' => $adminId,
            'verified_at' => date('Y-m-d H:i:s'),
            'catatan_verifikasi' => $catatan,
        ]);
    }

    public function archiveReport(int $id): bool {
        $this->assertStatus($id, ['submitted', 'verified', 'rejected']);
        return $this->update($id, [
            'status' => 'archived',
        ]);
    }

    public function isOwner(int $id, int $userId): bool {
        $report = $this->find($id);
        return $report !== null && (int) $report['user_id'] === $userId;
    }

    public function isDraft(int $id): bool {
        $report = $this->find($id);
        return $report !== null && $report['status'] === 'draft';
    }

    public function canEdit(int $id, int $userId, string $role): bool {
        $report = $this->find($id);
        if (!$report) {
            return false;
        }
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'operator') {
            // Operator bisa edit milik sendiri selama draft atau rejected
            return (int)$report['user_id'] === $userId
                && in_array($report['status'] ?? '', ['draft', 'rejected'], true);
        }
        // Petugas: hanya milik sendiri, status draft atau rejected
        return (int)$report['user_id'] === $userId
            && in_array($report['status'] ?? '', ['draft', 'rejected'], true);
    }

    public function generateKodeLaporan(): string {
        return $this->withKodeLaporanLock(fn(): string => $this->nextKodeLaporan());
    }

    private function withKodeLaporanLock(callable $callback) {
        $year = date('Ymd');
        $lockName = "kode_laporan_ll_{$year}";

        $stmt = $this->db->prepare("SELECT GET_LOCK(?, 10)");
        $stmt->execute([$lockName]);
        $lockAcquired = (int)$stmt->fetchColumn();

        if (!$lockAcquired) {
            throw new Exception('Failed to acquire lock for kode laporan generation');
        }

        try {
            return $callback();
        } finally {
            $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$lockName]);
        }
    }

    private function nextKodeLaporan(): string {
        $year = date('Ymd');
        $prefix = 'LL';
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(CAST(RIGHT(kode_laporan, 4) AS UNSIGNED)), 0)
             FROM laporan_lainnya WHERE kode_laporan LIKE ?'
        );
        $stmt->execute(["{$prefix}-{$year}-%"]);
        $sequence = (int) $stmt->fetchColumn() + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    private function assertStatus(int $id, array $allowedStatuses): void {
        $report = $this->find($id);
        if (!$report) {
            throw new InvalidArgumentException('Laporan tidak ditemukan');
        }
        if (!in_array($report['status'] ?? null, $allowedStatuses, true)) {
            throw new LogicException('Transisi status laporan tidak diizinkan');
        }
    }

    public function getStatsByJenis(int $tahun): array {
        $sql = "SELECT
                    mjl.kode as jenis_kode,
                    mjl.nama as jenis_nama,
                    COUNT(ll.id) as total_laporan,
                    SUM(CASE WHEN ll.status = 'verified' THEN 1 ELSE 0 END) as diverifikasi,
                    SUM(CASE WHEN ll.status = 'draft' THEN 1 ELSE 0 END) as draf
                FROM laporan_lainnya ll
                LEFT JOIN master_jenis_laporan mjl ON ll.jenis_id = mjl.id
                WHERE YEAR(ll.tanggal_kejadian) = :tahun
                GROUP BY mjl.id, mjl.kode, mjl.nama
                ORDER BY mjl.nama ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tahun' => $tahun]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDashboardSummary(?int $userId = null, bool $includeDraft = false): array {
        $whereClauses = [];
        $params = [];

        if ($userId !== null) {
            $whereClauses[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if (!$includeDraft) {
            $whereClauses[] = "status != 'draft'";
        }

        $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "SELECT
                    COUNT(*) as total_laporan,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as terverifikasi,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draf
                FROM laporan_lainnya {$whereSQL}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_laporan' => (int)($result['total_laporan'] ?? 0),
            'terverifikasi' => (int)($result['terverifikasi'] ?? 0),
            'draf' => (int)($result['draf'] ?? 0),
        ];
    }

    /**
     * Get performance summary for a petugas in a specific year
     * 
     * @param int $userId User ID of the petugas
     * @param int $year Year to analyze
     * @return array Summary with counts per status
     */
    public function getPetugasPerformanceSummary(int $userId, int $year): array {
        $sql = "SELECT
                    COUNT(*) as total_laporan,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                FROM laporan_lainnya
                WHERE user_id = :user_id
                AND YEAR(tanggal_kejadian) = :year";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':year' => $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_laporan' => (int)($result['total_laporan'] ?? 0),
            'draft' => (int)($result['draft'] ?? 0),
            'submitted' => (int)($result['submitted'] ?? 0),
            'verified' => (int)($result['verified'] ?? 0),
            'rejected' => (int)($result['rejected'] ?? 0),
            'archived' => (int)($result['archived'] ?? 0),
        ];
    }

    /**
     * Get monthly trend of reports for a petugas in a specific year
     * 
     * @param int $userId User ID of the petugas
     * @param int $year Year to analyze
     * @return array Monthly report counts (Jan-Dec)
     */
    public function getPetugasMonthlyTrend(int $userId, int $year): array {
        $sql = "SELECT
                    MONTH(tanggal_kejadian) as month,
                    COUNT(*) as count
                FROM laporan_lainnya
                WHERE user_id = :user_id
                AND YEAR(tanggal_kejadian) = :year
                GROUP BY MONTH(tanggal_kejadian)
                ORDER BY month ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':year' => $year]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize all months with 0
        $monthlyData = array_fill(1, 12, 0);
        
        // Fill in actual data
        foreach ($results as $row) {
            $month = (int)$row['month'];
            $monthlyData[$month] = (int)$row['count'];
        }

        return $monthlyData;
    }

    /**
     * Get breakdown of reports by jenis for a petugas in a specific year
     * 
     * @param int $userId User ID of the petugas
     * @param int $year Year to analyze
     * @return array Report counts per jenis
     */
    public function getPetugasBreakdownByJenis(int $userId, int $year): array {
        $sql = "SELECT
                    mjl.id as jenis_id,
                    mjl.kode as jenis_kode,
                    mjl.nama as jenis_nama,
                    COUNT(ll.id) as total_laporan,
                    SUM(CASE WHEN ll.status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN ll.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                    SUM(CASE WHEN ll.status = 'draft' THEN 1 ELSE 0 END) as draft
                FROM laporan_lainnya ll
                LEFT JOIN master_jenis_laporan mjl ON ll.jenis_id = mjl.id
                WHERE ll.user_id = :user_id
                AND YEAR(ll.tanggal_kejadian) = :year
                GROUP BY mjl.id, mjl.kode, mjl.nama
                ORDER BY total_laporan DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':year' => $year]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return [
                'jenis_id' => (int)$row['jenis_id'],
                'jenis_kode' => $row['jenis_kode'],
                'jenis_nama' => $row['jenis_nama'],
                'total_laporan' => (int)$row['total_laporan'],
                'verified' => (int)$row['verified'],
                'submitted' => (int)$row['submitted'],
                'draft' => (int)$row['draft'],
            ];
        }, $results);
    }

    /**
     * Get paginated list of reports for a petugas with full details
     * 
     * @param int $userId User ID of the petugas
     * @param array $filters Additional filters
     * @param int $limit Number of records per page
     * @param int $offset Offset for pagination
     * @return array Report list with joined data
     */
    public function getPetugasReportList(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array {
        $qb = new QueryBuilder();
        $qb->table('laporan_lainnya ll')
           ->select([
               'll.*',
               'u.nama_lengkap as pelapor_nama',
               'u.role as pelapor_role',
               'mjl.nama as jenis_nama',
               'mjl.kode as jenis_kode',
               'md.nama_desa',
               'mk.nama_kecamatan',
               'kab.nama_kabupaten',
               'v.nama_lengkap as verifikator_nama',
           ])
           ->leftJoin('users u', 'll.user_id = u.id')
           ->leftJoin('master_jenis_laporan mjl', 'll.jenis_id = mjl.id')
           ->leftJoin('master_desa md', 'll.desa_id = md.id')
           ->leftJoin('master_kecamatan mk', 'md.kecamatan_id = mk.id')
           ->leftJoin('master_kabupaten kab', 'mk.kabupaten_id = kab.id')
           ->leftJoin('users v', 'll.verified_by = v.id')
           ->where('ll.user_id', $userId);

        // Apply additional filters
        if (!empty($filters['status'])) {
            $qb->where('ll.status', $filters['status']);
        }

        if (!empty($filters['jenis_id'])) {
            $qb->where('ll.jenis_id', $filters['jenis_id']);
        }

        if (!empty($filters['desa_id'])) {
            $qb->where('ll.desa_id', $filters['desa_id']);
        }

        if (!empty($filters['date_from'])) {
            $qb->where('ll.tanggal_kejadian', $filters['date_from'], '>=');
        }

        if (!empty($filters['date_to'])) {
            $qb->where('ll.tanggal_kejadian', $filters['date_to'], '<=');
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $qb->whereRaw("(ll.deskripsi LIKE ? OR mjl.nama LIKE ?)", [$search, $search]);
        }

        return $qb->orderBy('ll.created_at', 'DESC')
                  ->limit($limit)
                  ->offset($offset)
                  ->get();
    }
}
