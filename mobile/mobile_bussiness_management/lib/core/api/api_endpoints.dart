/// All API endpoint paths — change [baseUrl] to your server.
class ApiEndpoints {
  ApiEndpoints._();

  static const String baseUrl = 'http://localhost:8000/api';

  // Auth (public)
  static const String login              = '/v1/pos/auth/token';
  static const String register           = '/v1/pos/auth/register';
  static const String businessCategories = '/v1/pos/auth/business-categories';

  // Auth (protected)
  static const String me                 = '/v1/pos/auth/me';
  static const String updateProfile      = '/v1/pos/auth/profile';
  static const String updatePassword     = '/v1/pos/auth/password';
  static const String revoke             = '/v1/pos/auth/revoke';

  // Businesses
  static const String businesses         = '/v1/pos/businesses';

  // Dashboard
  static const String todaySummary       = '/v1/pos/today-summary';
  static const String expensesOverview   = '/v1/pos/expenses/overview';
  static const String profitReport       = '/v1/pos/profit-report';
  static const String expenseBills       = '/v1/pos/expenses/bills';
  static const String financeFlow        = '/v1/pos/finance/flow';

  // Sales
  static const String sales              = '/v1/pos/sales';
  static const String salesHistory       = '/v1/pos/sales/history';

  // Products / Inventory (catalog endpoint)
  static const String products           = '/v1/pos/online/products';
  static const String productCategories  = '/v1/pos/online/categories';

  // Customers
  static const String customers          = '/v1/pos/customers';
}
