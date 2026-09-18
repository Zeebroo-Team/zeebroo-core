/// Formats a numeric amount as "1,234.56" — comma-grouped, 2 decimals.
/// Shared by the home tabs (account balances, expenses, profit figures).
String formatMoney(dynamic value) {
  final n = (value as num? ?? 0).toDouble();
  final fixed = n.toStringAsFixed(2);
  final parts = fixed.split('.');
  final whole = parts[0];
  final negative = whole.startsWith('-');
  final digits = negative ? whole.substring(1) : whole;

  final buffer = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buffer.write(',');
    buffer.write(digits[i]);
  }

  return '${negative ? '-' : ''}$buffer.${parts[1]}';
}
