<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\MasterDesa;
use App\Models\MasterKabupaten;
use App\Models\MasterKecamatan;

class LaporanIrigasiValidator
{
    public static function validateDraft(array $input): array
    {
        $errors = [];

        if (isset($input['tanggal']) && $input['tanggal'] !== '') {
            if (!self::isValidDate($input['tanggal'])) {
                $errors['tanggal'] = 'Format tanggal tidak valid.';
            }
        }

        if (isset($input['kabupaten_id']) && $input['kabupaten_id'] !== '') {
            $id = (int) $input['kabupaten_id'];
            $kab = MasterKabupaten::find($id);
            if ($kab === null) {
                $errors['kabupaten_id'] = 'Kabupaten tidak valid.';
            } else {
                $jember = MasterKabupaten::findByKode('3509');
                if ($jember && (int) $kab['id'] !== (int) $jember['id']) {
                    $errors['kabupaten_id'] = 'Hanya Kabupaten Jember yang didukung.';
                }
            }
        }

        if (isset($input['kecamatan_id']) && $input['kecamatan_id'] !== '') {
            $id = (int) $input['kecamatan_id'];
            $kec = MasterKecamatan::find($id);
            if ($kec === null) {
                $errors['kecamatan_id'] = 'Kecamatan tidak valid.';
            } elseif (isset($input['kabupaten_id']) && $input['kabupaten_id'] !== '') {
                if ((int) $kec['kabupaten_id'] !== (int) $input['kabupaten_id']) {
                    $errors['kecamatan_id'] = 'Kecamatan tidak sesuai dengan kabupaten yang dipilih.';
                }
            }
        }

        if (isset($input['desa_id']) && $input['desa_id'] !== '') {
            $id = (int) $input['desa_id'];
            $desa = MasterDesa::find($id);
            if ($desa === null) {
                $errors['desa_id'] = 'Desa tidak valid.';
            } elseif (isset($input['kecamatan_id']) && $input['kecamatan_id'] !== '') {
                if ((int) $desa['kecamatan_id'] !== (int) $input['kecamatan_id']) {
                    $errors['desa_id'] = 'Desa tidak sesuai dengan kecamatan yang dipilih.';
                }
            }
        }

        if (isset($input['nama_saluran']) && $input['nama_saluran'] !== '') {
            if (mb_strlen($input['nama_saluran']) > 200) {
                $errors['nama_saluran'] = 'Nama saluran maksimal 200 karakter.';
            }
        }

        if (isset($input['daerah_irigasi']) && $input['daerah_irigasi'] !== '') {
            if (mb_strlen($input['daerah_irigasi']) > 200) {
                $errors['daerah_irigasi'] = 'Daerah irigasi maksimal 200 karakter.';
            }
        }

        if (isset($input['kondisi_fisik']) && $input['kondisi_fisik'] !== '') {
            $allowed = ['Bagus', 'Sedang', 'Tidak Bagus', 'Rusak'];
            if (!in_array($input['kondisi_fisik'], $allowed, true)) {
                $errors['kondisi_fisik'] = 'Kondisi fisik harus Bagus, Sedang, Tidak Bagus, atau Rusak.';
            }
        }

        if (isset($input['debit_air']) && $input['debit_air'] !== '') {
            $allowed = ['Cukup', 'Kurang', 'Kering'];
            if (!in_array($input['debit_air'], $allowed, true)) {
                $errors['debit_air'] = 'Debit air harus Cukup, Kurang, atau Kering.';
            }
        }

        if (isset($input['latitude']) && $input['latitude'] !== '') {
            $val = (float) $input['latitude'];
            if ($val < -90 || $val > 90) {
                $errors['latitude'] = 'Latitude harus antara -90 dan 90.';
            }
        }

        if (isset($input['longitude']) && $input['longitude'] !== '') {
            $val = (float) $input['longitude'];
            if ($val < -180 || $val > 180) {
                $errors['longitude'] = 'Longitude harus antara -180 dan 180.';
            }
        }

        if (isset($input['catatan']) && mb_strlen($input['catatan']) > 5000) {
            $errors['catatan'] = 'Catatan maksimal 5000 karakter.';
        }

        return $errors;
    }

    public static function validateSubmit(array $input): array
    {
        $errors = [];

        $required = [
            'tanggal' => 'Tanggal wajib diisi.',
            'kabupaten_id' => 'Kabupaten wajib diisi.',
            'kecamatan_id' => 'Kecamatan wajib diisi.',
            'desa_id' => 'Desa wajib diisi.',
            'nama_saluran' => 'Nama saluran wajib diisi.',
            'kondisi_fisik' => 'Kondisi fisik wajib diisi.',
            'debit_air' => 'Debit air wajib diisi.',
        ];

        foreach ($required as $field => $message) {
            if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                $errors[$field] = $message;
            }
        }

        if (empty($errors)) {
            if (!self::isValidDate($input['tanggal'])) {
                $errors['tanggal'] = 'Format tanggal tidak valid.';
            }

            $kabId = (int) $input['kabupaten_id'];
            $kab = MasterKabupaten::find($kabId);
            if ($kab === null) {
                $errors['kabupaten_id'] = 'Kabupaten tidak valid.';
            } else {
                $jember = MasterKabupaten::findByKode('3509');
                if ($jember && (int) $kab['id'] !== (int) $jember['id']) {
                    $errors['kabupaten_id'] = 'Hanya Kabupaten Jember yang didukung.';
                }
            }

            $kecId = (int) $input['kecamatan_id'];
            $kec = MasterKecamatan::find($kecId);
            if ($kec === null) {
                $errors['kecamatan_id'] = 'Kecamatan tidak valid.';
            } elseif ((int) $kec['kabupaten_id'] !== $kabId) {
                $errors['kecamatan_id'] = 'Kecamatan tidak sesuai dengan kabupaten yang dipilih.';
            }

            $desaId = (int) $input['desa_id'];
            $desa = MasterDesa::find($desaId);
            if ($desa === null) {
                $errors['desa_id'] = 'Desa tidak valid.';
            } elseif ((int) $desa['kecamatan_id'] !== $kecId) {
                $errors['desa_id'] = 'Desa tidak sesuai dengan kecamatan yang dipilih.';
            }

            $allowedKondisi = ['Bagus', 'Sedang', 'Tidak Bagus', 'Rusak'];
            if (!in_array($input['kondisi_fisik'], $allowedKondisi, true)) {
                $errors['kondisi_fisik'] = 'Kondisi fisik harus Bagus, Sedang, Tidak Bagus, atau Rusak.';
            }

            $allowedDebit = ['Cukup', 'Kurang', 'Kering'];
            if (!in_array($input['debit_air'], $allowedDebit, true)) {
                $errors['debit_air'] = 'Debit air harus Cukup, Kurang, atau Kering.';
            }
        }

        $draftErrors = self::validateDraft($input);
        foreach ($draftErrors as $k => $v) {
            if (!isset($errors[$k])) {
                $errors[$k] = $v;
            }
        }

        return $errors;
    }

    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
