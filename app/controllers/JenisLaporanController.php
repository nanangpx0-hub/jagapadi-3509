<?php
declare(strict_types=1);

require_once ROOT_PATH . '/app/core/Controller.php';
require_once ROOT_PATH . '/app/models/JenisLaporan.php';
require_once ROOT_PATH . '/app/services/JenisLaporanService.php';
require_once ROOT_PATH . '/app/traits/LogsActivity.php';

/**
 * JenisLaporanController — kelola master jenis laporan (Admin).
 *
 * Web runtime root (session + CSRF). Seluruh method write memakai
 * requireStateChangingRequest() (POST + CSRF + anti double-submit).
 * Hapus diblokir bila jenis masih dipakai laporan (FK RESTRICT);
 * gunakan nonaktif untuk menyembunyikan dari dropdown tanpa merusak histori.
 */
class JenisLaporanController extends Controller {

    use LogsActivity;

    private JenisLaporan $jenisModel;
    private JenisLaporanService $service;

    public function __construct() {
        parent::__construct();
        $this->jenisModel = new JenisLaporan();
        $this->service = new JenisLaporanService();
    }

    public function index() {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengelola master jenis laporan.');

        $rows = $this->jenisModel->allOrderedWithUsage();

        $this->view('jenis-laporan/index', [
            'title' => 'Master Jenis Laporan',
            'rows' => $rows,
        ]);
    }

    public function create() {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengelola master jenis laporan.');

        $oldInput = [];
        if (!empty($_SESSION['old_input']) && is_array($_SESSION['old_input'])) {
            $oldInput = $_SESSION['old_input'];
        }
        unset($_SESSION['old_input']);

        $this->view('jenis-laporan/form', [
            'title' => 'Tambah Jenis Laporan',
            'row' => null,
            'oldInput' => $oldInput,
            'formAction' => 'jenis-laporan/store',
        ]);
    }

    public function store() {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengelola master jenis laporan.');
        $this->requireStateChangingRequest();

        if (!empty($_POST['website_hp'])) {
            error_log('Honeypot triggered on jenis-laporan/store. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $_SESSION['error'] = 'Terjadi kesalahan. Silakan coba lagi.';
            $this->redirect('jenis-laporan');
            return;
        }

        $_SESSION['old_input'] = $_POST;

        $data = $this->service->normalize($_POST);
        $errors = $this->service->validate($data);

        if ($errors === []) {
            $dupe = $this->jenisModel->findByKodeInclusive($data['kode']);
            if ($dupe !== null) {
                $errors[] = !empty($dupe['deleted_at'])
                    ? "Kode '{$data['kode']}' dipakai jenis dalam recycle bin. Pulihkan dulu dari recycle bin bila ingin memakainya kembali."
                    : "Kode '{$data['kode']}' sudah dipakai jenis lain";
            }
        }

        if ($errors !== []) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect('jenis-laporan/create');
            return;
        }

        try {
            $data['fields_json'] = $this->service->canonicalizeFieldsJson($data['fields_json']);
            $id = $this->jenisModel->create($data);
        } catch (PDOException $e) {
            error_log('[JenisLaporan::store] PDO: ' . $e->getMessage());
            $_SESSION['error'] = str_contains($e->getMessage(), 'Duplicate')
                ? "Kode '{$data['kode']}' sudah dipakai jenis lain"
                : 'Terjadi kesalahan database saat menyimpan jenis laporan.';
            $this->redirect('jenis-laporan/create');
            return;
        }

        if (!$id) {
            $_SESSION['error'] = 'Gagal menyimpan jenis laporan';
            $this->redirect('jenis-laporan/create');
            return;
        }

        $this->logActivity('Create', 'master_jenis_laporan', (int) $id, 'Jenis laporan dibuat: ' . $data['kode']);
        $this->clearJenisCache();
        unset($_SESSION['old_input']);
        $_SESSION['success'] = "Jenis laporan '{$data['nama']}' berhasil ditambahkan";
        $this->redirect('jenis-laporan');
    }

    public function edit(int $id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengelola master jenis laporan.');

        $row = $id > 0 ? $this->jenisModel->findByIdInclusive($id) : null;
        if (!$row) {
            $_SESSION['error'] = 'Jenis laporan tidak ditemukan';
            $this->redirect('jenis-laporan');
            return;
        }

        if (!empty($row['deleted_at'])) {
            $_SESSION['info'] = 'Jenis laporan berada di recycle bin. Pulihkan dulu sebelum mengedit.';
            $this->redirect('jenis-laporan');
            return;
        }

        $oldInput = [];
        if (!empty($_SESSION['old_input']) && is_array($_SESSION['old_input'])) {
            $oldInput = $_SESSION['old_input'];
        }
        unset($_SESSION['old_input']);

        $this->view('jenis-laporan/form', [
            'title' => 'Edit Jenis Laporan',
            'row' => $row,
            'oldInput' => $oldInput,
            'formAction' => 'jenis-laporan/update/' . $id,
        ]);
    }

    public function update(int $id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengelola master jenis laporan.');
        $this->requireStateChangingRequest();

        $row = $id > 0 ? $this->jenisModel->findByIdInclusive($id) : null;
        if (!$row) {
            $_SESSION['error'] = 'Jenis laporan tidak ditemukan';
            $this->redirect('jenis-laporan');
            return;
        }

        if (!empty($row['deleted_at'])) {
            $_SESSION['info'] = 'Jenis laporan berada di recycle bin. Pulihkan dulu sebelum mengubah.';
            $this->redirect('jenis-laporan');
            return;
        }

        $_SESSION['old_input'] = $_POST;

        $data = $this->service->normalize($_POST);
        $errors = $this->service->validate($data);

        if ($errors === []) {
            $existing = $this->jenisModel->findByKodeInclusive($data['kode']);
            if ($existing !== null && (int) $existing['id'] !== $id) {
                $errors[] = !empty($existing['deleted_at'])
                    ? "Kode '{$data['kode']}' dipakai jenis dalam recycle bin. Pulihkan dulu dari recycle bin bila ingin memakainya kembali."
                    : "Kode '{$data['kode']}' sudah dipakai jenis lain";
            }
        }

        if ($errors !== []) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect("jenis-laporan/edit/{$id}");
            return;
        }

        try {
            $data['fields_json'] = $this->service->canonicalizeFieldsJson($data['fields_json']);
            $success = $this->jenisModel->update($id, $data);
        } catch (PDOException $e) {
            error_log('[JenisLaporan::update] PDO: ' . $e->getMessage());
            $_SESSION['error'] = str_contains($e->getMessage(), 'Duplicate')
                ? "Kode '{$data['kode']}' sudah dipakai jenis lain"
                : 'Terjadi kesalahan database saat memperbarui jenis laporan.';
            $this->redirect("jenis-laporan/edit/{$id}");
            return;
        }

        if (!$success) {
            $_SESSION['error'] = 'Gagal memperbarui jenis laporan';
            $this->redirect("jenis-laporan/edit/{$id}");
            return;
        }

        $this->logActivity('Update', 'master_jenis_laporan', $id, 'Jenis laporan diperbarui: ' . $data['kode']);
        $this->clearJenisCache();
        unset($_SESSION['old_input']);
        $_SESSION['success'] = "Jenis laporan '{$data['nama']}' berhasil diperbarui";
        $this->redirect('jenis-laporan');
    }

    public function toggle(int $id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengelola master jenis laporan.');
        $this->requireStateChangingRequest();

        $row = $id > 0 ? $this->jenisModel->findByIdInclusive($id) : null;
        if (!$row) {
            $_SESSION['error'] = 'Jenis laporan tidak ditemukan';
            $this->redirect('jenis-laporan');
            return;
        }

        if (!empty($row['deleted_at'])) {
            $_SESSION['info'] = 'Jenis laporan berada di recycle bin. Pulihkan dulu sebelum mengubah status.';
            $this->redirect('jenis-laporan');
            return;
        }

        $next = ((int) ($row['is_active'] ?? 0)) === 1 ? false : true;
        $success = $this->jenisModel->setActive($id, $next);

        if ($success) {
            $this->logActivity(
                $next ? 'Activate' : 'Deactivate',
                'master_jenis_laporan',
                $id,
                'Jenis laporan ' . ($next ? 'diaktifkan' : 'dinonaktifkan') . ': ' . ($row['kode'] ?? $id)
            );
            $this->clearJenisCache();
            $_SESSION['success'] = $next
                ? 'Jenis laporan diaktifkan dan tampil di dropdown'
                : 'Jenis laporan dinonaktifkan dan disembunyikan dari dropdown';
        } else {
            $_SESSION['error'] = 'Gagal mengubah status jenis laporan';
        }

        $this->redirect('jenis-laporan');
    }

    /**
     * Hapus lunak: pindahkan ke recycle bin (dapat dipulihkan Admin).
     * Hanya untuk jenis yang belum dipakai laporan; yang sudah dipakai
     * wajib dinonaktifkan agar histori tetap utuh (FK RESTRICT).
     */
    public function delete(int $id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengelola master jenis laporan.');
        $this->requireStateChangingRequest();

        $row = $id > 0 ? $this->jenisModel->findByIdInclusive($id) : null;
        if (!$row) {
            $_SESSION['error'] = 'Jenis laporan tidak ditemukan';
            $this->redirect('jenis-laporan');
            return;
        }

        if (!empty($row['deleted_at'])) {
            $_SESSION['info'] = 'Jenis laporan sudah berada di recycle bin.';
            $this->redirect('jenis-laporan');
            return;
        }

        $used = $this->jenisModel->countUsage($id);
        if ($used > 0) {
            $_SESSION['error'] = "Jenis laporan dipakai {$used} laporan dan tidak dapat dihapus. Nonaktifkan saja agar histori tetap utuh.";
            $this->redirect('jenis-laporan');
            return;
        }

        try {
            $success = $this->jenisModel->softDelete($id, (int) $_SESSION['user_id']);
        } catch (PDOException $e) {
            error_log('[JenisLaporan::delete] PDO: ' . $e->getMessage());
            $_SESSION['error'] = 'Gagal memindahkan jenis laporan ke recycle bin.';
            $this->redirect('jenis-laporan');
            return;
        }

        if ($success) {
            $this->logActivity('SoftDelete', 'master_jenis_laporan', $id, 'Jenis laporan dipindahkan ke recycle bin: ' . ($row['kode'] ?? $id));
            $this->clearJenisCache();
            $_SESSION['success'] = 'Jenis laporan dipindahkan ke recycle bin dan dapat dipulihkan.';
        } else {
            $_SESSION['error'] = 'Gagal memindahkan jenis laporan ke recycle bin.';
        }

        $this->redirect('jenis-laporan');
    }

    private function clearJenisCache(): void {
        try {
            CacheManager::getInstance()->delete('jenis_laporan:active');
        } catch (Throwable $e) {
            error_log('[JenisLaporan] gagal membersihkan cache: ' . $e->getMessage());
        }
    }
}
