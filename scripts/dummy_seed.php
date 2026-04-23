<?php
declare(strict_types=1);

/**
 * Jagapadi: Data Dummy Seeder
 *
 * Features:
 * - Generates realistic dummy data for users, laporan_hama, data_irigasi
 * - Batch inserts for high throughput
 * - Scenario-based data generation (users, reports, transactions, etc.)
 * - Validation to ensure referential integrity where possible
 * - Logging via existing Logger helper (storage/logs/dummy_seed.log)
 * - Cleanup mode to purge seeded data without touching production data
 * - Parameterized by --count, --db, --scenario, --clean, --log
 *
 * Safety:
 * - Default targets a dedicated seed database (jagapadi_seed). Override with --db.
 * - If tables do not exist, script will skip that portion gracefully.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Logger.php';

// Configure logger to write to dedicated seed log
Logger::setLogFile(ROOT_PATH . '/storage/logs/dummy_seed.log');

// CLI options
$opts = getopt('', ['count:', 'db:', 'scenario:', 'clean', 'log::']);

$count   = isset($opts['count']) ? intval($opts['count']) : 500;
$dbName  = $opts['db'] ?? 'jagapadi_seed';
$scenario= $opts['scenario'] ?? 'full';
$doClean = isset($opts['clean']);
$logPath = $opts['log'] ?? null;

if ($logPath) {
    Logger::setLogFile($logPath);
}

Logger::info('Starting dummy seed', ['count' => $count, 'db' => $dbName, 'scenario' => $scenario]);

/**
 * Simple DB connection helper to target a specific DB.
 */
function createPdo(string $dbName): ?PDO {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn  = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        file_put_contents(
            ROOT_PATH . '/storage/logs/dummy_seed.err',
            $e->getMessage() . PHP_EOL,
            FILE_APPEND
        );
        return null;
    }
}

$pdo = createPdo($dbName);
if (!$pdo) {
    exit("DB connection failed for $dbName.\n");
}

// Ensure seed tables exist in target DB (best effort for isolated seeds)
function ensureSeedTables(PDO $pdo): void {
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (".
            "id INT AUTO_INCREMENT PRIMARY KEY, ".
            "username VARCHAR(64) NOT NULL UNIQUE, ".
            "password VARCHAR(255) NOT NULL, ".
            "email VARCHAR(128) NOT NULL, ".
            "nama_lengkap VARCHAR(128) NOT NULL, ".
            "role VARCHAR(32) NOT NULL, ".
            "aktif TINYINT(1) DEFAULT 1, ".
            "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP".
        ")",
        "CREATE TABLE IF NOT EXISTS master_opt (id INT AUTO_INCREMENT PRIMARY KEY)",
        "CREATE TABLE IF NOT EXISTS master_desa (id INT AUTO_INCREMENT PRIMARY KEY, nama_desa VARCHAR(100))",
        "CREATE TABLE IF NOT EXISTS laporan_hama (".
            "id INT AUTO_INCREMENT PRIMARY KEY, ".
            "user_id INT, ".
            "master_opt_id INT, ".
            "lokasi VARCHAR(255), ".
            "tanggal DATE, ".
            "jenis_hama VARCHAR(100), ".
            "tingkat_keparahan VARCHAR(20), ".
            "luas_serangan DECIMAL(10,2), ".
            "jenis_tanggulangan VARCHAR(50), ".
            "hasil_tanggulangan TEXT, ".
            "status VARCHAR(50), ".
            "catatan TEXT, ".
            "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP".
        ")",
        "CREATE TABLE IF NOT EXISTS data_irigasi (".
            "id INT AUTO_INCREMENT PRIMARY KEY, ".
            "user_id INT, ".
            "desa_id INT, ".
            "tanggal DATE, ".
            "status_kondisi VARCHAR(50), ".
            "debit_air DECIMAL(10,2), ".
            "luas_lahan DECIMAL(10,2), ".
            "catatan TEXT, ".
            "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP".
        ")"
    ];
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Ignore errors while ensuring tables; seed may still proceed
        }
    }
}

// Call to ensure seed tables exist before proceeding
ensureSeedTables($pdo);

// Helpers
function randFrom(array $arr) {
    if (empty($arr)) return null;
    return $arr[array_rand($arr)];
}

function randDateInLastDays(int $days): string {
    $offset = rand(0, $days);
    return date('Y-m-d', strtotime("-$offset days"));
}

function batchInsert(PDO $pdo, string $table, array $cols, array $rows): void {
    if (empty($rows)) return;
    $placeStr = '(' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
    $placeholders = implode(',', array_fill(0, count($rows), $placeStr));
    $sql = "INSERT INTO $table(" . implode(',', $cols) . ") VALUES " . $placeholders;
    $params = [];
    foreach ($rows as $r) {
        foreach ($cols as $idx => $c) {
            // Support both associative arrays (with column name as key)
            // and numeric-indexed rows (order matches $cols)
            if (is_array($r) && array_key_exists($c, $r)) {
                $params[] = $r[$c] ?? null;
            } elseif (is_array($r) && array_key_exists($idx, $r)) {
                $params[] = $r[$idx] ?? null;
            } else {
                $params[] = null;
            }
        }
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

// Helper: guard table existence
function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch();
}

// 1) Seed Users
$seedUsers = [];
$currentSeedUsers = [];
if (tableExists($pdo, 'users')) {
    $currentSeedUsers = $pdo->query("SELECT username FROM users WHERE username LIKE 'seed_user_%'")->fetchAll(PDO::FETCH_COLUMN);
}
$needed = max(0, $count - count($currentSeedUsers));
if ($needed > 0 && tableExists($pdo, 'users')) {
    $roles = ['admin','petugas','operator','statistisi'];
    $passwordHash = password_hash('SeedPass123!', PASSWORD_BCRYPT);
    for ($i = 0; $i < $needed; $i++) {
        $idx = count($currentSeedUsers) + $i;
        $username = 'seed_user_' . bin2hex(random_bytes(3));
        $email = 'seed+' . $idx . '@example.test';
        $name = 'Seed User ' . ($idx + 1);
        $role = randFrom($roles);
        $seedUsers[] = [$username, $passwordHash, $email, $name, $role, 1];
        $currentSeedUsers[] = $username;
    }
    batchInsert($pdo, 'users', ['username','password','email','nama_lengkap','role','aktif'], $seedUsers);
    Logger::info('Inserted seed users', ['count' => count($seedUsers)]);
}
// Retrieve IDs for seed users (best-effort)
$seedUserRows = [];
$seedUserIds = [];
if (tableExists($pdo, 'users')) {
    $seedUserRows = $pdo->query("SELECT id, username FROM users WHERE username LIKE 'seed_user_%' ORDER BY id ASC")->fetchAll();
    $seedUserIds = array_map(function($r){ return (int)$r['id']; }, $seedUserRows);
}

// 2) Seed laporan_hama (if master_opt and desa exist)
$reportsCount = (int)($count * 0.6); // 60% of count as reports
if (!empty($seedUserIds) && $reportsCount > 0) {
    $masterOptsExist = tableExists($pdo, 'master_opt');
    $desaExist = tableExists($pdo, 'master_desa');
    $masterOpts = $masterOptsExist ? $pdo->query("SELECT id FROM master_opt ORDER BY RAND() LIMIT 8")->fetchAll(PDO::FETCH_COLUMN) : [];
    $desaNames  = $desaExist ? $pdo->query("SELECT id, nama_desa FROM master_desa ORDER BY RAND() LIMIT 40")->fetchAll(PDO::FETCH_ASSOC) : [];
    $desaIds = $desaNames ? array_column($desaNames, 'id') : [];
    $desaNamesOnly = $desaNames ? array_column($desaNames, 'nama_desa') : [];
    $hama = ['Ulat Bulu','Hama Wereng','Lalat Buah','Penggerek','Uret'];
    $tingkat = ['Ringan','Sedang','Berat'];
    $tanggulangan = ['Kimia','Biologi','Fisik','Integrasi'];
    $rows = [];
    $now = date('Y-m-d');
    for ($i=0; $i<$reportsCount; $i++) {
        $user_id = randFrom($seedUserIds);
        $master_opt_id = randFrom($masterOpts) ?? null;
        $idx = $i;
        $lokasi = randFrom($desaNamesOnly) ?? ('Seed_' . $idx);
        $tanggal = randDateInLastDays(365);
        $jenis_hama = randFrom($hama);
        $keparahan = randFrom($tingkat) ?? 'Ringan';
        $luas_serangan = number_format((float)rand(1, 250) / 10, 2, '.', '');
        $jenis_tanggulangan = randFrom($tanggulangan) ?? 'Kimia';
        $hasil_tanggulangan = 'Seeded data generated';
        $status = rand(0,9) > 4 ? 'Diverifikasi' : 'Submitted';
        $catatan = 'Seed data batch';
        $rows[] = [
            'user_id' => $user_id,
            'master_opt_id' => $master_opt_id,
            'lokasi' => $lokasi,
            'tanggal' => $tanggal,
            'jenis_hama' => $jenis_hama,
            'tingkat_keparahan' => $keparahan,
            'luas_serangan' => $luas_serangan,
            'jenis_tanggulangan' => $jenis_tanggulangan,
            'hasil_tanggulangan' => $hasil_tanggulangan,
            'status' => $status,
            'catatan' => $catatan,
        ];
    }
    // Batch insert using a single multi-row statement
    if (!empty($rows)) {
        // Normalize keys order
        $cols = ['user_id','master_opt_id','lokasi','tanggal','jenis_hama','tingkat_keparahan','luas_serangan','jenis_tanggulangan','hasil_tanggulangan','status','catatan'];
        batchInsert($pdo, 'laporan_hama', $cols, $rows);
        Logger::info('Inserted dummy laporan_hama records', ['count' => count($rows)]);
    }
}

// 3) Seed data_irigasi (minimal viable data to cover API/tests)
$irrigasiCount = (int)($count * 0.3);
if (!empty($seedUserIds) && $irrigasiCount > 0) {
    $desaIds2 = $desaIds ?: [];
    $desaNames2 = $desaNamesOnly ?: ['Seeded Desa'];
    $statuses = ['Aktif','Rusak','Tidak Aktif'];
    $rowsI = [];
    for ($i=0; $i<$irrigasiCount; $i++) {
        $user_id = randFrom($seedUserIds);
        $desa_id = randFrom($desaIds2) ?? null;
        $tanggal = randDateInLastDays(365);
        $status_kondisi = randFrom($statuses) ?? 'Aktif';
        $debit_air = (float)rand(10, 1000) / 10.0;
        $luas_lahan = (float)rand(5, 500) / 1.0;
        $catatan = 'Seed data - irigasi';
        // Only insert if desa_id exists to maintain FK safety
        if ($desa_id !== null) {
            $rowsI[] = [$user_id, $desa_id, $tanggal, $status_kondisi, $debit_air, $luas_lahan, $catatan];
        }
    }
    if (!empty($rowsI)) {
        $colsI = ['user_id','desa_id','tanggal','status_kondisi','debit_air','luas_lahan','catatan'];
        batchInsert($pdo, 'data_irigasi', $colsI, $rowsI);
        Logger::info('Inserted dummy data_irigasi records', ['count' => count($rowsI)]);
    }
}

// 4) Validation (sanity check counts)
$sanity = [];
try {
    $sanity['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username LIKE 'seed_user_%'")->fetchColumn();
    $sanity['reports'] = (int)$pdo->query("SELECT COUNT(*) FROM laporan_hama WHERE lokasi LIKE 'Seed%' ")->fetchColumn();
    $sanity['irrig'] = (int)$pdo->query("SELECT COUNT(*) FROM data_irigasi WHERE catatan LIKE 'Seed data%'")->fetchColumn();
} catch (Exception $e) {
    Logger::warning('Sanity check failed', ['error' => $e->getMessage()]);
}
Logger::info('Sanity after seeding', $sanity);

// 5) Cleanup helper
if ($doClean) {
    cleanupDummyData($pdo);
    Logger::info('Cleanup completed via CLI');
}

exit(0);

/**
 * Cleanup dummy seed data from the database.
 */
function cleanupDummyData(PDO $pdo): void {
    // Remove from related tables first to avoid FK constraints issues
    $seedUserPattern = 'seed_user_%';
    // cleanup from dependent tables
    foreach (["activity_log","laporan_hama","data_irigasi"] as $tbl) {
        try {
            $pdo->exec("DELETE FROM $tbl WHERE user_id IN (SELECT id FROM users WHERE username LIKE '$seedUserPattern')");
        } catch (Exception $e) {
            // ignore if not applicable
        }
    }
    // delete seed users
    try {
        $pdo->exec("DELETE FROM users WHERE username LIKE '$seedUserPattern'");
    } catch (Exception $e) {
        // ignore
    }
    Logger::info('Dummy seed data cleanup executed');
}
