<?php
/**
 * CLI Import Data KSA (Survei Kerangka Sampel Area) BPS ke JAGAPADI
 *
 * Jalankan dari CLI:
 *   php data/ksa/run_ksa_import.php --angka-tetap
 *   php data/ksa/run_ksa_import.php --bulanan
 *   php data/ksa/run_ksa_import.php --sync-tahun=2025
 *   php data/ksa/run_ksa_import.php --all
 *
 * Opsi:
 *   --angka-tetap           Import file "Luas Panen dan Produksi Padi 2018-2025 (Angka Tetap)"
 *   --bulanan               Import semua file "2026.XX KSA Jatim.xlsx"
 *   --sync-tahun=<tahun>    Sinkronisasi agregat tahunan (status tetap) ke data_pertanian_bps
 *   --all                   Jalankan semua langkah di atas (sync default tahun 2025)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die('Script ini hanya dapat dijalankan dari command line');
}

// ============================================================
// BOOTSTRAP
// ============================================================
define('ROOT_PATH', dirname(__DIR__, 2));
define('CLI_MODE', true);

$_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? 80;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $envPath) {
    if (!file_exists($envPath)) continue;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) continue;
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;
        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if (empty($key)) continue;
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

require_once ROOT_PATH . '/app/core/Database.php';

spl_autoload_register(function ($class) {
    $paths = [
        'app/controllers/',
        'app/models/',
        'app/core/',
        'app/helpers/',
        'app/middleware/',
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

// ============================================================
// HELPER
// ============================================================

function ksaPrintHeader(string $title): void {
    echo "\n" . str_repeat('=', 60) . "\n  {$title}\n" . str_repeat('=', 60) . "\n";
}

function ksaPrintSummary(string $label, array $summary): void {
    $status = !empty($summary['success']) ? 'BERHASIL' : 'GAGAL';
    echo "  [{$status}] {$label}\n";
    echo "  - Total diproses : {$summary['total_processed']}\n";
    echo "  - Inserted       : {$summary['inserted']}\n";
    echo "  - Updated        : {$summary['updated']}\n";
    echo "  - Skipped        : {$summary['skipped']}\n";
    if (isset($summary['kabupaten'])) {
        echo "  - Kabupaten      : {$summary['kabupaten']}\n";
    }
    if (isset($summary['execution_time'])) {
        echo "  - Waktu eksekusi : {$summary['execution_time']} detik\n";
    }
    if (!empty($summary['errors'])) {
        foreach ($summary['errors'] as $err) {
            echo "  ! Error: {$err}\n";
        }
    }
}

// ============================================================
// ARGUMENTS
// ============================================================
$args = $argv ?? [];
$doAngkaTetap = in_array('--angka-tetap', $args, true);
$doBulanan = in_array('--bulanan', $args, true);
$doAll = in_array('--all', $args, true);
$doSync = $doAll;

$syncTahun = 2025;
foreach ($args as $arg) {
    if (preg_match('/^--sync-tahun=(\d{4})$/', $arg, $m)) {
        $doSync = true;
        $syncTahun = (int) $m[1];
    }
}

if (!$doAngkaTetap && !$doBulanan && !$doSync && !$doAll) {
    echo "Penggunaan:\n";
    echo "  php run_ksa_import.php --angka-tetap\n";
    echo "  php run_ksa_import.php --bulanan\n";
    echo "  php run_ksa_import.php --sync-tahun=2025\n";
    echo "  php run_ksa_import.php --all\n";
    exit(2);
}

ksaPrintHeader('JAGAPADI - Import Data KSA BPS');
echo "  Waktu: " . date('Y-m-d H:i:s') . "\n";

$service = new KsaImportService();
$dir = __DIR__;
$exitCode = 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) FROM data_ksa_bulanan");
    echo "  Records data_ksa_bulanan saat ini: " . $stmt->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo "  ! Gagal koneksi database: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================
// 1. IMPORT ANGKA TETAP 2018-2025
// ============================================================
if ($doAngkaTetap || $doAll) {
    ksaPrintHeader('LANGKAH 1: Import Angka Tetap 2018-2025');
    $files = $service->getAngkaTetapFiles($dir);
    if (empty($files)) {
        echo "  ! Tidak ada file '*Angka Tetap*.xlsx' di " . $dir . "\n";
        $exitCode = 1;
    } else {
        foreach ($files as $file) {
            echo "\n  Import: " . basename($file) . "\n";
            $summary = $service->importAngkaTetap($file);
            ksaPrintSummary(basename($file), $summary);
            if (empty($summary['success'])) {
                $exitCode = 1;
            }
        }
    }
}

// ============================================================
// 2. IMPORT KSA BULANAN 2026
// ============================================================
if ($doBulanan || $doAll) {
    ksaPrintHeader('LANGKAH 2: Import KSA Bulanan 2026');
    $files = $service->getKsaBulananFiles($dir);
    if (empty($files)) {
        echo "  ! Tidak ada file '2026.* KSA Jatim*.xlsx' di " . $dir . "\n";
        $exitCode = 1;
    } else {
        foreach ($files as $file) {
            echo "\n  Import: " . basename($file) . "\n";
            $summary = $service->importKsaBulanan($file);
            ksaPrintSummary(basename($file), $summary);
            if (empty($summary['success'])) {
                $exitCode = 1;
            }
        }
    }
}

// ============================================================
// 3. SINKRONISASI KE data_pertanian_bps
// ============================================================
if ($doSync) {
    ksaPrintHeader("LANGKAH 3: Sinkronisasi Tahun {$syncTahun} ke data_pertanian_bps");
    $summary = $service->syncToDataPertanianBps($syncTahun);
    $status = !empty($summary['success']) ? 'BERHASIL' : 'GAGAL';
    echo "  [{$status}] Sync tahun {$syncTahun}\n";
    echo "  - Total     : {$summary['total']}\n";
    echo "  - Inserted  : {$summary['inserted']}\n";
    echo "  - Updated   : {$summary['updated']}\n";
    echo "  - Skipped   : {$summary['skipped']}\n";
    if (isset($summary['execution_time'])) {
        echo "  - Waktu     : {$summary['execution_time']} detik\n";
    }
    if (!empty($summary['errors'])) {
        foreach ($summary['errors'] as $err) {
            echo "  ! Error: {$err}\n";
        }
        $exitCode = 1;
    }
}

// ============================================================
// SUMMARY AKHIR
// ============================================================
ksaPrintHeader('VALIDASI HASIL');

try {
    $stmt = $db->query("SELECT COUNT(*) FROM data_ksa_bulanan");
    $totalKsa = (int) $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM data_pertanian_bps");
    $totalBps = (int) $stmt->fetchColumn();
    echo "  Total records data_ksa_bulanan  : {$totalKsa}\n";
    echo "  Total records data_pertanian_bps: {$totalBps}\n";

    $stmt = $db->query(
        "SELECT tahun, COUNT(*) AS jumlah, SUM(status_data = 'tetap') AS tetap,
                SUM(status_data = 'sementara') AS sementara, SUM(status_data = 'potensi') AS potensi
         FROM data_ksa_bulanan GROUP BY tahun ORDER BY tahun"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        echo "\n  Rincian per tahun:\n";
        foreach ($rows as $r) {
            printf(
                "    %d : %d records (tetap=%d, sementara=%d, potensi=%d)\n",
                (int) $r['tahun'],
                (int) $r['jumlah'],
                (int) $r['tetap'],
                (int) $r['sementara'],
                (int) $r['potensi']
            );
        }
    }
} catch (Throwable $e) {
    echo "  ! Gagal validasi: " . $e->getMessage() . "\n";
    $exitCode = 1;
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "  Selesai: " . date('Y-m-d H:i:s') . ($exitCode === 0 ? " (SUKSES)" : " (DENGAN ERROR)") . "\n";
echo str_repeat('=', 60) . "\n\n";

exit($exitCode);
