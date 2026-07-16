<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\ImageCompressor;
use PHPUnit\Framework\TestCase;

class ImageCompressorTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__) . '/fixtures/images';
    }

    public function testCompressJpeg(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'img_') . '.jpg';
        copy($this->fixturesDir . '/1x1.jpg', $tmpFile);
        $origSize = filesize($tmpFile);

        ImageCompressor::compress($tmpFile);

        $this->assertFileExists($tmpFile);
        unlink($tmpFile);
    }

    public function testCompressPng(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'img_') . '.png';
        copy($this->fixturesDir . '/1x1.png', $tmpFile);

        ImageCompressor::compress($tmpFile);

        $this->assertFileExists($tmpFile);
        unlink($tmpFile);
    }

    public function testCompressInvalidPath(): void
    {
        ImageCompressor::compress('/nonexistent/path.jpg');
        $this->assertTrue(true);
    }

    public function testCompressNonImageFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'img_') . '.php';
        file_put_contents($tmpFile, '<?php echo "hi";');

        ImageCompressor::compress($tmpFile);

        $this->assertFileExists($tmpFile);
        unlink($tmpFile);
    }
}
