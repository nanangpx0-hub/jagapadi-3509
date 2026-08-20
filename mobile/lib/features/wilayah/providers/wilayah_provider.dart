import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../models/wilayah.dart';

class WilayahProvider extends ChangeNotifier {
  final ApiClient _api;
  List<Kabupaten> _kabupatenList = [];
  List<Kecamatan> _kecamatanList = [];
  List<Desa> _desaList = [];
  bool _loading = false;
  bool _loadingKecamatan = false;
  bool _loadingDesa = false;
  String? _error;
  int _kecamatanRequest = 0;
  int _desaRequest = 0;

  WilayahProvider(this._api);

  List<Kabupaten> get kabupatenList => _kabupatenList;
  List<Kecamatan> get kecamatanList => _kecamatanList;
  List<Desa> get desaList => _desaList;
  bool get loading => _loading;
  bool get loadingKecamatan => _loadingKecamatan;
  bool get loadingDesa => _loadingDesa;
  String? get error => _error;

  Future<void> loadKabupaten() async {
    if (_kabupatenList.isNotEmpty) return;
    _loading = true;
    _error = null;
    notifyListeners();    try {
      final res = await _api.get('/wilayah/kabupaten');
      if (res.success && res.data != null) {
        _kabupatenList = _readList(res.data!)
            .map((e) => Kabupaten.fromJson(Map<String, dynamic>.from(e as Map)))
            .where((item) => item.id > 0 && item.nama.isNotEmpty)
            .toList();
        if (_kabupatenList.isEmpty) _error = 'Data kabupaten belum tersedia.';
      } else {
        _error = res.message ?? 'Daftar kabupaten gagal dimuat.';
      }
    } catch (_) {
      _error = 'Format data kabupaten tidak valid. Silakan coba lagi.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> loadKecamatan(int kabupatenId) async {
    final request = ++_kecamatanRequest;
    _desaRequest++;
    _kecamatanList = [];
    _desaList = [];
    _loadingKecamatan = true;
    _loadingDesa = false;
    _error = null;
    notifyListeners();
    try {
      final res = await _api.get(
        '/wilayah/kecamatan',
        queryParams: {'kabupaten_id': kabupatenId},
      );
      if (request != _kecamatanRequest) return;
      if (res.success && res.data != null) {
        _kecamatanList = _readList(res.data!)
            .map((e) => Kecamatan.fromJson(Map<String, dynamic>.from(e as Map)))
            .where((item) => item.id > 0 && item.nama.isNotEmpty)
            .toList();
        if (_kecamatanList.isEmpty) {
          _error = 'Tidak ada data kecamatan untuk kabupaten yang dipilih.';
        }
      } else {
        _error = res.message ?? 'Daftar kecamatan gagal dimuat.';
      }
    } catch (_) {
      if (request == _kecamatanRequest) {
        _error = 'Data kecamatan gagal diproses. Silakan coba lagi.';
      }
    } finally {
      if (request == _kecamatanRequest) {
        _loadingKecamatan = false;
        notifyListeners();
      }
    }
  }

  Future<void> loadDesa(int kecamatanId) async {
    final request = ++_desaRequest;
    _desaList = [];
    _loadingDesa = true;
    _error = null;
    notifyListeners();
    try {
      final res = await _api.get(
        '/wilayah/desa',
        queryParams: {'kecamatan_id': kecamatanId},
      );
      if (request != _desaRequest) return;
      if (res.success && res.data != null) {
        _desaList = _readList(res.data!)
            .map((e) => Desa.fromJson(Map<String, dynamic>.from(e as Map)))
            .where((item) => item.id > 0 && item.nama.isNotEmpty)
            .toList();
        if (_desaList.isEmpty) {
          _error = 'Tidak ada data desa untuk kecamatan yang dipilih.';
        }
      } else {
        _error = res.message ?? 'Daftar desa gagal dimuat.';
      }
    } catch (_) {
      if (request == _desaRequest) {
        _error = 'Data desa gagal diproses. Silakan coba lagi.';
      }
    } finally {
      if (request == _desaRequest) {
        _loadingDesa = false;
        notifyListeners();
      }
    }
  }

  List<dynamic> _readList(Map<String, dynamic> data) {
    final raw = data['data'];
    return raw is List<dynamic> ? raw : const <dynamic>[];
  }

  /// Bersihkan semua cache wilayah.
  /// Dipanggil saat logout agar pengguna berikutnya tidak melihat data lama.
  void clearCache() {
    _kabupatenList = [];
    _kecamatanList = [];
    _desaList      = [];
    _error         = null;
    notifyListeners();
  }
}
