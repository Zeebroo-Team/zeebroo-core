import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';

/// Create/edit bottom sheet for a product brand. Pops `true` on success.
class BrandFormSheet extends StatefulWidget {
  const BrandFormSheet({super.key, this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<BrandFormSheet> createState() => _BrandFormSheetState();
}

class _BrandFormSheetState extends State<BrandFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameCtrl = TextEditingController(text: widget.existing?['name'] as String? ?? '');
  late final _descriptionCtrl = TextEditingController(text: widget.existing?['description'] as String? ?? '');
  late final _websiteCtrl = TextEditingController(text: widget.existing?['website'] as String? ?? '');
  late bool _isActive = (widget.existing?['is_active'] as bool?) ?? true;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void dispose() {
    _nameCtrl.dispose();
    _descriptionCtrl.dispose();
    _websiteCtrl.dispose();
    super.dispose();
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
      'description': _descriptionCtrl.text.trim(),
      'website': _websiteCtrl.text.trim(),
      'is_active': _isActive,
    };
    try {
      if (_isEdit) {
        await ApiClient.instance.patch(ApiEndpoints.brand((widget.existing!['id'] as num).toInt()), data: data);
      } else {
        await ApiClient.instance.post(ApiEndpoints.brands, data: data);
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
                  _isEdit ? 'Edit brand' : 'Add brand',
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: _nameCtrl,
                  decoration: const InputDecoration(labelText: 'Brand name'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _websiteCtrl,
                  keyboardType: TextInputType.url,
                  decoration: const InputDecoration(labelText: 'Website (optional)'),
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
                      : Text(_isEdit ? 'Save changes' : 'Add brand'),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
