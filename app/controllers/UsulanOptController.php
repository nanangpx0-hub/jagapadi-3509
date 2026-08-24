<?php

declare(strict_types=1);

class UsulanOptController extends Controller
{
    private const CREATE_ROLES = ['admin', 'petugas'];

    private UsulanOptReviewService $reviewService;
    private UsulanOptService $proposalService;
    private MasterOptService $masterService;
    private UsulanPhotoUploader $photoUploader;
    private OtherReportImportService $otherReportImportService;

    public function __construct(?Container $container = null)
    {
        parent::__construct($container);
        $this->reviewService = new UsulanOptReviewService();
        $this->proposalService = new UsulanOptService();
        $this->masterService = new MasterOptService();
        $this->photoUploader = new UsulanPhotoUploader();
        $this->otherReportImportService = new OtherReportImportService();
    }

    public function index(): void
    {
        $this->checkAuth();

        $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
        $userId = $isAdmin ? null : (int) $_SESSION['user_id'];

        $filters = $this->collectFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));

        $proposals = $this->model('UsulanOpt')->paginateFiltered($filters, $page, $perPage, $userId);
        $stats = $this->model('UsulanOpt')->getStats($userId);

        $photoCounts = [];
        foreach ($proposals['data'] as $row) {
            $photoCounts[(int) $row['id']] = $this->model('UsulanOpt')->countPhotos((int) $row['id']);
        }

        $this->view('usulan-opt/index', [
            'title' => $isAdmin ? 'Review Usulan OPT' : 'Usulan OPT Saya',
            'is_admin' => $isAdmin,
            'proposals' => $proposals['data'],
            'pagination' => $proposals,
            'stats' => $stats,
            'filters' => $filters,
            'photo_counts' => $photoCounts,
            'import_summary' => $_SESSION['usulan_opt_import_summary'] ?? null,
        ]);
        unset($_SESSION['usulan_opt_import_summary']);
    }

    public function importExcel(): void
    {
        $this->checkRole(self::CREATE_ROLES);
        $this->requireStateChangingRequest(['POST']);

        $file = $_FILES['excel_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = $this->excelUploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
            $this->redirect('usulan-opt');
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            $_SESSION['error'] = 'File impor tidak valid atau tidak berasal dari proses unggah.';
            $this->redirect('usulan-opt');
        }

        $excel = new UsulanOptExcelService();
        try {
            $rows = $excel->readImportRows((string) $file['tmp_name'], (string) $file['name']);
        } catch (Throwable $e) {
            error_log('UsulanOpt import read failed: ' . get_class($e));
            $_SESSION['error'] = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'File Excel gagal dibaca. Pastikan file tidak rusak dan gunakan template resmi.';
            $this->redirect('usulan-opt');
        }

        $ownerId = (int) $_SESSION['user_id'];
        $db = Database::getInstance()->getConnection();
        $success = 0;
        $optSuccess = 0;
        $otherReportSuccess = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($rows as $row) {
            $excelRow = (int) ($row['_excel_row'] ?? 0);
            unset($row['_excel_row']);
            $data = $this->proposalService->normalize($row);
            $routeToOtherReport = $this->otherReportImportService->supports((string) ($data['jenis'] ?? ''));
            $validationData = $data;
            if ($routeToOtherReport) {
                // Reuse every common validation rule without treating a supported
                // Laporan Lainnya category as an invalid OPT enum.
                $validationData['jenis'] = 'hama';
            }
            $errors = $this->proposalService->validate($validationData, false);
            $locationIds = [$data['kabupaten_id'], $data['kecamatan_id'], $data['desa_id']];
            $filledLocationIds = count(array_filter($locationIds, static fn($value): bool => $value !== null));
            if ($filledLocationIds > 0 && $filledLocationIds < 3) {
                $errors[] = 'kabupaten_id, kecamatan_id, dan desa_id harus diisi lengkap atau dikosongkan';
            } elseif ($filledLocationIds === 3) {
                $resolved = $this->proposalService->resolveWilayahForProposal(
                    (int) $data['kabupaten_id'],
                    (int) $data['kecamatan_id'],
                    (int) $data['desa_id']
                );
                if (($resolved['ok'] ?? false) !== true) {
                    $errors[] = (string) ($resolved['error'] ?? 'Hierarki wilayah tidak valid');
                }
            }

            if ($errors !== []) {
                $failed++;
                if (count($details) < 100) {
                    $details[] = ['row' => $excelRow, 'errors' => array_values(array_unique($errors))];
                }
                continue;
            }

            $duplicate = $routeToOtherReport
                ? $this->otherReportImportService->importDuplicateExists($ownerId, $data)
                : $this->proposalService->importDuplicateExists($ownerId, $data);
            if ($duplicate) {
                $skipped++;
                continue;
            }

            try {
                $db->beginTransaction();
                if ($routeToOtherReport) {
                    $this->otherReportImportService->createDraft($ownerId, $data, $ownerId);
                } elseif (($_SESSION['role'] ?? '') === 'admin') {
                    $this->proposalService->createPendingAdminImport($ownerId, $data, $ownerId);
                } else {
                    $this->proposalService->createDraft($ownerId, $data, $ownerId, 'excel_import');
                }
                $db->commit();
                if ($routeToOtherReport) {
                    $otherReportSuccess++;
                } else {
                    $optSuccess++;
                }
                $success++;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $failed++;
                if (count($details) < 100) {
                    $details[] = ['row' => $excelRow, 'errors' => ['Gagal menyimpan baris ke database']];
                }
                error_log('UsulanOpt import row failed');
            }
        }

        $_SESSION['usulan_opt_import_summary'] = [
            'total' => count($rows),
            'success' => $success,
            'opt_success' => $optSuccess,
            'other_report_success' => $otherReportSuccess,
            'skipped' => $skipped,
            'failed' => $failed,
            'details' => $details,
            'truncated' => $failed > count($details),
        ];
        if ($success > 0) {
            $this->invalidateStatsCache(['stats_']);
        }
        $_SESSION[$failed === 0 ? 'success' : 'info'] = sprintf(
            'Impor selesai: %d berhasil (%d Usulan OPT, %d Laporan Lainnya), %d dilewati karena sudah ada, dan %d gagal dari %d baris.',
            $success,
            $optSuccess,
            $otherReportSuccess,
            $skipped,
            $failed,
            count($rows)
        );
        $this->redirect('usulan-opt');
    }

    public function exportExcel(): void
    {
        $this->checkRole(self::CREATE_ROLES);
        $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
        $userId = $isAdmin ? null : (int) $_SESSION['user_id'];
        $rows = $this->model('UsulanOpt')->exportFiltered(
            $this->collectFilters(),
            $userId,
            UsulanOptExcelService::MAX_EXPORT_ROWS
        );

        $path = tempnam(sys_get_temp_dir(), 'usulan_opt_export_');
        if ($path === false) {
            $_SESSION['error'] = 'Gagal menyiapkan file ekspor.';
            $this->redirect('usulan-opt');
        }
        try {
            (new UsulanOptExcelService())->createExportFile($rows, $path);
            $this->sendExcelDownload($path, 'usulan_opt_' . date('Ymd_His') . '.xlsx');
        } catch (Throwable $e) {
            @unlink($path);
            error_log('UsulanOpt export failed: ' . get_class($e));
            $_SESSION['error'] = $e instanceof LengthException
                ? $e->getMessage()
                : 'Gagal membuat file ekspor Excel.';
            $this->redirect('usulan-opt');
        }
    }

    public function downloadTemplate(): void
    {
        $this->checkRole(self::CREATE_ROLES);
        $path = tempnam(sys_get_temp_dir(), 'usulan_opt_template_');
        if ($path === false) {
            $_SESSION['error'] = 'Gagal menyiapkan template impor.';
            $this->redirect('usulan-opt');
        }
        try {
            (new UsulanOptExcelService())->createTemplateFile($path);
            $this->sendExcelDownload($path, 'template_import_usulan_opt.xlsx');
        } catch (Throwable $e) {
            @unlink($path);
            error_log('UsulanOpt template failed: ' . get_class($e));
            $_SESSION['error'] = 'Gagal membuat template impor.';
            $this->redirect('usulan-opt');
        }
    }

    public function create(): void
    {
        $this->checkRole(self::CREATE_ROLES);

        $oldInput = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);

        $this->view('usulan-opt/create', [
            'title' => 'Buat Usulan OPT',
            'old' => $oldInput,
            'wilayah_names' => $_SESSION['form_wilayah_names'] ?? [],
            'keyakinan_options' => UsulanOptService::KEYAKINAN,
        ]);
        unset($_SESSION['form_wilayah_names']);
    }

    public function store(): void
    {
        $this->checkRole(self::CREATE_ROLES);
        $this->requireStateChangingRequest(['POST']);

        $ownerId = (int) $_SESSION['user_id'];
        $actorId = $ownerId;
        $intent = ($_POST['intent'] ?? '') === 'submit'
            ? UsulanOpt::STATUS_PENDING
            : UsulanOpt::STATUS_DRAFT;

        $data = $this->proposalService->normalize($_POST);
        $forSubmit = ($intent === UsulanOpt::STATUS_PENDING);
        $errors = $this->proposalService->validate($data, $forSubmit);

        $uploadedFiles = [];
        if ($errors === []) {
            $uploadResult = $this->handlePhotoUploads($actorId, 0);
            if ($uploadResult['errors'] !== []) {
                $errors = array_merge($errors, $uploadResult['errors']);
            } else {
                $uploadedFiles = $uploadResult['files'];
            }
        }
        if ($forSubmit && $errors === [] && count($uploadedFiles) < 1) {
            $errors[] = 'Minimal satu foto bukti wajib dilampirkan saat mengirim review';
        }

        if ($errors !== []) {
            foreach ($uploadedFiles as $file) {
                $this->photoUploader->deleteByPath((string) ($file['file_path'] ?? ''));
            }
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = $_POST;
            $this->rememberWilayahNames($_POST);
            $this->redirect('usulan-opt/create');
        }

        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();

            $proposalId = $forSubmit
                ? $this->createPendingProposal($ownerId, $data, $actorId, $uploadedFiles)
                : $this->createDraftWithPhotos($ownerId, $data, $actorId, $uploadedFiles);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($uploadedFiles as $file) {
                $this->photoUploader->deleteByPath((string) ($file['file_path'] ?? ''));
            }
            error_log('UsulanOpt store failed');
            $_SESSION['error'] = 'Gagal menyimpan usulan OPT. Silakan coba lagi.';
            $_SESSION['form_data'] = $_POST;
            $this->redirect('usulan-opt/create');
        }

        unset($_SESSION['form_data'], $_SESSION['form_wilayah_names']);
        $this->invalidateStatsCache(['stats_']);

        if ($forSubmit) {
            $_SESSION['success'] = 'Usulan OPT terkirim dan menunggu review Admin.';
        } else {
            $_SESSION['success'] = 'Draf usulan OPT tersimpan. Kirim untuk review setelah melengkapi data dan foto.';
        }
        $this->redirect('usulan-opt/detail/' . $proposalId);
    }

    public function detail($id): void
    {
        $this->checkAuth();

        $proposalId = (int) $id;
        if ($proposalId <= 0) {
            $this->redirect('usulan-opt');
        }

        $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
        $proposal = $this->model('UsulanOpt')->findByIdDetailed($proposalId);

        if (!$proposal || (!$isAdmin && (int) $proposal['user_id'] !== (int) $_SESSION['user_id'])) {
            $_SESSION['error'] = 'Usulan tidak ditemukan atau bukan milik Anda.';
            $this->redirect('usulan-opt');
        }

        $duplicates = [];
        if ($isAdmin && $proposal['status'] === UsulanOptReviewService::STATUS_PENDING) {
            $duplicates = $this->masterService->findDuplicates(
                $this->masterService->normalize([
                    'kode_opt' => '',
                    'nama_opt' => $proposal['nama_nasional'] ?: $proposal['nama_lokal'],
                    'nama_lokal' => $proposal['nama_lokal'],
                ])
            );
        }

        $this->view('usulan-opt/detail', [
            'title' => 'Detail Usulan OPT',
            'is_admin' => $isAdmin,
            'proposal' => $proposal,
            'photos' => $this->model('UsulanOpt')->getPhotos($proposalId),
            'history' => $this->model('UsulanOpt')->getHistory($proposalId),
            'laporan_count' => $this->model('UsulanOpt')->countReports($proposalId),
            'duplicates' => $duplicates,
            'can_edit' => !$isAdmin && in_array($proposal['status'], UsulanOpt::OWNER_EDITABLE, true),
        ]);
    }

    public function edit($id): void
    {
        $userId = (int) $_SESSION['user_id'];
        $this->authorizeOwner($id, $userId);

        $proposalId = (int) $id;
        $proposal = $this->model('UsulanOpt')->findByIdDetailed($proposalId);

        if (!in_array($proposal['status'], UsulanOpt::OWNER_EDITABLE, true)) {
            $_SESSION['error'] = 'Usulan dengan status "' . $proposal['status'] . '" tidak dapat diubah.';
            $this->redirect('usulan-opt/detail/' . $proposalId);
        }

        $oldInput = $_SESSION['form_data'] ?? null;
        unset($_SESSION['form_data']);

        $data = $oldInput ?? $this->proposalService->normalize(
            $this->flattenProposalForForm($proposal)
        );

        $wilayahNames = [];
        if ((int) ($data['kabupaten_id'] ?? 0) > 0) {
            $resolved = $this->proposalService->resolveWilayahForProposal(
                (int) $data['kabupaten_id'],
                (int) $data['kecamatan_id'],
                (int) $data['desa_id']
            );
            if ($resolved['ok']) {
                $wilayahNames = $resolved['names'];
            }
        }

        $this->view('usulan-opt/edit', [
            'title' => 'Edit Usulan OPT',
            'proposal' => $proposal,
            'old' => $data,
            'photos' => $this->model('UsulanOpt')->getPhotos($proposalId),
            'wilayah_names' => $wilayahNames,
            'keyakinan_options' => UsulanOptService::KEYAKINAN,
        ]);
    }

    public function update(): void
    {
        $this->requireStateChangingRequest(['POST']);

        $userId = (int) $_SESSION['user_id'];
        $proposalId = (int) ($_POST['id'] ?? 0);
        $expectedStatus = (string) ($_POST['expected_status'] ?? '');

        $result = $this->proposalService->updateProposal($proposalId, $userId, $expectedStatus, $this->proposalService->normalize($_POST), $userId);

        if (($result['reason'] ?? '') === UsulanOptService::REASON_INVALID) {
            $this->handleUploadedFilesRollbackOnly();
            $_SESSION['error'] = implode('<br>', $result['errors'] ?? ['Data tidak valid.']);
            $_SESSION['form_data'] = $_POST;
            $this->redirect('usulan-opt/edit/' . $proposalId);
        }

        if (($result['ok'] ?? false) !== true) {
            $this->flashOwnerActionFailure((string) ($result['reason'] ?? ''), $proposalId);
        }

        $uploadResult = $this->handlePhotoUploads($userId, $this->model('UsulanOpt')->countPhotos($proposalId));
        if ($uploadResult['files'] !== []) {
            $db = Database::getInstance()->getConnection();
            try {
                $db->beginTransaction();
                $stillEditable = $this->fetchEditableStatus($proposalId, $userId, $expectedStatus);
                if ($stillEditable === false) {
                    throw new RuntimeException('conflict');
                }
                foreach ($uploadResult['files'] as $file) {
                    $this->model('UsulanOpt')->addPhoto($proposalId, $file);
                }
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                foreach ($uploadResult['files'] as $file) {
                    $this->photoUploader->deleteByPath((string) ($file['file_path'] ?? ''));
                }
                error_log('UsulanOpt photo attach failed');
                $_SESSION['info'] = 'Perubahan data tersimpan, namun sebagian foto gagal dilampirkan.';
            }
            $this->writePhotoAudit($userId, count($uploadResult['files']), $proposalId);
        }

        $_SESSION['success'] = 'Usulan OPT berhasil diperbarui.';
        $this->redirect('usulan-opt/detail/' . $proposalId);
    }

    public function submit(): void
    {
        $this->requireStateChangingRequest(['POST']);
        $this->runOwnerTransition('submitDraft', 'Draf dikirim untuk review.');
    }

    public function resubmit(): void
    {
        $this->requireStateChangingRequest(['POST']);
        $this->runOwnerTransition('resubmit', 'Usulan dikirim ulang untuk review Admin.');
    }

    public function deleteDraft(): void
    {
        $this->requireStateChangingRequest(['POST']);

        $userId = (int) $_SESSION['user_id'];
        $proposalId = (int) ($_POST['id'] ?? 0);

        $result = $this->proposalService->deleteDraft($proposalId, $userId, $userId);
        if (($result['ok'] ?? false) === true) {
            $_SESSION['success'] = 'Draf usulan OPT berhasil dihapus.';
            $this->invalidateStatsCache(['stats_']);
            $this->redirect('usulan-opt');
        }

        $this->flashOwnerActionFailure((string) ($result['reason'] ?? ''), $proposalId);
    }

    public function deletePhoto(): void
    {
        $this->requireStateChangingRequest(['POST']);

        $userId = (int) $_SESSION['user_id'];
        $photoId = (int) ($_POST['photo_id'] ?? 0);
        $photo = $this->model('UsulanOpt')->findPhoto($photoId);

        if (!$photo) {
            $_SESSION['error'] = 'Foto tidak ditemukan.';
            $this->redirect('usulan-opt');
        }

        $proposal = $this->model('UsulanOpt')->findByIdDetailed((int) $photo['usulan_opt_id']);
        if (!$proposal || (int) $proposal['user_id'] !== $userId
            || !in_array($proposal['status'], UsulanOpt::OWNER_EDITABLE, true)) {
            $_SESSION['error'] = 'Foto hanya dapat dihapus pada draf/perlu perbaikan milik Anda.';
            $this->redirect('usulan-opt/detail/' . (int) $photo['usulan_opt_id']);
        }

        $stmt = Database::getInstance()->getConnection()->prepare(
            'DELETE FROM usulan_opt_photos WHERE id = ? AND usulan_opt_id IN (
                SELECT id FROM usulan_opt WHERE user_id = ? AND status IN (?, ?) AND deleted_at IS NULL)'
        );
        $stmt->execute([$photoId, $userId, UsulanOpt::STATUS_DRAFT, UsulanOpt::STATUS_REVISION]);

        if ($stmt->rowCount() > 0) {
            $this->photoUploader->deleteByPath((string) $photo['file_path']);
            $this->writeSimpleAudit($userId, 'delete_photo', (int) $photo['usulan_opt_id'], 'Foto usulan dihapus pemilik');
            $_SESSION['success'] = 'Foto berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Foto gagal dihapus.';
        }

        $this->redirect('usulan-opt/edit/' . (int) $photo['usulan_opt_id']);
    }

    public function requestRevision(): void
    {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $proposalId = (int) ($_POST['id'] ?? 0);
        $catatan = trim((string) ($_POST['catatan_perbaikan'] ?? ($_POST['alasan'] ?? '')));
        $reviewerId = (int) $_SESSION['user_id'];

        try {
            $result = $this->reviewService->requestRevision($proposalId, $reviewerId, $catatan);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = 'Catatan perbaikan wajib diisi minimal 10 karakter.';
            $this->redirect('usulan-opt');
        } catch (RuntimeException $e) {
            error_log('UsulanOpt requestRevision failed');
            $_SESSION['error'] = 'Gagal memproses permintaan perbaikan. Coba lagi.';
            $this->redirect('usulan-opt');
        }

        if (($result['ok'] ?? false) === true) {
            $_SESSION['success'] = 'Permintaan perbaikan terkirim kepada Petugas.';
            $this->invalidateStatsCache(['stats_']);
            $this->redirect('usulan-opt/detail/' . $proposalId);
        }

        $this->flashDecisionFailure((string) ($result['reason'] ?? ''));
    }

    public function review(): void
    {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $proposalId = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $reviewerId = (int) $_SESSION['user_id'];
        $catatan = trim((string) ($_POST['catatan_review'] ?? ''));

        if (!in_array($action, ['approve', 'merge', 'reject'], true)) {
            $_SESSION['error'] = 'Aksi review tidak valid.';
            $this->redirect('usulan-opt');
        }

        $proposal = $this->model('UsulanOpt')->findByIdDetailed($proposalId);
        if (!$proposal) {
            $_SESSION['error'] = 'Usulan tidak ditemukan.';
            $this->redirect('usulan-opt');
        }
        $name = $proposal['nama_nasional'] ?: $proposal['nama_lokal'];

        if ($action === 'approve') {
            if ($proposal['status'] !== UsulanOptReviewService::STATUS_PENDING) {
                $_SESSION['info'] = "Usulan \"{$name}\" sudah direview sebelumnya.";
                $this->redirect('usulan-opt');
            }
            $this->redirect('usulan-opt/finalize/' . $proposalId);
        }

        if ($action === 'merge') {
            $masterOptId = (int) ($_POST['master_opt_id'] ?? 0);
            try {
                $result = $this->reviewService->merge($proposalId, $masterOptId, $reviewerId, $catatan);
            } catch (RuntimeException $e) {
                error_log('UsulanOpt merge error');
                $_SESSION['error'] = 'Gagal memproses penggabungan usulan. Coba lagi.';
                $this->redirect('usulan-opt');
            }

            $this->flashReviewResult($result, $name, 'digabungkan', $proposalId);
        }

        $alasan = trim((string) ($_POST['alasan'] ?? $catatan));
        if (mb_strlen($alasan) < 10) {
            $_SESSION['error'] = 'Alasan penolakan wajib diisi minimal 10 karakter.';
            $this->redirect('usulan-opt');
        }

        try {
            $result = $this->reviewService->rejectPermanent($proposalId, $reviewerId, $alasan);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = 'Alasan penolakan wajib diisi minimal 10 karakter.';
            $this->redirect('usulan-opt');
        } catch (RuntimeException $e) {
            error_log('UsulanOpt reject failed');
            $_SESSION['error'] = 'Gagal memproses penolakan usulan. Coba lagi.';
            $this->redirect('usulan-opt');
        }

        $this->flashReviewResult($result, $name, 'ditolak permanen', $proposalId);
    }

    public function finalize($id): void
    {
        $this->checkRole(['admin']);

        $proposalId = (int) $id;
        if ($proposalId <= 0) {
            $this->redirect('usulan-opt');
        }

        $proposal = $this->model('UsulanOpt')->findByIdDetailed($proposalId);
        if (!$proposal) {
            $_SESSION['error'] = 'Usulan tidak ditemukan.';
            $this->redirect('usulan-opt');
        }
        if ($proposal['status'] !== UsulanOptReviewService::STATUS_PENDING) {
            $_SESSION['info'] = 'Usulan ini sudah direview sebelumnya.';
            $this->redirect('usulan-opt');
        }

        $prefill = $this->buildPrefill($proposal);
        $duplicates = $this->masterService->findDuplicates($prefill);

        $this->view('usulan-opt/finalize', [
            'title' => 'Finalisasi Master OPT dari Usulan',
            'proposal' => $proposal,
            'prefill' => $prefill,
            'duplicates' => $duplicates,
            'filter_options' => [
                'jenis' => MasterOptService::JENIS,
                'status_karantina' => MasterOptService::STATUS_KARANTINA,
                'tingkat_bahaya' => MasterOptService::TINGKAT_BAHAYA,
                'kategori' => ['Hama Karantina', 'OPT Utama', 'OPT Non-Karantina'],
            ],
        ]);
    }

    public function approveNew(): void
    {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $proposalId = (int) ($_POST['id'] ?? 0);
        $reviewerId = (int) $_SESSION['user_id'];
        $catatan = trim((string) ($_POST['catatan_review'] ?? ''));

        $masterData = $this->masterService->normalize($_POST);
        $errors = $this->masterService->validate($masterData);
        if (($masterData['kode_opt'] ?? '') === '') {
            $errors = array_values(array_filter(
                $errors,
                static fn (string $error): bool => $error !== 'Kode OPT wajib diisi'
            ));
        }

        if ($errors !== []) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = $_POST;
            $this->redirect('usulan-opt/finalize/' . $proposalId);
        }

        try {
            $result = $this->reviewService->approveNew($proposalId, $reviewerId, $masterData, $catatan);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = 'Data master tidak valid. Periksa kembali isian Anda.';
            $_SESSION['form_data'] = $_POST;
            $this->redirect('usulan-opt/finalize/' . $proposalId);
        } catch (RuntimeException $e) {
            error_log('UsulanOpt approveNew failed');
            $_SESSION['error'] = 'Gagal memproses persetujuan usulan. Coba lagi.';
            $_SESSION['form_data'] = $_POST;
            $this->redirect('usulan-opt/finalize/' . $proposalId);
        }

        if (($result['reason'] ?? '') === UsulanOptReviewService::REASON_DUPLICATE) {
            $_SESSION['error'] = 'Master OPT dengan kode atau nama serupa sudah ada. Gunakan aksi Gabungkan.';
            unset($_SESSION['form_data']);
            $this->redirect('usulan-opt');
        }

        if (($result['ok'] ?? false) !== true) {
            $_SESSION['info'] = 'Usulan sudah direview oleh sesi lain.';
            unset($_SESSION['form_data']);
            $this->redirect('usulan-opt');
        }

        $this->invalidateStatsCache(['stats_']);

        $relinked = (int) ($result['relinked'] ?? 0);
        $_SESSION['success'] = "Master OPT baru berhasil dibuat dan {$relinked} laporan telah terhubung.";
        unset($_SESSION['form_data']);
        $this->redirect('usulan-opt/detail/' . $proposalId);
    }

    /**
     * Approve selected pending proposals or every pending proposal matching
     * the current filters. Nama yang sama memperbarui master pada ID yang sama.
     */
    public function bulkApprove(): void
    {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $approveAll = ($_POST['approve_all'] ?? '') === '1';
        if ($approveAll) {
            $filters = ['status' => UsulanOpt::STATUS_PENDING];
            $jenis = strtolower(trim((string) ($_POST['filter_jenis'] ?? '')));
            if (in_array($jenis, MasterOptService::JENIS, true)) {
                $filters['jenis'] = $jenis;
            }
            $q = trim((string) ($_POST['filter_q'] ?? ''));
            if ($q !== '') {
                $filters['q'] = mb_substr($q, 0, 100);
            }
            foreach (['date_from', 'date_to'] as $key) {
                $value = trim((string) ($_POST['filter_' . $key] ?? ''));
                if ($value !== '') {
                    $filters[$key] = $value;
                }
            }
            $rows = $this->model('UsulanOpt')->exportFiltered($filters, null, 5000);
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        } else {
            $rawIds = $_POST['ids'] ?? [];
            $ids = is_array($rawIds)
                ? array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn (int $id): bool => $id > 0)))
                : [];
            if (count($ids) > 500) {
                $_SESSION['error'] = 'Maksimal 500 usulan dapat disetujui dalam satu proses pilihan.';
                $this->redirect('usulan-opt');
            }
        }

        if ($ids === []) {
            $_SESSION['error'] = 'Tidak ada usulan Menunggu Review yang dipilih.';
            $this->redirect('usulan-opt');
        }

        // Proses dari ID lama ke baru agar jika ada nama sama, usulan terbaru
        // menjadi data akhir yang menggantikan versi sebelumnya.
        sort($ids, SORT_NUMERIC);

        $approved = 0;
        $replaced = 0;
        $skipped = 0;
        $failed = 0;
        $reviewerId = (int) $_SESSION['user_id'];
        foreach ($ids as $id) {
            $proposal = $this->model('UsulanOpt')->findByIdDetailed($id);
            if (!$proposal || $proposal['status'] !== UsulanOpt::STATUS_PENDING) {
                $skipped++;
                continue;
            }
            $masterData = $this->bulkMasterData($proposal);
            try {
                $result = $this->reviewService->approveNew(
                    $id,
                    $reviewerId,
                    $masterData,
                    'Disetujui melalui persetujuan massal.'
                );
                if (($result['ok'] ?? false) === true) {
                    $approved++;
                    if (($result['replaced'] ?? false) === true) {
                        $replaced++;
                    }
                } elseif (($result['reason'] ?? '') === UsulanOptReviewService::REASON_DUPLICATE) {
                    $failed++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $failed++;
                error_log('UsulanOpt bulkApprove failed for proposal #' . $id);
            }
        }

        if ($approved > 0) {
            $this->invalidateStatsCache(['stats_']);
        }
        $_SESSION[$approved > 0 ? 'success' : 'info'] = sprintf(
            '%d usulan disetujui (%d memperbarui data master yang sama), %d sudah berubah status, dan %d gagal.',
            $approved,
            $replaced,
            $skipped,
            $failed
        );
        $this->redirect('usulan-opt?status=' . rawurlencode(UsulanOpt::STATUS_PENDING));
    }

    /**
     * Hapus massal usulan terpilih (khusus Admin). Status Disetujui/Digabungkan
     * dilewati karena terikat master OPT dan jejak audit laporan.
     */
    public function bulkDelete(): void
    {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $ids = $_POST['ids'] ?? null;
        if (!is_array($ids)) {
            $_SESSION['error'] = 'Tidak ada usulan yang dipilih.';
            $this->redirect('usulan-opt');
        }

        try {
            $result = $this->proposalService->bulkDeleteForAdmin($ids, (int) $_SESSION['user_id']);
        } catch (RuntimeException $e) {
            error_log('UsulanOpt bulkDelete failed');
            $_SESSION['error'] = 'Gagal memindahkan usulan ke recycle bin. Coba lagi.';
            $this->redirect('usulan-opt');
        }

        if (($result['requested'] ?? 0) === 0) {
            $_SESSION['error'] = 'Tidak ada usulan yang dipilih.';
            $this->redirect('usulan-opt');
        }

        $this->invalidateStatsCache(['stats_']);

        $deleted = (int) $result['deleted'];
        $skipped = (int) $result['skipped'];
        if ($deleted === 0) {
            $_SESSION['info'] = "Tidak ada usulan yang dapat dihapus. {$skipped} dipilih berstatus Disetujui/Digabungkan dan dilindungi.";
        } elseif ($skipped > 0) {
            $_SESSION['success'] = "{$deleted} usulan dipindahkan ke recycle bin. {$skipped} dilewati karena berstatus Disetujui/Digabungkan (terkait master OPT).";
        } else {
            $_SESSION['success'] = "{$deleted} usulan dipindahkan ke recycle bin.";
        }

        $this->redirect('usulan-opt');
    }

    public function searchMaster(): void
    {
        $this->checkRole(['admin']);

        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $jenis = $_GET['jenis'] ?? null;
        $jenis = is_string($jenis) && $jenis !== '' ? strtolower($jenis) : null;

        if (!empty($_GET['match_jenis'])) {
            $proposalJenis = isset($_GET['proposal_jenis']) ? strtolower((string) $_GET['proposal_jenis']) : null;
            if (in_array($proposalJenis, MasterOptService::JENIS, true)) {
                $jenis = $proposalJenis;
            }
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->masterService->searchActive($q, $jenis, $page, 20);

        $items = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'kode_opt' => $row['kode_opt'],
                'nama_opt' => $row['nama_opt'],
                'nama_ilmiah' => $row['nama_ilmiah'],
                'nama_lokal' => $row['nama_lokal'],
                'jenis' => $row['jenis'],
            ];
        }, $result['items']);

        $this->json(['success' => true, 'items' => $items, 'has_more' => $result['has_more']]);
    }

    // ==================== helpers ====================

    private function runOwnerTransition(string $method, string $successMessage): void
    {
        $this->requireStateChangingRequest(['POST']);

        $userId = (int) $_SESSION['user_id'];
        $proposalId = (int) ($_POST['id'] ?? 0);

        $result = $this->proposalService->{$method}($proposalId, $userId, $userId);
        if (($result['ok'] ?? false) === true) {
            $_SESSION['success'] = $successMessage;
            $this->invalidateStatsCache(['stats_']);
            $this->redirect('usulan-opt/detail/' . $proposalId);
        }

        if (($result['reason'] ?? '') === UsulanOptService::REASON_INVALID) {
            $_SESSION['error'] = 'Lengkapi data berikut sebelum mengirim:<br>'
                . implode('<br>', $result['errors'] ?? []);
            $this->redirect('usulan-opt/edit/' . $proposalId);
        }

        $this->flashOwnerActionFailure((string) ($result['reason'] ?? ''), $proposalId);
    }

    private function authorizeOwner(mixed $id, int $userId): void
    {
        $this->checkAuth();

        $proposalId = (int) $id;
        if ($proposalId <= 0) {
            $this->redirect('usulan-opt');
        }

        $proposal = $this->model('UsulanOpt')->findByIdDetailed($proposalId);
        if (!$proposal || (int) $proposal['user_id'] !== $userId) {
            $_SESSION['error'] = 'Usulan tidak ditemukan atau bukan milik Anda.';
            $this->redirect('usulan-opt');
        }
    }

    private function fetchEditableStatus(int $proposalId, int $ownerId, string $expectedStatus): bool
    {
        $stmt = Database::getInstance()->getConnection()->prepare(
            'SELECT COUNT(*) FROM usulan_opt WHERE id = ? AND user_id = ? AND status = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$proposalId, $ownerId, $expectedStatus]);

        return (int) $stmt->fetchColumn() === 1;
    }

    /**
     * @return array{files:array<int,array<string,mixed>>,errors:string[]}
     */
    private function handlePhotoUploads(int $actorId, int $existingCount): array
    {
        $files = $_FILES['photos'] ?? null;
        $errors = [];

        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return ['files' => [], 'errors' => []];
        }

        $picked = [];
        foreach ($files['name'] as $index => $name) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $picked[$index] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index],
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        if ($picked === []) {
            return ['files' => [], 'errors' => []];
        }

        if ($existingCount + count($picked) > UsulanPhotoUploader::MAX_FILES_PER_USULAN) {
            return ['files' => [], 'errors' => ['Maksimal ' . UsulanPhotoUploader::MAX_FILES_PER_USULAN . ' foto per usulan.']];
        }

        $stored = [];
        foreach ($picked as $file) {
            $result = $this->photoUploader->upload($file, $actorId);
            if ($result['success']) {
                $stored[] = $result['file'];
            } else {
                $errors[] = (string) $result['error'];
            }
        }

        return ['files' => $stored, 'errors' => $errors];
    }

    private function handleUploadedFilesRollbackOnly(): void
    {
        $uploadResult = $this->handlePhotoUploads((int) $_SESSION['user_id'], UsulanPhotoUploader::MAX_FILES_PER_USULAN);
        foreach ($uploadResult['files'] as $file) {
            $this->photoUploader->deleteByPath((string) ($file['file_path'] ?? ''));
        }
    }

    private function createDraftWithPhotos(int $ownerId, array $data, int $actorId, array $photos): int
    {
        $proposalId = $this->proposalService->createDraft($ownerId, $data, $actorId);
        foreach ($photos as $file) {
            $this->model('UsulanOpt')->addPhoto($proposalId, $file);
        }
        if ($photos !== []) {
            $first = reset($photos);
            $this->setLegacyFotoUrl($proposalId, (string) $first['file_path']);
        }
        $this->writePhotoAudit($actorId, count($photos), $proposalId);

        return $proposalId;
    }

    private function createPendingProposal(int $ownerId, array $data, int $actorId, array $photos): int
    {
        $firstPhoto = reset($photos);
        $stmt = Database::getInstance()->getConnection()->prepare(
            'INSERT INTO usulan_opt
                (user_id, nama_nasional, nama_lokal, jenis, komoditas, tanggal_ditemukan,
                 kabupaten_id, kecamatan_id, desa_id, alamat_lokasi, latitude, longitude,
                 bagian_terserang, pola_gejala, estimasi_terdampak, satuan_terdampak,
                 tingkat_keyakinan, sumber_identifikasi, ciri_ciri, wilayah, foto_url,
                 status, submitted_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $hierarchy = $this->safeHierarchy($data);
        $stmt->execute([
            $ownerId,
            $data['nama_nasional'] ?? null,
            $data['nama_lokal'] ?? null,
            $data['jenis'] ?? 'hama',
            $data['komoditas'] ?? null,
            $data['tanggal_ditemukan'] ?? null,
            $data['kabupaten_id'] ?? null,
            $data['kecamatan_id'] ?? null,
            $data['desa_id'] ?? null,
            $data['alamat_lokasi'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['bagian_terserang'] ?? null,
            $data['pola_gejala'] ?? null,
            $data['estimasi_terdampak'] ?? null,
            $data['satuan_terdampak'] ?? null,
            $data['tingkat_keyakinan'] ?? null,
            $data['sumber_identifikasi'] ?? null,
            trim((string) ($data['ciri_ciri'] ?? '')) ?: null,
            $hierarchy['wilayah'],
            $firstPhoto ? (string) $firstPhoto['file_path'] : null,
            UsulanOpt::STATUS_PENDING,
            date('Y-m-d H:i:s'),
        ]);

        $proposalId = (int) Database::getInstance()->getConnection()->lastInsertId();
        $this->model('UsulanOpt')->addHistory(
            $proposalId,
            null,
            UsulanOpt::STATUS_PENDING,
            $actorId,
            'Form mandiri: langsung dikirim untuk review'
        );
        $this->notifySubmitted($ownerId, $proposalId);
        foreach ($photos as $file) {
            $this->model('UsulanOpt')->addPhoto($proposalId, $file);
        }
        $this->writePhotoAudit($actorId, count($photos), $proposalId);

        return $proposalId;
    }

    private function safeHierarchy(array $data): array
    {
        $ids = [$data['kabupaten_id'] ?? null, $data['kecamatan_id'] ?? null, $data['desa_id'] ?? null];
        if (in_array(null, $ids, true)) {
            return ['wilayah' => $data['wilayah'] ?? null];
        }

        try {
            $resolved = $this->proposalService->resolveWilayah((int) $ids[0], (int) $ids[1], (int) $ids[2]);
            if ($resolved['ok']) {
                return ['wilayah' => (string) $resolved['wilayah']];
            }
        } catch (Throwable $e) {
        }

        return ['wilayah' => $data['wilayah'] ?? null];
    }

    private function notifySubmitted(int $ownerId, int $proposalId): void
    {
        $this->proposalService->notifySubmittedByOwner($ownerId, $proposalId);
    }

    private function setLegacyFotoUrl(int $proposalId, string $path): void
    {
        $stmt = Database::getInstance()->getConnection()->prepare(
            'UPDATE usulan_opt SET foto_url = ? WHERE id = ?'
        );
        $stmt->execute([$path, $proposalId]);
    }

    private function writePhotoAudit(int $actorId, int $count, int $proposalId): void
    {
        if ($count <= 0) {
            return;
        }
        $this->writeSimpleAudit($actorId, 'upload_photo', $proposalId, sprintf('%d foto usulan diunggah.', $count));
    }

    private function writeSimpleAudit(int $actorId, string $action, int $recordId, string $description): void
    {
        try {
            $stmt = Database::getInstance()->getConnection()->prepare(
                'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $actorId,
                $action,
                'usulan_opt',
                $recordId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('UsulanOptController audit failed');
        }
    }

    private function rememberWilayahNames(array $input): void
    {
        $ids = [$input['kabupaten_id'] ?? null, $input['kecamatan_id'] ?? null, $input['desa_id'] ?? null];
        if (in_array(null, $ids, true) || in_array('', $ids, true)) {
            return;
        }
        try {
            $resolved = $this->proposalService->resolveWilayahForProposal(
                (int) $ids[0], (int) $ids[1], (int) $ids[2]
            );
            $_SESSION['form_wilayah_names'] = $resolved['names'] ?? [];
        } catch (Throwable $e) {
            $_SESSION['form_wilayah_names'] = [];
        }
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private function flattenProposalForForm(array $proposal): array
    {
        return [
            'nama_nasional' => $proposal['nama_nasional'],
            'nama_lokal' => $proposal['nama_lokal'],
            'jenis' => $proposal['jenis'],
            'komoditas' => $proposal['komoditas'],
            'ciri_ciri' => $proposal['ciri_ciri'],
            'tanggal_ditemukan' => $proposal['tanggal_ditemukan'],
            'kabupaten_id' => $proposal['kabupaten_id'],
            'kecamatan_id' => $proposal['kecamatan_id'],
            'desa_id' => $proposal['desa_id'],
            'alamat_lokasi' => $proposal['alamat_lokasi'],
            'latitude' => $proposal['latitude'],
            'longitude' => $proposal['longitude'],
            'bagian_terserang' => $proposal['bagian_terserang'],
            'pola_gejala' => $proposal['pola_gejala'],
            'estimasi_terdampak' => $proposal['estimasi_terdampak'],
            'satuan_terdampak' => $proposal['satuan_terdampak'],
            'tingkat_keyakinan' => $proposal['tingkat_keyakinan'],
            'sumber_identifikasi' => $proposal['sumber_identifikasi'],
            'wilayah' => $proposal['wilayah'],
        ];
    }

    /**
     * @return array{status?:string,jenis?:string,q?:string,date_from?:string,date_to?:string}
     */
    private function collectFilters(): array
    {
        $filters = [];

        $status = trim((string) ($_GET['status'] ?? ''));
        if ($status !== '' && in_array($status, UsulanOpt::STATUSES, true)) {
            $filters['status'] = $status;
        }

        $jenis = strtolower(trim((string) ($_GET['jenis'] ?? '')));
        if ($jenis !== '' && in_array($jenis, MasterOptService::JENIS, true)) {
            $filters['jenis'] = $jenis;
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $filters['q'] = mb_substr($q, 0, 100);
        }

        foreach (['date_from', 'date_to'] as $key) {
            $value = trim((string) ($_GET[$key] ?? ''));
            $dt = DateTime::createFromFormat('Y-m-d', $value);
            if ($value !== '' && $dt && $dt->format('Y-m-d') === $value) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    private function excelUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file impor melebihi batas server (maksimal aplikasi 10 MB).',
            UPLOAD_ERR_PARTIAL => 'Unggahan file impor tidak lengkap. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE => 'Pilih file Excel yang akan diimpor.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori sementara unggahan tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file unggahan sementara.',
            UPLOAD_ERR_EXTENSION => 'Unggahan diblokir oleh ekstensi server.',
            default => 'Gagal mengunggah file Excel.',
        };
    }

    private function sendExcelDownload(string $path, string $filename): never
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        @unlink($path);
        exit;
    }

    private function flashOwnerActionFailure(string $reason, int $proposalId): void
    {
        switch ($reason) {
            case UsulanOptService::REASON_NOT_FOUND:
                $_SESSION['error'] = 'Usulan tidak ditemukan.';
                break;
            case UsulanOptService::REASON_FORBIDDEN:
                $_SESSION['error'] = 'Anda tidak memiliki akses ke usulan ini.';
                break;
            case UsulanOptService::REASON_STATUS_CONFLICT:
                $_SESSION['info'] = 'Status usulan baru saja berubah. Muat ulang halaman untuk melihat keadaan terbaru.';
                break;
            default:
                $_SESSION['error'] = 'Aksi gagal diproses.';
        }
        $this->redirect($proposalId > 0 ? 'usulan-opt/detail/' . $proposalId : 'usulan-opt');
    }

    private function flashReviewResult(array $result, string $name, string $verbPast, int $proposalId): void
    {
        if (($result['ok'] ?? false) === true) {
            $_SESSION['success'] = "Usulan \"{$name}\" berhasil {$verbPast}.";
            $this->invalidateStatsCache(['stats_']);
            $this->redirect('usulan-opt/detail/' . $proposalId);
        }

        switch ((string) ($result['reason'] ?? '')) {
            case UsulanOptReviewService::REASON_ALREADY_REVIEWED:
                $_SESSION['info'] = "Usulan \"{$name}\" sudah direview sebelumnya.";
                break;
            case UsulanOptReviewService::REASON_NOT_FOUND:
                $_SESSION['error'] = 'Usulan tidak ditemukan.';
                break;
            case UsulanOptReviewService::REASON_MASTER_INVALID:
                $_SESSION['error'] = 'Master OPT tujuan tidak ditemukan atau tidak aktif.';
                break;
            case UsulanOptReviewService::REASON_JENIS_MISMATCH:
                $_SESSION['error'] = 'Jenis usulan berbeda dengan jenis master tujuan. Pilih master lain atau minta perbaikan/tolak permanen.';
                break;
            default:
                $_SESSION['error'] = 'Aksi review gagal diproses.';
        }

        $this->redirect('usulan-opt');
    }

    private function flashDecisionFailure(string $reason): void
    {
        if ($reason === UsulanOptReviewService::REASON_ALREADY_REVIEWED) {
            $_SESSION['info'] = 'Keputusan lain sudah lebih dulu diproses untuk usulan ini.';
        } else {
            $_SESSION['error'] = 'Aksi review gagal diproses.';
        }
        $this->redirect('usulan-opt');
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private function buildPrefill(array $proposal): array
    {
        $session = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);

        $defaults = [
            'kode_opt' => '',
            'nama_opt' => $proposal['nama_nasional'] ?: $proposal['nama_lokal'],
            'nama_ilmiah' => '',
            'nama_lokal' => $proposal['nama_lokal'] ?: '',
            'jenis' => $proposal['jenis'],
            'kategori' => '',
            'status_karantina' => 'Tidak',
            'tingkat_bahaya' => 'Sedang',
            'kingdom' => '',
            'filum' => '',
            'kelas' => '',
            'ordo' => '',
            'famili' => '',
            'genus' => '',
            'etl_acuan' => '',
            'satuan_etl' => '%',
            'deskripsi' => $proposal['ciri_ciri'] ?: '',
            'rekomendasi' => '',
            'referensi' => '',
            'foto_url' => $proposal['foto_url'] ?: '',
            'aktif' => 1,
        ];

        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $session)) {
                $defaults[$key] = $session[$key];
            }
        }

        return $defaults;
    }

    /** @param array<string,mixed> $proposal */
    private function bulkMasterData(array $proposal): array
    {
        return $this->masterService->normalize([
            'kode_opt' => '',
            'nama_opt' => $proposal['nama_nasional'] ?: $proposal['nama_lokal'],
            'nama_lokal' => $proposal['nama_lokal'] ?? '',
            'jenis' => $proposal['jenis'],
            'status_karantina' => 'Tidak',
            'tingkat_bahaya' => 'Sedang',
            'satuan_etl' => '%',
            'foto_url' => $proposal['foto_url'] ?? '',
            'deskripsi' => $proposal['ciri_ciri'] ?? '',
            'referensi' => $proposal['sumber_identifikasi'] ?? '',
            'aktif' => 1,
        ]);
    }
}
