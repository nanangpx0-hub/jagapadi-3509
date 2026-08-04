<?php

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/app/core/Database.php';

foreach ([$rootPath . '/.env', $rootPath . '/.env.local'] as $envPath) {
    if (!file_exists($envPath)) continue;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            $eqPos = strpos($line, '=');
            if ($eqPos === false) continue;
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if (empty($key)) continue;
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$rollback = in_array('--rollback', $argv ?? [], true);
$db = Database::getInstance()->getConnection();

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$additions = [
    'kabupaten_id' => "ALTER TABLE `laporan_lainnya` ADD COLUMN `kabupaten_id` INT UNSIGNED NULL DEFAULT NULL AFTER `jenis_id`",
    'kecamatan_id' => "ALTER TABLE `laporan_lainnya` ADD COLUMN `kecamatan_id` INT UNSIGNED NULL DEFAULT NULL AFTER `kabupaten_id`",
    'alamat_lengkap' => "ALTER TABLE `laporan_lainnya` ADD COLUMN `alamat_lengkap` VARCHAR(255) NULL DEFAULT NULL AFTER `desa_id`",
    'foto_url' => "ALTER TABLE `laporan_lainnya` ADD COLUMN `foto_url` VARCHAR(255) NULL DEFAULT NULL AFTER `alamat_lengkap`",
];

$removals = [
    'foto_url' => "ALTER TABLE `laporan_lainnya` DROP COLUMN `foto_url`",
    'alamat_lengkap' => "ALTER TABLE `laporan_lainnya` DROP COLUMN `alamat_lengkap`",
    'kecamatan_id' => "ALTER TABLE `laporan_lainnya` DROP COLUMN `kecamatan_id`",
    'kabupaten_id' => "ALTER TABLE `laporan_lainnya` DROP COLUMN `kabupaten_id`",
];

if ($rollback) {
    foreach ($removals as $column => $sql) {
        if (columnExists($db, 'laporan_lainnya', $column)) {
            $db->exec($sql);
            echo "Rollback: kolom {$column} dihapus.\n";
        }
    }
    echo "Rollback selesai. Kolom wilayah/foto pada laporan_lainnya dikembalikan ke keadaan awal.\n";
    exit(0);
}

foreach ($additions as $column => $sql) {
    if (columnExists($db, 'laporan_lainnya', $column)) {
        echo "SKIP: kolom {$column} sudah ada.\n";
        continue;
    }
    $db->exec($sql);
    echo "[OK] Kolom {$column} ditambahkan.\n";
}

echo "Migration selesai. laporan_lainnya kini memiliki kabupaten_id, kecamatan_id, alamat_lengkap, foto_url.\n";
