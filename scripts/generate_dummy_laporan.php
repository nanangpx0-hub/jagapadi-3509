<?php
declare(strict_types=1);

/**
 * Jagapadi: Comprehensive Dummy Laporan Hama Generator v2.0
 *
 * Generates 1000 realistic dummy laporan hama records with photos.
 * Features:
 * - Realistic, varied data across OPT types, locations, dates, severity
 * - Image generation using GD (placeholder photos with OPT labels)
 * - Batch insert with progress tracking
 * - Validation of all data before insert
 * - Statistics report at completion
 *
 * Usage: php scripts/generate_dummy_laporan.php [--count=1000] [--batch=50]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ─── Configuration ───────────────────────────────────────────────
$opts = getopt('', ['count:', 'batch:', 'clean', 'help']);
if (isset($opts['help'])) {
    echo <<<USAGE
Jagapadi Dummy Laporan Hama Generator
===================================
Usage: php scripts/generate_dummy_laporan.php [options]

Options:
  --count=N   Number of records to generate (default: 1000, max: 5000)
  --batch=N  Rows per batch insert (default: 50)
  --clean    Delete all generated records before creating new ones
  --help     Show this help message

Examples:
  php scripts/generate_dummy_laporan.php
  php scripts/generate_dummy_laporan.php --count=500 --batch=25
  php scripts/generate_dummy_laporan.php --count=2000 --clean

USAGE;
    exit(0);
}

$TARGET_COUNT = min(5000, max(1, (int)($opts['count'] ?? 1000)));
$BATCH_SIZE  = max(10, min(100, (int)($opts['batch'] ?? 50)));
$DO_CLEAN   = isset($opts['clean']);

// ─── Database Setup ───────────────────────────────────────────────
$db = Database::getInstance()->getConnection();

// ─── Helpers ───────────────────────────────────────────────
function randItem(array $arr) {
    return $arr[array_rand($arr)];
}

function randInt(int $min, int $max): int {
    return random_int($min, $max);
}

function randFloat(int $min, int $max, int $dec = 2): float {
    return round(random_int($min * 100, $max * 100) / 100, $dec);
}

function randDateInRange(string $start, string $end): string {
    $s = strtotime($start);
    $e = strtotime($end);
    return date('Y-m-d', randInt($s, $e));
}

function padLeft(int $n, int $len): string {
    return str_pad((string)$n, $len, '0', STR_PAD_LEFT);
}

function fmtBytes(int $bytes): string {
    foreach (['B','KB','MB'] as $i => $u) {
        if ($bytes < 1024 ** ($i + 1)) return round($bytes / 1024 ** $i, 1) . $u;
    }
    return round($bytes / 1024 ** 3, 1) . 'GB';
}

// Progress bar
function progressBar(int $done, int $total, int $width = 40): string {
    $pct = $total > 0 ? $done / $total : 0;
    $filled = (int)($pct * $width);
    $bar = str_repeat('█', $filled) . str_repeat('░', $width - $filled);
    return sprintf("\r[%s] %3d%% (%d/%d)", $bar, (int)($pct * 100), $done, $total);
}

// ─── Load Reference Data ───────────────────────────────────────────────
echo "Loading reference data...\n";

$users = $db->query("SELECT id, nama_lengkap, role FROM users WHERE aktif = 1 AND role IN ('petugas','admin','operator') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$opt   = $db->query("SELECT id, nama_opt, jenis FROM master_opt ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$kec   = $db->query("SELECT id, nama_kecamatan FROM master_kecamatan ORDER BY nama_kecamatan")->fetchAll(PDO::FETCH_ASSOC);
$desa  = $db->query("SELECT id, nama_desa, kecamatan_id FROM master_desa ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

if (count($users) < 3) { echo "ERROR: Need at least 3 users.\n"; exit(1); }
if (count($opt)   < 3) { echo "ERROR: Need at least 3 OPT.\n";    exit(1); }
if (count($kec)   < 3) { echo "ERROR: Need at least 3 kecamatan.\n"; exit(1); }
if (count($desa)  < 3) { echo "ERROR: Need at least 3 desa.\n";        exit(1); }

echo "  Users: " . count($users) . ", OPT: " . count($opt) . ", Kecamatan: " . count($kec) . ", Desa: " . count($desa) . "\n";

// ─── OPT Color Mapping (for generated photos) ───────────────────────────────────────────────
$optColors = [];
foreach ($opt as $o) {
    $jenis = strtoupper(substr($o['jenis'] ?? 'default', 0, 4));
    $optColors[$o['id']] = match ($jenis) {
        'HAMA' => ['#27ae60', '#1e8449'],
        'PENY' => ['#e67e22', '#d35400'],
        'VIRU' => ['#9b59b6', '#8e44ad'],
        'GULM' => ['#f39c12', '#d68910'],
        default => ['#3498db', '#2980b9'],
    };
}

// ─── Cleanup ───────────────────────────────────────────────
if ($DO_CLEAN) {
    echo "Cleaning up previously generated records...\n";
    $cleaned = 0;
    // Find photos
    $photosDir = ROOT_PATH . '/storage/laporan';
    $existing = $db->query("SELECT id, foto_url FROM laporan_hama WHERE foto_url LIKE '%/laporan/%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($existing as $r) {
        if (!empty($r['foto_url'])) {
            $fp = ROOT_PATH . '/public' . $r['foto_url'];
            if (file_exists($fp)) {
                @unlink($fp);
                $cleaned++;
            }
        }
    }
    $db->exec("DELETE FROM laporan_hama WHERE foto_url LIKE '%/laporan/%'");
    echo "  Deleted $cleaned photos, " . $db->query("SELECT ROW_COUNT()")->fetchColumn() . " records\n";
    $existingCount = $db->query("SELECT COUNT(*) FROM laporan_hama")->fetchColumn();
    echo "  Remaining records: $existingCount\n";
}

// ─── Image Generation (GD-based placeholder) ───────────────────────────────────────────────
function imageCreatePlaceholder(int $width, int $height, string $optName, string $optJenis, array $colors): ?string {
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $img = imagecreatetruecolor($width, $height);
    if (!$img) return null;

    $bgRgb = sscanf($colors[0], '%x');
    $fgRgb = sscanf($colors[1], '%x');
    $bg = imagecolorallocate($img, ($bgRgb[0] >> 16) & 0xFF, ($bgRgb[0] >> 8) & 0xFF, $bgRgb[0] & 0xFF);
    $fg = imagecolorallocate($img, ($fgRgb[0] >> 16) & 0xFF, ($fgRgb[0] >> 8) & 0xFF, $fgRgb[0] & 0xFF);
    $white = imagecolorallocate($img, 255, 255, 255);
    $gray = imagecolorallocate($img, 180, 180, 180);
    $black = imagecolorallocate($img, 30, 30, 30);

    imagefill($img, 0, 0, $bg);

    // Draw grid pattern
    for ($x = 0; $x < $width; $x += 20) {
        imageline($img, $x, 0, $x, $height, $gray);
    }
    for ($y = 0; $y < $height; $y += 20) {
        imageline($img, 0, $y, $width, $y, $gray);
    }

    // Draw OPT name
    imagestring($img, 5, 10, 10, substr($optName, 0, 20), $fg);
    imagestring($img, 3, 10, $height - 25, $optJenis, $fg);

    // Draw a simple plant icon (cross)
    $cx = $width / 2;
    $cy = $height / 2;
    imageline($img, $cx - 20, $cy, $cx + 20, $cy, $fg);
    imageline($img, $cx, $cy - 20, $cx, $cy + 20, $fg);

    // Border
    imagerectangle($img, 0, 0, $width - 1, $height - 1, $fg);

    // Capture to string
    ob_start();
    imagejpeg($img, null, 75);
    $jpeg = ob_get_clean();
    imagedestroy($img);
    return $jpeg;
}

// ─── Data Templates ───────────────────────────────────────────────
$keparahan = ['Ringan', 'Sedang', 'Berat'];
$kepBobot  = [50, 35, 15]; // weighted: 50% Ringan, 35% Sedang, 15% Berat

$jenisTambling = ['Kimia', 'Biologi', 'Fisik', 'Mekanis'];
$jenisTBobot  = [45, 25, 20, 10];

$statusOpts = ['Submitted', 'Submitted', 'Submitted', 'Draf', 'Diverifikasi'];
$statusBobot = [35, 35, 15, 10, 5];

$tanggalRangeStart = date('Y-01-01');
$tanggalRangeEnd   = date('Y-m-d');

$notesByJenis = [
    'Hama' => [
        'Dilaporkan adanya serangan hama pada pertanaman padi usia generatif. Daun menguning lebih awal dari normal.',
        'Terdapat koloni hama pada pertanaman padi. Pengendalian segera diperlukan.',
        'Serangan hama bersifat lokal. Monitoring mingguan dianjurkan.',
        'Populasi hama meningkat signifikan. Tanaman testigo menunjukkan gejala awal.',
        'Serangan hama teridentifikasi di lokasi kejadian. Penurunan produksi diperkirakan 15-20%.',
    ],
    'Penyakit' => [
        'Terdeteksi gejala penyakit pada daun dan batang. Penyebaran masih terkendali.',
        'Gejala mozaik/virus terdeteksi. Isolasi area diperlukan.',
        'Penyakit menyerang pertanaman secara sporadis. Rotasi tanaman dianjurkan.',
        'Infeksi terdeteksi melalui perubahan warna daun. Pengendalian hayati diterapkan.',
        'Serangan penyakit meluas. Hasil inspeksi lapangan menunjukkan 10-25% tanaman terinfeksi.',
    ],
    'Virus' => [
        'Gejala klorosis teramati pada beberapa tanaman sampel.',
        'Virus terdeteksi melalui tes laboratorium. Eradikasi segera diperlukan.',
        'Serangan virus bersifat sistemik. Peremajaan tanaman dilakukan.',
        'Indikasi virus pada fase vegetatif. Pengendalian preventif dianjurkan.',
        'Epidemi virus melokalisir. Koordinasi dengan POPT setempat dilakukan.',
    ],
    'default' => [
        'Pengamatan lapangan menunjukkan adanya gangguan pada pertanaman.',
        'Gejala awal serangan terdeteksi. Investigasi lanjut diperlukan.',
        'Pelaporan dilakukan setelah inspeksi rutin petugas POPT.',
        'Kerusakan terdeteksi pada area serangan. Dokumentasi foto dilampirkan.',
        'Monitoring berkala menemukan ketidaknormalan. Tindakan pengendalian disiapkan.',
    ],
];

// ─── Batch Insert Helper ───────────────────────────────────────────────
function batchInsert(PDO $db, string $table, array $cols, array $rows): int {
    if (empty($rows)) return 0;
    $placeStr = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $placeholders = implode(',', array_fill(0, count($rows), $placeStr));
    $sql = "INSERT INTO $table(" . implode(',', $cols) . ") VALUES " . $placeholders;
    $params = [];
    foreach ($rows as $r) {
        foreach ($cols as $c) {
            $params[] = $r[$c] ?? null;
        }
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function getLastInsertId(PDO $db): int {
    return (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
}

// ─── Validation ───────────────────────────────────────────────
function validateRow(array $row): array {
    $errors = [];
    if (empty($row['user_id'])) $errors[] = 'user_id empty';
    if (empty($row['tanggal'])) $errors[] = 'tanggal empty';
    if (empty($row['lokasi'])) $errors[] = 'lokasi empty';
    if (empty($row['master_opt_id'])) $errors[] = 'master_opt_id empty';
    if (!in_array($row['tingkat_keparahan'], ['Ringan','Sedang','Berat'])) {
        $errors[] = 'tingkat_keparahan invalid: ' . ($row['tingkat_keparahan'] ?? 'NULL');
    }
    return $errors;
}

// ─── Generate Photo ───────────────────────────────────────────────
function generatePhoto(string $photosDir, int $reportId, string $optName, string $optJenis, array $colors): ?string {
    if (!is_dir($photosDir)) {
        @mkdir($photosDir, 0755, true);
    }
    $filename = 'laporan_' . padLeft($reportId, 6) . '_' . bin2hex(random_bytes(2)) . '.jpg';
    $filepath = $photosDir . '/' . $filename;
    $jpeg = imageCreatePlaceholder(640, 480, $optName, $optJenis, $colors);
    if ($jpeg === null) {
        // Fallback: create minimal 1x1 pixel JPEG
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHhwaG/2wBDAQcHBwoJBwgHCggLCg4OEhgZGy0tIy0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS3/wAARCAAKABQDASIAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAABgUEB//EAB8QAAMAAQUBAAAAAAAAAAAAAAABAgMEEQASITFBUf/EABUBAQEAAAAAAAAAAAAAAAAAAAEF/8QAFREBAQAAAAAAAAAAAAAAAAAAAAH/2gAMAwEAAhEDEEA');
    }
    file_put_contents($filepath, $jpeg);
    return '/uploads/laporan/' . $filename;
}

// ─── Weighted Random ───────────────────────────────────────────────
function weightedRand(array $items, array $weights): mixed {
    $total = array_sum($weights);
    $r = randInt(1, $total);
    $cumsum = 0;
    foreach ($items as $i => $item) {
        $cumsum += $weights[$i];
        if ($r <= $cumsum) return $item;
    }
    return end($items);
}

// ─── Main Generation ───────────────────────────────────────────────
echo "\n=============================================================\n";
echo "  JAGAPADI DUMMY LAPORAN HAMA GENERATOR\n";
echo "  Target : $TARGET_COUNT records\n";
echo "  Batch  : $BATCH_SIZE rows/batch\n";
echo "  Start  : " . date('H:i:s') . "\n";
echo "=============================================================\n\n";

$photosDir = ROOT_PATH . '/storage/laporan';
if (!is_dir($photosDir)) {
    mkdir($photosDir, 0755, true);
}

$cols = [
    'user_id', 'master_opt_id', 'tanggal', 'lokasi',
    'latitude', 'longitude',
    'tingkat_keparahan', 'populasi', 'luas_serangan',
    'foto_url', 'status', 'catatan',
    'kecamatan', 'desa', 'alamat_lengkap',
    'kabupaten', 'kabupaten_id', 'kecamatan_id', 'desa_id',
];

// Generate reports in batches
$inserted  = 0;
$validated = 0;
$skipped  = 0;
$photosGen = 0;
$bytesGen = 0;
$errorsAll = [];

$stats = [
    'by_opt'      => [],
    'by_kec'      => [],
    'by_desa'     => [],
    'by_keparahan'=> [],
    'by_status'   => [],
    'by_month'   => [],
    'by_role'    => [],
];

$optMap = [];
foreach ($opt as $o) { $optMap[$o['id']] = $o; }
$kecMap = [];
foreach ($kec as $k) { $kecMap[$k['id']] = $k; }
$desaMap = [];
foreach ($desa as $d) { $desaMap[$d['id']] = $d; }

echo "Generating and inserting records...\n";

for ($batchStart = 0; $batchStart < $TARGET_COUNT; $batchStart += $BATCH_SIZE) {
    $batchEnd = min($batchStart + $BATCH_SIZE, $TARGET_COUNT);
    $batchRows = [];

    for ($i = $batchStart; $i < $batchEnd; $i++) {
        $user = randItem($users);
        $optRec = randItem($opt);
        $kecRec = randItem($kec);
        $desaAll = array_filter($desa, fn($d) => $d['kecamatan_id'] == $kecRec['id']);
        if (empty($desaAll)) $desaAll = $desa;
        $desaRec = randItem($desaAll);

        $optJenis = strtoupper(substr($optRec['jenis'] ?? 'default', 0, 4));
        $tanggal = randDateInRange($tanggalRangeStart, $tanggalRangeEnd);

        // Luas: weighted by severity
        $kep = weightedRand($keparahan, $kepBobot);
        $luasMap = ['Ringan' => [0.1, 2.5], 'Sedang' => [2.0, 7.0], 'Berat' => [5.0, 20.0]];
        $ls = $luasMap[$kep];
        $luas = randFloat((int)($ls[0] * 10), (int)($ls[1] * 10));

        // Populasi: weighted by severity
        $popMap = ['Ringan' => [5, 50], 'Sedang' => [40, 200], 'Berat' => [150, 500]];
        $ps = $popMap[$kep];
        $populasi = randInt($ps[0], $ps[1]);

        // Catatan
        $catKey = match ($optJenis) {
            'HAMA' => 'Hama',
            'PENY' => 'Penyakit',
            'VIRU' => 'Virus',
            default  => 'default',
        };
        $catatan = randItem($notesByJenis[$catKey] ?? $notesByJenis['default']);

        // Status
        $status = weightedRand($statusOpts, $statusBobot);

        $row = [
            'user_id'              => (int)$user['id'],
            'master_opt_id'        => (int)$optRec['id'],
            'tanggal'             => $tanggal,
            'lokasi'              => 'Desa ' . $desaRec['nama_desa'] . ', Kec. ' . $kecRec['nama_kecamatan'],
            'latitude'            => round(-8 + (randInt(0, 30) / 100), 6),
            'longitude'           => round(113 + (randInt(0, 30) / 100), 6),
            'tingkat_keparahan'   => $kep,
            'populasi'           => $populasi,
            'luas_serangan'       => $luas,
            'foto_url'           => null,
            'status'             => $status,
            'catatan'            => $catatan,
            'kecamatan'          => $kecRec['nama_kecamatan'],
            'desa'              => $desaRec['nama_desa'],
            'alamat_lengkap'     => 'Desa ' . $desaRec['nama_desa'] . ', Kec. ' . $kecRec['nama_kecamatan'] . ', Kab. Jember',
            'kabupaten'         => 'Kabupaten Jember',
            'kabupaten_id'      => 9,
            'kecamatan_id'      => (int)$kecRec['id'],
            'desa_id'           => (int)$desaRec['id'],
        ];

        $rowErrors = validateRow($row);
        if (!empty($rowErrors)) {
            $errorsAll = array_merge($errorsAll, $rowErrors);
            $skipped++;
            continue;
        }
        $validated++;
        $batchRows[] = $row;
    }

    // Batch insert
    if (!empty($batchRows)) {
        $beforeId = getLastInsertId($db);
        $count = batchInsert($db, 'laporan_hama', $cols, $batchRows);
        $inserted += $count;
        $afterId = getLastInsertId($db);

        // Generate photos for inserted rows
        for ($reportId = $beforeId + 1; $reportId <= $afterId; $reportId++) {
            $optRec = randItem($opt);
            $colors = $optColors[$optRec['id']] ?? ['#3498db', '#2980b9'];
            $photoPath = generatePhoto($photosDir, $reportId, $optRec['nama_opt'], $optRec['jenis'], $colors);
            if ($photoPath) {
                $db->prepare("UPDATE laporan_hama SET foto_url = ? WHERE id = ?")->execute([$photoPath, $reportId]);
                $fpath = $photosDir . '/' . basename($photoPath);
                if (file_exists($fpath)) {
                    $bytesGen += filesize($fpath);
                    $photosGen++;
                }
            }
        }
    }

    // Progress
    $done = $batchEnd;
    echo "\r" . progressBar($done, $TARGET_COUNT) . " | Valid: $validated | Inserted: $inserted | Photos: $photosGen (" . fmtBytes($bytesGen) . ") | Skipped: $skipped";
}

// ─── Update Statistics ───────────────────────────────────────────────
echo "\n\nCollecting statistics...\n";
$allReports = $db->query("SELECT * FROM laporan_hama ORDER BY id DESC LIMIT $TARGET_COUNT")->fetchAll(PDO::FETCH_ASSOC);

foreach ($allReports as $r) {
    $optId = $r['master_opt_id'];
    $kecNama = $r['kecamatan'] ?? '';
    $desaNama = $r['desa'] ?? '';
    $kep = $r['tingkat_keparahan'] ?? '';
    $status = $r['status'] ?? '';
    $role = '';
    foreach ($users as $u) {
        if ($u['id'] == $r['user_id']) { $role = $u['role']; break; }
    }

    if (!isset($stats['by_opt'][$optId])) $stats['by_opt'][$optId] = 0;
    $stats['by_opt'][$optId]++;

    if ($kecNama) {
        if (!isset($stats['by_kec'][$kecNama])) $stats['by_kec'][$kecNama] = 0;
        $stats['by_kec'][$kecNama]++;
    }
    if ($desaNama) {
        if (!isset($stats['by_desa'][$desaNama])) $stats['by_desa'][$desaNama] = 0;
        $stats['by_desa'][$desaNama]++;
    }
    if ($kep) {
        if (!isset($stats['by_keparahan'][$kep])) $stats['by_keparahan'][$kep] = 0;
        $stats['by_keparahan'][$kep]++;
    }
    if ($status) {
        if (!isset($stats['by_status'][$status])) $stats['by_status'][$status] = 0;
        $stats['by_status'][$status]++;
    }
    if ($role) {
        if (!isset($stats['by_role'][$role])) $stats['by_role'][$role] = 0;
        $stats['by_role'][$role]++;
    }

    $monthKey = date('Y-m', strtotime($r['tanggal']));
    if (!isset($stats['by_month'][$monthKey])) $stats['by_month'][$monthKey] = 0;
    $stats['by_month'][$monthKey]++;
}

// ─── Print Final Report ───────────────────────────────────────────────
echo "\n";
echo "══════════════════════════════════════════════════════════\n";
echo "  LAPORAN REKAPITULASI PEMBUATAN DATA DUMMY\n";
echo "══════════════════════════════════════════════════════════\n\n";

echo ">>> STATUS UMUM <<<\n";
echo "  Total record dibuat   : $inserted\n";
echo "  Total foto       : $photosGen\n";
echo "  Ukuran foto    : " . fmtBytes($bytesGen) . "\n";
echo "  Valid rows    : $validated\n";
echo "  Dilewati    : $skipped\n";
echo "  Error valid : " . count($errorsAll) . "\n";

echo "\n>>> OLEH JENIS OPT <<<\n";
arsort($stats['by_opt']);
foreach (array_slice($stats['by_opt'], 0, 10, true) as $optId => $cnt) {
    $nama = $optMap[$optId]['nama_opt'] ?? "ID $optId";
    echo "  " . str_pad($nama, 25) . " : $cnt\n";
}

echo "\n>>> OLEH KECAMATAN (Top 10) <<<\n";
arsort($stats['by_kec']);
foreach (array_slice($stats['by_kec'], 0, 10, true) as $kecNama => $cnt) {
    echo "  " . str_pad($kecNama, 25) . " : $cnt\n";
}

echo "\n>>> OLEH TINGKAT KERUSAKAN <<<\n";
foreach ($stats['by_keparahan'] as $kep => $cnt) {
    $pct = $inserted > 0 ? round($cnt / $inserted * 100, 1) : 0;
    echo "  " . str_pad($kep, 10) . " : $cnt (" . $pct . "%)\n";
}

echo "\n>>> OLEH STATUS <<<\n";
foreach ($stats['by_status'] as $status => $cnt) {
    echo "  " . str_pad($status, 15) . " : $cnt\n";
}

echo "\n>>> OLEH ROLE PELAPOR <<<\n";
foreach ($stats['by_role'] as $role => $cnt) {
    echo "  " . str_pad($role, 15) . " : $cnt\n";
}

echo "\n>>> OLEH BULAN <<<\n";
ksort($stats['by_month']);
foreach ($stats['by_month'] as $month => $cnt) {
    echo "  " . str_pad($month, 10) . " : $cnt\n";
}

// Date range
$dates = array_column($allReports, 'tanggal');
echo "\n>>> RENTANG TANGGAL <<<\n";
echo "  Tanggal awal : " . min($dates) . "\n";
echo "  Tanggal akhir: " . max($dates) . "\n";

echo "\n>>> ERROR VALIDASI (sample) <<<\n";
if (empty($errorsAll)) {
    echo "  (tidak ada)\n";
} else {
    foreach (array_slice(array_unique($errorsAll), 0, 5) as $e) {
        echo "  - $e\n";
    }
}

echo "\n>>> LOKASI PENYIMPANAN FOTO <<<\n";
echo "  $photosDir\n";

echo "\n=============================================================\n";
echo "  Selesai: " . date('H:i:s') . "\n";
echo "=============================================================\n";

exit(0);