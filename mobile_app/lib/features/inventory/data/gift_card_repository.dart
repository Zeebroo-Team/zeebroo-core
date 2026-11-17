import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';

/// Group and individual-card responses share their editable settings, but not
/// their IDs or balances. Keep the kind explicit when selecting an endpoint.
class GiftCardRecord {
  GiftCardRecord(this.values, {this.isGroup = false});
  final Map<String, dynamic> values;
  final bool isGroup;
  int get id => (values['id'] as num).toInt();
  String get name => values['name']?.toString() ?? '';
  String get code => values['code']?.toString() ?? '';
  double get initialValue => _number('initial_value');
  double get balance => _number(isGroup ? 'total_balance' : 'balance');
  double get totalValue => isGroup ? _number('total_value') : initialValue;
  double get usedAmount => _number('used_amount');
  int get cardCount => (values['card_count'] as num?)?.toInt() ?? 0;
  bool get isActive => values['is_active'] == true;
  String get status => values['status']?.toString() ?? 'active';
  String get notes => values['notes']?.toString() ?? '';
  String get customerName => values['customer_name']?.toString() ?? '';
  DateTime? get validFrom =>
      DateTime.tryParse(values['valid_from']?.toString() ?? '');
  DateTime? get expiresAt =>
      DateTime.tryParse(values['expires_at']?.toString() ?? '');
  List<GiftCardRecord> get cards =>
      _rows('cards').map((row) => GiftCardRecord(row)).toList();
  List<Map<String, dynamic>> get transactions => _rows('transactions');
  bool get hasRedemptions => transactions.any((row) => row['type'] == 'redeem');
  Map<String, dynamic> get statusCounts =>
      Map<String, dynamic>.from(values['status_counts'] as Map? ?? {});
  String get validityLabel =>
      '${validFrom == null ? 'Any start date' : 'From ${giftCardDate(validFrom!)}'}'
      ' · ${expiresAt == null ? 'No expiry' : 'Until ${giftCardDate(expiresAt!)}'}';
  double _number(String key) => double.tryParse(values[key].toString()) ?? 0;
  List<Map<String, dynamic>> _rows(String key) => (values[key] as List? ?? [])
      .whereType<Map>()
      .map((row) => Map<String, dynamic>.from(row))
      .toList();
}

String giftCardDate(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-'
    '${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

class GiftCardRepository {
  const GiftCardRepository();

  Future<List<GiftCardRecord>> list({
    String query = '',
    String status = '',
  }) async {
    final response = await ApiClient.instance.get(
      ApiEndpoints.giftCardGroups,
      params: {'q': query, 'status': status},
      bypassCache: true,
    );
    final body = response.data;
    final rows = body is Map ? body['data'] : null;
    if (rows is! List) throw Exception('Could not read the gift card groups.');
    return rows
        .whereType<Map>()
        .map(
          (row) =>
              GiftCardRecord(Map<String, dynamic>.from(row), isGroup: true),
        )
        .toList();
  }

  Future<GiftCardRecord> detail(GiftCardRecord record) async {
    final response = await ApiClient.instance.get(
      _path(record),
      bypassCache: true,
    );
    return _read(response.data, isGroup: record.isGroup);
  }

  Future<String> generateCode() async {
    final response = await ApiClient.instance.get(
      ApiEndpoints.giftCardGenerateCode,
      bypassCache: true,
    );
    final body = response.data;
    final data = body is Map ? body['data'] : null;
    final code = data is Map ? data['code'] : null;
    if (code is! String || code.isEmpty) {
      throw Exception('Could not generate a gift card code.');
    }
    return code;
  }

  Future<GiftCardRecord> save(
    Map<String, dynamic> data, {
    GiftCardRecord? existing,
  }) async {
    final response = existing == null
        ? await ApiClient.instance.post(ApiEndpoints.giftCards, data: data)
        : await ApiClient.instance.patch(_path(existing), data: data);
    return _read(response.data, isGroup: existing == null || existing.isGroup);
  }

  Future<GiftCardRecord> addCards(int groupId, int quantity) async {
    final response = await ApiClient.instance.post(
      ApiEndpoints.giftCardGroupCards(groupId),
      data: {'quantity': quantity},
    );
    return _read(response.data, isGroup: true);
  }

  Future<void> delete(GiftCardRecord record) async {
    await ApiClient.instance.delete(_path(record));
  }

  String _path(GiftCardRecord record) => record.isGroup
      ? ApiEndpoints.giftCardGroup(record.id)
      : ApiEndpoints.giftCard(record.id);

  GiftCardRecord _read(dynamic body, {required bool isGroup}) {
    final data = body is Map ? body['data'] : null;
    if (data is! Map || data['id'] is! num) {
      throw Exception('Could not read the gift card details.');
    }
    return GiftCardRecord(Map<String, dynamic>.from(data), isGroup: isGroup);
  }
}
