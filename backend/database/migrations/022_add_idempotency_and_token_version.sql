-- Migration 022: idempotency keys + users.token_version
--
-- 1) `users.token_version` untuk revokasi seluruh JWT saat perubahan password.
--    Nilai 0 = belum pernah ganti password (backward compatible dengan token lama).
ALTER TABLE `users`
    ADD COLUMN `token_version` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `must_change_password`;

-- 2) Tabel idempotensi untuk mencegah duplikasi pada retry/offline sync mobile.
--    Unique (user_id, idempotency_key, method, path) menjamin satu Key hanya
--    dipakai sekali per (user, operasi). `expires_at` = TTL pembersihan.
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `idempotency_key` VARCHAR(128) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `request_hash` CHAR(64) NOT NULL,
    `status` ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
    `response_status` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `response_body` MEDIUMTEXT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_idem_user_key` (`user_id`, `idempotency_key`, `method`, `path`),
    KEY `idx_idem_expires` (`expires_at`),
    CONSTRAINT `fk_idem_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;