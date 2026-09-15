import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';
import '../widgets/stat_card.dart';

// ─── Formatters ───────────────────────────────────────────────────────────────
final _money    = NumberFormat.compactCurrency(symbol: '\$', decimalDigits: 1);
final _moneyFull= NumberFormat.currency(symbol: '\$', decimalDigits: 2);
final _dateFmt  = DateFormat('MMM d');
final _numFmt   = NumberFormat.compact();

String _fmt(dynamic v)  => _money.format(double.tryParse(v?.toString() ?? '0') ?? 0);
String _num(dynamic v)  => (v == null || v.toString() == 'null') ? '—' : _numFmt.format(num.tryParse(v.toString()) ?? 0);
String _fmtFull(dynamic v) => _moneyFull.format(double.tryParse(v?.toString() ?? '0') ?? 0);

// ─── Tab definitions ──────────────────────────────────────────────────────────
const _tabs = [
  _TabDef(label: 'Overview',  icon: Icons.dashboard_outlined,     activeIcon: Icons.dashboard_rounded),
  _TabDef(label: 'Income',    icon: Icons.trending_up_outlined,   activeIcon: Icons.trending_up_rounded),
  _TabDef(label: 'Expenses',  icon: Icons.trending_down_outlined, activeIcon: Icons.trending_down_rounded),
];

class _TabDef {
  const _TabDef({required this.label, required this.icon, required this.activeIcon});
  final String   label;
  final IconData icon, activeIcon;
}

// ─── Screen ───────────────────────────────────────────────────────────────────
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});
  @override State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen>
    with SingleTickerProviderStateMixin {

  late final TabController _tab = TabController(length: _tabs.length, vsync: this);

  // ── Data ────────────────────────────────────────────────────────────────────
  Map<String, dynamic>? _summary;
  Map<String, dynamic>? _profit;
  Map<String, dynamic>? _expenses;
  List<dynamic> _sales    = [];
  List<dynamic> _bills    = [];
  bool _loading  = true;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _hasError = false; });

    // Run all calls independently — one failure must not hide the rest.
    final results = await Future.wait([
      ApiClient.instance.get(ApiEndpoints.todaySummary).then<dynamic>((r) => r.data).catchError((_) => null),
      ApiClient.instance.get(ApiEndpoints.profitReport, params: {'period': 30}).then<dynamic>((r) => r.data).catchError((_) => null),
      ApiClient.instance.get(ApiEndpoints.expensesOverview).then<dynamic>((r) => r.data).catchError((_) => null),
      ApiClient.instance.get(ApiEndpoints.sales, params: {'limit': 8}).then<dynamic>((r) => r.data).catchError((_) => null),
    ]);

    if (!mounted) return;

    // ── today-summary → { data: { sales: { count, revenue, by_method }, top_products, recent_sales } }
    final sRaw = results[0];
    final sData = sRaw is Map ? (sRaw['data'] ?? sRaw) as Map<String, dynamic>? : null;

    // ── profit-report → { data: { summary: { revenue, cogs, gross_profit, net_profit }, trend: [...] } }
    final pRaw = results[1];
    final pData = pRaw is Map ? (pRaw['data'] ?? pRaw) as Map<String, dynamic>? : null;

    // ── expenses/overview → { data: { summary: { total_monthly, overdue_count, bills_count }, bills: [...] } }
    final eRaw = results[2];
    final eData = eRaw is Map ? (eRaw['data'] ?? eRaw) as Map<String, dynamic>? : null;

    // ── sales → { data: [...] }
    final rRaw = results[3];
    final salesList = rRaw is Map
        ? (rRaw['data'] ?? rRaw['items'] ?? [])
        : (rRaw is List ? rRaw : []);

    setState(() {
      _summary  = sData;
      _profit   = pData;
      _expenses = eData;
      _sales    = (salesList as List).toList();
      _bills    = ((eData?['bills'] as List?) ?? []).toList();
      // Show error only if ALL calls failed
      _hasError = sData == null && pData == null && eData == null && salesList.isEmpty;
      _loading  = false;
    });
  }


  // ─── Greeting ────────────────────────────────────────────────────────────────
  String get _greeting {
    final h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    final user      = context.watch<AuthState>().user;
    final firstName = (user?['name'] as String? ?? 'there').split(' ').first;

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: NestedScrollView(
        headerSliverBuilder: (ctx, _) => [
          // ── Gradient app-bar ─────────────────────────────────────────────
          SliverAppBar(
            expandedHeight: 170,
            floating: false,
            pinned: true,
            backgroundColor: AppColors.primary,
            automaticallyImplyLeading: false,
            actions: [
              IconButton(
                icon: CircleAvatar(
                  radius: 14,
                  backgroundColor: Colors.white.withValues(alpha: 0.22),
                  child: Text(firstName[0].toUpperCase(),
                      style: const TextStyle(color: Colors.white,
                          fontWeight: FontWeight.w700, fontSize: 13)),
                ),
                onPressed: _showSignOutDialog,
              ),
              const SizedBox(width: 8),
            ],
            flexibleSpace: FlexibleSpaceBar(
              collapseMode: CollapseMode.pin,
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [AppColors.primary, AppColors.primaryDk],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                ),
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('$_greeting, $firstName 👋',
                          style: const TextStyle(fontSize: 20,
                              fontWeight: FontWeight.w800, color: Colors.white)),
                        const SizedBox(height: 2),
                        Text(
                          user?['business']?['name'] ?? user?['company'] as String? ?? 'Your Business',
                          style: TextStyle(fontSize: 12,
                              color: Colors.white.withValues(alpha: 0.72)),
                        ),
                        const SizedBox(height: 14),

                        // Today quick summary row
                        // today-summary.data.sales.{revenue, count}
                        Row(
                          children: [
                            _HeaderChip(
                              icon: Icons.payments_outlined,
                              label: "Today's Revenue",
                              value: _loading ? '…' : _fmt(
                                  (_summary?['sales'] as Map?)?['revenue']),
                            ),
                            const SizedBox(width: 12),
                            _HeaderChip(
                              icon: Icons.receipt_outlined,
                              label: 'Orders',
                              value: _loading ? '…' : _num(
                                  (_summary?['sales'] as Map?)?['count']),
                            ),
                            const SizedBox(width: 12),
                            _HeaderChip(
                              icon: Icons.inventory_2_outlined,
                              label: 'Items Sold',
                              value: _loading ? '…' : _num(
                                  (_summary?['sales'] as Map?)?['items_sold']),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            // ── Tab bar pinned at bottom of app-bar ─────────────────────────
            bottom: PreferredSize(
              preferredSize: const Size.fromHeight(48),
              child: Container(
                color: AppColors.primary,
                child: TabBar(
                  controller: _tab,
                  isScrollable: false,
                  indicatorColor: Colors.white,
                  indicatorWeight: 3,
                  labelColor: Colors.white,
                  unselectedLabelColor: Colors.white.withValues(alpha: 0.55),
                  labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                  unselectedLabelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
                  tabs: _tabs.map((t) => Tab(
                    height: 46,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(t.icon, size: 18),
                        const SizedBox(width: 6),
                        Text(t.label),
                      ],
                    ),
                  )).toList(),
                ),
              ),
            ),
          ),
        ],

        // ── Tab content (swipeable) ────────────────────────────────────────
        body: _loading
            ? const Center(child: CircularProgressIndicator(
                color: AppColors.primary, strokeWidth: 2.5))
            : _hasError
            ? _ErrorView(onRetry: _load)
            : TabBarView(
                controller: _tab,
                children: [
                  _OverviewTab(summary: _summary, profit: _profit, onRefresh: _load),
                  _IncomeTab(sales: _sales, profit: _profit, onRefresh: _load),
                  _ExpensesTab(expenses: _expenses, bills: _bills, onRefresh: _load),
                ],
              ),
      ),
    );
  }

  void _showSignOutDialog() => showDialog(
    context: context,
    builder: (_) => AlertDialog(
      title: const Text('Sign out?'),
      content: const Text('You will be returned to the login screen.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        TextButton(
          onPressed: () { Navigator.pop(context); context.read<AuthState>().logout(); },
          child: const Text('Sign Out', style: TextStyle(color: AppColors.error)),
        ),
      ],
    ),
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 0 — OVERVIEW
// ═══════════════════════════════════════════════════════════════════════════════
class _OverviewTab extends StatelessWidget {
  const _OverviewTab({required this.summary, required this.profit, required this.onRefresh});
  final Map<String, dynamic>? summary;
  final Map<String, dynamic>? profit;
  final VoidCallback onRefresh;

  static const _quickActions = [
    _QuickAction(Icons.add_shopping_cart_outlined, 'New Sale',   AppColors.primary),
    _QuickAction(Icons.inventory_2_outlined,        'Inventory',  AppColors.info),
    _QuickAction(Icons.people_outline,              'Customers',  AppColors.success),
    _QuickAction(Icons.bar_chart_rounded,           'Reports',    AppColors.warning),
  ];

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    color: AppColors.primary,
    onRefresh: () async => onRefresh(),
    child: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // ── Stat cards 2×2 ─────────────────────────────────────────────────
        GridView.count(
          crossAxisCount: 2,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 1.15,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: [
            // profit-report.data.summary.*
            StatCard(
              label: 'Revenue (30d)',
              value: _fmt((profit?['summary'] as Map?)?['revenue']),
              icon: Icons.payments_outlined,
              iconColor: AppColors.primary, iconBg: AppColors.purpleLight,
            ),
            StatCard(
              label: 'Gross Profit',
              value: _fmt((profit?['summary'] as Map?)?['gross_profit']),
              icon: Icons.show_chart_rounded,
              iconColor: AppColors.success, iconBg: AppColors.greenLight,
            ),
            // today-summary.data.sales.*
            StatCard(
              label: "Today's Orders",
              value: _num((summary?['sales'] as Map?)?['count']),
              icon: Icons.receipt_long_outlined,
              iconColor: AppColors.info, iconBg: AppColors.blueLight,
            ),
            StatCard(
              label: 'Items Sold Today',
              value: _num((summary?['sales'] as Map?)?['items_sold']),
              icon: Icons.shopping_bag_outlined,
              iconColor: AppColors.warning, iconBg: AppColors.amberLight,
            ),
          ],
        ),
        const SizedBox(height: 24),

        // ── Quick actions ───────────────────────────────────────────────────
        const _SectionHeader('Quick Actions'),
        const SizedBox(height: 12),
        Row(
          children: _quickActions.map((a) => Expanded(
            child: _QuickActionBtn(action: a),
          )).toList(),
        ),
        const SizedBox(height: 24),

        // ── Payment method breakdown — today-summary.data.sales.by_method ────
        if (((summary?['sales'] as Map?)?['by_method'] as Map?)?.isNotEmpty ?? false) ...[
          const _SectionHeader('Payment Methods'),
          const SizedBox(height: 12),
          _PaymentBreakdown(data: (summary!['sales'] as Map)['by_method']),
          const SizedBox(height: 24),
        ],

        // ── Top products ────────────────────────────────────────────────────
        if ((summary?['top_products'] as List?)?.isNotEmpty ?? false) ...[
          const _SectionHeader('Top Products Today'),
          const SizedBox(height: 12),
          _TopProductsList(products: summary!['top_products'] as List),
        ],

        const SizedBox(height: 24),
      ],
    ),
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 1 — INCOME
// ═══════════════════════════════════════════════════════════════════════════════
class _IncomeTab extends StatelessWidget {
  const _IncomeTab({required this.sales, required this.profit, required this.onRefresh});
  final List<dynamic>          sales;
  final Map<String, dynamic>?  profit;
  final VoidCallback           onRefresh;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    color: AppColors.primary,
    onRefresh: () async => onRefresh(),
    child: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // ── Summary header cards — profit-report.data.summary.* ────────────
        Row(
          children: [
            Expanded(child: _MetricCard(
              label: 'Revenue (30d)',
              value: _fmt((profit?['summary'] as Map?)?['revenue']),
              color: AppColors.primary,
              icon: Icons.payments_rounded,
            )),
            const SizedBox(width: 12),
            Expanded(child: _MetricCard(
              label: 'Net Profit',
              value: _fmt((profit?['summary'] as Map?)?['net_profit']),
              color: AppColors.success,
              icon: Icons.trending_up_rounded,
            )),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: _MetricCard(
              label: 'Gross Profit',
              value: _fmt((profit?['summary'] as Map?)?['gross_profit']),
              color: AppColors.info,
              icon: Icons.show_chart_rounded,
            )),
            const SizedBox(width: 12),
            Expanded(child: _MetricCard(
              label: 'COGS',
              value: _fmt((profit?['summary'] as Map?)?['cogs']),
              color: AppColors.warning,
              icon: Icons.inventory_2_outlined,
            )),
          ],
        ),
        const SizedBox(height: 24),

        // ── Revenue bars — profit-report.data.trend (buckets) ──────────────
        if ((profit?['trend'] as List?)?.isNotEmpty ?? false) ...[
          const _SectionHeader('Revenue Trend (30 days)'),
          const SizedBox(height: 12),
          _RevenueBars(buckets: profit!['trend'] as List),
          const SizedBox(height: 24),
        ],

        // ── Recent sales ─────────────────────────────────────────────────────
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const _SectionHeader('Recent Sales'),
            TextButton(onPressed: () {}, child: const Text('See all',
                style: TextStyle(color: AppColors.primary, fontSize: 13))),
          ],
        ),
        const SizedBox(height: 8),
        if (sales.isEmpty)
          _EmptyCard(icon: Icons.receipt_long_outlined, message: 'No sales yet')
        else
          Card(
            child: Column(
              children: [
                for (int i = 0; i < sales.length; i++) ...[
                  _SaleTile(sale: sales[i] as Map<String, dynamic>),
                  if (i < sales.length - 1) const Divider(height: 1, indent: 60),
                ],
              ],
            ),
          ),
        const SizedBox(height: 24),
      ],
    ),
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 2 — EXPENSES
// ═══════════════════════════════════════════════════════════════════════════════
class _ExpensesTab extends StatelessWidget {
  const _ExpensesTab({required this.expenses, required this.bills, required this.onRefresh});
  final Map<String, dynamic>? expenses;
  final List<dynamic>         bills;
  final VoidCallback          onRefresh;

  @override
  Widget build(BuildContext context) {
    // expenses-overview.data.summary.*
    final expSum        = expenses?['summary'] as Map? ?? {};
    final monthlyTotal  = expSum['total_monthly']  ?? 0;
    final overdueCount  = expSum['overdue_count']  ?? 0;
    final totalBills    = expSum['bills_count']    ?? bills.length;

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () async => onRefresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // ── Summary row ─────────────────────────────────────────────────────
          Row(
            children: [
              Expanded(child: _MetricCard(
                label: 'Monthly Bills',
                value: _fmt(monthlyTotal),
                color: AppColors.error,
                icon: Icons.receipt_outlined,
              )),
              const SizedBox(width: 12),
              Expanded(child: _MetricCard(
                label: 'Overdue',
                value: overdueCount.toString(),
                color: overdueCount > 0 ? AppColors.error : AppColors.success,
                icon: Icons.warning_amber_rounded,
              )),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _MetricCard(
                label: 'Total Bills',
                value: totalBills.toString(),
                color: AppColors.warning,
                icon: Icons.description_outlined,
              )),
              const SizedBox(width: 12),
              Expanded(child: _MetricCard(
                label: 'Rentals/Month',
                value: _fmt(expSum['rentals_monthly']),
                color: AppColors.info,
                icon: Icons.home_work_outlined,
              )),
            ],
          ),
          const SizedBox(height: 24),

          // ── Bills list ───────────────────────────────────────────────────────
          const _SectionHeader('Bills'),
          const SizedBox(height: 12),
          if (bills.isEmpty)
            _EmptyCard(icon: Icons.receipt_outlined, message: 'No bills found')
          else
            Card(
              child: Column(
                children: [
                  for (int i = 0; i < bills.length; i++) ...[
                    _BillTile(bill: bills[i] as Map<String, dynamic>),
                    if (i < bills.length - 1) const Divider(height: 1, indent: 60),
                  ],
                ],
              ),
            ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  SHARED WIDGETS
// ═══════════════════════════════════════════════════════════════════════════════

class _HeaderChip extends StatelessWidget {
  const _HeaderChip({required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label, value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.13),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Icon(icon, size: 13, color: Colors.white.withValues(alpha: 0.75)),
            const SizedBox(width: 4),
            Expanded(child: Text(label,
              style: TextStyle(fontSize: 10, color: Colors.white.withValues(alpha: 0.72)),
              maxLines: 1, overflow: TextOverflow.ellipsis)),
          ]),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(
              fontSize: 15, fontWeight: FontWeight.w800, color: Colors.white)),
        ],
      ),
    ),
  );
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value,
      required this.color, required this.icon});
  final String label, value;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, size: 18, color: color),
          ),
          const SizedBox(width: 10),
          Expanded(child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontSize: 11,
                  color: AppColors.textHint, fontWeight: FontWeight.w500)),
              const SizedBox(height: 2),
              Text(value, style: TextStyle(fontSize: 16,
                  fontWeight: FontWeight.w800, color: color)),
            ],
          )),
        ],
      ),
    ),
  );
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader(this.title);
  final String title;

  @override
  Widget build(BuildContext context) => Text(title, style: const TextStyle(
      fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textDark));
}

// Revenue mini bar chart from profit-report buckets
class _RevenueBars extends StatelessWidget {
  const _RevenueBars({required this.buckets});
  final List<dynamic> buckets;

  @override
  Widget build(BuildContext context) {
    final values = buckets.map((b) {
      return double.tryParse((b['revenue'] ?? b['total'] ?? 0).toString()) ?? 0.0;
    }).toList();
    final maxVal = values.fold(0.0, (a, b) => a > b ? a : b);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: SizedBox(
          height: 80,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: List.generate(values.length, (i) {
              final pct = maxVal > 0 ? values[i] / maxVal : 0.0;
              final isLast = i == values.length - 1;
              return Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 1),
                  child: Tooltip(
                    message: _moneyFull.format(values[i]),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        AnimatedContainer(
                          duration: Duration(milliseconds: 300 + i * 15),
                          height: 4 + (pct * 60),
                          decoration: BoxDecoration(
                            color: isLast
                                ? AppColors.primary
                                : AppColors.primary.withValues(alpha: 0.45),
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }
}

class _PaymentBreakdown extends StatelessWidget {
  const _PaymentBreakdown({required this.data});
  final dynamic data;

  @override
  Widget build(BuildContext context) {
    if (data == null) return const SizedBox.shrink();
    final map = data is Map ? data as Map : <String, dynamic>{};
    if (map.isEmpty) return const SizedBox.shrink();

    final colors = [AppColors.primary, AppColors.info, AppColors.success,
                    AppColors.warning, AppColors.error];
    final entries = map.entries.toList();

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: entries.asMap().entries.map((me) {
            final i = me.key;
            final e = me.value;
            final val = e.value is Map ? (e.value['total'] ?? 0) : e.value;
            final cnt = e.value is Map ? (e.value['count'] ?? 0) : 0;
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                children: [
                  Container(
                    width: 10, height: 10,
                    decoration: BoxDecoration(
                      color: colors[i % colors.length],
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(child: Text(
                    (e.key as String).replaceAll('_', ' ').toUpperCase(),
                    style: const TextStyle(fontSize: 12,
                        fontWeight: FontWeight.w600, color: AppColors.textMid),
                  )),
                  if (cnt != 0) Text('$cnt orders · ',
                      style: const TextStyle(fontSize: 12, color: AppColors.textHint)),
                  Text(_fmt(val), style: TextStyle(fontSize: 13,
                      fontWeight: FontWeight.w700, color: colors[i % colors.length])),
                ],
              ),
            );
          }).toList(),
        ),
      ),
    );
  }
}

class _TopProductsList extends StatelessWidget {
  const _TopProductsList({required this.products});
  final List<dynamic> products;

  @override
  Widget build(BuildContext context) => Card(
    child: Column(
      children: [
        for (int i = 0; i < products.length; i++) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Row(
              children: [
                Container(
                  width: 32, height: 32,
                  decoration: BoxDecoration(
                    color: AppColors.purpleLight,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Center(child: Text('${i + 1}', style: const TextStyle(
                      fontSize: 13, fontWeight: FontWeight.w800, color: AppColors.primary))),
                ),
                const SizedBox(width: 12),
                Expanded(child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(products[i]['name']?.toString() ?? '—',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600,
                          color: AppColors.textDark), maxLines: 1, overflow: TextOverflow.ellipsis),
                    Text('Qty: ${_num(products[i]['qty'])}',
                      style: const TextStyle(fontSize: 11, color: AppColors.textHint)),
                  ],
                )),
                Text(_fmt(products[i]['revenue']),
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700,
                      color: AppColors.textDark)),
              ],
            ),
          ),
          if (i < products.length - 1) const Divider(height: 1, indent: 58),
        ],
      ],
    ),
  );
}

class _SaleTile extends StatelessWidget {
  const _SaleTile({required this.sale});
  final Map<String, dynamic> sale;

  @override
  Widget build(BuildContext context) {
    final ref      = sale['reference'] ?? sale['receipt_no'] ?? 'Order #${sale['id']}';
    final total    = double.tryParse(
        (sale['total'] ?? sale['grand_total'] ?? 0).toString()) ?? 0;
    final customer = (sale['customer'] is Map
        ? sale['customer']['name']
        : sale['customer_name']) ?? 'Walk-in';
    final dateStr  = sale['sold_at'] as String? ?? sale['created_at'] as String?;
    final date     = dateStr != null
        ? _dateFmt.format(DateTime.tryParse(dateStr) ?? DateTime.now()) : '';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
              color: AppColors.purpleLight,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.receipt_outlined, size: 18, color: AppColors.primary),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(ref.toString(), style: const TextStyle(fontSize: 13,
                  fontWeight: FontWeight.w600, color: AppColors.textDark),
                  maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 2),
              Text('$customer · $date',
                  style: const TextStyle(fontSize: 11, color: AppColors.textHint)),
            ],
          )),
          Text(_fmtFull(total), style: const TextStyle(
              fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textDark)),
        ],
      ),
    );
  }
}

class _BillTile extends StatelessWidget {
  const _BillTile({required this.bill});
  final Map<String, dynamic> bill;

  @override
  Widget build(BuildContext context) {
    final overdue    = bill['overdue'] == true;
    final fullyPaid  = bill['fully_paid'] == true;
    final amount     = double.tryParse(
        (bill['amount'] ?? bill['recurring_cost'] ?? 0).toString()) ?? 0;

    Color statusColor = overdue ? AppColors.error
        : fullyPaid ? AppColors.success : AppColors.warning;
    String statusLabel = overdue ? 'Overdue' : fullyPaid ? 'Paid' : 'Pending';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
              color: statusColor.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              overdue ? Icons.warning_amber_rounded
                  : fullyPaid ? Icons.check_circle_outline
                  : Icons.schedule_outlined,
              size: 18, color: statusColor),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(bill['name']?.toString() ?? '—', style: const TextStyle(
                  fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textDark),
                  maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 2),
              Row(children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(statusLabel, style: TextStyle(
                      fontSize: 10, fontWeight: FontWeight.w700, color: statusColor)),
                ),
                const SizedBox(width: 6),
                Text(bill['category_label'] ?? bill['category'] ?? '',
                  style: const TextStyle(fontSize: 11, color: AppColors.textHint)),
              ]),
            ],
          )),
          Text(_fmtFull(amount), style: TextStyle(
              fontSize: 13, fontWeight: FontWeight.w700, color: statusColor)),
        ],
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.icon, required this.message});
  final IconData icon;
  final String   message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(children: [
        Icon(icon, size: 36, color: AppColors.border),
        const SizedBox(height: 10),
        Text(message, style: const TextStyle(fontSize: 13, color: AppColors.textHint)),
      ]),
    ),
  );
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.onRetry});
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.cloud_off_outlined, size: 48, color: AppColors.border),
        const SizedBox(height: 12),
        const Text('Could not load data', style: TextStyle(
            color: AppColors.textMuted, fontSize: 15)),
        const SizedBox(height: 16),
        ElevatedButton.icon(
          onPressed: onRetry,
          icon: const Icon(Icons.refresh, size: 16),
          label: const Text('Retry'),
        ),
      ],
    ),
  );
}

// Quick action button
class _QuickAction {
  const _QuickAction(this.icon, this.label, this.color);
  final IconData icon;
  final String label;
  final Color color;
}

class _QuickActionBtn extends StatelessWidget {
  const _QuickActionBtn({required this.action});
  final _QuickAction action;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: () {},
    child: Column(
      children: [
        Container(
          width: 52, height: 52,
          decoration: BoxDecoration(
            color: action.color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(action.icon, size: 24, color: action.color),
        ),
        const SizedBox(height: 6),
        Text(action.label, style: const TextStyle(fontSize: 11,
            fontWeight: FontWeight.w600, color: AppColors.textMid),
            textAlign: TextAlign.center),
      ],
    ),
  );
}
