import 'package:flutter/foundation.dart';

import 'business_storage.dart';

enum BusinessSelectionStatus { unknown, ready }

/// Tracks which business (and optionally branch) the signed-in user is
/// currently working in — the mobile equivalent of the desktop app's
/// business/branch switcher. Every API request attaches the current
/// selection as `X-Business-Id`/`X-Branch-Id` headers (see `ApiClient`),
/// so the server resolves data for the right business regardless of which
/// platform the account was set up on.
class BusinessState extends ChangeNotifier {
  BusinessSelectionStatus _status = BusinessSelectionStatus.unknown;
  int? _businessId;
  String? _businessName;
  int? _branchId;
  String? _branchName;

  BusinessSelectionStatus get status => _status;
  int? get businessId => _businessId;
  String? get businessName => _businessName;
  int? get branchId => _branchId;
  String? get branchName => _branchName;
  bool get hasSelection => _businessId != null;

  Future<void> bootstrap() async {
    _businessId = await BusinessStorage.getBusinessId();
    _businessName = await BusinessStorage.getBusinessName();
    _branchId = await BusinessStorage.getBranchId();
    _branchName = await BusinessStorage.getBranchName();
    _status = BusinessSelectionStatus.ready;
    notifyListeners();
  }

  Future<void> select({
    required int businessId,
    required String businessName,
    int? branchId,
    String? branchName,
  }) async {
    await BusinessStorage.save(
      businessId: businessId,
      businessName: businessName,
      branchId: branchId,
      branchName: branchName,
    );
    _businessId = businessId;
    _businessName = businessName;
    _branchId = branchId;
    _branchName = branchName;
    notifyListeners();
  }

  Future<void> clear() async {
    await BusinessStorage.clear();
    _businessId = null;
    _businessName = null;
    _branchId = null;
    _branchName = null;
    notifyListeners();
  }
}
