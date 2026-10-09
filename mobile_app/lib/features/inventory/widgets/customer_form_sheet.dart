import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/contact_autofill_button.dart';
import 'picker_sheet.dart';

Future<Map<String, dynamic>?> showCustomerFormSheet(
  BuildContext context, {
  Map<String, dynamic>? existing,
}) => showModalBottomSheet<Map<String, dynamic>>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  backgroundColor: Colors.transparent,
  builder: (_) => CustomerFormSheet(existing: existing),
);

class CustomerFormSheet extends StatefulWidget {
  const CustomerFormSheet({super.key, this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<CustomerFormSheet> createState() => _CustomerFormSheetState();
}

class _CustomerFormSheetState extends State<CustomerFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameController = TextEditingController(
    text: widget.existing?['name']?.toString() ?? '',
  );
  late final _phoneController = TextEditingController(
    text: widget.existing?['phone']?.toString() ?? '',
  );
  late final _emailController = TextEditingController(
    text: widget.existing?['email']?.toString() ?? '',
  );
  late final _addressController = TextEditingController(
    text: widget.existing?['address']?.toString() ?? '',
  );
  late final _notesController = TextEditingController(
    text: widget.existing?['notes']?.toString() ?? '',
  );

  List<Map<String, dynamic>> _categories = [];
  late int? _categoryId = (widget.existing?['customer_category_id'] as num?)
      ?.toInt();
  late String _customerType =
      widget.existing?['customer_type']?.toString() == 'wholesale'
      ? 'wholesale'
      : 'retail';
  bool _loadingCategories = true;
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
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    try {
      final response = await ApiClient.instance.get(
        ApiEndpoints.customerCategories,
        bypassCache: true,
      );
      if (mounted) {
        setState(() => _categories = parseListData(response.data));
      }
    } catch (_) {
      // Category is optional; the customer can still be saved.
    } finally {
      if (mounted) setState(() => _loadingCategories = false);
    }
  }

  String? _optional(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  String? _validateEmail(String? value) {
    final email = value?.trim() ?? '';
    if (email.isEmpty) return null;
    return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)
        ? null
        : 'Enter a valid email';
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final data = {
        'name': _nameController.text.trim(),
        'phone': _optional(_phoneController.text),
        'email': _optional(_emailController.text),
        'address': _optional(_addressController.text),
        'notes': _optional(_notesController.text),
        'customer_category_id': _categoryId,
        'customer_type': _customerType,
      };
      final response = _isEdit
          ? await ApiClient.instance.patch(
              ApiEndpoints.customer((widget.existing!['id'] as num).toInt()),
              data: data,
            )
          : await ApiClient.instance.post(ApiEndpoints.customers, data: data);
      final raw = response.data;
      final customer = raw is Map ? raw['data'] : null;
      if (customer is! Map) throw Exception('Unexpected customer response.');
      if (!mounted) return;
      Navigator.pop(context, Map<String, dynamic>.from(customer));
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = apiErrorMessage(error);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.92,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: AppColors.border,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 8, 16, 4),
            child: Row(
              children: [
                IconButton(
                  onPressed: _saving ? null : () => Navigator.pop(context),
                  tooltip: 'Back',
                  icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
                ),
                Expanded(
                  child: Text(
                    _isEdit ? 'Edit customer' : 'Add customer',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textDark,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: Form(
              key: _formKey,
              child: ListView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                children: [
                  TextFormField(
                    controller: _nameController,
                    autofocus: true,
                    textCapitalization: TextCapitalization.words,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: 'Customer name *',
                      suffixIcon: ContactAutofillButton.maybe(
                        name: _nameController,
                        phone: _phoneController,
                        email: _emailController,
                        address: _addressController,
                        enabled: !_saving,
                      ),
                    ),
                    validator: (value) => value == null || value.trim().isEmpty
                        ? 'Customer name is required'
                        : null,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Mobile number (optional)',
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Email (optional)',
                    ),
                    validator: _validateEmail,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _addressController,
                    maxLines: 2,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: const InputDecoration(
                      labelText: 'Address (optional)',
                    ),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<int?>(
                    initialValue: _categoryId,
                    decoration: InputDecoration(
                      labelText: 'Category (optional)',
                      suffixIcon: _loadingCategories
                          ? const Padding(
                              padding: EdgeInsets.all(14),
                              child: SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              ),
                            )
                          : null,
                    ),
                    items: [
                      const DropdownMenuItem<int?>(
                        value: null,
                        child: Text('None'),
                      ),
                      for (final category in _categories)
                        DropdownMenuItem<int?>(
                          value: (category['id'] as num).toInt(),
                          child: Text(category['name']?.toString() ?? ''),
                        ),
                    ],
                    onChanged: _loadingCategories
                        ? null
                        : (value) => setState(() => _categoryId = value),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Customer type',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 8),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(
                        value: 'retail',
                        label: Text('Retail'),
                        icon: Icon(Icons.person_outline_rounded),
                      ),
                      ButtonSegment(
                        value: 'wholesale',
                        label: Text('Wholesale'),
                        icon: Icon(Icons.storefront_outlined),
                      ),
                    ],
                    selected: {_customerType},
                    onSelectionChanged: (selection) =>
                        setState(() => _customerType = selection.first),
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _notesController,
                    maxLines: 3,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: const InputDecoration(
                      labelText: 'Notes (optional)',
                    ),
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
                          : Text(_isEdit ? 'Save changes' : 'Add customer'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
