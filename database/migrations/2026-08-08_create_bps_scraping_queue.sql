-- Migration: Create BPS scraping queue table
-- Author: JAGAPADI System
-- 
-- This migration creates the bps_scraping_queue table for background
-- scraping job management.
--
-- Note: bps_scraping_logs is already created by DataPertanianBps::createTablesIfNotExist()
-- using a compatible schema (id, action, status, message, details, created_at).

CREATE TABLE IF NOT EXISTS `bps_scraping_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tahun` INT NOT NULL,
    `kabupaten` VARCHAR(100) NULL,
    `source` VARCHAR(50) NOT NULL DEFAULT 'simulasi',
    `skenario` VARCHAR(50) NOT NULL DEFAULT 'baseline',
    `force_refresh` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('pending','running','completed','failed') DEFAULT 'pending',
    `progress` INT DEFAULT 0,
    `result` JSON NULL,
    `error_message` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    INDEX idx_status_created (`status`, `created_at`),
    INDEX idx_tahun (`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
