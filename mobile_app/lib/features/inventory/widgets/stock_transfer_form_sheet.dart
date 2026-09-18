import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'picker_sheet.dart';

class _Line {
  _Line({required this.product}) : quantityCtrl = TextEditingController(text: '1');
  final Map<String, dynamic> product;
  final TextEditingController quantityCtrl;
}

/// Create sheet for a stock transfer between branches. Pops `true` on success.
class StockTransferFormSheet extends StatefulWidget {
  const StockTransferFormSheet({super.key});

  @override
  State<StockTransferFormSheet> createState() => _StockTransferFormSheetState();
}

class _StockTransferFormSheetState extends State<StockTransferFormSheet> {
  Map<String, dynamic>? _fromBranch;
  Map<String, dynamic>? _toBranch;
  final _notesCtrl = TextEditingController();
  final List<_Line> _lines = [];
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _notesCtrl.dispose();
    for (final l in _lines) {
      l.quantityCtrl.dispose();
    }
    super.dispose();
  }

  Future<void> _pickBranch(bool isFrom) async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: isFrom ? 'From branch' : 'To branch',
        searchable: false,
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        fetch: (_) async {
          final res = await ApiClient.instance.get(ApiEndpoints.branches);
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) {
      setState(() {
        if (isFrom) {
          _fromBranch = picked.first;
        } else {
          _toBranch = picked.first;
        }
      });
    }
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
          final res = await ApiClient.instance.get(ApiEndpoints.products, params: {'q': q, 'per_page': 30});
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
    });
  }

  Future<void> _submit() async {
    if (_fromBranch == null || _toBranch == null) {
      setState(() => _error = 'Select both branches.');
      return;
    }
    if (_fromBranch!['id'] == _toBranch!['id']) {
      setState(() => _error = 'From and to branches must differ.');
      return;
    }
    if (_lines.isEmpty) {
      setState(() => _error = 'Add at least one product.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.stockTransfers,
        data: {
          'from_branch_id': _fromBranch!['id'],
          'to_branch_id': _toBranch!['id'],
          if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
          'lines': [
            for (final l in _lines)
              {'product_id': l.product['id'], 'quantity': double.tryParse(l.quantityCtrl.text) ?? 0},
          ],
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
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.9),
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
                'New stock transfer',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () => _pickBranch(true),
                      borderRadius: BorderRadius.circular(12),
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'From branch'),
                        child: Text(
                          (_fromBranch?['name'] as String?) ?? 'Select',
                          style: TextStyle(fontSize: 14, color: _fromBranch == null ? AppColors.textHint : AppColors.textDark),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: InkWell(
                      onTap: () => _pickBranch(false),
                      borderRadius: BorderRadius.circular(12),
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'To branch'),
                        child: Text(
                          (_toBranch?['name'] as String?) ?? 'Select',
                          style: TextStyle(fontSize: 14, color: _toBranch == null ? AppColors.textHint : AppColors.textDark),
                        ),
                      ),
                    ),
                  ),
                ],
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
                  TextButton.icon(onPressed: _addLine, icon: const Icon(Icons.add, size: 16), label: const Text('Add product')),
                ],
              ),
              for (var i = 0; i < _lines.length; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(border: Border.all(color: AppColors.border), borderRadius: BorderRadius.circular(10)),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            (_lines[i].product['name'] as String?) ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                        ),
                        const SizedBox(width: 8),
                        SizedBox(
                          width: 80,
                          child: TextField(
                            controller: _lines[i].quantityCtrl,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            decoration: const InputDecoration(labelText: 'Qty', isDense: true),
                          ),
                        ),
                        IconButton(icon: const Icon(Icons.close, size: 18, color: AppColors.textMuted), onPressed: () => _removeLine(i)),
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
                    : const Text('Create transfer'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
