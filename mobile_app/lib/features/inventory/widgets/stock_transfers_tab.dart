import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../screens/stock_transfer_detail_screen.dart';
import 'list_states.dart';
import 'picker_sheet.dart';
import 'status_chip.dart';
import 'stock_transfer_form_sheet.dart';

class StockTransfersTab extends StatefulWidget {
  const StockTransfersTab({super.key});

  @override
  State<StockTransfersTab> createState() => _StockTransfersTabState();
}

class _StockTransfersTabState extends State<StockTransfersTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final _searchController = TextEditingController();
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  List<Map<String, dynamic>> _items = [];
  int _page = 1;
  int _lastPage = 1;

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
      _page = 1;
    });
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.stockTransfers,
        params: {'q': _searchController.text.trim(), 'page': 1},
        bypassCache: forceRefresh,
      );
      final body = res.data;
      final meta = (body is Map ? body['meta'] : null) as Map?;
      _items = parseListData(body);
      _lastPage = (meta?['last_page'] as num?)?.toInt() ?? 1;
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _page >= _lastPage) return;
    setState(() => _loadingMore = true);
    try {
      final nextPage = _page + 1;
      final res = await ApiClient.instance.get(
        ApiEndpoints.stockTransfers,
        params: {'q': _searchController.text.trim(), 'page': nextPage},
      );
      setState(() {
        _items = [..._items, ...parseListData(res.data)];
        _page = nextPage;
      });
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openCreate() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const StockTransferFormSheet(),
    );
    if (created == true) _load();
  }

  Future<void> _openDetail(Map<String, dynamic> item) async {
    final id = (item['id'] as num?)?.toInt();
    if (id == null) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => StockTransferDetailScreen(transferId: id)),
    );
    if (changed == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchController,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _load(),
                  decoration: InputDecoration(
                    hintText: 'Search transfers',
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
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_items.isEmpty) return const EmptyState(message: 'No stock transfers found.');
    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _items.length + (_page < _lastPage ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          if (i >= _items.length) {
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Center(
                child: _loadingMore
                    ? const CircularProgressIndicator(strokeWidth: 2.4)
                    : TextButton(onPressed: _loadMore, child: const Text('Load more')),
              ),
            );
          }
          final item = _items[i];
          final status = (item['status'] as String?) ?? '';
          final from = (item['from_branch'] as Map?)?['name'] ?? item['from_branch_id'];
          final to = (item['to_branch'] as Map?)?['name'] ?? item['to_branch_id'];
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
                          (item['transfer_number'] as String?) ?? '',
                          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '$from → $to · ${item['lines_count'] ?? 0} items',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                        ),
                      ],
                    ),
                  ),
                  StatusChip(label: status),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
