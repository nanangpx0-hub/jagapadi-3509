class LaporanHama {
  final int id;
  final int? userId;
  final String? nomorLaporan;
  final String status;
  final String? tanggal;
  final int? masterOptId;
  final String? namaOpt;
  final int? kabupatenId;
  final String? namaKabupaten;
  final int? kecamatanId;
  final String? namaKecamatan;
  final int? desaId;
  final String? namaDesa;
  final String? lokasi;
  final String? alamatLengkap;
  final double? latitude;
  final double? longitude;
  final String? tingkatKeparahan;
  final String? metodePengukuran;
  final double? luasSerangan;
  final double? persentaseSerangan;
  final double? luasArealDiamati;
  final double? luasSeranganEstimasi;
  final double? populasi;
  final String? fotoUrl;
  final String? videoUrl;
  final String? catatan;
  final String? verifiedBy;
  final String? verifiedAt;
  final String? catatanVerifikasi;
  final String? createdAt;
  final String? updatedAt;

  LaporanHama({
    required this.id,
    this.userId,
    this.nomorLaporan,
    required this.status,
    this.tanggal,
    this.masterOptId,
    this.namaOpt,
    this.kabupatenId,
    this.namaKabupaten,
    this.kecamatanId,
    this.namaKecamatan,
    this.desaId,
    this.namaDesa,
    this.lokasi,
    this.alamatLengkap,
    this.latitude,
    this.longitude,
    this.tingkatKeparahan,
    this.metodePengukuran,
    this.luasSerangan,
    this.persentaseSerangan,
    this.luasArealDiamati,
    this.luasSeranganEstimasi,
    this.populasi,
    this.fotoUrl,
    this.videoUrl,
    this.catatan,
    this.verifiedBy,
    this.verifiedAt,
    this.catatanVerifikasi,
    this.createdAt,
    this.updatedAt,
  });

  factory LaporanHama.fromJson(Map<String, dynamic> j) {
    final data = j['data'] as Map<String, dynamic>? ?? j;
    return LaporanHama(
      id: data['id'] as int? ?? 0,
      userId: data['user_id'] as int?,
      nomorLaporan: data['nomor_laporan'] as String?,
      status: data['status'] as String? ?? 'Draf',
      tanggal: data['tanggal'] as String?,
      masterOptId: data['master_opt_id'] as int?,
      namaOpt: data['nama_opt'] as String?,
      kabupatenId: data['kabupaten_id'] as int?,
      namaKabupaten: data['nama_kabupaten'] as String?,
      kecamatanId: data['kecamatan_id'] as int?,
      namaKecamatan: data['nama_kecamatan'] as String?,
      desaId: data['desa_id'] as int?,
      namaDesa: data['nama_desa'] as String?,
      lokasi: data['lokasi'] as String?,
      alamatLengkap: data['alamat_lengkap'] as String?,
      latitude: data['latitude'] != null ? double.tryParse(data['latitude'].toString()) : null,
      longitude: data['longitude'] != null ? double.tryParse(data['longitude'].toString()) : null,
      tingkatKeparahan: data['tingkat_keparahan'] as String?,
      metodePengukuran: data['metode_pengukuran'] as String? ?? 'absolut',
      luasSerangan: data['luas_serangan'] != null ? double.tryParse(data['luas_serangan'].toString()) : null,
      persentaseSerangan: data['persentase_serangan'] != null ? double.tryParse(data['persentase_serangan'].toString()) : null,
      luasArealDiamati: data['luas_areal_diamati'] != null ? double.tryParse(data['luas_areal_diamati'].toString()) : null,
      luasSeranganEstimasi: data['luas_serangan_estimasi'] != null ? double.tryParse(data['luas_serangan_estimasi'].toString()) : null,
      populasi: data['populasi'] != null ? double.tryParse(data['populasi'].toString()) : null,
      fotoUrl: data['foto_url'] as String?,
      videoUrl: data['video_url'] as String?,
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
  bool get isPersentaseMode => metodePengukuran == 'persentase';

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

class OptOption {
  final int id;
  final String nama;
  final String jenis;
  OptOption({required this.id, required this.nama, required this.jenis});
  factory OptOption.fromJson(Map<String, dynamic> j) =>
      OptOption(id: j['id'] as int? ?? 0, nama: j['nama_opt'] as String? ?? '', jenis: j['jenis'] as String? ?? '');
}
