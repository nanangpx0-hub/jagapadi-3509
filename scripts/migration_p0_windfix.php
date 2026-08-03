<?php
/**
 * Migration P0 - Wind Speed Critical Fixes
 * 1. Hapus duplikat (tanggal+lokasi) data wind speed
 * 2. Tambah UNIQUE CONSTRAINT uk_tgl_lokasi (tanggal, lokasi)
 * 3. Tambah COMPOSITE INDEX idx_tanggal_lokasi (tanggal, lokasi) untuk query analytics
 * 4. Tambah INDEX idx_tahun_lokasi untuk query by tahun
 * 5. Update kode_wilayah per kecamatan (sesuai master_kecamatan.kode BPS)
 */
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';
$db = Database::getInstance()->getConnection();

echo "=============================================\n";
echo "MIGRASI P0 - FIX KRITIS KECEPATAN ANGIN\n";
echo "=============================================\n\n";

echo "--- LANGKAH 1: Hapus Duplikat (tanggal+lokasi) ---\n";
$db->beginTransaction();
try {
    // Temukan grup duplikat & simpan id terkecil sebagai yang dipertahankan
    $dupGroups = $db->query("
        SELECT tanggal, lokasi, MIN(id) as keep_id, COUNT(*) as cnt
        FROM kecepatan_angin
        GROUP BY tanggal, lokasi
        HAVING cnt > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $totalDupGroups = count($dupGroups);
    $totalDeleted = 0;
    
    echo "  Ditemukan {$totalDupGroups} grup duplikat\n";
    
    foreach ($dupGroups as $g) {
        $stmt = $db->prepare("DELETE FROM kecepatan_angin WHERE tanggal = ? AND lokasi = ? AND id != ?");
        $stmt->execute([$g['tanggal'], $g['lokasi'], $g['keep_id']]);
        $deleted = $stmt->rowCount();
        $totalDeleted += $deleted;
        echo "  tanggal {$g['tanggal']} @ {$g['lokasi']}: hapus {$deleted} data (keep id {$g['keep_id']})\n";
    }
    
    $db->commit();
    echo "  ✅ Selesai. Total data duplikat dihapus: {$totalDeleted}\n\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "  ❌ Gagal hapus duplikat: " . $e->getMessage() . "\n";
    exit(1);
}

echo "--- LANGKAH 2: Tambah UNIQUE CONSTRAINT (tanggal, lokasi) ---\n";
try {
    $db->exec("ALTER TABLE kecepatan_angin 
        ADD CONSTRAINT uk_tgl_lokasi UNIQUE (tanggal, lokasi)");
    echo "  ✅ UNIQUE CONSTRAINT `uk_tgl_lokasi` berhasil dibuat\n\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "  ⚠️  Masih ada duplikat: " . $e->getMessage() . "\n";
    } elseif (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "  ℹ️  Constraint sudah ada (skip)\n";
    } else {
        echo "  ❌ Gagal buat constraint: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "--- LANGKAH 3: Tambah COMPOSITE INDEXES utk analytics ---\n";
$indexes = [
    'idx_tanggal_lokasi' => 'CREATE INDEX idx_tanggal_lokasi ON kecepatan_angin(tanggal, lokasi)',
    'idx_tahun_lokasi' => 'ALTER TABLE kecepatan_angin ADD COLUMN tahun INT AS (YEAR(tanggal)) STORED, ADD INDEX idx_tahun_lokasi(tahun, lokasi)',
];
foreach ($indexes as $name => $sql) {
    try {
        $db->exec($sql);
        echo "  ✅ INDEX `{$name}` berhasil dibuat\n";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate key') !== false || strpos($msg, 'Duplicate column') !== false) {
            echo "  ℹ️  INDEX / COLUMN `{$name}` sudah ada (skip)\n";
        } else {
            echo "  ⚠️  INDEX `{$name}` gagal (non-critical): {$msg}\n";
        }
    }
}
echo "\n";

echo "--- LANGKAH 4: Update kode_wilayah per kecamatan (BPS kode) ---\n";
try {
    // Load mapping nama_kecamatan -> kode dari master_kecamatan
    $kec = $db->query("SELECT id, nama_kecamatan, kode FROM master_kecamatan")->fetchAll(PDO::FETCH_ASSOC);
    $updates = 0;
    foreach ($kec as $k) {
        // lokasi di kecepatan_angin format: "NamaKec, Jember"
        $stmt = $db->prepare("UPDATE kecepatan_angin 
            SET kode_wilayah = ? 
            WHERE lokasi LIKE CONCAT(?, ', Jember%')
              AND (kode_wilayah != ? OR kode_wilayah IS NULL)");
        $stmt->execute([$k['kode'], $k['nama_kecamatan'], $k['kode']]);
        $cnt = $stmt->rowCount();
        if ($cnt > 0) {
            $updates += $cnt;
            echo "  Kec {$k['nama_kecamatan']} (kode {$k['kode']}): {$cnt} record diperbarui\n";
        }
    }
    if ($updates === 0) {
        echo "  ℹ️  Kode wilayah sudah sesuai (tidak ada perubahan)\n";
    } else {
        echo "  ✅ Total {$updates} record kode_wilayah diperbarui\n";
    }
} catch (Exception $e) {
    echo "  ⚠️  Gagal update kode wilayah: " . $e->getMessage() . "\n";
}
echo "\n";

echo "--- LANGKAH 5: Final Check ---\n";
$final = $db->query("
    SELECT
      COUNT(*) as total_records,
      COUNT(DISTINCT tanggal) as unique_days,
      COUNT(DISTINCT lokasi) as unique_lokasi,
      COUNT(DISTINCT kode_wilayah) as unique_kode_wilayah
    FROM kecepatan_angin
")->fetch(PDO::FETCH_ASSOC);
echo "  Total records: " . number_format($final['total_records']) . "\n";
echo "  Unique days: " . $final['unique_days'] . "\n";
echo "  Unique lokasi: " . $final['unique_lokasi'] . "\n";
echo "  Unique kode_wilayah: " . $final['unique_kode_wilayah'] . "\n";

// Cek sisa duplikat
$cekDup = $db->query("SELECT COUNT(*) FROM (
    SELECT tanggal, lokasi FROM kecepatan_angin GROUP BY tanggal, lokasi HAVING COUNT(*) > 1
) x")->fetchColumn();
echo "  Grup duplikat tersisa: {$cekDup}\n";

echo "\n=== MIGRASI P0 SELESAI ===\n";
