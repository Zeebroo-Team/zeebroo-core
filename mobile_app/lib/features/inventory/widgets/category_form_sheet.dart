import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'picker_sheet.dart';

/// Create/edit bottom sheet for a product category. Pops `true` on success.
class CategoryFormSheet extends StatefulWidget {
  const CategoryFormSheet({super.key, this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<CategoryFormSheet> createState() => _CategoryFormSheetState();
}

class _CategoryFormSheetState extends State<CategoryFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameCtrl = TextEditingController(text: widget.existing?['name'] as String? ?? '');
  late final _descriptionCtrl =
      TextEditingController(text: widget.existing?['description'] as String? ?? '');
  late bool _isActive = (widget.existing?['is_active'] as bool?) ?? true;
  Map<String, dynamic>? _parent;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final parentId = widget.existing?['parent_id'];
    final parentName = widget.existing?['parent_name'] as String?;
    if (parentId != null && parentName != null) {
      _parent = {'id': parentId, 'name': parentName};
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _descriptionCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickParent() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Parent category',
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        subtitleOf: (item) => item['label'] as String?,
        fetch: (q) async {
          final res = await ApiClient.instance.get(
            ApiEndpoints.categoryParentOptions,
            params: {if (widget.existing?['id'] != null) 'exclude': widget.existing!['id']},
          );
          final all = parseListData(res.data);
          if (q.isEmpty) return all;
          return all
              .where((c) => (c['name'] as String? ?? '').toLowerCase().contains(q.toLowerCase()))
              .toList();
        },
      ),
    );
    if (picked != null) {
      setState(() => _parent = picked.isEmpty ? null : picked.first);
    }
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
      'parent_id': _parent?['id'],
      'is_active': _isActive,
    };
    try {
      if (_isEdit) {
        await ApiClient.instance.patch(
          ApiEndpoints.category((widget.existing!['id'] as num).toInt()),
          data: data,
        );
      } else {
        await ApiClient.instance.post(ApiEndpoints.categories, data: data);
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
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
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
                  _isEdit ? 'Edit category' : 'Add category',
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: _nameCtrl,
                  decoration: const InputDecoration(labelText: 'Category name'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 14),
                InkWell(
                  onTap: _pickParent,
                  borderRadius: BorderRadius.circular(12),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Parent category (optional)'),
                    child: Text(
                      (_parent?['name'] as String?) ?? 'None',
                      style: TextStyle(
                        fontSize: 14,
                        color: _parent == null ? AppColors.textHint : AppColors.textDark,
                      ),
                    ),
                  ),
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
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                        )
                      : Text(_isEdit ? 'Save changes' : 'Add category'),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
