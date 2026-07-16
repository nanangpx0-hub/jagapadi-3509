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

  factory NotificationItem.fromJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>? ?? j;
    final rawData = d['data'] as Map<String, dynamic>?;
    return NotificationItem(
      id: d['id'] as int? ?? 0,
      title: d['title'] as String? ?? d['judul'] as String? ?? '',
      body: d['body'] as String? ?? d['pesan'] as String? ?? '',
      isRead: (d['is_read'] as int? ?? 0) == 1 || (d['dibaca'] as bool? ?? false),
      createdAt: d['created_at'] as String? ?? d['tanggal'] as String?,
      entity: rawData?['entity'] as String?,
      laporanId: rawData?['laporan_id'] is int ? rawData!['laporan_id'] as int : int.tryParse('${rawData?['laporan_id'] ?? ''}'),
    );
  }
}
