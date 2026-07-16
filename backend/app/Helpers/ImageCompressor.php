<?php

declare(strict_types=1);

namespace App\Helpers;

class ImageCompressor
{
    public static function compress(string $path): void
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
        $quality = 75;

        switch ($mime) {
            case 'image/jpeg':
                $img = @imagecreatefromjpeg($path);
                if ($img === false) {
                    return;
                }
                @imagejpeg($img, $path, $quality);
                break;

            case 'image/png':
                $img = @imagecreatefrompng($path);
                if ($img === false) {
                    return;
                }
                $pngQuality = (int) round((100 - $quality) / 10);
                @imagepng($img, $path, min($pngQuality, 9));
                break;

            case 'image/webp':
                $img = @imagecreatefromwebp($path);
                if ($img === false) {
                    return;
                }
                @imagewebp($img, $path, $quality);
                break;

            default:
                return;
        }

        imagedestroy($img);
    }
}
