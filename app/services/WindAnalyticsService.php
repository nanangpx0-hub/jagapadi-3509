<?php
/**
 * Wind Analytics Service
 * Service layer untuk analisis dan rekomendasi berbasis data kecepatan angin
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class WindAnalyticsService {
    
    private $db;
    private $model;
    
    // Threshold konfigurasi (dalam km/h)
    const SPRAY_OPTIMAL_MAX = 10.8;      // < 3 m/s = optimal
    const SPRAY_ACCEPTABLE_MAX = 18.0;    // < 5 m/s = masih bisa
    const SPRAY_NOT_RECOMMENDED = 25.2;   // < 7 m/s = tidak disarankan
    // > 7 m/s = berbahaya
    
    // Beaufort Scale definitions
    private $beaufortScale = [
        0 => ['min' => 0, 'max' => 1, 'desc' => 'Tenang', 'desc_en' => 'Calm', 'impact' => 'Tidak ada dampak'],
        1 => ['min' => 1, 'max' => 5, 'desc' => 'Sepoi-sepoi', 'desc_en' => 'Light air', 'impact' => 'Minimal'],
        2 => ['min' => 6, 'max' => 11, 'desc' => 'Angin ringan', 'desc_en' => 'Light breeze', 'impact' => 'Baik untuk penyerbukan'],
        3 => ['min' => 12, 'max' => 19, 'desc' => 'Angin lemah', 'desc_en' => 'Gentle breeze', 'impact' => 'Penyemprotan aman'],
        4 => ['min' => 20, 'max' => 28, 'desc' => 'Angin sedang', 'desc_en' => 'Moderate breeze', 'impact' => 'Penyemprotan hati-hati'],
        5 => ['min' => 29, 'max' => 38, 'desc' => 'Angin segar', 'desc_en' => 'Fresh breeze', 'impact' => 'Tidak disarankan semprot'],
        6 => ['min' => 39, 'max' => 49, 'desc' => 'Angin kuat', 'desc_en' => 'Strong breeze', 'impact' => 'Berbahaya untuk tanaman'],
        7 => ['min' => 50, 'max' => 61, 'desc' => 'Angin keras', 'desc_en' => 'High wind', 'impact' => 'Risiko kerusakan tanaman'],
        8 => ['min' => 62, 'max' => 74, 'desc' => 'Badai', 'desc_en' => 'Gale', 'impact' => 'Kerusakan signifikan'],
        9 => ['min' => 75, 'max' => 88, 'desc' => 'Badai kuat', 'desc_en' => 'Strong gale', 'impact' => 'Kerusakan berat'],
        10 => ['min' => 89, 'max' => 102, 'desc' => 'Badai dahsyat', 'desc_en' => 'Storm', 'impact' => 'Kerusakan luas'],
        11 => ['min' => 103, 'max' => 117, 'desc' => 'Badai sangat dahsyat', 'desc_en' => 'Violent storm', 'impact' => 'Kehancuran'],
        12 => ['min' => 118, 'max' => 999, 'desc' => 'Topan', 'desc_en' => 'Hurricane', 'impact' => 'Kehancuran total']
    ];
    
    // Cardinal directions with pest risk
    private $cardinalDirections = [
        'N'  => ['min' => 337.5, 'max' => 22.5, 'name' => 'Utara', 'risk' => 'medium'],
        'NE' => ['min' => 22.5, 'max' => 67.5, 'name' => 'Timur Laut', 'risk' => 'high'],
        'E'  => ['min' => 67.5, 'max' => 112.5, 'name' => 'Timur', 'risk' => 'low'],
        'SE' => ['min' => 112.5, 'max' => 157.5, 'name' => 'Tenggara', 'risk' => 'medium'],
        'S'  => ['min' => 157.5, 'max' => 202.5, 'name' => 'Selatan', 'risk' => 'high'],
        'SW' => ['min' => 202.5, 'max' => 247.5, 'name' => 'Barat Daya', 'risk' => 'medium'],
        'W'  => ['min' => 247.5, 'max' => 292.5, 'name' => 'Barat', 'risk' => 'low'],
        'NW' => ['min' => 292.5, 'max' => 337.5, 'name' => 'Barat Laut', 'risk' => 'medium']
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
        $this->model = new KecepatanAngin();
    }
    
    /**
     * Calculate Moving Average for wind speed data
     * @param array $data Array of wind data records
     * @param int $period Period for moving average (default 5)
     * @return array Smoothed data with moving average values
     */
    public function calculateMovingAverage($data, $period = 5) {
        $result = [];
        $count = count($data);
        
        if ($count < $period) {
            return $data; // Not enough data for moving average
        }
        
        for ($i = 0; $i < $count; $i++) {
            if ($i < $period - 1) {
                // Not enough previous data, use original
                $result[] = $data[$i];
                $result[$i]['moving_avg'] = null;
            } else {
                $sum = 0;
                for ($j = 0; $j < $period; $j++) {
                    $sum += floatval($data[$i - $j]['kecepatan_angin'] ?? 0);
                }
                $movingAvg = round($sum / $period, 2);
                $result[] = $data[$i];
                $result[$i]['moving_avg'] = $movingAvg;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert wind speed to Beaufort Scale
     * @param float $speedKmh Wind speed in km/h
     * @return array Beaufort scale info with scale number, description, and impact
     */
    public function convertToBeaufortScale($speedKmh) {
        $speed = floatval($speedKmh);
        
        foreach ($this->beaufortScale as $scale => $data) {
            if ($speed >= $data['min'] && $speed <= $data['max']) {
                return [
                    'scale' => $scale,
                    'description' => $data['desc'],
                    'description_en' => $data['desc_en'],
                    'impact' => $data['impact'],
                    'speed_kmh' => $speed,
                    'speed_ms' => round($speed / 3.6, 2)
                ];
            }
        }
        
        // Default to highest scale if > 118 km/h
        return [
            'scale' => 12,
            'description' => $this->beaufortScale[12]['desc'],
            'description_en' => $this->beaufortScale[12]['desc_en'],
            'impact' => $this->beaufortScale[12]['impact'],
            'speed_kmh' => $speed,
            'speed_ms' => round($speed / 3.6, 2)
        ];
    }
    
    /**
     * Analyze wind impact on pest spread and spraying
     * @param float $direction Wind direction in degrees (0-360)
     * @param float $speedKmh Wind speed in km/h
     * @return array Analysis result with direction, risk level, and recommendations
     */
    public function analyzeWindImpact($direction, $speedKmh) {
        $direction = floatval($direction);
        $speed = floatval($speedKmh);
        
        // Get cardinal direction and pest risk
        $cardinalDir = $this->getCardinalDirection($direction);
        $directionInfo = $this->cardinalDirections[$cardinalDir] ?? null;
        
        // Calculate spray recommendation
        $sprayStatus = $this->getSprayStatus($speed);
        
        // Calculate pest spread risk based on direction + speed
        $pestRisk = $this->calculatePestRisk($speed, $directionInfo['risk'] ?? 'medium');
        
        return [
            'direction_degrees' => $direction,
            'direction_cardinal' => $cardinalDir,
            'direction_name' => $directionInfo['name'] ?? 'Tidak diketahui',
            'speed_kmh' => $speed,
            'speed_ms' => round($speed / 3.6, 2),
            'beaufort' => $this->convertToBeaufortScale($speed),
            'spray_status' => $sprayStatus,
            'pest_risk' => $pestRisk,
            'recommendations' => $this->generateRecommendations($speed, $cardinalDir, $pestRisk)
        ];
    }
    
    /**
     * Get spray recommendation for current conditions
     * @param float $speedKmh Wind speed in km/h
     * @param float|null $direction Wind direction in degrees
     * @param string|null $timeOfDay 'morning', 'afternoon', 'evening'
     * @return array Spray recommendation with status, reason, and optimal times
     */
    public function getSprayRecommendation($speedKmh, $direction = null, $timeOfDay = null) {
        $speed = floatval($speedKmh);
        $sprayStatus = $this->getSprayStatus($speed);
        
        $recommendation = [
            'status' => $sprayStatus['status'],
            'status_code' => $sprayStatus['code'],
            'color' => $sprayStatus['color'],
            'icon' => $sprayStatus['icon'],
            'reason' => $sprayStatus['reason'],
            'speed_kmh' => $speed,
            'speed_ms' => round($speed / 3.6, 2),
            'beaufort' => $this->convertToBeaufortScale($speed),
            'optimal_times' => $this->getOptimalSprayTimes($speed),
            'precautions' => $this->getSprayPrecautions($speed, $direction)
        ];
        
        // Add direction analysis if available
        if ($direction !== null) {
            $recommendation['wind_direction'] = $this->getCardinalDirection($direction);
            $recommendation['direction_impact'] = $this->getDirectionImpact($direction);
        }
        
        return $recommendation;
    }
    
    /**
     * Calculate evapotranspiration factor based on wind
     * @param float $speedKmh Wind speed in km/h
     * @param float $temperature Temperature in Celsius (optional)
     * @param float $humidity Humidity percentage (optional)
     * @return array Evapotranspiration analysis for irrigation adjustment
     */
    public function calculateEvapotranspiration($speedKmh, $temperature = null, $humidity = null) {
        $speed = floatval($speedKmh);
        $speedMs = $speed / 3.6;
        
        // Simplified wind factor for evapotranspiration
        // Based on FAO Penman-Monteith equation simplified
        $windFactor = 1 + (0.34 * $speedMs);
        
        // Calculate irrigation adjustment multiplier
        if ($speedMs < 2) {
            $irrigationMultiplier = 1.0; // Normal irrigation
        } elseif ($speedMs < 5) {
            $irrigationMultiplier = 1.1; // 10% increase
        } elseif ($speedMs < 8) {
            $irrigationMultiplier = 1.2; // 20% increase
        } else {
            $irrigationMultiplier = 1.3; // 30% increase
        }
        
        $result = [
            'wind_speed_ms' => $speedMs,
            'wind_factor' => round($windFactor, 3),
            'irrigation_multiplier' => $irrigationMultiplier,
            'irrigation_adjustment' => round(($irrigationMultiplier - 1) * 100) . '%',
            'recommendation' => $this->getIrrigationRecommendation($speedMs)
        ];
        
        // Add temperature effect if provided
        if ($temperature !== null) {
            $tempFactor = 1 + (($temperature - 20) * 0.02); // 2% per degree above 20°C
            $result['temperature'] = $temperature;
            $result['temp_factor'] = round(max(0.8, min(1.5, $tempFactor)), 3);
        }
        
        // Add humidity effect if provided
        if ($humidity !== null) {
            $humidityFactor = 1 + ((50 - $humidity) * 0.005); // Increase if humidity < 50%
            $result['humidity'] = $humidity;
            $result['humidity_factor'] = round(max(0.8, min(1.3, $humidityFactor)), 3);
        }
        
        return $result;
    }
    
    /**
     * Get wind rose data for visualization
     * @param array $data Wind data records
     * @return array Wind rose data grouped by direction
     */
    public function getWindRoseData($data) {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $windRose = [];
        
        foreach ($directions as $dir) {
            $windRose[$dir] = [
                'direction' => $dir,
                'name' => $this->cardinalDirections[$dir]['name'],
                'count' => 0,
                'avg_speed' => 0,
                'max_speed' => 0,
                'speeds' => []
            ];
        }
        
        foreach ($data as $record) {
            if (!isset($record['arah_angin']) || $record['arah_angin'] === null) continue;
            
            $dir = $this->getCardinalDirection($record['arah_angin']);
            $speed = floatval($record['kecepatan_angin'] ?? 0);
            
            $windRose[$dir]['count']++;
            $windRose[$dir]['speeds'][] = $speed;
            $windRose[$dir]['max_speed'] = max($windRose[$dir]['max_speed'], $speed);
        }
        
        // Calculate averages
        foreach ($directions as $dir) {
            if ($windRose[$dir]['count'] > 0) {
                $windRose[$dir]['avg_speed'] = round(
                    array_sum($windRose[$dir]['speeds']) / $windRose[$dir]['count'], 
                    2
                );
            }
            unset($windRose[$dir]['speeds']); // Remove raw speeds
        }
        
        return [
            'directions' => $directions,
            'data' => array_values($windRose),
            'total_records' => count($data)
        ];
    }
    
    /**
     * Generate daily summary and save to database
     * @param string $date Date in Y-m-d format
     * @param string $lokasi Location name
     * @return array Summary data
     */
    public function generateDailySummary($date, $lokasi = null) {
        $sql = "SELECT 
                    tanggal,
                    lokasi,
                    ROUND(AVG(kecepatan_angin), 2) as avg_speed,
                    MAX(kecepatan_max) as max_speed,
                    MIN(kecepatan_angin) as min_speed,
                    AVG(arah_angin) as avg_direction,
                    COUNT(*) as data_points
                FROM kecepatan_angin
                WHERE tanggal = ?";
        
        $params = [$date];
        
        if ($lokasi) {
            $sql .= " AND lokasi LIKE ?";
            $params[] = "%$lokasi%";
        }
        
        $sql .= " GROUP BY tanggal, lokasi";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $summaries = [];
        foreach ($results as $row) {
            $beaufort = $this->convertToBeaufortScale($row['avg_speed']);
            $spraySafe = floatval($row['avg_speed']) <= self::SPRAY_ACCEPTABLE_MAX ? 1 : 0;
            $pestRisk = $this->calculatePestRiskLevel($row['avg_speed']);
            $dirDesc = $row['avg_direction'] ? $this->getCardinalDirection($row['avg_direction']) : null;
            
            $summary = [
                'tanggal' => $row['tanggal'],
                'lokasi' => $row['lokasi'],
                'avg_speed' => $row['avg_speed'],
                'max_speed' => $row['max_speed'],
                'min_speed' => $row['min_speed'],
                'dominant_direction' => $row['avg_direction'],
                'dominant_direction_desc' => $dirDesc,
                'beaufort_scale' => $beaufort['scale'],
                'beaufort_desc' => $beaufort['description'],
                'spray_safe' => $spraySafe,
                'pest_risk_level' => $pestRisk,
                'data_points' => $row['data_points']
            ];
            
            // Save to database
            $this->saveDailySummary($summary);
            $summaries[] = $summary;
        }
        
        return $summaries;
    }
    
    // =============================================
    // Private Helper Methods
    // =============================================
    
    private function getCardinalDirection($degrees) {
        $degrees = floatval($degrees);
        
        // N is special case (wraps around 360)
        if ($degrees >= 337.5 || $degrees < 22.5) return 'N';
        if ($degrees >= 22.5 && $degrees < 67.5) return 'NE';
        if ($degrees >= 67.5 && $degrees < 112.5) return 'E';
        if ($degrees >= 112.5 && $degrees < 157.5) return 'SE';
        if ($degrees >= 157.5 && $degrees < 202.5) return 'S';
        if ($degrees >= 202.5 && $degrees < 247.5) return 'SW';
        if ($degrees >= 247.5 && $degrees < 292.5) return 'W';
        if ($degrees >= 292.5 && $degrees < 337.5) return 'NW';
        
        return 'N';
    }
    
    private function getSprayStatus($speedKmh) {
        if ($speedKmh <= self::SPRAY_OPTIMAL_MAX) {
            return [
                'code' => 'optimal',
                'status' => 'Optimal untuk penyemprotan',
                'color' => 'success',
                'icon' => 'check-circle',
                'reason' => 'Kecepatan angin rendah, drift pestisida minimal'
            ];
        } elseif ($speedKmh <= self::SPRAY_ACCEPTABLE_MAX) {
            return [
                'code' => 'acceptable',
                'status' => 'Penyemprotan dapat dilakukan',
                'color' => 'warning',
                'icon' => 'exclamation-triangle',
                'reason' => 'Perhatikan arah angin dan gunakan teknik yang tepat'
            ];
        } elseif ($speedKmh <= self::SPRAY_NOT_RECOMMENDED) {
            return [
                'code' => 'not_recommended',
                'status' => 'Tidak disarankan menyemprot',
                'color' => 'danger',
                'icon' => 'times-circle',
                'reason' => 'Risiko drift tinggi, pestisida tidak efektif'
            ];
        } else {
            return [
                'code' => 'dangerous',
                'status' => 'Berbahaya! Jangan menyemprot',
                'color' => 'dark',
                'icon' => 'skull-crossbones',
                'reason' => 'Angin terlalu kencang, berbahaya untuk operator'
            ];
        }
    }
    
    private function calculatePestRisk($speed, $directionRisk) {
        $riskScore = 0;
        
        // Speed contribution (0-50 points)
        if ($speed < 10) $riskScore += 10;
        elseif ($speed < 20) $riskScore += 25;
        elseif ($speed < 30) $riskScore += 40;
        else $riskScore += 50;
        
        // Direction contribution (0-50 points)
        switch ($directionRisk) {
            case 'low': $riskScore += 10; break;
            case 'medium': $riskScore += 30; break;
            case 'high': $riskScore += 50; break;
        }
        
        // Determine level
        if ($riskScore < 30) return ['level' => 'low', 'score' => $riskScore, 'label' => 'Rendah'];
        if ($riskScore < 60) return ['level' => 'medium', 'score' => $riskScore, 'label' => 'Sedang'];
        if ($riskScore < 80) return ['level' => 'high', 'score' => $riskScore, 'label' => 'Tinggi'];
        return ['level' => 'critical', 'score' => $riskScore, 'label' => 'Kritis'];
    }
    
    private function calculatePestRiskLevel($avgSpeed) {
        if ($avgSpeed < 15) return 'low';
        if ($avgSpeed < 25) return 'medium';
        return 'high';
    }
    
    private function generateRecommendations($speed, $direction, $pestRisk) {
        $recommendations = [];
        
        // Spray recommendations
        if ($speed <= self::SPRAY_OPTIMAL_MAX) {
            $recommendations[] = 'Waktu optimal untuk penyemprotan pestisida';
        } elseif ($speed <= self::SPRAY_ACCEPTABLE_MAX) {
            $recommendations[] = 'Gunakan nozzle dengan butiran lebih besar untuk mengurangi drift';
        } else {
            $recommendations[] = 'Tunda penyemprotan hingga angin mereda';
        }
        
        // Pest risk recommendations
        if ($pestRisk['level'] === 'high' || $pestRisk['level'] === 'critical') {
            $recommendations[] = 'Perhatikan penyebaran hama dari arah ' . $direction;
            $recommendations[] = 'Lakukan monitoring intensif pada area terdampak';
        }
        
        // Irrigation recommendations
        if ($speed > 20) {
            $recommendations[] = 'Tingkatkan frekuensi irigasi karena evapotranspirasi tinggi';
        }
        
        return $recommendations;
    }
    
    private function getOptimalSprayTimes($currentSpeed) {
        // Recommend optimal times based on typical wind patterns
        $optimal = [];
        
        if ($currentSpeed > self::SPRAY_ACCEPTABLE_MAX) {
            $optimal[] = 'Pagi hari (05:00 - 08:00) biasanya lebih tenang';
            $optimal[] = 'Sore hari (16:00 - 18:00) setelah angin mereda';
        } else {
            $optimal[] = 'Kondisi saat ini sudah sesuai untuk penyemprotan';
        }
        
        return $optimal;
    }
    
    private function getSprayPrecautions($speed, $direction = null) {
        $precautions = [];
        
        if ($speed > self::SPRAY_OPTIMAL_MAX) {
            $precautions[] = 'Gunakan tekanan semprot lebih rendah';
            $precautions[] = 'Semprot searah angin untuk menghindari paparan operator';
        }
        
        if ($direction !== null) {
            $cardinal = $this->getCardinalDirection($direction);
            $precautions[] = "Perhatikan area di arah $cardinal yang mungkin terkena drift";
        }
        
        return $precautions;
    }
    
    private function getDirectionImpact($direction) {
        $cardinal = $this->getCardinalDirection($direction);
        $info = $this->cardinalDirections[$cardinal] ?? null;
        
        return [
            'direction' => $cardinal,
            'name' => $info['name'] ?? 'Tidak diketahui',
            'pest_risk' => $info['risk'] ?? 'medium'
        ];
    }
    
    private function getIrrigationRecommendation($speedMs) {
        if ($speedMs < 2) {
            return 'Irigasi normal sesuai jadwal';
        } elseif ($speedMs < 5) {
            return 'Pertimbangkan penambahan 10% volume irigasi';
        } elseif ($speedMs < 8) {
            return 'Tambah 20% volume irigasi, hindari irigasi sprinkler';
        } else {
            return 'Gunakan irigasi tetes, hindari sprinkler. Tambah 30% volume';
        }
    }
    
    private function saveDailySummary($summary) {
        $sql = "INSERT INTO wind_daily_summary 
                (tanggal, lokasi, avg_speed, max_speed, min_speed, 
                 dominant_direction, dominant_direction_desc, beaufort_scale, 
                 beaufort_desc, spray_safe, pest_risk_level, data_points)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    avg_speed = VALUES(avg_speed),
                    max_speed = VALUES(max_speed),
                    min_speed = VALUES(min_speed),
                    dominant_direction = VALUES(dominant_direction),
                    dominant_direction_desc = VALUES(dominant_direction_desc),
                    beaufort_scale = VALUES(beaufort_scale),
                    beaufort_desc = VALUES(beaufort_desc),
                    spray_safe = VALUES(spray_safe),
                    pest_risk_level = VALUES(pest_risk_level),
                    data_points = VALUES(data_points)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $summary['tanggal'],
                $summary['lokasi'],
                $summary['avg_speed'],
                $summary['max_speed'],
                $summary['min_speed'],
                $summary['dominant_direction'],
                $summary['dominant_direction_desc'],
                $summary['beaufort_scale'],
                $summary['beaufort_desc'],
                $summary['spray_safe'],
                $summary['pest_risk_level'],
                $summary['data_points']
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("WindAnalyticsService: Failed to save daily summary - " . $e->getMessage());
            return false;
        }
    }
}
