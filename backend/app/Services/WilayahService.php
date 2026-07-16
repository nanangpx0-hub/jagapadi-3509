<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLogWilayah;
use App\Models\MasterDesa;
use App\Models\MasterKabupaten;
use App\Models\MasterKecamatan;

class WilayahService
{
    public static function createKabupaten(array $data, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            if (MasterKabupaten::findByKode($data['kode']) !== null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Kode kabupaten sudah digunakan.', 'code' => 409];
            }

            $id = MasterKabupaten::insert([
                'kode' => $data['kode'],
                'nama_kabupaten' => $data['nama_kabupaten'],
            ]);

            AuditLogWilayah::log($adminId, 'master_kabupaten', (int) $id, 'INSERT', null, $data);

            $pdo->commit();

            return ['success' => true, 'data' => MasterKabupaten::find((int) $id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateKabupaten(int $id, array $data, array $oldData, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            if (isset($data['kode'])) {
                $existing = MasterKabupaten::findByKode($data['kode']);
                if ($existing !== null && (int) $existing['id'] !== $id) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Kode kabupaten sudah digunakan.', 'code' => 409];
                }
            }

            MasterKabupaten::update($id, $data);

            AuditLogWilayah::log($adminId, 'master_kabupaten', $id, 'UPDATE', $oldData, $data);

            $pdo->commit();

            return ['success' => true, 'data' => MasterKabupaten::find($id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteKabupaten(int $id, array $oldData, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $childCount = count(MasterKecamatan::findByKabupaten($id));
            if ($childCount > 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Tidak bisa dihapus karena masih memiliki kecamatan terkait.', 'code' => 409];
            }

            MasterKabupaten::delete($id);
            AuditLogWilayah::log($adminId, 'master_kabupaten', $id, 'DELETE', $oldData, null);

            $pdo->commit();

            return ['success' => true];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Tidak bisa dihapus karena masih digunakan data terkait.', 'code' => 409];
            }
            throw $e;
        }
    }

    public static function createKecamatan(array $data, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $kabupaten = MasterKabupaten::find((int) $data['kabupaten_id']);
            if ($kabupaten === null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Kabupaten tidak ditemukan.', 'code' => 404];
            }

            if (MasterKecamatan::findByKode($data['kode']) !== null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Kode kecamatan sudah digunakan.', 'code' => 409];
            }

            $id = MasterKecamatan::insert([
                'kabupaten_id' => (int) $data['kabupaten_id'],
                'kode' => $data['kode'],
                'nama_kecamatan' => $data['nama_kecamatan'],
            ]);

            AuditLogWilayah::log($adminId, 'master_kecamatan', (int) $id, 'INSERT', null, $data);

            $pdo->commit();

            return ['success' => true, 'data' => MasterKecamatan::find((int) $id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateKecamatan(int $id, array $data, array $oldData, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            if (isset($data['kode'])) {
                $existing = MasterKecamatan::findByKode($data['kode']);
                if ($existing !== null && (int) $existing['id'] !== $id) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Kode kecamatan sudah digunakan.', 'code' => 409];
                }
            }

            if (isset($data['kabupaten_id'])) {
                $kabupaten = MasterKabupaten::find((int) $data['kabupaten_id']);
                if ($kabupaten === null) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Kabupaten tidak ditemukan.', 'code' => 404];
                }
            }

            MasterKecamatan::update($id, $data);

            AuditLogWilayah::log($adminId, 'master_kecamatan', $id, 'UPDATE', $oldData, $data);

            $pdo->commit();

            return ['success' => true, 'data' => MasterKecamatan::find($id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteKecamatan(int $id, array $oldData, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $childCount = count(MasterDesa::findByKecamatan($id));
            if ($childCount > 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Tidak bisa dihapus karena masih memiliki desa terkait.', 'code' => 409];
            }

            MasterKecamatan::delete($id);
            AuditLogWilayah::log($adminId, 'master_kecamatan', $id, 'DELETE', $oldData, null);

            $pdo->commit();

            return ['success' => true];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Tidak bisa dihapus karena masih digunakan data terkait.', 'code' => 409];
            }
            throw $e;
        }
    }

    public static function createDesa(array $data, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $kecamatan = MasterKecamatan::find((int) $data['kecamatan_id']);
            if ($kecamatan === null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Kecamatan tidak ditemukan.', 'code' => 404];
            }

            if (MasterDesa::findByKode($data['kode']) !== null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Kode desa sudah digunakan.', 'code' => 409];
            }

            $id = MasterDesa::insert([
                'kecamatan_id' => (int) $data['kecamatan_id'],
                'kode' => $data['kode'],
                'nama_desa' => $data['nama_desa'],
            ]);

            AuditLogWilayah::log($adminId, 'master_desa', (int) $id, 'INSERT', null, $data);

            $pdo->commit();

            return ['success' => true, 'data' => MasterDesa::find((int) $id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateDesa(int $id, array $data, array $oldData, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            if (isset($data['kode'])) {
                $existing = MasterDesa::findByKode($data['kode']);
                if ($existing !== null && (int) $existing['id'] !== $id) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Kode desa sudah digunakan.', 'code' => 409];
                }
            }

            if (isset($data['kecamatan_id'])) {
                $kecamatan = MasterKecamatan::find((int) $data['kecamatan_id']);
                if ($kecamatan === null) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Kecamatan tidak ditemukan.', 'code' => 404];
                }
            }

            MasterDesa::update($id, $data);

            AuditLogWilayah::log($adminId, 'master_desa', $id, 'UPDATE', $oldData, $data);

            $pdo->commit();

            return ['success' => true, 'data' => MasterDesa::find($id)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteDesa(int $id, array $oldData, int $adminId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            MasterDesa::delete($id);
            AuditLogWilayah::log($adminId, 'master_desa', $id, 'DELETE', $oldData, null);

            $pdo->commit();

            return ['success' => true];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Tidak bisa dihapus karena masih digunakan data terkait.', 'code' => 409];
            }
            throw $e;
        }
    }
}
