import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/alat_sarana/models/laporan_alat_sarana.dart';

void main() {
  group('LaporanAlatSarana Model Tests', () {
    test('fromJson handles complete JSON data', () {
      final json = {
        'id': 40,
        'nomor_laporan': 'LAS-20260811-0001',
        'status': 'Ditolak',
        'tanggal': '2026-08-11',
        'nama_alat': 'Traktor Roda 4 Quick',
        'jenis_sarana': 'Traktor',
        'kondisi': 'Rusak Ringan',
        'kapasitas': '85 HP',
        'tahun_pengadaan': '2022',
        'nama_kabupaten': 'Jember',
        'nama_kecamatan': 'Ambulu',
        'nama_desa': 'Sumberejo',
        'catatan': 'Membutuhkan penggantian suku cadang',
      };

      final item = LaporanAlatSarana.fromJson(json);

      expect(item.id, equals(40));
      expect(item.nomorLaporan, equals('LAS-20260811-0001'));
      expect(item.namaAlat, equals('Traktor Roda 4 Quick'));
      expect(item.jenisSarana, equals('Traktor'));
      expect(item.kondisi, equals('Rusak Ringan'));
      expect(item.kapasitas, equals('85 HP'));
      expect(item.tahunPengadaan, equals(2022));
      expect(item.statusLabel, equals('Ditolak'));
      expect(item.isDitolak, isTrue);
      expect(item.isEditable, isTrue);
    });

    test('fromJson handles minimal and null data safely', () {
      final json = {'id': 4};
      final item = LaporanAlatSarana.fromJson(json);

      expect(item.id, equals(4));
      expect(item.status, equals('Draf'));
      expect(item.namaAlat, isNull);
      expect(item.tahunPengadaan, isNull);
      expect(item.statusLabel, equals('Draf'));
    });
  });
}
