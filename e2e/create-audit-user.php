<?php
// Buat user testing sementara untuk audit, lalu hapus setelah selesai.
$driver = getenv('DB_DRIVER') ?: 'mysql';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'jagapadi_local';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $dsn = "$driver:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $username = 'audit_admin';
    $password = 'AuditAdmin!123';
    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Hapus jika sudah ada
    $db->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);

    $stmt = $db->prepare("INSERT INTO users (username, password, role, nama_lengkap, email, aktif, must_change_password, created_at, updated_at) VALUES (?, ?, 'admin', 'Audit Admin', 'audit@local', 1, 0, NOW(), NOW())");
    $stmt->execute([$username, $hashed]);
    echo "OK user_created:$username\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}