<?php
/**
 * Wilayah Controller
 *
 * Controller untuk menangani data wilayah hierarkis (Kabupaten → Kecamatan → Desa)
 * Digunakan oleh cascading dropdown di halaman laporan/create
 *
 * @version 1.2.0
 * @author JAGAPADI System
 */
class WilayahController extends Controller {
    private $kabModel;
    private $kecModel;
    private $desaModel;

    public function __construct() {
        $this->kabModel  = $this->model('MasterKabupaten');
        $this->kecModel  = $this->model('MasterKecamatan');
        $this->desaModel = $this->model('MasterDesa');
    }

    /**
     * Get all kabupaten
     * GET /wilayah/kabupaten
     * Returns: { status, data: [{id, kode, nama_kabupaten}, ...] }
     */
    public function kabupaten() {
        try {
            $q     = isset($_GET['q']) && $_GET['q'] !== '' ? trim($_GET['q']) : null;
            $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));

            $raw = $q
                ? $this->kabModel->search($q, $limit)
                : $this->kabModel->getAllOrdered();

            // Normalize so each row always has string id, kode, nama_kabupaten
            $data = array_map(function (array $row): array {
                return [
                    'id'             => (string)($row['id'] ?? ''),
                    'kode'           => (string)($row['kode'] ?? ''),
                    'nama_kabupaten' => $row['nama_kabupaten'] ?? '',
                ];
            }, $raw);

            $this->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            error_log("WilayahController::kabupaten error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Gagal mengambil data kabupaten', 'data' => []], 500);
        }
    }

    /**
     * Get kecamatan by kabupaten ID
     * GET /wilayah/kecamatan/{kabupaten_id}
     *
     * Accepts all common kabupaten identifier formats:
     *   - DB id as integer   → "9"  or 9
     *   - DB id as zero-padded string → "09"
     *   - BPS kode           → "3509"
     *
     * Returns: { status, data: [{id, kode, nama_kecamatan, kabupaten_id}, ...] }
     */
    public function kecamatan($kabupatenId = null) {
        $kabupatenId = $kabupatenId ?? ($_GET['kabupaten_id'] ?? null);

        if ($kabupatenId === null || trim((string)$kabupatenId) === '') {
            $this->json(['status' => 'error', 'message' => 'kabupaten_id wajib diisi', 'data' => []], 400);
            return;
        }

        // Resolve to the actual DB id using flexible lookup
        $resolvedId = $this->resolveKabupatenId((string)$kabupatenId);

        if ($resolvedId === null) {
            error_log("WilayahController::kecamatan — kabupaten_id tidak ditemukan: " . $kabupatenId);
            $this->json([
                'status'  => 'success',
                'message' => 'Kabupaten tidak ditemukan',
                'data'    => [],
            ]);
            return;
        }

        try {
            $q     = isset($_GET['q']) && $_GET['q'] !== '' ? trim($_GET['q']) : null;
            $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));

            $raw = $this->kecModel->getByKabupaten($resolvedId, $q, $limit);

            if (empty($raw)) {
                $this->json([
                    'status'  => 'success',
                    'message' => 'Data kecamatan tidak ditemukan untuk kabupaten ini',
                    'data'    => [],
                ]);
                return;
            }

            // Normalize output
            $data = array_map(function (array $row): array {
                return [
                    'id'             => (string)($row['id'] ?? ''),
                    'kode'           => (string)($row['kode'] ?? ''),
                    'nama_kecamatan' => $row['nama_kecamatan'] ?? '',
                    'kabupaten_id'   => (string)($row['kabupaten_id'] ?? ''),
                ];
            }, $raw);

            $this->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            error_log("WilayahController::kecamatan error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Gagal mengambil data kecamatan', 'data' => []], 500);
        }
    }

    /**
     * Get desa by kecamatan ID
     * GET /wilayah/desa/{kecamatan_id}
     * Returns: { status, data: [{id, kode, nama_desa, kecamatan_id}, ...] }
     */
    public function desa($kecamatanId = null) {
        $kecamatanId = $kecamatanId ?? ($_GET['kecamatan_id'] ?? null);

        if (!$kecamatanId || trim((string)$kecamatanId) === '') {
            $this->json(['status' => 'error', 'message' => 'kecamatan_id wajib diisi', 'data' => []], 400);
            return;
        }

        // Cast to int for safety (kecamatan IDs are always numeric in this schema)
        $kecamatanId = (int)$kecamatanId;
        if ($kecamatanId <= 0) {
            $this->json(['status' => 'error', 'message' => 'kecamatan_id tidak valid', 'data' => []], 400);
            return;
        }

        try {
            $q     = isset($_GET['q']) && $_GET['q'] !== '' ? trim($_GET['q']) : null;
            $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));

            $raw = $this->desaModel->getByKecamatan($kecamatanId, $q, $limit);

            if (empty($raw)) {
                $this->json([
                    'status'  => 'success',
                    'message' => 'Data desa tidak ditemukan untuk kecamatan ini',
                    'data'    => [],
                ]);
                return;
            }

            // Normalize output
            $data = array_map(function (array $row): array {
                return [
                    'id'           => (string)($row['id'] ?? ''),
                    'kode'         => (string)($row['kode'] ?? ''),
                    'nama_desa'    => $row['nama_desa'] ?? '',
                    'kecamatan_id' => (string)($row['kecamatan_id'] ?? ''),
                ];
            }, $raw);

            $this->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            error_log("WilayahController::desa error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Gagal mengambil data desa', 'data' => []], 500);
        }
    }

    /**
     * Resolve any kabupaten identifier format to the actual DB string id.
     *
     * Tries in order:
     *  1. Exact match on master_kabupaten.id  (e.g., "9", "09", "1")
     *  2. Exact match on master_kabupaten.kode (e.g., "3509")
     *  3. BPS kode derived from numeric value  (e.g., "9" → "3509")
     *
     * @param string $input Raw identifier from URL / JS
     * @return string|null Resolved DB id, or null if not found
     */
    private function resolveKabupatenId(string $input): ?string {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $record = $this->kabModel->findByIdOrKode($input);
        if ($record) {
            return (string)$record['id'];
        }

        // If single digit, try zero-padded form
        if (ctype_digit($input) && strlen($input) === 1) {
            $padded = str_pad($input, 2, '0', STR_PAD_LEFT);
            $record = $this->kabModel->findByIdOrKode($padded);
            if ($record) {
                return (string)$record['id'];
            }
        }

        return null;
    }
}
