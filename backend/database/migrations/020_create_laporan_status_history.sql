CREATE TABLE IF NOT EXISTS `laporan_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `laporan_id` BIGINT UNSIGNED NOT NULL,
    `old_status` VARCHAR(30) NULL,
    `new_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `komentar` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_laporan_status_history_laporan` (`laporan_id`, `created_at`),
    KEY `idx_laporan_status_history_changed_by` (`changed_by`),
    CONSTRAINT `fk_laporan_status_history_laporan`
        FOREIGN KEY (`laporan_id`) REFERENCES `laporan_hama` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_laporan_status_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
