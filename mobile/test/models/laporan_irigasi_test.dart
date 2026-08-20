import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/irigasi/models/laporan_irigasi.dart';

void main() {
  group('LaporanIrigasi Model Tests', () {
    test('fromJson handles complete JSON data', () {
      final json = {
        'id': 20,
        'nomor_laporan': 'LI-20260811-0002',
        'status': 'Submitted',
        'tanggal': '2026-08-11',
        'nama_saluran': 'Saluran Sekunder Bedadung',
        'daerah_irigasi': 'DI Bedadung Pekalen',
        'kondisi_fisik': 'Rusak',
        'debit_air': 'Kurang',
        'latitude': '-8.1500',
        'longitude': '113.7200',
        'catatan': 'Tanggul jebol 2 meter',
      };

      final item = LaporanIrigasi.fromJson(json);

      expect(item.id, equals(20));
      expect(item.nomorLaporan, equals('LI-20260811-0002'));
      expect(item.status, equals('Submitted'));
      expect(item.namaSaluran, equals('Saluran Sekunder Bedadung'));
      expect(item.kondisiFisik, equals('Rusak'));
      expect(item.debitAir, equals('Kurang'));
      expect(item.latitude, equals(-8.1500));
      expect(item.longitude, equals(113.7200));
      expect(item.isEditable, isFalse);
      expect(item.statusLabel, equals('Dikirim'));
    });

    test('statusLabel and state flags compute correctly', () {
      final draf = LaporanIrigasi.fromJson({'id': 1, 'status': 'Draf'});
      expect(draf.isEditable, isTrue);
      expect(draf.statusLabel, equals('Draf'));

      final ditolak = LaporanIrigasi.fromJson({'id': 2, 'status': 'Ditolak'});
      expect(ditolak.isEditable, isTrue);
      expect(ditolak.isDitolak, isTrue);
      expect(ditolak.statusLabel, equals('Ditolak'));

      final verified = LaporanIrigasi.fromJson({'id': 3, 'status': 'Diverifikasi'});
      expect(verified.isEditable, isFalse);
      expect(verified.statusLabel, equals('Diverifikasi'));
    });
  });
}
