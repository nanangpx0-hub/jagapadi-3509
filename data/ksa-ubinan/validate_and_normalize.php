<?php
/**
 * TASK 2: Validasi & Normalisasi CSV KSA Ubinan
 *
 * Jalankan dari CLI: php validate_and_normalize.php [file.csv]
 * (default input: ksa_ubinan_2025_template_prefilled.csv)
 *
 * Proses:
 * 1. Parse CSV (header case-insensitive, support variasi nama kolom)
 * 2. Normalisasi nama kabupaten (hapus prefix Kab./Kabupaten/Kota, title case,
 *    cocokkan ke daftar standar 38 kab/kota Jatim + kode BPS)
 * 3. Normalisasi angka (format Indonesia "1.234,56" / ribuan "1.234" / desimal "12,5")
 * 4. Validasi: tahun, kabupaten, kode wilayah, luas_panen, produksi_gabah,
 *    produksi_beras, produktivitas + warning bisnis
 * 5. Output:
 *    - ksa_ubinan_2025_normalized.csv (baris valid)
 *    - ksa_ubinan_2025_errors.csv (baris gagal + alasan)
 */

declare(strict_types=1);

// ============================================================
// KONFIGURASI
// ============================================================

$inputPath = $argv[1] ?? __DIR__ . '/ksa_ubinan_2025_template_prefilled.csv';
const OUTPUT_DIR = __DIR__;
const OUTPUT_CLEAN = __DIR__ . '/ksa_ubinan_2025_normalized.csv';
const OUTPUT_ERRORS = __DIR__ . '/ksa_ubinan_2025_errors.csv';
const CURRENT_YEAR = 2026;

/**
 * 38 Kabupaten/Kota Jawa Timur dengan kode BPS (kode => nama).
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

const NAMA_STANDAR = [
    'Pacitan', 'Ponorogo', 'Trenggalek', 'Tulungagung', 'Blitar', 'Kediri',
    'Malang', 'Lumajang', 'Jember', 'Banyuwangi', 'Bondowoso', 'Situbondo',
    'Probolinggo', 'Pasuruan', 'Sidoarjo', 'Mojokerto', 'Jombang', 'Nganjuk',
    'Madiun', 'Magetan', 'Ngawi', 'Bojonegoro', 'Tuban', 'Lamongan', 'Gresik',
    'Bangkalan', 'Sampang', 'Pamekasan', 'Sumenep', 'Kota Kediri',
    'Kota Blitar', 'Kota Malang', 'Kota Probolinggo', 'Kota Pasuruan',
    'Kota Mojokerto', 'Kota Madiun', 'Kota Surabaya', 'Kota Batu',
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
 * Title case sederhana (huruf pertama tiap kata kapital).
 */
function titleCase(string $value): string
{
    $words = preg_split('/\s+/', trim($value));
    $result = [];
    foreach ($words as $word) {
        $lower = strtolower($word);
        $result[] = mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
    }
    return implode(' ', $result);
}

/**
 * Normalisasi nama kabupaten ke daftar standar 38.
 * - Hapus prefix "Kab.", "Kabupaten", "KAB", "Prop.", dll
 * - Title case
 * - Kembalikan nama standar bila cocok, null bila tidak dikenal
 */
function normalizeKabupatenName(string $value): ?string
{
    $clean = trim($value);
    if ($clean === '') {
        return null;
    }

    // Rapikan whitespace
    $clean = preg_replace('/\s+/u', ' ', $clean);
    // Hapus prefix umum (JANGAN hapus "kota", karena nama standar kota = "Kota X")
    $clean = preg_replace('/^(kab\.?|kabupaten|prop\.?|provinsi|pemkab|pemkot)\s+/i', '', $clean);
    // Hapus teks dalam kurung (mis. "(Kab. Jember)")
    $clean = preg_replace('/\(.*?\)/', '', $clean);
    $clean = trim($clean);

    // Normalisasi: "35.09 JEMBER" -> "Jember", atau pola "JEMBER-3509"
    $clean = preg_replace('/^\d{2}\.?\d{2}\s*/', '', $clean);
    $clean = preg_replace('/\s*-?\s*\d{4}$/', '', $clean);
    $clean = preg_replace('/[\.\s]+/u', ' ', trim($clean));

    $candidate = titleCase($clean);

    // 1) Match persis nama standar
    if (in_array($candidate, NAMA_STANDAR, true)) {
        return $candidate;
    }

    // 2) Nama kota tanpa prefix "Kota" yang tidak ambigu (hanya ada versi kota):
    //    Surabaya & Batu tidak punya kabupaten bernama sama.
    if (in_array($candidate, ['Surabaya', 'Batu'], true)) {
        $asKota = 'Kota ' . $candidate;
        if (in_array($asKota, NAMA_STANDAR, true)) {
            return $asKota;
        }
    }

    return null;
}

/**
 * Normalisasi kode wilayah BPS: "35.09" / "3509" -> "3509".
 */
function normalizeKodeWilayah(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $clean = preg_replace('/[^0-9]/', '', trim($value));
    return $clean === '' ? null : $clean;
}

/**
 * Normalisasi angka Indonesia:
 * - "1.234,56" -> 1234.56 (ribuan + desimal koma)
 * - "1,234.56" -> 1234.56 (western)
 * - "1.234"    -> 1234 (titik ribuan)
 * - "12,5"     -> 12.5 (desimal koma)
 * - "1234"     -> 1234
 * Mengembalikan float atau null bila tidak valid.
 */
function normalizeNumber(?string $value): ?float
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $clean = trim($value);
    // Hapus satuan & spasi yang menempel (mis. "1.234 ha", "12,5 ku/ha")
    $clean = preg_replace('/\s*(ha|ton|ku|ku\/ha|gkg|gkp)\s*$/i', '', $clean);
    $clean = str_replace(' ', '', $clean);

    if (str_contains($clean, ',') && str_contains($clean, '.')) {
        // Kedua separator: koma terakhir = desimal (format Indonesia)
        if (strrpos($clean, ',') > strrpos($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }
    } elseif (str_contains($clean, ',')) {
        // Hanya koma -> desimal
        $clean = str_replace(',', '.', $clean);
    } elseif (str_contains($clean, '.')) {
        // Hanya titik: ribuan bila diikuti tepat 3 digit & tidak ada desimal lain
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $clean)) {
            $clean = str_replace('.', '', $clean);
        }
    }

    if (!is_numeric($clean)) {
        return null;
    }

    return (float) $clean;
}

/**
 * Ambil nilai kolom dengan variasi nama header.
 */
function getColumn(array $row, string $key): string
{
    $variants = [
        'tahun' => ['tahun', 'year'],
        'kabupaten_kota' => ['kabupaten_kota', 'kabupaten', 'kota', 'regency', 'nama_kabupaten', 'nama_kota'],
        'kode_wilayah' => ['kode_wilayah', 'kode_bps', 'kode', 'wilayah_id'],
        'luas_panen' => ['luas_panen', 'luas', 'harvest_area'],
        'produksi_gabah' => ['produksi_gabah', 'gabah', 'produksi'],
        'produksi_beras' => ['produksi_beras', 'beras'],
        'produktivitas' => ['produktivitas', 'productivity', 'prod'],
        'keterangan' => ['keterangan', 'notes', 'catatan'],
    ];

    $list = $variants[$key] ?? [$key];
    foreach ($list as $name) {
        if (array_key_exists($name, $row)) {
            return (string) $row[$name];
        }
    }
    return '';
}

/**
 * Tulis CSV (dengan BOM UTF-8).
 */
function writeCsv(string $path, array $rows): int
{
    $handle = fopen($path, 'w');
    if ($handle === false) {
        throw new RuntimeException("Tidak dapat menulis: {$path}");
    }
    fwrite($handle, "\xEF\xBB\xBF");

    $header = ['tahun', 'kabupaten_kota', 'kode_wilayah', 'luas_panen', 'produksi_gabah', 'produksi_beras', 'produktivitas', 'keterangan'];
    fputcsv($handle, $header);

    $count = 0;
    foreach ($rows as $row) {
        fputcsv($handle, [
            $row['tahun'],
            $row['kabupaten_kota'],
            $row['kode_wilayah'],
            $row['luas_panen'],
            $row['produksi_gabah'],
            $row['produksi_beras'],
            $row['produktivitas'],
            $row['keterangan'],
        ]);
        $count++;
    }

    fclose($handle);
    return $count;
}

// ============================================================
// MAIN
// ============================================================

printHeader('TASK 2: Validasi & Normalisasi CSV KSA Ubinan');
printInfo('Input: ' . $inputPath);

if (!file_exists($inputPath)) {
    echo "\n  ERROR: File tidak ditemukan: {$inputPath}\n";
    exit(1);
}

// STEP 1: parse CSV
printHeader('STEP 1: Parse CSV');

$rawRows = [];
if (($handle = fopen($inputPath, 'r')) !== false) {
    $rowNum = 0;
    $headers = [];
    while (($row = fgetcsv($handle)) !== false) {
        $row = array_map('trim', $row);
        if ($rowNum === 0) {
            $headers = array_map('strtolower', $row);
            // Hapus BOM UTF-8 dari header pertama
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            printInfo('Header: ' . implode(', ', $headers));
        } else {
            if (!empty(array_filter($row, fn($v) => $v !== ''))) {
                $item = [];
                foreach ($headers as $idx => $h) {
                    $item[$h] = $row[$idx] ?? '';
                }
                $rawRows[] = ['row' => $rowNum + 1, 'data' => $item];
            }
        }
        $rowNum++;
    }
    fclose($handle);
} else {
    echo "\n  ERROR: Tidak dapat membuka file.\n";
    exit(1);
}

printInfo('Total baris data: ' . count($rawRows));

// STEP 2: normalisasi & validasi
printHeader('STEP 2: Normalisasi & Validasi');

$cleanRows = [];
$errorRows = [];
$allWarnings = [];
$yearMin = 2000;
$yearMax = CURRENT_YEAR + 1;

foreach ($rawRows as $entry) {
    $rowNum = $entry['row'];
    $row = $entry['data'];
    $errors = [];
    $warnings = [];

    $result = [
        'tahun' => null,
        'kabupaten_kota' => null,
        'kode_wilayah' => null,
        'luas_panen' => null,
        'produksi_gabah' => null,
        'produksi_beras' => null,
        'produktivitas' => null,
        'keterangan' => getColumn($row, 'keterangan'),
    ];

    // --- Tahun ---
    $tahunRaw = getColumn($row, 'tahun');
    if ($tahunRaw === '') {
        $errors[] = 'Kolom tahun wajib diisi';
    } else {
        $tahun = (int) $tahunRaw;
        if ($tahun < $yearMin || $tahun > $yearMax) {
            $errors[] = "Tahun '{$tahunRaw}' tidak valid (harus {$yearMin}-{$yearMax})";
        } else {
            $result['tahun'] = $tahun;
        }
    }

    // --- Kabupaten ---
    $kabRaw = getColumn($row, 'kabupaten_kota');
    $kabNormalized = normalizeKabupatenName($kabRaw);
    if ($kabNormalized === null) {
        $errors[] = "Kabupaten '{$kabRaw}' tidak dikenal / terlalu pendek";
    } else {
        $result['kabupaten_kota'] = $kabNormalized;
        if (trim($kabRaw) !== $kabNormalized) {
            $warnings[] = "Nama kabupaten dinormalisasi: '{$kabRaw}' -> '{$kabNormalized}'";
        }
    }

    // --- Kode wilayah ---
    $kodeRaw = getColumn($row, 'kode_wilayah');
    $kodeNormalized = normalizeKodeWilayah($kodeRaw);
    $expectedKode = $result['kabupaten_kota'] !== null
        ? (string) array_search($result['kabupaten_kota'], KABUPATEN_BPS, true)
        : null;
    if ($kodeNormalized !== null) {
        if ($expectedKode !== null && $kodeNormalized !== $expectedKode) {
            $warnings[] = "Kode wilayah '{$kodeNormalized}' tidak cocok untuk {$result['kabupaten_kota']} (seharusnya {$expectedKode})";
        }
        $result['kode_wilayah'] = $expectedKode ?? $kodeNormalized;
    } else {
        $result['kode_wilayah'] = $expectedKode;
        if ($expectedKode !== null && $kodeRaw !== '') {
            $warnings[] = "Kode wilayah tidak valid: '{$kodeRaw}', dipakai kode standar {$expectedKode}";
        }
    }

    // --- Luas panen ---
    $luas = normalizeNumber(getColumn($row, 'luas_panen'));
    if ($luas === null) {
        $luas = 0.0;
        $warnings[] = 'Luas panen kosong/tidak valid, dianggap 0';
    }
    if ($luas < 0) {
        $errors[] = "Luas panen negatif ({$luas})";
    } elseif ($luas > 500000) {
        $warnings[] = "Luas panen terlalu besar ({$luas} ha) - cek nilai";
    }
    $result['luas_panen'] = $luas;

    // --- Produksi gabah ---
    $gabah = normalizeNumber(getColumn($row, 'produksi_gabah'));
    if ($gabah === null) {
        $gabah = 0.0;
        $warnings[] = 'Produksi gabah kosong/tidak valid, dianggap 0';
    }
    if ($gabah < 0) {
        $errors[] = "Produksi gabah negatif ({$gabah})";
    } elseif ($gabah > 5000000) {
        $warnings[] = "Produksi gabah terlalu besar ({$gabah} ton) - cek nilai";
    }
    $result['produksi_gabah'] = $gabah;

    // --- Produksi beras ---
    $beras = normalizeNumber(getColumn($row, 'produksi_beras'));
    if ($beras !== null) {
        if ($beras < 0) {
            $errors[] = "Produksi beras negatif ({$beras})";
        } elseif ($gabah > 0 && $beras > $gabah) {
            $warnings[] = "Produksi beras ({$beras}) lebih besar dari produksi gabah ({$gabah})";
        }
        $result['produksi_beras'] = $beras;
    } else {
        $result['produksi_beras'] = null; // auto-calc saat import
    }

    // --- Produktivitas ---
    $produktivitas = normalizeNumber(getColumn($row, 'produktivitas'));
    if ($produktivitas !== null) {
        if ($produktivitas < 0) {
            $errors[] = "Produktivitas negatif ({$produktivitas})";
        } elseif ($produktivitas > 100) {
            $warnings[] = "Produktivitas sangat tinggi ({$produktivitas} ku/ha)";
        } elseif ($produktivitas > 0 && $produktivitas < 20) {
            $warnings[] = "Produktivitas sangat rendah ({$produktivitas} ku/ha)";
        }
        $result['produktivitas'] = $produktivitas;
    } else {
        $result['produktivitas'] = null; // auto-calc saat import
    }

    if (!empty($errors)) {
        $errorRows[] = [
            'row' => $rowNum,
            'kabupaten_kota' => $kabRaw,
            'tahun' => $tahunRaw,
            'kode_wilayah' => $kodeRaw,
            'luas_panen' => getColumn($row, 'luas_panen'),
            'produksi_gabah' => getColumn($row, 'produksi_gabah'),
            'produksi_beras' => getColumn($row, 'produksi_beras'),
            'produktivitas' => getColumn($row, 'produktivitas'),
            'keterangan' => $result['keterangan'],
            'alasan' => implode('; ', $errors),
        ];
    } else {
        $cleanRows[] = $result;
        foreach ($warnings as $w) {
            $allWarnings[] = "Baris {$rowNum}: {$w}";
        }
    }
}

printInfo('Baris valid: ' . count($cleanRows));
printInfo('Baris error: ' . count($errorRows));

if (!empty($allWarnings)) {
    echo "\n  WARNINGS:\n";
    foreach ($allWarnings as $w) {
        printWarn($w);
    }
}

if (!empty($errorRows)) {
    echo "\n  ERRORS:\n";
    foreach ($errorRows as $e) {
        printWarn("Baris {$e['row']} ({$e['kabupaten_kota']}): {$e['alasan']}");
    }
}

// STEP 3: tulis output
printHeader('STEP 3: Menulis Output');

try {
    $cleanCount = writeCsv(OUTPUT_CLEAN, $cleanRows);
    printOk('CSV valid: ' . OUTPUT_CLEAN . " ({$cleanCount} baris)");
} catch (RuntimeException $e) {
    echo "\n  ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

if (!empty($errorRows)) {
    $handle = fopen(OUTPUT_ERRORS, 'w');
    if ($handle !== false) {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['row', 'tahun', 'kabupaten_kota', 'kode_wilayah', 'luas_panen', 'produksi_gabah', 'produksi_beras', 'produktivitas', 'keterangan', 'alasan']);
        foreach ($errorRows as $e) {
            fputcsv($handle, [
                $e['row'], $e['tahun'], $e['kabupaten_kota'], $e['kode_wilayah'],
                $e['luas_panen'], $e['produksi_gabah'], $e['produksi_beras'], $e['produktivitas'],
                $e['keterangan'], $e['alasan'],
            ]);
        }
        fclose($handle);
        printOk('CSV error: ' . OUTPUT_ERRORS . ' (' . count($errorRows) . ' baris)');
    }
} else {
    if (file_exists(OUTPUT_ERRORS)) {
        unlink(OUTPUT_ERRORS);
    }
    printOk('Tidak ada baris error');
}

// STEP 4: verifikasi
printHeader('STEP 4: Verifikasi');

$kabSet = array_unique(array_map(fn($r) => $r['kabupaten_kota'], $cleanRows));
$missing = array_diff(NAMA_STANDAR, $kabSet);

$verifyErrors = [];
if (count($cleanRows) !== count($kabSet)) {
    $verifyErrors[] = 'Terdapat kabupaten duplikat di output valid';
}
if (!empty($missing) && count($cleanRows) > 0) {
    $verifyErrors[] = 'Kabupaten belum lengkap: ' . implode(', ', $missing);
}
foreach ($cleanRows as $r) {
    if ($r['tahun'] === null || $r['kabupaten_kota'] === null) {
        $verifyErrors[] = 'Baris tanpa tahun/kabupaten lolos validasi';
        break;
    }
}

if (empty($verifyErrors)) {
    printOk('Verifikasi LULUS');
} else {
    foreach ($verifyErrors as $e) {
        printWarn($e);
    }
}

printHeader('RINGKASAN TASK 2');
echo '  Input       : ' . $inputPath . "\n";
echo '  Baris       : ' . count($rawRows) . ' total / ' . count($cleanRows) . ' valid / ' . count($errorRows) . ' error' . "\n";
echo '  Output valid: ' . OUTPUT_CLEAN . "\n";
echo '  Output error: ' . OUTPUT_ERRORS . (empty($errorRows) ? ' (tidak dibuat)' : '') . "\n";
echo '  Kabupaten   : ' . count($kabSet) . '/' . count(NAMA_STANDAR) . ' unik' . "\n";
if (count($cleanRows) > 0 && !empty($kabSet)) {
    echo '  Contoh baris: ' . $cleanRows[0]['kabupaten_kota'] . ' (tahun ' . $cleanRows[0]['tahun'] . ')' . "\n";
}
echo "\n  Selesai: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";
