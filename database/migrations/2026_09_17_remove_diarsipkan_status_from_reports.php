<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/core/Database.php';

$db = Database::getInstance()->getConnection();

echo "[MIGRATION] Menghapus status 'Diarsipkan' dari seluruh modul laporan...\n";

$tables = [
    'laporan_hama' => 'Draf',
    'laporan_irigasi' => 'Draf',
    'laporan_pupuk' => 'Draf',
    'laporan_panen' => 'Draf',
    'laporan_cuaca' => 'Draf',
    'laporan_alat_sarana' => 'Draf',
];

foreach ($tables as $table => $default) {
    try {
        // Cek apakah tabel ada
        $check = $db->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if (!$check) {
            echo "  - Tabel {$table} tidak ditemukan, lewati.\n";
            continue;
        }

        // Migrasikan record berstatus 'Diarsipkan' menjadi 'Diverifikasi' bila ada
        $stmt = $db->prepare("UPDATE {$table} SET status = 'Diverifikasi' WHERE status = 'Diarsipkan'");
        $stmt->execute();
        $migratedRows = $stmt->rowCount();
        if ($migratedRows > 0) {
            echo "  - {$table}: {$migratedRows} record 'Diarsipkan' dialihkan ke 'Diverifikasi'.\n";
        }

        // Modifikasi kolom enum status tanpa 'Diarsipkan'
        $alterSql = "ALTER TABLE {$table} MODIFY status ENUM('Draf','Submitted','Diverifikasi','Ditolak') NOT NULL DEFAULT '{$default}'";
        $db->exec($alterSql);
        echo "  - {$table}: Enum status berhasil diperbarui ke ('Draf','Submitted','Diverifikasi','Ditolak').\n";
    } catch (\Throwable $e) {
        echo "  [ERROR] {$table}: " . $e->getMessage() . "\n";
    }
}

// Periksa tabel laporan_lainnya
try {
    $check = $db->query("SHOW TABLES LIKE 'laporan_lainnya'")->fetch();
    if ($check) {
        $db->exec("UPDATE laporan_lainnya SET status = 'verified' WHERE status = 'archived'");
        $db->exec("ALTER TABLE laporan_lainnya MODIFY status ENUM('draft','submitted','verified','rejected') NOT NULL DEFAULT 'draft'");
        echo "  - laporan_lainnya: Enum status berhasil diperbarui ke ('draft','submitted','verified','rejected').\n";
    }
} catch (\Throwable $e) {
    echo "  [ERROR] laporan_lainnya: " . $e->getMessage() . "\n";
}

// Catat ke schema_migrations jika tabel tersedia
try {
    $migName = '2026_09_17_remove_diarsipkan_status_from_reports.php';
    $checkMig = $db->prepare("SELECT id FROM schema_migrations WHERE migration = ?");
    $checkMig->execute([$migName]);
    if (!$checkMig->fetch()) {
        $ins = $db->prepare("INSERT INTO schema_migrations (migration, executed_at) VALUES (?, NOW())");
        $ins->execute([$migName]);
        echo "  - Tercatat di schema_migrations: {$migName}\n";
    }
} catch (\Throwable $e) {
    // Abaikan jika struktur schema_migrations berbeda
}

echo "[SUCCESS] Migration penghapusan status 'Diarsipkan' selesai dengan sukses.\n";
