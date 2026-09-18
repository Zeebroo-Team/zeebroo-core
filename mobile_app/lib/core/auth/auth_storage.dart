import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the bearer token across app restarts.
class AuthStorage {
  AuthStorage._();

  static const _store = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _kToken = 'auth_token';

  static Future<String?> getToken() => _store.read(key: _kToken);
  static Future<void> setToken(String token) => _store.write(key: _kToken, value: token);
  static Future<void> clear() => _store.delete(key: _kToken);
}
