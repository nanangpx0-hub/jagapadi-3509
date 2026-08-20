-- Migration: Create generic scraping job queue table
-- Tanggal: 2026-08-09
-- Tabel ini dipakai oleh scripts/scraper_worker.php untuk menjalankan
-- scraper (curah_hujan, angin, harga, bps) di background thread CLI.

CREATE TABLE IF NOT EXISTS `scraping_job_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_type` VARCHAR(50) NOT NULL,
    `parameters` JSON NOT NULL,
    `status` ENUM('pending','running','completed','failed') DEFAULT 'pending',
    `progress` INT DEFAULT 0,
    `result` JSON NULL,
    `error_message` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    INDEX idx_status (`status`),
    INDEX idx_type_status (`job_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
