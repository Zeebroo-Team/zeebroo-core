/// All API endpoint paths. Change [baseUrl] to point at your Laravel server.
///
/// - Android emulator reaching a host machine's `localhost` server: use `10.0.2.2`.
/// - iOS simulator / physical device on the same network: use the machine's LAN IP.
class ApiEndpoints {
  ApiEndpoints._();

  static const String baseUrl = 'http://localhost:8000/api';

  // Auth (public)
  static const String login = '/v1/pos/auth/token';
  static const String register = '/v1/pos/auth/register';
  static const String businessCategories = '/v1/pos/auth/business-categories';
  static const String packages = '/v1/pos/auth/packages';

  // Auth (protected)
  static const String me = '/v1/pos/auth/me';
  static const String profile = '/v1/pos/auth/profile';
  static const String password = '/v1/pos/auth/password';
  static const String revoke = '/v1/pos/auth/revoke';

  // Dashboard
  static const String todaySummary = '/v1/pos/today-summary';
  static const String expensesOverview = '/v1/pos/expenses/overview';
  static const String profitReport = '/v1/pos/profit-report';

  // Accounts (bank accounts / balances — home "Account overview" tab)
  static const String accounts = '/v1/pos/accounts';
  static const String bankTypes = '/v1/pos/bank-types';
  static const String banks = '/v1/pos/banks';

  // Business/branch switcher — shared with the desktop app's "Switch
  // Business"/"Switch Branch" menus (same account, same data either way).
  static const String businesses = '/v1/pos/businesses';
  static const String branches = '/v1/pos/online/branches';

  // Business features (package/plan driven — powers the bottom bar & side menu)
  static const String features = '/v1/pos/online/features';

  // Business settings (name, currency, timezone, logo, ...)
  static const String businessSettings = '/v1/pos/online/settings';
  static const String businessSettingsLogo = '/v1/pos/online/settings/logo';

  // Catalog — product list + full CRUD (Inventory > Products tab)
  static const String products = '/v1/pos/online/products';
  static String product(int id) => '/v1/pos/online/products/$id';
  static String productStockLayerBarcode(int productId, int layerId) =>
      '/v1/pos/online/products/$productId/stock-layers/$layerId/barcode';

  // Units — dropdown source for the product form
  static const String units = '/v1/pos/units';

  // Categories (Inventory > Categories tab)
  static const String categories = '/v1/pos/categories';
  static const String categoryParentOptions =
      '/v1/pos/categories/parent-options';
  static String category(int id) => '/v1/pos/categories/$id';

  // Brands (Inventory > Brands tab)
  static const String brands = '/v1/pos/brands';
  static String brand(int id) => '/v1/pos/brands/$id';

  // Discounts (Inventory > Discounts tab)
  static const String discounts = '/v1/pos/discounts';
  static const String discountProductOptions =
      '/v1/pos/discounts/product-options';
  static String discount(int id) => '/v1/pos/discounts/$id';

  // Suppliers — dropdown source for Purchase Orders / Goods Receive
  static const String suppliers = '/v1/pos/suppliers';

  // Purchase Orders (Inventory > Purchase Orders tab)
  static const String purchaseOrders = '/v1/pos/purchase-orders';
  static String purchaseOrder(int id) => '/v1/pos/purchase-orders/$id';
  static String purchaseOrderPlace(int id) =>
      '/v1/pos/purchase-orders/$id/place';
  static String purchaseOrderReceive(int id) =>
      '/v1/pos/purchase-orders/$id/receive';
  static String purchaseOrderCancel(int id) =>
      '/v1/pos/purchase-orders/$id/cancel';
  static String purchaseOrderGrnForm(int id) =>
      '/v1/pos/purchase-orders/$id/grn-form';
  static String purchaseOrderGrns(int id) =>
      '/v1/pos/purchase-orders/$id/grns';

  // Goods Receive Notes (Inventory > Goods Receive tab)
  static const String grns = '/v1/pos/grns';
  static String grn(int id) => '/v1/pos/grns/$id';
  static String grnPay(int id) => '/v1/pos/grns/$id/pay';
  static String grnApprove(int id) => '/v1/pos/grns/$id/approve';
  static String grnReject(int id) => '/v1/pos/grns/$id/reject';

  // Cheques (Inventory > Cheques tab)
  static const String cheques = '/v1/pos/cheques';
  static String chequeClear(int id) => '/v1/pos/cheques/$id/clear';

  // Stock Audits (Inventory > Stock Audit tab)
  static const String stockAudits = '/v1/pos/stock-audits';
  static String stockAudit(int id) => '/v1/pos/stock-audits/$id';
  static String stockAuditLines(int id) => '/v1/pos/stock-audits/$id/lines';
  static String stockAuditFinalize(int id) =>
      '/v1/pos/stock-audits/$id/finalize';

  // Stock Transfers (Inventory > Stock Transfer tab)
  static const String stockTransfers = '/v1/pos/stock-transfers';
  static String stockTransfer(int id) => '/v1/pos/stock-transfers/$id';
  static String stockTransferReceive(int id) =>
      '/v1/pos/stock-transfers/$id/receive';
  static String stockTransferCancel(int id) =>
      '/v1/pos/stock-transfers/$id/cancel';

  // Bills (Financial > Bills tab)
  static const String financeBills = '/v1/pos/expenses/bills';
  static String financeBill(int id) => '/v1/pos/expenses/bills/$id';
  static String financeBillPay(int id) => '/v1/pos/expenses/bills/$id/pay';
  static const String financeBillAssignmentTargets =
      '/v1/pos/expenses/bill-assignment-targets';

  // Loans (Financial > Loans tab)
  static const String financeLoans = '/v1/pos/loans';
  static String financeLoan(int id) => '/v1/pos/loans/$id';
  static String financeLoanPay(int id) => '/v1/pos/loans/$id/pay';

  // Rentals (Financial > Rentals tab)
  static const String financeRentals = '/v1/pos/rentals';
  static String financeRental(int id) => '/v1/pos/rentals/$id';
  static String financeRentalPay(int id) => '/v1/pos/rentals/$id/pay';

  // Properties (Financial > Properties tab)
  static const String financeProperties = '/v1/pos/properties';
  static String financeProperty(int id) => '/v1/pos/properties/$id';

  // Modifications (Financial > Modifications tab)
  static const String financeModifications = '/v1/pos/expenses/modifications';
  static String financeModification(int id) =>
      '/v1/pos/expenses/modifications/$id';
}
