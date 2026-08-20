import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/pupuk/models/laporan_pupuk.dart';

void main() {
  group('LaporanPupuk Model Tests', () {
    test('fromJson handles complete JSON data', () {
      final json = {
        'id': 10,
        'nomor_laporan': 'LP-20260811-0001',
        'status': 'Diverifikasi',
        'tanggal': '2026-08-11',
        'jenis_pupuk': 'Urea',
        'dosis_per_ha': '250.50',
        'luas_pemupukan': '1.50',
        'metode_aplikasi': 'Tabur',
        'nama_kabupaten': 'Jember',
        'nama_kecamatan': 'Sumbersari',
        'nama_desa': 'Antirogo',
        'latitude': '-8.1724',
        'longitude': '113.7003',
        'catatan': 'Pemupukan susulan pertama',
      };

      final item = LaporanPupuk.fromJson(json);

      expect(item.id, equals(10));
      expect(item.nomorLaporan, equals('LP-20260811-0001'));
      expect(item.status, equals('Diverifikasi'));
      expect(item.jenisPupuk, equals('Urea'));
      expect(item.dosisPerHa, equals(250.50));
      expect(item.luasPemupukan, equals(1.50));
      expect(item.metodeAplikasi, equals('Tabur'));
      expect(item.namaKecamatan, equals('Sumbersari'));
      expect(item.latitude, equals(-8.1724));
      expect(item.longitude, equals(113.7003));
      expect(item.statusLabel, equals('Diverifikasi'));
      expect(item.isEditable, isFalse);
    });

    test('fromJson handles minimal and null data safely', () {
      final json = {'id': 1};
      final item = LaporanPupuk.fromJson(json);

      expect(item.id, equals(1));
      expect(item.status, equals('Draf'));
      expect(item.jenisPupuk, isNull);
      expect(item.dosisPerHa, isNull);
      expect(item.luasPemupukan, isNull);
      expect(item.statusLabel, equals('Draf'));
      expect(item.isEditable, isTrue);
      expect(item.isSubmittable, isTrue);
    });
  });
}
