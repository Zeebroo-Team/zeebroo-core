import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/features/pos/data/scanner_cart_controller.dart';
import 'package:zeebroo_mobile/features/pos/data/scanner_product_repository.dart';
import 'package:zeebroo_mobile/features/pos/models/pos_cart_item.dart';

Map<String, dynamic> product(int id, {double price = 400, double stock = 20}) =>
    {
      'id': id,
      'name': 'Product $id',
      'sku': 'CODE-$id',
      'unit_sell_price': price,
      'stock_quantity': stock,
    };

class TestProducts extends ScannerProductRepository {
  final calls = <String>[];
  Future<Map<String, dynamic>?> Function(String)? handler;

  @override
  Future<Map<String, dynamic>?> lookup(String code) async {
    calls.add(code);
    return handler == null ? product(1) : await handler!(code);
  }
}

void main() {
  test(
    'Scans add a line and repeated intentional scans increase its quantity',
    () async {
      final repository = TestProducts();
      var now = DateTime(2026, 10, 6);
      final cart = ScannerCartController(
        repository: repository,
        clock: () => now,
      );
      addTearDown(cart.dispose);
      expect((await cart.scanCode(' CODE-1 '))!.ok, isTrue);
      expect(cart.count, 1);
      expect(cart.total, 400);
      // A stationary label must not auto-add every 1.5 seconds.
      for (var frame = 0; frame < 20; frame++) {
        now = now.add(const Duration(milliseconds: 250));
        expect(await cart.scanCode('CODE-1'), isNull);
      }
      expect(repository.calls, ['CODE-1']);
      now = now.add(const Duration(milliseconds: 1300));
      expect((await cart.scanCode('CODE-1'))!.ok, isTrue);
      expect(cart.items, hasLength(1));
      expect(cart.count, 2);
      expect(cart.total, 800);
    },
  );

  test(
    'A different code and manual entry bypass same-label suppression',
    () async {
      final repository = TestProducts()
        ..handler = (code) async => product(code == 'CODE-2' ? 2 : 1);
      final cart = ScannerCartController(repository: repository);
      addTearDown(cart.dispose);
      await cart.scanCode('CODE-1');
      await cart.scanCode('CODE-2');
      await cart.scanCode('CODE-1');
      await cart.scanCode('CODE-1', manual: true);
      expect(cart.items, hasLength(2));
      expect(cart.items.first.qty, 3);
      expect(cart.count, 4);
    },
  );

  test(
    'Only one lookup runs at a time, even across different barcode events',
    () async {
      final pending = Completer<Map<String, dynamic>?>();
      final repository = TestProducts()..handler = (_) => pending.future;
      final cart = ScannerCartController(repository: repository);
      addTearDown(cart.dispose);
      final first = cart.scanCode('CODE-1');
      expect(cart.busy, isTrue);
      expect(await cart.scanCode('CODE-2'), isNull);
      pending.complete(product(1));
      expect((await first)!.ok, isTrue);
      expect(cart.busy, isFalse);
      expect(repository.calls, ['CODE-1']);
      // The code seen during a pending lookup can be scanned next.
      expect((await cart.scanCode('CODE-2'))!.ok, isTrue);
      expect(cart.count, 2);
    },
  );

  test(
    'Not found, out of stock, and network errors do not change the cart',
    () async {
      final repository = TestProducts();
      final cart = ScannerCartController(repository: repository);
      addTearDown(cart.dispose);
      repository.handler = (_) async => null;
      expect(
        (await cart.scanCode('UNKNOWN'))!.message,
        contains('No product found'),
      );
      repository.handler = (_) async => product(1, stock: 0);
      expect(
        (await cart.scanCode('SOLD-OUT'))!.message,
        contains('out of stock'),
      );
      repository.handler = (_) async => throw Exception('Connection lost');
      expect((await cart.scanCode('NETWORK'))!.ok, isFalse);
      expect(cart.busy, isFalse);
      expect(cart.items, isEmpty);
      repository.handler = (_) async => product(1);
      expect((await cart.scanCode('NETWORK', manual: true))!.ok, isTrue);
    },
  );

  test(
    'Session and returned snapshots do not mutate the caller cart or discounts',
    () {
      final initial = PosCartItem(
        product: product(1),
        qty: 2,
        itemDiscountPct: 10,
      );
      final cart = ScannerCartController(
        repository: TestProducts(),
        initialCart: [initial],
      );
      addTearDown(cart.dispose);
      cart.addProduct(product(1));
      expect(initial.qty, 2);
      expect(cart.items.single.qty, 3);
      expect(cart.items.single.itemDiscountPct, 10);
      expect(cart.total, 1080);
      final result = cart.snapshot();
      result.single.qty = 50;
      result.single.product['name'] = 'Changed';
      expect(cart.items.single.qty, 3);
      expect(initial.name, 'Product 1');
    },
  );

  test('Quantity buttons, remove, and discounted totals stay in sync', () {
    final p = product(1)..['discounted_sell_price'] = 300.0;
    final cart = ScannerCartController(repository: TestProducts());
    addTearDown(cart.dispose);
    cart.addProduct(p);
    cart.setQuantity(1, 3);
    expect(cart.total, 900);
    cart.setQuantity(1, 2);
    expect(cart.total, 600);
    cart.setQuantity(1, 0);
    expect(cart.items, isEmpty);
    cart.addProduct(p);
    cart.remove(1);
    expect(cart.total, 0);
  });

  test(
    'Malformed products are rejected before creating an unusable cart line',
    () {
      final cart = ScannerCartController(repository: TestProducts());
      addTearDown(cart.dispose);
      for (final invalid in [
        {'name': 'Missing ID'},
        {...product(1), 'id': -1},
        {...product(1), 'id': double.nan},
        {...product(1), 'unit_sell_price': 'invalid'},
        {...product(1), 'unit_sell_price': -100},
        {...product(1), 'stock_quantity': 'invalid'},
      ]) {
        expect(cart.addProduct(invalid).ok, isFalse);
      }
      expect(cart.items, isEmpty);
    },
  );

  test(
    'A late lookup after disposal cannot mutate the cart or notify listeners',
    () async {
      final pending = Completer<Map<String, dynamic>?>();
      final cart = ScannerCartController(
        repository: TestProducts()..handler = (_) => pending.future,
      );
      final request = cart.scanCode('CODE-1');
      cart.dispose();
      pending.complete(product(1));
      expect(await request, isNull);
      expect(cart.items, isEmpty);
    },
  );
}
