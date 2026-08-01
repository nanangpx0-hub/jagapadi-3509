ALTER TABLE `users`
MODIFY COLUMN `role` ENUM('admin','petugas','operator','statistisi','viewer')
NOT NULL DEFAULT 'petugas';
