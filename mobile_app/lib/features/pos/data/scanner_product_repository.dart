import 'package:dio/dio.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/offline/offline_products.dart';

class ScannerProductsPage {
  const ScannerProductsPage({required this.products, this.hasMore = false});

  final List<Map<String, dynamic>> products;
  final bool hasMore;
}

/// Uses the current authenticated business's existing POS catalog APIs —
/// or, with "Offline products" on and synced, the phone's copy first.
class ScannerProductRepository {
  Future<Map<String, dynamic>?> lookup(String code) async {
    final offline = OfflineProducts.instance;
    if (await offline.isReady().catchError((_) => false)) {
      final local = await offline.productByCode(code).catchError((_) => null);
      if (local != null) return local;
    }
    try {
      final response = await ApiClient.instance.get(
        ApiEndpoints.productBySku(code),
        bypassCache: true,
      );
      final body = response.data;
      final product = body is Map ? body['data'] : null;
      return product is Map ? Map<String, dynamic>.from(product) : null;
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) return null;
      rethrow;
    }
  }

  Future<ScannerProductsPage> search(String query, {int page = 1}) async {
    final offline = OfflineProducts.instance;
    if (await offline.isReady().catchError((_) => false)) {
      try {
        // Local results come back in one go (no paging).
        if (page > 1) return const ScannerProductsPage(products: []);
        return ScannerProductsPage(products: await offline.products(search: query));
      } catch (_) {
        // fall back to the server below
      }
    }
    final response = await ApiClient.instance.get(
      ApiEndpoints.products,
      params: {'q': query, 'page': page, 'per_page': 40},
      bypassCache: true,
    );
    final body = response.data;
    final data = (body is Map ? body['data'] : body) as List? ?? [];
    final meta = body is Map ? body['meta'] : null;
    return ScannerProductsPage(
      products: data
          .whereType<Map>()
          .map((product) => Map<String, dynamic>.from(product))
          .toList(),
      hasMore: meta is Map && page < ((meta['last_page'] as num?) ?? page),
    );
  }
}
