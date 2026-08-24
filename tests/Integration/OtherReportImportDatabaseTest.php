<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OtherReportImportDatabaseTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance()->getConnection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testFiveNonOptRowsBecomeCategorizedOtherReportDrafts(): void
    {
        $ownerId = (int) $this->db->query('SELECT id FROM users WHERE aktif = 1 ORDER BY id LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $ownerId);
        $service = new OtherReportImportService($this->db);
        $rows = [
            ['jenis' => 'gangguan_sosial', 'nama_lokal' => 'Penggembalaan Liar', 'komoditas' => 'Padi'],
            ['jenis' => 'faktor_abiotik', 'nama_lokal' => 'Kekeringan / Puso', 'komoditas' => 'Padi'],
            ['jenis' => 'faktor_abiotik', 'nama_lokal' => 'Banjir / Rendaman Air', 'komoditas' => 'Padi'],
            ['jenis' => 'faktor_abiotik', 'nama_lokal' => 'Rebah Angin / Puting Beliung', 'komoditas' => 'Jagung'],
            ['jenis' => 'faktor_abiotik', 'nama_lokal' => 'Asam-asaman / Keracunan Besi', 'komoditas' => 'Padi'],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $service->createDraft($ownerId, $row + [
                'tanggal_ditemukan' => date('Y-m-d'),
                'ciri_ciri' => 'Data uji impor non-OPT',
                'estimasi_terdampak' => 0,
                'satuan_terdampak' => 'hektare',
            ], $ownerId);
        }

        self::assertTrue($service->importDuplicateExists($ownerId, $rows[0] + [
            'tanggal_ditemukan' => date('Y-m-d'),
        ]));

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT mjl.kode, COUNT(*) AS total FROM laporan_lainnya ll "
            . 'JOIN master_jenis_laporan mjl ON mjl.id = ll.jenis_id '
            . "WHERE ll.id IN ({$placeholders}) AND ll.status = 'draft' GROUP BY mjl.kode"
        );
        $stmt->execute($ids);
        $counts = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'kode');

        self::assertSame(1, (int) ($counts['gangguan_sosial'] ?? 0));
        self::assertSame(1, (int) ($counts['faktor_abiotik'] ?? 0));
        self::assertSame(2, (int) ($counts['bencana_cuaca'] ?? 0));
        self::assertSame(1, (int) ($counts['gangguan_fisiologis'] ?? 0));
    }
}
