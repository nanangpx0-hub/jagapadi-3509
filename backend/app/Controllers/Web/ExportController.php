<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Models\MasterKabupaten;
use App\Services\ExportService;

class ExportController extends Controller
{
    public function index(): void
    {
        $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
        $this->view('export/index', [
            'pageTitle' => 'Ekspor Data — JAGAPADI',
            'kabupaten' => $kabupaten,
            'errors' => [],
            'oldInput' => [],
        ]);
    }

    public function exportHama(): void
    {
        $this->handleExport('hama');
    }

    public function exportIrigasi(): void
    {
        $this->handleExport('irigasi');
    }

    private function handleExport(string $jenis): void
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $input = Request::all();

        $errors = ExportService::validateFiltersStatic($input);

        if (count($errors) > 0) {
            $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
            $_SESSION['flash_error'] = 'Validasi gagal: ' . implode(', ', array_values($errors));
            $this->view('export/index', [
                'pageTitle' => 'Ekspor Data — JAGAPADI',
                'kabupaten' => $kabupaten,
                'errors' => $errors,
                'oldInput' => $input,
            ]);
            return;
        }

        $format = $input['format'] ?? 'csv';
        $includeDraft = isset($input['include_draft']) ? filter_var($input['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;
        $service = new ExportService($role, $userId, $includeDraft);

        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            if ($jenis === 'hama') {
                $service->exportHama($format, $input);
            } else {
                $service->exportIrigasi($format, $input);
            }
        } catch (\DomainException $e) {
            $kabupaten = MasterKabupaten::all('nama_kabupaten', 'ASC');
            $_SESSION['flash_error'] = $e->getMessage();
            $this->view('export/index', [
                'pageTitle' => 'Ekspor Data — JAGAPADI',
                'kabupaten' => $kabupaten,
                'errors' => ['limit' => $e->getMessage()],
                'oldInput' => $input,
            ]);
        }
    }
}
