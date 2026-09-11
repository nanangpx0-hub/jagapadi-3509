<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Idempotensi UPSERT angin & harga dengan sumber UNITTEST
 * agar tidak menyentuh data nyata. Selalu dibersihkan kembali.
 */
final class AnginHargaBulkUpsertTest extends TestCase
{
    private const WIND_SOURCE = 'UNITTEST-WIND';
    private const PRICE_SOURCE = 'UNITTEST-HARGA';

    private PDO $db;
    private KecepatanAngin $wind;
    private HargaKomoditas $price;

    protected function setUp(): void
    {
        require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
        require_once ROOT_PATH . '/app/models/HargaKomoditas.php';
        $this->db = Database::getInstance()->getConnection();
        $this->wind = new KecepatanAngin();
        $this->price = new HargaKomoditas();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $stmt = $this->db->prepare('DELETE FROM kecepatan_angin WHERE sumber_data = ?');
        $stmt->execute([self::WIND_SOURCE]);
        $stmt = $this->db->prepare('DELETE FROM harga_komoditas WHERE sumber_data = ?');
        $stmt->execute([self::PRICE_SOURCE]);
    }

    public function testUpsertAnginIdempoten(): void
    {
        $records = [
            ['tanggal' => '2020-01-01', 'lokasi' => 'UNITTEST-A', 'kode_wilayah' => '35.09', 'kecepatan_angin' => 12.5, 'kecepatan_max' => 16.0, 'satuan' => 'km/h', 'sumber_data' => self::WIND_SOURCE, 'keterangan' => 'uji'],
            ['tanggal' => '2020-01-02', 'lokasi' => 'UNITTEST-A', 'kode_wilayah' => '35.09', 'kecepatan_angin' => 8.0, 'satuan' => 'km/h', 'sumber_data' => self::WIND_SOURCE],
        ];

        $first = $this->wind->bulkUpsertWindData($records);
        self::assertSame(2, $first['success']);
        self::assertSame(0, $first['failed']);

        $second = $this->wind->bulkUpsertWindData($records);
        self::assertSame(2, $second['success']);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM kecepatan_angin WHERE sumber_data = ?');
        $stmt->execute([self::WIND_SOURCE]);
        self::assertSame(2, (int)$stmt->fetchColumn(), 'Re-scrape tidak boleh duplikat');
    }

    public function testUpsertAnginTanggalMasaDepanDilewati(): void
    {
        $future = date('Y-m-d', strtotime('+5 days'));
        $result = $this->wind->bulkUpsertWindData([
            ['tanggal' => $future, 'lokasi' => 'UNITTEST-B', 'kecepatan_angin' => 10.0, 'sumber_data' => self::WIND_SOURCE],
            ['tanggal' => '2020-03-01', 'lokasi' => 'UNITTEST-B', 'kecepatan_angin' => 10.0, 'sumber_data' => self::WIND_SOURCE],
            ['tanggal' => 'salah', 'lokasi' => 'UNITTEST-B', 'kecepatan_angin' => 10.0, 'sumber_data' => self::WIND_SOURCE],
        ]);

        self::assertSame(1, $result['success']);
        self::assertSame(1, $result['failed']);
        self::assertSame(1, $result['skipped_future']);
    }

    public function testUpsertHargaIdempoten(): void
    {
        $record = [
            'tanggal' => '2020-01-05',
            'jenis_komoditas' => 'gabah_kering_panen',
            'harga' => 6200,
            'satuan' => 'Rp/kg',
            'lokasi' => 'UNITTEST-H',
            'kode_wilayah' => '35.09',
            'sumber_data' => self::PRICE_SOURCE,
            'metode_data' => 'aktual',
            'keterangan' => 'uji',
        ];

        self::assertSame('inserted', $this->price->upsert($record, false));
        self::assertSame('unchanged', $this->price->upsert($record, false));

        $record['harga'] = 6300;
        self::assertSame('updated', $this->price->upsert($record, false));

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM harga_komoditas WHERE sumber_data = ?');
        $stmt->execute([self::PRICE_SOURCE]);
        self::assertSame(1, (int)$stmt->fetchColumn(), 'Re-scrape tidak boleh duplikat');
    }

    public function testGetFailedLogsHanyaFailedPartial(): void
    {
        $this->wind->logActivity('uji', 'success', 'ok');
        $this->wind->logActivity('uji', 'failed', 'gagal uji');
        $this->price->logActivity('uji', 'partial', 'sebagian gagal');

        try {
            $windLogs = $this->wind->getFailedLogs(50);
            $statuses = array_column($windLogs, 'status');
            self::assertContains('failed', $statuses);
            self::assertNotContains('success', $statuses);

            $priceLogs = $this->price->getFailedLogs(50);
            self::assertNotEmpty($priceLogs);
            foreach ($priceLogs as $log) {
                self::assertContains($log['status'], ['failed', 'partial']);
            }
        } finally {
            $stmt = $this->db->prepare("DELETE FROM kecepatan_angin_logs WHERE action = 'uji'");
            $stmt->execute();
            $stmt = $this->db->prepare("DELETE FROM harga_komoditas_logs WHERE action = 'uji'");
            $stmt->execute();
        }
    }
}
