<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Services\ExportService;

class ExportController extends BaseApiController
{
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
        $currentUser = $GLOBALS['auth_user'] ?? null;
        if ($currentUser === null) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $input = $_GET;

        $errors = ExportService::validateFiltersStatic($input);

        if (count($errors) > 0) {
            $this->error('ValidationError', 'Validasi gagal.', $errors, 422);
            return;
        }

        $format = $input['format'] ?? 'csv';
        $includeDraft = isset($input['include_draft']) ? filter_var($input['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;

        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $service = new ExportService($currentUser['role'], (int)$currentUser['id'], $includeDraft);
            if ($jenis === 'hama') {
                $service->exportHama($format, $input);
            } else {
                $service->exportIrigasi($format, $input);
            }
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        }
    }
}
