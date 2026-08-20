import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/config.dart';
import '../../../core/permissions.dart';
import '../../../core/theme.dart';
import '../../../core/widgets/mini_map_preview.dart';
import '../../../core/widgets/status_timeline.dart';
import '../../auth/providers/auth_provider.dart';
import '../providers/laporan_pupuk_provider.dart';

class PupukDetailScreen extends StatefulWidget {
  final int id;
  const PupukDetailScreen({super.key, required this.id});

  @override
  State<PupukDetailScreen> createState() => _PupukDetailScreenState();
}

class _PupukDetailScreenState extends State<PupukDetailScreen> {
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
        (_) => context.read<LaporanPupukProvider>().loadDetail(widget.id));
  }

  String? _fullFotoUrl(String? url) {
    if (url == null || url.isEmpty) return null;
    if (url.startsWith('http')) return url;
    final base = AppConfig.baseUrl.replaceAll('/api/v1', '');
    return '$base/$url';
  }

  Future<void> _handleAdminVerify() async {
    final p = context.read<LaporanPupukProvider>();
    final res = await p.verify(widget.id);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil diverifikasi')));
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  Future<void> _handleAdminReject() async {
    final p = context.read<LaporanPupukProvider>();
    final alasanCtrl = TextEditingController();
    final alasan = await showDialog<String>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Tolak Laporan'),
        content: TextField(
          controller: alasanCtrl,
          decoration: const InputDecoration(
            labelText: 'Alasan penolakan',
            hintText: 'Minimal 10 karakter',
          ),
          maxLines: 3,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          ElevatedButton(onPressed: () {
            if (alasanCtrl.text.trim().length < 10) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Alasan minimal 10 karakter')));
              return;
            }
            Navigator.pop(context, alasanCtrl.text.trim());
          }, child: const Text('Tolak')),
        ],
      ),
    );
    if (alasan == null) return;
    final res = await p.reject(widget.id, alasan);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil ditolak')));
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  Future<void> _handleArchive() async {
    final p = context.read<LaporanPupukProvider>();
    final res = await p.archive(widget.id);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil diarsipkan')));
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  Future<void> _handleResubmit() async {
    final p = context.read<LaporanPupukProvider>();
    setState(() => _submitting = true);
    final res = await p.resubmit(widget.id);
    setState(() => _submitting = false);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil dikirim ulang')));
      p.loadDetail(widget.id);
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  Future<void> _handleSubmitDraft() async {
    final p = context.read<LaporanPupukProvider>();
    setState(() => _submitting = true);
    final res = await p.submit(widget.id);
    setState(() => _submitting = false);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil dikirim ke Admin')));
      p.loadDetail(widget.id);
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<LaporanPupukProvider>();
    final auth = context.watch<AuthProvider>();
    final l = p.detail;

    return Scaffold(
      appBar: AppBar(
        title: Text(l?.nomorLaporan ?? 'Detail Laporan Pupuk'),
        actions: [
          if (l != null && (auth.user?.can(ReportCapability.canVerifyReport) ?? false))
            PopupMenuButton(
              itemBuilder: (_) => [
                if (l.status == 'Submitted')
                  const PopupMenuItem(value: 'verify', child: Text('Verifikasi')),
                if (l.status == 'Submitted')
                  const PopupMenuItem(value: 'reject', child: Text('Tolak', style: TextStyle(color: Colors.red))),
                if (l.status == 'Diverifikasi')
                  const PopupMenuItem(value: 'archive', child: Text('Arsipkan')),
              ],
              onSelected: (v) async {
                if (v == 'verify') {
                  await _handleAdminVerify();
                } else if (v == 'reject') {
                  await _handleAdminReject();
                } else if (v == 'archive') {
                  await _handleArchive();
                }
              },
            ),
        ],
      ),
      body: p.loading
          ? const Center(child: CircularProgressIndicator())
          : l == null
              ? Center(child: Text(p.error ?? 'Gagal memuat detail'))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Timeline Status
                      StatusTimeline(
                        status: l.status,
                        createdAt: l.createdAt,
                        verifiedAt: l.verifiedAt,
                        catatanVerifikasi: l.catatanVerifikasi,
                      ),
                      const SizedBox(height: 16),

                      // Card Informasi Laporan
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Informasi Laporan',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                              ),
                              const SizedBox(height: 12),
                              _InfoRow('Nomor Laporan', l.nomorLaporan ?? 'Draf #${l.id}'),
                              _InfoRow('Status', l.statusLabel),
                              if (l.tanggal != null) _InfoRow('Tanggal Pemupukan', l.tanggal!),
                              if (l.jenisPupuk != null) _InfoRow('Jenis Pupuk', l.jenisPupuk!),
                              if (l.dosisPerHa != null) _InfoRow('Dosis per Hektar', '${l.dosisPerHa} kg/ha'),
                              if (l.luasPemupukan != null) _InfoRow('Luas Pemupukan', '${l.luasPemupukan} ha'),
                              if (l.metodeAplikasi != null) _InfoRow('Metode Aplikasi', l.metodeAplikasi!),
                              const Divider(height: 20),
                              if (l.namaKabupaten != null) _InfoRow('Kabupaten', l.namaKabupaten!),
                              if (l.namaKecamatan != null) _InfoRow('Kecamatan', l.namaKecamatan!),
                              if (l.namaDesa != null) _InfoRow('Desa', l.namaDesa!),
                              if (l.catatan != null) _InfoRow('Catatan Petugas', l.catatan!),
                              if (l.catatanVerifikasi != null)
                                _InfoRow(
                                  'Catatan Verifikasi',
                                  l.catatanVerifikasi!,
                                  valueColor: l.status == 'Ditolak' ? Colors.red : Colors.green,
                                ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Mini Map Preview
                      if (l.latitude != null && l.longitude != null) ...[
                        MiniMapPreview(
                          latitude: l.latitude!,
                          longitude: l.longitude!,
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Foto Laporan
                      if (_fullFotoUrl(l.fotoUrl) != null) ...[
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Foto Lapangan',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                ),
                                const SizedBox(height: 12),
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(8),
                                  child: Image.network(
                                    _fullFotoUrl(l.fotoUrl)!,
                                    height: 220,
                                    width: double.infinity,
                                    fit: BoxFit.cover,
                                    errorBuilder: (_, __, ___) => const Text('Foto tidak dapat dimuat'),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 24),
                      ],

                      // Explicit Action Buttons for Field Officer (Petugas)
                      if ((auth.user?.can(ReportCapability.canSubmitReport) ?? false)) ...[
                        if (l.isDraf) ...[
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton.icon(
                                  onPressed: () => context.push('/pupuk/${widget.id}/edit').then((_) => p.loadDetail(widget.id)),
                                  icon: const Icon(Icons.edit, size: 18),
                                  label: const Text('Edit Draf'),
                                  style: OutlinedButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: ElevatedButton.icon(
                                  onPressed: _submitting ? null : _handleSubmitDraft,
                                  icon: const Icon(Icons.send, size: 18),
                                  label: Text(_submitting ? 'Mengirim...' : 'Kirim Sekarang'),
                                ),
                              ),
                            ],
                          ),
                        ],
                        if (l.isDitolak) ...[
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton.icon(
                                  onPressed: () => context.push('/pupuk/${widget.id}/edit').then((_) => p.loadDetail(widget.id)),
                                  icon: const Icon(Icons.edit, size: 18),
                                  label: const Text('Edit & Perbaiki'),
                                  style: OutlinedButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: ElevatedButton.icon(
                                  onPressed: _submitting ? null : _handleResubmit,
                                  icon: const Icon(Icons.refresh, size: 18),
                                  label: Text(_submitting ? 'Mengirim...' : 'Kirim Ulang'),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;
  const _InfoRow(this.label, this.value, {this.valueColor});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 140,
            child: Text(
              label,
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(fontWeight: FontWeight.w500, color: valueColor),
            ),
          ),
        ],
      ),
    );
  }
}
