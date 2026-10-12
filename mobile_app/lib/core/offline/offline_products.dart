import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../business/business_storage.dart';
import 'product_store.dart';

/// "Offline products" — an optional, per-phone copy of the POS catalog in
/// SQLite so POS opens, searches and scans instantly.
///
/// The server stays the only source of truth: this copy is read-only, sales
/// and product edits still go to the API, and the server re-checks stock and
/// price on every sale. Freshness comes from [sync]: it fetches a light
/// id+fingerprint index, re-downloads only changed products and drops ones
/// that disappeared (products are hard-deleted server-side).
///
/// Turned off (the default) nothing here runs and POS loads online as before.
class OfflineProducts extends ChangeNotifier {
  OfflineProducts._();
  static final OfflineProducts instance = OfflineProducts._();

  static const _kEnabled = 'offline_products_enabled';
  static const _chunk = 100; // server cap for by-ids

  /// SQLite (sqflite) exists only in the Android/iOS builds.
  static bool get supported =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  final _store = ProductStore.instance;
  bool _loaded = false;
  bool _enabled = false;
  Future<void>? _running;

  bool syncing = false;
  int progressDone = 0;
  int progressTotal = 0;

  /// Set when the last sync failed (e.g. no internet); cleared on success.
  String? lastError;
  bool lastErrorWasConnection = false;

  bool get enabled => supported && _enabled;

  /// Reads the saved switch once; cheap to call repeatedly.
  Future<void> load() async {
    if (_loaded) return;
    final prefs = await SharedPreferences.getInstance();
    _enabled = prefs.getBool(_kEnabled) ?? false;
    _loaded = true;
  }

  Future<void> setEnabled(bool value) async {
    await load();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_kEnabled, value);
    _enabled = value;
    lastError = null;
    notifyListeners();
    if (value) {
      unawaited(sync());
    } else {
      await _store.clearAll();
      notifyListeners();
    }
  }

  /// Wipes the saved catalog but keeps the switch on; next sync re-downloads.
  Future<void> clearSaved() async {
    await _store.clearAll();
    notifyListeners();
  }

  /// Logout: never leave one account's catalog for the next user.
  Future<void> onLogout() async {
    if (!supported) return;
    await _store.clearAll();
  }

  /// Current business/branch key; null when no business is selected.
  Future<String?> currentScope() async {
    final businessId = await BusinessStorage.getBusinessId();
    if (businessId == null) return null;
    final branchId = await BusinessStorage.getBranchId();
    return 'b$businessId-br${branchId ?? 0}';
  }

  /// True once this business/branch has completed at least one sync.
  Future<bool> isReady() async {
    await load();
    if (!enabled) return false;
    final scope = await currentScope();
    if (scope == null) return false;
    return (await _store.meta(scope)).syncedAt != null;
  }

  Future<({int count, DateTime? syncedAt})> status() async {
    final scope = await currentScope();
    if (scope == null || !supported) return (count: 0, syncedAt: null);
    return (count: await _store.count(scope), syncedAt: (await _store.meta(scope)).syncedAt);
  }

  // ── Reads used by POS / scanner ──────────────────────────────────────────

  Future<List<Map<String, dynamic>>> products({String search = '', int categoryId = 0}) async {
    final scope = await currentScope();
    if (scope == null) return const [];
    return _store.products(scope, search: search, categoryId: categoryId);
  }

  Future<List<Map<String, dynamic>>> categories() async {
    final scope = await currentScope();
    if (scope == null) return const [];
    return _store.categories(scope);
  }

  Future<Map<String, dynamic>?> productByCode(String code) async {
    final scope = await currentScope();
    if (scope == null) return null;
    return _store.productByCode(scope, code);
  }

  /// Fresh server cards for [ids] (used to re-check the cart before payment).
  /// Also refreshes them in the local copy. Products no longer sellable are
  /// simply missing from the result.
  Future<List<Map<String, dynamic>>> fetchLatest(List<int> ids) async {
    final cards = <Map<String, dynamic>>[];
    for (var i = 0; i < ids.length; i += _chunk) {
      cards.addAll(await _byIds(ids.sublist(i, i + _chunk > ids.length ? ids.length : i + _chunk)));
    }
    final scope = await currentScope();
    if (enabled && scope != null) {
      // '' fingerprint: let the next index sync confirm these rows.
      await _store.upsertProducts(scope, cards, const {});
      final returned = cards.map((c) => (c['id'] as num).toInt()).toSet();
      await _store.deleteProducts(scope, ids.where((id) => !returned.contains(id)));
    }
    return cards;
  }

  // ── Sync ─────────────────────────────────────────────────────────────────

  /// Brings the local copy in line with the server. Concurrent calls share
  /// one run. Never throws — failures land in [lastError].
  Future<void> sync() {
    if (!enabled) return Future.value();
    return _running ??= _sync().whenComplete(() => _running = null);
  }

  Future<void> _sync() async {
    final scope = await currentScope();
    if (scope == null) return;
    syncing = true;
    progressDone = 0;
    progressTotal = 0;
    notifyListeners();
    try {
      // Categories are small — always replace.
      final catRes = await ApiClient.instance.get(
        ApiEndpoints.categories,
        params: {'per_page': 200},
        bypassCache: true,
      );
      final catBody = catRes.data;
      final catList = (catBody is Map ? (catBody['data'] ?? []) : catBody) as List? ?? [];

      final indexRes = await ApiClient.instance.get(ApiEndpoints.productsSyncIndex, bypassCache: true);
      final index = indexRes.data is Map ? indexRes.data['data'] : null;
      if (index is! Map) throw StateError('Unexpected sync index response.');
      final version = index['version'] as String?;
      final serverFp = <int, String>{
        for (final item in (index['items'] as List? ?? const []).whereType<Map>())
          (item['id'] as num).toInt(): item['fp'] as String,
      };

      // A new catalog-wide version (e.g. markup setting changed) → refetch all.
      if ((await _store.meta(scope)).version != version) {
        await _store.clearScope(scope);
      }
      await _store.replaceCategories(
        scope,
        catList.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList(),
      );

      final localFp = await _store.fingerprints(scope);
      final removed = localFp.keys.where((id) => !serverFp.containsKey(id)).toList();
      final changed = serverFp.entries.where((e) => localFp[e.key] != e.value).map((e) => e.key).toList();

      await _store.deleteProducts(scope, removed);

      progressTotal = changed.length;
      notifyListeners();
      for (var i = 0; i < changed.length; i += _chunk) {
        if (!enabled) return; // switched off mid-sync
        final ids = changed.sublist(i, i + _chunk > changed.length ? changed.length : i + _chunk);
        final cards = await _byIds(ids);
        await _store.upsertProducts(scope, cards, serverFp);
        final returned = cards.map((c) => (c['id'] as num).toInt()).toSet();
        await _store.deleteProducts(scope, ids.where((id) => !returned.contains(id)));
        progressDone += ids.length;
        notifyListeners();
      }

      await _store.setMeta(scope, version: version, syncedAt: DateTime.now());
      lastError = null;
      lastErrorWasConnection = false;
    } catch (e) {
      final status = e is DioException ? e.response?.statusCode : null;
      // 404/405: the server predates the sync endpoints (not deployed yet).
      lastError = status == 404 || status == 405
          ? 'The server doesn’t support offline products yet. Update the server, then tap Sync now.'
          : apiErrorMessage(e);
      lastErrorWasConnection = e is DioException &&
          (e.type == DioExceptionType.connectionError ||
              e.type == DioExceptionType.connectionTimeout ||
              e.type == DioExceptionType.receiveTimeout);
    } finally {
      // Switched off mid-sync: drop anything written after the wipe.
      if (!enabled) await _store.clearAll();
      syncing = false;
      notifyListeners();
    }
  }

  Future<List<Map<String, dynamic>>> _byIds(List<int> ids) async {
    if (ids.isEmpty) return const [];
    final res = await ApiClient.instance.get(
      ApiEndpoints.productsByIds,
      params: {'ids': ids.join(',')},
      bypassCache: true,
    );
    final body = res.data;
    final list = (body is Map ? body['data'] : null) as List? ?? const [];
    return list.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }
}
