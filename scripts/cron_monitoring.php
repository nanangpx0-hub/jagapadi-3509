<?php
/**
 * Cron Job Script untuk Monitoring Curah Hujan
 * 
 * Jalankan script ini dengan cron job:
 * 0 6 * * * php /path/to/jagapadi/scripts/cron_monitoring.php
 * 
 * @version 1.0.1
 * @author JAGAPADI System
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Define ROOT_PATH
define('ROOT_PATH', dirname(__DIR__));

// Set CLI-safe defaults for $_SERVER variables used by config
$_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? 80;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Load required files
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

// Autoload classes (same as index.php)
spl_autoload_register(function ($class) {
    $paths = [
        'app/controllers/',
        'app/models/',
        'app/core/',
        'app/helpers/',
        'app/services/',
        'app/middleware/'
    ];
    
    foreach ($paths as $path) {
        $file = ROOT_PATH . '/' . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});


echo "===========================================\n";
echo "JAGAPADI - Curah Hujan Monitoring\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

try {
    $monitor = new CurahHujanMonitor();
    
    echo "Menjalankan monitoring checks...\n\n";
    
    $alerts = $monitor->runDailyCheck();
    
    if (empty($alerts)) {
        echo "Status: OK\n";
        echo "Tidak ada alert yang ditemukan.\n";
    } else {
        echo "Status: ALERTS FOUND\n";
        echo "Jumlah alert: " . count($alerts) . "\n\n";
        
        foreach ($alerts as $index => $alert) {
            echo "--- Alert #" . ($index + 1) . " ---\n";
            echo "Type: " . $alert['type'] . "\n";
            echo "Severity: " . strtoupper($alert['severity']) . "\n";
            echo "Message: " . $alert['message'] . "\n";
            echo "\n";
        }
    }
    
    // Print summary stats
    echo "\n--- Summary ---\n";
    $stats = $monitor->getAlertStats();
    echo "Total alerts (30 days): " . $stats['total'] . "\n";
    echo "Unacknowledged: " . $stats['unacknowledged'] . "\n";
    
    if (!empty($stats['by_severity'])) {
        echo "\nBy severity:\n";
        foreach ($stats['by_severity'] as $severity => $data) {
            echo "  - {$severity}: {$data['count']} ({$data['unacknowledged']} unacknowledged)\n";
        }
    }
    
    echo "\n===========================================\n";
    echo "Monitoring selesai!\n";
    echo "===========================================\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
