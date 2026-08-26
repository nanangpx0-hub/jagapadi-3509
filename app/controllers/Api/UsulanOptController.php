<?php
/**
 * Usulan OPT API Controller (JSON, runtime root)
 *
 * Endpoint untuk aplikasi mobile Flutter:
 * - GET  /api/v1/usulan-opt            → daftar milik user (atau semua untuk admin)
 * - GET  /api/v1/usulan-opt/{id}       → detail + foto + history
 * - POST /api/v1/usulan-opt            → create (action=draft|submit)
 * - PUT  /api/v1/usulan-opt/{id}       → update (Draf/Perlu Perbaikan milik sendiri)
 * - POST /api/v1/usulan-opt/{id}/submit    → Draf → Menunggu Review
 * - POST /api/v1/usulan-opt/{id}/resubmit  → Perlu Perbaikan → Menunggu Review
 * - POST /api/v1/usulan-opt/{id}/foto      → upload foto bukti (multipart, field: foto)
 *
 * Autentikasi: session (auth middleware root). Ownership di-enforce di service.
 */
class ApiUsulanOptController extends Controller
{
    private UsulanOptService $service;
    private UsulanPhotoUploader $photoUploader;

    public function __construct(?Container $container = null)
    {
        parent::__construct($container);
        require_once ROOT_PATH . '/app/services/UsulanOptService.php';
        require_once ROOT_PATH . '/app/services/UsulanPhotoUploader.php';
        require_once ROOT_PATH . '/app/models/UsulanOpt.php';
        $this->service = new UsulanOptService();
        $this->photoUploader = new UsulanPhotoUploader();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function ok(mixed $data, string $message = 'OK', array $meta = []): void
    {
        $payload = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        $this->json($payload);
    }

    private function fail(string $code, string $message, array $errors = [], int $status = 400): void
    {
        $payload = ['success' => false, 'error' => $code, 'message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        $this->json($payload, $status);
    }

    private function currentUser(): array
    {
        return [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => (string) ($_SESSION['role'] ?? 'petugas'),
        ];
    }

    private function formatProposal(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'nama_lokal' => $row['nama_lokal'] ?? null,
            'nama_nasional' => $row['nama_nasional'] ?? null,
            'jenis' => $row['jenis'] ?? null,
            'komoditas' => $row['komoditas'] ?? null,
            'tanggal_ditemukan' => $row['tanggal_ditemukan'] ?? null,
            'kabupaten_id' => isset($row['kabupaten_id']) ? (int) $row['kabupaten_id'] : null,
            'kecamatan_id' => isset($row['kecamatan_id']) ? (int) $row['kecamatan_id'] : null,
            'desa_id' => isset($row['desa_id']) ? (int) $row['desa_id'] : null,
            'alamat_lokasi' => $row['alamat_lokasi'] ?? null,
            'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
            'bagian_terserang' => $row['bagian_terserang'] ?? null,
            'pola_gejala' => $row['pola_gejala'] ?? null,
            'estimasi_terdampak' => isset($row['estimasi_terdampak']) ? (float) $row['estimasi_terdampak'] : null,
            'satuan_terdampak' => $row['satuan_terdampak'] ?? null,
            'tingkat_keyakinan' => $row['tingkat_keyakinan'] ?? null,
            'sumber_identifikasi' => $row['sumber_identifikasi'] ?? null,
            'ciri_ciri' => $row['ciri_ciri'] ?? null,
            'status' => $row['status'] ?? UsulanOpt::STATUS_DRAFT,
            'catatan_review' => $row['catatan_review'] ?? null,
            'master_opt_id' => isset($row['master_opt_id']) ? (int) $row['master_opt_id'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function canView(int $ownerId, string $role): bool
    {
        return $role === 'admin' || $ownerId === (int) ($_SESSION['user_id'] ?? 0);
    }

    // ── Endpoints ────────────────────────────────────────────────────────────

    /** GET /api/v1/usulan-opt */
    public function index(): void
    {
        $user = $this->currentUser();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
        $status = trim((string) ($_GET['status'] ?? ''));
        $search = trim((string) ($_GET['q'] ?? ''));

        $filters = array_filter([
            'status' => $status !== '' ? $status : null,
            'q' => $search !== '' ? $search : null,
        ], static fn ($v): bool => $v !== null && $v !== '');

        $userId = $user['role'] === 'admin' ? null : $user['id'];
        $paged = $this->service->model->paginateFiltered($filters, $page, $perPage, $userId);

        $data = array_map([$this, 'formatProposal'], $paged['data'] ?? []);

        $this->ok($data, 'Daftar usulan OPT', [
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int) ($paged['total'] ?? count($data)),
            'total_pages' => (int) ($paged['last_page'] ?? 1),
        ]);
    }

    /** GET /api/v1/usulan-opt/{id} */
    public function show($id): void
    {
        $user = $this->currentUser();
        $proposal = $this->service->model->findByIdDetailed((int) $id);
        if (!$proposal || !$this->canView((int) $proposal['user_id'], $user['role'])) {
            $this->fail('NotFound', 'Usulan OPT tidak ditemukan.', [], 404);
        }

        $photos = array_map(
            static fn (array $p): array => [
                'id' => (int) $p['id'],
                'url' => $p['file_path'] ?? null,
                'caption' => $p['caption'] ?? null,
            ],
            $this->service->model->getPhotos((int) $id)
        );
        $history = array_map(
            static fn (array $h): array => [
                'from_status' => $h['from_status'] ?? null,
                'to_status' => $h['to_status'] ?? null,
                'catatan' => $h['catatan'] ?? null,
                'changed_by' => isset($h['changed_by']) ? (int) $h['changed_by'] : null,
                'changed_at' => $h['created_at'] ?? null,
            ],
            $this->service->model->getHistory((int) $id)
        );

        $data = $this->formatProposal($proposal);
        $data['photos'] = $photos;
        $data['history'] = $history;

        $this->ok($data, 'Detail usulan OPT');
    }

    /** POST /api/v1/usulan-opt  body JSON: action=draft|submit + field usulan */
    public function store(): void
    {
        $user = $this->currentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $intent = ($input['action'] ?? 'draft') === 'submit' ? 'submit' : 'draft';

        $data = $this->service->normalize($input);
        $errors = $this->service->validate($data, $intent === 'submit');
        if ($errors !== []) {
            $this->fail('ValidationError', 'Data usulan tidak valid.', $errors, 422);
        }

        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $proposalId = $intent === 'submit'
                ? $this->service->createPendingFromOwner($user['id'], $data, $user['id'])
                : $this->service->createDraft($user['id'], $data, $user['id'], 'api');
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[ApiUsulanOpt] store failed: ' . $e->getMessage());
            $this->fail('ServerError', 'Gagal menyimpan usulan OPT.', [], 500);
        }

        $this->ok(
            ['id' => $proposalId, 'status' => $intent === 'submit' ? 'Menunggu Review' : 'Draf'],
            $intent === 'submit' ? 'Usulan OPT terkirim untuk review.' : 'Draf usulan OPT tersimpan.',
            [],
            201
        );
    }

    /** PUT /api/v1/usulan-opt/{id}  body JSON: field usulan (Draf/Perlu Perbaikan milik sendiri) */
    public function update($id): void
    {
        $user = $this->currentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $proposal = $this->service->model->findByIdDetailed((int) $id);
        if (!$proposal || (int) $proposal['user_id'] !== $user['id']) {
            $this->fail('NotFound', 'Usulan OPT tidak ditemukan.', [], 404);
        }
        $expectedStatus = (string) ($proposal['status']);
        if (!in_array($expectedStatus, UsulanOpt::OWNER_EDITABLE, true)) {
            $this->fail('Conflict', "Status $expectedStatus tidak dapat diedit.", [], 409);
        }

        $data = $this->service->normalize($input);
        $result = $this->service->updateProposal((int) $id, $user['id'], $expectedStatus, $data, $user['id']);

        if (!$result['ok']) {
            $status = match ($result['reason'] ?? '') {
                'status_conflict' => 409,
                'invalid' => 422,
                default => 400,
            };
            $this->fail(
                $result['reason'] ?? 'Invalid',
                $result['errors'][0] ?? 'Gagal memperbarui usulan.',
                $result['errors'] ?? [],
                $status
            );
        }

        $this->ok(['id' => (int) $id], 'Usulan OPT berhasil diperbarui.');
    }

    /** POST /api/v1/usulan-opt/{id}/submit */
    public function submit($id): void
    {
        $user = $this->currentUser();
        $result = $this->service->submitDraft((int) $id, $user['id'], $user['id']);
        if (!$result['ok']) {
            $this->handleTransitionFail($result);
        }
        $this->ok(['id' => (int) $id, 'status' => 'Menunggu Review'], 'Usulan OPT terkirim untuk review.');
    }

    /** POST /api/v1/usulan-opt/{id}/resubmit */
    public function resubmit($id): void
    {
        $user = $this->currentUser();
        $result = $this->service->resubmit((int) $id, $user['id'], $user['id']);
        if (!$result['ok']) {
            $this->handleTransitionFail($result);
        }
        $this->ok(['id' => (int) $id, 'status' => 'Menunggu Review'], 'Usulan OPT dikirim ulang untuk review.');
    }

    private function handleTransitionFail(array $result): void
    {
        $status = match ($result['reason'] ?? '') {
            'not_found' => 404,
            'forbidden' => 403,
            'status_conflict' => 409,
            'invalid' => 422,
            default => 400,
        };
        $this->fail(
            $result['reason'] ?? 'Invalid',
            $result['errors'][0] ?? 'Transisi status tidak diizinkan.',
            $result['errors'] ?? [],
            $status
        );
    }
}
