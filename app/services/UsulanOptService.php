<?php

declare(strict_types=1);

/**
 * Service domain sisi Petugas untuk Usulan OPT: validasi payload,
 * create draft, update bersyarat, submit/resubmit, delete draft,
 * dan jalur integrasi dari Laporan Hama.
 *
 * Keputusan review (approve/merge/reject/request-revision) berada di
 * UsulanOptReviewService dan tetap kewenangan Admin.
 */
final class UsulanOptService
{
    public const KEYAKINAN = ['Rendah', 'Sedang', 'Tinggi'];
    public const JENIS = ['hama', 'penyakit', 'gulma'];

    public const REASON_NOT_FOUND = 'not_found';
    public const REASON_FORBIDDEN = 'forbidden';
    public const REASON_STATUS_CONFLICT = 'status_conflict';
    public const REASON_INVALID = 'invalid';

    public const NOTIF_TYPE_RECEIVED = UsulanOptReviewService::NOTIF_RECEIVED;

    public const BULK_DELETE_PROTECTED = [UsulanOpt::STATUS_APPROVED, UsulanOpt::STATUS_MERGED];
    public const BULK_DELETE_MAX = 500;

    private const TEXT_MAX = [
        'nama_lokal' => 200,
        'nama_nasional' => 150,
        'komoditas' => 150,
        'alamat_lokasi' => 300,
        'bagian_terserang' => 150,
        'pola_gejala' => 300,
        'satuan_terdampak' => 30,
        'sumber_identifikasi' => 255,
        'wilayah' => 255,
    ];

    private PDO $db;
    private UsulanOpt $model;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->model = new UsulanOpt();
    }

    /**
     * Normalisasi payload Petugas: trim, nullify kosong, cast numerik/enum.
     * Field administratif (status/reviewer/master/user_id) diabaikan total.
     *
     * @return array<string,mixed>
     */
    public function normalize(array $input): array
    {
        $data = [];
        foreach (array_keys(self::TEXT_MAX) as $field) {
            $data[$field] = trim((string) ($input[$field] ?? ''));
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }

        $data['jenis'] = strtolower(trim((string) ($input['jenis'] ?? '')));
        $data['ciri_ciri'] = trim((string) ($input['ciri_ciri'] ?? ''));
        if ($data['ciri_ciri'] === '') {
            $data['ciri_ciri'] = null;
        }
        $data['tingkat_keyakinan'] = trim((string) ($input['tingkat_keyakinan'] ?? ''));
        if ($data['tingkat_keyakinan'] === '') {
            $data['tingkat_keyakinan'] = null;
        }
        if (!in_array($data['tingkat_keyakinan'], self::KEYAKINAN, true)) {
            $data['tingkat_keyakinan'] = null;
        }

        foreach (['kabupaten_id', 'kecamatan_id', 'desa_id'] as $field) {
            $raw = $input[$field] ?? null;
            $data[$field] = ($raw !== null && $raw !== '' && is_numeric($raw)) ? (int) $raw : null;
        }

        $tanggal = trim((string) ($input['tanggal_ditemukan'] ?? ''));
        $dt = DateTime::createFromFormat('Y-m-d', $tanggal);
        $data['tanggal_ditemukan'] = ($dt && $dt->format('Y-m-d') === $tanggal) ? $tanggal : null;

        foreach (['latitude', 'longitude', 'estimasi_terdampak'] as $field) {
            $raw = $input[$field] ?? null;
            $data[$field] = ($raw !== null && $raw !== '' && is_numeric($raw)) ? (float) $raw : null;
        }

        return $data;
    }

    /**
     * Validasi field-level. Tidak menyentuh database wilayah.
     *
     * @return string[] pesan error aman
     */
    public function validate(array $data, bool $forSubmit = false): array
    {
        $errors = [];

        if (($data['nama_lokal'] ?? null) === null || trim((string) $data['nama_lokal']) === '') {
            $errors[] = 'Nama lokal/daerah wajib diisi';
        }
        $jenis = trim((string) ($data['jenis'] ?? ''));
        if ($jenis === '') {
            $errors[] = 'Jenis usulan wajib dipilih';
        } elseif (!in_array($jenis, self::JENIS, true)) {
            $errors[] = sprintf(
                'Jenis usulan "%s" tidak valid. Gunakan: hama, penyakit, atau gulma',
                mb_substr($jenis, 0, 50)
            );
        }
        if (($data['komoditas'] ?? null) === null || trim((string) $data['komoditas']) === '') {
            $errors[] = 'Komoditas yang diserang wajib diisi';
        }
        $ciri = trim((string) ($data['ciri_ciri'] ?? ''));
        if ($ciri === '') {
            $errors[] = 'Ciri-ciri/gejala wajib diisi';
        } elseif (mb_strlen($ciri) > 5000) {
            $errors[] = 'Ciri-ciri maksimal 5000 karakter';
        }
        if (($data['tanggal_ditemukan'] ?? null) === null) {
            $errors[] = 'Tanggal ditemukan wajib diisi (format YYYY-MM-DD)';
        } elseif ($data['tanggal_ditemukan'] > date('Y-m-d')) {
            $errors[] = 'Tanggal ditemukan tidak boleh di masa depan';
        }

        foreach ([['latitude', -90, 90], ['longitude', -180, 180]] as [$field, $min, $max]) {
            $value = $data[$field] ?? null;
            if ($value !== null && ($value < $min || $value > $max)) {
                $label = $field === 'latitude' ? 'Latitude' : 'Longitude';
                $errors[] = "{$label} harus di antara {$min} dan {$max}";
            }
        }
        $hasLat = ($data['latitude'] ?? null) !== null;
        $hasLng = ($data['longitude'] ?? null) !== null;
        if ($hasLat !== $hasLng) {
            $errors[] = 'Latitude dan longitude harus diisi keduanya atau dikosongkan keduanya';
        }

        $estimasi = $data['estimasi_terdampak'] ?? null;
        if ($estimasi !== null && $estimasi < 0) {
            $errors[] = 'Perkiraan luas/jumlah terdampak tidak boleh negatif';
        }
        $hasSatuan = ($data['satuan_terdampak'] ?? null) !== null;
        if (($estimasi !== null) !== $hasSatuan) {
            $errors[] = 'Satuan terdampak wajib diisi bila perkiraan terdampak diisi, dan sebaliknya';
        }

        foreach (self::TEXT_MAX as $field => $max) {
            $value = $data[$field] ?? null;
            if (is_string($value) && mb_strlen($value) > $max) {
                $label = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} maksimal {$max} karakter";
            }
        }

        if ($forSubmit) {
            $wilayahErrors = $this->validateWilayahFields($data);
            $errors = array_merge($errors, $wilayahErrors);
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return string[]
     */
    private function validateWilayahFields(array $data): array
    {
        $errors = [];
        foreach (['kabupaten_id', 'kecamatan_id', 'desa_id'] as $field) {
            if (($data[$field] ?? null) === null) {
                $label = str_replace('_id', '', $field);
                $errors[] = ucfirst($label) . ' wajib dipilih saat mengirim review';
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        $hierarchy = $this->resolveWilayah(
            (int) $data['kabupaten_id'],
            (int) $data['kecamatan_id'],
            (int) $data['desa_id']
        );
        if (!$hierarchy['ok']) {
            $errors[] = $hierarchy['error'];
        }

        return $errors;
    }

    /**
     * Validasi authoritative hierarki kabupaten-kecamatan-desa.
     *
     * @return array{ok:bool,error?:string,kabupaten?:string,kecamatan?:string,desa?:string,wilayah?:string}
     */
    public function resolveWilayah(int $kabupatenId, int $kecamatanId, int $desaId): array
    {
        $stmt = $this->db->prepare('SELECT id, nama_kabupaten FROM master_kabupaten WHERE id = ? LIMIT 1');
        $stmt->execute([$kabupatenId]);
        $kab = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$kab) {
            return ['ok' => false, 'error' => 'Kabupaten tidak ditemukan'];
        }

        $stmt = $this->db->prepare('SELECT id, nama_kecamatan, kabupaten_id FROM master_kecamatan WHERE id = ? LIMIT 1');
        $stmt->execute([$kecamatanId]);
        $kec = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$kec || (int) $kec['kabupaten_id'] !== $kabupatenId) {
            return ['ok' => false, 'error' => 'Kecamatan tidak ditemukan atau bukan bagian dari kabupaten yang dipilih'];
        }

        $stmt = $this->db->prepare('SELECT id, nama_desa, kecamatan_id FROM master_desa WHERE id = ? LIMIT 1');
        $stmt->execute([$desaId]);
        $desa = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$desa || (int) $desa['kecamatan_id'] !== $kecamatanId) {
            return ['ok' => false, 'error' => 'Desa tidak ditemukan atau bukan bagian dari kecamatan yang dipilih'];
        }

        return [
            'ok' => true,
            'kabupaten' => (string) $kab['nama_kabupaten'],
            'kecamatan' => (string) $kec['nama_kecamatan'],
            'desa' => (string) $desa['nama_desa'],
            'wilayah' => implode(', ', [$kab['nama_kabupaten'], $kec['nama_kecamatan'], $desa['nama_desa']]),
        ];
    }

    /**
     * Simpan usulan baru milik owner (session user).
     *
     * @param array<string,mixed> $data hasil normalize()
     * @return int id usulan
     */
    public function createDraft(int $ownerId, array $data, int $actorId, string $source = 'form'): int
    {
        $row = $this->buildRow($ownerId, $data, UsulanOpt::STATUS_DRAFT);

        $id = (int) $this->insertRow($row);
        $this->model->addHistory($id, null, UsulanOpt::STATUS_DRAFT, $actorId, 'Draf dibuat');
        $this->writeAudit($actorId, 'create_draft', $id, 'Usulan OPT draf dibuat');

        return $id;
    }

    /**
     * Admin workbook imports are review batches, not unfinished owner forms.
     * They enter the queue directly so review actions remain limited to Pending.
     *
     * @param array<string,mixed> $data
     */
    public function createPendingAdminImport(int $ownerId, array $data, int $actorId): int
    {
        $row = $this->buildRow($ownerId, $data, UsulanOpt::STATUS_PENDING);
        $row['submitted_at'] = date('Y-m-d H:i:s');

        $id = (int) $this->insertRow($row);
        $this->model->addHistory(
            $id,
            null,
            UsulanOpt::STATUS_PENDING,
            $actorId,
            'Impor Excel Admin langsung masuk antrean review'
        );
        $this->writeAudit(
            $actorId,
            'admin_excel_import_pending',
            $id,
            'Usulan OPT dari impor Excel Admin masuk antrean review.'
        );
        return $id;
    }

    /** @param array<string,mixed> $data */
    public function importDuplicateExists(int $ownerId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM usulan_opt WHERE user_id = ? AND jenis = ? '
            . 'AND COALESCE(nama_lokal, \'\') = ? AND COALESCE(nama_nasional, \'\') = ? '
            . 'AND COALESCE(komoditas, \'\') = ? AND tanggal_ditemukan <=> ? '
            . 'AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([
            $ownerId,
            $data['jenis'] ?? '',
            $data['nama_lokal'] ?? '',
            $data['nama_nasional'] ?? '',
            $data['komoditas'] ?? '',
            $data['tanggal_ditemukan'] ?? null,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Jalur Laporan Hama: usulan langsung Menunggu Review, dipanggil DI DALAM
     * transaksi laporan agar atomik dengan penyimpanan laporan.
     */
    public function createFromLaporan(int $ownerId, array $data, int $actorId, ?string $fotoUrl): int
    {
        $row = $this->buildRow($ownerId, $data, UsulanOpt::STATUS_PENDING);
        $row['foto_url'] = $fotoUrl;
        $row['submitted_at'] = date('Y-m-d H:i:s');
        $row['ciri_ciri'] = $data['ciri_ciri'] ?? null;

        $id = (int) $this->insertRow($row);
        $this->model->addHistory($id, null, UsulanOpt::STATUS_PENDING, $actorId, 'Dibuat dari Laporan Hama');
        $this->notifyOwner($ownerId, $id, 'usulan_diterima', 'Usulan OPT terkirim', 'Usulan dari Laporan Hama Anda menunggu review Admin.');
        $this->writeAudit($actorId, 'create_from_laporan', $id, 'Usulan OPT dibuat dari Laporan Hama');

        return $id;
    }

    /**
     * Update konten oleh pemilik pada status Draf/Perlu Perbaikan.
     * Conditional update mencegah lost update saat Admin mereview bersamaan.
     *
     * @return array{ok:bool,reason?:string,errors?:array}
     */
    public function updateProposal(int $proposalId, int $ownerId, string $expectedStatus, array $data, int $actorId): array
    {
        $proposal = $this->model->findByIdDetailed($proposalId);
        if (!$proposal) {
            return ['ok' => false, 'reason' => self::REASON_NOT_FOUND];
        }
        if ((int) $proposal['user_id'] !== $ownerId) {
            return ['ok' => false, 'reason' => self::REASON_FORBIDDEN];
        }
        if (!in_array($expectedStatus, UsulanOpt::OWNER_EDITABLE, true)) {
            throw new InvalidArgumentException('Status ekspektasi tidak valid.');
        }
        if ($proposal['status'] !== $expectedStatus) {
            return ['ok' => false, 'reason' => self::REASON_STATUS_CONFLICT];
        }

        $errors = $this->validate($data);
        if ($errors !== []) {
            return ['ok' => false, 'reason' => self::REASON_INVALID, 'errors' => $errors];
        }

        $hierarchy = $this->resolveWilayahIfComplete($data);
        $fields = $this->buildUpdatableFields($data, $hierarchy);

        $affected = $this->model->conditionalUpdateWithStatus($proposalId, $expectedStatus, $fields);
        if ($affected === 0) {
            return ['ok' => false, 'reason' => self::REASON_STATUS_CONFLICT];
        }

        $this->model->addHistory($proposalId, $expectedStatus, $expectedStatus, $actorId, 'Data usulan diperbarui pemilik');
        $this->writeAudit($actorId, 'update_draft', $proposalId, 'Usulan OPT diperbarui pemilik');

        return ['ok' => true];
    }

    /**
     * Draf → Menunggu Review dengan validasi kelengkapan final + foto minimum.
     *
     * @return array{ok:bool,reason?:string,errors?:array}
     */
    public function submitDraft(int $proposalId, int $ownerId, int $actorId): array
    {
        return $this->transitionToPending($proposalId, $ownerId, UsulanOpt::STATUS_DRAFT, $actorId, 'submit', 'usulan_diterima', 'Usulan OPT Anda diterima dan menunggu review Admin.');
    }

    /**
     * Perlu Perbaikan → Menunggu Review.
     *
     * @return array{ok:bool,reason?:string,errors?:array}
     */
    public function resubmit(int $proposalId, int $ownerId, int $actorId): array
    {
        return $this->transitionToPending($proposalId, $ownerId, UsulanOpt::STATUS_REVISION, $actorId, 'resubmit', 'usulan_dikirim_ulang', 'Petugas mengirim ulang usulan setelah perbaikan.');
    }

    private function transitionToPending(int $proposalId, int $ownerId, string $fromStatus, int $actorId, string $auditAction, string $notifType, string $notifBody): array
    {
        $proposal = $this->model->findByIdDetailed($proposalId);
        if (!$proposal) {
            return ['ok' => false, 'reason' => self::REASON_NOT_FOUND];
        }
        if ((int) $proposal['user_id'] !== $ownerId) {
            return ['ok' => false, 'reason' => self::REASON_FORBIDDEN];
        }
        if ($proposal['status'] !== $fromStatus) {
            return ['ok' => false, 'reason' => self::REASON_STATUS_CONFLICT];
        }

        $errors = $this->validate($this->proposalToData($proposal), true);
        if ($this->model->countPhotos($proposalId) < 1) {
            $errors[] = 'Minimal satu foto bukti wajib dilampirkan saat mengirim review';
        }
        if ($errors !== []) {
            return ['ok' => false, 'reason' => self::REASON_INVALID, 'errors' => $errors];
        }

        $affected = $this->model->conditionalUpdateWithStatus($proposalId, $fromStatus, [
            'status' => UsulanOpt::STATUS_PENDING,
            'submitted_at' => date('Y-m-d H:i:s'),
            'catatan_review' => null,
        ]);
        if ($affected === 0) {
            return ['ok' => false, 'reason' => self::REASON_STATUS_CONFLICT];
        }

        $this->model->addHistory($proposalId, $fromStatus, UsulanOpt::STATUS_PENDING, $actorId, $auditAction === 'submit' ? 'Dikirim untuk review' : 'Dikirim ulang setelah perbaikan');
        $this->writeAudit($actorId, $auditAction, $proposalId, 'Usulan OPT menuju ' . UsulanOpt::STATUS_PENDING);

        $name = mb_substr((string) ($proposal['nama_nasional'] ?: $proposal['nama_lokal']), 0, 100);
        if ($notifType === 'usulan_diterima') {
            $this->notifyOwner($ownerId, $proposalId, $notifType, 'Usulan OPT terkirim', sprintf('Usulan "%s" Anda diterima dan menunggu review Admin.', $name));
        }
        $this->notifyAdmins($proposalId, $notifType, 'Aksi Petugas pada Usulan OPT', sprintf('Usulan "%s" %s.', $name, $auditAction === 'submit' ? 'dikirim untuk review' : 'dikirim ulang setelah perbaikan'));

        return ['ok' => true];
    }

    /**
     * Hapus draf milik sendiri; file foto dibersihkan hanya setelah commit DB.
     *
     * @return array{ok:bool,reason?:string}
     */
    public function deleteDraft(int $proposalId, int $ownerId, int $actorId): array
    {
        $proposal = $this->model->findByIdDetailed($proposalId);
        if (!$proposal) {
            return ['ok' => false, 'reason' => self::REASON_NOT_FOUND];
        }
        if ((int) $proposal['user_id'] !== $ownerId) {
            return ['ok' => false, 'reason' => self::REASON_FORBIDDEN];
        }
        if ($proposal['status'] !== UsulanOpt::STATUS_DRAFT) {
            return ['ok' => false, 'reason' => self::REASON_STATUS_CONFLICT];
        }

        $photoRows = $this->model->getPhotos($proposalId);

        $stmt = $this->db->prepare('DELETE FROM usulan_opt WHERE id = ? AND status = ?');
        $stmt->execute([$proposalId, UsulanOpt::STATUS_DRAFT]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'reason' => self::REASON_STATUS_CONFLICT];
        }

        $this->writeAudit($actorId, 'delete_draft', $proposalId, 'Usulan OPT draf dihapus pemilik');

        $uploader = new UsulanPhotoUploader();
        foreach ($photoRows as $photo) {
            $uploader->deleteByPath((string) $photo['file_path']);
        }

        return ['ok' => true];
    }

    /**
     * Notifikasi "diterima untuk review" kepada pemilik saat kirim dari form mandiri.
     */
    public function notifySubmittedByOwner(int $ownerId, int $proposalId): void
    {
        $this->notifyOwner(
            $ownerId,
            $proposalId,
            self::NOTIF_TYPE_RECEIVED,
            'Usulan OPT terkirim',
            'Usulan Anda diterima dan menunggu review Admin.'
        );
    }

    /**
     * @return array{ok:bool,error?:string,names?:array<string,string>}
     */
    public function resolveWilayahForProposal(?int $kabId, ?int $kecId, ?int $desaId): array
    {
        if ($kabId === null || $kecId === null || $desaId === null) {
            return ['ok' => false, 'error' => 'Wilayah belum lengkap'];
        }
        $result = $this->resolveWilayah($kabId, $kecId, $desaId);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => (string) $result['error']];
        }

        return ['ok' => true, 'names' => [
            'kabupaten' => (string) $result['kabupaten'],
            'kecamatan' => (string) $result['kecamatan'],
            'desa' => (string) $result['desa'],
        ]];
    }

    /**
     * @return array<string,mixed> data siap insert
     */
    private function buildRow(int $ownerId, array $data, string $status): array
    {
        $hierarchy = $this->resolveWilayahIfComplete($data);

        return $this->buildUpdatableFields($data, $hierarchy) + [
            'user_id' => $ownerId,
            'status' => $status,
            'ciri_ciri' => trim((string) ($data['ciri_ciri'] ?? '')) ?: null,
        ];
    }

    /**
     * @return array<string,mixed>|null names bila lengkap dan valid, null selain itu
     */
    private function resolveWilayahIfComplete(array $data): ?array
    {
        $ids = [$data['kabupaten_id'] ?? null, $data['kecamatan_id'] ?? null, $data['desa_id'] ?? null];
        if (in_array(null, $ids, true)) {
            return null;
        }

        $result = $this->resolveWilayah((int) $ids[0], (int) $ids[1], (int) $ids[2]);
        if (!$result['ok']) {
            throw new InvalidArgumentException((string) $result['error']);
        }

        return [
            'kabupaten_nama' => (string) $result['kabupaten'],
            'kecamatan_nama' => (string) $result['kecamatan'],
            'desa_nama' => (string) $result['desa'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildUpdatableFields(array $data, ?array $hierarchy): array
    {
        return [
            'nama_nasional' => $data['nama_nasional'] ?? null,
            'nama_lokal' => $data['nama_lokal'] ?? null,
            'jenis' => $data['jenis'] ?? 'hama',
            'komoditas' => $data['komoditas'] ?? null,
            'tanggal_ditemukan' => $data['tanggal_ditemukan'] ?? null,
            'kabupaten_id' => $data['kabupaten_id'] ?? null,
            'kecamatan_id' => $data['kecamatan_id'] ?? null,
            'desa_id' => $data['desa_id'] ?? null,
            'alamat_lokasi' => $data['alamat_lokasi'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'bagian_terserang' => $data['bagian_terserang'] ?? null,
            'pola_gejala' => $data['pola_gejala'] ?? null,
            'estimasi_terdampak' => $data['estimasi_terdampak'] ?? null,
            'satuan_terdampak' => $data['satuan_terdampak'] ?? null,
            'tingkat_keyakinan' => $data['tingkat_keyakinan'] ?? null,
            'sumber_identifikasi' => $data['sumber_identifikasi'] ?? null,
            'wilayah' => $hierarchy !== null
                ? implode(', ', [$hierarchy['kabupaten_nama'], $hierarchy['kecamatan_nama'], $hierarchy['desa_nama']])
                : ($data['wilayah'] ?? null),
        ];
    }

    /**
     * Rekonstruksi payload dari baris proposal untuk validasi submit.
     *
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private function proposalToData(array $proposal): array
    {
        return [
            'nama_lokal' => $proposal['nama_lokal'],
            'nama_nasional' => $proposal['nama_nasional'],
            'jenis' => $proposal['jenis'],
            'komoditas' => $proposal['komoditas'],
            'ciri_ciri' => $proposal['ciri_ciri'],
            'tanggal_ditemukan' => $proposal['tanggal_ditemukan'],
            'kabupaten_id' => $proposal['kabupaten_id'],
            'kecamatan_id' => $proposal['kecamatan_id'],
            'desa_id' => $proposal['desa_id'],
            'alamat_lokasi' => $proposal['alamat_lokasi'],
            'latitude' => $proposal['latitude'],
            'longitude' => $proposal['longitude'],
            'bagian_terserang' => $proposal['bagian_terserang'],
            'pola_gejala' => $proposal['pola_gejala'],
            'estimasi_terdampak' => $proposal['estimasi_terdampak'],
            'satuan_terdampak' => $proposal['satuan_terdampak'],
            'tingkat_keyakinan' => $proposal['tingkat_keyakinan'],
            'sumber_identifikasi' => $proposal['sumber_identifikasi'],
            'wilayah' => $proposal['wilayah'],
        ];
    }

    /**
     * Hapus massal usulan oleh Admin. Status Disetujui/Digabungkan dilindungi
     * karena terikat master OPT dan jejak audit laporan; baris lain dihapus
     * dalam satu transaksi, file foto dibersihkan setelah commit.
     *
     * @return array{requested:int,deleted:int,skipped:int,skipped_statuses:array<string,int>,files:int}
     */
    public function bulkDeleteForAdmin(array $ids, int $actorId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn ($value): bool => $value > 0
        )));
        $empty = ['requested' => 0, 'deleted' => 0, 'skipped' => 0, 'skipped_statuses' => [], 'files' => 0];
        if ($ids === []) {
            return $empty;
        }
        $ids = array_slice($ids, 0, self::BULK_DELETE_MAX);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, status FROM usulan_opt WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $stmt->execute($ids);

        $deletable = [];
        $skipped = 0;
        $skippedStatuses = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (in_array((string) $row['status'], self::BULK_DELETE_PROTECTED, true)) {
                $skipped++;
                $status = (string) $row['status'];
                $skippedStatuses[$status] = ($skippedStatuses[$status] ?? 0) + 1;
                continue;
            }
            $deletable[] = (int) $row['id'];
        }

        if ($deletable === []) {
            return ['requested' => count($ids)] + $empty;
        }

        $ph = implode(',', array_fill(0, count($deletable), '?'));
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare(
                "UPDATE usulan_opt SET deleted_at = NOW(), deleted_by = ? "
                . "WHERE id IN ({$ph}) AND deleted_at IS NULL"
            );
            $del->execute(array_merge([$actorId], $deletable));
            $deleted = $del->rowCount();

            $idPreview = implode(',', array_slice($deletable, 0, 25))
                . (count($deletable) > 25 ? ',...' : '');
            $this->writeAudit(
                $actorId,
                'bulk_delete',
                (int) $deletable[0],
                sprintf('Hapus massal %d usulan OPT (ids: %s).', $deleted, $idPreview)
            );

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('UsulanOptService::bulkDeleteForAdmin failed');
            throw new RuntimeException('Gagal menghapus usulan secara massal.');
        }

        return [
            'requested' => count($ids),
            'deleted' => $deleted,
            'skipped' => $skipped,
            'skipped_statuses' => $skippedStatuses,
            'files' => 0,
        ];
    }

    private function insertRow(array $row): int
    {
        $columns = array_keys($row);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO usulan_opt (`' . implode('`, `', $columns) . '`) VALUES (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($row));

        return (int) $this->db->lastInsertId();
    }

    private function notifyOwner(int $ownerId, int $proposalId, string $type, string $title, string $body): void
    {
        $this->insertNotification($ownerId, $type, $title, $body, $proposalId);
    }

    private function notifyAdmins(int $proposalId, string $type, string $title, string $body): void
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 3");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
                $this->insertNotification((int) $adminId, $type, $title, $body, $proposalId);
            }
        } catch (Throwable $e) {
            error_log('UsulanOptService admin notification failed');
        }
    }

    private function insertNotification(int $userId, string $type, string $title, string $body, int $proposalId): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO notifications (user_id, title, body, type, data_json) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                mb_substr($title, 0, 200),
                mb_substr($body, 0, 500),
                mb_substr($type, 0, 50),
                json_encode([
                    'entity' => 'usulan_opt',
                    'usulan_opt_id' => $proposalId,
                    'web_path' => '/usulan-opt/detail/' . $proposalId,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            error_log('UsulanOptService notification failed');
        }
    }

    private function writeAudit(int $actorId, string $action, int $recordId, string $description): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $actorId,
                $action,
                'usulan_opt',
                $recordId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('UsulanOptService audit failed');
        }
    }
}
