<?php
/**
 * Data KSA Bulanan Model
 * Model untuk data hasil Survei Kerangka Sampel Area (KSA) BPS dengan
 * granularitas bulanan per kabupaten/kota Jawa Timur.
 *
 * Tabel ini menyimpan data bulanan (2018-2025 angka tetap + 2026 potensi/sementara),
 * berbeda dengan data_pertanian_bps yang menyimpan agregat tahunan.
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

declare(strict_types=1);

class DataKsaBulanan extends Model {

    protected $table = 'data_ksa_bulanan';

    private const LOG_TABLE = 'bps_scraping_logs';

    /**
     * Get all data dengan filter.
     *
     * @param array $filters tahun, bulan, kabupaten_kota, kode_wilayah, status_data
     */
    public function getAll(array $filters, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($filters['tahun'])) {
            $sql .= " AND tahun = ?";
            $params[] = (int) $filters['tahun'];
        }
        if (!empty($filters['bulan'])) {
            $sql .= " AND bulan = ?";
            $params[] = (int) $filters['bulan'];
        }
        if (!empty($filters['kabupaten_kota'])) {
            $sql .= " AND kabupaten_kota LIKE ?";
            $params[] = '%' . $filters['kabupaten_kota'] . '%';
        }
        if (!empty($filters['kode_wilayah'])) {
            $sql .= " AND kode_wilayah = ?";
            $params[] = (string) $filters['kode_wilayah'];
        }
        if (!empty($filters['status_data'])) {
            $sql .= " AND status_data = ?";
            $params[] = (string) $filters['status_data'];
        }

        $sql .= " ORDER BY tahun DESC, bulan DESC, kabupaten_kota ASC";
        $sql .= " LIMIT " . max(1, (int) $limit) . " OFFSET " . max(0, (int) $offset);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hitung jumlah record sesuai filter.
     */
    public function getCountWithFilters(array $filters): int {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($filters['tahun'])) {
            $sql .= " AND tahun = ?";
            $params[] = (int) $filters['tahun'];
        }
        if (!empty($filters['bulan'])) {
            $sql .= " AND bulan = ?";
            $params[] = (int) $filters['bulan'];
        }
        if (!empty($filters['kabupaten_kota'])) {
            $sql .= " AND kabupaten_kota LIKE ?";
            $params[] = '%' . $filters['kabupaten_kota'] . '%';
        }
        if (!empty($filters['kode_wilayah'])) {
            $sql .= " AND kode_wilayah = ?";
            $params[] = (string) $filters['kode_wilayah'];
        }
        if (!empty($filters['status_data'])) {
            $sql .= " AND status_data = ?";
            $params[] = (string) $filters['status_data'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get record by primary key.
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Get record by unique key (tahun, bulan, kode_wilayah).
     */
    public function findByTahunBulanWilayah(int $tahun, int $bulan, string $kodeWilayah): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE tahun = ? AND bulan = ? AND kode_wilayah = ?"
        );
        $stmt->execute([$tahun, $bulan, $kodeWilayah]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Insert atau update berdasarkan unique key uk_ksa (tahun, bulan, kode_wilayah).
     *
     * @return bool true bila query berhasil dieksekusi
     */
    public function upsert(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}`
                (tahun, bulan, kabupaten_kota, kode_wilayah, luas_panen,
                 produksi_gabah, produksi_beras, produktivitas, status_data,
                 sumber_file, sumber_sheet, keterangan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 kabupaten_kota = VALUES(kabupaten_kota),
                 luas_panen = VALUES(luas_panen),
                 produksi_gabah = VALUES(produksi_gabah),
                 produksi_beras = VALUES(produksi_beras),
                 produktivitas = VALUES(produktivitas),
                 status_data = VALUES(status_data),
                 sumber_file = VALUES(sumber_file),
                 sumber_sheet = VALUES(sumber_sheet),
                 keterangan = VALUES(keterangan)"
        );

        return $stmt->execute([
            (int) $data['tahun'],
            (int) $data['bulan'],
            (string) $data['kabupaten_kota'],
            (string) $data['kode_wilayah'],
            $data['luas_panen'] ?? null,
            $data['produksi_gabah'] ?? null,
            $data['produksi_beras'] ?? null,
            $data['produktivitas'] ?? null,
            $data['status_data'] ?? 'tetap',
            $data['sumber_file'] ?? null,
            $data['sumber_sheet'] ?? null,
            $data['keterangan'] ?? null,
        ]);
    }

    /**
     * Upsert dengan status perubahan untuk keperluan statistik import.
     *
     * @return int 1=inserted, 2=updated, 0=no-change (nilai identik)
     */
    public function upsertWithStatus(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}`
                (tahun, bulan, kabupaten_kota, kode_wilayah, luas_panen,
                 produksi_gabah, produksi_beras, produktivitas, status_data,
                 sumber_file, sumber_sheet, keterangan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 kabupaten_kota = VALUES(kabupaten_kota),
                 luas_panen = VALUES(luas_panen),
                 produksi_gabah = VALUES(produksi_gabah),
                 produksi_beras = VALUES(produksi_beras),
                 produktivitas = VALUES(produktivitas),
                 status_data = VALUES(status_data),
                 sumber_file = VALUES(sumber_file),
                 sumber_sheet = VALUES(sumber_sheet),
                 keterangan = VALUES(keterangan)"
        );

        $stmt->execute([
            (int) $data['tahun'],
            (int) $data['bulan'],
            (string) $data['kabupaten_kota'],
            (string) $data['kode_wilayah'],
            $data['luas_panen'] ?? null,
            $data['produksi_gabah'] ?? null,
            $data['produksi_beras'] ?? null,
            $data['produktivitas'] ?? null,
            $data['status_data'] ?? 'tetap',
            $data['sumber_file'] ?? null,
            $data['sumber_sheet'] ?? null,
            $data['keterangan'] ?? null,
        ]);

        return (int) $stmt->rowCount();
    }

    /**
     * Hapus seluruh data untuk tahun-bulan tertentu.
     */
    public function deleteByTahunBulan(int $tahun, int $bulan): bool {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE tahun = ? AND bulan = ?");
        return $stmt->execute([$tahun, $bulan]);
    }

    /**
     * Agregat tahunan per kabupaten/kota.
     *
     * @param bool $includeSementara true  = semua status (tetap + sementara + potensi)
     *                               false = hanya status 'tetap' (eksklusif potensi & sementara)
     */
    public function getAggregateByTahun(int $tahun, bool $includeSementara = true): array {
        $sql = "SELECT
                    kode_wilayah,
                    kabupaten_kota,
                    SUM(luas_panen)     AS luas_panen_tahunan,
                    SUM(produksi_gabah) AS produksi_gabah_tahunan,
                    SUM(produksi_beras) AS produksi_beras_tahunan,
                    ROUND(SUM(produksi_gabah) / NULLIF(SUM(luas_panen), 0) * 10, 4) AS produktivitas,
                    COUNT(*) AS jumlah_bulan
                FROM `{$this->table}`
                WHERE tahun = ?";
        $params = [$tahun];

        if (!$includeSementara) {
            $sql .= " AND status_data = 'tetap'";
        }

        $sql .= " GROUP BY kode_wilayah, kabupaten_kota ORDER BY kabupaten_kota ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Time series bulanan untuk satu wilayah (kode_wilayah) dalam rentang tahun.
     */
    public function getMonthlyTimeseries(string $kodeWilayah, int $tahunFrom, int $tahunTo): array {
        $stmt = $this->db->prepare(
            "SELECT tahun, bulan, luas_panen, produksi_gabah, produksi_beras,
                    produktivitas, status_data
             FROM `{$this->table}`
             WHERE kode_wilayah = ? AND tahun BETWEEN ? AND ?
             ORDER BY tahun ASC, bulan ASC"
        );
        $stmt->execute([$kodeWilayah, $tahunFrom, $tahunTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agregasi luas panen per bulan untuk line chart.
     * Jika kabupaten kosong, nilai merupakan total seluruh Jawa Timur.
     */
    public function getMonthlyHarvestAreaChart(int $tahun, string $kabupaten = ''): array {
        $sql = "SELECT
                    bulan,
                    SUM(luas_panen) AS luas_panen,
                    COUNT(DISTINCT kode_wilayah) AS jumlah_wilayah
                FROM `{$this->table}`
                WHERE tahun = ?";
        $params = [$tahun];

        if ($kabupaten !== '') {
            $sql .= " AND kabupaten_kota LIKE ?";
            $params[] = '%' . $kabupaten . '%';
        }

        $sql .= " GROUP BY bulan ORDER BY bulan ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistik ringkas per tahun: jumlah kabupaten, total luas, total produksi,
     * dan rincian status_data.
     */
    public function getStatsByTahun(int $tahun): array {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(DISTINCT kode_wilayah) AS jumlah_kabupaten,
                COUNT(*)                     AS jumlah_record,
                COUNT(DISTINCT bulan)        AS jumlah_bulan,
                SUM(luas_panen)              AS total_luas_panen,
                SUM(produksi_gabah)          AS total_produksi_gabah,
                SUM(produksi_beras)          AS total_produksi_beras,
                SUM(CASE WHEN status_data = 'tetap' THEN 1 ELSE 0 END)      AS jumlah_tetap,
                SUM(CASE WHEN status_data = 'sementara' THEN 1 ELSE 0 END)  AS jumlah_sementara,
                SUM(CASE WHEN status_data = 'potensi' THEN 1 ELSE 0 END)    AS jumlah_potensi
             FROM `{$this->table}`
             WHERE tahun = ?"
        );
        $stmt->execute([$tahun]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return [
                'tahun' => $tahun,
                'jumlah_kabupaten' => 0,
                'jumlah_record' => 0,
                'jumlah_bulan' => 0,
                'total_luas_panen' => null,
                'total_produksi_gabah' => null,
                'total_produksi_beras' => null,
                'jumlah_tetap' => 0,
                'jumlah_sementara' => 0,
                'jumlah_potensi' => 0,
            ];
        }
        $row['tahun'] = $tahun;
        return $row;
    }

    /**
     * Kabupaten/kota dengan jumlah bulan tidak lengkap untuk tahun tertentu.
     */
    public function getIncompleteKabupaten(int $tahun, int $targetBulan = 12): array {
        $stmt = $this->db->prepare(
            "SELECT kode_wilayah, kabupaten_kota, COUNT(DISTINCT bulan) AS jumlah_bulan
             FROM `{$this->table}`
             WHERE tahun = ?
             GROUP BY kode_wilayah, kabupaten_kota
             HAVING jumlah_bulan < ?
             ORDER BY kabupaten_kota ASC"
        );
        $stmt->execute([$tahun, $targetBulan]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Daftar tahun yang tersedia di tabel.
     */
    public function getAvailableYears(): array {
        $stmt = $this->db->query(
            "SELECT DISTINCT tahun FROM `{$this->table}` ORDER BY tahun DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Log aktivitas import/sync ke tabel bps_scraping_logs.
     */
    public function logImport(string $action, string $status, string $msg, array $detail): void {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO `" . self::LOG_TABLE . "` (action, status, message, details)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$action, $status, $msg, json_encode($detail)]);
        } catch (Throwable $e) {
            error_log("[DataKsaBulanan] Gagal menulis log import: " . $e->getMessage());
        }
    }
}
