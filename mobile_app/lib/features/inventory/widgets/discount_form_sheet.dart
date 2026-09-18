import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import 'picker_sheet.dart';

/// Create/edit bottom sheet for a product discount. Pops `true` on success.
class DiscountFormSheet extends StatefulWidget {
  const DiscountFormSheet({super.key, this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<DiscountFormSheet> createState() => _DiscountFormSheetState();
}

class _DiscountFormSheetState extends State<DiscountFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameCtrl = TextEditingController(text: widget.existing?['name'] as String? ?? '');
  late final _valueCtrl = TextEditingController(
    text: widget.existing?['discount_value'] != null ? '${widget.existing!['discount_value']}' : '',
  );
  late String _type = (widget.existing?['discount_type'] as String?) ?? 'percentage';
  Map<String, dynamic>? _product;
  List<Map<String, dynamic>> _sellingUnits = [];
  int? _sellingUnitId;
  DateTime? _startsAt;
  DateTime? _endsAt;
  late bool _isActive = (widget.existing?['is_active'] as bool?) ?? true;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    if (e != null) {
      _product = {'id': e['product_id'], 'name': e['product_name']};
      _sellingUnitId = (e['product_selling_unit_id'] as num?)?.toInt();
      _startsAt = DateTime.tryParse(e['starts_at'] as String? ?? '');
      _endsAt = DateTime.tryParse(e['ends_at'] as String? ?? '');
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _valueCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickProduct() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Product',
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        subtitleOf: (item) => (item['selling_price'] as num?)?.toStringAsFixed(2),
        fetch: (q) async {
          final res = await ApiClient.instance.get(
            ApiEndpoints.discountProductOptions,
            params: {if (q.isNotEmpty) 'q': q},
          );
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) {
      setState(() {
        _product = picked.first;
        _sellingUnits = (picked.first['selling_units'] as List? ?? [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
        _sellingUnitId = null;
      });
    }
  }

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk || _product == null) {
      setState(() => _error = _product == null ? 'Select a product.' : _error);
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final data = {
      'name': _nameCtrl.text.trim(),
      'product_id': _product!['id'],
      if (_sellingUnitId != null) 'product_selling_unit_id': _sellingUnitId,
      'discount_type': _type,
      'discount_value': double.tryParse(_valueCtrl.text.trim()) ?? 0,
      if (_startsAt != null) 'starts_at': toApiDate(_startsAt!),
      if (_endsAt != null) 'ends_at': toApiDate(_endsAt!),
      'is_active': _isActive,
    };
    try {
      if (_isEdit) {
        await ApiClient.instance.patch(ApiEndpoints.discount((widget.existing!['id'] as num).toInt()), data: data);
      } else {
        await ApiClient.instance.post(ApiEndpoints.discounts, data: data);
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
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
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
                    decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  _isEdit ? 'Edit discount' : 'Add discount',
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: _nameCtrl,
                  decoration: const InputDecoration(labelText: 'Discount name'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 14),
                InkWell(
                  onTap: _pickProduct,
                  borderRadius: BorderRadius.circular(12),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Product'),
                    child: Text(
                      (_product?['name'] as String?) ?? 'Tap to select',
                      style: TextStyle(fontSize: 14, color: _product == null ? AppColors.textHint : AppColors.textDark),
                    ),
                  ),
                ),
                if (_sellingUnits.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  DropdownButtonFormField<int?>(
                    initialValue: _sellingUnitId,
                    decoration: const InputDecoration(labelText: 'Selling unit (optional)'),
                    items: [
                      const DropdownMenuItem(value: null, child: Text('Default')),
                      for (final u in _sellingUnits)
                        DropdownMenuItem(value: (u['id'] as num).toInt(), child: Text((u['label'] as String?) ?? '')),
                    ],
                    onChanged: (v) => setState(() => _sellingUnitId = v),
                  ),
                ],
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'percentage', label: Text('%')),
                          ButtonSegment(value: 'flat', label: Text('Flat')),
                        ],
                        selected: {_type},
                        onSelectionChanged: (s) => setState(() => _type = s.first),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _valueCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Value'),
                        validator: (v) {
                          final n = double.tryParse((v ?? '').trim());
                          if (n == null || n <= 0) return 'Required';
                          return null;
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _DateField(
                        label: 'Starts at (optional)',
                        value: _startsAt,
                        onTap: () async {
                          final d = await pickDate(context, initial: _startsAt);
                          if (d != null) setState(() => _startsAt = d);
                        },
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _DateField(
                        label: 'Ends at (optional)',
                        value: _endsAt,
                        onTap: () async {
                          final d = await pickDate(context, initial: _endsAt);
                          if (d != null) setState(() => _endsAt = d);
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Active', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
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
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                      : Text(_isEdit ? 'Save changes' : 'Add discount'),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

class _DateField extends StatelessWidget {
  const _DateField({required this.label, required this.value, required this.onTap});
  final String label;
  final DateTime? value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: InputDecorator(
      decoration: InputDecoration(labelText: label),
      child: Text(
        value == null ? 'None' : formatDate(value!.toIso8601String()),
        style: TextStyle(fontSize: 14, color: value == null ? AppColors.textHint : AppColors.textDark),
      ),
    ),
  );
}
