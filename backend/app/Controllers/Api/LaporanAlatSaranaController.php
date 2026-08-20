<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Request;
use App\Helpers\LaporanStatus;
use App\Helpers\SecureImageUploader;
use App\Models\ActivityLog;
use App\Models\LaporanAlatSarana;
use App\Services\LaporanAlatSaranaService;

class LaporanAlatSaranaController extends BaseApiController
{
    public function index(): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $filters = Request::all();
        $result = LaporanAlatSaranaService::listForCurrentUser($currentUser, $filters);
        $this->json($result, $result['code']);
    }

    public function store(): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $input = Request::all();
        $action = $input['action'] ?? 'draft';
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        if ($action === 'submit') {
            $result = LaporanAlatSaranaService::createAndSubmit((int) $currentUser['id'], $input, $ip, $userAgent);
        } else {
            $result = LaporanAlatSaranaService::createDraft((int) $currentUser['id'], $input, $ip, $userAgent);
        }

        $this->json($result, $result['code']);
    }

    public function show(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $result = LaporanAlatSaranaService::getDetailForCurrentUser($id, $currentUser);

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
        $result = LaporanAlatSaranaService::updateDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function destroy(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $ip = Request::ip();
        $userAgent = Request::userAgent();
        $result = LaporanAlatSaranaService::deleteDraft($id, (int) $currentUser['id'], $ip, $userAgent);

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
        $result = LaporanAlatSaranaService::submitDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

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
        $result = LaporanAlatSaranaService::verify($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

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
        $result = LaporanAlatSaranaService::reject($id, (int) $currentUser['id'], $alasan, $ip, $userAgent);

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
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $catatan = $input['catatan'] ?? null;
        $result = LaporanAlatSaranaService::archive($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function resubmit(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $result = LaporanAlatSaranaService::resubmit($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function uploadFoto(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);

        $laporan = LaporanAlatSarana::findAccessibleById($id, $currentUser);
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

        $uploadRoot = dirname(__DIR__, 2) . '/public';
        $destDir = $uploadRoot . '/assets/uploads/laporan-alat-sarana';

        try {
            $oldUrl = $laporan['foto_url'] ?? '';

            $result = SecureImageUploader::validateAndStore($file, [
                'max_bytes' => 10485760,
                'destination_dir' => $destDir,
                'relative_base' => 'assets/uploads/laporan-alat-sarana',
            ]);

            if ($oldUrl !== '') {
                SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);
            }

            LaporanAlatSarana::update($id, ['foto_url' => $result['foto_url']]);
            ActivityLog::log((int) $currentUser['id'], 'laporan_alat_sarana_photo_uploaded', 'laporan_alat_sarana', $id, 'Foto laporan alat sarana diupload: ' . $result['foto_url'], Request::ip(), Request::userAgent());

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

        $laporan = LaporanAlatSarana::findAccessibleById($id, $currentUser);
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

        $uploadRoot = dirname(__DIR__, 2) . '/public';
        SecureImageUploader::deleteOldPhoto($uploadRoot, $oldUrl);

        LaporanAlatSarana::update($id, ['foto_url' => null]);
        ActivityLog::log((int) $currentUser['id'], 'laporan_alat_sarana_photo_deleted', 'laporan_alat_sarana', $id, 'Foto laporan alat sarana dihapus', Request::ip(), Request::userAgent());

        $this->success(['id' => $id, 'foto_url' => null], 'Foto berhasil dihapus.');
    }
}
