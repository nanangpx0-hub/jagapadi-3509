<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ExportService;
use PHPUnit\Framework\TestCase;

class ExportServiceTest extends TestCase
{
    public function testValidateFormatInvalid(): void
    {
        $errors = ExportService::validateFiltersStatic(['format' => 'pdf']);
        $this->assertArrayHasKey('format', $errors);
    }

    public function testValidateFormatValid(): void
    {
        $errors = ExportService::validateFiltersStatic(['format' => 'csv']);
        $this->assertArrayNotHasKey('format', $errors);

        $errors = ExportService::validateFiltersStatic(['format' => 'xlsx']);
        $this->assertArrayNotHasKey('format', $errors);
    }

    public function testValidateTanggalDariGreaterThanSampai(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'tanggal_dari' => '2026-06-15',
            'tanggal_sampai' => '2026-06-10',
        ]);
        $this->assertArrayHasKey('tanggal_sampai', $errors);
    }

    public function testValidateTanggalRangeTooLarge(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'tanggal_dari' => '2025-01-01',
            'tanggal_sampai' => '2026-06-01',
        ]);
        $this->assertArrayHasKey('tanggal_sampai', $errors);
    }

    public function testValidateStatusNonWhitelist(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'status' => 'InvalidStatus',
        ]);
        $this->assertArrayHasKey('status', $errors);
    }

    public function testValidateStatusValid(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'status' => 'Submitted,Diverifikasi',
        ]);
        $this->assertArrayNotHasKey('status', $errors);
    }

    public function testValidateWilayahIdInvalid(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'kabupaten_id' => 'abc',
        ]);
        $this->assertArrayHasKey('kabupaten_id', $errors);
    }

    public function testValidateWilayahIdValid(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'kabupaten_id' => '1',
        ]);
        $this->assertArrayNotHasKey('kabupaten_id', $errors);
    }

    public function testEmptyFiltersValid(): void
    {
        $errors = ExportService::validateFiltersStatic(['format' => 'csv']);
        $this->assertCount(0, $errors);
    }

    public function testHeadingsCountHama(): void
    {
        $headers = [
            'Nomor Laporan', 'Tanggal', 'Status', 'Nama Petugas',
            'Nama OPT', 'Jenis OPT', 'Tingkat Keparahan', 'Luas Serangan',
            'Populasi', 'Kabupaten', 'Kecamatan', 'Desa',
            'Lokasi', 'Alamat Lengkap', 'Latitude', 'Longitude',
            'Catatan', 'Diverifikasi Oleh', 'Tanggal Verifikasi',
            'Catatan Verifikasi', 'Dibuat Pada', 'Diperbarui Pada',
        ];
        $this->assertCount(22, $headers);
        $this->assertSame('Nomor Laporan', $headers[0]);
        $this->assertSame('Diperbarui Pada', $headers[21]);
    }

    public function testHeadingsCountIrigasi(): void
    {
        $headers = [
            'Nomor Laporan', 'Tanggal', 'Status', 'Nama Petugas',
            'Nama Saluran', 'Daerah Irigasi', 'Kondisi Fisik', 'Debit Air',
            'Kabupaten', 'Kecamatan', 'Desa',
            'Latitude', 'Longitude',
            'Catatan', 'Diverifikasi Oleh', 'Tanggal Verifikasi',
            'Catatan Verifikasi', 'Dibuat Pada', 'Diperbarui Pada',
        ];
        $this->assertCount(19, $headers);
        $this->assertSame('Nomor Laporan', $headers[0]);
        $this->assertSame('Diperbarui Pada', $headers[18]);
    }

    public function testValidateTanggalFormatBad(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'tanggal_dari' => '2026/06/10',
        ]);
        $this->assertArrayHasKey('tanggal_dari', $errors);
    }

    public function testValidateTanggalFormatGood(): void
    {
        $errors = ExportService::validateFiltersStatic([
            'format' => 'csv',
            'tanggal_dari' => '2026-06-10',
        ]);
        $this->assertArrayNotHasKey('tanggal_dari', $errors);
    }
}
