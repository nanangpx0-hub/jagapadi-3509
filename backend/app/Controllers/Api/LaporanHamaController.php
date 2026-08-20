<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Request;
use App\Helpers\LaporanStatus;
use App\Helpers\SecureImageUploader;
use App\Models\ActivityLog;
use App\Models\LaporanHama;
use App\Services\LaporanHamaService;

class LaporanHamaController extends BaseApiController
{
    public function index(): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $filters = Request::all();
        $result = LaporanHamaService::listForCurrentUser($currentUser, $filters);
        $this->json($result, $result['code']);
    }

    public function store(): void
    {
        $currentUser = $GLOBALS['auth_user'];

        $input = Request::all();
        unset($input['foto_url']);
        $action = $input['action'] ?? 'draft';
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        $uploadedPhotoUrl = null;
        $file = $_FILES['foto'] ?? null;
        $hasPhoto = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasPhoto) {
            try {
                $upload = SecureImageUploader::validateAndStore($file, [
                    'max_bytes' => 10485760,
                    'destination_dir' => $uploadRoot . '/assets/uploads/laporan-hama',
                    'relative_base' => 'assets/uploads/laporan-hama',
                ]);
                $input['foto_url'] = $upload['foto_url'];
                $uploadedPhotoUrl = $upload['foto_url'];
            } catch (\DomainException $e) {
                $this->error('ValidationError', $e->getMessage(), ['foto' => $e->getMessage()], 422);
                return;
            } catch (\RuntimeException $e) {
                $this->error('ServerError', $e->getMessage(), [], 500);
                return;
            }
        }

        if ($action === 'submit') {
            $result = LaporanHamaService::createAndSubmit((int) $currentUser['id'], $input, $ip, $userAgent);
        } else {
            $result = LaporanHamaService::createDraft((int) $currentUser['id'], $input, $ip, $userAgent);
        }

        if (!$result['success'] && $uploadedPhotoUrl !== null) {
            SecureImageUploader::deleteOldPhoto($uploadRoot, $uploadedPhotoUrl);
        }

        $this->json($result, $result['code']);
    }

    public function show(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $result = LaporanHamaService::getDetailForCurrentUser($id, $currentUser);

        if (!$result['success']) {
            $this->error($result['error'], $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data']);
    }

    public function update(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();

        if (isset($input['action']) && $input['action'] === 'submit') {
            $this->error('ValidationError', 'Gunakan endpoint /submit untuk mengirim laporan.', [], 422);
            return;
        }

        if (isset($input['status']) && $input['status'] !== 'Draf') {
            $this->error('ValidationError', 'Tidak dapat mengubah status melalui endpoint ini. Gunakan /submit.', [], 422);
            return;
        }

        $ip = Request::ip();
        $userAgent = Request::userAgent();
        $result = LaporanHamaService::updateDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function destroy(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $ip = Request::ip();
        $userAgent = Request::userAgent();
        $result = LaporanHamaService::deleteDraft($id, (int) $currentUser['id'], $ip, $userAgent);

        if (!$result['success']) {
            $this->json($result, $result['code']);
            return;
        }

        $this->json($result, $result['code']);
    }

    public function submit(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();
        $result = LaporanHamaService::submitDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function verify(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        if ($currentUser['role'] !== 'admin') {
            $this->error('Forbidden', 'Aksi ini hanya untuk admin.', [], 403);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $catatan = $input['catatan'] ?? null;
        $result = LaporanHamaService::verify($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function reject(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        if ($currentUser['role'] !== 'admin') {
            $this->error('Forbidden', 'Aksi ini hanya untuk admin.', [], 403);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $alasan = $input['alasan'] ?? '';
        $result = LaporanHamaService::reject($id, (int) $currentUser['id'], $alasan, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function archive(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        if ($currentUser['role'] !== 'admin') {
            $this->error('Forbidden', 'Aksi ini hanya untuk admin.', [], 403);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->error('ValidationError', 'ID laporan tidak valid.', [], 400);
            return;
        }

        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $catatan = $input['catatan'] ?? null;
        $result = LaporanHamaService::archive($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function resubmit(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $result = LaporanHamaService::resubmit($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function uploadFoto(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);

        $laporan = LaporanHama::findAccessibleById($id, $currentUser);
        if ($laporan === null) {
            $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
            return;
        }

        $canEdit = $currentUser['role'] === 'admin'
            ? LaporanStatus::isEditableByPetugas($laporan['status'])
            : ($laporan['user_id'] == $currentUser['id'] && LaporanStatus::isEditableByPetugas($laporan['status']));

        if (!$canEdit) {
            $this->error('Conflict', 'Status laporan tidak mengizinkan perubahan foto.', [], 409);
            return;
        }

        $file = $_FILES['foto'] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->error('ValidationError', 'File foto wajib diupload.', [], 422);
            return;
        }

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        $destDir = $uploadRoot . '/assets/uploads/laporan-hama';

        try {
            $oldUrl = $laporan['foto_url'] ?? '';

            $result = SecureImageUploader::validateAndStore($file, [
                'max_bytes' => 10485760,
                'destination_dir' => $destDir,
                'relative_base' => 'assets/uploads/laporan-hama',
            ]);

            if ($oldUrl !== '') {
                SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);
            }

            LaporanHama::update($id, ['foto_url' => $result['foto_url']]);
            ActivityLog::log((int) $currentUser['id'], 'laporan_hama_photo_uploaded', 'laporan_hama', $id, 'Foto laporan hama diupload: ' . $result['foto_url'], Request::ip(), Request::userAgent());

            $this->success(['id' => $id, 'foto_url' => $result['foto_url']], 'Foto berhasil diunggah.');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        } catch (\RuntimeException $e) {
            $this->error('ServerError', $e->getMessage(), [], 500);
        }
    }

    public function deleteFoto(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);

        $laporan = LaporanHama::findAccessibleById($id, $currentUser);
        if ($laporan === null) {
            $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
            return;
        }

        $canEdit = $currentUser['role'] === 'admin'
            ? LaporanStatus::isEditableByPetugas($laporan['status'])
            : ($laporan['user_id'] == $currentUser['id'] && LaporanStatus::isEditableByPetugas($laporan['status']));

        if (!$canEdit) {
            $this->error('Conflict', 'Status laporan tidak mengizinkan perubahan foto.', [], 409);
            return;
        }

        $oldUrl = $laporan['foto_url'] ?? '';
        if ($oldUrl === '') {
            $this->error('NotFound', 'Laporan tidak memiliki foto.', [], 404);
            return;
        }

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);

        LaporanHama::update($id, ['foto_url' => null]);
        ActivityLog::log((int) $currentUser['id'], 'laporan_hama_photo_deleted', 'laporan_hama', $id, 'Foto laporan hama dihapus', Request::ip(), Request::userAgent());

        $this->success(['id' => $id, 'foto_url' => null], 'Foto berhasil dihapus.');
    }
}
