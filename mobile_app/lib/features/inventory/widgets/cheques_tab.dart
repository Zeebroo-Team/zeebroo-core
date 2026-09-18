import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import 'cheque_clear_sheet.dart';
import 'list_states.dart';
import 'picker_sheet.dart';
import 'status_chip.dart';

const _kFilters = [
  (label: 'All', value: 'all'),
  (label: 'Pending', value: 'pending'),
  (label: 'Due', value: 'due'),
  (label: 'Overdue', value: 'overdue'),
  (label: 'Cleared', value: 'cleared'),
];

class ChequesTab extends StatefulWidget {
  const ChequesTab({super.key});

  @override
  State<ChequesTab> createState() => _ChequesTabState();
}

class _ChequesTabState extends State<ChequesTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  String _filter = 'all';
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];
  Map<String, dynamic> _summary = {};

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
      final res = await ApiClient.instance.get(ApiEndpoints.cheques, params: {'filter': _filter});
      final body = res.data;
      _items = parseListData(body);
      _summary = (body is Map ? body['summary'] as Map? : null)?.cast<String, dynamic>() ?? {};
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openClear(Map<String, dynamic> cheque) async {
    final cleared = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => ChequeClearSheet(cheque: cheque),
    );
    if (cleared == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        if (_summary.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
            child: Row(
              children: [
                Expanded(child: _SummaryTile(label: 'Pending', value: '${_summary['pending'] ?? 0}', color: AppColors.warning)),
                const SizedBox(width: 10),
                Expanded(child: _SummaryTile(label: 'Overdue', value: '${_summary['overdue'] ?? 0}', color: AppColors.error)),
                const SizedBox(width: 10),
                Expanded(
                  child: _SummaryTile(
                    label: 'Pending amount',
                    value: ((_summary['pending_amount'] as num?) ?? 0).toStringAsFixed(2),
                    color: AppColors.primary,
                  ),
                ),
              ],
            ),
          ),
        SizedBox(
          height: 52,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
            itemCount: _kFilters.length,
            separatorBuilder: (_, _) => const SizedBox(width: 8),
            itemBuilder: (context, i) {
              final filter = _kFilters[i];
              final selected = _filter == filter.value;
              return ChoiceChip(
                label: Text(filter.label),
                selected: selected,
                onSelected: (_) {
                  setState(() => _filter = filter.value);
                  _load();
                },
              );
            },
          ),
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_items.isEmpty) return const EmptyState(message: 'No cheques found.');
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final item = _items[i];
          final cleared = item['status'] == 'cleared';
          return InkWell(
            onTap: cleared ? null : () => _openClear(item),
            borderRadius: BorderRadius.circular(14),
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 12, offset: Offset(0, 3))],
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          (item['cheque_number'] as String?) ?? '',
                          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${item['supplier_name'] ?? item['grn_number'] ?? '—'} · Due ${formatDate(item['due_date'] as String?)}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      StatusChip(label: (item['status_label'] as String?) ?? ''),
                      const SizedBox(height: 6),
                      Text(
                        ((item['amount'] as num?) ?? 0).toStringAsFixed(2),
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({required this.label, required this.value, required this.color});
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 10, offset: Offset(0, 3))],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: color)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(fontSize: 10.5, color: AppColors.textMuted)),
      ],
    ),
  );
}
