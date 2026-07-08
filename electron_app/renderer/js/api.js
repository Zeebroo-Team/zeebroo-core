'use strict';

const API = (() => {
  async function request(method, path, body = null) {
    const res = await window.electronAPI.apiRequest(method, path, body);
    if (res.status === 401) {
      window.dispatchEvent(new CustomEvent('api-unauthorized'));
    }
    return res;
  }

  return {
    // Auth
    login:        (email, password, deviceName) =>
      request('POST', '/auth/token', { email, password, device_name: deviceName }),
    register:           (name, businessName, businessCategory, features, email, password, deviceName) =>
      request('POST', '/auth/register', { name, business_name: businessName, business_category: businessCategory, features, email, password, password_confirmation: password, device_name: deviceName }),
    businessCategories: () => request('GET', '/auth/business-categories'),
    me:                 () => request('GET', '/auth/me'),

    // Business selection
    businesses:      ()     => request('GET',  '/businesses'),
    createBusiness:  (body) => request('POST', '/businesses', body),
    branches:        ()     => request('GET',  '/online/branches'),

    // POS catalog
    bootstrap:    (search, catId, pg, filters) => {
      const p = new URLSearchParams({ q: search, category: catId || '', page: pg });
      if (filters?.stockStatus)  p.set('stock_status',  filters.stockStatus);
      if (filters?.brandId)      p.set('brand_id',      filters.brandId);
      if (filters?.recentSales)  p.set('recent_sales',  '1');
      if (filters?.sort && filters.sort !== 'name_asc') p.set('sort', filters.sort);
      return request('GET', `/online/bootstrap?${p.toString()}`);
    },
    productSearch:(q, perPage)    => request('GET', `/online/products?q=${encodeURIComponent(q || '')}&per_page=${perPage || 20}`),
    product:          (id)         => request('GET', `/online/products/${id}`),
    productSalesChart:(id, period) => request('GET', `/online/products/${id}/sales-chart?period=${period || 'weekly'}`),
    productBySku: (sku)               => request('GET',  `/online/products/sku/${encodeURIComponent(sku)}`),
    checkout:     (body)              => request('POST', '/online/checkout', body),
    createProduct: (body)              => request('POST',   '/online/products', body),
    updateProduct: (id, body)          => request('PATCH',  `/online/products/${id}`, body),
    deleteProduct: (id)                => request('DELETE', `/online/products/${id}`),

    // Invoices
    invoices:       (q, status) => request('GET',    `/invoices?q=${encodeURIComponent(q||'')}&status=${status||'all'}`),
    invoice:        (id)        => request('GET',    `/invoices/${id}`),
    createInvoice:  (body)      => request('POST',   '/invoices', body),
    updateInvoice:  (id, body)  => request('PATCH',  `/invoices/${id}`, body),
    invoiceSent:    (id)        => request('POST',   `/invoices/${id}/mark-sent`),
    invoicePaid:    (id)        => request('POST',   `/invoices/${id}/mark-paid`),
    invoiceOverdue: (id)        => request('POST',   `/invoices/${id}/mark-overdue`),
    invoiceCancel:  (id)        => request('POST',   `/invoices/${id}/cancel`),
    deleteInvoice:  (id)        => request('DELETE', `/invoices/${id}`),

    // Quotations
    quotations:      (q, status) => request('GET',    `/quotations?q=${encodeURIComponent(q||'')}&status=${status||'all'}`),
    quotation:       (id)        => request('GET',    `/quotations/${id}`),
    createQuotation: (body)      => request('POST',   '/quotations', body),
    updateQuotation: (id, body)  => request('PATCH',  `/quotations/${id}`, body),
    quotationSent:   (id)        => request('POST',   `/quotations/${id}/mark-sent`),
    quotationAccept: (id)        => request('POST',   `/quotations/${id}/accept`),
    quotationReject: (id)        => request('POST',   `/quotations/${id}/reject`),
    deleteQuotation: (id)        => request('DELETE', `/quotations/${id}`),

    // End of Day
    eodStatus:  () => request('GET',  '/eod'),
    eodSettle:  () => request('POST', '/eod/settle'),

    // Today Summary
    todaySummary: () => request('GET', '/today-summary'),

    // Sales
    sales:        (q, limit)          => request('GET',  `/sales?q=${encodeURIComponent(q)}${limit ? '&limit='+limit : ''}`),
    sale:         (id)                => request('GET',  `/sales/${id}`),
    voidSale:     (id)                => request('POST', `/sales/${id}/void`),
    salesHistory: (params)            => {
      const p = new URLSearchParams();
      if (params?.q)         p.set('q',         params.q);
      if (params?.status)    p.set('status',     params.status);
      if (params?.channel)   p.set('channel',    params.channel);
      if (params?.date_from) p.set('date_from',  params.date_from);
      if (params?.date_to)   p.set('date_to',    params.date_to);
      if (params?.page)      p.set('page',       String(params.page));
      return request('GET', `/sales/history?${p.toString()}`);
    },
    processReturn:(id, body)          => request('POST', `/sales/${id}/return`, body),
    returnReasons:()                  => request('GET',  '/online/return-reasons'),

    // Finance — expenses / bills / profit
    expensesOverview: ()              => request('GET',    '/expenses/overview'),
    profitReport:     (period)        => request('GET',    `/profit-report?period=${period}`),
    payrollOverview:  ()              => request('GET',    '/payroll-overview'),
    createBill:   (body)              => request('POST',   '/expenses/bills', body),
    bills:        ()                  => request('GET',    '/expenses/bills'),
    bill:         (id)                => request('GET',    `/expenses/bills/${id}`),
    payBill:      (id, body)          => request('POST',   `/expenses/bills/${id}/pay`, body),
    deleteBill:   (id)                => request('DELETE', `/expenses/bills/${id}`),

    // Finance — loans
    loans:        ()                  => request('GET',    '/loans'),
    loan:         (id)                => request('GET',    `/loans/${id}`),
    createLoan:   (body)              => request('POST',   '/loans', body),
    payLoan:      (id, body)          => request('POST',   `/loans/${id}/pay`, body),
    deleteLoan:   (id)                => request('DELETE', `/loans/${id}`),
    banks:         ()       => request('GET',  '/banks'),
    bankTypes:     ()       => request('GET',  '/bank-types'),
    accounts:      ()       => request('GET',  '/accounts'),
    createAccount: (body)   => request('POST', '/accounts', body),
    settingsGet:    ()     => request('GET',   '/online/settings'),
    settingsUpdate: (data) => request('PATCH', '/online/settings', data),
    features:       ()     => request('GET',   '/online/features'),
    updateFeatures: (data) => request('PUT',   '/online/features', data),
    syncStatus:     ()     => request('GET',   '/online/sync-status'),
    billTargets:  ()                  => request('GET',  '/expenses/bill-assignment-targets'),
    // Finance — flow overview
    financeFlow:    ()         => request('GET', '/finance/flow'),

    // Finance — properties
    propertyList:   ()         => request('GET',    '/properties'),
    createProperty: (body)     => request('POST',   '/properties', body),
    deleteProperty: (id)       => request('DELETE', `/properties/${id}`),

    // Finance — rentals
    rentalList:   ()                  => request('GET',    '/rentals'),
    createRental: (body)              => request('POST',   '/rentals', body),
    rental:       (id)                => request('GET',  `/rentals/${id}`),
    payRental:    (id, body)          => request('POST', `/rentals/${id}/pay`, body),
    deleteRental: (id)                => request('DELETE', `/rentals/${id}`),
    rentals:      ()                  => request('GET',  '/expenses/rentals'),
    modifications:      ()             => request('GET',    '/expenses/modifications'),
    modification:       (id)           => request('GET',    `/expenses/modifications/${id}`),
    createModification: (body)         => request('POST',   '/expenses/modifications', body),
    deleteModification: (id)           => request('DELETE', `/expenses/modifications/${id}`),

    // HR
    employees:      ()     => request('GET',  '/hr/employees'),
    employee:       (id)   => request('GET',  `/hr/employees/${id}`),
    createEmployee: (body) => request('POST', '/hr/employees', body),
    departments:      ()     => request('GET',    '/hr/departments'),
    createDepartment: (body) => request('POST',   '/hr/departments', body),
    deleteDepartment: (id)   => request('DELETE', `/hr/departments/${id}`),
    jobTitles:      ()     => request('GET',  '/hr/job-titles'),

    // HR — Allowance types
    allowanceTypes:      ()     => request('GET',    '/hr/allowance-types'),
    createAllowanceType: (body) => request('POST',   '/hr/allowance-types', body),
    deleteAllowanceType: (id)   => request('DELETE', `/hr/allowance-types/${id}`),

    // HR — Payroll rule sets
    payrollRuleSets:        ()            => request('GET',    '/hr/payroll/rule-sets'),
    createPayrollRuleSet:   (body)        => request('POST',   '/hr/payroll/rule-sets', body),
    payrollRuleSet:         (id)          => request('GET',    `/hr/payroll/rule-sets/${id}`),
    updatePayrollRuleSet:   (id, body)    => request('PATCH',  `/hr/payroll/rule-sets/${id}`, body),
    deletePayrollRuleSet:   (id)          => request('DELETE', `/hr/payroll/rule-sets/${id}`),
    createPayrollRule:      (rsId, body)  => request('POST',   `/hr/payroll/rule-sets/${rsId}/rules`, body),
    updatePayrollRule:      (rsId, rId, body) => request('PATCH', `/hr/payroll/rule-sets/${rsId}/rules/${rId}`, body),
    deletePayrollRule:      (rsId, rId)   => request('DELETE', `/hr/payroll/rule-sets/${rsId}/rules/${rId}`),
    payrollTemplates:       ()            => request('GET',    '/hr/payroll/templates'),
    installPayrollTemplate: (key)         => request('POST',   `/hr/payroll/templates/${encodeURIComponent(key)}/install`),

    // HR — Payroll cycles
    payrollCycles:        ()              => request('GET',    '/hr/payroll/cycles'),
    createPayrollCycle:   (body)          => request('POST',   '/hr/payroll/cycles', body),
    payrollCycle:         (id)            => request('GET',    `/hr/payroll/cycles/${id}`),
    deletePayrollCycle:   (id)            => request('DELETE', `/hr/payroll/cycles/${id}`),
    computePayrollCycle:  (id)            => request('POST',   `/hr/payroll/cycles/${id}/compute`),
    finalizePayrollCycle: (id)            => request('POST',   `/hr/payroll/cycles/${id}/finalize`),
    payrollPayment:       (id, body)      => request('POST',   `/hr/payroll/cycles/${id}/payment`, body),
    payrollSalarySheet:   (id)            => request('GET',    `/hr/payroll/cycles/${id}/salary-sheet`),
    recomputePayrollItem: (cId, iId, body) => request('POST',  `/hr/payroll/cycles/${cId}/items/${iId}/recompute`, body),

    // Design Studio
    designs:      (type)       => request('GET',    `/design-studio/designs${type ? '?type='+encodeURIComponent(type) : ''}`),
    createDesign: (body)       => request('POST',   '/design-studio/designs', body),
    design:       (id)         => request('GET',    `/design-studio/designs/${id}`),
    updateDesign: (id, body)   => request('PATCH',  `/design-studio/designs/${id}`, body),
    deleteDesign: (id)         => request('DELETE', `/design-studio/designs/${id}`),

    // Customers
    customers:      (q, page) => request('GET',  `/customers?q=${encodeURIComponent(q || '')}&page=${page || 1}`),
    customer:       (id)   => request('GET',  `/customers/${id}`),
    createCustomer: (body) => request('POST', '/customers', body),
    updateCustomer: (id, body) => request('PATCH',  `/customers/${id}`, body),
    deleteCustomer: (id)   => request('DELETE', `/customers/${id}`),

    // Suppliers
    suppliers:      (q, page)  => request('GET',    `/suppliers?q=${encodeURIComponent(q||'')}&page=${page||1}`),
    supplier:       (id)       => request('GET',    `/suppliers/${id}`),
    createSupplier: (body)     => request('POST',   '/suppliers', body),
    updateSupplier: (id, body) => request('PATCH',  `/suppliers/${id}`, body),
    deleteSupplier: (id)       => request('DELETE', `/suppliers/${id}`),
    // Product Units
    units:          ()              => request('GET',    '/units'),
    createUnit:     (body)          => request('POST',   '/units', body),
    updateUnit:     (id, body)      => request('PATCH',  `/units/${id}`, body),
    deleteUnit:     (id)            => request('DELETE', `/units/${id}`),

    // File Manager
    fileManagerBrowse: (folderId, imagesOnly) => request('GET', `/online/file-manager?folder=${folderId||''}&images_only=${imagesOnly?1:0}`),

    // Product Brands
    brands:        (q, status)    => request('GET',    `/brands?q=${encodeURIComponent(q||'')}&status=${status||''}`),
    createBrand:   (body)         => request('POST',   '/brands', body),
    updateBrand:   (id, body)     => request('PATCH',  `/brands/${id}`, body),
    deleteBrand:   (id)           => request('DELETE', `/brands/${id}`),

    // Product Discounts
    discounts:             (q, status)     => request('GET',    `/discounts?q=${encodeURIComponent(q||'')}&status=${status||''}`),
    discountProductOpts:   (q)             => request('GET',    `/discounts/product-options?q=${encodeURIComponent(q||'')}`),
    createDiscount:        (body)          => request('POST',   '/discounts', body),
    updateDiscount:        (id, body)      => request('PATCH',  `/discounts/${id}`, body),
    deleteDiscount:        (id)            => request('DELETE', `/discounts/${id}`),

    // Product Categories
    categories:          (q, status, page) => request('GET',    `/categories?q=${encodeURIComponent(q||'')}&status=${status||''}&page=${page||1}`),
    categoryParentOpts:  (excludeId)       => request('GET',    `/categories/parent-options${excludeId ? '?exclude='+excludeId : ''}`),
    createCategory:      (body)            => request('POST',   '/categories', body),
    updateCategory:      (id, body)        => request('PATCH',  `/categories/${id}`, body),
    deleteCategory:      (id)              => request('DELETE', `/categories/${id}`),

    // Stock Audits
    stockAudits:      (page)          => request('GET',    `/stock-audits?page=${page||1}`),
    stockAudit:       (id)            => request('GET',    `/stock-audits/${id}`),
    createStockAudit: (body)          => request('POST',   '/stock-audits', body),
    saveAuditLines:   (id, body)      => request('PUT',    `/stock-audits/${id}/lines`, body),
    finalizeAudit:    (id)            => request('POST',   `/stock-audits/${id}/finalize`),
    deleteAudit:      (id)            => request('DELETE', `/stock-audits/${id}`),

    // Cheques
    cheques:       (filter)           => request('GET',  `/cheques?filter=${filter||'all'}`),
    clearCheque:   (id, body)         => request('POST', `/cheques/${id}/clear`, body || {}),

    // Goods Receive Notes
    grns:          (q, payment)       => request('GET',  `/grns?q=${encodeURIComponent(q||'')}&payment=${payment||'all'}`),
    grn:           (id)               => request('GET',  `/grns/${id}`),
    payGrn:        (id, body)         => request('POST', `/grns/${id}/pay`, body),
    grnForm:       (purchaseId)       => request('GET',  `/purchase-orders/${purchaseId}/grn-form`),
    createGrn:     (purchaseId, body) => request('POST', `/purchase-orders/${purchaseId}/grns`, body),

    purchaseOrders:(q, status)        => request('GET',  `/purchase-orders?q=${encodeURIComponent(q||'')}&status=${status||''}`),
    purchaseOrder: (id)               => request('GET',  `/purchase-orders/${id}`),
    createPO:     (body)              => request('POST', '/purchase-orders', body),
    placePO:      (id)                => request('POST', `/purchase-orders/${id}/place`),
    receivePO:    (id)                => request('POST', `/purchase-orders/${id}/receive`),
    cancelPO:     (id)                => request('POST', `/purchase-orders/${id}/cancel`),

    // Restaurant Orders
    rstBootstrap:      ()                 => request('GET',   '/restaurant/orders/bootstrap'),
    rstItemStatuses:   (qs)              => request('GET',   `/restaurant/orders/item-statuses?${qs}`),
    rstOrders:         (q, status, page)  => request('GET',   `/restaurant/orders?q=${encodeURIComponent(q||'')}&status=${status||'all'}&page=${page||1}`),
    rstOrder:          (id)               => request('GET',   `/restaurant/orders/${id}`),
    rstTableOrders:    (tableId)          => request('GET',   `/restaurant/orders?table_id=${tableId}&status=open`),
    rstCreateOrder:    (body)             => request('POST',  '/restaurant/orders', body),
    rstOrderStatus:    (id, status)       => request('PATCH', `/restaurant/orders/${id}/status`, { status }),
    rstKdsOrders:      ()                 => request('GET',   '/restaurant/orders?status=open&per_page=100'),
    rstAddItems:       (orderId, items)   => request('POST',  `/restaurant/orders/${orderId}/items`, { items }),
    rstUpdateItemStatus:(orderId, itemId, status) => request('PATCH', `/restaurant/orders/${orderId}/items/${itemId}/status`, { status }),
    rstDeleteItem:     (orderId, itemId)  => request('DELETE', `/restaurant/orders/${orderId}/items/${itemId}`),
    rstCompleteOrder:  (orderId, paymentMethod, amountTendered) =>
      request('POST', `/restaurant/orders/${orderId}/complete`, { payment_method: paymentMethod, amount_tendered: amountTendered }),
    rstClearOrder:     (orderId)          => request('DELETE', `/restaurant/orders/${orderId}`),

    // Restaurant Menu Items
    rstMenuItems:       (q, status, catId, page) => {
      const p = new URLSearchParams({ q: q||'', status: status||'all', page: page||1 });
      if (catId) p.set('category', catId);
      return request('GET', `/restaurant/menu-items?${p}`);
    },
    rstCreateMenuItem:  (body)      => request('POST',   '/restaurant/menu-items', body),
    rstMenuItem:        (id)        => request('GET',    `/restaurant/menu-items/${id}`),
    rstUpdateMenuItem:  (id, body)  => request('PUT',    `/restaurant/menu-items/${id}`, body),
    rstDeleteMenuItem:  (id)        => request('DELETE', `/restaurant/menu-items/${id}`),
    rstToggleMenuItem:          (id)        => request('PATCH', `/restaurant/menu-items/${id}/toggle`, {}),
    rstMenuItemIngredients:     (id)        => request('GET',   `/restaurant/menu-items/${id}/ingredients`),
    rstSyncMenuItemIngredients: (id, body)  => request('PUT',   `/restaurant/menu-items/${id}/ingredients`, body),

    // Restaurant Menu Categories
    rstMenuCats:          (q)         => request('GET',    `/restaurant/menu-categories?q=${encodeURIComponent(q||'')}`),
    rstCreateMenuCat:     (body)      => request('POST',   '/restaurant/menu-categories', body),
    rstUpdateMenuCat:     (id, body)  => request('PUT',    `/restaurant/menu-categories/${id}`, body),
    rstDeleteMenuCat:     (id)        => request('DELETE', `/restaurant/menu-categories/${id}`),
    rstReorderMenuCats:   (ids)       => request('POST',   '/restaurant/menu-categories/reorder', { ids }),

    // Restaurant Ingredients
    rstIngredients:             (q)         => request('GET',    `/restaurant/ingredients?q=${encodeURIComponent(q||'')}`),
    rstCreateIngredient:        (body)      => request('POST',   '/restaurant/ingredients', body),
    rstUpdateIngredient:        (id, body)  => request('PUT',    `/restaurant/ingredients/${id}`, body),
    rstDeleteIngredient:        (id)        => request('DELETE', `/restaurant/ingredients/${id}`),
    rstStockIn:                 (id, body)  => request('POST',   `/restaurant/ingredients/${id}/stock-in`, body),
    rstIngredientTransactions:  (id)        => request('GET',    `/restaurant/ingredients/${id}/transactions`),

    // Ingredient Purchase Orders
    rstPoList:       (status, page) => request('GET',    `/restaurant/purchase-orders?status=${status||'all'}&page=${page||1}`),
    rstPoShow:       (id)           => request('GET',    `/restaurant/purchase-orders/${id}`),
    rstPoCreate:     (body)         => request('POST',   '/restaurant/purchase-orders', body),
    rstPoUpdate:     (id, body)     => request('PUT',    `/restaurant/purchase-orders/${id}`, body),
    rstPoPlace:      (id)           => request('POST',   `/restaurant/purchase-orders/${id}/place-order`, {}),
    rstPoCancel:     (id)           => request('POST',   `/restaurant/purchase-orders/${id}/cancel`, {}),
    rstPoDelete:     (id)           => request('DELETE', `/restaurant/purchase-orders/${id}`),
    rstPoCreateGrn:  (id, body)     => request('POST',   `/restaurant/purchase-orders/${id}/grn`, body),

    // Restaurant Tables
    rstTables:              ()          => request('GET',    '/restaurant/tables'),
    rstCreateTable:         (body)      => request('POST',   '/restaurant/tables', body),
    rstUpdateTable:         (id, body)  => request('PUT',    `/restaurant/tables/${id}`, body),
    rstDeleteTable:         (id)        => request('DELETE', `/restaurant/tables/${id}`),
    rstSaveTablePositions:  (positions) => request('POST',   '/restaurant/tables/positions', { positions }),

    // Service POS
    servicePosCatalog:  (q, catId) => request('GET',  `/service/pos/catalog?q=${encodeURIComponent(q || '')}&category=${catId || ''}`),
    servicePosCheckout: (body)     => request('POST', '/service/pos/checkout', body),

    // Service management (Services tab)
    serviceRequests:           (q, status) => request('GET',   `/service/requests?q=${encodeURIComponent(q || '')}&status=${encodeURIComponent(status || '')}`),
    updateServiceRequestStatus:(id, status) => request('PATCH', `/service/requests/${id}/status`, { status }),
    serviceMgmtCatalog:        (q)         => request('GET',   `/service/catalog?q=${encodeURIComponent(q || '')}`),
    createServiceItem:         (body)      => request('POST',  '/service/catalog', body),
    serviceItemDetail:         (id)        => request('GET',   `/service/catalog/${id}`),
    updateServiceItem:         (id, body)  => request('PATCH',  `/service/catalog/${id}`, body),
    deleteServiceItem:         (id)        => request('DELETE', `/service/catalog/${id}`),
    syncServiceEmployees:      (id, body)  => request('PUT',    `/service/catalog/${id}/employees`, body),
    syncServiceProducts:       (id, body)  => request('PUT',    `/service/catalog/${id}/products`,  body),
    deleteServiceCategory:     (id)        => request('DELETE', `/service/categories/${id}`),
    serviceMgmtCategories:     ()          => request('GET',   '/service/categories'),
    createServiceCategory:     (body)      => request('POST',  '/service/categories', body),
  };
})();
