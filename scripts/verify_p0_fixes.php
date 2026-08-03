<?php
/**
 * Verifikasi 4 P0 Fixes
 * 1. getData() default total tidak 0
 * 2. Upsert: Jalankan scraper run saat ini -> seharusnya TAMBAH 0 record (semua upsert tidak insert baru)
 * 3. getMapData() matched_coordinates = 31 (100%)
 * 4. Scraper loadLocations() lat & lon ada
 */
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';
require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
require_once ROOT_PATH . '/app/services/KecepatanAnginScraper.php';
$db = Database::getInstance()->getConnection();
$model = new KecepatanAngin();

echo "===== VERIFIKASI 4 P0 FIXES =====\n\n";
$before = $db->query("SELECT COUNT(*) FROM kecepatan_angin")->fetchColumn();
echo "Sebelum test: {$before} records\n\n";

// 1. getData() default controller via model GET ALL
echo "[R1] getData() filter default (tanpa data_source)\n";
$all = $model->getAll(['limit' => 50, 'offset' => 0]);
$cnt = $model->countAll([]);
echo "  getAll() result count: " . count($all) . "\n";
echo "  countAll() result: {$cnt}\n";
echo "  Status: " . (count($all) > 0 && $cnt > 0 ? "✅ PASS (Total {$cnt} records, data tampil)" : "❌ FAIL") . "\n\n";

// 2. Test UPSERT scraper rerun hari ini
echo "[R3] Test UPSERT: Run scraper bulan sekarang (force_simulation true), seharusnya TIDAK TAMBAH record baru\n";
$scraper = new KecepatanAnginScraper($model);
$y = (int)date('Y'); $m = (int)date('m');
echo "  Run bulan {$y}-{$m}...\n";
$res = $scraper->run(['year' => $y, 'month' => $m, 'force_simulation' => true]);
$after = $db->query("SELECT COUNT(*) FROM kecepatan_angin")->fetchColumn();
$diff = $after - $before;
echo "  Hasil run: success=" . ($res['success']?'Y':'T') . " records_success={$res['records_success']} records_failed={$res['records_failed']} source={$res['source']}\n";
echo "  Records BEFORE: {$before}, AFTER: {$after}, DIFF: {$diff}\n";
echo "  Status: " . ($diff === 0 ? "✅ PASS (UPSERT works! 0 record baru, semua di-update sempurna)" : ( $diff < 0 ? "✅ PASS ({$diff} record duplikat hilang)" : "⚠️  Ada {$diff} record baru ditambahkan (OK jika ada data baru)")) . "\n\n";

// 3. Cek koordinat kecamatan loaded di scraper locations (R2)
echo "[R2] loadLocations() lat/lon ada di scraper?\n";
$refl = new ReflectionClass($scraper);
$locProp = $refl->getProperty('locations');
$locProp->setAccessible(true);
$locs = $locProp->getValue($scraper);
if (empty($locs)) {
    // Force call loadLocations via reflection
    $loadM = $refl->getMethod('loadLocations');
    $loadM->setAccessible(true);
    $loadM->invoke($scraper);
    $locs = $locProp->getValue($scraper);
}
$withCoords = 0; $total = count($locs);
foreach ($locs as $l) {
    if (isset($l['latitude'], $l['longitude']) && $l['latitude'] !== null && $l['longitude'] !== null) {
        $withCoords++;
    }
}
echo "  Total kecamatan loaded: {$total}, dengan lat/lon: {$withCoords}\n";
if ($total > 0) {
    $sample = reset($locs);
    echo "  Sample: {$sample['nama_kecamatan']} (lat={$sample['latitude']}, lon={$sample['longitude']}, kode={$sample['kode_bmkg_adm4']})\n";
}
echo "  Status: " . ($withCoords === $total && $total === 31 ? "✅ PASS (31 kecamatan lengkap lat/lon)" : ( $withCoords > 0 ? "⚠️  PARSIAL: {$withCoords}/{$total}" : "❌ FAIL (tidak ada lat/lon)")) . "\n\n";

// 4. Test getMapData() 100% matched (R4) via direct simulating controller logic
echo "[R4] getMapData() matched_coordinates 100%\n";
$windByLoc = $model->getWindByLocation(date('Y'));
echo "  Total lokasi: " . count($windByLoc) . "\n";

// Simulate same lookup logic in getMapData()
$kecStmt = $db->prepare("SELECT nama_kecamatan, latitude, longitude, kode FROM master_kecamatan WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
$kecStmt->execute();
$kecLookup = [];
foreach ($kecStmt->fetchAll(PDO::FETCH_ASSOC) as $k) {
    $kecLookup[trim($k['nama_kecamatan'])] = ['latitude' => (float)$k['latitude'], 'longitude' => (float)$k['longitude'], 'kode' => $k['kode']];
}
foreach ($kecLookup as $nama => $coord) { $kecLookup[$nama . ', Jember'] = $coord; }

$matched = 0;
foreach ($windByLoc as $row) {
    $namaLokasi = trim($row['lokasi']);
    $kecNameOnly = explode(',', $namaLokasi)[0];
    $found = $kecLookup[$namaLokasi] ?? $kecLookup[$kecNameOnly] ?? null;
    if ($found !== null && !($found['latitude'] == -8.1706 && $found['longitude'] == 113.7003 && strpos($namaLokasi, ', Jember') === false)) {
        $matched++;
    }
}
echo "  Kecamatan terpetakan dengan koordinat benar: {$matched}/" . count($windByLoc) . "\n";
echo "  Status: " . ($matched === count($windByLoc) ? "✅ PASS (100% koordinat lokasi terpetakan benar)" : "⚠️  Kurang: " . (count($windByLoc)-$matched) . " lokasi fallback ke default") . "\n\n";

// 5. Quick summary unique constraint
echo "[R3] UNIQUE CONSTRAINT CHECK:\n";
$idx = $db->query("SHOW INDEX FROM kecepatan_angin WHERE Key_name = 'uk_tgl_lokasi'")->fetchAll(PDO::FETCH_ASSOC);
echo "  Constraint uk_tgl_lokasi ditemukan: " . (!empty($idx) ? '✅ YES' : '❌ NO') . "\n";
$dup = $db->query("SELECT COUNT(*) FROM (SELECT tanggal, lokasi FROM kecepatan_angin GROUP BY tanggal, lokasi HAVING COUNT(*)>1) x")->fetchColumn();
echo "  Grup duplikat: {$dup} / 0 diharapkan\n";
echo "  Status: " . (!empty($idx) && $dup == 0 ? "✅ PASS" : "⚠️  Perlu dicek") . "\n\n";

echo "===== VERIFIKASI SELESAI =====\n";
