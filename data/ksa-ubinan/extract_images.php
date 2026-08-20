<?php
/**
 * Ekstraksi gambar JPEG (DCTDecode) dari pada-2025.pdf
 * Output: data/ksa-ubinan/extracted/img_XX.jpg
 */

declare(strict_types=1);

const PDF_PATH = __DIR__ . '/pada-2025.pdf';
const OUT_DIR = __DIR__ . '/extracted';

$raw = file_get_contents(PDF_PATH);
if ($raw === false) {
    echo "ERROR: tidak bisa baca PDF\n";
    exit(1);
}

if (!is_dir(OUT_DIR)) {
    mkdir(OUT_DIR, 0777, true);
}

$count = 0;
$pos = 0;

while (($start = strpos($raw, 'stream', $pos)) !== false) {
    // Cek apakah object ini DCTDecode (JPEG): cari "/DCTDecode" di 2000 byte sebelum stream
    $ctxStart = max(0, $start - 2000);
    $ctx = substr($raw, $ctxStart, $start - $ctxStart);
    $isDct = strpos($ctx, '/DCTDecode') !== false;
    $isImage = strpos($ctx, '/Image') !== false || strpos($ctx, '/DCTDecode') !== false;

    $dataStart = $start + 6;
    if (substr($raw, $dataStart, 2) === "\r\n") {
        $dataStart += 2;
    } elseif (substr($raw, $dataStart, 1) === "\n" || substr($raw, $dataStart, 1) === "\r") {
        $dataStart += 1;
    }

    $end = strpos($raw, 'endstream', $dataStart);
    if ($end === false) {
        break;
    }

    $chunk = substr($raw, $dataStart, $end - $dataStart);

    if ($isDct) {
        // Pastikan mulai dari marker SOI (FFD8)
        $soi = strpos($chunk, "\xFF\xD8");
        $eoi = strrpos($chunk, "\xFF\xD9");
        if ($soi !== false && $eoi !== false && $eoi > $soi) {
            $jpeg = substr($chunk, $soi, $eoi - $soi + 2);
            $file = OUT_DIR . '/img_' . str_pad((string) $count, 2, '0', STR_PAD_LEFT) . '.jpg';
            file_put_contents($file, $jpeg);
            $size = filesize($file);
            $dim = @getimagesize($file);
            $dimStr = $dim ? "{$dim[0]}x{$dim[1]}" : 'unknown';
            echo "img_{$count}: {$size} bytes, {$dimStr}\n";
            $count++;
        } else {
            echo "skip stream at {$start}: JPEG marker tidak lengkap\n";
        }
    }

    $pos = $end + 9;
}

echo "Total JPEG diekstrak: {$count}\n";
