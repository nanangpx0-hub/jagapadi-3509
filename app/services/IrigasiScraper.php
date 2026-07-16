<?php
/**
 * Irigasi Data Scraper
 * Service untuk mengambil data irigasi Kabupaten Jember
 * 
 * Menggunakan data simulasi realistis berdasarkan pola DAS di Jember
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class IrigasiScraper {
    
    private $model;
    private $debug = false;
    private $logFile;
    
    // Major Dams / Areas details
    private const DAM_DETAILS = [
        'Dam Bedadung' => ['kecamatan' => 'Rambipuji', 'luas_layanan' => 12500, 'norm_debit' => 12000],
        'Dam Talang' => ['kecamatan' => 'Jenggawah', 'luas_layanan' => 8500, 'norm_debit' => 8000],
        'Dam Curahmalang' => ['kecamatan' => 'Rambipuji', 'luas_layanan' => 4500, 'norm_debit' => 4200],
        'Dam Pondok Waluh' => ['kecamatan' => 'Kencong', 'luas_layanan' => 6200, 'norm_debit' => 5800],
        'Dam Rowo' => ['kecamatan' => 'Bangsalsari', 'luas_layanan' => 3800, 'norm_debit' => 3500],
        'Dam Congapan' => ['kecamatan' => 'Tempurejo', 'luas_layanan' => 4200, 'norm_debit' => 3900],
        'Dam Sembah' => ['kecamatan' => 'Patrang', 'luas_layanan' => 2100, 'norm_debit' => 2000],
        'Dam Kramat' => ['kecamatan' => 'Sukorambi', 'luas_layanan' => 1800, 'norm_debit' => 1700],
        'Dam Cangkring' => ['kecamatan' => 'Jenggawah', 'luas_layanan' => 3200, 'norm_debit' => 3000],
        'Dam Darjanto' => ['kecamatan' => 'Ajung', 'luas_layanan' => 2500, 'norm_debit' => 2300],
        'Dam Gladak Putih' => ['kecamatan' => 'Sumbersari', 'luas_layanan' => 1500, 'norm_debit' => 1400],
        'Dam Jubung' => ['kecamatan' => 'Sukorambi', 'luas_layanan' => 2200, 'norm_debit' => 2100],
        'Dam Tegal Gede' => ['kecamatan' => 'Sumbersari', 'luas_layanan' => 1900, 'norm_debit' => 1800],
        'Dam Ajung' => ['kecamatan' => 'Ajung', 'luas_layanan' => 2800, 'norm_debit' => 2600],
        'Dam Kertosari' => ['kecamatan' => 'Pakusari', 'luas_layanan' => 3100, 'norm_debit' => 2900],
        'Dam Pecoro' => ['kecamatan' => 'Rambipuji', 'luas_layanan' => 2400, 'norm_debit' => 2200],
        'Dam Sempolan' => ['kecamatan' => 'Silo', 'luas_layanan' => 4500, 'norm_debit' => 4300],
        'Dam Sumberjati' => ['kecamatan' => 'Silo', 'luas_layanan' => 3600, 'norm_debit' => 3400],
        'Dam Sanen' => ['kecamatan' => 'Gumukmas', 'luas_layanan' => 5200, 'norm_debit' => 4900],
        'Dam Tanggul' => ['kecamatan' => 'Tanggul', 'luas_layanan' => 6800, 'norm_debit' => 6500]
    ];
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/DataIrigasi.php';
        $this->model = new DataIrigasi();
        $this->logFile = ROOT_PATH . '/logs/irigasi_scraper.log';
    }
    
    /**
     * Run the scraper simulation
     */
    public function run($options = []) {
        $startTime = microtime(true);
        
        $tanggal = $options['tanggal'] ?? date('Y-m-d');
        $lokasi = $options['daerah_irigasi'] ?? null;
        $forceRefresh = $options['force_refresh'] ?? false;
        
        $this->log("Starting Irrigation scraper for date {$tanggal}");
        
        $result = [
            'success' => false,
            'message' => '',
            'records_processed' => 0,
            'records_updated' => 0,
            'status_summary' => ['Aman' => 0, 'Waspada' => 0, 'Kritis' => 0]
        ];
        
        try {
            // Get target locations
            $targets = $lokasi ? [$lokasi] : array_keys(self::DAM_DETAILS);
            
            // Season factor (Wet season: Nov-Mar = 1.0-1.2, Dry season: Apr-Oct = 0.4-0.8)
            $month = (int)date('m', strtotime($tanggal));
            $isWetSeason = ($month >= 11 || $month <= 3);
            
            $seasonFactor = $isWetSeason 
                ? (rand(90, 120) / 100)  // 0.9 - 1.2
                : (rand(40, 80) / 100);  // 0.4 - 0.8
            
            // Log season context
            $this->log("Seasonal context: " . ($isWetSeason ? "Musim Hujan" : "Musim Kemarau") . ", Factor: {$seasonFactor}");
            
            foreach ($targets as $damName) {
                if (!isset(self::DAM_DETAILS[$damName])) continue;
                
                $details = self::DAM_DETAILS[$damName];
                
                // Determine existing data
                $existing = $this->model->getByDateAndLocation($tanggal, $damName);
                if ($existing && !$forceRefresh) {
                    continue; // Skip if exists and not force refresh
                }
                
                // Generate debit based on norm and season
                // Add daily fluctuation ±10%
                $fluctuation = 1 + (rand(-10, 10) / 100);
                $debit = round($details['norm_debit'] * $seasonFactor * $fluctuation);
                
                // Determine status
                $ratio = $debit / $details['norm_debit'];
                if ($ratio < 0.3) $statusPintu = 'Kritis';
                elseif ($ratio < 0.6) $statusPintu = 'Waspada';
                else $statusPintu = 'Aman';
                
                $data = [
                    'tanggal' => $tanggal,
                    'daerah_irigasi' => $damName,
                    'kecamatan' => $details['kecamatan'],
                    'luas_sawah' => $details['luas_layanan'],
                    'debit_air' => $debit,
                    'status_pintu' => $statusPintu,
                    'keterangan' => "Data observasi harian. Cuaca: " . ($isWetSeason ? "Mendung/Hujan" : "Cerah")
                ];
                
                // Save
                $this->model->upsert($data);
                
                $result['records_processed']++;
                if ($existing) $result['records_updated']++;
                $result['status_summary'][$statusPintu]++;
            }
            
            $result['success'] = true;
            $result['message'] = "Berhasil memproses {$result['records_processed']} data irigasi";
            
            // Log activity
            $this->model->logActivity('scrape', 'success', $result['message'], [
                'tanggal' => $tanggal,
                'processed' => $result['records_processed'],
                'summary' => $result['status_summary']
            ]);
            
        } catch (Exception $e) {
            $result['message'] = "Error: " . $e->getMessage();
            $this->log($result['message'], 'ERROR');
            
            $this->model->logActivity('scrape', 'failed', $result['message'], ['error' => $e->getMessage()]);
        }
        
        $executionTime = round(microtime(true) - $startTime, 2);
        $this->log("Completed in {$executionTime}s");
        
        return $result;
    }
    
    /**
     * Get list of dams
     */
    public function getDamList() {
        return self::DAM_DETAILS;
    }
    
    /**
     * Log helper
     */
    private function log($message, $level = 'INFO') {
        $logEntry = sprintf(
            "[%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        if ($this->debug) echo $logEntry;
    }
}
