import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../screens/stock_audit_detail_screen.dart';
import 'list_states.dart';
import 'picker_sheet.dart';
import 'status_chip.dart';

class StockAuditsTab extends StatefulWidget {
  const StockAuditsTab({super.key});

  @override
  State<StockAuditsTab> createState() => _StockAuditsTabState();
}

class _StockAuditsTabState extends State<StockAuditsTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  List<Map<String, dynamic>> _items = [];
  int _page = 1;
  int _lastPage = 1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _page = 1;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.stockAudits, params: {'page': 1});
      final body = res.data;
      final meta = (body is Map ? body['meta'] : null) as Map?;
      _items = parseListData(body);
      _lastPage = (meta?['last_page'] as num?)?.toInt() ?? 1;
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _page >= _lastPage) return;
    setState(() => _loadingMore = true);
    try {
      final nextPage = _page + 1;
      final res = await ApiClient.instance.get(ApiEndpoints.stockAudits, params: {'page': nextPage});
      setState(() {
        _items = [..._items, ...parseListData(res.data)];
        _page = nextPage;
      });
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openCreate() async {
    final notesCtrl = TextEditingController();
    var date = DateTime.now();
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
          child: Container(
            decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'New stock audit',
                      style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Snapshots every active product\'s current stock so you can count and reconcile.',
                      style: TextStyle(fontSize: 12.5, color: AppColors.textMuted),
                    ),
                    const SizedBox(height: 18),
                    InkWell(
                      onTap: () async {
                        final d = await pickDate(ctx, initial: date);
                        if (d != null) setSheetState(() => date = d);
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'Audit date'),
                        child: Text(formatDate(date.toIso8601String())),
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: notesCtrl,
                      maxLines: 2,
                      decoration: const InputDecoration(labelText: 'Notes (optional)'),
                    ),
                    const SizedBox(height: 18),
                    ElevatedButton(
                      onPressed: () async {
                        try {
                          await ApiClient.instance.post(
                            ApiEndpoints.stockAudits,
                            data: {
                              'audit_date': toApiDate(date),
                              if (notesCtrl.text.trim().isNotEmpty) 'notes': notesCtrl.text.trim(),
                            },
                          );
                          if (ctx.mounted) Navigator.pop(ctx, true);
                        } catch (e) {
                          if (ctx.mounted) {
                            ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
                          }
                        }
                      },
                      child: const Text('Start audit'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
    if (created == true) _load();
  }

  Future<void> _openDetail(Map<String, dynamic> item) async {
    final id = (item['id'] as num?)?.toInt();
    if (id == null) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => StockAuditDetailScreen(auditId: id)),
    );
    if (changed == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
          child: Row(
            children: [
              const Expanded(
                child: Text('Stock audits', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textDark)),
              ),
              AddButton(onTap: _openCreate),
            ],
          ),
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_items.isEmpty) return const EmptyState(message: 'No stock audits yet.');
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _items.length + (_page < _lastPage ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          if (i >= _items.length) {
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Center(
                child: _loadingMore
                    ? const CircularProgressIndicator(strokeWidth: 2.4)
                    : TextButton(onPressed: _loadMore, child: const Text('Load more')),
              ),
            );
          }
          final item = _items[i];
          final status = (item['status'] as String?) ?? 'open';
          return InkWell(
            onTap: () => _openDetail(item),
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
                          (item['audit_number'] as String?) ?? '',
                          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${formatDate(item['audit_date'] as String?)} · ${item['lines_count'] ?? 0} lines',
                          style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                        ),
                      ],
                    ),
                  ),
                  StatusChip(label: status == 'finalized' ? 'Finalized' : 'Open'),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
