class Kabupaten {
  final int id;
  final String nama;
  Kabupaten({required this.id, required this.nama});
  factory Kabupaten.fromJson(Map<String, dynamic> j) => Kabupaten(
      id: _parseId(j['id']), nama: j['nama_kabupaten']?.toString() ?? '');
}

class Kecamatan {
  final int id;
  final String nama;
  Kecamatan({required this.id, required this.nama});
  factory Kecamatan.fromJson(Map<String, dynamic> j) => Kecamatan(
      id: _parseId(j['id']), nama: j['nama_kecamatan']?.toString() ?? '');
}

class Desa {
  final int id;
  final String nama;
  Desa({required this.id, required this.nama});
  factory Desa.fromJson(Map<String, dynamic> j) =>
      Desa(id: _parseId(j['id']), nama: j['nama_desa']?.toString() ?? '');
}

int _parseId(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}
