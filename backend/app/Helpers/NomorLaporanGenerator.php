<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class NomorLaporanGenerator
{
    private const PREFIX = 'LH';

    public static function generate(string $tanggal): string
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `nomor_laporan_counter` (`prefix`, `tanggal`, `counter`)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE `counter` = `counter` + 1
            ");
            $stmt->execute([self::PREFIX, $tanggal]);

            $stmt = $pdo->prepare("
                SELECT `counter` FROM `nomor_laporan_counter`
                WHERE `prefix` = ? AND `tanggal` = ?
                LIMIT 1
            ");
            $stmt->execute([self::PREFIX, $tanggal]);
            $row = $stmt->fetch();

            $counter = $row ? (int) $row['counter'] : 1;
            $datePart = str_replace('-', '', $tanggal);
            $nomor = sprintf('%s-%s-%04d', self::PREFIX, $datePart, $counter);

            $pdo->commit();

            return $nomor;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
