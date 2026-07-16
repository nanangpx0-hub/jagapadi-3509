<?php
require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $username = 'admin';
    $password = 'password';
    $email = 'admin@example.com';
    $nama = 'Administrator';
    $role = 'admin';
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        $stmt = $db->prepare("UPDATE users SET password = ?, role = ?, aktif = 1 WHERE id = ?");
        $stmt->execute([$hashedPassword, $role, $user['id']]);
        echo "User 'admin' updated with password 'password'.\n";
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password, email, nama_lengkap, role, aktif, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$username, $hashedPassword, $email, $nama, $role]);
        echo "User 'admin' created with password 'password'.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
