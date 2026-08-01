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
    private const API_BASE_URL = 'https://webapi.bps.go.id/v1';
    
    // East Java province code
    private const PROV_CODE = '35';
    
    // BPS Variable IDs for agricultural data
    // These IDs need to be verified from BPS WebAPI documentation
    private const VAR_LUAS_PANEN = '87'; // Luas Panen Padi
    private const VAR_PRODUKSI_PADI = '88'; // Produksi Padi
    private const VAR_PRODUKTIVITAS = '89'; // Produktivitas Padi
    
    private $apiKey;
    private $timeout = 30;
    private $debug = false;
    private $lastError = null;
    private $lastResponse = null;
    
    /**
     * Constructor
     * 
     * @param string|null $apiKey BPS WebAPI key (register at webapi.bps.go.id)
     */
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: (defined('BPS_API_KEY') ? BPS_API_KEY : '');
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
     * @return array Fetched data records
     * @throws Exception if API call fails
     */
    public function fetchAgriculturalData($tahun, $kabupaten = null) {
        if (!$this->isConfigured()) {
            throw new Exception('BPS API key not configured. Register at webapi.bps.go.id');
        }
        
        $results = [];
        
        try {
            // Fetch luas panen
            $luasData = $this->fetchVariable(self::VAR_LUAS_PANEN, $tahun, $kabupaten);
            
            // Fetch produksi
            $produksiData = $this->fetchVariable(self::VAR_PRODUKSI_PADI, $tahun, $kabupaten);
            
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
     * @return array
     */
    private function fetchVariable($variableId, $tahun, $kabCode = null) {
        // Build API URL
        // Format: /list/model/data/domain/{domain}/var/{var}/th/{year}
        $domain = $kabCode ?: self::PROV_CODE;
        $url = sprintf(
            '%s/list/model/data/domain/%s/var/%s/th/%s/key/%s',
            self::API_BASE_URL,
            $domain,
            $variableId,
            $tahun,
            $this->apiKey
        );
        
        $this->log("Fetching: {$url}");
        
        $response = $this->makeRequest($url);
        
        if (!isset($response['data']) || !is_array($response['data'])) {
            return [];
        }
        
        return $response['data'];
    }
    
    /**
     * Make HTTP request to BPS API
     * 
     * @param string $url
     * @return array
     * @throws Exception
     */
    private function makeRequest($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
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
            throw new Exception("cURL Error: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("BPS API returned HTTP {$httpCode}");
        }
        
        $data = json_decode($response, true);
        $this->lastResponse = $data;
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from BPS API");
        }
        
        // Check for API error response
        if (isset($data['status']) && $data['status'] !== 'OK') {
            $message = $data['message'] ?? 'Unknown API error';
            throw new Exception("BPS API Error: {$message}");
        }
        
        return $data;
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
        if ($this->debug) {
            echo sprintf("[%s] [%s] BpsApiClient: %s\n", date('Y-m-d H:i:s'), $level, $message);
        }
    }
}
