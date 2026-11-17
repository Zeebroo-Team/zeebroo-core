import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/offline/offline_sales.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';

const _kWeekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const _kMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/// Compact, flat "today's sales" strip shown under the account balance on the
/// Overview tab — revenue, sale/item counts, gross profit and the
/// cash/card/credit split, from `GET /v1/pos/today-summary` (the same feed as
/// the desktop home panel).
class TodaySalesOverview extends StatefulWidget {
  const TodaySalesOverview({super.key});

  @override
  State<TodaySalesOverview> createState() => TodaySalesOverviewState();
}

class TodaySalesOverviewState extends State<TodaySalesOverview> {
  String? _error;
  Map<String, dynamic> _sales = const {};

  /// Today's sales made offline that the server doesn't have yet — added on
  /// top of the server's figures (once uploaded they're in the server's).
  ({int count, double total, double items, Map<String, double> byMethod}) _waiting =
      (count: 0, total: 0.0, items: 0.0, byMethod: const {});
  int _lastPending = 0;

  @override
  void initState() {
    super.initState();
    OfflineSales.instance.addListener(_onOfflineChanged);
    reload();
  }

  @override
  void dispose() {
    OfflineSales.instance.removeListener(_onOfflineChanged);
    super.dispose();
  }

  void _onOfflineChanged() {
    final pending = OfflineSales.instance.waitingCount;
    // Fewer waiting: some just uploaded, so the server's totals changed too.
    if (pending < _lastPending) {
      reload(force: true);
    } else {
      _loadWaiting().then((_) {
        if (mounted) setState(() {});
      });
    }
    _lastPending = pending;
  }

  Future<void> _loadWaiting() async {
    try {
      _waiting = await OfflineSales.instance.todayWaiting();
    } catch (_) {}
  }

  Future<void> reload({bool force = false}) async {
    setState(() {
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.todaySummary, bypassCache: force);
      final raw = res.data;
      final data = raw is Map ? raw['data'] : null;
      final sales = data is Map ? data['sales'] : null;
      _sales = sales is Map ? Map<String, dynamic>.from(sales) : const {};
    } catch (e) {
      _error = apiErrorMessage(e);
      _sales = const {};
    } finally {
      // Read after the server so an upload finishing in between is not
      // counted twice (it has left the waiting list by now).
      await _loadWaiting();
      _lastPending = OfflineSales.instance.waitingCount;
      if (mounted) setState(() {});
    }
  }

  static double _toDouble(dynamic v) {
    final value = v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;
    return value.isFinite ? value : 0;
  }

  double _methodTotal(String method) {
    final by = _sales['by_method'];
    final entry = by is Map ? by[method] : null;
    return (entry is Map ? _toDouble(entry['total']) : 0) + (_waiting.byMethod[method] ?? 0);
  }

  static String _dateLabel() {
    final now = DateTime.now();
    return '${_kWeekdays[now.weekday - 1]}, ${now.day} ${_kMonths[now.month - 1]}';
  }

  @override
  Widget build(BuildContext context) {
    // Offline but with sales on the phone: still show what was sold here.
    return _error != null && _waiting.count == 0 ? _buildError() : _buildBody();
  }

  Widget _buildError() => Row(
    children: [
      const Icon(Icons.cloud_off_rounded, size: 16, color: AppColors.textMuted),
      const SizedBox(width: 8),
      Expanded(
        child: Text(_error!, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
      ),
      TextButton(onPressed: () => reload(force: true), child: const Text('Retry')),
    ],
  );

  Widget _buildBody() {
    final revenue = _toDouble(_sales['revenue']) + _waiting.total;
    final profit = _toDouble(_sales['gross_profit']);
    final count = _toDouble(_sales['count']).toInt() + _waiting.count;
    final items = (_toDouble(_sales['items_sold']) + _waiting.items).round();
    final serverDown = _error != null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text("Today's sales", style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: AppColors.textMuted)),
            ),
            Text(_dateLabel(), style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
          ],
        ),
        const SizedBox(height: 4),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Flexible(
              child: TweenAnimationBuilder<double>(
                tween: Tween(begin: 0, end: revenue),
                duration: const Duration(milliseconds: 700),
                curve: Curves.easeOutCubic,
                builder: (_, value, _) => FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    formatMoney(value),
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.textDark, letterSpacing: -0.3, height: 1.15),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Align(
                alignment: Alignment.centerRight,
                child: count == 0
                    ? const Text('No sales yet', style: TextStyle(fontSize: 11.5, color: AppColors.textMuted))
                    : Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            '$count ${count == 1 ? 'sale' : 'sales'} · $items ${items == 1 ? 'item' : 'items'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted),
                          ),
                          const SizedBox(height: 2),
                          // Profit needs cost prices the phone doesn't have offline.
                          if (!serverDown) _ProfitInline(profit: profit),
                        ],
                      ),
              ),
            ),
          ],
        ),
        ..._buildSplit(),
        if (_waiting.count > 0) ...[
          const SizedBox(height: 8),
          Row(
            key: const ValueKey('today-sales-offline-note'),
            children: [
              Icon(serverDown ? Icons.cloud_off_rounded : Icons.cloud_queue_rounded, size: 14, color: AppColors.warning),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  serverDown
                      ? 'Offline — showing only the ${_waiting.count} sale${_waiting.count == 1 ? '' : 's'} saved on this phone'
                      : 'Includes ${_waiting.count} offline sale${_waiting.count == 1 ? '' : 's'} waiting to upload',
                  style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: AppColors.warning),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }

  List<Widget> _buildSplit() {
    final segments = [
      (label: 'Cash', color: AppColors.success, total: _methodTotal('cash')),
      (label: 'Card', color: AppColors.primary, total: _methodTotal('card')),
      (label: 'Credit', color: AppColors.warning, total: _methodTotal('credit')),
    ].where((s) => s.total > 0).toList();
    if (segments.isEmpty) return const [];

    return [
      const SizedBox(height: 10),
      ClipRRect(
        borderRadius: BorderRadius.circular(4),
        child: SizedBox(
          height: 5,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final s in segments)
                Expanded(flex: (s.total * 100).round().clamp(1, 1 << 30), child: ColoredBox(color: s.color)),
            ],
          ),
        ),
      ),
      const SizedBox(height: 7),
      Wrap(
        spacing: 14,
        runSpacing: 4,
        children: [
          for (final s in segments) _LegendDot(color: s.color, text: '${s.label} ${formatMoney(s.total)}'),
        ],
      ),
    ];
  }
}

class _ProfitInline extends StatelessWidget {
  const _ProfitInline({required this.profit});
  final double profit;

  @override
  Widget build(BuildContext context) {
    final positive = profit >= 0;
    final color = positive ? AppColors.success : AppColors.error;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(positive ? Icons.trending_up_rounded : Icons.trending_down_rounded, size: 13, color: color),
        const SizedBox(width: 3),
        Flexible(
          child: Text(
            '${formatMoney(profit)} profit',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: color),
          ),
        ),
      ],
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({required this.color, required this.text});
  final Color color;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(width: 7, height: 7, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
      const SizedBox(width: 5),
      Text(text, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: AppColors.textMuted)),
    ],
  );
}
