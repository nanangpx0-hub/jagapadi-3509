import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../models/laporan_pupuk.dart';

class LaporanPupukProvider extends ChangeNotifier {
  final ApiClient api;
  List<LaporanPupuk> _list = [];
  LaporanPupuk? _detail;
  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _fieldErrors;
  int _total = 0;
  int _page = 1;
  String? _statusFilter;

  LaporanPupukProvider(this.api);

  List<LaporanPupuk> get list => _list;
  LaporanPupuk? get detail => _detail;
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
      final res = await api.get('/laporan-pupuk', queryParams: q);
      if (res.success && res.data != null) {
        final rawList = res.data!['data'] as List<dynamic>? ?? [];
        final items = rawList.map((e) {
          if (e is Map<String, dynamic>) {
            return LaporanPupuk.fromJson(e);
          }
          return LaporanPupuk.fromJson(Map<String, dynamic>.from(e as Map));
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
        _error = res.message ?? 'Gagal memuat data laporan pupuk';
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
      final res = await api.get('/laporan-pupuk/$id');
      if (res.success && res.data != null) {
        _detail = LaporanPupuk.fromJson(res.data!);
      } else {
        _error = res.message ?? 'Gagal memuat detail laporan pupuk';
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
          ? await api.put('/laporan-pupuk/$id', data: data, headers: headers)
          : await api.post('/laporan-pupuk', data: data, headers: headers);
      if (res.success && res.data != null) {
        _detail = LaporanPupuk.fromJson(res.data!);
        return res.data;
      }
      if (res.errors != null) _fieldErrors = res.errors;
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat menyimpan: $e';
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
      final res = await api.post('/laporan-pupuk/$id/submit');
      if (res.success) return res.data;
      if (res.errors != null) _fieldErrors = res.errors;
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat mengirim: $e';
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
      final res = await api.delete('/laporan-pupuk/$id');
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
      final res = await api.post('/laporan-pupuk/$id/verifikasi',
          data: catatan != null ? {'catatan': catatan} : {});
      if (res.success) {
        _detail = LaporanPupuk.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat verifikasi: $e';
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
      final res =
          await api.post('/laporan-pupuk/$id/tolak', data: {'alasan': alasan});
      if (res.success) {
        _detail = LaporanPupuk.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat menolak: $e';
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
      final res = await api.post('/laporan-pupuk/$id/archive');
      if (res.success) {
        _detail = LaporanPupuk.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat mengarsipkan: $e';
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
      final res = await api.post('/laporan-pupuk/$id/resubmit');
      if (res.success) {
        _detail = LaporanPupuk.fromJson(res.data!);
        return res.data;
      }
      _error = res.message;
      return null;
    } catch (e) {
      _error = 'Terjadi kesalahan saat mengirim ulang: $e';
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
