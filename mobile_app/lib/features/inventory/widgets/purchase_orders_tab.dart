import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../screens/purchase_order_detail_screen.dart';
import 'list_states.dart';
import 'picker_sheet.dart';
import 'purchase_order_form_sheet.dart';
import 'status_chip.dart';

const _kStatusFilters = [
  (label: 'All', value: 'all'),
  (label: 'Draft', value: 'draft'),
  (label: 'Ordered', value: 'ordered'),
  (label: 'Partially received', value: 'partially_received'),
  (label: 'Received', value: 'received'),
  (label: 'Cancelled', value: 'cancelled'),
];

class PurchaseOrdersTab extends StatefulWidget {
  const PurchaseOrdersTab({super.key});

  @override
  State<PurchaseOrdersTab> createState() => _PurchaseOrdersTabState();
}

class _PurchaseOrdersTabState extends State<PurchaseOrdersTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final _searchController = TextEditingController();
  String _statusFilter = 'all';
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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.purchaseOrders,
        params: {'q': _searchController.text.trim(), 'status': _statusFilter},
      );
      _items = parseListData(res.data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openCreate() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const PurchaseOrderFormSheet(),
    );
    if (created == true) _load();
  }

  Future<void> _openDetail(Map<String, dynamic> item) async {
    final id = (item['id'] as num?)?.toInt();
    if (id == null) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => PurchaseOrderDetailScreen(purchaseOrderId: id)),
    );
    if (changed == true) _load();
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
                    hintText: 'Search purchase orders',
                    prefixIcon: const Icon(Icons.search_rounded, size: 20),
                    suffixIcon: IconButton(icon: const Icon(Icons.arrow_forward_rounded, size: 18), onPressed: _load),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              AddButton(onTap: _openCreate),
            ],
          ),
        ),
        SizedBox(
          height: 52,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
            itemCount: _kStatusFilters.length,
            separatorBuilder: (_, _) => const SizedBox(width: 8),
            itemBuilder: (context, i) {
              final filter = _kStatusFilters[i];
              final selected = _statusFilter == filter.value;
              return ChoiceChip(
                label: Text(filter.label),
                selected: selected,
                onSelected: (_) {
                  setState(() => _statusFilter = filter.value);
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
    if (_error != null && _items.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_items.isEmpty) return const EmptyState(message: 'No purchase orders found.');
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final item = _items[i];
          return InkWell(
            onTap: () => _openDetail(item),
            borderRadius: BorderRadius.circular(14),
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 12, offset: Offset(0, 3))],
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          (item['po_number'] as String?) ?? '',
                          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${item['supplier_name'] ?? 'No supplier'} · ${formatDate(item['purchase_date'] as String?)}',
                          style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      StatusChip(label: (item['status_label'] as String?) ?? (item['status'] as String? ?? '')),
                      const SizedBox(height: 6),
                      Text(
                        ((item['total'] as num?) ?? 0).toStringAsFixed(2),
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5),
                      ),
                    ],
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
