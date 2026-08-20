<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class NomorLaporanGenerator
{
    private const ALLOWED_PREFIXES = ['LH', 'LI', 'LP', 'LPA', 'LC', 'LAS'];

    public static function generate(string $prefix, string $tanggal): string
    {
        if (!in_array($prefix, self::ALLOWED_PREFIXES, true)) {
            throw new \InvalidArgumentException("Prefix laporan tidak valid: $prefix");
        }

        $pdo = Database::connect();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `nomor_laporan_counter` (`prefix`, `tanggal`, `counter`)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE `counter` = `counter` + 1
            ");
            $stmt->execute([$prefix, $tanggal]);

            $stmt = $pdo->prepare("
                SELECT `counter` FROM `nomor_laporan_counter`
                WHERE `prefix` = ? AND `tanggal` = ?
                LIMIT 1
            ");
            $stmt->execute([$prefix, $tanggal]);
            $row = $stmt->fetch();

            $counter = $row ? (int) $row['counter'] : 1;
            $datePart = str_replace('-', '', $tanggal);
            $nomor = sprintf('%s-%s-%04d', $prefix, $datePart, $counter);

            if ($ownTransaction) {
                $pdo->commit();
            }

            return $nomor;
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
