<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Request;
use App\Services\LaporanIrigasiService;

class LaporanIrigasiController extends BaseApiController
{
    public function index(): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $filters = Request::all();
        $result = LaporanIrigasiService::listForCurrentUser($currentUser, $filters);
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
            $result = LaporanIrigasiService::createAndSubmit((int) $currentUser['id'], $input, $ip, $userAgent);
        } else {
            $result = LaporanIrigasiService::createDraft((int) $currentUser['id'], $input, $ip, $userAgent);
        }

        $this->json($result, $result['code']);
    }

    public function show(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $result = LaporanIrigasiService::getDetailForCurrentUser($id, $currentUser);

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
        $result = LaporanIrigasiService::updateDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function destroy(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $ip = Request::ip();
        $userAgent = Request::userAgent();
        $result = LaporanIrigasiService::deleteDraft($id, (int) $currentUser['id'], $ip, $userAgent);

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
        $result = LaporanIrigasiService::submitDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

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
        $result = LaporanIrigasiService::verify($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

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
        $result = LaporanIrigasiService::reject($id, (int) $currentUser['id'], $alasan, $ip, $userAgent);

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
        $result = LaporanIrigasiService::archive($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        $this->json($result, $result['code']);
    }

    public function resubmit(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'];
        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $result = LaporanIrigasiService::resubmit($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        $this->json($result, $result['code']);
    }
}
