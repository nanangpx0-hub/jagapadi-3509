import 'dart:async';

import 'package:geolocator/geolocator.dart';

/// Status hasil pengambilan lokasi GPS.
enum GpsResultStatus {
  success,
  disabled,
  denied,
  deniedForever,
  timeout,
  error,
}

/// Hasil pengambilan lokasi GPS.
class GpsResult {
  final GpsResultStatus status;
  final double? latitude;
  final double? longitude;
  final double? accuracyMeters;
  final String message;
  final String? settingsActionLabel;

  const GpsResult({
    required this.status,
    this.latitude,
    this.longitude,
    this.accuracyMeters,
    required this.message,
    this.settingsActionLabel,
  });

  bool get isSuccess => status == GpsResultStatus.success;
}

/// Abstraksi platform GPS agar alur seragam di semua form dan mudah diuji.
abstract class GpsPlatform {
  Future<bool> isLocationServiceEnabled();
  Future<LocationPermission> checkPermission();
  Future<LocationPermission> requestPermission();
  Future<Position> getCurrentPosition({
    required LocationAccuracy desiredAccuracy,
    required Duration timeLimit,
  });
  Future<bool> openLocationSettings();
  Future<bool> openAppSettings();
}

/// Implementasi nyata menggunakan package geolocator.
class GeolocatorGpsPlatform implements GpsPlatform {
  const GeolocatorGpsPlatform();

  @override
  Future<bool> isLocationServiceEnabled() =>
      Geolocator.isLocationServiceEnabled();

  @override
  Future<LocationPermission> checkPermission() => Geolocator.checkPermission();

  @override
  Future<LocationPermission> requestPermission() =>
      Geolocator.requestPermission();

  @override
  Future<Position> getCurrentPosition({
    required LocationAccuracy desiredAccuracy,
    required Duration timeLimit,
  }) =>
      Geolocator.getCurrentPosition(
        desiredAccuracy: desiredAccuracy,
        timeLimit: timeLimit,
      );

  @override
  Future<bool> openLocationSettings() => Geolocator.openLocationSettings();

  @override
  Future<bool> openAppSettings() => Geolocator.openAppSettings();
}

/// Service GPS terpusat — alur seragam untuk semua form laporan:
/// 1. cek service lokasi, 2. cek izin, 3. minta izin, 4. tangani
/// denied/deniedForever/disabled/timeout, 5. ambil koordinat dengan 2
/// percobaan, 6. laporan akurasi.
class GpsService {
  GpsService._();

  static GpsPlatform platform = const GeolocatorGpsPlatform();

  static const int maxAttempts = 2;
  static const Duration attemptTimeout = Duration(seconds: 12);
  static const Duration retryDelay = Duration(milliseconds: 750);

  static Future<GpsResult> getCurrentLocation() async {
    try {
      if (!await platform.isLocationServiceEnabled()) {
        return const GpsResult(
          status: GpsResultStatus.disabled,
          message: 'Layanan lokasi belum aktif. Aktifkan GPS, lalu coba lagi.',
          settingsActionLabel: 'Buka Pengaturan',
        );
      }

      var permission = await platform.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await platform.requestPermission();
      }
      if (permission == LocationPermission.denied) {
        return const GpsResult(
          status: GpsResultStatus.denied,
          message:
              'Izin lokasi ditolak. Izinkan lokasi presisi untuk mengambil koordinat.',
        );
      }
      if (permission == LocationPermission.deniedForever) {
        return const GpsResult(
          status: GpsResultStatus.deniedForever,
          message:
              'Izin lokasi diblokir permanen. Aktifkan izin Lokasi dari pengaturan aplikasi.',
          settingsActionLabel: 'Buka Pengaturan',
        );
      }

      Position? pos;
      Object? lastError;
      for (var attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
          pos = await platform.getCurrentPosition(
            desiredAccuracy: LocationAccuracy.high,
            timeLimit: attemptTimeout,
          );
          break;
        } catch (error) {
          lastError = error;
          if (attempt < maxAttempts) {
            await Future<void>.delayed(retryDelay);
          }
        }
      }

      if (pos == null) {
        final isTimeout = lastError is TimeoutException;
        return GpsResult(
          status: isTimeout ? GpsResultStatus.timeout : GpsResultStatus.error,
          message: isTimeout
              ? 'GPS tidak memberi koordinat dalam '
                  '${(maxAttempts * attemptTimeout.inSeconds)} detik. '
                  'Pindah ke area terbuka dan coba lagi.'
              : 'Koordinat gagal diambil: $lastError',
        );
      }

      return GpsResult(
        status: GpsResultStatus.success,
        latitude: pos.latitude,
        longitude: pos.longitude,
        accuracyMeters: pos.accuracy,
        message:
            'Koordinat berhasil diambil (akurasi ${pos.accuracy.toStringAsFixed(0)} m)',
      );
    } catch (error) {
      return GpsResult(
        status: GpsResultStatus.error,
        message: 'Koordinat gagal diambil: ${error.toString()}',
      );
    }
  }
}
