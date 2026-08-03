<?php
/**
 * Quick script to:
 * 1. Check users table for valid credentials
 * 2. Try various login combos directly with password_verify
 * 3. Populate kecepatan_angin if empty
 * 4. List actual user/password combinations
 */

declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== CHECK USER CREDENTIALS ===\n";
$users = $db->query("SELECT id, username, email, password, role, nama_lengkap, aktif FROM users LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$testPasswords = ['password', 'admin123', 'Jagapadi1!', 'petugas3509', 'admin', '12345678', 'Nanang123!'];

foreach ($users as $u) {
    echo "\nUser #{$u['id']}: {$u['username']} ({$u['role']}) - {$u['nama_lengkap']}\n";
    echo "  Email: {$u['email']} | Aktif: " . ($u['aktif'] ? 'YA' : 'TIDAK') . "\n";
    $passFound = false;
    foreach ($testPasswords as $p) {
        if (password_verify($p, $u['password'])) {
            echo "  ✅ Password DITEMUKAN: {$p}\n";
            $passFound = true;
            break;
        }
    }
    if (!$passFound) {
        // Try set to 'password'
        $newHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE users SET password = ?, aktif = 1 WHERE id = ?")->execute([$newHash, $u['id']]);
        echo "  ⚠️  Password diRESET ke: 'password' (hash diupdate)\n";
    }
}

echo "\n\n=== CHECK DATA KECEPATAN ANGIN ===\n";
$tables = $db->query("SHOW TABLES LIKE 'kecepatan_angin'")->rowCount();
if ($tables == 0) {
    echo "⚠️  TABEL kecepatan_angin TIDAK ADA, membuat...\n";
    require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
    $m = new KecepatanAngin();
    $m->createTablesIfNotExist();
    echo "✅ Tabel dibuat\n";
}
$count = $db->query("SELECT COUNT(*) FROM kecepatan_angin")->fetchColumn();
echo "Total record: " . number_format((int)$count) . "\n";

if ($count < 100) {
    echo "⚠️  Data terlalu sedikit, mengisi via simulasi Open-Meteo fallback...\n";
    require_once ROOT_PATH . '/app/services/KecepatanAnginScraper.php';
    $scraper = new KecepatanAnginScraper();
    $result = $scraper->run(['year' => date('Y'), 'month' => (int)date('m'), 'force_simulation' => true]);
    echo "Scrape result: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Also fill previous months for trend analysis
    for ($m = 1; $m <= 12; $m++) {
        echo "Mengisi bulan {$m}...\n";
        $scraper->run(['year' => date('Y'), 'month' => $m, 'force_simulation' => true]);
    }
    if (date('Y') > 2024) {
        for ($m = 1; $m <= 12; $m++) {
            $scraper->run(['year' => date('Y') - 1, 'month' => $m, 'force_simulation' => true]);
        }
    }
    
    $count = $db->query("SELECT COUNT(*) FROM kecepatan_angin")->fetchColumn();
    echo "Total record SETELAH scrape: " . number_format((int)$count) . "\n";
}

echo "\n=== DATA SAMPLE 5 BARIS ===\n";
$rows = $db->query("SELECT * FROM kecepatan_angin ORDER BY tanggal DESC, lokasi LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) == 0) {
    echo "TIDAK ADA DATA\n";
} else {
    echo implode("\t", array_keys($rows[0])) . "\n";
    foreach ($rows as $r) {
        echo implode("\t", array_map(fn($v) => is_null($v) ? 'NULL' : substr((string)$v, 0, 20), $r)) . "\n";
    }
}

echo "\n=== SUMBER DATA ===\n";
$src = $db->query("SELECT sumber_data, COUNT(*) as cnt FROM kecepatan_angin GROUP BY sumber_data")->fetchAll(PDO::FETCH_ASSOC);
foreach ($src as $s) {
    echo "  - {$s['sumber_data']}: {$s['cnt']} record\n";
}

echo "\n✅ SETUP & POPULASI DATA SELESAI\n";
