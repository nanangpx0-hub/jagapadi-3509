<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
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

        if ($laporan['status'] !== 'Draf') {
            $_SESSION['flash_error'] = 'Hanya laporan dengan status Draf yang dapat diedit.';
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
}
