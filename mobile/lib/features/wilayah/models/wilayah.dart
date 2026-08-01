class Kabupaten {
  final int id;
  final String nama;
  Kabupaten({required this.id, required this.nama});
  factory Kabupaten.fromJson(Map<String, dynamic> j) =>
      Kabupaten(id: j['id'] as int? ?? 0, nama: j['nama_kabupaten'] as String? ?? '');
}

class Kecamatan {
  final int id;
  final String nama;
  Kecamatan({required this.id, required this.nama});
  factory Kecamatan.fromJson(Map<String, dynamic> j) =>
      Kecamatan(id: j['id'] as int? ?? 0, nama: j['nama_kecamatan'] as String? ?? '');
}

class Desa {
  final int id;
  final String nama;
  Desa({required this.id, required this.nama});
  factory Desa.fromJson(Map<String, dynamic> j) =>
      Desa(id: j['id'] as int? ?? 0, nama: j['nama_desa'] as String? ?? '');
}
