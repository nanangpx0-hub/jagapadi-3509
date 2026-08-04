<?php
/**
 * Migration: Laporan Lainnya
 *
 * Membuat dua tabel:
 * 1. master_jenis_laporan - master data jenis laporan selain hama dan irigasi
 * 2. laporan_lainnya - laporan fenomena di luar hama dan irigasi
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

class CreateLaporanLainnya {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function up() {
        $this->createMasterJenisLaporan();
        $this->createLaporanLainnya();
        $this->insertDefaultJenis();
    }

    private function createMasterJenisLaporan() {
        $sql = "CREATE TABLE IF NOT EXISTS `master_jenis_laporan` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `kode` VARCHAR(50) NOT NULL,
            `nama` VARCHAR(150) NOT NULL,
            `deskripsi` TEXT NULL DEFAULT NULL,
            `fields_json` JSON NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_kode` (`kode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
        echo "[OK] Table master_jenis_laporan created/exists.\n";
    }

    private function createLaporanLainnya() {
        $sql = "CREATE TABLE IF NOT EXISTS `laporan_lainnya` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `jenis_id` BIGINT UNSIGNED NOT NULL,
            `kode_laporan` VARCHAR(30) NOT NULL,
            `desa_id` INT UNSIGNED NULL DEFAULT NULL,
            `tanggal_kejadian` DATE NULL DEFAULT NULL,
            `data_json` JSON NOT NULL,
            `deskripsi` TEXT NULL DEFAULT NULL,
            `latitude` DECIMAL(10,7) NULL DEFAULT NULL,
            `longitude` DECIMAL(10,7) NULL DEFAULT NULL,
            `status` ENUM('draft','submitted','verified','rejected') NOT NULL DEFAULT 'draft',
            `catatan_verifikasi` TEXT NULL DEFAULT NULL,
            `verified_by` INT UNSIGNED NULL DEFAULT NULL,
            `verified_at` DATETIME NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_kode_laporan` (`kode_laporan`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_jenis_id` (`jenis_id`),
            KEY `idx_desa_id` (`desa_id`),
            KEY `idx_status` (`status`),
            KEY `idx_tanggal` (`tanggal_kejadian`),
            CONSTRAINT `fk_ll_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_ll_jenis` FOREIGN KEY (`jenis_id`) REFERENCES `master_jenis_laporan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_ll_desa` FOREIGN KEY (`desa_id`) REFERENCES `master_desa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_ll_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
        echo "[OK] Table laporan_lainnya created/exists.\n";
    }

    private function insertDefaultJenis() {
        $existing = $this->db->query("SELECT COUNT(*) as cnt FROM master_jenis_laporan")->fetch(PDO::FETCH_ASSOC);
        if ($existing['cnt'] > 0) {
            echo "[SKIP] master_jenis_laporan already has data.\n";
            return;
        }

        $jenis = [
            [
                'kode' => 'bibit_baru',
                'nama' => 'Penanaman Bibit Baru',
                'deskripsi' => 'Laporan penanaman bibit baru di lahan pertanian',
                'fields_json' => json_encode([
                    ['name' => 'nama_varietas', 'label' => 'Nama Varietas', 'type' => 'text', 'required' => true],
                    ['name' => 'jumlah_bibit', 'label' => 'Jumlah Bibit', 'type' => 'number', 'required' => true],
                    ['name' => 'sumber_bibit', 'label' => 'Sumber Bibit', 'type' => 'text', 'required' => false],
                ]),
            ],
            [
                'kode' => 'rumah_kaca',
                'nama' => 'Rumah Kaca',
                'deskripsi' => 'Laporan kondisi dan aktivitas rumah kaca',
                'fields_json' => json_encode([
                    ['name' => 'jumlah_unit', 'label' => 'Jumlah Unit', 'type' => 'number', 'required' => true],
                    ['name' => 'luas_m2', 'label' => 'Luas (m²)', 'type' => 'number', 'required' => false],
                    ['name' => 'komoditas', 'label' => 'Komoditas', 'type' => 'text', 'required' => false],
                ]),
            ],
            [
                'kode' => 'panen',
                'nama' => 'Panen',
                'deskripsi' => 'Laporan kegiatan panen pertanian',
                'fields_json' => json_encode([
                    ['name' => 'komoditas', 'label' => 'Komoditas', 'type' => 'text', 'required' => true],
                    ['name' => 'luas_ha', 'label' => 'Luas Panen (Ha)', 'type' => 'number', 'required' => false],
                    ['name' => 'estimasi_ton', 'label' => 'Estimasi Ton', 'type' => 'number', 'required' => false],
                ]),
            ],
            [
                'kode' => 'bantuan_alsintan',
                'nama' => 'Bantuan Alsintan',
                'deskripsi' => 'Laporan penerimaan bantuan alat dan mesin pertanian',
                'fields_json' => json_encode([
                    ['name' => 'nama_alat', 'label' => 'Nama Alat', 'type' => 'text', 'required' => true],
                    ['name' => 'jumlah', 'label' => 'Jumlah', 'type' => 'number', 'required' => false],
                    ['name' => 'sumber_bantuan', 'label' => 'Sumber Bantuan', 'type' => 'text', 'required' => false],
                ]),
            ],
            [
                'kode' => 'kerusakan_cuaca',
                'nama' => 'Kerusakan Cuaca',
                'deskripsi' => 'Laporan kerusakan akibat cuaca ekstrem',
                'fields_json' => json_encode([
                    ['name' => 'jenis_cuaca', 'label' => 'Jenis Cuaca', 'type' => 'text', 'required' => true],
                    ['name' => 'luas_terdampak_ha', 'label' => 'Luas Terdampak (Ha)', 'type' => 'number', 'required' => false],
                ]),
            ],
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO `master_jenis_laporan` (`kode`, `nama`, `deskripsi`, `fields_json`, `is_active`, `created_at`)
             VALUES (?, ?, ?, ?, 1, NOW())"
        );

        foreach ($jenis as $j) {
            $stmt->execute([$j['kode'], $j['nama'], $j['deskripsi'], $j['fields_json']]);
        }

        echo "[OK] " . count($jenis) . " default jenis laporan inserted.\n";
    }

    public function down() {
        $this->db->exec("DROP TABLE IF EXISTS `laporan_lainnya`");
        $this->db->exec("DROP TABLE IF EXISTS `master_jenis_laporan`");
        echo "[OK] Tables dropped.\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new CreateLaporanLainnya();
    $action = $argv[1] ?? 'up';
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}