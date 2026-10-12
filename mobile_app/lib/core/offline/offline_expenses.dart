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

class OfflineExpenseError implements Exception {
  const OfflineExpenseError(this.message);
  final String message;

  @override
  String toString() => message;
}

/// One expense saved on the phone (waiting, refused, or recently uploaded).
class OfflineExpense {
  OfflineExpense._(Map<String, Object?> row)
    : clientUuid = row['client_uuid'] as String,
      createdAt = DateTime.fromMillisecondsSinceEpoch(row['created_at'] as int),
      status = row['status'] as String,
      error = row['error'] as String?,
      payload = Map<String, dynamic>.from(jsonDecode(row['payload'] as String) as Map);

  final String clientUuid, status;
  final DateTime createdAt;
  final String? error;
  final Map<String, dynamic> payload;

  String get name => '${payload['name'] ?? ''}';
  double get amount => (payload['amount'] as num?)?.toDouble() ?? 0;
  bool get isPending => status == OfflineExpenses.statusPending;
  bool get needsAttention => status == OfflineExpenses.statusFailed;
  bool get isUploaded => status == OfflineExpenses.statusUploaded;
}

/// Expenses for offline mode (Phase 3).
///
/// A quick expense is sent to `POST expenses/offline`, which records it as a
/// one-time bill due on the day it was spent (pay it later from Bills). With
/// no connection and offline mode on, it is saved here and uploaded later;
/// its client_uuid stops a retried upload from creating a second bill.
class OfflineExpenses extends ChangeNotifier {
  OfflineExpenses._();
  static final OfflineExpenses instance = OfflineExpenses._();

  static const statusPending = 'pending';
  static const statusFailed = 'failed';
  static const statusUploaded = 'uploaded';
  static const maxAgeDays = 7; // server rejects older offline expenses
  static const _dbName = 'offline_expenses.db';

  /// Same categories as the server's bills.
  static const categories = <String, String>{
    'other': 'Other (specify)',
    'electricity': 'Electricity',
    'water': 'Water',
    'telephone': 'Telephone',
    'internet': 'Internet',
    'gas': 'Gas',
    'waste': 'Waste / sanitation',
  };

  static bool get supported => OfflineProducts.supported;
  bool get canRecord => supported && OfflineProducts.instance.enabled;

  Database? _db;
  int pendingCount = 0;
  int failedCount = 0;
  int get waitingCount => pendingCount + failedCount;

  Future<Database> get _database async => _db ??= await openDatabase(
    p.join(await getDatabasesPath(), _dbName),
    version: 1,
    onCreate: (db, _) => db.execute('''
      CREATE TABLE expenses (
        client_uuid TEXT PRIMARY KEY,
        business_id INTEGER NOT NULL,
        branch_id INTEGER,
        created_at INTEGER NOT NULL,
        payload TEXT NOT NULL,
        status TEXT NOT NULL,
        error TEXT,
        attempts INTEGER NOT NULL DEFAULT 0,
        bill_id INTEGER,
        uploaded_at INTEGER
      )'''),
  );

  /// Saves an expense: straight to the server when it can be reached,
  /// otherwise (offline mode on) on the phone. Returns true when it was
  /// saved on the phone to upload later. Throws when it couldn't be saved.
  Future<bool> submit({
    required String name,
    required double amount,
    required String category,
    String? categoryOther,
    String? notes,
  }) async {
    final payload = <String, dynamic>{
      'client_uuid': newUuidV4(),
      'name': name,
      'amount': amount,
      'bill_category': category,
      if (category == 'other') 'bill_category_other': categoryOther,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      'spent_at': DateTime.now().toUtc().toIso8601String(),
    };
    try {
      await ApiClient.instance.post(ApiEndpoints.offlineExpenses, data: payload);
      return false;
    } on DioException catch (e) {
      if (!couldNotReachServer(e) || !canRecord) {
        if (e.response?.statusCode == 404 || e.response?.statusCode == 405) {
          throw const OfflineExpenseError('The server doesn’t support quick expenses yet. Update the server first.');
        }
        rethrow;
      }
    }
    await _record(payload);
    return true;
  }

  Future<void> _record(Map<String, dynamic> payload) async {
    final businessId = await BusinessStorage.getBusinessId();
    if (businessId == null) throw StateError('No business selected.');
    await (await _database).insert('expenses', {
      'client_uuid': payload['client_uuid'],
      'business_id': businessId,
      'branch_id': await BusinessStorage.getBranchId(),
      'created_at': DateTime.now().millisecondsSinceEpoch,
      'payload': jsonEncode(payload),
      'status': statusPending,
    });
    await _refreshCounts();
  }

  /// Uploads waiting expenses, oldest first. Returns why it stopped early
  /// (null when it went through the whole queue). Never throws.
  Future<({UploadFailure? stopped, String? message})> upload() async {
    if (!supported) return (stopped: null, message: null);
    try {
      final db = await _database;
      final rows = await db.query('expenses', where: 'status = ?', whereArgs: [statusPending], orderBy: 'created_at');
      for (final row in rows) {
        final uuid = row['client_uuid'] as String;
        try {
          final res = await ApiClient.instance.post(
            ApiEndpoints.offlineExpenses,
            data: jsonDecode(row['payload'] as String),
            headers: {
              'X-Business-Id': '${row['business_id']}',
              if (row['branch_id'] != null) 'X-Branch-Id': '${row['branch_id']}',
            },
          );
          final data = res.data is Map ? res.data['data'] : null;
          await db.update(
            'expenses',
            {
              'status': statusUploaded,
              'error': null,
              'bill_id': data is Map ? (data['id'] as num?)?.toInt() : null,
              'uploaded_at': DateTime.now().millisecondsSinceEpoch,
            },
            where: 'client_uuid = ?',
            whereArgs: [uuid],
          );
        } on DioException catch (e) {
          final (kind, text) = classifyUploadError(e, 'expenses');
          if (kind == UploadFailure.rejected) {
            await db.rawUpdate(
              'UPDATE expenses SET status = ?, error = ?, attempts = attempts + 1 WHERE client_uuid = ?',
              [statusFailed, text, uuid],
            );
            continue;
          }
          if (kind == UploadFailure.stop) {
            await db.rawUpdate('UPDATE expenses SET attempts = attempts + 1 WHERE client_uuid = ?', [uuid]);
          }
          return (stopped: kind, message: text);
        }
      }
      final cutoff = DateTime.now().subtract(const Duration(days: maxAgeDays)).millisecondsSinceEpoch;
      await db.delete('expenses', where: 'status = ? AND uploaded_at < ?', whereArgs: [statusUploaded, cutoff]);
    } catch (e) {
      debugPrint('Offline expenses upload failed: $e');
      return (stopped: UploadFailure.stop, message: '$e');
    } finally {
      await _refreshCounts().catchError((_) {});
    }
    return (stopped: null, message: null);
  }

  Future<void> retry(String clientUuid) async {
    await (await _database).update(
      'expenses',
      {'status': statusPending, 'error': null},
      where: 'client_uuid = ? AND status = ?',
      whereArgs: [clientUuid, statusFailed],
    );
    await _refreshCounts();
  }

  Future<void> discard(String clientUuid) async {
    await (await _database).delete(
      'expenses',
      where: 'client_uuid = ? AND status != ?',
      whereArgs: [clientUuid, statusUploaded],
    );
    await _refreshCounts();
  }

  /// Newest first: waiting, needs-attention and recently uploaded expenses.
  Future<List<OfflineExpense>> list() async {
    if (!supported) return const [];
    final rows = await (await _database).query('expenses', orderBy: 'created_at DESC');
    return rows.map(OfflineExpense._).toList();
  }

  /// Expenses of the current business not uploaded yet (for Home).
  Future<({int count, double total})> waitingTotals() async {
    if (!supported) return (count: 0, total: 0.0);
    final businessId = await BusinessStorage.getBusinessId();
    final rows = await (await _database).query(
      'expenses',
      columns: ['payload'],
      where: 'status != ? AND business_id = ?',
      whereArgs: [statusUploaded, businessId],
    );
    var total = 0.0;
    for (final r in rows) {
      total += ((jsonDecode(r['payload'] as String) as Map)['amount'] as num?)?.toDouble() ?? 0;
    }
    return (count: rows.length, total: total);
  }

  Future<void> start() async {
    if (!supported) return;
    try {
      await _refreshCounts();
    } catch (e) {
      debugPrint('Offline expenses unavailable: $e');
    }
  }

  Future<void> _refreshCounts() async {
    final db = await _database;
    Future<int> count(String status) async =>
        Sqflite.firstIntValue(await db.rawQuery('SELECT COUNT(*) FROM expenses WHERE status = ?', [status])) ?? 0;
    pendingCount = await count(statusPending);
    failedCount = await count(statusFailed);
    notifyListeners();
  }
}
