<?php

require_once __DIR__ . '/../../config/database.php';

$rollback = in_array('--rollback', $argv ?? [], true);
$db = Database::getInstance()->getConnection();

function statusColumnType(PDO $db): string {
    $stmt = $db->query("SHOW COLUMNS FROM laporan_hama LIKE 'status'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    return strtolower($column['Type'] ?? '');
}

$currentType = statusColumnType($db);

if ($rollback) {
    if (strpos($currentType, 'diarsipkan') === false) {
        echo "Status Diarsipkan belum ada. Tidak ada perubahan.\n";
        exit(0);
    }

    $db->exec("UPDATE laporan_hama SET status = 'Submitted' WHERE status = 'Diarsipkan'");
    $db->exec("ALTER TABLE laporan_hama MODIFY status ENUM('Draf','Submitted','Diverifikasi','Ditolak') DEFAULT 'Draf'");
    echo "Rollback selesai. Status Diarsipkan dikembalikan menjadi Submitted.\n";
    exit(0);
}

if (strpos($currentType, 'diarsipkan') !== false) {
    echo "Status Diarsipkan sudah tersedia. Tidak ada perubahan.\n";
    exit(0);
}

$db->exec("ALTER TABLE laporan_hama MODIFY status ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan') DEFAULT 'Draf'");
echo "Migration selesai. Status Diarsipkan tersedia untuk laporan_hama.\n";
