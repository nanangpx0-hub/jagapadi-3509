import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:jagapadi_mobile/core/gps_service.dart';

void main() {
  tearDown(() {
    GpsService.platform = const GeolocatorGpsPlatform();
  });

  test('service lokasi mati → status disabled + label buka pengaturan',
      () async {
    GpsService.platform = _FakePlatform(
      serviceEnabled: false,
    );
    final result = await GpsService.getCurrentLocation();
    expect(result.status, GpsResultStatus.disabled);
    expect(result.settingsActionLabel, 'Buka Pengaturan');
    expect(result.isSuccess, isFalse);
  });

  test('izin ditolak → status denied tanpa label', () async {
    GpsService.platform = _FakePlatform(
      permission: LocationPermission.denied,
    );
    final result = await GpsService.getCurrentLocation();
    expect(result.status, GpsResultStatus.denied);
    expect(result.settingsActionLabel, isNull);
  });

  test('izin diblokir permanen → status deniedForever + label', () async {
    GpsService.platform = _FakePlatform(
      permission: LocationPermission.deniedForever,
    );
    final result = await GpsService.getCurrentLocation();
    expect(result.status, GpsResultStatus.deniedForever);
    expect(result.settingsActionLabel, 'Buka Pengaturan');
  });

  test('izin whileInUse → koordinat berhasil', () async {
    GpsService.platform = _FakePlatform(
      permission: LocationPermission.whileInUse,
      position: _pos(-8.1845, 113.6681),
    );
    final result = await GpsService.getCurrentLocation();
    expect(result.isSuccess, isTrue);
    expect(result.latitude, -8.1845);
    expect(result.longitude, 113.6681);
  });

  test('permintaan izin dilakukan saat izin denied', () async {
    var requested = false;
    GpsService.platform = _FakePlatform(
      permission: LocationPermission.denied,
      onRequestPermission: () async {
        requested = true;
        return LocationPermission.whileInUse;
      },
      position: _pos(0, 0),
    );
    final result = await GpsService.getCurrentLocation();
    expect(requested, isTrue);
    expect(result.isSuccess, isTrue);
  });

  test('dua percobaan gagal → status error', () async {
    GpsService.platform = _FakePlatform(
      permission: LocationPermission.whileInUse,
      positionError: Exception('fix lost'),
    );
    final result = await GpsService.getCurrentLocation();
    expect(result.status, GpsResultStatus.error);
    expect(result.isSuccess, isFalse);
  });

  test('kegagalan TimeoutException → status timeout', () async {
    GpsService.platform = _FakePlatform(
      permission: LocationPermission.whileInUse,
      positionError: TimeoutException('timeout'),
    );
    final result = await GpsService.getCurrentLocation();
    expect(result.status, GpsResultStatus.timeout);
  });

  test('panggilan kedua berhasil setelah percobaan pertama gagal', () async {
    var calls = 0;
    GpsService.platform = _FakePlatform(
      permission: LocationPermission.whileInUse,
      positionCalls: 2,
      position: _pos(1.5, 2.5),
      onGetPosition: () => calls++,
    );
    final result = await GpsService.getCurrentLocation();
    expect(calls, 2);
    expect(result.isSuccess, isTrue);
    expect(result.latitude, 1.5);
  });
}

Position _pos(double lat, double lng) => Position(
      latitude: lat,
      longitude: lng,
      timestamp: DateTime(2026, 1, 1),
      accuracy: 12.0,
      altitude: 100.0,
      altitudeAccuracy: 5.0,
      heading: 0.0,
      headingAccuracy: 5.0,
      speed: 0.0,
      speedAccuracy: 1.0,
      isMocked: false,
    );

class _FakePlatform implements GpsPlatform {
  final bool serviceEnabled;
  final LocationPermission permission;
  final Position? position;
  final Object? positionError;
  final int positionCalls;
  final void Function()? onGetPosition;
  final Future<LocationPermission> Function()? onRequestPermission;

  int _calls = 0;

  _FakePlatform({
    this.serviceEnabled = true,
    this.permission = LocationPermission.whileInUse,
    this.position,
    this.positionError,
    this.positionCalls = 1,
    this.onGetPosition,
    this.onRequestPermission,
  });

  @override
  Future<bool> isLocationServiceEnabled() async => serviceEnabled;

  @override
  Future<LocationPermission> checkPermission() async => permission;

  @override
  Future<LocationPermission> requestPermission() async {
    if (onRequestPermission != null) return onRequestPermission!();
    return permission;
  }

  @override
  Future<Position> getCurrentPosition({
    required LocationAccuracy desiredAccuracy,
    required Duration timeLimit,
  }) async {
    if (positionError != null) {
      _calls++;
      throw positionError!;
    }
    _calls++;
    onGetPosition?.call();
    if (position == null) throw Exception('no position');
    if (_calls < positionCalls) throw Exception('fix lost, retry');
    return position!;
  }

  @override
  Future<bool> openLocationSettings() async => true;

  @override
  Future<bool> openAppSettings() async => true;
}
