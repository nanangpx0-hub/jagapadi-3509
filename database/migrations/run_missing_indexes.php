<?php
/**
 * Runner untuk 2026-08-09_add_missing_indexes.sql
 * Memeriksa keberadaan tabel & index sebelum mengeksekusi ALTER (idempotent).
 */
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=jagapadi_local;charset=utf8mb4';
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$indexes = [
    ['harga_alerts', 'idx_created_at', 'CREATE INDEX idx_created_at ON harga_alerts (created_at)'],
    ['harga_alerts', 'idx_jenis_tanggal', 'CREATE INDEX idx_jenis_tanggal ON harga_alerts (jenis_komoditas, tanggal)'],
    ['nomor_laporan_counter', 'idx_tanggal_counter', 'CREATE INDEX idx_tanggal_counter ON nomor_laporan_counter (tanggal, counter)'],
    ['bps_scraping_logs', 'idx_action_status', 'CREATE INDEX idx_action_status ON bps_scraping_logs (action, status)'],
    ['bps_scraping_logs', 'idx_created_at', 'CREATE INDEX idx_created_at ON bps_scraping_logs (created_at)'],
    ['curah_hujan_logs', 'idx_created_at', 'CREATE INDEX idx_created_at ON curah_hujan_logs (created_at)'],
    ['harga_komoditas_logs', 'idx_created_at', 'CREATE INDEX idx_created_at ON harga_komoditas_logs (created_at)'],
    ['kecepatan_angin_logs', 'idx_created_at', 'CREATE INDEX idx_created_at ON kecepatan_angin_logs (created_at)'],
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