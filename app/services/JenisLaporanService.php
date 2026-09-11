<?php

declare(strict_types=1);

/**
 * JenisLaporanService — normalisasi & validasi master jenis laporan.
 *
 * Dipakai JenisLaporanController (admin) sebelum create/update.
 * Seluruh method murni (tanpa DB) agar mudah diuji unit; pengecekan
 * kode-duplikat dilakukan di controller/model karena butuh query.
 */
final class JenisLaporanService
{
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'integer', 'date'];

    private const KODE_PATTERN = '/^[a-z0-9_]{2,60}$/';
    private const FIELD_NAME_PATTERN = '/^[a-z][a-z0-9_]{1,59}$/';
    private const MAX_FIELDS = 50;
    private const MAX_NAMA = 150;
    private const MAX_DESKRIPSI = 2000;
    private const MAX_LABEL = 150;

    /**
     * Normalisasi input mentah form menjadi data siap simpan.
     * fields_json selalu dikembalikan sebagai string JSON kanonis.
     * Kode kosong otomatis dibuat dari nama (slug) agar konsisten
     * dengan form yang mengisi kode secara otomatis.
     */
    public function normalize(array $input): array
    {
        $fieldsJson = trim((string) ($input['fields_json'] ?? ''));
        if ($fieldsJson === '') {
            $fieldsJson = '[]';
        }

        $kode = strtolower(trim((string) ($input['kode'] ?? '')));
        if ($kode === '') {
            $kode = self::slugify((string) ($input['nama'] ?? ''));
        }

        return [
            'kode' => $kode,
            'nama' => trim((string) ($input['nama'] ?? '')),
            'deskripsi' => trim((string) ($input['deskripsi'] ?? '')) ?: null,
            'fields_json' => $fieldsJson,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];
    }

    /**
     * Ubah nama menjadi kode slug: huruf kecil, non-alfanumerik menjadi
     * underscore, maksimal 60 karakter. Cerminan JS di form admin.
     */
    public static function slugify(string $nama): string
    {
        $slug = strtolower($nama);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        if (strlen($slug) > 60) {
            $slug = substr($slug, 0, 60);
            $slug = rtrim($slug, '_');
        }
        return $slug;
    }

    /**
     * @return string[] daftar pesan error; kosong berarti valid.
     */
    public function validate(array $data): array
    {
        $errors = [];

        $kode = (string) ($data['kode'] ?? '');
        if ($kode === '') {
            $errors[] = 'Kode jenis laporan wajib diisi';
        } elseif (!preg_match(self::KODE_PATTERN, $kode)) {
            $errors[] = 'Kode hanya boleh huruf kecil, angka, dan underscore (2-60 karakter)';
        }

        $nama = (string) ($data['nama'] ?? '');
        if ($nama === '') {
            $errors[] = 'Nama jenis laporan wajib diisi';
        } elseif (mb_strlen($nama) > self::MAX_NAMA) {
            $errors[] = 'Nama jenis laporan maksimal 150 karakter';
        }

        if (isset($data['deskripsi']) && $data['deskripsi'] !== null
            && mb_strlen((string) $data['deskripsi']) > self::MAX_DESKRIPSI) {
            $errors[] = 'Deskripsi maksimal 2000 karakter';
        }

        foreach ($this->validateFieldsJson((string) ($data['fields_json'] ?? '')) as $error) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * @return string[] daftar pesan error struktur fields_json.
     */
    public function validateFieldsJson(string $fieldsJson): array
    {
        $errors = [];

        if (trim($fieldsJson) === '') {
            return $errors; // kosong = tanpa field tambahan (dinormalisasi jadi '[]').
        }

        try {
            $fields = json_decode($fieldsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['fields_json harus berupa JSON valid'];
        }

        if (!is_array($fields)) {
            return ['fields_json harus berupa array JSON'];
        }
        if (count($fields) > self::MAX_FIELDS) {
            return ['Maksimal 50 field dinamis per jenis laporan'];
        }

        $names = [];
        foreach ($fields as $index => $field) {
            $pos = $index + 1;
            if (!is_array($field)) {
                $errors[] = "Field #{$pos} harus berupa object";
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === '' || !preg_match(self::FIELD_NAME_PATTERN, $name)) {
                $errors[] = "Field #{$pos}: name wajib huruf kecil/angka/underscore (2-60 karakter, diawali huruf)";
            } elseif (in_array($name, $names, true)) {
                $errors[] = "Field #{$pos}: name '{$name}' duplikat";
            } else {
                $names[] = $name;
            }

            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                $errors[] = "Field #{$pos}: label wajib diisi";
            } elseif (mb_strlen($label) > self::MAX_LABEL) {
                $errors[] = "Field #{$pos}: label maksimal 150 karakter";
            }

            if (!in_array($field['type'] ?? '', self::FIELD_TYPES, true)) {
                $errors[] = "Field #{$pos}: type harus salah satu dari " . implode(', ', self::FIELD_TYPES);
            }
        }

        return $errors;
    }

    /**
     * Kanonisasi fields_json yang sudah lolos validasi (key rapi + required bool).
     */
    public function canonicalizeFieldsJson(string $fieldsJson): string
    {
        if (trim($fieldsJson) === '') {
            return '[]';
        }
        $fields = json_decode($fieldsJson, true);
        if (!is_array($fields)) {
            return '[]';
        }
        $clean = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $clean[] = [
                'name' => (string) ($field['name'] ?? ''),
                'label' => trim((string) ($field['label'] ?? '')),
                'type' => (string) ($field['type'] ?? 'text'),
                'required' => !empty($field['required']),
            ];
        }
        return (string) json_encode($clean, JSON_UNESCAPED_UNICODE);
    }
}
