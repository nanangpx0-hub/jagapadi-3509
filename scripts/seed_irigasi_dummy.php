<?php
/**
 * Seeder data dummy Laporan Irigasi (runtime root/integrated).
 *
 * Pemakaian:
 *   php scripts/seed_irigasi_dummy.php            -> tambah 100 baris
 *   php scripts/seed_irigasi_dummy.php --count=50 -> tambah 50 baris
 *   php scripts/seed_irigasi_dummy.php --cleanup  -> hapus semua baris [DUMMY]
 *
 * Semua baris ditandai "[DUMMY]" pada catatan agar mudah dibersihkan
 * dan tidak tercampur data produksi.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';

$isCleanup = in_array('--cleanup', $argv, true);
$count = 100;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--count=')) {
        $count = max(1, min(1000, (int) substr($arg, 8)));
    }
}

$db = Database::getInstance()->getConnection();

if ($isCleanup) {
    $stmt = $db->prepare("DELETE FROM laporan_irigasi WHERE catatan LIKE '%[DUMMY]%'");
    $stmt->execute();
    echo "Cleanup: {$stmt->rowCount()} baris [DUMMY] dihapus.\n";
    exit(0);
}

// ---------- Data referensi ----------
$petugasIds = array_map('intval', $db->query(
    "SELECT id FROM users WHERE aktif = 1 AND role = 'petugas' ORDER BY id"
)->fetchAll(PDO::FETCH_COLUMN));
if ($petugasIds === []) {
    fwrite(STDERR, "Tidak ada user petugas aktif.\n");
    exit(1);
}

$desaRows = $db->query(
    'SELECT d.id AS desa_id, d.kecamatan_id, k.kabupaten_id
     FROM master_desa d
     JOIN master_kecamatan k ON k.id = d.kecamatan_id
     WHERE k.kabupaten_id = 1
     ORDER BY d.id'
)->fetchAll(PDO::FETCH_ASSOC);
if ($desaRows === []) {
    fwrite(STDERR, "Master desa kosong untuk kabupaten Jember.\n");
    exit(1);
}

$saluran = [
    ['Sungai Bedadung', 'DI Bedadung'],
    ['Sungai Mayang', 'DI Mayang'],
    ['Sungai Bondoyudo', 'DI Bondoyudo'],
    ['Sungai Tanggul', 'DI Tanggul'],
    ['Sungai Sampean', 'DI Sampean Baru'],
    ['Sungai Badean', 'DI Badean'],
    ['Saluran Sekunder Sukowiryo', 'DI Sukowiryo'],
    ['Saluran Primer Rembangan', 'DI Rembangan'],
];
$jenis = ['Primer', 'Sekunder', 'Tersier'];
$kondisi = ['Bagus', 'Sedang', 'Tidak Bagus', 'Rusak'];
$debit = ['Cukup', 'Kurang', 'Kering'];
$perbaikan = ['Normal', 'Selesai Diperbaiki', 'Dalam Perbaikan', 'Belum Ditangani'];
$aksiPerKondisi = [
    'Bagus' => 'Pemeliharaan rutin, tidak ada kerusakan signifikan.',
    'Sedang' => 'Pembersihan sedimen dan pengecekan rembesan dilakukan.',
    'Tidak Bagus' => 'Pelaporan kerusakan bidang; koordinasi perbaikan berjalan.',
    'Rusak' => 'Ditemukan kerusakan talud; usulan perbaikan diajukan.',
];

// PART2
// ---------- Distribusi status & tanggal ----------
$distribusi = ['Draf' => 15, 'Submitted' => 45, 'Diverifikasi' => 25, 'Ditolak' => 8, 'Diarsipkan' => 7];
$statusPool = [];
foreach ($distribusi as $status => $jumlah) {
    $statusPool = array_merge($statusPool, array_fill(0, $jumlah, $status));
}
while (count($statusPool) < $count) {
    $statusPool[] = 'Submitted';
}
$statusPool = array_slice($statusPool, 0, $count);
shuffle($statusPool);

$counterStmt = $db->prepare(
    "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor_laporan, '-', -1) AS UNSIGNED)), 0)
     FROM laporan_irigasi WHERE nomor_laporan LIKE ?"
);

$insert = $db->prepare(
    'INSERT INTO laporan_irigasi
        (nomor_laporan, user_id, tanggal, kabupaten_id, kecamatan_id, desa_id,
         nama_saluran, daerah_irigasi, luas_layanan, jenis_saluran,
         latitude, longitude, kondisi_fisik, debit_air, status_perbaikan,
         aksi_dilakukan, catatan, status, verified_by, verified_at, catatan_verifikasi, ip_pengirim)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$hariIni = new DateTimeImmutable('2026-08-24');
$tanggalList = [];
for ($i = 0; $i < $count; $i++) {
    $offset = $i % 180;
    $tanggalList[$i] = $hariIni->modify("-{$offset} days");
}

// ---------- Insert dalam transaksi ----------
$db->beginTransaction();
$inserted = 0;
try {
    for ($i = 0; $i < $count; $i++) {
        $tanggal = $tanggalList[$i];
        $tglStr = $tanggal->format('Y-m-d');
        $status = $statusPool[$i];
        $desa = $desaRows[array_rand($desaRows)];
        [$namaSaluran, $di] = $saluran[array_rand($saluran)];
        $kondisiVal = $kondisi[array_rand($kondisi)];

        // Nomor laporan hanya untuk status non-Draf (aturan bisnis JAGAPADI).
        $nomor = null;
        if ($status !== 'Draf') {
            $counterStmt->execute(['LI-' . $tanggal->format('Ymd') . '-%']);
            $seq = ((int) $counterStmt->fetchColumn()) + 1 + (int) $i;
            $nomor = sprintf('LI-%s-%04d', $tanggal->format('Ymd'), $seq);
        }

        $lat = -8.10 - (mt_rand() / mt_getrandmax()) * 0.35;
        $lng = 113.40 + (mt_rand() / mt_getrandmax()) * 0.50;

        $verifiedBy = null;
        $verifiedAt = null;
        $catatanVerifikasi = null;
        if ($status === 'Diverifikasi' || $status === 'Diarsipkan') {
            $verifiedBy = 1;
            $verifiedAt = $tglStr . ' 09:00:00';
            $catatanVerifikasi = '[Auto Approved] Data dummy seeder.';
        } elseif ($status === 'Ditolak') {
            $verifiedBy = 1;
            $verifiedAt = $tglStr . ' 10:00:00';
            $catatanVerifikasi = '[DUMMY] Foto bukti kurang jelas, mohon lampirkan foto terbaru.';
        }

        $insert->execute([
            $nomor,
            $petugasIds[$i % count($petugasIds)],
            $tglStr,
            (int) $desa['kabupaten_id'],
            (int) $desa['kecamatan_id'],
            (int) $desa['desa_id'],
            $namaSaluran,
            $di,
            mt_rand(10, 500),
            $jenis[array_rand($jenis)],
            number_format($lat, 7, '.', ''),
            number_format($lng, 7, '.', ''),
            $kondisiVal,
            $debit[array_rand($debit)],
            $perbaikan[array_rand($perbaikan)],
            $aksiPerKondisi[$kondisiVal],
            "[DUMMY] Data uji sebaran irigasi #" . ($inserted + 1),
            $status,
            $verifiedBy,
            $verifiedAt,
            $catatanVerifikasi,
            '127.0.0.1',
        ]);
        $inserted++;
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Gagal seeding: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Selesai: {$inserted} baris dummy ditambahkan ke laporan_irigasi.\n";
echo 'Total baris sekarang: '
    . $db->query('SELECT COUNT(*) FROM laporan_irigasi')->fetchColumn()
    . " (bersihkan dengan --cleanup)\n";
foreach ($db->query('SELECT status, COUNT(*) c FROM laporan_irigasi GROUP BY status ORDER BY status') as $r) {
    echo "  {$r['status']}: {$r['c']}\n";
}