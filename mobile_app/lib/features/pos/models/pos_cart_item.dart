/// A cart line shared by POS and the live barcode scanner.
class PosCartItem {
  PosCartItem({required this.product, this.qty = 1, this.itemDiscountPct = 0});

  final Map<String, dynamic> product;
  int qty;
  double itemDiscountPct;

  int get id => (product['id'] as num).toInt();
  String get name => (product['name'] as String?) ?? '';
  double get basePrice =>
      (product['discounted_sell_price'] as num?)?.toDouble() ??
      (product['unit_sell_price'] as num?)?.toDouble() ??
      0;
  double get effectivePrice => basePrice * (1 - itemDiscountPct / 100);
  double get lineTotal => effectivePrice * qty;

  PosCartItem copy() => PosCartItem(
    product: Map<String, dynamic>.from(product),
    qty: qty,
    itemDiscountPct: itemDiscountPct,
  );
}

/// Keep scanner additions consistent with the existing POS stock rule.
bool posProductIsOutOfStock(Map<String, dynamic> product) {
  final stock = (product['stock_quantity'] as num?)?.toDouble();
  return stock != null && stock <= 0;
}
