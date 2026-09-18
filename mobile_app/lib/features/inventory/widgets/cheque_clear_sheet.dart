import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'picker_sheet.dart';

/// Confirm sheet to clear a pending/due/overdue cheque. Pops `true` on success.
class ChequeClearSheet extends StatefulWidget {
  const ChequeClearSheet({super.key, required this.cheque});

  final Map<String, dynamic> cheque;

  @override
  State<ChequeClearSheet> createState() => _ChequeClearSheetState();
}

class _ChequeClearSheetState extends State<ChequeClearSheet> {
  Map<String, dynamic>? _account;
  bool _saving = false;
  String? _error;

  Future<void> _pickAccount() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Deduct from account',
        idOf: (item) => item['id'],
        labelOf: (item) => (item['account_name'] as String?) ?? (item['name'] as String?) ?? '',
        fetch: (q) async {
          final res = await ApiClient.instance.get(ApiEndpoints.accounts);
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) setState(() => _account = picked.first);
  }

  Future<void> _submit() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.chequeClear((widget.cheque['id'] as num).toInt()),
        data: {if (_account != null) 'deduct_account_id': _account!['id']},
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
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      child: SafeArea(
        top: false,
        child: Padding(
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
              Text(
                'Clear cheque ${widget.cheque['cheque_number'] ?? ''}',
                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
              const SizedBox(height: 6),
              Text(
                'Amount ${((widget.cheque['amount'] as num?) ?? 0).toStringAsFixed(2)}',
                style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
              ),
              const SizedBox(height: 18),
              InkWell(
                onTap: _pickAccount,
                borderRadius: BorderRadius.circular(12),
                child: InputDecorator(
                  decoration: const InputDecoration(labelText: 'Account (defaults to the cheque\'s account)'),
                  child: Text(
                    (_account?['account_name'] as String?) ?? (_account?['name'] as String?) ?? (widget.cheque['account'] as String?) ?? 'Tap to select',
                    style: TextStyle(fontSize: 14, color: _account == null ? AppColors.textHint : AppColors.textDark),
                  ),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
              ],
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _saving ? null : _submit,
                child: _saving
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                    : const Text('Clear cheque'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
