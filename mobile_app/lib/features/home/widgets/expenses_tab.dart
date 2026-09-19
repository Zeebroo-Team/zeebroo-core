import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../../finance/widgets/bills_tab.dart';
import '../../finance/widgets/loans_tab.dart';
import '../../finance/widgets/modifications_tab.dart';
import '../../finance/widgets/rentals_tab.dart';
import 'expense_breakdown_chart.dart';

/// Second home tab — a period-filtered breakdown of everything paid out
/// (`GET /v1/pos/expenses/breakdown`), then bills, loans, rentals and recent
/// payments (`GET /v1/pos/expenses/overview`), mirroring the desktop admin
/// panel's Expenses view.
class ExpensesTab extends StatefulWidget {
  const ExpensesTab({super.key, this.refreshSignal});

  /// Notifies when something outside the tab changed its data (e.g. a bill
  /// added from the home quick actions) so it should reload from the server.
  final Listenable? refreshSignal;

  @override
  State<ExpensesTab> createState() => _ExpensesTabState();
}

class _ExpensesTabState extends State<ExpensesTab>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  bool _loaded = false;
  String? _error;
  Map<String, dynamic>? _data;
  List<Map<String, dynamic>> _loans = [];
  List<Map<String, dynamic>> _rentals = [];
  List<Map<String, dynamic>> _modifications = [];

  String _period = _kPeriods.first.$1;
  bool _breakdownLoading = true;
  String? _breakdownError;
  Map<String, dynamic>? _breakdown;

  @override
  void initState() {
    super.initState();
    widget.refreshSignal?.addListener(_onRefreshSignal);
    _reloadAll();
  }

  @override
  void dispose() {
    widget.refreshSignal?.removeListener(_onRefreshSignal);
    super.dispose();
  }

  void _onRefreshSignal() => _reloadAll(force: true);

  Future<void> _reloadAll({bool force = false}) =>
      Future.wait([_load(forceRefresh: force), _loadBreakdown(force: force)]);

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        ApiClient.instance.get(ApiEndpoints.expensesOverview, bypassCache: forceRefresh),
        ApiClient.instance.get(ApiEndpoints.financeLoans, bypassCache: forceRefresh),
        ApiClient.instance.get(ApiEndpoints.financeRentals, bypassCache: forceRefresh),
        ApiClient.instance.get(ApiEndpoints.financeModifications, bypassCache: forceRefresh),
      ]);
      final raw = results[0].data;
      _data = (raw is Map ? raw['data'] : raw) as Map<String, dynamic>?;
      _loans = _parseList(results[1].data);
      _rentals = _parseList(results[2].data);
      _modifications = _parseList(results[3].data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      _loaded = true;
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadBreakdown({bool force = false}) async {
    final period = _period;
    setState(() {
      _breakdownLoading = true;
      _breakdownError = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.expensesBreakdown, params: {'period': period}, bypassCache: force);
      if (period != _period) return;
      final raw = res.data;
      _breakdown = (raw is Map ? raw['data'] : raw) as Map<String, dynamic>?;
    } catch (e) {
      if (period == _period) _breakdownError = apiErrorMessage(e);
    } finally {
      if (mounted && period == _period) setState(() => _breakdownLoading = false);
    }
  }

  void _setPeriod(String period) {
    if (period == _period) return;
    setState(() => _period = period);
    _loadBreakdown();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final firstLoad = _loading && !_loaded;
    return RefreshIndicator(
      onRefresh: () => _reloadAll(force: true),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 120),
        children: [
          if (firstLoad)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 60),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _ErrorCard(message: _error!, onRetry: _reloadAll)
          else
            ..._buildContent(),
        ],
      ),
    );
  }

  Widget _buildChart(Map summary) {
    if (_breakdownError != null) {
      return _ChartMessageCard(message: _breakdownError!, onRetry: () => _loadBreakdown(force: true));
    }
    final breakdown = _breakdown;
    if (breakdown == null) return const _ChartLoadingCard();

    final items = ((breakdown['items'] as List?) ?? []).whereType<Map>();
    final range = breakdown['range_label'] as String?;

    return AnimatedOpacity(
      duration: const Duration(milliseconds: 150),
      opacity: _breakdownLoading ? 0.45 : 1,
      child: ExpenseBreakdownChart(
        key: ValueKey(breakdown['period']),
        overdueCount: (summary['overdue_count'] as num?)?.toInt() ?? 0,
        footnote: range == null || range.isEmpty ? null : 'Solid = paid, light = still due · $range',
        emptyMessage: 'No expenses in this period. Add a bill, loan, rental or modification below.',
        slices: [
          for (final i in items)
            ExpenseSlice(
              label: i['label'] as String? ?? '',
              value: _toDouble(i['total']),
              due: _toDouble(i['due']),
              color: _kindMeta(i['key'] as String?).color,
            ),
        ],
      ),
    );
  }

  List<Widget> _buildContent() {
    final summary = (_data?['summary'] as Map?) ?? {};
    final bills = ((_data?['bills'] as List?) ?? []).whereType<Map>().toList();
    final payments = ((_breakdown?['payments'] as List?) ?? []).whereType<Map>().toList();

    return [
      _PeriodChips(selected: _period, onSelected: _setPeriod),
      const SizedBox(height: 16),
      _buildChart(summary),
      const SizedBox(height: 16),
      _ShortcutRow(onCreated: _reloadAll),
      const SizedBox(height: 20),
      _SectionCard(
        title: 'Bills',
        icon: Icons.receipt_long_outlined,
        empty: bills.isEmpty,
        emptyLabel: 'No bills yet.',
        children: [
          for (final b in bills)
            _RowTile(
              leadingIcon: Icons.water_drop_outlined,
              title: b['name'] as String? ?? '',
              subtitle: [b['category_label'], b['due_date_fmt']]
                  .whereType<String>()
                  .where((s) => s.isNotEmpty)
                  .join(' · Due '),
              trailing: formatMoney(b['amount']),
              badge: (b['fully_paid'] as bool? ?? false)
                  ? const _Badge(label: 'PAID', color: AppColors.success)
                  : (b['overdue'] as bool? ?? false)
                      ? const _Badge(label: 'OVERDUE', color: AppColors.error)
                      : const _Badge(label: 'DUE', color: AppColors.warning),
            ),
        ],
      ),
      const SizedBox(height: 20),
      _SectionCard(
        title: 'Loan settlements',
        icon: Icons.account_balance_outlined,
        empty: _loans.isEmpty,
        emptyLabel: 'No loans yet.',
        children: [
          for (final l in _loans)
            _RowTile(
              leadingIcon: Icons.account_balance_rounded,
              leadingColor: _kindMeta('loans').color,
              title: l['name'] as String? ?? '',
              subtitle: [
                l['cadence_label'] as String?,
                l['payment_formatted'] as String? ?? formatMoney(l['payment_amount']),
              ].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
              trailing: (l['borrowed_amount_fmt'] as String?) ?? formatMoney(l['borrowed_amount']),
              badge: () {
                final endDate = DateTime.tryParse((l['loan_ending_date'] as String?) ?? '');
                final done = endDate != null && endDate.isBefore(DateTime.now());
                return _Badge(label: done ? 'DONE' : 'ACTIVE', color: done ? AppColors.textMuted : AppColors.success);
              }(),
            ),
        ],
      ),
      const SizedBox(height: 20),
      _SectionCard(
        title: 'Rental settlements',
        icon: Icons.apartment_outlined,
        empty: _rentals.isEmpty,
        emptyLabel: 'No rentals yet.',
        children: [
          for (final r in _rentals)
            _RowTile(
              leadingIcon: Icons.villa_outlined,
              leadingColor: _kindMeta('rentals').color,
              title: (r['property_type'] as String?) ?? (r['name'] as String?) ?? '',
              subtitle: [
                r['cadence_label'] as String?,
                r['due_date_fmt'] as String?,
              ].whereType<String>().where((s) => s.isNotEmpty).join(' · Due '),
              trailing: (r['recurring_cost_fmt'] as String?) ?? formatMoney(r['recurring_cost']),
              badge: (r['overdue'] as bool? ?? false)
                  ? const _Badge(label: 'OVERDUE', color: AppColors.error)
                  : const _Badge(label: 'ACTIVE', color: AppColors.success),
            ),
        ],
      ),
      const SizedBox(height: 20),
      _SectionCard(
        title: 'Modifications',
        icon: Icons.tune_rounded,
        empty: _modifications.isEmpty,
        emptyLabel: 'No modifications yet.',
        children: [
          for (final m in _modifications)
            _RowTile(
              leadingIcon: Icons.tune_rounded,
              leadingColor: _kindMeta('modifications').color,
              title: m['name'] as String? ?? '',
              subtitle: [
                m['assignment_display'] as String?,
                m['work_type_label'] as String?,
                m['duration'] as String?,
              ].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
              trailing: (m['estimated_cost_fmt'] as String?) ?? formatMoney(m['estimated_cost']),
              badge: () {
                final bills = (m['bills_count'] as num?)?.toInt() ?? 0;
                return bills > 0
                    ? _Badge(label: '$bills ${bills == 1 ? 'BILL' : 'BILLS'}', color: AppColors.primary)
                    : const _Badge(label: 'PLANNED', color: AppColors.textMuted);
              }(),
            ),
        ],
      ),
      if (payments.isNotEmpty) ...[
        const SizedBox(height: 20),
        _SectionCard(
          title: 'Recent payments',
          icon: Icons.history_rounded,
          empty: false,
          emptyLabel: '',
          children: [
            for (final p in payments)
              _RowTile(
                leadingIcon: _kindMeta(p['kind'] as String?).icon,
                leadingColor: _kindMeta(p['kind'] as String?).color,
                title: p['source_title'] as String? ?? p['source_label'] as String? ?? '',
                subtitle: [p['source_label'], p['date_fmt']]
                    .whereType<String>()
                    .where((s) => s.isNotEmpty)
                    .join(' · '),
                trailing: formatMoney(p['amount']),
              ),
          ],
        ),
      ],
    ];
  }
}

double _toDouble(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;

const _kPeriods = [
  ('month', 'This month'),
  ('6m', '6 months'),
  ('year', 'Year'),
];

class _KindMeta {
  const _KindMeta(this.icon, this.color);
  final IconData icon;
  final Color color;
}

// Shared by the chart slices and the payment rows so a type keeps one colour.
const _kKinds = <String, _KindMeta>{
  'bills': _KindMeta(Icons.receipt_long_rounded, Color(0xFF6366F1)),
  'loans': _KindMeta(Icons.account_balance_rounded, Color(0xFF0EA5E9)),
  'rentals': _KindMeta(Icons.apartment_rounded, Color(0xFF10B981)),
  'modifications': _KindMeta(Icons.tune_rounded, Color(0xFFF59E0B)),
  'payroll': _KindMeta(Icons.groups_rounded, Color(0xFFEC4899)),
  'purchases': _KindMeta(Icons.shopping_cart_rounded, Color(0xFF14B8A6)),
};

_KindMeta _kindMeta(String? key) => _kKinds[key] ?? const _KindMeta(Icons.payments_rounded, Color(0xFF64748B));

class _PeriodChips extends StatelessWidget {
  const _PeriodChips({required this.selected, required this.onSelected});
  final String selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      for (final p in _kPeriods)
        Padding(
          padding: const EdgeInsets.only(right: 8),
          child: ChoiceChip(
            label: Text(p.$2),
            selected: selected == p.$1,
            onSelected: (_) => onSelected(p.$1),
            labelStyle: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: selected == p.$1 ? Colors.white : AppColors.textMuted,
            ),
            selectedColor: AppColors.primary,
            backgroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: const BorderSide(color: AppColors.border)),
            showCheckmark: false,
          ),
        ),
    ],
  );
}

class _ChartLoadingCard extends StatelessWidget {
  const _ChartLoadingCard();

  @override
  Widget build(BuildContext context) => Container(
    height: 180,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
    ),
    alignment: Alignment.center,
    child: const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.4)),
  );
}

class _ChartMessageCard extends StatelessWidget {
  const _ChartMessageCard({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(message, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
        const SizedBox(height: 10),
        TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh, size: 16), label: const Text('Retry')),
      ],
    ),
  );
}

List<Map<String, dynamic>> _parseList(dynamic body) {
  final list = body is Map ? (body['data'] ?? body['items'] ?? []) : body;
  if (list is! List) return [];
  return list.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
}

// ── Animated shortcut row ─────────────────────────────────────────────────

class _ShortcutRow extends StatefulWidget {
  const _ShortcutRow({required this.onCreated});
  final VoidCallback onCreated;

  @override
  State<_ShortcutRow> createState() => _ShortcutRowState();
}

class _ShortcutRowState extends State<_ShortcutRow>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 500),
  )..forward();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  static const _items = [
    (label: 'Bill', icon: Icons.receipt_long_rounded, color: Color(0xFF6366F1)),
    (label: 'Loan', icon: Icons.account_balance_rounded, color: Color(0xFF0EA5E9)),
    (label: 'Rental', icon: Icons.apartment_rounded, color: Color(0xFF10B981)),
    (label: 'Modification', icon: Icons.tune_rounded, color: Color(0xFFF59E0B)),
  ];

  Future<void> _open(int index) async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => switch (index) {
        0 => const AddBillSheet(),
        1 => const AddLoanSheet(),
        2 => const AddRentalSheet(),
        _ => const AddModificationSheet(),
      },
    );
    if (result == true) widget.onCreated();
  }

  @override
  Widget build(BuildContext context) => Row(
    children: [
      for (var i = 0; i < _items.length; i++) ...[
        if (i > 0) const SizedBox(width: 10),
        Expanded(
          child: AnimatedBuilder(
            animation: _ctrl,
            builder: (_, child) {
              final delay = i * 0.15;
              final t = (((_ctrl.value - delay) / (1 - delay)).clamp(0.0, 1.0));
              final curve = Curves.easeOutBack.transform(t);
              return Transform.scale(
                scale: curve,
                child: Opacity(opacity: t.clamp(0.0, 1.0), child: child),
              );
            },
            child: _ShortcutCard(
              label: _items[i].label,
              icon: _items[i].icon,
              color: _items[i].color,
              onTap: () => _open(i),
            ),
          ),
        ),
      ],
    ],
  );
}

class _ShortcutCard extends StatefulWidget {
  const _ShortcutCard({required this.label, required this.icon, required this.color, required this.onTap});
  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  State<_ShortcutCard> createState() => _ShortcutCardState();
}

class _ShortcutCardState extends State<_ShortcutCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _press = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 100),
    lowerBound: 0.94,
    upperBound: 1.0,
    value: 1.0,
  );

  @override
  void dispose() {
    _press.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTapDown: (_) => _press.reverse(),
    onTapUp: (_) {
      _press.forward();
      widget.onTap();
    },
    onTapCancel: () => _press.forward(),
    child: ScaleTransition(
      scale: _press,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: widget.color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: widget.color.withValues(alpha: 0.25)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: widget.color,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(color: widget.color.withValues(alpha: 0.35), blurRadius: 10, offset: const Offset(0, 4)),
                ],
              ),
              alignment: Alignment.center,
              child: Icon(widget.icon, size: 18, color: Colors.white),
            ),
            const SizedBox(height: 8),
            Text(
              widget.label,
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: widget.color),
            ),
          ],
        ),
      ),
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.title,
    required this.icon,
    required this.children,
    required this.empty,
    required this.emptyLabel,
  });

  final String title;
  final IconData icon;
  final List<Widget> children;
  final bool empty;
  final String emptyLabel;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Row(
        children: [
          Icon(icon, size: 17, color: AppColors.textMuted),
          const SizedBox(width: 8),
          Text(title, style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
        ],
      ),
      const SizedBox(height: 10),
      Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
        ),
        child: empty
            ? Padding(
                padding: const EdgeInsets.all(18),
                child: Text(emptyLabel, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
              )
            : Column(
                children: [
                  for (var i = 0; i < children.length; i++) ...[
                    if (i > 0) const Divider(height: 1, color: AppColors.border),
                    children[i],
                  ],
                ],
              ),
      ),
    ],
  );
}

class _RowTile extends StatelessWidget {
  const _RowTile({
    required this.leadingIcon,
    required this.title,
    required this.trailing,
    this.subtitle,
    this.badge,
    this.leadingColor = AppColors.primary,
  });

  final IconData leadingIcon;
  final Color leadingColor;
  final String title;
  final String? subtitle;
  final String trailing;
  final Widget? badge;

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
    leading: Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(color: leadingColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(11)),
      alignment: Alignment.center,
      child: Icon(leadingIcon, size: 17, color: leadingColor),
    ),
    title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5, color: AppColors.textDark)),
    subtitle: (subtitle == null || subtitle!.isEmpty)
        ? null
        : Text(subtitle!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
    trailing: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Text(trailing, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: AppColors.textDark)),
        if (badge != null) ...[const SizedBox(height: 3), badge!],
      ],
    ),
  );
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
    child: Text(label, style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.w800, letterSpacing: 0.3)),
  );
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(message, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
        const SizedBox(height: 10),
        TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh, size: 16), label: const Text('Retry')),
      ],
    ),
  );
}
