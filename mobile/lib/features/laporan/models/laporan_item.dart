/// Model terpadu yang merepresentasikan satu entri laporan dari kedua jenis:
/// hama/OPT dan irigasi. Digunakan oleh LaporanTerpaduScreen dan provider-nya.
library;

enum JenisLaporan { hama, irigasi }

class LaporanItem {
  final int id;
  final JenisLaporan jenis;
  final String? nomorLaporan;
  final String status;
  final String? tanggal;

  // Wilayah
  final int? kabupatenId;
  final String? namaKabupaten;
  final int? kecamatanId;
  final String? namaKecamatan;
  final int? desaId;
  final String? namaDesa;

  // Hama-specific
  final String? namaOpt;
  final String? tingkatKeparahan;
  final double? luasSerangan;
  final double? populasi;

  // Irigasi-specific
  final String? namaSaluran;
  final String? daerahIrigasi;
  final String? kondisiFisik;
  final String? debitAir;

  // Shared
  final double? latitude;
  final double? longitude;
  final String? fotoUrl;
  final String? catatan;
  final String? catatanVerifikasi;
  final String? verifiedAt;
  final String? createdAt;
  final String? updatedAt;

  const LaporanItem({
    required this.id,
    required this.jenis,
    required this.status,
    this.nomorLaporan,
    this.tanggal,
    this.kabupatenId,
    this.namaKabupaten,
    this.kecamatanId,
    this.namaKecamatan,
    this.desaId,
    this.namaDesa,
    this.namaOpt,
    this.tingkatKeparahan,
    this.luasSerangan,
    this.populasi,
    this.namaSaluran,
    this.daerahIrigasi,
    this.kondisiFisik,
    this.debitAir,
    this.latitude,
    this.longitude,
    this.fotoUrl,
    this.catatan,
    this.catatanVerifikasi,
    this.verifiedAt,
    this.createdAt,
    this.updatedAt,
  });

  /// Parse dari JSON laporan hama.
  factory LaporanItem.fromHamaJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>? ?? j;
    return LaporanItem(
      id: d['id'] as int? ?? 0,
      jenis: JenisLaporan.hama,
      nomorLaporan: d['nomor_laporan'] as String?,
      status: d['status'] as String? ?? 'Draf',
      tanggal: d['tanggal'] as String?,
      kabupatenId: d['kabupaten_id'] as int?,
      namaKabupaten: d['nama_kabupaten'] as String?,
      kecamatanId: d['kecamatan_id'] as int?,
      namaKecamatan: d['nama_kecamatan'] as String?,
      desaId: d['desa_id'] as int?,
      namaDesa: d['nama_desa'] as String?,
      namaOpt: d['nama_opt'] as String?,
      tingkatKeparahan: d['tingkat_keparahan'] as String?,
      luasSerangan: d['luas_serangan'] != null
          ? double.tryParse(d['luas_serangan'].toString())
          : null,
      populasi: d['populasi'] != null
          ? double.tryParse(d['populasi'].toString())
          : null,
      latitude: d['latitude'] != null
          ? double.tryParse(d['latitude'].toString())
          : null,
      longitude: d['longitude'] != null
          ? double.tryParse(d['longitude'].toString())
          : null,
      fotoUrl: d['foto_url'] as String?,
      catatan: d['catatan'] as String?,
      catatanVerifikasi: d['catatan_verifikasi'] as String?,
      verifiedAt: d['verified_at'] as String?,
      createdAt: d['created_at'] as String?,
      updatedAt: d['updated_at'] as String?,
    );
  }

  /// Parse dari JSON laporan irigasi.
  factory LaporanItem.fromIrigasiJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>? ?? j;
    return LaporanItem(
      id: d['id'] as int? ?? 0,
      jenis: JenisLaporan.irigasi,
      nomorLaporan: d['nomor_laporan'] as String?,
      status: d['status'] as String? ?? 'Draf',
      tanggal: d['tanggal'] as String?,
      kabupatenId: d['kabupaten_id'] as int?,
      namaKabupaten: d['nama_kabupaten'] as String?,
      kecamatanId: d['kecamatan_id'] as int?,
      namaKecamatan: d['nama_kecamatan'] as String?,
      desaId: d['desa_id'] as int?,
      namaDesa: d['nama_desa'] as String?,
      namaSaluran: d['nama_saluran'] as String?,
      daerahIrigasi: d['daerah_irigasi'] as String?,
      kondisiFisik: d['kondisi_fisik'] as String?,
      debitAir: d['debit_air'] as String?,
      latitude: d['latitude'] != null
          ? double.tryParse(d['latitude'].toString())
          : null,
      longitude: d['longitude'] != null
          ? double.tryParse(d['longitude'].toString())
          : null,
      fotoUrl: d['foto_url'] as String?,
      catatan: d['catatan'] as String?,
      catatanVerifikasi: d['catatan_verifikasi'] as String?,
      verifiedAt: d['verified_at'] as String?,
      createdAt: d['created_at'] as String?,
      updatedAt: d['updated_at'] as String?,
    );
  }

  /// Label status dalam Bahasa Indonesia.
  String get statusLabel {
    switch (status) {
      case 'Draf':
        return 'Draf';
      case 'Submitted':
        return 'Dikirim';
      case 'Diverifikasi':
        return 'Diverifikasi';
      case 'Ditolak':
        return 'Ditolak';
      case 'Diarsipkan':
        return 'Diarsipkan';
      default:
        return status;
    }
  }

  /// Judul ringkas untuk ditampilkan di card.
  String get judulRingkas {
    if (jenis == JenisLaporan.hama) {
      return namaOpt ?? 'Laporan Hama';
    }
    return namaSaluran ?? 'Laporan Irigasi';
  }

  /// Label jenis laporan.
  String get jenisLabel =>
      jenis == JenisLaporan.hama ? 'Hama/OPT' : 'Irigasi';

  bool get isEditable => status == 'Draf' || status == 'Ditolak';
  bool get isDitolak => status == 'Ditolak';
  bool get isDraf => status == 'Draf';
}
