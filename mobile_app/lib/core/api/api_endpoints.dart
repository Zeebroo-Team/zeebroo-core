/// All API endpoint paths. Change [baseUrl] to point at your Laravel server.
///
/// - Android emulator reaching a host machine's `localhost` server: use `10.0.2.2`.
/// - iOS simulator / physical device on the same network: use the machine's LAN IP.
class ApiEndpoints {
  ApiEndpoints._();

  static const String baseUrl = 'http://localhost:8000/api';

  // Auth (public)
  static const String login              = '/v1/pos/auth/token';
  static const String register           = '/v1/pos/auth/register';
  static const String businessCategories = '/v1/pos/auth/business-categories';
  static const String packages           = '/v1/pos/auth/packages';

  // Auth (protected)
  static const String me      = '/v1/pos/auth/me';
  static const String revoke  = '/v1/pos/auth/revoke';

  // Dashboard
  static const String todaySummary = '/v1/pos/today-summary';
}
