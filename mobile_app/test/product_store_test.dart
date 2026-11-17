import 'package:flutter_test/flutter_test.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:zeebroo_mobile/core/offline/product_store.dart';

Map<String, dynamic> card(int id, String name, {String sku = '', List<int> categories = const [], List<Map<String, dynamic>> batches = const [], double price = 100}) => {
  'id': id,
  'name': name,
  'sku': sku,
  'category_ids': categories,
  'unit_sell_price': price,
  'stock_quantity': 5,
  'layers': [
    for (final b in batches) {'id': b['layer_id'], 'quantity_remaining': 3},
  ],
  'batch_skus': batches,
};

void main() {
  final store = ProductStore.instance;
  const scope = 'b1-br0';

  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  });

  setUp(() async {
    await store.clearAll();
    await store.upsertProducts(scope, [
      card(1, 'Basmati Rice 1kg', sku: 'RICE-1', categories: [10]),
      card(2, 'Red Rice 5kg', sku: 'RICE-5', categories: [10, 11]),
      card(3, 'Sugar 1kg', sku: 'SUG-1', categories: [11], batches: [
        {'layer_id': 77, 'sku': 'SUG-1-02'},
      ]),
    ], {1: 'a', 2: 'b', 3: 'c'});
  });

  tearDownAll(() => store.clearAll());

  test('lists products sorted by name and keeps the full card', () async {
    final products = await store.products(scope);
    expect(products.map((p) => p['name']), ['Basmati Rice 1kg', 'Red Rice 5kg', 'Sugar 1kg']);
    expect(products.first['unit_sell_price'], 100);
  });

  test('every search word must match name or SKU, any order', () async {
    expect((await store.products(scope, search: 'rice 5kg')).map((p) => p['id']), [2]);
    expect((await store.products(scope, search: 'RICE')).map((p) => p['id']), [1, 2]);
    expect((await store.products(scope, search: 'sug-1')).map((p) => p['id']), [3]);
    expect(await store.products(scope, search: '100%'), isEmpty);
  });

  test('filters by category id without matching partial ids', () async {
    expect((await store.products(scope, categoryId: 11)).map((p) => p['id']), [2, 3]);
    expect(await store.products(scope, categoryId: 1), isEmpty);
  });

  test('finds products by product SKU and batch SKU, case-insensitively', () async {
    expect((await store.productByCode(scope, 'rice-1'))?['id'], 1);
    final batch = await store.productByCode(scope, 'SUG-1-02');
    expect(batch?['id'], 3);
    expect(batch?['matched_layer_id'], 77);
    expect(batch?['layer_qty_remaining'], 3);
    expect(await store.productByCode(scope, 'NOPE'), isNull);
  });

  test('deleting removes the product and its barcodes', () async {
    await store.deleteProducts(scope, [3]);
    expect((await store.products(scope)).map((p) => p['id']), [1, 2]);
    expect(await store.productByCode(scope, 'SUG-1-02'), isNull);
    expect(await store.fingerprints(scope), {1: 'a', 2: 'b'});
  });

  test('updating a product replaces its old barcode', () async {
    await store.upsertProducts(scope, [card(1, 'Basmati Rice 1kg', sku: 'RICE-NEW')], {1: 'z'});
    expect(await store.productByCode(scope, 'RICE-1'), isNull);
    expect((await store.productByCode(scope, 'RICE-NEW'))?['id'], 1);
    expect((await store.fingerprints(scope))[1], 'z');
  });

  test('business/branch scopes never mix', () async {
    await store.upsertProducts('b2-br0', [card(9, 'Other shop item')], {9: 'x'});
    expect((await store.products(scope)).map((p) => p['id']), [1, 2, 3]);
    expect((await store.products('b2-br0')).map((p) => p['id']), [9]);
    await store.clearScope('b2-br0');
    expect(await store.products('b2-br0'), isEmpty);
    expect(await store.count(scope), 3);
  });
}
