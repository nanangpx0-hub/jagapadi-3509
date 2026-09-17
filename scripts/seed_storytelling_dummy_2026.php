<?php

declare(strict_types=1);

/**
 * Seeder dummy storytelling 2025-2026 (idempoten, aman diulang).
 *
 * Melengkapi indikator lag-1 (hama, irigasi, laporan lainnya, debit irigasi)
 * agar SELURUH cabang klasifikasi DataStoryService dapat dianalisis pada
 * periode produksi terverifikasi aktual (2025-09 s.d. 2026-08):
 *
 *   Target 2026-05 Kec. Ambulu (2), lag Apr-2026 -> 'Serangan OPT'
 *   Target 2026-07 Kec. Ambulu (2), lag Jun-2026 -> 'Kombinasi Cuaca & OPT'
 *   Target 2026-05 Kec. Bangsalsari (5), lag Apr-2026 -> 'Normal'
 *   Target 2026-08 Kec. Ambulu (2), lag Jul-2026 -> 'Cuaca Ekstrem'
 *   Target 2026-08 Kabupaten (0), lag Jul-2026 -> agregat regency-wide
 *
 * Plus sebaran hama 2025-10 s.d. 2026-08 untuk jendela 6-24 bulan
 * (trend, correlation, predictive, clustering, outlier).
 *
 * Hanya berjalan pada CLI, dengan APP_ENV=local/development/testing eksplisit
 * dan flag --confirm-local-seed (database lokal khusus data dummy).
 * Usage: APP_ENV=local php scripts/seed_storytelling_dummy_2026.php --confirm-local-seed
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$requestedEnv = strtolower(trim((string) getenv('APP_ENV')));
if (!in_array($requestedEnv, ['local', 'development', 'testing'], true)) {
    fwrite(STDERR, "[ABORT] Set APP_ENV=local/development/testing secara eksplisit sebelum bootstrap.\n");
    exit(1);
}

if (!in_array('--confirm-local-seed', $argv, true)) {
    fwrite(STDERR, "[ABORT] Wajib --confirm-local-seed pada database lokal khusus data dummy.\n");
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));

foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $envPath) {
    if (!is_file($envPath)) {
        continue;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

if (is_file(ROOT_PATH . '/config/config.php')) {
    require_once ROOT_PATH . '/config/config.php';
}
require_once ROOT_PATH . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

$petugasId = (int) ($db->query(
    "SELECT id FROM users WHERE role = 'petugas' ORDER BY id ASC LIMIT 1"
)->fetchColumn() ?: 0);
if ($petugasId <= 0) {
    fwrite(STDERR, "[ABORT] Tidak ada user petugas.\n");
    exit(1);
}

$kabId = (int) ($db->query('SELECT id FROM master_kabupaten ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
$optIds = $db->query('SELECT id FROM master_opt ORDER BY id ASC LIMIT 5')->fetchAll(PDO::FETCH_COLUMN);
if (count($optIds) < 3 || $kabId <= 0) {
    fwrite(STDERR, "[ABORT] master_opt/master_kabupaten belum tersedia.\n");
    exit(1);
}

$desaCache = [];
$desaFor = static function (int $kecamatanId) use ($db, &$desaCache): int {
    if (!isset($desaCache[$kecamatanId])) {
        $id = $db->prepare('SELECT id FROM master_desa WHERE kecamatan_id = ? ORDER BY id ASC LIMIT 1');
        $id->execute([$kecamatanId]);
        $desaCache[$kecamatanId] = (int) ($id->fetchColumn() ?: 0);
    }
    return $desaCache[$kecamatanId];
};

$jenisCuaca = (int) ($db->query(
    "SELECT id FROM master_jenis_laporan WHERE kode IN ('kerusakan_cuaca','bencana_cuaca') ORDER BY id ASC LIMIT 1"
)->fetchColumn() ?: 0);

echo "=========================================================\n";
echo "SEEDER DUMMY STORYTELLING 2025-2026 (idempoten)\n";
echo "=========================================================\n";

$hama = [
    // [kecamatan, tanggal, optIdx, keparahan, catatan]
    // Skenario Serangan OPT: lag Apr-2026 Kec 2 (4x Berat => skor 60 vs cuaca ~32)
    [2, '2026-04-05', 0, 'Berat', 'Dummy storytelling: wereng batang coklat blok A'],
    [2, '2026-04-10', 0, 'Berat', 'Dummy storytelling: populasi wereng memuncak'],
    [2, '2026-04-16', 2, 'Berat', 'Dummy storytelling: serangan tikus malai muda'],
    [2, '2026-04-20', 1, 'Berat', 'Dummy storytelling: sundep penggerek batang'],
    // Skenario Kombinasi: lag Jun-2026 Kec 2 (4x Berat => skor 60 vs cuaca ~57)
    [2, '2026-06-08', 0, 'Berat', 'Dummy storytelling: wereng + defisit air'],
    [2, '2026-06-14', 2, 'Berat', 'Dummy storytelling: tikus lahan kering'],
    [2, '2026-06-20', 1, 'Berat', 'Dummy storytelling: penggerek fase bunting'],
    [2, '2026-06-26', 3, 'Berat', 'Dummy storytelling: walang sangit isap bulir'],
    // Skenario Normal: lag Apr-2026 Kec 5 (1x Ringan => skor 2 vs cuaca ~18)
    [5, '2026-04-15', 0, 'Ringan', 'Dummy storytelling: OPT di bawah ambang ekonomi'],
    // Skenario Cuaca Ekstrem: lag Jul-2026 Kec 2 (1x Ringan => skor 2 vs cuaca ~61)
    [2, '2026-07-12', 2, 'Ringan', 'Dummy storytelling: OPT ringan saat kering'],
    // Sebaran deret waktu utk metode lanjutan (pest series tidak datar)
    [2, '2026-01-12', 1, 'Sedang', 'Dummy storytelling: sebaran tren Januari'],
    [2, '2026-02-15', 0, 'Sedang', 'Dummy storytelling: sebaran tren Februari'],
    [2, '2026-03-18', 2, 'Sedang', 'Dummy storytelling: sebaran tren Maret'],
    [2, '2026-05-11', 1, 'Sedang', 'Dummy storytelling: sebaran tren Mei'],
    [2, '2026-07-22', 0, 'Ringan', 'Dummy storytelling: sebaran tren Juli'],
    [2, '2026-08-05', 1, 'Sedang', 'Dummy storytelling: sebaran tren Agustus'],
    [5, '2026-01-20', 0, 'Ringan', 'Dummy storytelling: Bangsalsari Januari'],
    [5, '2026-03-09', 1, 'Sedang', 'Dummy storytelling: Bangsalsari Maret'],
    [5, '2026-06-17', 2, 'Sedang', 'Dummy storytelling: Bangsalsari Juni'],
    [5, '2026-07-08', 0, 'Ringan', 'Dummy storytelling: Bangsalsari Juli'],
    [2, '2025-10-14', 0, 'Sedang', 'Dummy storytelling: jendela panjang Okt-2025'],
    [2, '2025-11-19', 1, 'Ringan', 'Dummy storytelling: jendela panjang Nov-2025'],
    [2, '2025-12-10', 2, 'Sedang', 'Dummy storytelling: jendela panjang Des-2025'],
];

$irigasi = [
    // [kecamatan, tanggal, debit_air]
    [2, '2026-04-12', 'Kurang'],
    [2, '2026-06-10', 'Kering'],
    [2, '2026-06-22', 'Kering'],
    [2, '2026-07-09', 'Cukup'],
    [5, '2026-04-18', 'Cukup'],
];

$laporanLainnya = [];
if ($jenisCuaca > 0) {
    $laporanLainnya = [
        // [kecamatan, tanggal_kejadian, status, deskripsi]
        [2, '2026-04-20', 'submitted', 'Dummy storytelling: hujan tidak merata petak utara'],
        [2, '2026-06-25', 'verified', 'Dummy storytelling: retak tanah dampak kemarau'],
        [2, '2026-07-15', 'submitted', 'Dummy storytelling: embun kering pengisian bulir'],
    ];
}

$debitIrigasi = [
    // [tanggal, daerah_irigasi, debit_air] — global, tanpa kecamatan
    ['2026-03-05', 'DI Dummy Storytelling Utara', 200.00],
    ['2026-03-18', 'DI Dummy Storytelling Utara', 190.00],
    ['2026-05-07', 'DI Dummy Storytelling Selatan', 90.00],
    ['2026-05-21', 'DI Dummy Storytelling Selatan', 95.00],
];

$count = ['hama' => 0, 'irigasi' => 0, 'lainnya' => 0, 'debit' => 0, 'skip' => 0];
$db->beginTransaction();
try {
    $chkHama = $db->prepare('SELECT id FROM laporan_hama WHERE nomor_laporan = ?');
    $insHama = $db->prepare(
        "INSERT INTO laporan_hama (nomor_laporan, user_id, master_opt_id, tanggal,
            kabupaten_id, kecamatan_id, desa_id, lokasi, tingkat_keparahan,
            luas_serangan, persentase_serangan, luas_areal_diamati,
            luas_serangan_estimasi, metode_pengukuran, catatan,
            status, verified_by, verified_at, catatan_verifikasi, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 10.00, 1.00, 0.50, 'absolut', ?,
            'Diverifikasi', 1, NOW(), 'Dummy storytelling terverifikasi', NOW(), NOW())"
    );
    $seq = 1;
    foreach ($hama as [$kec, $tgl, $optIdx, $sev, $note]) {
        $nomor = sprintf('DST-%s-%03d', str_replace('-', '', $tgl), $seq++);
        $chkHama->execute([$nomor]);
        if ($chkHama->fetch()) {
            $count['skip']++;
            continue;
        }
        $insHama->execute([
            $nomor, $petugasId, $optIds[$optIdx] ?? $optIds[0], $tgl,
            $kabId, $kec, $desaFor($kec), 'Titik amatan dummy ' . $nomor, $sev, $note,
        ]);
        $count['hama']++;
    }

    $chkIrg = $db->prepare('SELECT id FROM laporan_irigasi WHERE nomor_laporan = ?');
    $insIrg = $db->prepare(
        "INSERT INTO laporan_irigasi (nomor_laporan, user_id, tanggal, kabupaten_id,
            kecamatan_id, desa_id, nama_saluran, debit_air, kondisi_fisik,
            status, verified_by, verified_at, catatan_verifikasi, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 'Saluran dummy storytelling', ?, 'Sedang',
            'Diverifikasi', 1, NOW(), 'Dummy storytelling terverifikasi', NOW(), NOW())"
    );
    $seq = 1;
    foreach ($irigasi as [$kec, $tgl, $debit]) {
        $nomor = sprintf('DSI-%s-%03d', str_replace('-', '', $tgl), $seq++);
        $chkIrg->execute([$nomor]);
        if ($chkIrg->fetch()) {
            $count['skip']++;
            continue;
        }
        $insIrg->execute([$nomor, $petugasId, $tgl, $kabId, $kec, $desaFor($kec), $debit]);
        $count['irigasi']++;
    }

    if ($laporanLainnya !== []) {
        $chkLain = $db->prepare('SELECT id FROM laporan_lainnya WHERE kode_laporan = ?');
        $insLain = $db->prepare(
            "INSERT INTO laporan_lainnya (user_id, jenis_id, kabupaten_id, kecamatan_id,
                desa_id, kode_laporan, tanggal_kejadian, data_json, deskripsi,
                status, verified_by, verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, '{}', ?, ?, 1, NOW(), NOW(), NOW())"
        );
        $seq = 1;
        foreach ($laporanLainnya as [$kec, $tgl, $st, $desc]) {
            $kode = sprintf('DSL-%s-%03d', str_replace('-', '', $tgl), $seq++);
            $chkLain->execute([$kode]);
            if ($chkLain->fetch()) {
                $count['skip']++;
                continue;
            }
            $insLain->execute([
                $petugasId, $jenisCuaca, $kabId, $kec, $desaFor($kec), $kode, $tgl, $desc, $st,
            ]);
            $count['lainnya']++;
        }
    }

    $chkDeb = $db->prepare('SELECT id FROM data_irigasi WHERE tanggal = ? AND daerah_irigasi = ?');
    $insDeb = $db->prepare(
        "INSERT INTO data_irigasi (tanggal, daerah_irigasi, debit_air, metode_data, keterangan)
         VALUES (?, ?, ?, 'manual', 'Dummy storytelling')"
    );
    foreach ($debitIrigasi as [$tgl, $daerah, $debit]) {
        $chkDeb->execute([$tgl, $daerah]);
        if ($chkDeb->fetch()) {
            $count['skip']++;
            continue;
        }
        $insDeb->execute([$tgl, $daerah, $debit]);
        $count['debit']++;
    }

    $db->commit();
    echo "OK hama={$count['hama']} irigasi={$count['irigasi']} lainnya={$count['lainnya']} "
        . "debit={$count['debit']} skip={$count['skip']}\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
