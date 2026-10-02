import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'picker_sheet.dart';

Future<Map<String, dynamic>?> showSupplierFormSheet(
  BuildContext context, {
  Map<String, dynamic>? existing,
}) => showModalBottomSheet<Map<String, dynamic>>(
  context: context,
  isScrollControlled: true,
  backgroundColor: Colors.transparent,
  builder: (_) => SupplierFormSheet(existing: existing),
);

class SupplierFormSheet extends StatefulWidget {
  const SupplierFormSheet({super.key, this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<SupplierFormSheet> createState() => _SupplierFormSheetState();
}

class _SupplierFormSheetState extends State<SupplierFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameCtrl = TextEditingController(
    text: widget.existing?['name'] as String? ?? '',
  );
  late final _contactCtrl = TextEditingController(
    text: widget.existing?['contact_name'] as String? ?? '',
  );
  late final _phoneCtrl = TextEditingController(
    text: widget.existing?['phone'] as String? ?? '',
  );
  late final _emailCtrl = TextEditingController(
    text: widget.existing?['email'] as String? ?? '',
  );
  late final _addressCtrl = TextEditingController(
    text: widget.existing?['address'] as String? ?? '',
  );
  late final _notesCtrl = TextEditingController(
    text: widget.existing?['notes'] as String? ?? '',
  );

  List<Map<String, dynamic>> _categories = [];
  late int? _categoryId = (widget.existing?['supplier_category_id'] as num?)
      ?.toInt();
  late bool _isActive = (widget.existing?['is_active'] as bool?) ?? true;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _contactCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _addressCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.supplierCategories);
      if (mounted) setState(() => _categories = parseListData(res.data));
    } catch (_) {
      // Categories are optional; supplier creation remains available.
    }
  }

  String? _optional(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final data = {
        'name': _nameCtrl.text.trim(),
        'contact_name': _optional(_contactCtrl.text),
        'phone': _optional(_phoneCtrl.text),
        'email': _optional(_emailCtrl.text),
        'address': _optional(_addressCtrl.text),
        'notes': _optional(_notesCtrl.text),
        'supplier_category_id': _categoryId,
        'is_active': _isActive,
      };
      final res = _isEdit
          ? await ApiClient.instance.patch(
              ApiEndpoints.supplier((widget.existing!['id'] as num).toInt()),
              data: data,
            )
          : await ApiClient.instance.post(ApiEndpoints.suppliers, data: data);
      final raw = res.data;
      final supplier = raw is Map ? raw['data'] : null;
      if (supplier is! Map) throw Exception('Unexpected supplier response.');
      if (!mounted) return;
      Navigator.pop(context, Map<String, dynamic>.from(supplier));
    } catch (e) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = apiErrorMessage(e);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
    child: Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.9,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
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
                const SizedBox(height: 12),
                Row(
                  children: [
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      tooltip: 'Back',
                      icon: const Icon(
                        Icons.arrow_back_ios_new_rounded,
                        size: 20,
                      ),
                    ),
                    Expanded(
                      child: Text(
                        _isEdit ? 'Edit supplier' : 'Add supplier',
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textDark,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _nameCtrl,
                  autofocus: true,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(labelText: 'Supplier name'),
                  validator: (value) => value == null || value.trim().isEmpty
                      ? 'Supplier name is required'
                      : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _contactCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Contact name (optional)',
                  ),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _phoneCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Phone (optional)',
                  ),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Email (optional)',
                  ),
                  validator: (value) {
                    final email = value?.trim() ?? '';
                    if (email.isEmpty) return null;
                    return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)
                        ? null
                        : 'Enter a valid email';
                  },
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _addressCtrl,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Address (optional)',
                  ),
                ),
                if (_categories.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  DropdownButtonFormField<int?>(
                    initialValue: _categoryId,
                    decoration: const InputDecoration(
                      labelText: 'Category (optional)',
                    ),
                    items: [
                      const DropdownMenuItem<int?>(
                        value: null,
                        child: Text('None'),
                      ),
                      for (final category in _categories)
                        DropdownMenuItem<int?>(
                          value: (category['id'] as num).toInt(),
                          child: Text(category['name'] as String? ?? ''),
                        ),
                    ],
                    onChanged: (value) => setState(() => _categoryId = value),
                  ),
                ],
                const SizedBox(height: 14),
                TextFormField(
                  controller: _notesCtrl,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Notes (optional)',
                  ),
                ),
                const SizedBox(height: 10),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text(
                    'Active',
                    style: TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  value: _isActive,
                  activeThumbColor: AppColors.primary,
                  onChanged: (value) => setState(() => _isActive = value),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: const TextStyle(
                      color: AppColors.error,
                      fontSize: 12.5,
                    ),
                  ),
                ],
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _saving ? null : _save,
                    child: _saving
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.4,
                              color: Colors.white,
                            ),
                          )
                        : Text(_isEdit ? 'Save changes' : 'Add supplier'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
