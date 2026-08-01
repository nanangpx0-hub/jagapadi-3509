<?php

class Wilayah extends Model {
    protected $table = 'master_kabupaten';

    public function getAllKabupaten() {
        $sql = "SELECT id, nama_kabupaten FROM master_kabupaten ORDER BY nama_kabupaten";
        return $this->query($sql);
    }

    public function getKecamatanByKabupaten($kabupatenId) {
        $sql = "SELECT id, nama_kecamatan, kabupaten_id FROM master_kecamatan WHERE kabupaten_id = ? ORDER BY nama_kecamatan";
        return $this->query($sql, [$kabupatenId]);
    }

    public function getDesaByKecamatan($kecamatanId) {
        $sql = "SELECT id, nama_desa, kecamatan_id FROM master_desa WHERE kecamatan_id = ? ORDER BY nama_desa";
        return $this->query($sql, [$kecamatanId]);
    }

    public function getWilayahHierarchy() {
        $sql = "
            SELECT
                kab.id AS kabupaten_id,
                kab.nama_kabupaten,
                kec.id AS kecamatan_id,
                kec.nama_kecamatan,
                des.id AS desa_id,
                des.nama_desa
            FROM master_kabupaten kab
            LEFT JOIN master_kecamatan kec ON kec.kabupaten_id = kab.id
            LEFT JOIN master_desa des ON des.kecamatan_id = kec.id
            ORDER BY kab.nama_kabupaten, kec.nama_kecamatan, des.nama_desa
        ";
        return $this->query($sql);
    }

    public function searchWilayah($query, $type = 'all') {
        $q = '%' . $query . '%';
        if ($type === 'kabupaten') {
            return $this->query("SELECT id, nama_kabupaten AS nama, 'kabupaten' AS tipe FROM master_kabupaten WHERE nama_kabupaten LIKE ? ORDER BY nama_kabupaten", [$q]);
        }
        if ($type === 'kecamatan') {
            return $this->query("SELECT id, nama_kecamatan AS nama, 'kecamatan' AS tipe FROM master_kecamatan WHERE nama_kecamatan LIKE ? ORDER BY nama_kecamatan", [$q]);
        }
        if ($type === 'desa') {
            return $this->query("SELECT id, nama_desa AS nama, 'desa' AS tipe FROM master_desa WHERE nama_desa LIKE ? ORDER BY nama_desa", [$q]);
        }

        $sql = "
            SELECT id, nama_kabupaten AS nama, 'kabupaten' AS tipe FROM master_kabupaten WHERE nama_kabupaten LIKE ?
            UNION ALL
            SELECT id, nama_kecamatan AS nama, 'kecamatan' AS tipe FROM master_kecamatan WHERE nama_kecamatan LIKE ?
            UNION ALL
            SELECT id, nama_desa AS nama, 'desa' AS tipe FROM master_desa WHERE nama_desa LIKE ?
            ORDER BY nama
            LIMIT 100
        ";
        return $this->query($sql, [$q, $q, $q]);
    }

    public function getWilayahStatistics() {
        $kab = $this->query("SELECT COUNT(*) AS total FROM master_kabupaten");
        $kec = $this->query("SELECT COUNT(*) AS total FROM master_kecamatan");
        $des = $this->query("SELECT COUNT(*) AS total FROM master_desa");

        return [
            'kabupaten' => (int)($kab[0]['total'] ?? 0),
            'kecamatan' => (int)($kec[0]['total'] ?? 0),
            'desa' => (int)($des[0]['total'] ?? 0)
        ];
    }

    public function getWilayahByCoordinates($lat, $lng, $radiusKm = 5) {
        // Fallback implementation: return nearest desa by direct coordinate fields if available.
        // If table does not contain coordinates, this safely returns empty data.
        $sql = "
            SELECT
                d.id,
                d.nama_desa,
                d.kecamatan_id,
                (
                    6371 * ACOS(
                        COS(RADIANS(?)) * COS(RADIANS(d.latitude)) *
                        COS(RADIANS(d.longitude) - RADIANS(?)) +
                        SIN(RADIANS(?)) * SIN(RADIANS(d.latitude))
                    )
                ) AS distance_km
            FROM master_desa d
            WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL
            HAVING distance_km <= ?
            ORDER BY distance_km ASC
            LIMIT 20
        ";

        try {
            return $this->query($sql, [$lat, $lng, $lat, $radiusKm]);
        } catch (Throwable $e) {
            return [];
        }
    }
}
