class LaporanIrigasi {
  final int id;
  final String? nomorLaporan;
  final String status;
  final String? tanggal;
  final int? kabupatenId;
  final String? namaKabupaten;
  final int? kecamatanId;
  final String? namaKecamatan;
  final int? desaId;
  final String? namaDesa;
  final String? namaSaluran;
  final String? daerahIrigasi;
  final double? latitude;
  final double? longitude;
  final String? kondisiFisik;
  final String? debitAir;
  final String? fotoUrl;
  final String? catatan;
  final String? verifiedBy;
  final String? verifiedAt;
  final String? catatanVerifikasi;
  final String? createdAt;

  LaporanIrigasi({
    required this.id,
    this.nomorLaporan,
    required this.status,
    this.tanggal,
    this.kabupatenId,
    this.namaKabupaten,
    this.kecamatanId,
    this.namaKecamatan,
    this.desaId,
    this.namaDesa,
    this.namaSaluran,
    this.daerahIrigasi,
    this.latitude,
    this.longitude,
    this.kondisiFisik,
    this.debitAir,
    this.fotoUrl,
    this.catatan,
    this.verifiedBy,
    this.verifiedAt,
    this.catatanVerifikasi,
    this.createdAt,
  });

  factory LaporanIrigasi.fromJson(Map<String, dynamic> j) {
    final data = j['data'] as Map<String, dynamic>? ?? j;
    return LaporanIrigasi(
      id: data['id'] as int? ?? 0,
      nomorLaporan: data['nomor_laporan'] as String?,
      status: data['status'] as String? ?? 'Draf',
      tanggal: data['tanggal'] as String?,
      kabupatenId: data['kabupaten_id'] as int?,
      namaKabupaten: data['nama_kabupaten'] as String?,
      kecamatanId: data['kecamatan_id'] as int?,
      namaKecamatan: data['nama_kecamatan'] as String?,
      desaId: data['desa_id'] as int?,
      namaDesa: data['nama_desa'] as String?,
      namaSaluran: data['nama_saluran'] as String?,
      daerahIrigasi: data['daerah_irigasi'] as String?,
      latitude: data['latitude'] != null
          ? double.tryParse(data['latitude'].toString())
          : null,
      longitude: data['longitude'] != null
          ? double.tryParse(data['longitude'].toString())
          : null,
      kondisiFisik: data['kondisi_fisik'] as String?,
      debitAir: data['debit_air'] as String?,
      fotoUrl: data['foto_url'] as String?,
      catatan: data['catatan'] as String?,
      verifiedBy: data['verified_by']?.toString(),
      verifiedAt: data['verified_at'] as String?,
      catatanVerifikasi: data['catatan_verifikasi'] as String?,
      createdAt: data['created_at'] as String?,
    );
  }

  bool get isEditable => status == 'Draf' || status == 'Ditolak';
  bool get isSubmittable => status == 'Draf' || status == 'Ditolak';
  bool get isDitolak => status == 'Ditolak';

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
}
