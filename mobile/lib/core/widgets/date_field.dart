import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

/// Widget field tanggal dengan DatePicker.
///
/// Perbaikan dari versi sebelumnya:
/// - `TextEditingController` untuk tampilan dikelola dalam state (StatefulWidget),
///   bukan dibuat baru setiap `build()` — mencegah memory leak controller
///   yang menumpuk dan tidak pernah di-dispose.
/// - `didUpdateWidget` memperbarui teks tampilan saat parent mengubah [controller].
class DateField extends StatefulWidget {
  final TextEditingController controller; // menyimpan nilai YYYY-MM-DD
  final String label;
  final String? errorText;
  final ValueChanged<String>? onChanged;

  const DateField({
    super.key,
    required this.controller,
    this.label = 'Tanggal',
    this.errorText,
    this.onChanged,
  });

  @override
  State<DateField> createState() => _DateFieldState();
}

class _DateFieldState extends State<DateField> {
  /// Controller tampilan: menampilkan format "d MMMM yyyy" ke user.
  /// Dibuat sekali di initState dan di-dispose bersama widget.
  late final TextEditingController _displayCtrl;

  @override
  void initState() {
    super.initState();
    _displayCtrl =
        TextEditingController(text: _formatForDisplay(widget.controller.text));
    // Dengarkan perubahan dari parent (misalnya saat form di-populate dari API)
    widget.controller.addListener(_onSourceChanged);
  }

  @override
  void didUpdateWidget(DateField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_onSourceChanged);
      widget.controller.addListener(_onSourceChanged);
      _displayCtrl.text = _formatForDisplay(widget.controller.text);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_onSourceChanged);
    _displayCtrl.dispose();
    super.dispose();
  }

  void _onSourceChanged() {
    final display = _formatForDisplay(widget.controller.text);
    if (_displayCtrl.text != display) {
      _displayCtrl.text = display;
    }
  }

  /// Konversi "YYYY-MM-DD" → "d MMMM yyyy" (contoh: "16 Agustus 2026").
  String _formatForDisplay(String raw) {
    if (raw.isEmpty) return '';
    try {
      final dt = DateTime.parse(raw);
      return DateFormat('d MMMM yyyy', 'id_ID').format(dt);
    } catch (_) {
      return raw; // tampilkan apa adanya jika parse gagal
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final maxDate = now.add(const Duration(days: 1));
    DateTime initial;
    try {
      initial = widget.controller.text.isEmpty
          ? now
          : DateTime.parse(widget.controller.text);
      if (initial.isAfter(maxDate)) initial = maxDate;
    } catch (_) {
      initial = now;
    }

    DateTime? picked;
    try {
      picked = await showDatePicker(
        context: context,
        initialDate: initial,
        firstDate: DateTime(2020),
        lastDate: maxDate,
        locale: const Locale('id', 'ID'),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Pemilih tanggal tidak dapat dibuka. Coba lagi.')),
      );
      return;
    }

    if (picked != null && mounted) {
      final formatted = DateFormat('yyyy-MM-dd').format(picked);
      widget.controller.text = formatted; // memicu _onSourceChanged
      widget.onChanged?.call(formatted);
    }
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: _pickDate,
      borderRadius: BorderRadius.circular(8),
      child: IgnorePointer(
        child: TextFormField(
          controller: _displayCtrl,
          decoration: InputDecoration(
            labelText: widget.label,
            hintText: 'Pilih tanggal',
            errorText: widget.errorText,
            suffixIcon: const Icon(Icons.calendar_today, size: 20),
          ),
          validator: (_) {
            final raw = widget.controller.text.trim();
            if (raw.isEmpty) {
              return '${widget.label} wajib diisi';
            }
            final parsed = DateTime.tryParse(raw);
            if (parsed == null ||
                DateFormat('yyyy-MM-dd').format(parsed) != raw) {
              return 'Format tanggal tidak valid';
            }
            if (parsed.isBefore(DateTime(2020)) ||
                parsed.isAfter(DateTime.now().add(const Duration(days: 1)))) {
              return 'Tanggal di luar rentang yang diizinkan';
            }
            return null;
          },
        ),
      ),
    );
  }
}
