import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/pos/data/scanner_product_repository.dart';
import 'package:zeebroo_mobile/features/pos/models/pos_cart_item.dart';
import 'package:zeebroo_mobile/features/pos/screens/barcode_scanner_screen.dart';
import 'package:zeebroo_mobile/features/pos/widgets/live_scanner_camera.dart';
import 'package:zeebroo_mobile/features/pos/widgets/scanner_product_search_sheet.dart';

Map<String, dynamic> book({int id = 1, double stock = 20}) => {
  'id': id,
  'name': id == 1 ? 'Inspirational Stories' : 'Notebook',
  'sku': 'CODE-$id',
  'unit_sell_price': 400.0,
  'stock_quantity': stock,
};

class FakeProducts extends ScannerProductRepository {
  final queries = <String>[];
  Future<Map<String, dynamic>?> Function(String)? lookupHandler;
  Future<ScannerProductsPage> Function(String, int)? searchHandler;

  @override
  Future<Map<String, dynamic>?> lookup(String code) async =>
      lookupHandler == null ? book() : await lookupHandler!(code);

  @override
  Future<ScannerProductsPage> search(String query, {int page = 1}) async {
    queries.add('$query:$page');
    return searchHandler == null
        ? ScannerProductsPage(products: [book()])
        : await searchHandler!(query, page);
  }
}

Future<void> size(WidgetTester tester, Size value) async {
  await tester.binding.setSurfaceSize(value);
  addTearDown(() => tester.binding.setSurfaceSize(null));
}

void main() {
  for (final screenSize in [
    const Size(320, 640),
    const Size(375, 812),
    const Size(844, 390),
  ]) {
    testWidgets(
      'Live cart controls and Finish return the cart at $screenSize',
      (tester) async {
        await size(tester, screenSize);
        ValueChanged<String>? detect;
        List<PosCartItem>? returned;
        final initial = PosCartItem(
          product: book(),
          qty: 2,
          itemDiscountPct: 10,
        );
        await tester.pumpWidget(
          MaterialApp(
            theme: buildAppTheme(),
            home: Builder(
              builder: (context) => Scaffold(
                body: TextButton(
                  onPressed: () async {
                    returned = await Navigator.of(context)
                        .push<List<PosCartItem>>(
                          MaterialPageRoute(
                            builder: (_) => BarcodeScannerScreen(
                              initialCart: [initial],
                              repository: FakeProducts(),
                              onProductAdded: () {},
                              cameraBuilder: (_, onCode, paused) {
                                detect = onCode;
                                return ColoredBox(
                                  color: Colors.black,
                                  child: Text(
                                    paused ? 'Camera paused' : 'Camera active',
                                  ),
                                );
                              },
                            ),
                          ),
                        );
                  },
                  child: const Text('Open scanner'),
                ),
              ),
            ),
          ),
        );
        await tester.tap(find.text('Open scanner'));
        await tester.pumpAndSettle();
        detect!('CODE-1');
        await tester.pumpAndSettle();
        expect(find.text('Cart (3)'), findsOneWidget);
        expect(
          find.byKey(const ValueKey('scanner-cart-total')),
          findsOneWidget,
        );
        expect(
          tester
              .widget<Text>(find.byKey(const ValueKey('scanner-cart-total')))
              .data,
          '1,080.00',
        );
        expect(find.text('Camera active'), findsOneWidget);
        await tester.tap(find.byKey(const ValueKey('scanner-plus-1')));
        await tester.pump();
        expect(find.text('Cart (4)'), findsOneWidget);
        await tester.tap(find.byKey(const ValueKey('scanner-minus-1')));
        await tester.pump();
        expect(find.text('Cart (3)'), findsOneWidget);
        expect(initial.qty, 2);
        await tester.tap(find.byKey(const ValueKey('finish-scanning')));
        await tester.pumpAndSettle();
        expect(returned!.single.qty, 3);
        expect(returned!.single.itemDiscountPct, 10);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'Manual product search pauses detection, selects a product, then resumes',
    (tester) async {
      await size(tester, const Size(320, 640));
      final repository = FakeProducts();
      ValueChanged<String>? detect;
      bool? paused;
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: BarcodeScannerScreen(
            repository: repository,
            onProductAdded: () {},
            cameraBuilder: (_, callback, isPaused) {
              detect = callback;
              paused = isPaused;
              return const ColoredBox(color: Colors.black);
            },
          ),
        ),
      );
      await tester.tap(find.byKey(const ValueKey('scanner-manual-search')));
      await tester.pumpAndSettle();
      expect(paused, isTrue);
      detect!('CODE-1');
      await tester.pump();
      expect(find.text('Cart (0)'), findsOneWidget);
      await tester.enterText(
        find.byKey(const ValueKey('scanner-product-search')),
        'Stories',
      );
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();
      expect(repository.queries, contains('Stories:1'));
      await tester.tap(find.byKey(const ValueKey('scanner-search-product-1')));
      await tester.pumpAndSettle();
      expect(paused, isFalse);
      expect(find.text('Cart (1)'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('scanner-remove-1')));
      await tester.pump();
      expect(find.text('Cart (0)'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Unknown typed codes report a problem and a successful retry adds an item',
    (tester) async {
      final repository = FakeProducts()
        ..lookupHandler = (code) async => code == 'UNKNOWN' ? null : book();
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: BarcodeScannerScreen(
            repository: repository,
            onProductAdded: () {},
            cameraBuilder: (_, callback, paused) =>
                const ColoredBox(color: Colors.black),
          ),
        ),
      );
      for (final code in ['UNKNOWN', 'CODE-1']) {
        await tester.tap(find.byKey(const ValueKey('scanner-manual-search')));
        await tester.pumpAndSettle();
        await tester.enterText(
          find.byKey(const ValueKey('scanner-manual-code')),
          code,
        );
        await tester.tap(find.text('Add code'));
        await tester.pumpAndSettle();
        expect(find.byType(ScannerProductSearchSheet), findsNothing);
        expect(
          find.text(code == 'UNKNOWN' ? 'Cart (0)' : 'Cart (1)'),
          findsOneWidget,
        );
        if (code == 'UNKNOWN') {
          expect(find.textContaining('No product found'), findsOneWidget);
        }
      }
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Manual search can load and select products from later pages', (
    tester,
  ) async {
    await size(tester, const Size(375, 812));
    final repository = FakeProducts()
      ..searchHandler = (query, page) async => ScannerProductsPage(
        products: [book(id: page)],
        hasMore: page == 1,
      );
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: BarcodeScannerScreen(
          repository: repository,
          onProductAdded: () {},
          cameraBuilder: (_, callback, paused) =>
              const ColoredBox(color: Colors.black),
        ),
      ),
    );
    await tester.tap(find.byKey(const ValueKey('scanner-manual-search')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Load more products'));
    await tester.pumpAndSettle();
    expect(repository.queries, [':1', ':2']);
    await tester.tap(find.byKey(const ValueKey('scanner-search-product-2')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('scanner-cart-item-2')), findsOneWidget);
    expect(find.text('Cart (1)'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'A late unfiltered response cannot overwrite manual search results',
    (tester) async {
      final firstRequest = Completer<ScannerProductsPage>();
      final repository = FakeProducts()
        ..searchHandler = (query, page) => query.isEmpty
            ? firstRequest.future
            : Future.value(ScannerProductsPage(products: [book(id: 2)]));
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: Scaffold(
            body: ScannerProductSearchSheet(repository: repository),
          ),
        ),
      );
      await tester.enterText(
        find.byKey(const ValueKey('scanner-product-search')),
        'Notebook',
      );
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();
      firstRequest.complete(ScannerProductsPage(products: [book()]));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey('scanner-search-product-1')),
        findsNothing,
      );
      expect(
        find.byKey(const ValueKey('scanner-search-product-2')),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Finish is disabled during lookup and back returns the finished cart',
    (tester) async {
      final pending = Completer<Map<String, dynamic>?>();
      ValueChanged<String>? detect;
      List<PosCartItem>? returned;
      final nav = GlobalKey<NavigatorState>();
      await tester.pumpWidget(
        MaterialApp(
          navigatorKey: nav,
          home: const Scaffold(body: Text('Previous screen')),
        ),
      );
      final route = nav.currentState!.push<List<PosCartItem>>(
        MaterialPageRoute(
          builder: (_) => BarcodeScannerScreen(
            repository: FakeProducts()..lookupHandler = (_) => pending.future,
            onProductAdded: () {},
            cameraBuilder: (_, callback, paused) {
              detect = callback;
              return const ColoredBox(color: Colors.black);
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      detect!('CODE-1');
      await tester.pump();
      expect(
        tester
            .widget<FilledButton>(find.byKey(const ValueKey('finish-scanning')))
            .onPressed,
        isNull,
      );
      expect(find.text('Looking up product...'), findsOneWidget);
      pending.complete(book());
      await tester.pumpAndSettle();
      await nav.currentState!.maybePop();
      await tester.pumpAndSettle();
      returned = await route;
      expect(returned!.single.qty, 1);
      expect(find.text('Previous screen'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Manual search remains scrollable above the keyboard on small screens',
    (tester) async {
      await size(tester, const Size(320, 480));
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: MediaQuery(
            data: const MediaQueryData(
              size: Size(320, 480),
              viewInsets: EdgeInsets.only(bottom: 240),
            ),
            child: Material(
              child: ScannerProductSearchSheet(repository: FakeProducts()),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.byKey(const ValueKey('scanner-manual-code')),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('scanner-manual-code')),
        'CODE-1',
      );
      expect(tester.takeException(), isNull);
    },
  );

  for (final exitAction in ['Finish', 'X', 'Back']) {
    testWidgets(
      '$exitAction waits for camera shutdown before returning the cart',
      (tester) async {
        await size(tester, const Size(320, 640));
        final previous = MobileScannerPlatform.instance;
        final platform = FakeCamera();
        MobileScannerPlatform.instance = platform;
        debugDefaultTargetPlatformOverride = TargetPlatform.android;
        addTearDown(() {
          MobileScannerPlatform.instance = previous;
          debugDefaultTargetPlatformOverride = null;
        });
        List<PosCartItem>? returned;
        await tester.pumpWidget(
          MaterialApp(
            theme: buildAppTheme(),
            home: Builder(
              builder: (context) => Scaffold(
                body: TextButton(
                  onPressed: () async {
                    returned = await Navigator.of(context)
                        .push<List<PosCartItem>>(
                          MaterialPageRoute(
                            builder: (_) => BarcodeScannerScreen(
                              initialCart: [
                                PosCartItem(product: book(), qty: 2),
                              ],
                              repository: FakeProducts(),
                              onProductAdded: () {},
                            ),
                          ),
                        );
                  },
                  child: const Text('Open scanner'),
                ),
              ),
            ),
          ),
        );
        await tester.tap(find.text('Open scanner'));
        await tester.pumpAndSettle();
        expect(platform.starts, 1);
        expect(platform.active, isTrue);
        expect(platform.webReader, kIsWeb ? WebBarcodeReader.zxingJs : isNull);
        platform.stopGate = Completer<void>();
        if (exitAction == 'Finish') {
          await tester.tap(find.byKey(const ValueKey('finish-scanning')));
        } else if (exitAction == 'X') {
          await tester.tap(find.byTooltip('Finish and close scanner'));
        } else {
          await tester.binding.handlePopRoute();
        }
        await tester.pump(const Duration(milliseconds: 100));
        expect(platform.stops, 1);
        expect(platform.active, isTrue);
        expect(returned, isNull);
        expect(find.text('Closing camera...'), findsOneWidget);
        expect(
          tester
              .widget<FilledButton>(
                find.byKey(const ValueKey('finish-scanning')),
              )
              .onPressed,
          isNull,
        );
        // Neither resume nor a late barcode may restart/change a closing session.
        tester.binding.handleAppLifecycleStateChanged(
          AppLifecycleState.inactive,
        );
        tester.binding.handleAppLifecycleStateChanged(
          AppLifecycleState.resumed,
        );
        platform.codes.add(
          const BarcodeCapture(barcodes: [Barcode(rawValue: 'CODE-1')]),
        );
        await tester.pump();
        expect(platform.starts, 1);
        expect(find.text('Cart (2)'), findsOneWidget);
        platform.stopGate!.complete();
        await tester.pumpAndSettle();
        expect(returned!.single.qty, 2);
        expect(platform.active, isFalse);
        expect(platform.disposed, isTrue);
        expect(find.byType(BarcodeScannerScreen), findsNothing);

        // A fresh scanner session can still acquire/release its own camera.
        final reopened = FakeCamera();
        MobileScannerPlatform.instance = reopened;
        await tester.tap(find.text('Open scanner'));
        await tester.pumpAndSettle();
        expect(reopened.starts, 1);
        expect(reopened.active, isTrue);
        await tester.tap(find.byKey(const ValueKey('finish-scanning')));
        await tester.pumpAndSettle();
        expect(reopened.active, isFalse);
        expect(reopened.disposed, isTrue);
        expect(tester.takeException(), isNull);
        debugDefaultTargetPlatformOverride = null;
      },
    );
  }

  testWidgets('Finish during pending startup stops the late camera session', (
    tester,
  ) async {
    final previous = MobileScannerPlatform.instance;
    final platform = FakeCamera()..startGate = Completer<void>();
    MobileScannerPlatform.instance = platform;
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    addTearDown(() {
      MobileScannerPlatform.instance = previous;
      debugDefaultTargetPlatformOverride = null;
    });
    await tester.pumpWidget(
      MaterialApp(
        home: BarcodeScannerScreen(
          repository: FakeProducts(),
          onProductAdded: () {},
        ),
      ),
    );
    await tester.pump();
    expect(platform.starts, 1);
    await tester.tap(find.byKey(const ValueKey('finish-scanning')));
    await tester.pump();
    expect(find.text('Closing camera...'), findsOneWidget);
    platform.startGate!.complete();
    await tester.pumpAndSettle();
    expect(platform.starts, 1);
    expect(platform.stops, 1);
    expect(platform.active, isFalse);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpAndSettle();
    expect(platform.disposed, isTrue);
    expect(tester.takeException(), isNull);
    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets('Unexpected removal during pending startup releases the camera', (
    tester,
  ) async {
    final previous = MobileScannerPlatform.instance;
    final platform = FakeCamera()..startGate = Completer<void>();
    MobileScannerPlatform.instance = platform;
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    addTearDown(() {
      MobileScannerPlatform.instance = previous;
      debugDefaultTargetPlatformOverride = null;
    });
    await tester.pumpWidget(
      const MaterialApp(home: LiveScannerCamera(onCode: ignoreCode)),
    );
    await tester.pump();
    expect(platform.starts, 1);
    await tester.pumpWidget(const SizedBox());
    platform.startGate!.complete();
    await tester.pumpAndSettle();
    expect(platform.starts, 1);
    expect(platform.active, isFalse);
    expect(platform.disposed, isTrue);
    expect(tester.takeException(), isNull);
    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets(
    'Real scanner widget starts once, continues scanning, and releases camera',
    (tester) async {
      final previous = MobileScannerPlatform.instance;
      final platform = FakeCamera();
      MobileScannerPlatform.instance = platform;
      debugDefaultTargetPlatformOverride = TargetPlatform.android;
      addTearDown(() {
        MobileScannerPlatform.instance = previous;
        debugDefaultTargetPlatformOverride = null;
      });
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: BarcodeScannerScreen(
            repository: FakeProducts(),
            onProductAdded: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(platform.starts, 1);
      platform.codes.add(
        const BarcodeCapture(barcodes: [Barcode(rawValue: 'CODE-1')]),
      );
      await tester.pumpAndSettle();
      expect(find.text('Cart (1)'), findsOneWidget);
      expect(platform.starts, 1);
      expect(platform.stops, 0);
      await tester.tap(find.byKey(const ValueKey('scanner-manual-search')));
      await tester.pumpAndSettle();
      expect(platform.stops, 1);
      await tester.tap(find.byTooltip('Close search'));
      await tester.pumpAndSettle();
      expect(platform.starts, 2);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pumpAndSettle();
      expect(platform.stops, 2);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(platform.starts, 3);
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
      expect(platform.disposed, isTrue);
      expect(tester.takeException(), isNull);
      debugDefaultTargetPlatformOverride = null;
    },
  );

  testWidgets(
    'Camera denial explains manual fallback instead of blocking the cart',
    (tester) async {
      final previous = MobileScannerPlatform.instance;
      final platform = FakeCamera()..denied = true;
      MobileScannerPlatform.instance = platform;
      debugDefaultTargetPlatformOverride = TargetPlatform.android;
      addTearDown(() {
        MobileScannerPlatform.instance = previous;
        debugDefaultTargetPlatformOverride = null;
      });
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: BarcodeScannerScreen(
            repository: FakeProducts(),
            onProductAdded: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Allow camera access'), findsOneWidget);
      expect(
        find.byKey(const ValueKey('scanner-manual-search')),
        findsOneWidget,
      );
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      debugDefaultTargetPlatformOverride = null;
    },
  );

  testWidgets('Unsupported desktop platforms still offer manual search', (
    tester,
  ) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.windows;
    addTearDown(() => debugDefaultTargetPlatformOverride = null);
    await tester.pumpWidget(
      const MaterialApp(home: LiveScannerCamera(onCode: ignoreCode)),
    );
    expect(find.textContaining('unavailable on this platform'), findsOneWidget);
    expect(tester.takeException(), isNull);
    debugDefaultTargetPlatformOverride = null;
  });
}

void ignoreCode(String code) {}

class FakeCamera extends MobileScannerPlatform {
  final codes = StreamController<BarcodeCapture>.broadcast();
  int starts = 0;
  int stops = 0;
  bool disposed = false;
  bool denied = false;
  bool active = false;
  Completer<void>? startGate;
  Completer<void>? stopGate;
  WebBarcodeReader? webReader;

  @override
  void setWebBarcodeReader(WebBarcodeReader reader) => webReader = reader;

  @override
  Stream<BarcodeCapture?> get barcodesStream => codes.stream;
  @override
  Stream<TorchState> get torchStateStream =>
      Stream.value(TorchState.unavailable);
  @override
  Stream<double> get zoomScaleStateStream => Stream.value(1);
  @override
  Future<MobileScannerViewAttributes> start(StartOptions options) async {
    starts++;
    if (denied) {
      throw const MobileScannerException(
        errorCode: MobileScannerErrorCode.permissionDenied,
      );
    }
    await startGate?.future;
    active = true;
    return const MobileScannerViewAttributes(
      cameraDirection: CameraFacing.back,
      currentTorchMode: TorchState.unavailable,
      size: Size(320, 320),
      numberOfCameras: 1,
    );
  }

  @override
  Widget buildCameraView() => const ColoredBox(
    key: ValueKey('fake-platform-camera'),
    color: Colors.black,
  );
  @override
  Future<void> stop() async {
    stops++;
    await stopGate?.future;
    active = false;
  }

  @override
  Future<void> dispose() async {
    disposed = true;
    active = false;
    await codes.close();
  }
}
