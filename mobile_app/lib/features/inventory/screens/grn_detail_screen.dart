import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../widgets/picker_sheet.dart';
import '../widgets/status_chip.dart';

/// View a goods receive note: items, payments, and approve/reject/pay
/// actions. Pops `true` if anything changed.
class GrnDetailScreen extends StatefulWidget {
  const GrnDetailScreen({super.key, required this.grnId});

  final int grnId;

  @override
  State<GrnDetailScreen> createState() => _GrnDetailScreenState();
}

class _GrnDetailScreenState extends State<GrnDetailScreen> {
  bool _loading = true;
  bool _acting = false;
  String? _error;
  Map<String, dynamic>? _grn;
  bool _changed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.grn(widget.grnId));
      final body = res.data;
      _grn = (body is Map ? body['data'] as Map? : null)?.cast<String, dynamic>();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _approveOrReject(String action) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(action == 'approve' ? 'Approve GRN' : 'Reject GRN'),
        content: Text(
          action == 'approve'
              ? 'Approving will apply this stock to your inventory.'
              : 'Rejecting will not apply any stock.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text(action == 'approve' ? 'Approve' : 'Reject')),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _acting = true);
    try {
      final path = action == 'approve' ? ApiEndpoints.grnApprove(widget.grnId) : ApiEndpoints.grnReject(widget.grnId);
      await ApiClient.instance.post(path);
      _changed = true;
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
    } finally {
      if (mounted) setState(() => _acting = false);
    }
  }

  Future<void> _pay() async {
    final outstanding = (_grn?['amount_outstanding'] as num?) ?? 0;
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _GrnPaySheet(grnId: widget.grnId, outstanding: outstanding.toDouble()),
    );
    if (result == true) {
      _changed = true;
      _load();
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop) Navigator.pop(context, _changed);
    },
    child: Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        title: Text(
          (_grn?['grn_number'] as String?) ?? 'Goods receive',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
      ),
      body: _buildBody(),
    ),
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null || _grn == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error ?? 'Not found', style: const TextStyle(color: AppColors.textMuted)),
            const SizedBox(height: 10),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }
    final grn = _grn!;
    final items = (grn['items'] as List? ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    final payments = (grn['payments'] as List? ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    final outstanding = (grn['amount_outstanding'] as num?)?.toDouble() ?? 0;
    final approvalStatus = grn['approval_status'] as String?;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          Row(
            children: [
              StatusChip(label: (grn['payment_status_label'] as String?) ?? ''),
              const SizedBox(width: 6),
              if (approvalStatus != null && approvalStatus.isNotEmpty)
                StatusChip(label: grn['approval_status_label'] as String? ?? approvalStatus),
              const Spacer(),
              Text(formatDate(grn['received_date'] as String?), style: const TextStyle(color: AppColors.textMuted, fontSize: 12.5)),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 12, offset: Offset(0, 3))],
            ),
            child: Column(
              children: [
                _kv('Supplier', (grn['supplier_name'] as String?) ?? '—'),
                const Divider(height: 1, color: AppColors.border),
                _kv('PO number', (grn['po_number'] as String?) ?? '—'),
                const Divider(height: 1, color: AppColors.border),
                _kv('Total', ((grn['total'] as num?) ?? 0).toStringAsFixed(2)),
                const Divider(height: 1, color: AppColors.border),
                _kv('Paid', ((grn['amount_paid'] as num?) ?? 0).toStringAsFixed(2)),
                const Divider(height: 1, color: AppColors.border),
                _kv('Outstanding', outstanding.toStringAsFixed(2)),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const Text('Items', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
          const SizedBox(height: 8),
          for (final item in items)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(border: Border.all(color: AppColors.border), borderRadius: BorderRadius.circular(12)),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text((item['product_name'] as String?) ?? '', style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600)),
                          Text('Qty ${item['quantity_received']}', style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                        ],
                      ),
                    ),
                    Text(
                      ((item['line_total'] as num?) ?? 0).toStringAsFixed(2),
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                    ),
                  ],
                ),
              ),
            ),
          if (payments.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Text('Payments', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
            const SizedBox(height: 8),
            for (final p in payments)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('${p['account'] ?? '—'} · ${formatDate(p['date'] as String?)}', style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted)),
                    Text(((p['amount'] as num?) ?? 0).toStringAsFixed(2), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12.5)),
                  ],
                ),
              ),
          ],
          const SizedBox(height: 20),
          if (_acting)
            const Center(child: CircularProgressIndicator())
          else
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (outstanding > 0)
                  ElevatedButton(onPressed: _pay, child: const Text('Record payment')),
                if (approvalStatus == 'pending') ...[
                  ElevatedButton(onPressed: () => _approveOrReject('approve'), child: const Text('Approve')),
                  OutlinedButton(
                    onPressed: () => _approveOrReject('reject'),
                    style: OutlinedButton.styleFrom(foregroundColor: AppColors.error, side: const BorderSide(color: AppColors.error)),
                    child: const Text('Reject'),
                  ),
                ],
              ],
            ),
        ],
      ),
    );
  }

  Widget _kv(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 10),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
      ],
    ),
  );
}

class _GrnPaySheet extends StatefulWidget {
  const _GrnPaySheet({required this.grnId, required this.outstanding});
  final int grnId;
  final double outstanding;

  @override
  State<_GrnPaySheet> createState() => _GrnPaySheetState();
}

class _GrnPaySheetState extends State<_GrnPaySheet> {
  String _paymentMethod = 'cash';
  Map<String, dynamic>? _account;
  String _paymentOption = 'full';
  final _amountCtrl = TextEditingController();
  final _referenceCtrl = TextEditingController();
  DateTime? _chequeDueDate;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _amountCtrl.dispose();
    _referenceCtrl.dispose();
    super.dispose();
  }

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
    if (_account == null) {
      setState(() => _error = 'Select an account.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.grnPay(widget.grnId),
        data: {
          'payment_method': _paymentMethod,
          'deduct_account_id': _account!['id'],
          'payment_option': _paymentOption,
          if (_paymentOption == 'partial' && _amountCtrl.text.trim().isNotEmpty)
            'pay_amount': double.tryParse(_amountCtrl.text.trim()),
          if (_referenceCtrl.text.trim().isNotEmpty) 'payment_reference': _referenceCtrl.text.trim(),
          if (_chequeDueDate != null) 'cheque_due_date': toApiDate(_chequeDueDate!),
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
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
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
                'Record payment (outstanding ${widget.outstanding.toStringAsFixed(2)})',
                style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
              const SizedBox(height: 18),
              DropdownButtonFormField<String>(
                initialValue: _paymentMethod,
                decoration: const InputDecoration(labelText: 'Payment method'),
                items: const [
                  DropdownMenuItem(value: 'cash', child: Text('Cash')),
                  DropdownMenuItem(value: 'cheque', child: Text('Cheque')),
                ],
                onChanged: (v) => setState(() => _paymentMethod = v ?? _paymentMethod),
              ),
              const SizedBox(height: 14),
              InkWell(
                onTap: _pickAccount,
                borderRadius: BorderRadius.circular(12),
                child: InputDecorator(
                  decoration: const InputDecoration(labelText: 'Account'),
                  child: Text(
                    (_account?['account_name'] as String?) ?? (_account?['name'] as String?) ?? 'Tap to select',
                    style: TextStyle(fontSize: 14, color: _account == null ? AppColors.textHint : AppColors.textDark),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: _paymentOption,
                decoration: const InputDecoration(labelText: 'Amount'),
                items: const [
                  DropdownMenuItem(value: 'full', child: Text('Pay in full')),
                  DropdownMenuItem(value: 'partial', child: Text('Pay partially')),
                ],
                onChanged: (v) => setState(() => _paymentOption = v ?? _paymentOption),
              ),
              if (_paymentOption == 'partial') ...[
                const SizedBox(height: 14),
                TextFormField(
                  controller: _amountCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Amount to pay'),
                ),
              ],
              if (_paymentMethod == 'cheque') ...[
                const SizedBox(height: 14),
                TextFormField(
                  controller: _referenceCtrl,
                  decoration: const InputDecoration(labelText: 'Cheque number'),
                ),
                const SizedBox(height: 14),
                InkWell(
                  onTap: () async {
                    final d = await pickDate(context, initial: _chequeDueDate);
                    if (d != null) setState(() => _chequeDueDate = d);
                  },
                  borderRadius: BorderRadius.circular(12),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Due date'),
                    child: Text(
                      _chequeDueDate == null ? 'Select' : formatDate(_chequeDueDate!.toIso8601String()),
                      style: TextStyle(color: _chequeDueDate == null ? AppColors.textHint : AppColors.textDark),
                    ),
                  ),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
              ],
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _saving ? null : _submit,
                child: _saving
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                    : const Text('Record payment'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
