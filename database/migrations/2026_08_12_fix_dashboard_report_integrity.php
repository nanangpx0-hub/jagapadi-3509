<?php

declare(strict_types=1);

/**
 * Persist the irrigation service area that is required by the web/mobile form.
 *
 * Run:
 *   php database/migrations/2026_08_12_fix_dashboard_report_integrity.php
 */

$rootPath = dirname(__DIR__, 2);
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $rootPath);
}

require_once $rootPath . '/app/core/Database.php';

foreach ([$rootPath . '/.env', $rootPath . '/.env.local'] as $envPath) {
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
        $value = trim($value, "\"'");
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

$db = Database::getInstance()->getConnection();
$columnStmt = $db->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$irrigationColumns = [
    'luas_layanan' => 'DECIMAL(12,2) NULL AFTER daerah_irigasi',
    'jenis_saluran' => "ENUM('Primer','Sekunder','Tersier') NULL AFTER luas_layanan",
    'status_perbaikan' => "ENUM('Normal','Selesai Diperbaiki','Dalam Perbaikan','Belum Ditangani') NULL AFTER debit_air",
    'aksi_dilakukan' => 'TEXT NULL AFTER status_perbaikan',
];
foreach ($irrigationColumns as $column => $definition) {
    $columnStmt->execute(['laporan_irigasi', $column]);
    if ((int) $columnStmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE laporan_irigasi ADD COLUMN `{$column}` {$definition}");
    }
}

$clearedHamaDraftNumbers = $db->exec(
    "UPDATE laporan_hama SET nomor_laporan = NULL WHERE status = 'Draf'"
);
$clearedIrigasiDraftNumbers = $db->exec(
    "UPDATE laporan_irigasi SET nomor_laporan = NULL WHERE status = 'Draf'"
);

$backfillReportNumbers = static function (string $table, string $prefix) use ($db): int {
    $stmt = $db->query(
        "SELECT id, COALESCE(created_at, CURRENT_TIMESTAMP) AS report_time
         FROM `{$table}`
         WHERE status <> 'Draf' AND (nomor_laporan IS NULL OR nomor_laporan = '')
         ORDER BY created_at, id"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $reportTime = new DateTimeImmutable((string) $row['report_time']);
        $datePart = $prefix === 'LH' ? $reportTime->format('Ym') : $reportTime->format('Ymd');
        $numberPrefix = "{$prefix}-{$datePart}-";
        $maxStmt = $db->prepare(
            "SELECT COALESCE(MAX(CAST(RIGHT(nomor_laporan, 4) AS UNSIGNED)), 0)
             FROM `{$table}` WHERE nomor_laporan LIKE ?"
        );
        $maxStmt->execute([$numberPrefix . '%']);
        $sequence = (int) $maxStmt->fetchColumn() + 1;

        $updateStmt = $db->prepare("UPDATE `{$table}` SET nomor_laporan = ? WHERE id = ?");
        $updateStmt->execute([$numberPrefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), $row['id']]);
    }

    return count($rows);
};

$backfilledHama = $backfillReportNumbers('laporan_hama', 'LH');
$backfilledIrigasi = $backfillReportNumbers('laporan_irigasi', 'LI');

echo "[OK] Kolom detail layanan dan perbaikan laporan irigasi tersedia.\n";
echo "[OK] Nomor draf dikosongkan: hama {$clearedHamaDraftNumbers}, irigasi {$clearedIrigasiDraftNumbers}.\n";
echo "[OK] Nomor laporan aktif dilengkapi: hama {$backfilledHama}, irigasi {$backfilledIrigasi}.\n";
