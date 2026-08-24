<?php

declare(strict_types=1);

class UsulanOpt extends Model
{
    public const STATUS_DRAFT = 'Draf';
    public const STATUS_PENDING = 'Menunggu Review';
    public const STATUS_REVISION = 'Perlu Perbaikan';
    public const STATUS_APPROVED = 'Disetujui';
    public const STATUS_MERGED = 'Digabungkan';
    public const STATUS_REJECTED = 'Ditolak Permanen';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_REVISION,
        self::STATUS_APPROVED,
        self::STATUS_MERGED,
        self::STATUS_REJECTED,
    ];

    public const OWNER_EDITABLE = [self::STATUS_DRAFT, self::STATUS_REVISION];

    protected $table = 'usulan_opt';

    protected $fillable = [
        'user_id', 'nama_nasional', 'nama_lokal', 'jenis', 'komoditas',
        'tanggal_ditemukan', 'kabupaten_id', 'kecamatan_id', 'desa_id',
        'alamat_lokasi', 'latitude', 'longitude', 'bagian_terserang',
        'pola_gejala', 'estimasi_terdampak', 'satuan_terdampak',
        'tingkat_keyakinan', 'sumber_identifikasi',
        'ciri_ciri', 'wilayah', 'foto_url', 'status', 'master_opt_id',
        'catatan_review', 'reviewed_by', 'reviewed_at', 'submitted_at',
    ];

    private const FILTER_STATUS = self::STATUSES;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->query(
            'SELECT * FROM usulan_opt WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
            [$userId]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function pending(): array
    {
        return $this->query(
            "SELECT uo.*, u.nama_lengkap AS nama_pengusul
             FROM usulan_opt uo JOIN users u ON u.id = uo.user_id
             WHERE uo.status = ? AND uo.deleted_at IS NULL ORDER BY uo.created_at DESC",
            [self::STATUS_PENDING]
        );
    }

    /**
     * Daftar usulan terpaginasikan dengan filter aman.
     *
     * @param array{status?:string,jenis?:string,q?:string,date_from?:string,date_to?:string} $filters
     * @return array{data:array,total:int,per_page:int,current_page:int,last_page:int,from:int,to:int}
     */
    public function paginateFiltered(array $filters, int $page = 1, int $perPage = 10, ?int $userId = null): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['uo.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::FILTER_STATUS, true)) {
            $where[] = 'uo.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['jenis']) && in_array($filters['jenis'], ['hama', 'penyakit', 'gulma'], true)) {
            $where[] = 'uo.jenis = ?';
            $params[] = $filters['jenis'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(uo.nama_nasional LIKE ? OR uo.nama_lokal LIKE ? OR uo.wilayah LIKE ? OR uo.alamat_lokasi LIKE ? OR u.nama_lengkap LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $dateFrom = $this->normalizeDate($filters['date_from'] ?? null);
        if ($dateFrom !== null) {
            $where[] = 'uo.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = $this->normalizeDate($filters['date_to'] ?? null);
        if ($dateTo !== null) {
            $where[] = 'uo.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        if ($userId !== null && $userId > 0) {
            $where[] = 'uo.user_id = ?';
            $params[] = $userId;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM usulan_opt uo JOIN users u ON u.id = uo.user_id' . $whereSql;
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $dataSql = 'SELECT uo.*, u.nama_lengkap AS nama_pengusul, u.username AS pengusul_username,
                           mo.nama_opt AS master_nama_opt, mo.kode_opt AS master_kode_opt,
                           ru.nama_lengkap AS reviewer_nama
                    FROM usulan_opt uo
                    JOIN users u ON u.id = uo.user_id
                    LEFT JOIN master_opt mo ON mo.id = uo.master_opt_id
                    LEFT JOIN users ru ON ru.id = uo.reviewed_by'
            . $whereSql
            . " ORDER BY (uo.status = '" . self::STATUS_PENDING . "') DESC,
                      (uo.status = '" . self::STATUS_REVISION . "') DESC,
                      uo.created_at DESC, uo.id DESC"
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($dataSql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Data ekspor dengan filter dan scope ownership yang sama seperti halaman daftar.
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportFiltered(array $filters, ?int $userId, int $limit = 10000): array
    {
        $limit = max(1, min(UsulanOptExcelService::MAX_EXPORT_ROWS, $limit));
        $where = ['uo.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::FILTER_STATUS, true)) {
            $where[] = 'uo.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['jenis']) && in_array($filters['jenis'], ['hama', 'penyakit', 'gulma'], true)) {
            $where[] = 'uo.jenis = ?';
            $params[] = $filters['jenis'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(uo.nama_nasional LIKE ? OR uo.nama_lokal LIKE ? OR uo.wilayah LIKE ? OR uo.alamat_lokasi LIKE ? OR u.nama_lengkap LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $dateFrom = $this->normalizeDate($filters['date_from'] ?? null);
        if ($dateFrom !== null) {
            $where[] = 'uo.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        $dateTo = $this->normalizeDate($filters['date_to'] ?? null);
        if ($dateTo !== null) {
            $where[] = 'uo.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($userId !== null && $userId > 0) {
            $where[] = 'uo.user_id = ?';
            $params[] = $userId;
        }

        $sql = 'SELECT uo.*, u.nama_lengkap AS nama_pengusul,
                       mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa
                FROM usulan_opt uo
                JOIN users u ON u.id = uo.user_id
                LEFT JOIN master_kabupaten mk ON mk.id = uo.kabupaten_id
                LEFT JOIN master_kecamatan mkc ON mkc.id = uo.kecamatan_id
                LEFT JOIN master_desa md ON md.id = uo.desa_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY uo.created_at DESC, uo.id DESC LIMIT ' . ($limit + 1);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > $limit) {
            throw new LengthException('Hasil ekspor melebihi batas ' . $limit . ' baris. Persempit filter.');
        }

        return $rows;
    }

    /**
     * Statistik ringkas per status.
     *
     * @return array{total:int,by_status:array<string,int>}
     */
    public function getStats(?int $userId = null): array
    {
        $sql = 'SELECT status, COUNT(*) AS total FROM usulan_opt WHERE deleted_at IS NULL';
        $params = [];
        if ($userId !== null && $userId > 0) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' GROUP BY status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $byStatus = array_fill_keys(self::STATUSES, 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
        }

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Detail satu usulan beserta pengusul/reviewer/master dan nama wilayah.
     *
     * @return array<string,mixed>|null
     */
    public function findByIdDetailed(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT uo.*, u.nama_lengkap AS nama_pengusul, u.username AS pengusul_username,
                    mo.nama_opt AS master_nama_opt, mo.kode_opt AS master_kode_opt,
                    ru.nama_lengkap AS reviewer_nama,
                    mk.nama_kabupaten, mkd.nama_kecamatan, md.nama_desa
             FROM usulan_opt uo
             JOIN users u ON u.id = uo.user_id
             LEFT JOIN master_opt mo ON mo.id = uo.master_opt_id
             LEFT JOIN users ru ON ru.id = uo.reviewed_by
             LEFT JOIN master_kabupaten mk ON mk.id = uo.kabupaten_id
             LEFT JOIN master_kecamatan mkd ON mkd.id = uo.kecamatan_id
             LEFT JOIN master_desa md ON md.id = uo.desa_id
              WHERE uo.id = ? AND uo.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function countReports(int $proposalId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM laporan_hama WHERE usulan_opt_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$proposalId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getPhotos(int $proposalId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM usulan_opt_photos WHERE usulan_opt_id = ? ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$proposalId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findPhoto(int $photoId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usulan_opt_photos WHERE id = ? LIMIT 1');
        $stmt->execute([$photoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function countPhotos(int $proposalId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usulan_opt_photos WHERE usulan_opt_id = ?');
        $stmt->execute([$proposalId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $data kolom: file_path,mime_type,size_bytes,checksum,caption,created_by
     */
    public function addPhoto(int $proposalId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usulan_opt_photos (usulan_opt_id, file_path, mime_type, size_bytes, checksum, caption, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $proposalId,
            $data['file_path'],
            $data['mime_type'] ?? null,
            $data['size_bytes'] ?? null,
            $data['checksum'] ?? null,
            $data['caption'] ?? null,
            $data['created_by'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deletePhotoRow(int $photoId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM usulan_opt_photos WHERE id = ?');

        return $stmt->execute([$photoId]);
    }

    public function addHistory(int $proposalId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $catatan): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usulan_opt_status_history (usulan_opt_id, from_status, to_status, changed_by, catatan)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$proposalId, $fromStatus, $toStatus, $changedBy, $catatan]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getHistory(int $proposalId): array
    {
        $stmt = $this->db->prepare(
            'SELECT h.*, u.nama_lengkap AS actor_nama
             FROM usulan_opt_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.usulan_opt_id = ?
             ORDER BY h.created_at ASC, h.id ASC'
        );
        $stmt->execute([$proposalId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update bersyarat status lama; mengembalikan jumlah baris terpengaruh
     * sehingga pemanggil dapat mendeteksi konflik konkurensi (lost update).
     */
    public function conditionalUpdateWithStatus(int $id, string $expectedStatus, array $fields): int
    {
        $allowed = [
            'nama_nasional', 'nama_lokal', 'jenis', 'komoditas', 'tanggal_ditemukan',
            'kabupaten_id', 'kecamatan_id', 'desa_id', 'alamat_lokasi', 'latitude',
            'longitude', 'bagian_terserang', 'pola_gejala', 'estimasi_terdampak',
            'satuan_terdampak', 'tingkat_keyakinan', 'sumber_identifikasi',
            'ciri_ciri', 'wilayah', 'foto_url', 'status', 'submitted_at',
            'catatan_review',
        ];

        $sets = [];
        $params = [];
        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                throw new InvalidArgumentException("Kolom tidak diizinkan: {$column}");
            }
            $sets[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $params[] = $id;
        $params[] = $expectedStatus;

        $sql = 'UPDATE usulan_opt SET ' . implode(', ', $sets)
            . ' WHERE id = ? AND status = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
