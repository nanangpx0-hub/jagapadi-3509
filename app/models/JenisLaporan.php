<?php
declare(strict_types=1);

class JenisLaporan extends Model {
    protected $table = 'master_jenis_laporan';
    protected $fillable = ['kode', 'nama', 'deskripsi', 'fields_json', 'is_active'];

    public function findAllActive(): array {
        $qb = new QueryBuilder();
        return $qb->table('master_jenis_laporan')
                  ->where('is_active', 1)
                  ->whereNull('deleted_at')
                  ->orderBy('nama', 'ASC')
                  ->get();
    }

    public function findByKode(string $kode): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('master_jenis_laporan')
                      ->where('kode', $kode)
                      ->where('is_active', 1)
                      ->whereNull('deleted_at')
                      ->limit(1)
                      ->get();
        return !empty($result) ? $result[0] : null;
    }

    public function findById(int $id): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('master_jenis_laporan')
                      ->where('id', $id)
                      ->where('is_active', 1)
                      ->whereNull('deleted_at')
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

    /* ================================================================
     * Metode admin (dipakai JenisLaporanController, role admin).
     * Semua query memakai prepared statement via QueryBuilder/Model.
     * ============================================================== */

    /** Semua jenis aktif (termasuk nonaktif), kecuali yang di recycle bin. */
    public function allOrdered(): array {
        $qb = new QueryBuilder();
        return $qb->table('master_jenis_laporan')
                  ->whereNull('deleted_at')
                  ->orderBy('is_active', 'DESC')
                  ->orderBy('nama', 'ASC')
                  ->get();
    }

    /** Cari by id tanpa filter aktif (admin boleh kelola nonaktif). */
    public function findByIdInclusive(int $id): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('master_jenis_laporan')
                      ->where('id', $id)
                      ->limit(1)
                      ->get();
        return !empty($result) ? $result[0] : null;
    }

    /** Cari by kode tanpa filter aktif (cek duplikat saat create/update). */
    public function findByKodeInclusive(string $kode): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table('master_jenis_laporan')
                      ->where('kode', $kode)
                      ->limit(1)
                      ->get();
        return !empty($result) ? $result[0] : null;
    }

    /** Semua jenis + jumlah pemakaian, satu query (halaman admin). */
    public function allOrderedWithUsage(): array {
        $stmt = $this->db->prepare(
            'SELECT m.*, (SELECT COUNT(*) FROM laporan_lainnya ll WHERE ll.jenis_id = m.id) AS dipakai '
            . 'FROM master_jenis_laporan m WHERE m.deleted_at IS NULL ORDER BY m.is_active DESC, m.nama ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Jumlah laporan yang memakai jenis ini (FK RESTRICT guard). */
    public function countUsage(int $id): int {        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM laporan_lainnya WHERE jenis_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public function setActive(int $id, bool $active): bool {
        $stmt = $this->db->prepare('UPDATE master_jenis_laporan SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }
}