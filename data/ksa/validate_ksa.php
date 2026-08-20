<?php
define('ROOT_PATH', dirname(__DIR__, 2));
foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $envPath) {
    if (!file_exists($envPath)) continue;
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        putenv(trim(substr($line, 0, $eq)) . '=' . trim(substr($line, $eq + 1)));
    }
}
require_once ROOT_PATH . '/app/core/Database.php';
$db = Database::getInstance()->getConnection();

echo "=== VALIDASI 1: Kelengkapan data KSA bulanan ===\n";
$stmt = $db->query("SELECT COUNT(*) AS total, COUNT(DISTINCT kode_wilayah) AS kabupaten, MIN(tahun) AS tahun_min, MAX(tahun) AS tahun_max FROM data_ksa_bulanan");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== VALIDASI 2: Rekapitulasi per tahun (kabupaten & bulan) ===\n";
$stmt = $db->query("SELECT tahun, COUNT(DISTINCT kode_wilayah) AS kabupaten, COUNT(DISTINCT bulan) AS bulan, COUNT(*) AS record FROM data_ksa_bulanan GROUP BY tahun ORDER BY tahun");
foreach ($stmt as $r) {
    printf("  %d: %d kabupaten x %d bulan = %d record\n", (int)$r['tahun'], (int)$r['kabupaten'], (int)$r['bulan'], (int)$r['record']);
}

echo "\n=== VALIDASI 3: Sync data_pertanian_bps (KSA) ===\n";
$stmt = $db->query("SELECT COUNT(*) total, COUNT(DISTINCT tahun) tahun_terisi, COUNT(DISTINCT kabupaten_kota) kabupaten FROM data_pertanian_bps WHERE sumber_data LIKE 'KSA BPS %'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== VALIDASI 4: Konsistensi agregat tahunan (ksa vs bps) ===\n";
$stmt = $db->query(
    "SELECT b.tahun, b.kabupaten_kota, b.luas_panen AS bps_luas, k.ksa_luas,
            ROUND(ABS(b.luas_panen - k.ksa_luas), 2) AS selisih
     FROM data_pertanian_bps b
     JOIN (
         SELECT tahun, kabupaten_kota, SUM(luas_panen) AS ksa_luas
         FROM data_ksa_bulanan WHERE status_data = 'tetap'
         GROUP BY tahun, kabupaten_kota
     ) k ON k.tahun = b.tahun AND k.kabupaten_kota = b.kabupaten_kota
     WHERE b.sumber_data LIKE 'KSA BPS %' AND ROUND(ABS(b.luas_panen - k.ksa_luas), 2) > 0.01
     LIMIT 10"
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "  OK: Semua agregat KSA tahunan cocok dengan data bulanan (selisih <= 0.01)\n";
} else {
    foreach ($rows as $r) {
        printf("  MISMATCH %d %s: bps=%.2f ksa=%.2f selisih=%.2f\n", (int)$r['tahun'], $r['kabupaten_kota'], (float)$r['bps_luas'], (float)$r['ksa_luas'], (float)$r['selisih']);
    }
}

echo "\n=== VALIDASI 5: Jumlah kabupaten per tahun di data_pertanian_bps (KSA) ===\n";
$stmt = $db->query("SELECT tahun, COUNT(*) c FROM data_pertanian_bps WHERE sumber_data LIKE 'KSA BPS %' GROUP BY tahun ORDER BY tahun");
foreach ($stmt as $r) {
    printf("  %d: %d kabupaten\n", (int)$r['tahun'], (int)$r['c']);
}

echo "\n=== VALIDASI 6: Log aktivitas KSA ===\n";
$stmt = $db->query("SELECT action, status, COUNT(*) c FROM bps_scraping_logs WHERE action LIKE 'ksa_%' GROUP BY action, status");
foreach ($stmt as $r) {
    printf("  %-22s %-8s %d\n", $r['action'], $r['status'], (int)$r['c']);
}

echo "\n=== VALIDASI 7: Cakupan kabupaten 2025 vs daftar standar 38 ===\n";
$stmt = $db->query("SELECT COUNT(*) c FROM (SELECT DISTINCT kabupaten_kota FROM data_ksa_bulanan WHERE tahun=2025) t");
echo "  kabupaten terisi 2025: " . $stmt->fetchColumn() . PHP_EOL;

echo "\n=== VALIDASI 8: Contoh agregat Jember 2025 ===\n";
$stmt = $db->query("SELECT * FROM data_pertanian_bps WHERE tahun=2025 AND kabupaten_kota='Jember'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
