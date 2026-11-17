import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'customer_form_sheet.dart';
import 'list_states.dart';

const _kCustomerTypeFilters = [
  (label: 'All', value: null),
  (label: 'Retail', value: 'retail'),
  (label: 'Wholesale', value: 'wholesale'),
];

class CustomersTab extends StatefulWidget {
  const CustomersTab({super.key});

  @override
  State<CustomersTab> createState() => _CustomersTabState();
}

class _CustomersTabState extends State<CustomersTab>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final _searchController = TextEditingController();
  String? _typeFilter;
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
      final response = await ApiClient.instance.get(
        ApiEndpoints.customers,
        params: {
          'q': _searchController.text.trim(),
          if (_typeFilter != null) 'customer_type': _typeFilter,
        },
        bypassCache: forceRefresh || _searchController.text.trim().isNotEmpty,
      );
      final body = response.data;
      final rows = (body is Map ? body['data'] : body) as List? ?? [];
      _items = rows
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList();
    } catch (error) {
      _error = apiErrorMessage(error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm({Map<String, dynamic>? existing}) async {
    final saved = await showCustomerFormSheet(context, existing: existing);
    if (saved != null) await _load(forceRefresh: true);
  }

  Future<void> _delete(Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete customer'),
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
        ApiEndpoints.customer((item['id'] as num).toInt()),
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
                    hintText: 'Search customers',
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
            itemCount: _kCustomerTypeFilters.length,
            separatorBuilder: (_, _) => const SizedBox(width: 8),
            itemBuilder: (context, index) {
              final filter = _kCustomerTypeFilters[index];
              return ChoiceChip(
                label: Text(filter.label),
                selected: _typeFilter == filter.value,
                onSelected: (_) {
                  setState(() => _typeFilter = filter.value);
                  _load(forceRefresh: true);
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
      return const EmptyState(message: 'No customers found.');
    }

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, index) => _customerCard(_items[index]),
      ),
    );
  }

  Widget _customerCard(Map<String, dynamic> item) {
    final name = item['name']?.toString() ?? '';
    final phone = item['phone']?.toString().trim() ?? '';
    final email = item['email']?.toString().trim() ?? '';
    final category = item['category_name']?.toString().trim() ?? '';
    final salesCount = (item['sales_count'] as num?)?.toInt() ?? 0;
    final type = item['customer_type']?.toString() == 'wholesale'
        ? 'Wholesale'
        : 'Retail';
    final details = [
      if (phone.isNotEmpty) phone,
      if (email.isNotEmpty) email,
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
            CircleAvatar(
              radius: 20,
              backgroundColor: AppColors.primary.withValues(alpha: 0.1),
              child: Text(
                name.isEmpty ? '?' : name[0].toUpperCase(),
                style: const TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textDark,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    [
                      '$salesCount sales',
                      if (category.isNotEmpty) category,
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
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: type == 'Wholesale'
                    ? const Color(0xFFEDE9FE)
                    : const Color(0xFFEFF6FF),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                type,
                style: TextStyle(
                  color: type == 'Wholesale'
                      ? const Color(0xFF7C3AED)
                      : AppColors.primary,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            IconButton(
              tooltip: 'Delete customer',
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
