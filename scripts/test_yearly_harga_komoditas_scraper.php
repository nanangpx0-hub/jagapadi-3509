<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';
require_once ROOT_PATH . '/app/core/Model.php';
require_once ROOT_PATH . '/app/models/HargaKomoditas.php';
require_once ROOT_PATH . '/app/services/HargaKomoditasScraper.php';

echo "=======================================================\n";
echo " TESTING YEARLY HARGA KOMODITAS SCRAPER (2025)\n";
echo "=======================================================\n\n";

$scraper = new HargaKomoditasScraper();
$year = 2025;
$successCount = 0;
$failedCount = 0;
$totalRecords = 0;

$startTime = microtime(true);

for ($m = 1; $m <= 12; $m++) {
    $monthStart = microtime(true);
    try {
        $result = $scraper->run([
            'year' => $year,
            'month' => $m,
            'source' => 'siskaperbapo'
        ]);

        $elapsed = round(microtime(true) - $monthStart, 2);
        if ($result['success']) {
            $successCount++;
            $recCount = $result['records_success'] ?? 0;
            $totalRecords += $recCount;
            echo "✅ Bulan {$m}: SUKSES | Records: {$recCount} | Time: {$elapsed}s | Source: {$result['source']}\n";
        } else {
            $failedCount++;
            echo "❌ Bulan {$m}: GAGAL  | Message: {$result['message']}\n";
        }
    } catch (Exception $e) {
        $failedCount++;
        echo "❌ Bulan {$m}: EXCEPTION - " . $e->getMessage() . "\n";
    }
}

$totalTime = round(microtime(true) - $startTime, 2);

echo "\n-------------------------------------------------------\n";
echo "SUMMARY YEARLY HARGA KOMODITAS SCRAPER TEST ({$year}):\n";
echo "  Success Months : {$successCount} / 12\n";
echo "  Failed Months  : {$failedCount} / 12\n";
echo "  Total Records  : " . number_format($totalRecords) . "\n";
echo "  Total Time     : {$totalTime} seconds\n";
echo "=======================================================\n";

if ($failedCount === 0) {
    echo "🎉 YEARLY HARGA KOMODITAS SCRAPER TEST PASSED PERFECTLY!\n";
    exit(0);
} else {
    echo "❌ TEST FAILED WITH {$failedCount} ERRORS!\n";
    exit(1);
}
