import 'package:intl/intl.dart';

double? receiptNumber(dynamic value) {
  final number = value is num
      ? value.toDouble()
      : double.tryParse(value?.toString() ?? '');
  return number != null && number.isFinite ? number : null;
}

String _text(dynamic value) => value?.toString().trim() ?? '';
Map _map(dynamic value) => value is Map ? value : const {};

/// A snapshot of the saved sale, independent of the editable POS cart.
class SaleReceipt {
  SaleReceipt.fromJson(Map data)
    : saleNumber = _text(data['sale_number']),
      paymentMethod = _text(data['payment_method']),
      paymentLabel = _text(data['payment_method_label']).isNotEmpty
          ? _text(data['payment_method_label'])
          : _text(data['payment_method']).toUpperCase(),
      soldAt = DateTime.tryParse(_text(data['sold_at'])),
      cashier = _text(_map(data['cashier'])['name']),
      customer = _text(data['customer_name']),
      account = _text(_map(data['credit_account'])['label']),
      notes = _text(data['notes']),
      subtotal = receiptNumber(data['subtotal']) ?? 0,
      discount = receiptNumber(data['discount_amount']) ?? 0,
      couponDiscount = receiptNumber(data['coupon_discount']) ?? 0,
      couponCode = _text(_map(data['coupon'])['code']),
      total = receiptNumber(data['total']) ?? 0,
      paid = receiptNumber(data['amount_paid']) ?? 0,
      tendered = receiptNumber(data['amount_tendered']),
      change = receiptNumber(data['change_amount']) ?? 0,
      giftCardAmount = receiptNumber(data['gift_card_amount']) ?? 0,
      giftCardCode = _text(_map(data['gift_card'])['code']),
      items = List.unmodifiable([
        if (data['items'] is List)
          for (final item in data['items'] as List)
            if (item is Map) ReceiptItem.fromJson(item),
      ]),
      detailsAvailable =
          _text(data['sale_number']).isNotEmpty &&
          (receiptNumber(data['total']) ?? -1) >= 0 &&
          receiptNumber(data['subtotal']) != null &&
          receiptNumber(data['amount_paid']) != null &&
          data['items'] is List &&
          (data['items'] as List).isNotEmpty &&
          (data['items'] as List).every(
            (item) =>
                item is Map &&
                receiptNumber(item['quantity']) != null &&
                receiptNumber(item['unit_sell_price']) != null &&
                receiptNumber(item['line_total']) != null,
          );

  final String saleNumber, paymentMethod, paymentLabel;
  final String cashier, customer, account, notes, couponCode, giftCardCode;
  final DateTime? soldAt;
  final double subtotal, discount, couponDiscount, total, paid, change;
  final double giftCardAmount;
  final double? tendered;
  final List<ReceiptItem> items;
  final bool detailsAvailable;

  String get dateLabel => soldAt == null
      ? 'Not available'
      : DateFormat('dd/MM/yyyy, h:mm a').format(soldAt!.toLocal());

  // API amount_paid includes the gift-card portion; don't count it twice.
  double get paidByMethod => (paid - giftCardAmount).clamp(0, total);
  double get outstanding => (total - paid).clamp(0, total);

  List<ReceiptSummaryLine> summary(ReceiptSettings settings) => [
    ReceiptSummaryLine('Subtotal', settings.money(subtotal)),
    if (discount > 0)
      ReceiptSummaryLine('Order discount', '-${settings.money(discount)}'),
    if (couponDiscount > 0)
      ReceiptSummaryLine(
        'Coupon $couponCode'.trim(),
        '-${settings.money(couponDiscount)}',
      ),
    ReceiptSummaryLine('TOTAL', settings.money(total), emphasized: true),
    if (giftCardAmount > 0)
      ReceiptSummaryLine(
        'Gift card $giftCardCode'.trim(),
        settings.money(giftCardAmount),
      ),
    if (giftCardAmount > 0)
      ReceiptSummaryLine(
        'Amount due',
        settings.money((total - giftCardAmount).clamp(0, total)),
      ),
    ReceiptSummaryLine('Paid ($paymentLabel)', settings.money(paidByMethod)),
    if (paymentMethod == 'cash' && tendered != null)
      ReceiptSummaryLine('Cash tendered', settings.money(tendered!)),
    if (paymentMethod == 'cash')
      ReceiptSummaryLine('Change', settings.money(change)),
    if (paymentMethod == 'credit')
      ReceiptSummaryLine('Outstanding', settings.money(outstanding)),
  ];
}

class ReceiptItem {
  ReceiptItem.fromJson(Map data)
    : name = _text(data['product_name']),
      quantity = receiptNumber(data['quantity']) ?? 0,
      unitPrice = receiptNumber(data['unit_sell_price']) ?? 0,
      lineTotal = receiptNumber(data['line_total']) ?? 0,
      discountPerUnit = receiptNumber(data['discount_amount']) ?? 0;

  final String name;
  final double quantity, unitPrice, lineTotal, discountPerUnit;

  String get quantityLabel => NumberFormat('0.###').format(quantity);
}

class ReceiptSummaryLine {
  const ReceiptSummaryLine(this.label, this.value, {this.emphasized = false});
  final String label, value;
  final bool emphasized;
}

class ReceiptSettings {
  const ReceiptSettings({
    this.businessName = 'Receipt',
    this.currency = '',
    this.currencyBefore = false,
    this.header = '',
    this.footer = 'Thank you for your purchase!',
    this.showBusinessName = true,
    this.address = '',
    this.showAccount = true,
    this.logoUrl = '',
    this.paperWidth = 80,
    this.isFallback = false,
  });

  factory ReceiptSettings.fromJson(Map data) => ReceiptSettings(
    businessName: _text(data['business_name']).isNotEmpty
        ? _text(data['business_name'])
        : 'Receipt',
    currency: _text(data['currency']),
    currencyBefore: data['currency_position'] == 'before',
    header: _text(data['receipt_header']),
    footer: data['receipt_footer'] == null
        ? 'Thank you for your purchase!'
        : _text(data['receipt_footer']),
    showBusinessName: data['show_business_name'] != false,
    address: data['show_business_address'] == true
        ? _text(data['receipt_address_line'])
        : '',
    showAccount: data['show_account_info'] != false,
    logoUrl: _text(data['receipt_logo_url']).isNotEmpty
        ? _text(data['receipt_logo_url'])
        : _text(data['business_logo_url']),
    paperWidth: data['receipt_paper_width']?.toString() == '58' ? 58 : 80,
  );

  final String businessName, currency, header, footer, address, logoUrl;
  final bool currencyBefore, showBusinessName, showAccount, isFallback;
  final int paperWidth;

  String money(double amount, {bool withCurrency = true}) {
    final value = NumberFormat('#,##0.00').format(amount);
    if (!withCurrency || currency.isEmpty) return value;
    return currencyBefore ? '$currency $value' : '$value $currency';
  }
}
