<?php
/**
 * Skrip Pengambil & Pengimpor Data Kecepatan Angin Harian (WS10M, WS2M) NASA POWER API - Kabupaten Jember
 * 
 * Penjelasan Skrip:
 * Skrip CLI PHP ini berfungsi untuk mengambil data kecepatan angin harian (kecepatan 10m & 2m di atas permukaan tanah dalam m/s)
 * dari NASA POWER Daily Point API untuk wilayah Kabupaten Jember, kemudian menyimpan/memperbarui data tersebut ke database MySQL
 * (tabel cuaca_angin_jember) dengan prinsip idempotensi (INSERT ... ON DUPLICATE KEY UPDATE).
 * 
 * Contoh Pemakaian CLI:
 * 1. Default (Pengambilan data kemarin WIB, mode single pusat kabupaten):
 *    php scripts/fetch_nasa_wind_jember.php
 * 
 * 2. Mode single pusat kabupaten untuk periode historis:
 *    php scripts/fetch_nasa_wind_jember.php --mode=single --start=20240101 --end=20240131
 * 
 * 3. Mode multi-titik (seluruh kecamatan dari master_kecamatan) untuk periode tertentu:
 *    php scripts/fetch_nasa_wind_jember.php --mode=multi --start=20250101 --end=20250131
 * 
 * Contoh Baris Cron (menjalankan otomatis setiap hari jam 06:00 WIB):
 * 0 6 * * * php /path/to/jagapadi-3509/scripts/fetch_nasa_wind_jember.php --mode=multi >> /path/to/jagapadi-3509/storage/logs/fetch_nasa_wind_jember.log 2>&1
 */

set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

define('ROOT_PATH', dirname(__DIR__));

// Load Database Singleton
require_once ROOT_PATH . '/app/core/Database.php';

/**
 * Tulis log ke stdout dan file log storage/logs/fetch_nasa_wind_jember.log
 * 
 * @param string $msg
 * @param string $level
 */
function logMessage(string $msg, string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');
    $formattedMsg = "[{$timestamp}] [{$level}] {$msg}";
    echo $formattedMsg . "\n";

    $logDir = ROOT_PATH . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/fetch_nasa_wind_jember.log';
    @file_put_contents($logFile, $formattedMsg . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Membuat tabel cuaca_angin_jember jika belum ada
 * 
 * @param PDO $db
 */
function ensureTableExists(PDO $db): void {
    $sql = "CREATE TABLE IF NOT EXISTS `cuaca_angin_jember` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `id_kecamatan` INT NULL,
        `latitude` DECIMAL(8,5) NOT NULL,
        `longitude` DECIMAL(8,5) NOT NULL,
        `tanggal` DATE NOT NULL,
        `ws10m_ms` DECIMAL(5,2) NOT NULL,
        `ws2m_ms` DECIMAL(5,2) NULL,
        `sumber` VARCHAR(50) DEFAULT 'NASA_POWER',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_wind_loc` (`id_kecamatan`, `tanggal`, `latitude`, `longitude`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sql);
}

/**
 * Menyusun URL endpoint NASA POWER Daily Point API
 * 
 * @param float $latitude
 * @param float $longitude
 * @param string $start Format YYYYMMDD
 * @param string $end Format YYYYMMDD
 * @return string
 */
function buildUrl(float $latitude, float $longitude, string $start, string $end): string {
    $params = [
        'parameters' => 'WS10M,WS2M',
        'community'  => 'AG',
        'longitude'  => $longitude,
        'latitude'   => $latitude,
        'start'      => $start,
        'end'        => $end,
        'format'     => 'JSON'
    ];
    return "https://power.larc.nasa.gov/api/temporal/daily/point?" . http_build_query($params);
}

/**
 * Mengambil JSON dari NASA POWER API menggunakan cURL dengan retry 3 kali (backoff 2, 4, 8 detik)
 * 
 * @param string $url
 * @return array|null
 */
function fetchNasaPowerJson(string $url): ?array {
    $maxRetries = 3;
    $backoffs = [2, 4, 8];

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'JAGAPADI-WindFetcher/1.0 (PHP-cURL)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlErrno === 0 && $httpCode === 200 && !empty($response)) {
            $jsonData = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (!isset($jsonData['properties']['parameter']['WS10M'])) {
                    logMessage("Error format baru API NASA POWER: 'properties.parameter.WS10M' tidak ditemukan pada response", 'ERROR');
                    return null;
                }
                return $jsonData;
            } else {
                logMessage("Attempt {$attempt}: Format JSON response tidak valid dari NASA POWER API", 'WARNING');
            }
        } else {
            logMessage("Attempt {$attempt}/{$maxRetries} gagal (HTTP Status: {$httpCode}, cURL error {$curlErrno}: {$curlError})", 'WARNING');
        }

        if ($attempt < $maxRetries) {
            $sleepSec = $backoffs[$attempt - 1] ?? 2;
            logMessage("Menunggu {$sleepSec} detik sebelum mencoba kembali (retry #{$attempt})...", 'INFO');
            sleep($sleepSec);
        }
    }

    logMessage("Gagal mengambil data dari NASA POWER API setelah {$maxRetries} kali percobaan.", 'ERROR');
    return null;
}

/**
 * Parsing response JSON NASA POWER ke struktur array data kecepatan angin
 * 
 * @param array $jsonData
 * @param float $lat
 * @param float $lon
 * @param int|null $kecId
 * @param array $stats
 * @return array
 */
function parseWindResponse(array $jsonData, float $lat, float $lon, ?int $kecId = null, array &$stats = []): array {
    $records = [];
    $ws10mData = $jsonData['properties']['parameter']['WS10M'] ?? [];
    $ws2mData  = $jsonData['properties']['parameter']['WS2M'] ?? [];

    foreach ($ws10mData as $rawDate => $val10m) {
        // Skip nilai missing indicator (-999) atau kosong
        if ($val10m === null || $val10m == -999 || $val10m === '') {
            $stats['skipped_999'] = ($stats['skipped_999'] ?? 0) + 1;
            continue;
        }

        $ws10mFloat = floatval($val10m);

        // Validasi jangkauan kecepatan angin (0 - 50 m/s)
        if ($ws10mFloat < 0.0 || $ws10mFloat > 50.0) {
            logMessage("Anomali terdeteksi! Tanggal {$rawDate}: WS10M = {$ws10mFloat} m/s di luar jangkauan 0-50 m/s. Record di-skip.", 'WARNING');
            $stats['anomalies'] = ($stats['anomalies'] ?? 0) + 1;
            continue;
        }

        $val2m = $ws2mData[$rawDate] ?? null;
        $ws2mFloat = ($val2m !== null && $val2m != -999 && $val2m !== '') ? floatval($val2m) : null;

        // Ubah key YYYYMMDD ke format YYYY-MM-DD
        $rawDateStr = (string)$rawDate;
        if (strlen($rawDateStr) === 8) {
            $formattedDate = substr($rawDateStr, 0, 4) . '-' . substr($rawDateStr, 4, 2) . '-' . substr($rawDateStr, 6, 2);
        } else {
            $dt = DateTime::createFromFormat('Ymd', $rawDateStr);
            $formattedDate = $dt ? $dt->format('Y-m-d') : $rawDateStr;
        }

        $records[] = [
            'id_kecamatan' => $kecId,
            'latitude'     => $lat,
            'longitude'    => $lon,
            'tanggal'      => $formattedDate,
            'ws10m_ms'     => $ws10mFloat,
            'ws2m_ms'      => $ws2mFloat,
            'sumber'       => 'NASA_POWER'
        ];
    }

    return $records;
}

/**
 * Menyimpan/Memperbarui data kecepatan angin ke database (INSERT ... ON DUPLICATE KEY UPDATE)
 * 
 * @param PDO $db
 * @param array $records
 * @return array Stat [inserted, updated, failed]
 */
function saveWindData(PDO $db, array $records): array {
    if (empty($records)) {
        return ['inserted' => 0, 'updated' => 0, 'failed' => 0];
    }

    $sql = "INSERT INTO `cuaca_angin_jember` 
            (`id_kecamatan`, `latitude`, `longitude`, `tanggal`, `ws10m_ms`, `ws2m_ms`, `sumber`)
            VALUES (:id_kecamatan, :latitude, :longitude, :tanggal, :ws10m_ms, :ws2m_ms, :sumber)
            ON DUPLICATE KEY UPDATE
                `ws10m_ms` = VALUES(`ws10m_ms`),
                `ws2m_ms` = VALUES(`ws2m_ms`),
                `updated_at` = CURRENT_TIMESTAMP";

    $stmt = $db->prepare($sql);
    $insertedCount = 0;
    $updatedCount = 0;
    $failedCount = 0;

    foreach ($records as $row) {
        try {
            $stmt->bindValue(':id_kecamatan', $row['id_kecamatan'], $row['id_kecamatan'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':latitude', $row['latitude']);
            $stmt->bindValue(':longitude', $row['longitude']);
            $stmt->bindValue(':tanggal', $row['tanggal']);
            $stmt->bindValue(':ws10m_ms', $row['ws10m_ms']);
            $stmt->bindValue(':ws2m_ms', $row['ws2m_ms'], $row['ws2m_ms'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':sumber', $row['sumber']);

            $stmt->execute();
            $affected = $stmt->rowCount();
            if ($affected === 1) {
                $insertedCount++;
            } elseif ($affected === 2) {
                $updatedCount++;
            } else {
                $insertedCount++;
            }
        } catch (PDOException $e) {
            logMessage("Error menyimpan record tanggal {$row['tanggal']}: " . $e->getMessage(), 'ERROR');
            $failedCount++;
        }
    }

    return [
        'inserted' => $insertedCount,
        'updated'  => $updatedCount,
        'failed'   => $failedCount
    ];
}

/**
 * Fungsi Utama (Main Workflow)
 * 
 * @param array $argv Argumen CLI
 */
function main(array $argv): void {
    // Parse argumen CLI (--mode, --start, --end)
    $options = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }

    $mode = strtolower($options['mode'] ?? 'single');
    
    // Default tanggal kemarin (WIB) jika tidak diisi
    $yesterday = date('Ymd', strtotime('-1 day'));
    $startDate = $options['start'] ?? $yesterday;
    $endDate   = $options['end']   ?? $yesterday;

    logMessage("==========================================================================");
    logMessage("   SKRIP PENGAMBIL DATA KECEPATAN ANGIN (NASA POWER API) - JAGAPADI");
    logMessage("==========================================================================");
    logMessage("Mode: " . strtoupper($mode) . " | Periode Data: {$startDate} s/d {$endDate}");

    try {
        $db = Database::getInstance()->getConnection();
        ensureTableExists($db);
    } catch (Exception $e) {
        logMessage("Gagal terhubung ke database: " . $e->getMessage(), 'CRITICAL');
        exit(1);
    }

    $locations = [];

    if ($mode === 'multi') {
        try {
            $stmt = $db->query("SELECT id, nama_kecamatan, latitude, longitude FROM master_kecamatan ORDER BY id ASC");
            $rawKecamatan = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawKecamatan as $kec) {
                $lat = ($kec['latitude'] !== null && $kec['latitude'] !== '') ? floatval($kec['latitude']) : null;
                $lon = ($kec['longitude'] !== null && $kec['longitude'] !== '') ? floatval($kec['longitude']) : null;

                if (empty($lat) || empty($lon)) {
                    logMessage("Warning: Kecamatan '{$kec['nama_kecamatan']}' (ID: {$kec['id']}) tidak memiliki koordinat valid. Skip.", 'WARNING');
                    continue;
                }

                $locations[] = [
                    'id_kecamatan'   => (int)$kec['id'],
                    'nama_kecamatan' => $kec['nama_kecamatan'],
                    'latitude'       => $lat,
                    'longitude'      => $lon
                ];
            }
        } catch (Exception $e) {
            logMessage("Gagal membaca tabel master_kecamatan: " . $e->getMessage(), 'ERROR');
        }
    }

    // Fallback atau default mode single jika locations kosong / mode single
    if (empty($locations)) {
        $locations[] = [
            'id_kecamatan'   => null,
            'nama_kecamatan' => 'Pusat Kabupaten Jember',
            'latitude'       => -8.16889,
            'longitude'      => 113.70222
        ];
    }

    $totalPoints = count($locations);
    $totalRecordsProcessed = 0;
    $totalInserted = 0;
    $totalUpdated = 0;
    $totalFailed = 0;
    $stats = ['skipped_999' => 0, 'anomalies' => 0];

    logMessage("Memproses {$totalPoints} titik lokasi...");

    foreach ($locations as $idx => $loc) {
        $pointNum = $idx + 1;
        $locName = $loc['nama_kecamatan'];
        $lat = $loc['latitude'];
        $lon = $loc['longitude'];
        $kecId = $loc['id_kecamatan'];

        logMessage("[{$pointNum}/{$totalPoints}] Mengambil data untuk {$locName} (Lat: {$lat}, Lon: {$lon})...");

        $url = buildUrl($lat, $lon, $startDate, $endDate);
        $jsonData = fetchNasaPowerJson($url);

        if (!$jsonData) {
            logMessage("Gagal mendapatkan data untuk {$locName}, melanjutkan ke titik berikutnya.", 'ERROR');
            continue;
        }

        $records = parseWindResponse($jsonData, $lat, $lon, $kecId, $stats);
        $saveRes = saveWindData($db, $records);

        $totalRecordsProcessed += count($records);
        $totalInserted += $saveRes['inserted'];
        $totalUpdated += $saveRes['updated'];
        $totalFailed += $saveRes['failed'];

        logMessage("   -> Hasil {$locName}: " . count($records) . " record (Baru: {$saveRes['inserted']}, Update: {$saveRes['updated']}, Gagal: {$saveRes['failed']})");

        if ($totalPoints > 1 && $pointNum < $totalPoints) {
            usleep(500000); // 0.5s rate-limit pause
        }
    }

    logMessage("\n==========================================================================");
    logMessage("                     RINGKASAN EKSEKUSI SELESAI");
    logMessage("==========================================================================");
    logMessage("  Total Titik Diproses     : {$totalPoints}");
    logMessage("  Total Record Data        : {$totalRecordsProcessed}");
    logMessage("  Insert Baru              : {$totalInserted}");
    logMessage("  Update Data Existing     : {$totalUpdated}");
    logMessage("  Gagal Simpan             : {$totalFailed}");
    logMessage("  Record Di-skip (-999)    : {$stats['skipped_999']}");
    logMessage("  Record Anomali (>50 m/s) : {$stats['anomalies']}");
    logMessage("==========================================================================");
}

// Jalankan skrip
main($argv ?? []);
