<?php

declare(strict_types=1);

/**
 * Memperbaiki integritas data fitur Harga Komoditas.
 *
 * - membedakan aktual/estimasi/simulasi/manual;
 * - mengarsipkan lalu menghapus duplikasi grain observasi;
 * - menambahkan unique key agar scraper/import idempoten;
 * - mengarsipkan dan membangun ulang alert dari rata-rata harian.
 *
 * Jalankan:
 *   php database/migrations/2026_08_11_fix_harga_komoditas_integrity.php
 */

$rootPath = dirname(__DIR__, 2);
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $rootPath);
}
require_once $rootPath . '/app/core/Database.php';

foreach ([$rootPath . '/.env', $rootPath . '/.env.local'] as $envPath) {
    if (!is_file($envPath)) {
        continue;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

$db = Database::getInstance()->getConnection();

$columnExists = static function (string $table, string $column) use ($db): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};
$indexExists = static function (string $table, string $index) use ($db): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
};
$tableExists = static function (string $table) use ($db): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$tableExists('harga_komoditas')) {
    require_once $rootPath . '/app/models/HargaKomoditas.php';
    new HargaKomoditas();
    echo "[OK] Tabel harga komoditas baru dibuat dengan skema yang benar.\n";
    exit(0);
}

if (!$columnExists('harga_komoditas', 'metode_data')) {
    $db->exec(
        "ALTER TABLE harga_komoditas
         ADD COLUMN metode_data ENUM('aktual','estimasi','simulasi','manual')
         NOT NULL DEFAULT 'manual' AFTER sumber_data"
    );
}

$db->exec("UPDATE harga_komoditas SET lokasi = TRIM(COALESCE(NULLIF(lokasi, ''), 'Jember'))");
$db->exec("UPDATE harga_komoditas SET sumber_data = TRIM(COALESCE(NULLIF(sumber_data, ''), 'Manual'))");
$db->exec(
    "UPDATE harga_komoditas
     SET metode_data = CASE
        WHEN sumber_data = 'SISKAPERBAPO Jatim' THEN 'aktual'
        WHEN sumber_data LIKE 'Simulasi%' THEN 'simulasi'
        WHEN sumber_data = 'Estimasi turunan SISKAPERBAPO' THEN 'estimasi'
        WHEN sumber_data = 'Dinas Pertanian Jember'
             AND (keterangan LIKE 'Harga acuan Gabah Kering%' OR keterangan IS NULL) THEN 'estimasi'
        ELSE 'manual'
     END"
);
$db->exec(
    "UPDATE harga_komoditas
     SET sumber_data = 'Estimasi turunan SISKAPERBAPO',
         keterangan = CASE jenis_komoditas
             WHEN 'gabah_kering_panen' THEN 'Estimasi teknis historis, bukan observasi resmi: 52% dari harga beras medium.'
             WHEN 'gabah_kering_giling' THEN 'Estimasi teknis historis, bukan observasi resmi: 60% dari harga beras medium.'
             ELSE keterangan
         END
     WHERE metode_data = 'estimasi' AND sumber_data = 'Dinas Pertanian Jember'"
);
$db->exec(
    "ALTER TABLE harga_komoditas
     MODIFY satuan VARCHAR(20) NOT NULL DEFAULT 'Rp/kg',
     MODIFY lokasi VARCHAR(100) NOT NULL DEFAULT 'Jember',
     MODIFY sumber_data VARCHAR(100) NOT NULL DEFAULT 'Manual'"
);

$backupTable = 'harga_komoditas_duplicate_backup_20260811';
if (!$tableExists($backupTable)) {
    $db->exec("CREATE TABLE {$backupTable} LIKE harga_komoditas");
}

$duplicatesBefore = (int) $db->query(
    "SELECT COALESCE(SUM(jumlah - 1), 0)
     FROM (
        SELECT COUNT(*) AS jumlah
        FROM harga_komoditas
        GROUP BY tanggal, jenis_komoditas, lokasi, sumber_data
        HAVING COUNT(*) > 1
     ) duplicate_groups"
)->fetchColumn();

if ($duplicatesBefore > 0) {
    $db->exec(
        "INSERT IGNORE INTO {$backupTable}
         SELECT h.*
         FROM harga_komoditas h
         INNER JOIN (
            SELECT tanggal, jenis_komoditas, lokasi, sumber_data, MAX(id) AS keep_id
            FROM harga_komoditas
            GROUP BY tanggal, jenis_komoditas, lokasi, sumber_data
            HAVING COUNT(*) > 1
         ) d ON d.tanggal = h.tanggal
             AND d.jenis_komoditas = h.jenis_komoditas
             AND d.lokasi = h.lokasi
             AND d.sumber_data = h.sumber_data
         WHERE h.id <> d.keep_id"
    );
    $backupCount = (int) $db->query("SELECT COUNT(*) FROM {$backupTable}")->fetchColumn();
    if ($backupCount < $duplicatesBefore) {
        throw new RuntimeException('Backup duplikasi tidak lengkap; penghapusan dibatalkan');
    }
    $db->exec(
        "DELETE h
         FROM harga_komoditas h
         INNER JOIN (
            SELECT tanggal, jenis_komoditas, lokasi, sumber_data, MAX(id) AS keep_id
            FROM harga_komoditas
            GROUP BY tanggal, jenis_komoditas, lokasi, sumber_data
            HAVING COUNT(*) > 1
         ) d ON d.tanggal = h.tanggal
             AND d.jenis_komoditas = h.jenis_komoditas
             AND d.lokasi = h.lokasi
             AND d.sumber_data = h.sumber_data
         WHERE h.id <> d.keep_id"
    );
}

if (!$indexExists('harga_komoditas', 'uk_harga_observation')) {
    $db->exec(
        'ALTER TABLE harga_komoditas
         ADD UNIQUE KEY uk_harga_observation (tanggal, jenis_komoditas, lokasi, sumber_data)'
    );
}
if (!$indexExists('harga_komoditas', 'idx_harga_filter')) {
    $db->exec('ALTER TABLE harga_komoditas ADD INDEX idx_harga_filter (tanggal, jenis_komoditas)');
}
if (!$indexExists('harga_komoditas', 'idx_harga_method')) {
    $db->exec('ALTER TABLE harga_komoditas ADD INDEX idx_harga_method (metode_data, sumber_data)');
}

if ($tableExists('harga_alerts')) {
    $alertBackup = 'harga_alerts_backup_20260811';
    if (!$tableExists($alertBackup)) {
        $db->exec("CREATE TABLE {$alertBackup} LIKE harga_alerts");
    }
    $db->exec("INSERT IGNORE INTO {$alertBackup} SELECT * FROM harga_alerts");
    $db->exec('DELETE FROM harga_alerts');
    if (!$indexExists('harga_alerts', 'uk_harga_alert_daily')) {
        $db->exec(
            'ALTER TABLE harga_alerts
             MODIFY jenis_komoditas VARCHAR(50) NOT NULL,
             MODIFY tanggal DATE NOT NULL,
             ADD UNIQUE KEY uk_harga_alert_daily (jenis_komoditas, tanggal)'
        );
    }
}

require_once $rootPath . '/app/models/HargaKomoditas.php';
$model = new HargaKomoditas();
$alertsCreated = $model->rebuildAlerts();
$duplicatesAfter = (int) $db->query(
    "SELECT COUNT(*) FROM (
        SELECT 1 FROM harga_komoditas
        GROUP BY tanggal, jenis_komoditas, lokasi, sumber_data
        HAVING COUNT(*) > 1
     ) remaining"
)->fetchColumn();
if ($duplicatesAfter !== 0) {
    throw new RuntimeException('Masih terdapat observasi duplikat setelah migrasi');
}

echo sprintf(
    "[OK] Integritas harga diperbarui: %d duplikasi diarsipkan, %d alert harian dibangun.\n",
    $duplicatesBefore,
    $alertsCreated
);
