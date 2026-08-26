<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Kebijakan otorisasi laporan untuk aksi level petugas.
 *
 * Sumber kebenaran identitas adalah session/JWT (bukan input client).
 * Kepemilikan selalu divalidasi ulang secara eksplisit meskipun query
 * pencarian sudah di-scope per pemilik (defense-in-depth).
 */
class LaporanPolicy
{
    /**
     * Evaluasi izin edit laporan oleh petugas.
     *
     * @param array<string, mixed> $laporan Baris laporan dari database
     * @param int $userId ID pengguna dari session/JWT
     *
     * @return array{error: string, message: string, code: int}|null
     *         null bila diizinkan; deskripsi kegagalan bila ditolak.
     */
    public static function editDenial(array $laporan, int $userId): ?array
    {
        if ((int) ($laporan['user_id'] ?? 0) !== $userId || $userId <= 0) {
            return [
                'error' => 'NotFound',
                'message' => 'Laporan tidak ditemukan.',
                'code' => 404,
            ];
        }

        if (!LaporanStatus::isEditableByPetugas((string) ($laporan['status'] ?? ''))) {
            return [
                'error' => 'Conflict',
                'message' => 'Laporan dengan status ini tidak dapat diubah.',
                'code' => 409,
            ];
        }

        return null;
    }
}
