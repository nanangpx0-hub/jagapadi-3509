<?php
/**
 * Migration: Add kecamatan_id column to curah_hujan table
 * Date: 2026-08-01
 * Purpose: Optimize queries by avoiding Full Table Scans with LIKE operators
 */

require_once __DIR__ . '/../../app/core/Database.php';

$rollback = in_array('--rollback', $argv ?? [], true);
$db = Database::getInstance()->getConnection();

$columnName = 'kecamatan_id';

// Check if column already exists
$stmt = $db->query("SHOW COLUMNS FROM curah_hujan LIKE '{$columnName}'");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$columnExists = count($columns) > 0;

if ($rollback) {
    if (!$columnExists) {
        echo "Kolom {$columnName} sudah tidak ada. Tidak ada perubahan.\n";
        exit(0);
    }

    $db->exec("ALTER TABLE curah_hujan DROP INDEX IF EXISTS idx_curah_hujan_kecamatan");
    $db->exec("ALTER TABLE curah_hujan DROP COLUMN {$columnName}");
    echo "Rollback selesai. Kolom {$columnName} dihapus.\n";
    exit(0);
}

if ($columnExists) {
    echo "Kolom {$columnName} sudah ada. Tidak ada perubahan.\n";
    exit(0);
}

// Add the column
$db->exec("ALTER TABLE curah_hujan 
    ADD COLUMN {$columnName} INT(11) DEFAULT NULL AFTER lokasi,
    ADD INDEX idx_curah_hujan_kecamatan ({$columnName})");

echo "Kolom {$columnName} berhasil ditambahkan.\n";

// Backfill data
echo "Memulai proses backfilling data untuk mencocokkan lokasi dengan kecamatan_id...\n";

// Use INSTR or LIKE to match `lokasi` to `nama_kecamatan`
$sql = "UPDATE curah_hujan ch
        JOIN master_kecamatan mk ON ch.lokasi LIKE CONCAT('%', mk.nama_kecamatan, '%')
        SET ch.kecamatan_id = mk.id
        WHERE ch.kecamatan_id IS NULL";
        
$updated = $db->exec($sql);

echo "Backfilling selesai. {$updated} baris diperbarui.\n";
echo "Migration berhasil.\n";
