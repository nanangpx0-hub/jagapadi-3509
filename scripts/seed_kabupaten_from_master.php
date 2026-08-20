<?php
// Seed sementara: isi tabel kabupaten dari master_kabupaten
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../config/config.php';

$pdo = Database::getInstance()->getConnection();
$n = $pdo->exec("INSERT IGNORE INTO kabupaten (kode_kabupaten, nama_kabupaten, provinsi) SELECT kode, nama_kabupaten, 'Jawa Timur' FROM master_kabupaten");
echo 'inserted: ' . $n . PHP_EOL;
echo 'kabupaten rows: ' . $pdo->query('SELECT COUNT(*) FROM kabupaten')->fetchColumn() . PHP_EOL;
