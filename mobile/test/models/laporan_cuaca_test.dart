import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/cuaca/models/laporan_cuaca.dart';

void main() {
  group('LaporanCuaca Model Tests', () {
    test('fromJson handles complete JSON data', () {
      final json = {
        'id': 30,
        'nomor_laporan': 'LC-20260811-0001',
        'status': 'Diverifikasi',
        'tanggal': '2026-08-11',
        'suhu_min': '24.0',
        'suhu_max': '32.5',
        'curah_hujan': '15.2',
        'kelembaban': '85.0',
        'kecepatan_angin': '12.0',
        'kondisi_cuaca': 'Hujan Ringan',
        'nama_kabupaten': 'Jember',
        'nama_kecamatan': 'Tanggul',
        'nama_desa': 'Klatakan',
        'catatan': 'Cuaca cukup lembab',
      };

      final item = LaporanCuaca.fromJson(json);

      expect(item.id, equals(30));
      expect(item.nomorLaporan, equals('LC-20260811-0001'));
      expect(item.suhuMin, equals(24.0));
      expect(item.suhuMax, equals(32.5));
      expect(item.curahHujan, equals(15.2));
      expect(item.kelembaban, equals(85.0));
      expect(item.kecepatanAngin, equals(12.0));
      expect(item.kondisiCuaca, equals('Hujan Ringan'));
      expect(item.statusLabel, equals('Diverifikasi'));
    });

    test('fromJson handles minimal and null data safely', () {
      final json = {'id': 3};
      final item = LaporanCuaca.fromJson(json);

      expect(item.id, equals(3));
      expect(item.status, equals('Draf'));
      expect(item.suhuMin, isNull);
      expect(item.kondisiCuaca, isNull);
      expect(item.statusLabel, equals('Draf'));
    });
  });
}
