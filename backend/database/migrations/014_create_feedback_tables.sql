CREATE TABLE IF NOT EXISTS `feedback` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `jenis_feedback` VARCHAR(50) NOT NULL COMMENT 'bug, fitur_baru, peningkatan',
    `judul` VARCHAR(255) NOT NULL,
    `deskripsi` TEXT,
    `prioritas` VARCHAR(20) NOT NULL DEFAULT 'medium' COMMENT 'low, medium, high, critical',
    `status` VARCHAR(20) NOT NULL DEFAULT 'diterima' COMMENT 'diterima, dalam_proses, selesai, ditolak',
    `attachment_url` VARCHAR(500) DEFAULT NULL,
    `admin_notes` TEXT,
    `processed_by` INT UNSIGNED DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `vote_count` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feedback_user_id` (`user_id`),
    KEY `idx_feedback_status` (`status`),
    KEY `idx_feedback_jenis` (`jenis_feedback`),
    KEY `idx_feedback_prioritas` (`prioritas`),
    KEY `idx_feedback_processed_by` (`processed_by`),
    KEY `idx_feedback_created_at` (`created_at`),
    CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_feedback_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feedback_votes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `feedback_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_feedback_user` (`feedback_id`, `user_id`),
    KEY `idx_feedback_votes_user_id` (`user_id`),
    CONSTRAINT `fk_feedback_votes_feedback` FOREIGN KEY (`feedback_id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_feedback_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feedback_status_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `feedback_id` INT UNSIGNED NOT NULL,
    `old_status` VARCHAR(20) DEFAULT NULL,
    `new_status` VARCHAR(20) NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feedback_history_feedback_id` (`feedback_id`),
    KEY `idx_feedback_history_changed_by` (`changed_by`),
    CONSTRAINT `fk_feedback_history_feedback` FOREIGN KEY (`feedback_id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_feedback_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
