<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Idempotensi UPSERT data NASA + penanganan tanggal masa depan.
 * Memakai sumber_data khusus UNITTEST agar tidak menyentuh data nyata.
 */
final class CurahHujanBulkUpsertTest extends TestCase
{
    private const SOURCE = 'UNITTEST-NASA';
    private PDO $db;
    private CurahHujan $model;

    protected function setUp(): void
    {
        require_once ROOT_PATH . '/app/models/CurahHujan.php';
        $this->db = Database::getInstance()->getConnection();
        $this->model = new CurahHujan();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $stmt = $this->db->prepare('DELETE FROM curah_hujan WHERE sumber_data = ?');
        $stmt->execute([self::SOURCE]);
    }

    private function record(string $tanggal, string $lokasi, float $curah): array
    {
        return [
            'tanggal' => $tanggal,
            'lokasi' => $lokasi,
            'kecamatan' => $lokasi,
            'kecamatan_id' => null,
            'latitude' => -8.17,
            'longitude' => 113.70,
            'curah_hujan' => $curah,
            'satuan' => 'mm',
            'sumber_data' => self::SOURCE,
            'keterangan' => 'fixture uji',
        ];
    }

    private function countRows(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM curah_hujan WHERE sumber_data = ?');
        $stmt->execute([self::SOURCE]);
        return (int)$stmt->fetchColumn();
    }

    public function testUpsertIdempotenSaatDijalankanUlang(): void
    {
        $records = [
            $this->record('2020-01-01', 'UNITTEST-A', 12.5),
            $this->record('2020-01-02', 'UNITTEST-A', 0.0),
        ];

        $first = $this->model->bulkUpsertNasaData($records);
        self::assertSame(2, $first['success']);
        self::assertSame(0, $first['failed']);

        $second = $this->model->bulkUpsertNasaData($records);
        self::assertSame(2, $second['success']);
        self::assertSame(2, $this->countRows(), 'Re-scrape tidak boleh duplikat');

        $stmt = $this->db->prepare('SELECT curah_hujan FROM curah_hujan WHERE sumber_data = ? AND tanggal = ? AND lokasi = ?');
        $stmt->execute([self::SOURCE, '2020-01-01', 'UNITTEST-A']);
        self::assertEqualsWithDelta(12.5, (float)$stmt->fetchColumn(), 0.001);
    }

    public function testUpsertMemperbaruiNilaiLama(): void
    {
        $this->model->bulkUpsertNasaData([$this->record('2020-02-01', 'UNITTEST-B', 5.0)]);
        $this->model->bulkUpsertNasaData([$this->record('2020-02-01', 'UNITTEST-B', 9.5)]);

        $stmt = $this->db->prepare('SELECT curah_hujan FROM curah_hujan WHERE sumber_data = ? AND tanggal = ?');
        $stmt->execute([self::SOURCE, '2020-02-01']);
        self::assertEqualsWithDelta(9.5, (float)$stmt->fetchColumn(), 0.001);
        self::assertSame(1, $this->countRows());
    }

    public function testTanggalMasaDepanDilewatiBukanError(): void
    {
        $future = date('Y-m-d', strtotime('+5 days'));
        $result = $this->model->bulkUpsertNasaData([
            $this->record($future, 'UNITTEST-C', 3.0),
            $this->record('2020-03-01', 'UNITTEST-C', 3.0),
        ]);

        self::assertSame(1, $result['success']);
        self::assertSame(0, $result['failed']);
        self::assertSame(1, $result['skipped_future']);
        self::assertSame(1, $this->countRows());
    }

    public function testRecordInvalidDihitungGagal(): void
    {
        $result = $this->model->bulkUpsertNasaData([
            ['tanggal' => 'bukan-tanggal', 'lokasi' => 'X', 'curah_hujan' => 1.0, 'sumber_data' => self::SOURCE],
            ['tanggal' => '2020-04-01', 'lokasi' => 'Y', 'sumber_data' => self::SOURCE],
            'bukan-array',
        ]);

        self::assertSame(0, $result['success']);
        self::assertSame(3, $result['failed']);
        self::assertSame(0, $this->countRows());
    }
}
