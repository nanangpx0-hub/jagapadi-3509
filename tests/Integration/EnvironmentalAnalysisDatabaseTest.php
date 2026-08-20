<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvironmentalAnalysisDatabaseTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testRainfallUsesDailyRegionalAverageAndKeepsSourcesSeparate(): void
    {
        $model = new CurahHujan();
        $base = ['kode_wilayah' => '35.09.99', 'satuan' => 'mm', 'sumber_data' => 'Fixture Aktual'];
        foreach ([
            ['2097-01-01', 'Fixture A', 10.0],
            ['2097-01-01', 'Fixture B', 20.0],
            ['2097-01-02', 'Fixture A', 30.0],
            ['2097-01-02', 'Fixture B', 50.0],
        ] as [$date, $location, $rainfall]) {
            self::assertTrue($model->insert($base + [
                'tanggal' => $date,
                'lokasi' => $location,
                'curah_hujan' => $rainfall,
            ]));
        }
        self::assertTrue($model->insert([
            'tanggal' => '2097-01-01',
            'lokasi' => 'Fixture A',
            'curah_hujan' => 400,
            'sumber_data' => 'Simulasi',
        ]));

        $filters = [
            'start_date' => '2097-01-01',
            'end_date' => '2097-01-02',
            'sumber_data_like' => 'Fixture Aktual',
        ];
        $stats = $model->getStatistics($filters);
        $daily = $model->getDailyData(2097, 1, ['sumber_data_like' => 'Fixture Aktual']);
        $trend = $model->getTrendAnalysis(2097, 2097, ['sumber_data_like' => 'Fixture Aktual']);
        $seasonal = $model->getSeasonalPattern(2097, ['sumber_data_like' => 'Fixture Aktual']);

        self::assertSame(4, $model->countAll($filters));
        self::assertSame(4, (int) $stats['total_records']);
        self::assertSame(2, (int) $stats['jumlah_hari']);
        self::assertSame(27.5, (float) $stats['rata_rata']);
        self::assertSame(55.0, (float) $stats['total_curah_hujan']);
        self::assertSame(15.0, (float) $daily[0]['curah_hujan']);
        self::assertSame(55.0, (float) $trend[0]['total']);
        self::assertSame(2, (int) $trend[0]['jumlah_data']);
        self::assertSame(55.0, (float) $seasonal[0]['total']);
        self::assertSame(2, (int) $seasonal[0]['total_hari']);

        $bulk = $model->bulkInsert([$base + [
            'tanggal' => '2097-01-01',
            'lokasi' => 'Fixture A',
            'curah_hujan' => 10,
        ]]);
        self::assertSame(1, $bulk['success']);
        self::assertSame(0, $bulk['failed']);
    }

    public function testWindAndIrrigationFiltersMatchRowsCountsAndStatistics(): void
    {
        $wind = new KecepatanAngin();
        foreach ([10.0, 20.0] as $index => $speed) {
            $wind->insertUpsert([
                'tanggal' => '2097-02-0' . ($index + 1),
                'lokasi' => 'Fixture Wind',
                'kecepatan_angin' => $speed,
                'sumber_data' => 'Fixture Aktual',
                'keterangan' => 'fixture',
            ]);
        }
        $wind->insertUpsert([
            'tanggal' => '2097-02-01',
            'lokasi' => 'Fixture Wind',
            'kecepatan_angin' => 99,
            'sumber_data' => 'Simulasi',
            'keterangan' => 'fixture simulation',
        ]);
        $windFilters = [
            'start_date' => '2097-02-02',
            'end_date' => '2097-02-02',
            'sumber_data_like' => 'Fixture Aktual',
        ];
        self::assertCount(1, $wind->getAll($windFilters));
        self::assertSame(1, (int) $wind->countAll($windFilters));
        self::assertSame(20.0, (float) $wind->getStatistics($windFilters)['rata_rata']);
        self::assertSame(
            15.0,
            (float) $wind->getMonthlyAverage(2097, ['sumber_data_like' => 'Fixture Aktual'])[0]['rata_rata']
        );
        self::assertSame(
            15.0,
            (float) $wind->getTrendAnalysis(2097, 2097, ['sumber_data_like' => 'Fixture Aktual'])[0]['rata_rata']
        );

        $irrigation = new DataIrigasi();
        foreach ([['Fixture Aman', 'Aman'], ['Fixture Kritis', 'Kritis']] as [$name, $status]) {
            $irrigation->upsert([
                'tanggal' => '2097-03-01',
                'daerah_irigasi' => $name,
                'luas_sawah' => 10,
                'debit_air' => $status === 'Aman' ? 100 : 10,
                'status_pintu' => $status,
                'metode_data' => 'manual',
            ]);
        }
        $irrigationFilters = ['tanggal' => '2097-03-01', 'status_pintu' => 'Kritis'];
        self::assertCount(1, $irrigation->getAll($irrigationFilters));
        self::assertSame(1, (int) $irrigation->countAll($irrigationFilters));
        self::assertSame(1, (int) $irrigation->getStatistics($irrigationFilters)['jumlah_kritis']);
        self::assertSame('2097-03-01', $irrigation->getLatestDate());
        self::assertSame(55.0, (float) $irrigation->getDebitTrend(1)[0]['rata_debit']);
    }

    public function testEvaluationSnapshotUsesExactMonthlyKsaAndZeroReleaseIsUndefined(): void
    {
        $model = new EvaluasiAkurasi();
        $result = $model->snapshotEstimasi(8, 2026);

        self::assertTrue($result['success']);
        $jember = $model->getByPeriodeWilayah(8, 2026, 3509);
        self::assertIsArray($jember);
        self::assertSame('Jember', $jember['nama_wilayah']);
        self::assertEqualsWithDelta(15329.4578, (float) $jember['luas_estimasi_daerah'], 0.01);

        $zero = $model->insert([
            'periode_bulan' => 1,
            'periode_tahun' => 2097,
            'wilayah_id' => 999999,
            'nama_wilayah' => 'Fixture Zero',
            'luas_estimasi_daerah' => 100,
            'luas_rilis_bps' => 0,
        ]);
        self::assertTrue($zero['success']);
        $record = $model->getById($zero['id']);
        self::assertNull($record['persentase_bias']);
        self::assertNull($record['status_akurasi']);
    }

    public function testOptLowercaseFilterAndExtendedClassificationPersist(): void
    {
        $model = new MasterOpt();
        $name = 'Fixture OPT ' . bin2hex(random_bytes(4));
        $id = $model->create([
            'kode_opt' => 'FX-' . bin2hex(random_bytes(3)),
            'nama_opt' => $name,
            'nama_ilmiah' => 'Fixture scientificus',
            'jenis' => 'hama',
            'filum' => 'Arthropoda',
            'rekomendasi' => 'Monitoring berkala',
        ]);

        $record = $model->getById((int) $id);
        self::assertSame('hama', $record['jenis']);
        self::assertSame('Arthropoda', $record['filum']);
        self::assertSame('Monitoring berkala', $record['rekomendasi']);

        $page = $model->paginate(1, 100, ['jenis' => 'hama'], $name);
        self::assertSame(1, (int) $page['total']);
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
