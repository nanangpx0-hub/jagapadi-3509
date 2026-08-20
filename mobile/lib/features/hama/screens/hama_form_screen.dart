import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/connectivity_service.dart';
import '../../../core/gps_service.dart';
import '../../../core/local_db.dart';
import '../../../core/photo_validator.dart';
import '../../../core/theme.dart';
import '../../../core/validators/laporan_validators.dart';
import '../../../core/widgets/date_field.dart';
import '../../../core/widgets/foto_picker.dart';
import '../../../core/widgets/upload_progress_dialog.dart';
import '../../wilayah/widgets/wilayah_picker.dart';
import '../providers/laporan_hama_provider.dart';
import '../widgets/opt_search_field.dart';

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
  String? _existingFotoUrl;
  bool _loading = false;
  bool _gettingLocation = false;
  Map<String, String> _fieldErrors = {};

  int? _localDraftId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final p = context.read<LaporanHamaProvider>();
      await p.loadOptList();
      if (widget.id != null) {
        await p.loadDetail(widget.id!);
        final d = p.detail;
        if (d != null && mounted) {
          setState(() {
            _tanggalCtrl.text = d.tanggal ?? '';
            _optId = d.masterOptId;
            _kabId = d.kabupatenId;
            _kecId = d.kecamatanId;
            _desaId = d.desaId;
            _keparahan = d.tingkatKeparahan;
            _lokasiCtrl.text = d.lokasi ?? '';
            _luasCtrl.text = d.luasSerangan?.toString() ?? '';
            _populasiCtrl.text = d.populasi?.toString() ?? '';
            _latCtrl.text = d.latitude?.toString() ?? '';
            _lngCtrl.text = d.longitude?.toString() ?? '';
            _catatanCtrl.text = d.catatan ?? '';
            _existingFotoUrl = d.fotoUrl;
          });
        }
      }
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
    if (_gettingLocation) return;
    setState(() => _gettingLocation = true);
    final result = await GpsService.getCurrentLocation();
    if (!mounted) return;
    setState(() => _gettingLocation = false);
    if (result.isSuccess) {
      _latCtrl.text = result.latitude!.toStringAsFixed(7);
      _lngCtrl.text = result.longitude!.toStringAsFixed(7);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result.message)),
      );
    } else {
      _showLocationError(
        result.message,
        actionLabel: result.settingsActionLabel,
        action: result.settingsActionLabel == null
            ? null
            : result.status == GpsResultStatus.disabled
                ? GpsService.platform.openLocationSettings
                : GpsService.platform.openAppSettings,
      );
    }
  }

  void _showLocationError(
    String message, {
    String? actionLabel,
    Future<bool> Function()? action,
  }) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        action: actionLabel == null || action == null
            ? null
            : SnackBarAction(label: actionLabel, onPressed: action),
      ),
    );
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
        if (_latCtrl.text.isNotEmpty)
          'latitude': double.tryParse(_latCtrl.text),
        if (_lngCtrl.text.isNotEmpty)
          'longitude': double.tryParse(_lngCtrl.text),
        'catatan': _catatanCtrl.text,
      };

  String? _validateFoto(File foto) => PhotoValidator.validateFile(foto);

  Future<int?> _saveDraft() async {
    if (!_formKey.currentState!.validate()) return null;
    final scheme = Theme.of(context).colorScheme;

    if (_foto != null) {
      final fotoError = _validateFoto(_foto!);
      if (fotoError != null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(fotoError),
              backgroundColor: scheme.errorContainer,
            ),
          );
        }
        return null;
      }
    }

    setState(() {
      _loading = true;
      _fieldErrors = {};
    });

    final p = context.read<LaporanHamaProvider>();
    final isOnline = context.read<ConnectivityService>().isOnline;
    final data = _buildPayload();

    if (widget.id == null) {
      if (_localDraftId == null) {
        _localDraftId = await LocalDb.instance.insertDraft(
          type: 'hama',
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
          type: 'hama',
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
      // Idempotency: kirim client_operation_id draf lokal agar retry (timeout/
      // jaringan) tidak membuat laporan duplikat di server.
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
            final fotoError = await showUploadProgress(
              context,
              title: 'Mengirim foto lampiranâ€¦',
              task: (onProgress) => p.api.uploadFoto(
                '/laporan-hama/$serverId/foto',
                _foto!.path,
                onSendProgress: onProgress,
              ),
            );
            if (fotoError.success && _localDraftId != null) {
              await LocalDb.instance.markPhotoSynced(_localDraftId!);
            }
            if (!fotoError.success && mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text(
                      'Laporan tersimpan, tapi foto gagal diupload. Coba upload ulang di detail laporan.'),
                ),
              );
            }
          }
          return serverId;
        }
      } else if (mounted) {
        if (p.fieldErrors != null) _applyFieldErrors(p.fieldErrors);
        final errMsg = p.error ?? 'Gagal menyimpan ke server.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
                '$errMsg\nDraf tersimpan lokal dan akan disinkronkan otomatis.'),
          ),
        );
        return _localDraftId != null ? -1 : null;
      }
    } else {
      setState(() => _loading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Mode Offline â€” Draf tersimpan aman di perangkat'),
          ),
        );
      }
      return _localDraftId != null ? -1 : null;
    }

    setState(() => _loading = false);
    return null;
  }

  Future<void> _handleSaveDraft() async {
    final result = await _saveDraft();
    if (result != null && mounted) {
      Navigator.pop(context);
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;
    final scheme = Theme.of(context).colorScheme;

    final hasPhoto = _foto != null ||
        (_existingFotoUrl != null && _existingFotoUrl!.trim().isNotEmpty);
    if (!hasPhoto) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text(
            'Foto laporan wajib disertakan sebelum laporan dapat dikirim.',
          ),
          backgroundColor: scheme.errorContainer,
        ),
      );
      return;
    }

    final isOnline = context.read<ConnectivityService>().isOnline;
    if (!isOnline) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Kirim laporan membutuhkan koneksi internet. '
              'Draf tersimpan dan bisa dikirim saat online.'),
        ),
      );
      return;
    }

    final p = context.read<LaporanHamaProvider>();
    final optName =
        p.optList.where((o) => o.id == _optId).map((o) => o.nama).firstOrNull ??
            'OPT tidak dipilih';

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
            Text('Tanggal   : ${_tanggalCtrl.text}'),
            Text('OPT       : $optName'),
            Text('Keparahan : ${_keparahan ?? '-'}'),
            Text(
                'Luas      : ${_luasCtrl.text.isNotEmpty ? "${_luasCtrl.text} ha" : "-"}'),
            Text(
                'Lokasi    : ${_lokasiCtrl.text.isNotEmpty ? _lokasiCtrl.text : "-"}'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Kirim'),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    final savedId = await _saveDraft();

    if (!mounted) return;

    if (savedId == null) return;

    final targetId = savedId > 0 ? savedId : widget.id;

    if (targetId == null || targetId < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Tidak dapat mengirim laporan saat offline. '
              'Draf tersimpan dan bisa dikirim saat online.'),
        ),
      );
      return;
    }

    final res = await p.submit(targetId);
    if (res != null && mounted) {
      if (_localDraftId != null) {
        await LocalDb.instance.deleteDraft(_localDraftId!);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Laporan berhasil dikirim ke Admin âœ“'),
        ),
      );
      Navigator.pop(context);
    } else if (p.error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(p.error!)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<LaporanHamaProvider>();
    final bottomPadding = MediaQuery.paddingOf(context).bottom;

    return Scaffold(
      appBar: AppBar(
        title: Semantics(
          header: true,
          child: Text(
              widget.id == null ? 'Buat Laporan Hama' : 'Edit Laporan Hama'),
        ),
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 720),
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.md + bottomPadding,
            ),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Semantics(
                    textField: true,
                    label: 'Tanggal laporan',
                    child: DateField(
                      controller: _tanggalCtrl,
                      label: 'Tanggal Laporan',
                      errorText: _fe('tanggal'),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  OptSearchField(
                    key: ValueKey<int?>(_optId),
                    options: p.optList,
                    value: _optId,
                    loading: p.loading && p.optList.isEmpty,
                    errorText: _fe('master_opt_id'),
                    onChanged: (v) => setState(() => _optId = v),
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
                    errorKabupaten: _fe('kabupaten_id'),
                    errorKecamatan: _fe('kecamatan_id'),
                    errorDesa: _fe('desa_id'),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Semantics(
                    textField: true,
                    label: 'Lokasi atau blok sawah',
                    child: TextFormField(
                      controller: _lokasiCtrl,
                      decoration: InputDecoration(
                        labelText: 'Lokasi / Blok Sawah',
                        errorText: _fe('lokasi'),
                      ),
                      textInputAction: TextInputAction.next,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Semantics(
                    button: true,
                    label: 'Pilih tingkat keparahan',
                    child: DropdownButtonFormField<String>(
                      decoration: InputDecoration(
                        labelText: 'Tingkat Keparahan',
                        errorText: _fe('tingkat_keparahan'),
                      ),
                      value: _keparahan,
                      items: ['Ringan', 'Sedang', 'Berat']
                          .map(
                              (k) => DropdownMenuItem(value: k, child: Text(k)))
                          .toList(),
                      onChanged: (v) => setState(() => _keparahan = v),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final isCompact = constraints.maxWidth < 480;
                      if (isCompact) {
                        return Column(
                          children: [
                            Semantics(
                              textField: true,
                              label: 'Luas serangan dalam hektar',
                              child: TextFormField(
                                controller: _luasCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Luas Serangan (ha)',
                                  errorText: _fe('luas_serangan'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.angka(v,
                                    nonNegative: true, label: 'Luas Serangan'),
                              ),
                            ),
                            const SizedBox(height: AppSpacing.md),
                            Semantics(
                              textField: true,
                              label: 'Populasi hama',
                              child: TextFormField(
                                controller: _populasiCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Populasi',
                                  errorText: _fe('populasi'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.angka(v,
                                    nonNegative: true, label: 'Populasi'),
                              ),
                            ),
                          ],
                        );
                      }
                      return Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Semantics(
                              textField: true,
                              label: 'Luas serangan dalam hektar',
                              child: TextFormField(
                                controller: _luasCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Luas Serangan (ha)',
                                  errorText: _fe('luas_serangan'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.angka(v,
                                    nonNegative: true, label: 'Luas Serangan'),
                              ),
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Semantics(
                              textField: true,
                              label: 'Populasi hama',
                              child: TextFormField(
                                controller: _populasiCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Populasi',
                                  errorText: _fe('populasi'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.angka(v,
                                    nonNegative: true, label: 'Populasi'),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: AppSpacing.md),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final isCompact = constraints.maxWidth < 480;
                      if (isCompact) {
                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Semantics(
                              textField: true,
                              label: 'Koordinat latitude',
                              child: TextFormField(
                                controller: _latCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Latitude',
                                  errorText: _fe('latitude'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true, signed: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.koordinat(
                                    v, _lngCtrl.text),
                              ),
                            ),
                            const SizedBox(height: AppSpacing.xs),
                            Semantics(
                              button: true,
                              label: _gettingLocation
                                  ? 'Sedang mengambil lokasi GPS'
                                  : 'Ambil lokasi GPS saat ini',
                              child: SizedBox(
                                width: double.infinity,
                                child: OutlinedButton.icon(
                                  icon: _gettingLocation
                                      ? SizedBox.square(
                                          dimension: 18,
                                          child: CircularProgressIndicator(
                                              strokeWidth: 2),
                                        )
                                      : const Icon(Icons.my_location, size: 20),
                                  label: Text(_gettingLocation
                                      ? 'Mengambil Lokasiâ€¦'
                                      : 'Ambil Lokasi GPS'),
                                  onPressed: _loading || _gettingLocation
                                      ? null
                                      : _getLocation,
                                ),
                              ),
                            ),
                            const SizedBox(height: AppSpacing.md),
                            Semantics(
                              textField: true,
                              label: 'Koordinat longitude',
                              child: TextFormField(
                                controller: _lngCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Longitude',
                                  errorText: _fe('longitude'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true, signed: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.koordinat(
                                    _latCtrl.text, v),
                              ),
                            ),
                          ],
                        );
                      }
                      return Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Semantics(
                              textField: true,
                              label: 'Koordinat latitude',
                              child: TextFormField(
                                controller: _latCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Latitude',
                                  errorText: _fe('latitude'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true, signed: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.koordinat(
                                    v, _lngCtrl.text),
                              ),
                            ),
                          ),
                          const SizedBox(width: AppSpacing.xs),
                          Semantics(
                            button: true,
                            label: _gettingLocation
                                ? 'Sedang mengambil lokasi GPS'
                                : 'Ambil lokasi GPS saat ini',
                            child: Tooltip(
                              message: 'Ambil Lokasi GPS',
                              child: IconButton(
                                icon: _gettingLocation
                                    ? const SizedBox.square(
                                        dimension: 22,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2),
                                      )
                                    : const Icon(Icons.my_location),
                                onPressed: _loading || _gettingLocation
                                    ? null
                                    : _getLocation,
                              ),
                            ),
                          ),
                          const SizedBox(width: AppSpacing.xs),
                          Expanded(
                            child: Semantics(
                              textField: true,
                              label: 'Koordinat longitude',
                              child: TextFormField(
                                controller: _lngCtrl,
                                decoration: InputDecoration(
                                  labelText: 'Longitude',
                                  errorText: _fe('longitude'),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true, signed: true),
                                textInputAction: TextInputAction.next,
                                validator: (v) => LaporanValidators.koordinat(
                                    _latCtrl.text, v),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Semantics(
                    textField: true,
                    label: 'Catatan lapangan, maksimal 2000 karakter',
                    child: TextFormField(
                      controller: _catatanCtrl,
                      decoration: InputDecoration(
                        labelText: 'Catatan Lapangan',
                        errorText: _fe('catatan'),
                      ),
                      maxLines: 3,
                      maxLength: 2000,
                      textInputAction: TextInputAction.newline,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  FotoPicker(
                    fotoFile: _foto,
                    existingFotoUrl: _existingFotoUrl,
                    onFotoChanged: (f) => setState(() => _foto = f),
                    onClearExistingFoto: () =>
                        setState(() => _existingFotoUrl = null),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final isCompact = constraints.maxWidth < 480;
                      if (isCompact) {
                        return Column(
                          children: [
                            Semantics(
                              button: true,
                              label: 'Simpan laporan sebagai draf',
                              child: OutlinedButton(
                                key: const Key('btn_simpan_draf'),
                                onPressed: _loading ? null : _handleSaveDraft,
                                style: OutlinedButton.styleFrom(
                                  minimumSize: const Size(double.infinity, 52),
                                ),
                                child: const Text('Simpan Draf'),
                              ),
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Semantics(
                              button: true,
                              label:
                                  'Kirim laporan ke admin untuk diverifikasi',
                              child: FilledButton(
                                key: const Key('btn_kirim_laporan'),
                                onPressed: _loading ? null : _handleSubmit,
                                style: FilledButton.styleFrom(
                                  minimumSize: const Size(double.infinity, 52),
                                ),
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
                        );
                      }
                      return Row(
                        children: [
                          Expanded(
                            child: Semantics(
                              button: true,
                              label: 'Simpan laporan sebagai draf',
                              child: OutlinedButton(
                                key: const Key('btn_simpan_draf'),
                                onPressed: _loading ? null : _handleSaveDraft,
                                style: OutlinedButton.styleFrom(
                                  minimumSize: const Size(double.infinity, 52),
                                ),
                                child: const Text('Simpan Draf'),
                              ),
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Semantics(
                              button: true,
                              label:
                                  'Kirim laporan ke admin untuk diverifikasi',
                              child: FilledButton(
                                key: const Key('btn_kirim_laporan'),
                                onPressed: _loading ? null : _handleSubmit,
                                style: FilledButton.styleFrom(
                                  minimumSize: const Size(double.infinity, 52),
                                ),
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
                          ),
                        ],
                      );
                    },
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
