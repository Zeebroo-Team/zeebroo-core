import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../business/business_storage.dart';
import 'offline_common.dart';
import 'offline_products.dart';

/// Customers for offline mode (Phase 2).
///
/// Keeps a copy of the business's customers so POS can pick one without a
/// connection, and an outbox of customers added on the phone while offline.
/// A customer added offline has no server id yet: it is identified by its
/// client_uuid (`{'id': null, 'client_uuid': …, 'offline': true}`), and a
/// sale made to it stores that uuid. Customers upload before sales, so by
/// the time a sale uploads its customer has a real id.
///
/// Its own database file: clearing products or turning offline mode off
/// never deletes a customer that hasn't been uploaded.
class OfflineCustomers extends ChangeNotifier {
  OfflineCustomers._();
  static final OfflineCustomers instance = OfflineCustomers._();

  static const statusSynced = 'synced'; // a copy of a server customer, or uploaded
  static const statusPending = 'pending';
  static const statusFailed = 'failed';

  static const _dbName = 'offline_customers.db';
  static const _maxPages = 40; // 50 per page → up to 2,000 customers

  static bool get supported => OfflineProducts.supported;

  /// Customers may be added offline only with offline mode switched on.
  bool get canRecord => supported && OfflineProducts.instance.enabled;

  Database? _db;
  Future<void>? _downloading;
  int pendingCount = 0;
  int failedCount = 0;

  int get waitingCount => pendingCount + failedCount;

  Future<Database> get _database async => _db ??= await openDatabase(
    p.join(await getDatabasesPath(), _dbName),
    version: 1,
    onCreate: (db, _) async {
      await db.execute('''
        CREATE TABLE customers (
          key TEXT PRIMARY KEY,
          business_id INTEGER NOT NULL,
          server_id INTEGER,
          client_uuid TEXT,
          name TEXT NOT NULL,
          search TEXT NOT NULL,
          data TEXT NOT NULL,
          status TEXT NOT NULL,
          error TEXT,
          created_at INTEGER NOT NULL
        )''');
      await db.execute('CREATE INDEX customers_business ON customers (business_id, status)');
    },
  );

  // ── Saved copy of server customers ───────────────────────────────────────

  static const _refreshEvery = Duration(minutes: 30);
  DateTime? _lastDownload;

  /// Downloads every customer of the current business (replacing the saved
  /// copy). Runs only with offline mode on. Product sync calls this every
  /// minute, so it re-downloads at most every [_refreshEvery] unless [force]
  /// (the "Download latest" button). Never throws.
  Future<void> download({bool force = false}) {
    if (!canRecord) return Future.value();
    final last = _lastDownload;
    if (!force && last != null && DateTime.now().difference(last) < _refreshEvery) return Future.value();
    return _downloading ??= _download().whenComplete(() => _downloading = null);
  }

  Future<void> _download() async {
    try {
      final businessId = await BusinessStorage.getBusinessId();
      if (businessId == null) return;
      final rows = <Map<String, dynamic>>[];
      for (var page = 1; page <= _maxPages; page++) {
        final res = await ApiClient.instance.get(ApiEndpoints.customers, params: {'page': page}, bypassCache: true);
        final body = res.data;
        final list = (body is Map ? body['data'] : body) as List? ?? const [];
        rows.addAll(list.whereType<Map>().map((m) => Map<String, dynamic>.from(m)));
        final lastPage = body is Map && body['meta'] is Map ? (body['meta']['last_page'] as num?)?.toInt() ?? 1 : 1;
        if (page >= lastPage || list.isEmpty) break;
      }
      final db = await _database;
      await db.transaction((txn) async {
        // Server copies are replaced; customers added on this phone stay.
        await txn.delete(
          'customers',
          where: 'business_id = ? AND status = ? AND client_uuid IS NULL',
          whereArgs: [businessId, statusSynced],
        );
        for (final c in rows) {
          await _upsertServer(txn, businessId, c);
        }
      });
      _lastDownload = DateTime.now();
    } catch (e) {
      debugPrint('Offline customers download failed: $e');
    }
  }

  /// Saves customers the picker just loaded online (keeps the copy fresh
  /// between full downloads). Never throws.
  Future<void> remember(List<Map<String, dynamic>> customers) async {
    if (!canRecord || customers.isEmpty) return;
    try {
      final businessId = await BusinessStorage.getBusinessId();
      if (businessId == null) return;
      final db = await _database;
      await db.transaction((txn) async {
        for (final c in customers) {
          await _upsertServer(txn, businessId, c);
        }
      });
    } catch (e) {
      debugPrint('Offline customers save failed: $e');
    }
  }

  Future<void> _upsertServer(DatabaseExecutor db, int businessId, Map<String, dynamic> c) async {
    final id = (c['id'] as num?)?.toInt();
    if (id == null) return;
    // Already known as an uploaded offline customer: refresh its details.
    final updated = await db.update(
      'customers',
      {'name': '${c['name'] ?? ''}', 'search': _searchText(c), 'data': jsonEncode(c)},
      where: 'business_id = ? AND server_id = ? AND client_uuid IS NOT NULL',
      whereArgs: [businessId, id],
    );
    if (updated > 0) return;
    await db.insert('customers', {
      'key': 's:$businessId:$id',
      'business_id': businessId,
      'server_id': id,
      'name': '${c['name'] ?? ''}',
      'search': _searchText(c),
      'data': jsonEncode(c),
      'status': statusSynced,
      'created_at': DateTime.now().millisecondsSinceEpoch,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  /// Saved customers matching [query] (name, phone or email), customers added
  /// on this phone first. Same map shape as the API's customer list.
  /// [notUploadedOnly]: just the customers added offline not uploaded yet.
  Future<List<Map<String, dynamic>>> search(String query, {int limit = 50, bool notUploadedOnly = false}) async {
    if (!supported) return const [];
    try {
      final businessId = await BusinessStorage.getBusinessId();
      if (businessId == null) return const [];
      final q = query.trim().toLowerCase();
      final rows = await (await _database).query(
        'customers',
        where: [
          'business_id = ?',
          if (q.isNotEmpty) 'search LIKE ?',
          if (notUploadedOnly) 'server_id IS NULL',
        ].join(' AND '),
        whereArgs: [businessId, if (q.isNotEmpty) '%$q%'],
        orderBy: 'CASE WHEN status = \'$statusSynced\' THEN 1 ELSE 0 END, name COLLATE NOCASE',
        limit: limit,
      );
      return rows.map(_toCustomer).toList();
    } catch (e) {
      debugPrint('Offline customers search failed: $e');
      return const [];
    }
  }

  Map<String, dynamic> _toCustomer(Map<String, Object?> row) {
    final data = Map<String, dynamic>.from(jsonDecode(row['data'] as String) as Map);
    final uuid = row['client_uuid'] as String?;
    return {
      ...data,
      'id': row['server_id'],
      'client_uuid': ?uuid,
      if (row['server_id'] == null) 'offline': true,
    };
  }

  // ── Customers added offline ──────────────────────────────────────────────

  /// Saves a customer added while offline. Returns it in the API's shape
  /// with `id: null`, its `client_uuid` and `offline: true`.
  Future<Map<String, dynamic>> record(Map<String, dynamic> fields) async {
    final businessId = await BusinessStorage.getBusinessId();
    if (businessId == null) throw StateError('No business selected.');
    final uuid = newUuidV4();
    final data = {
      ...fields,
      'customer_type': fields['customer_type'] ?? 'retail',
      'sales_count': 0,
    };
    await (await _database).insert('customers', {
      'key': 'u:$uuid',
      'business_id': businessId,
      'client_uuid': uuid,
      'name': '${fields['name'] ?? ''}',
      'search': _searchText(data),
      'data': jsonEncode(data),
      'status': statusPending,
      'created_at': DateTime.now().millisecondsSinceEpoch,
    });
    await _refreshCounts();
    return {...data, 'id': null, 'client_uuid': uuid, 'offline': true};
  }

  /// Server id of a customer added offline, once it has been uploaded.
  Future<int?> serverIdFor(String clientUuid) async {
    final row = await _row(clientUuid);
    return (row?['server_id'] as num?)?.toInt();
  }

  /// Why an offline customer was refused by the server; null if it wasn't.
  Future<String?> failureFor(String clientUuid) async {
    final row = await _row(clientUuid);
    return row?['status'] == statusFailed ? (row?['error'] as String? ?? 'Refused by the server.') : null;
  }

  Future<Map<String, Object?>?> _row(String clientUuid) async {
    if (!supported) return null;
    final rows = await (await _database).query('customers', where: 'client_uuid = ?', whereArgs: [clientUuid], limit: 1);
    return rows.isEmpty ? null : rows.first;
  }

  /// Uploads customers added offline, oldest first. Returns why it stopped
  /// early (null when it went through the whole queue). Never throws.
  Future<({UploadFailure? stopped, String? message})> upload() async {
    if (!supported) return (stopped: null, message: null);
    try {
      final db = await _database;
      final rows = await db.query('customers', where: 'status = ?', whereArgs: [statusPending], orderBy: 'created_at');
      for (final row in rows) {
        final uuid = row['client_uuid'] as String;
        final data = Map<String, dynamic>.from(jsonDecode(row['data'] as String) as Map);
        try {
          final res = await ApiClient.instance.post(
            ApiEndpoints.offlineCustomers,
            data: {
              'client_uuid': uuid,
              for (final k in ['name', 'phone', 'email', 'address', 'notes', 'customer_type', 'customer_category_id'])
                if (data[k] != null) k: data[k],
            },
            headers: {'X-Business-Id': '${row['business_id']}'},
          );
          final saved = res.data is Map ? res.data['data'] : null;
          final serverId = saved is Map ? (saved['id'] as num?)?.toInt() : null;
          if (serverId == null) throw StateError('The server did not return the customer id.');
          await db.update(
            'customers',
            {'status': statusSynced, 'error': null, 'server_id': serverId},
            where: 'client_uuid = ?',
            whereArgs: [uuid],
          );
          // The server may have matched an existing customer (same phone):
          // drop its separate saved copy so it isn't listed twice.
          await db.delete('customers', where: 'key = ?', whereArgs: ['s:${row['business_id']}:$serverId']);
        } on DioException catch (e) {
          final (kind, text) = classifyUploadError(e, 'customers');
          if (kind == UploadFailure.rejected) {
            await db.update('customers', {'status': statusFailed, 'error': text}, where: 'client_uuid = ?', whereArgs: [uuid]);
            continue;
          }
          return (stopped: kind, message: text);
        }
      }
    } catch (e) {
      debugPrint('Offline customers upload failed: $e');
      return (stopped: UploadFailure.stop, message: '$e');
    } finally {
      await _refreshCounts().catchError((_) {});
    }
    return (stopped: null, message: null);
  }

  Future<void> retry(String clientUuid) async {
    await (await _database).update(
      'customers',
      {'status': statusPending, 'error': null},
      where: 'client_uuid = ? AND status = ?',
      whereArgs: [clientUuid, statusFailed],
    );
    await _refreshCounts();
  }

  Future<void> discard(String clientUuid) async {
    await (await _database).delete(
      'customers',
      where: 'client_uuid = ? AND server_id IS NULL',
      whereArgs: [clientUuid],
    );
    await _refreshCounts();
  }

  /// Customers added offline that haven't uploaded, newest first.
  Future<List<Map<String, dynamic>>> waiting() async {
    if (!supported) return const [];
    final rows = await (await _database).query(
      'customers',
      where: 'status != ?',
      whereArgs: [statusSynced],
      orderBy: 'created_at DESC',
    );
    return [
      for (final r in rows)
        {..._toCustomer(r), 'status': r['status'], 'error': r['error']},
    ];
  }

  Future<void> start() async {
    if (!supported) return;
    try {
      await _refreshCounts();
    } catch (e) {
      debugPrint('Offline customers unavailable: $e');
    }
  }

  /// Offline mode off / logout: forget the saved copy of server customers.
  /// Customers added offline that haven't uploaded are kept.
  Future<void> clearSaved() async {
    if (!supported) return;
    try {
      await (await _database).delete('customers', where: 'status = ?', whereArgs: [statusSynced]);
    } catch (e) {
      debugPrint('Offline customers clear failed: $e');
    }
  }

  Future<void> _refreshCounts() async {
    final db = await _database;
    Future<int> count(String status) async =>
        Sqflite.firstIntValue(await db.rawQuery('SELECT COUNT(*) FROM customers WHERE status = ?', [status])) ?? 0;
    pendingCount = await count(statusPending);
    failedCount = await count(statusFailed);
    notifyListeners();
  }

  static String _searchText(Map<String, dynamic> c) =>
      [c['name'], c['phone'], c['email']].where((v) => v != null).join(' ').toLowerCase();
}
