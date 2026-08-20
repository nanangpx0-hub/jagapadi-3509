<?php

declare(strict_types=1);

/**
 * Pengambil harga beras aktual dari SISKAPERBAPO Jatim.
 *
 * Simulasi hanya dijalankan bila dipilih eksplisit dan selalu disimpan dengan
 * metode_data=simulasi. Estimasi gabah diturunkan dari beras medium dan diberi
 * label metode/sumber terpisah agar tidak dianggap sebagai observasi resmi.
 */
class HargaKomoditasScraper
{
    private HargaKomoditas $model;
    private bool $debug = false;
    private string $logFile;
    private array $locations = [];

    private const PRICE_RANGES = [
        'gabah_kering_panen' => ['min' => 5000, 'max' => 6500],
        'gabah_kering_giling' => ['min' => 6000, 'max' => 7500],
        'beras_medium' => ['min' => 11000, 'max' => 13000],
        'beras_premium' => ['min' => 13000, 'max' => 16000],
    ];

    private const SEASONAL_MULTIPLIERS = [
        1 => 1.05, 2 => 1.08, 3 => 1.10, 4 => 1.05,
        5 => 1.00, 6 => 0.95, 7 => 0.92, 8 => 0.95,
        9 => 0.98, 10 => 1.00, 11 => 1.02, 12 => 1.03,
    ];

    public function __construct()
    {
        require_once ROOT_PATH . '/app/models/HargaKomoditas.php';
        $this->model = new HargaKomoditas();
        $this->logFile = ROOT_PATH . '/logs/harga_scraper.log';
        $this->loadLocations();
    }

    public function run(array $options = []): array
    {
        $startedAt = microtime(true);
        $year = filter_var($options['year'] ?? date('Y'), FILTER_VALIDATE_INT);
        $month = filter_var($options['month'] ?? date('n'), FILTER_VALIDATE_INT);
        $source = strtolower(trim((string) ($options['source'] ?? $options['data_source'] ?? 'siskaperbapo')));
        if (!empty($options['force_simulation'])) {
            $source = 'simulation';
        }

        $result = [
            'success' => false,
            'no_data' => false,
            'message' => '',
            'source' => $source === 'simulation' ? 'Simulasi eksplisit' : 'SISKAPERBAPO Jatim',
            'records_success' => 0,
            'records_inserted' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
            'errors' => [],
            'execution_time' => 0.0,
        ];

        try {
            $this->validateOptions($year, $month, $source);
            $this->log(sprintf('Starting price scraper for %04d-%02d (source: %s)', $year, $month, $source));

            $data = $source === 'siskaperbapo'
                ? $this->fetchSiskaperbapoData($year, $month)
                : $this->generateSimulatedData($year, $month);

            if ($data === []) {
                $result['no_data'] = true;
                $result['message'] = $source === 'siskaperbapo'
                    ? 'SISKAPERBAPO tidak mengembalikan data Jember untuk periode tersebut. Tidak ada data simulasi yang dibuat otomatis.'
                    : 'Tidak ada tanggal yang dapat disimulasikan pada periode tersebut.';
                $this->model->logActivity('scrape', 'no_data', $result['message'], [
                    'year' => $year,
                    'month' => $month,
                    'source' => $source,
                ]);
                return $this->finishResult($result, $startedAt);
            }

            $affectedCommodities = [];
            foreach ($data as $record) {
                try {
                    $action = $this->model->upsert($record, false);
                    $result['records_success']++;
                    if ($action === 'inserted') {
                        $result['records_inserted']++;
                    } elseif ($action === 'updated') {
                        $result['records_updated']++;
                    } else {
                        $result['records_skipped']++;
                    }
                    $affectedCommodities[$record['jenis_komoditas']] = true;
                } catch (Throwable $e) {
                    $result['records_failed']++;
                    $result['errors'][] = $e->getMessage();
                    $this->log('Failed to save record: ' . $e->getMessage(), 'ERROR');
                }
            }

            foreach (array_keys($affectedCommodities) as $commodity) {
                $this->model->rebuildAlerts($commodity);
            }

            $result['success'] = $result['records_success'] > 0;
            $result['message'] = sprintf(
                '%d observasi diproses dari %s: %d baru, %d diperbarui, %d tidak berubah, %d gagal.',
                count($data),
                $result['source'],
                $result['records_inserted'],
                $result['records_updated'],
                $result['records_skipped'],
                $result['records_failed']
            );
            $this->model->logActivity('scrape', $result['success'] ? 'success' : 'failed', $result['message'], [
                'year' => $year,
                'month' => $month,
                'source' => $source,
                'processed' => count($data),
            ]);
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
            $result['errors'][] = $e->getMessage();
            $this->log($e->getMessage(), 'ERROR');
            $this->model->logActivity('scrape', 'failed', $e->getMessage(), [
                'year' => $year,
                'month' => $month,
                'source' => $source,
            ]);
        }

        return $this->finishResult($result, $startedAt);
    }

    private function validateOptions(int|false $year, int|false $month, string $source): void
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        if ($year === false || $year < 2020 || $year > $currentYear) {
            throw new InvalidArgumentException("Tahun harus antara 2020 dan {$currentYear}");
        }
        if ($month === false || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Bulan harus antara 1 dan 12');
        }
        if ($year === $currentYear && $month > $currentMonth) {
            throw new InvalidArgumentException('Periode masa depan tidak dapat diambil');
        }
        if (!in_array($source, ['siskaperbapo', 'simulation'], true)) {
            throw new InvalidArgumentException('Sumber hanya boleh SISKAPERBAPO atau simulasi eksplisit');
        }
    }

    /**
     * Mengambil sampel tanggal representatif (maksimal 8) agar endpoint eksternal
     * tidak dibebani, tanpa pernah mengganti periode kosong dengan tanggal hari ini.
     */
    private function fetchSiskaperbapoData(int $year, int $month): array
    {
        $targetDates = $this->buildTargetDates($year, $month);
        if ($targetDates === []) {
            return [];
        }

        $records = [];
        $commodityMap = [
            'beras_medium' => 4,
            'beras_premium' => 2,
        ];

        foreach ($targetDates as $date) {
            $mediumPrice = null;
            foreach ($commodityMap as $commodity => $commodityId) {
                $payload = $this->requestSiskaperbapo($date, $commodityId);
                if ($payload === null) {
                    continue;
                }
                $provinceAverage = isset($payload['avg']) && is_numeric($payload['avg'])
                    ? (float) $payload['avg']
                    : null;
                foreach ($payload['data'] as $row) {
                    if (!is_array($row) || stripos((string) ($row['nama'] ?? ''), 'Jember') === false) {
                        continue;
                    }
                    $price = filter_var($row['hrg'] ?? null, FILTER_VALIDATE_FLOAT);
                    if ($price === false || $price < 5000 || $price > 30000) {
                        continue;
                    }
                    $records[] = [
                        'tanggal' => $date,
                        'jenis_komoditas' => $commodity,
                        'harga' => $price,
                        'satuan' => 'Rp/kg',
                        'lokasi' => 'Jember',
                        'kode_wilayah' => '35.09',
                        'sumber_data' => 'SISKAPERBAPO Jatim',
                        'metode_data' => 'aktual',
                        'keterangan' => $provinceAverage === null
                            ? 'Observasi harga Kabupaten Jember dari SISKAPERBAPO Jatim.'
                            : sprintf(
                                'Observasi SISKAPERBAPO Jatim; rerata provinsi pada tanggal yang sama Rp %s/kg.',
                                number_format($provinceAverage, 0, ',', '.')
                            ),
                    ];
                    if ($commodity === 'beras_medium') {
                        $mediumPrice = (float) $price;
                    }
                    break;
                }
            }

            // Estimasi hanya dibuat bila observasi medium pada tanggal yang sama tersedia.
            if ($mediumPrice !== null) {
                foreach ([
                    'gabah_kering_panen' => 0.52,
                    'gabah_kering_giling' => 0.60,
                ] as $commodity => $factor) {
                    $records[] = [
                        'tanggal' => $date,
                        'jenis_komoditas' => $commodity,
                        'harga' => round($mediumPrice * $factor),
                        'satuan' => 'Rp/kg',
                        'lokasi' => 'Jember',
                        'kode_wilayah' => '35.09',
                        'sumber_data' => 'Estimasi turunan SISKAPERBAPO',
                        'metode_data' => 'estimasi',
                        'keterangan' => sprintf(
                            'Estimasi teknis, bukan observasi resmi: %.0f%% dari harga beras medium SISKAPERBAPO pada tanggal yang sama.',
                            $factor * 100
                        ),
                    ];
                }
            }
        }

        return $records;
    }

    private function buildTargetDates(int $year, int $month): array
    {
        $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $today = date('Y-m-d');
        $dates = [];
        for ($day = 1; $day <= $lastDay; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if ($date <= $today) {
                $dates[] = $date;
            }
        }
        if (count($dates) <= 8) {
            return $dates;
        }

        $indexes = [0];
        $lastIndex = count($dates) - 1;
        for ($i = 1; $i < 7; $i++) {
            $indexes[] = (int) round($lastIndex * $i / 7);
        }
        $indexes[] = $lastIndex;
        return array_values(array_unique(array_map(static fn (int $index): string => $dates[$index], $indexes)));
    }

    private function requestSiskaperbapo(string $date, int $commodityId): ?array
    {
        $url = 'https://siskaperbapo.jatimprov.go.id/home2/getDataMap/?' . http_build_query([
            'tanggal' => $date,
            'komoditas' => $commodityId,
        ]);
        $sslVerify = getenv('CURL_SSL_VERIFY') !== 'false';
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Ekstensi cURL tidak dapat diinisialisasi');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'JAGAPADI-SiskaperbapoClient/2.0',
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            $this->log(sprintf('SISKAPERBAPO request failed (%s, HTTP %d): %s', $date, $httpCode, $curlError), 'WARNING');
            return null;
        }
        try {
            $payload = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->log("Invalid SISKAPERBAPO JSON for {$date}: {$e->getMessage()}", 'WARNING');
            return null;
        }
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            $this->log("Unexpected SISKAPERBAPO response for {$date}", 'WARNING');
            return null;
        }
        return $payload;
    }

    private function generateSimulatedData(int $year, int $month): array
    {
        $data = [];
        $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $today = date('Y-m-d');
        $seasonMultiplier = self::SEASONAL_MULTIPLIERS[$month] ?? 1.0;
        $locations = $this->locations ?: $this->getFallbackLocations();

        foreach ($locations as $location) {
            $previousPrices = [];
            $locationName = trim((string) ($location['nama_kecamatan'] ?? 'Jember'));
            for ($day = 1; $day <= $lastDay; $day++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                if ($date > $today) {
                    continue;
                }
                foreach (self::PRICE_RANGES as $commodity => $range) {
                    $min = $range['min'] * $seasonMultiplier;
                    $max = $range['max'] * $seasonMultiplier;
                    $seed = "{$date}|{$locationName}|{$commodity}";
                    if (!isset($previousPrices[$commodity])) {
                        $price = $min + ($max - $min) * $this->deterministicFraction($seed . '|initial');
                    } else {
                        $maxChange = $previousPrices[$commodity] * 0.02;
                        $change = ($this->deterministicFraction($seed . '|change') * 2 - 1) * $maxChange;
                        $price = max($min, min($max, $previousPrices[$commodity] + $change));
                    }
                    $previousPrices[$commodity] = $price;
                    $variation = 0.97 + 0.06 * $this->deterministicFraction($seed . '|location');
                    $data[] = [
                        'tanggal' => $date,
                        'jenis_komoditas' => $commodity,
                        'harga' => round($price * $variation),
                        'satuan' => 'Rp/kg',
                        'lokasi' => $locationName . ', Jember',
                        'kode_wilayah' => $location['kode'] ?? '35.09',
                        'sumber_data' => 'Simulasi',
                        'metode_data' => 'simulasi',
                        'keterangan' => sprintf(
                            'Data simulasi deterministik untuk pengujian; bukan data observasi. Musim: %s, faktor %.2f.',
                            $this->getSeasonLabel($month),
                            $seasonMultiplier
                        ),
                    ];
                }
            }
        }
        return $data;
    }

    private function deterministicFraction(string $seed): float
    {
        $unsigned = (int) sprintf('%u', crc32($seed));
        return $unsigned / 4294967295;
    }

    private function getSeasonLabel(int $month): string
    {
        if (in_array($month, [11, 12, 1, 2, 3, 4], true)) {
            return 'Musim Hujan (Tanam)';
        }
        if (in_array($month, [5, 6, 7, 8], true)) {
            return 'Musim Kemarau (Panen)';
        }
        return 'Peralihan';
    }

    private function loadLocations(): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                'SELECT id, nama_kecamatan, kode FROM master_kecamatan ORDER BY nama_kecamatan LIMIT 10'
            );
            $stmt->execute();
            $this->locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->log('Loaded ' . count($this->locations) . ' locations');
        } catch (Throwable $e) {
            $this->log('Failed to load locations: ' . $e->getMessage(), 'ERROR');
            $this->locations = $this->getFallbackLocations();
        }
    }

    private function getFallbackLocations(): array
    {
        return [
            ['nama_kecamatan' => 'Jember', 'kode' => '35.09'],
            ['nama_kecamatan' => 'Kaliwates', 'kode' => '35.09.29'],
            ['nama_kecamatan' => 'Sumbersari', 'kode' => '35.09.30'],
            ['nama_kecamatan' => 'Patrang', 'kode' => '35.09.31'],
            ['nama_kecamatan' => 'Ambulu', 'kode' => '35.09.05'],
        ];
    }

    private function finishResult(array $result, float $startedAt): array
    {
        $result['errors'] = array_values(array_unique(array_slice($result['errors'], 0, 20)));
        $result['execution_time'] = round(microtime(true) - $startedAt, 2);
        $this->log("Scraper completed in {$result['execution_time']}s");
        return $result;
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        $entry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
        if ($this->debug) {
            echo $entry;
        }
    }

    public function setDebug(bool $enabled): void
    {
        $this->debug = $enabled;
    }
}
