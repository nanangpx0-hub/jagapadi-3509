<?php
/**
 * Script Pengujian Import KSA Ubinan ke BPS Scraper
 * Jalankan dari CLI: php run_import_test.php
 *
 * Script ini melakukan:
 * 1. Tes koneksi database
 * 2. Verifikasi tabel data_pertanian_bps tersedia
 * 3. Validasi file CSV test batch
 * 4. Dry-run audit 5 baris test
 * 5. Eksekusi import test batch
 * 6. Validasi post-import via SQL
 */

declare(strict_types=1);

// ============================================================
// BOOTSTRAP
// ============================================================
define('ROOT_PATH', dirname(__DIR__, 2));
define('CLI_MODE', true);

// Load environment variables
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

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function printHeader(string $title): void {
    $line = str_repeat('=', 60);
    echo "\n{$line}\n  {$title}\n{$line}\n";
}

function printStep(string $step): void {
    echo "\n[STEP] {$step}\n";
}

function printOk(string $msg): void {
    echo "  ✓ {$msg}\n";
}

function printWarn(string $msg): void {
    echo "  ⚠ {$msg}\n";
}

function printError(string $msg): void {
    echo "  ✗ {$msg}\n";
}

function printInfo(string $msg): void {
    echo "  → {$msg}\n";
}

// ============================================================
// STEP 1: TEST DATABASE CONNECTION
// ============================================================
printHeader('STEP 1: Koneksi Database');

try {
    $db = Database::getInstance()->getConnection();
    printOk('Koneksi ke database berhasil');

    $stmt = $db->query("SELECT DATABASE() as db, VERSION() as ver");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    printInfo("Database: {$row['db']}");
    printInfo("MySQL Version: {$row['ver']}");
} catch (Exception $e) {
    printError('Gagal konek ke database: ' . $e->getMessage());
    exit(1);
}

// ============================================================
// STEP 2: VERIFY TABLE EXISTS
// ============================================================
printHeader('STEP 2: Verifikasi Tabel data_pertanian_bps');

try {
    $stmt = $db->query("SHOW TABLES LIKE 'data_pertanian_bps'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        printOk('Tabel data_pertanian_bps sudah ada');

        $stmt = $db->query("SELECT COUNT(*) as total FROM data_pertanian_bps");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        printInfo("Records saat ini: {$count['total']}");

        $stmt = $db->query("SELECT tahun, COUNT(*) as cnt FROM data_pertanian_bps GROUP BY tahun ORDER BY tahun DESC LIMIT 5");
        $years = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($years)) {
            printInfo("Data per tahun:");
            foreach ($years as $y) {
                echo "     Tahun {$y['tahun']}: {$y['cnt']} kabupaten\n";
            }
        }
    } else {
        printWarn('Tabel data_pertanian_bps belum ada, akan dibuat saat pertama kali akses');
    }

    // Check logs table
    $stmt = $db->query("SHOW TABLES LIKE 'bps_scraping_logs'");
    $logsExists = $stmt->rowCount() > 0;
    if ($logsExists) {
        printOk('Tabel bps_scraping_logs sudah ada');
    } else {
        printWarn('Tabel bps_scraping_logs belum ada');
    }

} catch (Exception $e) {
    printError('Error saat verifikasi tabel: ' . $e->getMessage());
}

// ============================================================
// STEP 3: VALIDATE CSV FILE
// ============================================================
printHeader('STEP 3: Validasi File CSV Test Batch');

$csvFile = __DIR__ . '/test_batch_ksa_ubinan.csv';

if (!file_exists($csvFile)) {
    printError("File tidak ditemukan: {$csvFile}");
    exit(1);
}
printOk("File CSV ditemukan: test_batch_ksa_ubinan.csv");
printInfo("Ukuran: " . number_format(filesize($csvFile)) . " bytes");

// Parse CSV
$data = [];
$headers = [];
if (($handle = fopen($csvFile, 'r')) !== false) {
    $rowNum = 0;
    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        if ($rowNum === 0) {
            $headers = array_map('strtolower', array_map('trim', $row));
            printOk("Header kolom: " . implode(', ', $headers));
        } else {
            if (!empty(array_filter($row))) {
                $data[] = array_combine($headers, $row);
            }
        }
        $rowNum++;
    }
    fclose($handle);
}

printInfo("Jumlah baris data: " . count($data));

// ============================================================
// STEP 4: AUDIT DAN DRY-RUN VALIDASI
// ============================================================
printHeader('STEP 4: Audit & Dry-Run Validasi Data');

// Define Jawa Timur kabupaten list
$validKabupaten = [
    'Bangkalan', 'Banyuwangi', 'Blitar', 'Bojonegoro', 'Bondowoso',
    'Gresik', 'Jember', 'Jombang', 'Kediri', 'Kota Batu', 'Kota Blitar',
    'Kota Kediri', 'Kota Madiun', 'Kota Malang', 'Kota Mojokerto',
    'Kota Pasuruan', 'Kota Probolinggo', 'Kota Surabaya', 'Lamongan',
    'Lumajang', 'Madiun', 'Magetan', 'Malang', 'Mojokerto', 'Nganjuk',
    'Ngawi', 'Pacitan', 'Pamekasan', 'Pasuruan', 'Ponorogo',
    'Probolinggo', 'Sampang', 'Sidoarjo', 'Situbondo', 'Sumenep',
    'Trenggalek', 'Tuban', 'Tulungagung'
];

$auditErrors = [];
$auditWarnings = [];
$auditOk = [];

foreach ($data as $idx => $row) {
    $rowNum = $idx + 2;
    $rowErrors = [];
    $rowWarnings = [];

    // Validate tahun
    $tahun = intval($row['tahun'] ?? 0);
    if ($tahun < 2019 || $tahun > 2030) {
        $rowErrors[] = "Tahun '{$row['tahun']}' tidak valid (harus 2019-2030)";
    }

    // Validate kabupaten_kota
    $kabupaten = trim($row['kabupaten_kota'] ?? '');
    if (strlen($kabupaten) < 3) {
        $rowErrors[] = "Nama kabupaten kosong atau terlalu pendek";
    } elseif (!in_array($kabupaten, $validKabupaten)) {
        $rowWarnings[] = "Kabupaten '{$kabupaten}' tidak ada dalam daftar standar 38 Kab/Kota Jatim";
    }

    // Validate luas_panen
    $luasPanen = floatval(str_replace(['.', ','], ['', '.'], $row['luas_panen'] ?? '0'));
    if ($luasPanen < 0) {
        $rowErrors[] = "Luas panen negatif ({$luasPanen})";
    } elseif ($luasPanen === 0.0) {
        $rowWarnings[] = "Luas panen = 0";
    }

    // Validate produksi_gabah
    $produksiGabah = floatval(str_replace(['.', ','], ['', '.'], $row['produksi_gabah'] ?? '0'));
    if ($produksiGabah < 0) {
        $rowErrors[] = "Produksi gabah negatif ({$produksiGabah})";
    } elseif ($produksiGabah === 0.0) {
        $rowWarnings[] = "Produksi gabah = 0";
    }

    // Auto-calculate values
    $produksiBeras = $produksiGabah > 0 ? round($produksiGabah * 0.577, 2) : 0;
    $produktivitas = ($luasPanen > 0 && $produksiGabah > 0) ? round(($produksiGabah / $luasPanen) * 10, 2) : 0;

    // Check productivity range
    if ($produktivitas > 100) {
        $rowWarnings[] = "Produktivitas sangat tinggi ({$produktivitas} ku/ha)";
    } elseif ($produktivitas > 0 && $produktivitas < 20) {
        $rowWarnings[] = "Produktivitas sangat rendah ({$produktivitas} ku/ha)";
    }

    if (empty($rowErrors)) {
        $auditOk[] = [
            'row' => $rowNum,
            'kabupaten' => $kabupaten,
            'tahun' => $tahun,
            'luas_panen' => $luasPanen,
            'produksi_gabah' => $produksiGabah,
            'produksi_beras_calc' => $produksiBeras,
            'produktivitas_calc' => $produktivitas,
        ];
    }

    if (!empty($rowErrors)) {
        foreach ($rowErrors as $err) {
            $auditErrors[] = "Baris {$rowNum}: {$err}";
        }
    }
    if (!empty($rowWarnings)) {
        foreach ($rowWarnings as $warn) {
            $auditWarnings[] = "Baris {$rowNum}: {$warn}";
        }
    }
}

// Print audit results
printInfo("Total baris valid: " . count($auditOk));
printInfo("Total baris error: " . count($auditErrors));
printInfo("Total warnings: " . count($auditWarnings));

if (!empty($auditErrors)) {
    echo "\n  ERRORS:\n";
    foreach ($auditErrors as $err) {
        printError($err);
    }
}

if (!empty($auditWarnings)) {
    echo "\n  WARNINGS:\n";
    foreach ($auditWarnings as $warn) {
        printWarn($warn);
    }
}

echo "\n  PREVIEW DATA VALID:\n";
printf("  %-6s %-20s %-6s %12s %15s %15s %10s\n",
    "Baris", "Kabupaten", "Tahun", "Luas(Ha)", "Gabah(Ton)", "Beras(Ton)", "Prod(Ku/Ha)"
);
echo "  " . str_repeat('-', 88) . "\n";
foreach ($auditOk as $r) {
    printf("  %-6d %-20s %-6d %12s %15s %15s %10s\n",
        $r['row'],
        $r['kabupaten'],
        $r['tahun'],
        number_format($r['luas_panen'], 0),
        number_format($r['produksi_gabah'], 0),
        number_format($r['produksi_beras_calc'], 2),
        number_format($r['produktivitas_calc'], 2)
    );
}

// ============================================================
// STEP 5: EKSEKUSI IMPORT TEST BATCH
// ============================================================
printHeader('STEP 5: Eksekusi Import Test Batch ke Database');

if (empty($auditOk)) {
    printError('Tidak ada data valid untuk diimport. Periksa file CSV dan ulangi.');
    exit(1);
}

$importSuccess = 0;
$importFailed = 0;
$importSkipped = 0;
$importErrors = [];

// Ensure table exists
try {
    $createTableSql = "CREATE TABLE IF NOT EXISTS data_pertanian_bps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tahun INT NOT NULL,
        kabupaten_kota VARCHAR(100) NOT NULL,
        kode_wilayah VARCHAR(20),
        luas_panen DECIMAL(15,2) COMMENT 'dalam hektar',
        produksi_gabah DECIMAL(15,2) COMMENT 'dalam ton',
        produksi_beras DECIMAL(15,2) COMMENT 'dalam ton',
        produktivitas DECIMAL(10,2) COMMENT 'kuintal/ha',
        sumber_data VARCHAR(100),
        sumber_data_type ENUM('simulasi','resmi_webapi','manual') DEFAULT 'simulasi',
        tipe_skenario ENUM('baseline','optimis','pesimis') DEFAULT 'baseline',
        is_validated TINYINT(1) DEFAULT 0,
        validation_notes TEXT,
        keterangan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tahun (tahun),
        INDEX idx_kabupaten (kabupaten_kota),
        UNIQUE KEY unique_data (tahun, kabupaten_kota)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($createTableSql);
    printOk("Tabel data_pertanian_bps siap");
} catch (Exception $e) {
    printWarn("Tabel mungkin sudah ada: " . $e->getMessage());
}

// Import each valid row
$insertSql = "INSERT INTO data_pertanian_bps 
    (tahun, kabupaten_kota, kode_wilayah, luas_panen, produksi_gabah, produksi_beras, 
     produktivitas, sumber_data, sumber_data_type, tipe_skenario, is_validated, keterangan)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        luas_panen = VALUES(luas_panen),
        produksi_gabah = VALUES(produksi_gabah),
        produksi_beras = VALUES(produksi_beras),
        produktivitas = VALUES(produktivitas),
        sumber_data = VALUES(sumber_data),
        keterangan = VALUES(keterangan),
        updated_at = CURRENT_TIMESTAMP";

$stmt = $db->prepare($insertSql);

foreach ($auditOk as $record) {
    try {
        // Get kode_wilayah from original data
        $originalRow = $data[$record['row'] - 2]; // -2 for 0-index and header offset
        $kodeWilayah = trim($originalRow['kode_wilayah'] ?? '');

        $result = $stmt->execute([
            $record['tahun'],
            $record['kabupaten'],
            $kodeWilayah ?: null,
            $record['luas_panen'],
            $record['produksi_gabah'],
            $record['produksi_beras_calc'],
            $record['produktivitas_calc'],
            'KSA Ubinan 2025',
            'manual',
            'baseline',
            1,
            'Import dari KSA Ubinan pada-2025.pdf via CLI test'
        ]);

        if ($result) {
            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                printOk("Import: {$record['kabupaten']} ({$record['tahun']}) — " .
                    ($rowsAffected === 2 ? 'UPDATED (duplikat)' : 'INSERTED baru'));
                $importSuccess++;
            } else {
                $importSkipped++;
                printWarn("Skipped (tidak ada perubahan): {$record['kabupaten']} ({$record['tahun']})");
            }
        } else {
            $importFailed++;
            $importErrors[] = "Gagal insert: {$record['kabupaten']}";
        }

    } catch (Exception $e) {
        $importFailed++;
        $importErrors[] = "Error {$record['kabupaten']}: " . $e->getMessage();
        printError("Error: {$record['kabupaten']} — " . $e->getMessage());
    }
}

// ============================================================
// STEP 6: VALIDASI POST-IMPORT
// ============================================================
printHeader('STEP 6: Validasi Post-Import');

// Count records imported
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM data_pertanian_bps WHERE tahun = 2025 AND keterangan LIKE '%KSA Ubinan%'");
$stmt->execute();
$imported = $stmt->fetch(PDO::FETCH_ASSOC);
printInfo("Records tahun 2025 KSA Ubinan di DB: {$imported['cnt']}");

// Get statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT kabupaten_kota) as jumlah_kabupaten,
        SUM(luas_panen) as total_luas,
        SUM(produksi_gabah) as total_gabah,
        SUM(produksi_beras) as total_beras,
        ROUND(AVG(produktivitas), 2) as avg_produktivitas
    FROM data_pertanian_bps 
    WHERE tahun = 2025 AND keterangan LIKE '%KSA Ubinan%'
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n  STATISTIK DATA HASIL IMPORT:\n";
printf("  %-30s: %s\n", "Jumlah Kabupaten/Kota", $stats['jumlah_kabupaten']);
printf("  %-30s: %s Ha\n", "Total Luas Panen", number_format((float)($stats['total_luas'] ?? 0), 2));
printf("  %-30s: %s Ton\n", "Total Produksi Gabah", number_format((float)($stats['total_gabah'] ?? 0), 2));
printf("  %-30s: %s Ton\n", "Total Produksi Beras", number_format((float)($stats['total_beras'] ?? 0), 2));
printf("  %-30s: %s Ku/Ha\n", "Rata-rata Produktivitas", $stats['avg_produktivitas'] ?? 0);

// Check for anomalies
$stmt = $db->prepare("
    SELECT kabupaten_kota, produktivitas 
    FROM data_pertanian_bps 
    WHERE tahun = 2025 AND (produktivitas > 100 OR produktivitas < 20)
    AND keterangan LIKE '%KSA Ubinan%'
");
$stmt->execute();
$anomalies = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($anomalies)) {
    echo "\n  ANOMALI PRODUKTIVITAS:\n";
    foreach ($anomalies as $a) {
        printWarn("{$a['kabupaten_kota']}: {$a['produktivitas']} Ku/Ha");
    }
} else {
    printOk("Tidak ada anomali produktivitas");
}

// ============================================================
// SUMMARY
// ============================================================
printHeader('RINGKASAN HASIL');

echo "  Import Test Batch:\n";
printf("  %-25s: %d\n", "Total baris diproses", count($data));
printf("  %-25s: %d\n", "Baris valid (audit)", count($auditOk));
printf("  %-25s: %d\n", "Berhasil diimport", $importSuccess);
printf("  %-25s: %d\n", "Dilewati (no change)", $importSkipped);
printf("  %-25s: %d\n", "Gagal", $importFailed);

if (!empty($importErrors)) {
    echo "\n  Import Errors:\n";
    foreach ($importErrors as $err) {
        printError($err);
    }
}

echo "\n";
if ($importFailed === 0 && $importSuccess > 0) {
    printOk("TEST BATCH BERHASIL. Siap untuk full import dari PDF.");
    echo "\n  LANGKAH SELANJUTNYA:\n";
    printInfo("1. Konversi pada-2025.pdf ke Excel/CSV");
    printInfo("2. Normalisasi nama kabupaten (38 kabupaten Jawa Timur)");
    printInfo("3. Upload via web interface: http://localhost/jagapadi-3509/bpsScraper");
    printInfo("4. Gunakan fitur 'Import Excel' dengan dataType = data_pertanian_bps");
} elseif ($importFailed > 0) {
    printError("TEST BATCH GAGAL SEBAGIAN. Periksa error di atas sebelum full import.");
} else {
    printWarn("TEST BATCH: Tidak ada record baru (mungkin semua sudah ada).");
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "  Selesai: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";
