<?php

declare(strict_types=1);

// CLI-only fixture. Credentials travel over stdin/stdout, never command arguments.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2) . '/backend/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(dirname(__DIR__, 2) . '/backend/.env');
if (!in_array(Env::get('APP_ENV'), ['local', 'testing'], true)) {
    fwrite(STDERR, "Fixture requires APP_ENV=local or testing.\n");
    exit(1);
}

// Fixture menulis dan menghapus baris; tolak database non-loopback agar tidak
// pernah menyentuh data staging/production meski .env salah arah.
$dbHost = (string) Env::get('DB_HOST', '127.0.0.1');
if (!in_array($dbHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Fixture refuses non-loopback DB_HOST: $dbHost\n");
    exit(1);
}

$db = null;
try {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $run = $input['run'] ?? '';
    if (!is_string($run) || !preg_match('/^[a-z0-9_]{1,24}$/D', $run)) {
        throw new RuntimeException('Invalid fixture identifier');
    }
    $db = Database::connect();
    $db->beginTransaction();
    $users = [];
    foreach (['petugas', 'petugas', 'admin'] as $index => $role) {
        $username = 'ns_' . $run . '_' . $index;
        if (($input['action'] ?? '') === 'cleanup') {
            $select = $db->prepare('SELECT id FROM users WHERE username = ? AND email = ?');
            $select->execute([$username, $username . '@example.invalid']);
            $id = $select->fetchColumn();
            if ($id !== false) {
                $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM activity_log WHERE user_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM users WHERE id = ? AND username = ?')->execute([$id, $username]);
            }
            continue;
        }
        if (($input['action'] ?? '') !== 'create') {
            throw new RuntimeException('Invalid action');
        }
        $password = bin2hex(random_bytes(24));
        $db->prepare(
            'INSERT INTO users (username, password, role, nama_lengkap, email, aktif, must_change_password)
             VALUES (?, ?, ?, ?, ?, 1, 0)'
        )->execute([$username, password_hash($password, PASSWORD_BCRYPT), $role,
            'Notification smoke fixture', $username . '@example.invalid']);
        $id = (int) $db->lastInsertId();
        $notifications = [];
        foreach ([1, 2] as $number) {
            $db->prepare(
                'INSERT INTO notifications (user_id, type, title, body, data_json) VALUES (?, ?, ?, ?, ?)'
            )->execute([$id, 'laporan_verified', 'Smoke notification ' . $number,
                'Isolated test data', json_encode(['entity' => 'hama', 'laporan_id' => $number], JSON_THROW_ON_ERROR)]);
            $notifications[] = (int) $db->lastInsertId();
        }
        $users[] = compact('id', 'username', 'password', 'role', 'notifications');
    }
    $db->commit();
    echo json_encode(['users' => $users, 'success' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Notification fixture failed: " . $error->getMessage() . " (code=" . $error->getCode() . ")\n");
    exit(1);
}

