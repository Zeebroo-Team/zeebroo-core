import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

const _kMonths = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

/// Formats an ISO date/datetime string (as returned by the Laravel API) as
/// `18 Sep 2026`. Returns [fallback] (default `'—'`) for null/blank/unparseable input.
String formatDate(String? iso, {String fallback = '—'}) {
  if (iso == null || iso.trim().isEmpty) return fallback;
  final date = DateTime.tryParse(iso);
  if (date == null) return fallback;
  return '${date.day} ${_kMonths[date.month - 1]} ${date.year}';
}

/// Opens a themed date picker and returns the picked date, or null if cancelled.
Future<DateTime?> pickDate(
  BuildContext context, {
  DateTime? initial,
  DateTime? firstDate,
  DateTime? lastDate,
}) {
  final now = DateTime.now();
  return showDatePicker(
    context: context,
    initialDate: initial ?? now,
    firstDate: firstDate ?? DateTime(now.year - 5),
    lastDate: lastDate ?? DateTime(now.year + 5),
    builder: (context, child) => Theme(
      data: Theme.of(context).copyWith(
        colorScheme: Theme.of(context).colorScheme.copyWith(
          primary: AppColors.primary,
        ),
      ),
      child: child!,
    ),
  );
}

/// Formats a [DateTime] as the `YYYY-MM-DD` shape the Laravel API expects.
String toApiDate(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-'
    '${date.month.toString().padLeft(2, '0')}-'
    '${date.day.toString().padLeft(2, '0')}';
