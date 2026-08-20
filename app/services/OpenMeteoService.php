<?php
/**
 * Open-Meteo API Service
 * 
 * Client for fetching actual precipitation data from Open-Meteo API.
 * Free API, no authentication required.
 * 
 * API Documentation: https://open-meteo.com/en/docs
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class OpenMeteoService {
    
    /**
     * API Configuration
     */
    private const API_URL = 'https://api.open-meteo.com/v1/forecast';
    private const CACHE_DIR = ROOT_PATH . '/storage/cache/openmeteo';
    private const CACHE_TTL = 3600; // 1 hour cache
    private const REQUEST_DELAY = 200000; // 200ms between requests (microseconds)
    private const TIMEOUT = 30; // 30 seconds timeout
    
    /**
     * @var array Monitored locations from kecamatan table
     */
    private $locations = [];
    
    /**
     * @var bool Enable debug logging
     */
    private $debug = false;
    
    /**
     * @var string Log file path
     */
    private $logFile;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logFile = ROOT_PATH . '/logs/openmeteo.log';
        $this->ensureCacheDir();
        $this->loadLocations();
    }
    
    /**
     * Check if Open-Meteo API is available
     * 
     * @return bool
     */
    public function isAvailable(): bool {
        $testUrl = self::API_URL . '?latitude=-8.17&longitude=113.70&hourly=precipitation&forecast_days=1';
        
        $response = $this->httpRequest($testUrl, ['timeout' => 10]);
        if ($response === false) {
            return false;
        }
        
        $data = json_decode($response, true);
        return isset($data['hourly']['precipitation']);
    }
    
    /**
     * Fetch precipitation data for a single location
     * 
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param int $forecastDays Number of forecast days (1-16)
     * @return array|false Precipitation data or false on failure
     */
    public function fetchPrecipitation(float $lat, float $lon, int $forecastDays = 7) {
        // Check cache first
        $cacheKey = $this->getCacheKey($lat, $lon, $forecastDays);
        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            $this->log("Cache hit for {$lat},{$lon}");
            return $cached;
        }
        
        // Build URL
        $url = self::API_URL . '?' . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'hourly' => 'precipitation,weather_code',
            'daily' => 'precipitation_sum,weather_code',
            'timezone' => 'Asia/Jakarta',
            'forecast_days' => min($forecastDays, 16)
        ]);
        
        $this->log("Fetching: {$url}");
        
        $response = $this->httpRequest($url);
        if ($response === false) {
            $this->log("Failed to fetch data for {$lat},{$lon}", 'ERROR');
            return false;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['hourly']) || !isset($data['daily'])) {
            $this->log("Invalid response structure for {$lat},{$lon}", 'ERROR');
            return false;
        }
        
        // Process and structure the data
        $result = $this->processApiResponse($data, $lat, $lon);
        
        // Cache the result
        $this->setCache($cacheKey, $result);
        
        return $result;
    }
    
    /**
     * Fetch precipitation for a specific kecamatan
     * 
     * @param int $kecamatanId Kecamatan ID
     * @return array|false
     */
    public function fetchForKecamatan(int $kecamatanId) {
        $location = $this->getLocationById($kecamatanId);
        if (!$location) {
            $this->log("Kecamatan ID {$kecamatanId} not found", 'ERROR');
            return false;
        }
        
        $data = $this->fetchPrecipitation(
            (float) $location['latitude'],
            (float) $location['longitude']
        );
        
        if ($data) {
            $data['kecamatan'] = $location['nama_kecamatan'];
            $data['kecamatan_id'] = $kecamatanId;
        }
        
        return $data;
    }
    
    /**
     * Fetch precipitation for all active kecamatan
     * 
     * @param string|null $targetDate Target date (YYYY-MM-DD) for filtering
     * @return array Results array
     */
    public function fetchAllKecamatan(?string $targetDate = null): array {
        $results = [
            'success' => [],
            'failed' => [],
            'data' => [],
            'source' => 'Open-Meteo',
            'fetch_time' => date('Y-m-d H:i:s')
        ];
        
        $targetDate = $targetDate ?? date('Y-m-d');
        
        foreach ($this->locations as $location) {
            try {
                $lat = (float) ($location['latitude'] ?? $location['lat'] ?? -8.1706);
                $lon = (float) ($location['longitude'] ?? $location['lng'] ?? $location['lon'] ?? 113.7003);

                $data = $this->fetchPrecipitation($lat, $lon);
                
                if ($data) {
                    // Extract data for target date
                    $dailyData = $this->extractDailyData($data, $targetDate);
                    
                    if ($dailyData) {
                        $record = [
                            'tanggal' => $targetDate,
                            'lokasi' => ($location['nama_kecamatan'] ?? 'Jember') . ', Jember',
                            'kode_wilayah' => $location['kode_bmkg_adm4'] ?? '35.09',
                            'curah_hujan' => $dailyData['precipitation_sum'],
                            'satuan' => 'mm',
                            'sumber_data' => 'Open-Meteo',
                            'keterangan' => sprintf(
                                'Data curah hujan dari Open-Meteo API. Koordinat: %s, %s. Kondisi cuaca: %s',
                                $lat,
                                $lon,
                                $dailyData['weather_desc'] ?? 'N/A'
                            ),
                            'kecamatan_id' => $location['id']
                        ];
                        
                        $results['data'][] = $record;
                        $results['success'][] = $location['nama_kecamatan'];
                    }
                } else {
                    $results['failed'][] = $location['nama_kecamatan'];
                }
                
                // Rate limiting delay
                usleep(self::REQUEST_DELAY);
                
            } catch (Exception $e) {
                $this->log("Error for {$location['nama_kecamatan']}: " . $e->getMessage(), 'ERROR');
                $results['failed'][] = $location['nama_kecamatan'];
            }
        }
        
        $results['total_success'] = count($results['success']);
        $results['total_failed'] = count($results['failed']);
        
        return $results;
    }

    /** Fetch daily precipitation for every monitored kecamatan in a range. */
    public function fetchAllKecamatanRange(string $startDate, string $endDate): array {
        $records = [];

        foreach ($this->locations as $location) {
            $lat = (float) ($location['latitude'] ?? -8.1706);
            $lon = (float) ($location['longitude'] ?? 113.7003);
            $dailyRows = $this->getDailyPrecipitation($lat, $lon, $startDate, $endDate);

            foreach ($dailyRows as $day) {
                $records[] = [
                    'tanggal' => $day['date'],
                    'lokasi' => ($location['nama_kecamatan'] ?? 'Jember') . ', Jember',
                    'kode_wilayah' => $location['kode'] ?? '35.09',
                    'curah_hujan' => $day['precipitation_mm'],
                    'satuan' => 'mm',
                    'sumber_data' => 'Open-Meteo',
                    'keterangan' => sprintf(
                        'Data curah hujan harian Open-Meteo. Koordinat: %s, %s. Kondisi: %s',
                        $lat,
                        $lon,
                        $day['weather_desc'] ?? 'N/A'
                    ),
                    'kecamatan_id' => $location['id'],
                ];
            }

            usleep(self::REQUEST_DELAY);
        }

        return $records;
    }
    
    /**
     * Get daily precipitation for a date range
     * 
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getDailyPrecipitation(float $lat, float $lon, string $startDate, string $endDate): array {
        $url = self::API_URL . '?' . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'daily' => 'precipitation_sum,weather_code',
            'timezone' => 'Asia/Jakarta',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        $response = $this->httpRequest($url);
        if ($response === false) {
            return [];
        }
        
        $data = json_decode($response, true);
        if (!isset($data['daily'])) {
            return [];
        }
        
        $results = [];
        $times = $data['daily']['time'] ?? [];
        $precipitation = $data['daily']['precipitation_sum'] ?? [];
        $weatherCodes = $data['daily']['weather_code'] ?? [];
        
        foreach ($times as $i => $date) {
            $results[] = [
                'date' => $date,
                'precipitation_mm' => $precipitation[$i] ?? 0,
                'weather_code' => $weatherCodes[$i] ?? 0,
                'weather_desc' => $this->getWeatherDescription($weatherCodes[$i] ?? 0)
            ];
        }
        
        return $results;
    }
    
    /**
     * Process API response into structured format
     */
    private function processApiResponse(array $data, float $lat, float $lon): array {
        $result = [
            'latitude' => $lat,
            'longitude' => $lon,
            'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
            'hourly' => [],
            'daily' => []
        ];
        
        // Process hourly data
        if (isset($data['hourly']['time'])) {
            foreach ($data['hourly']['time'] as $i => $time) {
                $result['hourly'][] = [
                    'datetime' => $time,
                    'precipitation_mm' => $data['hourly']['precipitation'][$i] ?? 0,
                    'weather_code' => $data['hourly']['weather_code'][$i] ?? 0
                ];
            }
        }
        
        // Process daily data
        if (isset($data['daily']['time'])) {
            foreach ($data['daily']['time'] as $i => $date) {
                $weatherCode = $data['daily']['weather_code'][$i] ?? 0;
                $result['daily'][] = [
                    'date' => $date,
                    'precipitation_sum' => $data['daily']['precipitation_sum'][$i] ?? 0,
                    'weather_code' => $weatherCode,
                    'weather_desc' => $this->getWeatherDescription($weatherCode)
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Extract daily data for a specific date
     */
    private function extractDailyData(array $data, string $targetDate): ?array {
        foreach ($data['daily'] ?? [] as $day) {
            if ($day['date'] === $targetDate) {
                return $day;
            }
        }
        return null;
    }
    
    /**
     * Get weather description from WMO weather code
     * 
     * @see https://open-meteo.com/en/docs (WMO Weather interpretation codes)
     */
    private function getWeatherDescription(int $code): string {
        $descriptions = [
            0 => 'Cerah',
            1 => 'Sebagian Cerah',
            2 => 'Berawan Sebagian',
            3 => 'Berawan',
            45 => 'Berkabut',
            48 => 'Kabut Tebal',
            51 => 'Gerimis Ringan',
            53 => 'Gerimis Sedang',
            55 => 'Gerimis Lebat',
            56 => 'Gerimis Dingin Ringan',
            57 => 'Gerimis Dingin Lebat',
            61 => 'Hujan Ringan',
            63 => 'Hujan Sedang',
            65 => 'Hujan Lebat',
            66 => 'Hujan Dingin Ringan',
            67 => 'Hujan Dingin Lebat',
            71 => 'Salju Ringan',
            73 => 'Salju Sedang',
            75 => 'Salju Lebat',
            77 => 'Butiran Salju',
            80 => 'Hujan Shower Ringan',
            81 => 'Hujan Shower Sedang',
            82 => 'Hujan Shower Lebat',
            85 => 'Salju Shower Ringan',
            86 => 'Salju Shower Lebat',
            95 => 'Badai Petir',
            96 => 'Badai Petir + Hujan Es Ringan',
            99 => 'Badai Petir + Hujan Es Lebat'
        ];
        
        return $descriptions[$code] ?? 'Tidak Diketahui';
    }
    
    /**
     * Load locations from master_kecamatan (sumber kebenaran kecamatan)
     */
    private function loadLocations(): void {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT id, nama_kecamatan, kode, latitude, longitude 
                 FROM master_kecamatan 
                 WHERE latitude IS NOT NULL 
                   AND longitude IS NOT NULL 
                 ORDER BY nama_kecamatan"
            );
            $stmt->execute();
            $this->locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log("Loaded " . count($this->locations) . " kecamatan");

        } catch (Exception $e) {
            $this->log("Failed to load kecamatan: " . $e->getMessage(), 'ERROR');
            // Use fallback locations if table doesn't exist
            $this->locations = $this->getFallbackLocations();
        }
    }
    
    /**
     * Get fallback locations if database table not available
     */
    private function getFallbackLocations(): array {
        return [
            ['id' => 1, 'nama_kecamatan' => 'Kaliwates', 'latitude' => -8.1617, 'longitude' => 113.7214, 'kode_bmkg_adm4' => '35.09.29'],
            ['id' => 2, 'nama_kecamatan' => 'Sumbersari', 'latitude' => -8.1725, 'longitude' => 113.7161, 'kode_bmkg_adm4' => '35.09.30'],
            ['id' => 3, 'nama_kecamatan' => 'Patrang', 'latitude' => -8.1392, 'longitude' => 113.7169, 'kode_bmkg_adm4' => '35.09.31'],
            ['id' => 4, 'nama_kecamatan' => 'Ajung', 'latitude' => -8.2180, 'longitude' => 113.6420, 'kode_bmkg_adm4' => '35.09.11'],
            ['id' => 5, 'nama_kecamatan' => 'Ambulu', 'latitude' => -8.3450, 'longitude' => 113.6100, 'kode_bmkg_adm4' => '35.09.05'],
        ];
    }
    
    /**
     * Get location by ID
     */
    private function getLocationById(int $id): ?array {
        foreach ($this->locations as $loc) {
            if ($loc['id'] == $id) {
                return $loc;
            }
        }
        return null;
    }
    
    /**
     * Get all monitored locations
     */
    public function getMonitoredLocations(): array {
        return $this->locations;
    }
    
    /**
     * HTTP Request with cURL
     */
    private function httpRequest(string $url, array $options = []) {
        $timeout = $options['timeout'] ?? self::TIMEOUT;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'JAGAPADI-WeatherClient/1.0 (PHP)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            $this->log("HTTP Error: {$httpCode}, {$error}", 'ERROR');
            return false;
        }
        
        return $response;
    }
    
    /**
     * Cache management
     */
    private function getCacheKey(float $lat, float $lon, int $days): string {
        return md5("{$lat}_{$lon}_{$days}_" . date('Y-m-d-H'));
    }
    
    private function getCache(string $key) {
        $file = self::CACHE_DIR . "/{$key}.json";
        if (!file_exists($file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
            @unlink($file);
            return false;
        }
        
        return $data['content'];
    }
    
    private function setCache(string $key, $content): void {
        $file = self::CACHE_DIR . "/{$key}.json";
        $data = [
            'expires' => time() + self::CACHE_TTL,
            'content' => $content
        ];
        file_put_contents($file, json_encode($data));
    }
    
    private function ensureCacheDir(): void {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0755, true);
        }
    }
    
    /**
     * Clear all cache
     */
    public function clearCache(): void {
        $files = glob(self::CACHE_DIR . '/*.json');
        foreach ($files as $file) {
            @unlink($file);
        }
        $this->log("Cache cleared");
    }
    
    /**
     * Log message
     */
    private function log(string $message, string $level = 'INFO'): void {
        $logEntry = sprintf(
            "[%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );
        
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
    }
}
