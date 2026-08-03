<?php
/**
 * Dashboard Charts API Controller
 * API endpoints untuk data grafik dashboard
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/services/DashboardDataAggregator.php';

class DashboardChartsApiController extends BaseApiController {
    private $aggregator;
    
    public function __construct() {
        $this->aggregator = new DashboardDataAggregator();
    }
    
    /**
     * Get rainfall time-series data
     * GET /api/dashboard/charts/rainfall
     */
    public function rainfall() {
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y'),
                'month' => $_GET['month'] ?? null
            ];
            
            $data = $this->aggregator->getRainfallSummary($filters);
            
            // Format for Chart.js
            $chartData = $this->formatTimeSeriesData($data['monthly'], 'bulan', 'avg_rainfall');
            
            $this->jsonResponse([
                'success' => true,
                'data' => $chartData,
                'statistics' => $data['statistics'],
                'year' => $data['year'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data curah hujan: ' . $e->getMessage());
        }
    }
    
    /**
     * Get wind speed time-series data
     * GET /api/dashboard/charts/wind
     */
    public function wind() {
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y'),
                'month' => $_GET['month'] ?? null
            ];
            
            $data = $this->aggregator->getWindSummary($filters);
            
            // Format for Chart.js
            $chartData = $this->formatTimeSeriesData($data['monthly'], 'bulan', 'avg_speed');
            
            $this->jsonResponse([
                'success' => true,
                'data' => $chartData,
                'statistics' => $data['statistics'],
                'year' => $data['year'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data kecepatan angin: ' . $e->getMessage());
        }
    }
    
    /**
     * Get weather combined data (rainfall + wind)
     * GET /api/dashboard/charts/weather
     */
    public function weather() {
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y')
            ];
            
            $data = $this->aggregator->getWeatherSummary($filters);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data cuaca: ' . $e->getMessage());
        }
    }
    
    /**
     * Get price trend data
     * GET /api/dashboard/charts/prices
     */
    public function prices() {
        try {
            $months = $_GET['months'] ?? 6;
            
            $data = $this->aggregator->getPriceSummary(['months' => $months]);
            
            // Format trend data for multiple line chart
            $chartData = $this->formatPriceTrendData($data['trend']);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'chart' => $chartData,
                    'latest' => $data['latest'],
                    'comparison' => $data['comparison']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data harga: ' . $e->getMessage());
        }
    }
    
    /**
     * Get production/BPS data
     * GET /api/dashboard/charts/production
     */
    public function production() {
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y')
            ];
            
            $data = $this->aggregator->getProductionSummary($filters);
            
            // Format for charts
            $trendChart = $this->formatProductionTrendData($data['trend']);
            $topProducersChart = $this->formatBarChartData(
                $data['topProducers'], 
                'kabupaten', 
                'produksi_gabah'
            );
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'statistics' => $data['statistics'],
                    'trendChart' => $trendChart,
                    'topProducersChart' => $topProducersChart
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data produksi: ' . $e->getMessage());
        }
    }
    
    /**
     * Get hama/pest statistics data
     * GET /api/dashboard/charts/hama
     */
    public function hama() {
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y')
            ];
            
            $data = $this->aggregator->getHamaSummary($filters);
            
            // Format distribution for line chart
            $distributionChart = $this->formatTimeSeriesData(
                $data['distribution'], 
                'bulan', 
                'total_laporan'
            );
            
            // Format topOPT for bar chart
            $topOPTChart = $this->formatBarChartData(
                $data['topOPT'], 
                'nama_opt', 
                'total_laporan'
            );
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'statistics' => $data['statistics'],
                    'distributionChart' => $distributionChart,
                    'topOPTChart' => $topOPTChart,
                    'byKecamatan' => $data['byKecamatan']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data hama: ' . $e->getMessage());
        }
    }
    
    /**
     * Get irrigation data for charts
     * GET /api/dashboard/charts/irrigation
     */
    public function irrigation() {
        try {
            $data = $this->aggregator->getIrrigationSummary();
            
            // Format trend for line chart
            $trendChart = $this->formatIrrigationTrendData($data['trend']);
            
            // Format by area for bar chart
            $byAreaChart = $this->formatBarChartData(
                $data['byArea'],
                'daerah_irigasi',
                'avg_debit'
            );
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'statistics' => $data['statistics'],
                    'trendChart' => $trendChart,
                    'byAreaChart' => $byAreaChart
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data irigasi: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all dashboard summary data
     * GET /api/dashboard/charts/summary
     */
    public function summary() {
        try {
            $year = $_GET['year'] ?? date('Y');
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'hama' => $this->aggregator->getHamaStats($year),
                    'weather' => [
                        'rainfall' => $this->aggregator->getRainfallSummary(['year' => $year])['statistics'],
                        'wind' => $this->aggregator->getWindSummary(['year' => $year])['statistics']
                    ],
                    'prices' => $this->aggregator->getLatestPrices(),
                    'production' => $this->aggregator->getProductionStats($year),
                    'irrigation' => $this->aggregator->getIrrigationStats()
                ],
                'year' => $year,
                'availableYears' => $this->aggregator->getAvailableYears(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat summary: ' . $e->getMessage());
        }
    }
    
    /**
     * Export data to CSV
     * GET /api/dashboard/charts/export
     */
    public function export() {
        try {
            $type = $_GET['type'] ?? 'hama';
            $format = $_GET['format'] ?? 'csv';
            $year = $_GET['year'] ?? date('Y');
            
            $data = [];
            $filename = '';
            
            switch ($type) {
                case 'rainfall':
                    $result = $this->aggregator->getRainfallSummary(['year' => $year]);
                    $data = $result['monthly'];
                    $filename = "curah_hujan_{$year}";
                    break;
                    
                case 'wind':
                    $result = $this->aggregator->getWindSummary(['year' => $year]);
                    $data = $result['monthly'];
                    $filename = "kecepatan_angin_{$year}";
                    break;
                    
                case 'prices':
                    $result = $this->aggregator->getPriceTrend(12);
                    $data = $result;
                    $filename = "harga_komoditas";
                    break;
                    
                case 'production':
                    $result = $this->aggregator->getTopProducers($year, 50);
                    $data = $result;
                    $filename = "produksi_bps_{$year}";
                    break;
                    
                case 'hama':
                default:
                    $result = $this->aggregator->getHamaByKecamatan($year);
                    $data = $result;
                    $filename = "sebaran_hama_{$year}";
                    break;
            }
            
            if (empty($data)) {
                $this->errorResponse('Tidak ada data untuk diekspor', 404);
                return;
            }
            
            if ($format === 'csv') {
                $csv = $this->aggregator->exportToCSV($data, $filename);
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
                echo $csv;
                exit;
            }
            
            // JSON format fallback
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'filename' => $filename
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Gagal mengekspor data: ' . $e->getMessage());
        }
    }
    
    // =========================================
    // HELPER METHODS FOR CHART FORMATTING
    // =========================================
    
    /**
     * Format time series data for Chart.js
     */
    private function formatTimeSeriesData($data, $labelKey, $valueKey) {
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        
        // Initialize all months with 0
        $labels = $monthNames;
        $values = array_fill(0, 12, 0);
        
        foreach ($data as $item) {
            $month = (int)$item[$labelKey];
            if ($month >= 1 && $month <= 12) {
                $values[$month - 1] = (float)($item[$valueKey] ?? 0);
            }
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values
                ]
            ]
        ];
    }
    
    /**
     * Format price trend data for multiple line chart
     */
    private function formatPriceTrendData($data) {
        $commodities = [];
        $periods = [];
        
        foreach ($data as $item) {
            $commodity = $item['komoditas'];
            $period = $item['period'];
            
            if (!isset($commodities[$commodity])) {
                $commodities[$commodity] = [];
            }
            
            $commodities[$commodity][$period] = (float)$item['avg_price'];
            
            if (!in_array($period, $periods)) {
                $periods[] = $period;
            }
        }
        
        sort($periods);
        
        $datasets = [];
        $colors = ['#dc3545', '#198754', '#0d6efd', '#ffc107', '#6f42c1'];
        $colorIndex = 0;
        
        foreach ($commodities as $name => $values) {
            $dataPoints = [];
            foreach ($periods as $period) {
                $dataPoints[] = $values[$period] ?? null;
            }
            
            $datasets[] = [
                'label' => $name,
                'data' => $dataPoints,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'fill' => false
            ];
            
            $colorIndex++;
        }
        
        return [
            'labels' => $periods,
            'datasets' => $datasets
        ];
    }
    
    /**
     * Format production trend data for line chart
     */
    private function formatProductionTrendData($data) {
        $labels = [];
        $produksiGabah = [];
        $produksiBeras = [];
        $luasPanen = [];
        
        foreach ($data as $item) {
            $labels[] = (string)$item['tahun'];
            $produksiGabah[] = (float)($item['total_produksi_gabah'] ?? 0);
            $produksiBeras[] = (float)($item['total_produksi_beras'] ?? 0);
            $luasPanen[] = (float)($item['total_luas_panen'] ?? 0);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Produksi Gabah (ton)',
                    'data' => $produksiGabah,
                    'borderColor' => '#198754',
                    'fill' => false
                ],
                [
                    'label' => 'Produksi Beras (ton)',
                    'data' => $produksiBeras,
                    'borderColor' => '#0d6efd',
                    'fill' => false
                ]
            ]
        ];
    }
    
    /**
     * Format irrigation trend data for line chart
     */
    private function formatIrrigationTrendData($data) {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = date('d M', strtotime($item['tanggal']));
            $values[] = (float)($item['avg_debit'] ?? 0);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Rata-rata Debit (m³/s)',
                    'data' => $values,
                    'borderColor' => '#0d6efd',
                    'fill' => true,
                    'backgroundColor' => 'rgba(13, 110, 253, 0.1)'
                ]
            ]
        ];
    }
    
    /**
     * Format data for bar chart
     */
    private function formatBarChartData($data, $labelKey, $valueKey) {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = $item[$labelKey] ?? 'Unknown';
            $values[] = (float)($item[$valueKey] ?? 0);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values
                ]
            ]
        ];
    }
}
