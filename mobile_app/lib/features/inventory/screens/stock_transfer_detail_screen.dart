import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../widgets/status_chip.dart';

/// View a stock transfer and drive receive/cancel. Pops `true` if changed.
class StockTransferDetailScreen extends StatefulWidget {
  const StockTransferDetailScreen({super.key, required this.transferId});

  final int transferId;

  @override
  State<StockTransferDetailScreen> createState() => _StockTransferDetailScreenState();
}

class _StockTransferDetailScreenState extends State<StockTransferDetailScreen> {
  bool _loading = true;
  bool _acting = false;
  String? _error;
  Map<String, dynamic>? _transfer;
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
      final res = await ApiClient.instance.get(ApiEndpoints.stockTransfer(widget.transferId));
      final body = res.data;
      _transfer = (body is Map ? body['data'] as Map? : null)?.cast<String, dynamic>();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _act(String label, Future<void> Function() call, String confirmMessage) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(label),
        content: Text(confirmMessage),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text(label)),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _acting = true);
    try {
      await call();
      _changed = true;
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
    } finally {
      if (mounted) setState(() => _acting = false);
    }
  }

  double? _num(dynamic v) => v == null ? null : double.tryParse('$v');

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
          (_transfer?['transfer_number'] as String?) ?? 'Stock transfer',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
      ),
      body: _buildBody(),
    ),
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null || _transfer == null) {
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
    final t = _transfer!;
    final status = t['status'] as String? ?? '';
    final lines = (t['lines'] as List? ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    final fromBranch = (t['fromBranch'] as Map?)?['name'] ?? t['from_branch_id'];
    final toBranch = (t['toBranch'] as Map?)?['name'] ?? t['to_branch_id'];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          Row(children: [StatusChip(label: status), const Spacer()]),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 12, offset: Offset(0, 3))],
            ),
            child: Column(
              children: [
                _kv('From', '$fromBranch'),
                const Divider(height: 1, color: AppColors.border),
                _kv('To', '$toBranch'),
              ],
            ),
          ),
          if ((t['notes'] as String?)?.isNotEmpty == true) ...[
            const SizedBox(height: 14),
            const Text('Notes', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
            const SizedBox(height: 6),
            Text(t['notes'] as String, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
          ],
          const SizedBox(height: 20),
          const Text('Items', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
          const SizedBox(height: 8),
          for (final l in lines)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(border: Border.all(color: AppColors.border), borderRadius: BorderRadius.circular(12)),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        (l['product_name'] as String?) ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
                      ),
                    ),
                    Text('Qty ${_num(l['quantity'])?.toStringAsFixed(2) ?? '—'}', style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted)),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 20),
          if (_acting)
            const Center(child: CircularProgressIndicator())
          else if (status == 'in_transit')
            Wrap(
              spacing: 10,
              children: [
                ElevatedButton(
                  onPressed: () => _act(
                    'Mark received',
                    () => ApiClient.instance.post(ApiEndpoints.stockTransferReceive(widget.transferId)),
                    'Confirm the destination branch has received this stock?',
                  ),
                  child: const Text('Mark received'),
                ),
                OutlinedButton(
                  onPressed: () => _act(
                    'Cancel transfer',
                    () => ApiClient.instance.post(ApiEndpoints.stockTransferCancel(widget.transferId)),
                    'Cancel this transfer and restore stock at the source branch?',
                  ),
                  style: OutlinedButton.styleFrom(foregroundColor: AppColors.error, side: const BorderSide(color: AppColors.error)),
                  child: const Text('Cancel transfer'),
                ),
              ],
            ),
        ],
      ),
    );
  }

  Widget _kv(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 10),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
      ],
    ),
  );
}
