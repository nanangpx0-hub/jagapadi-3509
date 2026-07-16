<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class MasterOpt extends Model
{
    protected static function table(): string
    {
        return 'master_opt';
    }

    public static function findByNama(string $nama): ?array
    {
        return self::findBy('nama_opt', $nama);
    }

    public static function allActive(string $jenis = '', string $search = '', string $orderBy = 'nama_opt', string $direction = 'ASC'): array
    {
        $pdo = self::db();
        $conditions = ['`aktif` = 1'];
        $params = [];

        if ($jenis !== '') {
            $conditions[] = '`jenis` = ?';
            $params[] = $jenis;
        }

        if ($search !== '') {
            $conditions[] = '`nama_opt` LIKE ?';
            $params[] = "%$search%";
        }

        $where = implode(' AND ', $conditions);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $allowedOrder = ['nama_opt', 'jenis', 'created_at'];
        $orderByCol = in_array($orderBy, $allowedOrder) ? $orderBy : 'nama_opt';

        $stmt = $pdo->prepare("SELECT * FROM `master_opt` WHERE $where ORDER BY `$orderByCol` $direction");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function allWithFilters(string $jenis = '', string $search = '', ?int $aktif = null, string $orderBy = 'nama_opt', string $direction = 'ASC'): array
    {
        $pdo = self::db();
        $conditions = [];
        $params = [];

        if ($jenis !== '') {
            $conditions[] = '`jenis` = ?';
            $params[] = $jenis;
        }

        if ($search !== '') {
            $conditions[] = '`nama_opt` LIKE ?';
            $params[] = "%$search%";
        }

        if ($aktif !== null) {
            $conditions[] = '`aktif` = ?';
            $params[] = $aktif;
        }

        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $allowedOrder = ['nama_opt', 'jenis', 'aktif', 'created_at'];
        $orderByCol = in_array($orderBy, $allowedOrder) ? $orderBy : 'nama_opt';

        $stmt = $pdo->prepare("SELECT * FROM `master_opt` $where ORDER BY `$orderByCol` $direction");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
