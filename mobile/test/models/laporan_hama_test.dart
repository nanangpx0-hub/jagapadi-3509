import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/hama/models/laporan_hama.dart';

void main() {
  group('LaporanHama Model Tests', () {
    test('fromJson handles complete JSON data', () {
      final json = {
        'id': 10,
        'nomor_laporan': 'LH-20260811-0001',
        'status': 'Draf',
        'tanggal': '2026-08-11',
        'master_opt_id': 1,
        'nama_opt': 'Wereng Batang Cokelat',
        'kabupaten_id': 3509,
        'nama_kabupaten': 'Jember',
        'kecamatan_id': 100,
        'nama_kecamatan': 'Patrang',
        'desa_id': 1001,
        'nama_desa': 'Jemberlor',
        'lokasi': 'Blok A Sawah Selatan',
        'latitude': '-8.1728',
        'longitude': '113.7024',
        'tingkat_keparahan': 'Sedang',
        'luas_serangan': '2.5',
        'populasi': '150.0',
        'foto_url': 'uploads/foto/hama_10.jpg',
        'catatan': 'Ditemukan di sudut petakan',
      };

      final item = LaporanHama.fromJson(json);

      expect(item.id, equals(10));
      expect(item.nomorLaporan, equals('LH-20260811-0001'));
      expect(item.status, equals('Draf'));
      expect(item.namaOpt, equals('Wereng Batang Cokelat'));
      expect(item.latitude, equals(-8.1728));
      expect(item.longitude, equals(113.7024));
      expect(item.luasSerangan, equals(2.5));
      expect(item.populasi, equals(150.0));
      expect(item.isEditable, isTrue);
      expect(item.isSubmittable, isTrue);
      expect(item.isDraf, isTrue);
      expect(item.isDitolak, isFalse);
      expect(item.statusLabel, equals('Draf'));
    });

    test('fromJson handles minimal and null data safely', () {
      final json = <String, dynamic>{'id': 1};

      final item = LaporanHama.fromJson(json);

      expect(item.id, equals(1));
      expect(item.status, equals('Draf'));
      expect(item.nomorLaporan, isNull);
      expect(item.latitude, isNull);
      expect(item.longitude, isNull);
    });

    test('statusLabel and state flags compute correctly per status', () {
      final draf = LaporanHama.fromJson({'id': 1, 'status': 'Draf'});
      expect(draf.isEditable, isTrue);
      expect(draf.isSubmittable, isTrue);
      expect(draf.statusLabel, equals('Draf'));

      final submitted = LaporanHama.fromJson({'id': 2, 'status': 'Submitted'});
      expect(submitted.isEditable, isFalse);
      expect(submitted.isSubmittable, isFalse);
      expect(submitted.statusLabel, equals('Dikirim'));

      final verified = LaporanHama.fromJson({'id': 3, 'status': 'Diverifikasi'});
      expect(verified.isEditable, isFalse);
      expect(verified.statusLabel, equals('Diverifikasi'));

      final ditolak = LaporanHama.fromJson({'id': 4, 'status': 'Ditolak'});
      expect(ditolak.isEditable, isTrue);
      expect(ditolak.isSubmittable, isTrue);
      expect(ditolak.isDitolak, isTrue);
      expect(ditolak.statusLabel, equals('Ditolak'));
    });

    test('OptOption.fromJson parses correctly', () {
      final opt = OptOption.fromJson({'id': 5, 'nama_opt': 'Tikus', 'jenis': 'Hama'});
      expect(opt.id, equals(5));
      expect(opt.nama, equals('Tikus'));
      expect(opt.jenis, equals('Hama'));
    });
  });
}
