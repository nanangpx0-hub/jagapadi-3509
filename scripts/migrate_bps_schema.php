<?php
/**
 * Migration Script for BPS Data Features
 * Adds new columns and tables for the updated BPS feature set
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

echo "=== Starting BPS Schema Migration ===\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. ALTER table data_pertanian_bps
    echo "1. Checking table data_pertanian_bps columns...\n";
    
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM data_pertanian_bps LIKE 'sumber_data_type'");
    if ($stmt->rowCount() == 0) {
        echo "   Adding column sumber_data_type...\n";
        $db->exec("ALTER TABLE data_pertanian_bps ADD COLUMN sumber_data_type ENUM('simulasi', 'resmi_webapi', 'manual') DEFAULT 'simulasi' AFTER sumber_data");
    } else {
        echo "   Column sumber_data_type already exists.\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM data_pertanian_bps LIKE 'tipe_skenario'");
    if ($stmt->rowCount() == 0) {
        echo "   Adding column tipe_skenario...\n";
        $db->exec("ALTER TABLE data_pertanian_bps ADD COLUMN tipe_skenario ENUM('baseline', 'optimis', 'pesimis') DEFAULT 'baseline' AFTER sumber_data_type");
    } else {
        echo "   Column tipe_skenario already exists.\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM data_pertanian_bps LIKE 'is_validated'");
    if ($stmt->rowCount() == 0) {
        echo "   Adding column is_validated...\n";
        $db->exec("ALTER TABLE data_pertanian_bps ADD COLUMN is_validated TINYINT(1) DEFAULT 0 AFTER tipe_skenario");
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM data_pertanian_bps LIKE 'validation_notes'");
    if ($stmt->rowCount() == 0) {
        echo "   Adding column validation_notes...\n";
        $db->exec("ALTER TABLE data_pertanian_bps ADD COLUMN validation_notes TEXT AFTER is_validated");
    }
    
    // 2. Create table bps_data_anomalies
    echo "2. Creating table bps_data_anomalies...\n";
    $sql = "CREATE TABLE IF NOT EXISTS bps_data_anomalies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_id INT NOT NULL,
        field_name VARCHAR(50) NOT NULL,
        value_actual DECIMAL(15,2),
        value_expected_min DECIMAL(15,2),
        value_expected_max DECIMAL(15,2),
        anomaly_type ENUM('out_of_range', 'suspicious', 'duplicate') DEFAULT 'out_of_range',
        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_data_id (data_id),
        INDEX idx_status (status),
        FOREIGN KEY (data_id) REFERENCES data_pertanian_bps(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo "   Table bps_data_anomalies checked/created.\n";
    
    // 3. Create table bps_yearly_summary
    echo "3. Creating table bps_yearly_summary...\n";
    $sql = "CREATE TABLE IF NOT EXISTS bps_yearly_summary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tahun INT NOT NULL UNIQUE,
        total_kabupaten INT DEFAULT 0,
        total_luas_panen DECIMAL(15,2) DEFAULT 0,
        total_produksi_gabah DECIMAL(15,2) DEFAULT 0,
        total_produksi_beras DECIMAL(15,2) DEFAULT 0,
        rata_produktivitas DECIMAL(10,2) DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tahun (tahun)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo "   Table bps_yearly_summary checked/created.\n";
    
    echo "=== Migration Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
