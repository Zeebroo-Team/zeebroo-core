import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:zeebroo_mobile/core/offline/offline_sales.dart';
import 'package:zeebroo_mobile/features/pos/models/sale_receipt.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final sales = OfflineSales.instance;

  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  });

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({'selected_business_id': '10', 'selected_branch_id': '3'});
    for (final s in await sales.list()) {
      await sales.discard(s.clientUuid);
    }
  });

  tearDownAll(() async => deleteDatabase(p.join(await getDatabasesPath(), 'offline_sales.db')));

  Map<String, dynamic> body() => {
    'payment_method': 'cash',
    'amount_paid': 500,
    'items': [
      {'product_id': 5, 'qty': 2, 'unit_price': 250.0},
    ],
  };
  Map<String, dynamic> receipt() => {
    'payment_method': 'cash',
    'subtotal': 500,
    'total': 500,
    'amount_paid': 500,
    'items': [
      {'product_name': 'Rice', 'quantity': 2, 'unit_sell_price': 250, 'line_total': 500},
    ],
  };

  test('records a sale with a temporary OFF number and a waiting status', () async {
    final shown = await sales.record(body: body(), receipt: receipt());

    expect(shown['sale_number'], matches(RegExp(r'^OFF-[A-Z2-9]{4}-0001$')));
    expect(shown['offline'], isTrue);
    expect(sales.pendingCount, 1);

    final saved = (await sales.list()).single;
    expect(saved.isPending, isTrue);
    expect(saved.offlineRef, shown['sale_number']);
    expect(saved.total, 500);

    // The receipt shown at the till parses like a server receipt.
    final r = SaleReceipt.fromJson(shown);
    expect(r.isOffline, isTrue);
    expect(r.detailsAvailable, isTrue);
  });

  test('numbers keep counting on the same phone and use one phone code', () async {
    final a = await sales.record(body: body(), receipt: receipt());
    final b = await sales.record(body: body(), receipt: receipt());
    final codeA = (a['sale_number'] as String).split('-')[1];
    final codeB = (b['sale_number'] as String).split('-')[1];
    expect(codeA, codeB);
    expect(b['sale_number'], endsWith('-0002'));
  });

  test('each sale gets its own v4 client_uuid, phone time and the original prices', () async {
    await sales.record(body: body(), receipt: receipt());
    await sales.record(body: body(), receipt: receipt());
    final db = await openDatabase(p.join(await getDatabasesPath(), 'offline_sales.db'));
    final rows = await db.query('sales');
    final payloads = rows.map((r) => jsonDecode(r['payload'] as String) as Map).toList();

    final uuids = payloads.map((p) => p['client_uuid'] as String).toSet();
    expect(uuids, hasLength(2));
    for (final u in uuids) {
      expect(u, matches(RegExp(r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')));
    }
    for (final payload in payloads) {
      expect(DateTime.tryParse(payload['sold_at'] as String), isNotNull);
      expect(payload['offline_ref'], startsWith('OFF-'));
      expect((payload['items'] as List).single['unit_price'], 250.0);
    }
    // Uploads go to the business/branch the sale was made in.
    expect(rows.every((r) => r['business_id'] == 10 && r['branch_id'] == 3), isTrue);
  });

  test('discard removes a waiting sale and updates the count', () async {
    await sales.record(body: body(), receipt: receipt());
    final saved = (await sales.list()).single;
    await sales.discard(saved.clientUuid);
    expect(await sales.list(), isEmpty);
    expect(sales.waitingCount, 0);
  });

  test("today's waiting total adds up unsent sales", () async {
    await sales.record(body: body(), receipt: receipt());
    await sales.record(body: body(), receipt: receipt());
    final today = await sales.todayWaiting();
    expect(today.count, 2);
    expect(today.total, 1000);
  });
}
