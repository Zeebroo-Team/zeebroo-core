import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import 'picker_sheet.dart';

class _Line {
  _Line({
    required this.label,
    required this.subtitle,
    this.purchaseItemId,
    this.productId,
    double quantity = 0,
    double unitCost = 0,
  }) : quantityCtrl = TextEditingController(text: quantity == 0 ? '' : '$quantity'),
       unitCostCtrl = TextEditingController(text: unitCost == 0 ? '' : '$unitCost');

  final String label;
  final String subtitle;
  final int? purchaseItemId;
  final Object? productId;
  final TextEditingController quantityCtrl;
  final TextEditingController unitCostCtrl;
}

/// Create sheet for a Goods Receive Note. Two entry paths: from an existing
/// purchase order (prefills remaining quantities) or direct (pick supplier
/// + products with no PO). Pops `true` on success.
class GoodsReceiveFormSheet extends StatefulWidget {
  const GoodsReceiveFormSheet({super.key});

  @override
  State<GoodsReceiveFormSheet> createState() => _GoodsReceiveFormSheetState();
}

class _GoodsReceiveFormSheetState extends State<GoodsReceiveFormSheet> {
  bool _fromPo = false;
  Map<String, dynamic>? _purchase;
  Map<String, dynamic>? _supplier;
  DateTime _receivedDate = DateTime.now();
  final _referenceCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  String _paymentMethod = 'credit';
  final _paymentReferenceCtrl = TextEditingController();
  DateTime? _chequeDueDate;
  Map<String, dynamic>? _deductAccount;
  String _paymentOption = 'full';
  final _payAmountCtrl = TextEditingController();
  final List<_Line> _lines = [];
  bool _loadingPoForm = false;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _referenceCtrl.dispose();
    _notesCtrl.dispose();
    _paymentReferenceCtrl.dispose();
    _payAmountCtrl.dispose();
    for (final l in _lines) {
      l.quantityCtrl.dispose();
      l.unitCostCtrl.dispose();
    }
    super.dispose();
  }

  void _resetLines() {
    for (final l in _lines) {
      l.quantityCtrl.dispose();
      l.unitCostCtrl.dispose();
    }
    _lines.clear();
  }

  Future<void> _pickPurchaseOrder() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Purchase order',
        idOf: (item) => item['id'],
        labelOf: (item) => (item['po_number'] as String?) ?? '',
        subtitleOf: (item) => item['supplier_name'] as String?,
        fetch: (q) async {
          final res = await ApiClient.instance.get(
            ApiEndpoints.purchaseOrders,
            params: {'q': q, 'status': 'all'},
          );
          return parseListData(res.data)
              .where((p) => p['status'] == 'ordered' || p['status'] == 'partially_received')
              .toList();
        },
      ),
    );
    if (picked == null || picked.isEmpty) return;
    setState(() {
      _purchase = picked.first;
      _loadingPoForm = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.purchaseOrderGrnForm((picked.first['id'] as num).toInt()),
      );
      final body = res.data;
      final data = (body is Map ? body['data'] as Map? : null)?.cast<String, dynamic>();
      final items = (data?['items'] as List? ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      _resetLines();
      for (final item in items) {
        final remaining = (item['quantity_remaining'] as num?)?.toDouble() ?? 0;
        _lines.add(
          _Line(
            label: (item['product_name'] as String?) ?? '',
            subtitle: 'Remaining: $remaining',
            purchaseItemId: (item['id'] as num?)?.toInt(),
            quantity: remaining,
            unitCost: (item['unit_cost'] as num?)?.toDouble() ?? 0,
          ),
        );
      }
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loadingPoForm = false);
    }
  }

  Future<void> _pickSupplier() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Supplier',
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        fetch: (q) async {
          final res = await ApiClient.instance.get(ApiEndpoints.suppliers, params: {if (q.isNotEmpty) 'q': q});
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) setState(() => _supplier = picked.first);
  }

  Future<void> _addDirectLine() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Add product',
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        subtitleOf: (item) => item['sku'] as String?,
        fetch: (q) async {
          final res = await ApiClient.instance.get(ApiEndpoints.products, params: {'q': q, 'per_page': 30});
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) {
      final p = picked.first;
      setState(() => _lines.add(
        _Line(
          label: (p['name'] as String?) ?? '',
          subtitle: (p['sku'] as String?) ?? '',
          productId: p['id'],
          quantity: 1,
          unitCost: (p['cost_price'] as num?)?.toDouble() ?? 0,
        ),
      ));
    }
  }

  Future<void> _pickDeductAccount() async {
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
    if (picked != null && picked.isNotEmpty) setState(() => _deductAccount = picked.first);
  }

  Future<void> _submit() async {
    if (_lines.isEmpty) {
      setState(() => _error = 'Add at least one item.');
      return;
    }
    if (_fromPo && _purchase == null) {
      setState(() => _error = 'Select a purchase order.');
      return;
    }
    if (_paymentMethod != 'credit' && _deductAccount == null) {
      setState(() => _error = 'Select an account to deduct from.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final data = {
      'received_date': toApiDate(_receivedDate),
      if (_referenceCtrl.text.trim().isNotEmpty) 'reference': _referenceCtrl.text.trim(),
      if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
      'payment_method': _paymentMethod,
      if (_paymentReferenceCtrl.text.trim().isNotEmpty) 'payment_reference': _paymentReferenceCtrl.text.trim(),
      if (_chequeDueDate != null) 'cheque_due_date': toApiDate(_chequeDueDate!),
      'payment_option': _paymentOption,
      if (_paymentOption == 'partial' && _payAmountCtrl.text.trim().isNotEmpty)
        'pay_amount': double.tryParse(_payAmountCtrl.text.trim()),
      if (_deductAccount != null) 'deduct_account_id': _deductAccount!['id'],
      if (!_fromPo && _supplier != null) 'supplier_id': _supplier!['id'],
      'items': [
        for (final l in _lines)
          if (_fromPo)
            {
              'purchase_item_id': l.purchaseItemId,
              'quantity_received': double.tryParse(l.quantityCtrl.text) ?? 0,
              if (l.unitCostCtrl.text.trim().isNotEmpty) 'selling_unit_price': double.tryParse(l.unitCostCtrl.text),
            }
          else
            {
              'product_id': l.productId,
              'quantity_received': double.tryParse(l.quantityCtrl.text) ?? 0,
              'unit_cost': double.tryParse(l.unitCostCtrl.text) ?? 0,
            },
      ],
    };
    try {
      if (_fromPo) {
        await ApiClient.instance.post(ApiEndpoints.purchaseOrderGrns((_purchase!['id'] as num).toInt()), data: data);
      } else {
        await ApiClient.instance.post(ApiEndpoints.grns, data: data);
      }
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
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.92),
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
              const Text(
                'New goods receive note',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
              const SizedBox(height: 14),
              SegmentedButton<bool>(
                segments: const [
                  ButtonSegment(value: false, label: Text('Direct')),
                  ButtonSegment(value: true, label: Text('From purchase order')),
                ],
                selected: {_fromPo},
                onSelectionChanged: (s) => setState(() {
                  _fromPo = s.first;
                  _resetLines();
                  _purchase = null;
                }),
              ),
              const SizedBox(height: 14),
              if (_fromPo)
                InkWell(
                  onTap: _pickPurchaseOrder,
                  borderRadius: BorderRadius.circular(12),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Purchase order'),
                    child: Text(
                      (_purchase?['po_number'] as String?) ?? 'Tap to select',
                      style: TextStyle(fontSize: 14, color: _purchase == null ? AppColors.textHint : AppColors.textDark),
                    ),
                  ),
                )
              else
                InkWell(
                  onTap: _pickSupplier,
                  borderRadius: BorderRadius.circular(12),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Supplier (optional)'),
                    child: Text(
                      (_supplier?['name'] as String?) ?? 'Tap to select',
                      style: TextStyle(fontSize: 14, color: _supplier == null ? AppColors.textHint : AppColors.textDark),
                    ),
                  ),
                ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () async {
                        final d = await pickDate(context, initial: _receivedDate);
                        if (d != null) setState(() => _receivedDate = d);
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'Received date'),
                        child: Text(formatDate(_receivedDate.toIso8601String())),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _referenceCtrl,
                      decoration: const InputDecoration(labelText: 'Reference'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: _paymentMethod,
                decoration: const InputDecoration(labelText: 'Payment method'),
                items: const [
                  DropdownMenuItem(value: 'credit', child: Text('Credit')),
                  DropdownMenuItem(value: 'cash', child: Text('Cash')),
                  DropdownMenuItem(value: 'cheque', child: Text('Cheque')),
                ],
                onChanged: (v) => setState(() => _paymentMethod = v ?? _paymentMethod),
              ),
              if (_paymentMethod != 'credit') ...[
                const SizedBox(height: 14),
                InkWell(
                  onTap: _pickDeductAccount,
                  borderRadius: BorderRadius.circular(12),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Deduct from account'),
                    child: Text(
                      (_deductAccount?['account_name'] as String?) ?? (_deductAccount?['name'] as String?) ?? 'Tap to select',
                      style: TextStyle(fontSize: 14, color: _deductAccount == null ? AppColors.textHint : AppColors.textDark),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _paymentOption,
                  decoration: const InputDecoration(labelText: 'Payment amount'),
                  items: const [
                    DropdownMenuItem(value: 'full', child: Text('Pay in full')),
                    DropdownMenuItem(value: 'partial', child: Text('Pay partially')),
                  ],
                  onChanged: (v) => setState(() => _paymentOption = v ?? _paymentOption),
                ),
                if (_paymentOption == 'partial') ...[
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _payAmountCtrl,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: const InputDecoration(labelText: 'Amount to pay now'),
                  ),
                ],
              ],
              if (_paymentMethod == 'cheque') ...[
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _paymentReferenceCtrl,
                        decoration: const InputDecoration(labelText: 'Cheque number'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: InkWell(
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
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 14),
              TextFormField(
                controller: _notesCtrl,
                maxLines: 2,
                decoration: const InputDecoration(labelText: 'Notes (optional)'),
              ),
              const SizedBox(height: 18),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Items', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                  if (!_fromPo)
                    TextButton.icon(
                      onPressed: _addDirectLine,
                      icon: const Icon(Icons.add, size: 16),
                      label: const Text('Add product'),
                    ),
                ],
              ),
              if (_loadingPoForm)
                const Padding(padding: EdgeInsets.all(20), child: Center(child: CircularProgressIndicator()))
              else
                for (final line in _lines)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(border: Border.all(color: AppColors.border), borderRadius: BorderRadius.circular(10)),
                      child: Row(
                        children: [
                          Expanded(
                            flex: 3,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(line.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                                if (line.subtitle.isNotEmpty)
                                  Text(line.subtitle, style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            flex: 2,
                            child: TextField(
                              controller: line.quantityCtrl,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: const InputDecoration(labelText: 'Qty', isDense: true),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            flex: 2,
                            child: TextField(
                              controller: line.unitCostCtrl,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: InputDecoration(labelText: _fromPo ? 'Price' : 'Cost', isDense: true),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
              ],
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _saving ? null : _submit,
                child: _saving
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                    : const Text('Record goods receive'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
