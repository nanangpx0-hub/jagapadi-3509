<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Logger;

class SecureImageUploader
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const MAX_DIMENSION = 4096;

    public static bool $testMode = false;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAGIC_BYTES_LENGTH = 12;

    public static function validateAndStore(array $file, array $options): array
    {
        $maxBytes = $options['max_bytes'] ?? 10485760;
        $destDir = $options['destination_dir'] ?? '';
        $relativeBase = $options['relative_base'] ?? '';

        if ($destDir === '') {
            throw new \InvalidArgumentException('destination_dir wajib diisi.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \DomainException(self::uploadErrorMsg($file['error']));
        }

        if (!self::$testMode && !is_uploaded_file($file['tmp_name'])) {
            throw new \DomainException('File tidak valid.');
        }

        if (self::$testMode && !is_file($file['tmp_name'])) {
            throw new \DomainException('File tidak valid.');
        }

        if ($file['size'] <= 0) {
            throw new \DomainException('File kosong.');
        }

        if ($file['size'] > $maxBytes) {
            $maxMb = $maxBytes / 1048576;
            throw new \DomainException("Ukuran file maksimal {$maxMb} MB.");
        }

        $magicBytes = self::readMagicBytes($file['tmp_name']);
        $detectedExt = self::detectExtensionFromMagicBytes($magicBytes);
        if ($detectedExt === null) {
            throw new \DomainException('File bukan gambar yang diizinkan (JPEG/PNG/WebP).');
        }

        $finfoMime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
        if (!in_array($finfoMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \DomainException('MIME type file tidak sesuai.');
        }

        $maxDim = $options['max_dimension'] ?? self::MAX_DIMENSION;
        $dimensions = @getimagesize($file['tmp_name']);
        if ($dimensions === false) {
            throw new \DomainException('Gagal membaca dimensi gambar.');
        }
        [$imgW, $imgH] = $dimensions;
        if ($imgW > $maxDim || $imgH > $maxDim) {
            throw new \DomainException('Dimensi gambar maksimal ' . $maxDim . 'x' . $maxDim . ' piksel.');
        }

        $clientExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($clientExt, self::ALLOWED_EXTENSIONS, true)) {
            throw new \DomainException('Ekstensi file tidak diizinkan.');
        }

        $finalExt = self::normalizeExtension($detectedExt);
        $filename = bin2hex(random_bytes(16)) . '.' . $finalExt;

        $subDir = date('Ym');
        $fullDir = rtrim($destDir, '\\/') . DIRECTORY_SEPARATOR . $subDir;
        if (!is_dir($fullDir)) {
            if (!mkdir($fullDir, 0755, true) && !is_dir($fullDir)) {
                throw new \RuntimeException('Gagal membuat direktori penyimpanan.');
            }
        }

        $destPath = $fullDir . DIRECTORY_SEPARATOR . $filename;
        if (self::$testMode) {
            if (!copy($file['tmp_name'], $destPath)) {
                throw new \RuntimeException('Gagal memindahkan file.');
            }
        } elseif (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Gagal memindahkan file.');
        }

        $finalSize = filesize($destPath);

        if ($finalSize > ImageCompressor::DEFAULT_TARGET_BYTES) {
            try {
                ImageCompressor::compress($destPath, ImageCompressor::DEFAULT_TARGET_BYTES);
                $finalSize = filesize($destPath);
            } catch (\Throwable $e) {
                Logger::error('Kompresi gambar gagal, file tetap disimpan', [
                    'path' => $destPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $relativePath = rtrim($relativeBase, '\\/') . '/' . $subDir . '/' . $filename;

        return [
            'foto_url' => $relativePath,
            'bytes' => $finalSize,
            'mime' => $finfoMime,
            'full_path' => $destPath,
        ];
    }

    public static function deleteOldPhoto(string $uploadRoot, string $fotoUrl): bool
    {
        if ($fotoUrl === '') {
            return false;
        }

        $cleanPath = str_replace('/', DIRECTORY_SEPARATOR, $fotoUrl);
        $fullPath = realpath(rtrim($uploadRoot, '\\/') . DIRECTORY_SEPARATOR . $cleanPath);

        $realRoot = realpath($uploadRoot);
        if ($fullPath === false || $realRoot === false) {
            return false;
        }

        if (!str_starts_with($fullPath, $realRoot)) {
            return false;
        }

        if (is_file($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    private static function readMagicBytes(string $tmpPath): string
    {
        $fh = fopen($tmpPath, 'rb');
        if ($fh === false) {
            throw new \DomainException('Gagal membaca file.');
        }
        $bytes = fread($fh, self::MAGIC_BYTES_LENGTH);
        fclose($fh);
        if ($bytes === false || strlen($bytes) < self::MAGIC_BYTES_LENGTH) {
            return '';
        }
        return $bytes;
    }

    private static function detectExtensionFromMagicBytes(string $bytes): ?string
    {
        if (strlen($bytes) < 3) {
            return null;
        }

        $prefix3 = substr($bytes, 0, 3);
        if ($prefix3 === "\xFF\xD8\xFF") {
            return 'jpg';
        }

        $pngSig = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
        if (strlen($bytes) >= 8 && substr($bytes, 0, 8) === $pngSig) {
            return 'png';
        }

        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    private static function normalizeExtension(string $ext): string
    {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    private static function uploadErrorMsg(int $code): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File melebihi batas upload server.',
            UPLOAD_ERR_FORM_SIZE => 'File melebihi batas form.',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload diblokir oleh ekstensi PHP.',
        ];
        return $messages[$code] ?? 'Kesalahan upload tidak dikenal.';
    }
}
