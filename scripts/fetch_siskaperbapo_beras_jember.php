<?php
/**
 * Script Pengambilan Data Harga Beras SISKAPERBAPO Jatim
 * Khusus Kabupaten Jember
 * 
 * Penggunaan via CLI:
 *   php scripts/fetch_siskaperbapo_beras_jember.php [--date=YYYY-MM-DD] [--jenis=medium|premium|all]
 * 
 * Contoh:
 *   php scripts/fetch_siskaperbapo_beras_jember.php
 *   php scripts/fetch_siskaperbapo_beras_jember.php --date=2026-08-04 --jenis=all
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';

// Safe execution for CLI
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_RUN')) {
    die("Access denied: Script ini hanya dapat dijalankan melalui CLI atau cron task.\n");
}

// ----------------------------------------------------
// 1. Argument Parsing
// ----------------------------------------------------
$options = getopt('', ['date:', 'jenis:']);
$targetDate = $options['date'] ?? date('Y-m-d');
$jenisParam = strtolower($options['jenis'] ?? 'medium');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
    echo "[ERROR] Format tanggal tidak valid. Gunakan YYYY-MM-DD (contoh: 2026-08-04)\n";
    exit(1);
}

// Validate jenis parameter
$validJenis = ['medium', 'premium', 'all'];
if (!in_array($jenisParam, $validJenis, true)) {
    echo "[ERROR] Parameter jenis tidak valid. Pilihan: medium, premium, all\n";
    exit(1);
}

// Komoditas mapping SISKAPERBAPO
// 4 = Beras Medium / kg, 2 = Beras Premium / kg
$komoditasMap = [
    'medium'  => ['id' => 4, 'label' => 'Beras Medium / kg'],
    'premium' => ['id' => 2, 'label' => 'Beras Premium / kg']
];

$processList = ($jenisParam === 'all') ? ['medium', 'premium'] : [$jenisParam];

// Ensure table exists in database
$db = Database::getInstance()->getConnection();
ensureDatabaseTables($db);

$hasError = false;

foreach ($processList as $jenisKey) {
    $komoditasInfo = $komoditasMap[$jenisKey];
    
    // ----------------------------------------------------
    // 2. Fetch Data from SISKAPERBAPO
    // ----------------------------------------------------
    $rawResponse = fetchHtmlForKomoditas($komoditasInfo['id'], $targetDate);
    if (empty($rawResponse)) {
        echo "[ERROR] Gagal mengambil respon dari SISKAPERBAPO untuk jenis {$jenisKey}\n";
        $hasError = true;
        continue;
    }

    // ----------------------------------------------------
    // 3. Parse Data for Kabupaten Jember
    // ----------------------------------------------------
    $parsedData = parseHargaKabupatenJember($rawResponse);
    
    if (!$parsedData['found']) {
        echo "[ERROR] Data Kabupaten Jember tidak ditemukan di tabel SISKAPERBAPO untuk jenis '{$jenisKey}' pada tanggal {$targetDate}.\n";
        $hasError = true;
        continue;
    }

    $hargaKab  = $parsedData['harga_kab'];
    $hargaProv = $parsedData['harga_prov'];
    $dataDate  = !empty($parsedData['tanggal']) ? $parsedData['tanggal'] : $targetDate;

    // ----------------------------------------------------
    // 4. Validation & Safeguards (5.000 - 30.000 IDR/kg)
    // ----------------------------------------------------
    if ($hargaKab < 5000 || $hargaKab > 30000) {
        echo "[ERROR] Harga Kabupaten Jember ({$hargaKab}) di luar rentang wajar (5000 - 30000 IDR/kg). Data diabaikan.\n";
        $hasError = true;
        continue;
    }

    // ----------------------------------------------------
    // 5. Save Data to Database
    // ----------------------------------------------------
    $record = [
        'tanggal'         => $dataDate,
        'jenis'           => $jenisKey,
        'harga_kabupaten' => $hargaKab,
        'harga_provinsi'  => $hargaProv,
        'sumber'          => 'SISKAPERBAPO'
    ];

    try {
        saveHargaBeras($db, $record);
        
        // Print standardized summary output to stdout
        // Format: "YYYY-MM-DD jenis Kab Jember X, Jatim Y"
        $provText = ($hargaProv !== null) ? "Jatim {$hargaProv}" : "Jatim N/A";
        echo "{$dataDate} {$jenisKey} Kab Jember {$hargaKab}, {$provText}\n";
        
    } catch (Exception $e) {
        echo "[ERROR] Gagal menyimpan data ke database: " . $e->getMessage() . "\n";
        $hasError = true;
    }
}

if ($hasError) {
    exit(1);
}
exit(0);

// =========================================================================
// HELPER FUNCTIONS
// =========================================================================

/**
 * Fetch HTML/JSON response from SISKAPERBAPO Jatim
 *
 * @param int $komoditasId
 * @param string $tanggal
 * @return string
 */
function fetchHtmlForKomoditas(int $komoditasId, string $tanggal): string {
    // API endpoint per peta/tabel SISKAPERBAPO
    $apiUrl = "https://siskaperbapo.jatimprov.go.id/home2/getDataMap/?tanggal=" . urlencode($tanggal) . "&komoditas=" . $komoditasId;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) JAGAPADI-SiskaperbapoClient/1.0 (PHP)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'X-Requested-With: XMLHttpRequest'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $httpCode === 200 && !empty($response)) {
        return $response;
    }

    // Fallback: Fetch main page directly
    $fallbackUrl = "https://siskaperbapo.jatimprov.go.id/?tanggal=" . urlencode($tanggal) . "&komoditas=" . $komoditasId;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $fallbackUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) JAGAPADI-SiskaperbapoClient/1.0 (PHP)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response ? $response : '';
}

/**
 * Parse JSON or HTML response from SISKAPERBAPO to extract Jember & Jatim prices
 *
 * @param string $content
 * @return array ['found' => bool, 'harga_kab' => int, 'harga_prov' => int|null, 'tanggal' => string]
 */
function parseHargaKabupatenJember(string $content): array {
    $result = [
        'found'     => false,
        'harga_kab' => 0,
        'harga_prov'=> null,
        'tanggal'   => ''
    ];

    // Attempt 1: Parse JSON response from getDataMap
    $jsonData = @json_decode($content, true);
    if (is_array($jsonData) && isset($jsonData['data']) && is_array($jsonData['data'])) {
        
        // Extract Jatim Average
        if (isset($jsonData['avg']) && is_numeric($jsonData['avg'])) {
            $result['harga_prov'] = (int)$jsonData['avg'];
        }

        // Extract date from info text if present (e.g. "tanggal 2026-08-04")
        if (!empty($jsonData['info']) && preg_match('/tanggal\s+(\d{4}-\d{2}-\d{2})/i', $jsonData['info'], $dateMatch)) {
            $result['tanggal'] = $dateMatch[1];
        }

        // Find Kabupaten Jember
        foreach ($jsonData['data'] as $elem) {
            $namaKab = $elem['nama'] ?? '';
            if (stripos($namaKab, 'Jember') !== false) {
                $val = $elem['hrg'] ?? 0;
                $cleanVal = cleanPriceNumber($val);
                if ($cleanVal > 0) {
                    $result['harga_kab'] = $cleanVal;
                    $result['found']     = true;
                    return $result;
                }
            }
        }
    }

    // Attempt 2: Parse HTML content using DOMDocument & DOMXPath
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML($content);
    $xpath = new DOMXPath($dom);

    // Look for rows containing "Jember" in table
    $nodes = $xpath->query("//tr[td[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'jember')]]");
    if ($nodes && $nodes->length > 0) {
        foreach ($nodes as $rowNode) {
            $tds = $rowNode->getElementsByTagName('td');
            if ($tds->length >= 2) {
                $rawKabPrice = $tds->item(1)->nodeValue;
                $cleanKabPrice = cleanPriceNumber($rawKabPrice);

                if ($cleanKabPrice > 0) {
                    $result['harga_kab'] = $cleanKabPrice;
                    $result['found']     = true;

                    if ($tds->length >= 3) {
                        $rawProvPrice = $tds->item(2)->nodeValue;
                        $cleanProvPrice = cleanPriceNumber($rawProvPrice);
                        if ($cleanProvPrice > 0) {
                            $result['harga_prov'] = $cleanProvPrice;
                        }
                    }
                    break;
                }
            }
        }
    }

    // Extract Province average from HTML body if not found yet
    if ($result['found'] && $result['harga_prov'] === null) {
        if (preg_match('/harga\s+rata-rata\s+Jawa\s+Timur\s+adalah\s+<b>\s*Rp?\s*([\d\.,]+)/i', $content, $provMatch)) {
            $result['harga_prov'] = cleanPriceNumber($provMatch[1]);
        }
    }

    return $result;
}

/**
 * Clean price text/number into integer (e.g. "Rp12.060" -> 12060)
 *
 * @param mixed $price
 * @return int
 */
function cleanPriceNumber($price): int {
    if (is_numeric($price)) {
        return (int)$price;
    }
    // Remove "Rp", whitespace, and thousand separators
    $cleaned = preg_replace('/[^\d]/', '', (string)$price);
    return (int)$cleaned;
}

/**
 * Save / Upsert rice price record into database (harga_beras_siskaperbapo & harga_komoditas)
 *
 * @param PDO $db
 * @param array $record
 * @return void
 */
function saveHargaBeras(PDO $db, array $record): void {
    // 1. Primary Table: harga_beras_siskaperbapo
    $sqlSiskaperbapo = "INSERT INTO harga_beras_siskaperbapo 
            (tanggal, jenis, harga_kabupaten, harga_provinsi, sumber) 
        VALUES 
            (:tanggal, :jenis, :harga_kabupaten, :harga_provinsi, :sumber)
        ON DUPLICATE KEY UPDATE 
            harga_kabupaten = VALUES(harga_kabupaten),
            harga_provinsi = VALUES(harga_provinsi),
            sumber = VALUES(sumber),
            updated_at = CURRENT_TIMESTAMP";

    $stmt = $db->prepare($sqlSiskaperbapo);
    $stmt->execute([
        ':tanggal'         => $record['tanggal'],
        ':jenis'           => $record['jenis'],
        ':harga_kabupaten' => $record['harga_kabupaten'],
        ':harga_provinsi'  => $record['harga_provinsi'],
        ':sumber'          => $record['sumber']
    ]);

    // 2. Integration Table: harga_komoditas (if table exists in JAGAPADI)
    try {
        $jenisKomoditas = ($record['jenis'] === 'medium') ? 'beras_medium' : 'beras_premium';
        $namaKomoditas  = ($record['jenis'] === 'medium') ? 'Beras Medium' : 'Beras Premium';
        
        $sqlKomoditas = "INSERT INTO harga_komoditas 
            (tanggal, lokasi, kode_wilayah, jenis_komoditas, nama_komoditas, harga, harga_min, harga_max, satuan, sumber_data, keterangan)
        VALUES 
            (:tanggal, 'Jember', '35.09', :jenis_komoditas, :nama_komoditas, :harga, :harga_min, :harga_max, 'kg', :sumber_data, :keterangan)
        ON DUPLICATE KEY UPDATE 
            harga = VALUES(harga),
            sumber_data = VALUES(sumber_data),
            keterangan = VALUES(keterangan),
            updated_at = CURRENT_TIMESTAMP";
            
        $stmtKomoditas = $db->prepare($sqlKomoditas);
        $stmtKomoditas->execute([
            ':tanggal'         => $record['tanggal'],
            ':jenis_komoditas' => $jenisKomoditas,
            ':nama_komoditas'  => $namaKomoditas,
            ':harga'           => $record['harga_kabupaten'],
            ':harga_min'       => $record['harga_kabupaten'],
            ':harga_max'       => $record['harga_kabupaten'],
            ':sumber_data'     => 'SISKAPERBAPO Jatim',
            ':keterangan'      => 'Harga resmi SISKAPERBAPO Jatim (Provinsi: Rp' . number_format($record['harga_provinsi'] ?? 0, 0, ',', '.') . ')'
        ]);
    } catch (Exception $ex) {
        // Silently skip if table structure differs
    }
}

/**
 * Ensure database tables exist
 *
 * @param PDO $db
 * @return void
 */
function ensureDatabaseTables(PDO $db): void {
    $sql = "CREATE TABLE IF NOT EXISTS `harga_beras_siskaperbapo` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `tanggal` DATE NOT NULL,
        `jenis` ENUM('medium', 'premium') NOT NULL,
        `harga_kabupaten` INT NOT NULL,
        `harga_provinsi` INT NULL,
        `sumber` VARCHAR(50) DEFAULT 'SISKAPERBAPO',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_tanggal_jenis` (`tanggal`, `jenis`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sql);
}
