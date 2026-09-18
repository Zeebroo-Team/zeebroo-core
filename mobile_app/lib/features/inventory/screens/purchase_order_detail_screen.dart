import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../widgets/status_chip.dart';

/// View a purchase order and drive its workflow: place / receive / cancel.
/// Pops `true` if anything changed, so the list tab knows to refresh.
class PurchaseOrderDetailScreen extends StatefulWidget {
  const PurchaseOrderDetailScreen({super.key, required this.purchaseOrderId});

  final int purchaseOrderId;

  @override
  State<PurchaseOrderDetailScreen> createState() => _PurchaseOrderDetailScreenState();
}

class _PurchaseOrderDetailScreenState extends State<PurchaseOrderDetailScreen> {
  bool _loading = true;
  bool _acting = false;
  String? _error;
  Map<String, dynamic>? _po;
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
      final res = await ApiClient.instance.get(ApiEndpoints.purchaseOrder(widget.purchaseOrderId));
      final body = res.data;
      _po = (body is Map ? body['data'] as Map? : null)?.cast<String, dynamic>();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _act(String action, Future<void> Function() call, {String? confirmMessage}) async {
    if (confirmMessage != null) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(action),
          content: Text(confirmMessage),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text(action)),
          ],
        ),
      );
      if (confirmed != true) return;
    }
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
          (_po?['po_number'] as String?) ?? 'Purchase order',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
      ),
      body: _buildBody(),
    ),
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null || _po == null) {
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
    final po = _po!;
    final status = po['status'] as String? ?? '';
    final items = (po['items'] as List? ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          Row(
            children: [
              StatusChip(label: (po['status_label'] as String?) ?? status),
              const Spacer(),
              Text(formatDate(po['purchase_date'] as String?), style: const TextStyle(color: AppColors.textMuted, fontSize: 12.5)),
            ],
          ),
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
                _kv('Supplier', (po['supplier_name'] as String?) ?? '—'),
                const Divider(height: 1, color: AppColors.border),
                _kv('Expected delivery', formatDate(po['expected_delivery_date'] as String?)),
                const Divider(height: 1, color: AppColors.border),
                _kv('Total', ((po['total'] as num?) ?? 0).toStringAsFixed(2)),
              ],
            ),
          ),
          if ((po['notes'] as String?)?.isNotEmpty == true) ...[
            const SizedBox(height: 14),
            const Text('Notes', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
            const SizedBox(height: 6),
            Text(po['notes'] as String, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
          ],
          const SizedBox(height: 20),
          const Text('Items', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
          const SizedBox(height: 8),
          for (final item in items)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(border: Border.all(color: AppColors.border), borderRadius: BorderRadius.circular(12)),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            (item['product_name'] as String?) ?? '',
                            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
                          ),
                          Text(
                            'Qty ${item['quantity']} × ${((item['unit_cost'] as num?) ?? 0).toStringAsFixed(2)}',
                            style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                          ),
                        ],
                      ),
                    ),
                    Text(
                      ((item['line_total'] as num?) ?? 0).toStringAsFixed(2),
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                    ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 20),
          if (_acting)
            const Center(child: CircularProgressIndicator())
          else
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (status == 'draft')
                  ElevatedButton(
                    onPressed: () => _act(
                      'Place order',
                      () => ApiClient.instance.post(ApiEndpoints.purchaseOrderPlace(widget.purchaseOrderId)),
                    ),
                    child: const Text('Place order'),
                  ),
                if (status == 'ordered' || status == 'partially_received')
                  ElevatedButton(
                    onPressed: () => _act(
                      'Receive',
                      () => ApiClient.instance.post(ApiEndpoints.purchaseOrderReceive(widget.purchaseOrderId)),
                      confirmMessage: 'This will create a goods receive note for all remaining items on credit. Continue?',
                    ),
                    child: const Text('Receive all'),
                  ),
                if (status == 'draft' || status == 'ordered')
                  OutlinedButton(
                    onPressed: () => _act(
                      'Cancel order',
                      () => ApiClient.instance.post(ApiEndpoints.purchaseOrderCancel(widget.purchaseOrderId)),
                      confirmMessage: 'Cancel this purchase order?',
                    ),
                    style: OutlinedButton.styleFrom(foregroundColor: AppColors.error, side: const BorderSide(color: AppColors.error)),
                    child: const Text('Cancel order'),
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
