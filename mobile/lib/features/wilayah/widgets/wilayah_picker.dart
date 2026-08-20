import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/wilayah.dart';
import '../providers/wilayah_provider.dart';

class WilayahPicker extends StatefulWidget {
  final int? kabupatenId;
  final int? kecamatanId;
  final int? desaId;
  final ValueChanged<int?> onKabupatenChanged;
  final ValueChanged<int?> onKecamatanChanged;
  final ValueChanged<int?> onDesaChanged;
  final String? errorKabupaten;
  final String? errorKecamatan;
  final String? errorDesa;

  const WilayahPicker({
    super.key,
    this.kabupatenId,
    this.kecamatanId,
    this.desaId,
    required this.onKabupatenChanged,
    required this.onKecamatanChanged,
    required this.onDesaChanged,
    this.errorKabupaten,
    this.errorKecamatan,
    this.errorDesa,
  });

  @override
  State<WilayahPicker> createState() => _WilayahPickerState();
}

class _WilayahPickerState extends State<WilayahPicker> {
  int? _kabId, _kecId, _desaId;

  @override
  void initState() {
    super.initState();
    _kabId = widget.kabupatenId;
    _kecId = widget.kecamatanId;
    _desaId = widget.desaId;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final w = context.read<WilayahProvider>();
      w.loadKabupaten();
      if (_kabId != null) w.loadKecamatan(_kabId!);
      if (_kecId != null) w.loadDesa(_kecId!);
    });
  }

  @override
  void didUpdateWidget(covariant WilayahPicker oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.kabupatenId != oldWidget.kabupatenId ||
        widget.kecamatanId != oldWidget.kecamatanId ||
        widget.desaId != oldWidget.desaId) {
      final shouldLoadKecamatan = widget.kabupatenId != _kabId;
      final shouldLoadDesa = widget.kecamatanId != _kecId;
      setState(() {
        _kabId = widget.kabupatenId;
        _kecId = widget.kecamatanId;
        _desaId = widget.desaId;
      });
      final w = context.read<WilayahProvider>();
      if (shouldLoadKecamatan && _kabId != null) w.loadKecamatan(_kabId!);
      if (shouldLoadDesa && _kecId != null) w.loadDesa(_kecId!);
    }
  }

  @override
  Widget build(BuildContext context) {
    final w = context.watch<WilayahProvider>();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Dropdown(
          label: 'Kabupaten',
          value: _kabId,
          items: w.kabupatenList,
          loading: w.loading,
          errorText: widget.errorKabupaten,
          onChanged: (v) {
            setState(() {
              _kabId = v;
              _kecId = null;
              _desaId = null;
            });
            widget.onKabupatenChanged(v);
            if (v != null) w.loadKecamatan(v);
          },
          textFn: (e) => (e).nama,
        ),
        const SizedBox(height: 12),
        _Dropdown(
          label: 'Kecamatan',
          value: _kecId,
          items: w.kecamatanList,
          loading: w.loadingKecamatan,
          enabled: _kabId != null,
          errorText: widget.errorKecamatan,
          onChanged: (v) {
            setState(() {
              _kecId = v;
              _desaId = null;
            });
            widget.onKecamatanChanged(v);
            if (v != null) w.loadDesa(v);
          },
          textFn: (e) => (e).nama,
        ),
        const SizedBox(height: 12),
        _Dropdown(
          label: 'Desa',
          value: _desaId,
          items: w.desaList,
          loading: w.loadingDesa,
          enabled: _kecId != null,
          errorText: widget.errorDesa,
          onChanged: (v) {
            setState(() => _desaId = v);
            widget.onDesaChanged(v);
          },
          textFn: (e) => (e).nama,
        ),
        if (w.error != null) ...[
          const SizedBox(height: 8),
          Text(
            w.error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: w.loading || w.loadingKecamatan || w.loadingDesa
                  ? null
                  : () {
                      if (_kecId != null) {
                        w.loadDesa(_kecId!);
                      } else if (_kabId != null) {
                        w.loadKecamatan(_kabId!);
                      } else {
                        w.loadKabupaten();
                      }
                    },
              icon: const Icon(Icons.refresh),
              label: const Text('Muat Ulang Data Wilayah'),
            ),
          ),
        ],
      ],
    );
  }
}

class _Dropdown<T> extends StatelessWidget {
  final String label;
  final int? value;
  final List<T> items;
  final bool loading;
  final bool enabled;
  final String? errorText;
  final ValueChanged<int?> onChanged;
  final String Function(T) textFn;

  const _Dropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.loading,
    this.enabled = true,
    this.errorText,
    required this.onChanged,
    required this.textFn,
  });

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<int>(
      initialValue: items.any((e) =>
              (e is Kabupaten
                  ? (e as Kabupaten).id
                  : e is Kecamatan
                      ? (e as Kecamatan).id
                      : (e as Desa).id) ==
              value)
          ? value
          : null,
      decoration: InputDecoration(
        labelText: label,
        errorText: errorText,
        helperText: loading
            ? 'Memuat data $label…'
            : enabled && items.isEmpty
                ? 'Data belum tersedia'
                : null,
      ),
      items: [
        DropdownMenuItem(value: null, child: Text('Pilih $label')),
        ...items.map((e) {
          final id = e is Kabupaten
              ? e.id
              : e is Kecamatan
                  ? e.id
                  : (e as Desa).id;
          return DropdownMenuItem(value: id, child: Text(textFn(e)));
        }),
      ],
      onChanged: loading || !enabled ? null : onChanged,
    );
  }
}
