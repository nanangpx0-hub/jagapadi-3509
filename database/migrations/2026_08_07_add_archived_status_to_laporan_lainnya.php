<?php

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/app/core/Database.php';

foreach ([$rootPath . '/.env', $rootPath . '/.env.local'] as $envPath) {
    if (!file_exists($envPath)) continue;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            $eqPos = strpos($line, '=');
            if ($eqPos === false) continue;
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if (empty($key)) continue;
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$rollback = in_array('--rollback', $argv ?? [], true);
$db = Database::getInstance()->getConnection();

$stmt = $db->query("SHOW COLUMNS FROM laporan_lainnya LIKE 'status'");
$column = $stmt->fetch(PDO::FETCH_ASSOC);
$currentType = strtolower($column['Type'] ?? '');

if ($rollback) {
    if (strpos($currentType, 'archived') === false) {
        echo "Status archived belum ada. Tidak ada perubahan.\n";
        exit(0);
    }

    $db->exec("UPDATE laporan_lainnya SET status = 'submitted' WHERE status = 'archived'");
    $db->exec("ALTER TABLE laporan_lainnya MODIFY status ENUM('draft','submitted','verified','rejected') DEFAULT 'draft'");
    echo "Rollback selesai. Status archived dikembalikan menjadi submitted.\n";
    exit(0);
}

if (strpos($currentType, 'archived') !== false) {
    echo "Status archived sudah tersedia. Tidak ada perubahan.\n";
    exit(0);
}

$db->exec("ALTER TABLE laporan_lainnya MODIFY status ENUM('draft','submitted','verified','rejected','archived') DEFAULT 'draft'");
echo "[OK] Migration selesai. Status archived (Diarsipkan) tersedia untuk laporan_lainnya.\n";
