import 'dart:math';

/// Pembangkit ID operasi idempotent (client operation ID).
///
/// ID ini dibuat SEKALI per draf lokal dan dikirim ulang pada setiap retry
/// melalui header `Idempotency-Key` agar backend dapat mencegah duplikasi
/// laporan ketika request timeout setelah server memprosesnya.
class OperationId {
  OperationId._();

  static final Random _random = Random.secure();

  /// Menghasilkan 32 karakter hex acak (128-bit).
  ///
  /// Format stabil: `op-<hex>`, tanpa karakter yang sensitif terhadap
  /// casing ganda sehingga aman dijadikan header HTTP.
  static String generate() {
    final bytes = List<int>.generate(16, (_) => _random.nextInt(256));
    return 'op-${bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join()}';
  }
}
