<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

function authSchemaTableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

function authSchemaColumnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $stmt->execute([$table, $column]);

    return (int)$stmt->fetchColumn() > 0;
}

if (!authSchemaTableExists($db, 'users')) {
    fwrite(STDERR, "Table users tidak ditemukan di database " . DB_NAME . ". Pastikan DB_NAME mengarah ke skema JAGAPADI lengkap.\n");
    exit(1);
}

$changes = [];

if (!authSchemaColumnExists($db, 'users', 'must_change_password')) {
    $db->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NULL DEFAULT 0 AFTER aktif");
    $changes[] = 'added users.must_change_password';
}

if (!authSchemaColumnExists($db, 'users', 'last_password_change_at')) {
    $db->exec("ALTER TABLE users ADD COLUMN last_password_change_at DATETIME NULL DEFAULT NULL AFTER updated_at");
    $changes[] = 'added users.last_password_change_at';
}

$db->exec("UPDATE users SET must_change_password = 0 WHERE must_change_password IS NULL");
$db->exec("
    UPDATE users
    SET last_password_change_at = COALESCE(last_password_change_at, updated_at, created_at, NOW())
    WHERE last_password_change_at IS NULL
");

$summary = $changes ? implode(', ', $changes) : 'no schema changes needed';
echo "Users auth schema updated on " . DB_NAME . ": " . $summary . PHP_EOL;
