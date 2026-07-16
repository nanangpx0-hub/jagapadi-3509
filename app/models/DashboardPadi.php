<?php

class DashboardPadi extends Model {
    protected $table = 'produksi_gabah';

    private const JEMBER_KABUPATEN_ID = 9;
    private const JEMBER_MASTER_KABUPATEN_ID = '09';
    private const JEMBER_NAME = 'Jember';

    public function getAvailableYears(): array {
        $sql = "
            SELECT DISTINCT CAST(tahun AS UNSIGNED) AS tahun
            FROM produksi_gabah
            UNION
            SELECT DISTINCT CAST(tahun AS UNSIGNED) AS tahun
            FROM data_pertanian_bps
            WHERE kabupaten_kota LIKE ?
            ORDER BY tahun DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['%' . self::JEMBER_NAME . '%']);
        $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($years)) {
            return $years;
        }

        $currentYear = (int) date('Y');
        return range($currentYear, $currentYear - 4);
    }

    public function getKecamatanList(): array {
        $sql = "
            SELECT id, kode_kecamatan, nama_kecamatan
            FROM master_kecamatan
            WHERE kabupaten_id IN (?, ?) AND deleted_at IS NULL
            ORDER BY nama_kecamatan ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([self::JEMBER_KABUPATEN_ID, self::JEMBER_MASTER_KABUPATEN_ID]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getKecamatanById(int $kecamatanId): ?array {
        $sql = "
            SELECT id, kode_kecamatan, nama_kecamatan
            FROM master_kecamatan
            WHERE id = ? AND kabupaten_id IN (?, ?) AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$kecamatanId, self::JEMBER_KABUPATEN_ID, self::JEMBER_MASTER_KABUPATEN_ID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getSummary(int $year, ?int $kecamatanId = null): array {
        if ($kecamatanId !== null) {
            return $this->getProductionSummary($year, $kecamatanId);
        }

        $bpsSummary = $this->getBpsJemberSummary($year);
        if ($bpsSummary !== null) {
            $bpsSummary['operational_summary'] = $this->getProductionSummary($year, null);
            return $bpsSummary;
        }

        return $this->getProductionSummary($year, null);
    }

    public function getTrend(int $endYear, ?int $kecamatanId = null): array {
        if ($kecamatanId !== null) {
            return $this->getProductionTrend($endYear, $kecamatanId);
        }

        $bpsTrend = $this->getBpsJemberTrend($endYear);
        if (!empty($bpsTrend)) {
            return $bpsTrend;
        }

        return $this->getProductionTrend($endYear, null);
    }

    public function getKecamatanBreakdown(int $year): array {
        $sql = "
            SELECT
                pg.kecamatan_id,
                COALESCE(mk.nama_kecamatan, CONCAT('Kecamatan ID ', pg.kecamatan_id)) AS nama_kecamatan,
                COUNT(pg.id) AS jumlah_data,
                COALESCE(SUM(pg.luas_panen), 0) AS luas_panen,
                COALESCE(SUM(pg.produksi_total), 0) AS produksi,
                ROUND(
                    COALESCE(SUM(pg.produksi_total) / NULLIF(SUM(pg.luas_panen), 0) * 10, 0),
                    2
                ) AS produktivitas,
                SUM(CASE WHEN pg.status = 'verified' THEN 1 ELSE 0 END) AS verified_count,
                SUM(CASE WHEN pg.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN pg.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
            FROM produksi_gabah pg
            LEFT JOIN master_kecamatan mk ON pg.kecamatan_id = mk.id
            WHERE pg.tahun = ?
              AND COALESCE(pg.status, 'pending') <> 'draft'
            GROUP BY pg.kecamatan_id, mk.nama_kecamatan
            ORDER BY produksi DESC, luas_panen DESC, nama_kecamatan ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusBreakdown(int $year, ?int $kecamatanId = null): array {
        [$whereSql, $params] = $this->buildProductionWhere($year, $kecamatanId);

        $sql = "
            SELECT status, total
            FROM (
                SELECT COALESCE(pg.status, 'pending') AS status, COUNT(*) AS total
                FROM produksi_gabah pg
                {$whereSql}
                GROUP BY COALESCE(pg.status, 'pending')
            ) grouped_status
            ORDER BY FIELD(status, 'verified', 'pending', 'rejected', 'draft')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getProductionSummary(int $year, ?int $kecamatanId): array {
        [$whereSql, $params] = $this->buildProductionWhere($year, $kecamatanId);

        $sql = "
            SELECT
                COUNT(pg.id) AS jumlah_data,
                COUNT(DISTINCT pg.kecamatan_id) AS jumlah_kecamatan,
                COALESCE(SUM(pg.luas_panen), 0) AS luas_panen,
                COALESCE(SUM(pg.produksi_total), 0) AS produksi,
                ROUND(
                    COALESCE(SUM(pg.produksi_total) / NULLIF(SUM(pg.luas_panen), 0) * 10, 0),
                    2
                ) AS produktivitas,
                SUM(CASE WHEN pg.status = 'verified' THEN 1 ELSE 0 END) AS verified_count,
                SUM(CASE WHEN pg.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN pg.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                MAX(pg.updated_at) AS last_updated
            FROM produksi_gabah pg
            {$whereSql}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'source' => 'produksi_gabah',
            'source_label' => 'Input Produksi Gabah',
            'source_note' => 'Agregasi data produksi gabah non-draft per kecamatan.',
            'tahun' => $year,
            'luas_panen' => (float) ($summary['luas_panen'] ?? 0),
            'produksi' => (float) ($summary['produksi'] ?? 0),
            'produktivitas' => (float) ($summary['produktivitas'] ?? 0),
            'jumlah_data' => (int) ($summary['jumlah_data'] ?? 0),
            'jumlah_kecamatan' => (int) ($summary['jumlah_kecamatan'] ?? 0),
            'verified_count' => (int) ($summary['verified_count'] ?? 0),
            'pending_count' => (int) ($summary['pending_count'] ?? 0),
            'rejected_count' => (int) ($summary['rejected_count'] ?? 0),
            'last_updated' => $summary['last_updated'] ?? null,
        ];
    }

    private function getBpsJemberSummary(int $year): ?array {
        $sql = "
            SELECT *
            FROM data_pertanian_bps
            WHERE tahun = ? AND kabupaten_kota LIKE ?
            ORDER BY CASE WHEN kabupaten_kota = ? THEN 0 ELSE 1 END
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, '%' . self::JEMBER_NAME . '%', self::JEMBER_NAME]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'source' => 'data_pertanian_bps',
            'source_label' => 'Data BPS Kabupaten Jember',
            'source_note' => trim((string) ($row['sumber_data'] ?? 'Data pertanian BPS')),
            'tahun' => (int) $row['tahun'],
            'luas_panen' => (float) ($row['luas_panen'] ?? 0),
            'produksi' => (float) ($row['produksi_gabah'] ?? 0),
            'produktivitas' => (float) ($row['produktivitas'] ?? 0),
            'jumlah_data' => 1,
            'jumlah_kecamatan' => null,
            'verified_count' => null,
            'pending_count' => null,
            'rejected_count' => null,
            'last_updated' => $row['updated_at'] ?? $row['created_at'] ?? null,
            'bps_record' => $row,
        ];
    }

    private function getProductionTrend(int $endYear, ?int $kecamatanId): array {
        $startYear = $endYear - 4;
        $params = [$startYear, $endYear];
        $kecamatanSql = '';

        if ($kecamatanId !== null) {
            $kecamatanSql = ' AND pg.kecamatan_id = ?';
            $params[] = $kecamatanId;
        }

        $sql = "
            SELECT
                CAST(pg.tahun AS UNSIGNED) AS tahun,
                COALESCE(SUM(pg.luas_panen), 0) AS luas_panen,
                COALESCE(SUM(pg.produksi_total), 0) AS produksi,
                ROUND(
                    COALESCE(SUM(pg.produksi_total) / NULLIF(SUM(pg.luas_panen), 0) * 10, 0),
                    2
                ) AS produktivitas,
                COUNT(pg.id) AS jumlah_data,
                'Input Produksi Gabah' AS source_label
            FROM produksi_gabah pg
            WHERE pg.tahun BETWEEN ? AND ?
              AND COALESCE(pg.status, 'pending') <> 'draft'
              {$kecamatanSql}
            GROUP BY pg.tahun
            ORDER BY pg.tahun ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getBpsJemberTrend(int $endYear): array {
        $startYear = $endYear - 4;

        $sql = "
            SELECT
                CAST(tahun AS UNSIGNED) AS tahun,
                COALESCE(luas_panen, 0) AS luas_panen,
                COALESCE(produksi_gabah, 0) AS produksi,
                COALESCE(produktivitas, 0) AS produktivitas,
                1 AS jumlah_data,
                'Data BPS Kabupaten Jember' AS source_label
            FROM data_pertanian_bps
            WHERE tahun BETWEEN ? AND ?
              AND kabupaten_kota LIKE ?
            ORDER BY tahun ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startYear, $endYear, '%' . self::JEMBER_NAME . '%']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildProductionWhere(int $year, ?int $kecamatanId): array {
        $where = [
            'pg.tahun = ?',
            "COALESCE(pg.status, 'pending') <> 'draft'",
        ];
        $params = [$year];

        if ($kecamatanId !== null) {
            $where[] = 'pg.kecamatan_id = ?';
            $params[] = $kecamatanId;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }
}
