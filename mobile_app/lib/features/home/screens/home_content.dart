import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../dashboard/widgets/stat_tile.dart';

/// The "Home" tab content inside [HomeShell] — today's business overview.
/// The shell owns the header (greeting/avatar) and navigation chrome, so
/// this widget is just the scrollable body.
class HomeContent extends StatefulWidget {
  const HomeContent({super.key});

  @override
  State<HomeContent> createState() => _HomeContentState();
}

class _HomeContentState extends State<HomeContent> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _summary;

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
      final res = await ApiClient.instance.get(ApiEndpoints.todaySummary);
      final data = res.data;
      _summary = (data is Map ? data['data'] : data) as Map<String, dynamic>?;
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
        const Text("Today's overview",
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textDark)),
        const SizedBox(height: 4),
        const Text('A quick look at how business is going today.',
            style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
        const SizedBox(height: 18),
        if (_loading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 40),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_error != null)
          _ErrorCard(message: _error!, onRetry: _load)
        else
          _buildStats(),
      ],
    ),
  );

  Widget _buildStats() {
    final sales = (_summary?['sales'] as Map?) ?? {};
    final services = (_summary?['service_requests'] as Map?) ?? {};

    String money(dynamic v) => (v as num? ?? 0).toStringAsFixed(2);

    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 14,
      crossAxisSpacing: 14,
      childAspectRatio: 1.25,
      children: [
        StatTile(
          label: 'Sales today',
          value: '${sales['count'] ?? 0}',
          icon: Icons.receipt_long_outlined,
        ),
        StatTile(
          label: 'Revenue',
          value: money(sales['revenue']),
          icon: Icons.payments_outlined,
          color: AppColors.success,
        ),
        StatTile(
          label: 'Gross profit',
          value: money(sales['gross_profit']),
          icon: Icons.trending_up_rounded,
          color: AppColors.warning,
        ),
        StatTile(
          label: 'Pending services',
          value: '${services['pending'] ?? 0}',
          icon: Icons.build_outlined,
          color: AppColors.error,
        ),
      ],
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
        TextButton.icon(
          onPressed: onRetry,
          icon: const Icon(Icons.refresh, size: 16),
          label: const Text('Retry'),
        ),
      ],
    ),
  );
}
