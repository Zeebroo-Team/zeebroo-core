import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:zeebroo_mobile/core/api/api_client.dart';
import 'package:zeebroo_mobile/core/offline/offline_customers.dart';
import 'package:zeebroo_mobile/core/offline/offline_expenses.dart';
import 'package:zeebroo_mobile/core/offline/offline_products.dart';
import 'package:zeebroo_mobile/core/offline/offline_sales.dart';

/// Answers API calls from [handler]; records every request it saw.
class _FakeServer implements HttpClientAdapter {
  _FakeServer(this.handler);
  final ({int status, Object? body}) Function(RequestOptions options) handler;
  final requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    final r = handler(options);
    if (r.status == 0) {
      throw DioException.connectionError(requestOptions: options, reason: 'offline');
    }
    return ResponseBody.fromString(jsonEncode(r.body), r.status, headers: {
      Headers.contentTypeHeader: ['application/json'],
    });
  }

  @override
  void close({bool force = false}) {}

  List<Map<String, dynamic>> posts(String pathEnd) => [
    for (final r in requests)
      if (r.method == 'POST' && r.path.endsWith(pathEnd)) Map<String, dynamic>.from(r.data as Map),
  ];
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final customers = OfflineCustomers.instance;
  final expenses = OfflineExpenses.instance;
  final sales = OfflineSales.instance;

  setUpAll(() async {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
    // Own folder: other test files use offline_sales.db at the same time.
    await databaseFactory.setDatabasesPath(Directory.systemTemp.createTempSync('offline_records_test').path);
    // Offline mode on (customers/expenses may be recorded offline).
    SharedPreferences.setMockInitialValues({'offline_products_enabled': true});
    await OfflineProducts.instance.load();
  });

  setUp(() async {
    FlutterSecureStorage.setMockInitialValues({'selected_business_id': '10', 'selected_branch_id': '3'});
    ApiClient.instance.httpClientAdapter = _FakeServer((_) => (status: 0, body: null));
    // Make sure each store has opened (and created) its database, then empty
    // it — uploaded records can't be removed through the public API.
    await customers.search('');
    await sales.list();
    await expenses.list();
    for (final (file, table) in [
      ('offline_customers.db', 'customers'),
      ('offline_sales.db', 'sales'),
      ('offline_expenses.db', 'expenses'),
    ]) {
      final db = await openDatabase(p.join(await getDatabasesPath(), file));
      await db.delete(table);
    }
    await customers.start();
    await expenses.start();
    await sales.start();
  });

  tearDownAll(() async {
    for (final db in ['offline_customers.db', 'offline_expenses.db', 'offline_sales.db']) {
      await deleteDatabase(p.join(await getDatabasesPath(), db));
    }
  });

  group('customers', () {
    test('a customer added offline has no id, a uuid, and is found by search', () async {
      final c = await customers.record({'name': 'Nimal Perera', 'phone': '0771234567'});
      expect(c['id'], isNull);
      expect(c['offline'], isTrue);
      expect(c['client_uuid'], matches(RegExp(r'^[0-9a-f-]{36}$')));
      expect(customers.pendingCount, 1);

      expect((await customers.search('nimal')).single['client_uuid'], c['client_uuid']);
      expect((await customers.search('0771')).single['name'], 'Nimal Perera');
      expect(await customers.search('kamal'), isEmpty);
    });

    test('saved server customers are searchable; not-uploaded-only filter works', () async {
      await customers.remember([
        {'id': 7, 'name': 'Kamal Silva', 'phone': '0712222222'},
        {'id': 8, 'name': 'Sunil', 'phone': null},
      ]);
      await customers.record({'name': 'Kamala New'});

      final all = await customers.search('kamal');
      expect(all.map((c) => c['name']), ['Kamala New', 'Kamal Silva']); // added offline first
      expect(all.last['id'], 7);
      expect((await customers.search('kamal', notUploadedOnly: true)).single['name'], 'Kamala New');
    });

    test('customers belong to the business they were saved for', () async {
      await customers.record({'name': 'Business 10 customer'});
      FlutterSecureStorage.setMockInitialValues({'selected_business_id': '11'});
      expect(await customers.search(''), isEmpty);
    });

    test('upload sends the uuid and stores the server id; a 422 needs attention', () async {
      final ok = await customers.record({'name': 'Good', 'phone': '0701'});
      final bad = await customers.record({'name': 'Bad'});
      final server = _FakeServer((o) {
        final body = o.data as Map;
        return body['name'] == 'Bad'
            ? (status: 422, body: {'message': 'The email field must be a valid email address.'})
            : (status: 201, body: {'data': {'id': 55, 'name': 'Good'}});
      });
      ApiClient.instance.httpClientAdapter = server;

      final result = await customers.upload();

      expect(result.stopped, isNull);
      expect(server.posts('/customers/offline').first['client_uuid'], ok['client_uuid']);
      expect(server.requests.first.headers['X-Business-Id'], '10');
      expect(await customers.serverIdFor(ok['client_uuid'] as String), 55);
      expect(await customers.serverIdFor(bad['client_uuid'] as String), isNull);
      expect(await customers.failureFor(bad['client_uuid'] as String), contains('valid email'));
      expect(customers.pendingCount, 0);
      expect(customers.failedCount, 1);
    });

    test('no connection: stops and keeps the customer waiting', () async {
      await customers.record({'name': 'Waiting'});
      final result = await customers.upload();
      expect(result.stopped, isNotNull);
      expect(customers.pendingCount, 1);
    });
  });

  group('sales to a customer added offline', () {
    Map<String, dynamic> receipt() => {'payment_method': 'credit', 'total': 300, 'items': const []};

    test('the customer uploads first and the sale is sent with its real id', () async {
      final c = await customers.record({'name': 'Credit Customer'});
      await sales.record(
        body: {
          'payment_method': 'credit',
          'amount_paid': 0,
          'customer_client_uuid': c['client_uuid'],
          'items': [
            {'product_id': 5, 'qty': 1, 'unit_price': 300.0},
          ],
        },
        receipt: receipt(),
      );
      final server = _FakeServer((o) {
        if (o.path.endsWith('/customers/offline')) return (status: 201, body: {'data': {'id': 91}});
        if (o.path.endsWith('/sales/offline')) return (status: 201, body: {'data': {'sale_number': 'POS-0100'}});
        return (status: 200, body: {'data': []});
      });
      ApiClient.instance.httpClientAdapter = server;

      await sales.upload();

      final order = server.requests.where((r) => r.method == 'POST').map((r) => r.path).toList();
      expect(order.first, endsWith('/customers/offline'));
      expect(order[1], endsWith('/sales/offline'));
      final sent = server.posts('/sales/offline').single;
      expect(sent['customer_id'], 91);
      expect(sent.containsKey('customer_client_uuid'), isFalse);
      expect(sent['payment_method'], 'credit');
      expect((await sales.list()).single.saleNumber, 'POS-0100');
    });

    test('a sale waits while its customer cannot upload (server too old)', () async {
      final c = await customers.record({'name': 'Waits'});
      await sales.record(
        body: {'payment_method': 'cash', 'customer_client_uuid': c['client_uuid'], 'items': const []},
        receipt: receipt(),
      );
      final server = _FakeServer((o) => (status: 404, body: {'message': 'Not found'}));
      ApiClient.instance.httpClientAdapter = server;

      await sales.upload();

      expect(server.posts('/sales/offline'), isEmpty);
      expect(sales.pendingCount, 1);
      expect(sales.lastError, isNotNull);
    });

    test('a sale whose customer was refused needs attention', () async {
      final c = await customers.record({'name': 'Refused'});
      await sales.record(
        body: {'payment_method': 'credit', 'customer_client_uuid': c['client_uuid'], 'items': const []},
        receipt: receipt(),
      );
      ApiClient.instance.httpClientAdapter = _FakeServer((o) => (status: 422, body: {'message': 'Name is too long.'}));

      await sales.upload();

      final s = (await sales.list()).single;
      expect(s.needsAttention, isTrue);
      expect(s.error, contains('Name is too long.'));
    });
  });

  group('expenses', () {
    test('online: sent straight to the server, nothing kept on the phone', () async {
      final server = _FakeServer((_) => (status: 201, body: {'data': {'id': 3}}));
      ApiClient.instance.httpClientAdapter = server;

      final savedOnPhone = await expenses.submit(name: 'Tea', amount: 450.5, category: 'other', categoryOther: 'Refreshments');

      expect(savedOnPhone, isFalse);
      final sent = server.posts('/expenses/offline').single;
      expect(sent['amount'], 450.5);
      expect(sent['bill_category_other'], 'Refreshments');
      expect(DateTime.tryParse(sent['spent_at'] as String), isNotNull);
      expect(await expenses.list(), isEmpty);
    });

    test('offline: saved on the phone, counted, then uploaded with the same uuid', () async {
      final savedOnPhone = await expenses.submit(name: 'Bus fare', amount: 120, category: 'other', categoryOther: 'Transport');
      expect(savedOnPhone, isTrue);
      expect(expenses.pendingCount, 1);
      final totals = await expenses.waitingTotals();
      expect(totals.count, 1);
      expect(totals.total, 120);

      final uuid = (await expenses.list()).single.clientUuid;
      final server = _FakeServer((_) => (status: 201, body: {'data': {'id': 9}}));
      ApiClient.instance.httpClientAdapter = server;
      await sales.upload(); // one run uploads everything waiting

      expect(server.posts('/expenses/offline').single['client_uuid'], uuid);
      expect((await expenses.list()).single.isUploaded, isTrue);
      expect((await expenses.waitingTotals()).count, 0);
    });

    test('a refused expense needs attention and can be retried', () async {
      await expenses.submit(name: 'Old', amount: 10, category: 'water');
      ApiClient.instance.httpClientAdapter = _FakeServer((_) => (status: 422, body: {'message': 'Too old.'}));
      await expenses.upload();
      final e = (await expenses.list()).single;
      expect(e.needsAttention, isTrue);
      expect(e.error, 'Too old.');

      await expenses.retry(e.clientUuid);
      expect(expenses.pendingCount, 1);
    });

    test('server without the endpoint: a clear message, not saved', () async {
      ApiClient.instance.httpClientAdapter = _FakeServer((_) => (status: 405, body: {'message': 'Method not allowed'}));
      await expectLater(
        expenses.submit(name: 'X', amount: 1, category: 'gas'),
        throwsA(isA<OfflineExpenseError>()),
      );
      expect(await expenses.list(), isEmpty);
    });
  });

  test('Home’s recent list gets today’s waiting sales of this business, newest first', () async {
    await sales.record(body: {'payment_method': 'cash', 'items': const []}, receipt: {'payment_method': 'cash', 'total': 100});
    await Future<void>.delayed(const Duration(milliseconds: 5));
    await sales.record(body: {'payment_method': 'card', 'items': const []}, receipt: {'payment_method': 'card', 'total': 250});

    final listed = await sales.todayWaitingSales();
    expect(listed.map((s) => s.total), [250, 100]);
    expect(listed.first.offlineRef, startsWith('OFF-'));

    FlutterSecureStorage.setMockInitialValues({'selected_business_id': '11'});
    expect(await sales.todayWaitingSales(), isEmpty);
  });

  test('today’s waiting sales add up per payment method for Home', () async {
    await sales.record(
      body: {'payment_method': 'cash', 'items': const []},
      receipt: {
        'payment_method': 'cash',
        'total': 200,
        'items': [
          {'quantity': 2},
        ],
      },
    );
    await sales.record(
      body: {'payment_method': 'credit', 'items': const []},
      receipt: {
        'payment_method': 'credit',
        'total': 300,
        'items': [
          {'quantity': 1},
        ],
      },
    );
    final today = await sales.todayWaiting();
    expect(today.count, 2);
    expect(today.total, 500);
    expect(today.items, 3);
    expect(today.byMethod, {'cash': 200.0, 'credit': 300.0});
    expect(sales.allWaitingCount, 2);
  });
}
