<?php

declare(strict_types=1);

/**
 * Penguji koneksi database untuk lingkungan LOKAL maupun HOSTING.
 *
 * Pemakaian:
 *   php scripts/check_db_connection.php              # cek koneksi (SELECT 1)
 *   php scripts/check_db_connection.php --write-test # + uji tulis aman (tabel sementara)
 *
 * Kredensial dibaca dari environment / .env / .env.local — TIDAK pernah
 * hardcode. Output tidak pernah menampilkan password.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $envFile) {
    if (!is_file($envFile)) {
        continue;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'jagapadi_local';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$writeTest = in_array('--write-test', $argv ?? [], true);

echo "=== Penguji Koneksi Database JAGAPADI ===\n";
echo 'Host     : ' . $host . ':' . $port . "\n";
echo 'Database : ' . $name . "\n";
echo 'User     : ' . $user . "\n";
echo 'Charset  : ' . $charset . "\n";
echo 'Password : ' . ($pass !== '' ? '(diset, tidak ditampilkan)' : '(kosong)') . "\n";
echo str_repeat('-', 44) . "\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );

    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $serverCharset = $pdo->query('SELECT @@character_set_database, @@collation_database')->fetch(PDO::FETCH_ASSOC);
    $stmtTables = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
    $stmtTables->execute([$name]);
    $tables = (int) $stmtTables->fetchColumn();

    echo "STATUS   : ✅ KONEKSI BERHASIL\n";
    echo 'Server   : MySQL/MariaDB ' . $version . "\n";
    echo 'Collation: ' . $serverCharset['@@character_set_database'] . ' / ' . $serverCharset['@@collation_database'] . "\n";
    echo 'Jumlah tabel: ' . $tables . "\n";

    if ($writeTest) {
        $pdo->exec('CREATE TEMPORARY TABLE jp_conn_probe (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, note VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $insert = $pdo->prepare('INSERT INTO jp_conn_probe (note) VALUES (?)');
        $insert->execute(['probe-' . bin2hex(random_bytes(4))]);
        $read = $pdo->query('SELECT COUNT(*) FROM jp_conn_probe')->fetchColumn();
        if ((int) $read !== 1) {
            throw new RuntimeException('Uji tulis: pembacaan balik tidak konsisten');
        }
        echo "Uji tulis: ✅ INSERT/SELECT pada tabel sementara berhasil (otomatis dibersihkan)\n";
    }

    exit(0);
} catch (PDOException $e) {
    echo "STATUS   : ❌ KONEKSI GAGAL\n";
    echo 'Kode     : ' . $e->getCode() . "\n";
    echo 'Pesan    : ' . $e->getMessage() . "\n";
    echo "\nPeriksa: host/port, nama DB, user, password, dan allowlist IP (cPanel Remote MySQL).\n";
    exit(1);
}
