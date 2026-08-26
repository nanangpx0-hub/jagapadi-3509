<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Kompresor gambar sisi server untuk unggahan laporan.
 *
 * Tujuan: menjaga berkas foto tetap di bawah batas ukuran target tanpa
 * merusak isi untuk keperluan verifikasi data:
 * - menurunkan kualitas secara bertahap (bukan satu pass agresif),
 * - menurunkan dimensi maksimal sisi panjang bila kualitas minimum
 *   masih belum mencapai target,
 * - menerapkan orientasi EXIF sebelum re-encode agar hasil tidak miring,
 * - menulis atomik lewat berkas sementara sehingga gagal kompresi tidak
 *   menghasilkan berkas rusak (original dipertahankan).
 *
 * Format output selalu sama dengan input (JPEG/PNG/WebP) supaya ekstensi
 * dan MIME yang tersimpan tetap konsisten.
 */
class ImageCompressor
{
    public const DEFAULT_TARGET_BYTES = 2097152; // 2 MB
    private const JPEG_QUALITIES = [85, 75, 65, 55, 45];
    private const MAX_DIMENSIONS = [1920, 1600, 1280];

    public static function compress(string $path, int $targetBytes = self::DEFAULT_TARGET_BYTES): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return;
        }

        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return;
        }

        if (filesize($path) <= $targetBytes) {
            return;
        }

        $img = self::loadImage($path, $mime);
        if ($img === null) {
            return;
        }

        if ($mime === 'image/jpeg') {
            $img = self::applyExifOrientation($path, $img);
        }

        try {
            self::encodeWithinTarget($img, $path, $mime, $targetBytes);
        } catch (\Throwable) {
            // Biarkan berkas original tetap utuh bila kompresi gagal.
        } finally {
            imagedestroy($img);
        }
    }

    private static function loadImage(string $path, string $mime): ?\GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if ($img === false) {
            return null;
        }

        if ($mime !== 'image/jpeg') {
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }

        return $img;
    }

    /**
     * Terapkan rotasi EXIF pada gambar sebelum re-encode (JPEG saja).
     */
    private static function applyExifOrientation(string $path, \GdImage $img): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $img;
        }

        imagedestroy($img);
        return $rotated;
    }

    private static function encodeWithinTarget(\GdImage $img, string $destPath, string $mime, int $targetBytes): void
    {
        $current = $img;

        foreach (self::MAX_DIMENSIONS as $dimension) {
            $candidate = self::resizedCopy($current, $dimension);
            if ($candidate !== $current) {
                if ($current !== $img) {
                    imagedestroy($current);
                }
                $current = $candidate;
            }

            foreach (self::JPEG_QUALITIES as $quality) {
                $tmpPath = $destPath . '.tmp' . bin2hex(random_bytes(4));
                if (!self::encodeTo($current, $tmpPath, $mime, $quality)) {
                    continue;
                }

                $size = filesize($tmpPath);
                if ($size !== false && $size > 0 && $size <= $targetBytes) {
                    rename($tmpPath, $destPath);
                    if ($current !== $img) {
                        imagedestroy($current);
                    }
                    return;
                }

                @unlink($tmpPath);
            }
        }

        // Target tak tercapai: simpan hasil kualitas terendah terakhir agar
        // ukuran minimal menyusut, namun jangan pernah meninggalkan berkas rusak.
        $tmpPath = $destPath . '.tmp' . bin2hex(random_bytes(4));
        if (self::encodeTo($current, $tmpPath, $mime, self::JPEG_QUALITIES[array_key_last(self::JPEG_QUALITIES)])) {
            $newSize = filesize($tmpPath);
            $oldSize = filesize($destPath);
            if ($newSize !== false && $oldSize !== false && $newSize < $oldSize) {
                rename($tmpPath, $destPath);
            } else {
                @unlink($tmpPath);
            }
        } else {
            @unlink($tmpPath);
        }

        if ($current !== $img) {
            imagedestroy($current);
        }
    }

    private static function resizedCopy(\GdImage $img, int $maxDimension): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $longest = max($w, $h);
        if ($longest <= $maxDimension) {
            return $img;
        }

        $scale = $maxDimension / $longest;
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $new = imagecreatetruecolor($newW, $newH);
        imagealphablending($new, false);
        imagesavealpha($new, true);
        imagecopyresampled($new, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

        return $new;
    }

    private static function encodeTo(\GdImage $img, string $path, string $mime, int $quality): bool
    {
        return match ($mime) {
            'image/jpeg' => @imagejpeg($img, $path, $quality),
            'image/webp' => @imagewebp($img, $path, $quality),
            'image/png' => @imagepng($img, $path, min((int) round((100 - $quality) / 10), 9)),
            default => false,
        };
    }
}
