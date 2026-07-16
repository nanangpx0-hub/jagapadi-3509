<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Security;
use App\Helpers\SecureImageUploader;
use App\Models\ActivityLog;
use App\Models\MasterOpt;
use App\Services\MasterOptService;

class OptController extends Controller
{
    private function getAdminId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public function index(): void
    {
        $jenis = Request::input('jenis', '');
        $search = Request::input('q', '');
        $aktif = Request::input('aktif', '');
        $aktifFilter = $aktif !== '' ? (int) $aktif : null;

        $data = MasterOpt::allWithFilters($jenis, $search, $aktifFilter);

        $this->view('opt/index', [
            'pageTitle' => 'Master OPT',
            'data' => $data,
            'filterJenis' => $jenis,
            'filterSearch' => $search,
            'filterAktif' => $aktif,
        ]);
    }

    public function create(): void
    {
        $this->view('opt/form', [
            'pageTitle' => 'Tambah OPT',
            'data' => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $data = Request::all();
        $validation = MasterOptService::validate($data);
        if (!$validation['valid']) {
            $this->view('opt/form', [
                'pageTitle' => 'Tambah OPT',
                'data' => $data,
                'errors' => $validation['errors'],
            ]);
            return;
        }

        $result = MasterOptService::create($data, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/opt/create');
        }

        $_SESSION['flash_success'] = 'OPT berhasil ditambahkan.';
        $this->redirect('/opt');
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterOpt::find($id);
        if ($data === null) {
            $_SESSION['flash_error'] = 'OPT tidak ditemukan.';
            $this->redirect('/opt');
        }

        $this->view('opt/form', [
            'pageTitle' => 'Edit OPT',
            'data' => $data,
            'errors' => [],
        ]);
    }

    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = Request::all();

        $validation = MasterOptService::validate($data, true);
        if (!$validation['valid']) {
            $opt = MasterOpt::find($id);
            $this->view('opt/form', [
                'pageTitle' => 'Edit OPT',
                'data' => array_merge($opt ?? [], $data),
                'errors' => $validation['errors'],
            ]);
            return;
        }

        $result = MasterOptService::update($id, $data, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/opt/' . $id . '/edit');
        }

        $_SESSION['flash_success'] = 'OPT berhasil diperbarui.';
        $this->redirect('/opt');
    }

    public function delete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $result = MasterOptService::delete($id, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = $result['message'] ?? 'OPT berhasil dihapus.';
        }
        $this->redirect('/opt');
    }

    public function uploadFoto(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $opt = MasterOpt::find($id);
        if ($opt === null) {
            $_SESSION['flash_error'] = 'OPT tidak ditemukan.';
            $this->redirect('/opt');
            return;
        }

        $file = $_FILES['foto'] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['flash_error'] = 'File foto wajib diupload.';
            $this->redirect('/opt/' . $id . '/edit');
            return;
        }

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        $destDir = $uploadRoot . '/assets/uploads/opt-photos';

        try {
            $oldUrl = $opt['foto_url'] ?? '';
            $result = SecureImageUploader::validateAndStore($file, [
                'max_bytes' => 5242880,
                'destination_dir' => $destDir,
                'relative_base' => 'assets/uploads/opt-photos',
            ]);

            if ($oldUrl !== '') {
                SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);
            }

            MasterOpt::update($id, ['foto_url' => $result['foto_url']]);
            ActivityLog::log($this->getAdminId(), 'opt_photo_uploaded', 'master_opt', $id, 'Foto OPT diupload: ' . $result['foto_url']);

            $_SESSION['flash_success'] = 'Foto berhasil diunggah.';
        } catch (\DomainException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = 'Gagal menyimpan file.';
        }

        $this->redirect('/opt/' . $id . '/edit');
    }

    public function deleteFoto(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $opt = MasterOpt::find($id);
        if ($opt === null) {
            $_SESSION['flash_error'] = 'OPT tidak ditemukan.';
            $this->redirect('/opt');
            return;
        }

        $oldUrl = $opt['foto_url'] ?? '';
        if ($oldUrl === '') {
            $_SESSION['flash_error'] = 'OPT tidak memiliki foto.';
            $this->redirect('/opt/' . $id . '/edit');
            return;
        }

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);

        MasterOpt::update($id, ['foto_url' => null]);
        ActivityLog::log($this->getAdminId(), 'opt_photo_deleted', 'master_opt', $id, 'Foto OPT dihapus');

        $_SESSION['flash_success'] = 'Foto berhasil dihapus.';
        $this->redirect('/opt/' . $id . '/edit');
    }
}
