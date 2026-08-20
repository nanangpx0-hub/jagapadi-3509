<?php
declare(strict_types=1);

/**
 * Migration: Add missing indexes to laporan_hama table
 * Indexes untuk memperbaiki performa query laporan
 * Run: php database/migrations/2026_08_19_add_missing_laporan_indexes.php
 */

$rootPath = dirname(__DIR__, 2);
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $rootPath);
}

require_once $rootPath . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

// Index 1: idx_lh_user_tanggal - untuk filter laporan berdasarkan user dan tanggal
$db->exec("ALTER TABLE laporan_hama ADD INDEX IF NOT EXISTS idx_lh_user_tanggal (user_id, tanggal)");

// Index 2: idx_lh_status_tanggal - untuk filter laporan berdasarkan status dan tanggal
$db->exec("ALTER TABLE laporan_hama ADD INDEX IF NOT EXISTS idx_lh_status_tanggal (status, tanggal)");

// Index 3: idx_lh_user_status - untuk filter laporan berdasarkan user dan status (dashboard petugas)
$db->exec("ALTER TABLE laporan_hama ADD INDEX IF NOT EXISTS idx_lh_user_status (user_id, status)");

// Index 4: idx_lsh_laporan_id - untuk join laporan_status_history dengan laporan_hama
$db->exec("ALTER TABLE laporan_status_history ADD INDEX IF NOT EXISTS idx_lsh_laporan_id (laporan_id)");

// Index 5: idx_notif_user_created - untuk notifikasi berdasarkan user yang dibuat
$db->exec("ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_notif_user_created (user_id)");

echo "[OK] 5 index berhasil ditambahkan ke tabel laporan_hama dan laporan_status_history.\n";
echo "[OK] Index dibuat dengan IF NOT EXISTS - idempotent, bisa di-replay.\n";