<?php

class DashboardPadi extends Model {
    protected $table = 'produksi_gabah';

    private const JEMBER_MASTER_KABUPATEN_ID = 1;
    private const JEMBER_NAME = 'Jember';

    public function getAvailableYears(): array {
        try {
            $sql = "
                SELECT DISTINCT CAST(tahun AS UNSIGNED) AS tahun
                FROM produksi_gabah
                UNION
                SELECT DISTINCT CAST(tahun AS UNSIGNED) AS tahun
                FROM data_pertanian_bps
                WHERE kabupaten_kota = ?
                ORDER BY tahun DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([self::JEMBER_NAME]);
            $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!empty($years)) {
                return $years;
            }
        } catch (PDOException $e) {
            error_log('DashboardPadi::getAvailableYears - ' . $e->getMessage());
        }

        $currentYear = (int) date('Y');
        return range($currentYear, $currentYear - 4);
    }

    public function getKecamatanList(): array {
        $sql = "
            SELECT id, kode, nama_kecamatan
            FROM master_kecamatan
            WHERE kabupaten_id = ?
            ORDER BY nama_kecamatan ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([self::JEMBER_MASTER_KABUPATEN_ID]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getKecamatanById(int $kecamatanId): ?array {
        $sql = "
            SELECT id, kode, nama_kecamatan
            FROM master_kecamatan
            WHERE id = ? AND kabupaten_id = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$kecamatanId, self::JEMBER_MASTER_KABUPATEN_ID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getSummary(int $year, ?int $kecamatanId = null, ?int $userId = null): array {
        // Petugas: selalu gunakan produksi_gabah dengan filter userId
        if ($userId !== null) {
            return $this->getProductionSummary($year, $kecamatanId, $userId);
        }

        // Admin/operator: logika existing tetap
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

    public function getTrend(int $endYear, ?int $kecamatanId = null, ?int $userId = null): array {
        // Petugas: selalu gunakan produksi_gabah dengan filter userId
        if ($userId !== null) {
            return $this->getProductionTrend($endYear, $kecamatanId, $userId);
        }

        // Admin: logika existing tetap
        if ($kecamatanId !== null) {
            return $this->getProductionTrend($endYear, $kecamatanId);
        }

        $bpsTrend = $this->getBpsJemberTrend($endYear);
        if (!empty($bpsTrend)) {
            return $bpsTrend;
        }

        return $this->getProductionTrend($endYear, null);
    }

    public function getKecamatanBreakdown(int $year, ?int $userId = null): array {
        try {
            [$whereSql, $params] = $this->buildProductionWhere($year, null, $userId);

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
                {$whereSql}
                GROUP BY pg.kecamatan_id, mk.nama_kecamatan
                ORDER BY produksi DESC, luas_panen DESC, nama_kecamatan ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('DashboardPadi::getKecamatanBreakdown - ' . $e->getMessage());
            return [];
        }
    }

    public function getStatusBreakdown(int $year, ?int $kecamatanId = null, ?int $userId = null): array {
        try {
            [$whereSql, $params] = $this->buildProductionWhere($year, $kecamatanId, $userId);

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
        } catch (PDOException $e) {
            error_log('DashboardPadi::getStatusBreakdown - ' . $e->getMessage());
            return [];
        }
    }

    private function getProductionSummary(int $year, ?int $kecamatanId = null, ?int $userId = null): array {
        try {
            [$whereSql, $params] = $this->buildProductionWhere($year, $kecamatanId, $userId);

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
        } catch (PDOException $e) {
            error_log('DashboardPadi::getProductionSummary - ' . $e->getMessage());
            return [
                'source' => 'produksi_gabah',
                'source_label' => 'Input Produksi Gabah (tidak tersedia)',
                'source_note' => 'Tabel belum tersedia.',
                'tahun' => $year,
                'luas_panen' => 0,
                'produksi' => 0,
                'produktivitas' => 0,
                'jumlah_data' => 0,
                'jumlah_kecamatan' => 0,
                'verified_count' => 0,
                'pending_count' => 0,
                'rejected_count' => 0,
                'last_updated' => null,
            ];
        }
    }

    private function getBpsJemberSummary(int $year): ?array {
        try {
            $sql = "
                SELECT *
                FROM data_pertanian_bps
                WHERE tahun = ?
                  AND kabupaten_kota LIKE ?
                  AND COALESCE(tipe_skenario, 'baseline') = 'baseline'
                ORDER BY CASE sumber_data_type
                             WHEN 'ksa' THEN 1
                             WHEN 'resmi_webapi' THEN 2
                             WHEN 'manual' THEN 3
                             ELSE 4
                         END,
                         COALESCE(is_validated, 0) DESC,
                         CASE WHEN kabupaten_kota = ? THEN 0 ELSE 1 END,
                         updated_at DESC,
                         id DESC
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$year, '%' . self::JEMBER_NAME . '%', self::JEMBER_NAME]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }
        } catch (PDOException $e) {
            error_log('DashboardPadi::getBpsJemberSummary - ' . $e->getMessage());
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

    private function getProductionTrend(int $endYear, ?int $kecamatanId = null, ?int $userId = null): array {
        try {
            $startYear = $endYear - 4;
            $params = [$startYear, $endYear];
            $kecamatanSql = '';
            $paramsUser = [];

            if ($kecamatanId !== null) {
                $kecamatanSql = ' AND pg.kecamatan_id = ?';
                $params[] = $kecamatanId;
            }

            if ($userId !== null) {
                $kecamatanSql .= ' AND pg.user_id = ?';
                $paramsUser = [$userId];
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
            $stmt->execute(array_merge($params, $paramsUser));

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('DashboardPadi::getProductionTrend - ' . $e->getMessage());
            return [];
        }
    }

    private function getBpsJemberTrend(int $endYear): array {
        try {
            $startYear = $endYear - 4;

            $sql = "
                SELECT
                    CAST(preferred.tahun AS UNSIGNED) AS tahun,
                    COALESCE(preferred.luas_panen, 0) AS luas_panen,
                    COALESCE(preferred.produksi_gabah, 0) AS produksi,
                    COALESCE(preferred.produktivitas, 0) AS produktivitas,
                    1 AS jumlah_data,
                    'Data BPS Kabupaten Jember' AS source_label
                FROM (
                    SELECT bps.*,
                           ROW_NUMBER() OVER (
                               PARTITION BY bps.tahun
                               ORDER BY CASE bps.sumber_data_type
                                            WHEN 'ksa' THEN 1
                                            WHEN 'resmi_webapi' THEN 2
                                            WHEN 'manual' THEN 3
                                            ELSE 4
                                        END,
                                        COALESCE(bps.is_validated, 0) DESC,
                                        bps.updated_at DESC,
                                        bps.id DESC
                           ) AS source_rank
                    FROM data_pertanian_bps bps
                    WHERE bps.tahun BETWEEN ? AND ?
                      AND bps.kabupaten_kota LIKE ?
                      AND COALESCE(bps.tipe_skenario, 'baseline') = 'baseline'
                ) preferred
                WHERE preferred.source_rank = 1
                ORDER BY preferred.tahun ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$startYear, $endYear, '%' . self::JEMBER_NAME . '%']);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('DashboardPadi::getBpsJemberTrend - ' . $e->getMessage());
            return [];
        }
    }

    private function buildProductionWhere(int $year, ?int $kecamatanId = null, ?int $userId = null): array {
        $where = [
            'pg.tahun = ?',
            "COALESCE(pg.status, 'pending') <> 'draft'",
        ];
        $params = [$year];

        if ($kecamatanId !== null) {
            $where[] = 'pg.kecamatan_id = ?';
            $params[] = $kecamatanId;
        }

        if ($userId !== null) {
            $where[] = 'pg.user_id = ?';
            $params[] = $userId;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }
}
