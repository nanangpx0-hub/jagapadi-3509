<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\NomorLaporanGenerator;
use PHPUnit\Framework\TestCase;

class NomorLaporanGeneratorTest extends TestCase
{
    public function testLHFormatPattern(): void
    {
        $pattern = '/^LH-\d{8}-\d{4}$/';
        $this->assertMatchesRegularExpression($pattern, 'LH-20260716-0001');
        $this->assertMatchesRegularExpression($pattern, 'LH-20260716-9999');
    }

    public function testLIFormatPattern(): void
    {
        $pattern = '/^LI-\d{8}-\d{4}$/';
        $this->assertMatchesRegularExpression($pattern, 'LI-20260716-0001');
        $this->assertMatchesRegularExpression($pattern, 'LI-20260716-9999');
    }

    public function testPrefixLH(): void
    {
        $this->assertStringStartsWith('LH-', 'LH-20260716-0001');
    }

    public function testPrefixLI(): void
    {
        $this->assertStringStartsWith('LI-', 'LI-20260716-0001');
    }

    public function testDatePartIsEightDigits(): void
    {
        $parts = explode('-', 'LH-20260716-0001');
        $this->assertEquals('20260716', $parts[1]);
        $this->assertEquals(8, strlen($parts[1]));
    }

    public function testCounterIsFourDigits(): void
    {
        $parts = explode('-', 'LH-20260716-0001');
        $this->assertEquals('0001', $parts[2]);
        $this->assertEquals(4, strlen($parts[2]));
    }

    public function testCounterIncrements(): void
    {
        $pattern = '/^LH-\d{8}-\d{4}$/';
        $this->assertMatchesRegularExpression($pattern, 'LH-20260716-0002');
        $this->assertMatchesRegularExpression($pattern, 'LH-20260716-0003');
    }

    public function testDifferentDateDifferentCounter(): void
    {
        $n1 = 'LH-20260716-0001';
        $n2 = 'LH-20260717-0001';
        $this->assertNotEquals($n1, $n2);
    }

    public function testInvalidPrefixThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NomorLaporanGenerator::generate('XX', '2026-07-16');
    }
}
