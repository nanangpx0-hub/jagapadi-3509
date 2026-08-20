<?php
/**
 * TASK 1: Ekstraksi Data KSA Ubinan dari pada-2025.pdf
 *
 * Jalankan dari CLI: php extract_pdf_data.php
 *
 * Langkah:
 * 1. Coba ekstrak teks dari PDF (decompress FlateDecode stream, ambil teks
 *    dari operator Tj/TJ, lalu cari baris data per kabupaten dengan regex).
 * 2. Bila ekstraksi berhasil (>= 38 baris kabupaten): tulis CSV berisi data.
 * 3. Bila gagal (PDF kompleks/biner): fallback ke template prefilled
 *    38 kabupaten/kota Jawa Timur + kode BPS, angka dikosongkan untuk
 *    diisi manual oleh operator.
 *
 * Output: ksa_ubinan_2025_template_prefilled.csv
 */

declare(strict_types=1);

// ============================================================
// KONFIGURASI
// ============================================================

const PDF_PATH = __DIR__ . '/pada-2025.pdf';
const OUTPUT_PATH = __DIR__ . '/ksa_ubinan_2025_template_prefilled.csv';
const TARGET_TAHUN = 2025;
const JUMLAH_KABUPATEN_EXPECTED = 38;

/**
 * 38 Kabupaten/Kota Jawa Timur dengan kode BPS.
 * Urutan sesuai kode BPS (3501-3529 kabupaten, 3571-3579 kota).
 */
const KABUPATEN_BPS = [
    3501 => 'Pacitan',
    3502 => 'Ponorogo',
    3503 => 'Trenggalek',
    3504 => 'Tulungagung',
    3505 => 'Blitar',
    3506 => 'Kediri',
    3507 => 'Malang',
    3508 => 'Lumajang',
    3509 => 'Jember',
    3510 => 'Banyuwangi',
    3511 => 'Bondowoso',
    3512 => 'Situbondo',
    3513 => 'Probolinggo',
    3514 => 'Pasuruan',
    3515 => 'Sidoarjo',
    3516 => 'Mojokerto',
    3517 => 'Jombang',
    3518 => 'Nganjuk',
    3519 => 'Madiun',
    3520 => 'Magetan',
    3521 => 'Ngawi',
    3522 => 'Bojonegoro',
    3523 => 'Tuban',
    3524 => 'Lamongan',
    3525 => 'Gresik',
    3526 => 'Bangkalan',
    3527 => 'Sampang',
    3528 => 'Pamekasan',
    3529 => 'Sumenep',
    3571 => 'Kota Kediri',
    3572 => 'Kota Blitar',
    3573 => 'Kota Malang',
    3574 => 'Kota Probolinggo',
    3575 => 'Kota Pasuruan',
    3576 => 'Kota Mojokerto',
    3577 => 'Kota Madiun',
    3578 => 'Kota Surabaya',
    3579 => 'Kota Batu',
];

// ============================================================
// HELPER
// ============================================================

function printHeader(string $title): void
{
    $line = str_repeat('=', 60);
    echo "\n{$line}\n  {$title}\n{$line}\n";
}

function printOk(string $msg): void
{
    echo "  OK {$msg}\n";
}

function printWarn(string $msg): void
{
    echo "  !! {$msg}\n";
}

function printInfo(string $msg): void
{
    echo "  -> {$msg}\n";
}

/**
 * Decompress semua FlateDecode stream pada PDF dan kumpulkan teks.
 * Pendekatan strpos-based agar aman terhadap data biner (hindari
 * terminasi prematur dari regex lazy pada "endstream").
 * ObjStm (object stream) yang terkompres juga ikut ter-decompress.
 */
function extractPdfRawText(string $pdfPath): string
{
    $raw = file_get_contents($pdfPath);
    if ($raw === false) {
        return '';
    }

    $collected = '';
    $pos = 0;

    while (($start = strpos($raw, 'stream', $pos)) !== false) {
        // Data dimulai setelah keyword "stream" + optional newline
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
        $decoded = @gzuncompress($chunk);
        if ($decoded !== false) {
            $collected .= $decoded . "\n";
        }

        $pos = $end + 9;
    }

    return $collected;
}

/**
 * Ambil string teks dari konten PDF (operator Tj / TJ).
 * Mengembalikan teks dengan spasi antar blok.
 */
function extractStringsFromContent(string $content): string
{
    $chunks = [];

    // Tj: (teks) Tj
    if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj/', $content, $tjMatches)) {
        foreach ($tjMatches[0] as $tj) {
            $chunks[] = extractPdfString($tj);
        }
    }

    // TJ: [(teks) 10 (teks)] TJ
    if (preg_match_all('/\[(?:[^\[\]\\\\]|\\\\.)*\]\s*TJ/', $content, $tjArrMatches)) {
        foreach ($tjArrMatches[0] as $tj) {
            if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $tj, $strMatches)) {
                foreach ($strMatches[0] as $s) {
                    $chunks[] = extractPdfString($s);
                }
            }
        }
    }

    return implode(' ', $chunks);
}

/**
 * Decode string PDF: hapus kurung, unescape backslash.
 */
function extractPdfString(string $pdfString): string
{
    $inner = trim(substr($pdfString, 1, -1));
    $inner = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $inner);
    return $inner;
}

/**
 * Normalisasi angka Indonesia: "1.234.567" -> 1234567, "1234,56" -> 1234.56.
 * Mengembalikan float atau null bila tidak valid.
 */
function normalizeNumber(?string $value): ?float
{
    if ($value === null || trim($value) === '' || trim($value) === '-') {
        return null;
    }

    $clean = trim($value);
    $clean = str_replace(['.', ' '], '', $clean); // hapus ribuan/spasi
    $clean = str_replace(',', '.', $clean);        // koma desimal -> titik

    if (!is_numeric($clean)) {
        return null;
    }

    return (float) $clean;
}

/**
 * Coba cari data per kabupaten dari teks PDF.
 * Strategi: temukan posisi nama kabupaten, lalu baca 3-4 angka
 * berturut-turut setelahnya (luas panen, produksi, produktivitas).
 */
function extractRowsFromText(string $text): array
{
    $rows = [];

    foreach (KABUPATEN_BPS as $kode => $nama) {
        $pattern = '/' . preg_quote($nama, '/') . '[^0-9]{0,40}([\d.,\s]+)(?:[^\d.,]{0,20}([\d.,\s]+))?(?:[^\d.,]{0,20}([\d.,\s]+))?(?:[^\d.,]{0,20}([\d.,\s]+))?/iu';
        if (preg_match($pattern, $text, $m) !== 1) {
            continue;
        }

        $values = [];
        for ($i = 1; $i <= 4; $i++) {
            $values[] = isset($m[$i]) ? normalizeNumber($m[$i]) : null;
        }

        // Minimal 2 angka valid agar dianggap baris data
        $validCount = count(array_filter($values, fn($v) => $v !== null));
        if ($validCount < 2) {
            continue;
        }

        $rows[$kode] = [
            'kabupaten_kota' => $nama,
            'kode_wilayah' => (string) $kode,
            'luas_panen' => $values[0],
            'produksi_gabah' => $values[1],
            'produktivitas' => $values[2],
            'produksi_beras' => $values[3],
        ];
    }

    return $rows;
}

/**
 * Tulis CSV dengan header standar sistem.
 */
function writeCsv(string $path, array $rows, string $keteranganDefault): int
{
    $handle = fopen($path, 'w');
    if ($handle === false) {
        throw new RuntimeException("Tidak dapat membuat file: {$path}");
    }

    // BOM UTF-8 untuk kompatibilitas Excel
    fwrite($handle, "\xEF\xBB\xBF");

    fputcsv($handle, [
        'tahun', 'kabupaten_kota', 'kode_wilayah',
        'luas_panen', 'produksi_gabah', 'produksi_beras', 'produktivitas', 'keterangan',
    ]);

    $count = 0;
    foreach ($rows as $kode => $row) {
        fputcsv($handle, [
            TARGET_TAHUN,
            $row['kabupaten_kota'],
            (string) $kode,
            $row['luas_panen'] ?? '',
            $row['produksi_gabah'] ?? '',
            $row['produksi_beras'] ?? '',
            $row['produktivitas'] ?? '',
            $row['keterangan'] ?? $keteranganDefault,
        ]);
        $count++;
    }

    fclose($handle);
    return $count;
}

// ============================================================
// MAIN
// ============================================================

printHeader('TASK 1: Ekstraksi Data KSA Ubinan dari PDF');

if (!file_exists(PDF_PATH)) {
    echo "\n  ERROR: PDF tidak ditemukan: " . PDF_PATH . "\n";
    exit(1);
}

printInfo('File PDF: ' . PDF_PATH);
printInfo('Ukuran: ' . number_format(filesize(PDF_PATH)) . ' bytes');

// STEP 1: ekstraksi teks
printHeader('STEP 1: Ekstraksi Teks PDF');

$rawText = extractPdfRawText(PDF_PATH);
printInfo('Total konten terdecompress: ' . strlen($rawText) . ' bytes');

$textContent = '';
if (strlen($rawText) > 0) {
    $textContent = extractStringsFromContent($rawText);
    if (trim($textContent) === '') {
        // Fallback: pakai raw decompressed text langsung
        $textContent = $rawText;
    }
}

printInfo('Panjang teks hasil ekstraksi: ' . strlen($textContent));

// STEP 2: cari baris data
printHeader('STEP 2: Pencarian Baris Data per Kabupaten');

$rows = [];
$usedFallback = false;
if (trim($textContent) !== '') {
    $rows = extractRowsFromText($textContent);
}

if (count($rows) >= JUMLAH_KABUPATEN_EXPECTED) {
    printOk('Ekstraksi berhasil: ' . count($rows) . ' kabupaten ditemukan');
} else {
    printWarn('Ekstraksi tidak cukup: ' . count($rows) . ' dari ' . JUMLAH_KABUPATEN_EXPECTED . ' kabupaten');
    printWarn('PDF dianggap kompleks/biner -> memakai FALLBACK template prefilled');
    $rows = [];
    $usedFallback = true;
}

// STEP 3: susun baris final
printHeader('STEP 3: Penyusunan Baris Data');

if (empty($rows)) {
    foreach (KABUPATEN_BPS as $kode => $nama) {
        $rows[$kode] = [
            'kabupaten_kota' => $nama,
            'kode_wilayah' => (string) $kode,
            'luas_panen' => '',
            'produksi_gabah' => '',
            'produksi_beras' => '',
            'produktivitas' => '',
            'keterangan' => 'Prefilled template - isi angka dari pada-2025.pdf',
        ];
    }
    printWarn('Menggunakan template prefilled 38 kabupaten/kota (angka kosong)');
} else {
    foreach ($rows as $kode => &$row) {
        $row['keterangan'] = 'Diekstrak otomatis dari pada-2025.pdf - perlu verifikasi manual';
    }
    unset($row);
}

// STEP 4: tulis CSV
printHeader('STEP 4: Menulis CSV');

try {
    $written = writeCsv(OUTPUT_PATH, $rows, 'Prefilled template - isi angka dari pada-2025.pdf');
    printOk('CSV ditulis: ' . OUTPUT_PATH);
    printInfo('Jumlah baris: ' . $written);
    printInfo('Ukuran: ' . number_format(filesize(OUTPUT_PATH)) . ' bytes');
} catch (RuntimeException $e) {
    echo "\n  ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// STEP 5: verifikasi
printHeader('STEP 5: Verifikasi Output');

$verifyRows = [];
$handle = fopen(OUTPUT_PATH, 'r');
if ($handle !== false) {
    $isHeader = true;
    while (($row = fgetcsv($handle)) !== false) {
        if ($isHeader) {
            $isHeader = false;
            continue;
        }
        if (!empty(array_filter($row))) {
            $verifyRows[] = $row;
        }
    }
    fclose($handle);
}

$errors = [];
if (count($verifyRows) !== JUMLAH_KABUPATEN_EXPECTED) {
    $errors[] = "Jumlah baris " . count($verifyRows) . " != " . JUMLAH_KABUPATEN_EXPECTED;
}

$kodes = [];
foreach ($verifyRows as $r) {
    $kodes[] = (int) ($r[2] ?? 0);
    if ((int) ($r[0] ?? 0) !== TARGET_TAHUN) {
        $errors[] = "Tahun salah di baris: " . implode(',', $r);
    }
    if (trim($r[1] ?? '') === '') {
        $errors[] = "Kabupaten kosong di baris: " . implode(',', $r);
    }
}

sort($kodes);
$expectedKodes = array_keys(KABUPATEN_BPS);
sort($expectedKodes);
if ($kodes !== $expectedKodes) {
    $errors[] = 'Kode wilayah tidak lengkap/urut';
}

if (empty($errors)) {
    printOk('Verifikasi LULUS: ' . count($verifyRows) . ' baris, semua kode BPS valid');
} else {
    printWarn('Verifikasi GAGAL:');
    foreach ($errors as $e) {
        echo "     - {$e}\n";
    }
    exit(1);
}

printInfo('Kode BPS: ' . $kodes[0] . ' .. ' . end($kodes));
printInfo('Contoh 3 baris pertama:');
foreach (array_slice($verifyRows, 0, 3) as $r) {
    echo "     " . implode(' | ', $r) . "\n";
}

printHeader('RINGKASAN TASK 1');
echo '  Sumber      : ' . PDF_PATH . "\n";
echo '  Output      : ' . OUTPUT_PATH . "\n";
echo '  Metode      : ' . ($usedFallback ? 'Fallback template prefilled (data tabel PDF berupa gambar - tidak ada teks per kabupaten)' : 'Ekstraksi PDF otomatis') . "\n";
echo '  Baris       : ' . count($verifyRows) . "\n";
echo "\n  Selesai: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";
