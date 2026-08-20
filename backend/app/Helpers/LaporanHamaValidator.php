<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\MasterDesa;
use App\Models\MasterKabupaten;
use App\Models\MasterKecamatan;
use App\Models\MasterOpt;

class LaporanHamaValidator
{
    public static function validateDraft(array $input): array
    {
        $errors = [];

        if (isset($input['tanggal']) && $input['tanggal'] !== '') {
            if (!self::isValidDate($input['tanggal'])) {
                $errors['tanggal'] = 'Format tanggal tidak valid.';
            }
        }

        if (isset($input['master_opt_id']) && $input['master_opt_id'] !== '') {
            $id = (int) $input['master_opt_id'];
            $opt = MasterOpt::find($id);
            if ($opt === null || !$opt['aktif']) {
                $errors['master_opt_id'] = 'OPT tidak valid atau tidak aktif.';
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

        if (isset($input['tingkat_keparahan']) && $input['tingkat_keparahan'] !== '') {
            $allowed = ['Ringan', 'Sedang', 'Berat'];
            if (!in_array($input['tingkat_keparahan'], $allowed, true)) {
                $errors['tingkat_keparahan'] = 'Tingkat keparahan harus Ringan, Sedang, atau Berat.';
            }
        }

        if (isset($input['luas_serangan']) && $input['luas_serangan'] !== '') {
            $val = (float) $input['luas_serangan'];
            if ($val < 0 || $val > 9999.99) {
                $errors['luas_serangan'] = 'Luas serangan harus antara 0 dan 9999.99.';
            }
        }

        if (isset($input['populasi']) && $input['populasi'] !== '') {
            $val = (float) $input['populasi'];
            if ($val < 0) {
                $errors['populasi'] = 'Populasi harus >= 0.';
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

        if (isset($input['lokasi']) && mb_strlen($input['lokasi']) > 255) {
            $errors['lokasi'] = 'Lokasi maksimal 255 karakter.';
        }

        if (isset($input['alamat_lengkap']) && mb_strlen($input['alamat_lengkap']) > 300) {
            $errors['alamat_lengkap'] = 'Alamat lengkap maksimal 300 karakter.';
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
            'master_opt_id' => 'OPT wajib diisi.',
            'kabupaten_id' => 'Kabupaten wajib diisi.',
            'kecamatan_id' => 'Kecamatan wajib diisi.',
            'desa_id' => 'Desa wajib diisi.',
            'tingkat_keparahan' => 'Tingkat keparahan wajib diisi.',
            'luas_serangan' => 'Luas serangan wajib diisi.',
            'populasi' => 'Populasi wajib diisi.',
        ];

        foreach ($required as $field => $message) {
            if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                $errors[$field] = $message;
            }
        }

        if (!isset($input['foto_url']) || !is_string($input['foto_url']) || trim($input['foto_url']) === '') {
            $errors['foto'] = 'Foto laporan wajib disertakan sebelum laporan dapat dikirim.';
        }

        if (empty($errors)) {
            if (!self::isValidDate($input['tanggal'])) {
                $errors['tanggal'] = 'Format tanggal tidak valid.';
            }

            $optId = (int) $input['master_opt_id'];
            $opt = MasterOpt::find($optId);
            if ($opt === null || !$opt['aktif']) {
                $errors['master_opt_id'] = 'OPT tidak valid atau tidak aktif.';
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

            $allowed = ['Ringan', 'Sedang', 'Berat'];
            if (!in_array($input['tingkat_keparahan'], $allowed, true)) {
                $errors['tingkat_keparahan'] = 'Tingkat keparahan harus Ringan, Sedang, atau Berat.';
            }

            $ls = (float) $input['luas_serangan'];
            if ($ls < 0 || $ls > 9999.99) {
                $errors['luas_serangan'] = 'Luas serangan harus antara 0 dan 9999.99.';
            }

            $pop = (float) $input['populasi'];
            if ($pop < 0) {
                $errors['populasi'] = 'Populasi harus >= 0.';
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
