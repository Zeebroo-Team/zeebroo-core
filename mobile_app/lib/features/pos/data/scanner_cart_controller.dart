import 'package:flutter/foundation.dart';

import '../../../core/api/api_client.dart';
import '../models/pos_cart_item.dart';
import 'scanner_product_repository.dart';

class ScanOutcome {
  const ScanOutcome.added(this.message) : ok = true;
  const ScanOutcome.failed(this.message) : ok = false;

  final bool ok;
  final String message;
}

/// A private working copy of the POS cart, returned only when scanning finishes.
class ScannerCartController extends ChangeNotifier {
  ScannerCartController({
    required this.repository,
    List<PosCartItem> initialCart = const [],
    DateTime Function()? clock,
  }) : _items = initialCart.map((item) => item.copy()).toList(),
       _clock = clock ?? DateTime.now;

  final ScannerProductRepository repository;
  final List<PosCartItem> _items;
  final DateTime Function() _clock;
  bool _disposed = false;
  bool _busy = false;
  String? _lastCode;
  DateTime? _lastSeen;

  bool get busy => _busy;
  List<PosCartItem> get items => List.unmodifiable(_items);
  int get count => _items.fold(0, (count, item) => count + item.qty);
  double get total => _items.fold(0, (total, item) => total + item.lineTotal);
  List<PosCartItem> snapshot() => _items.map((item) => item.copy()).toList();

  /// Seeing the same label in consecutive camera frames adds it only once.
  /// Lift it out of view for a second, or scan a different label, to scan again.
  Future<ScanOutcome?> scanCode(String rawCode, {bool manual = false}) async {
    final code = rawCode.trim();
    if (_disposed || code.isEmpty) return null;
    final now = _clock();
    final repeated =
        !manual &&
        code == _lastCode &&
        _lastSeen != null &&
        now.difference(_lastSeen!) < const Duration(milliseconds: 1200);
    if (!manual && code == _lastCode) _lastSeen = now;
    if (_busy || repeated) return null;

    _lastCode = code;
    _lastSeen = now;
    _busy = true;
    notifyListeners();
    try {
      final product = await repository.lookup(code);
      if (_disposed) return null;
      if (product == null) {
        return ScanOutcome.failed(
          'No product found for "$code". Try manual search.',
        );
      }
      return addProduct(product);
    } catch (error) {
      if (_disposed) return null;
      return ScanOutcome.failed(apiErrorMessage(error));
    } finally {
      _busy = false;
      if (!_disposed) notifyListeners();
    }
  }

  ScanOutcome addProduct(Map<String, dynamic> product) {
    final id = product['id'];
    final price =
        product['discounted_sell_price'] ?? product['unit_sell_price'] ?? 0;
    final stock = product['stock_quantity'];
    if (_disposed ||
        id is! num ||
        !id.isFinite ||
        id <= 0 ||
        id != id.toInt() ||
        product['name'] is! String ||
        price is! num ||
        !price.isFinite ||
        price < 0 ||
        (stock != null && (stock is! num || !stock.isFinite))) {
      return const ScanOutcome.failed(
        'This product could not be read. Please try again.',
      );
    }
    final name = product['name'] as String;
    if (posProductIsOutOfStock(product)) {
      return ScanOutcome.failed('$name is out of stock');
    }
    final index = _items.indexWhere((item) => item.id == id.toInt());
    if (index < 0) {
      _items.add(PosCartItem(product: Map<String, dynamic>.from(product)));
    } else {
      _items[index].qty++;
    }
    notifyListeners();
    final quantity = _items.firstWhere((item) => item.id == id.toInt()).qty;
    return ScanOutcome.added(
      quantity > 1 ? '$name · $quantity in cart' : '$name added',
    );
  }

  void setQuantity(int id, int quantity) {
    if (_busy || _disposed) return;
    final index = _items.indexWhere((item) => item.id == id);
    if (index < 0) return;
    if (quantity <= 0) {
      _items.removeAt(index);
    } else {
      _items[index].qty = quantity;
    }
    notifyListeners();
  }

  void remove(int id) => setQuantity(id, 0);

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}
