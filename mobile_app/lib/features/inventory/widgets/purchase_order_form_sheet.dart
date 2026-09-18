import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import 'picker_sheet.dart';

class _Line {
  _Line({required this.product})
    : quantityCtrl = TextEditingController(text: '1'),
      unitCostCtrl = TextEditingController(
        text: (product['cost_price'] as num?)?.toString() ?? '0',
      );
  final Map<String, dynamic> product;
  final TextEditingController quantityCtrl;
  final TextEditingController unitCostCtrl;
}

/// Create sheet for a purchase order: supplier, dates, notes and a
/// repeatable product line-item list. Pops `true` on success.
class PurchaseOrderFormSheet extends StatefulWidget {
  const PurchaseOrderFormSheet({super.key});

  @override
  State<PurchaseOrderFormSheet> createState() => _PurchaseOrderFormSheetState();
}

class _PurchaseOrderFormSheetState extends State<PurchaseOrderFormSheet> {
  final _referenceCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  Map<String, dynamic>? _supplier;
  DateTime _purchaseDate = DateTime.now();
  DateTime? _expectedDate;
  String _status = 'draft';
  final List<_Line> _lines = [];
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _referenceCtrl.dispose();
    _notesCtrl.dispose();
    for (final l in _lines) {
      l.quantityCtrl.dispose();
      l.unitCostCtrl.dispose();
    }
    super.dispose();
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
          final res = await ApiClient.instance.get(
            ApiEndpoints.suppliers,
            params: {if (q.isNotEmpty) 'q': q},
          );
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) setState(() => _supplier = picked.first);
  }

  Future<void> _addLine() async {
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
          final res = await ApiClient.instance.get(
            ApiEndpoints.products,
            params: {'q': q, 'per_page': 30},
          );
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) {
      setState(() => _lines.add(_Line(product: picked.first)));
    }
  }

  void _removeLine(int index) {
    setState(() {
      final l = _lines.removeAt(index);
      l.quantityCtrl.dispose();
      l.unitCostCtrl.dispose();
    });
  }

  double get _total => _lines.fold(0, (sum, l) {
    final qty = double.tryParse(l.quantityCtrl.text) ?? 0;
    final cost = double.tryParse(l.unitCostCtrl.text) ?? 0;
    return sum + qty * cost;
  });

  Future<void> _submit() async {
    if (_lines.isEmpty) {
      setState(() => _error = 'Add at least one product.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final data = {
      if (_supplier != null) 'supplier_id': _supplier!['id'],
      if (_referenceCtrl.text.trim().isNotEmpty) 'reference': _referenceCtrl.text.trim(),
      'purchase_date': toApiDate(_purchaseDate),
      if (_expectedDate != null) 'expected_delivery_date': toApiDate(_expectedDate!),
      'status': _status,
      if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
      'items': [
        for (final l in _lines)
          {
            'product_id': l.product['id'],
            'quantity': double.tryParse(l.quantityCtrl.text) ?? 0,
            'unit_cost': double.tryParse(l.unitCostCtrl.text) ?? 0,
          },
      ],
    };
    try {
      await ApiClient.instance.post(ApiEndpoints.purchaseOrders, data: data);
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
                'New purchase order',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
              const SizedBox(height: 18),
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
              TextFormField(
                controller: _referenceCtrl,
                decoration: const InputDecoration(labelText: 'Reference (optional)'),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () async {
                        final d = await pickDate(context, initial: _purchaseDate);
                        if (d != null) setState(() => _purchaseDate = d);
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'Purchase date'),
                        child: Text(formatDate(_purchaseDate.toIso8601String())),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: InkWell(
                      onTap: () async {
                        final d = await pickDate(context, initial: _expectedDate);
                        if (d != null) setState(() => _expectedDate = d);
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'Expected delivery'),
                        child: Text(
                          _expectedDate == null ? 'None' : formatDate(_expectedDate!.toIso8601String()),
                          style: TextStyle(color: _expectedDate == null ? AppColors.textHint : AppColors.textDark),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: _status,
                decoration: const InputDecoration(labelText: 'Status'),
                items: const [
                  DropdownMenuItem(value: 'draft', child: Text('Draft')),
                  DropdownMenuItem(value: 'ordered', child: Text('Ordered')),
                ],
                onChanged: (v) => setState(() => _status = v ?? _status),
              ),
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
                  TextButton.icon(
                    onPressed: _addLine,
                    icon: const Icon(Icons.add, size: 16),
                    label: const Text('Add product'),
                  ),
                ],
              ),
              for (var i = 0; i < _lines.length; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _LineRow(line: _lines[i], onRemove: () => _removeLine(i), onChanged: () => setState(() {})),
                ),
              if (_lines.isNotEmpty) ...[
                const Divider(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text(_total.toStringAsFixed(2), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                  ],
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
              ],
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _saving ? null : _submit,
                child: _saving
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                    : const Text('Create purchase order'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _LineRow extends StatelessWidget {
  const _LineRow({required this.line, required this.onRemove, required this.onChanged});
  final _Line line;
  final VoidCallback onRemove;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(border: Border.all(color: AppColors.border), borderRadius: BorderRadius.circular(10)),
    child: Row(
      children: [
        Expanded(
          flex: 3,
          child: Text(
            (line.product['name'] as String?) ?? '',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          flex: 2,
          child: TextField(
            controller: line.quantityCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => onChanged(),
            decoration: const InputDecoration(labelText: 'Qty', isDense: true),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          flex: 2,
          child: TextField(
            controller: line.unitCostCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => onChanged(),
            decoration: const InputDecoration(labelText: 'Cost', isDense: true),
          ),
        ),
        IconButton(
          icon: const Icon(Icons.close, size: 18, color: AppColors.textMuted),
          onPressed: onRemove,
        ),
      ],
    ),
  );
}
