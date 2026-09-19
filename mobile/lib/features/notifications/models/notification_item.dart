/// Satu item notifikasi in-app.
class NotificationItem {
  final int id;
  final String title;
  final String body;
  final bool isRead;
  final String? createdAt;
  final String? entity;
  final int? laporanId;

  NotificationItem({
    required this.id,
    required this.title,
    required this.body,
    this.isRead = false,
    this.createdAt,
    this.entity,
    this.laporanId,
  });

  /// Route detail laporan jika notifikasi berasal dari modul yang dikenal.
  /// Mengembalikan null untuk entity tidak dikenal (mis. feedback) agar UI
  /// menampilkan pesan "detail tidak tersedia" daripada 404/blank screen.
  String? get reportRoute {
    const moduleByEntity = <String, String>{
      'hama': 'hama',
      'irigasi': 'irigasi',
      'pupuk': 'pupuk',
      'panen': 'panen',
      'cuaca': 'cuaca',
      'alat_sarana': 'alat-sarana',
    };
    final module = moduleByEntity[entity];
    if (module == null || laporanId == null) return null;
    return '/$module/$laporanId';
  }

  factory NotificationItem.fromJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>? ?? j;
    final rawData = d['data'] as Map<String, dynamic>?;
    return NotificationItem(
      id: d['id'] as int? ?? 0,
      title: d['title'] as String? ?? d['judul'] as String? ?? '',
      body: d['body'] as String? ?? d['pesan'] as String? ?? '',
      // read_at adalah sumber kebenaran server (docs/API.md):
      // null = belum dibaca; string timestamp apa pun = sudah dibaca.
      // Tidak lagi menebak dari is_read/dibaca yang tidak dipancarkan API.
      isRead: d['read_at'] != null,
      createdAt: d['created_at'] as String? ?? d['tanggal'] as String?,
      entity: rawData?['entity'] as String?,
      laporanId: rawData?['laporan_id'] is int
          ? rawData!['laporan_id'] as int
          : int.tryParse('${rawData?['laporan_id'] ?? ''}'),
    );
  }
}
