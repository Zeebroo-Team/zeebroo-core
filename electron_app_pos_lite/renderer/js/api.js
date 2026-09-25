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
      request('POST', '/auth/register', { ...payload, password_confirmation: payload.password, device_name: 'pos-lite' }),
    businessCategories: () => request('GET', '/auth/business-categories'),
    businesses: () => request('GET', '/businesses'),

    // Catalog / sales
    products: (q) => request('GET', '/online/products' + (q ? `?q=${encodeURIComponent(q)}` : '')),
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
    fileManagerBrowse: (folderId, imagesOnly) => request('GET', `/online/file-manager?folder=${folderId || ''}&images_only=${imagesOnly ? 1 : 0}`),

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
  };
})();
