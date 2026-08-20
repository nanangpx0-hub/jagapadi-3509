import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/panen/models/laporan_panen.dart';

void main() {
  group('LaporanPanen Model Tests', () {
    test('fromJson handles complete JSON data', () {
      final json = {
        'id': 20,
        'nomor_laporan': 'LPN-20260811-0001',
        'status': 'Submitted',
        'tanggal': '2026-08-11',
        'komoditas': 'Padi Ciherang',
        'varietas': 'IR64',
        'luas_panen': '2.00',
        'hasil_panen': '12.50',
        'produktivitas': '6.25',
        'musim_tanam': 'MT1',
        'nama_kabupaten': 'Jember',
        'nama_kecamatan': 'Wuluhan',
        'nama_desa': 'Dukuhdempok',
        'latitude': '-8.3510',
        'longitude': '113.5482',
        'catatan': 'Hasil panen melimpah',
      };

      final item = LaporanPanen.fromJson(json);

      expect(item.id, equals(20));
      expect(item.nomorLaporan, equals('LPN-20260811-0001'));
      expect(item.status, equals('Submitted'));
      expect(item.komoditas, equals('Padi Ciherang'));
      expect(item.varietas, equals('IR64'));
      expect(item.luasPanen, equals(2.00));
      expect(item.hasilPanen, equals(12.50));
      expect(item.produktivitas, equals(6.25));
      expect(item.musimTanam, equals('MT1'));
      expect(item.statusLabel, equals('Dikirim'));
      expect(item.isEditable, isFalse);
    });

    test('fromJson handles minimal and null data safely', () {
      final json = {'id': 2};
      final item = LaporanPanen.fromJson(json);

      expect(item.id, equals(2));
      expect(item.status, equals('Draf'));
      expect(item.komoditas, isNull);
      expect(item.hasilPanen, isNull);
      expect(item.statusLabel, equals('Draf'));
      expect(item.isEditable, isTrue);
    });
  });
}
