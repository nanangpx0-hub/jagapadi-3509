<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Security;
use App\Helpers\LaporanStatus;
use App\Models\MasterKabupaten;
use App\Models\MasterOpt;
use App\Services\LaporanHamaService;

class LaporanHamaController extends Controller
{
    public function index(): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $filters = Request::all();

        $result = LaporanHamaService::listForCurrentUser($currentUser, $filters);

        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $optList = MasterOpt::allActive();

        $this->view('laporan-hama/index', [
            'pageTitle' => 'Laporan Hama',
            'data' => $result['data'],
            'meta' => $result['meta'] ?? ['total' => 0, 'page' => 1, 'limit' => 20, 'last_page' => 1],
            'kabupaten' => $kabupaten,
            'optList' => $optList,
            'filters' => $filters,
            'currentUser' => $currentUser,
        ]);
    }

    public function create(): void
    {
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $optList = MasterOpt::allActive();

        $this->view('laporan-hama/create', [
            'pageTitle' => 'Buat Laporan Hama',
            'kabupaten' => $kabupaten,
            'optList' => $optList,
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
            $result = LaporanHamaService::createAndSubmit((int) $currentUser['id'], $input, $ip, $userAgent);
        } else {
            $result = LaporanHamaService::createDraft((int) $currentUser['id'], $input, $ip, $userAgent);
        }

        if (!$result['success']) {
            $errors = $result['errors'] ?? [];
            $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
            $optList = MasterOpt::allActive();
            $this->view('laporan-hama/create', [
                'pageTitle' => 'Buat Laporan Hama',
                'kabupaten' => $kabupaten,
                'optList' => $optList,
                'data' => [],
                'errors' => $errors,
                'oldInput' => $input,
            ]);
            return;
        }

        $msg = $action === 'submit' ? 'Laporan hama berhasil dikirim.' : 'Draf laporan hama berhasil disimpan.';
        $_SESSION['flash_success'] = $msg;
        $this->redirect('/laporan-hama');
    }

    public function show(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        $id = (int) ($params['id'] ?? 0);
        $result = LaporanHamaService::getDetailForCurrentUser($id, $currentUser);

        if (!$result['success']) {
            $_SESSION['flash_error'] = 'Laporan tidak ditemukan.';
            $this->redirect('/laporan-hama');
        }

        $this->view('laporan-hama/show', [
            'pageTitle' => 'Detail Laporan Hama',
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
        $result = LaporanHamaService::getDetailForCurrentUser($id, $currentUser);

        if (!$result['success']) {
            $_SESSION['flash_error'] = 'Laporan tidak ditemukan.';
            $this->redirect('/laporan-hama');
        }

        $laporan = $result['data'];

        if (!LaporanStatus::isEditableByPetugas($laporan['status'])) {
            $_SESSION['flash_error'] = 'Laporan dengan status ini tidak dapat diedit.';
            $this->redirect('/laporan-hama/' . $id);
        }

        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $optList = MasterOpt::allActive();

        $this->view('laporan-hama/edit', [
            'pageTitle' => 'Edit Laporan Hama',
            'kabupaten' => $kabupaten,
            'optList' => $optList,
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

        $result = LaporanHamaService::updateDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/laporan-hama/' . $id . '/edit');
        }

        $_SESSION['flash_success'] = 'Laporan hama berhasil diperbarui.';
        $this->redirect('/laporan-hama/' . $id);
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

        $result = LaporanHamaService::submitDraft($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/laporan-hama/' . $id . '/edit');
            return;
        }

        $_SESSION['flash_success'] = 'Laporan hama berhasil dikirim.';
        $this->redirect('/laporan-hama/' . $id);
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

        $result = LaporanHamaService::deleteDraft($id, (int) $currentUser['id'], $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Draf laporan hama berhasil dihapus.';
        }

        $this->redirect('/laporan-hama');
    }

    public function verify(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        if ($currentUser['role'] !== 'admin') {
            $_SESSION['flash_error'] = 'Aksi ini hanya untuk admin.';
            $this->redirect('/laporan-hama');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $catatan = $input['catatan_verifikasi'] ?? null;
        $result = LaporanHamaService::verify($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan hama berhasil diverifikasi.';
        }

        $this->redirect('/laporan-hama/' . $id);
    }

    public function reject(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        if ($currentUser['role'] !== 'admin') {
            $_SESSION['flash_error'] = 'Aksi ini hanya untuk admin.';
            $this->redirect('/laporan-hama');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $alasan = $input['alasan'] ?? '';
        $result = LaporanHamaService::reject($id, (int) $currentUser['id'], $alasan, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan hama berhasil ditolak.';
        }

        $this->redirect('/laporan-hama/' . $id);
    }

    public function archive(array $params): void
    {
        $currentUser = [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'role' => $_SESSION['role'] ?? '',
        ];

        if ($currentUser['role'] !== 'admin') {
            $_SESSION['flash_error'] = 'Aksi ini hanya untuk admin.';
            $this->redirect('/laporan-hama');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $input = Request::all();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $catatan = $input['catatan_verifikasi'] ?? null;
        $result = LaporanHamaService::archive($id, (int) $currentUser['id'], $catatan, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan hama berhasil diarsipkan.';
        }

        $this->redirect('/laporan-hama/' . $id);
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

        $result = LaporanHamaService::resubmit($id, (int) $currentUser['id'], $input, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = 'Laporan hama berhasil dikirim ulang.';
        }

        $this->redirect('/laporan-hama/' . $id);
    }
}
