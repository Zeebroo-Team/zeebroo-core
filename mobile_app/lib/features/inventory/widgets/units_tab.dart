import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'list_states.dart';
import 'picker_sheet.dart';
import 'status_chip.dart';
import 'unit_form_sheet.dart';

const _kUnitStatusFilters = [
  (label: 'All', value: null),
  (label: 'Active', value: true),
  (label: 'Inactive', value: false),
];

class UnitsTab extends StatefulWidget {
  const UnitsTab({super.key});

  @override
  State<UnitsTab> createState() => _UnitsTabState();
}

class _UnitsTabState extends State<UnitsTab> {
  final _searchController = TextEditingController();
  bool? _activeFilter;
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];

  List<Map<String, dynamic>> get _visibleItems {
    final query = _searchController.text.trim().toLowerCase();
    return _items.where((item) {
      final active = (item['is_active'] as bool?) ?? true;
      if (_activeFilter != null && active != _activeFilter) return false;
      if (query.isEmpty) return true;
      final name = item['name']?.toString().toLowerCase() ?? '';
      final abbreviation = item['abbreviation']?.toString().toLowerCase() ?? '';
      return name.contains(query) || abbreviation.contains(query);
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await ApiClient.instance.get(
        ApiEndpoints.units,
        bypassCache: forceRefresh,
      );
      _items = parseListData(response.data);
    } catch (error) {
      _error = apiErrorMessage(error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm({Map<String, dynamic>? existing}) async {
    final saved = await showUnitFormSheet(context, existing: existing);
    if (saved == true) await _load(forceRefresh: true);
  }

  Future<void> _delete(Map<String, dynamic> unit) async {
    final productsCount = (unit['products_count'] as num?)?.toInt() ?? 0;
    if (productsCount > 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'This unit is assigned to $productsCount product${productsCount == 1 ? '' : 's'} and cannot be deleted.',
          ),
        ),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete unit'),
        content: Text('Delete "${unit['name']}" permanently?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text(
              'Delete',
              style: TextStyle(color: AppColors.error),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ApiClient.instance.delete(
        ApiEndpoints.unit((unit['id'] as num).toInt()),
      );
      await _load(forceRefresh: true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiErrorMessage(error))));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _searchController,
                decoration: const InputDecoration(
                  hintText: 'Search units',
                  prefixIcon: Icon(Icons.search_rounded, size: 20),
                ),
                onChanged: (_) => setState(() {}),
              ),
            ),
            const SizedBox(width: 10),
            AddButton(onTap: () => _openForm()),
          ],
        ),
      ),
      SizedBox(
        height: 52,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
          itemCount: _kUnitStatusFilters.length,
          separatorBuilder: (_, _) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final filter = _kUnitStatusFilters[index];
            return ChoiceChip(
              label: Text(filter.label),
              selected: _activeFilter == filter.value,
              onSelected: (_) => setState(() => _activeFilter = filter.value),
            );
          },
        ),
      ),
      Expanded(child: _buildBody()),
    ],
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) {
      return ErrorState(error: _error!, onRetry: _load);
    }
    final items = _visibleItems;
    if (items.isEmpty) return const EmptyState(message: 'No units found.');

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final unit = items[index];
          final active = (unit['is_active'] as bool?) ?? true;
          final abbreviation = unit['abbreviation']?.toString().trim() ?? '';
          final productsCount = (unit['products_count'] as num?)?.toInt() ?? 0;
          return InkWell(
            onTap: () => _openForm(existing: unit),
            borderRadius: BorderRadius.circular(14),
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                boxShadow: const [
                  BoxShadow(
                    color: AppColors.shadow,
                    blurRadius: 12,
                    offset: Offset(0, 3),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3E8FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.straighten_rounded,
                      color: Color(0xFF9333EA),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          unit['name']?.toString() ?? '',
                          style: const TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textDark,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          [
                            if (abbreviation.isNotEmpty) abbreviation,
                            '$productsCount products',
                          ].join(' · '),
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  StatusChip(label: active ? 'Active' : 'Inactive'),
                  IconButton(
                    tooltip: 'Delete unit',
                    onPressed: () => _delete(unit),
                    icon: const Icon(
                      Icons.delete_outline,
                      size: 20,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
