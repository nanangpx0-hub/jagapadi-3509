<?php

declare(strict_types=1);

/**
 * Menyelaraskan grain produksi bulanan, metadata analisis, dan index query
 * storytelling. Data produksi tahunan lama dibiarkan dengan bulan = NULL dan
 * tidak dipakai sebagai outcome bulanan.
 *
 * Jalankan:
 *   php database/migrations/2026_08_11_fix_storytelling_analysis.php
 * Rollback:
 *   php database/migrations/2026_08_11_fix_storytelling_analysis.php --rollback
 */

$rootPath = dirname(__DIR__, 2);
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
$rollback = in_array('--rollback', $argv ?? [], true);

$columnExists = static function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$indexExists = static function (PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
};

if ($rollback) {
    $indexes = [
        ['produksi_gabah', 'idx_story_production_period'],
        ['curah_hujan', 'idx_story_rain_period'],
        ['laporan_hama', 'idx_story_pest_period'],
    ];
    foreach ($indexes as [$table, $index]) {
        if ($indexExists($db, $table, $index)) {
            $db->exec("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    $analysisColumns = [
        'total_produksi',
        'avg_produktivitas',
        'perubahan_produksi_pct',
        'data_quality_json',
        'source_snapshot_json',
        'algorithm_version',
        'published_by',
        'published_at',
    ];
    foreach (array_reverse($analysisColumns) as $column) {
        if ($columnExists($db, 'analisis_produksi_bulanan', $column)) {
            $db->exec("ALTER TABLE `analisis_produksi_bulanan` DROP COLUMN `{$column}`");
        }
    }

    if ($columnExists($db, 'produksi_gabah', 'bulan')) {
        $db->exec('ALTER TABLE `produksi_gabah` DROP COLUMN `bulan`');
    }

    echo "[OK] Rollback migration storytelling selesai.\n";
    exit(0);
}

if (!$columnExists($db, 'produksi_gabah', 'bulan')) {
    $db->exec(
        'ALTER TABLE `produksi_gabah`
         ADD COLUMN `bulan` TINYINT UNSIGNED NULL AFTER `tahun`'
    );
}

$analysisColumns = [
    'total_produksi' => 'DECIMAL(15,2) NULL AFTER `total_luas_panen`',
    'avg_produktivitas' => 'DECIMAL(12,4) NULL AFTER `total_produksi`',
    'perubahan_produksi_pct' => 'DECIMAL(10,2) NULL AFTER `avg_produktivitas`',
    'data_quality_json' => 'JSON NULL AFTER `narasi_final`',
    'source_snapshot_json' => 'JSON NULL AFTER `data_quality_json`',
    'algorithm_version' => 'VARCHAR(20) NOT NULL DEFAULT \'2.0.0\' AFTER `source_snapshot_json`',
    'published_by' => 'INT UNSIGNED NULL AFTER `created_by`',
    'published_at' => 'TIMESTAMP NULL AFTER `published_by`',
];

foreach ($analysisColumns as $column => $definition) {
    if (!$columnExists($db, 'analisis_produksi_bulanan', $column)) {
        $db->exec(
            "ALTER TABLE `analisis_produksi_bulanan` ADD COLUMN `{$column}` {$definition}"
        );
    }
}

$indexes = [
    'produksi_gabah' => [
        'idx_story_production_period',
        '(`kecamatan_id`, `tahun`, `bulan`, `status`)',
    ],
    'curah_hujan' => [
        'idx_story_rain_period',
        '(`kecamatan_id`, `tanggal`)',
    ],
    'laporan_hama' => [
        'idx_story_pest_period',
        '(`kecamatan_id`, `tanggal`, `status`)',
    ],
];

foreach ($indexes as $table => [$index, $columns]) {
    if (!$indexExists($db, $table, $index)) {
        $db->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` {$columns}");
    }
}

echo "[OK] Migration perbaikan storytelling selesai.\n";
