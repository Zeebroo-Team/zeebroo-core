import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';

const _kAccountCategories = [
  ('operating', 'Operating'),
  ('savings', 'Savings'),
  ('petty_cash', 'Petty cash'),
  ('credit_card', 'Credit card'),
  ('payroll', 'Payroll'),
  ('investment', 'Investment'),
  ('loan', 'Loan'),
];

/// Bottom sheet form that creates a bank account via `POST /v1/pos/accounts`.
/// Pops `true` on success so the caller knows to refresh its account list.
class AddAccountSheet extends StatefulWidget {
  const AddAccountSheet({super.key});

  @override
  State<AddAccountSheet> createState() => _AddAccountSheetState();
}

class _AddAccountSheetState extends State<AddAccountSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _balanceCtrl = TextEditingController(text: '0');
  final _accountNumberCtrl = TextEditingController();
  final _branchCtrl = TextEditingController();

  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _bankTypes = [];
  List<Map<String, dynamic>> _banks = [];
  String _category = _kAccountCategories.first.$1;
  int? _bankTypeId;
  int? _bankId;

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _balanceCtrl.dispose();
    _accountNumberCtrl.dispose();
    _branchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    try {
      final results = await Future.wait([
        ApiClient.instance.get(ApiEndpoints.bankTypes),
        ApiClient.instance.get(ApiEndpoints.banks),
      ]);
      final typesRaw = results[0].data;
      final types = (typesRaw is Map ? typesRaw['data'] : typesRaw) as List? ?? [];
      final banksRaw = results[1].data;
      final banks = (banksRaw is Map ? banksRaw['data'] : banksRaw) as List? ?? [];

      _bankTypes = types.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      _banks = banks.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      if (_bankTypes.isNotEmpty) _bankTypeId = _bankTypes.first['id'] as int?;
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk || _bankTypeId == null) {
      setState(() => _error = _bankTypeId == null ? 'Select an account type.' : _error);
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.accounts,
        data: {
          'account_name': _nameCtrl.text.trim(),
          'category': _category,
          'bank_type_id': _bankTypeId,
          if (_bankId != null) 'bank_id': _bankId,
          if (_accountNumberCtrl.text.trim().isNotEmpty)
            'bank_account_number': _accountNumberCtrl.text.trim(),
          if (_branchCtrl.text.trim().isNotEmpty) 'branch': _branchCtrl.text.trim(),
          'current_balance': double.tryParse(_balanceCtrl.text.trim()) ?? 0,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
    child: Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SafeArea(
        top: false,
        child: _loadingOptions
            ? const Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              )
            : SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Center(
                        child: Container(
                          width: 40,
                          height: 4,
                          decoration: BoxDecoration(
                            color: AppColors.border,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        'Add bank account',
                        style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
                      ),
                      const SizedBox(height: 18),
                      TextFormField(
                        controller: _nameCtrl,
                        decoration: const InputDecoration(labelText: 'Account name'),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<String>(
                        initialValue: _category,
                        decoration: const InputDecoration(labelText: 'Category'),
                        items: [
                          for (final c in _kAccountCategories) DropdownMenuItem(value: c.$1, child: Text(c.$2)),
                        ],
                        onChanged: (v) => setState(() => _category = v ?? _category),
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<int>(
                        initialValue: _bankTypeId,
                        decoration: const InputDecoration(labelText: 'Account type'),
                        items: [
                          for (final t in _bankTypes)
                            DropdownMenuItem(value: t['id'] as int, child: Text(t['name'] as String? ?? '')),
                        ],
                        onChanged: (v) => setState(() => _bankTypeId = v),
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<int?>(
                        initialValue: _bankId,
                        decoration: const InputDecoration(labelText: 'Bank (optional)'),
                        items: [
                          const DropdownMenuItem(value: null, child: Text('—')),
                          for (final b in _banks)
                            DropdownMenuItem(value: b['id'] as int, child: Text(b['name'] as String? ?? '')),
                        ],
                        onChanged: (v) => setState(() => _bankId = v),
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: _accountNumberCtrl,
                        decoration: const InputDecoration(labelText: 'Account number (optional)'),
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: _branchCtrl,
                        decoration: const InputDecoration(labelText: 'Branch (optional)'),
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: _balanceCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Opening balance'),
                        validator: (v) {
                          final n = double.tryParse((v ?? '').trim());
                          if (n == null || n < 0) return 'Enter a valid amount';
                          return null;
                        },
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 12),
                        Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
                      ],
                      const SizedBox(height: 20),
                      ElevatedButton(
                        onPressed: _saving ? null : _submit,
                        child: _saving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                              )
                            : const Text('Add account'),
                      ),
                    ],
                  ),
                ),
              ),
      ),
    ),
  );
}
