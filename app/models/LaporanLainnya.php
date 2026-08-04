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

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'petugas' && isset($_SESSION['user_id'])) {
            $qb->where('ll.user_id', $_SESSION['user_id']);
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

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'petugas' && isset($_SESSION['user_id'])) {
            $qb->where('ll.user_id', $_SESSION['user_id']);
        }

        return $qb->count();
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

    public function submitReport(int $id): bool {
        return $this->update($id, [
            'status' => 'submitted',
            'verified_by' => null,
            'verified_at' => null,
            'catatan_verifikasi' => null,
        ]);
    }

    public function isOwner(int $id, int $userId): bool {
        $report = $this->find($id);
        return $report !== null && $report['user_id'] === $userId;
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
        return $report['user_id'] === $userId && $report['status'] === 'draft';
    }

    public function generateKodeLaporan(): string {
        $year = date('Ymd');
        $prefix = 'LL';
        $lockName = "kode_laporan_ll_{$year}";

        $stmt = $this->db->prepare("SELECT GET_LOCK(?, 10)");
        $stmt->execute([$lockName]);
        $lockAcquired = (int)$stmt->fetchColumn();

        if (!$lockAcquired) {
            throw new Exception('Failed to acquire lock for kode laporan generation');
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM laporan_lainnya WHERE kode_laporan LIKE ?"
            );
            $stmt->execute(["{$prefix}-{$year}-%"]);
            $count = (int)$stmt->fetchColumn() + 1;

            return sprintf('%s-%s-%04d', $prefix, $year, $count);
        } finally {
            $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$lockName]);
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
}