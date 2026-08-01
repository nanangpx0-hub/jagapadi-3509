<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Security;
use App\Models\MasterKabupaten;
use App\Models\MasterKecamatan;
use App\Models\MasterOpt;
use App\Services\LaporanHamaService;

class LaporanHamaLightController extends Controller
{
    public function create(): void
    {
        $optList = MasterOpt::allActive();
        $jember = MasterKabupaten::findByKode('3509');
        $kecamatanList = $jember ? MasterKecamatan::findByKabupaten((int) $jember['id']) : [];

        $this->view('laporan-hama/create-light', [
            'pageTitle' => 'Laporan Cepat - JAGAPADI',
            'optList' => $optList,
            'kecamatanList' => $kecamatanList,
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
        $action = $input['action'] ?? 'submit';
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        if ($action === 'draft') {
            $result = LaporanHamaService::createDraft((int) $currentUser['id'], $input, $ip, $userAgent);
        } else {
            $result = LaporanHamaService::createAndSubmit((int) $currentUser['id'], $input, $ip, $userAgent);
        }

        if (!$result['success']) {
            $errors = $result['errors'] ?? [];
            $optList = MasterOpt::allActive();
            $jember = MasterKabupaten::findByKode('3509');
            $kecamatanList = $jember ? MasterKecamatan::findByKabupaten((int) $jember['id']) : [];

            $this->view('laporan-hama/create-light', [
                'pageTitle' => 'Laporan Cepat - JAGAPADI',
                'optList' => $optList,
                'kecamatanList' => $kecamatanList,
                'errors' => $errors,
                'oldInput' => $input,
            ]);
            return;
        }

        $msg = $action === 'submit' ? 'Laporan berhasil dikirim.' : 'Draf berhasil disimpan.';
        $_SESSION['flash_success'] = $msg;
        $this->redirect('/laporan-hama');
    }
}
