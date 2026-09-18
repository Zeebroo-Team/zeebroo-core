import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../../../core/utils/money.dart';
import '../../dashboard/widgets/stat_tile.dart';
import '../../inventory/widgets/list_states.dart';
import '../../inventory/widgets/picker_sheet.dart';
import 'finance_common.dart';

const _kPropertyTypes = [
  ('building', 'Building'),
  ('land', 'Land'),
  ('office', 'Office'),
  ('shop', 'Shop'),
  ('warehouse', 'Warehouse'),
  ('vehicle', 'Vehicle'),
  ('machinery', 'Machinery'),
  ('furniture', 'Furniture'),
  ('electronics', 'Electronics'),
  ('accessories', 'Accessories'),
  ('landing', 'Landing'),
  ('other', 'Other'),
];

/// Financial > Properties — list, create and delete a business asset.
/// No pay/detail action exists for properties on the desktop app either.
class PropertiesTab extends StatefulWidget {
  const PropertiesTab({super.key});

  @override
  State<PropertiesTab> createState() => _PropertiesTabState();
}

class _PropertiesTabState extends State<PropertiesTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _properties = [];
  Map<String, dynamic> _summary = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.financeProperties);
      final body = res.data;
      _properties = parseListData(body);
      _summary = body is Map ? Map<String, dynamic>.from(body) : {};
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openAdd() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _AddPropertySheet(),
    );
    if (created == true) _load();
  }

  Future<void> _delete(Map<String, dynamic> property) async {
    final confirmed = await confirmDelete(
      context,
      title: 'Delete property',
      message: 'Delete "${property['property_name']}"? This cannot be undone.',
    );
    if (!confirmed) return;
    try {
      await ApiClient.instance.delete(ApiEndpoints.financeProperty((property['id'] as num).toInt()));
      _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
          child: Row(
            children: [
              const Expanded(
                child: Text('Properties', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
              ),
              AddButton(onTap: _openAdd),
            ],
          ),
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _properties.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_properties.isEmpty) return const EmptyState(message: 'No properties yet.');

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        children: [
          StatGrid(
            tiles: [
              StatTile(label: 'Properties', value: '${_summary['total_count'] ?? _properties.length}', icon: Icons.home_work_outlined),
              StatTile(
                label: 'Expiring soon',
                value: '${_summary['expiring_count'] ?? 0}',
                icon: Icons.timelapse_outlined,
                color: AppColors.warning,
              ),
              StatTile(
                label: 'Total value',
                value: (_summary['total_cost_fmt'] as String?) ?? '0.00',
                icon: Icons.savings_outlined,
                color: AppColors.primary,
              ),
            ],
          ),
          const SizedBox(height: 14),
          for (final p in _properties) ...[
            _PropertyCard(property: p, onDelete: () => _delete(p)),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _PropertyCard extends StatelessWidget {
  const _PropertyCard({required this.property, required this.onDelete});
  final Map<String, dynamic> property;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final expired = property['expired'] as bool? ?? false;
    final expiringSoon = property['expiring_soon'] as bool? ?? false;
    final hasExpiry = property['has_expiry'] as bool? ?? false;
    final description = property['description'] as String?;

    return FinanceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  (property['property_name'] as String?) ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.delete_outline, size: 20, color: AppColors.textMuted),
                onPressed: onDelete,
              ),
            ],
          ),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              FinanceBadge(label: (property['property_type'] as String?) ?? '', color: AppColors.textMuted),
              if (expired) const FinanceBadge(label: 'EXPIRED', color: AppColors.error),
              if (!expired && expiringSoon) const FinanceBadge(label: 'EXPIRING SOON', color: AppColors.warning),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              if (hasExpiry)
                Text(
                  'Expires ${(property['expire_date_fmt'] as String?) ?? formatDate(property['expire_date'] as String?)}',
                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                )
              else
                const SizedBox.shrink(),
              Text(
                (property['cost_fmt'] as String?) ?? formatMoney(property['cost']),
                style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
            ],
          ),
          if (description != null && description.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(description, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
          ],
        ],
      ),
    );
  }
}

class _AddPropertySheet extends StatefulWidget {
  const _AddPropertySheet();

  @override
  State<_AddPropertySheet> createState() => _AddPropertySheetState();
}

class _AddPropertySheetState extends State<_AddPropertySheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _typeOtherCtrl = TextEditingController();
  final _costCtrl = TextEditingController();
  final _descriptionCtrl = TextEditingController();

  bool _saving = false;
  String? _error;
  String _propertyType = 'building';
  bool _hasExpiry = false;
  DateTime? _expireDate;

  @override
  void dispose() {
    _nameCtrl.dispose();
    _typeOtherCtrl.dispose();
    _costCtrl.dispose();
    _descriptionCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk) return;
    if (_propertyType == 'other' && _typeOtherCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Enter the custom property type.');
      return;
    }
    if (_hasExpiry && _expireDate == null) {
      setState(() => _error = 'Pick an expiry date.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.financeProperties,
        data: {
          'property_name': _nameCtrl.text.trim(),
          'property_type': _propertyType,
          if (_propertyType == 'other') 'property_type_other': _typeOtherCtrl.text.trim(),
          'cost': double.tryParse(_costCtrl.text.trim()) ?? 0,
          if (_descriptionCtrl.text.trim().isNotEmpty) 'description': _descriptionCtrl.text.trim(),
          'has_expiry': _hasExpiry,
          if (_hasExpiry && _expireDate != null) 'expire_date': toApiDate(_expireDate!),
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
  Widget build(BuildContext context) => FormSheetShell(
    title: 'Add property',
    child: Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          TextFormField(
            controller: _nameCtrl,
            decoration: const InputDecoration(labelText: 'Property name'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _propertyType,
            decoration: const InputDecoration(labelText: 'Property type'),
            items: [for (final t in _kPropertyTypes) DropdownMenuItem(value: t.$1, child: Text(t.$2))],
            onChanged: (v) => setState(() => _propertyType = v ?? _propertyType),
          ),
          if (_propertyType == 'other') ...[
            const SizedBox(height: 14),
            TextFormField(controller: _typeOtherCtrl, decoration: const InputDecoration(labelText: 'Custom type')),
          ],
          const SizedBox(height: 14),
          TextFormField(
            controller: _costCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Cost / value'),
            validator: (v) {
              final n = double.tryParse((v ?? '').trim());
              return (n == null || n < 0) ? 'Enter a valid amount' : null;
            },
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _descriptionCtrl,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Description (optional)'),
          ),
          const SizedBox(height: 6),
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            title: const Text('Has an expiry date', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
            value: _hasExpiry,
            activeThumbColor: AppColors.primary,
            onChanged: (v) => setState(() => _hasExpiry = v),
          ),
          if (_hasExpiry) ...[
            const SizedBox(height: 8),
            FinanceDateField(label: 'Expiry date', value: _expireDate, onPick: (d) => setState(() => _expireDate = d)),
          ],
          sheetError(_error),
          const SizedBox(height: 8),
          SheetSubmitButton(label: 'Add property', saving: _saving, onPressed: _submit),
        ],
      ),
    ),
  );
}
