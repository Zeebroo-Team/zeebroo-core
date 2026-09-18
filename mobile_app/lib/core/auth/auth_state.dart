import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import 'auth_storage.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState extends ChangeNotifier {
  AuthStatus _status = AuthStatus.unknown;
  Map<String, dynamic>? _user;

  AuthStatus get status => _status;
  Map<String, dynamic>? get user => _user;
  bool get isAuthenticated => _status == AuthStatus.authenticated;

  /// Set right after registration so the "you're all set" screen can name the
  /// plan that was auto-installed (or explain that none was available yet).
  String? lastInstalledPackageName;

  // ── Bootstrap ────────────────────────────────────────────────────────────
  Future<void> bootstrap() async {
    final token = await AuthStorage.getToken();
    if (token == null) {
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.me);
      _user = _extractUser(res.data);
      _status = AuthStatus.authenticated;
    } catch (_) {
      await AuthStorage.clear();
      _status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  // ── Login ────────────────────────────────────────────────────────────────
  Future<void> login(String email, String password) async {
    final res = await ApiClient.instance.post(
      ApiEndpoints.login,
      data: {
        'email': email,
        'password': password,
        'device_name': 'zeebroo-mobile',
      },
    );
    await _applyAuthResponse(res.data);
  }

  // ── Sign up + onboarding, in one call ───────────────────────────────────
  //
  // The mobile app has no feature/package picker: the industry (business
  // category) is the only choice the user makes. Whichever package is
  // flagged mobile-only on the server is looked up here and auto-attached —
  // if none exists yet, registration proceeds without one (no payment step
  // either way; see Modules/Payment/app/Services/PaymentProvisioningService).
  Future<void> registerAndFinish({
    required String name,
    required String email,
    required String password,
    required String businessName,
    required String businessCategory,
  }) async {
    int? packageId;
    try {
      final pkg = await _findMobileOnlyPackage();
      packageId = pkg?['id'] as int?;
      lastInstalledPackageName = pkg?['name'] as String?;
    } catch (_) {
      // Package lookup is best-effort — registration still proceeds without one.
      lastInstalledPackageName = null;
    }

    final res = await ApiClient.instance.post(
      ApiEndpoints.register,
      data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
        'business_name': businessName,
        'business_category': businessCategory,
        if (packageId != null) 'package_id': packageId,
        'platform': 'mobile',
        'device_name': 'zeebroo-mobile',
      },
    );
    await _applyAuthResponse(res.data);
  }

  Future<Map<String, dynamic>?> _findMobileOnlyPackage() async {
    final res = await ApiClient.instance.get(
      ApiEndpoints.packages,
      params: {'platform': 'mobile'},
    );
    final list = (res.data is Map ? res.data['data'] : res.data) as List?;
    if (list == null) return null;
    for (final item in list) {
      if (item is Map && item['is_mobile_only'] == true) {
        return item.cast<String, dynamic>();
      }
    }
    return null;
  }

  // ── Profile ──────────────────────────────────────────────────────────────
  Future<void> updateProfile({
    required String name,
    required String email,
  }) async {
    final res = await ApiClient.instance.put(
      ApiEndpoints.profile,
      data: {'name': name, 'email': email},
    );
    final data = res.data;
    final updated = (data is Map ? data['data'] : data) as Map?;
    if (updated != null) {
      _user = {...?_user, ...updated.cast<String, dynamic>()};
      notifyListeners();
    }
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String newPassword,
  }) => ApiClient.instance.put(
    ApiEndpoints.password,
    data: {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    },
  );

  // ── Logout ───────────────────────────────────────────────────────────────
  Future<void> logout() async {
    try {
      await ApiClient.instance.post(ApiEndpoints.revoke);
    } catch (_) {
      /* Token may already be invalid server-side — clear local state regardless. */
    }
    await AuthStorage.clear();
    _user = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  // ── Helpers ──────────────────────────────────────────────────────────────
  Future<void> _applyAuthResponse(dynamic body) async {
    if (body is! Map) throw Exception('Unexpected response from server.');
    final token = body['access_token'] as String?;
    if (token == null) throw Exception('No access token in response.');
    await AuthStorage.setToken(token);

    _user = _extractUser(body);
    _status = AuthStatus.authenticated;
    notifyListeners();
  }

  static Map<String, dynamic> _extractUser(dynamic body) {
    if (body is! Map) return {};
    final user = body['user'];
    if (user is Map) return user.cast<String, dynamic>();
    final data = body['data'];
    if (data is Map) return data.cast<String, dynamic>();
    return {};
  }
}
