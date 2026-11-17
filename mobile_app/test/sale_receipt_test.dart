import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/pos/data/pos_receipt_repository.dart';
import 'package:zeebroo_mobile/features/pos/data/receipt_pdf_service.dart';
import 'package:zeebroo_mobile/features/pos/models/pos_cart_item.dart';
import 'package:zeebroo_mobile/features/pos/models/sale_receipt.dart';
import 'package:zeebroo_mobile/features/pos/screens/pos_screen.dart';
import 'package:zeebroo_mobile/features/pos/screens/sale_receipt_screen.dart';

Map<String, dynamic> savedSale({String method = 'cash', int itemCount = 2}) => {
  'id': 11,
  'sale_number': 'POS-0011',
  'sold_at': '2026-10-06T22:48:00+05:30',
  'payment_method': method,
  'payment_method_label': method == 'credit' ? 'Credit' : 'Cash',
  'cashier': {'name': 'akilawer'},
  'customer_name': 'Nimal',
  'subtotal': '32500.00',
  'discount_amount': '500',
  'coupon_discount': '2000',
  'coupon': {'code': 'CP-SAVE'},
  'total': '30000.00',
  'amount_paid': method == 'credit' ? '5000' : '30000',
  'gift_card_amount': '5000',
  'gift_card': {'code': 'GC-TEST'},
  'amount_tendered': method == 'credit' ? null : '26000',
  'change_amount': method == 'credit' ? null : '1000',
  'items': [
    for (var index = 0; index < itemCount; index++)
      {
        'product_name': index == 0 ? 'p03' : 'p02',
        'quantity': index == 0 ? '2' : '1',
        'unit_sell_price': index == 0 ? '12500.00' : '7500.00',
        'line_total': index == 0 ? '25000.00' : '7500.00',
        'discount_amount': 0,
      },
  ],
};

const settings = ReceiptSettings(businessName: 'pos', currency: 'LKR');

class FakeRepository extends PosReceiptRepository {
  int submissions = 0;
  Map<String, dynamic>? request;
  Object? failure;
  Completer<SaleReceipt>? pending;
  SaleReceipt receipt = SaleReceipt.fromJson(savedSale());

  @override
  Future<SaleReceipt> completeSale(Map<String, dynamic> body) async {
    submissions++;
    request = body;
    if (failure != null) throw failure!;
    return pending == null ? receipt : pending!.future;
  }

  @override
  Future<ReceiptSettings> loadSettings() async => settings;
}

class FakePdf extends ReceiptPdfService {
  int prints = 0, shares = 0;
  bool fail = false;
  SaleReceipt? exported;

  @override
  Future<void> printReceipt(
    SaleReceipt receipt,
    ReceiptSettings settings,
  ) async {
    prints++;
    if (fail) throw Exception('Printer unavailable');
    exported = receipt;
  }

  @override
  Future<void> shareReceipt(
    SaleReceipt receipt,
    ReceiptSettings settings,
  ) async {
    shares++;
    if (fail) throw Exception('Sharing unavailable');
    exported = receipt;
  }
}

Future<void> openCheckout(
  WidgetTester tester,
  FakeRepository repository,
  FakePdf pdf,
) async {
  await tester.binding.setSurfaceSize(const Size(430, 932));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(),
      home: PosScreen(
        receiptRepository: repository,
        receiptPdfService: pdf,
        initialCart: [
          PosCartItem(
            product: {
              'id': 1,
              'name': 'Cart product',
              'unit_sell_price': 1000.0,
              'stock_quantity': 20,
            },
          ),
        ],
      ),
    ),
  );
  await tester.pumpAndSettle();
  await tester.tap(find.byIcon(Icons.shopping_cart_rounded));
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.text('Complete Sale'));
  await tester.pumpAndSettle();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('Uses immutable server amounts and separates mixed payments', () {
    final data = savedSale();
    final receipt = SaleReceipt.fromJson(data);
    expect(receipt.total, 30000);
    expect(receipt.items.first.lineTotal, 25000);
    expect(receipt.paidByMethod, 25000);
    expect(receipt.outstanding, 0);
    expect(receipt.change, 1000);
    data['items'][0]['product_name'] = 'Changed cart';
    expect(receipt.items.first.name, 'p03');
    expect(() => receipt.items.clear(), throwsUnsupportedError);
    final rows = receipt.summary(settings);
    expect(
      rows.singleWhere((row) => row.label == 'Paid (Cash)').value,
      '25,000.00 LKR',
    );
    expect(
      rows.singleWhere((row) => row.label == 'Coupon CP-SAVE').value,
      '-2,000.00 LKR',
    );
  });

  test('Credit amount paid already includes gift cards', () {
    final receipt = SaleReceipt.fromJson(savedSale(method: 'credit'));
    expect(receipt.paidByMethod, 0);
    expect(receipt.outstanding, 25000);
    expect(receipt.summary(settings).last.value, '25,000.00 LKR');
  });

  test('Item prices from the server are already net of item discounts', () {
    final data = savedSale();
    data['items'][0]['discount_amount'] = '500';
    final item = SaleReceipt.fromJson(data).items.first;
    expect(item.unitPrice, 12500);
    expect(item.lineTotal, 25000);
    expect(item.discountPerUnit, 500);
  });

  test(
    'Incomplete numeric receipt data cannot be exported as zero totals',
    () async {
      final data = savedSale();
      data['items'][0]['line_total'] = 'invalid';
      final receipt = SaleReceipt.fromJson(data);
      expect(receipt.detailsAvailable, isFalse);
      await expectLater(
        ReceiptPdfService().buildPdf(receipt, settings),
        throwsStateError,
      );
    },
  );

  test(
    'Numeric strings and fractional quantities are accepted without NaN',
    () {
      expect(receiptNumber('NaN'), isNull);
      expect(receiptNumber(double.infinity), isNull);
      final item = ReceiptItem.fromJson({
        'quantity': '1.125',
        'unit_sell_price': '10.50',
        'line_total': '11.81',
      });
      expect(item.quantityLabel, '1.125');
      expect(item.lineTotal, 11.81);
      expect(SaleReceipt.fromJson({}).detailsAvailable, isFalse);
    },
  );

  test(
    'Uses existing receipt settings including width, address and currency',
    () {
      final parsed = ReceiptSettings.fromJson({
        'business_name': 'My store',
        'currency': 'Rs.',
        'currency_position': 'before',
        'receipt_paper_width': '58',
        'show_business_address': true,
        'receipt_address_line': 'Colombo',
        'receipt_footer': 'Come again',
        'show_business_name': false,
      });
      expect(parsed.money(1250), 'Rs. 1,250.00');
      expect(parsed.paperWidth, 58);
      expect(parsed.address, 'Colombo');
      expect(parsed.footer, 'Come again');
      expect(parsed.showBusinessName, isFalse);
    },
  );

  testWidgets(
    'Successful checkout closes sheet, opens receipt and clears cart once',
    (tester) async {
      final repository = FakeRepository();
      final pdf = FakePdf();
      await openCheckout(tester, repository, pdf);
      await tester.tap(find.text('Complete Sale'));
      await tester.pumpAndSettle();
      expect(find.byType(SaleReceiptScreen), findsOneWidget);
      expect(find.text('Checkout'), findsNothing);
      expect(find.text('POS-0011'), findsOneWidget);
      expect(repository.submissions, 1);
      expect(repository.request!['items'], [
        {'product_id': 1, 'qty': 1},
      ]);
      // Server total differs from the local cart: the receipt must use the server.
      expect(find.text('30,000.00 LKR'), findsOneWidget);
      await tester.tap(find.text('Print'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Share PDF'));
      await tester.pumpAndSettle();
      expect(pdf.prints, 1);
      expect(pdf.shares, 1);
      expect(repository.submissions, 1);
      await tester.tap(find.text('New Sale'));
      await tester.pumpAndSettle();
      expect(find.byType(PosScreen), findsOneWidget);
      expect(find.byType(SaleReceiptScreen), findsNothing);
      expect(find.text('Cart product'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Failed submission keeps checkout and cart and allows retry', (
    tester,
  ) async {
    final repository = FakeRepository()
      ..failure = Exception('Sale could not be saved');
    await openCheckout(tester, repository, FakePdf());
    await tester.tap(find.text('Complete Sale'));
    await tester.pumpAndSettle();
    expect(find.text('Checkout'), findsOneWidget);
    expect(find.byType(SaleReceiptScreen), findsNothing);
    expect(find.textContaining('Sale could not be saved'), findsOneWidget);
    repository.failure = null;
    await tester.ensureVisible(find.text('Complete Sale'));
    await tester.tap(find.text('Complete Sale'));
    await tester.pumpAndSettle();
    expect(repository.submissions, 2);
    expect(find.byType(SaleReceiptScreen), findsOneWidget);
  });

  testWidgets('Pending submission disables repeat taps and blocks back', (
    tester,
  ) async {
    final repository = FakeRepository()..pending = Completer<SaleReceipt>();
    await openCheckout(tester, repository, FakePdf());
    await tester.tap(find.text('Complete Sale'));
    await tester.pump();
    expect(find.text('Processing...'), findsOneWidget);
    expect(
      tester
          .widget<ElevatedButton>(
            find.widgetWithText(ElevatedButton, 'Processing...'),
          )
          .onPressed,
      isNull,
    );
    await tester.binding.handlePopRoute();
    await tester.pump();
    expect(find.text('Checkout'), findsOneWidget);
    expect(repository.submissions, 1);
    repository.pending!.complete(repository.receipt);
    await tester.pumpAndSettle();
    expect(find.byType(SaleReceiptScreen), findsOneWidget);
  });

  testWidgets('Underpayment is rejected locally without submitting', (
    tester,
  ) async {
    final repository = FakeRepository();
    await openCheckout(tester, repository, FakePdf());
    await tester.enterText(
      find.widgetWithText(TextField, 'Amount paid'),
      '900',
    );
    await tester.ensureVisible(find.text('Complete Sale'));
    await tester.tap(find.text('Complete Sale'));
    await tester.pumpAndSettle();
    expect(repository.submissions, 0);
    expect(find.textContaining('covers the amount due'), findsOneWidget);
  });

  testWidgets(
    'Incomplete saved response opens warning without enabling export or retry',
    (tester) async {
      final repository = FakeRepository()..receipt = SaleReceipt.fromJson({});
      await openCheckout(tester, repository, FakePdf());
      await tester.tap(find.text('Complete Sale'));
      await tester.pumpAndSettle();
      expect(find.byType(SaleReceiptScreen), findsOneWidget);
      expect(find.textContaining('The sale was saved'), findsOneWidget);
      expect(
        tester
            .widget<ElevatedButton>(
              find.widgetWithText(ElevatedButton, 'Print'),
            )
            .onPressed,
        isNull,
      );
      await tester.tap(find.byTooltip('Close receipt'));
      await tester.pumpAndSettle();
      expect(find.byType(PosScreen), findsOneWidget);
      expect(repository.submissions, 1);
    },
  );

  testWidgets(
    'Printing failure retains saved receipt and allows export retry',
    (tester) async {
      final pdf = FakePdf()..fail = true;
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: SaleReceiptScreen(
            receipt: SaleReceipt.fromJson(savedSale()),
            settings: Future.value(settings),
            pdfService: pdf,
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Print'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Your sale is saved'), findsOneWidget);
      expect(find.text('POS-0011'), findsOneWidget);
      pdf.fail = false;
      await tester.tap(find.text('Share PDF'));
      await tester.pumpAndSettle();
      expect(pdf.shares, 1);
      expect(pdf.exported?.saleNumber, 'POS-0011');
      expect(find.textContaining('Your sale is saved'), findsNothing);
    },
  );

  testWidgets('Small phone and long product names do not overflow', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final data = savedSale(itemCount: 50);
    data['items'][0]['product_name'] =
        'Inspirational stories with a very long product name';
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: SaleReceiptScreen(
          receipt: SaleReceipt.fromJson(data),
          settings: Future.value(settings),
          pdfService: FakePdf(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Print'), findsOneWidget);
    expect(find.text('Share PDF'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Unavailable receipt settings use defaults without losing the sale',
    (tester) async {
      final pdf = FakePdf();
      final unavailableSettings = Completer<ReceiptSettings>();
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: SaleReceiptScreen(
            receipt: SaleReceipt.fromJson(savedSale()),
            settings: unavailableSettings.future,
            pdfService: pdf,
          ),
        ),
      );
      unavailableSettings.completeError(Exception('Settings unavailable'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Default layout used'), findsOneWidget);
      await tester.tap(find.text('Print'));
      await tester.pumpAndSettle();
      expect(pdf.prints, 1);
      expect(pdf.exported?.saleNumber, 'POS-0011');
    },
  );

  test(
    'Generates thermal-width PDF with long receipts and embedded fonts',
    () async {
      final service = ReceiptPdfService();
      for (final width in [58, 80]) {
        final data = savedSale(itemCount: width == 58 ? 100 : 2);
        if (width == 58) {
          data['items'][0]['product_name'] =
              'Very long product name to check wrapping without losing price or quantity';
        }
        final bytes = await service.buildPdf(
          SaleReceipt.fromJson(data),
          ReceiptSettings(
            businessName: 'pos',
            currency: 'LKR',
            paperWidth: width,
          ),
        );
        expect(ascii.decode(bytes.take(5).toList()), '%PDF-');
        expect(bytes.length, greaterThan(5000));
        if (const bool.fromEnvironment('WRITE_RECEIPT_QA')) {
          await Directory('tmp/pdfs').create(recursive: true);
          await File('tmp/pdfs/mobile-receipt-$width.pdf').writeAsBytes(bytes);
        }
      }
    },
  );

  testWidgets('Receipt preview visual QA capture', (tester) async {
    if (!const bool.fromEnvironment('WRITE_RECEIPT_QA')) return;
    await tester.binding.setSurfaceSize(const Size(375, 812));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final font = FontLoader('ReceiptQA')
      ..addFont(rootBundle.load('assets/fonts/NotoSans-Regular.ttf'));
    await tester.runAsync(font.load);
    final defaultFont = FontLoader('Ahem')
      ..addFont(rootBundle.load('assets/fonts/NotoSans-Regular.ttf'));
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    final receiptFont = FontLoader('ReceiptMono')
      ..addFont(rootBundle.load('assets/fonts/CourierPrime-Regular.ttf'))
      ..addFont(rootBundle.load('assets/fonts/CourierPrime-Bold.ttf'));
    await tester.runAsync(() async {
      await defaultFont.load();
      await icons.load();
      await receiptFont.load();
    });
    final key = GlobalKey();
    await tester.pumpWidget(
      RepaintBoundary(
        key: key,
        child: MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: buildAppTheme().copyWith(
            textTheme: buildAppTheme().textTheme.apply(fontFamily: 'ReceiptQA'),
            elevatedButtonTheme: ElevatedButtonThemeData(
              style: buildAppTheme().elevatedButtonTheme.style!.copyWith(
                textStyle: const WidgetStatePropertyAll(
                  TextStyle(
                    fontFamily: 'ReceiptQA',
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
            outlinedButtonTheme: OutlinedButtonThemeData(
              style: buildAppTheme().outlinedButtonTheme.style!.copyWith(
                textStyle: const WidgetStatePropertyAll(
                  TextStyle(
                    fontFamily: 'ReceiptQA',
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
          home: SaleReceiptScreen(
            receipt: SaleReceipt.fromJson(savedSale()),
            settings: Future.value(settings),
            pdfService: FakePdf(),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    final boundary =
        key.currentContext!.findRenderObject() as RenderRepaintBoundary;
    await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 1);
      final png = await image.toByteData(format: ui.ImageByteFormat.png);
      await Directory('tmp/pdfs').create(recursive: true);
      await File(
        'tmp/pdfs/receipt-preview.png',
      ).writeAsBytes(png!.buffer.asUint8List());
      image.dispose();
    });
  });
}
