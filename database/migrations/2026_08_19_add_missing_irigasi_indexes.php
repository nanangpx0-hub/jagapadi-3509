<?php
declare(strict_types=1);

/**
 * Migration: Add missing indexes to laporan_irigasi & data_irigasi
 * Indexes untuk memperbaiki performa query laporan irigasi
 * Run: php database/migrations/2026_08_19_add_missing_irigasi_indexes.php
 */

$rootPath = dirname(__DIR__, 2);
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $rootPath);
}

require_once $rootPath . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

/**
 * Portable index check: kompatibel MySQL 8+ dan MariaDB 10.2+
 * (MySQL tidak mendukung "ADD INDEX IF NOT EXISTS" sebelum 8.0.x tertentu,
 * dan meng-add index dengan prefix kolom yang sama hanya akan redundan).
 * Dilewati jika sudah ada index dengan kolom pertama yang sama.
 */
$created = 0;
$skipped = 0;

function indexExists(PDO $db, string $table, string $firstColumn): bool
{
    $stmt = $db->prepare(
        "SELECT 1 FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ?
           AND seq_in_index = 1 AND column_name = ?
         LIMIT 1"
    );
    $stmt->execute([$table, $firstColumn]);
    return (bool) $stmt->fetchColumn();
}

function addIndex(PDO $db, string $table, string $name, string $definition, string $firstColumn): string
{
    global $created, $skipped;
    if (indexExists($db, $table, $firstColumn)) {
        $skipped++;
        return "  - $name dilewati (sudah ada index pada kolom $firstColumn)";
    }
    $db->exec("ALTER TABLE `$table` ADD INDEX `$name` $definition");
    $created++;
    return "  + $name ditambahkan: $definition";
}

echo "[..] Index laporan_irigasi & data_irigasi\n";

// Index 1: query getAllWithDetails petugas (filter user_id + ORDER BY tanggal)
echo addIndex($db, 'laporan_irigasi', 'idx_li_user_tanggal', '(user_id, tanggal DESC)', 'user_id') . "\n";

// Index 2: filter status laporan irigasi
echo addIndex($db, 'laporan_irigasi', 'idx_li_status', '(status, created_at DESC)', 'status') . "\n";

// Index 3: countAll dengan user_id filter (diwakili oleh idx_user bila sudah ada)
echo addIndex($db, 'laporan_irigasi', 'idx_li_user_id', '(user_id)', 'user_id') . "\n";

// Index 4: nextNomorLaporan() (unik index uk_nomor_laporan juga melayani lookup)
echo addIndex($db, 'laporan_irigasi', 'idx_li_nomor_laporan', '(nomor_laporan)', 'nomor_laporan') . "\n";

// Index 5: verify query (WHERE status = 'Submitted')
echo addIndex($db, 'laporan_irigasi', 'idx_li_status_submitted', '(status)', 'status') . "\n";

// Index 6: data_irigasi query (untuk DashboardDataAggregator)
echo addIndex($db, 'data_irigasi', 'idx_di_tanggal_daerah', '(tanggal, daerah_irigasi(50))', 'tanggal') . "\n";

echo "\n[OK] $created index dibuat, $skipped dilewati (sudah ada).\n";
echo "[OK] Idempotent - bisa di-replay tanpa error.\n";
echo "\n-- ROLLBACK (hanya untuk index yang benar-benar dibuat):\n";
echo "-- ALTER TABLE laporan_irigasi DROP INDEX IF EXISTS idx_li_user_tanggal;\n";
echo "-- ALTER TABLE laporan_irigasi DROP INDEX IF EXISTS idx_li_status;\n";
echo "-- ALTER TABLE laporan_irigasi DROP INDEX IF EXISTS idx_li_user_id;\n";
echo "-- ALTER TABLE laporan_irigasi DROP INDEX IF EXISTS idx_li_nomor_laporan;\n";
echo "-- ALTER TABLE laporan_irigasi DROP INDEX IF EXISTS idx_li_status_submitted;\n";
echo "-- ALTER TABLE data_irigasi DROP INDEX IF EXISTS idx_di_tanggal_daerah;\n";