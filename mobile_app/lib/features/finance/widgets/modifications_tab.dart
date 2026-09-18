import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../../dashboard/widgets/stat_tile.dart';
import '../../inventory/widgets/list_states.dart';
import '../../inventory/widgets/picker_sheet.dart';
import 'finance_common.dart';

const _kAssignmentTypes = [
  ('renovation', 'Renovation'),
  ('property', 'Property'),
  ('other', 'Other'),
];

const _kRenovationReferences = [
  'Painting', 'Plumbing', 'Electrical', 'Flooring', 'Interior', 'Exterior', 'General', 'Other',
];

const _kPropertyWorkTypes = [
  ('repair', 'Repair'),
  ('modification', 'Modification'),
  ('other', 'Other'),
];

/// Financial > Modifications — list, create and delete. No pay action exists
/// for modifications; Bills reference them for cost tracking instead.
class ModificationsTab extends StatefulWidget {
  const ModificationsTab({super.key});

  @override
  State<ModificationsTab> createState() => _ModificationsTabState();
}

class _ModificationsTabState extends State<ModificationsTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _modifications = [];
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
      final res = await ApiClient.instance.get(ApiEndpoints.financeModifications);
      final body = res.data;
      _modifications = parseListData(body);
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
      builder: (_) => const _AddModificationSheet(),
    );
    if (created == true) _load();
  }

  Future<void> _delete(Map<String, dynamic> modification) async {
    final confirmed = await confirmDelete(
      context,
      title: 'Delete modification',
      message: 'Delete "${modification['name']}"? This cannot be undone.',
    );
    if (!confirmed) return;
    try {
      await ApiClient.instance.delete(ApiEndpoints.financeModification((modification['id'] as num).toInt()));
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
                child: Text('Modifications', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
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
    if (_error != null && _modifications.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_modifications.isEmpty) return const EmptyState(message: 'No modifications yet.');

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        children: [
          StatGrid(
            tiles: [
              StatTile(
                label: 'Modifications',
                value: '${_summary['total_count'] ?? _modifications.length}',
                icon: Icons.construction_outlined,
              ),
              StatTile(
                label: 'Total cost',
                value: (_summary['total_cost_fmt'] as String?) ?? '0.00',
                icon: Icons.savings_outlined,
                color: AppColors.primary,
              ),
            ],
          ),
          const SizedBox(height: 14),
          for (final m in _modifications) ...[
            _ModificationCard(modification: m, onDelete: () => _delete(m)),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _ModificationCard extends StatelessWidget {
  const _ModificationCard({required this.modification, required this.onDelete});
  final Map<String, dynamic> modification;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final typeLabel = _kAssignmentTypes
        .firstWhere((t) => t.$1 == modification['assignment_type'], orElse: () => ('', 'Other'))
        .$2;
    final workTypeLabel = modification['work_type_label'] as String?;
    final billsCount = (modification['bills_count'] as num?)?.toInt() ?? 0;
    final assignmentDisplay = modification['assignment_display'] as String?;
    final duration = modification['duration'] as String?;
    final description = modification['description'] as String?;

    return FinanceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  (modification['name'] as String?) ?? '',
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
              FinanceBadge(label: typeLabel, color: AppColors.primary),
              if (workTypeLabel != null && workTypeLabel.isNotEmpty) FinanceBadge(label: workTypeLabel, color: AppColors.textMuted),
              if (billsCount > 0) FinanceBadge(label: '$billsCount bill${billsCount == 1 ? '' : 's'}', color: AppColors.warning),
            ],
          ),
          if (assignmentDisplay != null && assignmentDisplay.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(assignmentDisplay, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
          ],
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                duration != null && duration.isNotEmpty ? duration : '',
                style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
              Text(
                (modification['estimated_cost_fmt'] as String?) ?? formatMoney(modification['estimated_cost']),
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

class _AddModificationSheet extends StatefulWidget {
  const _AddModificationSheet();

  @override
  State<_AddModificationSheet> createState() => _AddModificationSheetState();
}

class _AddModificationSheetState extends State<_AddModificationSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _estimatedCostCtrl = TextEditingController();
  final _durationCtrl = TextEditingController();
  final _descriptionCtrl = TextEditingController();
  final _renovationOtherCtrl = TextEditingController();
  final _otherReferenceCtrl = TextEditingController();
  final _propertyWorkTypeOtherCtrl = TextEditingController();

  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;

  String _assignmentType = 'renovation';
  String _renovationReference = _kRenovationReferences.first;
  String _propertyWorkType = 'repair';
  int? _propertyId;
  List<Map<String, dynamic>> _properties = [];

  @override
  void initState() {
    super.initState();
    _loadProperties();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _estimatedCostCtrl.dispose();
    _durationCtrl.dispose();
    _descriptionCtrl.dispose();
    _renovationOtherCtrl.dispose();
    _otherReferenceCtrl.dispose();
    _propertyWorkTypeOtherCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadProperties() async {
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.financeBillAssignmentTargets);
      final data = ((res.data is Map ? res.data['data'] : null) as Map?)?.cast<String, dynamic>() ?? {};
      final list = data['properties'] as List? ?? [];
      _properties = list.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk) return;
    if (_assignmentType == 'renovation' && _renovationReference == 'Other' && _renovationOtherCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Describe the renovation.');
      return;
    }
    if (_assignmentType == 'property') {
      if (_propertyId == null) {
        setState(() => _error = 'Pick a property.');
        return;
      }
      if (_propertyWorkType == 'other' && _propertyWorkTypeOtherCtrl.text.trim().isEmpty) {
        setState(() => _error = 'Describe the work type.');
        return;
      }
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.financeModifications,
        data: {
          'name': _nameCtrl.text.trim(),
          'assignment_type': _assignmentType,
          'estimated_cost': double.tryParse(_estimatedCostCtrl.text.trim()) ?? 0,
          if (_durationCtrl.text.trim().isNotEmpty) 'duration': _durationCtrl.text.trim(),
          if (_descriptionCtrl.text.trim().isNotEmpty) 'description': _descriptionCtrl.text.trim(),
          if (_assignmentType == 'renovation')
            'assignment_reference': _renovationReference == 'Other' ? _renovationOtherCtrl.text.trim() : _renovationReference,
          if (_assignmentType == 'property') ...{
            'assignment_reference': _propertyId,
            'property_work_type': _propertyWorkType,
            if (_propertyWorkType == 'other') 'property_work_type_other': _propertyWorkTypeOtherCtrl.text.trim(),
          },
          if (_assignmentType == 'other' && _otherReferenceCtrl.text.trim().isNotEmpty)
            'assignment_reference': _otherReferenceCtrl.text.trim(),
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
    title: 'Add modification',
    loading: _loadingOptions,
    child: Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          TextFormField(
            controller: _nameCtrl,
            decoration: const InputDecoration(labelText: 'Modification name'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _assignmentType,
            decoration: const InputDecoration(labelText: 'Type'),
            items: [for (final t in _kAssignmentTypes) DropdownMenuItem(value: t.$1, child: Text(t.$2))],
            onChanged: (v) => setState(() => _assignmentType = v ?? _assignmentType),
          ),
          if (_assignmentType == 'renovation') ...[
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _renovationReference,
              decoration: const InputDecoration(labelText: 'Renovation area'),
              items: [for (final r in _kRenovationReferences) DropdownMenuItem(value: r, child: Text(r))],
              onChanged: (v) => setState(() => _renovationReference = v ?? _renovationReference),
            ),
            if (_renovationReference == 'Other') ...[
              const SizedBox(height: 14),
              TextFormField(controller: _renovationOtherCtrl, decoration: const InputDecoration(labelText: 'Describe the renovation')),
            ],
          ],
          if (_assignmentType == 'property') ...[
            const SizedBox(height: 14),
            DropdownButtonFormField<int>(
              initialValue: _propertyId,
              decoration: const InputDecoration(labelText: 'Property'),
              items: [
                for (final p in _properties)
                  DropdownMenuItem(value: (p['id'] as num).toInt(), child: Text(p['name'] as String? ?? '')),
              ],
              onChanged: (v) => setState(() => _propertyId = v),
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _propertyWorkType,
              decoration: const InputDecoration(labelText: 'Work type'),
              items: [for (final w in _kPropertyWorkTypes) DropdownMenuItem(value: w.$1, child: Text(w.$2))],
              onChanged: (v) => setState(() => _propertyWorkType = v ?? _propertyWorkType),
            ),
            if (_propertyWorkType == 'other') ...[
              const SizedBox(height: 14),
              TextFormField(controller: _propertyWorkTypeOtherCtrl, decoration: const InputDecoration(labelText: 'Describe the work type')),
            ],
          ],
          if (_assignmentType == 'other') ...[
            const SizedBox(height: 14),
            TextFormField(controller: _otherReferenceCtrl, decoration: const InputDecoration(labelText: 'Reference (optional)')),
          ],
          const SizedBox(height: 14),
          TextFormField(
            controller: _estimatedCostCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Estimated cost'),
            validator: (v) {
              final n = double.tryParse((v ?? '').trim());
              return (n == null || n < 0) ? 'Enter a valid amount' : null;
            },
          ),
          const SizedBox(height: 14),
          TextFormField(controller: _durationCtrl, decoration: const InputDecoration(labelText: 'Duration (optional)')),
          const SizedBox(height: 14),
          TextFormField(
            controller: _descriptionCtrl,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Description (optional)'),
          ),
          sheetError(_error),
          const SizedBox(height: 8),
          SheetSubmitButton(label: 'Add modification', saving: _saving, onPressed: _submit),
        ],
      ),
    ),
  );
}
