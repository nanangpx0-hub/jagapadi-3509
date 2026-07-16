<?php
/**
 * Data Story Service
 * 
 * Service untuk menganalisis kausalitas sederhana antara produksi padi
 * dengan faktor eksogen (curah hujan & serangan hama) menggunakan 
 * Lagging Indicators (data bulan sebelumnya).
 * 
 * @version 1.0.0
 * @author JAGAPADI System - Data Storytelling Module
 */

class DataStoryService {
    
    private $db;
    private $debug = false;
    private $logFile;
    
    // Threshold untuk menentukan faktor penyebab
    private const CURAH_HUJAN_EKSTREM = 300; // mm
    private const CURAH_HUJAN_KERING = 50;   // mm
    private const HAMA_BERAT_THRESHOLD = 10; // jumlah laporan
    private const HAMA_RINGAN_THRESHOLD = 3; // jumlah laporan
    
    // Bobot untuk kalkulasi skor risiko
    private const WEIGHT_CUACA = 0.6;
    private const WEIGHT_HAMA = 0.4;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logFile = ROOT_PATH . '/logs/data_story_service.log';
    }
    
    /**
     * Analisis penyebab perubahan produksi berdasarkan Lagging Indicators
     * 
     * @param int $bulan Bulan analisis (1-12)
     * @param int $tahun Tahun analisis
     * @param int $wilayahId ID wilayah (kecamatan)
     * @return array Hasil analisis lengkap
     */
    public function analyzeCauses(int $bulan, int $tahun, int $wilayahId): array {
        $startTime = microtime(true);
        $this->log("Starting analysis for {$tahun}-{$bulan}, wilayah #{$wilayahId}");
        
        try {
            // 1. Ambil Data Produksi Bulan Terpilih
            $produksiData = $this->getProductionData($bulan, $tahun, $wilayahId);
            
            // 2. Ambil Data Penyebab dengan Time Lag -1 Bulan
            $lagData = $this->getLaggingIndicators($bulan, $tahun, $wilayahId);
            
            // 3. Rule Engine - Tentukan Faktor Penyebab Utama
            $faktorPenyebab = $this->determinePrimaryFactor($lagData);
            
            // 4. Kalkulasi Skor Risiko
            $skorRisiko = $this->calculateRiskScores($lagData);
            
            // 5. Generate Narasi Otomatis
            $narasi = $this->generateNarrative($bulan, $tahun, $wilayahId, $produksiData, $lagData, $faktorPenyebab, $skorRisiko);
            
            // 6. Compile hasil analisis
            $result = [
                'success' => true,
                'periode' => [
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'nama_bulan' => $this->getMonthName($bulan),
                    'wilayah_id' => $wilayahId
                ],
                'produksi_data' => $produksiData,
                'lagging_indicators' => $lagData,
                'faktor_penyebab_utama' => $faktorPenyebab,
                'skor_risiko' => $skorRisiko,
                'narasi_otomatis' => $narasi,
                'execution_time' => round(microtime(true) - $startTime, 4)
            ];
            
            $this->log("Analysis completed successfully in {$result['execution_time']}s");
            return $result;
            
        } catch (Exception $e) {
            $this->log("Analysis failed: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => round(microtime(true) - $startTime, 4)
            ];
        }
    }
    
    /**
     * Ambil data produksi untuk bulan dan wilayah tertentu
     */
    private function getProductionData(int $bulan, int $tahun, int $wilayahId): array {
        $sql = "
            SELECT 
                SUM(luas_panen) as total_luas_panen,
                SUM(produksi) as total_produksi,
                AVG(produktivitas) as avg_produktivitas,
                COUNT(*) as jumlah_laporan,
                MIN(tanggal_panen) as tanggal_panen_awal,
                MAX(tanggal_panen) as tanggal_panen_akhir
            FROM produksi_gabah pg
            LEFT JOIN master_desa md ON pg.desa_id = md.id
            WHERE MONTH(pg.tanggal_panen) = ? 
              AND YEAR(pg.tanggal_panen) = ?
              AND md.kecamatan_id = ?
              AND pg.status_verifikasi = 'verified'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Jika tidak ada data, return default values
        if (!$result || $result['total_luas_panen'] === null) {
            return [
                'total_luas_panen' => 0,
                'total_produksi' => 0,
                'avg_produktivitas' => 0,
                'jumlah_laporan' => 0,
                'tanggal_panen_awal' => null,
                'tanggal_panen_akhir' => null,
                'has_data' => false
            ];
        }
        
        $result['has_data'] = true;
        $result['total_luas_panen'] = (float) $result['total_luas_panen'];
        $result['total_produksi'] = (float) $result['total_produksi'];
        $result['avg_produktivitas'] = (float) $result['avg_produktivitas'];
        
        return $result;
    }
    
    /**
     * Ambil Lagging Indicators (data bulan sebelumnya)
     * Karena panen dipengaruhi kejadian 1-2 bulan sebelumnya
     */
    private function getLaggingIndicators(int $bulan, int $tahun, int $wilayahId): array {
        // Hitung bulan sebelumnya (handle year rollover)
        $lagBulan = $bulan - 1;
        $lagTahun = $tahun;
        
        if ($lagBulan < 1) {
            $lagBulan = 12;
            $lagTahun = $tahun - 1;
        }
        
        $this->log("Getting lagging indicators for {$lagTahun}-{$lagBulan} (lag from {$tahun}-{$bulan})");
        
        // 1. Data Curah Hujan Bulan Sebelumnya
        $curahHujanData = $this->getCurahHujanLag($lagBulan, $lagTahun, $wilayahId);
        
        // 2. Data Serangan Hama Bulan Sebelumnya  
        $hamaData = $this->getHamaLag($lagBulan, $lagTahun, $wilayahId);
        
        return [
            'lag_periode' => [
                'bulan' => $lagBulan,
                'tahun' => $lagTahun,
                'nama_bulan' => $this->getMonthName($lagBulan)
            ],
            'curah_hujan' => $curahHujanData,
            'hama' => $hamaData
        ];
    }
    
    /**
     * Ambil data curah hujan bulan sebelumnya
     */
    private function getCurahHujanLag(int $lagBulan, int $lagTahun, int $wilayahId): array {
        // Query rata-rata curah hujan dari bulan sebelumnya
        // Asumsi: tabel curah_hujan memiliki relasi dengan wilayah
        $sql = "
            SELECT 
                AVG(curah_hujan) as avg_curah_hujan,
                MIN(curah_hujan) as min_curah_hujan,
                MAX(curah_hujan) as max_curah_hujan,
                COUNT(*) as jumlah_data,
                SUM(CASE WHEN curah_hujan > ? THEN 1 ELSE 0 END) as hari_hujan_ekstrem
            FROM curah_hujan ch
            WHERE MONTH(ch.tanggal) = ? 
              AND YEAR(ch.tanggal) = ?
              AND (ch.lokasi LIKE ? OR ch.kode_wilayah LIKE ?)
        ";
        
        // Get nama kecamatan untuk pattern matching
        $kecamatanInfo = $this->getKecamatanInfo($wilayahId);
        $lokasiPattern = '%' . $kecamatanInfo['nama_kecamatan'] . '%';
        $kodePattern = $kecamatanInfo['kode_wilayah'] . '%';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            self::CURAH_HUJAN_EKSTREM, 
            $lagBulan, 
            $lagTahun, 
            $lokasiPattern,
            $kodePattern
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['avg_curah_hujan'] === null) {
            return [
                'avg_curah_hujan' => 0,
                'min_curah_hujan' => 0,
                'max_curah_hujan' => 0,
                'jumlah_data' => 0,
                'hari_hujan_ekstrem' => 0,
                'kategori' => 'Tidak Ada Data',
                'has_data' => false
            ];
        }
        
        $avgCurahHujan = (float) $result['avg_curah_hujan'];
        
        // Kategorisasi curah hujan
        $kategori = $this->categorizeCurahHujan($avgCurahHujan);
        
        return [
            'avg_curah_hujan' => $avgCurahHujan,
            'min_curah_hujan' => (float) $result['min_curah_hujan'],
            'max_curah_hujan' => (float) $result['max_curah_hujan'],
            'jumlah_data' => (int) $result['jumlah_data'],
            'hari_hujan_ekstrem' => (int) $result['hari_hujan_ekstrem'],
            'kategori' => $kategori,
            'has_data' => true
        ];
    }
    
    /**
     * Ambil data serangan hama bulan sebelumnya
     */
    private function getHamaLag(int $lagBulan, int $lagTahun, int $wilayahId): array {
        $sql = "
            SELECT 
                COUNT(*) as total_laporan_hama,
                SUM(CASE WHEN lh.intensitas_serangan IN ('Berat', 'Puso') THEN 1 ELSE 0 END) as laporan_hama_berat,
                SUM(CASE WHEN lh.intensitas_serangan = 'Sedang' THEN 1 ELSE 0 END) as laporan_hama_sedang,
                SUM(CASE WHEN lh.intensitas_serangan = 'Ringan' THEN 1 ELSE 0 END) as laporan_hama_ringan,
                SUM(lh.luas_serangan) as total_luas_serangan,
                GROUP_CONCAT(DISTINCT lh.jenis_hama ORDER BY lh.jenis_hama SEPARATOR ', ') as jenis_hama_list
            FROM laporan_hama lh
            LEFT JOIN master_desa md ON lh.desa_id = md.id
            WHERE MONTH(lh.tanggal_laporan) = ? 
              AND YEAR(lh.tanggal_laporan) = ?
              AND md.kecamatan_id = ?
              AND lh.status IN ('Submitted', 'Diverifikasi')
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lagBulan, $lagTahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['total_laporan_hama'] === null) {
            return [
                'total_laporan_hama' => 0,
                'laporan_hama_berat' => 0,
                'laporan_hama_sedang' => 0,
                'laporan_hama_ringan' => 0,
                'total_luas_serangan' => 0,
                'jenis_hama_list' => '',
                'kategori' => 'Tidak Ada Serangan',
                'has_data' => false
            ];
        }
        
        $totalLaporan = (int) $result['total_laporan_hama'];
        $laporanBerat = (int) $result['laporan_hama_berat'];
        
        // Kategorisasi tingkat serangan
        $kategori = $this->categorizeHamaAttack($totalLaporan, $laporanBerat);
        
        return [
            'total_laporan_hama' => $totalLaporan,
            'laporan_hama_berat' => $laporanBerat,
            'laporan_hama_sedang' => (int) $result['laporan_hama_sedang'],
            'laporan_hama_ringan' => (int) $result['laporan_hama_ringan'],
            'total_luas_serangan' => (float) $result['total_luas_serangan'],
            'jenis_hama_list' => $result['jenis_hama_list'] ?: '',
            'kategori' => $kategori,
            'has_data' => true
        ];
    }
    
    /**
     * Rule Engine - Tentukan faktor penyebab utama berdasarkan threshold
     */
    private function determinePrimaryFactor(array $lagData): string {
        $curahHujan = $lagData['curah_hujan']['avg_curah_hujan'];
        $laporanHamaBerat = $lagData['hama']['laporan_hama_berat'];
        $totalLaporanHama = $lagData['hama']['total_laporan_hama'];
        
        $this->log("Determining primary factor: curah_hujan={$curahHujan}mm, hama_berat={$laporanHamaBerat}, total_hama={$totalLaporanHama}");
        
        // Rule 1: Cuaca Ekstrem (Prioritas Tertinggi)
        if ($curahHujan > self::CURAH_HUJAN_EKSTREM) {
            $this->log("Primary factor: Cuaca Ekstrem (curah hujan > " . self::CURAH_HUJAN_EKSTREM . "mm)");
            return 'Cuaca Ekstrem';
        }
        
        // Rule 2: Kekeringan Ekstrem
        if ($curahHujan < self::CURAH_HUJAN_KERING) {
            $this->log("Primary factor: Cuaca Ekstrem (kekeringan < " . self::CURAH_HUJAN_KERING . "mm)");
            return 'Cuaca Ekstrem';
        }
        
        // Rule 3: Serangan OPT Berat
        if ($laporanHamaBerat > self::HAMA_BERAT_THRESHOLD) {
            $this->log("Primary factor: Serangan OPT (laporan berat > " . self::HAMA_BERAT_THRESHOLD . ")");
            return 'Serangan OPT';
        }
        
        // Rule 4: Serangan OPT Sedang (total laporan tinggi)
        if ($totalLaporanHama > (self::HAMA_BERAT_THRESHOLD * 2)) {
            $this->log("Primary factor: Serangan OPT (total laporan > " . (self::HAMA_BERAT_THRESHOLD * 2) . ")");
            return 'Serangan OPT';
        }
        
        // Rule 5: Kombinasi faktor (cuaca tidak normal + hama ringan)
        if ($curahHujan > 200 && $totalLaporanHama > self::HAMA_RINGAN_THRESHOLD) {
            $this->log("Primary factor: Cuaca Ekstrem (kombinasi cuaca + hama)");
            return 'Cuaca Ekstrem';
        }
        
        // Default: Kondisi Normal
        $this->log("Primary factor: Normal (tidak ada faktor ekstrem terdeteksi)");
        return 'Normal';
    }
    
    /**
     * Kalkulasi skor risiko berdasarkan data lagging indicators
     */
    private function calculateRiskScores(array $lagData): array {
        $curahHujan = $lagData['curah_hujan']['avg_curah_hujan'];
        $laporanHamaBerat = $lagData['hama']['laporan_hama_berat'];
        $totalLaporanHama = $lagData['hama']['total_laporan_hama'];
        
        // Skor Risiko Cuaca (0-100)
        $skorCuaca = 0;
        if ($curahHujan > self::CURAH_HUJAN_EKSTREM) {
            // Hujan ekstrem: skor tinggi
            $skorCuaca = min(100, 60 + (($curahHujan - self::CURAH_HUJAN_EKSTREM) / 10));
        } elseif ($curahHujan < self::CURAH_HUJAN_KERING) {
            // Kekeringan: skor tinggi
            $skorCuaca = min(100, 70 + ((self::CURAH_HUJAN_KERING - $curahHujan) / 2));
        } elseif ($curahHujan > 200) {
            // Hujan tinggi tapi belum ekstrem
            $skorCuaca = 30 + (($curahHujan - 200) / 5);
        } else {
            // Cuaca normal
            $skorCuaca = max(0, 20 - (abs($curahHujan - 150) / 10));
        }
        
        // Skor Risiko Hama (0-100)
        $skorHama = 0;
        if ($laporanHamaBerat > 0) {
            $skorHama = min(100, 40 + ($laporanHamaBerat * 8));
        } elseif ($totalLaporanHama > 0) {
            $skorHama = min(60, $totalLaporanHama * 3);
        }
        
        // Skor Risiko Total (weighted average)
        $skorTotal = ($skorCuaca * self::WEIGHT_CUACA) + ($skorHama * self::WEIGHT_HAMA);
        
        return [
            'skor_risiko_cuaca' => (int) round($skorCuaca),
            'skor_risiko_hama' => (int) round($skorHama),
            'skor_risiko_total' => (int) round($skorTotal)
        ];
    }
    
    /**
     * Generate narasi otomatis berdasarkan analisis
     */
    private function generateNarrative(int $bulan, int $tahun, int $wilayahId, array $produksiData, array $lagData, string $faktorPenyebab, array $skorRisiko): string {
        $namaBulan = $this->getMonthName($bulan);
        $namaBulanLag = $lagData['lag_periode']['nama_bulan'];
        $tahunLag = $lagData['lag_periode']['tahun'];
        $kecamatanInfo = $this->getKecamatanInfo($wilayahId);
        $namaKecamatan = $kecamatanInfo['nama_kecamatan'];
        
        $luasPanen = number_format($produksiData['total_luas_panen'], 2, ',', '.');
        $curahHujan = number_format($lagData['curah_hujan']['avg_curah_hujan'], 2, ',', '.');
        $laporanHamaBerat = $lagData['hama']['laporan_hama_berat'];
        $totalLaporanHama = $lagData['hama']['total_laporan_hama'];
        
        $narasi = "";
        
        // Template narasi berdasarkan faktor penyebab
        switch ($faktorPenyebab) {
            case 'Cuaca Ekstrem':
                if ($lagData['curah_hujan']['avg_curah_hujan'] > self::CURAH_HUJAN_EKSTREM) {
                    $narasi = "Luas panen pada {$namaBulan} {$tahun} di Kecamatan {$namaKecamatan} tercatat {$luasPanen} Ha. ";
                    $narasi .= "Kondisi ini dipengaruhi oleh curah hujan ekstrem sebesar {$curahHujan} mm yang terjadi pada {$namaBulanLag} {$tahunLag}, ";
                    $narasi .= "yang berada di atas ambang batas normal (" . self::CURAH_HUJAN_EKSTREM . " mm). ";
                    
                    if ($totalLaporanHama > 0) {
                        $narasi .= "Kondisi ini diperparah dengan adanya {$totalLaporanHama} laporan serangan hama";
                        if ($laporanHamaBerat > 0) {
                            $narasi .= ", termasuk {$laporanHamaBerat} laporan dengan intensitas berat";
                        }
                        $narasi .= ". ";
                    }
                } else {
                    // Kekeringan
                    $narasi = "Luas panen pada {$namaBulan} {$tahun} di Kecamatan {$namaKecamatan} tercatat {$luasPanen} Ha. ";
                    $narasi .= "Kondisi ini dipengaruhi oleh kekeringan dengan curah hujan hanya {$curahHujan} mm pada {$namaBulanLag} {$tahunLag}, ";
                    $narasi .= "yang berada di bawah ambang batas minimum (" . self::CURAH_HUJAN_KERING . " mm). ";
                }
                break;
                
            case 'Serangan OPT':
                $narasi = "Luas panen pada {$namaBulan} {$tahun} di Kecamatan {$namaKecamatan} tercatat {$luasPanen} Ha. ";
                $narasi .= "Kondisi ini dipengaruhi oleh serangan Organisme Pengganggu Tumbuhan (OPT) yang tinggi pada {$namaBulanLag} {$tahunLag} ";
                $narasi .= "dengan {$totalLaporanHama} total laporan serangan";
                
                if ($laporanHamaBerat > 0) {
                    $narasi .= ", termasuk {$laporanHamaBerat} laporan dengan intensitas berat";
                }
                $narasi .= ". ";
                
                if (!empty($lagData['hama']['jenis_hama_list'])) {
                    $narasi .= "Jenis hama yang menyerang meliputi: " . $lagData['hama']['jenis_hama_list'] . ". ";
                }
                
                if ($lagData['curah_hujan']['avg_curah_hujan'] > 0) {
                    $narasi .= "Curah hujan pada periode yang sama tercatat {$curahHujan} mm. ";
                }
                break;
                
            case 'Normal':
                $narasi = "Luas panen pada {$namaBulan} {$tahun} di Kecamatan {$namaKecamatan} tercatat {$luasPanen} Ha. ";
                $narasi .= "Kondisi ini menunjukkan pola normal dengan curah hujan {$curahHujan} mm pada {$namaBulanLag} {$tahunLag} ";
                $narasi .= "dan tingkat serangan hama yang terkendali ({$totalLaporanHama} laporan). ";
                break;
                
            default:
                $narasi = "Luas panen pada {$namaBulan} {$tahun} di Kecamatan {$namaKecamatan} tercatat {$luasPanen} Ha. ";
                $narasi .= "Analisis menunjukkan faktor penyebab: {$faktorPenyebab}. ";
        }
        
        // Tambahkan informasi skor risiko
        $narasi .= "Skor risiko total untuk periode ini adalah {$skorRisiko['skor_risiko_total']}/100 ";
        $narasi .= "(risiko cuaca: {$skorRisiko['skor_risiko_cuaca']}/100, risiko hama: {$skorRisiko['skor_risiko_hama']}/100).";
        
        return $narasi;
    }
    
    /**
     * Simpan hasil analisis ke database
     */
    public function saveAnalysis(array $analysisData, int $userId): array {
        try {
            $this->db->beginTransaction();
            
            // Check if analysis already exists
            $existingId = $this->checkExistingAnalysis(
                $analysisData['periode']['bulan'],
                $analysisData['periode']['tahun'],
                $analysisData['periode']['wilayah_id']
            );
            
            if ($existingId) {
                // Update existing analysis
                $result = $this->updateAnalysis($existingId, $analysisData, $userId);
            } else {
                // Create new analysis
                $result = $this->createAnalysis($analysisData, $userId);
            }
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Failed to save analysis: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Create new analysis record
     */
    private function createAnalysis(array $data, int $userId): array {
        $sql = "
            INSERT INTO analisis_produksi_bulanan (
                periode_bulan, periode_tahun, wilayah_id, total_luas_panen,
                faktor_penyebab_utama, skor_risiko_cuaca, skor_risiko_hama, skor_risiko_total,
                avg_curah_hujan_lag1, total_laporan_hama_lag1, laporan_hama_berat_lag1,
                narasi_otomatis, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['periode']['bulan'],
            $data['periode']['tahun'],
            $data['periode']['wilayah_id'],
            $data['produksi_data']['total_luas_panen'],
            $data['faktor_penyebab_utama'],
            $data['skor_risiko']['skor_risiko_cuaca'],
            $data['skor_risiko']['skor_risiko_hama'],
            $data['skor_risiko']['skor_risiko_total'],
            $data['lagging_indicators']['curah_hujan']['avg_curah_hujan'],
            $data['lagging_indicators']['hama']['total_laporan_hama'],
            $data['lagging_indicators']['hama']['laporan_hama_berat'],
            $data['narasi_otomatis'],
            $userId
        ]);
        
        $analysisId = $this->db->lastInsertId();
        
        // Log the creation
        $this->logAnalysisActivity($analysisId, 'create', null, $data, 'Analisis baru dibuat', $userId);
        
        return [
            'success' => true,
            'id' => $analysisId,
            'action' => 'created',
            'message' => 'Analisis berhasil disimpan'
        ];
    }
    
    /**
     * Helper methods
     */
    private function getMonthName(int $month): string {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $months[$month] ?? 'Unknown';
    }
    
    private function getKecamatanInfo(int $kecamatanId): array {
        $sql = "SELECT nama_kecamatan, kode_wilayah FROM master_kecamatan WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$kecamatanId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: ['nama_kecamatan' => 'Unknown', 'kode_wilayah' => ''];
    }
    
    private function categorizeCurahHujan(float $curahHujan): string {
        if ($curahHujan > self::CURAH_HUJAN_EKSTREM) return 'Sangat Tinggi';
        if ($curahHujan > 200) return 'Tinggi';
        if ($curahHujan > 100) return 'Sedang';
        if ($curahHujan > self::CURAH_HUJAN_KERING) return 'Rendah';
        return 'Sangat Rendah';
    }
    
    private function categorizeHamaAttack(int $totalLaporan, int $laporanBerat): string {
        if ($laporanBerat > self::HAMA_BERAT_THRESHOLD) return 'Serangan Berat';
        if ($laporanBerat > 5) return 'Serangan Sedang';
        if ($totalLaporan > 10) return 'Serangan Ringan';
        if ($totalLaporan > 0) return 'Serangan Sporadis';
        return 'Tidak Ada Serangan';
    }
    
    private function checkExistingAnalysis(int $bulan, int $tahun, int $wilayahId): ?int {
        $sql = "SELECT id FROM analisis_produksi_bulanan WHERE periode_bulan = ? AND periode_tahun = ? AND wilayah_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int) $result['id'] : null;
    }
    
    private function logAnalysisActivity(int $analysisId, string $action, ?array $oldValues, array $newValues, string $notes, int $userId): void {
        $sql = "INSERT INTO analisis_produksi_logs (analisis_id, action, old_values, new_values, notes, user_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $analysisId,
            $action,
            $oldValues ? json_encode($oldValues) : null,
            json_encode($newValues),
            $notes,
            $userId
        ]);
    }
    
    private function log(string $message, string $level = 'INFO'): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] [DataStoryService] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
