<?php

declare(strict_types=1);

/**
 * JAGAPADI — Password Reset Script (Maintenance)
 *
 * Mereset password SEMUA akun pengguna ke "Jember3509" untuk sementara,
 * mengaktifkan flag must_change_password, dan mencatat audit log untuk
 * setiap perubahan.
 *
 * Password "Jember3509" bersifat SEMENTARA. Setiap pengguna wajib mengganti
 * password setelah login pertama.
 *
 * Penggunaan:
 *   php scripts/reset_passwords.php           # reset semua user
 *   php scripts/reset_passwords.php --dry-run  # cek tanpa mengubah DB
 *   php scripts/reset_passwords.php --role=admin,petugas  # reset role tertentu
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Logger;
use App\Models\User;
use App\Models\ActivityLog;

$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

$env = Env::get('APP_ENV', 'production');

if ($env === 'production') {
    echo "[ERROR] Script ini tidak boleh dijalankan di lingkungan production (APP_ENV=production)." . PHP_EOL;
    echo "        Setel APP_ENV=local di .env untuk menjalankan script ini." . PHP_EOL;
    exit(1);
}

$timezone = Env::get('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($timezone);

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
Logger::init($logDir);

$tempPassword = 'Jember3509';
$isDryRun = in_array('--dry-run', $argv ?? [], true);

$roleFilter = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--role=')) {
        $roleFilter = explode(',', substr($arg, 7));
        $roleFilter = array_map('trim', $roleFilter);
    }
}

echo "=== JAGAPADI Password Reset Utility ===" . PHP_EOL;
echo "  Environment: $env" . PHP_EOL;
echo "  Mode: " . ($isDryRun ? 'DRY RUN (tidak mengubah DB)' : 'EXECUTE') . PHP_EOL;
if ($roleFilter !== null) {
    echo "  Role filter: " . implode(', ', $roleFilter) . PHP_EOL;
}
echo "  Temp password: $tempPassword" . PHP_EOL;
echo PHP_EOL;

try {
    $pdo = Database::connect();
} catch (\Throwable $e) {
    echo "[ERROR] Database tidak dapat diakses: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ---------------------------------------------------------------------------
// 1. Identifikasi semua role yang terdaftar
// ---------------------------------------------------------------------------
echo "--- Identifikasi Peran Pengguna ---" . PHP_EOL;

$roleStmt = $pdo->query("SELECT DISTINCT `role` FROM `users` ORDER BY `role`");
$allRoles = $roleStmt->fetchAll(\PDO::FETCH_COLUMN);

echo "  Peran yang ditemukan: " . implode(', ', $allRoles) . PHP_EOL;
echo "  Total peran: " . count($allRoles) . PHP_EOL . PHP_EOL;

// ---------------------------------------------------------------------------
// 2. Ambil semua pengguna (opsional filter per role)
// ---------------------------------------------------------------------------
echo "--- Daftar Pengguna ---" . PHP_EOL;

$sql = "SELECT * FROM `users` ORDER BY `role`, `id`";
if ($roleFilter !== null && count($roleFilter) > 0) {
    $placeholders = implode(',', array_fill(0, count($roleFilter), '?'));
    $sql = "SELECT * FROM `users` WHERE `role` IN ($placeholders) ORDER BY `role`, `id`";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($roleFilter);
} else {
    $stmt = $pdo->query($sql);
}

$users = $stmt->fetchAll();

if ($users === false || count($users) === 0) {
    echo "  [WARN] Tidak ada pengguna ditemukan." . PHP_EOL;
    exit(0);
}

printf("  %-4s %-20s %-15s %-10s %s\n", 'ID', 'Username', 'Role', 'Aktif', 'must_change');
printf("  %-4s %-20s %-15s %-10s %s\n", '----', '--------------------', '---------------', '----------', '------------');
foreach ($users as $u) {
    printf("  %-4d %-20s %-15s %-10s %s\n",
        (int) $u['id'],
        $u['username'],
        $u['role'],
        (int) $u['aktif'] ? 'Ya' : 'Tidak',
        (int) $u['must_change_password'] ? 'Ya' : 'Tidak'
    );
}
echo PHP_EOL;

// ---------------------------------------------------------------------------
// 3. Reset password untuk setiap pengguna
// ---------------------------------------------------------------------------
echo "--- Reset Password ---" . PHP_EOL;

$hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
echo "  Hash bcrypt (cost=12) yang digenerate: " . $hash . PHP_EOL;
echo PHP_EOL;

$successCount = 0;
$failCount = 0;
$skippedCount = 0;
$resetUsers = [];

foreach ($users as $user) {
    $userId = (int) $user['id'];
    $username = $user['username'];
    $role = $user['role'];

    try {
        if ($isDryRun) {
            echo "  [SKIP] $username (role: $role, id: $userId) — dry run" . PHP_EOL;
            $skippedCount++;
            continue;
        }

        $pdo->beginTransaction();

        // Update password dan flag must_change_password
        User::resetPassword($userId, $hash);

        // Catat audit log untuk setiap perubahan password
        ActivityLog::log(
            $userId,
            'password_reset',
            'users',
            $userId,
            "Password direset ke password sementara oleh script reset_passwords.php. "
            . "Pengguna wajib mengganti password setelah login berikutnya."
        );

        $pdo->commit();

        echo "  [OK]   $username (role: $role, id: $userId) — password direset" . PHP_EOL;
        $successCount++;
        $resetUsers[] = [
            'id' => $userId,
            'username' => $username,
            'role' => $role,
        ];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo "  [FAIL] $username (role: $role, id: $userId) — " . $e->getMessage() . PHP_EOL;
        Logger::error('Password reset failed for user: ' . $username, [
            'user_id' => $userId,
            'error' => $e->getMessage(),
        ]);
        $failCount++;
    }
}

// ---------------------------------------------------------------------------
// 4. Catat batch log
// ---------------------------------------------------------------------------
if (!$isDryRun && $successCount > 0) {
    ActivityLog::log(
        null,
        'password_reset_batch',
        'users',
        null,
        "Batch reset password " . $successCount . " pengguna ke password sementara. "
        . "Roles: " . implode(', ', $allRoles) . ". "
        . "Temporary password: Jember3509 (harus diganti setelah login pertama)."
    );

    Logger::info('Batch password reset completed', [
        'total_users' => count($users),
        'success' => $successCount,
        'failed' => $failCount,
        'roles' => $allRoles,
    ]);
}

// ---------------------------------------------------------------------------
// 5. Ringkasan
// ---------------------------------------------------------------------------
echo PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "  Total peran yang diproses: " . count($allRoles) . PHP_EOL;
echo "  Total pengguna: " . count($users) . PHP_EOL;
echo "  Berhasil direset: $successCount" . PHP_EOL;
if ($failCount > 0) {
    echo "  Gagal: $failCount" . PHP_EOL;
}
if ($skippedCount > 0) {
    echo "  Dilewati (dry-run): $skippedCount" . PHP_EOL;
}
echo PHP_EOL;

if (!$isDryRun && $successCount > 0) {
    echo "  Daftar pengguna yang di-reset:" . PHP_EOL;
    foreach ($resetUsers as $ru) {
        printf("  - ID:%d  %-20s  role:%s\n", $ru['id'], $ru['username'], $ru['role']);
    }
    echo PHP_EOL;
    echo "  Audit log tersedia di tabel: activity_audit_log / activity_log" . PHP_EOL;
    echo "  Action yang dicatat: password_reset (per user) & password_reset_batch (batch)" . PHP_EOL;
    echo PHP_EOL;
    echo "  INSTRUKSI KEAMANAN:" . PHP_EOL;
    echo "  - Password sementara: $tempPassword" . PHP_EOL;
    echo "  - Semua akun memiliki must_change_password=1" . PHP_EOL;
    echo "  - Web: otomatis redirect ke /password/change setelah login" . PHP_EOL;
    echo "  - API/Mobile: semua endpoint selain /auth/change-password diblokir hingga password diganti" . PHP_EOL;
    echo "  - Semua pengguna HARUS klik/masukkan password baru segera setelah login pertama" . PHP_EOL;
}

exit($failCount > 0 ? 1 : 0);
