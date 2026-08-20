<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Security;
use App\Helpers\SecureImageUploader;
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
        unset($input['foto_url']);
        $action = $input['action'] ?? 'submit';
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $uploadRoot = dirname(__DIR__, 3) . '/public';
        $uploadedPhotoUrl = null;
        $file = $_FILES['foto'] ?? null;
        $hasPhoto = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($action === 'submit' && !$hasPhoto) {
            $this->renderCreateForm($input, [
                'foto' => 'Foto laporan wajib disertakan sebelum laporan dapat dikirim.',
            ]);
            return;
        }

        if ($hasPhoto) {
            try {
                $upload = SecureImageUploader::validateAndStore($file, [
                    'max_bytes' => 10485760,
                    'destination_dir' => $uploadRoot . '/assets/uploads/laporan-hama',
                    'relative_base' => 'assets/uploads/laporan-hama',
                ]);
                $input['foto_url'] = $upload['foto_url'];
                $uploadedPhotoUrl = $upload['foto_url'];
            } catch (\DomainException | \RuntimeException $e) {
                $this->renderCreateForm($input, ['foto' => $e->getMessage()]);
                return;
            }
        }

        if ($action === 'draft') {
            $result = LaporanHamaService::createDraft((int) $currentUser['id'], $input, $ip, $userAgent);
        } else {
            $result = LaporanHamaService::createAndSubmit((int) $currentUser['id'], $input, $ip, $userAgent);
        }

        if (!$result['success']) {
            if ($uploadedPhotoUrl !== null) {
                SecureImageUploader::deleteOldPhoto($uploadRoot, $uploadedPhotoUrl);
            }
            $errors = $result['errors'] ?? [];
            $this->renderCreateForm($input, $errors);
            return;
        }

        $msg = $action === 'submit' ? 'Laporan berhasil dikirim.' : 'Draf berhasil disimpan.';
        $_SESSION['flash_success'] = $msg;
        $this->redirect('/laporan-hama');
    }

    private function renderCreateForm(array $oldInput, array $errors): void
    {
        $optList = MasterOpt::allActive();
        $jember = MasterKabupaten::findByKode('3509');
        $kecamatanList = $jember ? MasterKecamatan::findByKabupaten((int) $jember['id']) : [];

        $this->view('laporan-hama/create-light', [
            'pageTitle' => 'Laporan Cepat - JAGAPADI',
            'optList' => $optList,
            'kecamatanList' => $kecamatanList,
            'errors' => $errors,
            'oldInput' => $oldInput,
        ]);
    }
}
