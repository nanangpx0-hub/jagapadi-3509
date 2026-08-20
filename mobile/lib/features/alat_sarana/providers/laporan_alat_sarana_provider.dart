import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../models/laporan_alat_sarana.dart';

class LaporanAlatSaranaProvider extends ChangeNotifier {
  final ApiClient api;
  List<LaporanAlatSarana> _list = [];
  LaporanAlatSarana? _detail;
  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _fieldErrors;
  int _total = 0;
  int _page = 1;
  String? _statusFilter;

  LaporanAlatSaranaProvider(this.api);

  List<LaporanAlatSarana> get list => _list;
  LaporanAlatSarana? get detail => _detail;
  bool get loading => _loading;
  String? get error => _error;
  Map<String, dynamic>? get fieldErrors => _fieldErrors;
  int get total => _total;
  String? get statusFilter => _statusFilter;
  bool get hasMore => _list.length < _total;

  Future<void> loadList(
      {bool refresh = false, String? status, String? search}) async {
    if (refresh) {
      _page = 1;
      _list = [];
    }
    _statusFilter = status;
    _loading = true;
    _error = null;
    notifyListeners();

    final q = <String, dynamic>{
      'page': _page,
      'limit': 20,
      'include_draft': 'true'
    };
    if (status != null && status != 'all') q['status'] = status;
    if (search != null && search.trim().isNotEmpty) q['q'] = search.trim();

    try {
      final res = await api.get('/laporan-alat-sarana', queryParams: q);
      if (res.success && res.data != null) {
        final rawList = res.data!['data'] as List<dynamic>? ?? [];
        final items = rawList.map((e) {
          if (e is Map<String, dynamic>) {
            return LaporanAlatSarana.fromJson(e);
          }
          return LaporanAlatSarana.fromJson(
              Map<String, dynamic>.from(e as Map));
        }).toList();
        if (refresh) {
          _list = items;
        } else {
          _list.addAll(items);
        }
        final meta = res.data!['meta'] as Map<String, dynamic>?;
        _total = meta?['total'] as int? ?? items.length;
        _page++;
      } else {
        _error = res.message ?? 'Gagal memuat data laporan alat & sarana';
      }
    } catch (e) {
      _error = 'Terjadi kesalahan saat memuat data: $e';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> loadDetail(int id) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final res = await api.get('/laporan-alat-sarana/$id');
      if (res.success && res.data != null) {
        _detail = LaporanAlatSarana.fromJson(res.data!);
      } else {
        _error = res.message ?? 'Gagal memuat detail laporan alat & sarana';
      }
    } catch (e) {
      _error = 'Terjadi kesalahan saat memuat detail: $e';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> save(
    Map<String, dynamic> data, {
    int? id,
    Map<String, String>? headers,
  }) async {
    _loading = true;
    _fieldErrors = null;
    notifyListeners();
    try {
      final res = id != null
          ? await api.put('/laporan-alat-sarana/$id',
              data: data, headers: headers)
          : await api.post('/laporan-alat-sarana',
              data: data, headers: headers);
      if (res.success && res.data != null) {
        _detail = LaporanAlatSarana.fromJson(res.data!);
        return res.data;
      }
      if (res.errors != null) _fieldErrors = res.errors;
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat menyimpan data: $e';
      return null;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> submit(int id) async {
    _loading = true;
    _fieldErrors = null;
    notifyListeners();
    try {
      final res = await api.post('/laporan-alat-sarana/$id/submit');
      if (res.success) return res.data;
      if (res.errors != null) _fieldErrors = res.errors;
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat submit: $e';
      return null;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<bool> delete(int id) async {
    _loading = true;
    notifyListeners();
    try {
      final res = await api.delete('/laporan-alat-sarana/$id');
      if (res.success) {
        _list.removeWhere((e) => e.id == id);
      } else {
        _error = res.message;
      }
      return res.success;
    } catch (e) {
      _error = 'Terjadi kesalahan saat menghapus: $e';
      return false;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> verify(int id, {String? catatan}) async {
    _loading = true;
    notifyListeners();
    try {
      final res = await api.post('/laporan-alat-sarana/$id/verifikasi',
          data: catatan != null ? {'catatan': catatan} : {});
      if (res.success) {
        _detail = LaporanAlatSarana.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan: $e';
      return null;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> reject(int id, String alasan) async {
    _loading = true;
    notifyListeners();
    try {
      final res = await api
          .post('/laporan-alat-sarana/$id/tolak', data: {'alasan': alasan});
      if (res.success) {
        _detail = LaporanAlatSarana.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan: $e';
      return null;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> archive(int id) async {
    _loading = true;
    notifyListeners();
    try {
      final res = await api.post('/laporan-alat-sarana/$id/archive');
      if (res.success) {
        _detail = LaporanAlatSarana.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan: $e';
      return null;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> resubmit(int id) async {
    _loading = true;
    notifyListeners();
    try {
      final res = await api.post('/laporan-alat-sarana/$id/resubmit');
      if (res.success) {
        _detail = LaporanAlatSarana.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan: $e';
      return null;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  void clearError() {
    _error = null;
    _fieldErrors = null;
    notifyListeners();
  }
}
