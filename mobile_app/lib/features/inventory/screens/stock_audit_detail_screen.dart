import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../widgets/status_chip.dart';

double? _parseNum(dynamic v) => v == null ? null : double.tryParse('$v');

class _LineState {
  _LineState({required this.line})
    : countedCtrl = TextEditingController(
        text: _parseNum(line['counted_qty'])?.let((n) => n == n.roundToDouble() ? n.toInt().toString() : n.toString()) ?? '',
      ),
      notesCtrl = TextEditingController(text: line['notes'] as String? ?? '');
  final Map<String, dynamic> line;
  final TextEditingController countedCtrl;
  final TextEditingController notesCtrl;
}

extension _Let<T> on T {
  R? let<R>(R Function(T) f) => f(this);
}

/// View/edit a stock audit: count products against their expected quantity,
/// save progress, and finalize (which adjusts stock). Pops `true` if changed.
class StockAuditDetailScreen extends StatefulWidget {
  const StockAuditDetailScreen({super.key, required this.auditId});

  final int auditId;

  @override
  State<StockAuditDetailScreen> createState() => _StockAuditDetailScreenState();
}

class _StockAuditDetailScreenState extends State<StockAuditDetailScreen> {
  bool _loading = true;
  bool _saving = false;
  bool _acting = false;
  String? _error;
  Map<String, dynamic>? _audit;
  List<_LineState> _lines = [];
  bool _changed = false;

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
      final res = await ApiClient.instance.get(ApiEndpoints.stockAudit(widget.auditId));
      final body = res.data;
      _audit = (body is Map ? body['data'] as Map? : null)?.cast<String, dynamic>();
      final rawLines = (_audit?['lines'] as List? ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      for (final l in _lines) {
        l.countedCtrl.dispose();
        l.notesCtrl.dispose();
      }
      _lines = rawLines.map((l) => _LineState(line: l)).toList();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  bool get _finalized => _audit?['status'] == 'finalized';

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    final lines = <String, dynamic>{};
    for (final l in _lines) {
      final id = l.line['id'];
      lines['$id'] = {
        if (l.countedCtrl.text.trim().isNotEmpty) 'counted_qty': double.tryParse(l.countedCtrl.text.trim()),
        if (l.notesCtrl.text.trim().isNotEmpty) 'notes': l.notesCtrl.text.trim(),
      };
    }
    try {
      await ApiClient.instance.put(ApiEndpoints.stockAuditLines(widget.auditId), data: {'lines': lines});
      _changed = true;
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Saved.')));
    } catch (e) {
      setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _finalize() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Finalize audit'),
        content: const Text('This will adjust product stock quantities to match your counts and lock this audit. Continue?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Finalize')),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _acting = true);
    try {
      await ApiClient.instance.post(ApiEndpoints.stockAuditFinalize(widget.auditId));
      _changed = true;
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
    } finally {
      if (mounted) setState(() => _acting = false);
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete audit'),
        content: const Text('Delete this stock audit?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete', style: TextStyle(color: AppColors.error))),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await ApiClient.instance.delete(ApiEndpoints.stockAudit(widget.auditId));
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop) Navigator.pop(context, _changed);
    },
    child: Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        title: Text(
          (_audit?['audit_number'] as String?) ?? 'Stock audit',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
        actions: [
          if (_audit != null && !_finalized)
            IconButton(icon: const Icon(Icons.delete_outline, color: AppColors.error), onPressed: _delete),
        ],
      ),
      body: _buildBody(),
    ),
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null || _audit == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error ?? 'Not found', style: const TextStyle(color: AppColors.textMuted)),
            const SizedBox(height: 10),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
          child: Row(
            children: [
              StatusChip(label: _finalized ? 'Finalized' : 'Open'),
              const Spacer(),
              Text(formatDate(_audit!['audit_date'] as String?), style: const TextStyle(color: AppColors.textMuted, fontSize: 12.5)),
            ],
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
            itemCount: _lines.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final l = _lines[i];
              final expected = _parseNum(l.line['expected_qty']) ?? 0;
              return Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(border: Border.all(color: AppColors.border), borderRadius: BorderRadius.circular(12)),
                child: Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            (l.line['product_name'] as String?) ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                          Text('Expected: ${expected.toStringAsFixed(expected == expected.roundToDouble() ? 0 : 1)}', style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 2,
                      child: TextField(
                        controller: l.countedCtrl,
                        enabled: !_finalized,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Counted', isDense: true),
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12.5)),
          ),
        if (!_finalized)
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _saving || _acting ? null : _save,
                    child: _saving
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.2))
                        : const Text('Save counts'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: _saving || _acting ? null : _finalize,
                    child: _acting
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white))
                        : const Text('Finalize'),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
