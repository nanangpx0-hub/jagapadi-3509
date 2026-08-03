<?php
/**
 * Deep Analysis: Data Structure, Bug Verification, Anomaly Check
 */
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';
$db = Database::getInstance()->getConnection();

echo "=== DEEP ANALISIS DATA KECEPATAN ANGIN ===\n\n";

// 1. Cek bug arah_angin_desc + satuan TIDAK ADA SEPARATOR
echo "--- BUG VERIFIKASI: Penggabungan arah_angin_desc & satuan ---\n";
$bugCount = $db->query("SELECT COUNT(*) FROM kecepatan_angin WHERE 
    (arah_angin_desc LIKE '%km/h%' OR arah_angin_desc LIKE '%km%')
    OR (satuan LIKE '%Utara%' OR satuan LIKE '%Selatan%' OR satuan LIKE '%Timur%' OR satuan LIKE '%Barat%')
")->fetchColumn();
echo "  Record dengan arah_angin_desc mengandung 'km/h': {$bugCount}\n";

$sampleBug = $db->query("SELECT id, arah_angin_desc, satuan, lokasi, tanggal 
    FROM kecepatan_angin 
    WHERE arah_angin_desc LIKE '%km/h%'
    LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sampleBug as $sb) {
    echo "  ID {$sb['id']}: arah_desc='{$sb['arah_angin_desc']}' satuan='{$sb['satuan']}'\n";
}

// Cek satuan bernilai benar vs salah
$satuanStat = $db->query("SELECT satuan, COUNT(*) as cnt FROM kecepatan_angin GROUP BY satuan")->fetchAll(PDO::FETCH_ASSOC);
echo "  \n  Distribusi satuan:\n";
foreach ($satuanStat as $s) {
    echo "    '{$s['satuan']}': {$s['cnt']}\n";
}

// 2. Cek distribusi lokasi
echo "\n--- DISTRIBUSI LOKASI ---\n";
$locs = $db->query("SELECT lokasi, COUNT(*) as cnt, ROUND(AVG(kecepatan_angin),2) as avg 
    FROM kecepatan_angin 
    GROUP BY lokasi 
    ORDER BY cnt DESC
    LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$totalLocs = $db->query("SELECT COUNT(DISTINCT lokasi) FROM kecepatan_angin")->fetchColumn();
echo "  Total lokasi berbeda: {$totalLocs}\n";
foreach ($locs as $l) {
    echo "    {$l['lokasi']}: {$l['cnt']} data, avg {$l['avg']} km/h\n";
}

// 3. Cek data historis (rentang tanggal)
echo "\n--- RENTANG DATA HISTORIS ---\n";
$range = $db->query("SELECT 
    MIN(tanggal) as first_date, 
    MAX(tanggal) as last_date, 
    DATEDIFF(MAX(tanggal), MIN(tanggal)) + 1 as days_span,
    COUNT(DISTINCT tanggal) as unique_days,
    COUNT(*) as total_records
    FROM kecepatan_angin")->fetch(PDO::FETCH_ASSOC);
foreach ($range as $k => $v) {
    echo "  {$k}: {$v}\n";
}
$expectedRecords = ($range['days_span'] ?? 0) * ($totalLocs ?: 1);
$missing = $expectedRecords - $range['total_records'];
$pctComplete = $expectedRecords > 0 ? round($range['total_records'] / $expectedRecords * 100, 1) : 100;
echo "  Expected records (days * locations): {$expectedRecords}\n";
echo "  Data completeness: {$pctComplete}%\n";
echo "  Missing/over: {$missing}\n";

// 4. Cek duplikat lebih detail
echo "\n--- DUPLIKAT DAN UNIK ---\n";
$dups = $db->query("SELECT tanggal, lokasi, COUNT(*) as cnt,
    GROUP_CONCAT(DISTINCT kecepatan_angin ORDER BY kecepatan_angin) as speeds,
    GROUP_CONCAT(id SEPARATOR ',') as ids
    FROM kecepatan_angin 
    GROUP BY tanggal, lokasi 
    HAVING cnt > 1
    LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "  Total grup duplikat (tanggal+lokasi): " . count($dups) . "\n";
foreach ($dups as $d) {
    echo "    {$d['tanggal']} @ {$d['lokasi']}: {$d['cnt']}x | speeds: {$d['speeds']} | ids: {$d['ids']}\n";
}

// 5. Load getData via Controller direct call simulating to find bug why 289 bytes empty data
echo "\n--- VERIFIKASI CONTROLLER getData BUG (total=0, data kosong) ---\n";
require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
$model = new KecepatanAngin();

// Test filter default like getData()
$filters = [
    'limit' => 50,
    'offset' => 0,
    'sumber_data_like' => '%Open-Meteo%', // Default data_source=openmeteo in getData()
];
echo "  Menggunakan filter default getData():\n";
echo "    filters: " . json_encode($filters, JSON_UNESCAPED_SLASHES) . "\n";
$data = $model->getAll($filters);
$total = $model->countAll($filters);
echo "    getAll() count result: " . count($data) . "\n";
echo "    countAll() result: {$total}\n";

if (count($data) === 0) {
    echo "  ⚠️  DATA KOSONG karena filter sumber_data_like='%Open-Meteo%' tapi SEMUA data adalah 'Simulasi'!\n";
    echo "  => BUG: Controller getData() default data_source=openmeteo yang menghasilkan KOSONG karena data hanya Simulasi\n";
}

// Sekarang tanpa filter sumber_data_like
$filters2 = ['limit' => 50, 'offset' => 0];
$data2 = $model->getAll($filters2);
$total2 = $model->countAll($filters2);
echo "  \n  Tanpa filter sumber_data_like:\n";
echo "    getAll() count: " . count($data2) . "\n";
echo "    countAll() result: {$total2}\n";

// 6. Struktur record sample BENAR vs SALAH
echo "\n--- STRUKTUR DATA SAMPLE (5 record) ---\n";
$sample = $db->query("SELECT * FROM kecepatan_angin LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$fields = array_keys($sample[0]);
echo "  Fields (" . count($fields) . "): " . implode(', ', $fields) . "\n";
foreach ($sample as $i => $s) {
    echo "\n  Record #" . ($i+1) . ":\n";
    foreach ($s as $k => $v) {
        $displayVal = is_null($v) ? 'NULL' : (strlen((string)$v) > 40 ? substr((string)$v, 0, 40) . '...' : $v);
        echo "    {$k}: {$displayVal}\n";
    }
}

// 7. Cek indeks DB performance
echo "\n--- DATABASE INDEXES ---\n";
$idx = $db->query("SHOW INDEX FROM kecepatan_angin")->fetchAll(PDO::FETCH_ASSOC);
echo "  Jumlah indeks: " . count($idx) . "\n";
foreach ($idx as $ix) {
    echo "    Name: {$ix['Key_name']} | Column: {$ix['Column_name']} | Unique: " . ($ix['Non_unique'] ? 'NO' : 'YES') . "\n";
}

// 8. Cek kode_wilayah & lokasi konsistensi
echo "\n--- KONSISTENSI WILAYAH ---\n";
$kodeWil = $db->query("SELECT kode_wilayah, COUNT(*) as cnt FROM kecepatan_angin GROUP BY kode_wilayah")->fetchAll(PDO::FETCH_ASSOC);
foreach ($kodeWil as $k) {
    echo "  Kode '{$k['kode_wilayah']}': {$k['cnt']} records\n";
}

// 9. Verifikasi issue map hardcoded coordinates
echo "\n--- BUG PETA: Koordinat hardcoded di getMapData() ---\n";
echo "  Di Controller getMapData() lat/lon HARDCODED = -8.1706, 113.7003 untuk SEMUA lokasi\n";
echo "  => Semua titik peta akan bertumpuk di satu lokasi (TIDAK AKURAT)\n";

// 10. Cek UNIQUE INDEX proposal
echo "\n--- REKOMENDASI UNIQUE CONSTRAINT ---\n";
echo "  Tanggal+Lokasi harusnya UNIQUE (satu lokasi satu hari hanya 1 data)\n";
echo "  Saat ini duplikat ditemukan: " . count($dups) . " grup\n";

echo "\n=== PENUTUPAN ===\n";
echo "Deep analysis selesai. Temuan utama untuk laporan akhir disiapkan.\n";
