<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Request;
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
}
