<?php
/**
 * Migration: Soft Delete master_jenis_laporan (Recycle Bin)
 *
 * Menambahkan kolom `deleted_at` + `deleted_by` agar jenis laporan yang
 * dihapus masuk recycle bin (dapat dipulihkan) mengikuti pola
 * laporan_hama / laporan_irigasi / laporan_lainnya. Hapus permanen tetap
 * diblokir bila jenis masih dipakai laporan (FK RESTRICT).
 *
 * Run: php migrations/2026_09_05_add_soft_delete_master_jenis_laporan.php
 * Rollback: php migrations/2026_09_05_add_soft_delete_master_jenis_laporan.php down
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

class AddSoftDeleteMasterJenisLaporan {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    private function hasColumn(string $column): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute(['master_jenis_laporan', $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function hasIndex(string $index): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute(['master_jenis_laporan', $index]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function up() {
        if (!$this->hasColumn('deleted_at')) {
            $this->db->exec(
                'ALTER TABLE `master_jenis_laporan` '
                . 'ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_active`'
            );
            echo "[OK] Column deleted_at added.\n";
        } else {
            echo "[SKIP] Column deleted_at already exists.\n";
        }

        if (!$this->hasColumn('deleted_by')) {
            $this->db->exec(
                'ALTER TABLE `master_jenis_laporan` '
                . 'ADD COLUMN `deleted_by` INT UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`'
            );
            echo "[OK] Column deleted_by added.\n";
        } else {
            echo "[SKIP] Column deleted_by already exists.\n";
        }

        if (!$this->hasIndex('idx_mjl_deleted_at')) {
            $this->db->exec(
                'ALTER TABLE `master_jenis_laporan` ADD INDEX `idx_mjl_deleted_at` (`deleted_at`)'
            );
            echo "[OK] Index idx_mjl_deleted_at added.\n";
        } else {
            echo "[SKIP] Index idx_mjl_deleted_at already exists.\n";
        }
    }

    public function down() {
        if ($this->hasIndex('idx_mjl_deleted_at')) {
            $this->db->exec('ALTER TABLE `master_jenis_laporan` DROP INDEX `idx_mjl_deleted_at`');
            echo "[OK] Index idx_mjl_deleted_at dropped.\n";
        }
        if ($this->hasColumn('deleted_by')) {
            $this->db->exec('ALTER TABLE `master_jenis_laporan` DROP COLUMN `deleted_by`');
            echo "[OK] Column deleted_by dropped.\n";
        }
        if ($this->hasColumn('deleted_at')) {
            $this->db->exec('ALTER TABLE `master_jenis_laporan` DROP COLUMN `deleted_at`');
            echo "[OK] Column deleted_at dropped.\n";
        }
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddSoftDeleteMasterJenisLaporan();
    $action = $argv[1] ?? 'up';
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
