<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class LaporanStatusHistory extends Model
{
    protected static function table(): string
    {
        return 'laporan_status_history';
    }

    public static function record(
        int $laporanId,
        ?string $oldStatus,
        string $newStatus,
        int $changedBy,
        ?string $comment = null
    ): int|string {
        return self::insert([
            'laporan_id' => $laporanId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'komentar' => $comment,
        ]);
    }
}
