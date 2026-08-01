CREATE TABLE IF NOT EXISTS `jwt_blacklist` (
    `jti` VARCHAR(64) NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`jti`),
    KEY `idx_jwt_blacklist_expires` (`expires_at`),
    KEY `idx_jwt_blacklist_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
