<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class MasterKabupaten extends Model
{
    protected static function table(): string
    {
        return 'master_kabupaten';
    }

    public static function findByKode(string $kode): ?array
    {
        return self::findBy('kode', $kode);
    }
}
