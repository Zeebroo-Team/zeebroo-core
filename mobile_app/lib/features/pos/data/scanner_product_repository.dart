import 'package:dio/dio.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';

class ScannerProductsPage {
  const ScannerProductsPage({required this.products, this.hasMore = false});

  final List<Map<String, dynamic>> products;
  final bool hasMore;
}

/// Uses the current authenticated business's existing POS catalog APIs.
class ScannerProductRepository {
  Future<Map<String, dynamic>?> lookup(String code) async {
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
