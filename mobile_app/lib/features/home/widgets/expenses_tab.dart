import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../../dashboard/widgets/stat_tile.dart';

/// Second home tab — bills, rentals and recent payments, mirroring the
/// desktop admin panel's Expenses view (`GET /v1/pos/expenses/overview`).
class ExpensesTab extends StatefulWidget {
  const ExpensesTab({super.key});

  @override
  State<ExpensesTab> createState() => _ExpensesTabState();
}

class _ExpensesTabState extends State<ExpensesTab> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.expensesOverview);
      final raw = res.data;
      _data = (raw is Map ? raw['data'] : raw) as Map<String, dynamic>?;
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: _load,
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

  List<Widget> _buildContent() {
    final summary = (_data?['summary'] as Map?) ?? {};
    final bills = ((_data?['bills'] as List?) ?? []).whereType<Map>().toList();
    final rentals = ((_data?['rentals'] as List?) ?? []).whereType<Map>().toList();
    final recent = ((_data?['recent_payments'] as List?) ?? []).whereType<Map>().toList();

    return [
      StatGrid(
        tiles: [
          StatTile(
            label: 'Monthly total',
            value: formatMoney(summary['bills_monthly']),
            icon: Icons.receipt_long_outlined,
          ),
          StatTile(
            label: 'Active bills',
            value: '${summary['bills_count'] ?? 0}',
            icon: Icons.description_outlined,
            color: AppColors.primary,
          ),
          StatTile(
            label: 'Overdue',
            value: '${summary['overdue_count'] ?? 0}',
            icon: Icons.warning_amber_rounded,
            color: AppColors.warning,
          ),
          StatTile(
            label: 'Rentals / mo',
            value: formatMoney(summary['rentals_monthly']),
            icon: Icons.apartment_outlined,
            color: AppColors.error,
          ),
        ],
      ),
      const SizedBox(height: 12),
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
        title: 'Rentals',
        icon: Icons.apartment_outlined,
        empty: rentals.isEmpty,
        emptyLabel: 'No rentals yet.',
        children: [
          for (final r in rentals)
            _RowTile(
              leadingIcon: Icons.villa_outlined,
              title: r['name'] as String? ?? '',
              trailing: formatMoney(r['amount']),
            ),
        ],
      ),
      const SizedBox(height: 20),
      _SectionCard(
        title: 'Recent payments',
        icon: Icons.history_rounded,
        empty: recent.isEmpty,
        emptyLabel: 'No payments recorded yet.',
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
    ];
  }
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
