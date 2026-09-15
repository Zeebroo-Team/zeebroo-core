import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';

// ─── Data model ──────────────────────────────────────────────────────────────
class ProductItem {
  final int id;
  final String name;
  final String? sku;
  final double unitPrice;
  final int stockQuantity;
  final String stockStatus;
  final String? categoryName;
  final String? imageUrl;

  ProductItem({
    required this.id,
    required this.name,
    this.sku,
    required this.unitPrice,
    required this.stockQuantity,
    required this.stockStatus,
    this.categoryName,
    this.imageUrl,
  });

  factory ProductItem.fromJson(Map<String, dynamic> j) {
    // categories may be a List or a single Map
    String? cat;
    final cats = j['categories'];
    if (cats is List && cats.isNotEmpty) {
      cat = (cats.first as Map?)?['name']?.toString();
    }
    final brand = j['brand'];
    if (cat == null && brand is Map) cat = brand['name']?.toString();

    // image
    String? img;
    final imgs = j['images'];
    if (imgs is List && imgs.isNotEmpty) {
      img = (imgs.first as Map?)?['url']?.toString();
    }
    if (img == null) img = j['image_url']?.toString() ?? j['photo']?.toString();

    return ProductItem(
      id:            j['id'] as int? ?? 0,
      name:          j['name']?.toString() ?? '—',
      sku:           j['sku']?.toString(),
      unitPrice:     _toDouble(j['unit_price'] ?? j['price']),
      stockQuantity: j['stock_quantity'] as int? ?? j['stock'] as int? ?? 0,
      stockStatus:   j['stock_status']?.toString() ?? 'in_stock',
      categoryName:  cat,
      imageUrl:      img,
    );
  }

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }
}

// ─── Stock status helpers ─────────────────────────────────────────────────────
extension _StockExt on String {
  Color get stockColor {
    switch (this) {
      case 'in_stock':    return const Color(0xFF16A34A);
      case 'low_stock':   return const Color(0xFFD97706);
      case 'out_of_stock': return const Color(0xFFDC2626);
      default:             return AppColors.textMuted;
    }
  }

  String get stockLabel {
    switch (this) {
      case 'in_stock':    return 'In Stock';
      case 'low_stock':   return 'Low Stock';
      case 'out_of_stock': return 'Out of Stock';
      default:             return this;
    }
  }
}

// ─── Screen ──────────────────────────────────────────────────────────────────
class InventoryScreen extends StatefulWidget {
  const InventoryScreen({super.key});

  @override
  State<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends State<InventoryScreen> {
  final _scrollCtrl = ScrollController();
  final _searchCtrl = TextEditingController();

  List<ProductItem> _items = [];
  bool _loading            = true;
  bool _loadingMore        = false;
  String? _error;
  int  _page               = 1;
  bool _hasMore            = true;
  String? _stockFilter;    // null = all
  String _sort             = 'name_asc';

  static const _perPage = 20;
  static const _stockStatuses = [
    _FilterChip(value: null,            label: 'All'),
    _FilterChip(value: 'in_stock',      label: 'In Stock'),
    _FilterChip(value: 'low_stock',     label: 'Low Stock'),
    _FilterChip(value: 'out_of_stock',  label: 'Out of Stock'),
  ];

  final _priceFmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _load(reset: true);
    _scrollCtrl.addListener(_onScroll);
    _searchCtrl.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200 &&
        !_loadingMore && _hasMore) _load();
  }

  String? _searchDebounce;
  void _onSearchChanged() {
    final q = _searchCtrl.text;
    if (q == _searchDebounce) return;
    _searchDebounce = q;
    Future.delayed(const Duration(milliseconds: 400), () {
      if (_searchCtrl.text == q && mounted) _load(reset: true);
    });
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() { _items = []; _page = 1; _hasMore = true; _loading = true; _error = null; });
    } else {
      if (_loadingMore || !_hasMore) return;
      setState(() => _loadingMore = true);
    }

    try {
      final params = <String, dynamic>{
        'per_page': _perPage,
        'page':     reset ? 1 : _page,
        'sort':     _sort,
      };
      if (_stockFilter != null) params['stock_status'] = _stockFilter;
      if (_searchCtrl.text.trim().isNotEmpty) params['q'] = _searchCtrl.text.trim();

      final res = await ApiClient.instance.get(ApiEndpoints.products, params: params);
      final body = res.data;
      List raw = [];
      int total = 0;
      int pp    = _perPage;
      int pg    = reset ? 1 : _page;

      if (body is Map) {
        raw = (body['data'] ?? body['items'] ?? []) as List;
        final meta = body['meta'] ?? {};
        if (meta is Map) {
          total = (meta['total'] ?? 0) as int;
          pp    = (meta['per_page'] ?? _perPage) as int;
          pg    = (meta['page'] ?? pg) as int;
        }
        _hasMore = total > 0 ? pg * pp < total : raw.length == _perPage;
        _page = pg + 1;
      } else if (body is List) {
        raw = body;
        _hasMore = raw.length == _perPage;
        _page = (reset ? 1 : _page) + 1;
      }

      final parsed = raw.map((e) => ProductItem.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      if (mounted) setState(() {
        if (reset) _items = parsed; else _items.addAll(parsed);
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
      if (mounted) setState(() {
        _error = 'Failed to load products. Tap to retry.';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  void _showSortSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) {
        const options = [
          ('name_asc',   'Name A → Z',    Icons.sort_by_alpha_rounded),
          ('name_desc',  'Name Z → A',    Icons.sort_by_alpha_rounded),
          ('price_asc',  'Price ↑ Low–High', Icons.attach_money_rounded),
          ('price_desc', 'Price ↓ High–Low', Icons.attach_money_rounded),
          ('stock_asc',  'Stock ↑',       Icons.inventory_2_outlined),
          ('stock_desc', 'Stock ↓',       Icons.inventory_2_outlined),
        ];
        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Sort By', style: TextStyle(fontSize: 17,
                  fontWeight: FontWeight.w700, color: AppColors.textDark)),
              const SizedBox(height: 12),
              ...options.map((o) {
                final sel = _sort == o.$1;
                return ListTile(
                  leading: Icon(o.$3,
                      color: sel ? AppColors.primary : AppColors.textMid, size: 20),
                  title: Text(o.$2, style: TextStyle(
                      fontWeight: sel ? FontWeight.w700 : FontWeight.w500,
                      color: sel ? AppColors.primary : AppColors.textDark)),
                  trailing: sel
                      ? const Icon(Icons.check_circle_rounded,
                          color: AppColors.primary, size: 18)
                      : null,
                  contentPadding: EdgeInsets.zero,
                  onTap: () {
                    Navigator.pop(context);
                    if (_sort == o.$1) return;
                    setState(() => _sort = o.$1);
                    _load(reset: true);
                  },
                );
              }),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthState>().user;
    final bizName = user?['business']?['name']?.toString()
        ?? user?['business_name']?.toString()
        ?? 'Your Business';

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: NestedScrollView(
        controller: _scrollCtrl,
        headerSliverBuilder: (ctx, innerScrolled) => [
          SliverAppBar(
            expandedHeight: 130,
            pinned: true,
            backgroundColor: Colors.transparent,
            elevation: 0,
            actions: [
              IconButton(
                icon: const Icon(Icons.sort_rounded, color: Colors.white),
                tooltip: 'Sort',
                onPressed: _showSortSheet,
              ),
            ],
            flexibleSpace: FlexibleSpaceBar(
              collapseMode: CollapseMode.pin,
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Color(0xFF0EA5E9), Color(0xFF6366F1)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                padding: const EdgeInsets.fromLTRB(20, 56, 60, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    const Text('Inventory', style: TextStyle(
                        fontSize: 26, fontWeight: FontWeight.w800, color: Colors.white)),
                    const SizedBox(height: 2),
                    Text(bizName, style: TextStyle(
                        fontSize: 13, color: Colors.white.withValues(alpha: 0.75))),
                  ],
                ),
              ),
              title: innerScrolled
                  ? const Text('Inventory', style: TextStyle(
                      fontSize: 17, fontWeight: FontWeight.w700, color: Colors.white))
                  : null,
            ),
          ),
        ],
        body: Column(
          children: [
            // ── Search + Filter bar ────────────────────────────────────────
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Column(
                children: [
                  TextField(
                    controller: _searchCtrl,
                    decoration: InputDecoration(
                      hintText: 'Search products, SKU…',
                      hintStyle: const TextStyle(fontSize: 14, color: AppColors.textHint),
                      prefixIcon: const Icon(Icons.search, size: 20, color: AppColors.textHint),
                      suffixIcon: _searchCtrl.text.isNotEmpty
                          ? IconButton(
                              icon: const Icon(Icons.clear, size: 18),
                              onPressed: () { _searchCtrl.clear(); _load(reset: true); })
                          : null,
                      filled: true,
                      fillColor: AppColors.surface,
                      border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide.none),
                      contentPadding: const EdgeInsets.symmetric(vertical: 10),
                    ),
                  ),
                  const SizedBox(height: 10),
                  // Stock status filter chips
                  SizedBox(
                    height: 34,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: _stockStatuses.map((chip) {
                        final sel = _stockFilter == chip.value;
                        final col = chip.value == null
                            ? AppColors.primary
                            : chip.value!.stockColor;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: GestureDetector(
                            onTap: () {
                              if (_stockFilter == chip.value) return;
                              setState(() => _stockFilter = chip.value);
                              _load(reset: true);
                            },
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 180),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                              decoration: BoxDecoration(
                                color: sel ? col : Colors.transparent,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(
                                    color: sel ? col : AppColors.border, width: 1.5),
                              ),
                              child: Text(chip.label,
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: sel ? Colors.white : AppColors.textMid,
                                ),
                              ),
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
              ),
            ),

            // ── Grid / List ────────────────────────────────────────────────
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    if (_error != null) return _ErrorView(message: _error!, onRetry: () => _load(reset: true));
    if (_items.isEmpty) return const _EmptyView();

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () => _load(reset: true),
      child: GridView.builder(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 0.78,
        ),
        itemCount: _items.length + (_loadingMore ? 2 : 0),
        itemBuilder: (ctx, i) {
          if (i >= _items.length) {
            return const _ShimmerCard();
          }
          return _ProductCard(product: _items[i], priceFmt: _priceFmt);
        },
      ),
    );
  }
}

// ─── Product card ─────────────────────────────────────────────────────────────
class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product, required this.priceFmt});
  final ProductItem product;
  final NumberFormat priceFmt;

  @override
  Widget build(BuildContext context) {
    final ss = product.stockStatus;
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Image / Placeholder
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
            child: AspectRatio(
              aspectRatio: 1.3,
              child: product.imageUrl != null
                  ? Image.network(
                      product.imageUrl!,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _productPlaceholder(ss),
                    )
                  : _productPlaceholder(ss),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (product.categoryName != null) ...[
                    Text(product.categoryName!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 10, color: AppColors.textMuted,
                            fontWeight: FontWeight.w500)),
                    const SizedBox(height: 2),
                  ],
                  Text(product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700,
                          color: AppColors.textDark, height: 1.3)),
                  const Spacer(),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Text('RM ${priceFmt.format(product.unitPrice)}',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800,
                                color: AppColors.primary)),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: ss.stockColor.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          product.stockQuantity.toString(),
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700,
                              color: ss.stockColor),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: ss.stockColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(ss.stockLabel,
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600,
                            color: ss.stockColor)),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _productPlaceholder(String stockStatus) => Container(
    color: AppColors.surface,
    child: Center(
      child: Icon(Icons.inventory_2_outlined,
          size: 36, color: stockStatus.stockColor.withValues(alpha: 0.4)),
    ),
  );
}

// ─── Shimmer loading card ─────────────────────────────────────────────────────
class _ShimmerCard extends StatelessWidget {
  const _ShimmerCard();

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Column(
      children: [
        AspectRatio(
          aspectRatio: 1.3,
          child: ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
            child: Container(color: AppColors.surface),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(height: 10, width: 60, color: AppColors.surface,
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(4))),
              const SizedBox(height: 6),
              Container(height: 12, color: AppColors.surface,
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(4))),
            ],
          ),
        ),
      ],
    ),
  );
}

// ─── Empty / Error ────────────────────────────────────────────────────────────
class _EmptyView extends StatelessWidget {
  const _EmptyView();

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          width: 72, height: 72,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: const Color(0xFF0EA5E9).withValues(alpha: 0.08),
          ),
          child: const Icon(Icons.inventory_2_outlined, size: 34,
              color: Color(0xFF0EA5E9)),
        ),
        const SizedBox(height: 16),
        const Text('No products found',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700,
                color: AppColors.textDark)),
        const SizedBox(height: 6),
        const Text('Add products to your inventory to see them here.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
      ],
    ),
  );
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48, color: AppColors.textMuted),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 14, color: AppColors.textMuted)),
          const SizedBox(height: 20),
          ElevatedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh, size: 16),
            label: const Text('Retry'),
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
          ),
        ],
      ),
    ),
  );
}

// ─── Helper ───────────────────────────────────────────────────────────────────
class _FilterChip {
  const _FilterChip({required this.value, required this.label});
  final String? value;
  final String label;
}
