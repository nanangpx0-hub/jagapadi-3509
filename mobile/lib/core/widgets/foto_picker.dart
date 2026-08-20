import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';
import '../photo_compressor.dart';
import '../theme.dart';

class FotoPicker extends StatefulWidget {
  final File? fotoFile;
  final String? existingFotoUrl;
  final ValueChanged<File?> onFotoChanged;
  final VoidCallback? onClearExistingFoto;

  const FotoPicker({
    super.key,
    this.fotoFile,
    this.existingFotoUrl,
    required this.onFotoChanged,
    this.onClearExistingFoto,
  });

  @override
  State<FotoPicker> createState() => _FotoPickerState();
}

class _FotoPickerState extends State<FotoPicker> with WidgetsBindingObserver {
  final ImagePicker _picker = ImagePicker();
  bool _picking = false;
  int? _fotoSize;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _recoverLostImage();
    _refreshFotoSize();
  }

  @override
  void didUpdateWidget(FotoPicker oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.fotoFile, widget.fotoFile) &&
        widget.fotoFile?.path != oldWidget.fotoFile?.path) {
      _refreshFotoSize();
    }
  }

  Future<void> _refreshFotoSize() async {
    final file = widget.fotoFile;
    if (file == null) {
      if (_fotoSize != null) setState(() => _fotoSize = null);
      return;
    }
    final size = await file.length();
    if (mounted && size != _fotoSize) setState(() => _fotoSize = size);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _recoverLostImage();
  }

  Future<void> _recoverLostImage() async {
    try {
      final response = await _picker.retrieveLostData();
      if (!mounted || response.isEmpty) return;
      final file = response.files?.firstOrNull;
      if (file != null) widget.onFotoChanged(File(file.path));
    } on PlatformException catch (error) {
      _showError(
          'Foto kamera tidak dapat dipulihkan: ${error.message ?? error.code}.');
    }
  }

  Future<void> _pickFoto() async {
    if (_picking) return;
    setState(() => _picking = true);
    try {
      var permission = await Permission.camera.status;
      if (permission.isDenied) permission = await Permission.camera.request();
      if (!permission.isGranted) {
        _showError(
          permission.isPermanentlyDenied
              ? 'Izin kamera diblokir permanen. Aktifkan dari pengaturan aplikasi.'
              : 'Izin kamera diperlukan untuk mengambil foto laporan.',
          openSettings: permission.isPermanentlyDenied,
        );
        return;
      }

      final picked = await _picker.pickImage(
        source: ImageSource.camera,
        maxWidth: 1600,
        imageQuality: 85,
        requestFullMetadata: false,
      );
      if (picked != null && mounted) {
        final file = File(picked.path);
        if (!await file.exists() || await file.length() == 0) {
          _showError('Berkas foto kamera kosong. Silakan ambil foto ulang.');
          return;
        }
        // Kompresi ke <2 MB (isolate terpisah, tidak memblokir UI) agar
        // pengiriman laporan cepat meski di jaringan 3G/4G.
        final optimized = await PhotoCompressor.compressIfNeeded(file);
        widget.onFotoChanged(optimized);
      }
    } on PlatformException catch (error) {
      _showError(_cameraErrorMessage(error));
    } catch (error) {
      _showError('Kamera tidak dapat digunakan: ${error.toString()}');
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  String _cameraErrorMessage(PlatformException error) {
    return switch (error.code) {
      'camera_access_denied' => 'Izin kamera ditolak.',
      'camera_access_denied_without_prompt' =>
        'Izin kamera dinonaktifkan. Aktifkan melalui pengaturan aplikasi.',
      'camera_access_restricted' => 'Akses kamera dibatasi pada perangkat ini.',
      'no_available_camera' => 'Kamera tidak tersedia pada perangkat ini.',
      _ =>
        'Kamera gagal dibuka: ${error.message ?? error.code}. Coba tutup aplikasi kamera lain.',
    };
  }

  void _showError(String message, {bool openSettings = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        action: openSettings
            ? const SnackBarAction(
                label: 'Pengaturan',
                onPressed: openAppSettings,
              )
            : null,
      ),
    );
  }

  String _formatFileSize(int bytes) {
    if (bytes >= 1024 * 1024) {
      final mb = bytes / (1024 * 1024);
      return '${mb.toStringAsFixed(1)} MB';
    } else {
      final kb = bytes / 1024;
      return '${kb.toStringAsFixed(0)} KB';
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final hasNewFile = widget.fotoFile != null;
    final hasExistingUrl =
        widget.existingFotoUrl != null && widget.existingFotoUrl!.isNotEmpty;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          header: true,
          child: Text(
            'Foto Laporan',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        if (!hasNewFile && !hasExistingUrl)
          Semantics(
            button: true,
            label: _picking
                ? 'Membuka kamera, tunggu sebentar'
                : 'Ambil foto laporan dengan kamera',
            child: OutlinedButton.icon(
              onPressed: _picking ? null : _pickFoto,
              icon: _picking
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.camera_alt, size: 20),
              label: Text(_picking ? 'Membuka Kamera…' : 'Ambil Foto Kamera'),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(double.infinity, 52),
              ),
            ),
          )
        else
          Semantics(
            label: hasNewFile
                ? 'Pratinjau foto baru, ukuran ${_formatFileSize(_fotoSize ?? 0)}'
                : 'Pratinjau foto tersimpan di server',
            child: Stack(
              children: [
                Container(
                  width: double.infinity,
                  height: 180,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    color: scheme.surfaceContainerHighest,
                    border: Border.all(color: scheme.outlineVariant),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    child: hasNewFile
                        ? Image.file(
                            widget.fotoFile!,
                            fit: BoxFit.cover,
                            width: double.infinity,
                          )
                        : Image.network(
                            widget.existingFotoUrl!,
                            fit: BoxFit.cover,
                            width: double.infinity,
                            errorBuilder: (_, __, ___) => Center(
                              child: Icon(
                                Icons.broken_image,
                                size: 48,
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ),
                  ),
                ),
                Positioned(
                  top: AppSpacing.xs,
                  right: AppSpacing.xs,
                  child: Semantics(
                    button: true,
                    label: 'Hapus foto laporan',
                    child: Material(
                      color: Colors.black.withValues(alpha: 0.6),
                      shape: const CircleBorder(),
                      child: InkWell(
                        onTap: () {
                          if (hasNewFile) {
                            widget.onFotoChanged(null);
                          } else if (hasExistingUrl) {
                            widget.onClearExistingFoto?.call();
                          }
                        },
                        customBorder: const CircleBorder(),
                        child: const Padding(
                          padding: EdgeInsets.all(AppSpacing.xs),
                          child:
                              Icon(Icons.close, color: Colors.white, size: 20),
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned(
                  bottom: AppSpacing.xs,
                  left: AppSpacing.xs,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.xs,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.6),
                      borderRadius: BorderRadius.circular(AppRadius.sm),
                    ),
                    child: Text(
                      hasNewFile
                          ? 'Ukuran: ${_formatFileSize(_fotoSize ?? 0)}'
                          : 'Foto Tersimpan Server',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
