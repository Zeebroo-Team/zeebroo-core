import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../models/sale_receipt.dart';

/// No checkout API calls here: exports always use the already-saved sale.
class ReceiptPdfService {
  String filename(SaleReceipt receipt) {
    final number = receipt.saleNumber.replaceAll(
      RegExp(r'[^a-zA-Z0-9_-]'),
      '_',
    );
    return 'Receipt-${number.isEmpty ? 'sale' : number}.pdf';
  }

  Future<void> printReceipt(
    SaleReceipt receipt,
    ReceiptSettings settings,
  ) async {
    final document = await _createDocument(receipt, settings);
    final bytes = await document.save();
    await Printing.layoutPdf(
      name: filename(receipt),
      format: document.document.pdfPageList.pages.first.pageFormat,
      // Keep the exported receipt dimensions, even if the driver requests A4.
      dynamicLayout: false,
      onLayout: (_) async => bytes,
    );
  }

  Future<void> shareReceipt(
    SaleReceipt receipt,
    ReceiptSettings settings,
  ) async {
    final bytes = await buildPdf(receipt, settings);
    await Printing.sharePdf(bytes: bytes, filename: filename(receipt));
  }

  Future<Uint8List> buildPdf(
    SaleReceipt receipt,
    ReceiptSettings settings,
  ) async => (await _createDocument(receipt, settings)).save();

  Future<pw.Document> _createDocument(
    SaleReceipt receipt,
    ReceiptSettings settings,
  ) async {
    if (!receipt.detailsAvailable) {
      throw StateError('The saved sale did not include full receipt details.');
    }
    final regular = await rootBundle.load(
      'assets/fonts/CourierPrime-Regular.ttf',
    );
    final bold = await rootBundle.load('assets/fonts/CourierPrime-Bold.ttf');
    final fallback = await rootBundle.load('assets/fonts/NotoSans-Regular.ttf');
    final sinhala = await rootBundle.load(
      'assets/fonts/NotoSansSinhala-Regular.ttf',
    );
    final tamil = await rootBundle.load(
      'assets/fonts/NotoSansTamil-Regular.ttf',
    );
    final document = pw.Document(
      title: 'Receipt ${receipt.saleNumber}',
      author: settings.businessName,
      theme: pw.ThemeData.withFont(
        base: pw.Font.ttf(regular),
        bold: pw.Font.ttf(bold),
        fontFallback: [
          pw.Font.ttf(fallback),
          pw.Font.ttf(sinhala),
          pw.Font.ttf(tamil),
        ],
      ).copyWith(defaultTextStyle: const pw.TextStyle(fontSize: 8)),
    );
    pw.ImageProvider? logo;
    if (settings.logoUrl.isNotEmpty) {
      try {
        logo = await networkImage(
          settings.logoUrl,
        ).timeout(const Duration(seconds: 3));
      } catch (_) {
        // A missing logo must not prevent printing the transaction.
      }
    }
    pw.Widget divider() => pw.Divider(
      height: 12,
      thickness: 0.5,
      color: PdfColors.grey600,
      borderStyle: pw.BorderStyle.dashed,
    );
    pw.Widget line(
      String label,
      String value, {
      bool bold = false,
    }) => pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 2),
      child: pw.Row(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Expanded(
            flex: 5,
            child: pw.Text(
              label,
              style: pw.TextStyle(
                fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
              ),
            ),
          ),
          pw.SizedBox(width: 8),
          pw.Expanded(
            flex: 6,
            child: pw.Text(
              value,
              textAlign: pw.TextAlign.right,
              style: pw.TextStyle(
                fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
              ),
            ),
          ),
        ],
      ),
    );
    final content = <pw.Widget>[
      if (logo != null) pw.Center(child: pw.Image(logo, width: 65, height: 45)),
      if (settings.showBusinessName)
        pw.Center(
          child: pw.Text(
            settings.businessName,
            textAlign: pw.TextAlign.center,
            style: pw.TextStyle(fontSize: 14, fontWeight: pw.FontWeight.bold),
          ),
        ),
      for (final text in [settings.address, settings.header])
        if (text.isNotEmpty)
          pw.Padding(
            padding: const pw.EdgeInsets.only(top: 4),
            child: pw.Center(
              child: pw.Text(text, textAlign: pw.TextAlign.center),
            ),
          ),
      divider(),
      line('Receipt #', receipt.saleNumber),
      line('Date', receipt.dateLabel),
      if (receipt.cashier.isNotEmpty) line('Cashier', receipt.cashier),
      if (receipt.customer.isNotEmpty) line('Customer', receipt.customer),
      divider(),
      pw.TableHelper.fromTextArray(
        headers: ['ITEM', 'QTY', 'PRICE', 'TOTAL'],
        data: [
          for (final item in receipt.items)
            [
              item.discountPerUnit > 0
                  ? '${item.name}\nItem discount/unit: ${settings.money(item.discountPerUnit, withCurrency: false)}'
                  : item.name,
              item.quantityLabel,
              settings.money(item.unitPrice, withCurrency: false),
              settings.money(item.lineTotal, withCurrency: false),
            ],
        ],
        border: null,
        headerDecoration: const pw.BoxDecoration(
          border: pw.Border(
            bottom: pw.BorderSide(
              color: PdfColors.grey600,
              width: 0.5,
              style: pw.BorderStyle.dashed,
            ),
          ),
        ),
        rowDecoration: const pw.BoxDecoration(
          border: pw.Border(
            bottom: pw.BorderSide(
              color: PdfColors.grey300,
              width: 0.3,
              style: pw.BorderStyle.dotted,
            ),
          ),
        ),
        headerStyle: pw.TextStyle(fontSize: 7, fontWeight: pw.FontWeight.bold),
        cellStyle: const pw.TextStyle(fontSize: 7),
        cellPadding: const pw.EdgeInsets.symmetric(vertical: 4, horizontal: 2),
        cellBuilder: (column, value, _) => column == 0
            ? null
            : pw.FittedBox(
                fit: pw.BoxFit.scaleDown,
                alignment: pw.Alignment.centerRight,
                child: pw.Text(
                  value.toString(),
                  maxLines: 1,
                  style: pw.TextStyle(
                    fontSize: 7,
                    fontWeight: column == 3
                        ? pw.FontWeight.bold
                        : pw.FontWeight.normal,
                  ),
                ),
              ),
        columnWidths: {
          0: const pw.FlexColumnWidth(3),
          1: const pw.FlexColumnWidth(1),
          2: const pw.FlexColumnWidth(2),
          3: const pw.FlexColumnWidth(2),
        },
        cellAlignments: {
          0: pw.Alignment.topLeft,
          1: pw.Alignment.topRight,
          2: pw.Alignment.topRight,
          3: pw.Alignment.topRight,
        },
        headerAlignments: {
          0: pw.Alignment.centerLeft,
          1: pw.Alignment.centerRight,
          2: pw.Alignment.centerRight,
          3: pw.Alignment.centerRight,
        },
      ),
      // Keep the totals together when a long receipt spans several pages.
      pw.Inseparable(
        child: pw.Column(
          mainAxisSize: pw.MainAxisSize.min,
          children: [
            divider(),
            for (final row in receipt.summary(settings))
              line(row.label, row.value, bold: row.emphasized),
          ],
        ),
      ),
      if (receipt.notes.isNotEmpty) ...[
        divider(),
        pw.Text('Note: ${receipt.notes}'),
      ],
      if (settings.footer.isNotEmpty) ...[
        pw.SizedBox(height: 14),
        pw.Center(
          child: pw.Text(
            settings.footer,
            textAlign: pw.TextAlign.center,
            style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
          ),
        ),
      ],
      divider(),
      pw.Center(
        child: pw.Text(
          receipt.giftCardAmount > 0
              ? 'GIFT CARD + ${receipt.paymentLabel.toUpperCase()}'
              : receipt.paymentLabel.toUpperCase(),
          style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
        ),
      ),
    ];
    final pageWidth = settings.paperWidth * PdfPageFormat.mm;
    if (receipt.items.length <= 10) {
      // Auto-height roll paper makes normal receipts compact.
      document.addPage(
        pw.Page(
          pageFormat: PdfPageFormat(
            pageWidth,
            double.infinity,
            marginAll: 4 * PdfPageFormat.mm,
          ),
          build: (_) =>
              pw.Column(mainAxisSize: pw.MainAxisSize.min, children: content),
        ),
      );
    } else {
      // Bound exceptionally long receipts to printable, unclipped pages.
      document.addPage(
        pw.MultiPage(
          pageFormat: PdfPageFormat(
            pageWidth,
            297 * PdfPageFormat.mm,
            marginAll: 4 * PdfPageFormat.mm,
          ),
          maxPages: 1000,
          build: (_) => content,
        ),
      );
    }
    return document;
  }
}
