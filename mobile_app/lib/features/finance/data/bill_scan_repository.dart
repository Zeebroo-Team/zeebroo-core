import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';

/// Second validation boundary: never trust JSON types or dates from an API.
class BillScanDraft {
  BillScanDraft._(this.values);
  final Map<String, dynamic> values;

  factory BillScanDraft.clean(Map<String, dynamic> raw) {
    String? text(String key, int max) {
      final value = raw[key];
      if (value is! String) return null;
      final clean = value.replaceAll(RegExp(r'[\x00-\x1F\x7F]'), ' ').trim();
      return clean.isEmpty || clean.runes.length > max ? null : clean;
    }

    String? choice(String key, List<String> allowed) {
      final value = text(key, 50)?.toLowerCase();
      return allowed.contains(value) ? value : null;
    }

    int? integer(String key, int min, int max) {
      final value = raw[key];
      final number = value is int
          ? value
          : value is String && RegExp(r'^\d+$').hasMatch(value.trim())
          ? int.tryParse(value.trim())
          : null;
      return number == null || number < min || number > max ? null : number;
    }

    bool? boolean(String key) => switch (raw[key]) {
      true => true,
      false => false,
      'true' => true,
      'false' => false,
      _ => null,
    };
    String? date(String key) {
      final value = text(key, 10);
      if (value == null || !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(value)) {
        return null;
      }
      final parsed = DateTime.tryParse(value);
      if (parsed == null ||
          parsed.year < 2000 ||
          parsed.year > 2100 ||
          parsed.toIso8601String().substring(0, 10) != value) {
        return null;
      }
      return value;
    }

    double? amount() {
      final value = raw['recurring_cost'];
      double? number;
      if (value is num) {
        number = value.toDouble();
      } else if (value is String) {
        final clean = value.trim().replaceFirst(
          RegExp(r'^(?:LKR|Rs\.?|\$)\s*', caseSensitive: false),
          '',
        );
        if (RegExp(
          r'^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,2})?$',
        ).hasMatch(clean)) {
          number = double.tryParse(clean.replaceAll(',', ''));
        }
      }
      return number == null ||
              !number.isFinite ||
              number < 0 ||
              number > 9999999999.99
          ? null
          : double.parse(number.toStringAsFixed(2));
    }

    final category = choice('bill_category', [
      'water',
      'electricity',
      'telephone',
      'internet',
      'gas',
      'waste',
      'other',
    ]);
    final mode = choice('payment_mode', ['one_time', 'recurring']);
    return BillScanDraft._(
      Map.unmodifiable({
        'name': text('name', 255),
        'bill_category': category,
        'bill_category_other': category == 'other'
            ? text('bill_category_other', 255)
            : null,
        'description': text('description', 2000),
        'payment_mode': mode,
        'recurring_type': mode == 'recurring'
            ? choice('recurring_type', ['per_day', 'per_month', 'per_year'])
            : null,
        'agreement_valid_until_year': mode == 'recurring'
            ? integer('agreement_valid_until_year', 2000, 2100)
            : null,
        'due_date': date('due_date'),
        'first_installment_due_date': date('first_installment_due_date'),
        'amount_varies_by_usage': boolean('amount_varies_by_usage'),
        'recurring_cost': amount(),
        'allow_split_payment': boolean('allow_split_payment'),
        'remind_before_days': integer('remind_before_days', 0, 366),
        'notes': text('notes', 5000),
      }),
    );
  }
}

class BillScanRepository {
  const BillScanRepository();

  Future<XFile?> pick(ImageSource source) => ImagePicker().pickImage(
    source: source,
    imageQuality: 85,
    maxWidth: 2000,
    maxHeight: 2000,
    requestFullMetadata: false,
  );

  Future<BillScanDraft> scan(XFile image) async {
    if (await image.length() > 8 * 1024 * 1024) {
      throw Exception('Choose a bill image smaller than 8 MB.');
    }
    final bytes = await image.readAsBytes();
    final String? mime;
    if (bytes.length >= 3 &&
        bytes[0] == 0xff &&
        bytes[1] == 0xd8 &&
        bytes[2] == 0xff) {
      mime = 'image/jpeg';
    } else if (bytes.length >= 8 &&
        bytes.take(8).join(',') == '137,80,78,71,13,10,26,10') {
      mime = 'image/png';
    } else if (bytes.length >= 12 &&
        String.fromCharCodes(bytes.take(4)) == 'RIFF' &&
        String.fromCharCodes(bytes.sublist(8, 12)) == 'WEBP') {
      mime = 'image/webp';
    } else {
      mime = null;
    }
    if (mime == null) throw Exception('Choose a JPEG, PNG or WebP bill image.');
    final extension = mime == 'image/jpeg'
        ? 'jpg'
        : mime == 'image/png'
        ? 'png'
        : 'webp';
    final data = FormData.fromMap({
      'image': MultipartFile.fromBytes(
        bytes,
        filename: 'bill.$extension',
        contentType: DioMediaType.parse(mime),
      ),
    });
    final response = await ApiClient.instance.postMultipart(
      ApiEndpoints.financeBillScan,
      data,
      receiveTimeout: const Duration(seconds: 75),
    );
    final body = response.data;
    final fields = body is Map ? body['data'] : null;
    if (fields is! Map) {
      throw Exception('The bill scan returned an invalid response.');
    }
    return BillScanDraft.clean(Map<String, dynamic>.from(fields));
  }

  Future<({Map<String, dynamic> targets, List<Map<String, dynamic>> accounts})>
  loadOptions() async {
    final results = await Future.wait([
      ApiClient.instance.get(ApiEndpoints.financeBillAssignmentTargets),
      ApiClient.instance.get(ApiEndpoints.accounts),
    ]);
    final targets = results[0].data;
    final accounts = results[1].data;
    final rows = accounts is Map ? accounts['data'] : accounts;
    return (
      targets: Map<String, dynamic>.from(
        targets is Map && targets['data'] is Map ? targets['data'] as Map : {},
      ),
      accounts: (rows is List ? rows : [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList(),
    );
  }

  Future<void> save(Map<String, dynamic> fields) async {
    await ApiClient.instance.post(ApiEndpoints.financeBills, data: fields);
  }
}
