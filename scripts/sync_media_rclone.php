<?php

declare(strict_types=1);

/**
 * JAGAPADI — CLI Media Synchronization Script via Rclone (Pull Model)
 *
 * Script CLI untuk mensinkronisasi aset media uploads dari hosting cPanel ke
 * lingkungan lokal Laragon Windows menggunakan MediaSyncService.
 *
 * Penggunaan:
 *   php scripts/sync_media_rclone.php [options]
 *
 * Pilihan Argumen:
 *   --dry-run             Simulasi proses sinkronisasi tanpa modifikasi file lokal
 *   --remote=<name>       Nama remote profil rclone (default: cpanel_sftp)
 *   --remote-path=<path>  Path direktori uploads di remote cPanel
 *   --local=<path>        Path direktori uploads lokal (default: public/uploads)
 *   --mode=copy|sync      Mode sinkronisasi (default: copy)
 *   --limit=<bandwidth>   Batas bandwidth transfer (contoh: 10M, 2M, 500k)
 *   --config=<path>       Path file konfigurasi rclone (default: config/rclone.conf)
 *   --verbose, -v         Tampilkan detail argumen dan log rclone
 *   --help, -h            Tampilkan bantuan ini
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Error: Script ini hanya dapat dijalankan melalui CLI.\n");
    exit(1);
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Daftarkan class autoloader untuk lingkungan JAGAPADI
spl_autoload_register(static function (string $class): void {
    $paths = [
        ROOT_PATH . '/app/services/' . $class . '.php',
        ROOT_PATH . '/app/models/' . $class . '.php',
        ROOT_PATH . '/app/core/' . $class . '.php',
        ROOT_PATH . '/app/helpers/' . $class . '.php',
    ];

    foreach ($paths as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Helper pembaca file environment (.env.backup)
$envData = [];
$envCandidates = [
    ROOT_PATH . '/scripts/.env.backup',
    ROOT_PATH . '/.env.backup',
];

foreach ($envCandidates as $envCandidate) {
    if (is_file($envCandidate)) {
        $lines = file($envCandidate, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if ($key !== '') {
                    $envData[$key] = $value;
                }
            }
        }
        break;
    }
}

// Parsing parameter baris perintah (CLI options)
$shortopts = 'vh';
$longopts = [
    'dry-run',
    'remote:',
    'remote-path:',
    'local:',
    'mode:',
    'limit:',
    'config:',
    'verbose',
    'help',
];

$cliOptions = getopt($shortopts, $longopts);

if (isset($cliOptions['h']) || isset($cliOptions['help'])) {
    echo <<<HELP
JAGAPADI — Rclone Media Synchronization CLI Tool
=================================================
Sinkronisasi media cPanel hosting (Pull Model) ke lokal Laragon.

Penggunaan:
  php scripts/sync_media_rclone.php [options]

Opsi yang didukung:
  --dry-run             Simulasi transfer tanpa mendownload/menghapus file
  --remote=<name>       Nama remote rclone (default: cpanel_sftp atau dari .env.backup)
  --remote-path=<path>  Remote path cPanel (default: /home/jagapadi/public_html/public/uploads)
  --local=<path>        Local path tujuan (default: public/uploads)
  --mode=copy|sync      Mode rclone: copy (aman) atau sync (hapus berkas usang)
  --limit=<bandwidth>   Batas bandwidth (misal: 10M, 2M, 500k)
  --config=<path>       Path file konfigurasi rclone (default: config/rclone.conf)
  --verbose, -v         Tampilkan log detail dan perintah CLI lengkap
  --help, -h            Tampilkan petunjuk ini

HELP;
    exit(0);
}

$isDryRun = isset($cliOptions['dry-run']);
$isVerbose = isset($cliOptions['v']) || isset($cliOptions['verbose']);

$remoteName = (string)($cliOptions['remote'] ?? $envData['REMOTE_NAME'] ?? 'cpanel_sftp');
$remotePath = (string)($cliOptions['remote-path'] ?? $envData['REMOTE_UPLOADS_PATH'] ?? '/home/jagapadi/public_html/public/uploads');
$localPath = (string)($cliOptions['local'] ?? $envData['LOCAL_UPLOADS_PATH'] ?? 'public/uploads');
$syncMode = (string)($cliOptions['mode'] ?? $envData['SYNC_MODE'] ?? 'copy');
$bandwidthLimit = (string)($cliOptions['limit'] ?? $envData['BANDWIDTH_LIMIT'] ?? '');
$configPath = (string)($cliOptions['config'] ?? $envData['RCLONE_CONFIG_PATH'] ?? 'config/rclone.conf');
$rcloneExeCustom = (string)($envData['RCLONE_EXE_PATH'] ?? '');

$logFile = (string)($envData['LOG_FILE'] ?? 'storage/logs/rclone_media_sync.log');
$logLevel = (string)($envData['LOG_LEVEL'] ?? 'INFO');
$transfers = isset($envData['TRANSFERS']) ? (int)$envData['TRANSFERS'] : 4;
$checkers = isset($envData['CHECKERS']) ? (int)$envData['CHECKERS'] : 8;
$retries = isset($envData['RETRIES']) ? (int)$envData['RETRIES'] : 3;

echo "\n============================================================\n";
echo "  JAGAPADI Media Sync — Pull Model Backup (cPanel -> Local)\n";
echo "  Waktu: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";

$syncService = new MediaSyncService(ROOT_PATH);

// Validasi keberadaan binary rclone
$rcloneBinary = $syncService->findRcloneBinary($rcloneExeCustom !== '' ? $rcloneExeCustom : null);
if ($rcloneBinary === null) {
    fwrite(STDERR, "[ERROR] Binary rclone tidak ditemukan di sistem!\n");
    fwrite(STDERR, "[PETUNJUK] Pasang rclone dengan menjalankan: powershell -File scripts/ensure_rclone.ps1\n");
    fwrite(STDERR, "           atau letakkan rclone.exe di C:\\laragon\\bin\\rclone.exe\n");
    exit(1);
}

// Validasi keberadaan file konfigurasi rclone
$resolvedConfig = str_starts_with($configPath, '/') || preg_match('/^[a-zA-Z]:/', $configPath)
    ? $configPath
    : ROOT_PATH . '/' . ltrim($configPath, '/');

if (!is_file($resolvedConfig)) {
    fwrite(STDERR, sprintf("[ERROR] File konfigurasi tidak ditemukan: %s\n", $resolvedConfig));
    fwrite(STDERR, "[PETUNJUK] Buat file konfigurasi dari template:\n");
    fwrite(STDERR, "           cp config/rclone.conf.example config/rclone.conf\n");
    fwrite(STDERR, "           lalu isi kredensial hosting cPanel Anda.\n");
    exit(1);
}

// Opsi sinkronisasi
$syncOptions = [
    'command' => $syncMode,
    'remote_name' => $remoteName,
    'remote_path' => $remotePath,
    'local_path' => $localPath,
    'config_path' => $resolvedConfig,
    'dry_run' => $isDryRun,
    'transfers' => $transfers,
    'checkers' => $checkers,
    'retries' => $retries,
    'log_file' => ROOT_PATH . '/' . ltrim($logFile, '/'),
    'log_level' => $logLevel,
];

if ($bandwidthLimit !== '') {
    $syncOptions['bandwidth_limit'] = $bandwidthLimit;
}

try {
    // Pastikan folder tujuan lokal ada
    $syncService->validateLocalPath($localPath, true);

    echo sprintf("[INFO] Rclone Binary : %s\n", $rcloneBinary);
    echo sprintf("[INFO] Profil Remote : %s:%s\n", $remoteName, $remotePath);
    echo sprintf("[INFO] Target Lokal  : %s\n", $localPath);
    echo sprintf("[INFO] Mode Sync     : %s %s\n", strtoupper($syncMode), $isDryRun ? '(DRY-RUN)' : '');
    if ($bandwidthLimit !== '') {
        echo sprintf("[INFO] Bandwidth     : Limit %s\n", $bandwidthLimit);
    }
    echo "------------------------------------------------------------\n";
    echo "[RUNNING] Memulai proses sinkronisasi media...\n";

    $startTime = microtime(true);
    $result = $syncService->runSync($syncOptions, $rcloneBinary);
    $duration = round(microtime(true) - $startTime, 2);

    echo "------------------------------------------------------------\n";

    if ($isVerbose) {
        echo "[DEBUG] Command: " . $result['command_string'] . "\n";
        if ($result['stdout'] !== '') {
            echo "[STDOUT]\n" . $result['stdout'] . "\n";
        }
        if ($result['stderr'] !== '') {
            echo "[STDERR]\n" . $result['stderr'] . "\n";
        }
    }

    $stats = $result['stats'];

    if ($result['exit_code'] === 0 && $stats['success']) {
        echo "[SUCCESS] Sinkronisasi selesai dalam {$duration} detik!\n";
        echo sprintf("  - Ukuran Transfer : %s / %s\n", $stats['transferred_bytes'], $stats['total_bytes']);
        echo sprintf("  - File Ditransfer : %d file\n", $stats['transferred_files']);
        echo sprintf("  - File Diperiksa  : %d file\n", $stats['checks']);
        echo sprintf("  - Jumlah Error    : %d\n", $stats['errors']);
        echo sprintf("  - Elapsed Time    : %s\n", $stats['elapsed_time']);
        echo "============================================================\n";
        exit(0);
    }

    fwrite(STDERR, sprintf("[FAILED] Sinkronisasi selesai dengan peringatan/kegagalan (exit code: %d, durasi: %s s)\n", $result['exit_code'], $duration));
    fwrite(STDERR, sprintf("  - Error count : %d\n", $stats['errors']));
    if (!empty($stats['error_messages'])) {
        fwrite(STDERR, "  - Pesan Error :\n");
        foreach ($stats['error_messages'] as $errMsg) {
            fwrite(STDERR, "    * " . $errMsg . "\n");
        }
    }
    echo "============================================================\n";
    exit($result['exit_code'] !== 0 ? $result['exit_code'] : 1);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("[EXCEPTION] %s: %s\n", get_class($e), $e->getMessage()));
    fwrite(STDERR, "Trace: " . $e->getTraceAsString() . "\n");
    exit(1);
}
