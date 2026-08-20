<?php
/**
 * EvaluasiAkurasi Model
 * Model untuk evaluasi akurasi estimasi daerah vs rilis BPS
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class EvaluasiAkurasi {
    
    private $db;
    private $table = 'evaluasi_akurasi_panen';
    private $logTable = 'evaluasi_akurasi_logs';
    private $sourceTable = 'data_ksa_bulanan';
    
    // Status akurasi thresholds
    const BIAS_SANGAT_AKURAT = 5;    // < 5%
    const BIAS_PERLU_PERHATIAN = 10; // 5% - 10%
    
    // Nama bulan Indonesia
    const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTablesIfNotExist();
    }
    
    /**
     * Get all evaluasi data with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND periode_tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        if (!empty($filters['bulan'])) {
            $sql .= " AND periode_bulan = ?";
            $params[] = $filters['bulan'];
        }
        
        if (!empty($filters['wilayah_id'])) {
            $sql .= " AND wilayah_id = ?";
            $params[] = $filters['wilayah_id'];
        }
        
        if (!empty($filters['status_akurasi'])) {
            $sql .= " AND status_akurasi = ?";
            $params[] = $filters['status_akurasi'];
        }
        
        $sql .= " ORDER BY periode_tahun DESC, periode_bulan ASC, nama_wilayah ASC";
        
        if (isset($filters['limit'])) {
            $limit = (int) $filters['limit'];
            $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get by periode and wilayah
     */
    public function getByPeriodeWilayah($bulan, $tahun, $wilayahId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE periode_bulan = ? AND periode_tahun = ? AND wilayah_id = ?"
        );
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert new evaluasi record
     */
    public function insert($data) {
        try {
            $sql = "INSERT INTO {$this->table} 
                    (periode_bulan, periode_tahun, wilayah_id, nama_wilayah, 
                     luas_estimasi_daerah, luas_rilis_bps, catatan_analisis, 
                     snapshot_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURRENT_TIMESTAMP)";
            
            $wilayahId = !empty($data['wilayah_id'])
                ? (int) $data['wilayah_id']
                : (int) (sprintf('%u', crc32(strtolower(trim((string) $data['nama_wilayah'])))) % 2147483647);

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['periode_bulan'],
                $data['periode_tahun'],
                $wilayahId,
                $data['nama_wilayah'],
                $data['luas_estimasi_daerah'] ?? 0,
                $data['luas_rilis_bps'] ?? null,
                $data['catatan_analisis'] ?? null
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                
                // Calculate deviation if both values present
                if (array_key_exists('luas_rilis_bps', $data) && $data['luas_rilis_bps'] !== null) {
                    $this->hitungDeviasi($id);
                }
                
                $this->logActivity('insert', 'success', "Data evaluasi baru ditambahkan", ['id' => $id]);
                return ['success' => true, 'id' => $id, 'message' => 'Data berhasil ditambahkan'];
            }
            
            return ['success' => false, 'message' => 'Gagal menambahkan data'];
            
        } catch (Exception $e) {
            $this->logActivity('insert', 'failed', $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update evaluasi record
     */
    public function update($id, $data) {
        try {
            $existing = $this->getById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'Data tidak ditemukan'];
            }
            
            $sql = "UPDATE {$this->table} SET 
                        periode_bulan = ?,
                        periode_tahun = ?,
                        nama_wilayah = ?,
                        luas_estimasi_daerah = ?,
                        luas_rilis_bps = ?,
                        catatan_analisis = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['periode_bulan'] ?? $existing['periode_bulan'],
                $data['periode_tahun'] ?? $existing['periode_tahun'],
                $data['nama_wilayah'] ?? $existing['nama_wilayah'],
                $data['luas_estimasi_daerah'] ?? $existing['luas_estimasi_daerah'],
                $data['luas_rilis_bps'] ?? $existing['luas_rilis_bps'],
                $data['catatan_analisis'] ?? $existing['catatan_analisis'],
                $id
            ]);
            
            if ($result) {
                // Recalculate deviation
                $this->hitungDeviasi($id);
                $this->logActivity('update', 'success', "Data evaluasi ID {$id} diupdate", []);
                return ['success' => true, 'message' => 'Data berhasil diupdate'];
            }
            
            return ['success' => false, 'message' => 'Gagal mengupdate data'];
            
        } catch (Exception $e) {
            $this->logActivity('update', 'failed', $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete evaluasi record (no validation required per user request)
     */
    public function delete($id) {
        try {
            $existing = $this->getById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'Data tidak ditemukan'];
            }
            
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->logActivity('delete', 'success', "Data evaluasi ID {$id} dihapus", [
                    'deleted_data' => $existing
                ]);
                return ['success' => true, 'message' => 'Data berhasil dihapus'];
            }
            
            return ['success' => false, 'message' => 'Gagal menghapus data'];
            
        } catch (Exception $e) {
            $this->logActivity('delete', 'failed', $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete with snapshot backup
     * Creates a backup snapshot before deleting the record
     */
    public function deleteWithSnapshot($id) {
        try {
            $existing = $this->getById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'Data tidak ditemukan'];
            }
            
            // Log the snapshot backup before delete
            $this->logActivity('snapshot_backup', 'success', 
                "Backup sebelum hapus ID {$id}",
                ['backup_data' => $existing]
            );
            
            // Then delete
            return $this->delete($id);
            
        } catch (Exception $e) {
            $this->logActivity('delete_with_snapshot', 'failed', $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Snapshot Estimasi
     * Mengambil luas panen bulanan KSA BPS untuk periode yang sama.
     * 
     * @param int $bulan Bulan (1-12)
     * @param int $tahun Tahun
     * @return array Result with success status and message
     */
    public function snapshotEstimasi($bulan, $tahun) {
        // Date restriction removed per user request: "Snapshot process can be performed at any time"
        // if ($tahun == $currentYear && $bulan == $currentMonth && $today > 10) { ... }
        
        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->db->beginTransaction();
            }
            
            // Use the monthly KSA source. Annual aggregates must never be copied
            // into an arbitrary month because that multiplies the estimate.
            $sql = "SELECT 
                        kode_wilayah,
                        kabupaten_kota as nama_wilayah,
                        luas_panen as total_luas_panen,
                        status_data
                    FROM {$this->sourceTable}
                    WHERE tahun = ? AND bulan = ? AND luas_panen IS NOT NULL
                    ORDER BY kabupaten_kota";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tahun, $bulan]);
            $sourceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($sourceData)) {
                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => "Tidak ada data KSA bulanan untuk periode {$bulan}/{$tahun}"
                ];
            }
            
            $insertedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            
            foreach ($sourceData as $data) {
                $digits = preg_replace('/\D+/', '', (string) $data['kode_wilayah']);
                $wilayahId = (int) $digits;
                if ($wilayahId <= 0) {
                    $wilayahId = (int) (sprintf('%u', crc32((string) $data['kode_wilayah'])) % 2147483647);
                }
                
                // Check if record already exists and is locked
                $existing = $this->getByPeriodeWilayah($bulan, $tahun, $wilayahId);
                
                if ($existing && $existing['snapshot_locked']) {
                    $skippedCount++;
                    continue;
                }
                
                if ($existing) {
                    // Update existing record
                    $updateSql = "UPDATE {$this->table} SET 
                                    luas_estimasi_daerah = ?,
                                    nama_wilayah = ?,
                                    snapshot_date = CURDATE(),
                                    updated_at = CURRENT_TIMESTAMP
                                  WHERE id = ?";
                    $updateStmt = $this->db->prepare($updateSql);
                    $updateStmt->execute([
                        $data['total_luas_panen'],
                        $data['nama_wilayah'],
                        $existing['id']
                    ]);
                    $updatedCount++;
                } else {
                    // Insert new record
                    $insertSql = "INSERT INTO {$this->table} 
                                    (periode_bulan, periode_tahun, wilayah_id, nama_wilayah, 
                                     luas_estimasi_daerah, snapshot_date, created_at)
                                  VALUES (?, ?, ?, ?, ?, CURDATE(), CURRENT_TIMESTAMP)";
                    $insertStmt = $this->db->prepare($insertSql);
                    $insertStmt->execute([
                        $bulan,
                        $tahun,
                        $wilayahId,
                        $data['nama_wilayah'],
                        $data['total_luas_panen']
                    ]);
                    $insertedCount++;
                }
            }
            
            // Lock logic removed per user request
            /*
            if ($today >= 10) {
                $lockSql = "UPDATE {$this->table} SET snapshot_locked = 1 
                            WHERE periode_bulan = ? AND periode_tahun = ?";
                $lockStmt = $this->db->prepare($lockSql);
                $lockStmt->execute([$bulan, $tahun]);
            }
            */
            
            if ($ownsTransaction) {
                $this->db->commit();
            }
            
            // Log activity
            $this->logActivity('snapshot', 'success', 
                "Snapshot estimasi berhasil untuk periode {$bulan}/{$tahun}", 
                ['inserted' => $insertedCount, 'updated' => $updatedCount, 'skipped' => $skippedCount]
            );
            
            return [
                'success' => true,
                'message' => "Snapshot berhasil: {$insertedCount} data baru, {$updatedCount} diupdate, {$skippedCount} dilewati (terkunci)",
                'data' => [
                    'inserted' => $insertedCount,
                    'updated' => $updatedCount,
                    'skipped' => $skippedCount
                ]
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logActivity('snapshot', 'failed', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update Rilis Resmi
     * Mengupdate nilai rilis BPS dan catatan analisis
     * 
     * @param int $id ID record
     * @param float $nilaiRilis Nilai luas panen dari BPS Pusat
     * @param string $catatan Catatan analisis
     * @return array Result with success status
     */
    public function updateRilisResmi($id, $nilaiRilis, $catatan = '') {
        try {
            $existing = $this->getById($id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ];
            }
            
            $sql = "UPDATE {$this->table} SET 
                        luas_rilis_bps = ?,
                        catatan_analisis = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nilaiRilis, $catatan, $id]);
            
            // Calculate deviation after update
            $this->hitungDeviasi($id);
            
            // Log activity
            $this->logActivity('update_rilis', 'success', 
                "Update rilis BPS untuk ID {$id}", 
                ['nilai_rilis' => $nilaiRilis]
            );
            
            return [
                'success' => true,
                'message' => 'Data rilis berhasil disimpan'
            ];
            
        } catch (Exception $e) {
            $this->logActivity('update_rilis', 'failed', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Hitung Deviasi
     * Private method untuk menghitung deviasi absolut dan persentase bias
     * 
     * @param int $id ID record
     * @return bool Success status
     */
    private function hitungDeviasi($id) {
        $record = $this->getById($id);
        
        if (!$record || $record['luas_rilis_bps'] === null) {
            return false;
        }
        
        $estimasi = (float) $record['luas_estimasi_daerah'];
        $rilis = (float) $record['luas_rilis_bps'];
        
        // Calculate deviation
        $deviasiAbsolut = $estimasi - $rilis;
        
        // Calculate percentage bias with division by zero protection
        $persentaseBias = null;
        if ($rilis != 0) {
            $persentaseBias = round((($estimasi - $rilis) / $rilis) * 100, 2);
        }
        
        // A zero denominator has no defined percentage accuracy classification.
        $statusAkurasi = $persentaseBias === null
            ? null
            : $this->determineStatus(abs($persentaseBias));
        
        // Update record
        $sql = "UPDATE {$this->table} SET 
                    deviasi_absolut = ?,
                    persentase_bias = ?,
                    status_akurasi = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$deviasiAbsolut, $persentaseBias, $statusAkurasi, $id]);
    }
    
    /**
     * Determine Status
     * Menentukan status akurasi berdasarkan persentase bias
     * 
     * @param float $bias Absolute percentage bias
     * @return string Status akurasi
     */
    public function determineStatus($bias) {
        $bias = abs($bias);
        
        if ($bias < self::BIAS_SANGAT_AKURAT) {
            return 'Sangat Akurat';
        } elseif ($bias <= self::BIAS_PERLU_PERHATIAN) {
            return 'Perlu Perhatian';
        } else {
            return 'Bias Tinggi';
        }
    }
    
    /**
     * Get chart data for trend comparison
     */
    public function getChartData($tahun) {
        $sql = "SELECT 
                    periode_bulan,
                    SUM(CASE WHEN luas_rilis_bps IS NOT NULL THEN luas_estimasi_daerah END) as total_estimasi,
                    SUM(luas_rilis_bps) as total_rilis,
                    COUNT(luas_rilis_bps) as jumlah_sudah_rilis,
                    COUNT(*) as jumlah_total
                FROM {$this->table}
                WHERE periode_tahun = ?
                GROUP BY periode_bulan
                ORDER BY periode_bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tahun]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fill in missing months with null
        $chartData = [];
        for ($i = 1; $i <= 12; $i++) {
            $found = false;
            foreach ($data as $row) {
                if ((int)$row['periode_bulan'] === $i) {
                    $chartData[] = [
                        'bulan' => $i,
                        'nama_bulan' => self::NAMA_BULAN[$i],
                        'estimasi' => $row['total_estimasi'] !== null ? (float) $row['total_estimasi'] : null,
                        'rilis' => $row['total_rilis'] !== null ? (float) $row['total_rilis'] : null,
                        'jumlah_sudah_rilis' => (int) $row['jumlah_sudah_rilis'],
                        'jumlah_total' => (int) $row['jumlah_total']
                    ];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $chartData[] = [
                    'bulan' => $i,
                    'nama_bulan' => self::NAMA_BULAN[$i],
                    'estimasi' => null,
                    'rilis' => null,
                    'jumlah_sudah_rilis' => 0,
                    'jumlah_total' => 0
                ];
            }
        }
        
        return $chartData;
    }
    
    /**
     * Get summary statistics
     */
    public function getStatistics($tahun = null) {
        $tahun = $tahun ?: date('Y');
        
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT wilayah_id) as jumlah_wilayah,
                    SUM(CASE WHEN status_akurasi = 'Sangat Akurat' THEN 1 ELSE 0 END) as sangat_akurat,
                    SUM(CASE WHEN status_akurasi = 'Perlu Perhatian' THEN 1 ELSE 0 END) as perlu_perhatian,
                    SUM(CASE WHEN status_akurasi = 'Bias Tinggi' THEN 1 ELSE 0 END) as bias_tinggi,
                    COUNT(CASE WHEN luas_rilis_bps IS NOT NULL THEN 1 END) as sudah_rilis,
                    ROUND(AVG(ABS(persentase_bias)), 2) as rata_bias
                FROM {$this->table}
                WHERE periode_tahun = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tahun]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get available years
     */
    public function getAvailableYears() {
        // Also include years from source table
        $sql = "SELECT DISTINCT periode_tahun as tahun FROM {$this->table}
                UNION
                SELECT DISTINCT tahun FROM {$this->sourceTable}
                ORDER BY tahun DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Check if snapshot can be performed
     */
    public function canSnapshot($bulan, $tahun) {
        // Restriction removed per user request: "Snapshot process can be performed at any time"
        return true;
    }
    
    // ========== LOGGING METHODS ==========
    
    /**
     * Log activity
     */
    public function logActivity($action, $status, $message, $details = []) {
        $sql = "INSERT INTO {$this->logTable} (action, status, message, details, user_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $action,
            $status,
            $message,
            json_encode($details),
            $_SESSION['user_id'] ?? null
        ]);
    }
    
    /**
     * Get recent logs
     */
    public function getRecentLogs($limit = 10) {
        $limit = (int) $limit;
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->logTable} ORDER BY created_at DESC LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ========== TABLE MANAGEMENT ==========
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SELECT 1 FROM {$tableName} LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create tables if not exist
     */
    public function createTablesIfNotExist() {
        // Main table
        if (!$this->tableExists($this->table)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `periode_bulan` INT(2) NOT NULL,
                `periode_tahun` YEAR NOT NULL,
                `wilayah_id` INT(11) NOT NULL,
                `nama_wilayah` VARCHAR(100) DEFAULT NULL,
                `luas_estimasi_daerah` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `luas_rilis_bps` DECIMAL(10,2) DEFAULT NULL,
                `deviasi_absolut` DECIMAL(10,2) DEFAULT NULL,
                `persentase_bias` DECIMAL(5,2) DEFAULT NULL,
                `status_akurasi` ENUM('Sangat Akurat', 'Perlu Perhatian', 'Bias Tinggi') DEFAULT NULL,
                `catatan_analisis` TEXT DEFAULT NULL,
                `snapshot_locked` TINYINT(1) DEFAULT 0,
                `snapshot_date` DATE DEFAULT NULL,
                `created_by` INT(11) DEFAULT NULL,
                `updated_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_periode_wilayah` (`periode_bulan`, `periode_tahun`, `wilayah_id`),
                INDEX `idx_tahun` (`periode_tahun`),
                INDEX `idx_status` (`status_akurasi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        }
        
        // Log table
        if (!$this->tableExists($this->logTable)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->logTable} (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `action` VARCHAR(50) NOT NULL,
                `status` ENUM('success', 'failed', 'partial') NOT NULL,
                `message` TEXT,
                `details` JSON,
                `user_id` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        }
    }
    
    /**
     * Format number for display
     */
    public static function formatNumber($number, $decimals = 2) {
        if ($number === null) {
            return '-';
        }
        return number_format($number, $decimals, ',', '.');
    }
    
    /**
     * Get nama bulan
     */
    public static function getNamaBulan($bulan) {
        return self::NAMA_BULAN[$bulan] ?? '-';
    }
}
