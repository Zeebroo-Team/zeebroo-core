'use strict';

// Thin wrapper around the api-request IPC bridge (see preload.js / main.js).
// Talks to the same Laravel POS API the full desktop app uses:
// Modules/Pos/routes/api.php, prefix /api/v1/pos.
const API = (() => {
  function request(method, path, body = null) {
    return window.electronAPI.apiRequest(method, path, body);
  }

  return {
    // Auth
    login: (email, password) =>
      request('POST', '/auth/token', { email, password, device_name: 'pos-lite' }),
    register: (payload) =>
      request('POST', '/auth/register', { ...payload, platform: 'pos_lite', password_confirmation: payload.password, device_name: 'pos-lite' }),
    businessCategories: () => request('GET', '/auth/business-categories'),
    businesses: () => request('GET', '/businesses'),

    // Onboarding: the single "Support POS Lite" package, and its Stripe checkout
    packages: () => request('GET', '/auth/packages?platform=pos_lite'),
    startPaymentCheckout: (paymentId) => request('POST', '/auth/payment/checkout-session', { payment_id: paymentId }),
    paymentStatus: (paymentId) => request('GET', `/auth/payment/${paymentId}/status`),

    // Billing & Payments modal (subscription status + invoice history)
    paymentHistory: () => request('GET', '/auth/payment/history'),
    paymentDetail: (paymentId) => request('GET', `/auth/payment/${paymentId}`),
    cancelBillingSubscription: () => request('POST', '/auth/payment/subscription/cancel'),
    resumeBillingSubscription: () => request('POST', '/auth/payment/subscription/resume'),

    // Catalog / sales
    products: (q, opts = {}) => {
      const qs = new URLSearchParams();
      if (q) qs.set('q', q);
      if (opts.filter) qs.set('filter', opts.filter); // 'rental' | 'dynamic' — POS mode tabs
      if (opts.categoryId) qs.set('category', opts.categoryId);
      const suffix = qs.toString();
      return request('GET', '/online/products' + (suffix ? `?${suffix}` : ''));
    },
    posCategories: () => request('GET', '/online/categories'),
    checkout: (payload) => request('POST', '/online/checkout', payload),

    // Products & Categories management
    productList: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      if (params.categoryId) qs.set('category', params.categoryId);
      qs.set('per_page', params.perPage || 20);
      qs.set('page', params.page || 1);
      return request('GET', '/online/products?' + qs.toString());
    },
    product: (id) => request('GET', `/online/products/${id}`),
    createProduct: (payload) => request('POST', '/online/products', payload),
    updateProduct: (id, payload) => request('PATCH', `/online/products/${id}`, payload),
    deleteProduct: (id) => request('DELETE', `/online/products/${id}`),
    productSearch: (q, perPage) => request('GET', `/online/products?q=${encodeURIComponent(q || '')}&per_page=${perPage || 20}`),
    productStockHistory: (id) => request('GET', `/online/products/${id}/stock-history`),
    productSalesChart: (id, period) => request('GET', `/online/products/${id}/sales-chart?period=${period || 'weekly'}`),

    productCategories: (q, page, perPage) => {
      const qs = new URLSearchParams();
      if (q) qs.set('q', q);
      if (page) qs.set('page', page);
      if (perPage) qs.set('per_page', perPage);
      const suffix = qs.toString();
      return request('GET', '/categories' + (suffix ? `?${suffix}` : ''));
    },
    createProductCategory: (payload) => request('POST', '/categories', payload),
    deleteProductCategory: (id) => request('DELETE', `/categories/${id}`),
    categoryParentOpts: (excludeId) => request('GET', `/categories/parent-options${excludeId ? '?exclude=' + excludeId : ''}`),

    // `page`/`perPage` are opt-in: omit them to get the full unfiltered list (used by pickers).
    productBrands: (q, status, page, perPage) => {
      const qs = new URLSearchParams();
      qs.set('q', q || '');
      qs.set('status', status || '');
      if (page) qs.set('page', page);
      if (perPage) qs.set('per_page', perPage);
      return request('GET', `/brands?${qs.toString()}`);
    },
    createProductBrand: (payload) => request('POST', '/brands', payload),
    deleteProductBrand: (id) => request('DELETE', `/brands/${id}`),

    productUnits: (q) => request('GET', '/units' + (q ? `?q=${encodeURIComponent(q)}` : '')),
    createProductUnit: (payload) => request('POST', '/units', payload),
    deleteProductUnit: (id) => request('DELETE', `/units/${id}`),

    // Business settings (delivery partners etc.) + file manager (product images)
    settingsGet: () => request('GET', '/online/settings'),
    settingsUpdate: (payload) => request('PATCH', '/online/settings', payload),
    fileManagerBrowse: (folderId, imagesOnly) => request('GET', `/online/file-manager?folder=${folderId || ''}&images_only=${imagesOnly ? 1 : 0}`),

    // Invoice Setup (template, paper, margins, arrangement — no letterhead)
    invoiceSetupGet: () => request('GET', '/online/invoice-setup'),
    invoiceSetupUpdate: (payload) => request('PATCH', '/online/invoice-setup', payload),

    // Customers
    customers: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      if (params.categoryId) qs.set('category_id', params.categoryId);
      const suffix = qs.toString();
      return request('GET', '/customers' + (suffix ? `?${suffix}` : ''));
    },
    customer: (id) => request('GET', `/customers/${id}`),
    createCustomer: (payload) => request('POST', '/customers', payload),
    deleteCustomer: (id) => request('DELETE', `/customers/${id}`),
    customerCategories: () => request('GET', '/customer-categories'),
    createCustomerCategory: (payload) => request('POST', '/customer-categories', payload),

    // Cashiers
    cashiers: () => request('GET', '/cashiers'),
    createCashier: (payload) => request('POST', '/cashiers', payload),
    updateCashier: (id, payload) => request('PATCH', `/cashiers/${id}`, payload),
    deleteCashier: (id) => request('DELETE', `/cashiers/${id}`),

    // Sales Management — Transactions
    sales: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      if (params.channel) qs.set('channel', params.channel);
      if (params.limit) qs.set('limit', params.limit);
      const suffix = qs.toString();
      return request('GET', '/sales' + (suffix ? `?${suffix}` : ''));
    },
    sale: (id) => request('GET', `/sales/${id}`),
    voidSale: (id) => request('POST', `/sales/${id}/void`),

    // Sales Management — History (paginated, with summary + filters)
    salesHistory: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      if (params.status) qs.set('status', params.status);
      if (params.channel) qs.set('channel', params.channel);
      if (params.dateFrom) qs.set('date_from', params.dateFrom);
      if (params.dateTo) qs.set('date_to', params.dateTo);
      qs.set('page', params.page || 1);
      return request('GET', '/sales/history?' + qs.toString());
    },

    // Sales Management — Quotations
    quotations: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      qs.set('status', params.status || 'all');
      return request('GET', '/quotations?' + qs.toString());
    },
    quotation: (id) => request('GET', `/quotations/${id}`),
    createQuotation: (payload) => request('POST', '/quotations', payload),
    markQuotationSent: (id) => request('POST', `/quotations/${id}/mark-sent`),
    markQuotationAccepted: (id) => request('POST', `/quotations/${id}/accept`),
    markQuotationRejected: (id) => request('POST', `/quotations/${id}/reject`),

    // Sales Management — Recurring Sales (customer subscriptions)
    subscriptions: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      qs.set('status', params.status || 'all');
      qs.set('page', params.page || 1);
      return request('GET', '/subscriptions?' + qs.toString());
    },
    subscription: (id) => request('GET', `/subscriptions/${id}`),
    cancelSubscription: (id) => request('POST', `/subscriptions/${id}/cancel`),
    pauseSubscription: (id) => request('POST', `/subscriptions/${id}/pause`),
    resumeSubscription: (id) => request('POST', `/subscriptions/${id}/resume`),
    renewSubscription: (id) => request('POST', `/subscriptions/${id}/renew`),

    // Sales Management — Rentals (product rentals)
    productRentals: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      qs.set('status', params.status || 'all');
      qs.set('page', params.page || 1);
      return request('GET', '/product-rentals?' + qs.toString());
    },
    productRental: (id) => request('GET', `/product-rentals/${id}`),
    returnProductRental: (id) => request('POST', `/product-rentals/${id}/return`),

    // Stock — Purchase Orders
    purchaseOrders: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      if (params.status) qs.set('status', params.status);
      const suffix = qs.toString();
      return request('GET', '/purchase-orders' + (suffix ? `?${suffix}` : ''));
    },
    purchaseOrder: (id) => request('GET', `/purchase-orders/${id}`),
    createPurchaseOrder: (payload) => request('POST', '/purchase-orders', payload),
    placePurchaseOrder: (id) => request('POST', `/purchase-orders/${id}/place`),
    cancelPurchaseOrder: (id) => request('POST', `/purchase-orders/${id}/cancel`),

    // Stock — Goods Receive (GRNs)
    grns: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      if (params.payment) qs.set('payment', params.payment);
      const suffix = qs.toString();
      return request('GET', '/grns' + (suffix ? `?${suffix}` : ''));
    },
    grn: (id) => request('GET', `/grns/${id}`),
    grnFormForPurchase: (purchaseId) => request('GET', `/purchase-orders/${purchaseId}/grn-form`),
    createGrnForPurchase: (purchaseId, payload) => request('POST', `/purchase-orders/${purchaseId}/grns`, payload),
    createGrnDirect: (payload) => request('POST', '/grns', payload),
    payGrn: (id, payload) => request('POST', `/grns/${id}/pay`, payload),
    approveGrn: (id) => request('POST', `/grns/${id}/approve`),
    rejectGrn: (id) => request('POST', `/grns/${id}/reject`),

    // Stock — Cheques
    cheques: (filter) => request('GET', '/cheques' + (filter && filter !== 'all' ? `?filter=${filter}` : '')),
    clearCheque: (id, payload = {}) => request('POST', `/cheques/${id}/clear`, payload),

    // Stock — Stock Transfers
    stockTransfers: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      qs.set('page', params.page || 1);
      return request('GET', '/stock-transfers?' + qs.toString());
    },
    stockTransfer: (id) => request('GET', `/stock-transfers/${id}`),
    createStockTransfer: (payload) => request('POST', '/stock-transfers', payload),
    receiveStockTransfer: (id) => request('POST', `/stock-transfers/${id}/receive`),
    cancelStockTransfer: (id) => request('POST', `/stock-transfers/${id}/cancel`),

    // Stock — shared lookups (suppliers, finance accounts, branches)
    suppliers: (params = {}) => {
      const qs = new URLSearchParams();
      if (params.q) qs.set('q', params.q);
      qs.set('active', '1');
      return request('GET', '/suppliers?' + qs.toString());
    },
    accounts: () => request('GET', '/accounts'),
    branches: () => request('GET', '/branches'),
  };
})();
