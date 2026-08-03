import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/config.dart';
import '../../../core/theme.dart';
import '../../auth/providers/auth_provider.dart';
import '../providers/laporan_hama_provider.dart';

class HamaDetailScreen extends StatefulWidget {
  final int id;
  const HamaDetailScreen({super.key, required this.id});

  @override
  State<HamaDetailScreen> createState() => _HamaDetailScreenState();
}

class _HamaDetailScreenState extends State<HamaDetailScreen> {
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
        (_) => context.read<LaporanHamaProvider>().loadDetail(widget.id));
  }

  String? _fullFotoUrl(String? url) {
    if (url == null || url.isEmpty) return null;
    if (url.startsWith('http')) return url;
    final base = AppConfig.baseUrl.replaceAll('/api/v1', '');
    return '$base/$url';
  }

  Future<void> _handleAdminVerify() async {
    final p = context.read<LaporanHamaProvider>();
    final res = await p.verify(widget.id);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil diverifikasi')));
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  Future<void> _handleAdminReject() async {
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
    final p = context.read<LaporanHamaProvider>();
    final res = await p.reject(widget.id, alasan);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil ditolak')));
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  Future<void> _handleArchive() async {
    final p = context.read<LaporanHamaProvider>();
    final res = await p.archive(widget.id);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Laporan berhasil diarsipkan')));
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  Future<void> _handleResubmit() async {
    final p = context.read<LaporanHamaProvider>();
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

  @override
  Widget build(BuildContext context) {
    final p = context.watch<LaporanHamaProvider>();
    final auth = context.watch<AuthProvider>();
    final l = p.detail;

    return Scaffold(
      appBar: AppBar(
        title: Text(l?.nomorLaporan ?? 'Detail Laporan'),
        actions: [
          if (l != null && !auth.isAdmin && l.isEditable)
            PopupMenuButton(itemBuilder: (_) => [
              if (l.isSubmittable)
                const PopupMenuItem(value: 'submit', child: Text('Kirim Laporan')),
              if (l.isDraf || l.isDitolak)
                const PopupMenuItem(value: 'edit', child: Text('Edit')),
              if (l.isDraf)
                const PopupMenuItem(value: 'delete', child: Text('Hapus', style: TextStyle(color: Colors.red))),
            ], onSelected: (v) => _handleAction(v, context)),
          if (l != null && auth.isAdmin)
            PopupMenuButton(itemBuilder: (_) => [
              if (l.status == 'Submitted')
                const PopupMenuItem(value: 'verify', child: Text('Verifikasi')),
              if (l.status == 'Submitted')
                const PopupMenuItem(value: 'reject', child: Text('Tolak', style: TextStyle(color: Colors.red))),
              if (l.status == 'Diverifikasi')
                const PopupMenuItem(value: 'archive', child: Text('Arsipkan')),
            ], onSelected: (v) async {
              if (v == 'verify') {
                await _handleAdminVerify();
              } else if (v == 'reject') await _handleAdminReject();
              else if (v == 'archive') await _handleArchive();
            }),
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
                      _InfoRow('Status', l.statusLabel),
                      _InfoRow('Nomor', l.nomorLaporan ?? '-'),
                      if (l.tanggal != null) _InfoRow('Tanggal', l.tanggal!),
                      if (l.namaOpt != null) _InfoRow('OPT', l.namaOpt!),
                      if (l.namaKabupaten != null) _InfoRow('Kabupaten', l.namaKabupaten!),
                      if (l.namaKecamatan != null) _InfoRow('Kecamatan', l.namaKecamatan!),
                      if (l.namaDesa != null) _InfoRow('Desa', l.namaDesa!),
                      if (l.lokasi != null) _InfoRow('Lokasi', l.lokasi!),
                      if (l.tingkatKeparahan != null) _InfoRow('Tingkat Keparahan', l.tingkatKeparahan!),
                      if (l.luasSerangan != null) _InfoRow('Luas Serangan', '${l.luasSerangan} ha'),
                      if (l.populasi != null) _InfoRow('Populasi', '${l.populasi}'),
                      if (l.latitude != null) _InfoRow('Latitude', '${l.latitude}'),
                      if (l.longitude != null) _InfoRow('Longitude', '${l.longitude}'),
                      if (l.catatan != null) _InfoRow('Catatan', l.catatan!),
                      if (l.catatanVerifikasi != null) ...[
                        const Divider(height: 24),
                        _InfoRow('Catatan Verifikasi', l.catatanVerifikasi!,
                            valueColor: l.status == 'Ditolak' ? Colors.red : Colors.green),
                      ],
                      if (_fullFotoUrl(l.fotoUrl) != null) ...[
                        const SizedBox(height: 12),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.network(_fullFotoUrl(l.fotoUrl)!,
                              height: 200, width: double.infinity, fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) => const Text('Foto tidak tersedia')),
                        ),
                      ],
                      if (l.isDitolak && !auth.isAdmin) ...[
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: _submitting ? null : () => Navigator.pushNamed(context, '/hama/${widget.id}/edit'),
                            icon: const Icon(Icons.edit, size: 18),
                            label: const Text('Edit & Perbaiki'),
                          ),
                        ),
                        const SizedBox(height: 8),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: _submitting ? null : _handleResubmit,
                            icon: const Icon(Icons.send, size: 18),
                            label: Text(_submitting ? 'Mengirim...' : 'Kirim Ulang'),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
    );
  }

  Future<void> _handleAction(String action, BuildContext ctx) async {
    final p = ctx.read<LaporanHamaProvider>();
    if (action == 'submit') {
      setState(() => _submitting = true);
      final res = await p.submit(widget.id);
      setState(() => _submitting = false);
      if (res != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Laporan berhasil dikirim')));
        p.loadDetail(widget.id);
      } else if (p.error != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(p.error!)));
      }
    } else if (action == 'edit') {
      Navigator.pushNamed(context, '/hama/${widget.id}/edit');
    } else if (action == 'delete') {
      final ok = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Hapus laporan?'),
          content: const Text('Tindakan ini tidak dapat dibatalkan.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
            TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Hapus', style: TextStyle(color: Colors.red))),
          ],
        ),
      );
      if (ok == true && mounted) {
        await p.delete(widget.id);
        if (mounted) Navigator.pop(context);
      }
    }
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
            child: Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
          ),
          Expanded(child: Text(value, style: TextStyle(fontWeight: FontWeight.w500, color: valueColor))),
        ],
      ),
    );
  }
}
