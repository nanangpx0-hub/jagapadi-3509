class UsulanOpt {
  final int id;
  final String? namaLokal;
  final String? namaNasional;
  final String jenis;
  final String? komoditas;
  final String? tanggalDitemukan;
  final int? kabupatenId;
  final int? kecamatanId;
  final int? desaId;
  final String? alamatLokasi;
  final double? latitude;
  final double? longitude;
  final String? bagianTerserang;
  final String? polaGejala;
  final double? estimasiTerdampak;
  final String? satuanTerdampak;
  final String? tingkatKeyakinan;
  final String? sumberIdentifikasi;
  final String? ciriCiri;
  final String status;
  final String? catatanReview;
  final int? masterOptId;
  final List<UsulanPhoto> photos;
  final List<UsulanHistory> history;
  final String? createdAt;

  UsulanOpt({
    required this.id,
    this.namaLokal,
    this.namaNasional,
    required this.jenis,
    this.komoditas,
    this.tanggalDitemukan,
    this.kabupatenId,
    this.kecamatanId,
    this.desaId,
    this.alamatLokasi,
    this.latitude,
    this.longitude,
    this.bagianTerserang,
    this.polaGejala,
    this.estimasiTerdampak,
    this.satuanTerdampak,
    this.tingkatKeyakinan,
    this.sumberIdentifikasi,
    this.ciriCiri,
    required this.status,
    this.catatanReview,
    this.masterOptId,
    this.photos = const [],
    this.history = const [],
    this.createdAt,
  });

  factory UsulanOpt.fromJson(Map<String, dynamic> j) {
    final photosRaw = j['photos'] as List<dynamic>? ?? [];
    final historyRaw = j['history'] as List<dynamic>? ?? [];
    return UsulanOpt(
      id: j['id'] as int? ?? 0,
      namaLokal: j['nama_lokal'] as String?,
      namaNasional: j['nama_nasional'] as String?,
      jenis: j['jenis'] as String? ?? 'hama',
      komoditas: j['komoditas'] as String?,
      tanggalDitemukan: j['tanggal_ditemukan'] as String?,
      kabupatenId: j['kabupaten_id'] as int?,
      kecamatanId: j['kecamatan_id'] as int?,
      desaId: j['desa_id'] as int?,
      alamatLokasi: j['alamat_lokasi'] as String?,
      latitude: j['latitude'] != null ? double.tryParse(j['latitude'].toString()) : null,
      longitude: j['longitude'] != null ? double.tryParse(j['longitude'].toString()) : null,
      bagianTerserang: j['bagian_terserang'] as String?,
      polaGejala: j['pola_gejala'] as String?,
      estimasiTerdampak: j['estimasi_terdampak'] != null ? double.tryParse(j['estimasi_terdampak'].toString()) : null,
      satuanTerdampak: j['satuan_terdampak'] as String?,
      tingkatKeyakinan: j['tingkat_keyakinan'] as String?,
      sumberIdentifikasi: j['sumber_identifikasi'] as String?,
      ciriCiri: j['ciri_ciri'] as String?,
      status: j['status'] as String? ?? 'Draf',
      catatanReview: j['catatan_review'] as String?,
      masterOptId: j['master_opt_id'] as int?,
      photos: photosRaw
          .map((e) => UsulanPhoto.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      history: historyRaw
          .map((e) => UsulanHistory.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      createdAt: j['created_at'] as String?,
    );
  }

  bool get isEditable => status == 'Draf' || status == 'Perlu Perbaikan';
  bool get isSubmittable => status == 'Draf';
  bool get isResubmittable => status == 'Perlu Perbaikan';
  bool get needsRevision => status == 'Perlu Perbaikan';

  String get statusLabel {
    switch (status) {
      case 'Draf': return 'Draf';
      case 'Menunggu Review': return 'Menunggu Review';
      case 'Perlu Perbaikan': return 'Perlu Perbaikan';
      case 'Disetujui': return 'Disetujui';
      case 'Digabungkan': return 'Digabungkan';
      case 'Ditolak Permanen': return 'Ditolak';
      default: return status;
    }
  }
}

class UsulanPhoto {
  final int id;
  final String? url;
  final String? caption;

  UsulanPhoto({required this.id, this.url, this.caption});

  factory UsulanPhoto.fromJson(Map<String, dynamic> j) => UsulanPhoto(
        id: j['id'] as int? ?? 0,
        url: j['url'] as String?,
        caption: j['caption'] as String?,
      );
}

class UsulanHistory {
  final String? fromStatus;
  final String toStatus;
  final String? catatan;
  final String? changedAt;

  UsulanHistory({this.fromStatus, required this.toStatus, this.catatan, this.changedAt});

  factory UsulanHistory.fromJson(Map<String, dynamic> j) => UsulanHistory(
        fromStatus: j['from_status'] as String?,
        toStatus: j['to_status'] as String? ?? '',
        catatan: j['catatan'] as String?,
        changedAt: j['changed_at'] as String?,
      );
}
