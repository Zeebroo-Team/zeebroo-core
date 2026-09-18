import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../widgets/bills_tab.dart';
import '../widgets/loans_tab.dart';
import '../widgets/modifications_tab.dart';
import '../widgets/properties_tab.dart';
import '../widgets/rentals_tab.dart';

/// Full-screen "Financial" section (bills, loans, rentals, properties,
/// modifications — the desktop Finance module's tabs, minus Overview and
/// Budget). Reached from the side drawer's Features list.
class FinanceScreen extends StatelessWidget {
  const FinanceScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      title: const Text('Financial', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
    ),
    body: const FinanceBody(),
  );
}

/// Body-only variant used inside [HomeShell]'s `IndexedStack`, where the
/// shell's own glass app bar and bottom nav already frame the screen.
class FinanceBody extends StatefulWidget {
  const FinanceBody({super.key});

  @override
  State<FinanceBody> createState() => _FinanceBodyState();
}

class _FinanceBodyState extends State<FinanceBody> with SingleTickerProviderStateMixin {
  static const _tabLabels = ['Bills', 'Loans', 'Rentals', 'Properties', 'Modifications'];

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
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          padding: EdgeInsets.zero,
          labelPadding: const EdgeInsets.symmetric(horizontal: 12),
          indicatorSize: TabBarIndicatorSize.label,
          indicatorColor: AppColors.primary,
          indicatorWeight: 2,
          dividerColor: Colors.transparent,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textMuted,
          labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
          unselectedLabelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
          tabs: [for (final label in _tabLabels) Tab(height: 34, text: label)],
        ),
      ),
      const Divider(height: 1, color: AppColors.border),
      Expanded(
        child: TabBarView(
          controller: _tabController,
          children: const [
            BillsTab(),
            LoansTab(),
            RentalsTab(),
            PropertiesTab(),
            ModificationsTab(),
          ],
        ),
      ),
    ],
  );
}
