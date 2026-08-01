<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CacheManager;
use App\Core\Database;
use PDO;

class DashboardService
{
    private const ALL_STATUSES = ['Draf', 'Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'];
    private const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    private const CACHE_TTL = 300;

    private PDO $db;
    private string $role;
    private ?int $userId;
    private int $tahun;
    private bool $includeDraft = false;

    public function __construct(string $role, ?int $userId, int $tahun, bool $includeDraft = false)
    {
        $this->db = Database::connect();
        $this->role = $role;
        $this->userId = $role === 'petugas' ? $userId : null;
        $this->tahun = $tahun;
        $this->includeDraft = $includeDraft;
    }

    public static function validateTahun(int $tahun): int
    {
        $currentYear = (int) date('Y');
        if ($tahun < 2020 || $tahun > $currentYear + 1) {
            throw new \DomainException("Tahun harus antara 2020 dan " . ($currentYear + 1) . ".");
        }
        return $tahun;
    }

    public function getStats(): array
    {
        $draftFlag = $this->includeDraft ? ':draft' : '';
        $cacheKey = "dashboard:stats:{$this->role}:{$this->getUserId()}:{$this->tahun}{$draftFlag}";
        $cached = CacheManager::get($cacheKey);
        if ($cached !== null) {
            $cached['meta']['cached'] = true;
            return $cached;
        }

        $data = [
            'tahun' => $this->tahun,
            'hama' => $this->aggregateHamaStats(),
            'irigasi' => $this->aggregateIrigasiStats(),
            'meta' => [
                'cached' => false,
                'generated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        CacheManager::set($cacheKey, $data, self::CACHE_TTL);

        return $data;
    }

    public function getChartsHama(): array
    {
        $draftFlag = $this->includeDraft ? ':draft' : '';
        $cacheKey = "dashboard:charts:hama:{$this->role}:{$this->getUserId()}:{$this->tahun}{$draftFlag}";
        $cached = CacheManager::get($cacheKey);
        if ($cached !== null) {
            $cached['meta']['cached'] = true;
            return $cached;
        }

        $data = [
            'tahun' => $this->tahun,
            'meta' => ['cached' => false, 'generated_at' => date('Y-m-d H:i:s')],
            'labels' => self::MONTH_LABELS,
            'series' => $this->chartHamaSeries(),
            'by_keparahan_bulanan' => $this->chartHamaByKeparahan(),
        ];

        CacheManager::set($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }

    public function getChartsIrigasi(): array
    {
        $draftFlag = $this->includeDraft ? ':draft' : '';
        $cacheKey = "dashboard:charts:irigasi:{$this->role}:{$this->getUserId()}:{$this->tahun}{$draftFlag}";
        $cached = CacheManager::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $data = [
            'tahun' => $this->tahun,
            'labels' => self::MONTH_LABELS,
            'series' => $this->chartIrigasiSeries(),
        ];

        CacheManager::set($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }

    public function getMapHama(
        string $statusFilter = 'aktif',
        int $limit = 500,
        ?int $masterOptId = null,
        ?int $kecamatanId = null,
        ?int $desaId = null
    ): array {
        $allowedStatuses = $this->resolveMapStatuses($statusFilter);
        $filterKey = md5(
            implode(',', $allowedStatuses) .
            ($masterOptId ? ":opt{$masterOptId}" : '') .
            ($kecamatanId ? ":kec{$kecamatanId}" : '') .
            ($desaId ? ":des{$desaId}" : '')
        );
        $cacheKey = "dashboard:map:hama:{$this->role}:{$this->getUserId()}:{$this->tahun}:{$filterKey}";

        $cached = CacheManager::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $features = $this->mapQueryHama($allowedStatuses, min($limit, 1000), $masterOptId, $kecamatanId, $desaId);

        $data = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => [
                'count' => count($features),
                'limit' => min($limit, 1000),
                'tahun' => $this->tahun,
            ],
        ];

        CacheManager::set($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }

    public function getMapIrigasi(
        string $statusFilter = 'aktif',
        int $limit = 500,
        ?int $kecamatanId = null,
        ?int $desaId = null,
        ?string $kondisiFisik = null
    ): array {
        $allowedStatuses = $this->resolveMapStatuses($statusFilter);
        $filterKey = md5(
            implode(',', $allowedStatuses) .
            ($kecamatanId ? ":kec{$kecamatanId}" : '') .
            ($desaId ? ":des{$desaId}" : '') .
            ($kondisiFisik ? ":kon{$kondisiFisik}" : '')
        );
        $cacheKey = "dashboard:map:irigasi:{$this->role}:{$this->getUserId()}:{$this->tahun}:{$filterKey}";

        $cached = CacheManager::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $features = $this->mapQueryIrigasi($allowedStatuses, min($limit, 1000), $kecamatanId, $desaId, $kondisiFisik);

        $data = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => [
                'count' => count($features),
                'limit' => min($limit, 1000),
                'tahun' => $this->tahun,
            ],
        ];

        CacheManager::set($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }

    private function getUserId(): int
    {
        return $this->userId ?? 0;
    }

    private function activeStatuses(): array
    {
        return $this->includeDraft
            ? ['Draf', 'Submitted', 'Diverifikasi']
            : ['Submitted', 'Diverifikasi'];
    }

    private function statusInClause(): string
    {
        $statuses = $this->activeStatuses();
        return "status IN ('" . implode("','", $statuses) . "')";
    }

    private function userCondition(): string
    {
        return $this->userId !== null ? ' AND user_id = :userId' : '';
    }

    private function userParam(): array
    {
        return $this->userId !== null ? ['userId' => $this->userId] : [];
    }

    // --- Hama Stats ---

    private function aggregateHamaStats(): array
    {
        $counts = $this->countByStatus('laporan_hama');
        $luasSerangan = $this->sumLuasSerangan();
        $byKeparahan = $this->countByKeparahan();
        $topOpt = $this->topOpt(5);

        return [
            'total_submitted' => $counts['Submitted'] ?? 0,
            'total_diverifikasi' => $counts['Diverifikasi'] ?? 0,
            'total_aktif' => ($counts['Submitted'] ?? 0) + ($counts['Diverifikasi'] ?? 0),
            'total_ditolak' => $counts['Ditolak'] ?? 0,
            'total_draf' => $counts['Draf'] ?? 0,
            'total_diarsipkan' => $counts['Diarsipkan'] ?? 0,
            'luas_serangan_total' => $luasSerangan,
            'by_keparahan' => $byKeparahan,
            'top_opt' => $topOpt,
        ];
    }

    private function countByStatus(string $table): array
    {
        $sql = "SELECT status, COUNT(*) AS c FROM `{$table}` WHERE YEAR(tanggal) = :tahun";
        $params = ['tahun' => $this->tahun];

        if ($table === 'laporan_hama') {
            $sql .= $this->userCondition();
            $params = array_merge($params, $this->userParam());
        } else {
            if ($this->userId !== null) {
                $sql .= ' AND user_id = :userId';
                $params['userId'] = $this->userId;
            }
        }

        $sql .= ' GROUP BY status';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['status']] = (int) $row['c'];
        }

        foreach (self::ALL_STATUSES as $s) {
            if (!isset($result[$s])) {
                $result[$s] = 0;
            }
        }

        return $result;
    }

    private function sumLuasSerangan(): float
    {
        $sql = "SELECT COALESCE(SUM(luas_serangan), 0) AS total FROM `laporan_hama`
                WHERE YEAR(tanggal) = :tahun AND " . $this->statusInClause();
        $params = ['tahun' => $this->tahun];

        $sql .= $this->userCondition();
        $params = array_merge($params, $this->userParam());

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    private function countByKeparahan(): array
    {
        $sql = "SELECT tingkat_keparahan, COUNT(*) AS c FROM `laporan_hama`
                WHERE YEAR(tanggal) = :tahun AND " . $this->statusInClause();
        $params = ['tahun' => $this->tahun];

        $sql .= $this->userCondition();
        $params = array_merge($params, $this->userParam());

        $sql .= ' GROUP BY tingkat_keparahan';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['tingkat_keparahan']] = (int) $row['c'];
        }

        foreach (['Ringan', 'Sedang', 'Berat'] as $k) {
            if (!isset($result[$k])) {
                $result[$k] = 0;
            }
        }

        return $result;
    }

    private function topOpt(int $limit): array
    {
        $sql = "SELECT o.id AS master_opt_id, o.nama_opt, COUNT(*) AS jumlah
                FROM `laporan_hama` lh
                JOIN `master_opt` o ON o.id = lh.master_opt_id
                WHERE YEAR(lh.tanggal) = :tahun AND lh." . $this->statusInClause();
        $params = ['tahun' => $this->tahun];

        $sql .= $this->userCondition();
        $params = array_merge($params, $this->userParam());

        $sql .= ' GROUP BY o.id, o.nama_opt ORDER BY jumlah DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // --- Irigasi Stats ---

    private function aggregateIrigasiStats(): array
    {
        $counts = $this->countByStatus('laporan_irigasi');
        $byKondisiFisik = $this->countByField('laporan_irigasi', 'kondisi_fisik');
        $byDebitAir = $this->countByField('laporan_irigasi', 'debit_air');

        return [
            'total_submitted' => $counts['Submitted'] ?? 0,
            'total_diverifikasi' => $counts['Diverifikasi'] ?? 0,
            'total_aktif' => ($counts['Submitted'] ?? 0) + ($counts['Diverifikasi'] ?? 0),
            'total_ditolak' => $counts['Ditolak'] ?? 0,
            'total_draf' => $counts['Draf'] ?? 0,
            'total_diarsipkan' => $counts['Diarsipkan'] ?? 0,
            'by_kondisi_fisik' => $byKondisiFisik,
            'by_debit_air' => $byDebitAir,
        ];
    }

    private function countByField(string $table, string $field): array
    {
        $sql = "SELECT {$field}, COUNT(*) AS c FROM `{$table}`
                WHERE YEAR(tanggal) = :tahun AND " . $this->statusInClause();
        $params = ['tahun' => $this->tahun];

        if ($this->userId !== null) {
            $sql .= ' AND user_id = :userId';
            $params['userId'] = $this->userId;
        }

        $sql .= " GROUP BY {$field}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row[$field]] = (int) $row['c'];
        }

        return $result;
    }

    // --- Charts ---

    private function chartHamaSeries(): array
    {
        $sql = "SELECT MONTH(tanggal) AS m, status, COUNT(*) AS c
                FROM `laporan_hama`
                WHERE YEAR(tanggal) = :tahun AND " . $this->statusInClause();
        $params = ['tahun' => $this->tahun];

        $sql .= $this->userCondition();
        $params = array_merge($params, $this->userParam());

        $sql .= ' GROUP BY m, status';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $submitted = array_fill(0, 12, 0);
        $diverifikasi = array_fill(0, 12, 0);

        while ($row = $stmt->fetch()) {
            $idx = (int) $row['m'] - 1;
            if ($row['status'] === 'Submitted') {
                $submitted[$idx] = (int) $row['c'];
            } elseif ($row['status'] === 'Diverifikasi') {
                $diverifikasi[$idx] = (int) $row['c'];
            }
        }

        $aktif = [];
        for ($i = 0; $i < 12; $i++) {
            $aktif[$i] = $submitted[$i] + $diverifikasi[$i];
        }

        return [
            'submitted' => $submitted,
            'diverifikasi' => $diverifikasi,
            'aktif' => $aktif,
        ];
    }

    private function chartHamaByKeparahan(): array
    {
        $sql = "SELECT MONTH(tanggal) AS m, tingkat_keparahan, COUNT(*) AS c
                FROM `laporan_hama`
                WHERE YEAR(tanggal) = :tahun AND " . $this->statusInClause();
        $params = ['tahun' => $this->tahun];

        $sql .= $this->userCondition();
        $params = array_merge($params, $this->userParam());

        $sql .= ' GROUP BY m, tingkat_keparahan';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $ringan = array_fill(0, 12, 0);
        $sedang = array_fill(0, 12, 0);
        $berat = array_fill(0, 12, 0);

        while ($row = $stmt->fetch()) {
            $idx = (int) $row['m'] - 1;
            $k = $row['tingkat_keparahan'];
            if ($k === 'Ringan') {
                $ringan[$idx] = (int) $row['c'];
            } elseif ($k === 'Sedang') {
                $sedang[$idx] = (int) $row['c'];
            } elseif ($k === 'Berat') {
                $berat[$idx] = (int) $row['c'];
            }
        }

        return [
            'Ringan' => $ringan,
            'Sedang' => $sedang,
            'Berat' => $berat,
        ];
    }

    private function chartIrigasiSeries(): array
    {
        $sql = "SELECT MONTH(tanggal) AS m, status, COUNT(*) AS c
                FROM `laporan_irigasi`
                WHERE YEAR(tanggal) = :tahun AND " . $this->statusInClause();
        $params = ['tahun' => $this->tahun];

        if ($this->userId !== null) {
            $sql .= ' AND user_id = :userId';
            $params['userId'] = $this->userId;
        }

        $sql .= ' GROUP BY m, status';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $submitted = array_fill(0, 12, 0);
        $diverifikasi = array_fill(0, 12, 0);

        while ($row = $stmt->fetch()) {
            $idx = (int) $row['m'] - 1;
            if ($row['status'] === 'Submitted') {
                $submitted[$idx] = (int) $row['c'];
            } elseif ($row['status'] === 'Diverifikasi') {
                $diverifikasi[$idx] = (int) $row['c'];
            }
        }

        $aktif = [];
        for ($i = 0; $i < 12; $i++) {
            $aktif[$i] = $submitted[$i] + $diverifikasi[$i];
        }

        return [
            'submitted' => $submitted,
            'diverifikasi' => $diverifikasi,
            'aktif' => $aktif,
        ];
    }

    // --- Map ---

    private function resolveMapStatuses(string $statusFilter): array
    {
        return match ($statusFilter) {
            'Submitted' => ['Submitted'],
            'Diverifikasi' => ['Diverifikasi'],
            default => $this->activeStatuses(),
        };
    }

    private function mapQueryHama(
        array $statuses,
        int $limit,
        ?int $masterOptId = null,
        ?int $kecamatanId = null,
        ?int $desaId = null
    ): array {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT lh.id, lh.nomor_laporan, lh.status, lh.tanggal,
                       lh.latitude, lh.longitude, lh.tingkat_keparahan,
                       o.nama_opt, md.nama_desa, mkc.nama_kecamatan
                FROM `laporan_hama` lh
                LEFT JOIN `master_opt` o ON o.id = lh.master_opt_id
                LEFT JOIN `master_desa` md ON md.id = lh.desa_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = lh.kecamatan_id
                WHERE lh.latitude IS NOT NULL AND lh.longitude IS NOT NULL
                  AND lh.latitude != 0 AND lh.longitude != 0
                  AND YEAR(lh.tanggal) = ?
                  AND lh.status IN ({$placeholders})";

        $params = [$this->tahun];
        $params = array_merge($params, $statuses);

        if ($masterOptId !== null) {
            $sql .= ' AND lh.master_opt_id = ?';
            $params[] = $masterOptId;
        }

        if ($kecamatanId !== null) {
            $sql .= ' AND lh.kecamatan_id = ?';
            $params[] = $kecamatanId;
        }

        if ($desaId !== null) {
            $sql .= ' AND lh.desa_id = ?';
            $params[] = $desaId;
        }

        if ($this->userId !== null) {
            $sql .= ' AND lh.user_id = ?';
            $params[] = $this->userId;
        }

        $sql .= ' LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $features = [];
        while ($row = $stmt->fetch()) {
            $lng = (float) $row['longitude'];
            $lat = (float) $row['latitude'];
            $opt = $row['nama_opt'] ?? '-';
            $keparahan = $row['tingkat_keparahan'] ?? '-';

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'id' => (int) $row['id'],
                    'nomor_laporan' => $row['nomor_laporan'],
                    'status' => $row['status'],
                    'tanggal' => $row['tanggal'],
                    'desa' => $row['nama_desa'] ?? '',
                    'kecamatan' => $row['nama_kecamatan'] ?? '',
                    'opt' => $opt,
                    'tingkat_keparahan' => $keparahan,
                    'popup' => $row['nomor_laporan'] . ' · ' . $opt . ' · ' . $keparahan,
                ],
            ];
        }

        return $features;
    }

    private function mapQueryIrigasi(
        array $statuses,
        int $limit,
        ?int $kecamatanId = null,
        ?int $desaId = null,
        ?string $kondisiFisik = null
    ): array {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT li.id, li.nomor_laporan, li.status, li.tanggal,
                       li.latitude, li.longitude, li.nama_saluran,
                       li.kondisi_fisik, li.debit_air,
                       md.nama_desa, mkc.nama_kecamatan
                FROM `laporan_irigasi` li
                LEFT JOIN `master_desa` md ON md.id = li.desa_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = li.kecamatan_id
                WHERE li.latitude IS NOT NULL AND li.longitude IS NOT NULL
                  AND li.latitude != 0 AND li.longitude != 0
                  AND YEAR(li.tanggal) = ?
                  AND li.status IN ({$placeholders})";

        $params = [$this->tahun];
        $params = array_merge($params, $statuses);

        if ($kecamatanId !== null) {
            $sql .= ' AND li.kecamatan_id = ?';
            $params[] = $kecamatanId;
        }

        if ($desaId !== null) {
            $sql .= ' AND li.desa_id = ?';
            $params[] = $desaId;
        }

        if ($kondisiFisik !== null && $kondisiFisik !== '') {
            $sql .= ' AND li.kondisi_fisik = ?';
            $params[] = $kondisiFisik;
        }

        if ($this->userId !== null) {
            $sql .= ' AND li.user_id = ?';
            $params[] = $this->userId;
        }

        $sql .= ' LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $features = [];
        while ($row = $stmt->fetch()) {
            $lng = (float) $row['longitude'];
            $lat = (float) $row['latitude'];
            $namaSaluran = $row['nama_saluran'] ?? '-';
            $kondisi = $row['kondisi_fisik'] ?? '-';
            $debit = $row['debit_air'] ?? '-';

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'id' => (int) $row['id'],
                    'nomor_laporan' => $row['nomor_laporan'],
                    'status' => $row['status'],
                    'tanggal' => $row['tanggal'],
                    'desa' => $row['nama_desa'] ?? '',
                    'kecamatan' => $row['nama_kecamatan'] ?? '',
                    'nama_saluran' => $namaSaluran,
                    'kondisi_fisik' => $kondisi,
                    'debit_air' => $debit,
                    'popup' => $row['nomor_laporan'] . ' · ' . $namaSaluran . ' · ' . $kondisi,
                ],
            ];
        }

        return $features;
    }

    public static function invalidateCache(): int
    {
        return CacheManager::deletePrefix('dashboard:');
    }
}
