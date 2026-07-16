<?php
/**
 * Curah Hujan Health Check Cron Script
 * Run this script via cron job or Task Scheduler
 * 
 * Windows Task Scheduler:
 * - Program: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
 * - Arguments: c:\laragon\www\jagapadi\scripts\cron_health_check.php
 * 
 * @version 1.0.1
 * @author JAGAPADI System
 */

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Load configuration (same as index.php bootstrap)
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

// Autoload classes (same as index.php)
spl_autoload_register(function ($class) {
    $paths = [
        'app/controllers/',
        'app/models/',
        'app/core/',
        'app/helpers/',
        'app/services/'
    ];
    
    foreach ($paths as $path) {
        $file = ROOT_PATH . '/' . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

echo "=== Curah Hujan Health Check ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $healthCheck = new CurahHujanHealthCheck();
    
    // Run health check for current year
    $results = $healthCheck->run([
        'year' => date('Y')
    ]);
    
    // Output summary
    echo "Results:\n";
    echo "- Duplicates found: " . $results['summary']['duplicate_count'] . "\n";
    echo "- Data gaps found: " . $results['summary']['gap_count'] . "\n";
    echo "- Boundary issues: " . $results['summary']['boundary_issue_count'] . "\n";
    echo "- Seasonal anomalies: " . $results['summary']['anomaly_count'] . "\n";
    echo "- Execution time: " . $results['summary']['execution_time'] . "s\n\n";
    
    // Alert if critical issues
    if ($healthCheck->hasCriticalIssues()) {
        echo "⚠️ WARNING: Critical issues detected! Check logs for details.\n";
        exit(1);
    }
    
    echo "✓ Health check completed successfully.\n";
    exit(0);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
