<?php

declare(strict_types=1);

/**
 * Upload foto bukti usulan OPT dengan aturan keamanan repository:
 * error upload, ukuran, magic bytes finfo, allowlist MIME, ekstensi dari MIME,
 * nama acak, direktori bertanggal dengan .htaccess non-eksekusi, checksum,
 * dan pencegahan traversal pada penghapusan.
 */
final class UsulanPhotoUploader
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_FILES_PER_USULAN = 5;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAGIC_SIGNATURES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1A\n"],
        'image/webp' => ['RIFF'],
    ];

    private string $uploadDirRelative;

    public function __construct(string $uploadDirRelative = 'public/uploads/usulan-opt/')
    {
        $this->uploadDirRelative = rtrim($uploadDirRelative, '/') . '/';
    }

    /**
     * @return array{success:bool,error?:string,file?:array<string,mixed>}
     */
    public function upload(array $file, int $actorId): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'Tidak ada file yang dipilih.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload foto gagal. Silakan coba lagi.'];
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Sumber file tidak valid.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['success' => false, 'error' => 'Ukuran foto maksimal 5 MB.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            return ['success' => false, 'error' => 'Foto harus berformat JPG, PNG, atau WEBP.'];
        }

        $handle = fopen($file['tmp_name'], 'rb');
        $head = $handle !== false ? (string) fread($handle, 12) : '';
        if ($handle !== false) {
            fclose($handle);
        }
        $magicOk = false;
        foreach (self::MAGIC_SIGNATURES[$mime] as $signature) {
            if (str_starts_with($head, $signature)) {
                $magicOk = true;
                break;
            }
        }
        if (!$magicOk) {
            return ['success' => false, 'error' => 'Isi file bukan gambar yang valid.'];
        }

        $dimensions = @getimagesize($file['tmp_name']);
        if ($dimensions === false) {
            return ['success' => false, 'error' => 'Gambar tidak dapat dibaca.'];
        }
        if ((int) $dimensions[0] > 6000 || (int) $dimensions[1] > 6000) {
            return ['success' => false, 'error' => 'Dimensi gambar maksimal 6000 piksel.'];
        }

        $year = date('Y');
        $month = date('m');
        $absoluteDir = ROOT_PATH . '/' . $this->uploadDirRelative . $year . '/' . $month;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            return ['success' => false, 'error' => 'Penyimpanan foto tidak tersedia.'];
        }
        $this->ensureHtaccess(ROOT_PATH . '/' . $this->uploadDirRelative);

        $filename = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $targetPath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Gagal menyimpan foto. Coba lagi.'];
        }
        @chmod($targetPath, 0644);

        return [
            'success' => true,
            'file' => [
                'file_path' => $this->uploadDirRelative . $year . '/' . $month . '/' . $filename,
                'mime_type' => $mime,
                'size_bytes' => filesize($targetPath) ?: $size,
                'checksum' => hash_file('sha256', $targetPath),
                'created_by' => $actorId,
            ],
        ];
    }

    /**
     * Hapus aman hanya di dalam basis direktori upload modul ini.
     */
    public function deleteByPath(?string $relativePath): bool
    {
        if ($relativePath === null || $relativePath === '') {
            return false;
        }

        $normalized = str_replace('\\', '/', $relativePath);
        $base = str_replace('\\', '/', ROOT_PATH . '/');
        $fullPath = $base . ltrim($normalized, '/');

        if (!str_starts_with($fullPath, $base . rtrim($this->uploadDirRelative, '/'))) {
            return false;
        }

        $real = realpath($fullPath);
        $realBase = realpath($base . rtrim($this->uploadDirRelative, '/'));
        if ($real === false || $realBase === false || !str_starts_with($real, $realBase)) {
            return false;
        }

        return is_file($real) ? @unlink($real) : false;
    }

    private function ensureHtaccess(string $dir): void
    {
        $htaccess = $dir . '/.htaccess';
        if (is_file($htaccess)) {
            return;
        }
        $rules = "Options -ExecCGI -Indexes\n"
            . "AddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .sh .cgi\n"
            . "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n"
            . "    Require all denied\n"
            . "</FilesMatch>\n";
        @file_put_contents($htaccess, $rules);
    }
}
