import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../auth/auth_storage.dart';

/// Warms the GET cache used by the authenticated shell, Home tabs and POS.
/// Startup waits for these requests while the logo screen is visible, so the
/// widgets can resolve their first loads from memory.
class DashboardPreloader {
  DashboardPreloader._();

  static Future<void> warm() async {
    if (await AuthStorage.getToken() == null) return;

    await Future.wait<void>([
      _ignore(ApiClient.instance.get(ApiEndpoints.features)),
      _ignore(
        ApiClient.instance.get(
          ApiEndpoints.notifications,
          params: {'status': 'unread', 'limit': 1},
        ),
      ),
      _ignore(ApiClient.instance.get(ApiEndpoints.accounts)),
      _ignore(ApiClient.instance.get(ApiEndpoints.businessSettings)),
      _ignore(ApiClient.instance.get(ApiEndpoints.todaySummary)),
      _ignore(ApiClient.instance.get(ApiEndpoints.expensesOverview)),
      _ignore(
        ApiClient.instance.get(
          ApiEndpoints.expensesBreakdown,
          params: {'period': 'month'},
        ),
      ),
      _ignore(ApiClient.instance.get(ApiEndpoints.financeLoans)),
      _ignore(ApiClient.instance.get(ApiEndpoints.financeRentals)),
      _ignore(ApiClient.instance.get(ApiEndpoints.financeModifications)),
      _ignore(
        ApiClient.instance.get(
          ApiEndpoints.profitReport,
          params: {'period': 30},
        ),
      ),
      // These parameters must stay in sync with PosScreen's initial requests.
      // They are part of the cache key, so an exact match lets POS render its
      // products immediately instead of making another network request.
      _ignore(
        ApiClient.instance.get(ApiEndpoints.products, params: {'per_page': 60}),
      ),
      _ignore(
        ApiClient.instance.get(
          ApiEndpoints.categories,
          params: {'per_page': 200},
        ),
      ),
      _ignore(
        ApiClient.instance.get(
          ApiEndpoints.customers,
          params: {'per_page': 50},
        ),
      ),
    ]);
  }

  static Future<void> _ignore(Future<dynamic> request) async {
    try {
      await request;
    } catch (_) {
      // Individual cards retain their existing error/retry states. A failed
      // preload must not prevent the user from entering the application.
    }
  }
}
