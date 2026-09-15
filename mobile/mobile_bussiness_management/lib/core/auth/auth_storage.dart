import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthStorage {
  AuthStorage._();

  static const _store = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _kToken    = 'auth_token';
  static const _kUser     = 'auth_user_json';

  static Future<String?> getToken()  => _store.read(key: _kToken);
  static Future<void>    setToken(String t) => _store.write(key: _kToken, value: t);
  static Future<void>    deleteToken()      => _store.delete(key: _kToken);

  static Future<String?> getUserJson()  => _store.read(key: _kUser);
  static Future<void>    setUserJson(String j) => _store.write(key: _kUser, value: j);
  static Future<void>    deleteUserJson()       => _store.delete(key: _kUser);

  static Future<void> clearAll() async {
    await _store.delete(key: _kToken);
    await _store.delete(key: _kUser);
  }
}
