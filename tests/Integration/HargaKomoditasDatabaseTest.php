<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HargaKomoditasDatabaseTest extends TestCase
{
    private PDO $db;
    private HargaKomoditas $model;

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $this->db->beginTransaction();
        $this->model = new HargaKomoditas();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testFiltersAreIdenticalForRowsCountStatisticsAndOverall(): void
    {
        $base = [
            'jenis_komoditas' => 'beras_medium',
            'satuan' => 'Rp/kg',
            'kode_wilayah' => '35.09.99',
            'sumber_data' => 'Fixture Aktual',
            'metode_data' => 'aktual',
        ];
        $this->model->upsert($base + ['tanggal' => '2098-01-01', 'harga' => 10000, 'lokasi' => 'Fixture A'], false);
        $this->model->upsert($base + ['tanggal' => '2098-01-02', 'harga' => 11000, 'lokasi' => 'Fixture A'], false);
        $this->model->upsert(array_merge($base, [
            'tanggal' => '2098-01-02', 'harga' => 50000, 'lokasi' => 'Fixture B',
            'sumber_data' => 'Simulasi', 'metode_data' => 'simulasi',
        ]), false);

        $filters = [
            'start_date' => '2098-01-01',
            'end_date' => '2098-01-02',
            'jenis_komoditas' => 'beras_medium',
            'lokasi' => 'Fixture A',
            'sumber_data' => 'Fixture Aktual',
            'metode_data' => 'aktual',
        ];
        $rows = $this->model->getAll($filters);
        $statistics = $this->model->getStatistics($filters);
        $overall = $this->model->getOverallStats($filters);

        self::assertCount(2, $rows);
        self::assertSame(2, $this->model->countAll($filters));
        self::assertCount(1, $statistics);
        self::assertSame(2, (int) $statistics[0]['total_records']);
        self::assertSame(10500.0, (float) $statistics[0]['rata_rata']);
        self::assertSame(2, $overall['total_records']);
        self::assertSame(11000.0, $overall['harga_beras']);
        self::assertSame(10.0, $overall['perubahan_beras']);
    }

    public function testUpsertIsIdempotentAndUpdatesChangedObservation(): void
    {
        $record = [
            'tanggal' => '2098-02-01',
            'jenis_komoditas' => 'gabah_kering_panen',
            'harga' => 6000,
            'lokasi' => 'Fixture Idempoten',
            'sumber_data' => 'Fixture Manual',
            'metode_data' => 'manual',
        ];

        self::assertSame('inserted', $this->model->upsert($record, false));
        self::assertSame('unchanged', $this->model->upsert($record, false));
        self::assertSame('updated', $this->model->upsert(array_merge($record, ['harga' => 6250]), false));

        $filters = [
            'start_date' => '2098-02-01',
            'end_date' => '2098-02-01',
            'lokasi' => 'Fixture Idempoten',
            'sumber_data' => 'Fixture Manual',
        ];
        self::assertSame(1, $this->model->countAll($filters));
        self::assertSame(6250.0, (float) $this->model->getAll($filters)[0]['harga']);
    }

    public function testMissingInvalidAndOutlierValuesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->model->upsert([
            'tanggal' => 'not-a-date',
            'jenis_komoditas' => 'beras_medium',
            'harga' => 250000,
            'lokasi' => '',
            'sumber_data' => 'Fixture',
            'metode_data' => 'aktual',
        ]);
    }

    public function testAlertsUseDailyNonSimulationAverageOnly(): void
    {
        $base = [
            'jenis_komoditas' => 'gabah_kering_giling',
            'lokasi' => 'Fixture Alert',
            'kode_wilayah' => '35.09.99',
            'sumber_data' => 'Fixture Aktual Alert',
            'metode_data' => 'aktual',
        ];
        $this->model->upsert($base + ['tanggal' => '2097-03-01', 'harga' => 6000], false);
        $this->model->upsert($base + ['tanggal' => '2097-03-02', 'harga' => 12000], false);
        $this->model->upsert(array_merge($base, [
            'tanggal' => '2097-03-02',
            'harga' => 90000,
            'sumber_data' => 'Simulasi',
            'metode_data' => 'simulasi',
        ]), false);
        $this->model->rebuildAlerts('gabah_kering_giling');

        $stmt = $this->db->prepare(
            "SELECT harga_sebelum, harga_sesudah, COUNT(*) OVER () AS jumlah
             FROM harga_alerts
             WHERE jenis_komoditas = 'gabah_kering_giling' AND tanggal = '2097-03-02'"
        );
        $stmt->execute();
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($alert);
        self::assertSame(6000.0, (float) $alert['harga_sebelum']);
        self::assertSame(12000.0, (float) $alert['harga_sesudah']);
        self::assertSame(1, (int) $alert['jumlah']);
    }

    private function loadEnvironment(): void
    {
        foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $value = trim($value, "\"'");
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
