class LaporanPanen {
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
  final String? komoditas;
  final String? varietas;
  final double? luasPanen;
  final double? hasilPanen;
  final double? produktivitas;
  final String? musimTanam;
  final double? latitude;
  final double? longitude;
  final String? fotoUrl;
  final String? catatan;
  final String? verifiedBy;
  final String? verifiedAt;
  final String? catatanVerifikasi;
  final String? createdAt;
  final String? updatedAt;

  LaporanPanen({
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
    this.komoditas,
    this.varietas,
    this.luasPanen,
    this.hasilPanen,
    this.produktivitas,
    this.musimTanam,
    this.latitude,
    this.longitude,
    this.fotoUrl,
    this.catatan,
    this.verifiedBy,
    this.verifiedAt,
    this.catatanVerifikasi,
    this.createdAt,
    this.updatedAt,
  });

  factory LaporanPanen.fromJson(Map<String, dynamic> j) {
    final data = j['data'] as Map<String, dynamic>? ?? j;
    return LaporanPanen(
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
      komoditas: data['komoditas'] as String?,
      varietas: data['varietas'] as String?,
      luasPanen: data['luas_panen'] != null ? double.tryParse(data['luas_panen'].toString()) : null,
      hasilPanen: data['hasil_panen'] != null ? double.tryParse(data['hasil_panen'].toString()) : null,
      produktivitas: data['produktivitas'] != null ? double.tryParse(data['produktivitas'].toString()) : null,
      musimTanam: data['musim_tanam'] as String?,
      latitude: data['latitude'] != null ? double.tryParse(data['latitude'].toString()) : null,
      longitude: data['longitude'] != null ? double.tryParse(data['longitude'].toString()) : null,
      fotoUrl: data['foto_url'] as String?,
      catatan: data['catatan'] as String?,
      verifiedBy: data['verified_by']?.toString(),
      verifiedAt: data['verified_at'] as String?,
      catatanVerifikasi: data['catatan_verifikasi'] as String?,
      createdAt: data['created_at'] as String?,
      updatedAt: data['updated_at'] as String?,
    );
  }

  bool get isEditable => status == 'Draf' || status == 'Ditolak';
  bool get isSubmittable => status == 'Draf' || status == 'Ditolak';
  bool get isDitolak => status == 'Ditolak';
  bool get isDraf => status == 'Draf';

  String get statusLabel {
    switch (status) {
      case 'Draf': return 'Draf';
      case 'Submitted': return 'Dikirim';
      case 'Diverifikasi': return 'Diverifikasi';
      case 'Ditolak': return 'Ditolak';
      case 'Diarsipkan': return 'Diarsipkan';
      default: return status;
    }
  }
}
