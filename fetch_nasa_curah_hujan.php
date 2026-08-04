<?php
/**
 * Skrip Pengambil & Pengimpor Data Curah Hujan Harian dari NASA POWER API
 * Halaman Target: Curah Hujan - JAGAPADI (http://localhost/jagapadi-3509/curahHujan)
 * 
 * Spesifikasi & Fitur:
 * 1. Parameter PRECTOTCORR dari endpoint temporal/daily/point NASA POWER API.
 * 2. Periode data: 1 Januari 2021 - 31 Desember 2026.
 * 3. 31 Kecamatan Kabupaten Jember beserta koordinat pusatnya.
 * 4. Jeda rate limit 1.5 detik (usleep(1500000)) antar permintaan kecamatan.
 * 5. Penggunaan cURL murni (tanpa file_get_contents).
 * 6. Pembersihan data kosong / missing (-999).
 * 7. Format tanggal standar YYYY-MM-DD.
 * 8. Output CSV dengan struktur kolom: kecamatan, tanggal, curah_hujan_mm.
 * 9. Error handling komprehensif tanpa menghentikan eksekusi skrip.
 * 10. set_time_limit(0) di bagian awal skrip.
 * 11. Output progres real-time dengan buffer flushing.
 * 12. Kompatibel penuh PHP 7.4+ tanpa dependensi tambahan.
 * 13. [Integrasi Aplikasi] Mengimpor langsung data ke tabel database `curah_hujan` JAGAPADI.
 */

// 10. Menonaktifkan batas waktu eksekusi skrip agar tidak mengalami timeout
set_time_limit(0);

// Set header & matikan output buffer bawaan jika dijalankan via browser web
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    if (ob_get_level()) {
        ob_end_clean();
    }
    echo "<pre style='font-family: monospace; background: #1e1e1e; color: #00ff66; padding: 20px; line-height: 1.5;'>";
}

/**
 * Fungsi pembantu untuk menampilkan pesan progres secara real-time
 * 
 * @param string $message
 */
function print_progress(string $message): void {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

print_progress("==========================================================================");
print_progress("   SKRIP PENGAMBIL DATA CURAH HUJAN NASA POWER API - JAGAPADI SYSTEM");
print_progress("==========================================================================");

// 2. Periode pengambilan data (1 Januari 2021 hingga 31 Desember 2026)
$startDate = '20210101';
$endDate   = '20261231';

// 3. Array asosiatif 31 Kecamatan di Kabupaten Jember beserta koordinat pusatnya
$kecamatanJember = [
    'Ajung'       => ['lat' => -8.2435, 'lon' => 113.6644],
    'Ambulu'      => ['lat' => -8.3444, 'lon' => 113.6056],
    'Arjasa'      => ['lat' => -8.1064, 'lon' => 113.7381],
    'Balung'      => ['lat' => -8.2678, 'lon' => 113.5419],
    'Bangsalsari'  => ['lat' => -8.1481, 'lon' => 113.5289],
    'Gumukmas'    => ['lat' => -8.3308, 'lon' => 113.4150],
    'Jelbuk'      => ['lat' => -8.0531, 'lon' => 113.7431],
    'Jenggawah'   => ['lat' => -8.2717, 'lon' => 113.6706],
    'Jombang'     => ['lat' => -8.2567, 'lon' => 113.3481],
    'Kalisat'     => ['lat' => -8.1256, 'lon' => 113.8117],
    'Kaliwates'   => ['lat' => -8.1825, 'lon' => 113.6797],
    'Kencong'     => ['lat' => -8.2831, 'lon' => 113.3667],
    'Ledokombo'   => ['lat' => -8.1186, 'lon' => 113.8822],
    'Mayang'      => ['lat' => -8.1969, 'lon' => 113.7878],
    'Mumbulsari'  => ['lat' => -8.2750, 'lon' => 113.7222],
    'Pakusari'    => ['lat' => -8.1678, 'lon' => 113.7667],
    'Panti'       => ['lat' => -8.1086, 'lon' => 113.6067],
    'Patrang'     => ['lat' => -8.1506, 'lon' => 113.7125],
    'Puger'       => ['lat' => -8.3611, 'lon' => 113.4778],
    'Rambipuji'   => ['lat' => -8.2042, 'lon' => 113.6139],
    'Semboro'     => ['lat' => -8.2178, 'lon' => 113.4567],
    'Silo'        => ['lat' => -8.2464, 'lon' => 113.9186],
    'Sukorambi'   => ['lat' => -8.1469, 'lon' => 113.6492],
    'Sukowono'    => ['lat' => -8.0575, 'lon' => 113.8322],
    'Sumberbaru'  => ['lat' => -8.1278, 'lon' => 113.3986],
    'Sumberjambe' => ['lat' => -8.0317, 'lon' => 113.8967],
    'Sumbersari'  => ['lat' => -8.1758, 'lon' => 113.7208],
    'Tanggul'     => ['lat' => -8.1603, 'lon' => 113.4519],
    'Tempurejo'   => ['lat' => -8.3075, 'lon' => 113.7719],
    'Umbulsari'   => ['lat' => -8.2561, 'lon' => 113.4406],
    'Wuluhan'     => ['lat' => -8.3475, 'lon' => 113.5469],
];

// Inisialisasi Koneksi Database JAGAPADI jika tersedia
$db = null;
$kecamatanMapDB = [];
try {
    $rootDir = __DIR__;
    if (!file_exists($rootDir . '/app/core/Database.php')) {
        $rootDir = dirname(__DIR__);
    }
    if (file_exists($rootDir . '/app/core/Database.php')) {
        define('ROOT_PATH', $rootDir);
        require_once $rootDir . '/app/core/Database.php';
        $db = Database::getInstance()->getConnection();
        
        // Load peta ID master kecamatan
        $stmtKec = $db->query("SELECT id, nama_kecamatan FROM master_kecamatan");
        while ($row = $stmtKec->fetch(PDO::FETCH_ASSOC)) {
            $kecamatanMapDB[strtolower(trim($row['nama_kecamatan']))] = (int)$row['id'];
        }
        print_progress("Status Database: ✅ Terhubung ke Database JAGAPADI.");
    }
} catch (Exception $e) {
    print_progress("Status Database: ⚠️ Koneksi database dilewati ({$e->getMessage()}). Hanya membuat file CSV.");
}

// 8. File target CSV output
$outputFile = __DIR__ . '/curah_hujan_jember_2021_2026.csv';
$fp = fopen($outputFile, 'w');

if (!$fp) {
    print_progress("❌ [ERROR KRITIS] Tidak dapat membuka/membuat file output: {$outputFile}");
    exit(1);
}

// 8. Menuliskan header kolom pada file CSV
fputcsv($fp, ['kecamatan', 'tanggal', 'curah_hujan_mm']);

$totalKecamatan = count($kecamatanJember);
$currentKecamatanIdx = 0;
$totalRecordsSaved = 0;
$totalRecordsDB = 0;
$totalErrors = 0;

print_progress("Jumlah Kecamatan: {$totalKecamatan}");
print_progress("Periode Data    : 01-01-2021 s.d. 31-12-2026");
print_progress("File Output CSV : " . realpath($outputFile));
print_progress("--------------------------------------------------------------------------");

// Query UPSERT Database (Insert jika belum ada, Update jika tanggal & lokasi sudah ada)
$stmtInsertDB = null;
if ($db) {
    $sqlDB = "INSERT INTO curah_hujan 
                (tanggal, lokasi, kecamatan, kecamatan_id, latitude, longitude, curah_hujan, satuan, sumber_data) 
              VALUES 
                (:tanggal, :lokasi, :kecamatan, :kecamatan_id, :latitude, :longitude, :curah_hujan, 'mm', 'NASA POWER (PRECTOTCORR)')
              ON DUPLICATE KEY UPDATE 
                curah_hujan = VALUES(curah_hujan), 
                kecamatan = VALUES(kecamatan),
                kecamatan_id = VALUES(kecamatan_id),
                updated_at = CURRENT_TIMESTAMP";
    $stmtInsertDB = $db->prepare($sqlDB);
}

// 4. Loop untuk memproses seluruh kecamatan secara berurutan
foreach ($kecamatanJember as $namaKecamatan => $koordinat) {
    $currentKecamatanIdx++;
    $lat = $koordinat['lat'];
    $lon = $koordinat['lon'];
    $kecIdDB = $kecamatanMapDB[strtolower(trim($namaKecamatan))] ?? null;

    print_progress("[{$currentKecamatanIdx}/{$totalKecamatan}] Memproses Kecamatan: {$namaKecamatan} (Lat: {$lat}, Lon: {$lon})...");

    // 1. Endpoint temporal/daily/point NASA POWER API dengan parameter PRECTOTCORR
    $apiUrl = "https://power.larc.nasa.gov/api/temporal/daily/point?" . http_build_query([
        'parameters' => 'PRECTOTCORR',
        'community'  => 'AG',
        'longitude'  => $lon,
        'latitude'   => $lat,
        'start'      => $startDate,
        'end'        => $endDate,
        'format'     => 'JSON'
    ]);

    // 5. Inisialisasi cURL untuk permintaan HTTP (Dilarang menggunakan file_get_contents)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => 'JAGAPADI-System/1.0 (PHP-cURL)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 9. Error handling komprehensif jika koneksi cURL gagal
    if ($curlErrno !== 0) {
        print_progress("   ❌ [ERROR] Koneksi cURL gagal untuk kecamatan {$namaKecamatan}: {$curlError} (ErrNo: {$curlErrno})");
        $totalErrors++;
        usleep(1500000); // 4. Jeda rate limit 1.5 detik
        continue;
    }

    if ($httpCode !== 200) {
        print_progress("   ❌ [ERROR] Response error dari API NASA POWER untuk {$namaKecamatan}. HTTP Status: {$httpCode}");
        $totalErrors++;
        usleep(1500000);
        continue;
    }

    // 9. Decoding JSON & penanganan kesalahan format
    $jsonData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        print_progress("   ❌ [ERROR] Format JSON tidak terurai dengan benar untuk {$namaKecamatan}: " . json_last_error_msg());
        $totalErrors++;
        usleep(1500000);
        continue;
    }

    if (!isset($jsonData['properties']['parameter']['PRECTOTCORR'])) {
        print_progress("   ❌ [ERROR] Key PRECTOTCORR tidak ditemukan pada response API untuk {$namaKecamatan}.");
        $totalErrors++;
        usleep(1500000);
        continue;
    }

    $rainData = $jsonData['properties']['parameter']['PRECTOTCORR'];
    $validCount = 0;

    if ($db) {
        $db->beginTransaction();
    }

    foreach ($rainData as $rawDate => $val) {
        // 6. Pembersihan data: abaikan data bernilai -999, null, atau string kosong
        if ($val === null || $val == -999 || $val === '') {
            continue;
        }

        // 7. Format tanggal standar YYYY-MM-DD dari format YYYYMMDD
        $rawDateStr = (string)$rawDate;
        if (strlen($rawDateStr) === 8) {
            $formattedDate = substr($rawDateStr, 0, 4) . '-' . substr($rawDateStr, 4, 2) . '-' . substr($rawDateStr, 6, 2);
        } else {
            $dt = DateTime::createFromFormat('Ymd', $rawDateStr);
            $formattedDate = $dt ? $dt->format('Y-m-d') : $rawDateStr;
        }

        $rainVal = floatval($val);

        // 8. Tulis data yang valid ke file CSV
        fputcsv($fp, [
            $namaKecamatan,
            $formattedDate,
            number_format($rainVal, 2, '.', '')
        ]);

        // 13. Simpan / update ke database tabel curah_hujan jika terhubung
        if ($stmtInsertDB) {
            $stmtInsertDB->execute([
                ':tanggal'      => $formattedDate,
                ':lokasi'       => $namaKecamatan,
                ':kecamatan'    => $namaKecamatan,
                ':kecamatan_id' => $kecIdDB,
                ':latitude'     => $lat,
                ':longitude'    => $lon,
                ':curah_hujan'  => $rainVal,
            ]);
            $totalRecordsDB++;
        }

        $validCount++;
    }

    if ($db) {
        $db->commit();
    }

    $totalRecordsSaved += $validCount;

    // 11. Tampilkan pesan progres real-time setiap kali kecamatan selesai diproses
    print_progress("   ✅ [SELESAI] {$namaKecamatan}: {$validCount} record curah hujan valid berhasil diproses" . ($db ? " & diimpor ke database." : "."));

    // 4. Jeda 1,5 detik (1.500.000 microsecond) antar setiap permintaan API untuk menghindari rate limit NASA POWER
    usleep(1500000);
}

fclose($fp);

// Hapus file temporary jika ada
$tmpFile = __DIR__ . '/scripts/tmp_check_db.php';
if (file_exists($tmpFile)) {
    @unlink($tmpFile);
}

// 11. Ringkasan akhir eksekusi
print_progress("--------------------------------------------------------------------------");
print_progress("PROSES SELESAI SELURUHNYA!");
print_progress("Total Record Curah Hujan CSV   : " . number_format($totalRecordsSaved));
if ($db) {
    print_progress("Total Record Impor Database    : " . number_format($totalRecordsDB));
}
print_progress("Total Kecamatan Error/Gagal    : {$totalErrors}");
print_progress("Lokasi File CSV Output         : " . (realpath($outputFile) ?: $outputFile));
print_progress("==========================================================================");

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}
