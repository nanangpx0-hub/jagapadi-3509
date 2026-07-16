<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Request;
use App\Models\MasterDesa;
use App\Models\MasterKabupaten;
use App\Models\MasterKecamatan;
use App\Services\WilayahService;

class WilayahController extends BaseApiController
{
    // --- READ (public for authenticated users) ---

    public function listKabupaten(): void
    {
        $data = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $this->success($data);
    }

    public function getKabupaten(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterKabupaten::find($id);
        if ($data === null) {
            $this->error('NotFound', 'Kabupaten tidak ditemukan.', [], 404);
            return;
        }
        $this->success($data);
    }

    public function listKecamatan(): void
    {
        $kabupatenId = (int) (Request::input('kabupaten_id', 0));
        if ($kabupatenId <= 0) {
            $this->error('ValidationError', 'Parameter kabupaten_id wajib diisi.', [], 422);
            return;
        }
        $data = MasterKecamatan::findByKabupaten($kabupatenId);
        $this->success($data);
    }

    public function getKecamatan(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterKecamatan::find($id);
        if ($data === null) {
            $this->error('NotFound', 'Kecamatan tidak ditemukan.', [], 404);
            return;
        }
        $this->success($data);
    }

    public function listDesa(): void
    {
        $kecamatanId = (int) (Request::input('kecamatan_id', 0));
        if ($kecamatanId <= 0) {
            $this->error('ValidationError', 'Parameter kecamatan_id wajib diisi.', [], 422);
            return;
        }
        $data = MasterDesa::findByKecamatan($kecamatanId);
        $this->success($data);
    }

    public function getDesa(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterDesa::find($id);
        if ($data === null) {
            $this->error('NotFound', 'Desa tidak ditemukan.', [], 404);
            return;
        }
        $this->success($data);
    }

    // --- WRITE (admin only via middleware) ---

    private function getAdminId(): int
    {
        return (int) ($GLOBALS['auth_user']['id'] ?? $_SESSION['user_id'] ?? 0);
    }

    public function createKabupaten(): void
    {
        $data = Request::all();
        $errors = $this->validateWilayah($data, 'kabupaten');
        if (count($errors) > 0) {
            $this->error('ValidationError', 'Validasi gagal.', $errors, 422);
            return;
        }

        $result = WilayahService::createKabupaten($data, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data'], 'Kabupaten berhasil ditambahkan.', [], 201);
    }

    public function updateKabupaten(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKabupaten::find($id);
        if ($oldData === null) {
            $this->error('NotFound', 'Kabupaten tidak ditemukan.', [], 404);
            return;
        }

        $data = Request::all();
        $errors = $this->validateWilayah($data, 'kabupaten', true);
        if (count($errors) > 0) {
            $this->error('ValidationError', 'Validasi gagal.', $errors, 422);
            return;
        }

        $result = WilayahService::updateKabupaten($id, $data, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data'], 'Kabupaten berhasil diperbarui.');
    }

    public function deleteKabupaten(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKabupaten::find($id);
        if ($oldData === null) {
            $this->error('NotFound', 'Kabupaten tidak ditemukan.', [], 404);
            return;
        }

        $result = WilayahService::deleteKabupaten($id, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success([], 'Kabupaten berhasil dihapus.');
    }

    public function createKecamatan(): void
    {
        $data = Request::all();
        $errors = $this->validateWilayah($data, 'kecamatan');
        if (count($errors) > 0) {
            $this->error('ValidationError', 'Validasi gagal.', $errors, 422);
            return;
        }

        $result = WilayahService::createKecamatan($data, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data'], 'Kecamatan berhasil ditambahkan.', [], 201);
    }

    public function updateKecamatan(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKecamatan::find($id);
        if ($oldData === null) {
            $this->error('NotFound', 'Kecamatan tidak ditemukan.', [], 404);
            return;
        }

        $data = Request::all();
        $errors = $this->validateWilayah($data, 'kecamatan', true);
        if (count($errors) > 0) {
            $this->error('ValidationError', 'Validasi gagal.', $errors, 422);
            return;
        }

        $result = WilayahService::updateKecamatan($id, $data, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data'], 'Kecamatan berhasil diperbarui.');
    }

    public function deleteKecamatan(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKecamatan::find($id);
        if ($oldData === null) {
            $this->error('NotFound', 'Kecamatan tidak ditemukan.', [], 404);
            return;
        }

        $result = WilayahService::deleteKecamatan($id, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success([], 'Kecamatan berhasil dihapus.');
    }

    public function createDesa(): void
    {
        $data = Request::all();
        $errors = $this->validateWilayah($data, 'desa');
        if (count($errors) > 0) {
            $this->error('ValidationError', 'Validasi gagal.', $errors, 422);
            return;
        }

        $result = WilayahService::createDesa($data, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data'], 'Desa berhasil ditambahkan.', [], 201);
    }

    public function updateDesa(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterDesa::find($id);
        if ($oldData === null) {
            $this->error('NotFound', 'Desa tidak ditemukan.', [], 404);
            return;
        }

        $data = Request::all();
        $errors = $this->validateWilayah($data, 'desa', true);
        if (count($errors) > 0) {
            $this->error('ValidationError', 'Validasi gagal.', $errors, 422);
            return;
        }

        $result = WilayahService::updateDesa($id, $data, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success($result['data'], 'Desa berhasil diperbarui.');
    }

    public function deleteDesa(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterDesa::find($id);
        if ($oldData === null) {
            $this->error('NotFound', 'Desa tidak ditemukan.', [], 404);
            return;
        }

        $result = WilayahService::deleteDesa($id, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $this->error('Conflict', $result['message'], [], $result['code']);
            return;
        }

        $this->success([], 'Desa berhasil dihapus.');
    }

    private function validateWilayah(array $data, string $level, bool $isUpdate = false): array
    {
        $errors = [];

        if ($level === 'kabupaten') {
            if (!$isUpdate || isset($data['kode'])) {
                $kode = trim($data['kode'] ?? '');
                if ($kode === '') {
                    $errors['kode'] = 'Kode kabupaten wajib diisi.';
                } elseif (strlen($kode) > 10) {
                    $errors['kode'] = 'Kode maksimal 10 karakter.';
                }
            }
            if (!$isUpdate || isset($data['nama_kabupaten'])) {
                $nama = trim($data['nama_kabupaten'] ?? '');
                if ($nama === '') {
                    $errors['nama_kabupaten'] = 'Nama kabupaten wajib diisi.';
                } elseif (mb_strlen($nama) > 100) {
                    $errors['nama_kabupaten'] = 'Nama maksimal 100 karakter.';
                }
            }
        }

        if ($level === 'kecamatan') {
            if (!$isUpdate || isset($data['kabupaten_id'])) {
                if (empty($data['kabupaten_id'])) {
                    $errors['kabupaten_id'] = 'Kabupaten wajib dipilih.';
                }
            }
            if (!$isUpdate || isset($data['kode'])) {
                $kode = trim($data['kode'] ?? '');
                if ($kode === '') {
                    $errors['kode'] = 'Kode kecamatan wajib diisi.';
                } elseif (strlen($kode) > 10) {
                    $errors['kode'] = 'Kode maksimal 10 karakter.';
                }
            }
            if (!$isUpdate || isset($data['nama_kecamatan'])) {
                $nama = trim($data['nama_kecamatan'] ?? '');
                if ($nama === '') {
                    $errors['nama_kecamatan'] = 'Nama kecamatan wajib diisi.';
                } elseif (mb_strlen($nama) > 100) {
                    $errors['nama_kecamatan'] = 'Nama maksimal 100 karakter.';
                }
            }
        }

        if ($level === 'desa') {
            if (!$isUpdate || isset($data['kecamatan_id'])) {
                if (empty($data['kecamatan_id'])) {
                    $errors['kecamatan_id'] = 'Kecamatan wajib dipilih.';
                }
            }
            if (!$isUpdate || isset($data['kode'])) {
                $kode = trim($data['kode'] ?? '');
                if ($kode === '') {
                    $errors['kode'] = 'Kode desa wajib diisi.';
                } elseif (strlen($kode) > 10) {
                    $errors['kode'] = 'Kode maksimal 10 karakter.';
                }
            }
            if (!$isUpdate || isset($data['nama_desa'])) {
                $nama = trim($data['nama_desa'] ?? '');
                if ($nama === '') {
                    $errors['nama_desa'] = 'Nama desa wajib diisi.';
                } elseif (mb_strlen($nama) > 100) {
                    $errors['nama_desa'] = 'Nama maksimal 100 karakter.';
                }
            }
        }

        return $errors;
    }
}
