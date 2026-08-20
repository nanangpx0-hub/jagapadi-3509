<?php

declare(strict_types=1);

/**
 * Memisahkan data tahunan BPS berdasarkan sumber dan skenario.
 *
 * Sebelumnya UNIQUE(tahun, kabupaten_kota) membuat data simulasi, WebAPI,
 * manual, dan hasil agregasi KSA saling menimpa. Migrasi ini menambahkan KSA
 * sebagai tipe sumber resmi dan mengubah grain unik menjadi
 * tahun + provinsi + kabupaten + sumber + skenario.
 *
 * Jalankan:
 *   php database/migrations/2026_08_11_fix_bps_source_integrity.php
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

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines ?: [] as $line) {
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

$columnExists = static function (PDO $connection, string $table, string $column): bool {
    $stmt = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$indexExists = static function (PDO $connection, string $table, string $index): bool {
    $stmt = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
};

$tableExists = static function (PDO $connection, string $table): bool {
    $stmt = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$columnExists($db, 'data_pertanian_bps', 'kode_provinsi')) {
    $db->exec(
        "ALTER TABLE `data_pertanian_bps`
         ADD COLUMN `kode_provinsi` VARCHAR(10) NOT NULL DEFAULT '35' AFTER `tahun`"
    );
}

$db->exec(
    "ALTER TABLE `data_pertanian_bps`
     MODIFY COLUMN `sumber_data_type`
     ENUM('ksa','resmi_webapi','manual','simulasi') NOT NULL DEFAULT 'simulasi'"
);

$db->exec(
    "UPDATE `data_pertanian_bps`
     SET `sumber_data_type` = 'ksa', `is_validated` = 1
     WHERE `sumber_data` LIKE 'KSA BPS %'"
);

$db->exec(
    "UPDATE `data_pertanian_bps`
     SET `tipe_skenario` = 'baseline'
     WHERE `tipe_skenario` IS NULL"
);
$db->exec(
    "ALTER TABLE `data_pertanian_bps`
     MODIFY COLUMN `tipe_skenario`
     ENUM('baseline','optimis','pesimis') NOT NULL DEFAULT 'baseline'"
);

foreach (['unique_data', 'unique_tahun_prov_kab'] as $oldIndex) {
    if ($indexExists($db, 'data_pertanian_bps', $oldIndex)) {
        $db->exec("ALTER TABLE `data_pertanian_bps` DROP INDEX `{$oldIndex}`");
    }
}

if (!$indexExists($db, 'data_pertanian_bps', 'uk_bps_source_scenario')) {
    $db->exec(
        'ALTER TABLE `data_pertanian_bps`
         ADD UNIQUE KEY `uk_bps_source_scenario`
         (`tahun`, `kode_provinsi`, `kabupaten_kota`, `sumber_data_type`, `tipe_skenario`)'
    );
}

if (!$tableExists($db, 'bps_scraping_queue')) {
    $db->exec(
        "CREATE TABLE `bps_scraping_queue` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tahun` INT NOT NULL,
            `kabupaten` VARCHAR(100) NULL,
            `source` VARCHAR(50) NOT NULL DEFAULT 'simulasi',
            `skenario` VARCHAR(50) NOT NULL DEFAULT 'baseline',
            `force_refresh` TINYINT(1) NOT NULL DEFAULT 0,
            `status` ENUM('pending','running','completed','failed') DEFAULT 'pending',
            `progress` INT DEFAULT 0,
            `result` JSON NULL,
            `error_message` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `started_at` TIMESTAMP NULL,
            `completed_at` TIMESTAMP NULL,
            INDEX `idx_status_created` (`status`, `created_at`),
            INDEX `idx_tahun` (`tahun`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
} elseif (!$columnExists($db, 'bps_scraping_queue', 'force_refresh')) {
    $db->exec(
        'ALTER TABLE `bps_scraping_queue`
         ADD COLUMN `force_refresh` TINYINT(1) NOT NULL DEFAULT 0 AFTER `skenario`'
    );
}

require_once $rootPath . '/app/models/DataPertanianBps.php';
require_once $rootPath . '/app/services/BpsDataService.php';
$dataService = new BpsDataService();
$years = $db->query(
    'SELECT DISTINCT `tahun` FROM `data_pertanian_bps` ORDER BY `tahun`'
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($years as $year) {
    $dataService->updateYearlySummary((int) $year);
}

echo "[OK] Integritas sumber data BPS dan antrean scraper diperbarui.\n";
