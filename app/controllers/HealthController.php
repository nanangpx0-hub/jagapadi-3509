<?php
/**
 * Health Check Controller
 *
 * Endpoint: GET /admin/health
 * Menampilkan status kesehatan sistem:
 *   - Koneksi database
 *   - Konfigurasi BPS API Key
 *   - Keterjangkauan NASA POWER, Open-Meteo, BMKG
 *   - Ruang disk (storage/cache, logs, public/uploads)
 *   - Driver cache aktif
 *   - Status OPcache
 *   - Aktivitas scraping terakhir per modul
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

class HealthController extends Controller {

    private $db;

    public function __construct(?Container $container = null) {
        parent::__construct($container);
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /admin/health?format=json|html
     */
    public function index() {
        $this->checkAuth();

        if (!in_array($_SESSION['role'] ?? '', ['admin'], true)) {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
            $this->redirect('dashboard');
        }

        $checks = $this->runChecks();
        $format = $_GET['format'] ?? 'html';

        if ($format === 'json') {
            $this->json([
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'checks' => $checks,
                'overall' => $this->overallStatus($checks),
            ]);
        }

        $data = [
            'title' => 'Health Check - JAGAPADI',
            'page_title' => 'Health Check Sistem',
            'checks' => $checks,
            'overall' => $this->overallStatus($checks),
        ];
        $this->view('health/index', $data);
    }

    /**
     * Jalankan semua pemeriksaan kesehatan
     */
    private function runChecks(): array {
        return [
            'database' => $this->checkDatabase(),
            'bps_api_key' => $this->checkBpsApiKey(),
            'nasa_power' => $this->checkReachable('https://power.larc.nasa.gov/api/temporal/daily/point?parameters=T2M&community=RE&longitude=113.7003&latitude=-8.1706&start=20240101&end=20240102&format=JSON', 8),
            'open_meteo' => $this->checkReachable('https://api.open-meteo.com/v1/forecast?latitude=-8.17&longitude=113.70&hourly=precipitation&forecast_days=1', 8),
            'bmkg' => $this->checkReachable('https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=35.09.01.1001', 8),
            'disk_space' => $this->checkDiskSpace(),
            'cache_driver' => $this->checkCache(),
            'opcache' => $this->checkOpcache(),
            'last_scraping' => $this->checkLastScraping(),
        ];
    }

    private function checkDatabase(): array {
        try {
            $ok = (bool)$this->db->query('SELECT 1')->fetchColumn();
            return ['status' => $ok ? 'ok' : 'fail', 'detail' => $ok ? 'Koneksi DB OK' : 'SELECT 1 gagal'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function checkBpsApiKey(): array {
        $defined = defined('BPS_API_KEY');
        $value = $defined ? BPS_API_KEY : (getenv('BPS_API_KEY') ?: '');
        return [
            'status' => $defined && $value !== '' ? 'ok' : 'warning',
            'detail' => $defined && $value !== ''
                ? 'BPS_API_KEY terkonfigurasi'
                : 'BPS_API_KEY kosong — scraper memakai data simulasi (daftar di https://webapi.bps.go.id)',
        ];
    }

    private function checkReachable(string $url, int $timeout): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'JAGAPADI-HealthCheck/1.0',
            CURLOPT_SSL_VERIFYPEER => getenv('CURL_SSL_VERIFY') !== 'false',
            CURLOPT_SSL_VERIFYHOST => getenv('CURL_SSL_VERIFY') !== 'false' ? 2 : 0,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response !== false) {
            return ['status' => 'ok', 'detail' => "HTTP {$httpCode}"];
        }
        return ['status' => 'warning', 'detail' => "Tidak terjangkau (cURL: {$error})"];
    }

    private function checkDiskSpace(): array {
        $dirs = [
            'storage/cache' => ROOT_PATH . '/storage/cache',
            'logs' => ROOT_PATH . '/logs',
            'public/uploads' => ROOT_PATH . '/public/uploads',
        ];
        $details = [];
        $worst = 'ok';
        foreach ($dirs as $label => $dir) {
            if (!is_dir($dir)) {
                $details[] = "{$label}: tidak ada";
                $worst = 'warning';
                continue;
            }
            $free = @disk_free_space($dir);
            $details[] = $free !== false
                ? "{$label}: " . round($free / (1024 * 1024)) . ' MB bebas'
                : "{$label}: tidak dapat dibaca";
        }
        return ['status' => $worst, 'detail' => implode(' | ', $details)];
    }

    private function checkCache(): array {
        if (!class_exists('CacheManager')) {
            return ['status' => 'warning', 'detail' => 'CacheManager tidak tersedia'];
        }
        $cache = CacheManager::getInstance();
        $driver = 'unknown';
        try {
            $ref = new ReflectionProperty(CacheManager::class, 'driver');
            $ref->setAccessible(true);
            $driver = (string)$ref->getValue($cache);
        } catch (\Throwable $e) {
            // abaikan — pakai fallback
        }
        return [
            'status' => $cache->isAvailable() ? 'ok' : 'warning',
            'detail' => 'Driver: ' . $driver . ($cache->isAvailable() ? ' (aktif)' : ' (tidak aktif)'),
        ];
    }

    private function checkOpcache(): array {
        if (!function_exists('opcache_get_status')) {
            return ['status' => 'warning', 'detail' => 'Ekstensi OPcache tidak dimuat'];
        }
        $status = @opcache_get_status(false);
        if (!$status || empty($status['opcache_enabled'])) {
            return ['status' => 'warning', 'detail' => 'OPcache tidak aktif'];
        }
        return [
            'status' => 'ok',
            'detail' => sprintf(
                'OPcache aktif — %d file, hit rate %.1f%%, memori terpakai %.0f%%',
                $status['opcache_statistics']['num_cached_scripts'] ?? 0,
                $status['opcache_statistics']['opcache_hit_rate'] ?? 0,
                $status['memory_usage']['used_memory'] / max(1, $status['memory_usage']['used_memory'] + $status['memory_usage']['free_memory']) * 100
            ),
        ];
    }

    private function checkLastScraping(): array {
        $tables = [
            'curah_hujan_logs' => 'Curah Hujan',
            'kecepatan_angin_logs' => 'Kecepatan Angin',
            'harga_komoditas_logs' => 'Harga Komoditas',
            'bps_scraping_logs' => 'BPS',
        ];
        $details = [];
        foreach ($tables as $table => $label) {
            try {
                $last = $this->db->query("SELECT MAX(created_at) FROM {$table}")->fetchColumn();
                $details[] = $label . ': ' . ($last ?: 'belum pernah');
            } catch (\Throwable $e) {
                $details[] = $label . ': n/a';
            }
        }
        return ['status' => 'ok', 'detail' => implode(' | ', $details)];
    }

    private function overallStatus(array $checks): string {
        $statuses = array_column($checks, 'status');
        if (in_array('fail', $statuses, true)) {
            return 'fail';
        }
        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }
        return 'ok';
    }
}
