<?php
/**
 * TASK 4: Import Penuh KSA Ubinan ke data_pertanian_bps
 *
 * Jalankan dari CLI:
 *   php run_full_import.php                          (dry-run, CSV hasil TASK 2)
 *   php run_full_import.php file.csv dry-run
 *   php run_full_import.php file.csv execute [--year=2025]
 *
 * Mode:
 *   dry-run  -> preview apa yang akan diimport, TANPA menulis DB
 *   execute  -> import real (UPSERT berdasarkan UNIQUE tahun+kabupaten_kota),
 *               progress tiap 5 baris, log ke import_log.txt & bps_scraping_logs
 */

declare(strict_types=1);

// ============================================================
// BOOTSTRAP
// ============================================================
define('ROOT_PATH', dirname(__DIR__, 2));
define('CLI_MODE', true);

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
require_once ROOT_PATH . '/app/models/DataPertanianBps.php';

// ============================================================
// KONFIGURASI & ARGUMEN CLI
// ============================================================

const LOG_FILE = __DIR__ . '/import_log.txt';
const TAHUN_MIN = 2000;
const JUMLAH_KABUPATEN = 38;

$csvPath = $argv[1] ?? __DIR__ . '/ksa_ubinan_2025_normalized.csv';
$mode = $argv[2] ?? 'dry-run';
$yearOverride = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--year=')) {
        $yearOverride = (int) substr($arg, 7);
    }
}

if (!in_array($mode, ['dry-run', 'execute'], true)) {
    echo "  ERROR: mode harus 'dry-run' atau 'execute'\n";
    exit(1);
}

if ($yearOverride !== null && ($yearOverride < TAHUN_MIN || $yearOverride > (int)date('Y') + 1)) {
    echo "  ERROR: --year harus antara " . TAHUN_MIN . " dan " . (date('Y') + 1) . "\n";
    exit(1);
}

// ============================================================
// HELPER
// ============================================================

function printHeader(string $title): void
{
    $line = str_repeat('=', 60);
    echo "\n{$line}\n  {$title}\n{$line}\n";
}

function printOk(string $msg): void
{
    echo "  OK {$msg}\n";
}

function printWarn(string $msg): void
{
    echo "  !! {$msg}\n";
}

function printInfo(string $msg): void
{
    echo "  -> {$msg}\n";
}

function normalizeNumber(?string $value): ?float
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $clean = trim($value);
    $clean = preg_replace('/\s*(ha|ton|ku|ku\/ha|gkg|gkp)\s*$/i', '', $clean);
    $clean = str_replace(' ', '', $clean);
    if (str_contains($clean, ',') && str_contains($clean, '.')) {
        if (strrpos($clean, ',') > strrpos($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }
    } elseif (str_contains($clean, ',')) {
        $clean = str_replace(',', '.', $clean);
    } elseif (str_contains($clean, '.')) {
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $clean)) {
            $clean = str_replace('.', '', $clean);
        }
    }
    if (!is_numeric($clean)) {
        return null;
    }
    return (float) $clean;
}

function getColumn(array $row, string $key): string
{
    $variants = [
        'tahun' => ['tahun', 'year'],
        'kabupaten_kota' => ['kabupaten_kota', 'kabupaten', 'kota', 'regency', 'nama_kabupaten', 'nama_kota'],
        'kode_wilayah' => ['kode_wilayah', 'kode_bps', 'kode', 'wilayah_id'],
        'luas_panen' => ['luas_panen', 'luas', 'harvest_area'],
        'produksi_gabah' => ['produksi_gabah', 'gabah', 'produksi'],
        'produksi_beras' => ['produksi_beras', 'beras'],
        'produktivitas' => ['produktivitas', 'productivity', 'prod'],
        'keterangan' => ['keterangan', 'notes', 'catatan'],
    ];
    $list = $variants[$key] ?? [$key];
    foreach ($list as $name) {
        if (array_key_exists($name, $row)) {
            return (string) $row[$name];
        }
    }
    return '';
}

// ============================================================
// MAIN
// ============================================================

printHeader('TASK 4: Import Penuh KSA Ubinan');
printInfo('File CSV : ' . $csvPath);
printInfo('Mode     : ' . $mode . ($yearOverride !== null ? " (tahun dipaksa: {$yearOverride})" : ''));

if (!file_exists($csvPath)) {
    echo "\n  ERROR: File tidak ditemukan: {$csvPath}\n";
    exit(1);
}

// STEP 1: parse CSV
printHeader('STEP 1: Parse CSV');

$rawRows = [];
if (($handle = fopen($csvPath, 'r')) !== false) {
    $rowNum = 0;
    $headers = [];
    while (($row = fgetcsv($handle)) !== false) {
        $row = array_map('trim', $row);
        if ($rowNum === 0) {
            $headers = array_map('strtolower', $row);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        } else {
            if (!empty(array_filter($row, fn($v) => $v !== ''))) {
                $item = [];
                foreach ($headers as $idx => $h) {
                    $item[$h] = $row[$idx] ?? '';
                }
                $rawRows[] = ['row' => $rowNum + 1, 'data' => $item];
            }
        }
        $rowNum++;
    }
    fclose($handle);
} else {
    echo "\n  ERROR: Tidak dapat membuka file.\n";
    exit(1);
}

printInfo('Total baris data: ' . count($rawRows));

// STEP 2: validasi & siapkan data
printHeader('STEP 2: Validasi & Persiapan Data');

$tahunMax = (int) date('Y') + 1;
$records = [];
$errors = [];

foreach ($rawRows as $entry) {
    $row = $entry['data'];
    $rowErrors = [];

    $tahun = $yearOverride ?? (int) getColumn($row, 'tahun');
    if ($tahun < TAHUN_MIN || $tahun > $tahunMax) {
        $rowErrors[] = "tahun '{$tahun}' di luar rentang {$tahunMin}..{$tahunMax}";
    }

    $kabupaten = getColumn($row, 'kabupaten_kota');
    if (strlen($kabupaten) < 3) {
        $rowErrors[] = "kabupaten kosong/terlalu pendek";
    }

    $luas = normalizeNumber(getColumn($row, 'luas_panen'));
    if ($luas === null || $luas < 0) {
        $rowErrors[] = "luas_panen tidak valid ('" . getColumn($row, 'luas_panen') . "')";
    }

    $gabah = normalizeNumber(getColumn($row, 'produksi_gabah'));
    if ($gabah === null || $gabah < 0) {
        $rowErrors[] = "produksi_gabah tidak valid ('" . getColumn($row, 'produksi_gabah') . "')";
    }

    if (!empty($rowErrors)) {
        $errors[] = "Baris {$entry['row']} ({$kabupaten}): " . implode('; ', $rowErrors);
        continue;
    }

    $beras = normalizeNumber(getColumn($row, 'produksi_beras'));
    if ($beras === null) {
        $beras = round($gabah * 0.577, 2);
    }

    $produktivitas = normalizeNumber(getColumn($row, 'produktivitas'));
    if ($produktivitas === null) {
        $produktivitas = $luas > 0 ? round(($gabah / $luas) * 10, 2) : 0;
    }

    $kode = normalizeNumber(getColumn($row, 'kode_wilayah'));
    $kodeWilayah = $kode !== null ? (string) (int) $kode : null;

    $records[] = [
        'row' => $entry['row'],
        'tahun' => $tahun,
        'kabupaten_kota' => $kabupaten,
        'kode_wilayah' => $kodeWilayah,
        'luas_panen' => $luas,
        'produksi_gabah' => $gabah,
        'produksi_beras' => $beras,
        'produktivitas' => $produktivitas,
        'keterangan' => getColumn($row, 'keterangan'),
    ];
}

printInfo('Baris valid   : ' . count($records));
printInfo('Baris error   : ' . count($errors));
foreach ($errors as $e) {
    printWarn($e);
}

if (empty($records)) {
    echo "\n  ERROR: Tidak ada baris valid untuk diimport.\n";
    exit(1);
}

// STEP 3: preview (dry-run) atau eksekusi
printHeader('STEP 3: ' . ($mode === 'dry-run' ? 'PREVIEW (DRY-RUN)' : 'EKSEKUSI IMPORT'));

printf("  %-20s %-6s %6s %14s %14s %14s %10s\n", "Kabupaten", "Tahun", "Kode", "Luas(Ha)", "Gabah(Ton)", "Beras(Ton)", "Prod(Ku/Ha)");
echo "  " . str_repeat('-', 90) . "\n";
foreach ($records as $r) {
    printf("  %-20s %-6d %6s %14s %14s %14s %10s\n",
        $r['kabupaten_kota'],
        $r['tahun'],
        $r['kode_wilayah'] ?? '-',
        number_format($r['luas_panen'], 2),
        number_format($r['produksi_gabah'], 2),
        number_format($r['produksi_beras'], 2),
        number_format($r['produktivitas'], 2)
    );
}

if ($mode === 'dry-run') {
    printHeader('RINGKASAN (DRY-RUN)');
    echo '  Tidak ada data yang ditulis ke database.' . "\n";
    echo '  Jalankan: php run_full_import.php ' . basename($csvPath) . " execute [--year=YYYY]\n";
    echo "\n  Selesai: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('=', 60) . "\n\n";
    exit(0);
}

// --- execute mode ---
$startTime = microtime(true);
$db = Database::getInstance()->getConnection();
$model = new DataPertanianBps();

$inserted = 0;
$updated = 0;
$failed = 0;
$failedList = [];
$total = count($records);

foreach ($records as $i => $r) {
    $existing = $model->getByYearAndKabupaten($r['tahun'], $r['kabupaten_kota']);

    try {
        $result = $model->upsert([
            'tahun' => $r['tahun'],
            'kabupaten_kota' => $r['kabupaten_kota'],
            'kode_wilayah' => $r['kode_wilayah'],
            'luas_panen' => $r['luas_panen'],
            'produksi_gabah' => $r['produksi_gabah'],
            'produksi_beras' => $r['produksi_beras'],
            'produktivitas' => $r['produktivitas'],
            'sumber_data' => 'KSA Ubinan 2025',
            'sumber_data_type' => 'manual',
            'tipe_skenario' => 'baseline',
            'is_validated' => 1,
            'keterangan' => $r['keterangan'] ?: 'Import KSA Ubinan via CLI run_full_import.php',
        ]);

        if ($result) {
            if ($existing) {
                $updated++;
            } else {
                $inserted++;
            }
        } else {
            $failed++;
            $failedList[] = "{$r['kabupaten_kota']} ({$r['tahun']}): gagal menyimpan";
        }
    } catch (Exception $e) {
        $failed++;
        $failedList[] = "{$r['kabupaten_kota']} ({$r['tahun']}): " . $e->getMessage();
    }

    if (($i + 1) % 5 === 0 || $i + 1 === $total) {
        $n = $i + 1;
        printInfo("Progress {$n}/{$total} (inserted: {$inserted}, updated: {$updated}, failed: {$failed})");
    }
}

$duration = round(microtime(true) - $startTime, 2);

foreach ($failedList as $f) {
    printWarn($f);
}

// STEP 4: log & statistik
printHeader('STEP 4: Log & Statistik Post-Import');

$logLine = sprintf(
    "[%s] CSV=%s mode=%s rows=%d inserted=%d updated=%d failed=%d durasi=%ss\n",
    date('Y-m-d H:i:s'),
    basename($csvPath),
    $mode,
    $total,
    $inserted,
    $updated,
    $failed,
    $duration
);
file_put_contents(LOG_FILE, $logLine, FILE_APPEND);
printOk('Log ditulis: ' . LOG_FILE);

$model->logActivity('import_ksa_ubinan', $failed === 0 ? 'success' : 'partial', 'Import KSA Ubinan via CLI', [
    'csv' => basename($csvPath),
    'rows' => $total,
    'inserted' => $inserted,
    'updated' => $updated,
    'failed' => $failed,
    'durasi' => $duration,
]);

$tahunImport = $yearOverride ?? $records[0]['tahun'];
$stats = $model->getStatistics($tahunImport);
if ($stats && $stats['jumlah_kabupaten'] > 0) {
    printf("  %-30s: %s\n", "Kabupaten/Kota (tahun {$tahunImport})", $stats['jumlah_kabupaten']);
    printf("  %-30s: %s Ha\n", "Total Luas Panen", number_format((float) $stats['total_luas_panen'], 2));
    printf("  %-30s: %s Ton\n", "Total Produksi Gabah", number_format((float) $stats['total_produksi_gabah'], 2));
    printf("  %-30s: %s Ton\n", "Total Produksi Beras", number_format((float) $stats['total_produksi_beras'], 2));
    printf("  %-30s: %s Ku/Ha\n", "Rata-rata Produktivitas", $stats['rata_produktivitas'] ?? 0);
}

printHeader('RINGKASAN TASK 4');
printf("  %-25s: %d\n", "Total baris diproses", $total);
printf("  %-25s: %d\n", "Inserted", $inserted);
printf("  %-25s: %d\n", "Updated", $updated);
printf("  %-25s: %d\n", "Failed", $failed);
echo '  Waktu     : ' . $duration . " detik\n";

if ($failed === 0) {
    printOk('IMPORT BERHASIL SEMUA');
} else {
    printWarn('IMPORT SEBAGIAN GAGAL - cek daftar di atas');
}

echo "\n  Selesai: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";
