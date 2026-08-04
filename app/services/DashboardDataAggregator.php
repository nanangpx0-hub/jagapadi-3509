<?php
/**
 * Dashboard Data Aggregator Service
 * Service untuk agregasi data dari berbagai model untuk Dashboard
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class DashboardDataAggregator {
    private $db;
    private $cacheDir;
    private $cacheTTL = [
        'weather' => 1800,      // 30 minutes
        'prices' => 3600,       // 1 hour
        'production' => 86400,  // 24 hours
        'irrigation' => 1800,   // 30 minutes
        'hama' => 1800,         // 30 minutes
        'lainnya' => 1800       // 30 minutes
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cacheDir = ROOT_PATH . '/storage/cache/dashboard/';
        
        // Create cache directory if not exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    // =========================================
    // WEATHER DATA (Curah Hujan & Kecepatan Angin)
    // =========================================
    
    /**
     * Get weather summary combining rainfall and wind data
     * 
     * @param array $filters Optional filters (year, month, kecamatan)
     * @return array
     */
    public function getWeatherSummary($filters = []) {
        $cacheKey = 'weather_summary_' . md5(json_encode($filters));
        $cached = $this->getCache($cacheKey, 'weather');
        if ($cached !== null) {
            return $cached;
        }
        
        $result = [
            'rainfall' => $this->getRainfallSummary($filters),
            'wind' => $this->getWindSummary($filters),
            'alerts' => $this->getWeatherAlerts($filters),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        $this->setCache($cacheKey, $result, 'weather');
        return $result;
    }
    
    /**
     * Get rainfall summary statistics
     */
    public function getRainfallSummary($filters = []) {
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? null;
        
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    AVG(curah_hujan) as avg_rainfall,
                    MAX(curah_hujan) as max_rainfall,
                    MIN(curah_hujan) as min_rainfall,
                    SUM(curah_hujan) as total_rainfall
                FROM curah_hujan 
                WHERE YEAR(tanggal) = :year";
        
        $params = [':year' => $year];
        
        if ($month) {
            $sql .= " AND MONTH(tanggal) = :month";
            $params[':month'] = $month;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get monthly breakdown
        $sql = "SELECT 
                    MONTH(tanggal) as bulan,
                    AVG(curah_hujan) as avg_rainfall,
                    SUM(curah_hujan) as total_rainfall,
                    COUNT(*) as data_count
                FROM curah_hujan 
                WHERE YEAR(tanggal) = :year
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'statistics' => $stats,
            'monthly' => $monthly,
            'year' => $year
        ];
    }
    
    /**
     * Get wind speed summary statistics
     */
    public function getWindSummary($filters = []) {
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? null;
        
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    AVG(kecepatan_angin) as avg_speed,
                    MAX(kecepatan_angin) as max_speed,
                    MIN(kecepatan_angin) as min_speed
                FROM kecepatan_angin 
                WHERE YEAR(tanggal) = :year";
        
        $params = [':year' => $year];
        
        if ($month) {
            $sql .= " AND MONTH(tanggal) = :month";
            $params[':month'] = $month;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get monthly breakdown
        $sql = "SELECT 
                    MONTH(tanggal) as bulan,
                    AVG(kecepatan_angin) as avg_speed,
                    MAX(kecepatan_angin) as max_speed,
                    COUNT(*) as data_count
                FROM kecepatan_angin 
                WHERE YEAR(tanggal) = :year
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'statistics' => $stats,
            'monthly' => $monthly,
            'year' => $year
        ];
    }
    
    /**
     * Get weather alerts (high rainfall, strong winds)
     */
    public function getWeatherAlerts($filters = []) {
        $days = $filters['days'] ?? 7;
        $alerts = [];
        
        // High rainfall alerts (> 50mm)
        $sql = "SELECT tanggal, curah_hujan, kecamatan, 'high_rainfall' as alert_type
                FROM curah_hujan 
                WHERE curah_hujan > 50 
                AND tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY tanggal DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':days' => $days]);
        $rainfallAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // High wind speed alerts (> 30 km/h)
        $sql = "SELECT tanggal, kecepatan_angin, kecamatan, 'high_wind' as alert_type
                FROM kecepatan_angin 
                WHERE kecepatan_angin > 30 
                AND tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY tanggal DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':days' => $days]);
        $windAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_merge($rainfallAlerts, $windAlerts);
    }
    
    // =========================================
    // PRICE DATA (Harga Gabah & Beras)
    // =========================================
    
    /**
     * Get price summary for commodities
     */
    public function getPriceSummary($filters = []) {
        $cacheKey = 'price_summary_' . md5(json_encode($filters));
        $cached = $this->getCache($cacheKey, 'prices');
        if ($cached !== null) {
            return $cached;
        }
        
        $months = $filters['months'] ?? 6;
        
        $result = [
            'latest' => $this->getLatestPrices(),
            'trend' => $this->getPriceTrend($months),
            'comparison' => $this->getPriceComparison(),
            'alerts' => $this->getPriceAlerts(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        $this->setCache($cacheKey, $result, 'prices');
        return $result;
    }
    
    /**
     * Get latest prices for each commodity
     */
    public function getLatestPrices() {
        $sql = "SELECT 
                    jenis_komoditas as komoditas,
                    harga,
                    tanggal,
                    sumber_data
                FROM harga_komoditas h1
                WHERE tanggal = (
                    SELECT MAX(tanggal) 
                    FROM harga_komoditas h2 
                    WHERE h2.jenis_komoditas = h1.jenis_komoditas
                )
                ORDER BY jenis_komoditas";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get price trend over time
     */
    public function getPriceTrend($months = 6) {
        $sql = "SELECT 
                    jenis_komoditas as komoditas,
                    DATE_FORMAT(tanggal, '%Y-%m') as period,
                    AVG(harga) as avg_price,
                    MIN(harga) as min_price,
                    MAX(harga) as max_price
                FROM harga_komoditas 
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                GROUP BY jenis_komoditas, DATE_FORMAT(tanggal, '%Y-%m')
                ORDER BY jenis_komoditas, period";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':months' => $months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get price comparison between commodities
     */
    public function getPriceComparison() {
        $sql = "SELECT 
                    jenis_komoditas as komoditas,
                    AVG(harga) as avg_price,
                    MIN(harga) as min_price,
                    MAX(harga) as max_price,
                    STDDEV(harga) as price_volatility
                FROM harga_komoditas 
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                GROUP BY jenis_komoditas";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get price alerts (significant changes)
     */
    public function getPriceAlerts() {
        $sql = "SELECT 
                    jenis_komoditas as komoditas,
                    tanggal,
                    persentase as persentase_perubahan,
                    tipe_alert as deskripsi,
                    is_read as dibaca
                FROM harga_alerts 
                WHERE is_read = 0
                ORDER BY tanggal DESC
                LIMIT 10";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // =========================================
    // PRODUCTION DATA (BPS)
    // =========================================
    
    /**
     * Get agricultural production summary
     */
    public function getProductionSummary($filters = []) {
        $cacheKey = 'production_summary_' . md5(json_encode($filters));
        $cached = $this->getCache($cacheKey, 'production');
        if ($cached !== null) {
            return $cached;
        }
        
        $year = $filters['year'] ?? date('Y');
        
        $result = [
            'statistics' => $this->getProductionStats($year),
            'trend' => $this->getProductionTrend(),
            'topProducers' => $this->getTopProducers($year),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        $this->setCache($cacheKey, $result, 'production');
        return $result;
    }
    
    /**
     * Get production statistics for a year
     */
    public function getProductionStats($year) {
        $sql = "SELECT 
                    SUM(luas_panen) as total_luas_panen,
                    SUM(produksi_gabah) as total_produksi_gabah,
                    SUM(produksi_beras) as total_produksi_beras,
                    AVG(produktivitas) as avg_produktivitas,
                    COUNT(DISTINCT kabupaten_kota) as total_kabupaten
                FROM data_pertanian_bps 
                WHERE tahun = :year";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get production trend over years
     */
    public function getProductionTrend($years = 5) {
        $sql = "SELECT 
                    tahun,
                    SUM(luas_panen) as total_luas_panen,
                    SUM(produksi_gabah) as total_produksi_gabah,
                    SUM(produksi_beras) as total_produksi_beras,
                    AVG(produktivitas) as avg_produktivitas
                FROM data_pertanian_bps 
                WHERE tahun >= YEAR(CURDATE()) - :years
                GROUP BY tahun
                ORDER BY tahun";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':years' => $years]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top producing regions
     */
    public function getTopProducers($year, $limit = 10) {
        $sql = "SELECT 
                    kabupaten_kota as kabupaten,
                    luas_panen,
                    produksi_gabah,
                    produksi_beras,
                    produktivitas
                FROM data_pertanian_bps 
                WHERE tahun = :year
                ORDER BY produksi_gabah DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // =========================================
    // IRRIGATION DATA
    // =========================================
    
    /**
     * Get irrigation summary
     */
    public function getIrrigationSummary($filters = []) {
        $cacheKey = 'irrigation_summary_' . md5(json_encode($filters));
        $cached = $this->getCache($cacheKey, 'irrigation');
        if ($cached !== null) {
            return $cached;
        }
        
        $result = [
            'statistics' => $this->getIrrigationStats(),
            'trend' => $this->getIrrigationTrend(),
            'byArea' => $this->getIrrigationByArea(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        $this->setCache($cacheKey, $result, 'irrigation');
        return $result;
    }
    
    /**
     * Get irrigation statistics
     */
    public function getIrrigationStats() {
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    AVG(debit_air) as avg_debit,
                    MAX(debit_air) as max_debit,
                    MIN(debit_air) as min_debit,
                    COUNT(DISTINCT daerah_irigasi) as total_daerah_irigasi
                FROM data_irigasi 
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get irrigation trend over days
     */
    public function getIrrigationTrend($days = 30) {
        $sql = "SELECT 
                    tanggal,
                    AVG(debit_air) as avg_debit,
                    SUM(debit_air) as total_debit
                FROM data_irigasi 
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY tanggal
                ORDER BY tanggal";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get irrigation data by area
     */
    public function getIrrigationByArea() {
        $sql = "SELECT 
                    di.daerah_irigasi,
                    di.kecamatan,
                    AVG(di.debit_air) as avg_debit,
                    MAX(di.debit_air) as max_debit,
                    MIN(di.debit_air) as min_debit,
                    COUNT(*) as total_records,
                    AVG(mk.latitude) as latitude,
                    AVG(mk.longitude) as longitude
                FROM data_irigasi di
                LEFT JOIN master_kecamatan mk ON di.kecamatan = mk.nama_kecamatan
                WHERE di.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY di.daerah_irigasi, di.kecamatan
                ORDER BY avg_debit DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // =========================================
    // PEST/HAMA DATA
    // =========================================
    
    /**
     * Get pest/hama summary
     */
    public function getHamaSummary($filters = []) {
        $cacheKey = 'hama_summary_' . md5(json_encode($filters));
        $cached = $this->getCache($cacheKey, 'hama');
        if ($cached !== null) {
            return $cached;
        }
        
        $year = $filters['year'] ?? date('Y');
        
        $result = [
            'statistics' => $this->getHamaStats($year),
            'distribution' => $this->getHamaDistribution($year),
            'topOPT' => $this->getTopOPT($year),
            'byKecamatan' => $this->getHamaByKecamatan($year),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        $this->setCache($cacheKey, $result, 'hama');
        return $result;
    }
    
    /**
     * Get hama statistics
     */
    public function getHamaStats($year) {
        $sql = "SELECT 
                    COUNT(*) as total_laporan,
                    SUM(CASE WHEN status IN ('Submitted', 'Diverifikasi') THEN 1 ELSE 0 END) as terverifikasi,
                    0 as pending,
                    SUM(CASE WHEN tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as berat,
                    SUM(CASE WHEN tingkat_keparahan = 'Sedang' THEN 1 ELSE 0 END) as sedang,
                    SUM(CASE WHEN tingkat_keparahan = 'Ringan' THEN 1 ELSE 0 END) as ringan,
                    SUM(luas_serangan) as total_luas_serangan
                FROM laporan_hama 
                WHERE YEAR(tanggal) = :year";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly hama distribution
     */
    public function getHamaDistribution($year) {
        $sql = "SELECT 
                    MONTH(tanggal) as bulan,
                    COUNT(*) as total_laporan,
                    SUM(luas_serangan) as total_luas
                FROM laporan_hama 
                WHERE YEAR(tanggal) = :year
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top OPT
     */
    public function getTopOPT($year, $limit = 10) {
        $sql = "SELECT 
                    mo.nama_opt,
                    mo.jenis,
                    COUNT(lh.id) as total_laporan,
                    SUM(lh.luas_serangan) as total_luas
                FROM laporan_hama lh
                JOIN master_opt mo ON lh.master_opt_id = mo.id
                WHERE YEAR(lh.tanggal) = :year
                AND lh.status IN ('Submitted', 'Diverifikasi')
                GROUP BY lh.master_opt_id, mo.nama_opt, mo.jenis
                ORDER BY total_laporan DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get hama by kecamatan for map
     */
    public function getHamaByKecamatan($year) {
        $sql = "SELECT 
                    mk.id as kecamatan_id,
                    mk.nama_kecamatan,
                    COUNT(lh.id) as total_laporan,
                    SUM(lh.luas_serangan) as total_luas,
                    SUM(CASE WHEN lh.tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as berat,
                    AVG(lh.latitude) as lat,
                    AVG(lh.longitude) as lng
                FROM laporan_hama lh
                LEFT JOIN master_desa md ON lh.desa_id = md.id
                LEFT JOIN master_kecamatan mk ON md.kecamatan_id = mk.id
                WHERE YEAR(lh.tanggal) = :year
                AND lh.status IN ('Submitted', 'Diverifikasi')
                GROUP BY mk.id, mk.nama_kecamatan
                ORDER BY total_laporan DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // =========================================
    // MAP DATA
    // =========================================
    
    /**
     * Get all map layers data
     */
    public function getMapLayersData($filters = []) {
        return [
            'hama' => $this->getHamaMapData($filters),
            'irrigation' => $this->getIrrigationMapData($filters),
            'weather' => $this->getWeatherMapData($filters),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get hama map data with coordinates
     */
    public function getHamaMapData($filters = []) {
        $year = $filters['year'] ?? date('Y');
        $status = $filters['status'] ?? '';
        
        $sql = "SELECT 
                    lh.id,
                    lh.tanggal,
                    lh.lokasi,
                    lh.latitude,
                    lh.longitude,
                    lh.tingkat_keparahan,
                    lh.luas_serangan,
                    lh.populasi,
                    mo.nama_opt,
                    mo.jenis as jenis_opt
                FROM laporan_hama lh
                LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
                WHERE YEAR(lh.tanggal) = :year
                AND lh.status IN ('Submitted', 'Diverifikasi')
                AND lh.latitude IS NOT NULL
                AND lh.longitude IS NOT NULL";
        
        if ($status === 'Submitted' || $status === 'Diverifikasi') {
            $sql .= " AND lh.status = :status";
        }
        
        $sql .= " ORDER BY lh.tanggal DESC";
        
        $params = [':year' => $year];
        if ($status === 'Submitted' || $status === 'Diverifikasi') {
            $params[':status'] = $status;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get irrigation map data
     */
    public function getIrrigationMapData($filters = []) {
        // For now, return aggregated data by irrigation area
        // Can be expanded with actual coordinates later
        return $this->getIrrigationByArea();
    }
    
    /**
     * Get weather map data by location
     */
    public function getWeatherMapData($filters = []) {
        $sql = "SELECT 
                    kecamatan_id,
                    kecamatan,
                    AVG(curah_hujan) as avg_rainfall,
                    MAX(curah_hujan) as max_rainfall,
                    latitude,
                    longitude
                FROM curah_hujan 
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND latitude IS NOT NULL
                AND longitude IS NOT NULL
                GROUP BY kecamatan_id, kecamatan, latitude, longitude";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // =========================================
    // CACHE HELPERS
    // =========================================
    
    /**
     * Get cached data
     */
    private function getCache($key, $type = 'weather') {
        $file = $this->cacheDir . $key . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $ttl = $this->cacheTTL[$type] ?? 3600;
        
        if (filemtime($file) + $ttl < time()) {
            unlink($file);
            return null;
        }
        
        $data = file_get_contents($file);
        return json_decode($data, true);
    }
    
    /**
     * Set cache data
     */
    private function setCache($key, $data, $type = 'weather') {
        $file = $this->cacheDir . $key . '.json';
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Clear cache by type or all
     */
    public function clearCache($type = null) {
        $files = glob($this->cacheDir . '*.json');
        
        foreach ($files as $file) {
            if ($type === null) {
                unlink($file);
            } elseif (strpos(basename($file), $type) === 0) {
                unlink($file);
            }
        }
    }
    
    // =========================================
    // EXPORT HELPERS
    // =========================================
    
    /**
     * Export data to CSV format
     */
    public function exportToCSV($data, $filename) {
        $output = fopen('php://temp', 'r+');
        
        // Header row
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Get available years from all data sources
     */
    public function getAvailableYears() {
        $years = [];
        
        // From laporan_hama
        $sql = "SELECT DISTINCT YEAR(tanggal) as year FROM laporan_hama ORDER BY year DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $hamaYears = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'year');
        
        // From curah_hujan
        try {
            $sql = "SELECT DISTINCT YEAR(tanggal) as year FROM curah_hujan ORDER BY year DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rainfallYears = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'year');
        } catch (PDOException $e) {
            $rainfallYears = [];
        }
        
        // From data_pertanian_bps
        try {
            $sql = "SELECT DISTINCT tahun as year FROM data_pertanian_bps ORDER BY year DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $bpsYears = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'year');
        } catch (PDOException $e) {
            $bpsYears = [];
        }
        
        $allYears = array_unique(array_merge($hamaYears, $rainfallYears, $bpsYears));
        rsort($allYears);
        
        return $allYears;
    }

    // =========================================
    // LAPORAN LAINNYA DATA
    // =========================================

    /**
     * Get laporan lainnya summary for dashboard
     */
    public function getLainnyaSummary($filters = []) {
        $cacheKey = 'lainnya_summary_' . md5(json_encode($filters));
        $cached = $this->getCache($cacheKey, 'lainnya');
        if ($cached !== null) {
            return $cached;
        }

        $year = $filters['year'] ?? date('Y');

        $result = [
            'statistics' => $this->getLainnyaStats($year),
            'byJenis' => $this->getLainnyaByJenis($year),
            'trend' => $this->getLainnyaTrend($year),
            'last_updated' => date('Y-m-d H:i:s')
        ];

        $this->setCache($cacheKey, $result, 'lainnya');
        return $result;
    }

    /**
     * Get laporan lainnya statistics
     */
    public function getLainnyaStats($year) {
        $sql = "SELECT
                    COUNT(*) as total_laporan,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draf,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as diverifikasi
                FROM laporan_lainnya
                WHERE YEAR(tanggal_kejadian) = :year";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get laporan lainnya breakdown by jenis
     */
    public function getLainnyaByJenis($year, $limit = 10) {
        $sql = "SELECT
                    mjl.nama as jenis_nama,
                    mjl.kode as jenis_kode,
                    COUNT(ll.id) as total_laporan,
                    SUM(CASE WHEN ll.status = 'verified' THEN 1 ELSE 0 END) as diverifikasi
                FROM laporan_lainnya ll
                LEFT JOIN master_jenis_laporan mjl ON ll.jenis_id = mjl.id
                WHERE YEAR(ll.tanggal_kejadian) = :year
                GROUP BY mjl.id, mjl.nama, mjl.kode
                ORDER BY total_laporan DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get laporan lainnya monthly trend
     */
    public function getLainnyaTrend($year) {
        $sql = "SELECT
                    MONTH(tanggal_kejadian) as bulan,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as diverifikasi
                FROM laporan_lainnya
                WHERE YEAR(tanggal_kejadian) = :year
                GROUP BY MONTH(tanggal_kejadian)
                ORDER BY bulan";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clear lainnya cache
     */
    public function clearLainnyaCache() {
        $this->clearCache('lainnya');
    }
}
