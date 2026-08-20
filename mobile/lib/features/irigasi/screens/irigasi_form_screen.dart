import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/connectivity_service.dart';
import '../../../core/gps_service.dart';
import '../../../core/local_db.dart';
import '../../../core/photo_validator.dart';
import '../../../core/validators/laporan_validators.dart';
import '../../../core/widgets/date_field.dart';
import '../../../core/widgets/foto_picker.dart';
import '../../../core/widgets/upload_progress_dialog.dart';
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
  String? _existingFotoUrl;
  bool _loading = false;
  Map<String, String> _fieldErrors = {};

  // Bug #1 fix: track local draft ID agar tidak duplikat saat simpan berulang
  int? _localDraftId;

  // â”€â”€ Lifecycle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        final p = context.read<LaporanIrigasiProvider>();
        await p.loadDetail(widget.id!);
        final d = p.detail;
        if (d != null && mounted) {
          setState(() {
            _tanggalCtrl.text = d.tanggal ?? '';
            _saluranCtrl.text = d.namaSaluran ?? '';
            _daerahCtrl.text = d.daerahIrigasi ?? '';
            _kabId = d.kabupatenId;
            _kecId = d.kecamatanId;
            _desaId = d.desaId;
            _kondisiFisik = d.kondisiFisik;
            _debitAir = d.debitAir;
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
    _saluranCtrl.dispose();
    _daerahCtrl.dispose();
    _catatanCtrl.dispose();
    _latCtrl.dispose();
    _lngCtrl.dispose();
    super.dispose();
  }

  // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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
      _fieldErrors = errors.map(
        (k, v) => MapEntry(k, v is List ? v.join('\n') : v.toString()),
      );
    });
  }

  String? _fe(String field) => _fieldErrors[field];

  Map<String, dynamic> _buildPayload() => {
        'action': 'draft',
        'tanggal': _tanggalCtrl.text,
        'nama_saluran': _saluranCtrl.text,
        'daerah_irigasi': _daerahCtrl.text,
        'kabupaten_id': _kabId,
        'kecamatan_id': _kecId,
        'desa_id': _desaId,
        'kondisi_fisik': _kondisiFisik,
        'debit_air': _debitAir,
        if (_latCtrl.text.isNotEmpty)
          'latitude': double.tryParse(_latCtrl.text),
        if (_lngCtrl.text.isNotEmpty)
          'longitude': double.tryParse(_lngCtrl.text),
        'catatan': _catatanCtrl.text,
      };

  String? _validateFoto(File foto) => PhotoValidator.validateFile(foto);

  // â”€â”€ Simpan Draf (Bug #1 + #2 fixed) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // Mengembalikan:
  //   null   = gagal (validasi gagal atau error kritis)
  //   -1     = tersimpan lokal saja (offline / server error)
  //   > 0    = server ID yang valid

  Future<int?> _saveDraft() async {
    if (!_formKey.currentState!.validate()) return null;

    if (_foto != null) {
      final err = _validateFoto(_foto!);
      if (err != null) {
        if (mounted)
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(err), backgroundColor: Colors.red.shade700),
          );
        return null;
      }
    }

    setState(() {
      _loading = true;
      _fieldErrors = {};
    });

    final p = context.read<LaporanIrigasiProvider>();
    final isOnline = context.read<ConnectivityService>().isOnline;
    final data = _buildPayload();

    // Simpan / update lokal hanya untuk laporan baru (bukan edit laporan server)
    if (widget.id == null) {
      if (_localDraftId == null) {
        _localDraftId = await LocalDb.instance.insertDraft(
          type: 'irigasi',
          payload: data,
          fotoPath: _foto?.path,
        );
      } else {
        await LocalDb.instance.updateDraft(
          _localDraftId!,
          payload: data,
          fotoPath: _foto?.path,
        );
      }
    }

    if (widget.id != null) {
      if (_localDraftId == null) {
        _localDraftId = await LocalDb.instance.insertDraft(
          type: 'irigasi',
          payload: data,
          fotoPath: _foto?.path,
          serverId: widget.id,
        );
      } else {
        await LocalDb.instance.updateDraft(
          _localDraftId!,
          payload: data,
          fotoPath: _foto?.path,
        );
      }
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
        final rawData =
            res['data'] is Map ? res['data'] as Map<String, dynamic> : res;
        final serverId = rawData['id'] as int?;
        if (serverId != null) {
          if (_localDraftId != null) {
            await LocalDb.instance.markSynced(_localDraftId!, serverId);
          }
          if (_foto != null && mounted) {
            final uploadRes = await showUploadProgress(
              context,
              title: 'Mengirim foto lampiranâ€¦',
              task: (onProgress) => p.api.uploadFoto(
                '/laporan-irigasi/$serverId/foto',
                _foto!.path,
                onSendProgress: onProgress,
              ),
            );
            if (uploadRes.success && _localDraftId != null) {
              await LocalDb.instance.markPhotoSynced(_localDraftId!);
            }
            if (!uploadRes.success && mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Laporan tersimpan, tapi foto gagal diupload. '
                      'Coba upload ulang dari detail laporan.'),
                ),
              );
            }
          }
          return serverId;
        }
      } else if (mounted) {
        if (p.fieldErrors != null) _applyFieldErrors(p.fieldErrors);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${p.error ?? "Gagal menyimpan."}'
                '\nDraf tersimpan lokal, akan disinkronkan otomatis.'),
          ),
        );
        return _localDraftId != null ? -1 : null;
      }
    } else {
      setState(() => _loading = false);
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Mode Offline â€” Draf tersimpan aman di perangkat')),
        );
      return _localDraftId != null ? -1 : null;
    }

    setState(() => _loading = false);
    return null;
  }

  Future<void> _handleSaveDraft() async {
    final result = await _saveDraft();
    if (result != null && mounted) Navigator.pop(context);
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
            content: Text('Kirim laporan membutuhkan koneksi internet. '
                'Draf tersimpan dan bisa dikirim saat online.')),
      );
      return;
    }

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi Pengiriman Laporan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Laporan yang dikirim tidak dapat diedit. Yakin?'),
            const Divider(height: 20),
            Text('Tanggal  : ${_tanggalCtrl.text}'),
            Text('Saluran  : ${_saluranCtrl.text}'),
            Text(
                'Lokasi   : ${_daerahCtrl.text.isNotEmpty ? _daerahCtrl.text : "-"}'),
            Text('Kondisi  : ${_kondisiFisik ?? "-"}'),
            Text('Debit    : ${_debitAir ?? "-"}'),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Batal')),
          ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Kirim')),
        ],
      ),
    );
    if (confirm != true || !mounted) return;

    final savedId = await _saveDraft();
    if (!mounted) return;

    if (savedId == null) return;

    final p = context.read<LaporanIrigasiProvider>();
    final targetId = savedId > 0 ? savedId : widget.id;

    if (targetId == null || targetId < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Tidak dapat mengirim saat offline. '
                'Draf tersimpan dan bisa dikirim saat online.')),
      );
      return;
    }

    final res = await p.submit(targetId);
    if (!mounted) return;

    if (res != null) {
      if (_localDraftId != null) {
        await LocalDb.instance.deleteDraft(_localDraftId!);
      }
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Laporan irigasi berhasil dikirim ke Admin âœ“'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.pop(context);
    } else if (p.error != null) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(p.error!)));
    }
  }

  // â”€â”€ Build â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.id != null
            ? 'Edit Laporan Irigasi'
            : 'Laporan Irigasi Baru'),
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
                  label: 'Tanggal Laporan',
                  errorText: _fe('tanggal')),
              const SizedBox(height: 16),
              TextFormField(
                controller: _saluranCtrl,
                decoration: InputDecoration(
                    labelText: 'Nama Saluran Irigasi',
                    errorText: _fe('nama_saluran')),
                validator: (v) => (v == null || v.trim().isEmpty)
                    ? 'Nama saluran wajib diisi'
                    : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _daerahCtrl,
                decoration: InputDecoration(
                    labelText: 'Daerah Irigasi (opsional)',
                    errorText: _fe('daerah_irigasi')),
              ),
              const SizedBox(height: 16),
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
                errorKabupaten: _fe('kabupaten_id'),
                errorKecamatan: _fe('kecamatan_id'),
                errorDesa: _fe('desa_id'),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                decoration: InputDecoration(
                    labelText: 'Kondisi Fisik Saluran',
                    errorText: _fe('kondisi_fisik')),
                value: _kondisiFisik,
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
                value: _debitAir,
                items: ['Cukup', 'Kurang', 'Kering']
                    .map((k) => DropdownMenuItem(value: k, child: Text(k)))
                    .toList(),
                onChanged: (v) => setState(() => _debitAir = v),
                validator: (_) =>
                    _debitAir == null ? 'Debit air wajib dipilih' : null,
              ),
              const SizedBox(height: 16),
              Row(children: [
                Expanded(
                  child: TextFormField(
                    controller: _latCtrl,
                    decoration: InputDecoration(
                        labelText: 'Latitude', errorText: _fe('latitude')),
                    keyboardType: const TextInputType.numberWithOptions(
                        decimal: true, signed: true),
                    validator: (v) =>
                        LaporanValidators.koordinat(v, _lngCtrl.text),
                  ),
                ),
                const SizedBox(width: 8),
                Tooltip(
                  message: 'Ambil Lokasi GPS',
                  child: IconButton(
                      icon: const Icon(Icons.my_location),
                      onPressed: _loading ? null : _getLocation),
                ),
              ]),
              const SizedBox(height: 12),
              TextFormField(
                controller: _lngCtrl,
                decoration: InputDecoration(
                    labelText: 'Longitude', errorText: _fe('longitude')),
                keyboardType: const TextInputType.numberWithOptions(
                    decimal: true, signed: true),
                validator: (v) => LaporanValidators.koordinat(_latCtrl.text, v),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _catatanCtrl,
                decoration: InputDecoration(
                    labelText: 'Catatan Lapangan', errorText: _fe('catatan')),
                maxLines: 3,
                maxLength: 2000,
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
              Row(children: [
                Expanded(
                  child: OutlinedButton(
                    key: const Key('btn_simpan_draf_irigasi'),
                    onPressed: _loading ? null : _handleSaveDraft,
                    style: OutlinedButton.styleFrom(
                        minimumSize: const Size(double.infinity, 48)),
                    child: const Text('Simpan Draf'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    key: const Key('btn_kirim_irigasi'),
                    onPressed: _loading ? null : _handleSubmit,
                    child: _loading
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white))
                        : const Text('Kirim Laporan'),
                  ),
                ),
              ]),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}
