import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../finance/widgets/bills_tab.dart';
import '../../inventory/screens/inventory_screen.dart';
import '../../pos/screens/pos_screen.dart';
import '../widgets/account_overview_tab.dart';
import '../widgets/expenses_tab.dart';
import '../widgets/profit_tab.dart';
import '../widgets/quick_actions_row.dart';
import '../widgets/today_sales_overview.dart';

const _kTrack = Color(0xFFF3F4F6);

/// The "Home" tab content inside [HomeShell] — a pill-style segmented control
/// over three sub-tabs covering account balances, expenses and profit. The
/// Overview tab opens with a compact balance row, today's sales and quick
/// actions. The shell owns the header (greeting/avatar) and bottom navigation
/// chrome.
class HomeContent extends StatefulWidget {
  const HomeContent({super.key});

  @override
  State<HomeContent> createState() => _HomeContentState();
}

class _HomeContentState extends State<HomeContent> with SingleTickerProviderStateMixin {
  static const _tabs = [
    (label: 'Overview', icon: Icons.dashboard_rounded),
    (label: 'Expenses', icon: Icons.receipt_long_rounded),
    (label: 'Profit', icon: Icons.trending_up_rounded),
  ];

  late final TabController _tabController = TabController(length: _tabs.length, vsync: this);
  final _todayKey = GlobalKey<TodaySalesOverviewState>();
  final _expensesRefresh = ValueNotifier<int>(0);

  @override
  void dispose() {
    _tabController.dispose();
    _expensesRefresh.dispose();
    super.dispose();
  }

  Future<void> _newSale() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PosScreen()));
    if (mounted) _todayKey.currentState?.reload();
  }

  Future<void> _addBill() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const AddBillSheet(),
    );
    if (created != true || !mounted) return;
    _expensesRefresh.value++;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Bill added'),
        action: SnackBarAction(label: 'View', onPressed: () => _tabController.animateTo(1)),
      ),
    );
  }

  void _openInventory() => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const InventoryScreen()));

  @override
  Widget build(BuildContext context) => Column(
    children: [
      _PillTabBar(controller: _tabController, tabs: _tabs),
      Expanded(
        child: TabBarView(
          controller: _tabController,
          children: [
            AccountOverviewTab(
              onPullRefresh: () => _todayKey.currentState?.reload(force: true),
              belowBalance: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TodaySalesOverview(key: _todayKey),
                  const SizedBox(height: 20),
                  QuickActionsRow(
                    actions: [
                      QuickAction(label: 'New sale', icon: Icons.point_of_sale_rounded, color: AppColors.primary, primary: true, onTap: _newSale),
                      QuickAction(label: 'Add bill', icon: Icons.receipt_long_rounded, color: const Color(0xFF6366F1), onTap: _addBill),
                      QuickAction(label: 'Inventory', icon: Icons.inventory_2_rounded, color: const Color(0xFF10B981), onTap: _openInventory),
                      QuickAction(label: 'Reports', icon: Icons.bar_chart_rounded, color: AppColors.warning, onTap: () => _tabController.animateTo(2)),
                    ],
                  ),
                ],
              ),
            ),
            ExpensesTab(refreshSignal: _expensesRefresh),
            const ProfitTab(),
          ],
        ),
      ),
    ],
  );
}

/// iOS-style segmented control: a white "thumb" slides between segments
/// following the [TabController]'s animation, so it tracks swipes too.
class _PillTabBar extends StatelessWidget {
  const _PillTabBar({required this.controller, required this.tabs});

  final TabController controller;
  final List<({String label, IconData icon})> tabs;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 10, 16, 4),
    child: Container(
      height: 44,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(color: _kTrack, borderRadius: BorderRadius.circular(14)),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final segmentWidth = constraints.maxWidth / tabs.length;
          return AnimatedBuilder(
            animation: controller.animation!,
            builder: (context, _) {
              final position = controller.animation!.value;
              return Stack(
                children: [
                  Positioned(
                    left: position * segmentWidth,
                    top: 0,
                    bottom: 0,
                    width: segmentWidth,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(11),
                        boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 6, offset: Offset(0, 2))],
                      ),
                    ),
                  ),
                  Row(
                    children: [
                      for (var i = 0; i < tabs.length; i++)
                        Expanded(
                          child: _PillLabel(
                            label: tabs[i].label,
                            icon: tabs[i].icon,
                            selectedness: (1 - (position - i).abs()).clamp(0.0, 1.0),
                            selected: controller.index == i,
                            onTap: () => controller.animateTo(i),
                          ),
                        ),
                    ],
                  ),
                ],
              );
            },
          );
        },
      ),
    ),
  );
}

class _PillLabel extends StatelessWidget {
  const _PillLabel({
    required this.label,
    required this.icon,
    required this.selectedness,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;

  /// 0 (fully inactive) → 1 (fully active), interpolated while the thumb slides.
  final double selectedness;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = Color.lerp(AppColors.textMuted, AppColors.primaryDk, selectedness)!;
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      excludeSemantics: true,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: Center(
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: color),
              const SizedBox(width: 5),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12.5, fontWeight: selectedness > 0.5 ? FontWeight.w700 : FontWeight.w600, color: color),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
