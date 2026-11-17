import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:path/path.dart' as p;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite/sqflite.dart';

import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../business/business_storage.dart';
import 'offline_common.dart';
import 'offline_customers.dart';
import 'offline_expenses.dart';
import 'offline_products.dart';

/// One sale recorded on the phone while the server couldn't be reached.
class OfflineSale {
  OfflineSale._(Map<String, Object?> row)
    : clientUuid = row['client_uuid'] as String,
      offlineRef = row['offline_ref'] as String,
      createdAt = DateTime.fromMillisecondsSinceEpoch(row['created_at'] as int),
      status = row['status'] as String,
      error = row['error'] as String?,
      saleNumber = row['sale_number'] as String?,
      receipt = Map<String, dynamic>.from(jsonDecode(row['receipt'] as String) as Map);

  final String clientUuid, offlineRef, status;
  final DateTime createdAt;
  final String? error, saleNumber;

  /// The receipt JSON shown when the sale was made (same shape as the API's).
  final Map<String, dynamic> receipt;

  bool get isPending => status == OfflineSales.statusPending;
  bool get needsAttention => status == OfflineSales.statusFailed;
  bool get isUploaded => status == OfflineSales.statusUploaded;
  double get total => (receipt['total'] as num?)?.toDouble() ?? 0;
}

/// The offline-sales outbox (Phase 1 of offline mode).
///
/// When "Offline mode" is on and checkout can't reach the server, POS saves
/// the sale here and shows a receipt with a temporary number (OFF-7K2Q-0001).
/// [upload] later sends each one to `POST sales/offline` with a unique
/// client_uuid, so a retry after a dropped connection never creates a
/// duplicate; the server keeps the phone's sale time and price, assigns the
/// real POS number, and still checks stock.
///
/// Kept in its own database file: clearing saved products or turning offline
/// mode off never deletes sales that haven't been uploaded yet.
class OfflineSales extends ChangeNotifier with WidgetsBindingObserver {
  OfflineSales._() {
    // Screens watching this outbox also see customer/expense queue changes.
    OfflineCustomers.instance.addListener(notifyListeners);
    OfflineExpenses.instance.addListener(notifyListeners);
  }
  static final OfflineSales instance = OfflineSales._();

  static const statusPending = 'pending';
  static const statusFailed = 'failed';
  static const statusUploaded = 'uploaded';
  static const maxAgeDays = 7; // server rejects older offline sales

  static const _dbName = 'offline_sales.db';
  static const _kDeviceCode = 'offline_device_code';
  static const _kSequence = 'offline_sale_seq';
  static const _uploadInterval = Duration(seconds: 60);

  static bool get supported => OfflineProducts.supported;

  Database? _db;
  bool _started = false;
  Future<void>? _uploading;

  int pendingCount = 0;
  int failedCount = 0;
  bool uploading = false;

  /// Why the last upload stopped early (no connection, subscription, …).
  String? lastError;

  /// New sales may be saved offline only with offline mode switched on.
  bool get canRecord => supported && OfflineProducts.instance.enabled;

  int get waitingCount => pendingCount + failedCount;

  /// Sales, customers and expenses on this phone that haven't uploaded.
  int get allWaitingCount =>
      waitingCount + OfflineCustomers.instance.waitingCount + OfflineExpenses.instance.waitingCount;

  bool get hasPendingUploads =>
      pendingCount > 0 || OfflineCustomers.instance.pendingCount > 0 || OfflineExpenses.instance.pendingCount > 0;

  Future<Database> get _database async => _db ??= await openDatabase(
    p.join(await getDatabasesPath(), _dbName),
    version: 1,
    onCreate: (db, _) => db.execute('''
      CREATE TABLE sales (
        client_uuid TEXT PRIMARY KEY,
        offline_ref TEXT NOT NULL,
        business_id INTEGER NOT NULL,
        branch_id INTEGER,
        created_at INTEGER NOT NULL,
        payload TEXT NOT NULL,
        receipt TEXT NOT NULL,
        status TEXT NOT NULL,
        error TEXT,
        attempts INTEGER NOT NULL DEFAULT 0,
        sale_number TEXT,
        uploaded_at INTEGER
      )'''),
  );

  /// Loads counts and uploads anything waiting; also re-tries whenever the app
  /// returns to the foreground. Safe to call often, and never throws — a
  /// broken local database must not stop POS from working online.
  Future<void> start() async {
    if (!supported) return;
    try {
      if (!_started) {
        _started = true;
        WidgetsBinding.instance.addObserver(this);
      }
      await _refreshCounts();
      await OfflineCustomers.instance.start();
      await OfflineExpenses.instance.start();
      if (hasPendingUploads) unawaited(upload());
    } catch (e) {
      debugPrint('Offline sales unavailable: $e');
    }
  }

  Timer? _autoUpload;

  /// Retries every minute for the rest of the app session. Called once from
  /// the home screen (not from POS, so screens don't leave timers behind).
  void startAutoUpload() {
    if (!supported || _autoUpload != null) return;
    _autoUpload = Timer.periodic(_uploadInterval, (_) {
      if (hasPendingUploads) upload();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && hasPendingUploads) upload();
  }

  // ── Recording ────────────────────────────────────────────────────────────

  /// Saves a sale for later upload. [body] is the normal checkout body (with
  /// each item's `unit_price`); [receipt] is the receipt JSON to show now.
  /// Returns the receipt with its temporary OFF- number filled in.
  Future<Map<String, dynamic>> record({
    required Map<String, dynamic> body,
    required Map<String, dynamic> receipt,
  }) async {
    final businessId = await BusinessStorage.getBusinessId();
    if (businessId == null) throw StateError('No business selected.');
    final branchId = await BusinessStorage.getBranchId();
    final now = DateTime.now();
    final uuid = newUuidV4();
    final ref = await _nextOfflineRef();

    final payload = {
      ...body,
      'client_uuid': uuid,
      'offline_ref': ref,
      'sold_at': now.toUtc().toIso8601String(),
    };
    final shown = {
      ...receipt,
      'sale_number': ref,
      'sold_at': now.toIso8601String(),
      'offline': true,
    };

    await (await _database).insert('sales', {
      'client_uuid': uuid,
      'offline_ref': ref,
      'business_id': businessId,
      'branch_id': branchId,
      'created_at': now.millisecondsSinceEpoch,
      'payload': jsonEncode(payload),
      'receipt': jsonEncode(shown),
      'status': statusPending,
    });
    await _refreshCounts();
    return shown;
  }

  // ── Uploading ────────────────────────────────────────────────────────────
  /// Uploads everything waiting on the phone: customers added offline first
  /// (sales may point at them), then sales, then expenses. Concurrent calls
  /// share one run. Stops early (keeping the rest waiting) when there is no
  /// connection. Never throws.
  Future<void> upload() => _uploading ??= _upload().whenComplete(() => _uploading = null);

  Future<void> _upload() async {
    if (!supported) return;
    uploading = true;
    lastError = null;
    notifyListeners();
    var uploadedAny = false;
    try {
      final customers = await OfflineCustomers.instance.upload();
      if (customers.stopped == UploadFailure.offline) {
        lastError = customers.message;
        return;
      }
      final sales = await _uploadSales();
      uploadedAny = sales.uploadedAny;
      if (sales.stopped == UploadFailure.offline) {
        lastError = sales.message;
        return;
      }
      final expenses = await OfflineExpenses.instance.upload();
      lastError = sales.message ?? customers.message ?? expenses.message;
    } finally {
      await _refreshCounts().catchError((_) {});
      uploading = false;
      notifyListeners();
      // Uploaded sales changed stock on the server — refresh the saved products.
      if (uploadedAny && OfflineProducts.instance.enabled) {
        unawaited(OfflineProducts.instance.sync());
      }
    }
  }

  Future<({UploadFailure? stopped, String? message, bool uploadedAny})> _uploadSales() async {
    final Database db;
    try {
      db = await _database;
    } catch (e) {
      debugPrint('Offline sales unavailable: $e');
      return (stopped: UploadFailure.stop, message: '$e', uploadedAny: false);
    }
    final rows = await db.query(
      'sales',
      where: 'status = ?',
      whereArgs: [statusPending],
      orderBy: 'created_at',
    );
    var uploadedAny = false;
    String? waitingForCustomer;
    try {
      for (final row in rows) {
        final uuid = row['client_uuid'] as String;
        final payload = Map<String, dynamic>.from(jsonDecode(row['payload'] as String) as Map);

        // Sold to a customer added offline: send the customer's real id.
        final customerUuid = payload.remove('customer_client_uuid') as String?;
        if (customerUuid != null) {
          final customerId = await OfflineCustomers.instance.serverIdFor(customerUuid);
          if (customerId == null) {
            final refused = await OfflineCustomers.instance.failureFor(customerUuid);
            if (refused != null) {
              await db.update(
                'sales',
                {'status': statusFailed, 'error': 'Its new customer could not be uploaded: $refused'},
                where: 'client_uuid = ?',
                whereArgs: [uuid],
              );
            } else {
              waitingForCustomer = 'Some sales are waiting for their new customer to upload.';
            }
            continue; // the customer hasn't uploaded yet — keep the sale waiting
          }
          payload['customer_id'] = customerId;
        }

        try {
          final res = await ApiClient.instance.post(
            ApiEndpoints.offlineSales,
            data: payload,
            headers: {
              'X-Business-Id': '${row['business_id']}',
              if (row['branch_id'] != null) 'X-Branch-Id': '${row['branch_id']}',
            },
          );
          final data = res.data is Map ? res.data['data'] : null;
          await db.update(
            'sales',
            {
              'status': statusUploaded,
              'error': null,
              'sale_number': data is Map ? data['sale_number'] as String? : null,
              'uploaded_at': DateTime.now().millisecondsSinceEpoch,
            },
            where: 'client_uuid = ?',
            whereArgs: [uuid],
          );
          uploadedAny = true;
        } on DioException catch (e) {
          final (kind, text) = classifyUploadError(e, 'sales');
          if (kind == UploadFailure.rejected) {
            // The server refused this sale (sold out, product removed, too
            // old…) — it needs a person to look at it; continue with the rest.
            await db.rawUpdate(
              'UPDATE sales SET status = ?, error = ?, attempts = attempts + 1 WHERE client_uuid = ?',
              [statusFailed, text, uuid],
            );
            continue;
          }
          if (kind == UploadFailure.stop) {
            await db.rawUpdate('UPDATE sales SET attempts = attempts + 1 WHERE client_uuid = ?', [uuid]);
          }
          return (stopped: kind, message: text, uploadedAny: uploadedAny);
        }
      }
    } finally {
      await _pruneUploaded();
    }
    return (stopped: null, message: waitingForCustomer, uploadedAny: uploadedAny);
  }

  /// Puts a "needs attention" sale back in the queue and uploads.
  Future<void> retry(String clientUuid) async {
    await (await _database).update(
      'sales',
      {'status': statusPending, 'error': null},
      where: 'client_uuid = ? AND status = ?',
      whereArgs: [clientUuid, statusFailed],
    );
    await _refreshCounts();
    await upload();
  }

  /// Removes a sale that will never upload (after it was handled by hand).
  Future<void> discard(String clientUuid) async {
    await (await _database).delete(
      'sales',
      where: 'client_uuid = ? AND status != ?',
      whereArgs: [clientUuid, statusUploaded],
    );
    await _refreshCounts();
  }

  /// Newest first: waiting, needs-attention and recently uploaded sales.
  Future<List<OfflineSale>> list() async {
    if (!supported) return const [];
    final rows = await (await _database).query('sales', orderBy: 'created_at DESC');
    return rows.map(OfflineSale._).toList();
  }

  /// Today's not-yet-uploaded sales of the current business, newest first —
  /// for Home's "Recent transactions" list.
  Future<List<OfflineSale>> todayWaitingSales() async {
    if (!supported) return const [];
    final businessId = await BusinessStorage.getBusinessId();
    if (businessId == null) return const [];
    final now = DateTime.now();
    final midnight = DateTime(now.year, now.month, now.day).millisecondsSinceEpoch;
    final rows = await (await _database).query(
      'sales',
      where: 'status != ? AND created_at >= ? AND business_id = ?',
      whereArgs: [statusUploaded, midnight, businessId],
      orderBy: 'created_at DESC',
    );
    return rows.map(OfflineSale._).toList();
  }

  /// Today's not-yet-uploaded sales of the current business, for the Home
  /// "Today's sales" card: count, total, items and total per payment method.
  Future<({int count, double total, double items, Map<String, double> byMethod})> todayWaiting() async {
    const none = (count: 0, total: 0.0, items: 0.0, byMethod: <String, double>{});
    if (!supported) return none;
    final businessId = await BusinessStorage.getBusinessId();
    if (businessId == null) return none;
    final now = DateTime.now();
    final midnight = DateTime(now.year, now.month, now.day).millisecondsSinceEpoch;
    final rows = await (await _database).query(
      'sales',
      columns: ['receipt'],
      where: 'status != ? AND created_at >= ? AND business_id = ?',
      whereArgs: [statusUploaded, midnight, businessId],
    );
    var total = 0.0;
    var items = 0.0;
    final byMethod = <String, double>{};
    for (final r in rows) {
      final receipt = jsonDecode(r['receipt'] as String) as Map;
      final saleTotal = (receipt['total'] as num?)?.toDouble() ?? 0;
      total += saleTotal;
      final method = '${receipt['payment_method'] ?? 'cash'}';
      byMethod[method] = (byMethod[method] ?? 0) + saleTotal;
      for (final item in (receipt['items'] as List? ?? const []).whereType<Map>()) {
        items += (item['quantity'] as num?)?.toDouble() ?? 0;
      }
    }
    return (count: rows.length, total: total, items: items, byMethod: byMethod);
  }

  // ── Helpers ──────────────────────────────────────────────────────────────

  Future<void> _refreshCounts() async {
    if (!supported) return;
    final db = await _database;
    Future<int> count(String status) async =>
        Sqflite.firstIntValue(await db.rawQuery('SELECT COUNT(*) FROM sales WHERE status = ?', [status])) ?? 0;
    pendingCount = await count(statusPending);
    failedCount = await count(statusFailed);
    notifyListeners();
  }

  /// Keeps uploaded sales visible for a week, then forgets them locally.
  Future<void> _pruneUploaded() async {
    final cutoff = DateTime.now().subtract(const Duration(days: maxAgeDays)).millisecondsSinceEpoch;
    await (await _database).delete(
      'sales',
      where: 'status = ? AND uploaded_at < ?',
      whereArgs: [statusUploaded, cutoff],
    );
  }

  /// OFF-<4-char phone code>-<4-digit sequence>, e.g. OFF-7K2Q-0001.
  Future<String> _nextOfflineRef() async {
    final prefs = await SharedPreferences.getInstance();
    var code = prefs.getString(_kDeviceCode);
    if (code == null) {
      const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
      final rnd = Random.secure();
      code = List.generate(4, (_) => alphabet[rnd.nextInt(alphabet.length)]).join();
      await prefs.setString(_kDeviceCode, code);
    }
    final seq = (prefs.getInt(_kSequence) ?? 0) + 1;
    await prefs.setInt(_kSequence, seq);
    return 'OFF-$code-${seq.toString().padLeft(4, '0')}';
  }

}
