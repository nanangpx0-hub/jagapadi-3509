<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\LaporanStatus;
use PHPUnit\Framework\TestCase;

class LaporanStatusTest extends TestCase
{
    public static function validTransitionProvider(): array
    {
        return [
            'admin verifikasi' => ['Submitted', 'Diverifikasi', 'admin'],
            'admin tolak' => ['Submitted', 'Ditolak', 'admin'],
            'admin arsip' => ['Diverifikasi', 'Diarsipkan', 'admin'],
            'petugas resubmit' => ['Ditolak', 'Submitted', 'petugas'],
            'petugas kembali ke Draf' => ['Ditolak', 'Draf', 'petugas'],
        ];
    }

    public static function invalidTransitionProvider(): array
    {
        return [
            'petugas verifikasi' => ['Submitted', 'Diverifikasi', 'petugas', 'Hanya untuk admin'],
            'petugas tolak' => ['Submitted', 'Ditolak', 'petugas', 'Hanya untuk admin'],
            'admin resubmit' => ['Ditolak', 'Submitted', 'admin', 'role conflict'],
            'verifikasi Draf' => ['Draf', 'Diverifikasi', 'admin', 'invalid from'],
            'tolak Draf' => ['Draf', 'Ditolak', 'admin', 'invalid from'],
            'arsip Submitted' => ['Submitted', 'Diarsipkan', 'admin', 'invalid from'],
            'resubmit Submitted' => ['Submitted', 'Submitted', 'petugas', 'invalid from'],
            'arsip Ditolak' => ['Ditolak', 'Diarsipkan', 'admin', 'invalid from'],
            'verifikasi Diarsipkan' => ['Diarsipkan', 'Diverifikasi', 'admin', 'read-only'],
            'resubmit Diverifikasi' => ['Diverifikasi', 'Submitted', 'petugas', 'read-only'],
            'Draf to Diarsipkan' => ['Draf', 'Diarsipkan', 'admin', 'invalid from'],
        ];
    }

    /** @dataProvider validTransitionProvider */
    public function testValidTransition(string $from, string $to, string $role): void
    {
        $this->assertTrue(LaporanStatus::canTransition($from, $to, $role));
        LaporanStatus::assertCanTransition($from, $to, $role);
        $this->assertTrue(true);
    }

    /** @dataProvider invalidTransitionProvider */
    public function testInvalidTransition(string $from, string $to, string $role): void
    {
        $this->assertFalse(LaporanStatus::canTransition($from, $to, $role));
        $this->expectException(\DomainException::class);
        LaporanStatus::assertCanTransition($from, $to, $role);
    }

    public function testIsValid(): void
    {
        $this->assertTrue(LaporanStatus::isValid('Draf'));
        $this->assertTrue(LaporanStatus::isValid('Submitted'));
        $this->assertTrue(LaporanStatus::isValid('Diverifikasi'));
        $this->assertTrue(LaporanStatus::isValid('Ditolak'));
        $this->assertTrue(LaporanStatus::isValid('Diarsipkan'));
        $this->assertFalse(LaporanStatus::isValid('InvalidStatus'));
    }

    public function testIsEditableByPetugas(): void
    {
        $this->assertTrue(LaporanStatus::isEditableByPetugas('Draf'));
        $this->assertTrue(LaporanStatus::isEditableByPetugas('Ditolak'));
        $this->assertFalse(LaporanStatus::isEditableByPetugas('Submitted'));
        $this->assertFalse(LaporanStatus::isEditableByPetugas('Diverifikasi'));
        $this->assertFalse(LaporanStatus::isEditableByPetugas('Diarsipkan'));
    }

    public function testIsVerifiable(): void
    {
        $this->assertTrue(LaporanStatus::isVerifiable('Submitted'));
        $this->assertFalse(LaporanStatus::isVerifiable('Draf'));
        $this->assertFalse(LaporanStatus::isVerifiable('Diverifikasi'));
    }

    public function testIsRejectable(): void
    {
        $this->assertTrue(LaporanStatus::isRejectable('Submitted'));
        $this->assertFalse(LaporanStatus::isRejectable('Draf'));
    }

    public function testIsArchivable(): void
    {
        $this->assertTrue(LaporanStatus::isArchivable('Diverifikasi'));
        $this->assertFalse(LaporanStatus::isArchivable('Submitted'));
    }

    public function testIsResubmittable(): void
    {
        $this->assertTrue(LaporanStatus::isResubmittable('Ditolak'));
        $this->assertFalse(LaporanStatus::isResubmittable('Submitted'));
    }
}
