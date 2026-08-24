<?php
/**
 * Wind Integration Service
 * Service untuk mengintegrasikan data angin dengan modul Hama dan Irigasi
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class WindIntegrationService {
    
    private $db;
    private $analyticsService;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        
        require_once ROOT_PATH . '/app/services/WindAnalyticsService.php';
        $this->analyticsService = new WindAnalyticsService();
    }
    
    // =============================================
    // HAMA (PEST) INTEGRATION
    // =============================================
    
    /**
     * Get wind-pest correlation data
     * @param string|null $startDate Start date
     * @param string|null $endDate End date
     * @param string|null $lokasi Location filter
     * @return array Correlation data
     */
    public function getWindPestCorrelation($startDate = null, $endDate = null, $lokasi = null) {
        $endDate = $endDate ?: date('Y-m-d');
        $startDate = $startDate ?: date('Y-m-d', strtotime('-30 days'));
        
        // Get wind data aggregation
        $windSql = "SELECT 
                        DATE(tanggal) as tanggal,
                        AVG(kecepatan_angin) as avg_speed,
                        MAX(kecepatan_max) as max_speed,
                        AVG(arah_angin) as avg_direction
                    FROM kecepatan_angin
                    WHERE tanggal BETWEEN ? AND ?";
        $windParams = [$startDate, $endDate];
        
        if ($lokasi) {
            $windSql .= " AND lokasi LIKE ?";
            $windParams[] = "%$lokasi%";
        }
        
        $windSql .= " GROUP BY DATE(tanggal) ORDER BY tanggal";
        
        $stmt = $this->db->prepare($windSql);
        $stmt->execute($windParams);
        $windData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Try to get pest report data if table exists
        $pestData = [];
        try {
            $pestSql = "SELECT 
                            DATE(tanggal_laporan) as tanggal,
                            COUNT(*) as jumlah_laporan,
                            GROUP_CONCAT(DISTINCT jenis_hama) as jenis_hama
                        FROM laporan_hama
                        WHERE tanggal_laporan BETWEEN ? AND ? AND deleted_at IS NULL
                        GROUP BY DATE(tanggal_laporan)";
            
            $pestStmt = $this->db->prepare($pestSql);
            $pestStmt->execute([$startDate, $endDate]);
            $pestData = $pestStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table might not exist, continue without pest data
        }
        
        // Merge and analyze
        $correlations = [];
        foreach ($windData as $wind) {
            $date = $wind['tanggal'];
            $correlation = [
                'tanggal' => $date,
                'wind_speed' => round($wind['avg_speed'], 2),
                'wind_max' => round($wind['max_speed'], 2),
                'wind_direction' => $wind['avg_direction'] ? $this->getCardinalDirection($wind['avg_direction']) : null,
                'pest_reports' => 0,
                'pest_types' => null,
                'risk_score' => 0,
                'risk_level' => 'low'
            ];
            
            // Find matching pest data
            foreach ($pestData as $pest) {
                if ($pest['tanggal'] === $date) {
                    $correlation['pest_reports'] = $pest['jumlah_laporan'];
                    $correlation['pest_types'] = $pest['jenis_hama'];
                    break;
                }
            }
            
            // Calculate risk score based on wind conditions
            $riskScore = $this->calculatePestRiskScore($wind['avg_speed'], $wind['avg_direction']);
            $correlation['risk_score'] = $riskScore;
            $correlation['risk_level'] = $this->getRiskLevel($riskScore);
            
            $correlations[] = $correlation;
        }
        
        // Calculate overall statistics
        $stats = [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_days' => count($correlations),
            'avg_wind_speed' => count($windData) > 0 ? round(array_sum(array_column($windData, 'avg_speed')) / count($windData), 2) : 0,
            'total_pest_reports' => array_sum(array_column($correlations, 'pest_reports')),
            'high_risk_days' => count(array_filter($correlations, fn($c) => $c['risk_level'] === 'high')),
            'correlation_coefficient' => $this->calculateCorrelation(
                array_column($correlations, 'wind_speed'),
                array_column($correlations, 'pest_reports')
            )
        ];
        
        return [
            'success' => true,
            'statistics' => $stats,
            'data' => $correlations,
            'recommendations' => $this->generatePestRecommendations($stats, $correlations)
        ];
    }
    
    /**
     * Get pest spread prediction based on wind
     * @param float $speed Current wind speed km/h
     * @param float $direction Current wind direction degrees
     * @return array Spread prediction
     */
    public function getPestSpreadPrediction($speed, $direction) {
        $cardinal = $this->getCardinalDirection($direction);
        
        // Define spread vectors based on direction
        $spreadVectors = [
            'N' => ['affected_areas' => ['Selatan'], 'risk' => 'medium'],
            'NE' => ['affected_areas' => ['Barat Daya'], 'risk' => 'high'],
            'E' => ['affected_areas' => ['Barat'], 'risk' => 'low'],
            'SE' => ['affected_areas' => ['Barat Laut'], 'risk' => 'medium'],
            'S' => ['affected_areas' => ['Utara'], 'risk' => 'high'],
            'SW' => ['affected_areas' => ['Timur Laut'], 'risk' => 'medium'],
            'W' => ['affected_areas' => ['Timur'], 'risk' => 'low'],
            'NW' => ['affected_areas' => ['Tenggara'], 'risk' => 'medium']
        ];
        
        $vector = $spreadVectors[$cardinal] ?? $spreadVectors['N'];
        
        // Calculate spread distance based on speed
        $spreadDistance = $this->calculateSpreadDistance($speed);
        
        return [
            'wind_direction' => $cardinal,
            'wind_speed' => $speed,
            'affected_areas' => $vector['affected_areas'],
            'base_risk' => $vector['risk'],
            'spread_distance_km' => $spreadDistance,
            'spread_time_hours' => round($spreadDistance / max($speed / 3.6, 0.1), 1),
            'recommendations' => [
                "Monitor area {$vector['affected_areas'][0]} untuk potensi penyebaran hama",
                $speed > 15 ? "Siapkan tindakan pencegahan di area terdampak" : "Risiko penyebaran rendah",
                "Pertimbangkan penyemprotan preventif di area berisiko"
            ]
        ];
    }
    
    // =============================================
    // IRIGASI (IRRIGATION) INTEGRATION
    // =============================================
    
    /**
     * Get irrigation adjustment recommendation based on wind
     * @param float|null $windSpeed Wind speed km/h
     * @param float|null $temperature Temperature celsius
     * @param float|null $humidity Humidity percentage
     * @return array Irrigation adjustment
     */
    public function getIrrigationAdjustment($windSpeed = null, $temperature = null, $humidity = null) {
        // Get latest wind data if not provided
        if ($windSpeed === null) {
            $sql = "SELECT kecepatan_angin FROM kecepatan_angin ORDER BY tanggal DESC LIMIT 1";
            $stmt = $this->db->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $windSpeed = $result ? $result['kecepatan_angin'] : 10;
        }
        
        // Calculate evapotranspiration adjustment
        $etAnalysis = $this->analyticsService->calculateEvapotranspiration($windSpeed, $temperature, $humidity);
        
        // Get spray recommendation
        $sprayRec = $this->analyticsService->getSprayRecommendation($windSpeed);
        
        // Generate irrigation schedule adjustment
        $scheduleAdjustment = $this->calculateIrrigationSchedule($etAnalysis, $windSpeed);
        
        return [
            'success' => true,
            'wind_conditions' => [
                'speed_kmh' => $windSpeed,
                'speed_ms' => round($windSpeed / 3.6, 2),
                'beaufort' => $this->analyticsService->convertToBeaufortScale($windSpeed)
            ],
            'evapotranspiration' => $etAnalysis,
            'irrigation_adjustment' => [
                'volume_multiplier' => $etAnalysis['irrigation_multiplier'],
                'volume_adjustment' => $etAnalysis['irrigation_adjustment'],
                'method_recommendation' => $this->getIrrigationMethodRecommendation($windSpeed),
                'schedule' => $scheduleAdjustment
            ],
            'spray_status' => [
                'safe' => $sprayRec['status_code'] === 'optimal' || $sprayRec['status_code'] === 'acceptable',
                'status' => $sprayRec['status'],
                'reason' => $sprayRec['reason']
            ]
        ];
    }
    
    /**
     * Hook into existing irrigation schedule
     * @param array $existingSchedule Existing irrigation schedule
     * @param string $date Date to adjust
     * @return array Adjusted schedule
     */
    public function adjustIrrigationSchedule($existingSchedule, $date = null) {
        $date = $date ?: date('Y-m-d');
        
        // Get wind data for the date
        $sql = "SELECT AVG(kecepatan_angin) as avg_speed FROM kecepatan_angin WHERE DATE(tanggal) = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        $windData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $windSpeed = $windData ? $windData['avg_speed'] : 10;
        $adjustment = $this->getIrrigationAdjustment($windSpeed);
        
        // Adjust existing schedule
        $adjustedSchedule = [];
        foreach ($existingSchedule as $item) {
            $adjusted = $item;
            
            // Adjust volume
            if (isset($item['volume'])) {
                $adjusted['original_volume'] = $item['volume'];
                $adjusted['adjusted_volume'] = round($item['volume'] * $adjustment['irrigation_adjustment']['volume_multiplier'], 2);
                $adjusted['volume'] = $adjusted['adjusted_volume'];
            }
            
            // Add wind-based notes
            $adjusted['wind_adjustment_applied'] = true;
            $adjusted['wind_speed_avg'] = round($windSpeed, 2);
            $adjusted['adjustment_factor'] = $adjustment['irrigation_adjustment']['volume_adjustment'];
            
            $adjustedSchedule[] = $adjusted;
        }
        
        return [
            'success' => true,
            'date' => $date,
            'wind_conditions' => [
                'speed_kmh' => round($windSpeed, 2),
                'multiplier' => $adjustment['irrigation_adjustment']['volume_multiplier']
            ],
            'original_schedule' => $existingSchedule,
            'adjusted_schedule' => $adjustedSchedule
        ];
    }
    
    // =============================================
    // HELPER METHODS
    // =============================================
    
    private function getCardinalDirection($degrees) {
        $degrees = floatval($degrees);
        if ($degrees >= 337.5 || $degrees < 22.5) return 'N';
        if ($degrees >= 22.5 && $degrees < 67.5) return 'NE';
        if ($degrees >= 67.5 && $degrees < 112.5) return 'E';
        if ($degrees >= 112.5 && $degrees < 157.5) return 'SE';
        if ($degrees >= 157.5 && $degrees < 202.5) return 'S';
        if ($degrees >= 202.5 && $degrees < 247.5) return 'SW';
        if ($degrees >= 247.5 && $degrees < 292.5) return 'W';
        return 'NW';
    }
    
    private function calculatePestRiskScore($speed, $direction) {
        $score = 0;
        
        // Speed contribution (0-50)
        if ($speed < 10) $score += 10;
        elseif ($speed < 20) $score += 25;
        elseif ($speed < 30) $score += 40;
        else $score += 50;
        
        // Direction contribution (0-50)
        $highRiskDirections = ['NE', 'S', 'SW'];
        $cardinal = $direction ? $this->getCardinalDirection($direction) : 'N';
        if (in_array($cardinal, $highRiskDirections)) {
            $score += 50;
        } else {
            $score += 25;
        }
        
        return min(100, $score);
    }
    
    private function getRiskLevel($score) {
        if ($score < 30) return 'low';
        if ($score < 60) return 'medium';
        if ($score < 80) return 'high';
        return 'critical';
    }
    
    private function calculateSpreadDistance($speed) {
        // Simplified spread distance calculation
        // Based on average pest mobility with wind assistance
        $baseSpread = 0.5; // km without wind
        $windFactor = $speed / 10; // Additional km per 10 km/h
        return round($baseSpread + $windFactor, 2);
    }
    
    private function calculateCorrelation($x, $y) {
        $n = count($x);
        if ($n < 2 || count($y) !== $n) return 0;
        
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
    
    private function generatePestRecommendations($stats, $correlations) {
        $recommendations = [];
        
        if ($stats['high_risk_days'] > 5) {
            $recommendations[] = "Waspada: {$stats['high_risk_days']} hari dengan risiko penyebaran hama tinggi";
        }
        
        if ($stats['correlation_coefficient'] > 0.5) {
            $recommendations[] = "Korelasi kuat antara kecepatan angin dan laporan hama terdeteksi";
        }
        
        if ($stats['avg_wind_speed'] > 20) {
            $recommendations[] = "Rata-rata kecepatan angin tinggi, perhatikan penyebaran hama antar wilayah";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Kondisi angin normal, lanjutkan monitoring rutin";
        }
        
        return $recommendations;
    }
    
    private function getIrrigationMethodRecommendation($windSpeed) {
        if ($windSpeed < 10) {
            return [
                'method' => 'Sprinkler atau Tetes',
                'reason' => 'Angin rendah, semua metode irigasi efektif'
            ];
        } elseif ($windSpeed < 20) {
            return [
                'method' => 'Irigasi Tetes',
                'reason' => 'Hindari sprinkler karena angin sedang menyebabkan evaporasi tinggi'
            ];
        } else {
            return [
                'method' => 'Irigasi Tetes atau Bawah Permukaan',
                'reason' => 'Angin kencang, sprinkler sangat tidak efisien'
            ];
        }
    }
    
    private function calculateIrrigationSchedule($etAnalysis, $windSpeed) {
        $schedule = [];
        
        // Morning recommendation
        if ($windSpeed < 15) {
            $schedule[] = [
                'time' => '06:00 - 08:00',
                'recommended' => true,
                'reason' => 'Pagi hari, angin biasanya lebih tenang'
            ];
        }
        
        // Midday - avoid if windy
        $schedule[] = [
            'time' => '10:00 - 14:00',
            'recommended' => $windSpeed < 10,
            'reason' => $windSpeed >= 10 ? 'Hindari - evapotranspirasi tinggi' : 'Dapat dilakukan dengan hati-hati'
        ];
        
        // Evening recommendation
        $schedule[] = [
            'time' => '16:00 - 18:00',
            'recommended' => true,
            'reason' => 'Sore hari, angin biasanya mereda'
        ];
        
        return $schedule;
    }
}
