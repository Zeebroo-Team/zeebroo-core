import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../widgets/account_overview_tab.dart';
import '../widgets/expenses_tab.dart';
import '../widgets/profit_tab.dart';

/// The "Home" tab content inside [HomeShell] — three sub-tabs covering
/// account balances, expenses and profit. The shell owns the header
/// (greeting/avatar) and bottom navigation chrome, so this widget is just
/// the tab bar plus the scrollable body for whichever sub-tab is active.
class HomeContent extends StatefulWidget {
  const HomeContent({super.key});

  @override
  State<HomeContent> createState() => _HomeContentState();
}

class _HomeContentState extends State<HomeContent> with SingleTickerProviderStateMixin {
  static const _tabLabels = ['Account overview', 'Expenses', 'Profit'];

  late final TabController _tabController = TabController(length: _tabLabels.length, vsync: this);

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Container(
        color: AppColors.surface,
        width: double.infinity,
        child: TabBar(
          controller: _tabController,
          indicatorSize: TabBarIndicatorSize.tab,
          indicatorColor: AppColors.primary,
          indicatorWeight: 2,
          dividerColor: Colors.transparent,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textMuted,
          labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
          unselectedLabelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
          tabs: [for (final label in _tabLabels) Tab(height: 38, text: label)],
        ),
      ),
      const Divider(height: 1, color: AppColors.border),
      Expanded(
        child: TabBarView(
          controller: _tabController,
          children: const [
            AccountOverviewTab(),
            ExpensesTab(),
            ProfitTab(),
          ],
        ),
      ),
    ],
  );
}
