<?php
/**
 * Seeder data pendukung (laporan_hama & curah_hujan) untuk validasi logika Data Storytelling.
 * Memastikan semua cabang analisis kausalitas (Cuaca Ekstrem, Serangan OPT, Kombinasi, Normal)
 * dapat diuji dan diverifikasi secara empiris.
 * 
 * Usage: php scripts/seed_dummy_storytelling_supporting_data.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

echo "=================================================================\n";
echo "PENYIAPAN DATA PENDUKUNG DUMMY DATA STORYTELLING (JAGAPADI)\n";
echo "=================================================================\n";

// Pastikan ada user petugas aktif
$stmtUser = $db->query("SELECT id FROM users WHERE role = 'petugas' LIMIT 1");
$petugasId = (int) ($stmtUser->fetchColumn() ?: 2);


// Pastikan ada master_opt
$optList = $db->query("SELECT id, nama_opt FROM master_opt WHERE id IN (218, 219, 220, 221, 222)")->fetchAll(PDO::FETCH_KEY_PAIR);
if (empty($optList)) {
    // Fallback jika ID berbeda
    $optList = $db->query("SELECT id, nama_opt FROM master_opt ORDER BY id ASC LIMIT 5")->fetchAll(PDO::FETCH_KEY_PAIR);
}
$optIds = array_keys($optList);

$db->beginTransaction();

try {
    $stmtCheckHama = $db->prepare(
        "SELECT id FROM laporan_hama WHERE nomor_laporan = ?"
    );

    $stmtInsertHama = $db->prepare(
        "INSERT INTO laporan_hama (
            nomor_laporan, user_id, master_opt_id, tanggal, kabupaten_id, kecamatan_id, desa_id,
            lokasi, alamat_lengkap, tingkat_keparahan, luas_serangan, persentase_serangan,
            luas_areal_diamati, luas_serangan_estimasi, metode_pengukuran, catatan,
            status, verified_by, verified_at, catatan_verifikasi, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, 1, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, 'absolut', ?,
            'Diverifikasi', 1, NOW(), 'Terverifikasi otomatis untuk validasi storytelling', NOW(), NOW()
        )"
    );

    $laporanCount = 0;

    /**
     * DEFINISI DATA DUMMY SPESIFIK SESUAI TEST CASE MATRIX
     */
    $testScenarios = [
        // Skenario 1: Serangan OPT (Target Mei 2024 di Kec 2 Ambulu -> Lag April 2024)
        // Skor Hama harus >= 50 dan mengalahkan skor cuaca (cuaca Ambulu April = 13)
        // 5 Laporan Berat: 5 * 15 = 75 skor hama
        [
            'scenario' => 'Serangan OPT (Ambulu, Lag: Apr 2024 -> Target: Mei 2024)',
            'records' => [
                ['kecamatan_id' => 2, 'desa_id' => 3, 'tanggal' => '2024-04-05', 'opt_id' => $optIds[0] ?? 218, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Wabah wereng batang coklat di petak amatan blok A'],
                ['kecamatan_id' => 2, 'desa_id' => 3, 'tanggal' => '2024-04-10', 'opt_id' => $optIds[0] ?? 218, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Populasi wereng meningkat pesat di rumpun bawah'],
                ['kecamatan_id' => 2, 'desa_id' => 4, 'tanggal' => '2024-04-16', 'opt_id' => $optIds[2] ?? 220, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Serangan tikus sawah memotong malai muda'],
                ['kecamatan_id' => 2, 'desa_id' => 4, 'tanggal' => '2024-04-20', 'opt_id' => $optIds[1] ?? 219, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Gejala sundep meluas akibat penggerek batang'],
                ['kecamatan_id' => 2, 'desa_id' => 24, 'tanggal' => '2024-04-25', 'opt_id' => $optIds[0] ?? 218, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Hopperburn meluas pada petak terserang wereng'],
            ]
        ],

        // Skenario 2: Kombinasi Cuaca & OPT (Target Juli 2024 di Kec 3 Arjasa -> Lag Juni 2024)
        // Curah hujan Arjasa Juni 2024 = 63.67 mm -> Skor Cuaca = 60
        // 4 Laporan Berat: 4 * 15 = 60 skor hama -> abs(60 - 60) = 0 <= 15 -> Kombinasi!
        [
            'scenario' => 'Kombinasi Cuaca & OPT (Arjasa, Lag: Jun 2024 -> Target: Jul 2024)',
            'records' => [
                ['kecamatan_id' => 3, 'desa_id' => 6, 'tanggal' => '2024-06-08', 'opt_id' => $optIds[0] ?? 218, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Kombinasi cuaca kering dan wereng coklat'],
                ['kecamatan_id' => 3, 'desa_id' => 6, 'tanggal' => '2024-06-14', 'opt_id' => $optIds[2] ?? 220, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Tikus menyerang pada petak yang kekurangan air'],
                ['kecamatan_id' => 3, 'desa_id' => 6, 'tanggal' => '2024-06-20', 'opt_id' => $optIds[1] ?? 219, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Penggerek batang pada fase bunting'],
                ['kecamatan_id' => 3, 'desa_id' => 6, 'tanggal' => '2024-06-26', 'opt_id' => $optIds[3] ?? 221, 'keparahan' => 'Berat', 'luas' => 0.00, 'catatan' => 'Walang sangit mengisap bulir'],
            ]
        ],

        // Skenario 3: Kondisi Normal (Target April 2024 di Kec 5 Bangsalsari -> Lag Maret 2024)
        // Curah hujan Bangsalsari Maret 2024 = 167.32 mm -> Skor Cuaca = 8 (ideal)
        // 1 Laporan Ringan: skor hama = 2 -> Normal
        [
            'scenario' => 'Kondisi Normal (Bangsalsari, Lag: Mar 2024 -> Target: Apr 2024)',
            'records' => [
                ['kecamatan_id' => 5, 'desa_id' => 1, 'tanggal' => '2024-03-15', 'opt_id' => $optIds[0] ?? 218, 'keparahan' => 'Ringan', 'luas' => 0.00, 'catatan' => 'Populasi OPT di bawah ambang ekonomi, aman'],
            ]
        ],

        // Skenario 4: Cuaca Ekstrem Kekeringan (Target Sept 2024 di Kec 1 Ajung -> Lag Ags 2024)
        // Curah hujan Ajung Ags 2024 = 1.44 mm -> Skor Cuaca = 100
        // 1 Laporan Ringan: skor hama = 2 -> Cuaca Ekstrem mendominasi!
        [
            'scenario' => 'Cuaca Ekstrem Kekeringan (Ajung, Lag: Ags 2024 -> Target: Sep 2024)',
            'records' => [
                ['kecamatan_id' => 1, 'desa_id' => 1, 'tanggal' => '2024-08-12', 'opt_id' => $optIds[2] ?? 220, 'keparahan' => 'Ringan', 'luas' => 0.00, 'catatan' => 'Kekeringan parah menyebabkan tanah retak'],
            ]
        ],

        // Tambahan: Sebaran data tren 6 bulan di beberapa kecamatan agar chart komparatif tidak datar
        [
            'scenario' => 'Sebaran Tren 6 Bulan (Ambulu & Ajung Jan-Jun 2024)',
            'records' => [
                ['kecamatan_id' => 2, 'desa_id' => 3, 'tanggal' => '2024-01-15', 'opt_id' => $optIds[1] ?? 219, 'keparahan' => 'Sedang', 'luas' => 0.00, 'catatan' => 'Penggerek batang awal tahun'],
                ['kecamatan_id' => 2, 'desa_id' => 3, 'tanggal' => '2024-02-18', 'opt_id' => $optIds[0] ?? 218, 'keparahan' => 'Sedang', 'luas' => 0.00, 'catatan' => 'Wereng coklat pasca hujan'],
                ['kecamatan_id' => 2, 'desa_id' => 3, 'tanggal' => '2024-03-22', 'opt_id' => $optIds[2] ?? 220, 'keparahan' => 'Sedang', 'luas' => 0.00, 'catatan' => 'Tikus sawah'],
                ['kecamatan_id' => 1, 'desa_id' => 2, 'tanggal' => '2024-05-10', 'opt_id' => $optIds[3] ?? 221, 'keparahan' => 'Sedang', 'luas' => 0.00, 'catatan' => 'Walang sangit'],
                ['kecamatan_id' => 1, 'desa_id' => 2, 'tanggal' => '2024-06-15', 'opt_id' => $optIds[0] ?? 218, 'keparahan' => 'Sedang', 'luas' => 0.00, 'catatan' => 'Wereng coklat'],
                ['kecamatan_id' => 1, 'desa_id' => 2, 'tanggal' => '2024-07-20', 'opt_id' => $optIds[2] ?? 220, 'keparahan' => 'Ringan', 'luas' => 0.00, 'catatan' => 'Tikus'],
            ]
        ]
    ];

    $seq = 100;
    foreach ($testScenarios as $sc) {
        echo "Memproses: {$sc['scenario']}...\n";
        foreach ($sc['records'] as $rec) {
            $nomorLaporan = sprintf('OPT-%s-%04d', str_replace('-', '', substr($rec['tanggal'], 0, 7)), ++$seq);

            $stmtCheckHama->execute([$nomorLaporan]);
            if ($stmtCheckHama->fetch()) {
                continue;
            }

            $stmtInsertHama->execute([
                $nomorLaporan,
                $petugasId,
                $rec['opt_id'],
                $rec['tanggal'],
                $rec['kecamatan_id'],
                $rec['desa_id'],
                'Titik Amatan Jagapadi ' . $nomorLaporan,
                'Blok Pertanian Terpadu Jember ' . $nomorLaporan,
                $rec['keparahan'],
                $rec['luas'],
                10.0,
                1.0,
                0.5,
                $rec['catatan']
            ]);
            $laporanCount++;
        }
    }

    $db->commit();
    echo "\n✓ Berhasil menyisipkan {$laporanCount} data pendukung dummy laporan hama terverifikasi!\n";
    echo "=================================================================\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
