import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/laporan/models/laporan_item.dart';

void main() {
  // ── LaporanItem.fromHamaJson ─────────────────────────────────────────────
  group('LaporanItem.fromHamaJson', () {
    test('parses full hama payload correctly', () {
      final json = {
        'id': 42,
        'nomor_laporan': 'LH-20260811-0001',
        'status': 'Submitted',
        'tanggal': '2026-08-11',
        'kabupaten_id': 1,
        'nama_kabupaten': 'Jember',
        'kecamatan_id': 5,
        'nama_kecamatan': 'Kaliwates',
        'desa_id': 12,
        'nama_desa': 'Kepatihan',
        'nama_opt': 'Wereng Batang Coklat',
        'tingkat_keparahan': 'Sedang',
        'luas_serangan': '1.75',
        'populasi': '25.0',
        'latitude': '-8.1734',
        'longitude': '113.7012',
        'foto_url': 'assets/uploads/laporan-hama/202608/abc123.jpg',
        'catatan': 'Populasi meningkat',
        'catatan_verifikasi': null,
        'verified_at': null,
        'created_at': '2026-08-11T10:00:00+07:00',
        'updated_at': '2026-08-11T10:00:00+07:00',
      };

      final item = LaporanItem.fromHamaJson(json);

      expect(item.id, 42);
      expect(item.jenis, JenisLaporan.hama);
      expect(item.nomorLaporan, 'LH-20260811-0001');
      expect(item.status, 'Submitted');
      expect(item.tanggal, '2026-08-11');
      expect(item.namaKecamatan, 'Kaliwates');
      expect(item.namaOpt, 'Wereng Batang Coklat');
      expect(item.tingkatKeparahan, 'Sedang');
      expect(item.luasSerangan, closeTo(1.75, 0.001));
      expect(item.populasi, closeTo(25.0, 0.001));
      expect(item.latitude, closeTo(-8.1734, 0.0001));
      expect(item.longitude, closeTo(113.7012, 0.0001));
      expect(item.catatan, 'Populasi meningkat');
    });

    test('handles minimal hama payload (all nullable fields null)', () {
      final json = {'id': 1, 'status': 'Draf'};
      final item = LaporanItem.fromHamaJson(json);

      expect(item.id, 1);
      expect(item.jenis, JenisLaporan.hama);
      expect(item.status, 'Draf');
      expect(item.nomorLaporan, isNull);
      expect(item.namaOpt, isNull);
      expect(item.luasSerangan, isNull);
      expect(item.latitude, isNull);
    });

    test('handles numeric latitude/longitude as double or string', () {
      final jsonDouble = {'id': 1, 'status': 'Draf', 'latitude': -8.17, 'longitude': 113.70};
      final jsonString = {'id': 2, 'status': 'Draf', 'latitude': '-8.17', 'longitude': '113.70'};

      final a = LaporanItem.fromHamaJson(jsonDouble);
      final b = LaporanItem.fromHamaJson(jsonString);

      expect(a.latitude, closeTo(-8.17, 0.001));
      expect(b.latitude, closeTo(-8.17, 0.001));
      expect(a.longitude, closeTo(113.70, 0.001));
    });

    test('wraps data key if present', () {
      final json = {
        'data': {
          'id': 99,
          'status': 'Diverifikasi',
          'nama_opt': 'Tikus Sawah',
        },
      };
      final item = LaporanItem.fromHamaJson(json);
      expect(item.id, 99);
      expect(item.namaOpt, 'Tikus Sawah');
    });

    test('returns id=0 when id is missing', () {
      final item = LaporanItem.fromHamaJson({'status': 'Draf'});
      expect(item.id, 0);
    });
  });

  // ── LaporanItem.fromIrigasiJson ──────────────────────────────────────────
  group('LaporanItem.fromIrigasiJson', () {
    test('parses full irigasi payload correctly', () {
      final json = {
        'id': 15,
        'nomor_laporan': 'LI-20260811-0001',
        'status': 'Diverifikasi',
        'tanggal': '2026-08-11',
        'nama_kecamatan': 'Sumbersari',
        'nama_saluran': 'Saluran Primer Bedadung',
        'daerah_irigasi': 'Dam Bedadung',
        'kondisi_fisik': 'Rusak',
        'debit_air': 'Kurang',
        'catatan_verifikasi': 'Data lengkap dan valid',
        'verified_at': '2026-08-11T14:00:00+07:00',
      };

      final item = LaporanItem.fromIrigasiJson(json);

      expect(item.id, 15);
      expect(item.jenis, JenisLaporan.irigasi);
      expect(item.nomorLaporan, 'LI-20260811-0001');
      expect(item.status, 'Diverifikasi');
      expect(item.namaKecamatan, 'Sumbersari');
      expect(item.namaSaluran, 'Saluran Primer Bedadung');
      expect(item.kondisiFisik, 'Rusak');
      expect(item.debitAir, 'Kurang');
      expect(item.catatanVerifikasi, 'Data lengkap dan valid');
    });

    test('handles minimal irigasi payload', () {
      final item = LaporanItem.fromIrigasiJson({'id': 5, 'status': 'Draf'});
      expect(item.jenis, JenisLaporan.irigasi);
      expect(item.namaSaluran, isNull);
      expect(item.kondisiFisik, isNull);
    });
  });

  // ── Computed properties ──────────────────────────────────────────────────
  group('LaporanItem computed properties', () {
    LaporanItem _make(String status) => LaporanItem(
          id: 1,
          jenis: JenisLaporan.hama,
          status: status,
        );

    group('statusLabel', () {
      test('Draf → Draf', () => expect(_make('Draf').statusLabel, 'Draf'));
      test('Submitted → Dikirim', () => expect(_make('Submitted').statusLabel, 'Dikirim'));
      test('Diverifikasi → Diverifikasi', () => expect(_make('Diverifikasi').statusLabel, 'Diverifikasi'));
      test('Ditolak → Ditolak', () => expect(_make('Ditolak').statusLabel, 'Ditolak'));
      test('Diarsipkan → Diarsipkan', () => expect(_make('Diarsipkan').statusLabel, 'Diarsipkan'));
      test('unknown → raw status', () => expect(_make('Unknown').statusLabel, 'Unknown'));
    });

    group('isEditable', () {
      test('Draf is editable', () => expect(_make('Draf').isEditable, isTrue));
      test('Ditolak is editable', () => expect(_make('Ditolak').isEditable, isTrue));
      test('Submitted is NOT editable', () => expect(_make('Submitted').isEditable, isFalse));
      test('Diverifikasi is NOT editable', () => expect(_make('Diverifikasi').isEditable, isFalse));
      test('Diarsipkan is NOT editable', () => expect(_make('Diarsipkan').isEditable, isFalse));
    });

    group('isDraf / isDitolak', () {
      test('isDraf true only for Draf', () {
        expect(_make('Draf').isDraf, isTrue);
        expect(_make('Submitted').isDraf, isFalse);
      });
      test('isDitolak true only for Ditolak', () {
        expect(_make('Ditolak').isDitolak, isTrue);
        expect(_make('Submitted').isDitolak, isFalse);
      });
    });

    group('judulRingkas', () {
      test('hama uses namaOpt', () {
        final item = LaporanItem(
          id: 1, jenis: JenisLaporan.hama, status: 'Draf', namaOpt: 'Wereng',
        );
        expect(item.judulRingkas, 'Wereng');
      });
      test('hama fallback when namaOpt null', () {
        final item = LaporanItem(id: 1, jenis: JenisLaporan.hama, status: 'Draf');
        expect(item.judulRingkas, 'Laporan Hama');
      });
      test('irigasi uses namaSaluran', () {
        final item = LaporanItem(
          id: 2, jenis: JenisLaporan.irigasi, status: 'Draf',
          namaSaluran: 'Saluran Primer',
        );
        expect(item.judulRingkas, 'Saluran Primer');
      });
      test('irigasi fallback when namaSaluran null', () {
        final item = LaporanItem(id: 2, jenis: JenisLaporan.irigasi, status: 'Draf');
        expect(item.judulRingkas, 'Laporan Irigasi');
      });
    });

    group('jenisLabel', () {
      test('hama → Hama/OPT', () {
        final item = LaporanItem(id: 1, jenis: JenisLaporan.hama, status: 'Draf');
        expect(item.jenisLabel, 'Hama/OPT');
      });
      test('irigasi → Irigasi', () {
        final item = LaporanItem(id: 2, jenis: JenisLaporan.irigasi, status: 'Draf');
        expect(item.jenisLabel, 'Irigasi');
      });
    });
  });
}
