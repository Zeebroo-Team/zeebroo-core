import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/utils/money.dart';
import '../data/scanner_product_repository.dart';
import '../models/pos_cart_item.dart';

/// Select an existing product by name/SKU, or enter an unreadable barcode.
class ScannerProductSearchSheet extends StatefulWidget {
  const ScannerProductSearchSheet({super.key, required this.repository});

  final ScannerProductRepository repository;

  @override
  State<ScannerProductSearchSheet> createState() =>
      _ScannerProductSearchSheetState();
}

class _ScannerProductSearchSheetState extends State<ScannerProductSearchSheet> {
  final _search = TextEditingController();
  final _code = TextEditingController();
  Timer? _debounce;
  List<Map<String, dynamic>> _products = [];
  bool _loading = false;
  bool _hasMore = false;
  int _page = 1;
  int _request = 0;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _load({bool more = false}) async {
    final request = ++_request;
    final page = more ? _page + 1 : 1;
    final query = _search.text.trim();
    setState(() {
      _loading = true;
      _error = null;
      if (!more) _products = [];
    });
    try {
      final result = await widget.repository.search(query, page: page);
      if (!mounted || request != _request) return;
      setState(() {
        _products = more ? [..._products, ...result.products] : result.products;
        _hasMore = result.hasMore;
        _page = page;
      });
    } catch (error) {
      if (mounted && request == _request) {
        setState(() => _error = apiErrorMessage(error));
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  void _searchChanged(String value) {
    _debounce?.cancel();
    // Invalidate an old request immediately, not only when debounce completes.
    ++_request;
    setState(() {
      _products = [];
      _loading = true;
      _hasMore = false;
      _error = null;
    });
    _debounce = Timer(const Duration(milliseconds: 300), _load);
  }

  void _submitCode() {
    final code = _code.text.trim();
    if (code.isNotEmpty) Navigator.of(context).pop(code);
  }

  @override
  Widget build(BuildContext context) {
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;
    final availableHeight = MediaQuery.sizeOf(context).height - keyboard;
    return Padding(
      padding: EdgeInsets.only(bottom: keyboard),
      child: SafeArea(
        child: SizedBox(
          height: availableHeight * 0.88,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Add product manually',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close search',
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              TextField(
                key: const ValueKey('scanner-product-search'),
                controller: _search,
                onChanged: _searchChanged,
                decoration: const InputDecoration(
                  hintText: 'Search products by name or SKU',
                  prefixIcon: Icon(Icons.search),
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      key: const ValueKey('scanner-manual-code'),
                      controller: _code,
                      onSubmitted: (_) => _submitCode(),
                      decoration: const InputDecoration(
                        hintText: 'Enter barcode / SKU',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: _submitCode,
                    child: const Text('Add code'),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (_loading) const LinearProgressIndicator(),
              if (_error != null) ...[
                Text(_error!, textAlign: TextAlign.center),
                TextButton(
                  onPressed: () => _load(more: _products.isNotEmpty),
                  child: const Text('Retry'),
                ),
              ],
              if (!_loading && _error == null && _products.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(24),
                  child: Text(
                    'No matching products',
                    textAlign: TextAlign.center,
                  ),
                ),
              for (final product in _products) _productTile(product),
              if (_hasMore)
                TextButton(
                  onPressed: _loading ? null : () => _load(more: true),
                  child: const Text('Load more products'),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _productTile(Map<String, dynamic> product) {
    final line = PosCartItem(product: product);
    final soldOut = posProductIsOutOfStock(product);
    return ListTile(
      key: ValueKey('scanner-search-product-${line.id}'),
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.inventory_2_outlined),
      title: Text(line.name, maxLines: 2, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        '${product['sku'] ?? ''} · ${formatMoney(line.basePrice)}${soldOut ? ' · Out of stock' : ''}',
      ),
      trailing: IconButton(
        tooltip: 'Add ${line.name}',
        onPressed: soldOut ? null : () => Navigator.of(context).pop(product),
        icon: const Icon(Icons.add_circle_outline),
      ),
      onTap: soldOut ? null : () => Navigator.of(context).pop(product),
    );
  }
}
