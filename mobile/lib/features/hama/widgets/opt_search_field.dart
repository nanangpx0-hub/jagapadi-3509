import 'package:flutter/material.dart';

import '../models/laporan_hama.dart';

class OptSearchField extends FormField<int> {
  OptSearchField({
    super.key,
    required List<OptOption> options,
    required int? value,
    required ValueChanged<int?> onChanged,
    String? errorText,
    bool loading = false,
  }) : super(
          initialValue: value,
          validator: (selected) =>
              selected == null ? 'OPT wajib dipilih' : null,
          builder: (state) {
            final selected =
                options.where((option) => option.id == state.value);
            return InkWell(
              key: const Key('opt_search_field'),
              onTap: loading
                  ? null
                  : () async {
                      final result = await showDialog<int>(
                        context: state.context,
                        builder: (_) => _OptSearchDialog(options: options),
                      );
                      if (result != null) {
                        state.didChange(result);
                        onChanged(result);
                      }
                    },
              child: InputDecorator(
                decoration: InputDecoration(
                  labelText: 'OPT (Organisme Pengganggu Tanaman)',
                  errorText: errorText ?? state.errorText,
                  suffixIcon: loading
                      ? const Padding(
                          padding: EdgeInsets.all(14),
                          child: SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                      : const Icon(Icons.search),
                ),
                child: Text(
                  selected.isEmpty
                      ? 'Cari dan pilih jenis OPT'
                      : selected.first.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            );
          },
        );
}

class _OptSearchDialog extends StatefulWidget {
  final List<OptOption> options;

  const _OptSearchDialog({required this.options});

  @override
  State<_OptSearchDialog> createState() => _OptSearchDialogState();
}

class _OptSearchDialogState extends State<_OptSearchDialog> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final normalized = _query.trim().toLowerCase();
    final filtered = normalized.isEmpty
        ? widget.options
        : widget.options
            .where((option) => option.nama.toLowerCase().contains(normalized))
            .toList(growable: false);

    return AlertDialog(
      title: const Text('Pilih Jenis OPT'),
      content: SizedBox(
        width: double.maxFinite,
        height: MediaQuery.sizeOf(context).height * 0.6,
        child: Column(
          children: [
            TextField(
              key: const Key('opt_search_input'),
              autofocus: true,
              decoration: const InputDecoration(
                hintText: 'Ketik nama OPT…',
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: (value) => setState(() => _query = value),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: filtered.isEmpty
                  ? const Center(child: Text('OPT tidak ditemukan'))
                  : ListView.builder(
                      itemCount: filtered.length,
                      itemBuilder: (_, index) {
                        final option = filtered[index];
                        return ListTile(
                          title: Text(option.nama),
                          onTap: () => Navigator.pop(context, option.id),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Batal'),
        ),
      ],
    );
  }
}
