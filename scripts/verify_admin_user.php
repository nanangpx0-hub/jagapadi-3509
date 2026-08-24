<?php
// Quick verification script to check the newly created user
require_once __DIR__ . '/lib/db_bootstrap.php';

$db = Database::getInstance()->getConnection();

$username = $argv[1] ?? jp_env_optional('ADMIN_USERNAME', '');
if ($username === '') {
    fwrite(STDERR, "Pemakaian: php verify_admin_user.php <username>\n");
    exit(1);
}

// Get the user details
$stmt = $db->prepare("SELECT id, username, email, role, nama_lengkap, phone, aktif, password, created_at, updated_at FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✅ User found in database!\n\n";
    echo "User Details:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Username: {$user['username']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Role: {$user['role']}\n";
    echo "  Nama Lengkap: {$user['nama_lengkap']}\n";
    echo "  Phone: {$user['phone']}\n";
    echo "  Aktif: {$user['aktif']}\n";
    echo "  Created At: {$user['created_at']}\n";
    echo "  Updated At: {$user['updated_at']}\n\n";
    
    echo "Password Hash:\n";
    echo "  Hash: " . substr($user['password'], 0, 7) . '...' . substr($user['password'], -4) . "\n";
    echo "  Length: " . strlen($user['password']) . " characters\n";
    echo "  Format: " . (str_starts_with($user['password'], '$2y$12$') ? "✅ bcrypt with cost 12" : "❌ NOT bcrypt cost 12") . "\n\n";
    
    // Test password verification — hanya bila ADMIN_PASSWORD disediakan di environment.
    $testPassword = jp_env_optional('ADMIN_PASSWORD', '');
    if ($testPassword === '') {
        echo "Password Verification Test:\n";
        echo "  Dilewati (set ADMIN_PASSWORD pada environment bila ingin menguji).\n\n";
    } else {
        $verified = password_verify($testPassword, $user['password']);
        echo "Password Verification Test:\n";
        echo "  Test Password: " . str_repeat('*', strlen($testPassword)) . "\n";
        echo "  Result: " . ($verified ? "✅ Password verified successfully!" : "❌ Password verification FAILED!") . "\n\n";
    }
    
    // Check activity log
    $stmt = $db->query("SHOW TABLES LIKE 'activity_log'");
    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare("SELECT * FROM activity_log WHERE user_id = ? AND action = 'USER_CREATED' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $activityLog = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activityLog) {
            echo "Activity Log Entry:\n";
            echo "  Action: {$activityLog['action']}\n";
            echo "  Description: {$activityLog['description']}\n";
            echo "  IP Address: {$activityLog['ip_address']}\n";
            echo "  Created At: {$activityLog['created_at']}\n";
        } else {
            echo "⚠️  No activity log entry found\n";
        }
    }
} else {
    echo "❌ User NOT found in database!\n";
}
