<?php
// Runner: database/migrations/2026-08-08_add_multi_province_support.sql
require __DIR__ . '/../../app/core/Database.php';
require __DIR__ . '/../../config/config.php';

$pdo = Database::getInstance()->getConnection();
$sql = file_get_contents(__DIR__ . '/2026-08-08_add_multi_province_support.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

$ok = 0;
$fail = 0;
foreach ($statements as $stmt) {
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (Throwable $e) {
        $fail++;
        echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
    }
}
echo "{$ok} ok, {$fail} fail" . PHP_EOL;
echo 'master_provinsi: ' . $pdo->query('SELECT COUNT(*) FROM master_provinsi')->fetchColumn() . PHP_EOL;
echo 'master_kabupaten_by_province: ' . $pdo->query('SELECT COUNT(*) FROM master_kabupaten_by_province')->fetchColumn() . PHP_EOL;
echo 'kolom kode_provinsi ada: ' . $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='data_pertanian_bps' AND COLUMN_NAME='kode_provinsi'")->fetchColumn() . PHP_EOL;
