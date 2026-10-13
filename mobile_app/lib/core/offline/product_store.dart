import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// On-device SQLite copy of the POS catalog for "Offline products".
///
/// Every row is tagged with a [scope] (business + branch) so switching
/// business never mixes catalogs. Product rows keep the full POS card as
/// JSON — exactly what `GET /v1/pos/online/products` returns — plus a few
/// lower-cased columns for instant search and barcode lookup.
class ProductStore {
  ProductStore._();
  static final ProductStore instance = ProductStore._();

  static const _dbName = 'offline_products.db';
  static const _maxListRows = 500;

  Database? _db;

  Future<Database> get _database async => _db ??= await openDatabase(
    p.join(await getDatabasesPath(), _dbName),
    version: 1,
    onCreate: (db, _) async {
      await db.execute('''
        CREATE TABLE products (
          scope TEXT NOT NULL,
          id INTEGER NOT NULL,
          name_lc TEXT NOT NULL,
          sku_lc TEXT NOT NULL,
          category_ids TEXT NOT NULL,
          fp TEXT NOT NULL,
          card TEXT NOT NULL,
          PRIMARY KEY (scope, id)
        )''');
      await db.execute('CREATE INDEX products_name ON products (scope, name_lc)');
      await db.execute('''
        CREATE TABLE barcodes (
          scope TEXT NOT NULL,
          code TEXT NOT NULL,
          product_id INTEGER NOT NULL,
          layer_id INTEGER,
          PRIMARY KEY (scope, code)
        )''');
      await db.execute('CREATE INDEX barcodes_product ON barcodes (scope, product_id)');
      await db.execute('''
        CREATE TABLE categories (
          scope TEXT NOT NULL,
          position INTEGER NOT NULL,
          card TEXT NOT NULL
        )''');
      await db.execute('''
        CREATE TABLE meta (
          scope TEXT PRIMARY KEY,
          version TEXT,
          synced_at INTEGER
        )''');
    },
  );

  // ── Reads ────────────────────────────────────────────────────────────────

  /// POS product list: every search word must match the name or SKU (same
  /// rule as the server), optionally within one category, sorted by name.
  Future<List<Map<String, dynamic>>> products(
    String scope, {
    String search = '',
    int categoryId = 0,
  }) async {
    final where = <String>['scope = ?'];
    final args = <Object>[scope];
    for (final word in search.toLowerCase().split(RegExp(r'\s+'))) {
      if (word.isEmpty) continue;
      final like = '%${word.replaceAll(r'\', r'\\').replaceAll('%', r'\%').replaceAll('_', r'\_')}%';
      where.add(r"(name_lc LIKE ? ESCAPE '\' OR sku_lc LIKE ? ESCAPE '\')");
      args..add(like)..add(like);
    }
    if (categoryId > 0) {
      where.add('category_ids LIKE ?');
      args.add('%,$categoryId,%');
    }
    final rows = await (await _database).query(
      'products',
      columns: ['card'],
      where: where.join(' AND '),
      whereArgs: args,
      orderBy: 'name_lc',
      limit: _maxListRows,
    );
    return rows.map((r) => _decode(r['card'])).toList();
  }

  /// Product (or batch) barcode → POS card; batch matches carry the same
  /// `matched_*` fields as `GET online/products/sku/{sku}`.
  Future<Map<String, dynamic>?> productByCode(String scope, String code) async {
    final db = await _database;
    final hit = await db.query(
      'barcodes',
      where: 'scope = ? AND code = ?',
      whereArgs: [scope, code.trim().toLowerCase()],
      limit: 1,
    );
    if (hit.isEmpty) return null;
    final productId = hit.first['product_id'] as int;
    final layerId = hit.first['layer_id'] as int?;
    final rows = await db.query(
      'products',
      columns: ['card'],
      where: 'scope = ? AND id = ?',
      whereArgs: [scope, productId],
      limit: 1,
    );
    if (rows.isEmpty) return null;
    final card = _decode(rows.first['card']);
    if (layerId != null) {
      final layer = (card['layers'] as List? ?? const [])
          .whereType<Map>()
          .where((l) => (l['id'] as num?)?.toInt() == layerId)
          .firstOrNull;
      card['matched_layer_id'] = layerId;
      card['matched_batch_sku'] = code.trim();
      if (layer != null) card['layer_qty_remaining'] = layer['quantity_remaining'];
    }
    return card;
  }

  Future<List<Map<String, dynamic>>> categories(String scope) async {
    final rows = await (await _database).query(
      'categories',
      columns: ['card'],
      where: 'scope = ?',
      whereArgs: [scope],
      orderBy: 'position',
    );
    return rows.map((r) => _decode(r['card'])).toList();
  }

  /// Stored fingerprint per product id, for the sync diff.
  Future<Map<int, String>> fingerprints(String scope) async {
    final rows = await (await _database).query(
      'products',
      columns: ['id', 'fp'],
      where: 'scope = ?',
      whereArgs: [scope],
    );
    return {for (final r in rows) r['id'] as int: r['fp'] as String};
  }

  Future<int> count(String scope) async =>
      Sqflite.firstIntValue(
        await (await _database).rawQuery('SELECT COUNT(*) FROM products WHERE scope = ?', [scope]),
      ) ??
      0;

  Future<({String? version, DateTime? syncedAt})> meta(String scope) async {
    final rows = await (await _database).query('meta', where: 'scope = ?', whereArgs: [scope], limit: 1);
    if (rows.isEmpty) return (version: null, syncedAt: null);
    final ms = rows.first['synced_at'] as int?;
    return (
      version: rows.first['version'] as String?,
      syncedAt: ms == null ? null : DateTime.fromMillisecondsSinceEpoch(ms),
    );
  }

  // ── Writes ───────────────────────────────────────────────────────────────

  /// Inserts/replaces POS cards. [fingerprints] maps product id → index
  /// fingerprint ('' forces a re-download on the next sync).
  Future<void> upsertProducts(
    String scope,
    List<Map<String, dynamic>> cards,
    Map<int, String> fingerprints,
  ) async {
    if (cards.isEmpty) return;
    await (await _database).transaction((txn) async {
      final batch = txn.batch();
      for (final card in cards) {
        final id = (card['id'] as num).toInt();
        final categoryIds = (card['category_ids'] as List? ?? const []).join(',');
        batch.insert('products', {
          'scope': scope,
          'id': id,
          'name_lc': ((card['name'] as String?) ?? '').toLowerCase(),
          'sku_lc': ((card['sku'] as String?) ?? '').toLowerCase(),
          'category_ids': ',$categoryIds,',
          'fp': fingerprints[id] ?? '',
          'card': jsonEncode(card),
        }, conflictAlgorithm: ConflictAlgorithm.replace);

        batch.delete('barcodes', where: 'scope = ? AND product_id = ?', whereArgs: [scope, id]);
        final sku = ((card['sku'] as String?) ?? '').trim().toLowerCase();
        if (sku.isNotEmpty) {
          batch.insert('barcodes', {'scope': scope, 'code': sku, 'product_id': id, 'layer_id': null},
              conflictAlgorithm: ConflictAlgorithm.replace);
        }
        // `batch_skus` is added by the sync endpoint (cards' layers don't carry it).
        for (final entry in (card['batch_skus'] as List? ?? const []).whereType<Map>()) {
          final batchSku = ((entry['sku'] as String?) ?? '').trim().toLowerCase();
          if (batchSku.isEmpty) continue;
          batch.insert('barcodes', {
            'scope': scope,
            'code': batchSku,
            'product_id': id,
            'layer_id': (entry['layer_id'] as num?)?.toInt(),
          }, conflictAlgorithm: ConflictAlgorithm.replace);
        }
      }
      await batch.commit(noResult: true);
    });
  }

  /// Lowers saved stock after an offline sale (product id → quantity sold).
  /// The next sync replaces it with the server's figure.
  Future<void> reduceStock(String scope, Map<int, double> sold) async {
    if (sold.isEmpty) return;
    final db = await _database;
    await db.transaction((txn) async {
      for (final entry in sold.entries) {
        final rows = await txn.query('products',
            columns: ['card'], where: 'scope = ? AND id = ?', whereArgs: [scope, entry.key], limit: 1);
        if (rows.isEmpty) continue;
        final card = _decode(rows.first['card']);
        for (final key in ['stock_quantity', 'available_stock']) {
          final value = (card[key] as num?)?.toDouble();
          if (value != null) card[key] = (value - entry.value).clamp(0, double.infinity);
        }
        await txn.update('products', {'card': jsonEncode(card)},
            where: 'scope = ? AND id = ?', whereArgs: [scope, entry.key]);
      }
    });
  }

  Future<void> deleteProducts(String scope, Iterable<int> ids) async {
    final list = ids.toList();
    if (list.isEmpty) return;
    await (await _database).transaction((txn) async {
      for (var i = 0; i < list.length; i += 500) {
        final chunk = list.sublist(i, i + 500 > list.length ? list.length : i + 500);
        final marks = List.filled(chunk.length, '?').join(',');
        await txn.delete('products', where: 'scope = ? AND id IN ($marks)', whereArgs: [scope, ...chunk]);
        await txn.delete('barcodes', where: 'scope = ? AND product_id IN ($marks)', whereArgs: [scope, ...chunk]);
      }
    });
  }

  Future<void> replaceCategories(String scope, List<Map<String, dynamic>> categories) async {
    await (await _database).transaction((txn) async {
      await txn.delete('categories', where: 'scope = ?', whereArgs: [scope]);
      for (var i = 0; i < categories.length; i++) {
        await txn.insert('categories', {'scope': scope, 'position': i, 'card': jsonEncode(categories[i])});
      }
    });
  }

  Future<void> setMeta(String scope, {required String? version, required DateTime syncedAt}) async {
    await (await _database).insert(
      'meta',
      {'scope': scope, 'version': version, 'synced_at': syncedAt.millisecondsSinceEpoch},
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  /// Removes one business/branch catalog (e.g. after a full resync reset).
  Future<void> clearScope(String scope) async {
    final db = await _database;
    await db.transaction((txn) async {
      for (final table in ['products', 'barcodes', 'categories', 'meta']) {
        await txn.delete(table, where: 'scope = ?', whereArgs: [scope]);
      }
    });
  }

  /// Deletes the whole database file (switch turned off, or logout).
  Future<void> clearAll() async {
    await _db?.close();
    _db = null;
    await deleteDatabase(p.join(await getDatabasesPath(), _dbName));
  }

  static Map<String, dynamic> _decode(Object? json) =>
      Map<String, dynamic>.from(jsonDecode(json as String) as Map);
}
