import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../providers/usulan_opt_provider.dart';
import 'usulan_opt_form_screen.dart';

class UsulanOptDetailScreen extends StatefulWidget {
  final int id;
  const UsulanOptDetailScreen({super.key, required this.id});

  @override
  State<UsulanOptDetailScreen> createState() => _UsulanOptDetailScreenState();
}

class _UsulanOptDetailScreenState extends State<UsulanOptDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<UsulanOptProvider>().loadDetail(widget.id);
    });
  }

  Future<void> _action(UsulanOptProvider p, Future<bool> Function() fn,
      String successMsg) async {
    final ok = await fn();
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(successMsg)),
      );
      await p.loadDetail(widget.id);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(p.error ?? 'Aksi gagal')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<UsulanOptProvider>();
    final d = p.detail;

    return Scaffold(
      appBar: AppBar(
        title: Text(d?.namaLokal ?? 'Detail Usulan OPT'),
        actions: [
          if (d != null && d.isEditable)
            IconButton(
              icon: const Icon(Icons.edit),
              tooltip: 'Edit',
              onPressed: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute(
                      builder: (_) => UsulanOptFormScreen(id: d.id)),
                );
                if (context.mounted) p.loadDetail(widget.id);
              },
            ),
        ],
      ),
      body: p.loading && d == null
          ? const Center(child: CircularProgressIndicator())
          : d == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(p.error ?? 'Usulan tidak ditemukan'),
                      const SizedBox(height: AppSpacing.md),
                      FilledButton.icon(
                        onPressed: () => p.loadDetail(widget.id),
                        icon: const Icon(Icons.refresh),
                        label: const Text('Coba Lagi'),
                      ),
                    ],
                  ),
                )
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _StatusBanner(status: d.status),
                      if (d.needsRevision && d.catatanReview != null) ...[
                        const SizedBox(height: AppSpacing.md),
                        Card(
                          color: Theme.of(context)
                              .colorScheme
                              .errorContainer,
                          child: Padding(
                            padding: const EdgeInsets.all(AppSpacing.md),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Icon(Icons.warning,
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onErrorContainer),
                                    const SizedBox(width: 8),
                                    Text(
                                      'Perlu Perbaikan — Catatan Admin',
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onErrorContainer,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: AppSpacing.sm),
                                Text(
                                  d.catatanReview!,
                                  style: TextStyle(
                                    color: Theme.of(context)
                                        .colorScheme
                                        .onErrorContainer,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                      _Section(
                        title: 'Identifikasi',
                        children: [
                          _Row('Nama Lokal', d.namaLokal),
                          _Row('Nama Nasional', d.namaNasional),
                          _Row('Jenis', d.jenis),
                          _Row('Komoditas', d.komoditas),
                          _Row('Ciri-ciri', d.ciriCiri),
                          _Row('Tanggal Ditemukan', d.tanggalDitemukan),
                        ],
                      ),
                      _Section(
                        title: 'Lokasi',
                        children: [
                          _Row('Alamat', d.alamatLokasi),
                          _Row('Latitude', d.latitude?.toString()),
                          _Row('Longitude', d.longitude?.toString()),
                        ],
                      ),
                      _Section(
                        title: 'Detail Serangan',
                        children: [
                          _Row('Bagian Terserang', d.bagianTerserang),
                          _Row('Pola Gejala', d.polaGejala),
                          _Row('Estimasi Terdampak',
                              '${d.estimasiTerdampak ?? '-'} ${d.satuanTerdampak ?? ''}'),
                          _Row('Tingkat Keyakinan', d.tingkatKeyakinan),
                          _Row('Sumber Identifikasi', d.sumberIdentifikasi),
                        ],
                      ),
                      if (d.photos.isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.md),
                        Text('Foto Bukti (${d.photos.length})',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: AppSpacing.sm),
                        SizedBox(
                          height: 120,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: d.photos.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(width: AppSpacing.sm),
                            itemBuilder: (context, i) => ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: d.photos[i].url != null
                                  ? Image.network(
                                      d.photos[i].url!,
                                      width: 120,
                                      height: 120,
                                      fit: BoxFit.cover,
                                      errorBuilder: (_, __, ___) =>
                                          const SizedBox(
                                        width: 120,
                                        height: 120,
                                        child: ColoredBox(
                                          color: Colors.grey,
                                          child: Icon(Icons.broken_image),
                                        ),
                                      ),
                                    )
                                  : const SizedBox(
                                      width: 120,
                                      height: 120,
                                      child: ColoredBox(
                                          color: Colors.grey,
                                          child: Icon(Icons.image)),
                                    ),
                            ),
                          ),
                        ),
                      ],
                      if (d.history.isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.lg),
                        Text('Riwayat Status',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: AppSpacing.sm),
                        ...d.history.reversed.map((h) => ListTile(
                              dense: true,
                              leading: const Icon(Icons.history, size: 20),
                              title: Text(
                                  '${h.fromStatus ?? '—'} → ${h.toStatus}'),
                              subtitle: h.catatan != null
                                  ? Text(h.catatan!)
                                  : null,
                              trailing: h.changedAt != null
                                  ? Text(
                                      h.changedAt!.substring(0, 16),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall,
                                    )
                                  : null,
                            )),
                      ],
                      const SizedBox(height: AppSpacing.xl),
                      if (d.isSubmittable)
                        FilledButton.icon(
                          onPressed: p.loading
                              ? null
                              : () => _action(
                                  p,
                                  () => p.submitDraft(d.id),
                                  'Usulan terkirim untuk review.'),
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(double.infinity, 52),
                          ),
                          icon: const Icon(Icons.send),
                          label: const Text('Kirim untuk Review'),
                        ),
                      if (d.isResubmittable)
                        FilledButton.icon(
                          onPressed: p.loading
                              ? null
                              : () => _action(
                                  p,
                                  () => p.resubmit(d.id),
                                  'Usulan dikirim ulang untuk review.'),
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(double.infinity, 52),
                          ),
                          icon: const Icon(Icons.replay),
                          label: const Text('Kirim Ulang untuk Review'),
                        ),
                      const SizedBox(height: AppSpacing.xl),
                    ],
                  ),
                ),
    );
  }
}

class _StatusBanner extends StatelessWidget {
  final String status;
  const _StatusBanner({required this.status});

  Color get _color {
    switch (status) {
      case 'Draf':
        return Colors.grey;
      case 'Menunggu Review':
        return Colors.blue;
      case 'Perlu Perbaikan':
        return Colors.orange;
      case 'Disetujui':
        return Colors.green;
      case 'Digabungkan':
        return Colors.teal;
      case 'Ditolak Permanen':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: _color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _color),
      ),
      child: Row(
        children: [
          Icon(Icons.info_outline, color: _color),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              'Status: ${switch (status) {
                'Draf' => 'Draf (belum dikirim)',
                'Menunggu Review' => 'Menunggu review Admin',
                'Perlu Perbaikan' => 'Perlu perbaikan — baca catatan Admin',
                'Disetujui' => 'Disetujui dan menjadi master OPT',
                'Digabungkan' => 'Digabungkan ke master OPT yang ada',
                'Ditolak Permanen' => 'Ditolak permanen',
                _ => status,
              }}',
              style: TextStyle(color: _color, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  final String title;
  final List<Widget> children;
  const _Section({required this.title, required this.children});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title,
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: AppSpacing.sm),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  final String label;
  final String? value;
  const _Row(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 140,
            child: Text(label,
                style: const TextStyle(color: Colors.grey, fontSize: 13)),
          ),
          Expanded(
            child: Text(value ?? '-',
                style: const TextStyle(fontWeight: FontWeight.w500)),
          ),
        ],
      ),
    );
  }
}
