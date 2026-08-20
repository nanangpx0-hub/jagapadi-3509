import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/validators/laporan_validators.dart';

void main() {
  group('LaporanValidators.tanggal', () {
    test('menerima format YYYY-MM-DD', () {
      expect(LaporanValidators.tanggal('2026-08-11'), isNull);
    });

    test('menolak format lain', () {
      expect(LaporanValidators.tanggal('11-08-2026'), isNotNull);
      expect(LaporanValidators.tanggal('2026/08/11'), isNotNull);
      expect(LaporanValidators.tanggal('abc'), isNotNull);
    });

    test('menolak tanggal tidak valid', () {
      expect(LaporanValidators.tanggal('2026-13-45'), isNotNull);
      expect(LaporanValidators.tanggal('2026-02-30'), isNotNull);
    });

    test('menolak tanggal terlalu tua atau terlalu baru', () {
      expect(LaporanValidators.tanggal('1999-12-31'), isNotNull);
      expect(LaporanValidators.tanggal('2099-01-01'), isNotNull);
    });
  });

  group('LaporanValidators.angka', () {
    test('menerima angka desimal', () {
      expect(LaporanValidators.angka('12.5'), isNull);
      expect(LaporanValidators.angka('0'), isNull);
    });

    test('allowEmpty=true menerima kosong, allowEmpty=false menolak', () {
      expect(LaporanValidators.angka(''), isNull);
      expect(LaporanValidators.angka('', allowEmpty: false), isNotNull);
    });

    test('nonNegative menolak nilai negatif', () {
      expect(LaporanValidators.angka('-3', nonNegative: true), isNotNull);
      expect(LaporanValidators.angka('-3', nonNegative: false), isNull);
    });

    test('positive menolak nol', () {
      expect(LaporanValidators.angka('0', positive: true), isNotNull);
      expect(LaporanValidators.angka('0.5', positive: true), isNull);
    });

    test('max membatasi nilai', () {
      expect(LaporanValidators.angka('101', max: 100), isNotNull);
      expect(LaporanValidators.angka('99', max: 100), isNull);
    });

    test('menolak teks bukan angka', () {
      expect(LaporanValidators.angka('abc'), isNotNull);
      expect(LaporanValidators.angka('1,5'), isNotNull);
    });
  });

  group('LaporanValidators.koordinat', () {
    test('menerima koordinat valid', () {
      expect(LaporanValidators.koordinat('-8.1845', '113.6681'), isNull);
      expect(LaporanValidators.koordinat('90', '180'), isNull);
    });

    test('menolak di luar rentang', () {
      expect(LaporanValidators.koordinat('91', '113'), isNotNull);
      expect(LaporanValidators.koordinat('-8', '181'), isNotNull);
    });

    test('kosong diizinkan (opsional)', () {
      expect(LaporanValidators.koordinat('', ''), isNull);
      expect(LaporanValidators.koordinat(null, null), isNull);
    });

    test('menolak teks bukan angka', () {
      expect(LaporanValidators.koordinat('abc', '113'), isNotNull);
    });
  });

  group('LaporanValidators.catatan', () {
    test('menerima catatan pendek', () {
      expect(LaporanValidators.catatan('Hama ringan'), isNull);
    });

    test('menolak catatan lebih dari 2000 karakter', () {
      expect(LaporanValidators.catatan('a' * 2001), isNotNull);
      expect(LaporanValidators.catatan('a' * 2000), isNull);
    });
  });

  group('LaporanValidators.wajib & enumValue', () {
    test('wajib menolak kosong', () {
      expect(LaporanValidators.wajib('', 'Komoditas'), isNotNull);
      expect(LaporanValidators.wajib('Padi', 'Komoditas'), isNull);
    });

    test('enumValue menolak nilai di luar daftar', () {
      expect(LaporanValidators.enumValue('Aneh', ['A', 'B'], 'x'), isNotNull);
      expect(LaporanValidators.enumValue('A', ['A', 'B'], 'x'), isNull);
      expect(LaporanValidators.enumValue(null, ['A', 'B'], 'x'), isNull);
    });
  });

  group('ModuleValidators.hama', () {
    test('draft dengan field minimum tidak menghasilkan error', () {
      final errors = ModuleValidators.hama({
        'tanggal': '2026-08-11',
        'catatan': '',
      }, draft: true);
      expect(errors, isEmpty);
    });

    test('submit membutuhkan field wajib', () {
      final errors = ModuleValidators.hama({
        'tanggal': '2026-08-11',
        'catatan': '',
      }, draft: false);
      expect(errors.keys, containsAll(['master_opt_id', 'kabupaten_id']));
    });

    test('menolak luas_serangan negatif', () {
      final errors = ModuleValidators.hama({
        'tanggal': '2026-08-11',
        'catatan': '',
        'luas_serangan': '-2',
      }, draft: true);
      expect(errors.containsKey('luas_serangan'), isTrue);
    });

    test('menolak tingkat_keparahan di luar daftar', () {
      final errors = ModuleValidators.hama({
        'tanggal': '2026-08-11',
        'catatan': '',
        'tingkat_keparahan': 'Parah Sekali',
      }, draft: true);
      expect(errors.containsKey('tingkat_keparahan'), isTrue);
    });

    test('menerima tingkat_keparahan yang diizinkan', () {
      final errors = ModuleValidators.hama({
        'tanggal': '2026-08-11',
        'catatan': '',
        'tingkat_keparahan': 'Berat',
      }, draft: true);
      expect(errors.containsKey('tingkat_keparahan'), isFalse);
    });
  });

  group('ModuleValidators.cuaca', () {
    test('submit membutuhkan kondisi_cuaca', () {
      final errors = ModuleValidators.cuaca({
        'tanggal': '2026-08-11',
        'catatan': '',
      }, draft: false);
      expect(errors.containsKey('kondisi_cuaca'), isTrue);
    });

    test('menerima kondisi_cuaca valid', () {
      final errors = ModuleValidators.cuaca({
        'tanggal': '2026-08-11',
        'catatan': '',
        'kondisi_cuaca': 'Hujan Ringan',
      }, draft: false);
      expect(errors.containsKey('kondisi_cuaca'), isFalse);
    });
  });

  group('ModuleValidators.koordinat & wilayah', () {
    test('menolak koordinat teks tidak valid', () {
      final errors = ModuleValidators.hama({
        'tanggal': '2026-08-11',
        'catatan': '',
        'latitude': 'x',
      }, draft: true);
      expect(errors.containsKey('latitude'), isTrue);
    });

    test('wilayah: desa tanpa kecamatan ditolak', () {
      expect(
        LaporanValidators.wilayah(kabupatenId: 1, kecamatanId: null, desaId: 5),
        isNotNull,
      );
      expect(
        LaporanValidators.wilayah(kabupatenId: 1, kecamatanId: 2, desaId: 5),
        isNull,
      );
    });
  });
}
