import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/utils/money.dart';

class CouponData {
  CouponData(this.values);

  final Map<String, dynamic> values;
  int get id => (values['id'] as num).toInt();
  String get name => values['name']?.toString() ?? '';
  String get code => values['code']?.toString() ?? '';
  String get discountType => values['discount_type']?.toString() ?? 'percent';
  double get discountValue =>
      double.tryParse(values['discount_value'].toString()) ?? 0;
  int get quantity => (values['quantity'] as num?)?.toInt() ?? 0;
  int get usedCount => (values['used_count'] as num?)?.toInt() ?? 0;
  int get remaining => (values['remaining'] as num?)?.toInt() ?? 0;
  String get status => values['status']?.toString() ?? 'active';
  bool get isActive => values['is_active'] == true;
  String get notes => values['notes']?.toString() ?? '';
  DateTime? get validFrom =>
      DateTime.tryParse(values['valid_from']?.toString() ?? '');
  DateTime? get expiresAt =>
      DateTime.tryParse(values['expires_at']?.toString() ?? '');
  double get totalDiscount =>
      double.tryParse(values['total_discount'].toString()) ?? 0;
  List<Map<String, dynamic>> get redemptions =>
      (values['redemptions'] as List? ?? [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList();

  String get discountLabel {
    if (discountType == 'flat') return '${formatMoney(discountValue)} OFF';
    final value = discountValue == discountValue.roundToDouble()
        ? discountValue.toStringAsFixed(0)
        : discountValue.toStringAsFixed(2);
    return '$value% OFF';
  }

  String get statusLabel => switch (status) {
    'used' => 'Used up',
    'expired' => 'Expired',
    'disabled' => 'Disabled',
    'scheduled' => 'Scheduled',
    _ => 'Active',
  };

  String get validityLabel =>
      '${validFrom == null ? 'Any start date' : 'From ${couponDate(validFrom!)}'}'
      ' · ${expiresAt == null ? 'No expiry' : 'Until ${couponDate(expiresAt!)}'}';
}

String couponDate(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-'
    '${date.month.toString().padLeft(2, '0')}-'
    '${date.day.toString().padLeft(2, '0')}';

class CouponRepository {
  const CouponRepository();

  Future<List<CouponData>> list({String query = '', String status = ''}) async {
    final response = await ApiClient.instance.get(
      ApiEndpoints.coupons,
      params: {'q': query, 'status': status},
      bypassCache: true,
    );
    final body = response.data;
    final rows = body is Map ? body['data'] : null;
    if (rows is! List) throw Exception('Could not read the coupon list.');
    return rows
        .whereType<Map>()
        .map((row) => CouponData(Map<String, dynamic>.from(row)))
        .toList();
  }

  Future<CouponData> detail(int id) async {
    final response = await ApiClient.instance.get(
      ApiEndpoints.coupon(id),
      bypassCache: true,
    );
    return _readCoupon(response.data);
  }

  Future<String> generateCode() async {
    final response = await ApiClient.instance.get(
      ApiEndpoints.couponGenerateCode,
      bypassCache: true,
    );
    final body = response.data;
    final data = body is Map ? body['data'] : null;
    final code = data is Map ? data['code'] : null;
    if (code is! String || code.isEmpty) {
      throw Exception('Could not generate a coupon code.');
    }
    return code;
  }

  Future<CouponData> save(Map<String, dynamic> data, {int? id}) async {
    final response = id == null
        ? await ApiClient.instance.post(ApiEndpoints.coupons, data: data)
        : await ApiClient.instance.patch(ApiEndpoints.coupon(id), data: data);
    return _readCoupon(response.data);
  }

  Future<void> delete(int id) =>
      ApiClient.instance.delete(ApiEndpoints.coupon(id));

  CouponData _readCoupon(dynamic body) {
    final row = body is Map ? body['data'] : null;
    if (row is! Map || row['id'] is! num) {
      throw Exception('Could not read the coupon details.');
    }
    return CouponData(Map<String, dynamic>.from(row));
  }
}
