<?php
/**
 * Seeder 100 Data Dummy Laporan Hama JAGAPADI
 *
 * Menghasilkan 100 laporan hama realistis dengan wilayah Jember berjenjang,
 * OPT master terdaftar, koordinat valid, dan status laporan proporsional.
 */

declare(strict_types=1);

define('ROOT_PATH', 'C:/laragon/www/jagapadi-3509');

// Load environment
$envPaths = [ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'];
foreach ($envPaths as $envPath) {
    if (!file_exists($envPath)) continue;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            $eqPos = strpos($line, '=');
            if ($eqPos === false) continue;
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

require_once ROOT_PATH . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== MEMULAI SEEDING 100 DATA DUMMY LAPORAN HAMA ===\n";

// 1. Ambil data master pendukung
$kabupaten = $db->query("SELECT id, nama_kabupaten FROM master_kabupaten LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kabId = $kabupaten['id'] ?? 1;

$users = $db->query("SELECT id, username, nama_lengkap, role FROM users WHERE role = 'petugas' AND aktif = 1")->fetchAll(PDO::FETCH_ASSOC);
if (empty($users)) {
    $users = $db->query("SELECT id, username, nama_lengkap, role FROM users WHERE aktif = 1")->fetchAll(PDO::FETCH_ASSOC);
}
$admin = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$adminId = $admin['id'] ?? 1;

$opts = $db->query("SELECT id, nama_opt, jenis, etl_acuan, satuan_etl FROM master_opt WHERE aktif = 1")->fetchAll(PDO::FETCH_ASSOC);

$kecamatans = $db->query("SELECT id, nama_kecamatan FROM master_kecamatan WHERE kabupaten_id = $kabId")->fetchAll(PDO::FETCH_ASSOC);
$desas = $db->query("SELECT id, kecamatan_id, nama_desa FROM master_desa")->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan desa per kecamatan
$desasByKecamatan = [];
foreach ($desas as $d) {
    $desasByKecamatan[$d['kecamatan_id']][] = $d;
}

// Foto sample yang tersedia di sistem
$samplePhotos = [
    'public/uploads/laporan/161bcf8a5e39139edc1b67f2386d889c16ed43079eec04e2492d81cb86f91e90.png',
    'public/uploads/laporan/26ac66b2e81623b9fea21591988e3125.png',
    'public/uploads/laporan/2c053a331d120114b2b08eb104bbf4f7.png',
    'public/uploads/laporan/329ee220adf41430bb654b7fbca41ce4.png',
    'public/uploads/laporan/360a799bb1a8a4e5f84b97c7aa442883.png',
    'public/uploads/laporan/5344e75a4d431edb41dc8c137d8b2cc6.png',
    'public/uploads/laporan/561463ffaa9755362fb869205ca269d9.png',
    'public/uploads/laporan/7e2d7ccbfb9fa4ff3acf92f6463ef704.png',
    'public/uploads/laporan/81b2fcc2cd3b5e8d016c688ec8ae3ea9.png',
];

// Template catatan lapangan realistis
$catatanTemplates = [
    'Ditemukan koloni nimfa instar 2 dan 3 pada pangkal batang padi varietas Inpari 32, umur tanaman 35-45 HST. Petani dihimbau melakukan pengeringan berkala.',
    'Serangan sundep mulai tampak pada anakan produktif petak sawah bagian barat, persentase anakan mati sekitar %s%%. Telah direkomendasikan aplikasi agens hayati.',
    'Gejala bercak belah ketupat khas pada helai daun atas varietas Ciherang umur 50 HST. Kondisi kelembapan tinggi pasca hujan berurutan.',
    'Terdapat liang aktif dan gigitan pada malai padi fase bunting tua. Petani bersama Poktan telah melakukan gerakan gropyokan massal dan pemasangan umpan.',
    'Populasi serangga pengisap bulir mulai meningkat pada fase matang susu. Bau khas menyengat terdeteksi di sekitar pematang.',
    'Kerusakan daun mengering kecoklatan (hopperburn) seluas %s Ha. Perlu tindakan sanitasi dan penyemprotan agens hayati Beauveria bassiana.',
    'Pucuk daun menguning menggulung dengan larva terdeteksi di dalam pelepah. Dihimbau tidak menggunakan insektisida berspektrum luas secara berlebihan.',
    'Ditemukan gejala hawar daun bakteri / kresek mulai dari ujung daun merambat ke bawah pada hamparan sawah irigasi teknis.',
    'Serangan ulat grayak memakan helai daun tanaman jagung fase vegetatif V4-V6. Kondisi cuaca panas kering memicu lonjakan populasi.',
    'Pengamatan rutin mingguan POPT. Populasi hama masih di bawah ambang kendali ekonomi, monitoring berkala tetap dilanjutkan.',
];

// Template catatan verifikasi
$verifikasiDisetujuiTemplates = [
    'Disetujui. Laporan valid dan foto dokumentasi jelas. Rekomendasi pengendalian agens hayati telah dikoordinasikan dengan BPP setempat.',
    'Disetujui. Data telah diverifikasi dan masuk dalam rekapitulasi data perlindungan tanaman tingkat kabupaten.',
    'Disetujui. Tingkat serangan sesuai dengan hasil observasi pengawas benih dan brigade proteksi tanaman.',
    'Disetujui. Petugas lapangan agar memantau perkembangan populasi pasca perlakuan pengendalian.',
];

$verifikasiDitolakTemplates = [
    'Ditolak. Foto dokumentasi buram dan tidak memperlihatkan gejala serangan OPT dengan jelas. Harap perbarui foto dan kirim ulang.',
    'Ditolak. Titik koordinat GPS berada di luar batas wilayah desa yang dilaporkan. Mohon koreksi koordinat lokasi.',
    'Ditolak. Angka populasi dan luas serangan tidak proporsional dengan deskripsi lapangan. Harap verifikasi ulang data lapangan.',
];

// Ambil counter nomor laporan tertinggi saat ini
$stmt = $db->query("SELECT COALESCE(MAX(CAST(RIGHT(nomor_laporan, 4) AS UNSIGNED)), 0) FROM laporan_hama WHERE nomor_laporan LIKE 'LH-%'");
$currentSeq = (int) $stmt->fetchColumn();

// Siapkan Prepared Statement
$insertStmt = $db->prepare("
    INSERT INTO `laporan_hama` (
        `nomor_laporan`, `user_id`, `master_opt_id`, `usulan_opt_id`, `tanggal`,
        `kabupaten_id`, `kecamatan_id`, `desa_id`, `lokasi`, `alamat_lengkap`,
        `latitude`, `longitude`, `tingkat_keparahan`, `luas_serangan`,
        `persentase_serangan`, `luas_areal_diamati`, `luas_serangan_estimasi`,
        `populasi`, `metode_pengukuran`, `foto_url`, `video_url`, `catatan`,
        `status`, `verified_by`, `verified_at`, `catatan_verifikasi`,
        `ip_pengirim`, `created_at`, `updated_at`
    ) VALUES (
        :nomor_laporan, :user_id, :master_opt_id, :usulan_opt_id, :tanggal,
        :kabupaten_id, :kecamatan_id, :desa_id, :lokasi, :alamat_lengkap,
        :latitude, :longitude, :tingkat_keparahan, :luas_serangan,
        :persentase_serangan, :luas_areal_diamati, :luas_serangan_estimasi,
        :populasi, :metode_pengukuran, :foto_url, :video_url, :catatan,
        :status, :verified_by, :verified_at, :catatan_verifikasi,
        :ip_pengirim, :created_at, :updated_at
    )
");

$historyStmt = $db->prepare("
    INSERT INTO `laporan_status_history` (
        `laporan_id`, `old_status`, `new_status`, `changed_by`, `komentar`, `created_at`
    ) VALUES (
        :laporan_id, :old_status, :new_status, :changed_by, :komentar, :created_at
    )
");

$db->beginTransaction();

$createdCount = 0;
$statusSummary = ['Submitted' => 0, 'Diverifikasi' => 0, 'Ditolak' => 0, 'Draf' => 0, 'Diarsipkan' => 0];

for ($i = 1; $i <= 100; $i++) {
    // 1. Pilih User Petugas
    $user = $users[array_rand($users)];
    $userId = (int) $user['id'];

    // 2. Pilih OPT
    $opt = $opts[array_rand($opts)];
    $optId = (int) $opt['id'];

    // 3. Pilih Kecamatan & Desa
    $kec = $kecamatans[array_rand($kecamatans)];
    $kecId = (int) $kec['id'];
    $desaList = $desasByKecamatan[$kecId] ?? [];
    if (empty($desaList)) {
        $desa = $desas[array_rand($desas)];
        $desaId = (int) $desa['id'];
        $kecId = (int) $desa['kecamatan_id'];
    } else {
        $desa = $desaList[array_rand($desaList)];
        $desaId = (int) $desa['id'];
    }

    // 4. Tanggal antara Januari 2026 s.d. Agustus 2026
    $month = rand(1, 8);
    $maxDay = ($month == 8) ? 24 : 28;
    $day = rand(1, $maxDay);
    $reportDate = sprintf('2026-%02d-%02d', $month, $day);
    $createdTime = sprintf('2026-%02d-%02d %02d:%02d:%02d', $month, $day, rand(7, 16), rand(0, 59), rand(0, 59));

    // 5. Geotagging presisi di area Jember
    // Jember lat: -8.05 s.d. -8.45, lon: 113.40 s.d. 113.85
    $lat = round(-8.05 - (mt_rand(0, 40000) / 100000), 7);
    $lon = round(113.40 + (mt_rand(0, 45000) / 100000), 7);

    // 6. Alamat & Lokasi
    $blokNames = ['Blok Krajan', 'Blok Sawah Kidul', 'Blok Sawah Lor', 'Blok Kedawung', 'Blok Bedadung', 'Blok Gumuk', 'Blok Sumber Salak', 'Blok Tempuran', 'Blok Kebonrejo'];
    $poktanNames = ['Tani Jaya', 'Tani Makmur', 'Sumber Rejeki', 'Karya Tani', 'Subur Makmur', 'Tani Mulyo', 'Sri Rejeki', 'Rukun Tani'];
    $blok = $blokNames[array_rand($blokNames)];
    $poktan = $poktanNames[array_rand($poktanNames)];
    $rt = rand(1, 8);
    $rw = rand(1, 5);
    $lokasi = "Hamparan Poktan {$poktan} {$blok}";
    $alamatLengkap = "{$blok}, RT 0{$rt} / RW 0{$rw}, Poktan {$poktan}, Desa {$desa['nama_desa']}, Kec. {$kec['nama_kecamatan']}";

    // 7. Metode Pengukuran, Keparahan, dan Metrik Serangan
    $metode = (rand(1, 100) <= 70) ? 'absolut' : 'persentase';
    $severityRoll = rand(1, 100);
    if ($severityRoll <= 45) {
        $keparahan = 'Ringan';
        $persentase = round(rand(500, 2400) / 100, 2); // 5 - 24%
        $populasi = round(rand(200, 800) / 100, 2);
        $luasSerangan = round(rand(20, 150) / 100, 2); // 0.20 - 1.50 Ha
    } elseif ($severityRoll <= 80) {
        $keparahan = 'Sedang';
        $persentase = round(rand(2500, 4900) / 100, 2); // 25 - 49%
        $populasi = round(rand(800, 2500) / 100, 2);
        $luasSerangan = round(rand(100, 450) / 100, 2); // 1.00 - 4.50 Ha
    } else {
        $keparahan = 'Berat';
        $persentase = round(rand(5000, 8500) / 100, 2); // 50 - 85%
        $populasi = round(rand(2500, 8000) / 100, 2);
        $luasSerangan = round(rand(250, 1200) / 100, 2); // 2.50 - 12.00 Ha
    }

    $luasDiamati = null;
    $luasEstimasi = null;
    if ($metode === 'persentase') {
        $luasDiamati = round(rand(200, 1500) / 100, 2); // 2 - 15 Ha
        $luasEstimasi = round($luasDiamati * $persentase / 100, 2);
    }

    // 8. Catatan
    $templateCatatan = $catatanTemplates[array_rand($catatanTemplates)];
    $catatan = sprintf($templateCatatan, $persentase, $luasSerangan);

    // 9. Foto
    $fotoUrl = $samplePhotos[array_rand($samplePhotos)];

    // 10. Status & Verifikasi
    // Proporsi: 45% Submitted, 40% Diverifikasi, 8% Ditolak, 5% Draf, 2% Diarsipkan
    $statusRoll = rand(1, 100);
    $status = 'Submitted';
    $verifiedBy = null;
    $verifiedAt = null;
    $catatanVerifikasi = null;
    $nomorLaporan = null;

    if ($statusRoll <= 5) {
        $status = 'Draf';
        $nomorLaporan = null;
        $fotoUrl = (rand(1, 10) > 3) ? $fotoUrl : null;
    } else {
        // Generate nomor laporan
        $currentSeq++;
        $yearMonth = sprintf('2026%02d', $month);
        $nomorLaporan = sprintf('LH-%s-%04d', $yearMonth, $currentSeq);

        if ($statusRoll <= 50) {
            $status = 'Submitted';
        } elseif ($statusRoll <= 90) {
            $status = 'Diverifikasi';
            $verifiedBy = $adminId;
            $verifiedAt = date('Y-m-d H:i:s', strtotime($createdTime . ' +' . rand(2, 48) . ' hours'));
            $catatanVerifikasi = $verifikasiDisetujuiTemplates[array_rand($verifikasiDisetujuiTemplates)];
        } elseif ($statusRoll <= 98) {
            $status = 'Ditolak';
            $verifiedBy = $adminId;
            $verifiedAt = date('Y-m-d H:i:s', strtotime($createdTime . ' +' . rand(2, 48) . ' hours'));
            $catatanVerifikasi = $verifikasiDitolakTemplates[array_rand($verifikasiDitolakTemplates)];
        } else {
            $status = 'Diarsipkan';
            $verifiedBy = $adminId;
            $verifiedAt = date('Y-m-d H:i:s', strtotime($createdTime . ' +' . rand(2, 48) . ' hours'));
            $catatanVerifikasi = 'Laporan telah diverifikasi dan diarsipkan setelah masa aktif penanganan selesai.';
        }
    }

    $ipPengirim = '127.0.0.1';

    // Insert laporan_hama
    $insertStmt->execute([
        ':nomor_laporan' => $nomorLaporan,
        ':user_id' => $userId,
        ':master_opt_id' => $optId,
        ':usulan_opt_id' => null,
        ':tanggal' => $reportDate,
        ':kabupaten_id' => $kabId,
        ':kecamatan_id' => $kecId,
        ':desa_id' => $desaId,
        ':lokasi' => $lokasi,
        ':alamat_lengkap' => $alamatLengkap,
        ':latitude' => $lat,
        ':longitude' => $lon,
        ':tingkat_keparahan' => $keparahan,
        ':luas_serangan' => $luasSerangan,
        ':persentase_serangan' => $persentase,
        ':luas_areal_diamati' => $luasDiamati,
        ':luas_serangan_estimasi' => $luasEstimasi,
        ':populasi' => $populasi,
        ':metode_pengukuran' => $metode,
        ':foto_url' => $fotoUrl,
        ':video_url' => null,
        ':catatan' => $catatan,
        ':status' => $status,
        ':verified_by' => $verifiedBy,
        ':verified_at' => $verifiedAt,
        ':catatan_verifikasi' => $catatanVerifikasi,
        ':ip_pengirim' => $ipPengirim,
        ':created_at' => $createdTime,
        ':updated_at' => $verifiedAt ?: $createdTime,
    ]);

    $laporanId = (int) $db->lastInsertId();

    // Insert laporan_status_history
    if ($status === 'Draf') {
        $historyStmt->execute([
            ':laporan_id' => $laporanId,
            ':old_status' => null,
            ':new_status' => 'Draf',
            ':changed_by' => $userId,
            ':komentar' => 'Draf laporan dibuat oleh petugas',
            ':created_at' => $createdTime,
        ]);
    } else {
        $historyStmt->execute([
            ':laporan_id' => $laporanId,
            ':old_status' => null,
            ':new_status' => 'Submitted',
            ':changed_by' => $userId,
            ':komentar' => 'Laporan dikirim oleh petugas',
            ':created_at' => $createdTime,
        ]);

        if (in_array($status, ['Diverifikasi', 'Ditolak', 'Diarsipkan'], true)) {
            $historyStmt->execute([
                ':laporan_id' => $laporanId,
                ':old_status' => 'Submitted',
                ':new_status' => $status,
                ':changed_by' => $adminId,
                ':komentar' => $catatanVerifikasi,
                ':created_at' => $verifiedAt ?: $createdTime,
            ]);
        }
    }

    $createdCount++;
    $statusSummary[$status]++;
}

$db->commit();

echo "\n=== BERHASIL MENAMBAHKAN {$createdCount} DATA DUMMY LAPORAN HAMA ===\n";
echo "Ringkasan status data dummy yang dibuat:\n";
foreach ($statusSummary as $st => $cnt) {
    echo "- Status {$st}: {$cnt} laporan\n";
}

$totalInDb = $db->query("SELECT COUNT(*) FROM laporan_hama")->fetchColumn();
echo "\nTotal seluruh data laporan_hama di database sekarang: {$totalInDb} laporan\n";
