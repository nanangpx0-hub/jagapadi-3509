<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Logger;

$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

$env = Env::get('APP_ENV', 'production');

if ($env === 'production') {
    echo "[ERROR] Seed tidak boleh dijalankan di lingkungan production (APP_ENV=production)." . PHP_EOL;
    echo "        Setel APP_ENV=local atau APP_ENV=development di .env untuk menjalankan seed." . PHP_EOL;
    exit(1);
}

$timezone = Env::get('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($timezone);

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
Logger::init($logDir);

echo "=== JAGAPADI Database Seed ===" . PHP_EOL;
echo "  Environment: $env" . PHP_EOL;
echo PHP_EOL;

try {
    $pdo = Database::connect();
} catch (\Throwable $e) {
    echo "[ERROR] Database tidak dapat diakses: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$seedDir = BASE_PATH . '/database/seeds';
$seedFiles = glob($seedDir . '/*.sql');
sort($seedFiles);

$seedCount = 0;

foreach ($seedFiles as $file) {
    $filename = basename($file);

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        continue;
    }

    try {
        $pdo->exec($sql);
        echo "  [OK]   $filename" . PHP_EOL;
        $seedCount++;
    } catch (\PDOException $e) {
        echo "  [WARN] $filename: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL;
echo "--- Seed Users ---" . PHP_EOL;

$users = [
    [
        'username' => 'admin',
        'password' => 'ChangeMeAdmin!123',
        'email' => 'admin@jagapadi.local',
        'nama_lengkap' => 'Administrator JAGAPADI',
        'role' => 'admin',
    ],
    [
        'username' => 'petugas01',
        'password' => 'ChangeMePetugas!123',
        'email' => 'petugas01@jagapadi.local',
        'nama_lengkap' => 'Petugas Lapangan 01',
        'role' => 'petugas',
    ],
    [
        'username' => 'operator01',
        'password' => 'ChangeMeOperator!123',
        'email' => 'operator01@jagapadi.local',
        'nama_lengkap' => 'Operator Irigasi 01',
        'role' => 'operator',
    ],
    [
        'username' => 'statistisi01',
        'password' => 'ChangeMeStatistisi!123',
        'email' => 'statistisi01@jagapadi.local',
        'nama_lengkap' => 'Statistisi 01',
        'role' => 'statistisi',
    ],
];

foreach ($users as $user) {
    $stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
    $stmt->execute([$user['username']]);

    if ($stmt->fetch()) {
        echo "  [SKIP] {$user['username']} (already exists)" . PHP_EOL;
        continue;
    }

    $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $mustChange = in_array($user['role'], ['operator', 'statistisi']) ? 0 : 1;
    $insert = $pdo->prepare("
        INSERT INTO `users` (`username`, `password`, `email`, `nama_lengkap`, `role`, `aktif`, `must_change_password`)
        VALUES (?, ?, ?, ?, ?, 1, ?)
    ");
    $insert->execute([
        $user['username'],
        $hash,
        $user['email'],
        $user['nama_lengkap'],
        $user['role'],
        $mustChange,
    ]);

    echo "  [OK]   {$user['username']} created (role: {$user['role']})" . PHP_EOL;
}

echo PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "  SQL seed files executed: $seedCount" . PHP_EOL;
echo "  Users seeded: " . count($users) . " (admin, petugas01, operator01, statistisi01)" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM `master_opt`");
$totalOpt = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM `master_kabupaten`");
$totalKab = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM `master_kecamatan`");
$totalKec = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM `master_desa`");
$totalDesa = $stmt->fetchColumn();

echo "  Users:        $totalUsers" . PHP_EOL;
echo "  Master OPT:   $totalOpt" . PHP_EOL;
echo "  Kabupaten:    $totalKab" . PHP_EOL;
echo "  Kecamatan:    $totalKec" . PHP_EOL;
echo "  Desa:         $totalDesa" . PHP_EOL;
echo PHP_EOL;
echo "Seed selesai." . PHP_EOL;

exit(0);
