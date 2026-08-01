<?php
/**
 * Migration: kecamatan_jember (DEPRECATED)
 *
 * Sejarah: Awalnya migration ini membuat tabel paralel `kecamatan_jember` dengan
 * kolom koordinat (latitude/longitude/kode_bps/kode_bmkg_adm4). Hal ini menciptakan
 * sumber kebenaran wilayah ganda bersama `master_kecamatan` (yang sudah digunakan
 * oleh backend produksi di `backend/`, mobile Flutter, dan seluruh endpoint API).
 *
 * Keputusan FASE 1 (satu sumber kebenaran):
 *   `master_kecamatan` adalah tabel master kecamatan yang KONSISTEN untuk seluruh
 *   kode (backend, mobile, seeder, relasi laporan). Migration ini dinonaktifkan
 *   agar tidak membuat tabel paralel `kecamatan_jember` yang tidak dipakai backend.
 *
 * Jika koordinat kecamatan (lat/long/kode_bmkg) suatu saat dibutuhkan, tambahkan
 * kolom tersebut ke `master_kecamatan` lewat migration baru — jangan buat tabel baru.
 *
 * Run: php migrations/2025_01_create_kecamatan_jember.php
 *
 * @version 2.0.0
 * @author JAGAPADI System
 */

// Autoload or include database connection
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/database.php';

class CreateKecamatanJember {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Run the migration (no-op)
     * Tidak membuat tabel paralel. master_kecamatan adalah sumber kebenaran.
     */
    public function up() {
        $count = (int) $this->db->query("SELECT COUNT(*) FROM master_kecamatan")->fetchColumn();
        echo "kecamatan_jember migration is deprecated.\n";
        echo "Sumber kebenaran kecamatan adalah `master_kecamatan` ({$count} row aktif).\n";
        echo "Tidak membuat tabel paralel `kecamatan_jember`.\n";
        return true;
    }

    /**
     * Rollback (no-op)
     */
    public function down() {
        echo "kecamatan_jember migration is deprecated; nothing to drop.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new CreateKecamatanJember();

    $action = $argv[1] ?? 'up';

    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
