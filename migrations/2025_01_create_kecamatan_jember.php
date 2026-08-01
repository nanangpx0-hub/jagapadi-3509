<?php
/**
 * Migration: Create kecamatan_jember table
 * 
 * This migration creates the reference table for all 31 kecamatan in Kabupaten Jember
 * with coordinates for Open-Meteo API integration.
 * 
 * Run: php migrations/2025_01_create_kecamatan_jember.php
 * 
 * @version 1.0.0
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
     * Run the migration
     */
    public function up() {
        echo "Creating kecamatan_jember table...\n";
        
        // Create table
        $sql = "CREATE TABLE IF NOT EXISTS `kecamatan_jember` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nama_kecamatan` VARCHAR(100) NOT NULL,
            `latitude` DECIMAL(9,6) NOT NULL,
            `longitude` DECIMAL(9,6) NOT NULL,
            `kode_bps` VARCHAR(10) NULL,
            `kode_bmkg_adm4` VARCHAR(20) NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_nama` (`nama_kecamatan`),
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "✓ Table created successfully\n";
        
        // Insert all 31 kecamatan data
        echo "Inserting kecamatan data...\n";
        
        $kecamatan = [
            // Data koordinat dari berbagai sumber resmi
            ['Ajung', -8.2180, 113.6420, '3509010', '35.09.11'],
            ['Ambulu', -8.3450, 113.6100, '3509020', '35.09.05'],
            ['Arjasa', -8.1200, 113.8300, '3509030', '35.09.08'],
            ['Balung', -8.2600, 113.5500, '3509040', '35.09.04'],
            ['Bangsalsari', -8.1800, 113.5800, '3509050', '35.09.12'],
            ['Gumukmas', -8.3100, 113.4700, '3509060', '35.09.02'],
            ['Jelbuk', -8.0500, 113.8000, '3509070', '35.09.09'],
            ['Jenggawah', -8.2800, 113.6400, '3509080', '35.09.13'],
            ['Jombang', -8.2200, 113.5100, '3509090', '35.09.03'],
            ['Kalisat', -8.1500, 113.8100, '3509100', '35.09.17'],
            ['Kaliwates', -8.1617, 113.7214, '3509110', '35.09.29'],
            ['Kencong', -8.2900, 113.3800, '3509120', '35.09.01'],
            ['Ledokombo', -8.0800, 113.8500, '3509130', '35.09.10'],
            ['Mayang', -8.1300, 113.7600, '3509140', '35.09.16'],
            ['Mumbulsari', -8.2400, 113.6800, '3509150', '35.09.14'],
            ['Pakusari', -8.1100, 113.7800, '3509160', '35.09.18'],
            ['Panti', -8.0900, 113.7000, '3509170', '35.09.20'],
            ['Patrang', -8.1392, 113.7169, '3509180', '35.09.31'],
            ['Puger', -8.3700, 113.4800, '3509190', '35.09.06'],
            ['Rambipuji', -8.2100, 113.6200, '3509200', '35.09.15'],
            ['Semboro', -8.2300, 113.4500, '3509210', '35.09.07'],
            ['Silo', -8.2000, 113.9000, '3509220', '35.09.24'],
            ['Sukorambi', -8.1000, 113.6800, '3509230', '35.09.19'],
            ['Sukowono', -8.1200, 113.8600, '3509240', '35.09.23'],
            ['Sumberbaru', -8.2700, 113.4000, '3509250', '35.09.26'],
            ['Sumberjambe', -8.1500, 113.9200, '3509260', '35.09.25'],
            ['Sumbersari', -8.1725, 113.7161, '3509270', '35.09.30'],
            ['Tanggul', -8.1700, 113.5000, '3509280', '35.09.21'],
            ['Tempurejo', -8.3000, 113.7500, '3509290', '35.09.27'],
            ['Umbulsari', -8.2500, 113.4200, '3509300', '35.09.28'],
            ['Wuluhan', -8.3200, 113.5300, '3509310', '35.09.22'],
        ];
        
        $stmt = $this->db->prepare(
            "INSERT INTO kecamatan_jember (nama_kecamatan, latitude, longitude, kode_bps, kode_bmkg_adm4) 
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             latitude = VALUES(latitude), 
             longitude = VALUES(longitude),
             kode_bps = VALUES(kode_bps),
             kode_bmkg_adm4 = VALUES(kode_bmkg_adm4)"
        );
        
        $count = 0;
        foreach ($kecamatan as $kec) {
            $stmt->execute($kec);
            $count++;
        }
        
        echo "✓ Inserted {$count} kecamatan\n";
        echo "Migration completed successfully!\n";
        
        return true;
    }
    
    /**
     * Rollback the migration
     */
    public function down() {
        echo "Rolling back: Dropping kecamatan_jember table...\n";
        $this->db->exec("DROP TABLE IF EXISTS kecamatan_jember");
        echo "✓ Table dropped\n";
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
