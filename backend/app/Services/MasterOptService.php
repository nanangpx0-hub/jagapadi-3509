<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\MasterOpt;
use App\Core\Request;

class MasterOptService
{
    public static function create(array $data, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $existing = MasterOpt::findByNama($data['nama_opt']);
            if ($existing !== null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Nama OPT sudah digunakan.', 'code' => 409];
            }

            $insertData = [
                'nama_opt' => $data['nama_opt'],
                'jenis' => $data['jenis'],
            ];

            if (isset($data['etl_acuan']) && $data['etl_acuan'] !== '') {
                $insertData['etl_acuan'] = $data['etl_acuan'];
            }
            if (isset($data['satuan_etl']) && $data['satuan_etl'] !== '') {
                $insertData['satuan_etl'] = $data['satuan_etl'];
            }
            if (isset($data['foto_url']) && $data['foto_url'] !== '') {
                $insertData['foto_url'] = $data['foto_url'];
            }
            if (isset($data['deskripsi']) && $data['deskripsi'] !== '') {
                $insertData['deskripsi'] = $data['deskripsi'];
            }
            $insertData['aktif'] = (int) ($data['aktif'] ?? 1);

            $id = MasterOpt::insert($insertData);

            ActivityLog::log($adminId, 'opt_created', 'master_opt', (int) $id, 'OPT dibuat: ' . $data['nama_opt']);

            $pdo->commit();

            return ['success' => true, 'data' => MasterOpt::find((int) $id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $opt = MasterOpt::find($id);
            if ($opt === null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'OPT tidak ditemukan.', 'code' => 404];
            }

            if (isset($data['nama_opt'])) {
                $existing = MasterOpt::findByNama($data['nama_opt']);
                if ($existing !== null && (int) $existing['id'] !== $id) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Nama OPT sudah digunakan.', 'code' => 409];
                }
            }

            $updateData = [];
            foreach (['nama_opt', 'jenis', 'etl_acuan', 'satuan_etl', 'foto_url', 'deskripsi'] as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            if (isset($data['aktif'])) {
                $updateData['aktif'] = (int) $data['aktif'];
            }

            if (count($updateData) > 0) {
                MasterOpt::update($id, $updateData);
            }

            ActivityLog::log($adminId, 'opt_updated', 'master_opt', $id, 'OPT diperbarui: ' . ($data['nama_opt'] ?? $opt['nama_opt']));

            $pdo->commit();

            return ['success' => true, 'data' => MasterOpt::find($id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $opt = MasterOpt::find($id);
            if ($opt === null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'OPT tidak ditemukan.', 'code' => 404];
            }

            try {
                MasterOpt::delete($id);
            } catch (\Throwable $e) {
                $pdo->rollBack();
                if ($e->getCode() === '23000') {
                    MasterOpt::update($id, ['aktif' => 0]);
                    ActivityLog::log($adminId, 'opt_deactivated', 'master_opt', $id, 'OPT dinonaktifkan (soft): ' . $opt['nama_opt']);
                    $pdo->commit();
                    return ['success' => true, 'message' => 'OPT tidak bisa dihapus langsung karena masih digunakan. Status dinonaktifkan.', 'data' => MasterOpt::find($id)];
                }
                throw $e;
            }

            ActivityLog::log($adminId, 'opt_deleted', 'master_opt', $id, 'OPT dihapus: ' . $opt['nama_opt']);
            $pdo->commit();

            return ['success' => true];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (!$isUpdate || isset($data['nama_opt'])) {
            $nama = trim($data['nama_opt'] ?? '');
            if ($nama === '') {
                $errors['nama_opt'] = 'Nama OPT wajib diisi.';
            } elseif (mb_strlen($nama) > 150) {
                $errors['nama_opt'] = 'Nama OPT maksimal 150 karakter.';
            }
        }

        if (!$isUpdate || isset($data['jenis'])) {
            $jenis = $data['jenis'] ?? '';
            $allowed = ['hama', 'penyakit', 'gulma'];
            if ($jenis === '') {
                $errors['jenis'] = 'Jenis OPT wajib diisi.';
            } elseif (!in_array($jenis, $allowed, true)) {
                $errors['jenis'] = 'Jenis OPT harus salah satu: hama, penyakit, gulma.';
            }
        }

        if (isset($data['etl_acuan']) && $data['etl_acuan'] !== '') {
            if (!is_numeric($data['etl_acuan']) || (float) $data['etl_acuan'] < 0) {
                $errors['etl_acuan'] = 'ETL acuan harus angka >= 0.';
            }
        }

        if (isset($data['satuan_etl']) && mb_strlen($data['satuan_etl']) > 30) {
            $errors['satuan_etl'] = 'Satuan ETL maksimal 30 karakter.';
        }

        if (isset($data['foto_url']) && mb_strlen($data['foto_url']) > 300) {
            $errors['foto_url'] = 'URL foto maksimal 300 karakter.';
        }

        return ['valid' => count($errors) === 0, 'errors' => $errors];
    }
}
