<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit Test untuk MediaSyncService
 * Menguji pembentukan CLI arguments, validasi path lokal & remote,
 * mitigasi path traversal / injeksi, parsing output rclone, dan generator config.
 */
final class MediaSyncServiceTest extends TestCase
{
    private MediaSyncService $service;
    private string $dummyBasePath;

    protected function setUp(): void
    {
        $this->dummyBasePath = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/jagapadi_test_' . bin2hex(random_bytes(4));
        if (!is_dir($this->dummyBasePath)) {
            mkdir($this->dummyBasePath, 0755, true);
        }
        $this->service = new MediaSyncService($this->dummyBasePath);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dummyBasePath)) {
            $this->removeDirectoryRecursive($this->dummyBasePath);
        }
    }

    // =========================================================================
    // 1. Pengujian buildRcloneArgs()
    // =========================================================================

    public function testBuildRcloneArgsDefaultOptions(): void
    {
        $args = $this->service->buildRcloneArgs([
            'remote_name' => 'cpanel_sftp',
            'remote_path' => '/public_html/public/uploads',
            'local_path'  => 'public/uploads',
        ]);

        self::assertContains('copy', $args);
        self::assertContains('cpanel_sftp:/public_html/public/uploads', $args);
        self::assertContains($this->dummyBasePath . '/public/uploads', $args);
        self::assertContains('--update', $args);
        self::assertContains('--use-mtime', $args);
        self::assertContains('--transfers=4', $args);
        self::assertContains('--checkers=8', $args);
        self::assertContains('--retries=3', $args);
        self::assertContains('--contimeout=30s', $args);
        self::assertContains('--timeout=10m', $args);
        self::assertContains('--low-level-retries=10', $args);
        self::assertContains('--stats=10s', $args);
        self::assertContains('--stats-one-line', $args);
        self::assertContains('--log-level=INFO', $args);
        self::assertNotContains('--dry-run', $args);
    }

    public function testBuildRcloneArgsSyncModeAndDryRun(): void
    {
        $args = $this->service->buildRcloneArgs([
            'command'     => 'sync',
            'remote_name' => 'cpanel_ftp',
            'remote_path' => '/public/uploads',
            'local_path'  => 'public/uploads',
            'dry_run'     => true,
            'transfers'   => 2,
            'checkers'    => 4,
            'retries'     => 5,
        ]);

        self::assertSame('sync', $args[0]);
        self::assertContains('--dry-run', $args);
        self::assertContains('--transfers=2', $args);
        self::assertContains('--checkers=4', $args);
        self::assertContains('--retries=5', $args);
    }

    public function testBuildRcloneArgsBandwidthLimitAndCustomConfig(): void
    {
        $args = $this->service->buildRcloneArgs([
            'remote_name'     => 'cpanel_sftp',
            'remote_path'     => '/public_html/public/uploads',
            'local_path'      => 'public/uploads',
            'bandwidth_limit' => '10M',
            'config_path'     => 'config/rclone.conf',
            'log_file'        => 'storage/logs/sync.log',
            'log_level'       => 'DEBUG',
        ]);

        self::assertContains('--bwlimit=10M', $args);
        self::assertContains('--config=config/rclone.conf', $args);
        self::assertContains('--log-file=storage/logs/sync.log', $args);
        self::assertContains('--log-level=DEBUG', $args);
    }

    public function testBuildRcloneArgsRejectsInvalidCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Perintah rclone "delete" tidak valid');

        $this->service->buildRcloneArgs([
            'command'     => 'delete',
            'remote_name' => 'cpanel_sftp',
            'remote_path' => '/public_html',
            'local_path'  => 'public/uploads',
        ]);
    }

    public function testBuildRcloneArgsRejectsInvalidRemoteName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Remote name tidak boleh kosong dan hanya boleh mengandung');

        $this->service->buildRcloneArgs([
            'remote_name' => 'cpanel;rm -rf',
            'remote_path' => '/public_html',
            'local_path'  => 'public/uploads',
        ]);
    }

    public function testBuildRcloneArgsRejectsInvalidBandwidthLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format bandwidth_limit');

        $this->service->buildRcloneArgs([
            'remote_name'     => 'cpanel_sftp',
            'remote_path'     => '/public_html',
            'local_path'      => 'public/uploads',
            'bandwidth_limit' => 'unlimited_fast',
        ]);
    }

    public function testBuildRcloneArgsRejectsInvalidLogLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Log level "SUPERVERBOSE" tidak valid');

        $this->service->buildRcloneArgs([
            'remote_name' => 'cpanel_sftp',
            'remote_path' => '/public_html',
            'local_path'  => 'public/uploads',
            'log_level'   => 'SUPERVERBOSE',
        ]);
    }

    // =========================================================================
    // 2. Pengujian validateLocalPath()
    // =========================================================================

    public function testValidateLocalPathRelativeAndAbsolute(): void
    {
        $relative = $this->service->validateLocalPath('public/uploads');
        self::assertSame($this->dummyBasePath . '/public/uploads', $relative);

        $dummySub = $this->dummyBasePath . '/storage/media';
        $absolute = $this->service->validateLocalPath($dummySub);
        self::assertSame($dummySub, $absolute);
    }

    public function testValidateLocalPathRejectsDirectoryTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('traversal');

        $this->service->validateLocalPath('../../etc/passwd');
    }

    public function testValidateLocalPathRejectsInlineTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('traversal');

        $this->service->validateLocalPath('public/../secrets');
    }

    public function testValidateLocalPathRejectsNullBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('null byte');

        $this->service->validateLocalPath("public/uploads\0injection");
    }

    public function testValidateLocalPathRejectsDangerousShellCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('karakter yang berpotensi berbahaya');

        $this->service->validateLocalPath('public/uploads; echo pwned');
    }

    public function testValidateLocalPathRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Local path tidak boleh kosong');

        $this->service->validateLocalPath('   ');
    }

    // =========================================================================
    // 3. Pengujian validateRemotePath()
    // =========================================================================

    public function testValidateRemotePathValidPaths(): void
    {
        $path1 = $this->service->validateRemotePath('/public_html/public/uploads');
        self::assertSame('/public_html/public/uploads', $path1);

        $path2 = $this->service->validateRemotePath('public/uploads');
        self::assertSame('public/uploads', $path2);

        $path3 = $this->service->validateRemotePath('//home//jagapadi//public_html//uploads');
        self::assertSame('/home/jagapadi/public_html/uploads', $path3);
    }

    public function testValidateRemotePathRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Remote path tidak boleh kosong');

        $this->service->validateRemotePath('');
    }

    public function testValidateRemotePathRejectsTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('traversal');

        $this->service->validateRemotePath('/public_html/../../etc/shadow');
    }

    public function testValidateRemotePathRejectsCommandInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('karakter terlarang/injeksi');

        $this->service->validateRemotePath('/public_html/uploads | rm -rf /');
    }

    public function testValidateRemotePathRejectsNullBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('null byte');

        $this->service->validateRemotePath("/public_html/\0/uploads");
    }

    // =========================================================================
    // 4. Pengujian parseRcloneOutput()
    // =========================================================================

    public function testParseRcloneOutputStandardMultiline(): void
    {
        $output = <<<OUTPUT
Transferred:   	    1.234 MiB / 5.678 MiB, 22%, 250.12 KiB/s, ETA 17s
Checks:                12 / 12, 100%
Transferred:            5 / 20, 25%
Elapsed time:        5.2s
Errors:                 0
OUTPUT;

        $stats = $this->service->parseRcloneOutput($output);

        self::assertSame('1.234 MiB', $stats['transferred_bytes']);
        self::assertSame('5.678 MiB', $stats['total_bytes']);
        self::assertSame(22.0, $stats['bytes_percentage']);
        self::assertSame('250.12 KiB/s', $stats['speed']);
        self::assertSame('17s', $stats['eta']);
        self::assertSame(5, $stats['transferred_files']);
        self::assertSame(20, $stats['total_files']);
        self::assertSame(12, $stats['checks']);
        self::assertSame(12, $stats['total_checks']);
        self::assertSame(0, $stats['errors']);
        self::assertSame('5.2s', $stats['elapsed_time']);
        self::assertTrue($stats['success']);
    }

    public function testParseRcloneOutputOneLineStats(): void
    {
        $output = "2026/09/17 13:00:00 NOTICE: 3.500 MiB / 3.500 MiB, 100%, 500.00 KiB/s, ETA 0s (xfr#8/8, chk#14/14)";

        $stats = $this->service->parseRcloneOutput($output);

        self::assertSame('3.500 MiB', $stats['transferred_bytes']);
        self::assertSame('3.500 MiB', $stats['total_bytes']);
        self::assertSame(8, $stats['transferred_files']);
        self::assertSame(8, $stats['total_files']);
        self::assertSame(14, $stats['checks']);
        self::assertSame(14, $stats['total_checks']);
        self::assertSame(0, $stats['errors']);
        self::assertTrue($stats['success']);
    }

    public function testParseRcloneOutputWithErrors(): void
    {
        $output = <<<OUTPUT
2026/09/17 13:05:10 ERROR : upload_1.jpg: Failed to copy: connection timed out
2026/09/17 13:05:12 ERROR : upload_2.png: Failed to copy: permission denied
Errors:                 2 (retrying may help)
Elapsed time:        15.4s
OUTPUT;

        $stats = $this->service->parseRcloneOutput($output);

        self::assertSame(2, $stats['errors']);
        self::assertCount(2, $stats['error_messages']);
        self::assertFalse($stats['success']);
        self::assertSame('15.4s', $stats['elapsed_time']);
    }

    public function testParseRcloneOutputEmptyString(): void
    {
        $stats = $this->service->parseRcloneOutput('');

        self::assertSame('0 B', $stats['transferred_bytes']);
        self::assertSame(0, $stats['transferred_files']);
        self::assertSame(0, $stats['errors']);
        self::assertSame('0s', $stats['elapsed_time']);
        self::assertTrue($stats['success']);
    }

    // =========================================================================
    // 5. Pengujian generateRcloneConfig()
    // =========================================================================

    public function testGenerateRcloneConfigSuccess(): void
    {
        $config = $this->service->generateRcloneConfig([
            'cpanel_sftp' => [
                'type' => 'sftp',
                'host' => 'jagapadi.bpsjember.my.id',
                'user' => 'jagapadi_cpanel',
                'port' => 22,
                'pass' => 'encrypted_hash',
            ],
            'cpanel_ftp' => [
                'type'         => 'ftp',
                'host'         => 'jagapadi.bpsjember.my.id',
                'user'         => 'ftp_user@jagapadi.bpsjember.my.id',
                'port'         => 21,
                'pass'         => 'encrypted_hash',
                'tls'          => true,
                'explicit_tls' => true,
            ],
        ]);

        self::assertStringContainsString('[cpanel_sftp]', $config);
        self::assertStringContainsString('type = sftp', $config);
        self::assertStringContainsString('host = jagapadi.bpsjember.my.id', $config);
        self::assertStringContainsString('port = 22', $config);
        self::assertStringContainsString('[cpanel_ftp]', $config);
        self::assertStringContainsString('tls = true', $config);
        self::assertStringContainsString('explicit_tls = true', $config);
    }

    public function testGenerateRcloneConfigRejectsInvalidSectionName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nama seksi profil rclone');

        $this->service->generateRcloneConfig([
            'bad section [name]' => [
                'type' => 'sftp',
            ],
        ]);
    }

    public function testGenerateRcloneConfigRejectsInvalidKeyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nama opsi');

        $this->service->generateRcloneConfig([
            'cpanel_sftp' => [
                'bad key = injection' => 'value',
            ],
        ]);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function removeDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
