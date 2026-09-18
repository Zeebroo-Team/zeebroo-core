import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../../dashboard/widgets/stat_tile.dart';
import 'trend_chart.dart';

const _kPeriods = [
  (7, '7d'),
  (30, '30d'),
  (90, '90d'),
  (365, '1y'),
];

/// Third home tab — revenue/COGS/margin summary, a profit trend chart and
/// top products by profit, mirroring the desktop admin panel's Profit
/// report (`GET /v1/pos/profit-report`).
class ProfitTab extends StatefulWidget {
  const ProfitTab({super.key});

  @override
  State<ProfitTab> createState() => _ProfitTabState();
}

class _ProfitTabState extends State<ProfitTab> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _data;
  int _period = 30;

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
      final res = await ApiClient.instance.get(ApiEndpoints.profitReport, params: {'period': _period});
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
        _buildPeriodSelector(),
        const SizedBox(height: 18),
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

  Widget _buildPeriodSelector() => Row(
    children: [
      for (final p in _kPeriods)
        Padding(
          padding: const EdgeInsets.only(right: 8),
          child: ChoiceChip(
            label: Text(p.$2),
            selected: _period == p.$1,
            onSelected: (_) {
              setState(() => _period = p.$1);
              _load();
            },
            labelStyle: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: _period == p.$1 ? Colors.white : AppColors.textMuted,
            ),
            selectedColor: AppColors.primary,
            backgroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: const BorderSide(color: AppColors.border)),
            showCheckmark: false,
          ),
        ),
    ],
  );

  List<Widget> _buildContent() {
    final summary = (_data?['summary'] as Map?) ?? {};
    final trend = (_data?['trend'] as Map?) ?? {};
    final topProducts = ((_data?['top_products'] as List?) ?? []).whereType<Map>().toList();

    final netProfit = (summary['net_profit'] as num? ?? 0).toDouble();

    return [
      StatGrid(
        tiles: [
          StatTile(label: 'Revenue', value: formatMoney(summary['revenue']), icon: Icons.payments_outlined),
          StatTile(label: 'COGS', value: formatMoney(summary['cogs']), icon: Icons.inventory_2_outlined, color: AppColors.warning),
          StatTile(label: 'Gross margin', value: '${summary['gross_margin'] ?? 0}%', icon: Icons.percent_rounded, color: AppColors.primary),
          StatTile(label: 'Gross profit', value: formatMoney(summary['gross_profit']), icon: Icons.trending_up_rounded, color: AppColors.success),
          StatTile(label: 'Expenses paid', value: formatMoney(summary['expenses']), icon: Icons.receipt_long_outlined, color: AppColors.error),
          StatTile(
            label: 'Net profit',
            value: formatMoney(summary['net_profit']),
            icon: netProfit >= 0 ? Icons.arrow_upward_rounded : Icons.arrow_downward_rounded,
            color: netProfit >= 0 ? AppColors.success : AppColors.error,
          ),
        ],
      ),
      const SizedBox(height: 12),
      _buildTrendCard(trend),
      const SizedBox(height: 24),
      _buildTopProducts(topProducts),
    ];
  }

  Widget _buildTrendCard(Map trend) {
    final revenue = ((trend['revenue'] as List?) ?? []).map((v) => (v as num).toDouble()).toList();
    final grossProfit = ((trend['gross_profit'] as List?) ?? []).map((v) => (v as num).toDouble()).toList();
    final expenses = ((trend['expenses'] as List?) ?? []).map((v) => (v as num).toDouble()).toList();
    final hasData = revenue.length > 1;

    final chartSeries = [
      TrendSeries(label: 'Revenue', color: AppColors.primary, values: revenue),
      TrendSeries(label: 'Gross profit', color: AppColors.success, values: grossProfit),
      TrendSeries(label: 'Expenses', color: AppColors.error, values: expenses),
    ];

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Profit trend', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
          const SizedBox(height: 14),
          if (!hasData)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 30),
              child: Center(child: Text('Not enough data yet.', style: TextStyle(color: AppColors.textMuted, fontSize: 13))),
            )
          else
            TrendChart(series: chartSeries),
          const SizedBox(height: 14),
          TrendLegend(series: chartSeries),
        ],
      ),
    );
  }

  Widget _buildTopProducts(List<Map> products) {
    final maxGp = products.isEmpty
        ? 1.0
        : products.map((p) => (p['gp'] as num? ?? 0).toDouble()).reduce((a, b) => a > b ? a : b).clamp(1.0, double.infinity);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Top products by profit', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
        const SizedBox(height: 12),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
          ),
          child: products.isEmpty
              ? const Padding(
                  padding: EdgeInsets.all(18),
                  child: Text('No product sales in this period.', style: TextStyle(color: AppColors.textMuted, fontSize: 13)),
                )
              : Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Column(
                    children: [
                      for (var i = 0; i < products.length; i++) ...[
                        if (i > 0) const Divider(height: 1, color: AppColors.border),
                        _ProductRow(rank: i + 1, product: products[i], maxGp: maxGp),
                      ],
                    ],
                  ),
                ),
        ),
      ],
    );
  }
}

class _ProductRow extends StatelessWidget {
  const _ProductRow({required this.rank, required this.product, required this.maxGp});
  final int rank;
  final Map product;
  final double maxGp;

  @override
  Widget build(BuildContext context) {
    final gp = (product['gp'] as num? ?? 0).toDouble();
    final margin = product['margin'] as num? ?? 0;
    final fraction = maxGp > 0 ? (gp / maxGp).clamp(0.0, 1.0) : 0.0;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 22,
            height: 22,
            margin: const EdgeInsets.only(top: 1),
            decoration: BoxDecoration(color: AppColors.primaryLt, borderRadius: BorderRadius.circular(7)),
            alignment: Alignment.center,
            child: Text('$rank', style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: AppColors.primaryDk)),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(product['name'] as String? ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppColors.textDark)),
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(3),
                  child: LinearProgressIndicator(
                    value: fraction,
                    minHeight: 5,
                    backgroundColor: AppColors.primaryLt,
                    valueColor: const AlwaysStoppedAnimation(AppColors.primary),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(formatMoney(gp), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: AppColors.success)),
              const SizedBox(height: 2),
              Text('$margin%', style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
            ],
          ),
        ],
      ),
    );
  }
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
