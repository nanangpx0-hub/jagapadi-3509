class LaporanAlatSarana {
  final int id;
  final int? userId;
  final String? nomorLaporan;
  final String status;
  final String? tanggal;
  final int? kabupatenId;
  final String? namaKabupaten;
  final int? kecamatanId;
  final String? namaKecamatan;
  final int? desaId;
  final String? namaDesa;
  final String? namaAlat;
  final String? jenisSarana;
  final String? kondisi;
  final String? kapasitas;
  final int? tahunPengadaan;
  final double? latitude;
  final double? longitude;
  final String? fotoUrl;
  final String? catatan;
  final String? verifiedBy;
  final String? verifiedAt;
  final String? catatanVerifikasi;
  final String? createdAt;
  final String? updatedAt;

  LaporanAlatSarana({
    required this.id,
    this.userId,
    this.nomorLaporan,
    required this.status,
    this.tanggal,
    this.kabupatenId,
    this.namaKabupaten,
    this.kecamatanId,
    this.namaKecamatan,
    this.desaId,
    this.namaDesa,
    this.namaAlat,
    this.jenisSarana,
    this.kondisi,
    this.kapasitas,
    this.tahunPengadaan,
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

  factory LaporanAlatSarana.fromJson(Map<String, dynamic> j) {
    final data = j['data'] as Map<String, dynamic>? ?? j;
    return LaporanAlatSarana(
      id: data['id'] as int? ?? 0,
      userId: data['user_id'] as int?,
      nomorLaporan: data['nomor_laporan'] as String?,
      status: data['status'] as String? ?? 'Draf',
      tanggal: data['tanggal'] as String?,
      kabupatenId: data['kabupaten_id'] as int?,
      namaKabupaten: data['nama_kabupaten'] as String?,
      kecamatanId: data['kecamatan_id'] as int?,
      namaKecamatan: data['nama_kecamatan'] as String?,
      desaId: data['desa_id'] as int?,
      namaDesa: data['nama_desa'] as String?,
      namaAlat: data['nama_alat'] as String?,
      jenisSarana: data['jenis_sarana'] as String?,
      kondisi: data['kondisi'] as String?,
      kapasitas: data['kapasitas'] as String?,
      tahunPengadaan: data['tahun_pengadaan'] != null ? int.tryParse(data['tahun_pengadaan'].toString()) : null,
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
