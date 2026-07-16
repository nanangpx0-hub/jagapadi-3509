import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../models/laporan_hama.dart';

class LaporanHamaProvider extends ChangeNotifier {
  final ApiClient api;
  List<LaporanHama> _list = [];
  List<OptOption> _optList = [];
  LaporanHama? _detail;
  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _fieldErrors;
  int _total = 0;
  int _page = 1;
  String? _statusFilter;

  LaporanHamaProvider(this.api);

  List<LaporanHama> get list => _list;
  LaporanHama? get detail => _detail;
  List<OptOption> get optList => _optList;
  bool get loading => _loading;
  String? get error => _error;
  Map<String, dynamic>? get fieldErrors => _fieldErrors;
  int get total => _total;
  bool get hasMore => _list.length < _total;

  Future<void> loadList({bool refresh = false, String? status}) async {
    if (refresh) {
      _page = 1;
      _list = [];
    }
    _statusFilter = status;
    _loading = true;
    _error = null;
    notifyListeners();

    final q = <String, dynamic>{'page': _page, 'limit': 20, 'include_draft': 'true'};
    if (status != null && status != 'all') q['status'] = status;

    final res = await api.get('/laporan-hama', queryParams: q);
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List<dynamic>? ?? [];
      final items = rawList.map((e) => LaporanHama.fromJson({'data': e})).toList();
      if (refresh) {
        _list = items;
      } else {
        _list.addAll(items);
      }
      final meta = res.data!['meta'] as Map<String, dynamic>?;
      _total = meta?['total'] as int? ?? items.length;
      _page++;
    } else {
      _error = res.message ?? 'Gagal memuat data';
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadDetail(int id) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await api.get('/laporan-hama/$id');
    if (res.success && res.data != null) {
      _detail = LaporanHama.fromJson(res.data!);
    } else {
      _error = res.message ?? 'Gagal memuat detail';
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadOptList() async {
    if (_optList.isNotEmpty) return;
    final res = await api.get('/opt', queryParams: {'aktif': '1'});
    if (res.success && res.data != null) {
      final raw = res.data!['data'] as List<dynamic>? ?? [];
      _optList = raw.map((e) => OptOption.fromJson(e as Map<String, dynamic>)).toList();
    }
    notifyListeners();
  }

  Future<Map<String, dynamic>?> save(Map<String, dynamic> data, {int? id}) async {
    _loading = true;
    _fieldErrors = null;
    notifyListeners();
    final res = id != null ? await api.put('/laporan-hama/$id', data: data) : await api.post('/laporan-hama', data: data);
    _loading = false;
    notifyListeners();
    if (res.success) return res.data;
    if (res.errors != null) _fieldErrors = res.errors;
    _error = res.message;
    return null;
  }

  Future<Map<String, dynamic>?> submit(int id) async {
    _loading = true;
    _fieldErrors = null;
    notifyListeners();
    final res = await api.post('/laporan-hama/$id/submit');
    _loading = false;
    notifyListeners();
    if (res.success) return res.data;
    if (res.errors != null) _fieldErrors = res.errors;
    _error = res.message;
    return null;
  }

  Future<bool> delete(int id) async {
    _loading = true;
    notifyListeners();
    final res = await api.delete('/laporan-hama/$id');
    _loading = false;
    notifyListeners();
    if (res.success) {
      _list.removeWhere((e) => e.id == id);
    } else {
      _error = res.message;
    }
    return res.success;
  }

  Future<Map<String, dynamic>?> verify(int id, {String? catatan}) async {
    _loading = true;
    notifyListeners();
    final res = await api.post('/laporan-hama/$id/verifikasi', data: catatan != null ? {'catatan': catatan} : {});
    _loading = false;
    notifyListeners();
    if (res.success) { _detail = LaporanHama.fromJson(res.data!); return res.data; }
    _error = res.message;
    return null;
  }

  Future<Map<String, dynamic>?> reject(int id, String alasan) async {
    _loading = true;
    notifyListeners();
    final res = await api.post('/laporan-hama/$id/tolak', data: {'alasan': alasan});
    _loading = false;
    notifyListeners();
    if (res.success) { _detail = LaporanHama.fromJson(res.data!); return res.data; }
    _error = res.message;
    return null;
  }

  Future<Map<String, dynamic>?> archive(int id) async {
    _loading = true;
    notifyListeners();
    final res = await api.post('/laporan-hama/$id/archive');
    _loading = false;
    notifyListeners();
    if (res.success) { _detail = LaporanHama.fromJson(res.data!); return res.data; }
    _error = res.message;
    return null;
  }

  Future<Map<String, dynamic>?> resubmit(int id) async {
    _loading = true;
    notifyListeners();
    final res = await api.post('/laporan-hama/$id/resubmit');
    _loading = false;
    notifyListeners();
    if (res.success) { _detail = LaporanHama.fromJson(res.data!); return res.data; }
    _error = res.message;
    return null;
  }

  void clearError() {
    _error = null;
    _fieldErrors = null;
    notifyListeners();
  }
}
