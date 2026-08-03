import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../auth/providers/auth_provider.dart';
import '../../wilayah/widgets/wilayah_picker.dart';
import '../providers/laporan_hama_provider.dart';

class HamaFormScreen extends StatefulWidget {
  final int? id;
  const HamaFormScreen({super.key, this.id});

  @override
  State<HamaFormScreen> createState() => _HamaFormScreenState();
}

class _HamaFormScreenState extends State<HamaFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _tanggalCtrl = TextEditingController();
  final _lokasiCtrl = TextEditingController();
  final _luasCtrl = TextEditingController();
  final _populasiCtrl = TextEditingController();
  final _catatanCtrl = TextEditingController();
  final _latCtrl = TextEditingController();
  final _lngCtrl = TextEditingController();

  int? _kabId, _kecId, _desaId, _optId;
  String? _keparahan;
  File? _foto;
  bool _loading = false;
  Map<String, String> _fieldErrors = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final p = context.read<LaporanHamaProvider>();
      p.loadOptList();
      if (widget.id != null) p.loadDetail(widget.id!);
    });
  }

  @override
  void dispose() {
    _tanggalCtrl.dispose();
    _lokasiCtrl.dispose();
    _luasCtrl.dispose();
    _populasiCtrl.dispose();
    _catatanCtrl.dispose();
    _latCtrl.dispose();
    _lngCtrl.dispose();
    super.dispose();
  }

  Future<void> _getLocation() async {
    final pos = await Geolocator.getCurrentPosition();
    _latCtrl.text = pos.latitude.toString();
    _lngCtrl.text = pos.longitude.toString();
  }

  Future<void> _pickFoto() async {
    final picked = await ImagePicker()
        .pickImage(source: ImageSource.camera, maxWidth: 1024);
    if (picked != null) setState(() => _foto = File(picked.path));
  }

  void _applyFieldErrors(Map<String, dynamic>? errors) {
    if (errors == null) return;
    setState(() {
      _fieldErrors = errors
          .map((k, v) => MapEntry(k, v is List ? v.join('\n') : v.toString()));
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _fieldErrors = {};
    });
    final p = context.read<LaporanHamaProvider>();
    final data = <String, dynamic>{
      'action': 'draft',
      'tanggal': _tanggalCtrl.text,
      'master_opt_id': _optId,
      'kabupaten_id': _kabId,
      'kecamatan_id': _kecId,
      'desa_id': _desaId,
      'tingkat_keparahan': _keparahan,
      'lokasi': _lokasiCtrl.text,
      if (_luasCtrl.text.isNotEmpty)
        'luas_serangan': double.tryParse(_luasCtrl.text),
      if (_populasiCtrl.text.isNotEmpty)
        'populasi': double.tryParse(_populasiCtrl.text),
      if (_latCtrl.text.isNotEmpty) 'latitude': double.tryParse(_latCtrl.text),
      if (_lngCtrl.text.isNotEmpty) 'longitude': double.tryParse(_lngCtrl.text),
      'catatan': _catatanCtrl.text,
    };

    final res = await p.save(data, id: widget.id);
    setState(() => _loading = false);

    if (res != null && mounted) {
      if (_foto != null) {
        final newId = widget.id ?? res['id'] as int;
        await p.api.uploadFoto('/laporan-hama/$newId/foto', _foto!.path);
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Draf berhasil disimpan')));
        Navigator.pop(context);
      }
    } else if (mounted) {
      if (p.fieldErrors != null) _applyFieldErrors(p.fieldErrors);
      final msg = p.error ?? 'Terjadi kesalahan. Silakan coba lagi.';
      if (msg.contains('Terlalu banyak')) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Terlalu banyak permintaan, coba lagi nanti')));
      } else {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(msg)));
      }
    }
  }

  String? _fe(String field) => _fieldErrors[field];

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      appBar: AppBar(
          title: Text(
              widget.id != null ? 'Edit Laporan Hama' : 'Laporan Hama Baru')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextFormField(
                controller: _tanggalCtrl,
                decoration: InputDecoration(
                    labelText: 'Tanggal',
                    hintText: 'YYYY-MM-DD',
                    errorText: _fe('tanggal')),
                validator: (v) =>
                    v == null || v.isEmpty ? 'Tanggal wajib diisi' : null,
              ),
              const SizedBox(height: 16),
              Consumer<LaporanHamaProvider>(
                builder: (_, p, __) => DropdownButtonFormField<int>(
                  decoration: InputDecoration(
                      labelText: 'OPT', errorText: _fe('master_opt_id')),
                  initialValue: _optId,
                  items: [
                    const DropdownMenuItem(
                        value: null, child: Text('Pilih OPT')),
                    ...p.optList.map((o) =>
                        DropdownMenuItem(value: o.id, child: Text(o.nama))),
                  ],
                  onChanged: (v) => setState(() => _optId = v),
                  validator: (_) => _optId == null ? 'OPT wajib dipilih' : null,
                ),
              ),
              const SizedBox(height: 16),
              WilayahPicker(
                kabupatenId: _kabId,
                kecamatanId: _kecId,
                desaId: _desaId,
                onKabupatenChanged: (v) => _kabId = v,
                onKecamatanChanged: (v) => _kecId = v,
                onDesaChanged: (v) => _desaId = v,
                errorKabupaten: _fe('kabupaten_id'),
                errorKecamatan: _fe('kecamatan_id'),
                errorDesa: _fe('desa_id'),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _lokasiCtrl,
                decoration: InputDecoration(
                    labelText: 'Lokasi', errorText: _fe('lokasi')),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                decoration: InputDecoration(
                    labelText: 'Tingkat Keparahan',
                    errorText: _fe('tingkat_keparahan')),
                initialValue: _keparahan,
                items: ['Ringan', 'Sedang', 'Berat']
                    .map((k) => DropdownMenuItem(value: k, child: Text(k)))
                    .toList(),
                onChanged: (v) => setState(() => _keparahan = v),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _luasCtrl,
                      decoration: InputDecoration(
                          labelText: 'Luas (ha)',
                          errorText: _fe('luas_serangan')),
                      keyboardType: TextInputType.number,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _populasiCtrl,
                      decoration: InputDecoration(
                          labelText: 'Populasi', errorText: _fe('populasi')),
                      keyboardType: TextInputType.number,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _latCtrl,
                      decoration: InputDecoration(
                          labelText: 'Latitude', errorText: _fe('latitude')),
                      keyboardType: TextInputType.number,
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    icon: const Icon(Icons.my_location),
                    onPressed: _getLocation,
                    tooltip: 'Ambil lokasi',
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _lngCtrl,
                decoration: InputDecoration(
                    labelText: 'Longitude', errorText: _fe('longitude')),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _catatanCtrl,
                decoration: InputDecoration(
                    labelText: 'Catatan', errorText: _fe('catatan')),
                maxLines: 3,
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  ElevatedButton.icon(
                    onPressed: _pickFoto,
                    icon: const Icon(Icons.camera_alt, size: 18),
                    label: const Text('Ambil Foto'),
                    style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.grey.shade200,
                        foregroundColor: Colors.black87),
                  ),
                  if (_foto != null) ...[
                    const SizedBox(width: 12),
                    Text('${_foto!.lengthSync() ~/ 1024} KB'),
                  ],
                ],
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _loading ? null : _save,
                child: _loading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white))
                    : const Text('Simpan Draft'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
