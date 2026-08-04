<?php
declare(strict_types=1);

class JenisLaporan extends Model {
    protected $table = 'master_jenis_laporan';

    public function findAllActive(): array {
        $qb = new QueryBuilder();
        return $qb->table('master_jenis_laporan')
                  ->where('is_active', 1)
                  ->orderBy('nama', 'ASC')
                  ->get();
    }

    public function findByKode(string $kode): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('master_jenis_laporan')
                     ->where('kode', $kode)
                     ->where('is_active', 1)
                     ->limit(1)
                     ->get();
        return !empty($result) ? $result[0] : null;
    }

    public function findById(int $id): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('master_jenis_laporan')
                     ->where('id', $id)
                     ->where('is_active', 1)
                     ->limit(1)
                     ->get();
        return !empty($result) ? $result[0] : null;
    }

    public function getFields(int $jenisId): array {
        $jenis = $this->findById($jenisId);
        if (!$jenis || empty($jenis['fields_json'])) {
            return [];
        }
        $fields = json_decode($jenis['fields_json'], true);
        return is_array($fields) ? $fields : [];
    }

    public function getFieldsByKode(string $kode): array {
        $jenis = $this->findByKode($kode);
        if (!$jenis || empty($jenis['fields_json'])) {
            return [];
        }
        $fields = json_decode($jenis['fields_json'], true);
        return is_array($fields) ? $fields : [];
    }
}