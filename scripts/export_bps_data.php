<?php
/**
 * BPS Data Export Script
 * Mengambil seluruh Data Pertanian per Kabupaten/Kota dari database
 * dan menyimpannya dalam format CSV ke direktori data/ksa/
 *
 * @author JAGAPADI System
 * @version 1.0.0
 */

define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL', 'https://localhost/jagapadi-3509');
define('APP_DEBUG', true);

$envPath = ROOT_PATH . '/.env.local';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;
        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))) { $value = substr($value, 1, -1); }
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['nama_lengkap'] = 'Administrator';

require_once ROOT_PATH . '/app/core/Database.php';
require_once ROOT_PATH . '/app/core/Security.php';
require_once ROOT_PATH . '/app/models/DataPertanianBps.php';

ini_set('memory_limit', '512M');

$model = new DataPertanianBps();

echo "=== BPS DATA EXPORT SCRIPT ===\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

// Step 1: Verify database connectivity and data availability
echo "[Step 1] Verifikasi koneksi database dan ketersediaan data\n";
$availableYears = $model->getAvailableYears();
echo "Tahun tersedia: " . implode(', ', $availableYears) . "\n";

$latestYear = !empty($availableYears) ? (int)$availableYears[0] : (int)date('Y');
$totalRecords = $model->countAll([]);
echo "Total records di database: {$totalRecords}\n";

// Step 2: Fetch all data
echo "\n[Step 2] Mengambil seluruh data pertanian dari database\n";
$allData = $model->getAll([]);
echo "Records yang diambil: " . count($allData) . "\n";

// Verify completeness for latest year
$latestYearRecords = $model->getAll(['tahun' => $latestYear]);
echo "Records untuk tahun {$latestYear}: " . count($latestYearRecords) . "\n";

// Step 3: Prepare export directory
$exportDir = ROOT_PATH . '/data/ksa';
if (!is_dir($exportDir)) {
    @mkdir($exportDir, 0755, true);
    echo "Direktori export dibuat: {$exportDir}\n";
} else {
    echo "Direktori export sudah ada: {$exportDir}\n";
}

// Step 4: Export full dataset to CSV
$timestamp = date('Ymd_His');
$exportFile = $exportDir . '/data_pertanian_bps_export_' . $timestamp . '.csv';

echo "\n[Step 3] Menyimpan data ke CSV: {$exportFile}\n";

$fp = fopen($exportFile, 'w');
// BOM for UTF-8 compatibility
fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header row
$header = [
    'Tahun',
    'Kabupaten/Kota',
    'Kode Wilayah',
    'Luas Panen (Ha)',
    'Produksi Gabah (Ton)',
    'Produksi Beras (Ton)',
    'Produktivitas (Ku/Ha)',
    'Sumber Data',
    'Tipe Sumber',
    'Skenario',
    'Validated',
    'Validation Notes',
    'Keterangan',
    'Dibuat Pada',
    'Diupdate Pada'
];
fputcsv($fp, $header);

$exportCount = 0;
foreach ($allData as $row) {
    $exportRow = [
        $row['tahun'],
        $row['kabupaten_kota'],
        $row['kode_wilayah'] ?? '-',
        number_format($row['luas_panen'], 2, '.', ','),
        number_format($row['produksi_gabah'], 2, '.', ','),
        number_format($row['produksi_beras'], 2, '.', ','),
        number_format($row['produktivitas'], 2, '.', ','),
        $row['sumber_data'] ?? '-',
        $row['sumber_data_type'] ?? '-',
        $row['tipe_skenario'] ?? '-',
        $row['is_validated'] ? 'Valid' : 'Invalid',
        $row['validation_notes'] ?? '-',
        $row['keterangan'] ?? '-',
        $row['created_at'] ?? '-',
        $row['updated_at'] ?? '-'
    ];
    fputcsv($fp, $exportRow);
    $exportCount++;
}
fclose($fp);
echo "Records tersimpan: {$exportCount}\n";

// Step 5: Export latest year data specifically for kabupaten/kota completeness
$latestYearFile = $exportDir . '/data_pertanian_bps_' . $latestYear . '.csv';
echo "\n[Step 4] Menyimpan data tahun {$latestYear} ke: {$latestYearFile}\n";

$fp2 = fopen($latestYearFile, 'w');
fprintf($fp2, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($fp2, $header);

$yearExportCount = 0;
foreach ($latestYearRecords as $row) {
    $exportRow = [
        $row['tahun'],
        $row['kabupaten_kota'],
        $row['kode_wilayah'] ?? '-',
        number_format($row['luas_panen'], 2, '.', ','),
        number_format($row['produksi_gabah'], 2, '.', ','),
        number_format($row['produksi_beras'], 2, '.', ','),
        number_format($row['produktivitas'], 2, '.', ','),
        $row['sumber_data'] ?? '-',
        $row['sumber_data_type'] ?? '-',
        $row['tipe_skenario'] ?? '-',
        $row['is_validated'] ? 'Valid' : 'Invalid',
        $row['validation_notes'] ?? '-',
        $row['keterangan'] ?? '-',
        $row['created_at'] ?? '-',
        $row['updated_at'] ?? '-'
    ];
    fputcsv($fp2, $exportRow);
    $yearExportCount++;
}
fclose($fp2);
echo "Records tahun {$latestYear} tersimpan: {$yearExportCount}\n";

// Step 6: Validate exported data
echo "\n[Step 5] Validasi data yang diekspor\n";

$validationReport = [
    'total_records' => $exportCount,
    'latest_year_records' => $yearExportCount,
    'latest_year' => $latestYear,
    'kabupaten_list' => [],
    'data_completeness' => [],
    'validation_errors' => [],
    'export_files' => [
        'full_export' => [
            'path' => $exportFile,
            'file_size_bytes' => filesize($exportFile)
        ],
        'latest_year_export' => [
            'path' => $latestYearFile,
            'file_size_bytes' => filesize($latestYearFile)
        ]
    ]
];

// Verify completeness for latest year
$expectedKabupaten = $model->getKabupatenList();
$exportKabupaten = array_column($latestYearRecords, 'kabupaten_kota');
$foundKabupaten = array_intersect($expectedKabupaten, $exportKabupaten);
$missingKabupaten = array_diff($expectedKabupaten, $exportKabupaten);

$validationReport['kabupaten_list']['total_expected'] = count($expectedKabupaten);
$validationReport['kabupaten_list']['total_found'] = count($foundKabupaten);
$validationReport['kabupaten_list']['total_missing'] = count($missingKabupaten);
$validationReport['kabupaten_list']['missing'] = array_values($missingKabupaten);

echo "Kabupaten expected: " . count($expectedKabupaten) . "\n";
echo "Kabupaten found in export: " . count($foundKabupaten) . "\n";
echo "Kabupaten missing: " . count($missingKabupaten) . "\n";

// Validate data integrity
$emptyFields = 0;
$nullValues = 0;
foreach ($latestYearRecords as $i => $row) {
    $fieldIssues = [];
    
    if (empty($row['kabupaten_kota'])) {
        $fieldIssues[] = 'kabupaten_kota kosong';
        $emptyFields++;
    }
    if ($row['luas_panen'] <= 0) {
        $fieldIssues[] = 'luas_panen <= 0';
        $nullValues++;
    }
    if ($row['produksi_gabah'] <= 0) {
        $fieldIssues[] = 'produksi_gabah <= 0';
        $nullValues++;
    }
    if ($row['produksi_beras'] === null || $row['produksi_beras'] <= 0) {
        $fieldIssues[] = 'produksi_beras kosong atau <= 0';
        $nullValues++;
    }
    if ($row['produktivitas'] === null || $row['produktivitas'] <= 0) {
        $fieldIssues[] = 'produktivitas kosong atau <= 0';
        $nullValues++;
    }
    
    if (!empty($fieldIssues)) {
        $validationReport['validation_errors'][] = [
            'kabupaten' => $row['kabupaten_kota'] ?? 'Unknown',
            'tahun' => $row['tahun'],
            'issues' => $fieldIssues
        ];
    }
    
    $validationReport['data_completeness'][$row['kabupaten_kota']] = [
        'luas_panen' => $row['luas_panen'] !== null ? 'OK' : 'NULL',
        'produksi_gabah' => $row['produksi_gabah'] !== null ? 'OK' : 'NULL',
        'produksi_beras' => $row['produksi_beras'] !== null ? 'OK' : 'NULL',
        'produktivitas' => $row['produktivitas'] !== null ? 'OK' : 'NULL',
        'sumber_data' => $row['sumber_data'] ?? 'N/A',
    ];
}

echo "Empty fields: {$emptyFields}\n";
echo "Null/zero values: {$nullValues}\n";
echo "Validation errors: " . count($validationReport['validation_errors']) . "\n";

// Verify CSV readability
echo "\n[Step 6] Verifikasi integritas file CSV\n";
$csvRows = array_map('str_getcsv', file($exportFile));
echo "CSV rows (incl header): " . count($csvRows) . "\n";
echo "CSV header: " . implode(' | ', $csvRows[0]) . "\n";
echo "CSV sample record: " . implode(' | ', array_slice($csvRows[1], 0, 5)) . "\n";

// Verify file sizes
echo "\nFile sizes:\n";
echo "  Full export: " . number_format(filesize($exportFile) / 1024, 2) . " KB\n";
echo "  Latest year export: " . number_format(filesize($latestYearFile) / 1024, 2) . " KB\n";

// Save validation report
$reportFile = $exportDir . '/export_validation_' . $timestamp . '.json';
file_put_contents($reportFile, json_encode($validationReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\n[Step 7] Validation report saved to: {$reportFile}\n";

echo "\n=== EXPORT COMPLETE ===\n";
echo "End time: " . date('Y-m-d H:i:s') . "\n";
echo "Files created:\n";
echo "  1. {$exportFile}\n";
echo "  2. {$latestYearFile}\n";
echo "  3. {$reportFile}\n";
echo "\nData summary:\n";
echo "  - Full export: {$exportCount} records across " . count($availableYears) . " years\n";
echo "  - Latest year ({$latestYear}) export: {$yearExportCount} records\n";
echo "  - Completeness: {$validationReport['kabupaten_list']['total_found']}/{$validationReport['kabupaten_list']['total_expected']} kabupaten\n";
echo "  - Validation: " . (empty($validationReport['validation_errors']) ? 'PASSED - No issues' : 'FAILED - ' . count($validationReport['validation_errors']) . ' issues') . "\n";
