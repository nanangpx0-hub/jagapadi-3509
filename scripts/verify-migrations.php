<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__) . '/backend');
require_once BASE_PATH . '/vendor/autoload.php';
use App\Core\Env;
use App\Core\Database;

$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) Env::load($envPath);

try { $pdo = Database::connect(); } catch (Throwable $e) { fwrite(STDERR, "DB unreachable: {$e->getMessage()}\n"); exit(1); }

$dir = BASE_PATH . '/database/migrations';
$files = array_map('basename', glob($dir . '/*.sql') ?: []);
sort($files);

$stmt = $pdo->query("SELECT migration FROM schema_migrations ORDER BY migration");
$dbMigs = $stmt->fetchAll(PDO::FETCH_COLUMN);
sort($dbMigs);

$fsSet = array_flip($files);
$dbSet = array_flip($dbMigs);

$pending = array_diff($files, $dbMigs);
$orphan = array_diff($dbMigs, $files);

echo "Filesystem: ".count($files)."  DB: ".count($dbMigs)."\n";
if ($pending) { echo "[PENDING] belum di DB:\n"; foreach ($pending as $f) echo "  - $f\n"; }
if ($orphan) { echo "[DRIFT] di DB tapi tidak di filesystem (migrasi manual?):\n"; foreach ($orphan as $f) echo "  - $f\n"; }
if (!$pending && !$orphan) { echo "[OK] filesystem == schema_migrations\n"; exit(0); }
exit(1);
