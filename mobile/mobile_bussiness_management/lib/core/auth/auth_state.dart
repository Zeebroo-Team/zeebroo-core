import 'dart:convert';
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

  // ── Bootstrap ─────────────────────────────────────────────────────────────
  Future<void> bootstrap() async {
    final token = await AuthStorage.getToken();
    if (token == null) {
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.me);
      _user   = _extractUser(res.data);
      _status = AuthStatus.authenticated;
    } catch (_) {
      await AuthStorage.clearAll();
      _status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  // ── Login ─────────────────────────────────────────────────────────────────
  // POST /v1/pos/auth/token → { token_type, access_token, user: {...} }
  Future<void> login(String email, String password) async {
    final res  = await ApiClient.instance.post(ApiEndpoints.login, data: {
      'email':    email,
      'password': password,
    });
    final body  = res.data as Map<String, dynamic>;
    final token = _extractToken(body);
    if (token == null) throw Exception('No token in response');
    await AuthStorage.setToken(token);

    final userData = _extractUser(body);
    await AuthStorage.setUserJson(jsonEncode(userData));
    _user   = userData;
    _status = AuthStatus.authenticated;
    notifyListeners();
  }

  // ── Register + finalise in one call ──────────────────────────────────────
  // POST /v1/pos/auth/register → { token_type, access_token, user: {...} }
  // business_name + business_category are required fields in the same request.
  Future<void> registerAndFinish(Map<String, dynamic> data) async {
    final res  = await ApiClient.instance.post(ApiEndpoints.register, data: data);
    final body = res.data as Map<String, dynamic>;
    final token = _extractToken(body);
    if (token == null) throw Exception('No token in response');
    await AuthStorage.setToken(token);

    final userData = _extractUser(body);
    await AuthStorage.setUserJson(jsonEncode(userData));
    _user   = userData;
    _status = AuthStatus.authenticated;
    notifyListeners();
  }

  // ── Logout ────────────────────────────────────────────────────────────────
  Future<void> logout() async {
    try { await ApiClient.instance.post(ApiEndpoints.revoke); } catch (_) {}
    await AuthStorage.clearAll();
    _user   = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  /// Extracts the Bearer token. API returns `access_token` (not `token`).
  static String? _extractToken(Map<String, dynamic> body) =>
      body['access_token'] as String?
      ?? (body['data'] as Map?)?['access_token'] as String?;

  /// Extracts the user object from login/register/me responses.
  static Map<String, dynamic> _extractUser(dynamic body) {
    if (body is! Map<String, dynamic>) return {};
    final user = body['user'];
    if (user is Map<String, dynamic>) return user;
    // /me endpoint may return user fields at the top level
    final data = body['data'];
    if (data is Map<String, dynamic>) return data;
    return body;
  }
}
