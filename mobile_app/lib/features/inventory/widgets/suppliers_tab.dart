import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'list_states.dart';
import 'picker_sheet.dart';
import 'status_chip.dart';
import 'supplier_form_sheet.dart';

const _kSupplierStatusFilters = [
  (label: 'All', value: null),
  (label: 'Active', value: '1'),
  (label: 'Inactive', value: '0'),
];

class SuppliersTab extends StatefulWidget {
  const SuppliersTab({super.key});

  @override
  State<SuppliersTab> createState() => _SuppliersTabState();
}

class _SuppliersTabState extends State<SuppliersTab>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final _searchController = TextEditingController();
  String? _activeFilter;
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];

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
      final res = await ApiClient.instance.get(
        ApiEndpoints.suppliers,
        params: {
          'q': _searchController.text.trim(),
          if (_activeFilter != null) 'active': _activeFilter,
        },
        bypassCache: forceRefresh,
      );
      _items = parseListData(res.data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm({Map<String, dynamic>? existing}) async {
    final saved = await showSupplierFormSheet(context, existing: existing);
    if (saved != null) await _load(forceRefresh: true);
  }

  Future<void> _delete(Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete supplier'),
        content: Text('Delete "${item['name']}" permanently?'),
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
        ApiEndpoints.supplier((item['id'] as num).toInt()),
      );
      await _load(forceRefresh: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
      }
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
              Expanded(
                child: TextField(
                  controller: _searchController,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _load(),
                  decoration: InputDecoration(
                    hintText: 'Search suppliers',
                    prefixIcon: const Icon(Icons.search_rounded, size: 20),
                    suffixIcon: IconButton(
                      icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                      onPressed: _load,
                    ),
                  ),
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
            itemCount: _kSupplierStatusFilters.length,
            separatorBuilder: (_, _) => const SizedBox(width: 8),
            itemBuilder: (context, index) {
              final filter = _kSupplierStatusFilters[index];
              final selected = _activeFilter == filter.value;
              return ChoiceChip(
                label: Text(filter.label),
                selected: selected,
                onSelected: (_) {
                  setState(() => _activeFilter = filter.value);
                  _load();
                },
              );
            },
          ),
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) {
      return ErrorState(error: _error!, onRetry: _load);
    }
    if (_items.isEmpty) {
      return const EmptyState(message: 'No suppliers found.');
    }

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, index) => _supplierCard(_items[index]),
      ),
    );
  }

  Widget _supplierCard(Map<String, dynamic> item) {
    final isActive = (item['is_active'] as bool?) ?? true;
    final contactName = item['contact_name'] as String?;
    final phone = item['phone'] as String?;
    final email = item['email'] as String?;
    final category = item['category_name'] as String?;
    final purchasesCount = (item['purchases_count'] as num?)?.toInt() ?? 0;
    final details = [
      if (contactName != null && contactName.isNotEmpty) contactName,
      if (phone != null && phone.isNotEmpty) phone,
      if (email != null && email.isNotEmpty) email,
    ].join(' · ');

    return InkWell(
      onTap: () => _openForm(existing: item),
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
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item['name'] as String? ?? '',
                    style: const TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textDark,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    [
                      '$purchasesCount purchase orders',
                      if (category != null && category.isNotEmpty) category,
                    ].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textMuted,
                    ),
                  ),
                  if (details.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      details,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            StatusChip(label: isActive ? 'Active' : 'Inactive'),
            IconButton(
              tooltip: 'Delete supplier',
              icon: const Icon(
                Icons.delete_outline,
                size: 20,
                color: AppColors.textMuted,
              ),
              onPressed: () => _delete(item),
            ),
          ],
        ),
      ),
    );
  }
}
