<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\LaporanStatus;
use App\Helpers\SecureImageUploader;
use App\Models\ActivityLog;
use App\Models\LaporanIrigasi;
use App\Models\MasterKabupaten;
use App\Services\LaporanIrigasiService;

class LaporanIrigasiController extends Controller
{
    public function index(): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $filters = Request::all();
        $result = LaporanIrigasiService::listForCurrentUser($currentUser, $filters);
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');

        $this->view('laporan-irigasi/index', [
            'pageTitle' => 'Laporan Irigasi',
            'data' => $result['data'],
            'meta' => $result['meta'] ?? ['total' => 0, 'page' => 1, 'limit' => 20, 'last_page' => 1],
            'kabupaten' => $kabupaten,
            'filters' => $filters,
            'currentUser' => $currentUser,
        ]);
    }

    public function create(): void
    {
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');

        $this->view('laporan-irigasi/create', [
            'pageTitle' => 'Buat Laporan Irigasi',
            'kabupaten' => $kabupaten,
            'data' => [],
            'errors' => [],
            'oldInput' => [],
        ]);
    }

    public function store(): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $input = Request::all();
        $action = $input['action'] ?? 'draft';
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        if ($action === 'submit') {
            $result = LaporanIrigasiService::createAndSubmit((int) $currentUser['id'], $input, $ip, $userAgent);
        } else {
            $result = LaporanIrigasiService::createDraft((int) $currentUser['id'], $input, $ip, $userAgent);
        }

        if (!$result['success']) {
            $errors = $result['errors'] ?? [];
            $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
            $this->view('laporan-irigasi/create', [
                'pageTitle' => 'Buat Laporan Irigasi',
                'kabupaten' => $kabupaten,
                'data' => [],
                'errors' => $errors,
                'oldInput' => $input,
            ]);
            return;
        }

        $msg = $action === 'submit' ? 'Laporan irigasi berhasil dikirim.' : 'Draf laporan irigasi berhasil disimpan.';
        $_SESSION['flash_success'] = $msg;
        $this->redirect('/laporan-irigasi');
    }

    public function show(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $result = LaporanIrigasiService::getDetailForCurrentUser($id, $currentUser);

        if (!$result['success']) {
            $_SESSION['flash_error'] = 'Laporan tidak ditemukan.';
            $this->redirect('/laporan-irigasi');
        }

        $this->view('laporan-irigasi/show', [
            'pageTitle' => 'Detail Laporan Irigasi',
            'laporan' => $result['data'],
            'currentUser' => $currentUser,
        ]);
    }

    public function edit(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $result = LaporanIrigasiService::getDetailForCurrentUser($id, $currentUser);

        if (!$result['success']) {
            $_SESSION['flash_error'] = 'Laporan tidak ditemukan.';
            $this->redirect('/laporan-irigasi');
        }

        $laporan = $result['data'];

        if (!LaporanStatus::isEditableByPetugas($laporan['status'])) {
            $_SESSION['flash_error'] = 'Laporan dengan status ini tidak dapat diedit.';
            $this->redirect('/laporan-irigasi/' . $id);
        }

        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');

        $this->view('laporan-irigasi/edit', [
            'pageTitle' => 'Edit Laporan Irigasi',
            'kabupaten' => $kabupaten,
            'data' => $laporan,
            'errors' => [],
            'oldInput' => [],
        ]);
    }

    public function update(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $result = LaporanIrigasiService::updateDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/laporan-irigasi/' . $id . '/edit');
        }

        $_SESSION['flash_success'] = 'Draf laporan irigasi berhasil diperbarui.';
        $this->redirect('/laporan-irigasi/' . $id);
    }

    public function submit(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $result = LaporanIrigasiService::submitDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/laporan-irigasi/' . $id . '/edit');
            return;
        }

        $_SESSION['flash_success'] = 'Laporan irigasi berhasil dikirim.';
        $this->redirect('/laporan-irigasi/' . $id);
    }

    public function delete(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $result = LaporanIrigasiService::deleteDraft($id, (int) $currentUser['id'], $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Draf laporan irigasi berhasil dihapus.';
        }

        $this->redirect('/laporan-irigasi');
    }

    public function verify(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        if ($currentUser['role'] !== 'admin') {
            $_SESSION['flash_error'] = 'Aksi ini hanya untuk admin.';
            $this->redirect('/laporan-irigasi');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $catatan = $input['catatan_verifikasi'] ?? null;
        $result = LaporanIrigasiService::verify($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan irigasi berhasil diverifikasi.';
        }

        $this->redirect('/laporan-irigasi/' . $id);
    }

    public function reject(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        if ($currentUser['role'] !== 'admin') {
            $_SESSION['flash_error'] = 'Aksi ini hanya untuk admin.';
            $this->redirect('/laporan-irigasi');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $alasan = $input['alasan'] ?? '';
        $result = LaporanIrigasiService::reject($id, (int) $currentUser['id'], $alasan, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan irigasi berhasil ditolak.';
        }

        $this->redirect('/laporan-irigasi/' . $id);
    }

    public function archive(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        if ($currentUser['role'] !== 'admin') {
            $_SESSION['flash_error'] = 'Aksi ini hanya untuk admin.';
            $this->redirect('/laporan-irigasi');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $catatan = $input['catatan_verifikasi'] ?? null;
        $result = LaporanIrigasiService::archive($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan irigasi berhasil diarsipkan.';
        }

        $this->redirect('/laporan-irigasi/' . $id);
    }

    public function resubmit(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $result = LaporanIrigasiService::resubmit($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan irigasi berhasil dikirim ulang.';
        }

        $this->redirect('/laporan-irigasi/' . $id);
    }

    public function uploadFoto(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $laporan = LaporanIrigasi::findAccessibleById($id, $currentUser);
        if ($laporan === null) {
            $_SESSION['flash_error'] = 'Laporan tidak ditemukan.';
            $this->redirect('/laporan-irigasi');
            return;
        }

        $canEdit = $currentUser['role'] === 'admin'
            ? LaporanStatus::isEditableByPetugas($laporan['status'])
            : ($laporan['user_id'] == $currentUser['id'] && LaporanStatus::isEditableByPetugas($laporan['status']));

        if (!$canEdit) {
            $_SESSION['flash_error'] = 'Status laporan tidak mengizinkan perubahan foto.';
            $this->redirect('/laporan-irigasi/' . $id);
            return;
        }

        $file = $_FILES['foto'] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['flash_error'] = 'File foto wajib diupload.';
            $this->redirect('/laporan-irigasi/' . $id . '/edit');
            return;
        }

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        $destDir = $uploadRoot . '/assets/uploads/laporan-irigasi';

        try {
            $oldUrl = $laporan['foto_url'] ?? '';
            $result = SecureImageUploader::validateAndStore($file, [
                'max_bytes' => 10485760,
                'destination_dir' => $destDir,
                'relative_base' => 'assets/uploads/laporan-irigasi',
            ]);

            if ($oldUrl !== '') {
                SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);
            }

            LaporanIrigasi::update($id, ['foto_url' => $result['foto_url']]);
            ActivityLog::log((int) $currentUser['id'], 'laporan_irigasi_photo_uploaded', 'laporan_irigasi', $id, 'Foto laporan irigasi diupload: ' . $result['foto_url'], Request::ip(), Request::userAgent());

            $_SESSION['flash_success'] = 'Foto berhasil diunggah.';
        } catch (\DomainException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = 'Gagal menyimpan file.';
        }

        $this->redirect('/laporan-irigasi/' . $id . '/edit');
    }

    public function deleteFoto(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $laporan = LaporanIrigasi::findAccessibleById($id, $currentUser);
        if ($laporan === null) {
            $_SESSION['flash_error'] = 'Laporan tidak ditemukan.';
            $this->redirect('/laporan-irigasi');
            return;
        }

        $canEdit = $currentUser['role'] === 'admin'
            ? LaporanStatus::isEditableByPetugas($laporan['status'])
            : ($laporan['user_id'] == $currentUser['id'] && LaporanStatus::isEditableByPetugas($laporan['status']));

        if (!$canEdit) {
            $_SESSION['flash_error'] = 'Status laporan tidak mengizinkan perubahan foto.';
            $this->redirect('/laporan-irigasi/' . $id);
            return;
        }

        $oldUrl = $laporan['foto_url'] ?? '';
        if ($oldUrl === '') {
            $_SESSION['flash_error'] = 'Laporan tidak memiliki foto.';
            $this->redirect('/laporan-irigasi/' . $id . '/edit');
            return;
        }

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);

        LaporanIrigasi::update($id, ['foto_url' => null]);
        ActivityLog::log((int) $currentUser['id'], 'laporan_irigasi_photo_deleted', 'laporan_irigasi', $id, 'Foto laporan irigasi dihapus', Request::ip(), Request::userAgent());

        $_SESSION['flash_success'] = 'Foto berhasil dihapus.';
        $this->redirect('/laporan-irigasi/' . $id . '/edit');
    }
}
