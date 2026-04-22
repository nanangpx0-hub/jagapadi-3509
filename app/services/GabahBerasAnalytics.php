<?php
/**
 * GabahBerasAnalytics Service
 * Service untuk analisis korelasi produksi gabah/beras dengan irigasi, cuaca, dan hama
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class GabahBerasAnalytics {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get dashboard summary for KPI cards
     */
    public function getDashboardSummary($tahun = null, $musim = null) {
        $tahun = $tahun ?: date('Y');
        
        // Current period stats
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    SUM(luas_panen) as total_luas_panen,
                    SUM(produksi_total) as total_produksi,
                    ROUND(AVG(produktivitas), 2) as avg_produktivitas,
                    ROUND(AVG(kadar_air), 2) as avg_kadar_air,
                    COUNT(DISTINCT kecamatan_id) as jumlah_wilayah
                FROM produksi_gabah
                WHERE tahun = ? AND status = 'verified'";
        $params = [$tahun];
        
        if ($musim) {
            $sql .= " AND musim_tanam = ?";
            $params[] = $musim;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Previous year for comparison - build new params
        $prevYear = $tahun - 1;
        $prevParams = [$prevYear];
        if ($musim) {
            $prevParams[] = $musim;
        }
        $stmt->execute($prevParams);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate changes
        $produksiChange = $previous['total_produksi'] > 0 
            ? round(($current['total_produksi'] - $previous['total_produksi']) / $previous['total_produksi'] * 100, 2)
            : null;
            
        $produktivitasChange = $previous['avg_produktivitas'] > 0
            ? round(($current['avg_produktivitas'] - $previous['avg_produktivitas']) / $previous['avg_produktivitas'] * 100, 2)
            : null;
        
        // Grade distribution
        $gradeSQL = "SELECT 
                        grade_kualitas,
                        COUNT(*) as count,
                        ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
                     FROM produksi_gabah
                     WHERE tahun = ? AND status = 'verified'
                     GROUP BY grade_kualitas";
        $stmt = $this->db->prepare($gradeSQL);
        $stmt->execute([$tahun]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'periode' => ['tahun' => $tahun, 'musim' => $musim],
            'kpi' => [
                'total_produksi' => [
                    'value' => round($current['total_produksi'] ?? 0, 2),
                    'unit' => 'ton GKG',
                    'change' => $produksiChange,
                    'trend' => $produksiChange > 0 ? 'up' : ($produksiChange < 0 ? 'down' : 'stable')
                ],
                'total_luas_panen' => [
                    'value' => round($current['total_luas_panen'] ?? 0, 2),
                    'unit' => 'hektar'
                ],
                'avg_produktivitas' => [
                    'value' => round($current['avg_produktivitas'] ?? 0, 2),
                    'unit' => 'ton/ha',
                    'change' => $produktivitasChange,
                    'trend' => $produktivitasChange > 0 ? 'up' : ($produktivitasChange < 0 ? 'down' : 'stable')
                ],
                'avg_kadar_air' => [
                    'value' => round($current['avg_kadar_air'] ?? 0, 2),
                    'unit' => '%'
                ],
                'total_wilayah' => [
                    'value' => $current['jumlah_wilayah'] ?? 0,
                    'unit' => 'kecamatan'
                ]
            ],
            'grade_distribution' => $grades,
            'total_records' => $current['total_records'] ?? 0
        ];
    }
    
    /**
     * Correlate production with irrigation data
     */
    public function correlateWithIrrigation($tahun = null, $lokasi = null) {
        $tahun = $tahun ?: date('Y');
        
        try {
            $sql = "SELECT 
                        pg.kecamatan_id,
                        MIN(pg.nama_lokasi) as nama_lokasi,
                        AVG(pg.produktivitas) as avg_produktivitas,
                        AVG(i.debit_air) as avg_debit,
                        COUNT(DISTINCT pg.id) as produksi_count,
                        COUNT(DISTINCT i.id) as irigasi_count
                    FROM produksi_gabah pg
                    LEFT JOIN irigasi i ON pg.irigasi_id = i.id
                    WHERE pg.tahun = ? AND pg.status = 'verified'";
            $params = [$tahun];
            
            if ($lokasi) {
                $sql .= " AND pg.kecamatan_id = ?";
                $params[] = $lokasi;
            }
            
            $sql .= " GROUP BY pg.kecamatan_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback: get production data without irrigation join
            $sql = "SELECT 
                        kecamatan_id,
                        MIN(nama_lokasi) as nama_lokasi,
                        AVG(produktivitas) as avg_produktivitas,
                        NULL as avg_debit,
                        COUNT(*) as produksi_count,
                        0 as irigasi_count
                    FROM produksi_gabah
                    WHERE tahun = ? AND status = 'verified'
                    GROUP BY kecamatan_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tahun]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Calculate correlation
        $produktivitas = array_column($data, 'avg_produktivitas');
        $debit = array_column($data, 'avg_debit');
        $correlation = $this->calculateCorrelation($produktivitas, $debit);
        
        return [
            'data' => $data,
            'correlation' => $correlation,
            'interpretation' => $this->interpretCorrelation($correlation),
            'recommendation' => $this->getIrrigationRecommendation($correlation, $data)
        ];
    }
    
    /**
     * Correlate production with weather data
     */
    public function correlateWithWeather($tahun = null, $musim = null) {
        $tahun = $tahun ?: date('Y');
        
        // Get production data by month
        $sql = "SELECT 
                    MONTH(pg.created_at) as bulan,
                    AVG(pg.produktivitas) as avg_produktivitas,
                    SUM(pg.produksi_total) as total_produksi
                FROM produksi_gabah pg
                WHERE pg.tahun = ? AND pg.status = 'verified'
                GROUP BY MONTH(pg.created_at)
                ORDER BY bulan";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tahun]);
        $prodData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get weather data (from kecepatan_angin or simulated)
        $weatherSQL = "SELECT 
                        MONTH(tanggal) as bulan,
                        AVG(kecepatan_angin) as avg_wind_speed,
                        COUNT(*) as data_count
                       FROM kecepatan_angin
                       WHERE YEAR(tanggal) = ?
                       GROUP BY MONTH(tanggal)";
        $stmt = $this->db->prepare($weatherSQL);
        $stmt->execute([$tahun]);
        $weatherData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge data
        $mergedData = [];
        foreach ($prodData as $prod) {
            $weather = array_filter($weatherData, fn($w) => $w['bulan'] == $prod['bulan']);
            $weather = !empty($weather) ? reset($weather) : null;
            
            $mergedData[] = [
                'bulan' => $prod['bulan'],
                'produktivitas' => round($prod['avg_produktivitas'], 2),
                'produksi' => round($prod['total_produksi'], 2),
                'avg_wind_speed' => $weather ? round($weather['avg_wind_speed'], 2) : null
            ];
        }
        
        return [
            'data' => $mergedData,
            'weather_impact' => $this->analyzeWeatherImpact($mergedData),
            'recommendation' => $this->getWeatherRecommendation($mergedData)
        ];
    }
    
    /**
     * Correlate production with pest data 
     */
    public function correlateWithPest($tahun = null) {
        $tahun = $tahun ?: date('Y');
        
        // Try to get hama data if table exists
        try {
            $sql = "SELECT 
                        pg.kecamatan_id,
                        MIN(pg.nama_lokasi) as nama_lokasi,
                        AVG(pg.produktivitas) as avg_produktivitas,
                        COUNT(DISTINCT h.id) as pest_incidents
                    FROM produksi_gabah pg
                    LEFT JOIN hama h ON pg.kecamatan_id = h.kecamatan_id 
                        AND YEAR(h.tanggal_laporan) = pg.tahun
                    WHERE pg.tahun = ? AND pg.status = 'verified'
                    GROUP BY pg.kecamatan_id
                    ORDER BY pest_incidents DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tahun]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Hama table doesn't exist, return simulated data
            $data = $this->getSimulatedPestCorrelation($tahun);
        }
        
        $produktivitas = array_column($data, 'avg_produktivitas');
        $pestIncidents = array_column($data, 'pest_incidents');
        $correlation = $this->calculateCorrelation($produktivitas, $pestIncidents);
        
        return [
            'data' => $data,
            'correlation' => $correlation,
            'interpretation' => $this->interpretPestCorrelation($correlation),
            'risk_areas' => $this->identifyHighRiskAreas($data)
        ];
    }
    
    /**
     * Generate risk score for a location
     */
    public function generateRiskScore($kecamatanId, $tahun = null) {
        $tahun = $tahun ?: date('Y');
        
        $score = 50; // Base score (medium)
        $factors = [];
        
        // Factor 1: Productivity trend
        $trendSQL = "SELECT 
                        tahun,
                        AVG(produktivitas) as avg_prod
                     FROM produksi_gabah
                     WHERE kecamatan_id = ? AND tahun >= ? - 2
                     GROUP BY tahun ORDER BY tahun";
        $stmt = $this->db->prepare($trendSQL);
        $stmt->execute([$kecamatanId, $tahun]);
        $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($trends) >= 2) {
            $first = $trends[0]['avg_prod'];
            $last = end($trends)['avg_prod'];
            $trendChange = (($last - $first) / $first) * 100;
            
            if ($trendChange < -10) {
                $score += 20;
                $factors[] = ['name' => 'Trend Menurun', 'impact' => '+20', 'value' => round($trendChange, 1) . '%'];
            } elseif ($trendChange > 10) {
                $score -= 15;
                $factors[] = ['name' => 'Trend Meningkat', 'impact' => '-15', 'value' => round($trendChange, 1) . '%'];
            }
        }
        
        // Factor 2: Weather conditions (wind speed)
        $weatherSQL = "SELECT AVG(kecepatan_angin) as avg_wind
                       FROM kecepatan_angin
                       WHERE YEAR(tanggal) = ?
                       LIMIT 100";
        $stmt = $this->db->prepare($weatherSQL);
        $stmt->execute([$tahun]);
        $weather = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($weather && $weather['avg_wind'] > 20) {
            $score += 15;
            $factors[] = ['name' => 'Angin Kencang', 'impact' => '+15', 'value' => round($weather['avg_wind'], 1) . ' km/h'];
        }
        
        // Factor 3: Grade distribution
        $gradeSQL = "SELECT 
                        COUNT(CASE WHEN grade_kualitas IN ('C','D') THEN 1 END) as low_grade,
                        COUNT(*) as total
                     FROM produksi_gabah
                     WHERE kecamatan_id = ? AND tahun = ?";
        $stmt = $this->db->prepare($gradeSQL);
        $stmt->execute([$kecamatanId, $tahun]);
        $grades = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($grades['total'] > 0) {
            $lowGradePercent = ($grades['low_grade'] / $grades['total']) * 100;
            if ($lowGradePercent > 30) {
                $score += 10;
                $factors[] = ['name' => 'Grade Rendah Tinggi', 'impact' => '+10', 'value' => round($lowGradePercent, 1) . '%'];
            }
        }
        
        // Cap score between 0-100
        $score = max(0, min(100, $score));
        
        return [
            'kecamatan_id' => $kecamatanId,
            'tahun' => $tahun,
            'risk_score' => $score,
            'risk_level' => $this->getRiskLevel($score),
            'factors' => $factors,
            'recommendation' => $this->getRiskRecommendation($score, $factors)
        ];
    }
    
    /**
     * Get analytics by irigasi
     */
    public function getAnalyticsByIrigasi($tahun = null) {
        $tahun = $tahun ?: date('Y');
        
        try {
            $sql = "SELECT 
                        pg.irigasi_id,
                        i.nama_irigasi,
                        COUNT(*) as jumlah_data,
                        SUM(pg.luas_panen) as total_luas,
                        SUM(pg.produksi_total) as total_produksi,
                        ROUND(AVG(pg.produktivitas), 2) as avg_produktivitas,
                        ROUND(AVG(i.debit_air), 2) as avg_debit
                    FROM produksi_gabah pg
                    LEFT JOIN irigasi i ON pg.irigasi_id = i.id
                    WHERE pg.tahun = ? AND pg.status = 'verified' AND pg.irigasi_id IS NOT NULL
                    GROUP BY pg.irigasi_id
                    ORDER BY total_produksi DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tahun]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Irigasi table doesn't exist, return empty array
            return [];
        }
    }
    
    /**
     * Generate and save analytics to production_analytics table
     */
    public function generateAnalytics($tahun, $musim, $lokasiType, $lokasiId) {
        // Get aggregated production data
        $prodSQL = "SELECT 
                        SUM(luas_tanam) as total_luas_tanam,
                        SUM(luas_panen) as total_luas_panen,
                        SUM(produksi_total) as total_produksi,
                        ROUND(AVG(produktivitas), 2) as avg_produktivitas,
                        ROUND(AVG(kadar_air), 2) as avg_kadar_air,
                        COUNT(*) as data_count
                    FROM produksi_gabah
                    WHERE tahun = ? AND musim_tanam = ? AND {$lokasiType}_id = ?
                      AND status = 'verified'";
        
        $stmt = $this->db->prepare($prodSQL);
        $stmt->execute([$tahun, $musim, $lokasiId]);
        $prodData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prodData || $prodData['data_count'] == 0) {
            return false;
        }
        
        // Get location name
        $lokasi = $this->getLokasiName($lokasiType, $lokasiId);
        
        // Insert/Update analytics
        $sql = "INSERT INTO production_analytics 
                (periode, musim_tanam, tahun, lokasi_type, lokasi_id, lokasi_nama,
                 total_luas_tanam, total_luas_panen, total_produksi, avg_produktivitas,
                 avg_kadar_air, data_source_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_luas_tanam = VALUES(total_luas_tanam),
                    total_luas_panen = VALUES(total_luas_panen),
                    total_produksi = VALUES(total_produksi),
                    avg_produktivitas = VALUES(avg_produktivitas),
                    avg_kadar_air = VALUES(avg_kadar_air),
                    data_source_count = VALUES(data_source_count),
                    calculated_at = NOW()";
        
        $periode = $this->getPeriodeDate($musim, $tahun);
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $periode,
            $musim,
            $tahun,
            $lokasiType,
            $lokasiId,
            $lokasi,
            $prodData['total_luas_tanam'],
            $prodData['total_luas_panen'],
            $prodData['total_produksi'],
            $prodData['avg_produktivitas'],
            $prodData['avg_kadar_air'],
            $prodData['data_count']
        ]);
    }
    
    // ==================== HELPER METHODS ====================
    
    private function calculateCorrelation($x, $y) {
        $n = count($x);
        if ($n < 2 || count($y) !== $n) return 0;
        
        $x = array_map('floatval', $x);
        $y = array_map('floatval', $y);
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }
        
        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
        
        return $denominator != 0 ? round($numerator / $denominator, 4) : 0;
    }
    
    private function interpretCorrelation($r) {
        $absR = abs($r);
        $direction = $r >= 0 ? 'positif' : 'negatif';
        
        if ($absR >= 0.7) return "Korelasi {$direction} sangat kuat ({$r})";
        if ($absR >= 0.5) return "Korelasi {$direction} cukup kuat ({$r})";
        if ($absR >= 0.3) return "Korelasi {$direction} lemah ({$r})";
        return "Tidak ada korelasi signifikan ({$r})";
    }
    
    private function interpretPestCorrelation($r) {
        if ($r < -0.3) return "Serangan hama berkorelasi negatif dengan produktivitas - semakin banyak hama, semakin rendah hasil";
        if ($r > 0.3) return "Data menunjukkan pola tidak terduga, perlu investigasi lebih lanjut";
        return "Tidak ada korelasi signifikan antara insiden hama dan produktivitas";
    }
    
    private function getIrrigationRecommendation($correlation, $data) {
        if ($correlation > 0.5) {
            return "Tingkatkan debit air pada wilayah dengan produktivitas rendah untuk meningkatkan hasil";
        }
        return "Pertahankan pola irigasi saat ini, fokus pada pemeliharaan infrastruktur";
    }
    
    private function getWeatherRecommendation($data) {
        $highWindMonths = array_filter($data, fn($d) => ($d['avg_wind_speed'] ?? 0) > 20);
        if (!empty($highWindMonths)) {
            return "Perhatikan bulan dengan angin kencang, pertimbangkan varietas tahan rebah";
        }
        return "Kondisi cuaca mendukung produksi optimal";
    }
    
    private function analyzeWeatherImpact($data) {
        $impacts = [];
        foreach ($data as $d) {
            if (($d['avg_wind_speed'] ?? 0) > 25 && ($d['produktivitas'] ?? 0) < 5) {
                $impacts[] = "Bulan {$d['bulan']}: Angin kencang mungkin berdampak pada produktivitas";
            }
        }
        return $impacts ?: ['Tidak ada dampak cuaca signifikan terdeteksi'];
    }
    
    private function getSimulatedPestCorrelation($tahun) {
        // Return simulated data when hama table doesn't exist
        $sql = "SELECT 
                    kecamatan_id,
                    MIN(nama_lokasi) as nama_lokasi,
                    AVG(produktivitas) as avg_produktivitas,
                    0 as pest_incidents
                FROM produksi_gabah
                WHERE tahun = ? AND status = 'verified'
                GROUP BY kecamatan_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tahun]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function identifyHighRiskAreas($data) {
        return array_filter($data, fn($d) => ($d['pest_incidents'] ?? 0) > 3);
    }
    
    private function getRiskLevel($score) {
        if ($score < 30) return 'low';
        if ($score < 50) return 'medium';
        if ($score < 70) return 'high';
        return 'critical';
    }
    
    private function getRiskRecommendation($score, $factors) {
        if ($score >= 70) {
            return "Wilayah berisiko tinggi. Prioritaskan: monitoring intensif, perbaikan irigasi, pengendalian hama preventif.";
        }
        if ($score >= 50) {
            return "Perhatian diperlukan. Pantau kondisi cuaca dan irigasi secara teratur.";
        }
        return "Risiko rendah. Pertahankan praktik pertanian saat ini.";
    }
    
    private function getLokasiName($type, $id) {
        $tableMap = [
            'kabupaten' => 'kabupaten',
            'kecamatan' => 'kecamatan',
            'desa' => 'desa'
        ];
        
        try {
            $table = $tableMap[$type] ?? 'kecamatan';
            $sql = "SELECT nama FROM {$table} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['nama'] : "Lokasi {$id}";
        } catch (Exception $e) {
            return "Lokasi {$id}";
        }
    }
    
    private function getPeriodeDate($musim, $tahun) {
        $monthMap = ['MT1' => '10', 'MT2' => '04', 'MT3' => '08'];
        $month = $monthMap[$musim] ?? '01';
        return "{$tahun}-{$month}-01";
    }
}
