<?php

declare(strict_types=1);

/**
 * Service untuk orkestrasi, validasi, dan eksekusi sinkronisasi media JAGAPADI
 * menggunakan rclone (Pull Model dari cPanel ke Laragon Windows).
 *
 * Mengikuti standar PSR-12, tipe ketat PHP 8.2, serta pencegahan
 * path traversal dan command injection.
 */
class MediaSyncService
{
    public const ALLOWED_COMMANDS = ['copy', 'sync'];
    public const ALLOWED_LOG_LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'ERROR'];
    public const DEFAULT_COMMAND = 'copy';
    public const DEFAULT_TRANSFERS = 4;
    public const DEFAULT_CHECKERS = 8;
    public const DEFAULT_RETRIES = 3;
    public const DEFAULT_LOG_LEVEL = 'INFO';

    private string $baseLocalPath;
    private string $logFile;

    public function __construct(?string $baseLocalPath = null, ?string $logFile = null)
    {
        $this->baseLocalPath = $baseLocalPath !== null
            ? rtrim(str_replace('\\', '/', $baseLocalPath), '/')
            : (defined('ROOT_PATH') ? str_replace('\\', '/', ROOT_PATH) : str_replace('\\', '/', dirname(__DIR__, 2)));

        $this->logFile = $logFile ?? ($this->baseLocalPath . '/storage/logs/rclone_media_sync.log');
    }

    public function getBaseLocalPath(): string
    {
        return $this->baseLocalPath;
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Membangun daftar argumen CLI rclone secara dinamis dan aman.
     *
     * @param array{
     *     command?: string,
     *     remote_name: string,
     *     remote_path: string,
     *     local_path: string,
     *     config_path?: ?string,
     *     dry_run?: bool,
     *     transfers?: int,
     *     checkers?: int,
     *     bandwidth_limit?: ?string,
     *     log_file?: ?string,
     *     log_level?: string,
     *     retries?: int
     * } $options
     * @return array<int, string>
     * @throws InvalidArgumentException
     */
    public function buildRcloneArgs(array $options): array
    {
        $command = strtolower(trim((string)($options['command'] ?? self::DEFAULT_COMMAND)));
        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Perintah rclone "%s" tidak valid. Pilihan yang diizinkan: %s',
                $command,
                implode(', ', self::ALLOWED_COMMANDS)
            ));
        }

        $remoteName = trim((string)($options['remote_name'] ?? ''));
        if ($remoteName === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $remoteName)) {
            throw new InvalidArgumentException(
                'Remote name tidak boleh kosong dan hanya boleh mengandung huruf, angka, underscore, atau tanda hubung'
            );
        }

        $rawRemotePath = (string)($options['remote_path'] ?? '');
        $remotePath = $this->validateRemotePath($rawRemotePath);

        $rawLocalPath = (string)($options['local_path'] ?? '');
        $localPath = $this->validateLocalPath($rawLocalPath);

        $source = sprintf('%s:%s', $remoteName, $remotePath);

        $args = [
            $command,
            $source,
            $localPath,
            '--update',
            '--use-mtime',
            '--contimeout=30s',
            '--timeout=10m',
            '--low-level-retries=10',
            '--stats=10s',
            '--stats-one-line',
        ];

        $transfers = isset($options['transfers']) ? (int)$options['transfers'] : self::DEFAULT_TRANSFERS;
        if ($transfers < 1) {
            throw new InvalidArgumentException('Nilai transfers harus bilangan bulat positif (>= 1)');
        }
        $args[] = sprintf('--transfers=%d', $transfers);

        $checkers = isset($options['checkers']) ? (int)$options['checkers'] : self::DEFAULT_CHECKERS;
        if ($checkers < 1) {
            throw new InvalidArgumentException('Nilai checkers harus bilangan bulat positif (>= 1)');
        }
        $args[] = sprintf('--checkers=%d', $checkers);

        $retries = isset($options['retries']) ? (int)$options['retries'] : self::DEFAULT_RETRIES;
        if ($retries < 1) {
            throw new InvalidArgumentException('Nilai retries harus bilangan bulat positif (>= 1)');
        }
        $args[] = sprintf('--retries=%d', $retries);

        if (!empty($options['config_path'])) {
            $configPath = $this->sanitizeFilePath((string)$options['config_path'], 'config_path');
            $args[] = sprintf('--config=%s', $configPath);
        }

        if (!empty($options['dry_run'])) {
            $args[] = '--dry-run';
        }

        if (!empty($options['bandwidth_limit'])) {
            $bw = trim((string)$options['bandwidth_limit']);
            if (!preg_match('/^[0-9]+([kKmMgGbB](iB)?)?$/', $bw)) {
                throw new InvalidArgumentException(sprintf('Format bandwidth_limit "%s" tidak valid', $bw));
            }
            $args[] = sprintf('--bwlimit=%s', $bw);
        }

        if (!empty($options['log_file'])) {
            $logFile = $this->sanitizeFilePath((string)$options['log_file'], 'log_file');
            $args[] = sprintf('--log-file=%s', $logFile);
        }

        $logLevel = strtoupper(trim((string)($options['log_level'] ?? self::DEFAULT_LOG_LEVEL)));
        if (!in_array($logLevel, self::ALLOWED_LOG_LEVELS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Log level "%s" tidak valid. Pilihan: %s',
                $logLevel,
                implode(', ', self::ALLOWED_LOG_LEVELS)
            ));
        }
        $args[] = sprintf('--log-level=%s', $logLevel);

        return $args;
    }

    /**
     * Memvalidasi dan mensanitasi path lokal, memastikan bebas dari path traversal dan karakter berbahaya.
     *
     * @param string $path Path direktori lokal
     * @param bool $autoCreate Buat direktori otomatis jika belum ada (opsional)
     * @return string Path lokal yang dinormalisasi
     * @throws InvalidArgumentException
     */
    public function validateLocalPath(string $path, bool $autoCreate = false): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new InvalidArgumentException('Local path tidak boleh kosong');
        }

        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Local path mengandung karakter terlarang (null byte)');
        }

        // Cek karakter injeksi shell
        if (preg_match('/[;&|`$><\r\n"\']/', $path)) {
            throw new InvalidArgumentException('Local path mengandung karakter yang berpotensi berbahaya');
        }

        $normalized = str_replace('\\', '/', $path);

        // Deteksi path traversal (".." sebagai segmen path)
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new InvalidArgumentException('Local path terdeteksi mengandung traversal ("..")');
        }

        // Tentukan apakah absolute path (Windows drive e.g. C:/ atau Unix /)
        $isAbsolute = (bool)preg_match('/^[a-zA-Z]:\//', $normalized) || str_starts_with($normalized, '/');

        $fullPath = $isAbsolute
            ? $normalized
            : $this->baseLocalPath . '/' . ltrim($normalized, '/');

        // Pastikan kembali tidak ada traversal pada path gabungan
        if (preg_match('#(^|/)\.\.(/|$)#', $fullPath)) {
            throw new InvalidArgumentException('Full path terdeteksi mengandung traversal ("..")');
        }

        if ($autoCreate && !is_dir($fullPath)) {
            @mkdir($fullPath, 0755, true);
        }

        return $fullPath;
    }

    /**
     * Memvalidasi dan mensanitasi remote path cPanel, mencegah path traversal dan karakter berbahaya.
     *
     * @param string $path Path remote cPanel
     * @return string Path remote yang telah divalidasi
     * @throws InvalidArgumentException
     */
    public function validateRemotePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new InvalidArgumentException('Remote path tidak boleh kosong');
        }

        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Remote path mengandung karakter terlarang (null byte)');
        }

        // Cek karakter injeksi shell atau karakter berbahaya
        if (preg_match('/[;&|`$><\r\n"\'{}]/', $path)) {
            throw new InvalidArgumentException('Remote path mengandung karakter terlarang/injeksi');
        }

        $normalized = str_replace('\\', '/', $path);

        // Deteksi path traversal ("..")
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new InvalidArgumentException('Remote path terdeteksi mengandung traversal ("..")');
        }

        // Hanya izinkan karakter yang valid untuk direktori UNIX cPanel:
        // huruf, angka, slash, titik, minus, underscore, tilde
        if (!preg_match('#^[a-zA-Z0-9_\-\./~]+$#', $normalized)) {
            throw new InvalidArgumentException('Remote path mengandung karakter tidak valid untuk direktori UNIX cPanel');
        }

        // Rapikan multiple slashes berturut-turut
        $cleanPath = preg_replace('#/{2,}#', '/', $normalized) ?? $normalized;

        return $cleanPath;
    }

    /**
     * Mem-parsing output rclone (stdout / stderr) menjadi data terstruktur.
     *
     * @param string $output
     * @return array{
     *     transferred_bytes: string,
     *     total_bytes: string,
     *     bytes_percentage: ?float,
     *     speed: ?string,
     *     eta: ?string,
     *     transferred_files: int,
     *     total_files: int,
     *     checks: int,
     *     total_checks: int,
     *     errors: int,
     *     error_messages: array<int, string>,
     *     elapsed_time: string,
     *     success: bool,
     *     raw_output: string
     * }
     */
    public function parseRcloneOutput(string $output): array
    {
        $output = trim($output);

        $result = [
            'transferred_bytes' => '0 B',
            'total_bytes' => '0 B',
            'bytes_percentage' => null,
            'speed' => null,
            'eta' => null,
            'transferred_files' => 0,
            'total_files' => 0,
            'checks' => 0,
            'total_checks' => 0,
            'errors' => 0,
            'error_messages' => [],
            'elapsed_time' => '0s',
            'success' => true,
            'raw_output' => $output,
        ];

        if ($output === '') {
            return $result;
        }

        // 1. Parsing baris ukuran bytes (Transferred: X MiB / Y MiB, Z%, speed, ETA)
        if (preg_match('/Transferred:\s+([0-9\.]+\s*[a-zA-Z]+)\s*\/\s*([0-9\.]+\s*[a-zA-Z]+)(?:,\s*([0-9\.]+|-)%)?(?:,\s*([^,\r\n]+))?(?:,\s*ETA\s*([0-9a-zA-Z\-]+))?/i', $output, $mBytes)) {
            $result['transferred_bytes'] = trim($mBytes[1]);
            $result['total_bytes'] = trim($mBytes[2]);
            if (isset($mBytes[3]) && is_numeric($mBytes[3])) {
                $result['bytes_percentage'] = (float)$mBytes[3];
            }
            if (isset($mBytes[4]) && trim($mBytes[4]) !== '-') {
                $result['speed'] = trim($mBytes[4]);
            }
            if (isset($mBytes[5]) && trim($mBytes[5]) !== '-') {
                $result['eta'] = trim($mBytes[5]);
            }
        }

        // 2. Parsing baris jumlah file (Transferred: X / Y, Z%)
        if (preg_match('/Transferred:\s+(\d+)\s*\/\s*(\d+)/i', $output, $mFiles)) {
            $result['transferred_files'] = (int)$mFiles[1];
            $result['total_files'] = (int)$mFiles[2];
        }

        // 3. Parsing baris checks (Checks: X / Y, Z%)
        if (preg_match('/Checks:\s+(\d+)\s*\/\s*(\d+)/i', $output, $mChecks)) {
            $result['checks'] = (int)$mChecks[1];
            $result['total_checks'] = (int)$mChecks[2];
        }

        // 4. Parsing stats one-line fallback: e.g. "1.2 MiB / 1.2 MiB, 100%, 250 KiB/s, ETA 0s (xfr#3/3, chk#5/5)"
        if ($result['transferred_files'] === 0 && $result['total_files'] === 0) {
            if (preg_match('/\(xfr#(\d+)\/(\d+)(?:,\s*chk#(\d+)\/(\d+))?\)/i', $output, $mOneLine)) {
                $result['transferred_files'] = (int)$mOneLine[1];
                $result['total_files'] = (int)$mOneLine[2];
                if (isset($mOneLine[3])) {
                    $result['checks'] = (int)$mOneLine[3];
                }
                if (isset($mOneLine[4])) {
                    $result['total_checks'] = (int)$mOneLine[4];
                }
            }
        }

        // Fallback bytes jika dari one-line format
        if ($result['transferred_bytes'] === '0 B') {
            if (preg_match('/([0-9\.]+\s*[a-zA-Z]+)\s*\/\s*([0-9\.]+\s*[a-zA-Z]+)(?:,\s*([0-9\.]+|-)%)?/i', $output, $mAltBytes)) {
                $result['transferred_bytes'] = trim($mAltBytes[1]);
                $result['total_bytes'] = trim($mAltBytes[2]);
                if (isset($mAltBytes[3]) && is_numeric($mAltBytes[3])) {
                    $result['bytes_percentage'] = (float)$mAltBytes[3];
                }
            }
        }

        // 5. Parsing Errors
        if (preg_match('/Errors:\s+(\d+)/i', $output, $mErr)) {
            $result['errors'] = (int)$mErr[1];
        }

        // Ambil baris-baris error individual
        if (preg_match_all('/(?:ERROR\s*:\s*|Failed to\s*)([^\r\n]+)/mi', $output, $errMatches)) {
            $cleanedErrors = array_values(array_unique(array_filter(array_map('trim', $errMatches[0]))));
            $result['error_messages'] = $cleanedErrors;
            if ($result['errors'] === 0 && count($cleanedErrors) > 0) {
                $result['errors'] = count($cleanedErrors);
            }
        }

        // 6. Parsing Elapsed Time
        if (preg_match('/Elapsed time:\s+([0-9a-zA-Z\.]+)/i', $output, $mElapsed)) {
            $result['elapsed_time'] = trim($mElapsed[1]);
        }

        $result['success'] = ($result['errors'] === 0);

        return $result;
    }

    /**
     * Menghasilkan isi file konfigurasi INI rclone dari array parameter profil.
     *
     * @param array<string, array<string, scalar|null>> $params
     * @return string Konten rclone.conf dalam format INI
     * @throws InvalidArgumentException
     */
    public function generateRcloneConfig(array $params): string
    {
        if (empty($params)) {
            return '';
        }

        $lines = [];
        foreach ($params as $section => $options) {
            $section = trim((string)$section);
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $section)) {
                throw new InvalidArgumentException(sprintf(
                    'Nama seksi profil rclone "%s" tidak valid. Hanya boleh alphanumeric, dash, dan underscore.',
                    $section
                ));
            }

            if (!is_array($options)) {
                throw new InvalidArgumentException(sprintf(
                    'Nilai konfigurasi untuk seksi [%s] harus berupa array.',
                    $section
                ));
            }

            $lines[] = sprintf('[%s]', $section);
            foreach ($options as $key => $value) {
                $key = trim((string)$key);
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    throw new InvalidArgumentException(sprintf(
                        'Nama opsi "%s" pada [%s] tidak valid.',
                        $key,
                        $section
                    ));
                }

                if ($value === null) {
                    continue;
                }

                if (is_bool($value)) {
                    $strVal = $value ? 'true' : 'false';
                } else {
                    $strVal = (string)$value;
                }

                $lines[] = sprintf('%s = %s', $key, $strVal);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Mendeteksi lokasi binary rclone di sistem lokal Windows / Laragon.
     *
     * @param ?string $preferredPath
     * @return ?string Path binary rclone jika ditemukan, atau null
     */
    public function findRcloneBinary(?string $preferredPath = null): ?string
    {
        if ($preferredPath !== null && $preferredPath !== '') {
            $preferred = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $preferredPath);
            if (is_file($preferred)) {
                return $preferred;
            }
        }

        $candidates = [
            'C:\\laragon\\bin\\rclone.exe',
            'C:\\Program Files\\rclone\\rclone.exe',
            'C:\\rclone\\rclone.exe',
        ];

        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }

        // Cek dari variabel lingkungan PATH
        $pathEnv = getenv('PATH') ?: getenv('Path');
        if (is_string($pathEnv) && $pathEnv !== '') {
            $paths = explode(PATH_SEPARATOR, $pathEnv);
            foreach ($paths as $p) {
                $p = trim($p);
                if ($p === '') {
                    continue;
                }
                $file = rtrim($p, '\\/') . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'rclone.exe' : 'rclone');
                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Menjalankan proses rclone secara aman melalui proc_open.
     *
     * @param array{
     *     command?: string,
     *     remote_name: string,
     *     remote_path: string,
     *     local_path: string,
     *     config_path?: ?string,
     *     dry_run?: bool,
     *     transfers?: int,
     *     checkers?: int,
     *     bandwidth_limit?: ?string,
     *     log_file?: ?string,
     *     log_level?: string,
     *     retries?: int
     * } $options Opsi sinkronisasi
     * @param ?string $rcloneBinary Path kustom ke binary rclone
     * @return array{
     *     exit_code: int,
     *     stdout: string,
     *     stderr: string,
     *     stats: array,
     *     command_string: string
     * }
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function runSync(array $options, ?string $rcloneBinary = null): array
    {
        $bin = $this->findRcloneBinary($rcloneBinary);
        if ($bin === null) {
            throw new RuntimeException(
                'Binary rclone tidak ditemukan. Pastikan rclone terpasang di C:\laragon\bin\rclone.exe atau terdaftar di PATH.'
            );
        }

        $args = $this->buildRcloneArgs($options);
        $fullCmd = array_merge([$bin], $args);

        // Buat representasi string command untuk logging/debug
        $escapedArgs = array_map(static function (string $arg): string {
            return preg_match('/[\s"\'&|;><]/', $arg) ? '"' . addcslashes($arg, '"\\') . '"' : $arg;
        }, $fullCmd);
        $commandString = implode(' ', $escapedArgs);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($fullCmd, $descriptors, $pipes, $this->baseLocalPath);

        if (!is_resource($process)) {
            throw new RuntimeException('Gagal mengeksekusi proses rclone.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $combinedOutput = trim((string)$stdout . "\n" . (string)$stderr);
        $stats = $this->parseRcloneOutput($combinedOutput);

        return [
            'exit_code' => $exitCode,
            'stdout' => (string)$stdout,
            'stderr' => (string)$stderr,
            'stats' => $stats,
            'command_string' => $commandString,
        ];
    }

    /**
     * Sanitasi path file pendukung (config_path, log_file).
     *
     * @param string $path
     * @param string $paramName
     * @return string
     * @throws InvalidArgumentException
     */
    private function sanitizeFilePath(string $path, string $paramName): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new InvalidArgumentException(sprintf('%s tidak boleh kosong', $paramName));
        }

        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException(sprintf('%s mengandung karakter null byte terlarang', $paramName));
        }

        if (preg_match('/[;&|`$><\r\n"\']/', $path)) {
            throw new InvalidArgumentException(sprintf('%s mengandung karakter berbahaya', $paramName));
        }

        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new InvalidArgumentException(sprintf('%s terdeteksi mengandung traversal ("..")', $paramName));
        }

        return $normalized;
    }
}
