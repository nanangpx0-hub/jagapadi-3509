<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\SecureImageUploader;
use PHPUnit\Framework\TestCase;

class SecureImageUploaderTest extends TestCase
{
    private string $fixturesDir;
    private string $uploadRoot;
    private string $destDir;
    private string $relativeBase;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__) . '/fixtures/images';
        $this->uploadRoot = dirname(__DIR__, 2) . '/public';
        $this->destDir = $this->uploadRoot . '/assets/uploads/test';
        $this->relativeBase = 'assets/uploads/test';

        if (!is_dir($this->destDir)) {
            mkdir($this->destDir, 0755, true);
        }

        SecureImageUploader::$testMode = true;
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->destDir);
    }

    public function testValidJpegUpload(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        copy($this->fixturesDir . '/1x1.jpg', $tmpFile);

        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $result = SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 10485760,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);

        $this->assertArrayHasKey('foto_url', $result);
        $this->assertStringContainsString('assets/uploads/test/', $result['foto_url']);
        $this->assertStringEndsWith('.jpg', $result['foto_url']);
        $this->assertArrayHasKey('bytes', $result);
        $this->assertArrayHasKey('mime', $result);
        $this->assertArrayHasKey('full_path', $result);
        $this->assertFileExists($result['full_path']);

        unlink($tmpFile);
    }

    public function testValidPngUpload(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.png';
        copy($this->fixturesDir . '/1x1.png', $tmpFile);

        $file = [
            'name' => 'test.png',
            'type' => 'image/png',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $result = SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 10485760,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);

        $this->assertStringEndsWith('.png', $result['foto_url']);
        $this->assertFileExists($result['full_path']);

        unlink($tmpFile);
    }

    public function testRejectPhpFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.php';
        copy($this->fixturesDir . '/fake.php', $tmpFile);

        $file = [
            'name' => 'fake.php',
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('bukan gambar');

        SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 10485760,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);

        unlink($tmpFile);
    }

    public function testRejectEmptyFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.bin';
        copy($this->fixturesDir . '/empty.bin', $tmpFile);

        $file = [
            'name' => 'empty.bin',
            'type' => 'application/octet-stream',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 0,
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('File kosong');

        SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 10485760,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);

        unlink($tmpFile);
    }

    public function testRejectOversizedFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        copy($this->fixturesDir . '/1x1.jpg', $tmpFile);

        $file = [
            'name' => 'big.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 99999,
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ukuran file');

        SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 100,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);

        unlink($tmpFile);
    }

    public function testRejectNoFileUpload(): void
    {
        $file = [
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tidak ada file');

        SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 10485760,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);
    }

    public function testRejectInvalidExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.gif';
        copy($this->fixturesDir . '/1x1.jpg', $tmpFile);

        $file = [
            'name' => 'test.gif',
            'type' => 'image/gif',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ekstensi file');

        SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 10485760,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);

        unlink($tmpFile);
    }

    public function testDeleteOldPhotoValid(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        copy($this->fixturesDir . '/1x1.jpg', $tmpFile);

        $file = [
            'name' => 'del_test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $result = SecureImageUploader::validateAndStore($file, [
            'max_bytes' => 10485760,
            'destination_dir' => $this->destDir,
            'relative_base' => $this->relativeBase,
        ]);

        $this->assertFileExists($result['full_path']);

        $deleted = SecureImageUploader::deleteOldPhoto($this->uploadRoot, $result['foto_url']);
        $this->assertTrue($deleted);
        $this->assertFileDoesNotExist($result['full_path']);

        unlink($tmpFile);
    }

    public function testDeleteOldPhotoEmptyUrl(): void
    {
        $this->assertFalse(SecureImageUploader::deleteOldPhoto($this->uploadRoot, ''));
    }

    public function testDeleteOldPhotoPathTraversal(): void
    {
        $deleted = SecureImageUploader::deleteOldPhoto($this->uploadRoot, '../../../etc/passwd');
        $this->assertFalse($deleted);
    }

    public function testRequiresDestinationDir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SecureImageUploader::validateAndStore([], []);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rmdirRecursive($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
