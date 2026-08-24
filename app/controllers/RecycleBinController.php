<?php

declare(strict_types=1);

class RecycleBinController extends Controller
{
    private const MODULES = [
        'usulan-opt' => ['table' => 'usulan_opt', 'name' => 'Usulan OPT', 'icon' => 'fa-clipboard-check', 'label' => "COALESCE(NULLIF(t.nama_nasional, ''), t.nama_lokal)"],
        'laporan' => ['table' => 'laporan_hama', 'name' => 'Laporan Hama', 'icon' => 'fa-bug', 'label' => "COALESCE(NULLIF(t.nomor_laporan, ''), t.lokasi)"],
        'irigasi' => ['table' => 'laporan_irigasi', 'name' => 'Laporan Irigasi', 'icon' => 'fa-water', 'label' => "COALESCE(NULLIF(t.nomor_laporan, ''), CONCAT('Irigasi #', t.id))"],
        'laporan-lainnya' => ['table' => 'laporan_lainnya', 'name' => 'Laporan Lainnya', 'icon' => 'fa-clipboard-list', 'label' => "COALESCE(NULLIF(t.kode_laporan, ''), t.deskripsi)"],
    ];

    public function index(): void
    {
        $this->checkAuth();
        $this->checkRole(['admin']);
        $db = Database::getInstance()->getConnection();
        $rows = [];
        $moduleFilter = trim((string) ($_GET['module'] ?? ''));
        if ($moduleFilter !== '' && !isset(self::MODULES[$moduleFilter])) {
            $moduleFilter = '';
        }
        $search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $allowedPerPage = [10, 20, 50, 100];
        $perPage = (int) ($_GET['per_page'] ?? 20);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }
        $stats = array_fill_keys(array_keys(self::MODULES), 0);
        foreach (self::MODULES as $module => $definition) {
            $stats[$module] = (int) $db->query(
                "SELECT COUNT(*) FROM `{$definition['table']}` WHERE deleted_at IS NOT NULL"
            )->fetchColumn();
        }

        $selectedModules = $moduleFilter !== ''
            ? [$moduleFilter => self::MODULES[$moduleFilter]]
            : self::MODULES;
        $parts = [];
        foreach ($selectedModules as $module => $definition) {
            $moduleSql = $db->quote($module);
            $moduleNameSql = $db->quote($definition['name']);
            $moduleIconSql = $db->quote($definition['icon']);
            $parts[] = "SELECT t.id, {$definition['label']} AS label, t.deleted_at, "
                . "u.nama_lengkap AS deleted_by_name, {$moduleSql} AS module, "
                . "{$moduleNameSql} AS module_name, {$moduleIconSql} AS module_icon "
                . "FROM `{$definition['table']}` t "
                . 'LEFT JOIN users u ON u.id = t.deleted_by '
                . 'WHERE t.deleted_at IS NOT NULL';
        }

        $unionSql = implode(' UNION ALL ', $parts);
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE COALESCE(label, \'\') LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }
        $countStmt = $db->prepare("SELECT COUNT(*) FROM ({$unionSql}) recycle_rows{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $dataStmt = $db->prepare(
            "SELECT * FROM ({$unionSql}) recycle_rows{$where} "
            . 'ORDER BY deleted_at DESC, module ASC, id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $dataStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        $this->view('recycle-bin/index', [
            'title' => 'Recycle Bin',
            'items' => $rows,
            'modules' => self::MODULES,
            'stats' => $stats,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'moduleFilter' => $moduleFilter,
            'search' => $search,
        ]);
    }

    public function restore(): void
    {
        $this->checkAuth();
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $module = (string) ($_POST['module'] ?? '');
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0 || !isset(self::MODULES[$module])) {
            $_SESSION['error'] = 'Data recycle bin tidak valid.';
            $this->redirect('recycle-bin');
        }

        $table = self::MODULES[$module]['table'];
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $stmt = $db->prepare(
                "UPDATE `{$table}` SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND deleted_at IS NOT NULL"
            );
            $stmt->execute([(int) $id]);

            if ($stmt->rowCount() !== 1) {
                $db->rollBack();
                $_SESSION['info'] = 'Data sudah dipulihkan atau tidak ditemukan.';
                $this->redirect('recycle-bin');
            }

            $audit = $db->prepare(
                'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $audit->execute([
                (int) $_SESSION['user_id'],
                'restore',
                $table,
                (int) $id,
                'Data dipulihkan dari recycle bin.',
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]);
            $db->commit();
            $this->clearReportCaches();
            $_SESSION['success'] = 'Data berhasil dipulihkan.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('RecycleBinController::restore failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Data gagal dipulihkan. Silakan coba kembali.';
        }

        $this->redirect('recycle-bin');
    }

    public function bulkRestore(): void
    {
        $this->checkAuth();
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $restoreAll = ($_POST['restore_all'] ?? '') === '1';
        $moduleFilter = trim((string) ($_POST['module'] ?? ''));
        if ($moduleFilter !== '' && !isset(self::MODULES[$moduleFilter])) {
            $_SESSION['error'] = 'Modul recycle bin tidak valid.';
            $this->redirect('recycle-bin');
        }

        $groups = [];
        if ($restoreAll) {
            $definitions = $moduleFilter !== ''
                ? [$moduleFilter => self::MODULES[$moduleFilter]]
                : self::MODULES;
            foreach ($definitions as $module => $definition) {
                $groups[$module] = null;
            }
        } else {
            $items = $_POST['items'] ?? [];
            if (!is_array($items) || $items === [] || count($items) > 5000) {
                $_SESSION['error'] = 'Pilih minimal satu data yang akan dipulihkan.';
                $this->redirect('recycle-bin');
            }
            foreach ($items as $item) {
                if (!is_string($item) || !preg_match('/^([a-z-]+):(\d+)$/', $item, $matches)) {
                    $_SESSION['error'] = 'Pilihan data recycle bin tidak valid.';
                    $this->redirect('recycle-bin');
                }
                $module = $matches[1];
                $id = (int) $matches[2];
                if ($id <= 0 || !isset(self::MODULES[$module])) {
                    $_SESSION['error'] = 'Pilihan data recycle bin tidak valid.';
                    $this->redirect('recycle-bin');
                }
                $groups[$module][$id] = $id;
            }
        }

        $db = Database::getInstance()->getConnection();
        $restored = 0;
        try {
            $db->beginTransaction();
            $audit = $db->prepare(
                'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($groups as $module => $ids) {
                $table = self::MODULES[$module]['table'];
                if ($ids === null) {
                    $select = $db->query("SELECT id FROM `{$table}` WHERE deleted_at IS NOT NULL");
                    $targetIds = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));
                } else {
                    $targetIds = array_values($ids);
                }
                if ($targetIds === []) {
                    continue;
                }
                $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
                $update = $db->prepare(
                    "UPDATE `{$table}` SET deleted_at = NULL, deleted_by = NULL "
                    . "WHERE deleted_at IS NOT NULL AND id IN ({$placeholders})"
                );
                $update->execute($targetIds);
                $restored += $update->rowCount();

                foreach ($targetIds as $id) {
                    $audit->execute([
                        (int) $_SESSION['user_id'],
                        'bulk_restore',
                        $table,
                        $id,
                        'Data dipulihkan secara massal dari recycle bin.',
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                    ]);
                }
            }
            $db->commit();
            if ($restored > 0) {
                $this->clearReportCaches();
            }
            $_SESSION[$restored > 0 ? 'success' : 'info'] = $restored > 0
                ? "{$restored} data berhasil dipulihkan."
                : 'Tidak ada data yang perlu dipulihkan.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('RecycleBinController::bulkRestore failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Data gagal dipulihkan secara massal.';
        }

        $redirect = 'recycle-bin' . ($moduleFilter !== '' ? '?module=' . rawurlencode($moduleFilter) : '');
        $this->redirect($redirect);
    }

    /**
     * Permanently delete selected recycle-bin rows or all rows in a module.
     */
    public function bulkDelete(): void
    {
        $this->checkAuth();
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $deleteAll = ($_POST['delete_all'] ?? '') === '1';
        $moduleFilter = trim((string) ($_POST['module'] ?? ''));
        if ($moduleFilter !== '' && !isset(self::MODULES[$moduleFilter])) {
            $_SESSION['error'] = 'Modul recycle bin tidak valid.';
            $this->redirect('recycle-bin');
        }

        $groups = [];
        if ($deleteAll) {
            $definitions = $moduleFilter !== ''
                ? [$moduleFilter => self::MODULES[$moduleFilter]]
                : self::MODULES;
            foreach ($definitions as $module => $definition) {
                $groups[$module] = null;
            }
        } else {
            $items = $_POST['items'] ?? [];
            if (!is_array($items) || $items === [] || count($items) > 5000) {
                $_SESSION['error'] = 'Pilih minimal satu data yang akan dihapus permanen.';
                $this->redirect('recycle-bin');
            }
            foreach ($items as $item) {
                if (!is_string($item) || !preg_match('/^([a-z-]+):(\d+)$/', $item, $matches)) {
                    $_SESSION['error'] = 'Pilihan data recycle bin tidak valid.';
                    $this->redirect('recycle-bin');
                }
                $module = $matches[1];
                $id = (int) $matches[2];
                if ($id <= 0 || !isset(self::MODULES[$module])) {
                    $_SESSION['error'] = 'Pilihan data recycle bin tidak valid.';
                    $this->redirect('recycle-bin');
                }
                $groups[$module][$id] = $id;
            }
        }

        $db = Database::getInstance()->getConnection();
        $deleted = 0;
        $storedFiles = [];
        try {
            $db->beginTransaction();
            $audit = $db->prepare(
                'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($groups as $module => $ids) {
                $table = self::MODULES[$module]['table'];
                if ($ids === null) {
                    $select = $db->query("SELECT id FROM `{$table}` WHERE deleted_at IS NOT NULL");
                    $targetIds = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));
                } else {
                    $targetIds = array_values($ids);
                }

                foreach (array_chunk($targetIds, 500) as $chunk) {
                    if ($chunk === []) {
                        continue;
                    }
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $lock = $db->prepare(
                        "SELECT id FROM `{$table}` WHERE deleted_at IS NOT NULL AND id IN ({$placeholders}) FOR UPDATE"
                    );
                    $lock->execute($chunk);
                    $chunk = array_map('intval', $lock->fetchAll(PDO::FETCH_COLUMN));
                    if ($chunk === []) {
                        continue;
                    }
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $storedFiles = array_merge(
                        $storedFiles,
                        $this->collectStoredFiles($db, $module, $table, $chunk, $placeholders)
                    );
                    $delete = $db->prepare(
                        "DELETE FROM `{$table}` WHERE deleted_at IS NOT NULL AND id IN ({$placeholders})"
                    );
                    $delete->execute($chunk);
                    $deleted += $delete->rowCount();

                    foreach ($chunk as $id) {
                        $audit->execute([
                            (int) $_SESSION['user_id'],
                            'permanent_delete',
                            $table,
                            $id,
                            'Data dihapus permanen dari recycle bin.',
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                        ]);
                    }
                }
            }

            $db->commit();
            foreach (array_unique(array_filter($storedFiles)) as $storedFile) {
                $this->deleteStoredFile((string) $storedFile);
            }
            if ($deleted > 0) {
                $this->clearReportCaches();
            }
            $_SESSION[$deleted > 0 ? 'success' : 'info'] = $deleted > 0
                ? "{$deleted} data berhasil dihapus permanen."
                : 'Tidak ada data yang perlu dihapus.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('RecycleBinController::bulkDelete failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Data gagal dihapus permanen dari recycle bin.';
        }

        $redirect = 'recycle-bin' . ($moduleFilter !== '' ? '?module=' . rawurlencode($moduleFilter) : '');
        $this->redirect($redirect);
    }

    private function collectStoredFiles(PDO $db, string $module, string $table, array $ids, string $placeholders): array
    {
        $columns = [
            'usulan-opt' => ['foto_url'],
            'laporan' => ['foto_url', 'video_url'],
            'irigasi' => ['foto_url'],
            'laporan-lainnya' => ['foto_url'],
        ][$module] ?? [];
        $files = [];
        if ($columns !== []) {
            $select = $db->prepare(
                'SELECT ' . implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns))
                . " FROM `{$table}` WHERE deleted_at IS NOT NULL AND id IN ({$placeholders})"
            );
            $select->execute($ids);
            foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $row) {
                foreach ($columns as $column) {
                    if (!empty($row[$column])) {
                        $files[] = $row[$column];
                    }
                }
            }
        }

        if ($module === 'usulan-opt') {
            $photos = $db->prepare(
                "SELECT file_path FROM usulan_opt_photos WHERE usulan_opt_id IN ({$placeholders})"
            );
            $photos->execute($ids);
            $files = array_merge($files, $photos->fetchAll(PDO::FETCH_COLUMN));
        }
        return $files;
    }

    private function deleteStoredFile(string $storedPath): void
    {
        if ($storedPath === '' || filter_var($storedPath, FILTER_VALIDATE_URL)) {
            return;
        }
        $relativePath = ltrim(str_replace('\\', '/', $storedPath), '/');
        $candidate = realpath(ROOT_PATH . '/' . $relativePath);
        if ($candidate === false || !is_file($candidate)) {
            return;
        }
        foreach ([ROOT_PATH . '/public/uploads', ROOT_PATH . '/data'] as $allowedDirectory) {
            $allowedRoot = realpath($allowedDirectory);
            if ($allowedRoot !== false && str_starts_with($candidate, $allowedRoot . DIRECTORY_SEPARATOR)) {
                @unlink($candidate);
                return;
            }
        }
    }

    private function clearReportCaches(): void
    {
        $this->invalidateStatsCache(['dashboard:', 'stats_', 'map_', 'export_']);
        if (class_exists('DashboardDataAggregator')) {
            $aggregator = new DashboardDataAggregator();
            $aggregator->clearCache('hama');
            $aggregator->clearCache('irrigation');
            $aggregator->clearCache('lainnya');
        }
    }
}
