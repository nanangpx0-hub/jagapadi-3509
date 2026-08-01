<?php
class LaporanHama extends Model {
    protected $table = 'laporan_hama';
protected $fillable = [
        'user_id',
        'master_opt_id',
        'tanggal',
        'lokasi',
        'latitude',
        'longitude',
        'tingkat_keparahan',
        'populasi',
        'luas_serangan',
        'foto_url',
        'status',
        'catatan',
        'catatan_verifikasi',
        'verified_by',
        'verified_at',
        'kabupaten',
        'kecamatan',
        'desa',
        'alamat_lengkap',
        'kabupaten_id',
        'kecamatan_id',
        'desa_id',
        'nomor_laporan',
    ];
    protected array $relations = [
        'user' => [
            'type' => 'belongsTo',
            'table' => 'users',
            'local_key' => 'user_id',
            'foreign_key' => 'id',
            'columns' => ['id', 'username', 'nama_lengkap', 'role', 'email'],
            'result_key' => 'user',
        ],
        'masterOpt' => [
            'type' => 'belongsTo',
            'table' => 'master_opt',
            'local_key' => 'master_opt_id',
            'foreign_key' => 'id',
            'columns' => ['id', 'nama_opt', 'jenis', 'etl_acuan'],
            'result_key' => 'master_opt',
        ],
        'kabupaten' => [
            'type' => 'belongsTo',
            'table' => 'master_kabupaten',
            'local_key' => 'kabupaten_id',
            'foreign_key' => 'id',
            'columns' => ['id', 'nama_kabupaten'],
            'result_key' => 'kabupaten',
        ],
        'kecamatan' => [
            'type' => 'belongsTo',
            'table' => 'master_kecamatan',
            'local_key' => 'kecamatan_id',
            'foreign_key' => 'id',
            'columns' => ['id', 'nama_kecamatan'],
            'result_key' => 'kecamatan',
        ],
        'desa' => [
            'type' => 'belongsTo',
            'table' => 'master_desa',
            'local_key' => 'desa_id',
            'foreign_key' => 'id',
            'columns' => ['id', 'nama_desa'],
            'result_key' => 'desa',
        ],
    ];

    /**
     * Get reports with pagination using QueryBuilder
     */
    public function getWithPagination(array $filters = [], int $page = 1, int $limit = 20): array {
        $qb = new QueryBuilder();
        $qb->table('laporan_hama lh')
           ->select([
               'lh.*',
               'u.nama_lengkap as pelapor',
               'mo.nama_opt'
           ])
           ->leftJoin('users u', 'lh.user_id = u.id')
           ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id');

        // Apply filters safely
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $qb->where('lh.lokasi', $searchTerm, 'LIKE');
        }

        if (!empty($filters['status'])) {
            $qb->where('lh.status', $filters['status']);
        }

        if (!empty($filters['kabupaten_id'])) {
            $qb->where('lh.kabupaten_id', $filters['kabupaten_id']);
        }

        if (!empty($filters['kecamatan_id'])) {
            $qb->where('lh.kecamatan_id', $filters['kecamatan_id']);
        }

        if (!empty($filters['desa_id'])) {
            $qb->where('lh.desa_id', $filters['desa_id']);
        }

        if (!empty($filters['master_opt_id'])) {
            $qb->where('lh.master_opt_id', $filters['master_opt_id']);
        }

        $offset = ($page - 1) * $limit;
        $qb->orderBy('lh.created_at', 'DESC')
           ->limit($limit)
           ->offset($offset);

        return $qb->get();
    }

    /**
     * Get reports by status using QueryBuilder
     */
    public function getByStatus(string $status): array {
        $qb = new QueryBuilder();
        return $qb->table('laporan_hama lh')
                  ->select([
                      'lh.*',
                      'u.nama_lengkap as pelapor_nama',
                      'u.username as pelapor_username',
                      'u.role as pelapor_role',
                      'mo.nama_opt',
                      'mo.jenis',
                      'mo.etl_acuan'
                  ])
                  ->leftJoin('users u', 'lh.user_id = u.id')
                  ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id')
                  ->where('lh.status', $status)
                  ->orderBy('lh.created_at', 'DESC')
                  ->get();
    }

    /**
     * Get reports by status and user using QueryBuilder
     */
    public function getByStatusAndUser(string $status, int $userId): array {
        $qb = new QueryBuilder();
        return $qb->table('laporan_hama lh')
                  ->select([
                      'lh.*',
                      'u.nama_lengkap as pelapor_nama',
                      'u.username as pelapor_username',
                      'u.role as pelapor_role',
                      'mo.nama_opt',
                      'mo.jenis',
                      'mo.etl_acuan'
                  ])
                  ->leftJoin('users u', 'lh.user_id = u.id')
                  ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id')
                  ->where('lh.status', $status)
                  ->where('lh.user_id', $userId)
                  ->orderBy('lh.created_at', 'DESC')
                  ->get();
    }

    /**
     * Get all reports with details using QueryBuilder
     * @param int|null $userId Optional user ID to filter reports by user
     */
    public function getAllWithDetails(?int $userId = null): array {
        $qb = new QueryBuilder();
        $qb->table('laporan_hama lh')
           ->select([
               'lh.*',
               'u.nama_lengkap as pelapor_nama',
               'u.username as pelapor_username',
               'u.role as pelapor_role',
               'mo.nama_opt',
               'mo.jenis',
               'mo.etl_acuan',
               'kab.nama_kabupaten',
               'kec.nama_kecamatan',
               'des.nama_desa'
           ])
           ->leftJoin('users u', 'lh.user_id = u.id')
           ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id')
           ->leftJoin('master_kabupaten kab', 'lh.kabupaten_id = kab.id')
           ->leftJoin('master_kecamatan kec', 'lh.kecamatan_id = kec.id')
           ->leftJoin('master_desa des', 'lh.desa_id = des.id');

        // Filter by user if provided
        if ($userId !== null) {
            $qb->where('lh.user_id', $userId);
        }

        return $qb->orderBy('lh.created_at', 'DESC')->get();
    }

    /**
     * Dashboard recent reports with bounded result size and eager-loaded relations.
     */
    public function getRecentForDashboard(?int $userId = null, int $limit = 5): array {
        $limit = min(50, max(1, $limit));

        $qb = new QueryBuilder();
        $qb->table('laporan_hama lh')->select(['lh.*']);

        if ($userId !== null) {
            $qb->where('lh.user_id', $userId);
        }

        $reports = $qb->orderBy('lh.created_at', 'DESC')
            ->limit($limit)
            ->get();

        $reports = $this->eagerLoad($reports, ['user', 'masterOpt', 'kabupaten', 'kecamatan', 'desa']);

        return array_map([$this, 'flattenDashboardReportRelations'], $reports);
    }

    private function flattenDashboardReportRelations(array $report): array {
        $user = $report['user'] ?? [];
        $masterOpt = $report['master_opt'] ?? [];
        $kabupaten = $report['kabupaten'] ?? [];
        $kecamatan = $report['kecamatan'] ?? [];
        $desa = $report['desa'] ?? [];

        $report['pelapor_nama'] = $user['nama_lengkap'] ?? null;
        $report['pelapor_username'] = $user['username'] ?? null;
        $report['pelapor_role'] = $user['role'] ?? null;
        $report['nama_opt'] = $masterOpt['nama_opt'] ?? null;
        $report['jenis'] = $masterOpt['jenis'] ?? null;
        $report['etl_acuan'] = $masterOpt['etl_acuan'] ?? null;
        $report['nama_kabupaten'] = $kabupaten['nama_kabupaten'] ?? null;
        $report['nama_kecamatan'] = $kecamatan['nama_kecamatan'] ?? null;
        $report['nama_desa'] = $desa['nama_desa'] ?? null;

        return $report;
    }

    /**
     * Get all reports with details by user using QueryBuilder
     */
    public function getAllWithDetailsByUser(int $userId): array {
        $qb = new QueryBuilder();
        return $qb->table('laporan_hama lh')
                  ->select([
                      'lh.*',
                      'u.nama_lengkap as pelapor_nama',
                      'u.username as pelapor_username',
                      'u.role as pelapor_role',
                      'mo.nama_opt',
                      'mo.jenis',
                      'mo.etl_acuan'
                  ])
                  ->leftJoin('users u', 'lh.user_id = u.id')
                  ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id')
                  ->where('lh.user_id', $userId)
                  ->orderBy('lh.created_at', 'DESC')
                  ->get();
    }

    /**
     * Get report count by status using QueryBuilder
     */
    public function getCountByStatus(string $status, ?int $userId = null): int {
        $qb = new QueryBuilder();
        $qb->table('laporan_hama')->where('status', $status);
        if ($userId !== null) {
            $qb->where('user_id', $userId);
        }
        return $qb->count();
    }

    /**
     * Get report count by status and user using QueryBuilder
     */
    public function getCountByStatusAndUser(string $status, int $userId): int {
        $qb = new QueryBuilder();
        return $qb->table('laporan_hama')
                  ->where('status', $status)
                  ->where('user_id', $userId)
                  ->count();
    }

    /**
     * Get total count using QueryBuilder
     */
    public function count(): int {
        $qb = new QueryBuilder();
        return $qb->table('laporan_hama')->count();
    }

    /**
     * Get top pests statistics
     */
    public function getTopPests(int $limit = 10, ?int $userId = null): array {
        try {
            $sql = "
                SELECT
                    mo.nama_opt,
                    mo.jenis,
                    COUNT(lh.id) as total_laporan,
                    AVG(lh.populasi) as avg_populasi,
                    SUM(lh.luas_serangan) as total_luas,
                    SUM(CASE WHEN lh.tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as berat,
                    SUM(CASE WHEN lh.tingkat_keparahan = 'Sedang' THEN 1 ELSE 0 END) as sedang,
                    SUM(CASE WHEN lh.tingkat_keparahan = 'Ringan' THEN 1 ELSE 0 END) as ringan
                FROM laporan_hama lh
                LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
                WHERE lh.status IN ('Submitted', 'Diverifikasi')
                AND mo.nama_opt IS NOT NULL";

            if ($userId !== null) {
                $sql .= " AND lh.user_id = :user_id";
            }

            $sql .= "
                GROUP BY mo.id, mo.nama_opt, mo.jenis
                ORDER BY total_laporan DESC
                LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            if ($userId !== null) {
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Data validation
            foreach ($results as &$row) {
                $row['total_laporan'] = (int) $row['total_laporan'];
                $row['avg_populasi'] = round((float) $row['avg_populasi'], 2);
                $row['total_luas'] = round((float) $row['total_luas'], 2);
                $row['berat'] = (int) $row['berat'];
                $row['sedang'] = (int) $row['sedang'];
                $row['ringan'] = (int) $row['ringan'];
            }

            return $results;

        } catch (PDOException $e) {
            error_log("Error in getTopPests: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get severity distribution statistics
     * @param int|null $userId Optional user ID to filter reports by user
     */
    public function getSeverityDistribution(?int $userId = null): array {
        try {
            $sql = "
                SELECT
                    tingkat_keparahan,
                    COUNT(*) as total,
                    SUM(luas_serangan) as total_luas,
                    AVG(populasi) as avg_populasi
                FROM laporan_hama
                WHERE status IN ('Submitted', 'Diverifikasi')
                AND tingkat_keparahan IS NOT NULL";

            if ($userId !== null) {
                $sql .= " AND user_id = :user_id";
            }

            $sql .= "
                GROUP BY tingkat_keparahan
                ORDER BY FIELD(tingkat_keparahan, 'Ringan', 'Sedang', 'Berat')";

            $stmt = $this->db->prepare($sql);
            if ($userId !== null) {
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ensure all severity levels exist
            $severityLevels = ['Ringan', 'Sedang', 'Berat'];
            $distribution = [];

            foreach ($severityLevels as $level) {
                $found = false;
                foreach ($results as $row) {
                    if ($row['tingkat_keparahan'] === $level) {
                        $distribution[] = [
                            'tingkat_keparahan' => $level,
                            'total' => (int) $row['total'],
                            'total_luas' => round((float) $row['total_luas'], 2),
                            'avg_populasi' => round((float) $row['avg_populasi'], 2)
                        ];
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $distribution[] = [
                        'tingkat_keparahan' => $level,
                        'total' => 0,
                        'total_luas' => 0,
                        'avg_populasi' => 0
                    ];
                }
            }

            return $distribution;

        } catch (PDOException $e) {
            error_log("Error in getSeverityDistribution: " . $e->getMessage());
            return [
                ['tingkat_keparahan' => 'Ringan', 'total' => 0, 'total_luas' => 0, 'avg_populasi' => 0],
                ['tingkat_keparahan' => 'Sedang', 'total' => 0, 'total_luas' => 0, 'avg_populasi' => 0],
                ['tingkat_keparahan' => 'Berat', 'total' => 0, 'total_luas' => 0, 'avg_populasi' => 0]
            ];
        }
    }

    /**
     * Get area statistics by month
     * @param int $year Year to get statistics for
     * @param int|null $userId Optional user ID to filter reports by user
     */
    public function getAreaStatsByMonth(int $year, ?int $userId = null): array {
        try {
            $sql = "
                SELECT
                    MONTH(tanggal) as bulan,
                    SUM(luas_serangan) as total_luas,
                    AVG(luas_serangan) as avg_luas,
                    COUNT(*) as jumlah_laporan
                FROM laporan_hama
                WHERE YEAR(tanggal) = :year
                AND status IN ('Submitted', 'Diverifikasi')
                AND luas_serangan > 0";

            if ($userId !== null) {
                $sql .= " AND user_id = :user_id";
            }

            $sql .= "
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':year', $year, PDO::PARAM_INT);
            if ($userId !== null) {
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Initialize all 12 months
            $stats = [];
            for ($i = 1; $i <= 12; $i++) {
                $stats[$i] = [
                    'bulan' => $i,
                    'total_luas' => 0,
                    'avg_luas' => 0,
                    'jumlah_laporan' => 0
                ];
            }

            // Fill in actual data
            foreach ($results as $row) {
                $month = (int) $row['bulan'];
                $stats[$month] = [
                    'bulan' => $month,
                    'total_luas' => round((float) $row['total_luas'], 2),
                    'avg_luas' => round((float) $row['avg_luas'], 2),
                    'jumlah_laporan' => (int) $row['jumlah_laporan']
                ];
            }

            return array_values($stats);

        } catch (PDOException $e) {
            error_log("Error in getAreaStatsByMonth: " . $e->getMessage());
            return [];
        }
    }



    /**
     * Verify report using QueryBuilder
     * Only allows verification of Submitted reports (business rule: Draf cannot be verified)
     */
    public function verify(int $id, int $userId, string $status, string $catatan = ''): int {
        // First check the current status of the report
        $laporan = $this->find($id);
        if (!$laporan) {
            throw new InvalidArgumentException('Laporan tidak ditemukan.');
        }
        
        // Business rule: Only Submitted reports can be verified
        if ($laporan['status'] !== 'Submitted') {
            throw new LogicException(
                "Laporan dengan status '{$laporan['status']}' tidak dapat diverifikasi. " .
                "Hanya laporan berstatus 'Submitted' yang dapat diverifikasi."
            );
        }
        
        // Validate status is one of the allowed verification statuses
        $allowedStatuses = ['Diverifikasi', 'Ditolak'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Status verifikasi tidak valid: {$status}");
        }
        
        $data = [
            'status' => $status,
            'verified_by' => $userId,
            'verified_at' => date('Y-m-d H:i:s'),
            'catatan_verifikasi' => $catatan
        ];

        $qb = new QueryBuilder();
        return $qb->table('laporan_hama')
                  ->where('id', $id)
                  ->update($data);
    }

    public function archive(int $id): bool {
        return (bool) $this->update($id, [
            'status' => 'Diarsipkan',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Generate unique nomor laporan for submitted reports
     * Format: LH-YYYYMM-NNNN (e.g., LH-202607-0001)
     */
    public function generateNomorLaporan(string $prefix = 'LH'): string {
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM laporan_hama 
             WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? 
             AND status != 'Draf'"
        );
        $stmt->execute([$year, $month]);
        $count = (int)$stmt->fetchColumn() + 1;
        
        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $count);
    }

    /**
     * Get reports for DataTables with pagination
     */
    public function getForDataTable(array $params = []): array {
        $qb = new QueryBuilder();
        $qb->table('laporan_hama lh')
           ->select([
               'lh.id',
               'lh.tanggal',
               'lh.lokasi',
               'mo.nama_opt',
               'lh.tingkat_keparahan',
               'lh.status',
               'u.nama_lengkap as pelapor',
               'lh.created_at'
           ])
           ->leftJoin('users u', 'lh.user_id = u.id')
           ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id');

        // Search functionality
        if (!empty($params['search'])) {
            $searchTerm = '%' . $params['search'] . '%';
            $qb->whereRaw("lh.lokasi LIKE ? OR mo.nama_opt LIKE ? OR u.nama_lengkap LIKE ?", [
                $searchTerm, $searchTerm, $searchTerm
            ]);
        }

        // Status filter
        if (!empty($params['status'])) {
            $qb->where('lh.status', $params['status']);
        }

        // Ordering
        $orderColumn = $params['order'][0]['column'] ?? 0;
        $orderDir = $params['order'][0]['dir'] ?? 'desc';

        $columns = ['lh.id', 'lh.tanggal', 'lh.lokasi', 'mo.nama_opt', 'lh.tingkat_keparahan', 'lh.status', 'u.nama_lengkap'];
        if (isset($columns[$orderColumn])) {
            $qb->orderBy($columns[$orderColumn], $orderDir);
        }

        // Pagination
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);

        $qb->limit($length)->offset($start);

        return $qb->get();
    }

    /**
     * Get filtered count for DataTables
     */
    public function getFilteredCount(array $params = []): int {
        $qb = new QueryBuilder();
        $qb->table('laporan_hama lh')
           ->leftJoin('users u', 'lh.user_id = u.id')
           ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id');

        // Apply same filters as getForDataTable
        if (!empty($params['search'])) {
            $searchTerm = '%' . $params['search'] . '%';
            $qb->whereRaw("lh.lokasi LIKE ? OR mo.nama_opt LIKE ? OR u.nama_lengkap LIKE ?", [
                $searchTerm, $searchTerm, $searchTerm
            ]);
        }

        if (!empty($params['status'])) {
            $qb->where('lh.status', $params['status']);
        }

        return $qb->count();
    }

    /**
     * Get dashboard statistics
     * @param int|null $userId Optional user ID to filter reports by user
     * @param bool $includeDraft Whether to include Draft reports (default false per AGENTS.md)
     */
    public function getDashboardStats(?int $userId = null, bool $includeDraft = false): array {
        try {
            $whereClauses = [];
            $params = [];

            if ($userId !== null) {
                $whereClauses[] = 'user_id = :user_id';
                $params[':user_id'] = $userId;
            }

            if (!$includeDraft) {
                $whereClauses[] = "status != 'Draf'";
            }

            $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            $sql = "
                SELECT
                    COUNT(*) as total_laporan,
                    SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as pending_verifikasi,
                    SUM(CASE WHEN status = 'Diverifikasi' THEN 1 ELSE 0 END) as terverifikasi,
                    SUM(CASE WHEN status = 'Draf' THEN 1 ELSE 0 END) as draf,
                    SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as ditolak,
                    SUM(CASE WHEN tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as keparahan_berat,
                    SUM(CASE WHEN status IN ('Submitted', 'Diverifikasi') THEN luas_serangan ELSE 0 END) as total_luas,
                    SUM(CASE WHEN status IN ('Submitted', 'Diverifikasi') THEN populasi ELSE 0 END) as total_populasi
                FROM laporan_hama {$whereSQL}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_laporan' => (int) ($result['total_laporan'] ?? 0),
                'pending_verifikasi' => (int) ($result['pending_verifikasi'] ?? 0),
                'terverifikasi' => (int) ($result['terverifikasi'] ?? 0),
                'keparahan_berat' => (int) ($result['keparahan_berat'] ?? 0),
                'draf' => (int) ($result['draf'] ?? 0),
                'ditolak' => (int) ($result['ditolak'] ?? 0),
                'total_luas' => (float) ($result['total_luas'] ?? 0),
                'total_populasi' => (int) ($result['total_populasi'] ?? 0),
                'total_reports' => (int) ($result['total_laporan'] ?? 0),
                'verified_reports' => (int) ($result['terverifikasi'] ?? 0),
                'pending_reports' => (int) ($result['pending_verifikasi'] ?? 0),
                'draft_reports' => (int) ($result['draf'] ?? 0),
                'total_area_affected' => (float) ($result['total_luas'] ?? 0),
                'total_population' => (int) ($result['total_populasi'] ?? 0)
            ];

        } catch (PDOException $e) {
            error_log("Error in getDashboardStats: " . $e->getMessage());

            return [
                'total_laporan' => 0,
                'pending_verifikasi' => 0,
                'terverifikasi' => 0,
                'keparahan_berat' => 0,
                'draf' => 0,
                'ditolak' => 0,
                'total_luas' => 0,
                'total_populasi' => 0,
                'total_reports' => 0,
                'verified_reports' => 0,
                'pending_reports' => 0,
                'draft_reports' => 0,
                'total_area_affected' => 0,
                'total_population' => 0
            ];
        }
    }

    /**
     * Get monthly statistics for a given year
     * @param int $year Year to get statistics for
     * @param int|null $userId Optional user ID to filter reports by user
     */
    public function getMonthlyStats(int $year, ?int $userId = null): array {
        try {
            $sql = "
                SELECT
                    MONTH(tanggal) as bulan,
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('Submitted', 'Diverifikasi') THEN 1 ELSE 0 END) as terverifikasi,
                    0 as pending,
                    SUM(luas_serangan) as total_luas
                FROM laporan_hama
                WHERE YEAR(tanggal) = :year";

            if ($userId !== null) {
                $sql .= " AND user_id = :user_id";
            }

            $sql .= "
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':year', $year, PDO::PARAM_INT);
            if ($userId !== null) {
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $stmt->execute();

            $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Initialize array with all months
            $stats = [];
            for ($i = 1; $i <= 12; $i++) {
                $stats[$i] = [
                    'bulan' => $i,
                    'total' => 0,
                    'terverifikasi' => 0,
                    'pending' => 0,
                    'total_luas' => 0
                ];
            }

            // Fill in actual data with validation
            foreach ($monthlyData as $data) {
                $month = (int) $data['bulan'];
                if ($month >= 1 && $month <= 12) {
                    $stats[$month] = [
                        'bulan' => $month,
                        'total' => (int) $data['total'],
                        'terverifikasi' => (int) $data['terverifikasi'],
                        'pending' => (int) $data['pending'],
                        'total_luas' => round((float) $data['total_luas'], 2)
                    ];
                }
            }

            return array_values($stats);

        } catch (PDOException $e) {
            error_log("Error in getMonthlyStats: " . $e->getMessage());

            // Return empty structure for all 12 months
            $stats = [];
            for ($i = 1; $i <= 12; $i++) {
                $stats[] = [
                    'bulan' => $i,
                    'total' => 0,
                    'terverifikasi' => 0,
                    'pending' => 0,
                    'total_luas' => 0
                ];
            }
            return $stats;
        }
    }

    /**
     * Get map data for pest distribution
     * @param int|null $userId Optional user ID to filter reports by user
     */
    public function getMapData(?int $userId = null): array {
        $sql = "
            SELECT
                lh.id,
                lh.tanggal,
                lh.lokasi,
                lh.latitude,
                lh.longitude,
                lh.tingkat_keparahan,
                lh.populasi,
                lh.luas_serangan,
                mo.nama_opt,
                mo.jenis,
                u.nama_lengkap as pelapor
            FROM laporan_hama lh
            LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
            LEFT JOIN users u ON lh.user_id = u.id
            WHERE lh.status IN ('Submitted', 'Diverifikasi')
            AND lh.latitude IS NOT NULL
            AND lh.longitude IS NOT NULL";

        if ($userId !== null) {
            $sql .= " AND lh.user_id = :user_id";
        }

        $sql .= " ORDER BY lh.tanggal DESC";

        $stmt = $this->db->prepare($sql);
        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top kecamatan statistics
     * Returns top 5 kecamatan by report count and by affected area
     * @param int $limit Number of top kecamatan to return
     * @param int|null $userId Optional user ID to filter reports by user
     */
    public function getTopKecamatan(int $limit = 5, ?int $userId = null): array {
        try {
            $result = [
                'by_count' => [],
                'by_area' => []
            ];

            // 1. Get Top by Count (Jumlah Laporan)
            $sqlCount = "
                SELECT
                    mk.nama_kecamatan,
                    COUNT(lh.id) as total_laporan
                FROM laporan_hama lh
                JOIN master_kecamatan mk ON lh.kecamatan_id = mk.id
                WHERE lh.status IN ('Submitted', 'Diverifikasi')";

            if ($userId !== null) {
                $sqlCount .= " AND lh.user_id = :user_id";
            }

            $sqlCount .= "
                GROUP BY mk.id, mk.nama_kecamatan
                ORDER BY total_laporan DESC
                LIMIT :limit";

            $stmtCount = $this->db->prepare($sqlCount);
            if ($userId !== null) {
                $stmtCount->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $stmtCount->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtCount->execute();
            $result['by_count'] = $stmtCount->fetchAll(PDO::FETCH_ASSOC);

            // 2. Get Top by Area (Luas Serangan)
            $sqlArea = "
                SELECT
                    mk.nama_kecamatan,
                    SUM(lh.luas_serangan) as total_luas
                FROM laporan_hama lh
                JOIN master_kecamatan mk ON lh.kecamatan_id = mk.id
                WHERE lh.status IN ('Submitted', 'Diverifikasi')";

            if ($userId !== null) {
                $sqlArea .= " AND lh.user_id = :user_id";
            }

            $sqlArea .= "
                GROUP BY mk.id, mk.nama_kecamatan
                ORDER BY total_luas DESC
                LIMIT :limit";

            $stmtArea = $this->db->prepare($sqlArea);
            if ($userId !== null) {
                $stmtArea->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $stmtArea->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtArea->execute();
            $result['by_area'] = $stmtArea->fetchAll(PDO::FETCH_ASSOC);

            // Format numbers
            foreach ($result['by_area'] as &$row) {
                $row['total_luas'] = round((float) $row['total_luas'], 2);
            }

            return $result;

        } catch (PDOException $e) {
            error_log("Error in getTopKecamatan: " . $e->getMessage());
            return [
                'by_count' => [],
                'by_area' => []
            ];
        }
    }

    /**
     * Get report by ID (API compatibility)
     */
    public function getById($id) {
        $qb = new QueryBuilder();
        return $qb->table('laporan_hama lh')
            ->select([
                'lh.*',
                'u.nama_lengkap as pelapor',
                'mo.nama_opt',
                'md.nama_desa',
                'mk.nama_kecamatan',
                'mkab.nama_kabupaten'
            ])
            ->leftJoin('users u', 'lh.user_id = u.id')
            ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id')
            ->leftJoin('master_desa md', 'lh.desa_id = md.id')
            ->leftJoin('master_kecamatan mk', 'md.kecamatan_id = mk.id')
            ->leftJoin('master_kabupaten mkab', 'mk.kabupaten_id = mkab.id')
            ->where('lh.id', $id)
            ->first();
    }

    /**
     * Get all reports with filters (API compatibility)
     */
    public function getAllWithFilters($filters = [], $limit = 20, $offset = 0) {
        $page = intdiv($offset, max(1, $limit)) + 1;

        $qb = new QueryBuilder();
        $qb->table('laporan_hama lh')
            ->select([
                'lh.*',
                'u.nama_lengkap as pelapor',
                'mo.nama_opt'
            ])
            ->leftJoin('users u', 'lh.user_id = u.id')
            ->leftJoin('master_opt mo', 'lh.master_opt_id = mo.id');

        // Apply filters
        if (!empty($filters['search'])) {
            $qb->where('lh.lokasi', '%' . $filters['search'] . '%', 'LIKE');
        }

        if (!empty($filters['status'])) {
            $qb->where('lh.status', $filters['status']);
        }

        if (!empty($filters['kabupaten_id'])) {
            $qb->where('lh.kabupaten_id', $filters['kabupaten_id']);
        }

        if (!empty($filters['kecamatan_id'])) {
            $qb->where('lh.kecamatan_id', $filters['kecamatan_id']);
        }

        if (!empty($filters['desa_id'])) {
            $qb->where('lh.desa_id', $filters['desa_id']);
        }

        if (!empty($filters['master_opt_id'])) {
            $qb->where('lh.master_opt_id', $filters['master_opt_id']);
        }

        if (!empty($filters['user_id'])) {
            $qb->where('lh.user_id', $filters['user_id']);
        }

        $qb->orderBy('lh.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $qb->get();
    }

    /**
     * Get count with filters (API compatibility)
     */
    public function getCountWithFilters($filters = []) {
        $qb = new QueryBuilder();
        $qb->table('laporan_hama lh');

        // Apply same filters
        if (!empty($filters['search'])) {
            $qb->where('lh.lokasi', '%' . $filters['search'] . '%', 'LIKE');
        }

        if (!empty($filters['status'])) {
            $qb->where('lh.status', $filters['status']);
        }

        if (!empty($filters['kabupaten_id'])) {
            $qb->where('lh.kabupaten_id', $filters['kabupaten_id']);
        }

        if (!empty($filters['kecamatan_id'])) {
            $qb->where('lh.kecamatan_id', $filters['kecamatan_id']);
        }

        if (!empty($filters['desa_id'])) {
            $qb->where('lh.desa_id', $filters['desa_id']);
        }

        if (!empty($filters['master_opt_id'])) {
            $qb->where('lh.master_opt_id', $filters['master_opt_id']);
        }

        if (!empty($filters['user_id'])) {
            $qb->where('lh.user_id', $filters['user_id']);
        }

        return $qb->count();
    }

    public function getTotalCount(?int $userId = null): int {
        if ($userId !== null) {
            return $this->getCountByStatusAndUser('Submitted', $userId)
                + $this->getCountByStatusAndUser('Diverifikasi', $userId);
        }

        return $this->getCountByStatus('Submitted') + $this->getCountByStatus('Diverifikasi');
    }

    public function getMonthlyTrends($period = 30, ?int $userId = null): array {
        $days = max(1, (int)$period);
        $sql = "
            SELECT DATE(tanggal) as tanggal, COUNT(*) as total
            FROM laporan_hama
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ";
        $params = [$days];
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        $sql .= " GROUP BY DATE(tanggal) ORDER BY tanggal ASC";
        return $this->query($sql, $params);
    }

    public function getTopOPT($limit = 10, ?int $userId = null): array {
        return $this->getTopPests((int)$limit, $userId);
    }

    public function getAreaStatistics(?int $userId = null): array {
        $sql = "
            SELECT
                mk.nama_kecamatan,
                COUNT(lh.id) as total_laporan,
                SUM(lh.luas_serangan) as total_luas
            FROM laporan_hama lh
            LEFT JOIN master_kecamatan mk ON lh.kecamatan_id = mk.id
            WHERE 1=1
        ";
        $params = [];
        if ($userId !== null) {
            $sql .= " AND lh.user_id = ?";
            $params[] = $userId;
        }
        $sql .= " GROUP BY lh.kecamatan_id, mk.nama_kecamatan ORDER BY total_laporan DESC";
        return $this->query($sql, $params);
    }

    public function getRecentActivities($limit = 10, ?int $userId = null): array {
        $sql = "
            SELECT
                lh.id,
                lh.tingkat_keparahan,
                lh.created_at,
                mo.nama_opt as opt_name,
                md.nama_desa as desa_name,
                u.nama_lengkap as user_name
            FROM laporan_hama lh
            LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
            LEFT JOIN master_desa md ON lh.desa_id = md.id
            LEFT JOIN users u ON lh.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if ($userId !== null) {
            $sql .= " AND lh.user_id = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY lh.created_at DESC LIMIT ?";
        $params[] = (int)$limit;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $idx => $param) {
            $stmt->bindValue($idx + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCriticalReports(?int $userId = null): array {
        $sql = "
            SELECT id, tanggal, tingkat_keparahan, lokasi
            FROM laporan_hama
            WHERE tingkat_keparahan = 'Berat' AND status IN ('Submitted', 'Diverifikasi')
        ";
        $params = [];
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY tanggal DESC LIMIT 20";
        return $this->query($sql, $params);
    }

    public function getPendingVerifications(): array {
        return [];
    }

    public function getOldPendingReports($olderThanDays = 7): int {
        return 0;
    }

    /**
     * AJAX pagination: fetch a page of reports with full details
     * Used by the Daftar Laporan page with per-page dropdown
     *
     * @param array $filters    Optional search/status/kecamatan filters
     * @param int   $page      Page number (1-indexed)
     * @param int   $perPage   Rows per page (-1 = ALL)
     * @param int   $userId    Optional user filter
     * @return array{rows: array, total: int, page: int, perPage: int, totalPages: int, statusCounts: array}
     */
    public function fetchPaginated(array $filters = [], int $page = 1, int $perPage = 10, ?int $userId = null): array {
        error_log("[LaporanHama::fetchPaginated] Entered - page=$page, perPage=$perPage, userId=$userId, filters=" . json_encode($filters));

        try {
            $where = ['1=1'];
            $params = [];

            // Role-based: petugas only sees their own
            if ($userId !== null) {
                $where[] = 'lh.user_id = ?';
                $params[] = $userId;
                error_log("[LaporanHama::fetchPaginated] Filtering by user_id=$userId");
            }

            // Status filter
            if (!empty($filters['status'])) {
                $statusVal = trim((string)$filters['status']);
                $statusMap = [
                    'draft' => 'Draf',
                    'draf' => 'Draf',
                    'submitted' => 'Submitted',
                    'diverifikasi' => 'Diverifikasi',
                    'verified' => 'Diverifikasi',
                    'ditolak' => 'Ditolak',
                    'rejected' => 'Ditolak',
                    'diarsipkan' => 'Diarsipkan',
                    'archived' => 'Diarsipkan',
                ];
                $normalizedStatus = $statusMap[strtolower($statusVal)] ?? $statusVal;
                $where[] = 'lh.status = ?';
                $params[] = $normalizedStatus;
                error_log("[LaporanHama::fetchPaginated] Filtering by status={$normalizedStatus}");
            }

            // Search
            if (!empty($filters['search'])) {
                $q = '%' . $filters['search'] . '%';
                $where[] = '(lh.lokasi LIKE ? OR mo.nama_opt LIKE ? OR u.nama_lengkap LIKE ? OR lh.catatan LIKE ?)';
                $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
                error_log("[LaporanHama::fetchPaginated] Searching with query='{$filters['search']}'");
            }

            // OPT filter
            if (!empty($filters['master_opt_id'])) {
                $where[] = 'lh.master_opt_id = ?';
                $params[] = $filters['master_opt_id'];
                error_log("[LaporanHama::fetchPaginated] Filtering by master_opt_id={$filters['master_opt_id']}");
            }

            // Kecamatan filter
            if (!empty($filters['kecamatan_id'])) {
                $where[] = 'lh.kecamatan_id = ?';
                $params[] = $filters['kecamatan_id'];
                error_log("[LaporanHama::fetchPaginated] Filtering by kecamatan_id={$filters['kecamatan_id']}");
            }

            $whereSQL = implode(' AND ', $where);
            error_log("[LaporanHama::fetchPaginated] WHERE clause: $whereSQL");
            error_log("[LaporanHama::fetchPaginated] Params: " . json_encode($params));

            // Total count
            $countSQL = "SELECT COUNT(*) FROM laporan_hama lh LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id LEFT JOIN users u ON lh.user_id = u.id WHERE $whereSQL";
            $stmt = $this->db->prepare($countSQL);
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            error_log("[LaporanHama::fetchPaginated] Total records: $total");

            // Total pages
            $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
            $page = max(1, min($page, $totalPages));

            // Fetch rows
            $orderCol = $filters['order_col'] ?? 'lh.tanggal';
            $orderDir = in_array(strtoupper($filters['order_dir'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($filters['order_dir'] ?? 'DESC') : 'DESC';
            $safeOrder = preg_replace('/[^a-zA-Z0-9_.]/', '', $orderCol);

            $dataSQL = "SELECT
                lh.id,
                lh.tanggal,
                lh.lokasi,
                mo.nama_opt,
                mo.jenis,
                mo.etl_acuan,
                lh.tingkat_keparahan,
                lh.populasi,
                lh.luas_serangan,
                lh.foto_url,
                lh.status,
                lh.catatan,
                lh.catatan_verifikasi,
                lh.created_at,
                u.nama_lengkap AS pelapor,
                u.role AS pelapor_role,
                kec.nama_kecamatan AS kecamatan,
                des.nama_desa AS desa
            FROM laporan_hama lh
            LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
            LEFT JOIN users u ON lh.user_id = u.id
            LEFT JOIN master_kecamatan kec ON lh.kecamatan_id = kec.id
            LEFT JOIN master_desa des ON lh.desa_id = des.id
            WHERE $whereSQL
            ORDER BY $safeOrder $orderDir";

            if ($perPage > 0) {
                $offset = ($page - 1) * $perPage;
                $dataSQL .= " LIMIT $perPage OFFSET $offset";
            }

            error_log("[LaporanHama::fetchPaginated] Executing data query");
            $stmt = $this->db->prepare($dataSQL);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("[LaporanHama::fetchPaginated] Fetched " . count($rows) . " rows");

            // Debug: log first row structure if any
            if (!empty($rows)) {
                error_log("[LaporanHama::fetchPaginated] Sample row keys: " . implode(', ', array_keys($rows[0])));
            }

            // Status counts for filter badges
            $statusSQL = "SELECT status, COUNT(*) as cnt FROM laporan_hama lh WHERE " . ($userId !== null ? "lh.user_id = ? AND " : "") . "1=1 GROUP BY status";
            $sParams = $userId !== null ? [$userId] : [];
            $sStmt = $this->db->prepare($statusSQL);
            $sStmt->execute($sParams);
            $statusCounts = [];
            while ($r = $sStmt->fetch(PDO::FETCH_ASSOC)) {
                $statusCounts[$r['status']] = (int)$r['cnt'];
            }
            error_log("[LaporanHama::fetchPaginated] Status counts: " . json_encode($statusCounts));

            return [
                'rows'         => $rows,
                'total'        => $total,
                'page'         => $page,
                'perPage'      => $perPage,
                'totalPages'   => $totalPages,
                'statusCounts' => $statusCounts,
            ];

        } catch (PDOException $e) {
            error_log("[LaporanHama::fetchPaginated] PDOException: " . $e->getMessage());
            error_log("[LaporanHama::fetchPaginated] SQL State: " . $e->getCode());
            error_log("[LaporanHama::fetchPaginated] Trace: " . $e->getTraceAsString());
            throw $e; // Re-throw to be caught by controller
        } catch (Exception $e) {
            error_log("[LaporanHama::fetchPaginated] Exception: " . $e->getMessage());
            error_log("[LaporanHama::fetchPaginated] Trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}
