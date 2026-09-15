import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';

// ─── Data model ──────────────────────────────────────────────────────────────
class SaleItem {
  final int id;
  final String saleNumber;
  final String status;
  final String? paymentMethod;
  final String? channel;
  final double total;
  final int itemsCount;
  final DateTime soldAt;
  final String? customerName;

  SaleItem({
    required this.id,
    required this.saleNumber,
    required this.status,
    this.paymentMethod,
    this.channel,
    required this.total,
    required this.itemsCount,
    required this.soldAt,
    this.customerName,
  });

  factory SaleItem.fromJson(Map<String, dynamic> j) => SaleItem(
    id:            j['id'] as int? ?? 0,
    saleNumber:    j['sale_number']?.toString() ?? '#—',
    status:        j['status']?.toString() ?? 'unknown',
    paymentMethod: j['payment_method']?.toString(),
    channel:       j['channel']?.toString(),
    total:         _toDouble(j['total']),
    itemsCount:    j['items_count'] as int? ?? 0,
    soldAt:        DateTime.tryParse(j['sold_at']?.toString() ?? '') ?? DateTime.now(),
    customerName:  j['customer_name']?.toString(),
  );

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }
}

// ─── Status helpers ───────────────────────────────────────────────────────────
extension _StatusExt on String {
  Color get statusColor {
    switch (toLowerCase()) {
      case 'completed': return const Color(0xFF16A34A);
      case 'pending':   return const Color(0xFFD97706);
      case 'refunded':  return const Color(0xFFDC2626);
      case 'void':      return const Color(0xFF6B7280);
      default:          return AppColors.primary;
    }
  }

  IconData get statusIcon {
    switch (toLowerCase()) {
      case 'completed': return Icons.check_circle_rounded;
      case 'pending':   return Icons.schedule_rounded;
      case 'refunded':  return Icons.undo_rounded;
      case 'void':      return Icons.block_rounded;
      default:          return Icons.receipt_long_rounded;
    }
  }

  String get statusLabel => isEmpty ? 'Unknown' :
      '${this[0].toUpperCase()}${substring(1).toLowerCase()}';
}

// ─── Screen ──────────────────────────────────────────────────────────────────
class SalesScreen extends StatefulWidget {
  const SalesScreen({super.key});

  @override
  State<SalesScreen> createState() => _SalesScreenState();
}

class _SalesScreenState extends State<SalesScreen> {
  final _scrollCtrl = ScrollController();
  final _searchCtrl = TextEditingController();

  List<SaleItem> _items  = [];
  bool _loading          = true;
  bool _loadingMore      = false;
  String? _error;
  int  _page             = 1;
  bool _hasMore          = true;
  String _statusFilter   = 'all';

  static const _perPage = 20;
  static const _statuses = ['all', 'completed', 'pending', 'refunded', 'void'];
  final _fmt = NumberFormat('#,##0.00');
  final _dateFmt = DateFormat('d MMM, h:mm a');

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
        !_loadingMore && _hasMore) {
      _load();
    }
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
      };
      if (_statusFilter != 'all') params['status'] = _statusFilter;
      if (_searchCtrl.text.trim().isNotEmpty) params['q'] = _searchCtrl.text.trim();

      final res = await ApiClient.instance.get(ApiEndpoints.sales, params: params);
      final body = res.data;
      List raw = [];
      if (body is Map) {
        raw = (body['data'] ?? body['items'] ?? []) as List;
        // pagination
        final meta = body['meta'] ?? body;
        final total = (meta['total'] ?? 0) as int;
        final pp    = (meta['per_page'] ?? _perPage) as int;
        final pg    = (meta['page'] ?? (reset ? 1 : _page)) as int;
        _hasMore = pg * pp < total;
        _page = pg + 1;
      } else if (body is List) {
        raw = body;
        _hasMore = raw.length == _perPage;
        _page = (reset ? 1 : _page) + 1;
      }

      final parsed = raw.map((e) => SaleItem.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      if (mounted) setState(() {
        if (reset) _items = parsed; else _items.addAll(parsed);
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
      if (mounted) setState(() {
        _error = 'Failed to load sales. Tap to retry.';
        _loading = false;
        _loadingMore = false;
      });
    }
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
            flexibleSpace: FlexibleSpaceBar(
              collapseMode: CollapseMode.pin,
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [AppColors.primary, Color(0xFF6366F1)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                padding: const EdgeInsets.fromLTRB(20, 56, 20, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    const Text('Sales', style: TextStyle(
                        fontSize: 26, fontWeight: FontWeight.w800, color: Colors.white)),
                    const SizedBox(height: 2),
                    Text(bizName, style: TextStyle(
                        fontSize: 13, color: Colors.white.withValues(alpha: 0.75))),
                  ],
                ),
              ),
              title: innerScrolled
                  ? const Text('Sales', style: TextStyle(
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
                  // Search field
                  TextField(
                    controller: _searchCtrl,
                    decoration: InputDecoration(
                      hintText: 'Search sales, customer…',
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
                  // Status filter chips
                  SizedBox(
                    height: 34,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: _statuses.map((s) {
                        final sel = _statusFilter == s;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: GestureDetector(
                            onTap: () {
                              if (_statusFilter == s) return;
                              setState(() => _statusFilter = s);
                              _load(reset: true);
                            },
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 180),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                              decoration: BoxDecoration(
                                color: sel ? AppColors.primary : Colors.transparent,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(
                                    color: sel ? AppColors.primary : AppColors.border,
                                    width: 1.5),
                              ),
                              child: Text(
                                s == 'all' ? 'All' : s.statusLabel,
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

            // ── List ──────────────────────────────────────────────────────
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
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        itemCount: _items.length + (_loadingMore ? 1 : 0),
        itemBuilder: (ctx, i) {
          if (i == _items.length) {
            return const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Center(child: CircularProgressIndicator(
                  strokeWidth: 2, color: AppColors.primary)),
            );
          }
          return _SaleTile(sale: _items[i], fmt: _fmt, dateFmt: _dateFmt);
        },
      ),
    );
  }
}

// ─── Sale tile ────────────────────────────────────────────────────────────────
class _SaleTile extends StatelessWidget {
  const _SaleTile({required this.sale, required this.fmt, required this.dateFmt});
  final SaleItem sale;
  final NumberFormat fmt;
  final DateFormat dateFmt;

  @override
  Widget build(BuildContext context) {
    final st = sale.status;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            // Status icon circle
            Container(
              width: 44, height: 44,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: st.statusColor.withValues(alpha: 0.12),
              ),
              child: Icon(st.statusIcon, color: st.statusColor, size: 20),
            ),
            const SizedBox(width: 12),
            // Details
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(sale.saleNumber,
                          style: const TextStyle(fontSize: 14,
                              fontWeight: FontWeight.w700, color: AppColors.textDark)),
                      const Spacer(),
                      Text('RM ${fmt.format(sale.total)}',
                          style: const TextStyle(fontSize: 15,
                              fontWeight: FontWeight.w800, color: AppColors.textDark)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      _Badge(label: st.statusLabel, color: st.statusColor),
                      const SizedBox(width: 6),
                      if (sale.paymentMethod != null)
                        _Badge(
                          label: sale.paymentMethod!.replaceAll('_', ' '),
                          color: AppColors.textMuted,
                        ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Icon(Icons.person_outline,
                          size: 13, color: AppColors.textMuted),
                      const SizedBox(width: 3),
                      Text(sale.customerName ?? 'Walk-in',
                          style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                      const Spacer(),
                      Icon(Icons.shopping_bag_outlined,
                          size: 13, color: AppColors.textMuted),
                      const SizedBox(width: 3),
                      Text('${sale.itemsCount} item${sale.itemsCount == 1 ? '' : 's'}',
                          style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                      const SizedBox(width: 8),
                      Icon(Icons.access_time_rounded,
                          size: 13, color: AppColors.textMuted),
                      const SizedBox(width: 3),
                      Text(dateFmt.format(sale.soldAt.toLocal()),
                          style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(6),
    ),
    child: Text(label,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: color)),
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
            color: AppColors.primary.withValues(alpha: 0.08),
          ),
          child: const Icon(Icons.receipt_long_outlined,
              size: 34, color: AppColors.primary),
        ),
        const SizedBox(height: 16),
        const Text('No sales found',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700,
                color: AppColors.textDark)),
        const SizedBox(height: 6),
        const Text('Sales will appear here once recorded.',
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
          Text(message,
              textAlign: TextAlign.center,
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
