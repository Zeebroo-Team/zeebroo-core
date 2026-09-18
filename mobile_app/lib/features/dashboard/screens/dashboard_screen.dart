import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';
import '../widgets/stat_tile.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
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
  Widget build(BuildContext context) {
    final user = context.watch<AuthState>().user;
    final firstName = (user?['name'] as String?)?.split(' ').first ?? 'there';

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Welcome back',
                            style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
                        Text(firstName,
                            style: const TextStyle(
                                fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.textDark)),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Log out',
                    icon: const Icon(Icons.logout, color: AppColors.textMid),
                    onPressed: () => context.read<AuthState>().logout(),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              const Text("Today's overview",
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textDark)),
              const SizedBox(height: 12),
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
        ),
      ),
    );
  }

  Widget _buildStats() {
    final sales = (_summary?['sales'] as Map?) ?? {};
    final services = (_summary?['service_requests'] as Map?) ?? {};

    String money(dynamic v) => (v as num? ?? 0).toStringAsFixed(2);

    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.3,
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
      color: AppColors.card,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: AppColors.border),
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
