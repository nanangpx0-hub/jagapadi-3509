<?php

declare(strict_types=1);

final class MasterOptService
{
    public const JENIS = ['hama', 'penyakit', 'gulma'];
    public const STATUS_KARANTINA = ['Tidak', 'OPTK A1', 'OPTK A2', 'OPTK B'];
    public const TINGKAT_BAHAYA = ['Rendah', 'Sedang', 'Tinggi', 'Sangat Tinggi'];

    private const TEXT_MAX = [
        'kode_opt' => 50,
        'nama_opt' => 150,
        'nama_ilmiah' => 200,
        'nama_lokal' => 200,
        'kategori' => 50,
        'status_karantina' => 50,
        'tingkat_bahaya' => 50,
        'kingdom' => 100,
        'filum' => 100,
        'kelas' => 100,
        'ordo' => 100,
        'famili' => 100,
        'genus' => 100,
        'satuan_etl' => 30,
        'foto_url' => 300,
    ];

    private const OPTIONAL_TEXT = [
        'nama_ilmiah',
        'nama_lokal',
        'kategori',
        'kingdom',
        'filum',
        'kelas',
        'ordo',
        'famili',
        'genus',
        'satuan_etl',
        'deskripsi',
        'rekomendasi',
        'referensi',
        'status_karantina',
        'tingkat_bahaya',
        'foto_url',
    ];

    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function pdo(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getInstance()->getConnection();
        }

        return $this->db;
    }

    public function normalize(array $input): array
    {
        $data = [];
        foreach (array_keys(self::TEXT_MAX) as $field) {
            $data[$field] = trim((string) ($input[$field] ?? ''));
        }
        $data['jenis'] = strtolower(trim((string) ($input['jenis'] ?? '')));
        $data['etl_acuan'] = isset($input['etl_acuan']) && $input['etl_acuan'] !== ''
            ? (float) $input['etl_acuan']
            : null;
        $data['deskripsi'] = trim((string) ($input['deskripsi'] ?? ''));
        $data['rekomendasi'] = trim((string) ($input['rekomendasi'] ?? ''));
        $data['referensi'] = trim((string) ($input['referensi'] ?? ''));
        $data['aktif'] = !empty($input['aktif']) ? 1 : 0;

        if ($data['status_karantina'] === '') {
            $data['status_karantina'] = 'Tidak';
        }
        if ($data['tingkat_bahaya'] === '') {
            $data['tingkat_bahaya'] = 'Sedang';
        }

        foreach (self::OPTIONAL_TEXT as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * @return string[] daftar pesan error; kosong berarti valid.
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (($data['kode_opt'] ?? '') === '') {
            $errors[] = 'Kode OPT wajib diisi';
        }
        if (($data['nama_opt'] ?? '') === '') {
            $errors[] = 'Nama OPT (nasional) wajib diisi';
        }
        if (!in_array($data['jenis'] ?? '', self::JENIS, true)) {
            $errors[] = 'Jenis OPT tidak valid';
        }
        if (isset($data['status_karantina'])
            && $data['status_karantina'] !== null
            && !in_array($data['status_karantina'], self::STATUS_KARANTINA, true)) {
            $errors[] = 'Status karantina tidak valid';
        }
        if (isset($data['tingkat_bahaya'])
            && $data['tingkat_bahaya'] !== null
            && !in_array($data['tingkat_bahaya'], self::TINGKAT_BAHAYA, true)) {
            $errors[] = 'Tingkat bahaya tidak valid';
        }
        if (isset($data['etl_acuan']) && $data['etl_acuan'] !== null && $data['etl_acuan'] < 0) {
            $errors[] = 'ETL acuan tidak boleh negatif';
        }

        foreach (self::TEXT_MAX as $field => $max) {
            $value = $data[$field] ?? null;
            if (is_string($value) && mb_strlen($value) > $max) {
                $label = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} maksimal {$max} karakter";
            }
        }

        foreach (['deskripsi', 'rekomendasi', 'referensi'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && mb_strlen($value) > 5000) {
                $label = ucfirst($field);
                $errors[] = "{$label} maksimal 5000 karakter";
            }
        }

        return $errors;
    }

    /**
     * Kandidat duplikat case-insensitive pada kode/nama nasional/nama lokal.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findDuplicates(array $data, int $excludeId = 0): array
    {
        $clauses = ['LOWER(nama_opt) = LOWER(?)'];
        $params = [(string) ($data['nama_opt'] ?? '')];

        $kode = trim((string) ($data['kode_opt'] ?? ''));
        if ($kode !== '') {
            $clauses[] = '(kode_opt IS NOT NULL AND LOWER(kode_opt) = LOWER(?))';
            $params[] = $kode;
        }

        $lokal = trim((string) ($data['nama_lokal'] ?? ''));
        if ($lokal !== '') {
            $clauses[] = '(nama_lokal IS NOT NULL AND nama_lokal <> \'\' AND LOWER(nama_lokal) = LOWER(?))';
            $params[] = $lokal;
        }

        $sql = 'SELECT id, kode_opt, nama_opt, nama_ilmiah, nama_lokal, jenis, aktif
                FROM master_opt WHERE (' . implode(' OR ', $clauses) . ')';
        if ($excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY aktif DESC, id ASC LIMIT 10';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ambil dan kunci master dengan nama nasional yang sama. ID dan kode master
     * dipertahankan ketika usulan terbaru menggantikan isinya.
     *
     * @return array<string,mixed>|null
     */
    public function findSameNameForUpdate(string $name): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM master_opt WHERE LOWER(TRIM(nama_opt)) = LOWER(TRIM(?)) ORDER BY id ASC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $data */
    public function replaceKeepingIdentity(int $id, array $data): void
    {
        $columns = [
            'nama_opt', 'nama_ilmiah', 'nama_lokal', 'jenis',
            'status_karantina', 'tingkat_bahaya', 'kategori', 'kingdom',
            'filum', 'kelas', 'ordo', 'famili', 'genus', 'etl_acuan',
            'satuan_etl', 'foto_url', 'deskripsi', 'rekomendasi', 'referensi', 'aktif',
        ];
        $sets = array_map(static fn (string $column): string => "`{$column}` = ?", $columns);
        $params = array_map(static fn (string $column): mixed => $data[$column] ?? null, $columns);
        $params[] = $id;

        $stmt = $this->pdo()->prepare(
            'UPDATE master_opt SET ' . implode(', ', $sets) . ' WHERE id = ?'
        );
        $stmt->execute($params);
    }

    /**
     * Pencarian master aktif terpaginasikan untuk autocomplete merge.
     *
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    public function searchActive(string $query, ?string $jenis, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT id, kode_opt, nama_opt, nama_ilmiah, nama_lokal, jenis, etl_acuan, satuan_etl, foto_url
                FROM master_opt WHERE aktif = 1';
        $params = [];

        $q = trim($query);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' AND (kode_opt LIKE ? OR nama_opt LIKE ? OR nama_ilmiah LIKE ? OR nama_lokal LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        if ($jenis !== null && in_array($jenis, self::JENIS, true)) {
            $sql .= ' AND jenis = ?';
            $params[] = $jenis;
        }

        $sql .= ' ORDER BY nama_opt ASC LIMIT ' . ($perPage + 1) . ' OFFSET ' . $offset;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $perPage);
        }

        return ['items' => $rows, 'has_more' => $hasMore];
    }

    /**
     * Insert master baru. Melempar DuplicateMasterException bila
     * unique constraint nama_opt dilanggar (perlomba antar transaksi).
     */
    public function insert(array $data): int
    {
        $columns = [
            'kode_opt', 'nama_opt', 'nama_ilmiah', 'nama_lokal', 'jenis',
            'status_karantina', 'tingkat_bahaya', 'kategori', 'kingdom',
            'filum', 'kelas', 'ordo', 'famili', 'genus', 'etl_acuan',
            'satuan_etl', 'foto_url', 'deskripsi', 'rekomendasi', 'referensi', 'aktif',
        ];

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO master_opt (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

        $params = [];
        foreach ($columns as $column) {
            $params[] = $data[$column] ?? null;
        }

        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                throw new DuplicateMasterException('Master OPT dengan kode atau nama yang sama sudah ada.');
            }
            throw $e;
        }

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Generate the next code while the caller holds master_opt_code_generation.
     */
    public function nextAutomaticCode(string $jenis): string
    {
        $prefix = match ($jenis) {
            'hama' => 'OPT-H-',
            'penyakit' => 'OPT-P-',
            'gulma' => 'OPT-G-',
            default => throw new InvalidArgumentException('Jenis OPT tidak valid.'),
        };
        $stmt = $this->pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(kode_opt, ?) AS UNSIGNED)), 0) '
            . 'FROM master_opt WHERE kode_opt LIKE ?'
        );
        $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
        return $prefix . str_pad((string) ((int) $stmt->fetchColumn() + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Hapus cache turunan MasterOpt setelah data master berubah.
     */
    public static function clearMasterOptCache(): void
    {
        if (!class_exists('Cache')) {
            return;
        }
        Cache::delete('master_opt_stats');
        Cache::delete('master_opt_filter_options');
        foreach (self::JENIS as $jenis) {
            Cache::delete('master_opt_jenis_' . $jenis);
        }
    }
}
