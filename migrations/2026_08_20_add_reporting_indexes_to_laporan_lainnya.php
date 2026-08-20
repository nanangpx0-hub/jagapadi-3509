<?php
/**
 * Migration: Add Performance Indexes to Laporan Lainnya
 *
 * Menambahkan indeks komposit untuk optimasi performa query rekapitulasi pelaporan petugas
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

class AddReportingIndexesToLaporanLainnya {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function up() {
        $this->alterStatusEnum();
        $this->addKodeLaporanNullable();
        $this->addPerformanceIndexes();
    }

    private function alterStatusEnum() {
        // Check if 'archived' status already exists
        $checkSql = "SHOW COLUMNS FROM laporan_lainnya LIKE 'status'";
        $result = $this->db->query($checkSql)->fetch(PDO::FETCH_ASSOC);
        
        if ($result && str_contains($result['Type'], 'archived')) {
            echo "[SKIP] Status enum already includes 'archived'.\n";
            return;
        }

        // Alter status enum to include 'archived'
        $sql = "ALTER TABLE laporan_lainnya 
                MODIFY COLUMN status ENUM('draft','submitted','verified','rejected','archived') 
                NOT NULL DEFAULT 'draft'";
        
        $this->db->exec($sql);
        echo "[OK] Status enum updated to include 'archived'.\n";
    }

    private function addKodeLaporanNullable() {
        // Check if kode_laporan is already nullable
        $checkSql = "SHOW COLUMNS FROM laporan_lainnya LIKE 'kode_laporan'";
        $result = $this->db->query($checkSql)->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['Null'] === 'YES') {
            echo "[SKIP] kode_laporan is already nullable.\n";
            return;
        }

        // Make kode_laporan nullable for draft reports
        $sql = "ALTER TABLE laporan_lainnya 
                MODIFY COLUMN kode_laporan VARCHAR(30) NULL DEFAULT NULL";
        
        $this->db->exec($sql);
        echo "[OK] kode_laporan column made nullable.\n";
    }

    private function addPerformanceIndexes() {
        // Check if indexes already exist
        $checkSql = "SHOW INDEX FROM laporan_lainnya WHERE Key_name = 'idx_petugas_status_tgl'";
        $result = $this->db->query($checkSql)->fetch();
        
        if ($result) {
            echo "[SKIP] Performance indexes already exist.\n";
            return;
        }

        // Add composite index for petugas performance summary queries
        $sql1 = "ALTER TABLE laporan_lainnya 
                 ADD KEY idx_petugas_status_tgl (user_id, status, tanggal_kejadian)";
        
        $this->db->exec($sql1);
        echo "[OK] Composite index idx_petugas_status_tgl added.\n";

        // Add composite index for jenis breakdown queries
        $sql2 = "ALTER TABLE laporan_lainnya 
                 ADD KEY idx_petugas_jenis_created (user_id, jenis_id, created_at)";
        
        $this->db->exec($sql2);
        echo "[OK] Composite index idx_petugas_jenis_created added.\n";
    }

    public function down() {
        // Remove composite indexes
        $this->db->exec("ALTER TABLE laporan_lainnya DROP INDEX idx_petugas_status_tgl");
        $this->db->exec("ALTER TABLE laporan_lainnya DROP INDEX idx_petugas_jenis_created");
        
        // Revert kode_laporan to NOT NULL
        $this->db->exec("ALTER TABLE laporan_lainnya MODIFY COLUMN kode_laporan VARCHAR(30) NOT NULL");
        
        // Revert status enum
        $this->db->exec("ALTER TABLE laporan_lainnya 
                        MODIFY COLUMN status ENUM('draft','submitted','verified','rejected') 
                        NOT NULL DEFAULT 'draft'");
        
        echo "[OK] Migration rolled back.\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddReportingIndexesToLaporanLainnya();
    $action = $argv[1] ?? 'up';
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
