import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../connectivity_service.dart';
import '../network_diagnostic.dart';

/// Banner yang ditampilkan di atas halaman ketika koneksi server bermasalah.
///
/// Menampilkan:
/// - Ikon dan warna sesuai jenis error (offline vs server tidak tersedia)
/// - Pesan singkat yang bisa dimengerti pengguna lapangan
/// - Tombol "Diagnosa" untuk detail teknis (admin/debug)
/// - Tombol "Coba Lagi" untuk retry koneksi
///
/// Gunakan di halaman utama atau screen mana pun yang perlu data dari server.
class ConnectionErrorBanner extends StatelessWidget {
  /// Callback dipanggil setelah diagnosis selesai dan koneksi berhasil.
  final VoidCallback? onConnected;

  /// Tampilkan tombol "Detail Teknis" (berguna untuk petugas yang melapor).
  final bool showTechnicalDetail;

  const ConnectionErrorBanner({
    super.key,
    this.onConnected,
    this.showTechnicalDetail = false,
  });

  @override
  Widget build(BuildContext context) {
    final connectivity = context.watch<ConnectivityService>();

    // Tidak tampilkan apa-apa jika koneksi normal
    if (connectivity.isOnline && connectivity.lastDiagnostic == null) {
      return const SizedBox.shrink();
    }
    if (connectivity.lastDiagnostic?.isHealthy == true) {
      return const SizedBox.shrink();
    }

    final failure = connectivity.lastDiagnostic?.failure ??
        (connectivity.isOnline
            ? ConnectionFailure.serverUnreachable
            : ConnectionFailure.noNetwork);

    return _BannerContent(
      failure: failure,
      message: connectivity.connectionErrorMessage ??
          (connectivity.isOnline
              ? 'Tidak dapat terhubung ke server.'
              : 'Perangkat Anda sedang offline.'),
      suggestions: connectivity.lastDiagnostic?.suggestions ?? const [],
      isDiagnosing: connectivity.isDiagnosing,
      showTechnicalDetail: showTechnicalDetail,
      technicalDetail: connectivity.lastDiagnostic?.technicalDetail,
      onRetry: () async {
        final result = await connectivity.runDiagnostic();
        if (result.isHealthy && onConnected != null) {
          onConnected!();
        }
      },
    );
  }
}

class _BannerContent extends StatefulWidget {
  final ConnectionFailure failure;
  final String message;
  final List<String> suggestions;
  final bool isDiagnosing;
  final bool showTechnicalDetail;
  final String? technicalDetail;
  final VoidCallback onRetry;

  const _BannerContent({
    required this.failure,
    required this.message,
    required this.suggestions,
    required this.isDiagnosing,
    required this.showTechnicalDetail,
    required this.technicalDetail,
    required this.onRetry,
  });

  @override
  State<_BannerContent> createState() => _BannerContentState();
}

class _BannerContentState extends State<_BannerContent> {
  bool _expanded = false;

  Color get _bgColor {
    return switch (widget.failure) {
      ConnectionFailure.noNetwork        => Colors.orange.shade50,
      ConnectionFailure.serverUnreachable => Colors.red.shade50,
      ConnectionFailure.serverTimeout    => Colors.amber.shade50,
      ConnectionFailure.sslError         => Colors.purple.shade50,
      _                                  => Colors.grey.shade100,
    };
  }

  Color get _borderColor {
    return switch (widget.failure) {
      ConnectionFailure.noNetwork        => Colors.orange.shade300,
      ConnectionFailure.serverUnreachable => Colors.red.shade300,
      ConnectionFailure.serverTimeout    => Colors.amber.shade400,
      ConnectionFailure.sslError         => Colors.purple.shade300,
      _                                  => Colors.grey.shade300,
    };
  }

  Color get _iconColor {
    return switch (widget.failure) {
      ConnectionFailure.noNetwork        => Colors.orange.shade800,
      ConnectionFailure.serverUnreachable => Colors.red.shade700,
      ConnectionFailure.serverTimeout    => Colors.amber.shade800,
      ConnectionFailure.sslError         => Colors.purple.shade700,
      _                                  => Colors.grey.shade700,
    };
  }

  IconData get _icon {
    return switch (widget.failure) {
      ConnectionFailure.noNetwork        => Icons.wifi_off,
      ConnectionFailure.serverUnreachable => Icons.cloud_off,
      ConnectionFailure.serverTimeout    => Icons.hourglass_empty,
      ConnectionFailure.sslError         => Icons.lock_clock,
      ConnectionFailure.invalidResponse  => Icons.error_outline,
      _                                  => Icons.warning_amber_outlined,
    };
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(12, 8, 12, 0),
      decoration: BoxDecoration(
        color: _bgColor,
        border: Border.all(color: _borderColor),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Baris utama ──────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
            child: Row(
              children: [
                Icon(_icon, color: _iconColor, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    widget.message,
                    style: TextStyle(
                      color: _iconColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                // Expand/collapse saran
                if (widget.suggestions.isNotEmpty)
                  IconButton(
                    icon: Icon(
                      _expanded ? Icons.expand_less : Icons.expand_more,
                      size: 18,
                      color: _iconColor,
                    ),
                    onPressed: () => setState(() => _expanded = !_expanded),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                    tooltip: _expanded ? 'Sembunyikan saran' : 'Lihat saran',
                  ),
              ],
            ),
          ),

          // ── Daftar saran (expandable) ────────────────────────────────────
          if (_expanded && widget.suggestions.isNotEmpty) ...[
            Divider(height: 1, color: _borderColor),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
              child: Text(
                'Langkah yang dapat dicoba:',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: _iconColor,
                ),
              ),
            ),
            ...widget.suggestions.map(
              (s) => Padding(
                padding: const EdgeInsets.fromLTRB(20, 2, 12, 2),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('• ', style: TextStyle(color: _iconColor, fontSize: 12)),
                    Expanded(
                      child: Text(
                        s,
                        style: TextStyle(color: _iconColor, fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            // Detail teknis (hanya jika flag aktif)
            if (widget.showTechnicalDetail && widget.technicalDetail != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 6, 12, 4),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.black12,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    widget.technicalDetail!,
                    style: const TextStyle(
                      fontSize: 10,
                      fontFamily: 'monospace',
                      color: Colors.black87,
                    ),
                  ),
                ),
              ),
            const SizedBox(height: 4),
          ],

          // ── Tombol aksi ──────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (widget.isDiagnosing)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    child: SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: _iconColor,
                      ),
                    ),
                  )
                else
                  TextButton.icon(
                    key: const Key('btn_retry_connection'),
                    onPressed: widget.onRetry,
                    icon: Icon(Icons.refresh, size: 16, color: _iconColor),
                    label: Text(
                      'Coba Lagi',
                      style: TextStyle(color: _iconColor, fontSize: 13),
                    ),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 4,
                      ),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Widget inline sederhana untuk menampilkan status koneksi di halaman list.
/// Digunakan saat halaman membutuhkan indikator minimal tanpa banner penuh.
class ConnectionStatusIndicator extends StatelessWidget {
  const ConnectionStatusIndicator({super.key});

  @override
  Widget build(BuildContext context) {
    final connectivity = context.watch<ConnectivityService>();
    if (connectivity.isOnline) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      color: Colors.amber.shade100,
      child: Row(
        children: [
          Icon(Icons.wifi_off, size: 16, color: Colors.amber.shade800),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Tidak ada koneksi internet. Menampilkan data terakhir.',
              style: TextStyle(
                fontSize: 12,
                color: Colors.amber.shade900,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
