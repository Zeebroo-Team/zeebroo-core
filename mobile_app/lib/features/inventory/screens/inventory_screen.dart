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

const _kTabLabels = [
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
            children: const [
              ProductsTab(),
              PurchaseOrdersTab(),
              GoodsReceiveTab(),
              ChequesTab(),
              StockAuditsTab(),
              StockTransfersTab(),
              CategoriesTab(),
              DiscountsTab(),
              BrandsTab(),
              BarcodesTab(),
            ],
          ),
        ),
      ],
    ),
  );
}
