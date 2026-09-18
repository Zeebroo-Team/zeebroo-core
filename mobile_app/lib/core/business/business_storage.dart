import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the selected business/branch across app restarts — the mobile
/// equivalent of the desktop app's local `business_id`/`branch_id` config.
class BusinessStorage {
  BusinessStorage._();

  static const _store = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _kBusinessId = 'selected_business_id';
  static const _kBusinessName = 'selected_business_name';
  static const _kBranchId = 'selected_branch_id';
  static const _kBranchName = 'selected_branch_name';

  static Future<int?> getBusinessId() async {
    final v = await _store.read(key: _kBusinessId);
    return v == null ? null : int.tryParse(v);
  }

  static Future<String?> getBusinessName() => _store.read(key: _kBusinessName);

  static Future<int?> getBranchId() async {
    final v = await _store.read(key: _kBranchId);
    return v == null ? null : int.tryParse(v);
  }

  static Future<String?> getBranchName() => _store.read(key: _kBranchName);

  static Future<void> save({
    required int businessId,
    required String businessName,
    int? branchId,
    String? branchName,
  }) async {
    await _store.write(key: _kBusinessId, value: businessId.toString());
    await _store.write(key: _kBusinessName, value: businessName);
    if (branchId != null) {
      await _store.write(key: _kBranchId, value: branchId.toString());
      await _store.write(key: _kBranchName, value: branchName ?? '');
    } else {
      await _store.delete(key: _kBranchId);
      await _store.delete(key: _kBranchName);
    }
  }

  static Future<void> clear() async {
    await _store.delete(key: _kBusinessId);
    await _store.delete(key: _kBusinessName);
    await _store.delete(key: _kBranchId);
    await _store.delete(key: _kBranchName);
  }
}
