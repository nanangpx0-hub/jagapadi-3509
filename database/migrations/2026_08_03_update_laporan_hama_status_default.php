<?php

require_once __DIR__ . '/../../config/database.php';

$rollback = in_array('--rollback', $argv ?? [], true);
$db = Database::getInstance()->getConnection();

function statusColumnType(PDO $db): string {
    $stmt = $db->query("SHOW COLUMNS FROM laporan_hama LIKE 'status'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    return strtolower($column['Type'] ?? '');
}

if ($rollback) {
    // Rollback ke default Draf
    $db->exec("ALTER TABLE laporan_hama MODIFY status ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan') DEFAULT 'Draf'");
    echo "Rollback selesai. Status default laporan_hama dikembalikan menjadi Draf.\n";
    exit(0);
}

// Update existing records with 'Draf' status to 'Submitted' if any exist
$db->exec("UPDATE laporan_hama SET status = 'Submitted' WHERE status = 'Draf'");

// Alter column status to default 'Submitted'
$db->exec("ALTER TABLE laporan_hama MODIFY status ENUM('Submitted','Diverifikasi','Ditolak','Diarsipkan') DEFAULT 'Submitted'");
echo "Migration selesai. Status default laporan_hama diubah menjadi Submitted (alur Draf dihapus).\n";
