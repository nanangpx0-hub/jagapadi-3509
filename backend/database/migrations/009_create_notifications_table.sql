CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'laporan_submitted, laporan_verified, laporan_rejected, laporan_resubmitted, laporan_archived',
    `title` VARCHAR(200) NOT NULL,
    `body` VARCHAR(500) NOT NULL,
    `data_json` TEXT NULL COMMENT 'JSON payload: entity, laporan_id, nomor_laporan, status, web_path, api_path',
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_created` (`user_id`, `created_at`),
    INDEX `idx_user_unread` (`user_id`, `read_at`),
    INDEX `idx_type` (`type`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
