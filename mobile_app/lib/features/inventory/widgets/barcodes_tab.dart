import 'package:barcode_widget/barcode_widget.dart';
import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import 'list_states.dart';
import 'picker_sheet.dart';

/// Search a product, then view/edit the barcode on each of its stock
/// batches — mirrors the Electron app's barcode-sheet workflow, scoped to
/// "view and edit" (no label-sheet printing on mobile).
class BarcodesTab extends StatefulWidget {
  const BarcodesTab({super.key});

  @override
  State<BarcodesTab> createState() => _BarcodesTabState();
}

class _BarcodesTabState extends State<BarcodesTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  Map<String, dynamic>? _product;
  bool _loadingBatches = false;
  String? _error;
  List<Map<String, dynamic>> _batches = [];

  Future<void> _pickProduct() async {
    final picked = await showModalBottomSheet<List<Map<String, dynamic>>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PickerSheet(
        title: 'Select product',
        idOf: (item) => item['id'],
        labelOf: (item) => (item['name'] as String?) ?? '',
        subtitleOf: (item) => item['sku'] as String?,
        fetch: (q) async {
          final res = await ApiClient.instance.get(ApiEndpoints.products, params: {'q': q, 'per_page': 30});
          return parseListData(res.data);
        },
      ),
    );
    if (picked != null && picked.isNotEmpty) {
      setState(() => _product = picked.first);
      _loadBatches();
    }
  }

  Future<void> _loadBatches() async {
    final id = (_product?['id'] as num?)?.toInt();
    if (id == null) return;
    setState(() {
      _loadingBatches = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.product(id));
      final body = res.data;
      final p = (body is Map ? body['data'] as Map? : body as Map?)?.cast<String, dynamic>();
      final raw = p?['stock_layers'] ?? p?['batches'] ?? p?['layers'];
      _batches = (raw is List ? raw : []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loadingBatches = false);
    }
  }

  Future<void> _editBarcode(Map<String, dynamic> batch) async {
    final productId = (_product?['id'] as num?)?.toInt();
    final layerId = (batch['id'] as num?)?.toInt();
    if (productId == null || layerId == null) return;
    final controller = TextEditingController(text: (batch['barcode'] as String?) ?? '');
    final newValue = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit barcode'),
        content: TextField(controller: controller, decoration: const InputDecoration(labelText: 'Barcode value')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, controller.text.trim()), child: const Text('Save')),
        ],
      ),
    );
    if (newValue == null) return;
    try {
      await ApiClient.instance.patch(
        ApiEndpoints.productStockLayerBarcode(productId, layerId),
        data: {'barcode': newValue},
      );
      _loadBatches();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
          child: InkWell(
            onTap: _pickProduct,
            borderRadius: BorderRadius.circular(12),
            child: InputDecorator(
              decoration: const InputDecoration(
                labelText: 'Product',
                prefixIcon: Icon(Icons.search_rounded, size: 20),
              ),
              child: Text(
                (_product?['name'] as String?) ?? 'Search for a product',
                style: TextStyle(fontSize: 14, color: _product == null ? AppColors.textHint : AppColors.textDark),
              ),
            ),
          ),
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_product == null) {
      return const EmptyState(message: 'Search for a product to view its barcodes.');
    }
    if (_loadingBatches) return const Center(child: CircularProgressIndicator());
    if (_error != null) return ErrorState(error: _error!, onRetry: _loadBatches);
    if (_batches.isEmpty) return const EmptyState(message: 'No batches with barcodes for this product.');
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      itemCount: _batches.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, i) {
        final batch = _batches[i];
        final barcode = (batch['barcode'] as String?) ?? '';
        final qty = batch['quantity'];
        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 12, offset: Offset(0, 3))],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Batch qty: ${qty ?? '—'}', style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted)),
              const SizedBox(height: 10),
              if (barcode.isNotEmpty)
                Center(
                  child: BarcodeWidget(
                    barcode: Barcode.code128(),
                    data: barcode,
                    width: 220,
                    height: 70,
                    drawText: true,
                    errorBuilder: (context, error) => Text(
                      'Cannot render barcode',
                      style: const TextStyle(color: AppColors.error, fontSize: 12),
                    ),
                  ),
                )
              else
                const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Text('No barcode set.', style: TextStyle(color: AppColors.textHint, fontSize: 13)),
                  ),
                ),
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: () => _editBarcode(batch),
                  icon: const Icon(Icons.edit_outlined, size: 16),
                  label: Text(barcode.isEmpty ? 'Set barcode' : 'Edit barcode'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
