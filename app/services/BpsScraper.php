<?php
/**
 * BPS Data Scraper (Refactored)
 * Service facade untuk mengambil data pertanian dari BPS Jawa Timur
 * 
 * Orchestrates:
 * - BpsSimulationService: Generasi data simulasi
 * - BpsApiClient: Integrasi WebAPI BPS Resmi
 * - BpsDataService: Validasi dan penyimpanan data
 * 
 * @version 2.0.0
 * @author JAGAPADI System
 */

class BpsScraper {
    
    // Services
    private $simulationService;
    private $apiClient;
    private $dataService;
    
    private $debug = false;
    private $logFile;
    
    public function __construct() {
        // Load services
        require_once ROOT_PATH . '/app/services/BpsSimulationService.php';
        require_once ROOT_PATH . '/app/services/BpsApiClient.php';
        require_once ROOT_PATH . '/app/services/BpsDataService.php';
        
        $this->simulationService = new BpsSimulationService();
        $this->apiClient = new BpsApiClient();
        $this->dataService = new BpsDataService();
        
        $this->logFile = ROOT_PATH . '/logs/bps_scraper.log';
    }
    
    /**
     * Run the scraper with given options
     * 
     * @param array $options
     * @return array Result
     */
    public function run($options = []) {
        $startTime = microtime(true);
        
        $tahun = $options['tahun'] ?? date('Y');
        $kabupaten = $options['kabupaten'] ?? null;
        $source = $options['source'] ?? 'simulasi';
        $skenario = $options['skenario'] ?? 'baseline';
        $forceRefresh = $options['force_refresh'] ?? false;
        
        $this->log("Starting BPS scraper for year {$tahun}. Source: {$source}, Kabupaten: " . ($kabupaten ?? 'all'));
        
        $records = [];
        $sourceTypeUsed = $source;
        $message = "";
        
        try {
            // 1. Fetch/Generate Data
            if ($source === 'resmi_webapi') {
                if ($kabupaten) {
                    $records = $this->apiClient->fetchAgriculturalData($tahun, $this->getKodeWilayah($kabupaten));
                } else {
                    $records = $this->apiClient->fetchAgriculturalData($tahun);
                }
            }

            if ($source === 'simulasi') {
                $records = $kabupaten
                    ? [$this->simulationService->generateData($tahun, $kabupaten, $skenario)]
                    : $this->simulationService->generateAllKabupaten($tahun, $skenario);
            }

            if (empty($records)) {
                throw new RuntimeException(
                    'Sumber yang dipilih tidak mengembalikan data. Tidak ada fallback otomatis ke simulasi.'
                );
            }
            
            // 2. Process Data (Validate & Reference)
            $processResult = $this->dataService->processRecords($records, [
                'force_refresh' => $forceRefresh
            ]);
            
            // 3. Prepare Result
            $result = array_merge($processResult, [
                'source' => $sourceTypeUsed,
                'execution_time' => round(microtime(true) - $startTime, 2)
            ]);
            
            if (empty($result['message'])) {
                $result['message'] = sprintf(
                    "Berhasil memproses %d data, %d gagal, %d dilewati. Sumber: %s",
                    $result['records_success'],
                    $result['records_failed'],
                    $result['records_skipped'],
                    ucfirst($sourceTypeUsed)
                );
            }
            
            if (!empty($message)) {
                $result['message'] .= " (" . $message . ")";
            }
            
            $this->log("Scraping completed. " . $result['message']);
            
            return $result;
            
        } catch (Throwable $e) {
            $this->log("Scraper Error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage(),
                'error' => $e->getMessage(),
                'source' => $sourceTypeUsed,
                'records_success' => 0,
                'records_failed' => 0,
                'records_skipped' => 0,
                'errors' => [$e->getMessage()],
                'execution_time' => round(microtime(true) - $startTime, 2)
            ];
        }
    }
    
    /**
     * Get Kabupaten codes
     */
    public function getKabupatenList() {
        return $this->simulationService->getKabupatenList();
    }
    
    /**
     * Get available scenarios
     */
    public function getAvailableScenarios() {
        return $this->simulationService->getAvailableScenarios();
    }
    
    /**
     * Set debug mode
     */
    public function setDebug($enabled) {
        $this->debug = $enabled;
        if ($this->apiClient) $this->apiClient->setDebug($enabled);
    }
    
    /**
     * Helper to get kode wilayah by name
     */
    private function getKodeWilayah($name) {
        $codes = $this->getKabupatenList();
        return $codes[$name] ?? null;
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'INFO') {
        $logEntry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        if ($this->debug) {
            echo $logEntry;
        }
    }
}
