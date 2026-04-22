<?php
/**
 * Harga Komoditas Scraper
 * Service untuk mengambil data harga gabah dan beras
 * 
 * Menggunakan data simulasi realistis berdasarkan harga resmi
 * Dapat dikembangkan untuk web scraping dari SISKAPERBAPO/BPS
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class HargaKomoditasScraper {
    
    private $model;
    private $debug = false;
    private $logFile;
    
    // Price ranges based on official sources (Rp/kg)
    private const PRICE_RANGES = [
        'gabah_kering_panen' => ['min' => 5000, 'max' => 6500],
        'gabah_kering_giling' => ['min' => 6000, 'max' => 7500],
        'beras_medium' => ['min' => 11000, 'max' => 13000],
        'beras_premium' => ['min' => 13000, 'max' => 16000]
    ];
    
    // Seasonal adjustment (higher prices during planting season)
    private const SEASONAL_MULTIPLIERS = [
        1 => 1.05, 2 => 1.08, 3 => 1.10, 4 => 1.05, // Plant season high
        5 => 1.00, 6 => 0.95, 7 => 0.92, 8 => 0.95, // Harvest low
        9 => 0.98, 10 => 1.00, 11 => 1.02, 12 => 1.03
    ];
    
    // Kecamatan Jember for location variation
    private $locations = [];
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/HargaKomoditas.php';
        $this->model = new HargaKomoditas();
        $this->logFile = ROOT_PATH . '/logs/harga_scraper.log';
        $this->loadLocations();
    }
    
    /**
     * Run the scraper
     */
    public function run($options = []) {
        $startTime = microtime(true);
        
        $year = $options['year'] ?? date('Y');
        $month = $options['month'] ?? date('m');
        $forceSimulation = $options['force_simulation'] ?? true; // Default to simulation
        
        $this->log("Starting price scraper for {$year}-{$month}");
        
        $result = [
            'success' => false,
            'message' => '',
            'source' => 'Simulasi',
            'records_success' => 0,
            'records_failed' => 0,
            'execution_time' => 0
        ];
        
        try {
            // Generate simulation data (can be replaced with web scraping)
            $data = $this->generateSimulatedData($year, $month);
            $result['source'] = 'Simulasi (Berdasarkan Data Resmi BPS/Dinas Pertanian)';
            
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
                "Berhasil mengambil %d data harga dari %s",
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
     * Generate simulated price data based on realistic ranges
     */
    private function generateSimulatedData($year, $month) {
        $data = [];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        // Get seasonal multiplier
        $seasonMultiplier = self::SEASONAL_MULTIPLIERS[$month] ?? 1.0;
        
        // Get locations
        $locations = !empty($this->locations) ? $this->locations : [
            ['nama_kecamatan' => 'Jember', 'kode_bmkg_adm4' => '35.09'],
            ['nama_kecamatan' => 'Kaliwates', 'kode_bmkg_adm4' => '35.09.29'],
            ['nama_kecamatan' => 'Sumbersari', 'kode_bmkg_adm4' => '35.09.30']
        ];
        
        // Generate daily data for key locations
        foreach ($locations as $location) {
            // Store previous prices for realistic daily changes
            $prevPrices = [];
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%d-%02d-%02d', $year, $month, $day);
                
                // Generate price for each commodity type
                foreach (self::PRICE_RANGES as $komoditas => $range) {
                    // Base price with seasonal adjustment
                    $baseMin = $range['min'] * $seasonMultiplier;
                    $baseMax = $range['max'] * $seasonMultiplier;
                    
                    // If we have previous price, make daily change smoother (max 2% change)
                    if (isset($prevPrices[$komoditas])) {
                        $prevPrice = $prevPrices[$komoditas];
                        $maxChange = $prevPrice * 0.02;
                        $change = (rand(-100, 100) / 100) * $maxChange;
                        $price = $prevPrice + $change;
                        
                        // Keep within bounds
                        $price = max($baseMin, min($baseMax, $price));
                    } else {
                        // Initial price
                        $price = rand($baseMin * 100, $baseMax * 100) / 100;
                    }
                    
                    $prevPrices[$komoditas] = $price;
                    
                    // Add location-based variation (±3%)
                    $locationVariation = 1 + (rand(-30, 30) / 1000);
                    $finalPrice = round($price * $locationVariation, 0);
                    
                    $data[] = [
                        'tanggal' => $date,
                        'jenis_komoditas' => $komoditas,
                        'harga' => $finalPrice,
                        'satuan' => 'Rp/kg',
                        'lokasi' => ($location['nama_kecamatan'] ?? 'Jember') . ', Jember',
                        'kode_wilayah' => $location['kode_bmkg_adm4'] ?? '35.09',
                        'sumber_data' => 'Simulasi',
                        'keterangan' => sprintf(
                            'Data simulasi berdasarkan rentang harga resmi BPS. Musim: %s, Faktor musim: %.2f',
                            $this->getSeasonLabel($month),
                            $seasonMultiplier
                        )
                    ];
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get season label for a month
     */
    private function getSeasonLabel($month) {
        if (in_array($month, [11, 12, 1, 2, 3, 4])) {
            return 'Musim Hujan (Tanam)';
        } elseif (in_array($month, [5, 6, 7, 8])) {
            return 'Musim Kemarau (Panen)';
        }
        return 'Peralihan';
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
                 ORDER BY nama_kecamatan
                 LIMIT 10" // Limit to major areas for price data
            );
            $stmt->execute();
            $this->locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->log("Loaded " . count($this->locations) . " locations");
            
        } catch (Exception $e) {
            $this->log("Failed to load locations: " . $e->getMessage(), 'ERROR');
            $this->locations = $this->getFallbackLocations();
        }
    }
    
    /**
     * Fallback locations
     */
    private function getFallbackLocations() {
        return [
            ['nama_kecamatan' => 'Jember', 'kode_bmkg_adm4' => '35.09'],
            ['nama_kecamatan' => 'Kaliwates', 'kode_bmkg_adm4' => '35.09.29'],
            ['nama_kecamatan' => 'Sumbersari', 'kode_bmkg_adm4' => '35.09.30'],
            ['nama_kecamatan' => 'Patrang', 'kode_bmkg_adm4' => '35.09.31'],
            ['nama_kecamatan' => 'Ambulu', 'kode_bmkg_adm4' => '35.09.05']
        ];
    }
    
    /**
     * Future: Scrape from SISKAPERBAPO
     * This is a placeholder for actual web scraping implementation
     */
    private function scrapeFromSiskaperbapo($year, $month) {
        // TODO: Implement web scraping from SISKAPERBAPO
        // URL: https://siskaperbapo.jatimprov.go.id/
        
        // For now, return empty to fall back to simulation
        return [];
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
        
        // Ensure log directory exists
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
     * Set debug mode
     */
    public function setDebug($enabled) {
        $this->debug = $enabled;
    }
}
