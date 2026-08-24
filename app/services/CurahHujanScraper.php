<?php
/**
 * Curah Hujan Scraper Service
 * Service untuk mengambil data curah hujan dari sumber eksternal
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class CurahHujanScraper {
    
    private $model;
    private $logFile;
    private $config;
    private $bmkgService;
    
    // Konfigurasi sumber data (ordered by priority)
    private $sources = [
        'nasa_power' => [
            'name' => 'NASA POWER API',
            'url' => 'https://power.larc.nasa.gov/api/temporal/daily/point',
            'enabled' => true,
            'priority' => 1  // Primary source for daily precipitation PRECTOTCORR
        ],
        'openmeteo' => [
            'name' => 'Open-Meteo',
            'url' => 'https://api.open-meteo.com/v1/forecast',
            'enabled' => true,
            'priority' => 2
        ],
        'bmkg_api' => [
            'name' => 'BMKG API',
            'url' => 'https://api.bmkg.go.id/publik/prakiraan-cuaca',
            'enabled' => true,
            'priority' => 3
        ],
        'simulation' => [
            'name' => 'Data Simulasi',
            'url' => null,
            'enabled' => true,
            'priority' => 99  // Last resort fallback
        ]
    ];
    
    // Kode wilayah Jember (tingkat kabupaten)
    private $kodeWilayahJember = '35.09';
    
    // Daftar kecamatan di Jember untuk sampling
    private $kecamatanJember = [
        '35.09.01' => 'Kencong',
        '35.09.02' => 'Gumukmas',
        '35.09.03' => 'Puger',
        '35.09.04' => 'Wuluhan',
        '35.09.05' => 'Ambulu',
        '35.09.06' => 'Tempurejo',
        '35.09.07' => 'Silo',
        '35.09.08' => 'Mayang',
        '35.09.09' => 'Mumbulsari',
        '35.09.10' => 'Jenggawah',
        '35.09.11' => 'Ajung',
        '35.09.12' => 'Rambipuji',
        '35.09.13' => 'Balung',
        '35.09.14' => 'Umbulsari',
        '35.09.15' => 'Semboro',
        '35.09.16' => 'Jombang',
        '35.09.17' => 'Sumberbaru',
        '35.09.18' => 'Tanggul',
        '35.09.19' => 'Bangsalsari',
        '35.09.20' => 'Panti',
        '35.09.21' => 'Sukorambi',
        '35.09.22' => 'Arjasa',
        '35.09.23' => 'Pakusari',
        '35.09.24' => 'Kalisat',
        '35.09.25' => 'Ledokombo',
        '35.09.26' => 'Sumberjambe',
        '35.09.27' => 'Sukowono',
        '35.09.28' => 'Jelbuk',
        '35.09.29' => 'Kaliwates',
        '35.09.30' => 'Sumbersari',
        '35.09.31' => 'Patrang'
    ];
    
    private $openMeteoService;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/CurahHujan.php';
        require_once ROOT_PATH . '/app/services/BMKGService.php';
        require_once ROOT_PATH . '/app/services/OpenMeteoService.php';
        
        $this->model = new CurahHujan();
        $this->bmkgService = new BMKGService();
        $this->openMeteoService = new OpenMeteoService();
        $this->logFile = ROOT_PATH . '/logs/curah_hujan_scraper.log';
        
        // Ensure tables exist
        $this->model->createTablesIfNotExist();
    }
    
    /**
     * Run scraping process
     * 
     * @param array $options
     * @return array Result summary
     */
    public function run($options = []) {
        $startTime = microtime(true);
        $this->log("=== Starting Curah Hujan Scraper ===");
        
        // Normalize month and year as integers
        $targetMonth = (int)($options['month'] ?? date('m'));
        $targetYear = (int)($options['year'] ?? date('Y'));
        $forceSimulation = $options['force_simulation'] ?? false;

        if ($targetMonth < 1 || $targetMonth > 12 || $targetYear < 2000) {
            return [
                'success' => false,
                'source' => null,
                'records_processed' => 0,
                'records_success' => 0,
                'records_failed' => 0,
                'message' => 'Periode curah hujan tidak valid',
                'execution_time' => round(microtime(true) - $startTime, 4),
                'target_month' => $targetMonth,
                'target_year' => $targetYear,
                'no_data' => true,
            ];
        }
        
        $this->log("Target: {$targetYear}-" . str_pad($targetMonth, 2, '0', STR_PAD_LEFT));
        
        $result = [
            'success' => false,
            'source' => null,
            'records_processed' => 0,
            'records_success' => 0,
            'records_failed' => 0,
            'message' => '',
            'execution_time' => 0,
            'target_month' => $targetMonth,
            'target_year' => $targetYear,
            'no_data' => false
        ];
        
        // Check if target month is in the future (no data available yet)
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        $isFutureMonth = false;
        
        if ($targetYear > $currentYear) {
            $isFutureMonth = true;
        } elseif ($targetYear == $currentYear && $targetMonth > $currentMonth) {
            $isFutureMonth = true;
        }
        
        if ($isFutureMonth) {
            $monthName = $this->getMonthName($targetMonth);
            $this->log("Month {$targetMonth}/{$targetYear} is in the future, no data available yet");
            
            $result['success'] = false;
            $result['no_data'] = true;
            $result['message'] = "Data untuk bulan {$monthName} {$targetYear} belum tersedia (bulan masa depan)";
            $result['execution_time'] = round(microtime(true) - $startTime, 4);
            
            // Log to database
            $this->model->logActivity(
                'scrape',
                'skipped',
                $result['message'],
                [
                    'processed' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'reason' => 'future_month'
                ]
            );
            
            return $result;
        }
        
        try {
            // Determine if we can use real-time APIs
            // Open-Meteo provides forecast data (up to 16 days ahead)
            // BMKG API provides forecast data (future dates)
            $currentDate = new DateTime();
            $targetDate = DateTime::createFromFormat('Y-m-d', "{$targetYear}-{$targetMonth}-01");
            $isHistoricalRequest = $targetDate < $currentDate && $targetDate->format('Y-m') !== $currentDate->format('Y-m');
            
            $requestedSource = strtolower(trim((string) ($options['source'] ?? 'nasa')));

            if ($forceSimulation) {
                $this->log("Force simulation mode enabled");
                $data = $this->generateSimulationData($targetYear, $targetMonth);
                $result['source'] = 'Simulasi';
            } else {
                $data = null;
                
                // === Priority 1: NASA POWER API (daily precipitation PRECTOTCORR) ===
                if ($requestedSource === 'nasa' || $requestedSource === 'nasa_power') {
                    $this->log("[Priority 1] Attempting NASA POWER API (PRECTOTCORR daily)");
                    try {
                        $nasaResult = $this->fetch_nasa_curah_hujan($targetYear, $targetMonth);
                        if (!empty($nasaResult)) {
                            $data = $nasaResult;
                            $result['source'] = 'NASA POWER (PRECTOTCORR)';
                            $this->log("✓ NASA POWER: fetched " . count($data) . " records");
                        } else {
                            $this->log("NASA POWER API returned empty data, trying next source");
                        }
                    } catch (Exception $e) {
                        $this->log("NASA POWER ERROR: " . $e->getMessage());
                    }
                }

                // === Priority 2: Open-Meteo (hanya jika diminta eksplisit) ===
                $isOpenMeteoRequested = $requestedSource === 'openmeteo';
                if (empty($data) && $isOpenMeteoRequested && $this->sources['openmeteo']['enabled']) {
                    $this->log("[Priority 2] Attempting Open-Meteo API (actual precipitation data)");
                        try {
                            if ($this->openMeteoService->isAvailable()) {
                                $startDate = sprintf('%04d-%02d-01', $targetYear, $targetMonth);
                                $endDate = date('Y-m-t', strtotime($startDate));
                                if ($endDate > date('Y-m-d')) {
                                    $endDate = date('Y-m-d');
                                }
                                $openMeteoData = $this->openMeteoService
                                    ->fetchAllKecamatanRange($startDate, $endDate);
                                
                                if (!empty($openMeteoData)) {
                                    $data = $openMeteoData;
                                    $result['source'] = 'Open-Meteo';
                                    $this->log("✓ Open-Meteo: fetched " . count($data) . " records");
                                } else {
                                    $this->log("Open-Meteo returned empty data, trying next source");
                                }
                            } else {
                                $this->log("Open-Meteo API health check failed, trying next source");
                            }
                        } catch (Exception $e) {
                            $this->log("Open-Meteo ERROR: " . $e->getMessage());
                        }
                }
                
                // === Priority 3: BMKG API (hanya jika diminta eksplisit) ===
                $isBmkgRequested = in_array($requestedSource, ['bmkg', 'bmkg_api'], true);
                if (empty($data) && $isBmkgRequested && $this->sources['bmkg_api']['enabled']) {
                    $this->log("[Priority 3] Attempting BMKG API (weather categories)");
                        try {
                            if ($this->bmkgService->isAvailable()) {
                                $bmkgResult = [
                                    'success' => true,
                                    'fetch_results' => ['data' => $this->fetchFromBMKG($targetYear, $targetMonth)],
                                ];
                                
                                if ($bmkgResult['success'] && isset($bmkgResult['fetch_results']['data'])) {
                                    $data = $bmkgResult['fetch_results']['data'];
                                    if (!empty($data)) {
                                        $result['source'] = 'Estimasi Kategori Cuaca BMKG';
                                        $this->log("✓ BMKG: fetched " . count($data) . " records");
                                    } else {
                                        $this->log("BMKG returned empty data, trying next source");
                                    }
                                } else {
                                    $this->log("BMKG Service failed: " . ($bmkgResult['message'] ?? 'Unknown error'));
                                }
                            } else {
                                $this->log("BMKG API health check failed, trying next source");
                            }
                        } catch (Exception $e) {
                            $this->log("BMKG ERROR: " . $e->getMessage());
                        }
                }
                
                // === Priority 99: Simulation (fallback otomatis - FIX hosting) ===
                if (empty($data) && $this->sources['simulation']['enabled']) {
                    if ($requestedSource === 'simulation') {
                        $this->log("Using explicitly requested simulation data");
                    } else {
                        $this->log("[Fallback] Semua sumber API gagal/kosong untuk {$targetYear}-{$targetMonth} — beralih otomatis ke Simulasi");
                    }
                    $data = $this->generateSimulationData($targetYear, $targetMonth);
                    $result['source'] = ($requestedSource === 'simulation') ? 'Simulasi' : 'Simulasi (fallback)';
                }
            }
            
            if (empty($data)) {
                $sourceLabel = $result['source'] ?: $requestedSource;
                // Jika simulasi disabled dan semua API gagal, baru throw tanpa fallback
                throw new Exception("Sumber {$sourceLabel} tidak mengembalikan data untuk {$targetYear}-{$targetMonth} dan simulasi dinonaktifkan");
            }
            
            $result['records_processed'] = count($data);
            
            // Validate and insert data
            $validData = $this->validateData($data);
            $insertResult = $this->model->bulkInsert($validData);
            
            $result['records_success'] = $insertResult['success'];
            $result['records_failed'] = $insertResult['failed'];
            $result['success'] = $insertResult['success'] > 0;
            $result['message'] = sprintf(
                "Berhasil memproses %d dari %d record dari %s",
                $insertResult['success'],
                count($data),
                $result['source']
            );
            
        } catch (Exception $e) {
            $result['message'] = "Error: " . $e->getMessage();
            $this->log("ERROR: " . $e->getMessage());
        }
        
        $result['execution_time'] = round(microtime(true) - $startTime, 4);
        
        // Log to database
        $this->model->logActivity(
            'scrape',
            $result['success'] ? 'success' : 'failed',
            $result['message'],
            [
                'processed' => $result['records_processed'],
                'success' => $result['records_success'],
                'failed' => $result['records_failed'],
                'execution_time' => $result['execution_time'],
                'source' => $result['source']
            ]
        );
        
        $this->log("=== Scraper Finished: {$result['message']} ===");
        
        return $result;
    }
    
    /**
     * Fetch data from BMKG API with retry logic
     * 
     * @param int $year
     * @param int $month
     * @return array
     */
    private function fetchFromBMKG($year, $month) {
        $this->log("Fetching from BMKG API...");
        
        try {
            // Sample multiple kecamatan for better coverage
            $sampleKodes = [
                '35.09.29.1001' => 'Kaliwates',   // Pusat kota
                '35.09.30.1001' => 'Sumbersari',  // Timur
                '35.09.05.1001' => 'Ambulu',      // Selatan
                '35.09.19.1001' => 'Bangsalsari'  // Utara
            ];
            
            $allData = [];
            $successCount = 0;
            $analysisDate = null;
            
            foreach ($sampleKodes as $kode => $nama) {
                $url = "https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4={$kode}";
                $this->log("Fetching data for {$nama} (kode: {$kode})");
                
                // Use retry logic with exponential backoff
                $response = $this->fetchWithRetry($url);
                
                if ($response === false) {
                    $this->log("Failed to fetch data for {$nama} after all retries");
                    continue;
                }
                
                $data = json_decode($response, true);
                
                if (empty($data) || !isset($data['data'])) {
                    $this->log("Invalid response for {$nama}");
                    continue;
                }
                
                // Capture analysis date from first successful response
                if ($analysisDate === null && isset($data['data'][0]['cuaca'][0][0]['analysis_date'])) {
                    $analysisDate = $data['data'][0]['cuaca'][0][0]['analysis_date'];
                }
                
                $parsedData = $this->parseBMKGData($data, $year, $month, $nama, $analysisDate);
                $allData = array_merge($allData, $parsedData);
                $successCount++;
            }
            
            if ($successCount === 0) {
                $this->log("All kecamatan fetch attempts failed");
                // Send failure notification
                $this->sendFailureNotification("BMKG API tidak dapat diakses untuk semua kecamatan");
                return [];
            }
            
            $this->log("Successfully fetched data from {$successCount} kecamatan");
            return $allData;
            
        } catch (Exception $e) {
            $this->log("BMKG API Error: " . $e->getMessage());
            $this->sendFailureNotification("BMKG API Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch URL with retry and exponential backoff
     * 
     * @param string $url URL to fetch
     * @param int $maxRetries Maximum number of retry attempts
     * @return string|false Response body or false on failure
     */
    private function fetchWithRetry($url, $maxRetries = 3) {
        // Exponential backoff delays in seconds: 0, 10, 30
        $delays = [0, 10, 30];
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            // Apply delay (skip for first attempt)
            $delay = $delays[$attempt] ?? 30;
            if ($attempt > 0) {
                $this->log("Retry {$attempt}/{$maxRetries} after {$delay}s delay for URL: {$url}");
                sleep($delay);
            }
            
            $response = $this->httpRequest($url);
            
            if ($response !== false) {
                return $response;
            }
            
            $this->log("Attempt " . ($attempt + 1) . "/{$maxRetries} failed for URL: {$url}");
        }
        
        $this->log("All {$maxRetries} attempts failed for URL: {$url}");
        return false;
    }
    
    /**
     * Parse BMKG API response
     * 
     * @param array $data
     * @param int $year
     * @param int $month
     * @param string $lokasi
     * @param string $analysisDate
     * @return array
     */
    private function parseBMKGData($data, $year, $month, $lokasi = 'Jember', $analysisDate = null) {
        $result = [];
        $targetYear = (int)$year;
        $targetMonth = (int)$month;
        $allDatesReceived = [];
        $matchedDates = [];
        
        $this->log("Parsing BMKG data for {$lokasi}, target: {$targetYear}-" . str_pad($targetMonth, 2, '0', STR_PAD_LEFT));
        
        // Format analysis date for keterangan
        $releaseDateText = 'Estimasi kategori cuaca BMKG; bukan pengukuran curah hujan';
        if ($analysisDate) {
            try {
                $dateObj = new DateTime($analysisDate);
                $bulan = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                ];
                $day = $dateObj->format('j');
                $monthNum = (int) $dateObj->format('n');
                $releaseDateText = "Estimasi kategori cuaca dari prakiraan BMKG tanggal {$day} {$bulan[$monthNum]}; bukan pengukuran curah hujan";
            } catch (Exception $e) {
                $releaseDateText = 'Estimasi kategori cuaca BMKG; bukan pengukuran curah hujan';
            }
        }
        
        // BMKG provides forecast, not historical data
        // We'll extract what we can and estimate rainfall from weather descriptions
        
        if (isset($data['data'][0]['cuaca'])) {
            foreach ($data['data'][0]['cuaca'] as $dayData) {
                foreach ($dayData as $hourData) {
                    $datetime = $hourData['local_datetime'] ?? null;
                    if (!$datetime) continue;
                    
                    $date = substr($datetime, 0, 10);
                    $allDatesReceived[$date] = true;
                    
                    // CRITICAL FIX: Filter by target month and year
                    $dateYear = (int)substr($date, 0, 4);
                    $dateMonth = (int)substr($date, 5, 2);
                    
                    if ($dateYear !== $targetYear || $dateMonth !== $targetMonth) {
                        continue; // Skip dates outside target month
                    }
                    
                    $matchedDates[$date] = true;
                    $weatherCode = $hourData['weather'] ?? 0;
                    $weatherDesc = $hourData['weather_desc'] ?? '';
                    
                    // Estimate rainfall based on weather code
                    $rainfall = $this->estimateRainfallFromWeather($weatherCode);
                    
                    // Build keterangan with weather description
                    $keterangan = $releaseDateText;
                    if ($weatherDesc && $rainfall > 0) {
                        $keterangan .= " ({$weatherDesc})";
                    }
                    
                    // Only add if not already exists for this date and location
                    $dateKey = $date . '_' . $lokasi;
                    if (!isset($result[$dateKey])) {
                        $result[$dateKey] = [
                            'tanggal' => $date,
                            'lokasi' => $lokasi . ', Jember',
                            'kode_wilayah' => $this->kodeWilayahJember,
                            'curah_hujan' => $rainfall,
                            'satuan' => 'mm',
                            'sumber_data' => 'Estimasi Kategori Cuaca BMKG',
                            'keterangan' => $keterangan
                        ];
                    } else {
                        // Take maximum rainfall for the day
                        if ($rainfall > $result[$dateKey]['curah_hujan']) {
                            $result[$dateKey]['curah_hujan'] = $rainfall;
                            $result[$dateKey]['keterangan'] = $keterangan;
                        }
                    }
                }
            }
        }
        
        // Enhanced logging for troubleshooting
        $receivedCount = count($allDatesReceived);
        $matchedCount = count($matchedDates);
        $this->log("BMKG data for {$lokasi}: received {$receivedCount} dates, matched {$matchedCount} for target month");
        if ($receivedCount > 0 && $matchedCount === 0) {
            $sampleDates = array_slice(array_keys($allDatesReceived), 0, 3);
            $this->log("Sample dates in response: " . implode(', ', $sampleDates) . " (expected: {$targetYear}-" . str_pad($targetMonth, 2, '0', STR_PAD_LEFT) . ")");
        }
        
        return array_values($result);
    }
    
    /**
     * Estimate rainfall from BMKG weather code
     * 
     * @param int $code
     * @return float
     */
    private function estimateRainfallFromWeather($code) {
        $rainfallEstimates = [
            0 => 0,      // Cerah
            1 => 0,      // Cerah Berawan
            2 => 0,      // Berawan
            3 => 0,      // Berawan Tebal
            4 => 0,      // Udara Kabur
            5 => 0,      // Asap
            10 => 0,     // Kabut
            45 => 0,     // Berkabut
            60 => 5.0,   // Hujan Ringan
            61 => 15.0,  // Hujan Sedang
            63 => 35.0,  // Hujan Lebat
            80 => 2.0,   // Hujan Lokal
            95 => 25.0,  // Hujan Petir
            97 => 40.0,  // Hujan Petir Lebat
        ];
        
        return $rainfallEstimates[$code] ?? 0;
    }
    
    /**
     * Generate simulation data for demo purposes
     * 
     * @param int $year
     * @param int $month
     * @return array
     */
    private function generateSimulationData($year, $month) {
        $this->log("Generating simulation data for {$year}-{$month}");
        
        $data = [];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        if ((int) $year === (int) date('Y') && (int) $month === (int) date('n')) {
            $daysInMonth = min($daysInMonth, (int) date('j'));
        }
        
        // Rainfall patterns for Jember (tropical monsoon climate)
        // Higher in Nov-Apr (wet season), lower in May-Oct (dry season)
        $wetMonths = [11, 12, 1, 2, 3, 4];
        $isWetSeason = in_array((int)$month, $wetMonths);
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            
            $seed = (int) sprintf('%u', crc32($date . '|curah-hujan'));

            // Generate deterministic synthetic rainfall for repeatable tests.
            if ($isWetSeason) {
                $hasRain = (($seed % 100) < 60);
                $rainfall = $hasRain ? round(5 + (intdiv($seed, 100) % 7600) / 100, 2) : 0;
            } else {
                $hasRain = (($seed % 100) < 15);
                $rainfall = $hasRain ? round(1 + (intdiv($seed, 100) % 1900) / 100, 2) : 0;
            }
            
            $data[] = [
                'tanggal' => $date,
                'lokasi' => 'Jember',
                'kode_wilayah' => $this->kodeWilayahJember,
                'curah_hujan' => $rainfall,
                'satuan' => 'mm',
                'sumber_data' => 'Simulasi',
                'keterangan' => 'Data simulasi internal; bukan observasi atau rilis instansi.'
            ];
        }
        
        return $data;
    }
    
    /**
     * Validate data before insert
     * 
     * @param array $data
     * @return array
     */
    private function validateData($data) {
        $valid = [];
        
        foreach ($data as $record) {
            // Check required fields
            if (empty($record['tanggal']) || !isset($record['curah_hujan'])) {
                continue;
            }
            
            // Validate date format
            $date = DateTime::createFromFormat('!Y-m-d', (string) $record['tanggal']);
            if (!$date || $date->format('Y-m-d') !== $record['tanggal'] || $record['tanggal'] > date('Y-m-d')) {
                continue;
            }
            
            // Validate rainfall range (0-500 mm is reasonable for daily rainfall)
            $rainfall = floatval($record['curah_hujan']);
            if ($rainfall < 0 || $rainfall > 500) {
                $this->log("Invalid rainfall value: {$rainfall} for date {$record['tanggal']}");
                continue;
            }
            
            $valid[] = $record;
        }
        
        return $valid;
    }
    
    /**
     * HTTP request using cURL
     * 
     * @param string $url
     * @param array $options
     * @return string|false
     */
    private function httpRequest($url, $options = []) {
        // SSL verification diaktifkan secara default; bisa dimatikan via .env untuk dev
        $sslVerify = getenv('CURL_SSL_VERIFY') !== 'false';

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: JAGAPADI/1.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->log("cURL Error: {$error}");
            return false;
        }
        
        if ($httpCode !== 200) {
            $this->log("HTTP Error: {$httpCode}");
            return false;
        }
        
        return $response;
    }
    
    /**
     * Log message to file
     * 
     * @param string $message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";

        if (str_contains((string) $this->logFile, '://')) {
            file_put_contents($this->logFile, $logMessage, FILE_APPEND);
            return;
        }

        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir) && (!mkdir($logDir, 0755, true) && !is_dir($logDir))) {
            error_log("Curah hujan scraper log directory unavailable: {$logDir}");
            return;
        }

        if (file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            error_log("Curah hujan scraper log file unavailable: {$this->logFile}");
        }
    }
    
    /**
     * Check if should run (end of month check)
     * 
     * @return bool
     */
    public function shouldRunToday() {
        $day = (int) date('d');
        $daysInMonth = (int) date('t');
        
        // Run on days 28-31 (end of month)
        return $day >= 28 && $day <= $daysInMonth;
    }
    
    /**
     * Send email notification on failure
     * 
     * @param string $message
     * @return bool
     */
    public function sendFailureNotification($message) {
        // Check if mail function is available
        if (!function_exists('mail')) {
            $this->log("Mail function not available");
            return false;
        }
        
        $to = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@jagapadi.local';
        $subject = '[JAGAPADI] Curah Hujan Scraper Failed';
        $body = "Scraper curah hujan mengalami error:\n\n{$message}\n\n";
        $body .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Server: " . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        
        $headers = "From: noreply@jagapadi.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        return @mail($to, $subject, $body, $headers);
    }
    
    /**
     * Get Indonesian month name from month number
     * 
     * @param int $month Month number (1-12)
     * @return string Month name in Indonesian
     */
    private function getMonthName($month) {
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        
        return $monthNames[(int)$month] ?? 'Bulan ' . $month;
    }

    /**
     * Fetch daily rainfall data from NASA POWER API (PRECTOTCORR)
     * Menggunakan cURL Multi (Parallel Requests) & Local Caching untuk kecepatan tinggi
     * 
     * @param int|null $year Target year
     * @param int|null $month Target month (1-12) or null for full year
     * @return array List of formatted record arrays
     */
    public function fetch_nasa_curah_hujan($year = null, $month = null) {
        $year = $year ? (int)$year : (int)date('Y');
        $month = $month !== null ? (int) $month : null;

        if (($month !== null && ($month < 1 || $month > 12)) || $year < 2000 || $year > (int) date('Y')) {
            throw new InvalidArgumentException('Periode NASA POWER tidak valid');
        }
        if ($month !== null && $year === (int) date('Y') && $month > (int) date('n')) {
            throw new InvalidArgumentException('Data curah hujan bulan masa depan belum tersedia');
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

        $this->log("Fetching from NASA POWER API for {$startDate} - {$endDate}...");

        $cacheDir = ROOT_PATH . '/storage/cache/nasa';
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

        // Fetch master_kecamatan IDs for proper mapping
        $kecamatanMapDB = [];
        try {
            $db = Database::getInstance()->getConnection();
            $stmtKec = $db->query("SELECT id, nama_kecamatan FROM master_kecamatan");
            while ($row = $stmtKec->fetch(PDO::FETCH_ASSOC)) {
                $kecamatanMapDB[strtolower(trim($row['nama_kecamatan']))] = (int)$row['id'];
            }
        } catch (Exception $e) {
            $this->log("DB Lookup Warning: " . $e->getMessage());
        }

        $allData = [];
        $uncached = [];

        // Check local file cache first
        foreach ($kecamatanCoords as $namaKecamatan => $coord) {
            $cacheKey = "nasa_" . preg_replace('/[^a-z0-9_]/i', '', strtolower($namaKecamatan)) . "_{$startDate}_{$endDate}.json";
            $cachePath = $cacheDir . '/' . $cacheKey;

            if (file_exists($cachePath) && (time() - filemtime($cachePath) < 86400 * 7)) { // 7 days TTL
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
            $this->log("Loaded all " . count($allData) . " records for {$startDate}-{$endDate} directly from local cache.");
            return $allData;
        }

        // Execute parallel HTTP requests using cURL multi for uncached kecamatan
        $mh = curl_multi_init();
        $curlHandles = [];

        foreach ($uncached as $namaKecamatan => $coord) {
            $lat = $coord['lat'];
            $lon = $coord['lon'];

            $apiUrl = "https://power.larc.nasa.gov/api/temporal/daily/point?" . http_build_query([
                'parameters' => 'PRECTOTCORR',
                'community'  => 'AG',
                'longitude'  => $lon,
                'latitude'   => $lat,
                'start'      => $startDate,
                'end'        => $endDate,
                'format'     => 'JSON'
            ]);

            // SSL verification diaktifkan secara default; bisa dimatikan via .env untuk dev
            $sslVerify = getenv('CURL_SSL_VERIFY') !== 'false';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT      => 'JAGAPADI-System/1.0 (PHP-cURL-Multi)',
                CURLOPT_SSL_VERIFYPEER => $sslVerify,
                CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            ]);

            curl_multi_add_handle($mh, $ch);
            $curlHandles[$namaKecamatan] = [
                'ch' => $ch,
                'coord' => $coord
            ];
        }

        // Execute cURL multi handles in parallel
        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.05);
            }
        } while ($running > 0 && $status === CURLM_OK);

        // Collect and parse responses
        foreach ($curlHandles as $namaKecamatan => $handleInfo) {
            $ch = $handleInfo['ch'];
            $coord = $handleInfo['coord'];
            $lat = $coord['lat'];
            $lon = $coord['lon'];
            $kecId = $kecamatanMapDB[strtolower(trim($namaKecamatan))] ?? null;

            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($curlErrno !== 0 || $httpCode !== 200 || empty($response)) {
                $this->log("NASA POWER API Error for {$namaKecamatan}: HTTP status {$httpCode}, cURL error ({$curlErrno}): {$curlError}");
                continue;
            }

            $jsonData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($jsonData['properties']['parameter']['PRECTOTCORR'])) {
                $this->log("NASA POWER API Invalid JSON format or missing PRECTOTCORR for {$namaKecamatan}");
                continue;
            }

            $rainData = $jsonData['properties']['parameter']['PRECTOTCORR'];
            $kecRecords = [];

            foreach ($rainData as $rawDate => $val) {
                if ($val === null || $val == -999 || $val === '') {
                    continue;
                }

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
                    'kecamatan' => $namaKecamatan,
                    'kecamatan_id' => $kecId,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'curah_hujan' => floatval($val),
                    'satuan' => 'mm',
                    'sumber_data' => 'NASA POWER (PRECTOTCORR)',
                    'keterangan' => 'Terkoreksi NASA POWER Daily Point API (PRECTOTCORR)'
                ];
                $allData[] = $rec;
                $kecRecords[] = $rec;
            }

            // Save to file cache for instant retrieval on next run
            if (!empty($kecRecords)) {
                $cacheKey = "nasa_" . preg_replace('/[^a-z0-9_]/i', '', strtolower($namaKecamatan)) . "_{$startDate}_{$endDate}.json";
                $cachePath = $cacheDir . '/' . $cacheKey;
                @file_put_contents($cachePath, json_encode($kecRecords), LOCK_EX);
            }
        }

        curl_multi_close($mh);
        return $allData;
    }
}
