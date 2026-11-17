import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/business/business_storage.dart';
import '../models/sale_receipt.dart';

class PosReceiptRepository {
  Future<SaleReceipt> completeSale(Map<String, dynamic> body) async {
    final response = await ApiClient.instance.post(
      ApiEndpoints.sales,
      data: body,
    );
    final envelope = response.data;
    final data = envelope is Map ? envelope['data'] : null;
    // A successful POST must never become a failed checkout just because the
    // receipt payload is incomplete. It is unsafe to submit the sale again.
    return SaleReceipt.fromJson(data is Map ? data : const {});
  }

  Future<ReceiptSettings> loadSettings() async {
    try {
      final response = await ApiClient.instance
          .get(ApiEndpoints.businessSettings, bypassCache: true)
          .timeout(const Duration(seconds: 5));
      final envelope = response.data;
      final data = envelope is Map ? envelope['data'] : null;
      if (data is Map) return ReceiptSettings.fromJson(data);
    } catch (_) {
      // Receipt settings are optional; the saved transaction is not.
    }
    String? businessName;
    try {
      businessName = await BusinessStorage.getBusinessName();
    } catch (_) {}
    return ReceiptSettings(
      businessName: businessName ?? 'Receipt',
      isFallback: true,
    );
  }
}
