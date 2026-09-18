import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';

/// Small colored status pill used across the Financial tabs (Paid/Overdue/
/// Active/Varies/Expired/...). Unlike Inventory's [StatusChip] this takes an
/// explicit color rather than inferring one from the label, since Financial
/// has many more distinct statuses than the shared inventory vocabulary.
class FinanceBadge extends StatelessWidget {
  const FinanceBadge({super.key, required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 10.5, fontWeight: FontWeight.w800, letterSpacing: 0.2),
    ),
  );
}

/// White rounded card shell shared by every Financial list row.
class FinanceCard extends StatelessWidget {
  const FinanceCard({super.key, required this.child, this.onTap});
  final Widget child;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final content = Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 12, offset: Offset(0, 3))],
      ),
      child: child,
    );
    if (onTap == null) return content;
    return InkWell(onTap: onTap, borderRadius: BorderRadius.circular(14), child: content);
  }
}

/// Outer chrome shared by every "Add X" / "Pay X" bottom sheet: keyboard-
/// aware padding, rounded white sheet, drag handle and title. [child] is the
/// sheet's own `Form` (or, for the pay sheets, a plain `Column`).
class FormSheetShell extends StatelessWidget {
  const FormSheetShell({super.key, required this.title, required this.child, this.loading = false});
  final String title;
  final Widget child;
  final bool loading;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
    child: Container(
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      child: SafeArea(
        top: false,
        child: loading
            ? const Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              )
            : SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark)),
                    const SizedBox(height: 18),
                    child,
                  ],
                ),
              ),
      ),
    ),
  );
}

/// Submit button shared by every sheet: shows a spinner while [saving].
class SheetSubmitButton extends StatelessWidget {
  const SheetSubmitButton({super.key, required this.label, required this.saving, required this.onPressed});
  final String label;
  final bool saving;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => ElevatedButton(
    onPressed: saving ? null : onPressed,
    child: saving
        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
        : Text(label),
  );
}

/// Inline form error text, or nothing if [error] is null.
Widget sheetError(String? error) => error == null
    ? const SizedBox.shrink()
    : Padding(
        padding: const EdgeInsets.only(top: 12, bottom: 4),
        child: Text(error, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
      );

/// Confirms a destructive delete, matching the Inventory tabs' dialog style.
Future<bool> confirmDelete(BuildContext context, {required String title, required String message}) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
        TextButton(
          onPressed: () => Navigator.pop(ctx, true),
          child: const Text('Delete', style: TextStyle(color: AppColors.error)),
        ),
      ],
    ),
  );
  return confirmed == true;
}

/// Date-picker form field shared by every Financial "Add"/"Pay" sheet.
class FinanceDateField extends StatelessWidget {
  const FinanceDateField({super.key, required this.label, required this.value, required this.onPick});
  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onPick;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: () async {
      final picked = await pickDate(context, initial: value);
      if (picked != null) onPick(picked);
    },
    child: InputDecorator(
      decoration: InputDecoration(labelText: label, suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18)),
      child: Text(
        value == null ? 'Select a date' : toApiDate(value!),
        style: TextStyle(color: value == null ? AppColors.textHint : AppColors.textDark, fontSize: 14),
      ),
    ),
  );
}

/// Bottom action sheet listing simple (icon, label) actions for a list card
/// (Pay/Delete/...). Returns the tapped action's label, or null if dismissed.
Future<String?> showFinanceActionSheet(BuildContext context, List<(IconData, String, Color?)> actions) =>
    showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        child: SafeArea(
          top: false,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(height: 10),
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
              ),
              const SizedBox(height: 6),
              for (final a in actions)
                ListTile(
                  leading: Icon(a.$1, color: a.$3 ?? AppColors.textDark),
                  title: Text(a.$2, style: TextStyle(fontWeight: FontWeight.w600, color: a.$3 ?? AppColors.textDark)),
                  onTap: () => Navigator.pop(ctx, a.$2),
                ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
