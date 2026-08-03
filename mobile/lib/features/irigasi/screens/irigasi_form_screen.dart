import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../wilayah/widgets/wilayah_picker.dart';
import '../providers/laporan_irigasi_provider.dart';

class IrigasiFormScreen extends StatefulWidget {
  final int? id;
  const IrigasiFormScreen({super.key, this.id});

  @override
  State<IrigasiFormScreen> createState() => _IrigasiFormScreenState();
}

class _IrigasiFormScreenState extends State<IrigasiFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _tanggalCtrl = TextEditingController();
  final _saluranCtrl = TextEditingController();
  final _daerahCtrl = TextEditingController();
  final _catatanCtrl = TextEditingController();
  final _latCtrl = TextEditingController();
  final _lngCtrl = TextEditingController();

  int? _kabId, _kecId, _desaId;
  String? _kondisiFisik, _debitAir;
  File? _foto;
  bool _loading = false;
  Map<String, String> _fieldErrors = {};

  @override
  void dispose() {
    _tanggalCtrl.dispose();
    _saluranCtrl.dispose();
    _daerahCtrl.dispose();
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

  String? _fe(String field) => _fieldErrors[field];

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _fieldErrors = {};
    });
    final p = context.read<LaporanIrigasiProvider>();
    final data = <String, dynamic>{
      'action': 'draft',
      'tanggal': _tanggalCtrl.text,
      'nama_saluran': _saluranCtrl.text,
      'daerah_irigasi': _daerahCtrl.text,
      'kabupaten_id': _kabId,
      'kecamatan_id': _kecId,
      'desa_id': _desaId,
      'kondisi_fisik': _kondisiFisik,
      'debit_air': _debitAir,
      if (_latCtrl.text.isNotEmpty) 'latitude': double.tryParse(_latCtrl.text),
      if (_lngCtrl.text.isNotEmpty) 'longitude': double.tryParse(_lngCtrl.text),
      'catatan': _catatanCtrl.text,
    };
    final res = await p.save(data, id: widget.id);
    setState(() => _loading = false);
    if (res != null && mounted) {
      if (_foto != null) {
        final newId = widget.id ?? res['id'] as int;
        await p.api.uploadFoto('/laporan-irigasi/$newId/foto', _foto!.path);
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
          title: Text(
              widget.id != null ? 'Edit Irigasi' : 'Laporan Irigasi Baru')),
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
              TextFormField(
                controller: _saluranCtrl,
                decoration: InputDecoration(
                    labelText: 'Nama Saluran', errorText: _fe('nama_saluran')),
                validator: (v) =>
                    v == null || v.isEmpty ? 'Nama saluran wajib diisi' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _daerahCtrl,
                decoration: InputDecoration(
                    labelText: 'Daerah Irigasi',
                    errorText: _fe('daerah_irigasi')),
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
              DropdownButtonFormField<String>(
                decoration: InputDecoration(
                    labelText: 'Kondisi Fisik',
                    errorText: _fe('kondisi_fisik')),
                initialValue: _kondisiFisik,
                items: ['Bagus', 'Sedang', 'Tidak Bagus', 'Rusak']
                    .map((k) => DropdownMenuItem(value: k, child: Text(k)))
                    .toList(),
                onChanged: (v) => setState(() => _kondisiFisik = v),
                validator: (_) => _kondisiFisik == null
                    ? 'Kondisi fisik wajib dipilih'
                    : null,
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                decoration: InputDecoration(
                    labelText: 'Debit Air', errorText: _fe('debit_air')),
                initialValue: _debitAir,
                items: ['Cukup', 'Kurang', 'Kering']
                    .map((k) => DropdownMenuItem(value: k, child: Text(k)))
                    .toList(),
                onChanged: (v) => setState(() => _debitAir = v),
                validator: (_) =>
                    _debitAir == null ? 'Debit air wajib dipilih' : null,
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
                      tooltip: 'Ambil lokasi'),
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
              ElevatedButton.icon(
                onPressed: _pickFoto,
                icon: const Icon(Icons.camera_alt, size: 18),
                label: const Text('Ambil Foto'),
                style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.grey.shade200,
                    foregroundColor: Colors.black87),
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
