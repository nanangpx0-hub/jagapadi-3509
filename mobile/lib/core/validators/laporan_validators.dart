/// Validator bersama untuk enam modul laporan (hama, irigasi, pupuk, panen,
/// cuaca, alat_sarana).
///
/// Prinsip:
/// - validasi draft = minimum (tanggal + struktur payload aman),
/// - validasi submit = field wajib sesuai kontrak backend,
/// - angka invalid TIDAK pernah di-skip diam-diam (jika tryParse gagal,
///   kembalikan error field),
/// - validasi mobile bukan pengganti validasi backend.
library;

/// Format tanggal API backend: YYYY-MM-DD.
final RegExp _tanggalFormat = RegExp(r'^\d{4}-\d{2}-\d{2}$');

/// Rentang tanggal laporan yang diizinkan aplikasi.
DateTime tanggalMin() => DateTime(2020, 1, 1);
DateTime tanggalMax() => DateTime.now().add(const Duration(days: 1));

class LaporanValidators {
  LaporanValidators._();

  /// Validasi tanggal teks dengan format API `YYYY-MM-DD`.
  static String? tanggal(String? value) {
    final v = (value ?? '').trim();
    if (v.isEmpty) return null; // opsional di level validator dasar
    if (!_tanggalFormat.hasMatch(v)) {
      return 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    }
    final parts = v.split('-');
    final year = int.parse(parts[0]);
    final month = int.parse(parts[1]);
    final day = int.parse(parts[2]);
    // DateTime.tryParse menormalkan tanggal tidak valid (mis. 13-45 → bulan
    // berikutnya), jadi komponen dicek eksplisit agar tanggal seperti
    // 2026-02-30 benar-benar ditolak.
    final parsed = DateTime.tryParse(v);
    if (parsed == null ||
        parsed.year != year ||
        parsed.month != month ||
        parsed.day != day) {
      return 'Tanggal tidak valid.';
    }
    if (parsed.isBefore(tanggalMin()) || parsed.isAfter(tanggalMax())) {
      return 'Tanggal di luar rentang yang diizinkan (2020 s.d. hari ini).';
    }
    return null;
  }

  /// Validasi tanggal WAJIB diisi (draft maupun submit).
  static String? tanggalWajib(String? value) {
    final v = (value ?? '').trim();
    if (v.isEmpty) return 'Tanggal wajib diisi';
    return tanggal(v);
  }

  /// Validasi angka desimal.
  /// - [allowEmpty]: true → teks kosong dianggap valid (null).
  /// - [nonNegative]: menolak nilai negatif.
  /// - [positive]: menolak nilai <= 0.
  /// - [max]: batas atas nilai.
  /// - [label]: nama field untuk pesan error.
  static String? angka(
    String? value, {
    bool allowEmpty = true,
    bool nonNegative = false,
    bool positive = false,
    double? max,
    String label = 'Angka',
  }) {
    final v = (value ?? '').trim();
    if (v.isEmpty) return allowEmpty ? null : '$label wajib diisi';
    final parsed = double.tryParse(v);
    if (parsed == null || !parsed.isFinite) {
      return '$label tidak valid. Masukkan angka.';
    }
    if (nonNegative && parsed < 0) {
      return '$label tidak boleh negatif.';
    }
    if (positive && parsed <= 0) {
      return '$label harus lebih dari 0.';
    }
    if (max != null && parsed > max) {
      return '$label tidak boleh melebihi $max.';
    }
    return null;
  }

  /// Validasi koordinat (latitude/longitude).
  static String? koordinat(String? latText, String? lngText) {
    final lat = double.tryParse((latText ?? '').trim());
    final lng = double.tryParse((lngText ?? '').trim());
    if (latText != null && latText.trim().isNotEmpty) {
      if (lat == null || lat < -90 || lat > 90) {
        return 'Latitude harus antara -90 dan 90.';
      }
    }
    if (lngText != null && lngText.trim().isNotEmpty) {
      if (lng == null || lng < -180 || lng > 180) {
        return 'Longitude harus antara -180 dan 180.';
      }
    }
    return null;
  }

  /// Validasi panjang catatan — konsisten 2000 karakter di semua modul.
  static String? catatan(String? value, {int maxLength = 2000}) {
    final v = value ?? '';
    if (v.length > maxLength) {
      return 'Catatan maksimal $maxLength karakter '
          '(saat ini ${v.length}).';
    }
    return null;
  }

  /// Validasi teks wajib.
  static String? wajib(String? value, String label) {
    if ((value ?? '').trim().isEmpty) return '$label wajib diisi';
    return null;
  }

  /// Validasi enum hanya berisi nilai yang diizinkan.
  static String? enumValue(String? value, List<String> allowed, String label) {
    if (value == null) return null; // wajib/tidaknya diatur validator lain
    if (!allowed.contains(value)) {
      return '$label tidak valid. Nilai diizinkan: ${allowed.join(', ')}.';
    }
    return null;
  }

  /// Validasi wilayah: kecamatan tidak boleh diisi tanpa kabupaten,
  /// desa tidak boleh diisi tanpa kecamatan.
  static String? wilayah({
    required int? kabupatenId,
    required int? kecamatanId,
    required int? desaId,
    bool requireDesa = false,
  }) {
    if (kecamatanId != null && kabupatenId == null) {
      return 'Pilih kabupaten terlebih dahulu sebelum kecamatan.';
    }
    if (desaId != null && kecamatanId == null) {
      return 'Pilih kecamatan terlebih dahulu sebelum desa.';
    }
    if (requireDesa && desaId == null) {
      return 'Desa wajib dipilih.';
    }
    return null;
  }
}

/// Validator khusus per modul laporan.
///
/// [draftOnly] menentukan aturan minimum (draft) vs penuh (submit).
/// Kembali berupa map field → pesan error.
class ModuleValidators {
  ModuleValidators._();

  static const int maxCatatanLength = 2000;

  static const hamaEnums = {
    'tingkat_keparahan': ['Ringan', 'Sedang', 'Berat'],
  };

  static const irigasiEnums = {
    'kondisi_fisik': ['Bagus', 'Sedang', 'Tidak Bagus', 'Rusak'],
    'debit_air': ['Cukup', 'Kurang', 'Kering'],
  };

  static const pupukEnums = {
    'jenis_pupuk': ['Urea', 'NPK', 'Organik', 'Kompos', 'Lainnya'],
    'metode_aplikasi': ['Tabur', 'Kocor', 'Semprot', 'Injeksi'],
  };

  static const panenEnums = {
    'musim_tanam': ['MT1', 'MT2', 'MT3'],
  };

  static const cuacaEnums = {
    'kondisi_cuaca': [
      'Cerah',
      'Berawan',
      'Hujan Ringan',
      'Hujan Lebat',
      'Badai',
    ],
  };

  static const alatSaranaEnums = {
    'jenis_sarana': [
      'Traktor',
      'Pompa Air',
      'Gudang',
      'Jalan Usaha Tani',
      'Lainnya'
    ],
    'kondisi': ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Tidak Layak'],
  };

  static Map<String, String> _checkEnums(
    Map<String, dynamic> values,
    Map<String, List<String>> enums,
  ) {
    final errors = <String, String>{};
    enums.forEach((key, allowed) {
      final v = values[key];
      if (v is String && v.isNotEmpty) {
        final e = LaporanValidators.enumValue(v, allowed, key);
        if (e != null) errors[key] = e;
      }
    });
    return errors;
  }

  static String? _optionalNumber(
    Map<String, dynamic> values,
    String key, {
    required String label,
    bool positive = false,
    double? max,
  }) {
    final v = values[key];
    if (v is String && v.isNotEmpty) {
      return LaporanValidators.angka(
        v,
        allowEmpty: true,
        positive: positive,
        max: max,
        label: label,
      );
    }
    return null;
  }

  static Map<String, String> _base(
    Map<String, dynamic> values, {
    required bool draft,
    required List<String> requiredOnSubmit,
    required List<String> numericPositive,
    required Map<String, List<String>> enums,
  }) {
    final errors = <String, String>{};

    final tanggalErr = draft
        ? LaporanValidators.tanggalWajib(values['tanggal'] as String?)
        : LaporanValidators.tanggalWajib(values['tanggal'] as String?);
    if (tanggalErr != null) errors['tanggal'] = tanggalErr;

    if (values['latitude'] is String ||
        values['longitude'] is String ||
        (values['lat'] is String) ||
        (values['lng'] is String)) {
      final lat = (values['latitude'] ?? values['lat']) as String?;
      final lng = (values['longitude'] ?? values['lng']) as String?;
      final coordErr = LaporanValidators.koordinat(lat, lng);
      if (coordErr != null) {
        errors['latitude'] = coordErr;
      }
    }

    final catatanErr = LaporanValidators.catatan(values['catatan'] as String?,
        maxLength: maxCatatanLength);
    if (catatanErr != null) errors['catatan'] = catatanErr;

    for (final key in numericPositive) {
      final label = key.replaceAll('_', ' ');
      final err = _optionalNumber(
        values,
        key,
        label: label,
        positive: true,
      );
      if (err != null) errors[key] = err;
    }

    errors.addAll(_checkEnums(values, enums));

    if (!draft) {
      for (final key in requiredOnSubmit) {
        final raw = values[key];
        if (raw == null || (raw is String && raw.trim().isEmpty)) {
          errors[key] = '${key.replaceAll('_', ' ')} wajib diisi';
        }
      }
    }
    return errors;
  }

  static Map<String, String> hama(Map<String, dynamic> values,
      {required bool draft}) {
    return _base(
      values,
      draft: draft,
      requiredOnSubmit: [
        'master_opt_id',
        'kabupaten_id',
        'kecamatan_id',
        'desa_id'
      ],
      numericPositive: ['luas_serangan', 'populasi'],
      enums: hamaEnums,
    );
  }

  static Map<String, String> irigasi(Map<String, dynamic> values,
      {required bool draft}) {
    return _base(
      values,
      draft: draft,
      requiredOnSubmit: [
        'nama_saluran',
        'kabupaten_id',
        'kecamatan_id',
        'desa_id',
        'kondisi_fisik',
        'debit_air'
      ],
      numericPositive: const [],
      enums: irigasiEnums,
    );
  }

  static Map<String, String> pupuk(Map<String, dynamic> values,
      {required bool draft}) {
    return _base(
      values,
      draft: draft,
      requiredOnSubmit: [
        'jenis_pupuk',
        'kabupaten_id',
        'kecamatan_id',
        'desa_id'
      ],
      numericPositive: ['dosis_per_ha', 'luas_pemupukan'],
      enums: pupukEnums,
    );
  }

  static Map<String, String> panen(Map<String, dynamic> values,
      {required bool draft}) {
    return _base(
      values,
      draft: draft,
      requiredOnSubmit: [
        'komoditas',
        'luas_panen',
        'hasil_panen',
        'kabupaten_id',
        'kecamatan_id',
        'desa_id'
      ],
      numericPositive: ['luas_panen', 'hasil_panen'],
      enums: panenEnums,
    );
  }

  static Map<String, String> cuaca(Map<String, dynamic> values,
      {required bool draft}) {
    return _base(
      values,
      draft: draft,
      requiredOnSubmit: ['kondisi_cuaca'],
      numericPositive: const [],
      enums: cuacaEnums,
    );
  }

  static Map<String, String> alatSarana(Map<String, dynamic> values,
      {required bool draft}) {
    return _base(
      values,
      draft: draft,
      requiredOnSubmit: ['nama_alat', 'jenis_sarana'],
      numericPositive: ['kapasitas', 'tahun_pengadaan'],
      enums: alatSaranaEnums,
    );
  }
}
