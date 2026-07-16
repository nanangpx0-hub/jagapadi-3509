<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Request;
use App\Helpers\SecureImageUploader;
use App\Models\ActivityLog;
use App\Models\MasterOpt;
use App\Services\MasterOptService;

class OptController extends BaseApiController
{
    private function getUserId(): int
    {
        return (int) ($GLOBALS['auth_user']['id'] ?? $_SESSION['user_id'] ?? 0);
    }

    private function isAdmin(): bool
    {
        $role = $GLOBALS['auth_user']['role'] ?? $_SESSION['role'] ?? '';
        return $role === 'admin';
    }

    // --- READ ---

    public function index(): void
    {
        $jenis = Request::input('jenis', '');
        $search = Request::input('q', '');
        $aktif = Request::input('aktif', '');

        if ($this->isAdmin() && $aktif !== '') {
            $data = MasterOpt::allWithFilters($jenis, $search, (int) $aktif);
        } elseif ($this->isAdmin()) {
            $data = MasterOpt::allWithFilters($jenis, $search, null);
        } else {
            $data = MasterOpt::allActive($jenis, $search);
        }

        $this->success($data);
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterOpt::find($id);
        if ($data === null) {
            $this->error('NotFound', 'OPT tidak ditemukan.', [], 404);
            return;
        }
        $this->success($data);
    }

    // --- WRITE (admin only) ---

    public function store(): void
    {
        $data = Request::all();
        $validation = MasterOptService::validate($data);
        if (!$validation['valid']) {
            $this->error('ValidationError', 'Validasi gagal.', $validation['errors'], 422);
            return;
        }

        $result = MasterOptService::create($data, $this->getUserId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data'], 'OPT berhasil ditambahkan.', [], 201);
    }

    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = Request::all();

        $validation = MasterOptService::validate($data, true);
        if (!$validation['valid']) {
            $this->error('ValidationError', 'Validasi gagal.', $validation['errors'], 422);
            return;
        }

        $result = MasterOptService::update($id, $data, $this->getUserId());
        if (!$result['success']) {
            $code = $result['code'] === 404 ? 404 : 409;
            $this->error($code === 404 ? 'NotFound' : 'Conflict', $result['message'], [], $code);
            return;
        }

        $this->success($result['data'], 'OPT berhasil diperbarui.');
    }

    public function destroy(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $result = MasterOptService::delete($id, $this->getUserId());
        if (!$result['success']) {
            $code = $result['code'] === 404 ? 404 : 409;
            $this->error($code === 404 ? 'NotFound' : 'Conflict', $result['message'], [], $code);
            return;
        }

        $message = $result['message'] ?? 'OPT berhasil dihapus.';
        $this->success([], $message);
    }

    public function uploadFoto(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $opt = MasterOpt::find($id);
        if ($opt === null) {
            $this->error('NotFound', 'OPT tidak ditemukan.', [], 404);
            return;
        }

        $file = $_FILES['foto'] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->error('ValidationError', 'File foto wajib diupload.', [], 422);
            return;
        }

        $uploadRoot = dirname(__DIR__, 2) . '/public';
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
            ActivityLog::log($this->getUserId(), 'opt_photo_uploaded', 'master_opt', $id, 'Foto OPT diupload: ' . $result['foto_url']);

            $this->success(['id' => $id, 'foto_url' => $result['foto_url']], 'Foto berhasil diunggah.');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        } catch (\RuntimeException $e) {
            $this->error('ServerError', $e->getMessage(), [], 500);
        }
    }

    public function deleteFoto(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $opt = MasterOpt::find($id);
        if ($opt === null) {
            $this->error('NotFound', 'OPT tidak ditemukan.', [], 404);
            return;
        }

        $oldUrl = $opt['foto_url'] ?? '';
        if ($oldUrl === '') {
            $this->error('NotFound', 'OPT tidak memiliki foto.', [], 404);
            return;
        }

        $uploadRoot = dirname(__DIR__, 2) . '/public';
        SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);

        MasterOpt::update($id, ['foto_url' => null]);
        ActivityLog::log($this->getUserId(), 'opt_photo_deleted', 'master_opt', $id, 'Foto OPT dihapus');

        $this->success(['id' => $id, 'foto_url' => null], 'Foto berhasil dihapus.');
    }
}
