<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Penangan job scraper latar belakang (ADR-011 Tahap 2 Fase 3).
 *
 * Dieksekusi oleh `backend/scripts/queue-worker.php` (CLI/cron/daemon),
 * bukan di thread request HTTP — controller web hanya `push()` lalu
 * mengembalikan 202 Accepted. Setiap handler:
 * - dibatasi timeout per-job oleh worker (procurement via $timeoutSeconds),
 * - mengembalikan fallback simulasi deterministik bila sumber eksternal
 *   gagal, dan menandai `fallback_used=true` + `stale=true` bila perlu,
 * - menyimpan status last-success ke file JSON agar dashboard ops dapat
 *   membaca `GET /admin/health` tanpa query eksternal.
 *
 * Kelas ini tidak membuka koneksi database; penyimpanan dibatasi pada
 * file cache JSON (injectable untuk test in-memory).
 */
final class ScraperJobService
{
    public const TYPE_NASA_WIND = 'nasa_wind';
    public const TYPE_BPS_SYNC = 'bps_sync';

    /** @var list<string> */
    private const KNOWN_TYPES = [self::TYPE_NASA_WIND, self::TYPE_BPS_SYNC];

    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Dispatch job antrean berdasarkan `type`.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed> hasil handler (selalu menyertakan `success`)
     */
    public function dispatch(array $payload, int $timeoutSeconds = 30): array
    {
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        if (!in_array($type, self::KNOWN_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown scraper job type: {$type}");
        }

        $started = microtime(true);
        try {
            $result = $type === self::TYPE_NASA_WIND
                ? $this->handleNasaWind($payload, $timeoutSeconds)
                : $this->handleBpsSync($payload, $timeoutSeconds);
        } catch (\InvalidArgumentException $e) {
            // Input tidak valid (periode/tipe) adalah kesalahan programmer:
            // fail-closed agar worker retry/DLQ, bukan fallback sunyi.
            throw $e;
        } catch (\Throwable $e) {
            Logger::warning('Scraper job failed, applying simulation fallback', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            $result = $this->simulationFallback($type, $payload, $e->getMessage());
        }

        $result['execution_time'] = round(microtime(true) - $started, 2);
        $this->recordLastSuccess($type, $result);

        return $result;
    }

    /**
     * @param array<string,mixed> $params year, month, force_simulation
     * @return array<string,mixed>
     */
    public function handleNasaWind(array $params, int $timeoutSeconds = 30): array
    {
        $year = (int) ($params['year'] ?? date('Y'));
        $month = (int) ($params['month'] ?? date('n'));
        $this->assertValidPeriod($year, $month);

        if (filter_var($params['force_simulation'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->simulationFallback(self::TYPE_NASA_WIND, $params, 'forced simulation');
        }

        $records = $this->fetchNasaPower($year, $month, $timeoutSeconds);
        if ($records === []) {
            return $this->simulationFallback(self::TYPE_NASA_WIND, $params, 'NASA POWER empty response');
        }

        return [
            'success' => true,
            'type' => self::TYPE_NASA_WIND,
            'source' => 'NASA POWER (WS10M/WS2M)',
            'year' => $year,
            'month' => $month,
            'records' => $records,
            'records_count' => count($records),
            'fallback_used' => false,
            'stale' => false,
        ];
    }

    /**
     * @param array<string,mixed> $params year, force_simulation
     * @return array<string,mixed>
     */
    public function handleBpsSync(array $params, int $timeoutSeconds = 30): array
    {
        $year = (int) ($params['year'] ?? date('Y'));

        if (filter_var($params['force_simulation'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->simulationFallback(self::TYPE_BPS_SYNC, $params, 'forced simulation');
        }

        $records = $this->fetchBps($year, $timeoutSeconds);
        if ($records === []) {
            return $this->simulationFallback(self::TYPE_BPS_SYNC, $params, 'BPS source empty response');
        }

        return [
            'success' => true,
            'type' => self::TYPE_BPS_SYNC,
            'source' => 'BPS',
            'year' => $year,
            'records' => $records,
            'records_count' => count($records),
            'fallback_used' => false,
            'stale' => false,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchNasaPower(int $year, int $month, int $timeoutSeconds): array
    {
        $endpoint = (string) ($this->nasaEndpoint($year, $month));
        $body = $this->httpGet($endpoint, max(1, min($timeoutSeconds, 15)));
        if ($body === null) {
            return [];
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [];
        }
        $daily = $json['properties']['parameter']['WS10M'] ?? null;
        if (!is_array($daily) || $daily === []) {
            return [];
        }

        $records = [];
        foreach ($daily as $rawDate => $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $records[] = [
                'tanggal' => $this->normalizeNasaDate((string) $rawDate),
                'kecepatan_angin' => round(((float) $value) * 3.6, 2),
                'satuan' => 'km/h',
                'sumber_data' => 'NASA POWER (WS10M/WS2M)',
            ];
        }

        return $records;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchBps(int $year, int $timeoutSeconds): array
    {
        // Endpoint BPS eksternal belum dikontrakkan di Backend v1 (ADR-011:
        // scraper parkir). Hingga kontrak ada, kembalikan kosong agar
        // fallback simulasi yang deterministik dipakai dan ditandai stale.
        unset($year, $timeoutSeconds);

        return [];
    }

    private function nasaEndpoint(int $year, int $month): string
    {
        $monthPad = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $lastDay = (string) cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $query = http_build_query([
            'parameters' => 'WS10M',
            'community' => 'AG',
            'longitude' => '113.70',
            'latitude' => '-8.17',
            'start' => "{$year}{$monthPad}01",
            'end' => "{$year}{$monthPad}{$lastDay}",
            'format' => 'JSON',
        ]);

        return 'https://power.larc.nasa.gov/api/temporal/daily/point?' . $query;
    }

    private function httpGet(string $url, int $timeoutSeconds): ?string
    {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create(['http' => ['timeout' => $timeoutSeconds, 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $ctx);
            return is_string($body) && $body !== '' ? $body : null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min($timeoutSeconds, 5),
            CURLOPT_USERAGENT => 'JAGAPADI-QueueWorker/1.0 (+https://jagapadi.local)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $code !== 200 || !is_string($body) || $body === '') {
            return null;
        }

        return $body;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function simulationFallback(string $type, array $params, string $reason): array
    {
        $year = (int) ($params['year'] ?? date('Y'));
        $month = (int) ($params['month'] ?? date('n'));
        $seed = (int) sprintf('%u', crc32($type . '|' . $year . '-' . $month));

        $records = [];
        if ($type === self::TYPE_NASA_WIND) {
            $days = cal_days_in_month(CAL_GREGORIAN, max(1, min(12, $month)), $year);
            $base = ($month >= 6 && $month <= 9) ? 15.0 : 10.0;
            for ($day = 1; $day <= min($days, 3); $day++) {
                $avg = $base + ((($seed + $day) % 101) - 50) / 10;
                $records[] = [
                    'tanggal' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                    'kecepatan_angin' => round(max(0.0, $avg), 2),
                    'satuan' => 'km/h',
                    'sumber_data' => 'Simulasi',
                ];
            }
        } else {
            for ($i = 0; $i < 2; $i++) {
                $records[] = [
                    'tahun' => $year,
                    'komoditas' => 'Padi',
                    'produksi_ton' => 1000 + (($seed + $i * 37) % 500),
                    'sumber_data' => 'Simulasi',
                ];
            }
        }

        return [
            'success' => true,
            'type' => $type,
            'source' => 'Simulasi',
            'year' => $year,
            'month' => $month,
            'records' => $records,
            'records_count' => count($records),
            'fallback_used' => true,
            'fallback_reason' => $reason,
            'stale' => true,
        ];
    }

    /**
     * @param array<string,mixed> $result
     */
    private function recordLastSuccess(string $type, array $result): void
    {
        $file = $this->cacheDir . '/scraper_' . $type . '_last_success.json';
        $payload = [
            'type' => $type,
            'at' => date('Y-m-d H:i:s'),
            'success' => (bool) ($result['success'] ?? false),
            'source' => (string) ($result['source'] ?? ''),
            'records_count' => (int) ($result['records_count'] ?? 0),
            'fallback_used' => (bool) ($result['fallback_used'] ?? false),
            'stale' => (bool) ($result['stale'] ?? false),
        ];
        @file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function assertValidPeriod(int $year, int $month): void
    {
        $currentYear = (int) date('Y');
        if ($month < 1 || $month > 12 || $year < 2000 || $year > $currentYear + 1) {
            throw new \InvalidArgumentException('Periode scraper tidak valid.');
        }
    }

    private function normalizeNasaDate(string $raw): string
    {
        if (strlen($raw) === 8 && ctype_digit($raw)) {
            return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
        }
        return $raw;
    }
}
