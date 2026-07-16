import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../models/wilayah.dart';

class WilayahProvider extends ChangeNotifier {
  final ApiClient _api;
  List<Kabupaten> _kabupatenList = [];
  List<Kecamatan> _kecamatanList = [];
  List<Desa> _desaList = [];
  bool _loading = false;

  WilayahProvider(this._api);

  List<Kabupaten> get kabupatenList => _kabupatenList;
  List<Kecamatan> get kecamatanList => _kecamatanList;
  List<Desa> get desaList => _desaList;
  bool get loading => _loading;

  Future<void> loadKabupaten() async {
    if (_kabupatenList.isNotEmpty) return;
    _loading = true;
    notifyListeners();
    final res = await _api.get('/wilayah/kabupaten');
    if (res.success && res.data != null) {
      final list = res.data!['data'] as List<dynamic>? ?? [];
      _kabupatenList = list.map((e) => Kabupaten.fromJson(e as Map<String, dynamic>)).toList();
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadKecamatan(int kabupatenId) async {
    _kecamatanList = [];
    _desaList = [];
    _loading = true;
    notifyListeners();
    final res = await _api.get('/wilayah/kecamatan', queryParams: {'kabupaten_id': kabupatenId});
    if (res.success && res.data != null) {
      final list = res.data!['data'] as List<dynamic>? ?? [];
      _kecamatanList = list.map((e) => Kecamatan.fromJson(e as Map<String, dynamic>)).toList();
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadDesa(int kecamatanId) async {
    _desaList = [];
    _loading = true;
    notifyListeners();
    final res = await _api.get('/wilayah/desa', queryParams: {'kecamatan_id': kecamatanId});
    if (res.success && res.data != null) {
      final list = res.data!['data'] as List<dynamic>? ?? [];
      _desaList = list.map((e) => Desa.fromJson(e as Map<String, dynamic>)).toList();
    }
    _loading = false;
    notifyListeners();
  }
}
