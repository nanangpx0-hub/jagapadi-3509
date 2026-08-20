import 'dart:io';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:share_plus/share_plus.dart';

/// Service reusable untuk mengekspor data laporan ke PDF.
/// Digunakan oleh semua jenis laporan (hama, irigasi, pupuk, panen, cuaca, alat_sarana).
class PdfExportService {
  PdfExportService._();

  /// Generate dan tampilkan preview PDF dari daftar laporan.
  static Future<void> exportAndPreview({
    required String title,
    required List<String> columnHeaders,
    required List<List<String>> rows,
    String? subtitle,
  }) async {
    final pdf = await _buildDocument(
      title: title,
      columnHeaders: columnHeaders,
      rows: rows,
      subtitle: subtitle,
    );
    await Printing.layoutPdf(onLayout: (_) => pdf.save());
  }

  /// Generate PDF dan simpan ke penyimpanan lokal perangkat.
  /// Mengembalikan path file yang disimpan.
  static Future<String> exportAndSave({
    required String title,
    required List<String> columnHeaders,
    required List<List<String>> rows,
    String? subtitle,
    String? filename,
  }) async {
    final pdf = await _buildDocument(
      title: title,
      columnHeaders: columnHeaders,
      rows: rows,
      subtitle: subtitle,
    );

    final dir = await getApplicationDocumentsDirectory();
    final exportDir = Directory('${dir.path}/JAGAPADI_Export');
    if (!await exportDir.exists()) {
      await exportDir.create(recursive: true);
    }

    final now = DateFormat('yyyyMMdd_HHmmss').format(DateTime.now());
    final safeName = filename ?? title.replaceAll(RegExp(r'[^\w]'), '_');
    final file = File('${exportDir.path}/${safeName}_$now.pdf');
    await file.writeAsBytes(await pdf.save());
    return file.path;
  }

  /// Generate PDF dan bagikan via platform share sheet.
  static Future<void> exportAndShare({
    required String title,
    required List<String> columnHeaders,
    required List<List<String>> rows,
    String? subtitle,
  }) async {
    final path = await exportAndSave(
      title: title,
      columnHeaders: columnHeaders,
      rows: rows,
      subtitle: subtitle,
    );
    await SharePlus.instance.share(
      ShareParams(
        files: [XFile(path)],
        subject: title,
        text: 'Laporan $title - JAGAPADI',
      ),
    );
  }

  static Future<pw.Document> _buildDocument({
    required String title,
    required List<String> columnHeaders,
    required List<List<String>> rows,
    String? subtitle,
  }) async {
    final pdf = pw.Document(
      title: title,
      author: 'JAGAPADI - Kabupaten Jember',
      creator: 'JAGAPADI Mobile',
    );

    final now = DateFormat('dd MMMM yyyy, HH:mm', 'id_ID').format(DateTime.now());

    // Calculate column widths proportionally
    final colCount = columnHeaders.length;
    final colWidths = <int, pw.TableColumnWidth>{};
    for (int i = 0; i < colCount; i++) {
      if (i == 0) {
        colWidths[i] = const pw.FixedColumnWidth(30); // No.
      } else {
        colWidths[i] = const pw.FlexColumnWidth();
      }
    }

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(24),
        header: (context) => pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.start,
          children: [
            pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.Text(
                      'JAGAPADI — Kabupaten Jember',
                      style: pw.TextStyle(
                        fontSize: 14,
                        fontWeight: pw.FontWeight.bold,
                      ),
                    ),
                    pw.SizedBox(height: 2),
                    pw.Text(
                      title,
                      style: pw.TextStyle(
                        fontSize: 12,
                        fontWeight: pw.FontWeight.bold,
                        color: PdfColors.green800,
                      ),
                    ),
                    if (subtitle != null)
                      pw.Text(
                        subtitle,
                        style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
                      ),
                  ],
                ),
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.end,
                  children: [
                    pw.Text('Dicetak: $now', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey600)),
                    pw.Text('Halaman ${context.pageNumber}/${context.pagesCount}',
                        style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey600)),
                  ],
                ),
              ],
            ),
            pw.Divider(thickness: 1, color: PdfColors.green800),
            pw.SizedBox(height: 8),
          ],
        ),
        footer: (context) => pw.Container(
          alignment: pw.Alignment.centerRight,
          margin: const pw.EdgeInsets.only(top: 8),
          child: pw.Text(
            'JAGAPADI © ${DateTime.now().year} — Dinas Pertanian Kabupaten Jember',
            style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey500),
          ),
        ),
        build: (context) {
          if (rows.isEmpty) {
            return [
              pw.Center(
                child: pw.Padding(
                  padding: const pw.EdgeInsets.all(40),
                  child: pw.Text(
                    'Tidak ada data laporan untuk diekspor.',
                    style: const pw.TextStyle(fontSize: 12, color: PdfColors.grey600),
                  ),
                ),
              ),
            ];
          }

          return [
            pw.Text('Total: ${rows.length} laporan',
                style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700)),
            pw.SizedBox(height: 8),
            pw.TableHelper.fromTextArray(
              context: context,
              headers: columnHeaders,
              data: rows,
              columnWidths: colWidths,
              headerStyle: pw.TextStyle(
                fontSize: 8,
                fontWeight: pw.FontWeight.bold,
                color: PdfColors.white,
              ),
              headerDecoration: const pw.BoxDecoration(color: PdfColors.green800),
              headerAlignment: pw.Alignment.centerLeft,
              cellStyle: const pw.TextStyle(fontSize: 7),
              cellAlignment: pw.Alignment.centerLeft,
              cellPadding: const pw.EdgeInsets.symmetric(horizontal: 4, vertical: 3),
              oddRowDecoration: const pw.BoxDecoration(color: PdfColors.grey100),
              border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
            ),
          ];
        },
      ),
    );

    return pdf;
  }
}
