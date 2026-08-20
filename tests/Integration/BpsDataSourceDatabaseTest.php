<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BpsDataSourceDatabaseTest extends TestCase
{
    private PDO $db;
    private DataPertanianBps $model;

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $this->db->beginTransaction();
        $this->model = new DataPertanianBps();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testSourcesRemainSeparateAndPreferredReadChoosesKsa(): void
    {
        $base = [
            'tahun' => 2098,
            'kode_provinsi' => '35',
            'kabupaten_kota' => 'Kabupaten Fixture BPS',
            'kode_wilayah' => '3598',
            'luas_panen' => 100.0,
            'produksi_gabah' => 500.0,
            'produksi_beras' => 288.5,
            'produktivitas' => 50.0,
            'tipe_skenario' => 'baseline',
            'is_validated' => 1,
        ];

        self::assertTrue($this->model->insert($base + [
            'sumber_data' => 'Simulasi Fixture',
            'sumber_data_type' => 'simulasi',
        ]));
        self::assertTrue($this->model->insert(array_merge($base, [
            'produksi_gabah' => 620.0,
            'produksi_beras' => 357.74,
            'produktivitas' => 62.0,
            'sumber_data' => 'KSA BPS Fixture',
            'sumber_data_type' => 'ksa',
        ])));

        $all = $this->model->getAll([
            'tahun' => 2098,
            'kabupaten_kota' => 'Kabupaten Fixture BPS',
        ]);
        $preferred = $this->model->getAll([
            'tahun' => 2098,
            'kabupaten_kota' => 'Kabupaten Fixture BPS',
            'tipe_skenario' => 'baseline',
            'is_validated' => 1,
            'preferred_only' => true,
        ]);

        self::assertCount(2, $all);
        self::assertCount(1, $preferred);
        self::assertSame('ksa', $preferred[0]['sumber_data_type']);
        self::assertSame('620.00', (string) $preferred[0]['produksi_gabah']);
    }

    public function testStatisticsUseWeightedProductivity(): void
    {
        $records = [
            ['nama' => 'Fixture Weighted A', 'luas' => 100.0, 'gabah' => 500.0],
            ['nama' => 'Fixture Weighted B', 'luas' => 900.0, 'gabah' => 6300.0],
        ];

        foreach ($records as $record) {
            self::assertTrue($this->model->insert([
                'tahun' => 2097,
                'kode_provinsi' => '35',
                'kabupaten_kota' => $record['nama'],
                'luas_panen' => $record['luas'],
                'produksi_gabah' => $record['gabah'],
                'produksi_beras' => $record['gabah'] * 0.577,
                'produktivitas' => $record['gabah'] / $record['luas'] * 10,
                'sumber_data' => 'Fixture Manual',
                'sumber_data_type' => 'manual',
                'tipe_skenario' => 'baseline',
                'is_validated' => 1,
            ]));
        }

        $stats = $this->model->getStatistics(2097, [
            'kabupaten_kota' => 'Fixture Weighted',
            'sumber_data_type' => 'manual',
            'tipe_skenario' => 'baseline',
            'is_validated' => 1,
        ]);

        self::assertSame(2, (int) $stats['jumlah_kabupaten']);
        self::assertSame(68.0, (float) $stats['rata_produktivitas']);
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
