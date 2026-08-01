<?php
/**
 * Migration: Add nomor_laporan column to laporan_hama table
 * Date: 2026-07-20
 * Purpose: Support auto-generated report numbers for submitted reports
 */

require_once __DIR__ . '/../../app/core/Database.php';

$rollback = in_array('--rollback', $argv ?? [], true);
$db = Database::getInstance()->getConnection();

$columnName = 'nomor_laporan';

// Check if column already exists
$stmt = $db->query("SHOW COLUMNS FROM laporan_hama LIKE '{$columnName}'");
$columnExists = $stmt->rowCount() > 0;

if ($rollback) {
    if (!$columnExists) {
        echo "Kolom {$columnName} sudah tidak ada. Tidak ada perubahan.\n";
        exit(0);
    }

    $db->exec("ALTER TABLE laporan_hama DROP INDEX IF EXISTS uk_nomor_laporan");
    $db->exec("ALTER TABLE laporan_hama DROP COLUMN {$columnName}");
    echo "Rollback selesai. Kolom {$columnName} dihapus.\n";
    exit(0);
}

if ($columnExists) {
    echo "Kolom {$columnName} sudah ada. Tidak ada perubahan.\n";
    exit(0);
}

// Add the column after status
$db->exec("ALTER TABLE laporan_hama 
    ADD COLUMN {$columnName} VARCHAR(30) DEFAULT NULL AFTER status,
    ADD UNIQUE KEY uk_nomor_laporan ({$columnName})");

echo "Migration selesai. Kolom {$columnName} berhasil ditambahkan ke laporan_hama.\n";
echo "Format nomor: LH-YYYYMM-NNNN (e.g., LH-202607-0001)\n";
echo "Unique constraint diterapkan untuk mencegah duplikasi.\n";
