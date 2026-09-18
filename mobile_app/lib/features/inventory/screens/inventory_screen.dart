import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../widgets/barcodes_tab.dart';
import '../widgets/brands_tab.dart';
import '../widgets/categories_tab.dart';
import '../widgets/cheques_tab.dart';
import '../widgets/discounts_tab.dart';
import '../widgets/goods_receive_tab.dart';
import '../widgets/products_tab.dart';
import '../widgets/purchase_orders_tab.dart';
import '../widgets/stock_audits_tab.dart';
import '../widgets/stock_transfers_tab.dart';

// Overview is index 0; all other tabs are shifted by 1.
const _kTabLabels = [
  'Overview',
  'Products',
  'Purchase Orders',
  'Goods Receive',
  'Cheques',
  'Stock Audit',
  'Stock Transfer',
  'Categories',
  'Discounts',
  'Brands',
  'Barcodes',
];

const _kOverviewItems = [
  _OverviewItem('Products',        Icons.inventory_2_rounded,        Color(0xFF6366F1), 1),
  _OverviewItem('Purchase Orders', Icons.shopping_cart_rounded,       Color(0xFF0EA5E9), 2),
  _OverviewItem('Goods Receive',   Icons.local_shipping_rounded,      Color(0xFF10B981), 3),
  _OverviewItem('Cheques',         Icons.receipt_rounded,             Color(0xFFF59E0B), 4),
  _OverviewItem('Stock Audit',     Icons.fact_check_rounded,          Color(0xFFEF4444), 5),
  _OverviewItem('Stock Transfer',  Icons.swap_horiz_rounded,          Color(0xFF8B5CF6), 6),
  _OverviewItem('Categories',      Icons.category_rounded,            Color(0xFF06B6D4), 7),
  _OverviewItem('Discounts',       Icons.local_offer_rounded,         Color(0xFFEC4899), 8),
  _OverviewItem('Brands',          Icons.verified_rounded,            Color(0xFF84CC16), 9),
  _OverviewItem('Barcodes',        Icons.qr_code_scanner_rounded,     Color(0xFF64748B), 10),
];

class _OverviewItem {
  const _OverviewItem(this.label, this.icon, this.color, this.tabIndex);
  final String label;
  final IconData icon;
  final Color color;
  final int tabIndex;
}

/// Inventory — full CRUD across the same 10 areas as the Electron desktop
/// app's "Inventory" ribbon tab (`inv-subnav`): Products, Purchase Orders,
/// Goods Receive, Cheques, Stock Audit, Stock Transfer, Categories,
/// Discounts, Brands, Barcodes. There's no backend "inventory" feature key
/// — this is an always-available menu entry (not gated by the business's
/// enabled plan features), reached from the side drawer.
class InventoryScreen extends StatefulWidget {
  const InventoryScreen({super.key});

  @override
  State<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends State<InventoryScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController = TabController(length: _kTabLabels.length, vsync: this)
    ..addListener(() {
      if (mounted) setState(() {});
    });

  void _goToTab(int index) => _tabController.animateTo(index);

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Widget _pill(String label, int index) {
    final selected = _tabController.index == index;
    return GestureDetector(
      onTap: () => _tabController.animateTo(index),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? AppColors.primary : Colors.transparent, width: 1),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11.5,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            color: selected ? AppColors.primary : AppColors.textMuted,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      title: const Text(
        'Inventory',
        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
      ),
    ),
    body: Column(
      children: [
        Container(
          color: AppColors.surface,
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Align(
            alignment: Alignment.centerLeft,
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  for (var i = 0; i < _kTabLabels.length; i++) ...[
                    if (i > 0) const SizedBox(width: 4),
                    _pill(_kTabLabels[i], i),
                  ],
                ],
              ),
            ),
          ),
        ),
        const Divider(height: 1, color: AppColors.border),
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: [
              _InventoryOverview(onNavigate: _goToTab),
              const ProductsTab(),
              const PurchaseOrdersTab(),
              const GoodsReceiveTab(),
              const ChequesTab(),
              const StockAuditsTab(),
              const StockTransfersTab(),
              const CategoriesTab(),
              const DiscountsTab(),
              const BrandsTab(),
              const BarcodesTab(),
            ],
          ),
        ),
      ],
    ),
  );
}

// ─── Inventory Overview Tab ────────────────────────────────────────────────

class _InventoryOverview extends StatefulWidget {
  const _InventoryOverview({required this.onNavigate});
  final void Function(int tabIndex) onNavigate;

  @override
  State<_InventoryOverview> createState() => _InventoryOverviewState();
}

class _InventoryOverviewState extends State<_InventoryOverview>
    with SingleTickerProviderStateMixin, AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  late final AnimationController _ctrl = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..forward();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header banner
          _OverviewBanner(controller: _ctrl),
          const SizedBox(height: 24),
          const Text(
            'Quick Access',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: AppColors.textMuted,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 12),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 14,
              crossAxisSpacing: 14,
              childAspectRatio: 1.1,
            ),
            itemCount: _kOverviewItems.length,
            itemBuilder: (context, index) => _OverviewCard(
              item: _kOverviewItems[index],
              controller: _ctrl,
              delay: index * 0.07,
              onTap: () => widget.onNavigate(_kOverviewItems[index].tabIndex),
            ),
          ),
        ],
      ),
    );
  }
}

class _OverviewBanner extends StatelessWidget {
  const _OverviewBanner({required this.controller});
  final AnimationController controller;

  @override
  Widget build(BuildContext context) {
    final fade = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: controller, curve: const Interval(0, 0.5, curve: Curves.easeOut)),
    );
    final slide = Tween<Offset>(begin: const Offset(0, -0.2), end: Offset.zero).animate(
      CurvedAnimation(parent: controller, curve: const Interval(0, 0.5, curve: Curves.easeOut)),
    );
    return FadeTransition(
      opacity: fade,
      child: SlideTransition(
        position: slide,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF6366F1), Color(0xFF8B5CF6)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF6366F1).withOpacity(0.35),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(Icons.inventory_2_rounded, color: Colors.white, size: 28),
              ),
              const SizedBox(width: 16),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Inventory',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Colors.white),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Manage stock, orders & more',
                      style: TextStyle(fontSize: 12.5, color: Colors.white70),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OverviewCard extends StatefulWidget {
  const _OverviewCard({
    required this.item,
    required this.controller,
    required this.delay,
    required this.onTap,
  });
  final _OverviewItem item;
  final AnimationController controller;
  final double delay;
  final VoidCallback onTap;

  @override
  State<_OverviewCard> createState() => _OverviewCardState();
}

class _OverviewCardState extends State<_OverviewCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pressCtrl = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 120),
    lowerBound: 0,
    upperBound: 1,
  );

  late final Animation<double> _scale = Tween<double>(begin: 1.0, end: 0.93)
      .animate(CurvedAnimation(parent: _pressCtrl, curve: Curves.easeInOut));

  @override
  void dispose() {
    _pressCtrl.dispose();
    super.dispose();
  }

  Animation<double> get _entrance {
    final start = widget.delay.clamp(0.0, 0.85);
    final end = (widget.delay + 0.35).clamp(0.0, 1.0);
    return Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(
        parent: widget.controller,
        curve: Interval(start, end, curve: Curves.easeOutBack),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final color = widget.item.color;
    return ScaleTransition(
      scale: _entrance,
      child: ScaleTransition(
        scale: _scale,
        child: GestureDetector(
          onTapDown: (_) => _pressCtrl.forward(),
          onTapUp: (_) {
            _pressCtrl.reverse();
            widget.onTap();
          },
          onTapCancel: () => _pressCtrl.reverse(),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              boxShadow: [
                BoxShadow(
                  color: color.withOpacity(0.18),
                  blurRadius: 16,
                  offset: const Offset(0, 6),
                ),
                BoxShadow(
                  color: Colors.black.withOpacity(0.05),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [color, color.withOpacity(0.75)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: color.withOpacity(0.4),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Icon(widget.item.icon, color: Colors.white, size: 26),
                ),
                const SizedBox(height: 10),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Text(
                    widget.item.label,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textDark,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
