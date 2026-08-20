import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../providers/laporan_terpadu_provider.dart';
import '../../../core/theme.dart';

/// Bottom sheet modal untuk memilih filter laporan terpadu.
/// Mengembalikan [LaporanFilter] yang baru via Navigator.pop().
class LaporanFilterSheet extends StatefulWidget {
  final LaporanFilter initial;

  const LaporanFilterSheet({super.key, required this.initial});

  /// Buka bottom sheet dan kembalikan filter yang dipilih.
  static Future<LaporanFilter?> show(
    BuildContext context,
    LaporanFilter current,
  ) {
    return showModalBottomSheet<LaporanFilter>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (_) => LaporanFilterSheet(initial: current),
    );
  }

  @override
  State<LaporanFilterSheet> createState() => _LaporanFilterSheetState();
}

class _LaporanFilterSheetState extends State<LaporanFilterSheet> {
  late String _jenis;
  late String _status;
  DateTime? _dari;
  DateTime? _sampai;

  static const _jenisOptions = [
    ('semua', 'Semua Jenis'),
    ('hama', 'Hama/OPT'),
    ('irigasi', 'Irigasi'),
  ];

  static const _statusOptions = [
    ('all', 'Semua Status'),
    ('Draf', 'Draf'),
    ('Submitted', 'Dikirim'),
    ('Diverifikasi', 'Diverifikasi'),
    ('Ditolak', 'Ditolak'),
    ('Diarsipkan', 'Diarsipkan'),
  ];

  @override
  void initState() {
    super.initState();
    _jenis = widget.initial.jenisKey;
    _status = widget.initial.statusKey;
    _dari = widget.initial.tanggalDari != null
        ? DateTime.tryParse(widget.initial.tanggalDari!)
        : null;
    _sampai = widget.initial.tanggalSampai != null
        ? DateTime.tryParse(widget.initial.tanggalSampai!)
        : null;
  }

  Future<void> _pickDate({required bool isDari}) async {
    final now = DateTime.now();
    final initial = isDari
        ? (_dari ?? now.subtract(const Duration(days: 30)))
        : (_sampai ?? now);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: now.add(const Duration(days: 1)),
      helpText: isDari ? 'Pilih Tanggal Awal' : 'Pilih Tanggal Akhir',
      locale: const Locale('id', 'ID'),
    );
    if (picked != null) {
      setState(() {
        if (isDari) {
          _dari = picked;
          // Pastikan tanggal akhir tidak sebelum awal
          if (_sampai != null && _sampai!.isBefore(picked)) {
            _sampai = picked;
          }
        } else {
          _sampai = picked;
          if (_dari != null && _dari!.isAfter(picked)) {
            _dari = picked;
          }
        }
      });
    }
  }

  void _resetFilter() {
    setState(() {
      _jenis = 'semua';
      _status = 'all';
      _dari = null;
      _sampai = null;
    });
  }

  void _apply() {
    final fmt = DateFormat('yyyy-MM-dd');
    Navigator.of(context).pop(
      LaporanFilter(
        jenisKey: _jenis,
        statusKey: _status,
        tanggalDari: _dari != null ? fmt.format(_dari!) : null,
        tanggalSampai: _sampai != null ? fmt.format(_sampai!) : null,
        searchQuery: widget.initial.searchQuery,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final df = DateFormat('dd MMM yyyy', 'id_ID');
    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Handle
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Filter Laporan',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
              ),
              TextButton(
                onPressed: _resetFilter,
                child: const Text('Reset', style: TextStyle(color: Colors.red)),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // ── Jenis Laporan ────────────────────────────────────────────────
          _SectionLabel('Jenis Laporan'),
          Wrap(
            spacing: 8,
            children: _jenisOptions.map((opt) {
              final selected = _jenis == opt.$1;
              return ChoiceChip(
                key: Key('jenis_${opt.$1}'),
                label: Text(opt.$2),
                selected: selected,
                selectedColor: AppTheme.primaryColor,
                labelStyle: TextStyle(
                  color: selected ? Colors.white : Colors.black87,
                  fontWeight:
                      selected ? FontWeight.bold : FontWeight.normal,
                ),
                onSelected: (_) => setState(() => _jenis = opt.$1),
              );
            }).toList(),
          ),
          const SizedBox(height: 16),

          // ── Status ───────────────────────────────────────────────────────
          _SectionLabel('Status Laporan'),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: _statusOptions.map((opt) {
              final selected = _status == opt.$1;
              return ChoiceChip(
                key: Key('status_${opt.$1}'),
                label: Text(opt.$2),
                selected: selected,
                selectedColor: AppTheme.primaryColor,
                labelStyle: TextStyle(
                  color: selected ? Colors.white : Colors.black87,
                  fontWeight:
                      selected ? FontWeight.bold : FontWeight.normal,
                ),
                onSelected: (_) => setState(() => _status = opt.$1),
              );
            }).toList(),
          ),
          const SizedBox(height: 16),

          // ── Rentang Tanggal ──────────────────────────────────────────────
          _SectionLabel('Rentang Tanggal'),
          Row(
            children: [
              Expanded(
                child: _DatePickerButton(
                  key: const Key('btn_dari'),
                  label: 'Dari',
                  value: _dari != null ? df.format(_dari!) : null,
                  onTap: () => _pickDate(isDari: true),
                  onClear: _dari != null
                      ? () => setState(() => _dari = null)
                      : null,
                ),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 8),
                child: Text('–', style: TextStyle(color: AppColors.textSecondary)),
              ),
              Expanded(
                child: _DatePickerButton(
                  key: const Key('btn_sampai'),
                  label: 'Sampai',
                  value: _sampai != null ? df.format(_sampai!) : null,
                  onTap: () => _pickDate(isDari: false),
                  onClear: _sampai != null
                      ? () => setState(() => _sampai = null)
                      : null,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),

          // ── Tombol Terapkan ─────────────────────────────────────────────
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              key: const Key('btn_terapkan'),
              onPressed: _apply,
              child: const Text('Terapkan Filter'),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;
  const _SectionLabel(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: AppColors.textSecondary,
        ),
      ),
    );
  }
}

class _DatePickerButton extends StatelessWidget {
  final String label;
  final String? value;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  const _DatePickerButton({
    super.key,
    required this.label,
    this.value,
    required this.onTap,
    this.onClear,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey.shade400),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          children: [
            const Icon(Icons.calendar_today, size: 16, color: AppColors.textSecondary),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                value ?? label,
                style: TextStyle(
                  fontSize: 12,
                  color: value != null
                      ? AppColors.textPrimary
                      : AppColors.textSecondary,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            if (onClear != null)
              GestureDetector(
                onTap: onClear,
                child: const Icon(Icons.close, size: 14, color: AppColors.textSecondary),
              ),
          ],
        ),
      ),
    );
  }
}
