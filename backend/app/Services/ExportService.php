<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\CsvWriter;
use App\Helpers\XlsxWriter;
use App\Models\ActivityLog;
use PDO;

class ExportService
{
    private const MAX_ROWS = 10000;
    private const MAX_DATE_RANGE_DAYS = 366;
    private const VALID_STATUSES = ['Draf', 'Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'];
    private const VALID_FORMATS = ['csv', 'xlsx'];

    private PDO $db;
    private string $role;
    private ?int $userId;
    private bool $includeDraft = false;

    public function __construct(string $role, ?int $userId, bool $includeDraft = false)
    {
        $this->db = Database::connect();
        $this->role = $role;
        $this->userId = $role === 'petugas' ? $userId : null;
        $this->includeDraft = $includeDraft;
    }

    public static function validateFiltersStatic(array $input): array
    {
        $errors = [];

        $includeDraft = $input['include_draft'] ?? null;
        if ($includeDraft !== null && $includeDraft !== '' && !in_array($includeDraft, ['true', 'false', '1', '0'], true)) {
            $errors['include_draft'] = 'include_draft harus true atau false.';
        }

        $format = strtolower(trim($input['format'] ?? ''));
        if (!in_array($format, self::VALID_FORMATS, true)) {
            $errors['format'] = 'Format harus csv atau xlsx.';
        }

        $status = $input['status'] ?? null;
        if ($status !== null && $status !== '') {
            $statuses = explode(',', $status);
            foreach ($statuses as $s) {
                $s = trim($s);
                if (!in_array($s, self::VALID_STATUSES, true)) {
                    $errors['status'] = 'Status tidak valid: ' . $s;
                    break;
                }
            }
        }

        $tanggalDari = $input['tanggal_dari'] ?? null;
        $tanggalSampai = $input['tanggal_sampai'] ?? null;

        if ($tanggalDari !== null && $tanggalDari !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDari)) {
                $errors['tanggal_dari'] = 'Format tanggal_dari harus YYYY-MM-DD.';
            }
        }

        if ($tanggalSampai !== null && $tanggalSampai !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSampai)) {
                $errors['tanggal_sampai'] = 'Format tanggal_sampai harus YYYY-MM-DD.';
            }
        }

        if ($tanggalDari && $tanggalSampai && !isset($errors['tanggal_dari']) && !isset($errors['tanggal_sampai'])) {
            if ($tanggalDari > $tanggalSampai) {
                $errors['tanggal_sampai'] = 'tanggal_sampai harus >= tanggal_dari.';
            } else {
                $diff = (strtotime($tanggalSampai) - strtotime($tanggalDari)) / 86400;
                if ($diff > self::MAX_DATE_RANGE_DAYS) {
                    $errors['tanggal_sampai'] = 'Rentang tanggal maksimal ' . self::MAX_DATE_RANGE_DAYS . ' hari.';
                }
            }
        }

        foreach (['kabupaten_id', 'kecamatan_id', 'desa_id'] as $field) {
            $val = $input[$field] ?? null;
            if ($val !== null && $val !== '') {
                if (!ctype_digit((string) $val) || (int) $val <= 0) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' harus bilangan positif.';
                }
            }
        }

        return $errors;
    }

    public function countHama(array $filters): int
    {
        return $this->countQuery('laporan_hama', 'lh', $filters);
    }

    public function countIrigasi(array $filters): int
    {
        return $this->countQuery('laporan_irigasi', 'li', $filters);
    }

    public function exportHama(string $format, array $filters): void
    {
        $count = $this->countHama($filters);
        if ($count > self::MAX_ROWS) {
            throw new \DomainException('Data terlalu banyak (' . $count . ' baris). Maksimal ' . self::MAX_ROWS . ' baris. Perketat filter.');
        }

        $rows = $this->fetchHamaData($filters);
        $headers = [
            'Nomor Laporan', 'Tanggal', 'Status', 'Nama Petugas',
            'Nama OPT', 'Jenis OPT', 'Tingkat Keparahan', 'Luas Serangan',
            'Populasi', 'Kabupaten', 'Kecamatan', 'Desa',
            'Lokasi', 'Alamat Lengkap', 'Latitude', 'Longitude',
            'Catatan', 'Diverifikasi Oleh', 'Tanggal Verifikasi',
            'Catatan Verifikasi', 'Dibuat Pada', 'Diperbarui Pada',
        ];

        $this->streamExport($format, $headers, $rows, 'laporan-hama');
    }

    public function exportIrigasi(string $format, array $filters): void
    {
        $count = $this->countIrigasi($filters);
        if ($count > self::MAX_ROWS) {
            throw new \DomainException('Data terlalu banyak (' . $count . ' baris). Maksimal ' . self::MAX_ROWS . ' baris. Perketat filter.');
        }

        $rows = $this->fetchIrigasiData($filters);
        $headers = [
            'Nomor Laporan', 'Tanggal', 'Status', 'Nama Petugas',
            'Nama Saluran', 'Daerah Irigasi', 'Kondisi Fisik', 'Debit Air',
            'Kabupaten', 'Kecamatan', 'Desa',
            'Latitude', 'Longitude',
            'Catatan', 'Diverifikasi Oleh', 'Tanggal Verifikasi',
            'Catatan Verifikasi', 'Dibuat Pada', 'Diperbarui Pada',
        ];

        $this->streamExport($format, $headers, $rows, 'laporan-irigasi');
    }

    private function streamExport(string $format, array $headers, array $rows, string $prefix): void
    {
        $timestamp = date('Ymd-His');
        $filename = "{$prefix}-{$timestamp}.{$format}";

        if ($format === 'csv') {
            $this->streamCsv($filename, $headers, $rows);
        } else {
            $this->streamXlsx($filename, $headers, $rows);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $logAction = $prefix === 'laporan-hama' ? 'export_hama' : 'export_irigasi';
        $userId = $this->userId ?? (isset($GLOBALS['auth_user']['id']) ? (int) $GLOBALS['auth_user']['id'] : (int) ($_SESSION['user_id'] ?? 0));
        ActivityLog::log(
            $userId,
            $logAction,
            $prefix === 'laporan-hama' ? 'laporan_hama' : 'laporan_irigasi',
            null,
            "Ekspor {$prefix} format {$format}: {$filename} (" . count($rows) . " baris)",
            $ip,
            $userAgent
        );
    }

    private function streamCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $writer = new CsvWriter();
        $writer->open();
        $writer->writeRow($headers);
        foreach ($rows as $row) {
            $writer->writeRow($row);
        }
        $writer->close();
    }

    private function streamXlsx(string $filename, array $headers, array $rows): void
    {
        $tmpDir = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpFile = $tmpDir . '/export_' . bin2hex(random_bytes(8)) . '.xlsx';

        try {
            $xlsx = new XlsxWriter($tmpFile);
            $xlsx->setHeaders($headers);
            foreach ($rows as $row) {
                $xlsx->addRow($row);
            }
            $xlsx->save();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function countQuery(string $table, string $alias, array $filters): int
    {
        $sql = "SELECT COUNT(*) FROM `{$table}` {$alias}";
        $conditions = [];
        $params = [];

        $this->applyRoleScope($table, $alias, $conditions, $params);
        $this->applyDateFilter($alias, $filters, $conditions, $params);
        $this->applyStatusFilter($alias, $filters, $conditions, $params);
        $this->applyWilayahFilter($alias, $filters, $conditions, $params);

        if (count($conditions) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function fetchHamaData(array $filters): array
    {
        $sql = "SELECT lh.nomor_laporan, lh.tanggal, lh.status,
                       u.nama_lengkap AS nama_petugas,
                       mo.nama_opt, mo.jenis AS jenis_opt,
                       lh.tingkat_keparahan, lh.luas_serangan, lh.populasi,
                       mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa,
                       lh.lokasi, lh.alamat_lengkap, lh.latitude, lh.longitude,
                       lh.catatan,
                       v.nama_lengkap AS verified_by_nama,
                       lh.verified_at, lh.catatan_verifikasi,
                       lh.created_at, lh.updated_at
                FROM `laporan_hama` lh
                LEFT JOIN `users` u ON u.id = lh.user_id
                LEFT JOIN `users` v ON v.id = lh.verified_by
                LEFT JOIN `master_opt` mo ON mo.id = lh.master_opt_id
                LEFT JOIN `master_kabupaten` mk ON mk.id = lh.kabupaten_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = lh.kecamatan_id
                LEFT JOIN `master_desa` md ON md.id = lh.desa_id";

        $conditions = [];
        $params = [];

        $this->applyRoleScope('laporan_hama', 'lh', $conditions, $params);
        $this->applyDateFilter('lh', $filters, $conditions, $params);
        $this->applyStatusFilter('lh', $filters, $conditions, $params);
        $this->applyWilayahFilter('lh', $filters, $conditions, $params);

        if (count($conditions) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY lh.tanggal DESC, lh.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                $row['nomor_laporan'] ?? '',
                $row['tanggal'] ?? '',
                $row['status'] ?? '',
                $row['nama_petugas'] ?? '',
                $row['nama_opt'] ?? '',
                $row['jenis_opt'] ?? '',
                $row['tingkat_keparahan'] ?? '',
                $row['luas_serangan'] !== null ? (string) $row['luas_serangan'] : '',
                $row['populasi'] !== null ? (string) $row['populasi'] : '',
                $row['nama_kabupaten'] ?? '',
                $row['nama_kecamatan'] ?? '',
                $row['nama_desa'] ?? '',
                $row['lokasi'] ?? '',
                $row['alamat_lengkap'] ?? '',
                $row['latitude'] !== null ? (string) $row['latitude'] : '',
                $row['longitude'] !== null ? (string) $row['longitude'] : '',
                $row['catatan'] ?? '',
                $row['verified_by_nama'] ?? '',
                $row['verified_at'] ?? '',
                $row['catatan_verifikasi'] ?? '',
                $row['created_at'] ?? '',
                $row['updated_at'] ?? '',
            ];
        }

        return $rows;
    }

    private function fetchIrigasiData(array $filters): array
    {
        $sql = "SELECT li.nomor_laporan, li.tanggal, li.status,
                       u.nama_lengkap AS nama_petugas,
                       li.nama_saluran, li.daerah_irigasi,
                       li.kondisi_fisik, li.debit_air,
                       mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa,
                       li.latitude, li.longitude,
                       li.catatan,
                       v.nama_lengkap AS verified_by_nama,
                       li.verified_at, li.catatan_verifikasi,
                       li.created_at, li.updated_at
                FROM `laporan_irigasi` li
                LEFT JOIN `users` u ON u.id = li.user_id
                LEFT JOIN `users` v ON v.id = li.verified_by
                LEFT JOIN `master_kabupaten` mk ON mk.id = li.kabupaten_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = li.kecamatan_id
                LEFT JOIN `master_desa` md ON md.id = li.desa_id";

        $conditions = [];
        $params = [];

        $this->applyRoleScope('laporan_irigasi', 'li', $conditions, $params);
        $this->applyDateFilter('li', $filters, $conditions, $params);
        $this->applyStatusFilter('li', $filters, $conditions, $params);
        $this->applyWilayahFilter('li', $filters, $conditions, $params);

        if (count($conditions) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY li.tanggal DESC, li.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                $row['nomor_laporan'] ?? '',
                $row['tanggal'] ?? '',
                $row['status'] ?? '',
                $row['nama_petugas'] ?? '',
                $row['nama_saluran'] ?? '',
                $row['daerah_irigasi'] ?? '',
                $row['kondisi_fisik'] ?? '',
                $row['debit_air'] ?? '',
                $row['nama_kabupaten'] ?? '',
                $row['nama_kecamatan'] ?? '',
                $row['nama_desa'] ?? '',
                $row['latitude'] !== null ? (string) $row['latitude'] : '',
                $row['longitude'] !== null ? (string) $row['longitude'] : '',
                $row['catatan'] ?? '',
                $row['verified_by_nama'] ?? '',
                $row['verified_at'] ?? '',
                $row['catatan_verifikasi'] ?? '',
                $row['created_at'] ?? '',
                $row['updated_at'] ?? '',
            ];
        }

        return $rows;
    }

    private function applyRoleScope(string $table, string $alias, array &$conditions, array &$params): void
    {
        if ($this->userId !== null) {
            $conditions[] = "{$alias}.user_id = ?";
            $params[] = $this->userId;
        }
    }

    private function applyDateFilter(string $alias, array $filters, array &$conditions, array &$params): void
    {
        $tanggalDari = $filters['tanggal_dari'] ?? null;
        $tanggalSampai = $filters['tanggal_sampai'] ?? null;

        if ($tanggalDari !== null && $tanggalDari !== '') {
            $conditions[] = "{$alias}.tanggal >= ?";
            $params[] = $tanggalDari;
        }
        if ($tanggalSampai !== null && $tanggalSampai !== '') {
            $conditions[] = "{$alias}.tanggal <= ?";
            $params[] = $tanggalSampai;
        }
    }

    private function applyStatusFilter(string $alias, array $filters, array &$conditions, array &$params): void
    {
        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $statuses = explode(',', $status);
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $conditions[] = "{$alias}.status IN ({$placeholders})";
            foreach ($statuses as $s) {
                $params[] = trim($s);
            }
        } elseif (!$this->includeDraft) {
            $conditions[] = "{$alias}.status IN ('Submitted','Diverifikasi')";
        }
    }

    private function applyWilayahFilter(string $alias, array $filters, array &$conditions, array &$params): void
    {
        foreach (['kabupaten_id', 'kecamatan_id', 'desa_id'] as $field) {
            $val = $filters[$field] ?? null;
            if ($val !== null && $val !== '') {
                $conditions[] = "{$alias}.{$field} = ?";
                $params[] = (int) $val;
            }
        }
    }
}
