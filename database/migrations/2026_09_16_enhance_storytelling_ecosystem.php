<?php

declare(strict_types=1);

/**
 * Migration: Menambahkan dukungan integrasi ekosistem multi-sektor dan persistensi
 * analisis lanjutan pada tabel analisis_produksi_bulanan.
 *
 * Kolom yang ditambahkan:
 * 1. advanced_analysis_json JSON NULL - Menyimpan hasil 5 metode analisis lanjutan
 * 2. avg_debit_irigasi_lag1 DECIMAL(10,2) NULL - Rata-rata debit irigasi lag-1
 * 3. avg_kecepatan_angin_lag1 DECIMAL(8,2) NULL - Rata-rata kecepatan angin lag-1
 *
 * Jalankan:
 *   php database/migrations/2026_09_16_enhance_storytelling_ecosystem.php
 * Rollback:
 *   php database/migrations/2026_09_16_enhance_storytelling_ecosystem.php --rollback
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

$table = 'analisis_produksi_bulanan';

$columns = [
    'advanced_analysis_json' => 'JSON NULL AFTER `source_snapshot_json`',
    'avg_debit_irigasi_lag1' => 'DECIMAL(10,2) NULL AFTER `avg_curah_hujan_lag1`',
    'avg_kecepatan_angin_lag1' => 'DECIMAL(8,2) NULL AFTER `avg_debit_irigasi_lag1`',
];

if ($rollback) {
    foreach (array_keys($columns) as $column) {
        if ($columnExists($db, $table, $column)) {
            $db->exec("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
            echo "[OK] Kolom `{$column}` berhasil dihapus dari `{$table}`.\n";
        }
    }
    echo "[OK] Rollback migration ekosistem storytelling selesai.\n";
    exit(0);
}

foreach ($columns as $column => $definition) {
    if (!$columnExists($db, $table, $column)) {
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "[OK] Kolom `{$column}` berhasil ditambahkan ke `{$table}`.\n";
    } else {
        echo "[SKIP] Kolom `{$column}` sudah ada pada `{$table}`.\n";
    }
}

echo "[OK] Migration ekosistem storytelling berhasil dieksekusi.\n";
