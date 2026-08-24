<?php
/**
 * BPS API Client
 * Client untuk integrasi dengan BPS WebAPI (https://webapi.bps.go.id/v1)
 * 
 * Mengambil data resmi dari BPS untuk luas panen, produksi padi, dll.
 * Jika WebAPI gagal, akan men-throw exception agar caller bisa fallback ke simulasi.
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class BpsApiClient {
    
    // BPS WebAPI base URL
    private const API_BASE_URL = BPS_API_BASE_URL;
    
    // East Java province code
    private const PROV_CODE = '35';
    
    // BPS Variable IDs for agricultural data
    // These IDs need to be verified from BPS WebAPI documentation
    private const VAR_LUAS_PANEN = '87'; // Luas Panen Padi
    private const VAR_PRODUKSI_PADI = '88'; // Produksi Padi
    private const VAR_PRODUKTIVITAS = '89'; // Produktivitas Padi
    
    // Rate limiting: delay between API requests (seconds)
    private const RATE_LIMIT_DELAY = 1.5;
    // Max retry attempts with exponential backoff
    private const MAX_RETRIES = 3;
    
    private $apiKey;
    private $timeout;
    private $debug = false;
    private $lastError = null;
    private $lastResponse = null;
    private $lastRequestTime = 0;
    private $logFile;
    
    /**
     * Constructor
     * 
     * @param string|null $apiKey BPS WebAPI key (register at webapi.bps.go.id)
     */
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: (defined('BPS_API_KEY') ? BPS_API_KEY : '');
        $this->timeout = defined('BPS_API_TIMEOUT') ? (int)BPS_API_TIMEOUT : 30;
        $this->logFile = ROOT_PATH . '/logs/bps_api.log';
    }
    
    /**
     * Set API key
     * 
     * @param string $apiKey
     * @return self
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Set request timeout
     * 
     * @param int $seconds
     * @return self
     */
    public function setTimeout($seconds) {
        $this->timeout = (int) $seconds;
        return $this;
    }
    
    /**
     * Enable debug mode
     * 
     * @param bool $enabled
     * @return self
     */
    public function setDebug($enabled) {
        $this->debug = (bool) $enabled;
        return $this;
    }
    
    /**
     * Check if API is configured
     * 
     * @return bool
     */
    public function isConfigured() {
        return !empty($this->apiKey);
    }
    
    /**
     * Get last error message
     * 
     * @return string|null
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Get last API response (for debugging)
     * 
     * @return mixed
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }
    
    /**
     * Fetch agricultural data from BPS WebAPI
     * 
     * @param int $tahun Year to fetch
     * @param string|null $kabupaten Optional specific kabupaten code
     * @param string $provCode Province code (default: East Java '35')
     * @return array Fetched data records
     * @throws Exception if API call fails
     */
    public function fetchAgriculturalData($tahun, $kabupaten = null, $provCode = self::PROV_CODE) {
        if (!$this->isConfigured()) {
            throw new Exception('BPS API key not configured. Register at webapi.bps.go.id');
        }
        
        $results = [];
        
        try {
            // Fetch luas panen
            $luasData = $this->fetchVariable(self::VAR_LUAS_PANEN, $tahun, $kabupaten, $provCode);
            
            // Fetch produksi
            $produksiData = $this->fetchVariable(self::VAR_PRODUKSI_PADI, $tahun, $kabupaten, $provCode);
            
            // Merge and map data
            $results = $this->mergeAndMapData($luasData, $produksiData, $tahun);
            
            if (empty($results)) {
                throw new Exception("No data returned from BPS WebAPI for year {$tahun}");
            }
            
            $this->log("Successfully fetched " . count($results) . " records from BPS WebAPI");
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->log("BPS API Error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
        
        return $results;
    }
    
    /**
     * Fetch specific variable from BPS
     * 
     * @param string $variableId BPS variable ID
     * @param int $tahun Year
     * @param string|null $kabCode Optional kabupaten code
     * @param string $provCode Province code (default: East Java '35')
     * @return array
     */
    private function fetchVariable($variableId, $tahun, $kabCode = null, $provCode = self::PROV_CODE) {
        // Build API URL
        // Format: /list/model/data/domain/{domain}/var/{var}/th/{year}
        $domain = $kabCode ?: $provCode;
        $url = sprintf(
            '%s/list/model/data/domain/%s/var/%s/th/%s/key/%s',
            self::API_BASE_URL,
            rawurlencode((string) $domain),
            rawurlencode((string) $variableId),
            rawurlencode((string) $tahun),
            rawurlencode((string) $this->apiKey)
        );
        
        $this->log("Fetching BPS variable {$variableId}, domain {$domain}, period {$tahun}");
        
        $response = $this->makeRequest($url);
        
        if (!isset($response['data']) || !is_array($response['data'])) {
            return [];
        }
        
        return $response['data'];
    }
    
    /**
     * Make HTTP request to BPS API with rate limiting and retry
     *
     * @param string $url
     * @return array
     * @throws Exception
     */
    private function makeRequest($url) {
        // Rate limiting: enforce minimum delay between requests
        $now = microtime(true);
        $elapsed = $now - $this->lastRequestTime;
        if ($this->lastRequestTime > 0 && $elapsed < self::RATE_LIMIT_DELAY) {
            $sleepTime = self::RATE_LIMIT_DELAY - $elapsed;
            usleep((int)($sleepTime * 1000000));
        }
        $this->lastRequestTime = microtime(true);

        $retryCount = 0;
        $lastError = null;

        while ($retryCount < self::MAX_RETRIES) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: JAGAPADI/1.0 (+https://jagapadi.jember.gov.id)'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {
                $lastError = "cURL Error: {$error} (URL: " . substr($url, 0, 120) . ")";
                $this->log($lastError, 'WARNING');
            } elseif ($httpCode === 403 || $httpCode === 429 || $httpCode >= 500) {
                // 403 di hosting sering karena API key belum dikonfigurasi, IP diblokir, atau rate-limit
                $detail = '';
                if ($httpCode === 403) {
                    $hasKey = !empty($this->apiKey) ? 'API key terkonfigurasi' : 'API key KOSONG - daftar di webapi.bps.go.id';
                    $detail = " | {$hasKey} | Cek BPS_API_KEY di .env dan whitelist IP hosting";
                }
                $lastError = "BPS API returned HTTP {$httpCode} (Server Response Error){$detail}";
                $this->log($lastError . " | URL: " . substr($url, 0, 150), 'WARNING');
            } else if ($httpCode !== 200) {
                $lastError = "BPS API returned HTTP {$httpCode}";
                $this->log($lastError . " | URL: " . substr($url, 0, 150), 'WARNING');
            } else {
                $data = json_decode($response, true);
                $this->lastResponse = $data;

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastError = "Invalid JSON response from BPS API";
                    $this->log($lastError, 'WARNING');
                } elseif (isset($data['status']) && $data['status'] !== 'OK') {
                    $message = $data['message'] ?? 'Unknown API error';
                    $lastError = "BPS API Error: {$message} (HTTP 200 but status != OK)";
                    $this->log($lastError, 'WARNING');
                } else {
                    $this->log("Successful request to BPS API (HTTP 200)");
                    return $data;
                }
            }

            $retryCount++;
            if ($retryCount < self::MAX_RETRIES) {
                $delay = pow(2, $retryCount); // 2s, 4s
                $this->log("Retry {$retryCount}/" . self::MAX_RETRIES . " in {$delay}s...");
                sleep($delay);
            }
        }

        throw new Exception($lastError ?? "Unknown error after " . self::MAX_RETRIES . " retries");
    }
    
    /**
     * Merge luas and produksi data into unified records
     * 
     * @param array $luasData
     * @param array $produksiData
     * @param int $tahun
     * @return array
     */
    private function mergeAndMapData($luasData, $produksiData, $tahun) {
        $results = [];
        
        // Index produksi by kabupaten code for merging
        $produksiByKab = [];
        foreach ($produksiData as $item) {
            $kode = $item['var'] ?? $item['turvar'] ?? null;
            if ($kode) {
                $produksiByKab[$kode] = $item;
            }
        }
        
        // Process luas data and merge with produksi
        foreach ($luasData as $item) {
            $kode = $item['var'] ?? $item['turvar'] ?? null;
            $label = $item['label'] ?? $item['turtahun'] ?? 'Unknown';
            $luasValue = floatval($item['val'] ?? 0);
            
            // Get matching produksi
            $produksiValue = 0;
            if (isset($produksiByKab[$kode])) {
                $produksiValue = floatval($produksiByKab[$kode]['val'] ?? 0);
            }
            
            // Calculate produktivitas (ku/ha = produksi ton / luas ha * 10)
            $produktivitas = $luasValue > 0 ? round(($produksiValue / $luasValue) * 10, 2) : 0;
            
            $results[] = [
                'tahun' => $tahun,
                'kabupaten_kota' => $this->normalizeKabupatenName($label),
                'kode_wilayah' => $kode,
                'luas_panen' => $luasValue,
                'produksi_gabah' => $produksiValue,
                'produktivitas' => $produktivitas,
                'sumber_data_type' => 'resmi_webapi',
                'tipe_skenario' => 'baseline',
                'keterangan' => sprintf(
                    'Data resmi dari BPS WebAPI. Diambil pada: %s',
                    date('Y-m-d H:i:s')
                )
            ];
        }
        
        return $results;
    }
    
    /**
     * Normalize kabupaten name from BPS format
     * 
     * @param string $name
     * @return string
     */
    private function normalizeKabupatenName($name) {
        // Remove "Kab. " or "Kabupaten " prefix if present, then add "Kota" if needed
        $name = preg_replace('/^(Kab\.|Kabupaten)\s+/i', '', $name);
        $name = trim($name);
        return $name;
    }
    
    /**
     * Test API connection
     * 
     * @return bool
     */
    public function testConnection() {
        if (!$this->isConfigured()) {
            $this->lastError = 'API key not configured';
            return false;
        }
        
        try {
            // Try to fetch domain list (lightweight test)
            $url = sprintf('%s/domain/prov/%s/key/%s', self::API_BASE_URL, self::PROV_CODE, $this->apiKey);
            $this->makeRequest($url);
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Log message
     * 
     * @param string $message
     * @param string $level
     */
    private function log($message, $level = 'INFO') {
        $logEntry = sprintf("[%s] [%s] BpsApiClient: %s\n", date('Y-m-d H:i:s'), $level, $message);
        
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
     * Get list of supported provinces for dropdown UI
     * 
     * @return array Array of [kode => nama] pairs
     */
    public static function getProvinsiList() {
        $listFile = ROOT_PATH . '/config/provinces.json';
        if (file_exists($listFile)) {
            $data = json_decode(file_get_contents($listFile), true);
            if (is_array($data)) return $data;
        }
        
        // Fallback: built-in list for development
        return [
            '35' => 'Jawa Timur',
            '01' => 'DKI Jakarta',
            '31' => 'DI Yogyakarta',
            '32' => 'Jawa Barat',
            '33' => 'Jawa Tengah',
            '36' => 'Banten',
            '15' => 'Jambi',
            '17' => 'Bengkulu',
            '18' => 'Lampung',
            '19' => 'Bangka Belitung',
            '21' => 'Kepulauan Riau',
            '23' => 'Kalimantan Barat',
            '25' => 'Sumatera Barat',
            '26' => 'Sulawesi Selatan',
            '28' => 'NTT'
        ];
    }
    
    /**
     * Get kabupaten list for a specific province
     * 
     * @param string $provCode Province code
     * @return array Array of [kode_kabupaten => nama_kabupaten] pairs
     */
    public static function getKabupatenForProvinsi($provCode = '35') {
        // Try API first if configured
        $apiKey = defined('BPS_API_KEY') ? BPS_API_KEY : '';
        if (!empty($apiKey)) {
            try {
                $url = sprintf(
                    '%s/domain/info/%s/key/%s',
                    self::API_BASE_URL,
                    $provCode,
                    $apiKey
                );
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_HTTPHEADER => ['Accept: application/json']
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                
                $data = json_decode($response, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    $result = [];
                    foreach ($data['data'] as $row) {
                        $result[$row['kode_bps']] = $row['nama_kabupaten'];
                    }
                    if (!empty($result)) return $result;
                }
            } catch (Exception $e) {
                // Fall through to local lookup
            }
        }
        
        // Fallback: local lookup from database
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT kode_kabupaten, nama_kabupaten FROM master_kabupaten_by_province WHERE kode_provinsi = ? ORDER BY nama_kabupaten"
        );
        $stmt->execute([$provCode]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($rows as $row) {
            $result[$row['kode_kabupaten']] = $row['nama_kabupaten'];
        }
        return $result;
    }
}
