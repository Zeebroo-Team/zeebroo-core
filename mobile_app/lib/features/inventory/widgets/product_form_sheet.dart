import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'picker_sheet.dart';

/// Create/edit bottom sheet for a product. Pass [existing] (the full
/// `GET product(id)` payload) to edit; omit it to create a new product.
/// Pops `true` on success so the caller refreshes its list.
class ProductFormSheet extends StatefulWidget {
  const ProductFormSheet({super.key, this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<ProductFormSheet> createState() => _ProductFormSheetState();
}

class _ProductFormSheetState extends State<ProductFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameCtrl = TextEditingController(
    text: widget.existing?['name'] as String? ?? '',
  );
  late final _skuCtrl = TextEditingController(
    text: widget.existing?['sku'] as String? ?? '',
  );
  late final _unitPriceCtrl = TextEditingController(
    text: _numToText(widget.existing?['unit_price'] ?? widget.existing?['unit_sell_price']),
  );
  late final _costPriceCtrl = TextEditingController(
    text: _numToText(widget.existing?['cost_price']),
  );
  late final _wholesalePriceCtrl = TextEditingController(
    text: _numToText(widget.existing?['wholesale_price']),
  );
  late final _stockQtyCtrl = TextEditingController(
    text: _numToText(widget.existing?['stock_quantity']),
  );
  late final _descriptionCtrl = TextEditingController(
    text: widget.existing?['description'] as String? ?? '',
  );

  bool _loadingUnits = true;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _units = [];
  int? _unitId;
  List<Map<String, dynamic>> _selectedCategories = [];
  List<Map<String, dynamic>> _selectedBrands = [];
  late bool _isActive = (widget.existing?['is_active'] as bool?) ?? true;

  bool get _isEdit => widget.existing != null;

  static String _numToText(dynamic v) {
    if (v == null) return '';
    final n = num.tryParse('$v');
    return n == null ? '' : (n == n.roundToDouble() ? n.toInt().toString() : n.toString());
  }

  @override
  void initState() {
    super.initState();
    _unitId = (widget.existing?['product_unit_id'] as num?)?.toInt();
    _loadUnits();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _skuCtrl.dispose();
    _unitPriceCtrl.dispose();
    _costPriceCtrl.dispose();
    _wholesalePriceCtrl.dispose();
    _stockQtyCtrl.dispose();
    _descriptionCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadUnits() async {
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.units);
      _units = parseListData(res.data);
    } catch (_) {
      // Non-fatal — unit stays unset if this fails.
    } finally {
      if (mounted) setState(() => _loadingUnits = false);
    }
  }

  Future<void> _pickCategories() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Categories',
        multi: true,
        initialSelectedIds: _selectedCategories.map((c) => c['id']).toList(),
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        fetch: (q) async {
          final res = await ApiClient.instance.get(
            ApiEndpoints.categories,
            params: {if (q.isNotEmpty) 'q': q, 'per_page': 100},
          );
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null) setState(() => _selectedCategories = picked);
  }

  Future<void> _pickBrands() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Brands',
        multi: true,
        initialSelectedIds: _selectedBrands.map((b) => b['id']).toList(),
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        fetch: (q) async {
          final res = await ApiClient.instance.get(
            ApiEndpoints.brands,
            params: {if (q.isNotEmpty) 'q': q},
          );
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null) setState(() => _selectedBrands = picked);
  }

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk) return;

    setState(() {
      _saving = true;
      _error = null;
    });
    final data = {
      'name': _nameCtrl.text.trim(),
      if (_skuCtrl.text.trim().isNotEmpty) 'sku': _skuCtrl.text.trim(),
      'unit_price': double.tryParse(_unitPriceCtrl.text.trim()) ?? 0,
      if (_costPriceCtrl.text.trim().isNotEmpty)
        'cost_price': double.tryParse(_costPriceCtrl.text.trim()),
      if (_wholesalePriceCtrl.text.trim().isNotEmpty)
        'wholesale_price': double.tryParse(_wholesalePriceCtrl.text.trim()),
      if (!_isEdit && _stockQtyCtrl.text.trim().isNotEmpty)
        'stock_quantity': double.tryParse(_stockQtyCtrl.text.trim()) ?? 0,
      if (_unitId != null) 'product_unit_id': _unitId,
      'description': _descriptionCtrl.text.trim(),
      'is_active': _isActive,
      'product_category_ids': _selectedCategories.map((c) => c['id']).toList(),
      'product_brand_ids': _selectedBrands.map((b) => b['id']).toList(),
    };
    try {
      if (_isEdit) {
        final id = (widget.existing!['id'] as num).toInt();
        await ApiClient.instance.patch(ApiEndpoints.product(id), data: data);
      } else {
        await ApiClient.instance.post(ApiEndpoints.products, data: data);
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
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.9),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SafeArea(
        top: false,
        child: _loadingUnits
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
                      Text(
                        _isEdit ? 'Edit product' : 'Add product',
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textDark,
                        ),
                      ),
                      const SizedBox(height: 18),
                      TextFormField(
                        controller: _nameCtrl,
                        decoration: const InputDecoration(labelText: 'Product name'),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: _skuCtrl,
                        decoration: const InputDecoration(labelText: 'SKU (optional)'),
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _unitPriceCtrl,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: const InputDecoration(labelText: 'Selling price'),
                              validator: (v) {
                                final n = double.tryParse((v ?? '').trim());
                                if (n == null || n < 0) return 'Required';
                                return null;
                              },
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextFormField(
                              controller: _costPriceCtrl,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: const InputDecoration(labelText: 'Cost price'),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _wholesalePriceCtrl,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: const InputDecoration(labelText: 'Wholesale price'),
                            ),
                          ),
                          if (!_isEdit) ...[
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _stockQtyCtrl,
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                decoration: const InputDecoration(labelText: 'Opening stock'),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<int?>(
                        initialValue: _unitId,
                        decoration: const InputDecoration(labelText: 'Unit (optional)'),
                        items: [
                          const DropdownMenuItem(value: null, child: Text('—')),
                          for (final u in _units)
                            DropdownMenuItem(
                              value: (u['id'] as num).toInt(),
                              child: Text((u['name'] as String?) ?? ''),
                            ),
                        ],
                        onChanged: (v) => setState(() => _unitId = v),
                      ),
                      const SizedBox(height: 14),
                      _PickerField(
                        label: 'Categories',
                        value: _selectedCategories.map((c) => c['name'] as String? ?? '').join(', '),
                        onTap: _pickCategories,
                      ),
                      const SizedBox(height: 14),
                      _PickerField(
                        label: 'Brands',
                        value: _selectedBrands.map((b) => b['name'] as String? ?? '').join(', '),
                        onTap: _pickBrands,
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: _descriptionCtrl,
                        maxLines: 3,
                        decoration: const InputDecoration(labelText: 'Description (optional)'),
                      ),
                      const SizedBox(height: 10),
                      SwitchListTile.adaptive(
                        contentPadding: EdgeInsets.zero,
                        title: const Text(
                          'Active',
                          style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600),
                        ),
                        value: _isActive,
                        activeThumbColor: AppColors.primary,
                        onChanged: (v) => setState(() => _isActive = v),
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 8),
                        Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
                      ],
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _saving ? null : _submit,
                        child: _saving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                              )
                            : Text(_isEdit ? 'Save changes' : 'Add product'),
                      ),
                    ],
                  ),
                ),
              ),
      ),
    ),
  );
}

class _PickerField extends StatelessWidget {
  const _PickerField({required this.label, required this.value, required this.onTap});
  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: InputDecorator(
      decoration: InputDecoration(labelText: label),
      child: Text(
        value.isEmpty ? 'Tap to select' : value,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          fontSize: 14,
          color: value.isEmpty ? AppColors.textHint : AppColors.textDark,
        ),
      ),
    ),
  );
}
