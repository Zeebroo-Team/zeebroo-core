import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../screens/product_detail_screen.dart';
import 'picker_sheet.dart';
import 'product_form_sheet.dart';

const _kStatusFilters = [
  (label: 'All', value: null),
  (label: 'In stock', value: 'in_stock'),
  (label: 'Low stock', value: 'low_stock'),
  (label: 'Out of stock', value: 'out_of_stock'),
];

class ProductsTab extends StatefulWidget {
  const ProductsTab({super.key});

  @override
  State<ProductsTab> createState() => _ProductsTabState();
}

class _ProductsTabState extends State<ProductsTab>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final _searchController = TextEditingController();
  String? _statusFilter;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  List<Map<String, dynamic>> _products = [];
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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _page = 1;
    });
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.products,
        params: {
          'q': _searchController.text.trim(),
          if (_statusFilter != null) 'stock_status': _statusFilter,
          'page': 1,
          'per_page': 40,
        },
      );
      final body = res.data;
      final meta = (body is Map ? body['meta'] : null) as Map?;
      _products = parseListData(body);
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
        ApiEndpoints.products,
        params: {
          'q': _searchController.text.trim(),
          if (_statusFilter != null) 'stock_status': _statusFilter,
          'page': nextPage,
          'per_page': 40,
        },
      );
      setState(() {
        _products = [..._products, ...parseListData(res.data)];
        _page = nextPage;
      });
    } catch (_) {
      // Best-effort — the user can just tap "Load more" again.
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openCreate() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const ProductFormSheet(),
    );
    if (created == true) _load();
  }

  Future<void> _openDetail(Map<String, dynamic> product) async {
    final id = (product['id'] as num?)?.toInt();
    if (id == null) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ProductDetailScreen(productId: id)),
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
                    hintText: 'Search products',
                    prefixIcon: const Icon(Icons.search_rounded, size: 20),
                    suffixIcon: IconButton(
                      icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                      onPressed: _load,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              _AddButton(onTap: _openCreate),
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
    if (_error != null && _products.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 10),
              TextButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh, size: 16),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }
    if (_products.isEmpty) {
      return const Center(
        child: Text(
          'No products found.',
          style: TextStyle(color: AppColors.textMuted, fontSize: 13),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _products.length + (_page < _lastPage ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          if (i >= _products.length) {
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Center(
                child: _loadingMore
                    ? const CircularProgressIndicator(strokeWidth: 2.4)
                    : TextButton(
                        onPressed: _loadMore,
                        child: const Text('Load more'),
                      ),
              ),
            );
          }
          return _ProductRow(
            product: _products[i],
            onTap: () => _openDetail(_products[i]),
          );
        },
      ),
    );
  }
}

class _AddButton extends StatelessWidget {
  const _AddButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Container(
      height: 50,
      width: 50,
      decoration: BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Icon(Icons.add_rounded, color: Colors.white),
    ),
  );
}

class _ProductRow extends StatelessWidget {
  const _ProductRow({required this.product, required this.onTap});
  final Map<String, dynamic> product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final name = (product['name'] as String?) ?? '';
    final sku = (product['sku'] as String?) ?? '';
    final stock = (product['stock_quantity'] as num?)?.toDouble() ?? 0;
    final price = (product['unit_sell_price'] as num?)?.toDouble() ?? 0;
    final unit = (product['unit'] as String?) ?? '';
    final outOfStock = stock <= 0;

    return InkWell(
      onTap: onTap,
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
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textDark,
                    ),
                  ),
                  if (sku.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      sku,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '${stock.toStringAsFixed(stock == stock.roundToDouble() ? 0 : 1)} ${unit.isNotEmpty ? unit : ''}'
                      .trim(),
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: outOfStock ? AppColors.error : AppColors.textDark,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  price.toStringAsFixed(2),
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
            const SizedBox(width: 4),
            const Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: AppColors.textHint,
            ),
          ],
        ),
      ),
    );
  }
}
