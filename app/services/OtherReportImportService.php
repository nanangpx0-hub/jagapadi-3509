<?php

declare(strict_types=1);

/**
 * Routes non-OPT rows from the Usulan OPT workbook into Laporan Lainnya drafts.
 */
final class OtherReportImportService
{
    public const ROUTABLE_TYPES = ['gangguan_sosial', 'faktor_abiotik'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function supports(string $jenis): bool
    {
        return in_array(strtolower(trim($jenis)), self::ROUTABLE_TYPES, true);
    }

    /** @param array<string,mixed> $data */
    public function categoryCode(array $data): string
    {
        $jenis = strtolower(trim((string) ($data['jenis'] ?? '')));
        if ($jenis === 'gangguan_sosial') {
            return 'gangguan_sosial';
        }

        $name = mb_strtolower(implode(' ', [
            (string) ($data['nama_lokal'] ?? ''),
            (string) ($data['nama_nasional'] ?? ''),
        ]));
        if (preg_match('/angin|puting|badai|banjir|rendaman|genangan/', $name) === 1) {
            return 'bencana_cuaca';
        }
        if (preg_match('/keracunan|fisiolog|asam|hara|nutrisi|salinitas|\bph\b/', $name) === 1) {
            return 'gangguan_fisiologis';
        }
        return 'faktor_abiotik';
    }

    /** @param array<string,mixed> $data */
    public function createDraft(int $ownerId, array $data, int $actorId): int
    {
        $categoryCode = $this->categoryCode($data);
        $category = $this->db->prepare(
            'SELECT id FROM master_jenis_laporan WHERE kode = ? AND is_active = 1 LIMIT 1'
        );
        $category->execute([$categoryCode]);
        $categoryId = (int) $category->fetchColumn();
        if ($categoryId <= 0) {
            throw new RuntimeException('Kategori Laporan Lainnya belum tersedia: ' . $categoryCode);
        }

        $name = trim((string) (($data['nama_nasional'] ?? '') ?: ($data['nama_lokal'] ?? '')));
        $dynamic = $this->dynamicFields($categoryCode, $name, $data);
        $descriptionParts = array_filter([
            $name,
            trim((string) ($data['ciri_ciri'] ?? '')),
            trim((string) ($data['pola_gejala'] ?? '')),
        ]);

        $model = new LaporanLainnya();
        $id = $model->createReport([
            'user_id' => $ownerId,
            'jenis_id' => $categoryId,
            'kabupaten_id' => $data['kabupaten_id'] ?? null,
            'kecamatan_id' => $data['kecamatan_id'] ?? null,
            'kode_laporan' => null,
            'desa_id' => $data['desa_id'] ?? null,
            'alamat_lengkap' => $data['alamat_lokasi'] ?? null,
            'foto_url' => null,
            'tanggal_kejadian' => $data['tanggal_ditemukan'] ?? null,
            'data_json' => json_encode($dynamic, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'deskripsi' => mb_substr(implode("\n", $descriptionParts), 0, 5000),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => 'draft',
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Laporan Lainnya gagal disimpan');
        }

        $audit = $this->db->prepare(
            'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $audit->execute([
            $actorId,
            'excel_import_draft',
            'laporan_lainnya',
            $id,
            'Draf Laporan Lainnya dibuat dari impor Excel Usulan OPT (' . $categoryCode . ').',
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
        return $id;
    }

    /** @param array<string,mixed> $data */
    public function importDuplicateExists(int $ownerId, array $data): bool
    {
        $categoryCode = $this->categoryCode($data);
        $nameField = match ($categoryCode) {
            'gangguan_sosial', 'gangguan_fisiologis' => 'nama_gangguan',
            'bencana_cuaca' => 'jenis_bencana',
            default => 'nama_faktor',
        };
        $name = trim((string) (($data['nama_nasional'] ?? '') ?: ($data['nama_lokal'] ?? '')));
        $stmt = $this->db->prepare(
            'SELECT 1 FROM laporan_lainnya ll '
            . 'JOIN master_jenis_laporan mjl ON mjl.id = ll.jenis_id '
            . 'WHERE ll.user_id = ? AND mjl.kode = ? AND ll.tanggal_kejadian <=> ? '
            . 'AND JSON_UNQUOTE(JSON_EXTRACT(ll.data_json, ?)) = ? AND ll.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([
            $ownerId,
            $categoryCode,
            $data['tanggal_ditemukan'] ?? null,
            '$.' . $nameField,
            $name,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $data */
    private function dynamicFields(string $categoryCode, string $name, array $data): array
    {
        $common = [
            'komoditas' => mb_substr((string) ($data['komoditas'] ?? ''), 0, 2000),
            'luas_terdampak_ha' => $this->hectareEstimate($data),
        ];
        return match ($categoryCode) {
            'gangguan_sosial' => ['nama_gangguan' => $name, 'sumber_gangguan' => $data['sumber_identifikasi'] ?? null] + $common,
            'bencana_cuaca' => ['jenis_bencana' => $name, 'tingkat_kerusakan' => $data['tingkat_keyakinan'] ?? null] + $common,
            'gangguan_fisiologis' => ['nama_gangguan' => $name, 'faktor_pemicu' => $data['sumber_identifikasi'] ?? null] + $common,
            default => ['nama_faktor' => $name, 'fase_tanaman' => $data['bagian_terserang'] ?? null] + $common,
        };
    }

    /** @param array<string,mixed> $data */
    private function hectareEstimate(array $data): ?float
    {
        $unit = mb_strtolower(trim((string) ($data['satuan_terdampak'] ?? '')));
        if (!in_array($unit, ['ha', 'hektar', 'hektare'], true)) {
            return null;
        }
        return isset($data['estimasi_terdampak']) ? (float) $data['estimasi_terdampak'] : null;
    }
}
