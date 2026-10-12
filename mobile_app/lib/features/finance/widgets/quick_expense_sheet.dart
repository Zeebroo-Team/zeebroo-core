import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_client.dart';
import '../../../core/offline/offline_expenses.dart';
import '../../../core/theme/app_theme.dart';

/// Records an everyday expense in a few taps. It is saved as a one-time bill
/// due today (pay it later from Bills). Works offline when Offline mode is
/// on: the expense waits on the phone and uploads when the connection is back.
///
/// Pops `true` once saved (either way).
class QuickExpenseSheet extends StatefulWidget {
  const QuickExpenseSheet({super.key});

  static Future<bool?> show(BuildContext context) => showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    builder: (_) => const QuickExpenseSheet(),
  );

  @override
  State<QuickExpenseSheet> createState() => _QuickExpenseSheetState();
}

class _QuickExpenseSheetState extends State<QuickExpenseSheet> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _amount = TextEditingController();
  final _describe = TextEditingController();
  final _notes = TextEditingController();
  String _category = 'other';
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _amount.dispose();
    _describe.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final name = _name.text.trim();
      final savedOnPhone = await OfflineExpenses.instance.submit(
        name: name,
        amount: double.parse(_amount.text.trim()),
        category: _category,
        categoryOther: _describe.text.trim().isEmpty ? name : _describe.text.trim(),
        notes: _notes.text.trim(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            savedOnPhone
                ? 'No connection — expense saved on this phone. It uploads when you’re back online.'
                : 'Expense saved. Pay it from Bills when ready.',
          ),
        ),
      );
      Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: keyboard),
      child: Container(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.9),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Form(
          key: _formKey,
          child: ListView(
            shrinkWrap: true,
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
                ),
              ),
              const SizedBox(height: 14),
              const Text('Quick expense', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              const Text(
                'Saved as a one-time bill due today. Works offline too.',
                style: TextStyle(fontSize: 12.5, color: AppColors.textMuted),
              ),
              const SizedBox(height: 16),
              TextFormField(
                key: const ValueKey('quick-expense-name'),
                controller: _name,
                autofocus: true,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'What was it for? *',
                  prefixIcon: Icon(Icons.receipt_long_outlined),
                ),
                validator: (v) => (v?.trim().isEmpty ?? true) ? 'Enter what the expense was for' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                key: const ValueKey('quick-expense-amount'),
                controller: _amount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
                decoration: const InputDecoration(labelText: 'Amount *', prefixIcon: Icon(Icons.payments_outlined)),
                validator: (v) {
                  final amount = double.tryParse(v?.trim() ?? '');
                  return amount == null || amount < 0.01 ? 'Enter an amount' : null;
                },
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _category,
                decoration: const InputDecoration(labelText: 'Category', prefixIcon: Icon(Icons.category_outlined)),
                items: [
                  for (final c in OfflineExpenses.categories.entries)
                    DropdownMenuItem(value: c.key, child: Text(c.value)),
                ],
                onChanged: _saving ? null : (v) => setState(() => _category = v ?? 'other'),
              ),
              if (_category == 'other') ...[
                const SizedBox(height: 12),
                TextFormField(
                  controller: _describe,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Type of expense (optional)',
                    hintText: 'e.g. Refreshments, Transport',
                    prefixIcon: Icon(Icons.label_outline_rounded),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              TextFormField(
                controller: _notes,
                maxLines: 2,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(labelText: 'Note', prefixIcon: Icon(Icons.notes_rounded)),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: AppColors.error)),
              ],
              const SizedBox(height: 20),
              ElevatedButton.icon(
                key: const ValueKey('quick-expense-save'),
                onPressed: _saving ? null : _save,
                icon: _saving
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.check_rounded),
                label: Text(_saving ? 'Saving...' : 'Save expense'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
