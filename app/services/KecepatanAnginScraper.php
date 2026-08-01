<?php
/**
 * Kecepatan Angin Scraper
 * Service untuk mengambil data kecepatan angin dari Open-Meteo API
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class KecepatanAnginScraper {
    
    private const API_URL = 'https://api.open-meteo.com/v1/forecast';
    private const CACHE_DIR = ROOT_PATH . '/storage/cache/wind';
    private const CACHE_TTL = 3600;
    private const REQUEST_DELAY = 200000;
    private const TIMEOUT = 30;
    
    private $model;
    private $locations = [];
    private $debug = false;
    private $logFile;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
        $this->model = new KecepatanAngin();
        $this->logFile = ROOT_PATH . '/logs/wind_scraper.log';
        $this->ensureCacheDir();
        $this->loadLocations();
    }
    
    /**
     * Run the scraper
     */
    public function run($options = []) {
        $startTime = microtime(true);
        
        $year = $options['year'] ?? date('Y');
        $month = $options['month'] ?? date('m');
        $forceSimulation = $options['force_simulation'] ?? false;
        
        $this->log("Starting wind speed scraper for {$year}-{$month}");
        
        $result = [
            'success' => false,
            'message' => '',
            'source' => '',
            'records_success' => 0,
            'records_failed' => 0,
            'execution_time' => 0
        ];
        
        try {
            if ($forceSimulation) {
                $data = $this->generateSimulatedData($year, $month);
                $result['source'] = 'Simulasi';
            } else {
                $data = $this->fetchFromOpenMeteo($year, $month);
                if (empty($data)) {
                    $this->log("Open-Meteo failed, using simulation");
                    $data = $this->generateSimulatedData($year, $month);
                    $result['source'] = 'Simulasi (Fallback)';
                } else {
                    $result['source'] = 'Open-Meteo';
                }
            }
            
            // Save data
            foreach ($data as $record) {
                try {
                    $this->model->insert($record);
                    $result['records_success']++;
                } catch (Exception $e) {
                    $this->log("Failed to insert: " . $e->getMessage(), 'ERROR');
                    $result['records_failed']++;
                }
            }
            
            $result['success'] = $result['records_success'] > 0;
            $result['message'] = sprintf(
                "Berhasil mengambil %d data kecepatan angin dari %s",
                $result['records_success'],
                $result['source']
            );
            
            // Log activity
            $this->model->logActivity('scrape', $result['success'] ? 'success' : 'failed', $result['message'], [
                'year' => $year,
                'month' => $month,
                'source' => $result['source'],
                'processed' => count($data),
                'success' => $result['records_success'],
                'failed' => $result['records_failed']
            ]);
            
        } catch (Exception $e) {
            $result['message'] = "Error: " . $e->getMessage();
            $this->log($result['message'], 'ERROR');
            
            $this->model->logActivity('scrape', 'failed', $result['message'], [
                'error' => $e->getMessage()
            ]);
        }
        
        $result['execution_time'] = round(microtime(true) - $startTime, 2);
        
        $this->log("Scraper completed in {$result['execution_time']}s");
        
        return $result;
    }
    
    /**
     * Fetch data from Open-Meteo API
     */
    private function fetchFromOpenMeteo($year, $month) {
        $data = [];
        $targetDate = sprintf('%d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($targetDate));
        
        foreach ($this->locations as $location) {
            try {
                $url = self::API_URL . '?' . http_build_query([
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'daily' => 'wind_speed_10m_max,wind_direction_10m_dominant',
                    'hourly' => 'wind_speed_10m',
                    'timezone' => 'Asia/Jakarta',
                    'start_date' => $targetDate,
                    'end_date' => $endDate
                ]);
                
                $response = $this->httpRequest($url);
                if ($response === false) {
                    continue;
                }
                
                $apiData = json_decode($response, true);
                if (!isset($apiData['daily'])) {
                    continue;
                }
                
                $times = $apiData['daily']['time'] ?? [];
                $maxSpeeds = $apiData['daily']['wind_speed_10m_max'] ?? [];
                $directions = $apiData['daily']['wind_direction_10m_dominant'] ?? [];
                
                // Calculate daily average from hourly data
                $hourlyTimes = $apiData['hourly']['time'] ?? [];
                $hourlySpeeds = $apiData['hourly']['wind_speed_10m'] ?? [];
                
                $dailyAvg = [];
                foreach ($hourlyTimes as $i => $time) {
                    $date = substr($time, 0, 10);
                    if (!isset($dailyAvg[$date])) {
                        $dailyAvg[$date] = ['sum' => 0, 'count' => 0];
                    }
                    $dailyAvg[$date]['sum'] += $hourlySpeeds[$i] ?? 0;
                    $dailyAvg[$date]['count']++;
                }
                
                foreach ($times as $i => $date) {
                    $avgSpeed = 0;
                    if (isset($dailyAvg[$date]) && $dailyAvg[$date]['count'] > 0) {
                        $avgSpeed = $dailyAvg[$date]['sum'] / $dailyAvg[$date]['count'];
                    }
                    
                    $direction = $directions[$i] ?? null;
                    $directionDesc = $direction !== null ? KecepatanAngin::degreesToDirection($direction) : null;
                    
                    $data[] = [
                        'tanggal' => $date,
                        'lokasi' => $location['nama_kecamatan'] . ', Jember',
                        'kode_wilayah' => $location['kode_bmkg_adm4'] ?? '35.09',
                        'kecepatan_angin' => round($avgSpeed, 2),
                        'kecepatan_max' => $maxSpeeds[$i] ?? null,
                        'arah_angin' => $direction,
                        'arah_angin_desc' => $directionDesc ? KecepatanAngin::getDirectionName($directionDesc) : null,
                        'satuan' => 'km/h',
                        'sumber_data' => 'Open-Meteo',
                        'keterangan' => sprintf('Data angin dari Open-Meteo API. Koordinat: %s, %s',
                            $location['latitude'], $location['longitude'])
                    ];
                }
                
                usleep(self::REQUEST_DELAY);
                
            } catch (Exception $e) {
                $this->log("Error for {$location['nama_kecamatan']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return $data;
    }
    
    /**
     * Generate simulated data
     */
    private function generateSimulatedData($year, $month) {
        $data = [];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        $locations = !empty($this->locations) ? $this->locations : [
            ['nama_kecamatan' => 'Kaliwates', 'kode_bmkg_adm4' => '35.09.29'],
            ['nama_kecamatan' => 'Sumbersari', 'kode_bmkg_adm4' => '35.09.30'],
            ['nama_kecamatan' => 'Patrang', 'kode_bmkg_adm4' => '35.09.31']
        ];
        
        // Wind patterns vary by season (June-Sept typically drier with stronger winds in Indonesia)
        $baseSpeed = ($month >= 6 && $month <= 9) ? 15 : 10;
        
        foreach ($locations as $location) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%d-%02d-%02d', $year, $month, $day);
                
                // Add some randomness
                $avgSpeed = $baseSpeed + (rand(-50, 50) / 10);
                $maxSpeed = $avgSpeed * (1.5 + (rand(0, 50) / 100));
                $direction = rand(0, 359);
                $directionCode = KecepatanAngin::degreesToDirection($direction);
                
                $data[] = [
                    'tanggal' => $date,
                    'lokasi' => ($location['nama_kecamatan'] ?? 'Jember') . ', Jember',
                    'kode_wilayah' => $location['kode_bmkg_adm4'] ?? '35.09',
                    'kecepatan_angin' => round(max(0, $avgSpeed), 2),
                    'kecepatan_max' => round(max(0, $maxSpeed), 2),
                    'arah_angin' => $direction,
                    'arah_angin_desc' => KecepatanAngin::getDirectionName($directionCode),
                    'satuan' => 'km/h',
                    'sumber_data' => 'Simulasi',
                    'keterangan' => 'Data simulasi untuk pengujian. Tidak mencerminkan kondisi aktual.'
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Load locations from database
     */
    private function loadLocations() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT id, nama_kecamatan, latitude, longitude, kode_bps, kode_bmkg_adm4 
                 FROM kecamatan_jember 
                 WHERE is_active = 1 
                 ORDER BY nama_kecamatan"
            );
            $stmt->execute();
            $this->locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->log("Loaded " . count($this->locations) . " active kecamatan");
            
        } catch (Exception $e) {
            $this->log("Failed to load kecamatan: " . $e->getMessage(), 'ERROR');
            $this->locations = $this->getFallbackLocations();
        }
    }
    
    /**
     * Fallback locations
     */
    private function getFallbackLocations() {
        return [
            ['id' => 1, 'nama_kecamatan' => 'Kaliwates', 'latitude' => -8.1617, 'longitude' => 113.7214, 'kode_bmkg_adm4' => '35.09.29'],
            ['id' => 2, 'nama_kecamatan' => 'Sumbersari', 'latitude' => -8.1725, 'longitude' => 113.7161, 'kode_bmkg_adm4' => '35.09.30'],
            ['id' => 3, 'nama_kecamatan' => 'Patrang', 'latitude' => -8.1392, 'longitude' => 113.7169, 'kode_bmkg_adm4' => '35.09.31'],
        ];
    }
    
    /**
     * HTTP Request
     */
    private function httpRequest($url, $options = []) {
        $timeout = $options['timeout'] ?? self::TIMEOUT;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'JAGAPADI-WindClient/1.0 (PHP)',
            CURLOPT_HTTPHEADER => ['Accept: application/json']
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
    private function ensureCacheDir() {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0755, true);
        }
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'INFO') {
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
     * Set debug mode
     */
    public function setDebug($enabled) {
        $this->debug = $enabled;
    }
}
