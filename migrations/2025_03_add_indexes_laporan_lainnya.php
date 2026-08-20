<?php
/**
 * Migration: Index Optimasi Query `laporan_lainnya`
 *
 * Menambahkan index untuk mempercepat query yang sering dijalankan:
 *  1. idx_ll_user_status    - query utama petugas (user_id + status + created_at)
 *  2. idx_ll_tanggal_status - filter tanggal kejadian (date_from/date_to)
 *  3. idx_ll_jenis_status   - filter jenis laporan
 *  4. idx_ll_desa           - filter query wilayah (desa_id)
 *  5. idx_ll_kode_laporan   - lookup kode_laporan untuk nextKodeLaporan()
 *  6. idx_mjl_is_active     - jenis laporan aktif (master_jenis_laporan)
 *
 * Catatan: `ADD INDEX IF NOT EXISTS` didukung oleh MariaDB (target DB).
 * Di MySQL klasik, jalankan dengan hati-hati karena IF NOT EXISTS tidak
 * dikenali — error "Duplicate key name" akan di-skip di up().
 *
 * Run: php migrations/2025_03_add_indexes_laporan_lainnya.php
 * Rollback: php migrations/2025_03_add_indexes_laporan_lainnya.php down
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

define('ROOT_PATH', dirname(__DIR__));

// Load .env file
$envPaths = [ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'];
foreach ($envPaths as $envPath) {
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

require_once ROOT_PATH . '/app/core/Database.php';

class AddIndexesLaporanLainnya {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function up() {
        $statements = [
            // Index 1: query utama petugas (user_id + status + created_at)
            'ALTER TABLE laporan_lainnya ADD INDEX IF NOT EXISTS idx_ll_user_status (user_id, status, created_at DESC)',
            // Index 2: filter tanggal kejadian
            'ALTER TABLE laporan_lainnya ADD INDEX IF NOT EXISTS idx_ll_tanggal_status (tanggal_kejadian, status)',
            // Index 3: filter jenis laporan
            'ALTER TABLE laporan_lainnya ADD INDEX IF NOT EXISTS idx_ll_jenis_status (jenis_id, status)',
            // Index 4: filter desa_id untuk query wilayah
            'ALTER TABLE laporan_lainnya ADD INDEX IF NOT EXISTS idx_ll_desa (desa_id)',
            // Index 5: lookup kode_laporan untuk nextKodeLaporan()
            'ALTER TABLE laporan_lainnya ADD INDEX IF NOT EXISTS idx_ll_kode_laporan (kode_laporan)',
            // Index 6: jenis laporan aktif
            'ALTER TABLE master_jenis_laporan ADD INDEX IF NOT EXISTS idx_mjl_is_active (is_active)',
        ];

        foreach ($statements as $sql) {
            try {
                $this->db->exec($sql);
                echo "[OK] " . $sql . "\n";
            } catch (PDOException $e) {
                // Di MySQL klasik, IF NOT EXISTS tidak dikenali —
                // abaikan error "Duplicate key name" agar migration idempoten.
                if (str_contains($e->getMessage(), 'Duplicate key name')
                    || str_contains($e->getMessage(), 'Duplicate')
                    || str_contains($e->getMessage(), 'already exists')) {
                    echo "[SKIP] index sudah ada: " . $sql . "\n";
                } else {
                    throw $e;
                }
            }
        }
    }

    public function down() {
        $statements = [
            'ALTER TABLE laporan_lainnya DROP INDEX IF EXISTS idx_ll_user_status',
            'ALTER TABLE laporan_lainnya DROP INDEX IF EXISTS idx_ll_tanggal_status',
            'ALTER TABLE laporan_lainnya DROP INDEX IF EXISTS idx_ll_jenis_status',
            'ALTER TABLE laporan_lainnya DROP INDEX IF EXISTS idx_ll_desa',
            'ALTER TABLE laporan_lainnya DROP INDEX IF EXISTS idx_ll_kode_laporan',
            'ALTER TABLE master_jenis_laporan DROP INDEX IF EXISTS idx_mjl_is_active',
        ];

        foreach ($statements as $sql) {
            $this->db->exec($sql);
            echo "[OK] " . $sql . "\n";
        }
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddIndexesLaporanLainnya();
    $action = $argv[1] ?? 'up';
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}