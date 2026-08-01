<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Security;
use App\Models\MasterDesa;
use App\Models\MasterKabupaten;
use App\Models\MasterKecamatan;
use App\Services\WilayahService;

class WilayahController extends Controller
{
    private function getAdminId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public function index(): void
    {
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $this->view('wilayah/index', [
            'pageTitle' => 'Master Wilayah',
            'kabupaten' => $kabupaten,
        ]);
    }

    // --- Kabupaten ---

    public function kabupatenCreate(): void
    {
        $this->view('wilayah/kabupaten_form', [
            'pageTitle' => 'Tambah Kabupaten',
            'data' => [],
            'errors' => [],
        ]);
    }

    public function kabupatenEdit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterKabupaten::find($id);
        if ($data === null) {
            $_SESSION['flash_error'] = 'Kabupaten tidak ditemukan.';
            $this->redirect('/wilayah');
        }
        $this->view('wilayah/kabupaten_form', [
            'pageTitle' => 'Edit Kabupaten',
            'data' => $data,
            'errors' => [],
        ]);
    }

    public function kabupatenStore(): void
    {
        $data = Request::all();
        $errors = $this->validateSimple($data, ['kode', 'nama_kabupaten']);
        if (count($errors) > 0) {
            $_SESSION['flash_error'] = implode('<br>', array_values($errors));
            $this->redirect('/wilayah/kabupaten/create');
        }

        $result = WilayahService::createKabupaten($data, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/wilayah/kabupaten/create');
        }

        $_SESSION['flash_success'] = 'Kabupaten berhasil ditambahkan.';
        $this->redirect('/wilayah');
    }

    public function kabupatenUpdate(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKabupaten::find($id);
        if ($oldData === null) {
            $_SESSION['flash_error'] = 'Kabupaten tidak ditemukan.';
            $this->redirect('/wilayah');
        }

        $data = Request::all();
        $result = WilayahService::updateKabupaten($id, $data, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/wilayah/kabupaten/edit/' . $id);
        }

        $_SESSION['flash_success'] = 'Kabupaten berhasil diperbarui.';
        $this->redirect('/wilayah');
    }

    public function kabupatenDelete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKabupaten::find($id);
        if ($oldData === null) {
            $_SESSION['flash_error'] = 'Kabupaten tidak ditemukan.';
            $this->redirect('/wilayah');
        }

        $result = WilayahService::deleteKabupaten($id, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Kabupaten berhasil dihapus.';
        }
        $this->redirect('/wilayah');
    }

    // --- Kecamatan ---

    public function kecamatanCreate(): void
    {
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $this->view('wilayah/kecamatan_form', [
            'pageTitle' => 'Tambah Kecamatan',
            'data' => [],
            'errors' => [],
            'kabupaten' => $kabupaten,
        ]);
    }

    public function kecamatanEdit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterKecamatan::find($id);
        if ($data === null) {
            $_SESSION['flash_error'] = 'Kecamatan tidak ditemukan.';
            $this->redirect('/wilayah');
        }
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $this->view('wilayah/kecamatan_form', [
            'pageTitle' => 'Edit Kecamatan',
            'data' => $data,
            'errors' => [],
            'kabupaten' => $kabupaten,
        ]);
    }

    public function kecamatanStore(): void
    {
        $data = Request::all();
        $result = WilayahService::createKecamatan($data, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/wilayah/kecamatan/create');
        }

        $_SESSION['flash_success'] = 'Kecamatan berhasil ditambahkan.';
        $this->redirect('/wilayah');
    }

    public function kecamatanUpdate(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKecamatan::find($id);
        if ($oldData === null) {
            $_SESSION['flash_error'] = 'Kecamatan tidak ditemukan.';
            $this->redirect('/wilayah');
        }

        $data = Request::all();
        $result = WilayahService::updateKecamatan($id, $data, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/wilayah/kecamatan/edit/' . $id);
        }

        $_SESSION['flash_success'] = 'Kecamatan berhasil diperbarui.';
        $this->redirect('/wilayah');
    }

    public function kecamatanDelete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterKecamatan::find($id);
        if ($oldData === null) {
            $_SESSION['flash_error'] = 'Kecamatan tidak ditemukan.';
            $this->redirect('/wilayah');
        }

        $result = WilayahService::deleteKecamatan($id, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Kecamatan berhasil dihapus.';
        }
        $this->redirect('/wilayah');
    }

    // --- Desa ---

    public function desaCreate(): void
    {
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $this->view('wilayah/desa_form', [
            'pageTitle' => 'Tambah Desa',
            'data' => [],
            'errors' => [],
            'kabupaten' => $kabupaten,
        ]);
    }

    public function desaEdit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = MasterDesa::find($id);
        if ($data === null) {
            $_SESSION['flash_error'] = 'Desa tidak ditemukan.';
            $this->redirect('/wilayah');
        }

        $kecamatan = MasterKecamatan::find((int) $data['kecamatan_id']);
        $kabupatenId = $kecamatan ? (int) $kecamatan['kabupaten_id'] : 0;
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');

        $this->view('wilayah/desa_form', [
            'pageTitle' => 'Edit Desa',
            'data' => $data,
            'errors' => [],
            'kabupaten' => $kabupaten,
            'selectedKabupaten' => $kabupatenId,
        ]);
    }

    public function desaStore(): void
    {
        $data = Request::all();
        $result = WilayahService::createDesa($data, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/wilayah/desa/create');
        }

        $_SESSION['flash_success'] = 'Desa berhasil ditambahkan.';
        $this->redirect('/wilayah');
    }

    public function desaUpdate(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterDesa::find($id);
        if ($oldData === null) {
            $_SESSION['flash_error'] = 'Desa tidak ditemukan.';
            $this->redirect('/wilayah');
        }

        $data = Request::all();
        $result = WilayahService::updateDesa($id, $data, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/wilayah/desa/edit/' . $id);
        }

        $_SESSION['flash_success'] = 'Desa berhasil diperbarui.';
        $this->redirect('/wilayah');
    }

    public function desaDelete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $oldData = MasterDesa::find($id);
        if ($oldData === null) {
            $_SESSION['flash_error'] = 'Desa tidak ditemukan.';
            $this->redirect('/wilayah');
        }

        $result = WilayahService::deleteDesa($id, $oldData, $this->getAdminId());
        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Desa berhasil dihapus.';
        }
        $this->redirect('/wilayah');
    }

    public function kecamatanJson(): void
    {
        $kabupatenId = (int) (Request::input('kabupaten_id', 0));
        if ($kabupatenId <= 0) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'ValidationError', 'message' => 'Parameter kabupaten_id wajib diisi.']);
            return;
        }
        $jember = MasterKabupaten::findByKode('3509');
        $jemberId = $jember ? (int) $jember['id'] : 0;
        if ($kabupatenId !== $jemberId) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'ValidationError', 'message' => 'Hanya Kabupaten Jember yang didukung.']);
            return;
        }
        $data = MasterKecamatan::findByKabupaten($kabupatenId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'OK', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function desaJson(): void
    {
        $kecamatanId = (int) (Request::input('kecamatan_id', 0));
        if ($kecamatanId <= 0) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'ValidationError', 'message' => 'Parameter kecamatan_id wajib diisi.']);
            return;
        }
        $data = MasterDesa::findByKecamatan($kecamatanId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'OK', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validateSimple(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $f) {
            if (empty(trim((string) ($data[$f] ?? '')))) {
                $errors[$f] = 'Field ' . str_replace('_', ' ', $f) . ' wajib diisi.';
            }
        }
        return $errors;
    }
}
