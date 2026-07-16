<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class MasterKecamatan extends Model
{
    protected static function table(): string
    {
        return 'master_kecamatan';
    }

    public static function findByKode(string $kode): ?array
    {
        return self::findBy('kode', $kode);
    }

    public static function findByKabupaten(int $kabupatenId): array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM `master_kecamatan` WHERE `kabupaten_id` = ? ORDER BY `nama_kecamatan` ASC");
        $stmt->execute([$kabupatenId]);
        return $stmt->fetchAll();
    }
}
