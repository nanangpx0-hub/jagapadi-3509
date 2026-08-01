<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Logger;
use App\Helpers\NomorLaporanGenerator;

$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

$env = Env::get('APP_ENV', 'production');

$isFresh = in_array('--fresh', $argv ?? [], true);

if ($env === 'production') {
    echo "[ERROR] seed_dummy tidak boleh dijalankan di production (APP_ENV=production)." . PHP_EOL;
    exit(1);
}

$timezone = Env::get('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($timezone);

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
Logger::init($logDir);

echo "=== JAGAPADI Dummy Data Seed ===" . PHP_EOL;
echo "  Environment: $env" . PHP_EOL;
echo "  Mode: " . ($isFresh ? '--fresh (hapus data lama)' : 'append/idempotent') . PHP_EOL;
echo PHP_EOL;

try {
    $pdo = Database::connect();
} catch (\Throwable $e) {
    echo "[ERROR] Database tidak dapat diakses: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ---------------------------------------------------------------------------
// FRESH MODE
// ---------------------------------------------------------------------------
if ($isFresh) {
    echo "--- Fresh mode: membersihkan data transaksional ---" . PHP_EOL;
    $pdo->exec("DELETE FROM `device_tokens`");
    $pdo->exec("DELETE FROM `notifications`");
    $pdo->exec("DELETE FROM `activity_log`");
    $pdo->exec("DELETE FROM `audit_log_wilayah`");
    $pdo->exec("DELETE FROM `laporan_irigasi`");
    $pdo->exec("DELETE FROM `laporan_hama`");
    $pdo->exec("DELETE FROM `nomor_laporan_counter`");
    echo "  [OK] Data transaksional dibersihkan." . PHP_EOL . PHP_EOL;
}

// ---------------------------------------------------------------------------
// Helper: buat gambar dummy 1x1 JPEG
// ---------------------------------------------------------------------------
function createDummyImage(string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!file_exists($path)) {
        $img = imagecreatetruecolor(1, 1);
        imagejpeg($img, $path, 75);
        imagedestroy($img);
    }
}

function pick(array $arr) { return $arr[array_rand($arr)]; }

$pdo->beginTransaction();

try {

// ===========================================================================
// 1. USERS
// ===========================================================================
echo "--- Users ---" . PHP_EOL;

$seedUsers = [
    ['username' => 'admin',             'password' => 'ChangeMeAdmin!123',   'email' => 'admin@jagapadi.local',      'nama_lengkap' => 'Administrator JAGAPADI',      'role' => 'admin',   'aktif' => 1, 'must_change_password' => 0],
    ['username' => 'petugas01',         'password' => 'ChangeMePetugas!123', 'email' => 'petugas01@jagapadi.local',  'nama_lengkap' => 'Petugas Lapangan 01',          'role' => 'petugas', 'aktif' => 1, 'must_change_password' => 0],
    ['username' => 'petugas02',         'password' => 'ChangeMePetugas!123', 'email' => 'petugas02@jagapadi.local',  'nama_lengkap' => 'Petugas Lapangan 02',          'role' => 'petugas', 'aktif' => 1, 'must_change_password' => 0],
    ['username' => 'petugas_nonaktif',  'password' => 'ChangeMePetugas!123', 'email' => 'petugas_nonaktif@jagapadi.local', 'nama_lengkap' => 'Petugas Nonaktif',     'role' => 'petugas', 'aktif' => 0, 'must_change_password' => 0],
    ['username' => 'petugas_demo',      'password' => 'ChangeMePetugas!123', 'email' => 'petugas_demo@jagapadi.local','nama_lengkap' => 'Petugas Demo',            'role' => 'petugas', 'aktif' => 1, 'must_change_password' => 1],
];

$userIdMap = [];
foreach ($seedUsers as $u) {
    $stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
    $stmt->execute([$u['username']]);
    $existing = $stmt->fetchColumn();

    if ($existing !== false && $existing !== null) {
        $userIdMap[$u['username']] = (int) $existing;
        // Verify password hash is still correct, re-hash if not
        $stmt = $pdo->prepare("SELECT `password` FROM `users` WHERE `id` = ?");
        $stmt->execute([$existing]);
        $currentHash = $stmt->fetchColumn();
        if ($currentHash !== false && !password_verify($u['password'], $currentHash)) {
            $newHash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
            $stmt->execute([$newHash, $existing]);
            echo "  [FIX]  {$u['username']} password hash re-hashed" . PHP_EOL;
        } else {
            echo "  [SKIP] {$u['username']} (already exists)" . PHP_EOL;
        }
        continue;
    }

    $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `email`, `nama_lengkap`, `role`, `aktif`, `must_change_password`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$u['username'], $hash, $u['email'], $u['nama_lengkap'], $u['role'], $u['aktif'], $u['must_change_password']]);

    $userIdMap[$u['username']] = (int) $pdo->lastInsertId();
    echo "  [OK]   {$u['username']} created (role: {$u['role']})" . PHP_EOL;
}

// Ensure admin and petugas01 are mapped
if (!isset($userIdMap['admin'])) {
    $stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
    $stmt->execute(['admin']);
    $userIdMap['admin'] = (int) $stmt->fetchColumn();
}
if (!isset($userIdMap['petugas01'])) {
    $stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
    $stmt->execute(['petugas01']);
    $userIdMap['petugas01'] = (int) $stmt->fetchColumn();
}

$adminId = $userIdMap['admin'];
$petugas01Id = $userIdMap['petugas01'];
$petugas02Id = $userIdMap['petugas02'] ?? $petugas01Id;

// ===========================================================================
// 2. WILAYAH — tambah desa jika < 15
// ===========================================================================
echo "--- Wilayah ---" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM `master_desa`");
$desaCount = (int) $stmt->fetchColumn();

if ($desaCount < 15) {
    $kecMap = [];
    $stmt = $pdo->query("SELECT `id`, `kode`, `nama_kecamatan` FROM `master_kecamatan`");
    foreach ($stmt->fetchAll() as $row) {
        $kecMap[$row['nama_kecamatan']] = ['id' => (int) $row['id'], 'kode' => $row['kode']];
    }

    $additionalDesa = [
        ['kecamatan' => 'Kaliwates', 'kode' => '3509010003', 'nama' => 'Mangli'],
        ['kecamatan' => 'Kaliwates', 'kode' => '3509010004', 'nama' => 'Sempusari'],
        ['kecamatan' => 'Sumbersari', 'kode' => '3509020003', 'nama' => 'Tegalgede'],
        ['kecamatan' => 'Sumbersari', 'kode' => '3509020004', 'nama' => 'Wirolegi'],
        ['kecamatan' => 'Patrang', 'kode' => '3509030003', 'nama' => 'Jemberlor'],
        ['kecamatan' => 'Patrang', 'kode' => '3509030004', 'nama' => 'Gebang'],
        ['kecamatan' => 'Ajung', 'kode' => '3509040003', 'nama' => 'Mangaran'],
        ['kecamatan' => 'Rambipuji', 'kode' => '3509050003', 'nama' => 'Nogosari'],
        ['kecamatan' => 'Rambipuji', 'kode' => '3509050004', 'nama' => 'Kaliwining'],
    ];

    $added = 0;
    foreach ($additionalDesa as $d) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `master_desa` WHERE `kode` = ?");
        $stmt->execute([$d['kode']]);
        if ((int) $stmt->fetchColumn() > 0) {
            continue;
        }
        $stmt = $pdo->prepare("INSERT INTO `master_desa` (`kecamatan_id`, `kode`, `nama_desa`) VALUES (?, ?, ?)");
        $stmt->execute([$kecMap[$d['kecamatan']]['id'], $d['kode'], $d['nama']]);
        $added++;
    }
    echo "  [OK]   Added $added desa (total: " . ($desaCount + $added) . ")" . PHP_EOL;
} else {
    echo "  [SKIP] Desa sudah $desaCount (>= 15)" . PHP_EOL;
}

// ===========================================================================
// 3. MASTER OPT — tambah 1 nonaktif
// ===========================================================================
echo "--- Master OPT ---" . PHP_EOL;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM `master_opt` WHERE `nama_opt` = ?");
$stmt->execute(['Ulat Grayak']);
$exists = (int) $stmt->fetchColumn();
if ($exists === 0) {
    $pdo->exec("INSERT INTO `master_opt` (`nama_opt`, `jenis`, `etl_acuan`, `satuan_etl`, `deskripsi`, `aktif`) VALUES ('Ulat Grayak', 'hama', 3.00, 'ekor/rumpun', 'Hama ulat grayak pada tanaman jagung', 0)");
    echo "  [OK]   Added OPT nonaktif: Ulat Grayak" . PHP_EOL;
} else {
    echo "  [SKIP] OPT nonaktif already exists" . PHP_EOL;
}

// Fetch all OPT IDs
$optMap = [];
$stmt = $pdo->query("SELECT `id`, `nama_opt` FROM `master_opt` WHERE `aktif` = 1");
foreach ($stmt->fetchAll() as $row) {
    $optMap[$row['nama_opt']] = (int) $row['id'];
}

// Fetch wilayah mappings
$kabId = (int) $pdo->query("SELECT `id` FROM `master_kabupaten` WHERE `kode` = '3509'")->fetchColumn();
$kecDesaMap = [];
$stmt = $pdo->query("SELECT kec.`id` AS kec_id, kec.`nama_kecamatan`, des.`id` AS desa_id, des.`nama_desa` FROM `master_kecamatan` kec JOIN `master_desa` des ON des.`kecamatan_id` = kec.`id`");
foreach ($stmt->fetchAll() as $row) {
    $kecDesaMap[] = [
        'kec_id' => (int) $row['kec_id'],
        'kec' => $row['nama_kecamatan'],
        'desa_id' => (int) $row['desa_id'],
        'desa' => $row['nama_desa'],
    ];
}

// Helper: date ranges
$now = new DateTimeImmutable();
$dates = [];
for ($i = 0; $i < 90; $i++) {
    $dates[] = $now->sub(new DateInterval("P" . $i . "D"))->format('Y-m-d');
}

// ===========================================================================
// 4. LAPORAN HAMA
// ===========================================================================
echo "--- Laporan Hama ---" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM `laporan_hama`");
$existingHama = (int) $stmt->fetchColumn();
$neededHama = max(0, 20 - $existingHama);

if ($neededHama > 0) {
    $hamaStatuses = array_merge(
        array_fill(0, 6, 'Draf'),
        array_fill(0, 6, 'Submitted'),
        array_fill(0, 4, 'Diverifikasi'),
        array_fill(0, 3, 'Ditolak'),
        array_fill(0, 1, 'Diarsipkan')
    );
    $hamaStatuses = array_slice($hamaStatuses, 0, $neededHama);
    shuffle($hamaStatuses);

    $optNames = array_keys($optMap);
    $tingkat = ['Ringan', 'Sedang', 'Berat'];

    $hamaCreated = 0;
    foreach ($hamaStatuses as $status) {
        $tanggal = pick($dates);
        $wl = pick($kecDesaMap);
        $petugasPick = pick(['petugas01', 'petugas02']);
        $userId = $petugasPick === 'petugas01' ? $petugas01Id : $petugas02Id;
        $lat = round(-8.18 + (mt_rand(-50, 50) / 1000), 7);
        $lng = round(113.70 + (mt_rand(-50, 50) / 1000), 7);
        $optId = $optMap[pick($optNames)];

        $data = [
            'user_id' => $userId,
            'master_opt_id' => $optId,
            'tanggal' => $tanggal,
            'kabupaten_id' => $kabId,
            'kecamatan_id' => $wl['kec_id'],
            'desa_id' => $wl['desa_id'],
            'lokasi' => 'Sawah ' . $wl['desa'],
            'alamat_lengkap' => $wl['desa'] . ', Kec. ' . $wl['kec'] . ', Kab. Jember',
            'latitude' => $lat,
            'longitude' => $lng,
            'tingkat_keparahan' => pick($tingkat),
            'luas_serangan' => round(mt_rand(10, 500) / 10, 2),
            'populasi' => round(mt_rand(100, 5000) / 10, 2),
            'catatan' => pick(['Tanaman padi mulai terserang hama sejak 2 minggu lalu.', 'Serangan menyebar merata di area persawahan.', 'Petani setempat melaporkan peningkatan populasi hama.', 'Kondisi tanaman menguning dan pertumbuhan terhambat.', 'Serangan baru terdeteksi, masih di area kecil.']),
            'status' => $status,
        ];

        if (in_array($status, ['Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'])) {
            $data['nomor_laporan'] = NomorLaporanGenerator::generate('LH', $tanggal);
            $data['ip_pengirim'] = '127.0.0.1';
        }

        if (in_array($status, ['Diverifikasi', 'Ditolak', 'Diarsipkan'])) {
            $data['verified_by'] = $adminId;
            $data['verified_at'] = date('Y-m-d H:i:s', strtotime($tanggal . ' +1 day'));
            if ($status === 'Ditolak') {
                $data['catatan_verifikasi'] = pick(['Data tidak lengkap, harap lengkapi alamat dan koordinat.', 'Foto tidak sesuai dengan kondisi lapangan yang dilaporkan.', 'Luas serangan tidak sesuai dengan hasil verifikasi lapangan.']);
            } elseif ($status === 'Diverifikasi') {
                $data['catatan_verifikasi'] = 'Data sesuai dengan hasil verifikasi lapangan.';
            } elseif ($status === 'Diarsipkan') {
                $data['catatan_verifikasi'] = 'Laporan diarsipkan karena sudah ditindaklanjuti.';
            }
        }

        // Draf & Ditolak dapat foto
        if (in_array($status, ['Draf', 'Ditolak'])) {
            $subDir = date('Ym', strtotime($tanggal));
            $fotoDir = BASE_PATH . '/public/assets/uploads/laporan-hama/' . $subDir;
            $fotoFile = bin2hex(random_bytes(16)) . '.jpg';
            $fotoPath = $fotoDir . '/' . $fotoFile;
            createDummyImage($fotoPath);
            $data['foto_url'] = 'assets/uploads/laporan-hama/' . $subDir . '/' . $fotoFile;
        }

        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $pdo->prepare("INSERT INTO `laporan_hama` ($cols) VALUES ($vals)");
        $stmt->execute(array_values($data));
        $hamaCreated++;
    }
    echo "  [OK]   Created $hamaCreated laporan_hama (total: " . ($existingHama + $hamaCreated) . ")" . PHP_EOL;
} else {
    echo "  [SKIP] Already $existingHama laporan_hama (>= 20)" . PHP_EOL;
}

// ===========================================================================
// 5. LAPORAN IRIGASI
// ===========================================================================
echo "--- Laporan Irigasi ---" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM `laporan_irigasi`");
$existingIrigasi = (int) $stmt->fetchColumn();
$neededIrigasi = max(0, 15 - $existingIrigasi);

if ($neededIrigasi > 0) {
    $irigasiStatuses = array_merge(
        array_fill(0, 4, 'Draf'),
        array_fill(0, 5, 'Submitted'),
        array_fill(0, 3, 'Diverifikasi'),
        array_fill(0, 2, 'Ditolak'),
        array_fill(0, 1, 'Diarsipkan')
    );
    $irigasiStatuses = array_slice($irigasiStatuses, 0, $neededIrigasi);
    shuffle($irigasiStatuses);

    $kondisiFisik = ['Bagus', 'Sedang', 'Tidak Bagus', 'Rusak'];
    $debitAir = ['Cukup', 'Kurang', 'Kering'];

    $irigasiCreated = 0;
    foreach ($irigasiStatuses as $status) {
        $tanggal = pick($dates);
        $wl = pick($kecDesaMap);
        $petugasPick = pick(['petugas01', 'petugas02']);
        $userId = $petugasPick === 'petugas01' ? $petugas01Id : $petugas02Id;
        $lat = round(-8.18 + (mt_rand(-50, 50) / 1000), 7);
        $lng = round(113.70 + (mt_rand(-50, 50) / 1000), 7);
        $saluran = pick(['Saluran Primer ' . $wl['desa'], 'Saluran Sekunder ' . $wl['kec'], 'Saluran Tersier ' . $wl['desa'], 'Dam ' . $wl['desa']]);

        $data = [
            'user_id' => $userId,
            'tanggal' => $tanggal,
            'kabupaten_id' => $kabId,
            'kecamatan_id' => $wl['kec_id'],
            'desa_id' => $wl['desa_id'],
            'nama_saluran' => $saluran,
            'daerah_irigasi' => 'Daerah Irigasi ' . $wl['kec'],
            'latitude' => $lat,
            'longitude' => $lng,
            'kondisi_fisik' => pick($kondisiFisik),
            'debit_air' => pick($debitAir),
            'catatan' => pick(['Saluran irigasi mengalami pendangkalan.', 'Debit air menurun drastis di musim kemarau.', 'Kondisi saluran cukup baik, perlu pemeliharaan rutin.', 'Terdapat kebocoran di beberapa titik saluran.', 'Air mengalir lancar, cukup untuk kebutuhan sawah.']),
            'status' => $status,
        ];

        if (in_array($status, ['Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'])) {
            $data['nomor_laporan'] = NomorLaporanGenerator::generate('LI', $tanggal);
            $data['ip_pengirim'] = '127.0.0.1';
        }

        if (in_array($status, ['Diverifikasi', 'Ditolak', 'Diarsipkan'])) {
            $data['verified_by'] = $adminId;
            $data['verified_at'] = date('Y-m-d H:i:s', strtotime($tanggal . ' +1 day'));
            if ($status === 'Ditolak') {
                $data['catatan_verifikasi'] = pick(['Data belum lengkap, silakan lengkapi informasi kondisi fisik.', 'Koordinat lokasi tidak sesuai dengan saluran yang dilaporkan.']);
            } elseif ($status === 'Diverifikasi') {
                $data['catatan_verifikasi'] = 'Data valid, kondisi sesuai verifikasi lapangan.';
            } elseif ($status === 'Diarsipkan') {
                $data['catatan_verifikasi'] = 'Laporan irigasi diarsipkan.';
            }
        }

        // Draf & Ditolak dapat foto
        if (in_array($status, ['Draf', 'Ditolak'])) {
            $subDir = date('Ym', strtotime($tanggal));
            $fotoDir = BASE_PATH . '/public/assets/uploads/laporan-irigasi/' . $subDir;
            $fotoFile = bin2hex(random_bytes(16)) . '.jpg';
            $fotoPath = $fotoDir . '/' . $fotoFile;
            createDummyImage($fotoPath);
            $data['foto_url'] = 'assets/uploads/laporan-irigasi/' . $subDir . '/' . $fotoFile;
        }

        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $pdo->prepare("INSERT INTO `laporan_irigasi` ($cols) VALUES ($vals)");
        $stmt->execute(array_values($data));
        $irigasiCreated++;
    }
    echo "  [OK]   Created $irigasiCreated laporan_irigasi (total: " . ($existingIrigasi + $irigasiCreated) . ")" . PHP_EOL;
} else {
    echo "  [SKIP] Already $existingIrigasi laporan_irigasi (>= 15)" . PHP_EOL;
}

// ===========================================================================
// 6. NOTIFICATIONS (only if < 10)
// ===========================================================================
echo "--- Notifications ---" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM `notifications`");
$existingNotif = (int) $stmt->fetchColumn();
$neededNotif = max(0, 10 - $existingNotif);

if ($neededNotif > 0) {
    $notifTemplates = [
        ['user_id' => $adminId, 'type' => 'laporan_submitted', 'title' => 'Laporan Hama Baru', 'body' => 'Petugas telah mengirim laporan hama baru.'],
        ['user_id' => $adminId, 'type' => 'laporan_submitted', 'title' => 'Laporan Irigasi Baru', 'body' => 'Petugas telah mengirim laporan irigasi baru.'],
        ['user_id' => $petugas01Id, 'type' => 'laporan_verified', 'title' => 'Laporan Diverifikasi', 'body' => 'Laporan hama Anda telah diverifikasi oleh admin.'],
        ['user_id' => $petugas01Id, 'type' => 'laporan_rejected', 'title' => 'Laporan Ditolak', 'body' => 'Laporan hama Anda ditolak. Silakan perbaiki dan kirim ulang.'],
        ['user_id' => $petugas02Id, 'type' => 'laporan_verified', 'title' => 'Laporan Irigasi Diverifikasi', 'body' => 'Laporan irigasi Anda telah diverifikasi.'],
        ['user_id' => $petugas02Id, 'type' => 'laporan_rejected', 'title' => 'Laporan Irigasi Ditolak', 'body' => 'Laporan irigasi Anda ditolak. Silakan perbaiki.'],
        ['user_id' => $petugas01Id, 'type' => 'laporan_archived', 'title' => 'Laporan Diarsipkan', 'body' => 'Laporan hama Anda telah diarsipkan.'],
        ['user_id' => $adminId, 'type' => 'laporan_submitted', 'title' => 'Laporan Baru Masuk', 'body' => 'Ada laporan baru yang perlu diverifikasi.'],
        ['user_id' => $petugas01Id, 'type' => 'laporan_verified', 'title' => 'Laporan Irigasi Diverifikasi', 'body' => 'Laporan irigasi Anda telah diverifikasi oleh admin.'],
        ['user_id' => $petugas02Id, 'type' => 'laporan_archived', 'title' => 'Laporan Diarsipkan', 'body' => 'Laporan irigasi Anda telah diarsipkan.'],
    ];

    $notifTemplates = array_slice($notifTemplates, 0, $neededNotif);
    $notifCreated = 0;
    foreach ($notifTemplates as $n) {
        $stmt = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `type`, `title`, `body`, `read_at`, `created_at`) VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))");
        $readAt = (mt_rand(0, 1) === 0) ? date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 10) . ' days')) : null;
        $daysAgo = mt_rand(1, 30);
        $stmt->execute([$n['user_id'], $n['type'], $n['title'], $n['body'], $readAt, $daysAgo]);
        $notifCreated++;
    }
    echo "  [OK]   Created $notifCreated notifications" . PHP_EOL;
} else {
    echo "  [SKIP] Already $existingNotif notifications (>= 10)" . PHP_EOL;
}

// ===========================================================================
// 7. AUDIT LOG WILAYAH
// ===========================================================================
echo "--- Audit Log Wilayah ---" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM `audit_log_wilayah`");
$existingAudit = (int) $stmt->fetchColumn();

if ($existingAudit < 2) {
    $stmt = $pdo->prepare("INSERT INTO `audit_log_wilayah` (`admin_id`, `tabel`, `record_id`, `aksi`, `data_lama`, `data_baru`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$adminId, 'master_desa', 1, 'UPDATE', json_encode(['nama_desa' => 'Desa Lama']), json_encode(['nama_desa' => 'Desa Baru'])]);
    $stmt->execute([$adminId, 'master_kecamatan', 1, 'INSERT', null, json_encode(['kode' => '3509010', 'nama_kecamatan' => 'Kaliwates'])]);
    echo "  [OK]   Added 2 audit_log_wilayah" . PHP_EOL;
} else {
    echo "  [SKIP] Already $existingAudit audit_log_wilayah" . PHP_EOL;
}

// ===========================================================================
// 8. ACTIVITY LOG — add a few if < 5
// ===========================================================================
echo "--- Activity Log ---" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM `activity_log`");
$existingAct = (int) $stmt->fetchColumn();

if ($existingAct < 5) {
    $activities = [
        ['user_id' => $adminId, 'action' => 'login', 'description' => 'Admin login', 'ip_address' => '127.0.0.1'],
        ['user_id' => $petugas01Id, 'action' => 'login', 'description' => 'Petugas01 login', 'ip_address' => '127.0.0.1'],
        ['user_id' => $petugas01Id, 'action' => 'create_draft', 'description' => 'Membuat draft laporan hama', 'ip_address' => '127.0.0.1'],
        ['user_id' => $adminId, 'action' => 'verify_laporan', 'description' => 'Verifikasi laporan hama', 'ip_address' => '127.0.0.1'],
    ];
    $stmt = $pdo->prepare("INSERT INTO `activity_log` (`user_id`, `action`, `description`, `ip_address`) VALUES (?, ?, ?, ?)");
    foreach ($activities as $a) {
        $stmt->execute([$a['user_id'], $a['action'], $a['description'], $a['ip_address']]);
    }
    echo "  [OK]   Added " . count($activities) . " activity_log" . PHP_EOL;
} else {
    echo "  [SKIP] Already $existingAct activity_log (>= 5)" . PHP_EOL;
}

// ===========================================================================
// COMMIT
// ===========================================================================
$pdo->commit();

echo PHP_EOL;
echo "=== Summary ===" . PHP_EOL;

$summaryTables = ['users', 'master_kabupaten', 'master_kecamatan', 'master_desa', 'master_opt', 'laporan_hama', 'laporan_irigasi', 'notifications', 'activity_log', 'audit_log_wilayah', 'nomor_laporan_counter'];
foreach ($summaryTables as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "  $t: $c" . PHP_EOL;
}

echo PHP_EOL;
echo "Seed dummy selesai." . PHP_EOL;
exit(0);

} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "[ERROR] " . $e->getMessage() . PHP_EOL;
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    exit(1);
}
