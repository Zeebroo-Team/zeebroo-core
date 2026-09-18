import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../../finance/widgets/bills_tab.dart';
import '../../finance/widgets/loans_tab.dart';
import '../../finance/widgets/modifications_tab.dart';
import '../../finance/widgets/rentals_tab.dart';

/// Second home tab — bills, rentals and recent payments, mirroring the
/// desktop admin panel's Expenses view (`GET /v1/pos/expenses/overview`).
class ExpensesTab extends StatefulWidget {
  const ExpensesTab({super.key});

  @override
  State<ExpensesTab> createState() => _ExpensesTabState();
}

class _ExpensesTabState extends State<ExpensesTab>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _data;
  List<Map<String, dynamic>> _loans = [];
  List<Map<String, dynamic>> _rentals = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

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
      ]);
      final raw = results[0].data;
      _data = (raw is Map ? raw['data'] : raw) as Map<String, dynamic>?;
      _loans = _parseList(results[1].data);
      _rentals = _parseList(results[2].data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
        children: [
          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 60),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _ErrorCard(message: _error!, onRetry: _load)
          else
            ..._buildContent(),
        ],
      ),
    );
  }

  List<Widget> _buildContent() {
    final summary = (_data?['summary'] as Map?) ?? {};
    final bills = ((_data?['bills'] as List?) ?? []).whereType<Map>().toList();
    final recent = ((_data?['recent_payments'] as List?) ?? []).whereType<Map>().toList();

    return [
      _SummaryCard(summary: summary),
      const SizedBox(height: 16),
      _ShortcutRow(onCreated: _load),
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
      if (_loans.isNotEmpty) ...[
        const SizedBox(height: 20),
        _SectionCard(
          title: 'Loan settlements',
          icon: Icons.account_balance_outlined,
          empty: false,
          emptyLabel: '',
          children: [
            for (final l in _loans)
              _RowTile(
                leadingIcon: Icons.account_balance_rounded,
                leadingColor: const Color(0xFF0EA5E9),
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
      ],
      if (_rentals.isNotEmpty) ...[
        const SizedBox(height: 20),
        _SectionCard(
          title: 'Rental settlements',
          icon: Icons.apartment_outlined,
          empty: false,
          emptyLabel: '',
          children: [
            for (final r in _rentals)
              _RowTile(
                leadingIcon: Icons.villa_outlined,
                leadingColor: const Color(0xFF10B981),
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
      ],
      if (recent.isNotEmpty) ...[
        const SizedBox(height: 20),
        _SectionCard(
          title: 'Recent payments',
          icon: Icons.history_rounded,
          empty: false,
          emptyLabel: '',
          children: [
            for (final p in recent)
              _RowTile(
                leadingIcon: Icons.check_circle_outline_rounded,
                leadingColor: AppColors.success,
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

// ── Summary card ──────────────────────────────────────────────────────────

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});
  final Map summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(22),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(24),
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [AppColors.primaryDk, AppColors.primary],
      ),
      boxShadow: [
        BoxShadow(color: AppColors.primaryDk.withValues(alpha: 0.28), blurRadius: 24, offset: const Offset(0, 10)),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.18),
                borderRadius: BorderRadius.circular(10),
              ),
              alignment: Alignment.center,
              child: const Icon(Icons.receipt_long_outlined, size: 18, color: Colors.white),
            ),
            const SizedBox(width: 10),
            const Expanded(
              child: Text('Expenses overview',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13.5)),
            ),
          ],
        ),
        const SizedBox(height: 22),
        const Text('Monthly total', style: TextStyle(color: Colors.white70, fontSize: 12.5)),
        const SizedBox(height: 6),
        Text(
          formatMoney(summary['bills_monthly']),
          style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w800, letterSpacing: -0.5),
        ),
        const SizedBox(height: 18),
        Row(
          children: [
            _StatChip(
              icon: Icons.description_outlined,
              label: 'Bills',
              value: '${summary['bills_count'] ?? 0}',
            ),
            const SizedBox(width: 10),
            _StatChip(
              icon: Icons.warning_amber_rounded,
              label: 'Overdue',
              value: '${summary['overdue_count'] ?? 0}',
              highlight: (summary['overdue_count'] as num? ?? 0) > 0,
            ),
            const SizedBox(width: 10),
            _StatChip(
              icon: Icons.apartment_outlined,
              label: 'Rentals / mo',
              value: formatMoney(summary['rentals_monthly']),
            ),
          ],
        ),
      ],
    ),
  );
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.icon, required this.label, required this.value, this.highlight = false});
  final IconData icon;
  final String label;
  final String value;
  final bool highlight;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: highlight
            ? AppColors.warning.withValues(alpha: 0.22)
            : Colors.white.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 13, color: Colors.white70),
          const SizedBox(height: 4),
          Text(value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800)),
          Text(label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white60, fontSize: 9.5)),
        ],
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
