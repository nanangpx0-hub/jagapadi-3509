<?php

declare(strict_types=1);

/**
 * Align provenance and natural keys for wind, rainfall, irrigation, and OPT.
 *
 * Run: php database/migrations/2026_08_11_fix_environmental_analysis.php
 */

$rootPath = dirname(__DIR__, 2);
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
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"'))
            || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$db = Database::getInstance()->getConnection();
$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();

$columnExists = static function (string $table, string $column) use ($db, $databaseName): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$databaseName, $table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$indexExists = static function (string $table, string $index) use ($db, $databaseName): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$databaseName, $table, $index]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$columnExists('data_irigasi', 'metode_data')) {
    $db->exec(
        "ALTER TABLE data_irigasi
         ADD COLUMN metode_data ENUM('aktual','manual','simulasi') NOT NULL DEFAULT 'manual'
         AFTER status_pintu"
    );
}

$irrigationUpdated = $db->exec(
    "UPDATE data_irigasi
     SET metode_data = 'simulasi',
         keterangan = 'Data simulasi internal berbasis norma debit dan pola musim; bukan hasil observasi lapangan.'
     WHERE keterangan LIKE 'Data observasi harian.%'"
);

$windFutureDeleted = $db->exec(
    "DELETE FROM kecepatan_angin
     WHERE tanggal > CURDATE() AND sumber_data LIKE 'Simulasi%'"
);
$windNotesNormalized = $db->exec(
    "UPDATE kecepatan_angin
     SET keterangan = TRIM(SUBSTRING_INDEX(keterangan, ' | ', 1))
     WHERE sumber_data LIKE 'NASA%'
       AND keterangan LIKE '% | %'
       AND SUBSTRING_INDEX(keterangan, ' | ', 1) = SUBSTRING_INDEX(keterangan, ' | ', -1)"
);
$db->exec(
    "UPDATE kecepatan_angin
     SET keterangan = 'Data simulasi untuk pengujian. Tidak mencerminkan kondisi aktual.'
     WHERE sumber_data LIKE 'Simulasi%'"
);
if ($indexExists('kecepatan_angin', 'uk_tgl_lokasi')) {
    $db->exec('ALTER TABLE kecepatan_angin DROP INDEX uk_tgl_lokasi');
}
if (!$indexExists('kecepatan_angin', 'uk_tgl_lokasi_sumber')) {
    $db->exec(
        'ALTER TABLE kecepatan_angin
         ADD UNIQUE KEY uk_tgl_lokasi_sumber (tanggal, lokasi, sumber_data)'
    );
}

$rainFutureDeleted = $db->exec(
    "DELETE FROM curah_hujan
     WHERE tanggal > CURDATE() AND sumber_data LIKE 'Simulasi%'"
);
if ($indexExists('curah_hujan', 'unique_tanggal_lokasi')) {
    $db->exec('ALTER TABLE curah_hujan DROP INDEX unique_tanggal_lokasi');
}
if (!$indexExists('curah_hujan', 'unique_tanggal_lokasi_sumber')) {
    $db->exec(
        'ALTER TABLE curah_hujan
         ADD UNIQUE KEY unique_tanggal_lokasi_sumber (tanggal, lokasi, sumber_data)'
    );
}

$optColumns = [
    'filum' => 'VARCHAR(100) NULL AFTER kingdom',
    'kelas' => 'VARCHAR(100) NULL AFTER filum',
    'ordo' => 'VARCHAR(100) NULL AFTER kelas',
    'famili' => 'VARCHAR(100) NULL AFTER ordo',
    'genus' => 'VARCHAR(100) NULL AFTER famili',
    'rekomendasi' => 'TEXT NULL AFTER deskripsi',
    'referensi' => 'TEXT NULL AFTER rekomendasi',
];
foreach ($optColumns as $column => $definition) {
    if (!$columnExists('master_opt', $column)) {
        $db->exec("ALTER TABLE master_opt ADD COLUMN `{$column}` {$definition}");
    }
}

echo "[OK] Provenance dan natural key data lingkungan diperbaiki.\n";
echo "[OK] Irigasi lama yang dilabel ulang sebagai simulasi: {$irrigationUpdated}.\n";
echo "[OK] Simulasi angin masa depan dihapus: {$windFutureDeleted}.\n";
echo "[OK] Catatan angin NASA duplikat dinormalisasi: {$windNotesNormalized}.\n";
echo "[OK] Simulasi hujan masa depan dihapus: {$rainFutureDeleted}.\n";
echo "[OK] Kolom klasifikasi OPT tersedia.\n";
