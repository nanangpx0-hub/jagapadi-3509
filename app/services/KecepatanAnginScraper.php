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
    private const ARCHIVE_API_URL = 'https://archive-api.open-meteo.com/v1/archive';
    private const CACHE_DIR = ROOT_PATH . '/storage/cache/wind';
    private const CACHE_TTL = 3600;
    private const REQUEST_DELAY = 200000;
    private const TIMEOUT = 30;
    private const NASA_MAX_CONCURRENCY = 4;
    private const NASA_MAX_RETRIES = 3;
    
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
        
        $year = (int) ($options['year'] ?? date('Y'));
        $month = (int) ($options['month'] ?? date('m'));
        $source = strtolower(trim((string) ($options['source'] ?? $options['data_source'] ?? 'nasa')));
        $forceSimulation = $options['force_simulation'] ?? false;
        
        $this->log("Starting wind speed scraper for {$year}-{$month} (source: {$source})");
        
        $result = [
            'success' => false,
            'message' => '',
            'source' => '',
            'records_success' => 0,
            'records_failed' => 0,
            'execution_time' => 0
        ];
        
        try {
            $this->assertValidPeriod($year, $month);
            if (!in_array($source, ['nasa', 'nasa_power', 'openmeteo', 'simulation'], true)) {
                throw new InvalidArgumentException('Sumber data angin tidak valid');
            }

            if ($forceSimulation || $source === 'simulation') {
                $data = $this->generateSimulatedData($year, $month);
                $result['source'] = 'Simulasi';
            } elseif ($source === 'nasa' || $source === 'nasa_power') {
                $data = $this->fetch_nasa_kecepatan_angin($year, $month);
                if (empty($data)) {
                    throw new RuntimeException('NASA POWER tidak mengembalikan data; tidak ada fallback otomatis ke simulasi');
                } else {
                    $result['source'] = 'NASA POWER (WS10M/WS2M)';
                }
            } else {
                $data = $this->fetchFromOpenMeteo($year, $month);
                if (empty($data)) {
                    throw new RuntimeException('Open-Meteo tidak mengembalikan data; pilih simulasi secara eksplisit bila diperlukan');
                } else {
                    $result['source'] = 'Open-Meteo';
                }
            }
            
            // Save data using UPSERT to prevent duplicates
            foreach ($data as $record) {
                try {
                    if (method_exists($this->model, 'insertUpsert')) {
                        $this->model->insertUpsert($record);
                    } else {
                        $this->model->insert($record);
                    }
                    $result['records_success']++;
                } catch (Exception $e) {
                    $this->log("Failed to insert: " . $e->getMessage(), 'ERROR');
                    $result['records_failed']++;
                }
            }
            
            $result['execution_time'] = round(microtime(true) - $startTime, 2);
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
        if ($endDate > date('Y-m-d')) {
            $endDate = date('Y-m-d');
        }
        $apiBaseUrl = $endDate < date('Y-m-d') ? self::ARCHIVE_API_URL : self::API_URL;
        
        foreach ($this->locations as $location) {
            try {
                $url = $apiBaseUrl . '?' . http_build_query([
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'daily' => 'wind_speed_10m_max,wind_direction_10m_dominant',
                    'hourly' => 'wind_speed_10m',
                    'timezone' => 'Asia/Jakarta',
                    'wind_speed_unit' => 'kmh',
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
        if ($year === (int) date('Y') && $month === (int) date('n')) {
            $daysInMonth = min($daysInMonth, (int) date('j'));
        }
        
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
                
                $seed = (int) sprintf('%u', crc32($date . '|' . ($location['nama_kecamatan'] ?? 'Jember')));
                $avgSpeed = $baseSpeed + ((($seed % 101) - 50) / 10);
                $maxSpeed = $avgSpeed * (1.5 + ((intdiv($seed, 101) % 51) / 100));
                $direction = intdiv($seed, 5151) % 360;
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
                "SELECT id, 
                        nama_kecamatan, 
                        kode AS kode_bmkg_adm4, 
                        latitude, 
                        longitude 
                 FROM master_kecamatan 
                 WHERE latitude IS NOT NULL 
                   AND longitude IS NOT NULL
                 ORDER BY nama_kecamatan"
            );
            $stmt->execute();
            $this->locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->log("Loaded " . count($this->locations) . " active kecamatan with coordinates");
            
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

    /**
     * Fetch daily wind speed data from NASA POWER API (WS10M, WS2M)
     * Menggunakan cURL Multi (Parallel Requests) & Local Caching
     *
     * @param int|null $year
     * @param int|null $month
     * @return array List of formatted records for model insertion
     */
    public function fetch_nasa_kecepatan_angin($year = null, $month = null) {
        $year = $year ? (int)$year : (int)date('Y');
        $month = $month !== null ? (int) $month : null;

        if ($month !== null) {
            $this->assertValidPeriod($year, $month);
        }
        
        if ($month) {
            $monthPad = str_pad($month, 2, '0', STR_PAD_LEFT);
            $lastDay = date('t', strtotime("{$year}-{$monthPad}-01"));
            $startDate = "{$year}{$monthPad}01";
            $endDate = "{$year}{$monthPad}{$lastDay}";
        } else {
            $startDate = "{$year}0101";
            $endDate = "{$year}1231";
        }

        $today = date('Ymd');
        if ($endDate > $today) {
            $endDate = $today;
        }

        $this->log("Fetching wind speed from NASA POWER API for {$startDate} - {$endDate}...");

        $cacheDir = ROOT_PATH . '/storage/cache/wind_nasa';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $kecamatanCoords = [
            'Ajung'       => ['lat' => -8.2435, 'lon' => 113.6644],
            'Ambulu'      => ['lat' => -8.3444, 'lon' => 113.6056],
            'Arjasa'      => ['lat' => -8.1064, 'lon' => 113.7381],
            'Balung'      => ['lat' => -8.2678, 'lon' => 113.5419],
            'Bangsalsari'  => ['lat' => -8.1481, 'lon' => 113.5289],
            'Gumukmas'    => ['lat' => -8.3308, 'lon' => 113.4150],
            'Jelbuk'      => ['lat' => -8.0531, 'lon' => 113.7431],
            'Jenggawah'   => ['lat' => -8.2717, 'lon' => 113.6706],
            'Jombang'     => ['lat' => -8.2567, 'lon' => 113.3481],
            'Kalisat'     => ['lat' => -8.1256, 'lon' => 113.8117],
            'Kaliwates'   => ['lat' => -8.1825, 'lon' => 113.6797],
            'Kencong'     => ['lat' => -8.2831, 'lon' => 113.3667],
            'Ledokombo'   => ['lat' => -8.1186, 'lon' => 113.8822],
            'Mayang'      => ['lat' => -8.1969, 'lon' => 113.7878],
            'Mumbulsari'  => ['lat' => -8.2750, 'lon' => 113.7222],
            'Pakusari'    => ['lat' => -8.1678, 'lon' => 113.7667],
            'Panti'       => ['lat' => -8.1086, 'lon' => 113.6067],
            'Patrang'     => ['lat' => -8.1506, 'lon' => 113.7125],
            'Puger'       => ['lat' => -8.3611, 'lon' => 113.4778],
            'Rambipuji'   => ['lat' => -8.2042, 'lon' => 113.6139],
            'Semboro'     => ['lat' => -8.2178, 'lon' => 113.4567],
            'Silo'        => ['lat' => -8.2464, 'lon' => 113.9186],
            'Sukorambi'   => ['lat' => -8.1469, 'lon' => 113.6492],
            'Sukowono'    => ['lat' => -8.0575, 'lon' => 113.8322],
            'Sumberbaru'  => ['lat' => -8.1278, 'lon' => 113.3986],
            'Sumberjambe' => ['lat' => -8.0317, 'lon' => 113.8967],
            'Sumbersari'  => ['lat' => -8.1758, 'lon' => 113.7208],
            'Tanggul'     => ['lat' => -8.1603, 'lon' => 113.4519],
            'Tempurejo'   => ['lat' => -8.3075, 'lon' => 113.7719],
            'Umbulsari'   => ['lat' => -8.2561, 'lon' => 113.4406],
            'Wuluhan'     => ['lat' => -8.3475, 'lon' => 113.5469],
        ];

        $allData = [];
        $uncached = [];

        // Check file cache
        foreach ($kecamatanCoords as $namaKecamatan => $coord) {
            $cacheKey = "nasa_wind_" . preg_replace('/[^a-z0-9_]/i', '', strtolower($namaKecamatan)) . "_{$startDate}_{$endDate}.json";
            $cachePath = $cacheDir . '/' . $cacheKey;

            if (file_exists($cachePath) && (time() - filemtime($cachePath) < 86400 * 7)) {
                $cachedContent = @file_get_contents($cachePath);
                $cachedJson = json_decode($cachedContent, true);
                if (is_array($cachedJson) && !empty($cachedJson)) {
                    foreach ($cachedJson as $rec) {
                        $allData[] = $rec;
                    }
                    continue;
                }
            }
            $uncached[$namaKecamatan] = $coord;
        }

        if (empty($uncached)) {
            $this->log("Loaded all " . count($allData) . " wind records for {$startDate}-{$endDate} directly from cache.");
            return $allData;
        }

        $requests = [];
        foreach ($uncached as $namaKecamatan => $coord) {
            $lat = $coord['lat'];
            $lon = $coord['lon'];

            $apiUrl = "https://power.larc.nasa.gov/api/temporal/daily/point?" . http_build_query([
                'parameters' => 'WS10M,WS2M',
                'community'  => 'AG',
                'longitude'  => $lon,
                'latitude'   => $lat,
                'start'      => $startDate,
                'end'        => $endDate,
                'format'     => 'JSON'
            ]);

            $requests[$namaKecamatan] = ['url' => $apiUrl, 'coord' => $coord];
        }

        // NASA POWER may return 403/429 when dozens of point requests arrive at
        // once. Keep concurrency deliberately small and retry transient denials.
        $responses = $this->fetchNasaRequests($requests);

        foreach ($responses as $namaKecamatan => $result) {
            $coord = $requests[$namaKecamatan]['coord'];
            $lat = $coord['lat'];
            $lon = $coord['lon'];

            $response = $result['body'];
            $httpCode = $result['status'];
            $curlErrno = $result['errno'];

            if ($curlErrno !== 0 || $httpCode !== 200 || empty($response)) {
                $this->log(
                    "NASA POWER request failed for {$namaKecamatan}: HTTP {$httpCode}, cURL {$curlErrno}",
                    'ERROR'
                );
                continue;
            }

            $jsonData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($jsonData['properties']['parameter']['WS10M'])) {
                continue;
            }

            $ws10mData = $jsonData['properties']['parameter']['WS10M'];
            $ws2mData  = $jsonData['properties']['parameter']['WS2M'] ?? [];
            $kecRecords = [];

            foreach ($ws10mData as $rawDate => $val10m) {
                if ($val10m === null || $val10m == -999 || $val10m === '') {
                    continue;
                }

                $ws10mMs = floatval($val10m);
                if ($ws10mMs < 0.0 || $ws10mMs > 50.0) {
                    continue;
                }

                // Convert m/s to km/h for UI consistency (1 m/s = 3.6 km/h)
                $speedKmh = round($ws10mMs * 3.6, 2);

                $rawDateStr = (string)$rawDate;
                if (strlen($rawDateStr) === 8) {
                    $formattedDate = substr($rawDateStr, 0, 4) . '-' . substr($rawDateStr, 4, 2) . '-' . substr($rawDateStr, 6, 2);
                } else {
                    $dt = DateTime::createFromFormat('Ymd', $rawDateStr);
                    $formattedDate = $dt ? $dt->format('Y-m-d') : $rawDateStr;
                }

                $rec = [
                    'tanggal' => $formattedDate,
                    'lokasi' => $namaKecamatan,
                    'kode_wilayah' => '35.09',
                    'kecepatan_angin' => $speedKmh,
                    'kecepatan_max' => round($speedKmh * 1.3, 2),
                    'satuan' => 'km/h',
                    'sumber_data' => 'NASA POWER (WS10M/WS2M)',
                    'keterangan' => sprintf('Kecepatan angin 10m: %.2f m/s (%.2f km/h) dari NASA POWER API', $ws10mMs, $speedKmh)
                ];
                $allData[] = $rec;
                $kecRecords[] = $rec;
            }

            if (!empty($kecRecords)) {
                $cacheKey = "nasa_wind_" . preg_replace('/[^a-z0-9_]/i', '', strtolower($namaKecamatan)) . "_{$startDate}_{$endDate}.json";
                $cachePath = $cacheDir . '/' . $cacheKey;
                @file_put_contents($cachePath, json_encode($kecRecords), LOCK_EX);
            }
        }

        return $allData;
    }

    /**
     * Fetch NASA point requests in bounded batches and retry transient HTTP
     * denials. This is server-to-server traffic; browser CORS is not involved.
     */
    private function fetchNasaRequests(array $requests): array {
        $pending = $requests;
        $results = [];
        $sslVerify = getenv('CURL_SSL_VERIFY') !== 'false';

        for ($attempt = 1; $attempt <= self::NASA_MAX_RETRIES && !empty($pending); $attempt++) {
            $retry = [];

            foreach (array_chunk($pending, self::NASA_MAX_CONCURRENCY, true) as $batch) {
                $multi = curl_multi_init();
                $handles = [];

                foreach ($batch as $name => $request) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $request['url'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_TIMEOUT => 45,
                        CURLOPT_CONNECTTIMEOUT => 10,
                        CURLOPT_USERAGENT => 'JAGAPADI-WindFetcher/1.1 (+https://jagapadi.local)',
                        CURLOPT_HTTPHEADER => ['Accept: application/json'],
                        CURLOPT_SSL_VERIFYPEER => $sslVerify,
                        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
                    ]);
                    curl_multi_add_handle($multi, $ch);
                    $handles[$name] = $ch;
                }

                do {
                    $status = curl_multi_exec($multi, $running);
                    if ($running > 0) {
                        $selected = curl_multi_select($multi, 1.0);
                        if ($selected === -1) {
                            usleep(100000);
                        }
                    }
                } while ($running > 0 && $status === CURLM_OK);

                foreach ($handles as $name => $ch) {
                    $result = [
                        'body' => (string) curl_multi_getcontent($ch),
                        'status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
                        'errno' => curl_errno($ch),
                    ];
                    $results[$name] = $result;

                    if ($result['errno'] !== 0 || in_array($result['status'], [403, 408, 429, 500, 502, 503, 504], true)) {
                        $retry[$name] = $requests[$name];
                    }

                    curl_multi_remove_handle($multi, $ch);
                    curl_close($ch);
                }
                curl_multi_close($multi);

                // Avoid tripping the provider's burst protection between batches.
                if (count($pending) > self::NASA_MAX_CONCURRENCY) {
                    usleep(250000);
                }
            }

            $pending = $retry;
            if (!empty($pending) && $attempt < self::NASA_MAX_RETRIES) {
                $this->log('Retrying ' . count($pending) . " NASA requests after transient denial (attempt {$attempt})", 'WARNING');
                usleep((int) (500000 * (2 ** ($attempt - 1))));
            }
        }

        return $results;
    }

    private function assertValidPeriod(int $year, int $month): void {
        if ($month < 1 || $month > 12 || $year < 2000 || $year > (int) date('Y')) {
            throw new InvalidArgumentException('Periode data angin tidak valid');
        }

        if ($year === (int) date('Y') && $month > (int) date('n')) {
            throw new InvalidArgumentException('Data angin untuk bulan masa depan belum tersedia');
        }
    }
}
