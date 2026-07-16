<?php
/**
 * Weather Service
 * 
 * Wrapper service untuk integrasi data cuaca dengan sistem irigasi.
 * Menggunakan OpenMeteoService dan menyediakan caching serta
 * fungsi-fungsi khusus untuk keputusan pengairan.
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class WeatherService {
    
    private ?OpenMeteoService $openMeteo = null;
    private $db;
    private $cacheTable = 'weather_cache';
    private $alertsTable = 'weather_alerts';
    private $thresholdsTable = 'irrigation_adaptive_thresholds';
    private $debug = false;
    private $logFile;
    
    // Weather impact multipliers for irrigation
    private const WEATHER_MULTIPLIERS = [
        'heavy_rain' => 0.0,      // No irrigation needed
        'moderate_rain' => 0.3,   // Reduce by 70%
        'light_rain' => 0.6,      // Reduce by 40%
        'cloudy' => 0.9,          // Slight reduction
        'partly_cloudy' => 1.0,   // Normal
        'sunny' => 1.1,           // Slight increase
        'hot_dry' => 1.3,         // Increase by 30%
    ];
    
    // Precipitation thresholds (mm)
    private const RAIN_THRESHOLDS = [
        'heavy' => 20,
        'moderate' => 10,
        'light' => 2,
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logFile = ROOT_PATH . '/logs/weather_service.log';
        
        // Initialize OpenMeteoService
        $openMeteoPath = ROOT_PATH . '/app/services/OpenMeteoService.php';
        if (file_exists($openMeteoPath)) {
            require_once $openMeteoPath;
            $this->openMeteo = new OpenMeteoService();
        }
    }
    
    /**
     * Get weather forecast for a location
     * 
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param int $days Number of days (1-16)
     * @return array
     */
    public function getForecast(float $lat, float $lng, int $days = 7): array {
        // Check cache first
        $cacheKey = $this->getCacheKey($lat, $lng);
        $cached = $this->getFromCache($cacheKey, $days);
        
        if (!empty($cached)) {
            $this->log("Cache hit for {$cacheKey}");
            return $cached;
        }
        
        // Fetch from API
        if ($this->openMeteo && $this->openMeteo->isAvailable()) {
            $data = $this->openMeteo->fetchPrecipitation($lat, $lng, $days);
            
            if ($data) {
                $this->cacheResult($cacheKey, $data, $lat, $lng);
                return $data;
            }
        }
        
        $this->log("Failed to fetch weather data for lat={$lat}, lng={$lng}", 'WARNING');
        return $this->getDefaultForecast($days);
    }
    
    /**
     * Get weather for a specific irigasi location
     * 
     * @param int $irigasiId
     * @return array
     */
    public function getForIrigasi(int $irigasiId): array {
        // Get irigasi coordinates
        $stmt = $this->db->prepare("
            SELECT koordinat_lat, koordinat_lng, nama_saluran, kecamatan_id
            FROM laporan_irigasi
            WHERE id = ?
        ");
        $stmt->execute([$irigasiId]);
        $irigasi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$irigasi || !$irigasi['koordinat_lat'] || !$irigasi['koordinat_lng']) {
            // Try to get from kecamatan
            if ($irigasi && $irigasi['kecamatan_id']) {
                return $this->getForKecamatan($irigasi['kecamatan_id']);
            }
            return $this->getDefaultForecast(7);
        }
        
        return $this->getForecast(
            (float) $irigasi['koordinat_lat'],
            (float) $irigasi['koordinat_lng'],
            7
        );
    }
    
    /**
     * Get weather for a kecamatan
     * 
     * @param int $kecamatanId
     * @return array
     */
    public function getForKecamatan(int $kecamatanId): array {
        // Get kecamatan coordinates from kecamatan_jember table
        $stmt = $this->db->prepare("
            SELECT latitude, longitude, nama
            FROM kecamatan_jember
            WHERE id = ?
        ");
        $stmt->execute([$kecamatanId]);
        $kec = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($kec && $kec['latitude'] && $kec['longitude']) {
            return $this->getForecast(
                (float) $kec['latitude'],
                (float) $kec['longitude'],
                7
            );
        }
        
        return $this->getDefaultForecast(7);
    }
    
    /**
     * Get current weather conditions for an irigasi
     * 
     * @param int $irigasiId
     * @return array
     */
    public function getCurrentConditions(int $irigasiId): array {
        $forecast = $this->getForIrigasi($irigasiId);
        $today = date('Y-m-d');
        
        if (isset($forecast['daily'])) {
            foreach ($forecast['daily'] as $day) {
                if ($day['date'] === $today) {
                    return [
                        'date' => $today,
                        'precipitation' => $day['precipitation_sum'] ?? 0,
                        'temperature_max' => $day['temperature_max'] ?? null,
                        'temperature_min' => $day['temperature_min'] ?? null,
                        'weather_code' => $day['weather_code'] ?? null,
                        'description' => $day['description'] ?? 'Tidak diketahui',
                        'category' => $this->categorizeWeather($day),
                    ];
                }
            }
        }
        
        return [
            'date' => $today,
            'precipitation' => 0,
            'category' => 'unknown',
            'description' => 'Data tidak tersedia'
        ];
    }
    
    /**
     * Determine if irrigation should be reduced based on weather
     * 
     * @param int $irigasiId
     * @return bool
     */
    public function shouldReduceIrrigation(int $irigasiId): bool {
        $conditions = $this->getCurrentConditions($irigasiId);
        $precipitation = $conditions['precipitation'] ?? 0;
        
        // Check today and tomorrow
        $forecast = $this->getForIrigasi($irigasiId);
        $totalPrecip = $precipitation;
        
        if (isset($forecast['daily']) && count($forecast['daily']) > 1) {
            $totalPrecip += $forecast['daily'][1]['precipitation_sum'] ?? 0;
        }
        
        // Reduce if significant rain expected
        return $totalPrecip >= self::RAIN_THRESHOLDS['light'];
    }
    
    /**
     * Get adaptive multiplier for irrigation based on weather
     * 
     * @param int $irigasiId
     * @return float Multiplier (0.0 - 1.5)
     */
    public function getAdaptiveMultiplier(int $irigasiId): float {
        $conditions = $this->getCurrentConditions($irigasiId);
        $precipitation = $conditions['precipitation'] ?? 0;
        $category = $conditions['category'] ?? 'unknown';
        
        // Check precipitation levels
        if ($precipitation >= self::RAIN_THRESHOLDS['heavy']) {
            return self::WEATHER_MULTIPLIERS['heavy_rain'];
        }
        if ($precipitation >= self::RAIN_THRESHOLDS['moderate']) {
            return self::WEATHER_MULTIPLIERS['moderate_rain'];
        }
        if ($precipitation >= self::RAIN_THRESHOLDS['light']) {
            return self::WEATHER_MULTIPLIERS['light_rain'];
        }
        
        // Use category-based multiplier
        return self::WEATHER_MULTIPLIERS[$category] ?? 1.0;
    }
    
    /**
     * Get adjusted thresholds based on weather
     * 
     * @param int $irigasiId
     * @param string $sensorType
     * @return array ['min' => float, 'max' => float]
     */
    public function getAdjustedThresholds(int $irigasiId, string $sensorType): array {
        // Get base thresholds
        $stmt = $this->db->prepare("
            SELECT base_threshold_min, base_threshold_max, is_auto_adjust
            FROM {$this->thresholdsTable}
            WHERE irigasi_id = ? AND sensor_type = ?
        ");
        $stmt->execute([$irigasiId, $sensorType]);
        $threshold = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Default thresholds if not configured
        if (!$threshold) {
            $defaults = $this->getDefaultThresholds($sensorType);
            return ['min' => $defaults['min'], 'max' => $defaults['max']];
        }
        
        if (!$threshold['is_auto_adjust']) {
            return [
                'min' => (float) $threshold['base_threshold_min'],
                'max' => (float) $threshold['base_threshold_max']
            ];
        }
        
        // Apply weather adjustment
        $multiplier = $this->getAdaptiveMultiplier($irigasiId);
        $baseMin = (float) $threshold['base_threshold_min'];
        $baseMax = (float) $threshold['base_threshold_max'];
        
        // For soil moisture: if rain expected, lower the min threshold
        if ($sensorType === 'soil_moisture') {
            $adjustedMin = $baseMin * $multiplier;
            $adjustedMax = $baseMax;
            
            // Don't go below absolute minimum
            $adjustedMin = max($adjustedMin, 10);
        } else {
            $adjustedMin = $baseMin;
            $adjustedMax = $baseMax;
        }
        
        // Update current thresholds in database
        $this->updateCurrentThresholds($irigasiId, $sensorType, $adjustedMin, $adjustedMax, $multiplier);
        
        return ['min' => $adjustedMin, 'max' => $adjustedMax];
    }
    
    /**
     * Check weather and create alerts if necessary
     * 
     * @param int $irigasiId
     * @return array Created alerts
     */
    public function checkAndCreateAlerts(int $irigasiId): array {
        $forecast = $this->getForIrigasi($irigasiId);
        $alerts = [];
        
        if (!isset($forecast['daily'])) {
            return $alerts;
        }
        
        foreach (array_slice($forecast['daily'], 0, 3) as $day) {
            $precipitation = $day['precipitation_sum'] ?? 0;
            $tempMax = $day['temperature_max'] ?? null;
            
            // Heavy rain alert
            if ($precipitation >= self::RAIN_THRESHOLDS['heavy']) {
                $alerts[] = $this->createAlert($irigasiId, 'heavy_rain', 'warning', 
                    "Prakiraan hujan lebat {$day['date']}",
                    "Curah hujan diprakirakan {$precipitation}mm. Pengairan otomatis akan dikurangi.",
                    $day
                );
            }
            
            // Drought alert (no rain for extended period would need historical data)
            
            // Extreme temperature alert
            if ($tempMax && $tempMax > 38) {
                $alerts[] = $this->createAlert($irigasiId, 'extreme_temp', 'warning',
                    "Suhu ekstrem diprakirakan {$day['date']}",
                    "Suhu maksimum diprakirakan {$tempMax}°C. Pertimbangkan untuk meningkatkan frekuensi pengairan.",
                    $day
                );
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get active weather alerts for an irigasi
     * 
     * @param int $irigasiId
     * @return array
     */
    public function getActiveAlerts(int $irigasiId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->alertsTable}
            WHERE irigasi_id = ?
            AND is_dismissed = 0
            AND (valid_until IS NULL OR valid_until > NOW())
            ORDER BY severity DESC, created_at DESC
        ");
        $stmt->execute([$irigasiId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Dismiss an alert
     * 
     * @param int $alertId
     * @return bool
     */
    public function dismissAlert(int $alertId): bool {
        $stmt = $this->db->prepare("
            UPDATE {$this->alertsTable}
            SET is_dismissed = 1
            WHERE id = ?
        ");
        return $stmt->execute([$alertId]);
    }
    
    // =========================================================================
    // Private Helper Methods
    // =========================================================================
    
    private function getCacheKey(float $lat, float $lng): string {
        return sprintf("%.4f_%.4f", $lat, $lng);
    }
    
    private function getFromCache(string $locationKey, int $days): array {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->cacheTable}
            WHERE location_key = ?
            AND forecast_date >= CURDATE()
            AND forecast_date < DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND is_valid = 1
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY forecast_date ASC
        ");
        $stmt->execute([$locationKey, $days]);
        $cached = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($cached)) {
            return [];
        }
        
        // Format as forecast array
        return [
            'source' => 'cache',
            'location_key' => $locationKey,
            'daily' => array_map(function($row) {
                return [
                    'date' => $row['forecast_date'],
                    'precipitation_sum' => (float) $row['precipitation_mm'],
                    'precipitation_probability' => (int) $row['precipitation_probability'],
                    'temperature_max' => (float) $row['temperature_max'],
                    'temperature_min' => (float) $row['temperature_min'],
                    'weather_code' => (int) $row['weather_code'],
                    'description' => $row['weather_description'],
                ];
            }, $cached)
        ];
    }
    
    private function cacheResult(string $locationKey, array $data, float $lat, float $lng): void {
        if (!isset($data['daily'])) {
            return;
        }
        
        $expiresAt = date('Y-m-d H:i:s', strtotime('+6 hours'));
        
        foreach ($data['daily'] as $day) {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->cacheTable}
                (location_key, forecast_date, precipitation_mm, precipitation_probability,
                 temperature_max, temperature_min, weather_code, weather_description,
                 source, fetched_at, expires_at, is_valid)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open_meteo', NOW(), ?, 1)
                ON DUPLICATE KEY UPDATE
                    precipitation_mm = VALUES(precipitation_mm),
                    precipitation_probability = VALUES(precipitation_probability),
                    temperature_max = VALUES(temperature_max),
                    temperature_min = VALUES(temperature_min),
                    weather_code = VALUES(weather_code),
                    weather_description = VALUES(weather_description),
                    fetched_at = NOW(),
                    expires_at = VALUES(expires_at),
                    is_valid = 1
            ");
            
            $stmt->execute([
                $locationKey,
                $day['date'] ?? date('Y-m-d'),
                $day['precipitation_sum'] ?? 0,
                $day['precipitation_probability'] ?? null,
                $day['temperature_max'] ?? null,
                $day['temperature_min'] ?? null,
                $day['weather_code'] ?? null,
                $day['description'] ?? null,
                $expiresAt
            ]);
        }
    }
    
    private function categorizeWeather(array $dayData): string {
        $precipitation = $dayData['precipitation_sum'] ?? 0;
        $weatherCode = $dayData['weather_code'] ?? 0;
        
        if ($precipitation >= self::RAIN_THRESHOLDS['heavy']) {
            return 'heavy_rain';
        }
        if ($precipitation >= self::RAIN_THRESHOLDS['moderate']) {
            return 'moderate_rain';
        }
        if ($precipitation >= self::RAIN_THRESHOLDS['light']) {
            return 'light_rain';
        }
        
        // WMO weather codes
        if ($weatherCode <= 1) {
            return 'sunny';
        }
        if ($weatherCode <= 3) {
            return 'partly_cloudy';
        }
        if ($weatherCode <= 49) {
            return 'cloudy';
        }
        
        return 'partly_cloudy';
    }
    
    private function getDefaultForecast(int $days): array {
        $daily = [];
        for ($i = 0; $i < $days; $i++) {
            $daily[] = [
                'date' => date('Y-m-d', strtotime("+{$i} days")),
                'precipitation_sum' => 0,
                'temperature_max' => null,
                'temperature_min' => null,
                'weather_code' => null,
                'description' => 'Data tidak tersedia'
            ];
        }
        
        return [
            'source' => 'default',
            'daily' => $daily
        ];
    }
    
    private function getDefaultThresholds(string $sensorType): array {
        $defaults = [
            'soil_moisture' => ['min' => 30, 'max' => 80],
            'water_ph' => ['min' => 6.0, 'max' => 7.5],
            'water_flow' => ['min' => 5, 'max' => 50],
            'temperature' => ['min' => 20, 'max' => 35],
            'humidity' => ['min' => 40, 'max' => 90],
        ];
        
        return $defaults[$sensorType] ?? ['min' => 0, 'max' => 100];
    }
    
    private function updateCurrentThresholds(
        int $irigasiId, 
        string $sensorType, 
        float $min, 
        float $max, 
        float $multiplier
    ): void {
        $stmt = $this->db->prepare("
            UPDATE {$this->thresholdsTable}
            SET current_threshold_min = ?,
                current_threshold_max = ?,
                adjustment_factor = ?,
                adjustment_reason = ?,
                last_adjusted_at = NOW()
            WHERE irigasi_id = ? AND sensor_type = ?
        ");
        
        $reason = $multiplier < 1 ? 'Prakiraan hujan - threshold diturunkan' :
                 ($multiplier > 1 ? 'Cuaca panas/kering - threshold normal' : 'Cuaca normal');
        
        $stmt->execute([$min, $max, $multiplier, $reason, $irigasiId, $sensorType]);
    }
    
    private function createAlert(
        int $irigasiId,
        string $type,
        string $severity,
        string $title,
        string $message,
        array $weatherData
    ): array {
        // Check if similar alert already exists
        $stmt = $this->db->prepare("
            SELECT id FROM {$this->alertsTable}
            WHERE irigasi_id = ? AND alert_type = ? AND is_dismissed = 0
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$irigasiId, $type]);
        
        if ($stmt->fetch()) {
            return []; // Alert already exists
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO {$this->alertsTable}
            (irigasi_id, alert_type, severity, title, message, weather_data, valid_until)
            VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        
        $stmt->execute([
            $irigasiId,
            $type,
            $severity,
            $title,
            $message,
            json_encode($weatherData)
        ]);
        
        return [
            'id' => $this->db->lastInsertId(),
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message
        ];
    }
    
    private function log(string $message, string $level = 'INFO'): void {
        $logEntry = sprintf(
            "[%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        if ($this->debug) {
            echo $logEntry;
        }
    }
    
    /**
     * Enable/disable debug mode
     */
    public function setDebug(bool $enabled): void {
        $this->debug = $enabled;
        if ($this->openMeteo) {
            $this->openMeteo->setDebug($enabled);
        }
    }
    
    /**
     * Clear expired cache entries
     */
    public function clearExpiredCache(): int {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->cacheTable}
            WHERE expires_at < NOW()
            OR forecast_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
