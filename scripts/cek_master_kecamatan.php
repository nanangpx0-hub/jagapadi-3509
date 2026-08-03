<?php
/**
 * Cek struktur master_kecamatan: ada kolom lat/lon tidak?
 * Dan daftar kecamatan beserta koordinatnya (jika ada)
 */
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';
$db = Database::getInstance()->getConnection();

echo "=== STRUKTUR TABEL master_kecamatan ===\n";
$cols = $db->query("SHOW COLUMNS FROM master_kecamatan")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  {$c['Field']} | {$c['Type']} | Null: {$c['Null']} | Default: {$c['Default']}\n";
}

echo "\n=== ISI 5 RECORD PERTAMA master_kecamatan ===\n";
$rows = $db->query("SELECT * FROM master_kecamatan LIMIT 31")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $lat = $r['latitude'] ?? 'N/A';
    $lon = $r['longitude'] ?? 'N/A';
    $kode = $r['kode_bmkg_adm4'] ?? ($r['kode'] ?? 'N/A');
    echo "  ID {$r['id']}: {$r['nama_kecamatan']} | kode={$kode} | lat={$lat} | lon={$lon}\n";
}
echo "Total kecamatan: " . count($rows) . "\n";

echo "\n=== STRUKTUR TABEL kecepatan_angin ===\n";
$cols2 = $db->query("SHOW COLUMNS FROM kecepatan_angin")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) {
    echo "  {$c['Field']} | {$c['Type']} | Null: {$c['Null']} | Default: {$c['Default']}\n";
}
echo "\n=== INDEX kecepatan_angin ===\n";
$idx = $db->query("SHOW INDEX FROM kecepatan_angin")->fetchAll(PDO::FETCH_ASSOC);
foreach ($idx as $ix) {
    echo "  Key_name={$ix['Key_name']}, Column={$ix['Column_name']}, Unique=" . ($ix['Non_unique'] ? 'No' : 'Yes') . "\n";
}
