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
    
    public function __construct(?PDO $pdo = null) {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
        // Skema dikelola via migration append-only
        // (database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php).
        // Jangan panggil createTablesIfNotExist() per-request agar tidak
        // menambah 2 query metadata pada setiap instansiasi model.
    }

    /**
     * Daftar wilayah resmi (kode BPS => nama) dari data KSA bulanan.
     * Dipakai dropdown input manual agar wilayah_id selalu memakai kode BPS
     * resmi (mis. 3509 Jember) dan tidak memakai hash CRC32 acak.
     *
     * @return array<int, array{kode: int, nama: string}>
     */
    public function getWilayahOptions(): array {
        try {
            $stmt = $this->db->query(
                "SELECT DISTINCT kode_wilayah, kabupaten_kota AS nama_wilayah "
                . "FROM {$this->sourceTable} ORDER BY kabupaten_kota"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            $rows = [];
        }

        $options = [];
        foreach ($rows as $row) {
            $digits = preg_replace('/\D+/', '', (string) ($row['kode_wilayah'] ?? ''));
            $kode = (int) $digits;
            $nama = trim((string) ($row['nama_wilayah'] ?? ''));
            if ($kode > 0 && $nama !== '') {
                $options[$kode] = ['kode' => $kode, 'nama' => $nama];
            }
        }

        return array_values($options);
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
     * Normalisasi nama wilayah untuk pencocokan fleksibel.
     * "Kab. Jember", "Kabupaten Jember", "jember" => "jember".
     */
    private function normalizeWilayahName(string $nama): string {
        $nama = strtolower(trim($nama));
        $nama = (string) preg_replace('/^(kab\.?|kabupaten|kota)\s+/i', '', $nama);
        $nama = (string) preg_replace('/\s+/', ' ', $nama);
        return trim($nama);
    }

    /**
     * Resolusi wilayah_id/nama_wilayah ke kode BPS resmi.
     * Menolak wilayah tak dikenal (return null) agar tidak tercipta duplikat
     * via hash CRC32 seperti perilaku lama.
     *
     * @return array{wilayah_id: int, nama_wilayah: string}|null
     */
    public function resolveWilayah(mixed $wilayahId, mixed $namaWilayah): ?array {
        $map = [];
        foreach ($this->getWilayahOptions() as $opt) {
            $map[(int) $opt['kode']] = $opt['nama'];
        }
        if (empty($map)) {
            return null;
        }

        $kodeInput = (int) preg_replace('/\D+/', '', (string) ($wilayahId ?? ''));
        if ($kodeInput > 0 && isset($map[$kodeInput])) {
            return ['wilayah_id' => $kodeInput, 'nama_wilayah' => $map[$kodeInput]];
        }

        $namaNorm = $this->normalizeWilayahName((string) ($namaWilayah ?? ''));
        if ($namaNorm !== '') {
            foreach ($map as $kode => $namaResmi) {
                if ($this->normalizeWilayahName($namaResmi) === $namaNorm) {
                    return ['wilayah_id' => $kode, 'nama_wilayah' => $namaResmi];
                }
            }
        }

        return null;
    }

    /**
     * Hitung deviasi/bias/status di memori (tanpa query tambahan).
     *
     * @return array{deviasi_absolut: float|null, persentase_bias: float|null, status_akurasi: string|null}
     */
    private function calculateDeviation(float $estimasi, mixed $rilis): array {
        if ($rilis === null || $rilis === '') {
            return ['deviasi_absolut' => null, 'persentase_bias' => null, 'status_akurasi' => null];
        }

        $rilisFloat = (float) $rilis;
        $deviasi = $estimasi - $rilisFloat;
        if ($rilisFloat == 0.0) {
            return ['deviasi_absolut' => $deviasi, 'persentase_bias' => null, 'status_akurasi' => null];
        }

        $bias = round((($estimasi - $rilisFloat) / $rilisFloat) * 100, 2);
        return [
            'deviasi_absolut' => $deviasi,
            'persentase_bias' => $bias,
            'status_akurasi' => $this->determineStatus(abs($bias)),
        ];
    }

    /**
     * Sanitasi nilai teks terhadap CSV formula injection (=,+,-,@,TAB,CR).
     */
    private function sanitizeFormulaValue(mixed $value): mixed {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Insert new evaluasi record
     */
    public function insert($data) {
        try {
            $resolved = $this->resolveWilayah($data['wilayah_id'] ?? null, $data['nama_wilayah'] ?? null);
            if ($resolved === null) {
                return ['success' => false, 'message' => 'Wilayah tidak dikenal. Pilih kabupaten/kota resmi Jawa Timur (kode BPS 3501-3529, 3571-3579).'];
            }

            $estimasi = (float) ($data['luas_estimasi_daerah'] ?? 0);
            $rilisRaw = $data['luas_rilis_bps'] ?? null;
            $rilis = ($rilisRaw !== null && $rilisRaw !== '')
                ? (float) $rilisRaw
                : null;
            $dev = $this->calculateDeviation($estimasi, $rilis);
            $catatan = $this->sanitizeFormulaValue($data['catatan_analisis'] ?? null);
            $createdBy = $_SESSION['user_id'] ?? null;

            $sql = "INSERT INTO {$this->table}
                    (periode_bulan, periode_tahun, wilayah_id, nama_wilayah,
                     luas_estimasi_daerah, luas_rilis_bps, deviasi_absolut,
                     persentase_bias, status_akurasi, catatan_analisis,
                     snapshot_date, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, CURRENT_TIMESTAMP)";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['periode_bulan'],
                $data['periode_tahun'],
                $resolved['wilayah_id'],
                $resolved['nama_wilayah'],
                $estimasi,
                $rilis,
                $dev['deviasi_absolut'],
                $dev['persentase_bias'],
                $dev['status_akurasi'],
                $catatan,
                $createdBy,
            ]);

            if ($result) {
                $id = $this->db->lastInsertId();

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

            $estimasi = (float) ($data['luas_estimasi_daerah'] ?? $existing['luas_estimasi_daerah']);
            $rilis = array_key_exists('luas_rilis_bps', $data)
                ? ($data['luas_rilis_bps'] !== null && $data['luas_rilis_bps'] !== '' ? (float) $data['luas_rilis_bps'] : null)
                : ($existing['luas_rilis_bps'] !== null ? (float) $existing['luas_rilis_bps'] : null);
            $dev = $this->calculateDeviation($estimasi, $rilis);
            $catatan = $this->sanitizeFormulaValue($data['catatan_analisis'] ?? $existing['catatan_analisis']);
            $updatedBy = $_SESSION['user_id'] ?? null;

            $sql = "UPDATE {$this->table} SET
                        periode_bulan = ?,
                        periode_tahun = ?,
                        nama_wilayah = ?,
                        luas_estimasi_daerah = ?,
                        luas_rilis_bps = ?,
                        deviasi_absolut = ?,
                        persentase_bias = ?,
                        status_akurasi = ?,
                        catatan_analisis = ?,
                        updated_by = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['periode_bulan'] ?? $existing['periode_bulan'],
                $data['periode_tahun'] ?? $existing['periode_tahun'],
                $data['nama_wilayah'] ?? $existing['nama_wilayah'],
                $estimasi,
                $rilis,
                $dev['deviasi_absolut'],
                $dev['persentase_bias'],
                $dev['status_akurasi'],
                $catatan,
                $updatedBy,
                $id
            ]);

            if ($result) {
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

        $bulan = (int) $bulan;
        $tahun = (int) $tahun;
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > ((int) date('Y') + 1)) {
            return ['success' => false, 'message' => 'Periode snapshot tidak valid'];
        }

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

            // Ambil status existing dalam 1 query (hindari pola N+1).
            $existingStmt = $this->db->prepare(
                "SELECT wilayah_id, snapshot_locked FROM {$this->table} "
                . "WHERE periode_bulan = ? AND periode_tahun = ?"
            );
            $existingStmt->execute([$bulan, $tahun]);
            $existingMap = [];
            foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existingMap[(int) $row['wilayah_id']] = (int) ($row['snapshot_locked'] ?? 0);
            }

            $rows = [];
            $skippedCount = 0;
            foreach ($sourceData as $data) {
                $digits = preg_replace('/\D+/', '', (string) $data['kode_wilayah']);
                $wilayahId = (int) $digits;
                if ($wilayahId <= 0) {
                    continue;
                }

                if (isset($existingMap[$wilayahId]) && $existingMap[$wilayahId] === 1) {
                    $skippedCount++;
                    continue;
                }

                $rows[] = [
                    $bulan,
                    $tahun,
                    $wilayahId,
                    $data['nama_wilayah'],
                    $data['total_luas_panen'],
                ];
            }

            $insertedCount = 0;
            $updatedCount = 0;
            foreach ($rows as $row) {
                if (isset($existingMap[(int) $row[2]])) {
                    $updatedCount++;
                } else {
                    $insertedCount++;
                }
            }

            if (!empty($rows)) {
                // Single bulk upsert: 1 round-trip untuk seluruh wilayah.
                $actorId = $_SESSION['user_id'] ?? null;
                $placeholders = [];
                $params = [];
                foreach ($rows as $row) {
                    $placeholders[] = "(?, ?, ?, ?, ?, CURDATE(), ?, ?, CURRENT_TIMESTAMP)";
                    $params[] = $row[0];
                    $params[] = $row[1];
                    $params[] = $row[2];
                    $params[] = $row[3];
                    $params[] = $row[4];
                    $params[] = $actorId;
                    $params[] = $actorId;
                }

                $bulkSql = "INSERT INTO {$this->table}
                                (periode_bulan, periode_tahun, wilayah_id, nama_wilayah,
                                 luas_estimasi_daerah, snapshot_date, created_by, updated_by, created_at)
                                VALUES " . implode(', ', $placeholders)
                            . " ON DUPLICATE KEY UPDATE
                                 luas_estimasi_daerah = VALUES(luas_estimasi_daerah),
                                 nama_wilayah = VALUES(nama_wilayah),
                                 snapshot_date = CURDATE(),
                                 updated_by = VALUES(updated_by),
                                 created_by = COALESCE(created_by, VALUES(created_by)),
                                 updated_at = CURRENT_TIMESTAMP";
                $bulkStmt = $this->db->prepare($bulkSql);
                $bulkStmt->execute($params);
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

            $estimasi = (float) $existing['luas_estimasi_daerah'];
            $dev = $this->calculateDeviation($estimasi, (float) $nilaiRilis);
            $catatanBersih = $this->sanitizeFormulaValue($catatan);
            $updatedBy = $_SESSION['user_id'] ?? null;

            $sql = "UPDATE {$this->table} SET
                        luas_rilis_bps = ?,
                        catatan_analisis = ?,
                        deviasi_absolut = ?,
                        persentase_bias = ?,
                        status_akurasi = ?,
                        updated_by = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $nilaiRilis,
                $catatanBersih,
                $dev['deviasi_absolut'],
                $dev['persentase_bias'],
                $dev['status_akurasi'],
                $updatedBy,
                $id,
            ]);
            
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
        // Estimasi selalu diagregat penuh 12 bulan; garis estimasi tidak boleh
        // hilang hanya karena rilis BPS periode berjalan belum diumumkan.
        $sql = "SELECT
                    periode_bulan,
                    SUM(luas_estimasi_daerah) as total_estimasi,
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
     * @deprecated Dipakai hanya oleh migration 2026_09_12 dan skrip
     * pemeliharaan; JANGAN dipanggil dari constructor per-request.
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
