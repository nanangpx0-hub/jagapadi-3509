<?php
declare(strict_types=1);
/**
 * Skrip Batch Multi-Tahun: Kecepatan Angin (NASA POWER) & Harga (SISKAPERBAPO).
 *
 * Memakai ulang service runBatchRange (idempoten via UPSERT) sehingga
 * perilaku identik dengan endpoint web. Tahun/bulan yang gagal tidak
 * menghentikan periode lain; rekap kegagalan ditulis terstruktur ke
 * logs/{angin,harga}_failures.json untuk audit & retry.
 *
 * Penggunaan:
 *   php scripts/scrape_range.php --module=angin --start-year=2020 --end-year=2026
 *   php scripts/scrape_range.php --module=harga --start-year=2020 --end-year=2026 [--source=siskaperbapo]
 *   php scripts/scrape_range.php --module=angin --retry-failed
 *   php scripts/scrape_range.php --help
 */

set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_RUN')) {
    die("Access denied: script ini hanya untuk CLI/cron.\n");
}

function range_progress(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

function range_usage(): void
{
    echo "Penggunaan:\n";
    echo "  php scripts/scrape_range.php --module=angin|harga [--start-year=2020] [--end-year=2026] [--source=SOURCE] [--retry-failed]\n\n";
    echo "  --module       Wajib: 'angin' (NASA POWER WS10M/WS2M) atau 'harga' (SISKAPERBAPO Jatim).\n";
    echo "  --start-year   Tahun awal (2020..tahun berjalan). Default: 2020.\n";
    echo "  --end-year     Tahun akhir (2020..tahun berjalan). Default: tahun berjalan.\n";
    echo "  --source       Angin: 'nasa' (default). Harga: 'siskaperbapo' (default) atau 'simulation'.\n";
    echo "  --retry-failed Hanya proses ulang periode gagal dari logs/{angin,harga}_failures.json.\n";
    echo "  --help         Tampilkan bantuan ini.\n";
}

if (in_array('--help', $argv ?? [], true)) {
    range_usage();
    exit(0);
}

$options = getopt('', ['module:', 'start-year::', 'end-year::', 'source::', 'retry-failed']);
$module = strtolower(trim((string)($options['module'] ?? '')));
if (!in_array($module, ['angin', 'harga'], true)) {
    fwrite(STDERR, "ERROR: --module wajib 'angin' atau 'harga'.\n");
    range_usage();
    exit(1);
}

$currentYear = (int)date('Y');
$startYear = isset($options['start-year']) && $options['start-year'] !== false ? (int)$options['start-year'] : 2020;
$endYear = isset($options['end-year']) && $options['end-year'] !== false ? (int)$options['end-year'] : $currentYear;
$failuresPath = __DIR__ . '/../logs/' . $module . '_failures.json';

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';

$retryPeriods = null; // null = mode rentang penuh; array = mode retry
if (isset($options['retry-failed'])) {
    $raw = @file_get_contents($failuresPath);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $items = is_array($decoded) && isset($decoded['failures']) && is_array($decoded['failures']) ? $decoded['failures'] : [];
    $seen = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = $module === 'harga'
            ? ((int)($item['year'] ?? 0)) . '-' . ((int)($item['month'] ?? 0))
            : (string)(int)($item['year'] ?? 0);
        if (!isset($seen[$key])) {
            $seen[$key] = $item;
        }
    }
    if ($seen === []) {
        range_progress("Mode --retry-failed: tidak ada kegagalan tercatat di {$failuresPath}; tidak ada yang diproses.");
        exit(0);
    }
    $retryPeriods = array_values($seen);
    range_progress('Mode --retry-failed: ' . count($retryPeriods) . ' periode akan diproses ulang.');
}

$exitCode = 0;
try {
    if ($module === 'angin') {
        require_once ROOT_PATH . '/app/services/KecepatanAnginScraper.php';
        $errors = KecepatanAnginScraper::validateRange($startYear, $endYear);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
        $scraper = new KecepatanAnginScraper();
        if ($retryPeriods === null) {
            range_progress("Mulai batch angin {$startYear}-{$endYear}...");
            $report = $scraper->runBatchRange($startYear, $endYear, static function (int $year) {
                range_progress("Tahun {$year} selesai.");
            });
        } else {
            $report = [
                'success' => true, 'start_year' => $startYear, 'end_year' => $endYear,
                'total_records_saved' => 0, 'total_records_failed' => 0, 'total_skipped_future' => 0,
                'years_summary' => [], 'failures' => [], 'execution_time' => 0.0,
            ];
            $t0 = microtime(true);
            foreach ($retryPeriods as $item) {
                $year = (int)($item['year'] ?? 0);
                try {
                    $one = $scraper->runSingleYear($year);
                } catch (Throwable $e) {
                    $one = ['year' => $year, 'status' => 'failed', 'records' => 0, 'failed' => 1, 'skipped' => 0, 'failures' => [['year' => $year, 'error' => $e->getMessage()]], 'execution_time' => 0.0];
                }
                $report['total_records_saved'] += (int)$one['records'];
                $report['total_records_failed'] += (int)$one['failed'];
                $report['total_skipped_future'] += (int)$one['skipped'];
                $report['years_summary'][$year] = ['status' => $one['status'], 'records' => (int)$one['records'], 'failed' => (int)$one['failed']];
                foreach ($one['failures'] as $failure) {
                    $report['failures'][] = $failure;
                }
                if ($report['total_records_failed'] > 0) {
                    $report['success'] = false;
                }
                range_progress("Retry tahun {$year}: {$one['status']}.");
            }
            $report['execution_time'] = round(microtime(true) - $t0, 4);
        }
    } else {
        require_once ROOT_PATH . '/app/services/HargaKomoditasScraper.php';
        $source = strtolower(trim((string)($options['source'] ?? 'siskaperbapo')));
        if (!in_array($source, ['siskaperbapo', 'simulation'], true)) {
            throw new InvalidArgumentException("Source harga harus 'siskaperbapo' atau 'simulation'.");
        }
        $errors = HargaKomoditasScraper::validateRange($startYear, $endYear);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
        $scraper = new HargaKomoditasScraper();
        if ($retryPeriods === null) {
            range_progress("Mulai batch harga {$startYear}-{$endYear} (sumber: {$source})...");
            $report = $scraper->runBatchRange($startYear, $endYear, $source, static function (int $year, int $month) {
                range_progress(sprintf('Periode %04d-%02d selesai.', $year, $month));
            });
        } else {
            $report = [
                'success' => true, 'start_year' => $startYear, 'end_year' => $endYear,
                'total_records_saved' => 0, 'total_records_failed' => 0, 'total_skipped_future' => 0,
                'years_summary' => [], 'failures' => [], 'execution_time' => 0.0,
            ];
            $t0 = microtime(true);
            foreach ($retryPeriods as $item) {
                $year = (int)($item['year'] ?? 0);
                $month = (int)($item['month'] ?? 0);
                try {
                    $one = $scraper->runSingleMonth($year, $month, $source);
                } catch (Throwable $e) {
                    $one = ['year' => $year, 'month' => $month, 'status' => 'failed', 'records' => 0, 'failed' => 1, 'skipped' => 0, 'failures' => [['year' => $year, 'month' => $month, 'error' => $e->getMessage()]], 'execution_time' => 0.0];
                }
                $report['total_records_saved'] += (int)$one['records'];
                $report['total_records_failed'] += (int)$one['failed'];
                if (!isset($report['years_summary'][$year])) {
                    $report['years_summary'][$year] = ['status' => 'success', 'records' => 0, 'failed' => 0];
                }
                $report['years_summary'][$year]['records'] += (int)$one['records'];
                $report['years_summary'][$year]['failed'] += (int)$one['failed'];
                if ($report['years_summary'][$year]['failed'] > 0) {
                    $report['years_summary'][$year]['status'] = $report['years_summary'][$year]['records'] > 0 ? 'partial' : 'failed';
                }
                foreach ($one['failures'] as $failure) {
                    $report['failures'][] = $failure;
                }
                if ($report['total_records_failed'] > 0) {
                    $report['success'] = false;
                }
                range_progress(sprintf('Retry %04d-%02d: %s.', $year, $month, $one['status']));
            }
            $report['execution_time'] = round(microtime(true) - $t0, 4);
        }
    }

    $payload = [
        'generated_at' => date('Y-m-d H:i:s'),
        'module' => $module,
        'start_year' => $startYear,
        'end_year' => $endYear,
        'failures' => array_values($report['failures']),
    ];
    $logDir = dirname($failuresPath);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    if (@file_put_contents($failuresPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        range_progress("PERINGATAN: gagal menulis {$failuresPath}");
    } else {
        range_progress('Rekap kegagalan: ' . $failuresPath . ' (' . count($report['failures']) . ' item)');
    }

    range_progress(sprintf(
        'SELESAI: %d tersimpan, %d gagal, %d dilewati (%.1f dtk).',
        $report['total_records_saved'],
        $report['total_records_failed'],
        $report['total_skipped_future'],
        (float)$report['execution_time']
    ));
    if (!$report['success']) {
        $exitCode = 2;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    error_log('scrape_range CLI failed: ' . $e->getMessage());
    $exitCode = 1;
}

exit($exitCode);
