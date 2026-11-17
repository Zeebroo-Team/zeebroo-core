import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../inventory/screens/campaign_overview_screen.dart';
import '../../inventory/screens/contacts_overview_screen.dart';
import '../../inventory/screens/inventory_screen.dart';
import '../../inventory/screens/product_overview_screen.dart';
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
  const HomeContent({super.key, this.homeTapSignal = 0, this.salesRefreshSignal = 0});

  /// Changes whenever the bottom Home button is tapped. This lets the Home
  /// page return to Overview even when it is already the selected main tab.
  final int homeTapSignal;

  /// Changes when the shell returns from a screen that may have recorded a
  /// sale (POS, scanner), so today's sales and recent transactions reload.
  final int salesRefreshSignal;

  @override
  State<HomeContent> createState() => _HomeContentState();
}

class _HomeContentState extends State<HomeContent> with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  static const _tabs = [
    (label: 'Overview', icon: Icons.dashboard_rounded),
    (label: 'Expenses', icon: Icons.receipt_long_rounded),
    (label: 'Profit', icon: Icons.trending_up_rounded),
  ];

  /// How often the Overview polls for sales made elsewhere (e.g. the desktop app).
  static const _kSalesPollInterval = Duration(seconds: 30);

  late final TabController _tabController = TabController(length: _tabs.length, vsync: this);
  final _todayKey = GlobalKey<TodaySalesOverviewState>();
  final _overviewKey = GlobalKey<AccountOverviewTabState>();
  final _expensesRefresh = ValueNotifier<int>(0);
  Timer? _salesPoll;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startSalesPoll();
  }

  @override
  void didUpdateWidget(covariant HomeContent oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.homeTapSignal != oldWidget.homeTapSignal) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _tabController.index != 0) {
          _tabController.index = 0;
        }
      });
    }
    if (widget.salesRefreshSignal != oldWidget.salesRefreshSignal) _refreshSales();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refreshSales();
      _startSalesPoll();
    } else if (state == AppLifecycleState.paused) {
      _salesPoll?.cancel();
    }
  }

  void _startSalesPoll() {
    _salesPoll?.cancel();
    _salesPoll = Timer.periodic(_kSalesPollInterval, (_) {
      // Only poll while Home is the visible route (not under POS etc.).
      if (ModalRoute.of(context)?.isCurrent ?? true) _refreshSales();
    });
  }

  void _refreshSales() {
    _todayKey.currentState?.reload(force: true);
    _overviewKey.currentState?.reloadRecentSales();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _salesPoll?.cancel();
    _tabController.dispose();
    _expensesRefresh.dispose();
    super.dispose();
  }

  void _push(Widget screen) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));

  void _openInventory() => _push(const InventoryScreen());

  @override
  Widget build(BuildContext context) => Column(
    children: [
      _PillTabBar(controller: _tabController, tabs: _tabs),
      Expanded(
        child: TabBarView(
          controller: _tabController,
          children: [
            AccountOverviewTab(
              key: _overviewKey,
              onPullRefresh: () => _todayKey.currentState?.reload(force: true),
              belowBalance: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TodaySalesOverview(key: _todayKey),
                  const SizedBox(height: 20),
                  QuickActionsRow(
                    actions: [
                      QuickAction(label: 'Inventory', icon: Icons.inventory_2_rounded, color: const Color(0xFF10B981), onTap: _openInventory),
                      QuickAction(label: 'Product', icon: Icons.category_rounded, color: const Color(0xFF6366F1), onTap: () => _push(const ProductOverviewScreen())),
                      QuickAction(label: 'Contacts', icon: Icons.contacts_rounded, color: const Color(0xFF0EA5E9), onTap: () => _push(const ContactsOverviewScreen())),
                      QuickAction(label: 'Campaign', icon: Icons.campaign_rounded, color: AppColors.warning, onTap: () => _push(const CampaignOverviewScreen())),
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
              final position = controller.index.toDouble();
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
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;

  /// 0 (fully inactive) → 1 (fully active), interpolated while the thumb slides.
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primaryDk : AppColors.textMuted;
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
                  style: TextStyle(fontSize: 12.5, fontWeight: selected ? FontWeight.w700 : FontWeight.w600, color: color),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
