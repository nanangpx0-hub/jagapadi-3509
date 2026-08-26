<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Database;
use App\Core\Request;

/**
 * API: Upload video pendukung laporan hama (MP4/MOV ≤50 MB).
 *
 * POST /api/v1/laporan-hama/{id}/video
 * POST /api/v1/laporan-hama/{id}/video/delete
 *
 * Ownership: petugas hanya pada laporan miliknya dengan status Draf/Ditolak
 * (berlaku untuk upload maupun hapus).
 */
class LaporanVideoController extends BaseApiController
{
    private const MAX_BYTES = 50 * 1024 * 1024;

    /** @var array<string, string> MIME tervalidasi finfo => ekstensi penyimpanan */
    private const ALLOWED_MIMES = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
    ];

    public function uploadVideo(array $params = []): void
    {
        $user = $GLOBALS['auth_user'] ?? null;
        $id = (int) ($params['id'] ?? 0);

        if ($user === null || $id <= 0) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $file = $_FILES['video'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->error('ValidationError', 'Tidak ada video yang diupload.', [], 422);
            return;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->error('ValidationError', 'Upload tidak valid.', [], 422);
            return;
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            $this->error('ValidationError', 'Ukuran video harus maksimal 50 MB.', [], 422);
            return;
        }

        // MIME check via finfo + magic bytes (ftyp box wajib pada MP4/MOV)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            $this->error('ValidationError', 'Video harus berformat MP4 atau MOV yang valid.', [], 422);
            return;
        }
        if (!self::hasFtypBox($file['tmp_name'])) {
            $this->error('ValidationError', 'Berkas bukan video MP4/MOV yang valid.', [], 422);
            return;
        }
        if ($mime === 'video/quicktime' && !self::isQuickTimeBrand($file['tmp_name'])) {
            $this->error('ValidationError', 'Berkas MOV tidak valid.', [], 422);
            return;
        }

        try {
            $db = Database::connect();
            if ($db === null) {
                $this->error('ServerError', 'Database tidak tersedia.', [], 503);
                return;
            }

            // Ownership + status check
            $stmt = $db->prepare(
                'SELECT user_id, status, video_url FROM laporan_hama WHERE id = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$id]);
            $report = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($report === false) {
                $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
                return;
            }
            if ($user['role'] !== 'admin' && (int) $report['user_id'] !== (int) $user['id']) {
                $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
                return;
            }
            if (!in_array($report['status'], ['Draf', 'Ditolak'], true)) {
                $this->error(
                    'Conflict',
                    'Video hanya dapat diunggah pada laporan berstatus Draf atau Ditolak.',
                    [],
                    409
                );
                return;
            }

            $dir = BASE_PATH . '/public/assets/uploads/laporan-hama/video/';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $this->error('ServerError', 'Penyimpanan video tidak tersedia.', [], 500);
                return;
            }

            $ext = self::ALLOWED_MIMES[$mime];
            $filename = bin2hex(random_bytes(24)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $this->error('ServerError', 'Video gagal disimpan.', [], 500);
                return;
            }

            // Hapus video lama HANYA setelah file baru tersimpan agar gagal
            // move tidak membuat video sebelumnya hilang.
            $oldPath = $report['video_url'];
            $relativePath = 'assets/uploads/laporan-hama/video/' . $filename;
            $upd = $db->prepare('UPDATE laporan_hama SET video_url = ? WHERE id = ?');
            $upd->execute([$relativePath, $id]);

            if (!empty($oldPath) && $oldPath !== $relativePath) {
                $oldFull = BASE_PATH . '/public/' . ltrim((string) $oldPath, '/');
                if (is_file($oldFull)) {
                    @unlink($oldFull);
                }
            }

            $this->success([
                'id' => $id,
                'video_url' => $relativePath,
            ], 'Video berhasil diunggah.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('LaporanVideoController::uploadVideo: ' . $e->getMessage());
            $this->error('ServerError', 'Gagal mengunggah video.', [], 500);
        }
    }

    public function deleteVideo(array $params = []): void
    {
        $user = $GLOBALS['auth_user'] ?? null;
        $id = (int) ($params['id'] ?? 0);

        if ($user === null || $id <= 0) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        try {
            $db = Database::connect();
            if ($db === null) {
                $this->error('ServerError', 'Database tidak tersedia.', [], 503);
                return;
            }

            $stmt = $db->prepare(
                'SELECT user_id, status, video_url FROM laporan_hama WHERE id = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$id]);
            $report = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($report === false) {
                $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
                return;
            }
            if ($user['role'] !== 'admin' && (int) $report['user_id'] !== (int) $user['id']) {
                $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
                return;
            }
            if (!in_array($report['status'], ['Draf', 'Ditolak'], true)) {
                $this->error(
                    'Conflict',
                    'Video hanya dapat dihapus pada laporan berstatus Draf atau Ditolak.',
                    [],
                    409
                );
                return;
            }

            if (!empty($report['video_url'])) {
                $full = BASE_PATH . '/public/' . ltrim((string) $report['video_url'], '/');
                if (is_file($full)) {
                    @unlink($full);
                }
                $upd = $db->prepare('UPDATE laporan_hama SET video_url = NULL WHERE id = ?');
                $upd->execute([$id]);
            }

            $this->success(['id' => $id], 'Video berhasil dihapus.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('LaporanVideoController::deleteVideo: ' . $e->getMessage());
            $this->error('ServerError', 'Gagal menghapus video.', [], 500);
        }
    }

    /**
     * Magic bytes MP4/MOV: box 'ftyp' selalu berada pada offset 4.
     */
    private static function hasFtypBox(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        fseek($fh, 4);
        $bytes = (string) fread($fh, 4);
        fclose($fh);
        return strlen($bytes) === 4 && $bytes === 'ftyp';
    }

    private static function isQuickTimeBrand(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        fseek($fh, 8);
        $brand = (string) fread($fh, 4);
        fclose($fh);
        return str_starts_with($brand, 'qt');
    }
}
