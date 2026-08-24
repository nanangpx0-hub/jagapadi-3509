<?php

declare(strict_types=1);

final class VideoUploader
{
    private const MAX_BYTES = 50 * 1024 * 1024;
    private const MIME_EXTENSIONS = ['video/mp4' => 'mp4'];

    public function upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'path' => null];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload video gagal.'];
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_BYTES) {
            return ['success' => false, 'error' => 'Ukuran video harus maksimal 50 MB.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            return ['success' => false, 'error' => 'Video harus berformat MP4 yang valid.'];
        }

        $directory = ROOT_PATH . '/public/uploads/laporan/video/';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ['success' => false, 'error' => 'Penyimpanan video tidak tersedia.'];
        }

        $filename = bin2hex(random_bytes(24)) . '.' . self::MIME_EXTENSIONS[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . $filename)) {
            return ['success' => false, 'error' => 'Video gagal disimpan.'];
        }

        return ['success' => true, 'path' => 'public/uploads/laporan/video/' . $filename];
    }
}
