import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/pdf_export_service.dart';
import '../models/laporan_item.dart';
import '../../../core/theme.dart';

/// Bottom sheet pilihan ekspor PDF: Preview, Simpan, atau Bagikan.
class LaporanExportBottomSheet extends StatefulWidget {
  final List<LaporanItem> items;
  final String subtitle;

  const LaporanExportBottomSheet({
    super.key,
    required this.items,
    required this.subtitle,
  });

  static Future<void> show(
    BuildContext context, {
    required List<LaporanItem> items,
    required String subtitle,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (_) => LaporanExportBottomSheet(items: items, subtitle: subtitle),
    );
  }

  @override
  State<LaporanExportBottomSheet> createState() =>
      _LaporanExportBottomSheetState();
}

class _LaporanExportBottomSheetState extends State<LaporanExportBottomSheet> {
  bool _loading = false;
  String? _savedPath;
  String? _error;

  // ── PDF column definitions per jenis ─────────────────────────────────────

  static const _hamaHeaders = [
    'No.', 'Nomor Laporan', 'Tanggal', 'Status', 'OPT',
    'Keparahan', 'Luas (ha)', 'Kecamatan', 'Desa', 'Catatan',
  ];

  static const _irigasiHeaders = [
    'No.', 'Nomor Laporan', 'Tanggal', 'Status', 'Saluran',
    'Kondisi Fisik', 'Debit Air', 'Kecamatan', 'Desa', 'Catatan',
  ];

  static const _terpaduHeaders = [
    'No.', 'Nomor Laporan', 'Jenis', 'Tanggal', 'Status',
    'Ringkasan', 'Keparahan/Kondisi', 'Kecamatan', 'Desa',
  ];

  List<String> get _headers {
    final hamaOnly = widget.items.every((i) => i.jenis == JenisLaporan.hama);
    final irigasiOnly =
        widget.items.every((i) => i.jenis == JenisLaporan.irigasi);
    if (hamaOnly) return _hamaHeaders;
    if (irigasiOnly) return _irigasiHeaders;
    return _terpaduHeaders;
  }

  List<List<String>> get _rows {
    final hamaOnly = widget.items.every((i) => i.jenis == JenisLaporan.hama);
    final irigasiOnly =
        widget.items.every((i) => i.jenis == JenisLaporan.irigasi);

    return widget.items.asMap().entries.map((e) {
      final no = '${e.key + 1}';
      final i = e.value;
      if (hamaOnly) {
        return [
          no,
          i.nomorLaporan ?? '-',
          i.tanggal ?? '-',
          i.statusLabel,
          i.namaOpt ?? '-',
          i.tingkatKeparahan ?? '-',
          i.luasSerangan != null ? i.luasSerangan!.toStringAsFixed(2) : '-',
          i.namaKecamatan ?? '-',
          i.namaDesa ?? '-',
          _truncate(i.catatan),
        ];
      } else if (irigasiOnly) {
        return [
          no,
          i.nomorLaporan ?? '-',
          i.tanggal ?? '-',
          i.statusLabel,
          i.namaSaluran ?? '-',
          i.kondisiFisik ?? '-',
          i.debitAir ?? '-',
          i.namaKecamatan ?? '-',
          i.namaDesa ?? '-',
          _truncate(i.catatan),
        ];
      } else {
        final ringkasan = i.jenis == JenisLaporan.hama
            ? (i.namaOpt ?? '-')
            : (i.namaSaluran ?? '-');
        final detail = i.jenis == JenisLaporan.hama
            ? (i.tingkatKeparahan ?? '-')
            : (i.kondisiFisik ?? '-');
        return [
          no,
          i.nomorLaporan ?? '-',
          i.jenisLabel,
          i.tanggal ?? '-',
          i.statusLabel,
          ringkasan,
          detail,
          i.namaKecamatan ?? '-',
          i.namaDesa ?? '-',
        ];
      }
    }).toList();
  }

  String _truncate(String? s, [int max = 60]) {
    if (s == null || s.isEmpty) return '-';
    return s.length > max ? '${s.substring(0, max)}…' : s;
  }

  String get _title => 'Laporan JAGAPADI';

  Future<void> _doExport(_ExportMode mode) async {
    setState(() {
      _loading = true;
      _error = null;
      _savedPath = null;
    });

    try {
      switch (mode) {
        case _ExportMode.preview:
          await PdfExportService.exportAndPreview(
            title: _title,
            columnHeaders: _headers,
            rows: _rows,
            subtitle: widget.subtitle,
          );
          if (mounted) Navigator.of(context).pop();

        case _ExportMode.save:
          final now = DateFormat('yyyyMMdd').format(DateTime.now());
          final path = await PdfExportService.exportAndSave(
            title: _title,
            columnHeaders: _headers,
            rows: _rows,
            subtitle: widget.subtitle,
            filename: 'laporan_jagapadi_$now',
          );
          if (mounted) {
            setState(() => _savedPath = path);
          }

        case _ExportMode.share:
          await PdfExportService.exportAndShare(
            title: _title,
            columnHeaders: _headers,
            rows: _rows,
            subtitle: widget.subtitle,
          );
          if (mounted) Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        setState(() => _error = 'Gagal mengekspor: $e');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Handle
          Center(
            child: Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),

          // Judul
          Row(
            children: [
              const Icon(Icons.picture_as_pdf, color: Colors.red, size: 24),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Ekspor ke PDF',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                    Text(
                      '${widget.items.length} laporan · ${widget.subtitle}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          if (_loading) ...[
            const LinearProgressIndicator(),
            const SizedBox(height: 12),
            const Text('Membuat dokumen PDF…',
                style: TextStyle(color: AppColors.textSecondary)),
          ] else if (_error != null) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(Icons.error_outline, color: Colors.red.shade700),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(_error!,
                        style: TextStyle(color: Colors.red.shade700)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ] else if (_savedPath != null) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(Icons.check_circle, color: Colors.green.shade700),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'PDF tersimpan:\n$_savedPath',
                      style: TextStyle(
                          color: Colors.green.shade700, fontSize: 12),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],

          // Tombol aksi
          if (!_loading) ...[
            _ExportButton(
              key: const Key('btn_preview_pdf'),
              icon: Icons.preview,
              label: 'Preview PDF',
              subtitle: 'Tampilkan dokumen sebelum menyimpan',
              color: AppTheme.primaryColor,
              onTap: () => _doExport(_ExportMode.preview),
            ),
            const SizedBox(height: 8),
            _ExportButton(
              key: const Key('btn_simpan_pdf'),
              icon: Icons.download,
              label: 'Simpan ke Perangkat',
              subtitle: 'Disimpan di folder JAGAPADI_Export',
              color: Colors.green.shade700,
              onTap: () => _doExport(_ExportMode.save),
            ),
            const SizedBox(height: 8),
            _ExportButton(
              key: const Key('btn_bagikan_pdf'),
              icon: Icons.share,
              label: 'Bagikan PDF',
              subtitle: 'Kirim via WhatsApp, email, atau platform lain',
              color: Colors.teal.shade700,
              onTap: () => _doExport(_ExportMode.share),
            ),
          ],
        ],
      ),
    );
  }
}

enum _ExportMode { preview, save, share }

class _ExportButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ExportButton({
    super.key,
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Icon(icon, color: color, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label,
                        style: TextStyle(
                            fontWeight: FontWeight.w600,
                            color: color,
                            fontSize: 14)),
                    Text(subtitle,
                        style: const TextStyle(
                            fontSize: 11, color: AppColors.textSecondary)),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: color.withValues(alpha: 0.5)),
            ],
          ),
        ),
      ),
    );
  }
}
