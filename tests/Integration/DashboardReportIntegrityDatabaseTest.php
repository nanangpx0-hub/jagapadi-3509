<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DashboardReportIntegrityDatabaseTest extends TestCase
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

    public function testDashboardUsesPreferredBpsSourceInsteadOfAddingSimulation(): void
    {
        $base = [
            'tahun' => 2027,
            'kode_provinsi' => '35',
            'kabupaten_kota' => 'Kabupaten Dashboard Fixture',
            'kode_wilayah' => '3599',
            'luas_panen' => 100,
            'produksi_beras' => 500,
            'produktivitas' => 50,
            'tipe_skenario' => 'baseline',
            'is_validated' => 1,
        ];
        $model = new DataPertanianBps();
        self::assertTrue($model->insert($base + [
            'produksi_gabah' => 9999999,
            'sumber_data' => 'Simulasi Dashboard Fixture',
            'sumber_data_type' => 'simulasi',
        ]));
        self::assertTrue($model->insert(array_merge($base, [
            'produksi_gabah' => 8888888,
            'sumber_data' => 'KSA Dashboard Fixture',
            'sumber_data_type' => 'ksa',
        ])));

        $aggregator = new DashboardDataAggregator();
        $rows = $aggregator->getTopProducers(2027, 100);
        $fixtureRows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['kabupaten'] === 'Kabupaten Dashboard Fixture'
        ));

        self::assertCount(1, $fixtureRows);
        self::assertSame(8888888.0, (float) $fixtureRows[0]['produksi_gabah']);
    }

    public function testWeatherSummariesUseDailyActualDataAndIgnoreSimulationOutlier(): void
    {
        $rain = new CurahHujan();
        foreach ([
            ['2027-01-01', 'Dashboard Rain A', 10.0],
            ['2027-01-01', 'Dashboard Rain B', 20.0],
            ['2027-01-02', 'Dashboard Rain A', 30.0],
            ['2027-01-02', 'Dashboard Rain B', 50.0],
        ] as [$date, $location, $value]) {
            self::assertTrue($rain->insert([
                'tanggal' => $date,
                'lokasi' => $location,
                'curah_hujan' => $value,
                'sumber_data' => 'Fixture Aktual Dashboard',
            ]));
        }
        self::assertTrue($rain->insert([
            'tanggal' => '2027-01-01',
            'lokasi' => 'Dashboard Rain A',
            'curah_hujan' => 9999,
            'sumber_data' => 'Simulasi Dashboard',
        ]));

        $wind = new KecepatanAngin();
        foreach ([10.0, 40.0] as $index => $value) {
            self::assertTrue($wind->insertUpsert([
                'tanggal' => '2027-02-0' . ($index + 1),
                'lokasi' => 'Dashboard Wind',
                'kecepatan_angin' => $value,
                'sumber_data' => 'Fixture Aktual Dashboard',
            ]));
        }
        self::assertTrue($wind->insertUpsert([
            'tanggal' => '2027-02-01',
            'lokasi' => 'Dashboard Wind',
            'kecepatan_angin' => 999,
            'sumber_data' => 'Simulasi Dashboard',
        ]));
        self::assertTrue($wind->insertUpsert([
            'tanggal' => date('Y-m-d'),
            'lokasi' => 'Dashboard Wind Recent',
            'kecepatan_angin' => 40.0,
            'sumber_data' => 'Fixture Aktual Dashboard',
        ]));

        $aggregator = new DashboardDataAggregator();
        $rainStats = $aggregator->getRainfallSummary(['year' => 2027])['statistics'];
        $windStats = $aggregator->getWindSummary(['year' => 2027])['statistics'];
        $alerts = $aggregator->getWeatherAlerts(['days' => 30]);

        self::assertSame(2, (int) $rainStats['total_records']);
        self::assertSame(55.0, (float) $rainStats['total_rainfall']);
        self::assertSame(2, (int) $windStats['total_records']);
        self::assertSame(25.0, (float) $windStats['avg_speed']);
        self::assertNotEmpty(array_filter(
            $alerts,
            static fn(array $row): bool => ($row['alert_type'] ?? '') === 'high_wind'
                && ($row['kecamatan'] ?? '') === 'Dashboard Wind Recent'
        ));
    }

    public function testDashboardLatestPricesReturnOnlyLatestDatePerCommodity(): void
    {
        $insert = $this->db->prepare(
            'INSERT INTO harga_komoditas
             (tanggal, jenis_komoditas, harga, lokasi, sumber_data, metode_data)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ([
            ['2098-01-01', 'gabah_kering_panen', 5000],
            ['2098-02-01', 'gabah_kering_panen', 5500],
            ['2098-02-01', 'beras_medium', 12500],
        ] as [$date, $commodity, $price]) {
            $insert->execute([
                $date,
                $commodity,
                $price,
                'Dashboard Price Fixture',
                'Dashboard Price Fixture',
                'aktual',
            ]);
        }

        $rows = (new DashboardDataAggregator())->getLatestPrices();
        $fixtureRows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => ($row['sumber_data'] ?? '') === 'Dashboard Price Fixture'
        ));

        self::assertCount(2, $fixtureRows);
        foreach ($fixtureRows as $row) {
            self::assertSame('2098-02-01', $row['tanggal']);
        }
    }

    public function testHamaAnalyticsOnlyUseActiveRowsAndRespectPetugasScope(): void
    {
        $insert = $this->db->prepare(
            'INSERT INTO laporan_hama
             (user_id, master_opt_id, tanggal, lokasi, tingkat_keparahan, populasi, luas_serangan, status)
             VALUES (?, 1, ?, ?, ?, ?, ?, ?)'
        );
        foreach ([
            [2, 'Petugas Aktif A', 'Ringan', 10, 1, 'Submitted'],
            [2, 'Petugas Aktif B', 'Berat', 20, 2, 'Diverifikasi'],
            [2, 'Petugas Draf Outlier', 'Berat', 9999, 1000, 'Draf'],
            [2, 'Petugas Ditolak', 'Berat', 9999, 1000, 'Ditolak'],
            [3, 'Petugas Lain', 'Berat', 30, 3, 'Submitted'],
        ] as [$userId, $location, $severity, $population, $area, $status]) {
            $insert->execute([$userId, '2027-03-01', $location, $severity, $population, $area, $status]);
        }

        $aggregator = new DashboardDataAggregator();
        $stats = $aggregator->getHamaStats(2027, 2);
        $distribution = $aggregator->getHamaDistribution(2027, 2);

        self::assertSame(2, (int) $stats['total_laporan']);
        self::assertSame(1, (int) $stats['pending']);
        self::assertSame(1, (int) $stats['terverifikasi']);
        self::assertSame(3.0, (float) $stats['total_luas_serangan']);
        self::assertSame(2, (int) $distribution[0]['total_laporan']);

        $expectedCounts = $this->db->query(
            'SELECT status, COUNT(*) AS total
             FROM laporan_hama
             WHERE user_id = 2
             GROUP BY status'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $actualCounts = (new LaporanHama())->getStatusCounts(2);
        self::assertSame(
            array_map('intval', $expectedCounts),
            $actualCounts
        );
    }

    public function testIrrigationFieldsPersistAndDraftCannotBeVerified(): void
    {
        $model = new LaporanIrigasi();
        $id = $model->createSubmitted([
            'user_id' => 2,
            'tanggal' => '2026-08-12',
            'kabupaten_id' => 1,
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'nama_saluran' => 'Fixture Saluran',
            'daerah_irigasi' => 'Fixture Saluran',
            'luas_layanan' => 12.5,
            'jenis_saluran' => 'Sekunder',
            'kondisi_fisik' => 'Tidak Bagus',
            'debit_air' => 'Kurang',
            'status_perbaikan' => 'Dalam Perbaikan',
            'aksi_dilakukan' => 'Pembersihan sedimen',
        ]);
        $saved = $model->find($id);

        self::assertSame('Submitted', $saved['status']);
        self::assertMatchesRegularExpression('/^LI-\d{8}-\d{4}$/', (string) $saved['nomor_laporan']);
        self::assertSame('12.50', (string) $saved['luas_layanan']);
        self::assertSame('Sekunder', $saved['jenis_saluran']);
        self::assertSame('Dalam Perbaikan', $saved['status_perbaikan']);
        self::assertSame('Pembersihan sedimen', $saved['aksi_dilakukan']);
        self::assertLessThanOrEqual(25, count($model->getAllWithDetails(null, 25, 0)));
        self::assertSame(
            (int) $this->db->query('SELECT COUNT(*) FROM laporan_irigasi')->fetchColumn(),
            $model->countAll()
        );

        $draftId = (int) $model->create([
            'user_id' => 2,
            'tanggal' => '2026-08-12',
            'nama_saluran' => 'Fixture Draft',
            'status' => 'Draf',
        ]);
        $this->expectException(LogicException::class);
        $model->verify($draftId, 'Diverifikasi', 1);
    }

    public function testDraftsAreExcludedFromRecentAndOtherReportAggregates(): void
    {
        $hama = new LaporanHama();
        foreach ($hama->getRecentForDashboard(null, 50) as $report) {
            self::assertNotSame('Draf', $report['status']);
        }

        $jenisId = (int) $this->db->query('SELECT id FROM master_jenis_laporan ORDER BY id LIMIT 1')->fetchColumn();
        $stmt = $this->db->prepare(
            'INSERT INTO laporan_lainnya
             (user_id, jenis_id, tanggal_kejadian, data_json, deskripsi, status)
             VALUES (2, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$jenisId, '2027-04-01', '{}', 'Fixture Draft', 'draft']);
        $stmt->execute([$jenisId, '2027-04-01', '{}', 'Fixture Submitted', 'submitted']);

        $stats = (new DashboardDataAggregator())->getLainnyaStats(2027);
        self::assertSame(1, (int) $stats['total_laporan']);
        self::assertSame(1, (int) $stats['draf']);

        self::assertSame(
            0,
            (int) $this->db->query(
                "SELECT COUNT(*) FROM laporan_hama WHERE status = 'Draf' AND nomor_laporan IS NOT NULL"
            )->fetchColumn()
        );
    }

    public function testDashboardPadiChoosesKsaAndReturnsOnePointPerYear(): void
    {
        $model = new DashboardPadi();
        $summary = $model->getSummary(2025);
        $trend = $model->getTrend(2025);
        $yearRows = array_values(array_filter(
            $trend,
            static fn(array $row): bool => (int) $row['tahun'] === 2025
        ));

        self::assertSame('ksa', $summary['bps_record']['sumber_data_type'] ?? null);
        self::assertCount(1, $yearRows);
    }

    public function testRainfallMapUsesMasterCoordinatesForActualData(): void
    {
        $rows = (new DashboardDataAggregator())->getWeatherMapData(['days' => 30]);

        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertNotEmpty($row['latitude']);
            self::assertNotEmpty($row['longitude']);
        }
    }

    public function testLaporanLainnyaOwnerComparisonHandlesPdoStringIds(): void
    {
        $row = $this->db->query(
            'SELECT id, user_id FROM laporan_lainnya ORDER BY id LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        $model = new LaporanLainnya();
        self::assertTrue($model->isOwner((int) $row['id'], (int) $row['user_id']));
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
