<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class MasterDesa extends Model
{
    protected static function table(): string
    {
        return 'master_desa';
    }

    public static function findByKode(string $kode): ?array
    {
        return self::findBy('kode', $kode);
    }

    public static function findByKecamatan(int $kecamatanId): array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM `master_desa` WHERE `kecamatan_id` = ? ORDER BY `nama_desa` ASC");
        $stmt->execute([$kecamatanId]);
        return $stmt->fetchAll();
    }
}
