import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';

Future<bool?> showUnitFormSheet(
  BuildContext context, {
  Map<String, dynamic>? existing,
}) => showModalBottomSheet<bool>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  backgroundColor: Colors.transparent,
  builder: (_) => UnitFormSheet(existing: existing),
);

class UnitFormSheet extends StatefulWidget {
  const UnitFormSheet({super.key, this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<UnitFormSheet> createState() => _UnitFormSheetState();
}

class _UnitFormSheetState extends State<UnitFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameController = TextEditingController(
    text: widget.existing?['name']?.toString() ?? '',
  );
  late final _abbreviationController = TextEditingController(
    text: widget.existing?['abbreviation']?.toString() ?? '',
  );
  late bool _isActive = (widget.existing?['is_active'] as bool?) ?? true;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void dispose() {
    _nameController.dispose();
    _abbreviationController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });

    final abbreviation = _abbreviationController.text.trim();
    final data = {
      'name': _nameController.text.trim(),
      'abbreviation': abbreviation.isEmpty ? null : abbreviation,
      'is_active': _isActive,
    };

    try {
      if (_isEdit) {
        await ApiClient.instance.patch(
          ApiEndpoints.unit((widget.existing!['id'] as num).toInt()),
          data: data,
        );
      } else {
        await ApiClient.instance.post(ApiEndpoints.units, data: data);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
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
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        _isEdit ? 'Edit Unit' : 'New Unit',
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textDark,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: _saving ? null : () => Navigator.pop(context),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _nameController,
                  autofocus: true,
                  textCapitalization: TextCapitalization.words,
                  textInputAction: TextInputAction.next,
                  maxLength: 80,
                  decoration: const InputDecoration(labelText: 'Name *'),
                  validator: (value) => value == null || value.trim().isEmpty
                      ? 'Unit name is required'
                      : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _abbreviationController,
                  textCapitalization: TextCapitalization.characters,
                  maxLength: 20,
                  decoration: const InputDecoration(
                    labelText: 'Abbreviation (optional)',
                    hintText: 'e.g. kg, pcs, L',
                  ),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text(
                    'Active',
                    style: TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  subtitle: const Text(
                    'Active units are available when creating products.',
                    style: TextStyle(fontSize: 12),
                  ),
                  value: _isActive,
                  activeThumbColor: AppColors.primary,
                  onChanged: _saving
                      ? null
                      : (value) => setState(() => _isActive = value),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
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
                        : Text(_isEdit ? 'Save changes' : 'Add Unit'),
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
