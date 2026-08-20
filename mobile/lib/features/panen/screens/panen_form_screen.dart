import 'dart:io';
import 'package:flutter/material.dart';
import '../../../core/gps_service.dart';
import 'package:provider/provider.dart';
import '../../../core/connectivity_service.dart';
import '../../../core/local_db.dart';
import '../../../core/photo_validator.dart';
import '../../../core/validators/laporan_validators.dart';
import '../../../core/widgets/date_field.dart';
import '../../../core/widgets/foto_picker.dart';
import '../../../core/widgets/upload_progress_dialog.dart';
import '../../wilayah/widgets/wilayah_picker.dart';
import '../providers/laporan_panen_provider.dart';

class PanenFormScreen extends StatefulWidget {
  final int? id;
  const PanenFormScreen({super.key, this.id});

  @override
  State<PanenFormScreen> createState() => _PanenFormScreenState();
}

class _PanenFormScreenState extends State<PanenFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _tanggalCtrl = TextEditingController();
  final _komoditasCtrl = TextEditingController();
  final _varietasCtrl = TextEditingController();
  final _luasCtrl = TextEditingController();
  final _hasilCtrl = TextEditingController();
  final _catatanCtrl = TextEditingController();
  final _latCtrl = TextEditingController();
  final _lngCtrl = TextEditingController();

  int? _kabId, _kecId, _desaId;
  String? _musimTanam;
  File? _foto;
  String? _existingFotoUrl;
  bool _loading = false;
  int? _localDraftId;
  Map<String, String> _fieldErrors = {};

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        final p = context.read<LaporanPanenProvider>();
        await p.loadDetail(widget.id!);
        final d = p.detail;
        if (d != null) {
          setState(() {
            _tanggalCtrl.text = d.tanggal ?? '';
            _komoditasCtrl.text = d.komoditas ?? '';
            _varietasCtrl.text = d.varietas ?? '';
            _luasCtrl.text = d.luasPanen?.toString() ?? '';
            _hasilCtrl.text = d.hasilPanen?.toString() ?? '';
            _musimTanam = d.musimTanam;
            _kabId = d.kabupatenId;
            _kecId = d.kecamatanId;
            _desaId = d.desaId;
            _latCtrl.text = d.latitude?.toString() ?? '';
            _lngCtrl.text = d.longitude?.toString() ?? '';
            _catatanCtrl.text = d.catatan ?? '';
            _existingFotoUrl = d.fotoUrl;
          });
        }
      });
    }
  }

  @override
  void dispose() {
    _tanggalCtrl.dispose();
    _komoditasCtrl.dispose();
    _varietasCtrl.dispose();
    _luasCtrl.dispose();
    _hasilCtrl.dispose();
    _catatanCtrl.dispose();
    _latCtrl.dispose();
    _lngCtrl.dispose();
    super.dispose();
  }

  Future<void> _getLocation() async {
    final result = await GpsService.getCurrentLocation();
    if (!mounted) return;
    if (result.isSuccess) {
      setState(() {
        _latCtrl.text = result.latitude!.toStringAsFixed(7);
        _lngCtrl.text = result.longitude!.toStringAsFixed(7);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result.message)),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result.message),
          action: result.settingsActionLabel == null
              ? null
              : SnackBarAction(
                  label: result.settingsActionLabel!,
                  onPressed: result.status == GpsResultStatus.disabled
                      ? GpsService.platform.openLocationSettings
                      : GpsService.platform.openAppSettings,
                ),
        ),
      );
    }
  }

  void _applyFieldErrors(Map<String, dynamic>? errors) {
    if (errors == null) return;
    setState(() {
      _fieldErrors = errors
          .map((k, v) => MapEntry(k, v is List ? v.join('\n') : v.toString()));
    });
  }

  String? _fe(String field) => _fieldErrors[field];

  Future<bool> _saveDraft() async {
    if (!_formKey.currentState!.validate()) return false;
    setState(() {
      _loading = true;
      _fieldErrors = {};
    });

    if (_foto != null) {
      final fotoError = PhotoValidator.validateFile(_foto!);
      if (fotoError != null) {
        setState(() => _loading = false);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(fotoError), backgroundColor: Colors.red),
          );
        }
        return false;
      }
    }
    final p = context.read<LaporanPanenProvider>();
    final isOnline = context.read<ConnectivityService>().isOnline;

    final data = <String, dynamic>{
      'action': 'draft',
      'tanggal': _tanggalCtrl.text,
      'komoditas': _komoditasCtrl.text,
      'varietas': _varietasCtrl.text,
      if (_luasCtrl.text.isNotEmpty)
        'luas_panen': double.tryParse(_luasCtrl.text),
      if (_hasilCtrl.text.isNotEmpty)
        'hasil_panen': double.tryParse(_hasilCtrl.text),
      'musim_tanam': _musimTanam,
      'kabupaten_id': _kabId,
      'kecamatan_id': _kecId,
      'desa_id': _desaId,
      if (_latCtrl.text.isNotEmpty) 'latitude': double.tryParse(_latCtrl.text),
      if (_lngCtrl.text.isNotEmpty) 'longitude': double.tryParse(_lngCtrl.text),
      'catatan': _catatanCtrl.text,
    };

    int? localId = _localDraftId;
    if (localId == null) {
      localId = await LocalDb.instance.insertDraft(
        type: 'panen',
        payload: data,
        fotoPath: _foto?.path,
        serverId: widget.id,
      );
      _localDraftId = localId;
    } else {
      await LocalDb.instance.updateDraft(
        localId,
        payload: data,
        fotoPath: _foto?.path,
      );
    }

    if (isOnline) {
      Map<String, String>? headers;
      if (_localDraftId != null) {
        final draft = await LocalDb.instance.getDraft(_localDraftId!);
        if (draft?.clientOperationId != null) {
          headers = {'Idempotency-Key': draft!.clientOperationId!};
        }
      }
      final res = await p.save(data, id: widget.id, headers: headers);
      setState(() => _loading = false);
      if (res != null && mounted) {
        final newId =
            widget.id ?? (res['data']?['id'] as int? ?? res['id'] as int?);
        if (newId != null) {
          await LocalDb.instance.markSynced(localId, newId);
          if (_foto != null) {
            final upload = await showUploadProgress(
              context,
              title: 'Mengirim foto lampiranâ€¦',
              task: (onProgress) => p.api.uploadFoto(
                '/laporan-panen/$newId/foto',
                _foto!.path,
                onSendProgress: onProgress,
              ),
            );
            if (upload.success) await LocalDb.instance.markPhotoSynced(localId);
          }
        }
        return true;
      } else if (mounted) {
        if (p.fieldErrors != null) _applyFieldErrors(p.fieldErrors);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text(
                  'Draf tersimpan di perangkat lokal (akan disinkronkan saat server siap)')),
        );
        return true;
      }
    } else {
      setState(() => _loading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Mode Offline â€” Draf tersimpan aman di perangkat')),
        );
      }
      return true;
    }
    return false;
  }

  Future<void> _handleSaveDraft() async {
    final success = await _saveDraft();
    if (success && mounted) {
      Navigator.pop(context);
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;

    final hasPhoto = _foto != null ||
        (_existingFotoUrl != null && _existingFotoUrl!.trim().isNotEmpty);
    if (!hasPhoto) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Foto laporan wajib disertakan sebelum laporan dapat dikirim.',
          ),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final isOnline = context.read<ConnectivityService>().isOnline;
    if (!isOnline) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Kirim laporan membutuhkan koneksi internet online')),
      );
      return;
    }

    final p = context.read<LaporanPanenProvider>();
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi Pengiriman Laporan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Apakah Anda yakin ingin mengirim laporan panen ini?'),
            const SizedBox(height: 12),
            Text('â€¢ Tanggal: ${_tanggalCtrl.text}'),
            Text('â€¢ Komoditas: ${_komoditasCtrl.text}'),
            Text(
                'â€¢ Luas Panen: ${_luasCtrl.text.isNotEmpty ? "${_luasCtrl.text} ha" : "-"}'),
            Text(
                'â€¢ Hasil Panen: ${_hasilCtrl.text.isNotEmpty ? "${_hasilCtrl.text} ton" : "-"}'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Kirim'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    final saved = await _saveDraft();
    if (!saved || !mounted) return;

    final targetId = widget.id ?? p.detail?.id;
    if (targetId != null) {
      final res = await p.submit(targetId);
      if (res != null && mounted) {
        if (_localDraftId != null) {
          await LocalDb.instance.deleteDraft(_localDraftId!);
        }
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Laporan berhasil dikirim ke Admin')),
        );
        Navigator.pop(context);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
            widget.id != null ? 'Edit Laporan Panen' : 'Buat Laporan Panen'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              DateField(
                controller: _tanggalCtrl,
                label: 'Tanggal Panen',
                errorText: _fe('tanggal'),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _komoditasCtrl,
                decoration: InputDecoration(
                  labelText: 'Komoditas',
                  errorText: _fe('komoditas'),
                ),
                validator: (v) => v == null || v.trim().isEmpty
                    ? 'Komoditas wajib diisi'
                    : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _varietasCtrl,
                decoration: InputDecoration(
                  labelText: 'Varietas',
                  errorText: _fe('varietas'),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _luasCtrl,
                      decoration: InputDecoration(
                        labelText: 'Luas Panen (ha)',
                        errorText: _fe('luas_panen'),
                      ),
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      validator: (v) => LaporanValidators.angka(v,
                          nonNegative: true,
                          label: 'Luas Panen',
                          allowEmpty: false),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _hasilCtrl,
                      decoration: InputDecoration(
                        labelText: 'Hasil (ton)',
                        errorText: _fe('hasil_panen'),
                      ),
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      validator: (v) => LaporanValidators.angka(v,
                          nonNegative: true, label: 'Hasil', allowEmpty: false),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                decoration: InputDecoration(
                  labelText: 'Musim Tanam',
                  errorText: _fe('musim_tanam'),
                ),
                value: _musimTanam,
                items: ['MT1', 'MT2', 'MT3']
                    .map((k) => DropdownMenuItem(value: k, child: Text(k)))
                    .toList(),
                onChanged: (v) => setState(() => _musimTanam = v),
              ),
              const SizedBox(height: 16),
              WilayahPicker(
                kabupatenId: _kabId,
                kecamatanId: _kecId,
                desaId: _desaId,
                onKabupatenChanged: (v) => setState(() => _kabId = v),
                onKecamatanChanged: (v) => setState(() => _kecId = v),
                onDesaChanged: (v) => setState(() => _desaId = v),
                errorKabupaten: _fe('kabupaten_id'),
                errorKecamatan: _fe('kecamatan_id'),
                errorDesa: _fe('desa_id'),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _latCtrl,
                      decoration: InputDecoration(
                        labelText: 'Latitude',
                        errorText: _fe('latitude'),
                      ),
                      keyboardType: const TextInputType.numberWithOptions(
                          decimal: true, signed: true),
                      validator: (v) =>
                          LaporanValidators.koordinat(v, _lngCtrl.text),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    icon: const Icon(Icons.my_location),
                    onPressed: _getLocation,
                    tooltip: 'Ambil Lokasi GPS',
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _lngCtrl,
                decoration: InputDecoration(
                  labelText: 'Longitude',
                  errorText: _fe('longitude'),
                ),
                keyboardType: const TextInputType.numberWithOptions(
                    decimal: true, signed: true),
                validator: (v) => LaporanValidators.koordinat(_latCtrl.text, v),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _catatanCtrl,
                decoration: InputDecoration(
                  labelText: 'Catatan Lapangan',
                  errorText: _fe('catatan'),
                ),
                maxLength: 2000,
                maxLines: 3,
              ),
              const SizedBox(height: 16),
              FotoPicker(
                fotoFile: _foto,
                existingFotoUrl: _existingFotoUrl,
                onFotoChanged: (f) => setState(() => _foto = f),
                onClearExistingFoto: () =>
                    setState(() => _existingFotoUrl = null),
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _loading ? null : _handleSaveDraft,
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size(double.infinity, 48),
                      ),
                      child: const Text('Simpan Draf'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _loading ? null : _handleSubmit,
                      child: _loading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Kirim Laporan'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
