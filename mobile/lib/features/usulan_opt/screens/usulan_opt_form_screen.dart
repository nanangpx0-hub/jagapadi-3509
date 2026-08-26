import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../../../core/connectivity_service.dart';
import '../../wilayah/widgets/wilayah_picker.dart';
import '../providers/usulan_opt_provider.dart';

/// Form buat/edit Usulan OPT.
///
/// Field mengikuti backend `UsulanOptService::normalize/validate`:
/// nama_lokal*, jenis*, komoditas*, ciri_ciri*, tanggal_ditemukan*,
/// wilayah (wajib saat submit), koordinat opsional, dst.
class UsulanOptFormScreen extends StatefulWidget {
  final int? id;
  const UsulanOptFormScreen({super.key, this.id});

  @override
  State<UsulanOptFormScreen> createState() => _UsulanOptFormScreenState();
}

class _UsulanOptFormScreenState extends State<UsulanOptFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _namaLokalCtrl = TextEditingController();
  final _namaNasionalCtrl = TextEditingController();
  final _komoditasCtrl = TextEditingController();
  final _ciriCtrl = TextEditingController();
  final _tanggalCtrl = TextEditingController();
  final _alamatCtrl = TextEditingController();
  final _latCtrl = TextEditingController();
  final _lngCtrl = TextEditingController();
  final _bagianCtrl = TextEditingController();
  final _gejalaCtrl = TextEditingController();
  final _estimasiCtrl = TextEditingController();
  final _satuanCtrl = TextEditingController();
  final _sumberCtrl = TextEditingController();

  String _jenis = 'hama';
  String? _keyakinan;
  int? _kabId, _kecId, _desaId;
  bool _loading = false;
  int? _loadedId;

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        final p = context.read<UsulanOptProvider>();
        await p.loadDetail(widget.id!);
        final d = p.detail;
        if (d != null && mounted) {
          setState(() {
            _loadedId = d.id;
            _namaLokalCtrl.text = d.namaLokal ?? '';
            _namaNasionalCtrl.text = d.namaNasional ?? '';
            _jenis = d.jenis;
            _komoditasCtrl.text = d.komoditas ?? '';
            _ciriCtrl.text = d.ciriCiri ?? '';
            _tanggalCtrl.text = d.tanggalDitemukan ?? '';
            _kabId = d.kabupatenId;
            _kecId = d.kecamatanId;
            _desaId = d.desaId;
            _alamatCtrl.text = d.alamatLokasi ?? '';
            _latCtrl.text = d.latitude?.toString() ?? '';
            _lngCtrl.text = d.longitude?.toString() ?? '';
            _bagianCtrl.text = d.bagianTerserang ?? '';
            _gejalaCtrl.text = d.polaGejala ?? '';
            _estimasiCtrl.text = d.estimasiTerdampak?.toString() ?? '';
            _satuanCtrl.text = d.satuanTerdampak ?? '';
            _keyakinan = d.tingkatKeyakinan;
            _sumberCtrl.text = d.sumberIdentifikasi ?? '';
          });
        }
      });
    }
  }

  @override
  void dispose() {
    _namaLokalCtrl.dispose();
    _namaNasionalCtrl.dispose();
    _komoditasCtrl.dispose();
    _ciriCtrl.dispose();
    _tanggalCtrl.dispose();
    _alamatCtrl.dispose();
    _latCtrl.dispose();
    _lngCtrl.dispose();
    _bagianCtrl.dispose();
    _gejalaCtrl.dispose();
    _estimasiCtrl.dispose();
    _satuanCtrl.dispose();
    _sumberCtrl.dispose();
    super.dispose();
  }

  Map<String, dynamic> _buildPayload() => {
        'nama_lokal': _namaLokalCtrl.text,
        if (_namaNasionalCtrl.text.isNotEmpty)
          'nama_nasional': _namaNasionalCtrl.text,
        'jenis': _jenis,
        'komoditas': _komoditasCtrl.text,
        'ciri_ciri': _ciriCtrl.text,
        'tanggal_ditemukan': _tanggalCtrl.text,
        'kabupaten_id': _kabId,
        'kecamatan_id': _kecId,
        'desa_id': _desaId,
        if (_alamatCtrl.text.isNotEmpty) 'alamat_lokasi': _alamatCtrl.text,
        if (_latCtrl.text.isNotEmpty)
          'latitude': double.tryParse(_latCtrl.text),
        if (_lngCtrl.text.isNotEmpty)
          'longitude': double.tryParse(_lngCtrl.text),
        if (_bagianCtrl.text.isNotEmpty) 'bagian_terserang': _bagianCtrl.text,
        if (_gejalaCtrl.text.isNotEmpty) 'pola_gejala': _gejalaCtrl.text,
        if (_estimasiCtrl.text.isNotEmpty)
          'estimasi_terdampak': double.tryParse(_estimasiCtrl.text),
        if (_satuanCtrl.text.isNotEmpty)
          'satuan_terdampak': _satuanCtrl.text,
        if (_keyakinan != null) 'tingkat_keyakinan': _keyakinan,
        if (_sumberCtrl.text.isNotEmpty)
          'sumber_identifikasi': _sumberCtrl.text,
      };

  Future<void> _save({required bool submit}) async {
    if (!_formKey.currentState!.validate()) return;
    final scheme = Theme.of(context).colorScheme;

    final isOnline = context.read<ConnectivityService>().isOnline;
    if (!isOnline) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Kirim usulan membutuhkan koneksi internet.')),
      );
      return;
    }

    final p = context.read<UsulanOptProvider>();
    final id = await p.save(_buildPayload(), id: _loadedId, submit: submit);

    if (!mounted) return;
    if (id != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(submit
              ? 'Usulan OPT terkirim untuk review Admin.'
              : 'Draf usulan OPT tersimpan.'),
        ),
      );
      Navigator.pop(context, true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(p.error ?? 'Gagal menyimpan usulan OPT'),
          backgroundColor: scheme.errorContainer,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<UsulanOptProvider>();
    final isEdit = _loadedId != null;

    return Scaffold(
      appBar: AppBar(
        title: Text(isEdit ? 'Edit Usulan OPT' : 'Buat Usulan OPT'),
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 720),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextFormField(
                    controller: _namaLokalCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Nama Lokal/Daerah *',
                      helperText: 'Nama yang dipakai petani setempat',
                    ),
                    textInputAction: TextInputAction.next,
                    validator: (v) => (v == null || v.trim().length < 2)
                        ? 'Nama lokal wajib diisi'
                        : null,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _namaNasionalCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Nama Nasional/Ilmiah',
                      helperText: 'Opsional; jika diketahui',
                    ),
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DropdownButtonFormField<String>(
                    decoration: const InputDecoration(
                        labelText: 'Jenis Usulan *'),
                    value: _jenis,
                    items: const [
                      DropdownMenuItem(value: 'hama', child: Text('Hama')),
                      DropdownMenuItem(
                          value: 'penyakit', child: Text('Penyakit')),
                      DropdownMenuItem(value: 'gulma', child: Text('Gulma')),
                    ],
                    onChanged: (v) => setState(() => _jenis = v ?? 'hama'),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _komoditasCtrl,
                    decoration: const InputDecoration(
                        labelText: 'Komoditas yang Diserang *'),
                    textInputAction: TextInputAction.next,
                    validator: (v) => (v == null || v.trim().isEmpty)
                        ? 'Komoditas wajib diisi'
                        : null,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _ciriCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Ciri-ciri / Gejala *',
                      alignLabelWithHint: true,
                    ),
                    maxLines: 3,
                    maxLength: 5000,
                    validator: (v) => (v == null || v.trim().isEmpty)
                        ? 'Ciri-ciri wajib diisi'
                        : null,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _tanggalCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Tanggal Ditemukan *',
                      helperText: 'Format YYYY-MM-DD',
                    ),
                    onTap: () async {
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: DateTime.now(),
                        firstDate: DateTime(2020),
                        lastDate: DateTime.now(),
                      );
                      if (picked != null) {
                        _tanggalCtrl.text =
                            picked.toIso8601String().substring(0, 10);
                      }
                    },
                    readOnly: true,
                    validator: (v) => (v == null || v.isEmpty)
                        ? 'Tanggal ditemukan wajib diisi'
                        : null,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  WilayahPicker(
                    kabupatenId: _kabId,
                    kecamatanId: _kecId,
                    desaId: _desaId,
                    onKabupatenChanged: (v) => setState(() {
                      _kabId = v;
                      _kecId = null;
                      _desaId = null;
                    }),
                    onKecamatanChanged: (v) => setState(() {
                      _kecId = v;
                      _desaId = null;
                    }),
                    onDesaChanged: (v) => setState(() => _desaId = v),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _alamatCtrl,
                    decoration: const InputDecoration(
                        labelText: 'Alamat Lokasi (opsional)'),
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _latCtrl,
                          decoration:
                              const InputDecoration(labelText: 'Latitude'),
                          keyboardType:
                              const TextInputType.numberWithOptions(
                                  decimal: true, signed: true),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: TextFormField(
                          controller: _lngCtrl,
                          decoration:
                              const InputDecoration(labelText: 'Longitude'),
                          keyboardType:
                              const TextInputType.numberWithOptions(
                                  decimal: true, signed: true),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _bagianCtrl,
                    decoration: const InputDecoration(
                        labelText: 'Bagian Terserang (opsional)'),
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _gejalaCtrl,
                    decoration: const InputDecoration(
                        labelText: 'Pola Gejala (opsional)'),
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _estimasiCtrl,
                          decoration: const InputDecoration(
                              labelText: 'Estimasi Terdampak (opsional)'),
                          keyboardType:
                              const TextInputType.numberWithOptions(
                                  decimal: true),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: TextFormField(
                          controller: _satuanCtrl,
                          decoration: const InputDecoration(
                              labelText: 'Satuan (opsional)',
                              helperText: 'ha / tanaman'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DropdownButtonFormField<String>(
                    decoration: const InputDecoration(
                        labelText: 'Tingkat Keyakinan (opsional)'),
                    value: _keyakinan,
                    items: const [
                      DropdownMenuItem(value: 'Rendah', child: Text('Rendah')),
                      DropdownMenuItem(
                          value: 'Sedang', child: Text('Sedang')),
                      DropdownMenuItem(
                          value: 'Tinggi', child: Text('Tinggi')),
                    ],
                    onChanged: (v) => setState(() => _keyakinan = v),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _sumberCtrl,
                    decoration: const InputDecoration(
                        labelText: 'Sumber Identifikasi (opsional)'),
                    textInputAction: TextInputAction.done,
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: p.loading
                              ? null
                              : () => _save(submit: false),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size(double.infinity, 52),
                          ),
                          child: const Text('Simpan Draf'),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: FilledButton(
                          onPressed:
                              p.loading ? null : () => _save(submit: true),
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(double.infinity, 52),
                          ),
                          child: p.loading
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Kirim untuk Review'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    'Catatan: Kirim untuk review membutuhkan wilayah lengkap '
                    'dan minimal satu foto bukti. Foto dapat ditambahkan '
                    'setelah draf tersimpan.',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
