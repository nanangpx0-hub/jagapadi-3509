<?php

declare(strict_types=1);

/**
 * Data harga gabah dan beras Kabupaten Jember.
 *
 * Satu observasi unik ditentukan oleh tanggal, komoditas, lokasi, dan sumber.
 * Kolom metode_data membedakan data aktual, estimasi, simulasi, dan input manual.
 */
class HargaKomoditas
{
    private PDO $db;
    private string $table = 'harga_komoditas';
    private string $logTable = 'harga_komoditas_logs';
    private string $alertTable = 'harga_alerts';

    public const GABAH_KERING_PANEN = 'gabah_kering_panen';
    public const GABAH_KERING_GILING = 'gabah_kering_giling';
    public const BERAS_MEDIUM = 'beras_medium';
    public const BERAS_PREMIUM = 'beras_premium';

    public const ALERT_THRESHOLD = 5.0;
    public const CRITICAL_THRESHOLD = 10.0;

    private const DATA_METHODS = ['aktual', 'estimasi', 'simulasi', 'manual'];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->createTablesIfNotExist();
    }

    /**
     * @return array{0:string,1:array<int, mixed>}
     */
    private function buildWhere(array $filters = [], string $alias = ''): array
    {
        $prefix = $alias === '' ? '' : rtrim($alias, '.') . '.';
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['start_date'])) {
            $conditions[] = "{$prefix}tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $conditions[] = "{$prefix}tanggal <= ?";
            $params[] = $filters['end_date'];
        }

        $commodity = (string) ($filters['jenis_komoditas'] ?? '');
        if ($commodity === 'gabah') {
            $conditions[] = "{$prefix}jenis_komoditas IN ('gabah_kering_panen', 'gabah_kering_giling')";
        } elseif ($commodity === 'beras') {
            $conditions[] = "{$prefix}jenis_komoditas IN ('beras_medium', 'beras_premium')";
        } elseif ($commodity !== '') {
            if (!array_key_exists($commodity, self::getKomoditasTypes())) {
                throw new InvalidArgumentException('Jenis komoditas tidak valid');
            }
            $conditions[] = "{$prefix}jenis_komoditas = ?";
            $params[] = $commodity;
        }

        if (!empty($filters['lokasi'])) {
            $conditions[] = "{$prefix}lokasi LIKE ?";
            $params[] = '%' . trim((string) $filters['lokasi']) . '%';
        }
        if (!empty($filters['sumber_data'])) {
            $conditions[] = "{$prefix}sumber_data = ?";
            $params[] = $filters['sumber_data'];
        } elseif (!empty($filters['sumber_data_like'])) {
            $conditions[] = "{$prefix}sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }

        $method = (string) ($filters['metode_data'] ?? '');
        if ($method === 'non_simulasi' || !empty($filters['exclude_simulasi'])) {
            $conditions[] = "{$prefix}metode_data <> 'simulasi'";
        } elseif ($method !== '' && $method !== 'semua') {
            if (!in_array($method, self::DATA_METHODS, true)) {
                throw new InvalidArgumentException('Metode data tidak valid');
            }
            $conditions[] = "{$prefix}metode_data = ?";
            $params[] = $method;
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function getAll(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT * FROM {$this->table} WHERE {$where}
                ORDER BY tanggal DESC, jenis_komoditas ASC, lokasi ASC, id DESC";

        if (array_key_exists('limit', $filters)) {
            $limit = max(1, min(500, (int) $filters['limit']));
            $offset = max(0, (int) ($filters['offset'] ?? 0));
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengembalikan satu rata-rata lintas lokasi/sumber pada tanggal terbaru
     * untuk setiap komoditas, bukan baris arbitrer yang kebetulan terakhir.
     */
    public function getLatestPrices(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT tanggal, jenis_komoditas, ROUND(AVG(harga), 2) AS harga,
                    'Rp/kg' AS satuan, 'Rata-rata seluruh lokasi' AS lokasi,
                    'Agregasi data terfilter' AS sumber_data, COUNT(*) AS jumlah_data
             FROM {$this->table}
             WHERE {$where}
             GROUP BY tanggal, jenis_komoditas
             ORDER BY tanggal DESC, jenis_komoditas"
        );
        $stmt->execute($params);

        $latest = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $commodity = $row['jenis_komoditas'];
            if (!isset($latest[$commodity])) {
                $latest[$commodity] = $row;
            }
        }
        ksort($latest);
        return array_values($latest);
    }

    /**
     * Upsert idempoten berdasarkan grain observasi unik.
     *
     * @return string inserted|updated|unchanged
     */
    public function upsert(array $data, bool $refreshAlert = true): string
    {
        $normalized = $this->normalizeRecord($data);
        $existingStmt = $this->db->prepare(
            "SELECT harga, satuan, kode_wilayah, metode_data, keterangan
             FROM {$this->table}
             WHERE tanggal = ? AND jenis_komoditas = ? AND lokasi = ? AND sumber_data = ?
             LIMIT 1"
        );
        $existingStmt->execute([
            $normalized['tanggal'],
            $normalized['jenis_komoditas'],
            $normalized['lokasi'],
            $normalized['sumber_data'],
        ]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table}
                (tanggal, jenis_komoditas, harga, satuan, lokasi, kode_wilayah,
                 sumber_data, metode_data, keterangan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                harga = VALUES(harga), satuan = VALUES(satuan),
                kode_wilayah = VALUES(kode_wilayah), metode_data = VALUES(metode_data),
                keterangan = VALUES(keterangan), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            $normalized['tanggal'],
            $normalized['jenis_komoditas'],
            $normalized['harga'],
            $normalized['satuan'],
            $normalized['lokasi'],
            $normalized['kode_wilayah'],
            $normalized['sumber_data'],
            $normalized['metode_data'],
            $normalized['keterangan'],
        ]);

        $action = 'inserted';
        if ($existing !== false) {
            $comparable = [
                'harga' => (float) $existing['harga'],
                'satuan' => (string) $existing['satuan'],
                'kode_wilayah' => (string) ($existing['kode_wilayah'] ?? ''),
                'metode_data' => (string) $existing['metode_data'],
                'keterangan' => (string) ($existing['keterangan'] ?? ''),
            ];
            $incoming = [
                'harga' => (float) $normalized['harga'],
                'satuan' => (string) $normalized['satuan'],
                'kode_wilayah' => (string) ($normalized['kode_wilayah'] ?? ''),
                'metode_data' => (string) $normalized['metode_data'],
                'keterangan' => (string) ($normalized['keterangan'] ?? ''),
            ];
            $action = $comparable === $incoming ? 'unchanged' : 'updated';
        }

        if ($refreshAlert) {
            $this->rebuildAlerts($normalized['jenis_komoditas']);
        }
        return $action;
    }

    public function insert(array $data): bool
    {
        $this->upsert($data);
        return true;
    }

    public function update(int $id, array $data): bool
    {
        $old = $this->getById($id);
        if ($old === false) {
            return false;
        }
        $normalized = $this->normalizeRecord(array_merge($old, $data));
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET
                tanggal = ?, jenis_komoditas = ?, harga = ?, satuan = ?, lokasi = ?,
                kode_wilayah = ?, sumber_data = ?, metode_data = ?, keterangan = ?
             WHERE id = ?"
        );
        $result = $stmt->execute([
            $normalized['tanggal'],
            $normalized['jenis_komoditas'],
            $normalized['harga'],
            $normalized['satuan'],
            $normalized['lokasi'],
            $normalized['kode_wilayah'],
            $normalized['sumber_data'],
            $normalized['metode_data'],
            $normalized['keterangan'],
            $id,
        ]);
        if ($result) {
            $this->rebuildAlerts((string) $old['jenis_komoditas']);
            if ($old['jenis_komoditas'] !== $normalized['jenis_komoditas']) {
                $this->rebuildAlerts($normalized['jenis_komoditas']);
            }
        }
        return $result;
    }

    public function delete(int $id): bool
    {
        $old = $this->getById($id);
        if ($old === false) {
            return false;
        }
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result && $stmt->rowCount() > 0) {
            $this->rebuildAlerts((string) $old['jenis_komoditas']);
            return true;
        }
        return false;
    }

    public function deleteMultiple(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT DISTINCT jenis_komoditas FROM {$this->table} WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $commodities = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare("DELETE FROM {$this->table} WHERE id IN ({$placeholders})");
            $delete->execute($ids);
            $deleted = $delete->rowCount();
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        foreach ($commodities as $commodity) {
            $this->rebuildAlerts((string) $commodity);
        }
        return $deleted;
    }

    public function getStatistics(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT jenis_komoditas, ROUND(AVG(harga), 0) AS rata_rata,
                    MAX(harga) AS tertinggi, MIN(harga) AS terendah, COUNT(*) AS total_records
             FROM {$this->table}
             WHERE {$where}
             GROUP BY jenis_komoditas ORDER BY jenis_komoditas"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverallStats(array $filters = []): array
    {
        $gabah = $this->getCategorySeries('gabah', $filters, 2);
        $beras = $this->getCategorySeries('beras', $filters, 2);

        return [
            'harga_gabah' => isset($gabah[0]) ? (float) $gabah[0]['harga'] : 0.0,
            'harga_beras' => isset($beras[0]) ? (float) $beras[0]['harga'] : 0.0,
            'perubahan_gabah' => $this->seriesChange($gabah),
            'perubahan_beras' => $this->seriesChange($beras),
            'total_records' => $this->countAll($filters),
        ];
    }

    private function getCategorySeries(string $category, array $filters, int $limit): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $categoryCondition = $category === 'gabah'
            ? "jenis_komoditas IN ('gabah_kering_panen', 'gabah_kering_giling')"
            : "jenis_komoditas IN ('beras_medium', 'beras_premium')";
        $limit = max(1, min(366, $limit));
        $stmt = $this->db->prepare(
            "SELECT tanggal, ROUND(AVG(harga), 2) AS harga
             FROM {$this->table}
             WHERE {$where} AND {$categoryCondition}
             GROUP BY tanggal ORDER BY tanggal DESC LIMIT {$limit}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function seriesChange(array $series): float
    {
        if (count($series) < 2 || (float) $series[1]['harga'] <= 0) {
            return 0.0;
        }
        return round(((float) $series[0]['harga'] - (float) $series[1]['harga']) / (float) $series[1]['harga'] * 100, 2);
    }

    public function calculatePriceChange(string $commodity, array $filters = []): float
    {
        if (!array_key_exists($commodity, self::getKomoditasTypes())) {
            return 0.0;
        }
        $filters['jenis_komoditas'] = $commodity;
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT tanggal, ROUND(AVG(harga), 2) AS harga
             FROM {$this->table} WHERE {$where}
             GROUP BY tanggal ORDER BY tanggal DESC LIMIT 2"
        );
        $stmt->execute($params);
        return $this->seriesChange($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getMonthlyAverage(?int $year = null, ?string $commodity = null): array
    {
        $filters = ['start_date' => ($year ?? (int) date('Y')) . '-01-01', 'end_date' => ($year ?? (int) date('Y')) . '-12-31'];
        if ($commodity !== null) {
            $filters['jenis_komoditas'] = $commodity;
        }
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT MONTH(tanggal) AS bulan, jenis_komoditas, ROUND(AVG(harga), 0) AS rata_rata
             FROM {$this->table} WHERE {$where}
             GROUP BY MONTH(tanggal), jenis_komoditas ORDER BY bulan, jenis_komoditas"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTrendAnalysis(?string $startDate = null, ?string $endDate = null, array $filters = []): array
    {
        $filters['start_date'] = $startDate ?: date('Y-m-d', strtotime('-30 days'));
        $filters['end_date'] = $endDate ?: date('Y-m-d');
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT tanggal, jenis_komoditas, ROUND(AVG(harga), 0) AS harga
             FROM {$this->table} WHERE {$where}
             GROUP BY tanggal, jenis_komoditas ORDER BY tanggal, jenis_komoditas"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPriceComparison(int $months = 6, array $filters = []): array
    {
        $months = max(1, min(60, $months));
        $filters['start_date'] = $filters['start_date'] ?? date('Y-m-d', strtotime("-{$months} months"));
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode,
                    CASE WHEN jenis_komoditas LIKE 'gabah%' THEN 'Gabah' ELSE 'Beras' END AS kategori,
                    ROUND(AVG(harga), 0) AS rata_rata
             FROM {$this->table} WHERE {$where}
             GROUP BY DATE_FORMAT(tanggal, '%Y-%m'),
                      CASE WHEN jenis_komoditas LIKE 'gabah%' THEN 'Gabah' ELSE 'Beras' END
             ORDER BY periode, kategori"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPriceByLocation(?string $commodity = null, array $filters = []): array
    {
        if ($commodity !== null && $commodity !== '') {
            $filters['jenis_komoditas'] = $commodity;
        }
        $filters['start_date'] = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        [$where, $params] = $this->buildWhere($filters, 'h');
        $stmt = $this->db->prepare(
            "SELECT h.lokasi, h.jenis_komoditas, ROUND(AVG(h.harga), 0) AS rata_rata,
                    MAX(h.harga) AS tertinggi, MIN(h.harga) AS terendah, COUNT(*) AS jumlah_data,
                    COALESCE(MAX(m.latitude), IF(LOWER(h.lokasi) = 'jember', -8.1706, NULL)) AS latitude,
                    COALESCE(MAX(m.longitude), IF(LOWER(h.lokasi) = 'jember', 113.7003, NULL)) AS longitude
             FROM {$this->table} h
             LEFT JOIN master_kecamatan m
               ON LOWER(TRIM(SUBSTRING_INDEX(h.lokasi, ',', 1))) = LOWER(m.nama_kecamatan)
             WHERE {$where}
             GROUP BY h.lokasi, h.jenis_komoditas
             HAVING latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY h.lokasi, h.jenis_komoditas"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableYears(): array
    {
        return $this->db->query(
            "SELECT DISTINCT YEAR(tanggal) AS tahun FROM {$this->table} ORDER BY tahun DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Rebuild alert memakai rata-rata harian dan mempertahankan status sudah dibaca.
     */
    public function rebuildAlerts(?string $commodity = null): int
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        $commodities = $commodity !== null ? [$commodity] : array_keys(self::getKomoditasTypes());
        $created = 0;
        try {
            foreach ($commodities as $type) {
                if (!array_key_exists($type, self::getKomoditasTypes())) {
                    continue;
                }
                $readStmt = $this->db->prepare("SELECT tanggal, is_read FROM {$this->alertTable} WHERE jenis_komoditas = ?");
                $readStmt->execute([$type]);
                $readState = [];
                foreach ($readStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $readState[$row['tanggal']] = (int) $row['is_read'];
                }

                $delete = $this->db->prepare("DELETE FROM {$this->alertTable} WHERE jenis_komoditas = ?");
                $delete->execute([$type]);

                $seriesStmt = $this->db->prepare(
                    "SELECT tanggal, ROUND(AVG(harga), 2) AS harga
                     FROM {$this->table}
                     WHERE jenis_komoditas = ? AND metode_data <> 'simulasi'
                     GROUP BY tanggal ORDER BY tanggal"
                );
                $seriesStmt->execute([$type]);
                $series = $seriesStmt->fetchAll(PDO::FETCH_ASSOC);
                $previous = null;
                foreach ($series as $point) {
                    $current = (float) $point['harga'];
                    if ($previous !== null && $previous > 0) {
                        $signedChange = ($current - $previous) / $previous * 100;
                        if (abs($signedChange) >= self::ALERT_THRESHOLD) {
                            $insert = $this->db->prepare(
                                "INSERT INTO {$this->alertTable}
                                    (jenis_komoditas, tipe_alert, persentase, harga_sebelum,
                                     harga_sesudah, tanggal, is_read)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)"
                            );
                            $insert->execute([
                                $type,
                                $signedChange > 0 ? 'naik' : 'turun',
                                round(abs($signedChange), 2),
                                $previous,
                                $current,
                                $point['tanggal'],
                                $readState[$point['tanggal']] ?? 0,
                            ]);
                            $created++;
                        }
                    }
                    $previous = $current;
                }
            }
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $created;
    }

    public function checkAndGenerateAlert(string $commodity, string $date): void
    {
        // Rebuild mengoreksi alert pada tanggal berikutnya juga ketika histori berubah.
        $this->rebuildAlerts($commodity);
    }

    public function getAlerts(int $limit = 20, bool $unreadOnly = false): array
    {
        $limit = max(1, min(100, $limit));
        $where = $unreadOnly ? ' WHERE is_read = 0' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->alertTable}{$where} ORDER BY tanggal DESC, id DESC LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnreadAlerts(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM {$this->alertTable} WHERE is_read = 0")->fetchColumn();
    }

    public function markAlertRead(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->alertTable} SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function markAllAlertsRead(): int
    {
        return $this->db->exec("UPDATE {$this->alertTable} SET is_read = 1 WHERE is_read = 0");
    }

    public function logActivity(string $action, string $status, string $message, array $details = []): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->logTable} (action, status, message, details) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$action, $status, $message, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }

    public function getRecentLogs(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->query(
            "SELECT * FROM {$this->logTable} ORDER BY created_at DESC LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeRecord(array $data): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['tanggal'] ?? ''));
        if ($date === false || $date->format('Y-m-d') !== (string) ($data['tanggal'] ?? '')) {
            throw new InvalidArgumentException('Tanggal tidak valid');
        }
        $commodity = (string) ($data['jenis_komoditas'] ?? '');
        if (!array_key_exists($commodity, self::getKomoditasTypes())) {
            throw new InvalidArgumentException('Jenis komoditas tidak valid');
        }
        if (!is_numeric($data['harga'] ?? null)) {
            throw new InvalidArgumentException('Harga harus berupa angka');
        }
        $price = (float) $data['harga'];
        if ($price <= 0 || $price > 100000) {
            throw new InvalidArgumentException('Harga harus antara Rp1 dan Rp100.000 per kg');
        }
        $method = strtolower((string) ($data['metode_data'] ?? 'manual'));
        if (!in_array($method, self::DATA_METHODS, true)) {
            throw new InvalidArgumentException('Metode data tidak valid');
        }
        $location = trim((string) ($data['lokasi'] ?? 'Jember'));
        $source = trim((string) ($data['sumber_data'] ?? 'Manual'));
        if ($location === '' || mb_strlen($location) > 100) {
            throw new InvalidArgumentException('Lokasi tidak valid');
        }
        if ($source === '' || mb_strlen($source) > 100) {
            throw new InvalidArgumentException('Sumber data tidak valid');
        }

        return [
            'tanggal' => $date->format('Y-m-d'),
            'jenis_komoditas' => $commodity,
            'harga' => $price,
            'satuan' => trim((string) ($data['satuan'] ?? 'Rp/kg')) ?: 'Rp/kg',
            'lokasi' => $location,
            'kode_wilayah' => trim((string) ($data['kode_wilayah'] ?? '35.09')) ?: null,
            'sumber_data' => $source,
            'metode_data' => $method,
            'keterangan' => isset($data['keterangan']) && trim((string) $data['keterangan']) !== ''
                ? trim((string) $data['keterangan'])
                : null,
        ];
    }

    private function tableExists(string $tableName): bool
    {
        try {
            return $this->db->query("SELECT 1 FROM {$tableName} LIMIT 1") !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function createTablesIfNotExist(): void
    {
        if (!$this->tableExists($this->table)) {
            $this->db->exec(
                "CREATE TABLE {$this->table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tanggal DATE NOT NULL,
                    jenis_komoditas ENUM('gabah_kering_panen','gabah_kering_giling','beras_medium','beras_premium') NOT NULL,
                    harga DECIMAL(12,2) NOT NULL,
                    satuan VARCHAR(20) NOT NULL DEFAULT 'Rp/kg',
                    lokasi VARCHAR(100) NOT NULL DEFAULT 'Jember',
                    kode_wilayah VARCHAR(20) NULL,
                    sumber_data VARCHAR(100) NOT NULL DEFAULT 'Manual',
                    metode_data ENUM('aktual','estimasi','simulasi','manual') NOT NULL DEFAULT 'manual',
                    keterangan TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_harga_observation (tanggal, jenis_komoditas, lokasi, sumber_data),
                    INDEX idx_harga_filter (tanggal, jenis_komoditas),
                    INDEX idx_harga_method (metode_data, sumber_data),
                    INDEX idx_lokasi (lokasi)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (!$this->tableExists($this->logTable)) {
            $this->db->exec(
                "CREATE TABLE {$this->logTable} (
                    id INT AUTO_INCREMENT PRIMARY KEY, action VARCHAR(50), status VARCHAR(20),
                    message TEXT, details JSON, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (!$this->tableExists($this->alertTable)) {
            $this->db->exec(
                "CREATE TABLE {$this->alertTable} (
                    id INT AUTO_INCREMENT PRIMARY KEY, jenis_komoditas VARCHAR(50) NOT NULL,
                    tipe_alert ENUM('naik','turun','fluktuasi') NOT NULL, persentase DECIMAL(5,2),
                    harga_sebelum DECIMAL(12,2), harga_sesudah DECIMAL(12,2), tanggal DATE NOT NULL,
                    is_read BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_harga_alert_daily (jenis_komoditas, tanggal)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public static function getKomoditasLabel(string $code): string
    {
        return self::getKomoditasTypes()[$code] ?? $code;
    }

    public static function getKomoditasTypes(): array
    {
        return [
            self::GABAH_KERING_PANEN => 'Gabah Kering Panen (GKP)',
            self::GABAH_KERING_GILING => 'Gabah Kering Giling (GKG)',
            self::BERAS_MEDIUM => 'Beras Medium',
            self::BERAS_PREMIUM => 'Beras Premium',
        ];
    }

    public static function formatHarga(float|int|string $price): string
    {
        return 'Rp ' . number_format((float) $price, 0, ',', '.');
    }
}
