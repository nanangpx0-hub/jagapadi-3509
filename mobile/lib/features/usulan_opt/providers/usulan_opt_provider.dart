import 'package:flutter/foundation.dart';
import '../../../core/api_client.dart';
import '../models/usulan_opt.dart';

class UsulanOptProvider extends ChangeNotifier {
  final ApiClient api;
  UsulanOptProvider(this.api);

  List<UsulanOpt> _list = [];
  UsulanOpt? _detail;
  bool _loading = false;
  String? _error;
  Map<String, String> _fieldErrors = {};
  int _page = 1;
  int _totalPages = 1;
  String? _statusFilter;

  List<UsulanOpt> get list => _list;
  UsulanOpt? get detail => _detail;
  bool get loading => _loading;
  String? get error => _error;
  Map<String, String> get fieldErrors => _fieldErrors;
  int get page => _page;
  int get totalPages => _totalPages;

  void clearFieldErrors() {
    _fieldErrors = {};
    notifyListeners();
  }

  Future<void> load({int page = 1, String? status}) async {
    _loading = true;
    _error = null;
    _page = page;
    _statusFilter = status;
    notifyListeners();

    final q = <String, dynamic>{'page': page, 'per_page': 20};
    if (status != null && status.isNotEmpty) q['status'] = status;
    final res = await api.get('/usulan-opt', queryParams: q);
    _loading = false;

    if (res.success && res.data != null) {
      final raw = res.data!['data'] as List<dynamic>? ?? [];
      _list = raw
          .map((e) => UsulanOpt.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
      _totalPages = (res.data!['meta'] as Map<String, dynamic>?)?['total_pages'] as int? ?? 1;
    } else {
      _error = res.message ?? 'Gagal memuat usulan OPT';
      _list = [];
    }
    notifyListeners();
  }

  Future<void> loadDetail(int id) async {
    _loading = true;
    _error = null;
    notifyListeners();

    final res = await api.get('/usulan-opt/$id');
    _loading = false;
    if (res.success && res.data != null) {
      _detail = UsulanOpt.fromJson(Map<String, dynamic>.from(res.data!));
    } else {
      _error = res.message ?? 'Gagal memuat detail usulan';
      _detail = null;
    }
    notifyListeners();
  }

  /// Simpan draf atau submit usulan baru.
  ///
  /// Returns server id jika sukses, null jika gagal.
  Future<int?> save(Map<String, dynamic> data, {int? id, required bool submit}) async {
    _loading = true;
    _error = null;
    _fieldErrors = {};
    notifyListeners();

    final payload = Map<String, dynamic>.from(data)
      ..['action'] = submit ? 'submit' : 'draft';

    final res = id != null
        ? await api.put('/usulan-opt/$id', data: payload)
        : await api.post('/usulan-opt', data: payload);
    _loading = false;

    if (res.success && res.data != null) {
      final serverId = res.data!['id'] as int?;
      notifyListeners();
      return serverId;
    }

    if (res.errors != null && res.errors!.isNotEmpty) {
      _fieldErrors = res.errors!.map(
        (k, v) => MapEntry(k, v is List ? v.join('\n') : v.toString()),
      );
    }
    _error = res.message ?? 'Gagal menyimpan usulan OPT';
    notifyListeners();
    return null;
  }

  Future<bool> submitDraft(int id) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await api.post('/usulan-opt/$id/submit');
    _loading = false;
    if (!res.success) _error = res.message ?? 'Gagal mengirim usulan';
    notifyListeners();
    return res.success;
  }

  Future<bool> resubmit(int id) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await api.post('/usulan-opt/$id/resubmit');
    _loading = false;
    if (!res.success) _error = res.message ?? 'Gagal mengirim ulang usulan';
    notifyListeners();
    return res.success;
  }

  String? get statusFilter => _statusFilter;
}
