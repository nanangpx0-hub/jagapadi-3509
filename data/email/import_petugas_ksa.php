<?php
/**
 * CLI: Import / sinkronkan data petugas KSA dari Excel ke tabel `users` (role petugas).
 *
 * Sumber : data/email/data petugas ksa.xlsx  (kolom: No, Nama Petugas, Email, KSA)
 * KSA    : nilai dinormalisasi ke "KSA Padi" / "KSA Jagung" (case-insensitive).
 *
 * Pakai:
 *   php data/email/import_petugas_ksa.php                 # dry-run (tampil saja, tidak tulis DB)
 *   php data/email/import_petugas_ksa.php --apply         # upsert idempotent ke DB
 *   php data/email/import_petugas_ksa.php --apply --reset-passwords   # paksa reset password temp
 *
 * Aturan keamanan (idempoten & non-destructive):
 *  - Upsert berdasarkan email (UNIQUE). Baris yang sudah ada TIDAK pernah menimpa password
 *    kecuali--reset-passwords diberikan.
 *  - Baru disisipkan: role=petugas, aktif=1, must_change_password=1, password=temp hash.
 *  - Baru saja ada: hanya update nama_lengkap, ksa, role, aktif. Password eksis dihormati.
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die('Script ini hanya dapat dijalankan dari command line.');
}

define('ROOT_PATH', dirname(__DIR__, 2));
define('CLI_MODE', true);

foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $envPath) {
    if (!file_exists($envPath)) {
        continue;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        putenv(trim(substr($line, 0, $eq)) . '=' . trim(substr($line, $eq + 1)));
    }
}
require_once ROOT_PATH . '/app/core/Database.php';

$apply = in_array('--apply', $argv, true);
$resetPw = in_array('--reset-passwords', $argv, true);
$tempPw = getenv('PETUGAS_TEMP_PASSWORD') ?: 'Ganti#123!';
$xlsxPath = ROOT_PATH . '/data/email/data petugas ksa.xlsx';

if (!file_exists($xlsxPath)) {
    fwrite(STDERR, "File sumber tidak ditemukan: $xlsxPath\n");
    exit(1);
}

/* -------------------------------------------------
 * 1. Parse XLSX (native: ZipArchive + DOM, tanpa library eksternal)
 * ------------------------------------------------- */
$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
    fwrite(STDERR, "Tidak dapat membuka $xlsxPath\n");
    exit(1);
}

$shared = [];
$ssRaw = $zip->getFromName('xl/sharedStrings.xml');
if ($ssRaw !== false) {
    $s = simplexml_load_string($ssRaw);
    $s->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    foreach ($s->xpath('//m:sst/m:si') as $i => $node) {
        $txt = '';
        if (isset($node->t)) {
            $txt = (string) $node->t;
        } elseif (isset($node->is->t)) {
            $txt = (string) $node->is->t;
        }
        $shared[$i] = $txt;
    }
}
$sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();
if ($sheetRaw === false) {
    fwrite(STDERR, "Sheet1 tidak ditemukan di dalam xlsx.\n");
    exit(1);
}
$dom = new DOMDocument();
$dom->loadXML($sheetRaw);

function colIndex(string $ref): int {
    $c = preg_replace('/[0-9].*$/', '', $ref);
    $idx = 0;
    foreach (str_split($c) as $ch) {
        $idx = $idx * 26 + (ord(strtoupper($ch)) - ord('A') + 1);
    }
    return $idx;
}
function cellValue(DOMElement $cell, array $shared): string {
    $type = $cell->getAttribute('t');
    if ($type === 's') {
        $idx = (int) trim($cell->textContent);
        return $shared[$idx] ?? '';
    }
    if ($type === 'inlineStr') {
        $t = $cell->getElementsByTagName('t')->item(0);
        return $t ? $t->textContent : '';
    }
    return trim($cell->textContent);
}
function normalizeKsa(string $k): string {
    $k = strtolower(trim($k));
    $map = ['ksa padi' => 'KSA Padi', 'ksa jagung' => 'KSA Jagung'];
    return $map[$k] ?? ucwords($k);
}

$rowsDom = $dom->getElementsByTagName('row');
$parsed = [];
foreach ($rowsDom as $row) {
    $cells = $row->getElementsByTagName('c');
    $r = [];
    foreach ($cells as $cell) {
        $ref = $cell->getAttribute('r');
        if ($ref === '') {
            continue;
        }
        $r[colIndex($ref)] = cellValue($cell, $shared);
    }
    $hasContent = false;
    foreach ($r as $v) {
        if ($v !== '') {
            $hasContent = true;
            break;
        }
    }
    if ($hasContent) {
        $parsed[] = $r;
    }
}
if (empty($parsed)) {
    fwrite(STDERR, "Tidak ada baris data di sheet1.\n");
    exit(1);
}

/* -------------------------------------------------
 * 2. Petakan kolom lewat header (robust vs posisi kolom)
 * ------------------------------------------------- */
$header = $parsed[0];
$colNama = $colEmail = $colKsa = 0;
foreach ($header as $idx => $val) {
    $low = strtolower(trim((string) $val));
    if (str_contains($low, 'nama')) {
        $colNama = $idx;
    } elseif (str_contains($low, 'email')) {
        $colEmail = $idx;
    } elseif (str_contains($low, 'ksa')) {
        $colKsa = $idx;
    }
}
if ($colNama === 0 || $colEmail === 0 || $colKsa === 0) {
    fwrite(STDERR, "Header kolom tidak dikenali. Ditemukan: " . implode(',', $header) . "\n");
    exit(1);
}

$records = [];
foreach (array_slice($parsed, 1) as $row) {
    $nama = trim((string) ($row[$colNama] ?? ''));
    $email = strtolower(trim((string) ($row[$colEmail] ?? '')));
    $ksa = normalizeKsa((string) ($row[$colKsa] ?? ''));
    if ($nama === '' || $email === '') {
        continue;
    }
    $records[] = ['nama' => $nama, 'email' => $email, 'ksa' => $ksa];
}

echo "Sumber: $xlsxPath\n";
echo "Total petugas terbaca dari XLSX: " . count($records) . "\n";

/* -------------------------------------------------
 * 3. Bandingkan dengan DB
 * ------------------------------------------------- */
$db = Database::getInstance()->getConnection();
$exist = $db->query(
    'SELECT email, nama_lengkap, ksa, username, role, aktif, must_change_password FROM users WHERE email IS NOT NULL'
)->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

$newCount = 0;
$matched = 0;
$ksaMismatch = [];
$nameMismatch = [];
foreach ($records as $rec) {
    $e = $rec['email'];
    if (!isset($exist[$e])) {
        $newCount++;
        continue;
    }
    $matched++;
    $cur = $exist[$e];
    if (($cur['ksa'] ?? null) !== $rec['ksa']) {
        $ksaMismatch[] = $e . " (XLSX=" . $rec['ksa'] . ", DB=" . ($cur['ksa'] ?? 'NULL') . ')';
    }
    if (($cur['nama_lengkap'] ?? '') !== $rec['nama']) {
        $nameMismatch[] = $e . ' (XLSX="' . $rec['nama'] . '", DB="' . ($cur['nama_lengkap'] ?? '') . '")';
    }
}

echo "Sudah ada di DB    : $matched\n";
echo "Baru (bukan di DB) : $newCount\n";
echo "Kesesuaian KSA      : " . (empty($ksaMismatch) ? 'SEMUA MATCH' : count($ksaMismatch) . ' mismatch') . "\n";
echo "Kesesuaian Nama     : " . (empty($nameMismatch) ? 'SEMUA MATCH' : count($nameMismatch) . ' mismatch') . "\n\n";

$ksaGroups = [];
foreach ($records as $rec) {
    $ksaGroups[$rec['ksa']] = ($ksaGroups[$rec['ksa']] ?? 0) + 1;
}
echo "Distribusi KSA (XLSX): " . json_encode($ksaGroups, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
$ksaDb = [];
foreach ($exist as $u) {
    if ($u['ksa'] !== null) {
        $ksaDb[$u['ksa']] = ($ksaDb[$u['ksa']] ?? 0) + 1;
    }
}
echo "Distribusi KSA (DB)  : " . json_encode($ksaDb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

if (!empty($ksaMismatch)) {
    echo "KSA mismatch:\n  " . implode("\n  ", $ksaMismatch) . "\n\n";
}
if (!empty($nameMismatch)) {
    echo "Nama mismatch:\n  " . implode("\n  ", $nameMismatch) . "\n\n";
}

/* -------------------------------------------------
 * 4. Write (hanya bila --apply)
 * ------------------------------------------------- */
if (!$apply) {
    echo "MODE: DRY-RUN (tidak ada penulisan ke DB). Pakai --apply untuk menuliskan.\n";
    exit(0);
}

$db->beginTransaction();
$inserted = 0;
$updated = 0;
$skipped = 0;
$hash = password_hash($tempPw, PASSWORD_DEFAULT);
$upsert = $db->prepare(
    'INSERT INTO users (username, password, email, nama_lengkap, role, aktif, ksa, must_change_password, token_version, created_at, updated_at)
     VALUES (:username, :password, :email, :nama, "petugas", 1, :ksa, 1, 0, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
        nama_lengkap = VALUES(nama_lengkap),
        ksa = VALUES(ksa),
        role = "petugas",
        aktif = 1,
        updated_at = NOW()'
);
$resetStmt = null;
foreach ($records as $rec) {
    try {
        $isNew = !isset($exist[$rec['email']]);
        $upsert->execute([
            ':username' => $rec['email'],
            ':password' => $hash,
            ':email' => $rec['email'],
            ':nama' => $rec['nama'],
            ':ksa' => $rec['ksa'],
        ]);
        if ($isNew) {
            $inserted++;
        } else {
            $updated++;
            if ($resetPw) {
                if ($resetStmt === null) {
                    $resetStmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 1, updated_at = NOW() WHERE email = ?');
                }
                $resetStmt->execute([$hash, $rec['email']]);
            }
        }
    } catch (Throwable $e) {
        $skipped++;
        fwrite(STDERR, "  ! Gagal upsert {$rec['email']}: " . $e->getMessage() . "\n");
    }
}
$db->commit();

echo "\nMODE: --apply AKTIF\n";
echo "Inserted (baru) : $inserted\n";
echo "Updated (ada)   : $updated\n";
echo "Skipped (error) : $skipped\n";
echo "Temp password   : $tempPw (must_change_password=1 pada yang baru)\n";

$total = (int) $db->query('SELECT COUNT(*) FROM users WHERE ksa IS NOT NULL')->fetchColumn();
$p = (int) $db->query("SELECT COUNT(*) FROM users WHERE ksa = 'KSA Padi'")->fetchColumn();
$j = (int) $db->query("SELECT COUNT(*) FROM users WHERE ksa = 'KSA Jagung'")->fetchColumn();
echo "DB sekarang -> total ksa=$total, KSA Padi=$p, KSA Jagung=$j\n";

