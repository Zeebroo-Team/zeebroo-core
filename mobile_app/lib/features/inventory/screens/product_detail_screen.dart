import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../widgets/product_form_sheet.dart';
import '../widgets/status_chip.dart';

/// View/edit/delete a single product, including its stock batches (each
/// batch's barcode can be edited inline). Pops `true` if the product was
/// edited or deleted, so the Products list knows to refresh.
class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({super.key, required this.productId});

  final int productId;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  bool _loading = true;
  bool _deleting = false;
  String? _error;
  Map<String, dynamic>? _product;
  bool _changed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.product(widget.productId),
        bypassCache: forceRefresh,
      );
      final body = res.data;
      _product = (body is Map ? body['data'] as Map? : body as Map?)
          ?.cast<String, dynamic>();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _batches {
    final p = _product;
    if (p == null) return [];
    final raw = p['stock_layers'] ?? p['batches'] ?? p['layers'];
    if (raw is! List) return [];
    return raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }

  Future<void> _edit() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => ProductFormSheet(existing: _product),
    );
    if (saved == true) {
      _changed = true;
      _load();
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete product'),
        content: Text(
          'Delete "${_product?['name'] ?? 'this product'}"? This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete', style: TextStyle(color: AppColors.error)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _deleting = true);
    try {
      await ApiClient.instance.delete(ApiEndpoints.product(widget.productId));
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(apiErrorMessage(e))),
        );
      }
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

  Future<void> _editBarcode(Map<String, dynamic> batch) async {
    final layerId = (batch['id'] as num?)?.toInt();
    if (layerId == null) return;
    final controller = TextEditingController(
      text: (batch['barcode'] as String?) ?? '',
    );
    final newValue = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit barcode'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Barcode value'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    if (newValue == null) return;
    try {
      await ApiClient.instance.patch(
        ApiEndpoints.productStockLayerBarcode(widget.productId, layerId),
        data: {'barcode': newValue},
      );
      _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(apiErrorMessage(e))),
        );
      }
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
          (_product?['name'] as String?) ?? 'Product',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
        actions: [
          if (_product != null) ...[
            IconButton(icon: const Icon(Icons.edit_outlined), onPressed: _edit),
            IconButton(
              icon: _deleting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.delete_outline, color: AppColors.error),
              onPressed: _deleting ? null : _delete,
            ),
          ],
        ],
      ),
      body: _buildBody(),
    ),
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null || _product == null) {
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
    final p = _product!;
    final unitPrice = (p['unit_price'] as num?) ?? (p['unit_sell_price'] as num?);
    final costPrice = p['cost_price'] as num?;
    final wholesalePrice = p['wholesale_price'] as num?;
    final stockQty = p['stock_quantity'] as num?;
    final isActive = (p['is_active'] as bool?) ?? true;

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          Row(
            children: [
              Text(
                (p['sku'] as String?)?.isNotEmpty == true ? p['sku'] as String : 'No SKU',
                style: const TextStyle(color: AppColors.textMuted, fontSize: 12.5),
              ),
              const Spacer(),
              StatusChip(label: isActive ? 'Active' : 'Inactive'),
            ],
          ),
          const SizedBox(height: 16),
          _InfoCard(
            rows: [
              ('Selling price', unitPrice != null ? unitPrice.toStringAsFixed(2) : '—'),
              ('Cost price', costPrice != null ? costPrice.toStringAsFixed(2) : '—'),
              ('Wholesale price', wholesalePrice != null ? wholesalePrice.toStringAsFixed(2) : '—'),
              ('Stock quantity', stockQty != null ? stockQty.toString() : '—'),
            ],
          ),
          if ((p['description'] as String?)?.isNotEmpty == true) ...[
            const SizedBox(height: 16),
            const Text('Description', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
            const SizedBox(height: 6),
            Text(p['description'] as String, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
          ],
          const SizedBox(height: 20),
          const Text('Batches', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
          const SizedBox(height: 8),
          if (_batches.isEmpty)
            const Text('No batch data.', style: TextStyle(color: AppColors.textMuted, fontSize: 13))
          else
            ..._batches.map(
              (batch) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _BatchRow(batch: batch, onEditBarcode: () => _editBarcode(batch)),
              ),
            ),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.rows});
  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 12, offset: Offset(0, 3))],
    ),
    child: Column(
      children: [
        for (var i = 0; i < rows.length; i++) ...[
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(rows[i].$1, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
                Text(rows[i].$2, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
              ],
            ),
          ),
          if (i < rows.length - 1) const Divider(height: 1, color: AppColors.border),
        ],
      ],
    ),
  );
}

class _BatchRow extends StatelessWidget {
  const _BatchRow({required this.batch, required this.onEditBarcode});
  final Map<String, dynamic> batch;
  final VoidCallback onEditBarcode;

  @override
  Widget build(BuildContext context) {
    final qty = batch['quantity'] as num?;
    final cost = batch['cost_price'] as num?;
    final barcode = (batch['barcode'] as String?) ?? '';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Qty ${qty ?? '—'}${cost != null ? ' · Cost ${cost.toStringAsFixed(2)}' : ''}',
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 2),
                Text(
                  barcode.isEmpty ? 'No barcode' : barcode,
                  style: TextStyle(
                    fontSize: 12,
                    color: barcode.isEmpty ? AppColors.textHint : AppColors.textMuted,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.qr_code_2_outlined, size: 20),
            onPressed: onEditBarcode,
          ),
        ],
      ),
    );
  }
}
