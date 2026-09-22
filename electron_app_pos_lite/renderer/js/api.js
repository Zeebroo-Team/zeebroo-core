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
  };
})();
