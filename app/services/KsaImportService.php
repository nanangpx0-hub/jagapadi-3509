<?php
/**
 * KSA Import Service
 * Service untuk parsing dan import data KSA (Survei Kerangka Sampel Area) BPS
 * dari dua jenis file:
 *
 * 1. File "Luas Panen dan Produksi Padi 2018-2025 (Angka Tetap)..." — 3 sheet
 *    (luas panen, prod gabah, prod beras), kolom bulan "Jan 18" s/d "Des 25".
 * 2. File bulanan "2026.XX KSA Jatim.xlsx" — sheet "Level KABKOT" dengan blok
 *    kolom Luas Panen / Produksi Padi / Produksi Beras dan penanda
 *    * = potensi, ** = sementara.
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

declare(strict_types=1);

class KsaImportService {

    private const NS_SPREADSHEET = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const BULAN_INDONESIA = [
        'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
        'Mei' => 5, 'Jun' => 6, 'Juni' => 6,
        'Jul' => 7, 'Juli' => 7,
        'Ags' => 8, 'Agus' => 8,
        'Sep' => 9, 'Okt' => 10, 'Nov' => 11, 'Des' => 12,
    ];

    /** Mapping UPPERCASE (dari file KSA) ke nama standar Title Case (38 kab/kota Jatim). */
    private const KABUPATEN_MAP = [
        'PACITAN' => 'Pacitan',
        'PONOROGO' => 'Ponorogo',
        'TRENGGALEK' => 'Trenggalek',
        'TULUNGAGUNG' => 'Tulungagung',
        'BLITAR' => 'Blitar',
        'KEDIRI' => 'Kediri',
        'MALANG' => 'Malang',
        'LUMAJANG' => 'Lumajang',
        'JEMBER' => 'Jember',
        'BANYUWANGI' => 'Banyuwangi',
        'BONDOWOSO' => 'Bondowoso',
        'SITUBONDO' => 'Situbondo',
        'PROBOLINGGO' => 'Probolinggo',
        'PASURUAN' => 'Pasuruan',
        'SIDOARJO' => 'Sidoarjo',
        'MOJOKERTO' => 'Mojokerto',
        'JOMBANG' => 'Jombang',
        'NGANJUK' => 'Nganjuk',
        'MADIUN' => 'Madiun',
        'MAGETAN' => 'Magetan',
        'NGAWI' => 'Ngawi',
        'BOJONEGORO' => 'Bojonegoro',
        'TUBAN' => 'Tuban',
        'LAMONGAN' => 'Lamongan',
        'GRESIK' => 'Gresik',
        'BANGKALAN' => 'Bangkalan',
        'SAMPANG' => 'Sampang',
        'PAMEKASAN' => 'Pamekasan',
        'SUMENEP' => 'Sumenep',
        'KOTA KEDIRI' => 'Kota Kediri',
        'KOTA BLITAR' => 'Kota Blitar',
        'KOTA MALANG' => 'Kota Malang',
        'KOTA PROBOLINGGO' => 'Kota Probolinggo',
        'KOTA PASURUAN' => 'Kota Pasuruan',
        'KOTA MOJOKERTO' => 'Kota Mojokerto',
        'KOTA MADIUN' => 'Kota Madiun',
        'KOTA SURABAYA' => 'Kota Surabaya',
        'KOTA BATU' => 'Kota Batu',
    ];

    /** Tahun minimum data yang dianggap valid untuk file angka tetap. */
    private const TAHUN_MIN_ANGKA_TETAP = 2018;
    private const TAHUN_MAX_ANGKA_TETAP = 2025;

    private DataKsaBulanan $model;

    public function __construct() {
        require_once ROOT_PATH . '/app/models/DataKsaBulanan.php';
        $this->model = new DataKsaBulanan();
    }

    // ============================================================
    // API PUBLIK
    // ============================================================

    /**
     * Import file "Luas Panen dan Produksi Padi 2018-2025 (Angka Tetap)".
     * Membaca 3 sheet (luas panen, prod gabah, prod beras) dengan layout kolom
     * yang identik, lalu upsert ke data_ksa_bulanan dengan status 'tetap'.
     */
    public function importAngkaTetap(string $filePath): array {
        $start = microtime(true);
        $summary = [
            'success' => true,
            'total_processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            if (!file_exists($filePath)) {
                throw new Exception('File tidak ditemukan: ' . $filePath);
            }

            $zip = $this->openXlsx($filePath);
            $shared = $this->loadSharedStrings($zip);

            $sheetLuas = $this->getSheetFile($zip, 'luas panen');
            $sheetGabah = $this->getSheetFile($zip, 'prod gabah');
            $sheetBeras = $this->getSheetFile($zip, 'prod beras');
            if ($sheetLuas === null || $sheetGabah === null || $sheetBeras === null) {
                throw new Exception('Sheet "luas panen"/"prod gabah"/"prod beras" tidak ditemukan');
            }

            $rowsLuas = $this->readSheetRows($zip, $sheetLuas, $shared);
            $rowsGabah = $this->readSheetRows($zip, $sheetGabah, $shared);
            $rowsBeras = $this->readSheetRows($zip, $sheetBeras, $shared);
            $zip->close();

            if (empty($rowsLuas)) {
                throw new Exception('Sheet "luas panen" kosong');
            }

            // --- Deteksi baris header (berisi "Kab/Kota" di kolom 0) ---
            $headerRow = null;
            foreach ($rowsLuas as $rowNum => $cells) {
                if (trim((string) ($cells[0] ?? '')) === 'Kab/Kota') {
                    $headerRow = $rowNum;
                    break;
                }
            }
            if ($headerRow === null) {
                throw new Exception('Baris header (Kab/Kota) tidak ditemukan di sheet "luas panen"');
            }

            // --- Build kolom map: index kolom -> {tahun, bulan} (skip subtotal) ---
            $columnMap = [];
            foreach ($rowsLuas[$headerRow] as $col => $value) {
                $parsed = $this->parseMonthHeader((string) $value);
                if ($parsed !== null) {
                    $tahun = (int) $parsed['tahun'];
                    if ($tahun < self::TAHUN_MIN_ANGKA_TETAP || $tahun > self::TAHUN_MAX_ANGKA_TETAP) {
                        continue;
                    }
                    $columnMap[$col] = [
                        'tahun' => $tahun,
                        'bulan' => (int) $parsed['bulan'],
                        'status' => $parsed['status'],
                    ];
                }
            }
            ksort($columnMap);

            if (empty($columnMap)) {
                throw new Exception('Tidak ada kolom bulan yang valid pada baris header');
            }

            $sumberFile = basename($filePath);
            $kabupatenImported = [];

            foreach ($rowsLuas as $rowNum => $cells) {
                if ($rowNum <= $headerRow) {
                    continue;
                }
                $parsedWilayah = $this->parseKodeNama((string) ($cells[0] ?? ''));
                if ($parsedWilayah === null) {
                    continue; // baris agregat [3500] Jawa Timur atau catatan sumber
                }
                [$kodeWilayah, $namaKabupaten] = $parsedWilayah;

                foreach ($columnMap as $col => $meta) {
                    $luas = $this->parseNumeric($rowsLuas[$rowNum][$col] ?? null);
                    $gabah = $this->parseNumeric($rowsGabah[$rowNum][$col] ?? null);
                    $beras = $this->parseNumeric($rowsBeras[$rowNum][$col] ?? null);

                    $record = [
                        'tahun' => $meta['tahun'],
                        'bulan' => $meta['bulan'],
                        'kabupaten_kota' => $namaKabupaten,
                        'kode_wilayah' => $kodeWilayah,
                        'luas_panen' => $luas,
                        'produksi_gabah' => $gabah,
                        'produksi_beras' => $beras,
                        'produktivitas' => $this->calculateProduktivitas($luas, $gabah),
                        'status_data' => 'tetap',
                        'sumber_file' => $sumberFile,
                        'sumber_sheet' => 'luas panen / prod gabah / prod beras',
                        'keterangan' => 'Angka Tetap BPS 2018-2025 (Survei KSA)',
                    ];

                    $this->countUpsertResult($this->model->upsertWithStatus($record), $summary);
                    $kabupatenImported[$kodeWilayah] = $namaKabupaten;
                }
            }

            $summary['kabupaten'] = count($kabupatenImported);
            $this->model->logImport(
                'ksa_import_tetap',
                'success',
                'Import angka tetap KSA 2018-2025: ' . $sumberFile,
                $summary
            );
        } catch (Exception $e) {
            $summary['success'] = false;
            $summary['errors'][] = $e->getMessage();
            $this->model->logImport('ksa_import_tetap', 'error', $e->getMessage(), []);
        }

        $summary['execution_time'] = round(microtime(true) - $start, 2);
        return $summary;
    }

    /**
     * Import file KSA bulanan (2026.01 s/d 2026.05, sheet "Level KABKOT").
     * Hanya kolom bulan tahun >= 2026 yang diimport; kolom 2025 (riwayat) diabaikan
     * karena sudah tercakup oleh file angka tetap.
     */
    public function importKsaBulanan(string $filePath): array {
        $start = microtime(true);
        $summary = [
            'success' => true,
            'total_processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            if (!file_exists($filePath)) {
                throw new Exception('File tidak ditemukan: ' . $filePath);
            }

            $zip = $this->openXlsx($filePath);
            $shared = $this->loadSharedStrings($zip);

            $sheetFile = $this->getSheetFile($zip, 'Level KABKOT');
            if ($sheetFile === null) {
                throw new Exception('Sheet "Level KABKOT" tidak ditemukan');
            }

            $rows = $this->readSheetRows($zip, $sheetFile, $shared);
            $zip->close();

            if (empty($rows)) {
                throw new Exception('Sheet "Level KABKOT" kosong');
            }
            ksort($rows);
            $rowNums = array_keys($rows);

            // --- Deteksi baris header blok (berisi "Kode\nProvinsi" di kolom 0) ---
            $blockRow = null;
            $monthRow = null;
            foreach ($rowNums as $i => $rn) {
                $c0 = strtoupper((string) ($rows[$rn][0] ?? ''));
                if (str_contains($c0, 'KODE') && str_contains($c0, 'PROVINSI')) {
                    $blockRow = $rn;
                    $monthRow = $rowNums[$i + 1] ?? null;
                    break;
                }
            }
            if ($blockRow === null || $monthRow === null) {
                throw new Exception('Baris header blok/tanggal tidak ditemukan');
            }

            // --- Tipe blok per kolom (baris 1: Luas Panen / Produksi Padi / Produksi Beras) ---
            $blockMap = [];
            $currentBlock = null;
            foreach ($rows[$blockRow] as $col => $value) {
                $block = $this->normalizeBlockName((string) $value);
                if ($block !== null) {
                    $currentBlock = $block;
                }
                if ($currentBlock !== null) {
                    $blockMap[$col] = $currentBlock;
                }
            }

            // --- Kolom bulan per kolom (baris 2) ---
            $colMeta = [];
            foreach ($rows[$monthRow] as $col => $value) {
                $parsed = $this->parseMonthHeader((string) $value);
                if ($parsed === null || !isset($blockMap[$col])) {
                    continue;
                }
                $tahun = (int) $parsed['tahun'];
                if ($tahun < 2026) {
                    continue; // kolom riwayat 2025: sudah diimport dari file angka tetap
                }
                $colMeta[$col] = [
                    'tahun' => $tahun,
                    'bulan' => (int) $parsed['bulan'],
                    'status' => $parsed['status'],
                    'block' => $blockMap[$col],
                ];
            }

            if (empty($colMeta)) {
                throw new Exception('Tidak ada kolom bulan 2026 yang valid pada file ini');
            }

            $sumberFile = basename($filePath);
            $kabupatenImported = [];

            foreach ($rows as $rowNum => $cells) {
                if ($rowNum <= $monthRow) {
                    continue;
                }

                $kode = trim((string) ($cells[2] ?? ''));
                $nama = trim((string) ($cells[3] ?? ''));
                if (!preg_match('/^\d{4}$/', $kode)) {
                    continue; // bukan baris data kabupaten
                }
                if (strtoupper($nama) === 'JAWA TIMUR') {
                    continue; // baris agregat provinsi
                }

                $namaKabupaten = $this->normalizeNama($kode, $nama);
                if ($namaKabupaten === '') {
                    continue;
                }

                // --- Kumpulkan nilai per (tahun, bulan) dari ketiga blok ---
                $records = [];
                foreach ($colMeta as $col => $meta) {
                    $key = $meta['tahun'] . '-' . $meta['bulan'];
                    if (!isset($records[$key])) {
                        $records[$key] = [
                            'tahun' => $meta['tahun'],
                            'bulan' => $meta['bulan'],
                            'status' => 'tetap',
                            'luas' => null,
                            'gabah' => null,
                            'beras' => null,
                        ];
                    }
                    $value = $this->parseNumeric($cells[$col] ?? null);
                    if ($meta['block'] === 'luas') {
                        $records[$key]['luas'] = $value;
                    } elseif ($meta['block'] === 'gabah') {
                        $records[$key]['gabah'] = $value;
                    } elseif ($meta['block'] === 'beras') {
                        $records[$key]['beras'] = $value;
                    }
                    // status record = tingkat paling konservatif (sementara > potensi > tetap)
                    $records[$key]['status'] = $this->maxStatus($records[$key]['status'], $meta['status']);
                }
                ksort($records);

                foreach ($records as $rec) {
                    $record = [
                        'tahun' => $rec['tahun'],
                        'bulan' => $rec['bulan'],
                        'kabupaten_kota' => $namaKabupaten,
                        'kode_wilayah' => $kode,
                        'luas_panen' => $rec['luas'],
                        'produksi_gabah' => $rec['gabah'],
                        'produksi_beras' => $rec['beras'],
                        'produktivitas' => $this->calculateProduktivitas($rec['luas'], $rec['gabah']),
                        'status_data' => $rec['status'],
                        'sumber_file' => $sumberFile,
                        'sumber_sheet' => 'Level KABKOT',
                        'keterangan' => 'KSA Bulanan (Survei Kerangka Sampel Area)',
                    ];

                    $this->countUpsertResult($this->model->upsertWithStatus($record), $summary);
                    $kabupatenImported[$kode] = $namaKabupaten;
                }
            }

            $summary['kabupaten'] = count($kabupatenImported);
            $this->model->logImport(
                'ksa_import_bulanan',
                'success',
                'Import KSA bulanan: ' . $sumberFile,
                $summary
            );
        } catch (Exception $e) {
            $summary['success'] = false;
            $summary['errors'][] = $e->getMessage();
            $this->model->logImport('ksa_import_bulanan', 'error', $e->getMessage(), []);
        }

        $summary['execution_time'] = round(microtime(true) - $start, 2);
        return $summary;
    }

    /**
     * Sinkronisasi agregat tahunan data KSA (status 'tetap') ke tabel
     * data_pertanian_bps yang dipakai dashboard BPS Scraper.
     *
     * Hanya data angka tetap yang lengkap (12 bulan untuk seluruh 38
     * kabupaten/kota) yang boleh dipublikasikan sebagai agregat tahunan.
     */
    public function syncToDataPertanianBps(int $tahun): array {
        $start = microtime(true);
        $summary = [
            'success' => true,
            'tahun' => $tahun,
            'total' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $db = Database::getInstance()->getConnection();
        $ownsTransaction = false;
        $savepoint = null;

        try {
            require_once ROOT_PATH . '/app/models/DataPertanianBps.php';
            require_once ROOT_PATH . '/app/services/BpsDataService.php';
            $bpsModel = new DataPertanianBps();

            $aggregates = $this->model->getAggregateByTahun($tahun, false);
            if (empty($aggregates)) {
                throw new Exception("Tidak ada data KSA status 'tetap' untuk tahun {$tahun}");
            }

            $incomplete = array_values(array_filter(
                $aggregates,
                static fn (array $row): bool => (int) ($row['jumlah_bulan'] ?? 0) !== 12
            ));
            $expectedKabupaten = count(DataPertanianBps::KABUPATEN_JATIM);
            if (count($aggregates) !== $expectedKabupaten || !empty($incomplete)) {
                $notReadyCount = max(0, $expectedKabupaten - count($aggregates)) + count($incomplete);
                $sample = array_slice(array_map(
                    static fn (array $row): string => sprintf(
                        '%s (%d/12 bulan)',
                        (string) ($row['kabupaten_kota'] ?? 'Tidak diketahui'),
                        (int) ($row['jumlah_bulan'] ?? 0)
                    ),
                    $incomplete
                ), 0, 5);
                $detail = !empty($sample) ? ' Contoh: ' . implode(', ', $sample) . '.' : '';
                throw new RuntimeException(
                    "Data KSA {$tahun} belum lengkap: " . count($aggregates)
                    . "/{$expectedKabupaten} kabupaten terdeteksi, tetapi {$notReadyCount}"
                    . " kabupaten belum memiliki angka tetap 12 bulan.{$detail}"
                );
            }

            if ($db->inTransaction()) {
                $savepoint = 'ksa_sync_annual';
                $db->exec("SAVEPOINT {$savepoint}");
            } else {
                $db->beginTransaction();
                $ownsTransaction = true;
            }

            foreach ($aggregates as $agg) {
                $kabupaten = (string) $agg['kabupaten_kota'];
                $existing = $bpsModel->getByYearAndKabupaten(
                    $tahun,
                    $kabupaten,
                    'ksa',
                    'baseline',
                    '35'
                );
                $summary['total']++;

                $data = [
                    'tahun' => $tahun,
                    'kode_provinsi' => '35',
                    'kabupaten_kota' => $kabupaten,
                    'kode_wilayah' => (string) ($agg['kode_wilayah'] ?? ''),
                    'luas_panen' => round((float) ($agg['luas_panen_tahunan'] ?? 0), 2),
                    'produksi_gabah' => round((float) ($agg['produksi_gabah_tahunan'] ?? 0), 2),
                    'produksi_beras' => $agg['produksi_beras_tahunan'] !== null
                        ? round((float) $agg['produksi_beras_tahunan'], 2)
                        : null,
                    'produktivitas' => $agg['produktivitas'] !== null
                        ? round((float) $agg['produktivitas'], 2)
                        : 0.0,
                    'sumber_data' => 'KSA BPS ' . $tahun,
                    'sumber_data_type' => 'ksa',
                    'tipe_skenario' => 'baseline',
                    'is_validated' => 1,
                    'validation_notes' => null,
                    'keterangan' => 'Sumber: Survei KSA BPS, Angka Tetap (12 bulan)',
                ];

                if ($existing) {
                    $ok = $bpsModel->update((int) $existing['id'], $data);
                    if ($ok) {
                        $summary['updated']++;
                    } else {
                        $summary['errors'][] = "Gagal update {$kabupaten} ({$tahun})";
                    }
                } else {
                    $ok = $bpsModel->insert($data);
                    if ($ok) {
                        $summary['inserted']++;
                    } else {
                        $summary['errors'][] = "Gagal insert {$kabupaten} ({$tahun})";
                    }
                }
            }

            if (!empty($summary['errors'])) {
                throw new RuntimeException(implode('; ', $summary['errors']));
            }

            if ($ownsTransaction) {
                $db->commit();
            } elseif ($savepoint !== null) {
                $db->exec("RELEASE SAVEPOINT {$savepoint}");
            }
            $dataService = new BpsDataService();
            $dataService->updateYearlySummary($tahun);

            $this->model->logImport(
                'ksa_sync_annual',
                'success',
                "Sinkronisasi KSA tahun {$tahun} ke data_pertanian_bps",
                $summary
            );
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($savepoint !== null && $db->inTransaction()) {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            $summary['success'] = false;
            $summary['errors'][] = $e->getMessage();
            $this->model->logImport('ksa_sync_annual', 'error', $e->getMessage(), []);
        }

        $summary['execution_time'] = round(microtime(true) - $start, 2);
        return $summary;
    }

    /**
     * Daftar file angka tetap di direktori data KSA.
     */
    public function getAngkaTetapFiles(string $dir): array {
        $files = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*Angka Tetap*.xlsx');
        if ($files === false) {
            return [];
        }
        sort($files);
        return $files;
    }

    /**
     * Daftar file KSA bulanan (2026.XX KSA Jatim.xlsx) di direktori data KSA.
     */
    public function getKsaBulananFiles(string $dir): array {
        $files = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '2026.* KSA Jatim*.xlsx');
        if ($files === false) {
            return [];
        }
        sort($files);
        return $files;
    }

    // ============================================================
    // PARSING XLSX
    // ============================================================

    private function openXlsx(string $filePath): ZipArchive {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('File Excel tidak dapat dibuka: ' . $filePath);
        }
        return $zip;
    }

    private function loadSharedStrings(ZipArchive $zip): array {
        $shared = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return $shared;
        }
        $ss = simplexml_load_string($xml);
        if ($ss === false) {
            return $shared;
        }
        $ss->registerXPathNamespace('m', self::NS_SPREADSHEET);
        $sis = $ss->xpath('//m:si');
        if (empty($sis)) {
            $sis = $ss->si;
        }
        foreach ($sis as $si) {
            $text = '';
            foreach ($si->t as $t) {
                $text .= (string) $t;
            }
            foreach ($si->r as $r) {
                $text .= (string) $r->t;
            }
            $text = preg_replace('/_x([0-9A-Fa-f]{4})_/', '\\u', $text);
            $shared[] = trim((string) $text);
        }
        return $shared;
    }

    private function getSheetFile(ZipArchive $zip, string $sheetName): ?string {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb === false || $rels === false) {
            return null;
        }
        $wbx = simplexml_load_string($wb);
        $relsx = simplexml_load_string($rels);
        if ($wbx === false || $relsx === false) {
            return null;
        }
        foreach ($wbx->sheets->sheet as $s) {
            if ((string) $s['name'] !== $sheetName) {
                continue;
            }
            $rid = (string) $s->attributes(self::NS_RELATIONSHIPS)['id'];
            foreach ($relsx->Relationship as $r) {
                if ((string) $r['Id'] === $rid) {
                    return 'xl/' . ltrim((string) $r['Target'], '/');
                }
            }
        }
        return null;
    }

    /**
     * Baca seluruh baris sheet menjadi [nomorBaris => [indexKolom => nilai]].
     * Posisi kolom berasal dari referensi sel (mis. "BC7" -> index 54),
     * sehingga sel kosong yang di-skip Excel tidak menggeser kolom.
     */
    private function readSheetRows(ZipArchive $zip, string $sheetFile, array $shared): array {
        $xml = $zip->getFromName($sheetFile);
        if ($xml === false) {
            return [];
        }
        $sheet = simplexml_load_string($xml);
        if ($sheet === false) {
            return [];
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $rowEl) {
            $rowNum = (int) $rowEl['r'];
            $cells = [];
            foreach ($rowEl->c as $c) {
                $ref = (string) $c['r'];
                $col = $this->colLetterToIndex($ref);
                if ($col < 0) {
                    continue;
                }
                $type = (string) $c['t'];
                if ($type === 's') {
                    $cells[$col] = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $cells[$col] = trim((string) $c->is->t);
                } else {
                    $cells[$col] = trim((string) $c->v);
                }
            }
            if ($rowNum > 0) {
                $rows[$rowNum] = $cells;
            }
        }
        return $rows;
    }

    private function colLetterToIndex(string $ref): int {
        if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
            return -1;
        }
        $letters = $m[1];
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    // ============================================================
    // PARSING HEADER & NILAI
    // ============================================================

    /**
     * Parse label kolom bulan: "Jan 18", "Feb-26*", "Mar-26**", atau serial
     * tanggal Excel (45658 = 1 Jan 2025). Subtotal ("Jan-Apr 18", "Jan-Des 2025")
     * dan nilai lain mengembalikan null.
     *
     * @return array{tahun:int, bulan:int, status:string}|null
     */
    private function parseMonthHeader(string $value): ?array {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Serial tanggal Excel untuk beberapa kolom 2025 (mis. 45658)
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial > 40000 && $serial < 60000) {
                $ts = (int) (($serial - 25569) * 86400);
                $tahun = (int) gmdate('Y', $ts);
                $bulan = (int) gmdate('n', $ts);
                if ($tahun >= 2010 && $tahun <= 2035) {
                    return ['tahun' => $tahun, 'bulan' => $bulan, 'status' => 'tetap'];
                }
            }
            return null;
        }

        // "Jan 18", "Juni 19", "Feb-26*", "Mar-26**", "Agus 25"
        if (preg_match('/^([A-Za-z]+)[\s-](\d{2,4})\s*(\*{0,2})$/', $value, $m)) {
            $bulanNama = $m[1];
            if (!isset(self::BULAN_INDONESIA[$bulanNama])) {
                return null;
            }
            $tahun = (int) $m[2];
            if ($tahun < 100) {
                $tahun += 2000;
            }
            $stars = $m[3];
            $status = $stars === '' ? 'tetap' : ($stars === '*' ? 'potensi' : 'sementara');
            return ['tahun' => $tahun, 'bulan' => self::BULAN_INDONESIA[$bulanNama], 'status' => $status];
        }

        return null;
    }

    /**
     * Parse "[3501] Pacitan" -> ['3501', 'Pacitan']. Baris agregat "[3500] Jawa Timur"
     * dan teks lain mengembalikan null.
     *
     * @return array{0:string, 1:string}|null
     */
    private function parseKodeNama(string $value): ?array {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\[(\d{4})\]\s*(.+)$/', $value, $m)) {
            return null;
        }
        $kode = $m[1];
        $nama = trim($m[2]);
        if ($kode === '3500' || $nama === '') {
            return null; // agregat provinsi
        }
        $namaNormal = $this->normalizeNama($kode, $nama);
        if ($namaNormal === '') {
            return null;
        }
        return [$kode, $namaNormal];
    }

    /**
     * Normalisasi nama kabupaten/kota dari file KSA (UPPERCASE) ke Title Case
     * standar 38 kab/kota Jatim. Kode 3571-3579 (kota) dipastikan berprefix "Kota".
     */
    private function normalizeNama(string $kode, string $nama): string {
        $upper = strtoupper(preg_replace('/\s+/u', ' ', trim($nama)));

        if (str_starts_with($kode, '357')) {
            $base = str_starts_with($upper, 'KOTA ') ? substr($upper, 5) : $upper;
            $upper = 'KOTA ' . trim($base);
        }

        if (isset(self::KABUPATEN_MAP[$upper])) {
            return self::KABUPATEN_MAP[$upper];
        }

        return $this->titleCase($upper);
    }

    private function titleCase(string $value): string {
        $words = preg_split('/\s+/', trim($value));
        $result = [];
        foreach ($words as $word) {
            $lower = strtolower($word);
            $result[] = strtoupper(substr($lower, 0, 1)) . substr($lower, 1);
        }
        return implode(' ', $result);
    }

    /**
     * Normalisasi angka dengan presisi 4 desimal (rounding floating point
     * artifacts BPS, mis. 4761.8500000000004 -> 4761.85). Nilai "-"/"–" -> null.
     */
    private function parseNumeric(?string $value): ?float {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (in_array($value, ['-', '–', '—'], true)) {
            return null;
        }
        $value = preg_replace('/\s*(ha|ton|ku|ku\/ha|gkg|gkp)\s*$/i', '', $value);
        $value = str_replace(' ', '', $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        } elseif (strpos($value, '.') !== false && preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }
        return round((float) $value, 4);
    }

    private function calculateProduktivitas(?float $luas, ?float $gabah): ?float {
        if ($luas === null || $gabah === null || $luas <= 0) {
            return null;
        }
        return round($gabah / $luas * 10, 4);
    }

    // ============================================================
    // UTILITAS
    // ============================================================

    private function normalizeBlockName(string $value): ?string {
        $norm = strtolower(preg_replace('/[^a-z0-9]/i', '', $value));
        if (str_contains($norm, 'luaspanen')) {
            return 'luas';
        }
        if (str_contains($norm, 'produksipad')) {
            return 'gabah';
        }
        if (str_contains($norm, 'produksiberas')) {
            return 'beras';
        }
        return null;
    }

    private function maxStatus(string $a, string $b): string {
        $rank = ['tetap' => 0, 'potensi' => 1, 'sementara' => 2];
        $ra = $rank[$a] ?? 0;
        $rb = $rank[$b] ?? 0;
        return $ra >= $rb ? $a : $b;
    }

    private function countUpsertResult(int $result, array &$summary): void {
        $summary['total_processed']++;
        if ($result === 1) {
            $summary['inserted']++;
        } elseif ($result === 2) {
            $summary['updated']++;
        } else {
            $summary['skipped']++;
        }
    }
}
