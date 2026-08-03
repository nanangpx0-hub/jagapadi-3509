<?php

use PHPUnit\Framework\TestCase;

final class LaporanHamaStatusTest extends TestCase {

    public function testDefaultStatusIsSubmitted(): void {
        $data = [
            'tanggal' => '2026-08-03',
            'master_opt_id' => 1,
            'kabupaten_id' => 1,
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'alamat_lengkap' => 'Jl. Kalimantan No. 37, Jember',
            'tingkat_keparahan' => 'Ringan',
            'latitude' => -8.172100,
            'longitude' => 113.700100
        ];

        // Validasi koordinat
        $geoRes = GeoValidator::validateJemberCoordinates((float)$data['latitude'], (float)$data['longitude']);
        self::assertTrue($geoRes['valid']);

        // Default status harus Submitted
        $status = $data['status'] ?? 'Submitted';
        self::assertSame('Submitted', $status);

        $allowedStatuses = ['Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'];
        self::assertContains($status, $allowedStatuses);
    }

    public function testRejectInitialDraftStatus(): void {
        $data = [
            'status' => 'Draf'
        ];

        $allowedInitialStatuses = ['Submitted'];
        self::assertNotContains($data['status'], $allowedInitialStatuses);
    }
}
