CREATE TABLE `device_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(512) NOT NULL,
    `platform` ENUM('android','ios','web') NOT NULL DEFAULT 'android',
    `user_agent` VARCHAR(500) NULL,
    `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_device_token` (`token`),
    INDEX `idx_device_tokens_user` (`user_id`),
    CONSTRAINT `fk_device_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
