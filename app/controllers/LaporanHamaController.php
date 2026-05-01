<?php

require_once ROOT_PATH . '/app/core/Controller.php';
require_once ROOT_PATH . '/scripts/laporan_hama_analytics.php';

class LaporanHamaController extends Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Dashboard Analisis Laporan Hama
     */
    public function analytics() {
        $this->checkAuth();
        
        $analytics = new LaporanHamaAnalytics();
        $data = $analytics->runAll();
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        require_once ROOT_PATH . '/app/views/analytics/laporan-hama.php';
    }
    
    /**
     * Export sebagai JSON
     */
    public function exportAnalytics() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $analytics = new LaporanHamaAnalytics();
        $data = $analytics->runAll();
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Export sebagai CSV
     */
    public function exportCSV() {
        $this->checkAuth();
        
        $analytics = new LaporanHamaAnalytics();
        $data = $analytics->runAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="laporan-hama-analisis-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Summary
        fputcsv($output, ['ANALISIS LAPORAN HAMA - SUMMARY']);
        fputcsv($output, ['Total Laporan', $data['overview']['summary']['total_laporan']]);
        fputcsv($output, ['Total Pelapor', $data['overview']['summary']['total_pelapor']]);
        fputcsv($output, ['Total Kecamatan', $data['overview']['summary']['total_kecamatan']]);
        fputcsv($output, ['Total Desa', $data['overview']['summary']['total_desa']]);
        fputcsv($output, ['Total OPT', $data['overview']['summary']['total_opt']]);
        fputcsv($output, ['Total Luas Serangan', $data['overview']['summary']['total_luas_serangan'] . ' Ha']);
        fputcsv($output, ['Rata-rata Luas', $data['overview']['summary']['avg_luas_serangan'] . ' Ha']);
        fputcsv($output, []);
        
        // Top OPT
        fputcsv($output, ['TOP OPT']);
        fputcsv($output, ['Nama', 'Jenis', 'Jumlah', 'Total Luas', 'Avg Luas']);
        foreach ($data['pest_categories']['top_opt'] as $opt) {
            fputcsv($output, [
                $opt['nama'],
                $opt['jenis'],
                $opt['jumlah'],
                $opt['total_luas'],
                $opt['avg_luas']
            ]);
        }
        fputcsv($output, []);
        
        // Top Kecamatan
        fputcsv($output, ['TOP KECAMATAN']);
        fputcsv($output, ['Nama', 'Jumlah', 'Total Luas']);
        foreach ($data['geographic']['top_kecamatan'] as $kc) {
            fputcsv($output, [$kc['nama'], $kc['jumlah'], $kc['total_luas']]);
        }
        fputcsv($output, []);
        
        // Top Reporters
        fputcsv($output, ['TOP PELAPOR']);
        fputcsv($output, ['Nama', 'Role', 'Jumlah', 'Total Luas']);
        foreach ($data['role_analysis']['top_reporters'] as $r) {
            fputcsv($output, [$r['nama'], $r['role'], $r['jumlah'], $r['total_luas']]);
        }
        
        fclose($output);
        exit;
    }
}