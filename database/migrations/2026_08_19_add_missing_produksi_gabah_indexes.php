<?php
/**
 * Migration: Add missing indexes to produksi_gabah and data_pertanian_bps
 * Menambah index untuk performa query filtering berdasarkan user_id, tahun, status
 * 
 * Dibuat untuk mendukung PERBAIKAN 11: Role-based scoped view dan query optimization
 */

$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=jagapadi_local;charset=utf8mb4';
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$indexes = [
    // Index untuk produksi_gabah - filtering berdasarkan user_id (petugas scope)
    ['produksi_gabah', 'idx_produksi_user_id', 'CREATE INDEX idx_produksi_user_id ON produksi_gabah (user_id)'],
    ['produksi_gabah', 'idx_produksi_tahun_status', 'CREATE INDEX idx_produksi_tahun_status ON produksi_gabah (tahun, status)'],
    ['produksi_gabah', 'idx_produksi_kecamatan_tahun', 'CREATE INDEX idx_produksi_kecamatan_tahun ON produksi_gabah (kecamatan_id, tahun)'],
    // Index untuk data_pertanian_bps - optimasi query tahun dan kabupaten
    ['data_pertanian_bps', 'idx_bps_tahun_kabupaten', 'CREATE INDEX idx_bps_tahun_kabupaten ON data_pertanian_bps (tahun, kabupaten_kota(191))'],
    ['data_pertanian_bps', 'idx_bps_kadar_air', 'CREATE INDEX idx_bps_kadar_air ON data_pertanian_bps (kadar_air)'],
];

$ok = 0;
$skipped = 0;
$failed = 0;

foreach ($indexes as [$table, $indexName, $sql]) {
    try {
        // Cek apakah tabel ada
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() === 0) {
            echo "SKIP: tabel {$table} tidak ditemukan\n";
            $skipped++;
            continue;
        }

        // Cek apakah index sudah ada
        $stmt = $db->prepare("SHOW INDEX FROM {$table} WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        if ($stmt->rowCount() > 0) {
            echo "SKIP: index {$indexName} sudah ada di {$table}\n";
            $skipped++;
            continue;
        }

        $db->exec($sql);
        echo "OK: {$indexName} ditambahkan ke {$table}\n";
        $ok++;
    } catch (Exception $e) {
        echo "FAIL: {$table}.{$indexName} — " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nRingkasan: {$ok} ditambahkan, {$skipped} skip, {$failed} gagal\n";