import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../data/receipt_pdf_service.dart';
import '../models/sale_receipt.dart';

class SaleReceiptScreen extends StatefulWidget {
  const SaleReceiptScreen({
    super.key,
    required this.receipt,
    required this.settings,
    this.pdfService,
  });

  final SaleReceipt receipt;
  final Future<ReceiptSettings> settings;
  final ReceiptPdfService? pdfService;

  @override
  State<SaleReceiptScreen> createState() => _SaleReceiptScreenState();
}

class _SaleReceiptScreenState extends State<SaleReceiptScreen> {
  ReceiptSettings? _settings;
  String? _activeAction;
  String? _error;
  late final _pdf = widget.pdfService ?? ReceiptPdfService();

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    ReceiptSettings settings;
    try {
      settings = await widget.settings;
    } catch (_) {
      settings = const ReceiptSettings(isFallback: true);
    }
    if (mounted) setState(() => _settings = settings);
  }

  Future<void> _export(bool print) async {
    final settings = _settings;
    if (_activeAction != null || settings == null) return;
    setState(() {
      _activeAction = print ? 'Print' : 'Share PDF';
      _error = null;
    });
    try {
      if (print) {
        await _pdf.printReceipt(widget.receipt, settings);
      } else {
        await _pdf.shareReceipt(widget.receipt, settings);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = print
              ? 'Could not open printing. Your sale is saved. Try again or share the PDF.'
              : 'Could not share the PDF. Your sale is saved. Please try again.';
        });
      }
    } finally {
      if (mounted) setState(() => _activeAction = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final settings = _settings ?? const ReceiptSettings();
    final receipt = widget.receipt;
    final canExport =
        _settings != null && _activeAction == null && receipt.detailsAvailable;
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textDark,
        automaticallyImplyLeading: false,
        title: const Row(
          children: [
            Icon(Icons.receipt_long_rounded, color: AppColors.primary),
            SizedBox(width: 8),
            Text('Receipt'),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Close receipt',
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.close_rounded),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.check_circle_rounded,
                      color: AppColors.success,
                      size: 20,
                    ),
                    SizedBox(width: 8),
                    Text(
                      'Sale completed',
                      style: TextStyle(color: AppColors.success),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                if (_settings == null) const LinearProgressIndicator(),
                if (settings.isFallback)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: Text(
                      'Receipt settings unavailable. Default layout used.',
                    ),
                  ),
                if (!receipt.detailsAvailable)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: Text(
                      'The sale was saved, but full receipt details were not returned. Check Sales before trying another payment.',
                    ),
                  ),
                Container(
                  key: const ValueKey('receipt-paper'),
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(8),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x16000000),
                        blurRadius: 20,
                        offset: Offset(0, 5),
                      ),
                    ],
                  ),
                  child: DefaultTextStyle.merge(
                    style: const TextStyle(
                      fontFamily: 'ReceiptMono',
                      color: Color(0xFF111111),
                      fontSize: 11.5,
                      height: 1.55,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (settings.logoUrl.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Image.network(
                              settings.logoUrl,
                              height: 60,
                              fit: BoxFit.contain,
                              errorBuilder: (_, _, _) =>
                                  const SizedBox.shrink(),
                            ),
                          ),
                        if (settings.showBusinessName)
                          Text(
                            settings.businessName,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        for (final text in [settings.address, settings.header])
                          if (text.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 6),
                              child: Text(text, textAlign: TextAlign.center),
                            ),
                        const _ReceiptDivider(),
                        _ReceiptLine(
                          'Receipt #',
                          receipt.saleNumber.isEmpty
                              ? 'Unavailable'
                              : receipt.saleNumber,
                        ),
                        _ReceiptLine('Date', receipt.dateLabel),
                        if (receipt.cashier.isNotEmpty)
                          _ReceiptLine('Cashier', receipt.cashier),
                        if (receipt.customer.isNotEmpty)
                          _ReceiptLine('Customer', receipt.customer),
                        if (receipt.detailsAvailable) ...[
                          const _ReceiptDivider(),
                          Table(
                            columnWidths: const {
                              0: FlexColumnWidth(3),
                              1: FlexColumnWidth(1),
                              2: FlexColumnWidth(2),
                              3: FlexColumnWidth(2),
                            },
                            defaultVerticalAlignment:
                                TableCellVerticalAlignment.top,
                            children: [
                              const TableRow(
                                children: [
                                  _ReceiptCell('ITEM', header: true),
                                  _ReceiptCell(
                                    'QTY',
                                    header: true,
                                    right: true,
                                  ),
                                  _ReceiptCell(
                                    'PRICE',
                                    header: true,
                                    right: true,
                                  ),
                                  _ReceiptCell(
                                    'TOTAL',
                                    header: true,
                                    right: true,
                                  ),
                                ],
                              ),
                              const TableRow(
                                children: [
                                  _ReceiptDivider(height: 1),
                                  _ReceiptDivider(height: 1),
                                  _ReceiptDivider(height: 1),
                                  _ReceiptDivider(height: 1),
                                ],
                              ),
                              for (final item in receipt.items) ...[
                                TableRow(
                                  children: [
                                    _ReceiptCell(
                                      item.discountPerUnit > 0
                                          ? '${item.name}\nDiscount/unit: ${settings.money(item.discountPerUnit, withCurrency: false)}'
                                          : item.name,
                                    ),
                                    _ReceiptCell(
                                      item.quantityLabel,
                                      right: true,
                                    ),
                                    _ReceiptCell(
                                      settings.money(
                                        item.unitPrice,
                                        withCurrency: false,
                                      ),
                                      right: true,
                                    ),
                                    _ReceiptCell(
                                      settings.money(
                                        item.lineTotal,
                                        withCurrency: false,
                                      ),
                                      right: true,
                                      emphasized: true,
                                    ),
                                  ],
                                ),
                                const TableRow(
                                  children: [
                                    _ReceiptDivider(height: 1, dotted: true),
                                    _ReceiptDivider(height: 1, dotted: true),
                                    _ReceiptDivider(height: 1, dotted: true),
                                    _ReceiptDivider(height: 1, dotted: true),
                                  ],
                                ),
                              ],
                            ],
                          ),
                          const _ReceiptDivider(),
                          for (final row in receipt.summary(settings))
                            _ReceiptLine(
                              row.label,
                              row.value,
                              emphasized: row.emphasized,
                            ),
                        ],
                        if (receipt.notes.isNotEmpty) ...[
                          const _ReceiptDivider(),
                          Text('Note: ${receipt.notes}'),
                        ],
                        if (settings.footer.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 20),
                            child: Text(
                              settings.footer,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        const _ReceiptDivider(),
                        if (receipt.paymentLabel.isNotEmpty)
                          Center(
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 14,
                                vertical: 5,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFF1E293B),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                receipt.giftCardAmount > 0
                                    ? 'GIFT CARD + ${receipt.paymentLabel.toUpperCase()}'
                                    : receipt.paymentLabel.toUpperCase(),
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          color: Colors.white,
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    _error!,
                    style: const TextStyle(
                      color: AppColors.error,
                      fontSize: 12,
                    ),
                  ),
                ),
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: canExport ? () => _export(true) : null,
                      style: ElevatedButton.styleFrom(
                        minimumSize: const Size(0, 48),
                      ),
                      icon: const Icon(Icons.print_rounded, size: 18),
                      label: Text(
                        _activeAction == 'Print' ? 'Opening...' : 'Print',
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: canExport ? () => _export(false) : null,
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size(0, 48),
                      ),
                      icon: const Icon(Icons.ios_share_rounded, size: 18),
                      label: Text(
                        _activeAction == 'Share PDF'
                            ? 'Preparing...'
                            : 'Share PDF',
                      ),
                    ),
                  ),
                ],
              ),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('New Sale'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReceiptLine extends StatelessWidget {
  const _ReceiptLine(this.label, this.value, {this.emphasized = false});
  final String label, value;
  final bool emphasized;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.symmetric(vertical: emphasized ? 8 : 2),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          flex: 5,
          child: Text(
            label,
            style: TextStyle(
              fontWeight: emphasized ? FontWeight.w800 : FontWeight.normal,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          flex: 6,
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              fontWeight: emphasized ? FontWeight.w700 : FontWeight.normal,
            ),
          ),
        ),
      ],
    ),
  );
}

class _ReceiptCell extends StatelessWidget {
  const _ReceiptCell(
    this.text, {
    this.right = false,
    this.header = false,
    this.emphasized = false,
  });
  final String text;
  final bool right, header, emphasized;

  @override
  Widget build(BuildContext context) {
    final label = Text(
      text,
      maxLines: right ? 1 : null,
      textAlign: right ? TextAlign.right : TextAlign.left,
      style: TextStyle(
        fontSize: header ? 10 : 11,
        fontWeight: header || emphasized ? FontWeight.w700 : FontWeight.normal,
      ),
    );
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 4),
      child: right
          ? FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerRight,
              child: label,
            )
          : label,
    );
  }
}

class _ReceiptDivider extends StatelessWidget {
  const _ReceiptDivider({this.height = 16, this.dotted = false});
  final double height;
  final bool dotted;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    height: height,
    child: CustomPaint(painter: _ReceiptDashPainter(dotted: dotted)),
  );
}

class _ReceiptDashPainter extends CustomPainter {
  const _ReceiptDashPainter({this.dotted = false});
  final bool dotted;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = dotted ? const Color(0xFFDDDDDD) : const Color(0xFF999999)
      ..strokeWidth = dotted ? 0.5 : 0.7;
    final dash = dotted ? 1.0 : 3.0;
    for (double x = 0; x < size.width; x += dotted ? 3 : 6) {
      final end = x + dash < size.width ? x + dash : size.width;
      canvas.drawLine(
        Offset(x, size.height / 2),
        Offset(end, size.height / 2),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _ReceiptDashPainter oldDelegate) =>
      oldDelegate.dotted != dotted;
}
