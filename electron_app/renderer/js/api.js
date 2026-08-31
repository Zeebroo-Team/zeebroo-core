'use strict';

const API = (() => {
  async function request(method, path, body = null) {
    const res = await window.electronAPI.apiRequest(method, path, body);
    if (res.status === 401) {
      window.dispatchEvent(new CustomEvent('api-unauthorized'));
    }
    return res;
  }

  // Public request — does NOT fire api-unauthorized on 401 (used for login endpoints)
  async function publicRequest(method, path, body = null) {
    return window.electronAPI.apiRequest(method, path, body);
  }

  return {
    // Auth
    login:        (email, password, deviceName) =>
      publicRequest('POST', '/auth/token', { email, password, device_name: deviceName }),
    cashierLogin: (slug, username, password) =>
      publicRequest('POST', '/cashier/login', { slug, username, password }),
    register:           (name, businessName, businessCategory, features, email, password, deviceName) =>
      publicRequest('POST', '/auth/register', { name, business_name: businessName, business_category: businessCategory, features, email, password, password_confirmation: password, device_name: deviceName }),
    businessCategories: () => publicRequest('GET', '/auth/business-categories'),
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
    productSalesChart:  (id, period) => request('GET', `/online/products/${id}/sales-chart?period=${period || 'weekly'}`),
    productStockHistory:(id)        => request('GET', `/online/products/${id}/stock-history`),
    updateLayerSellingPrice: (productId, layerId, price) =>
      request('PATCH', `/online/products/${productId}/stock-layers/${layerId}/selling-price`, { selling_unit_price: price }),
    updateLayerWholesalePrice: (productId, layerId, price) =>
      request('PATCH', `/online/products/${productId}/stock-layers/${layerId}/wholesale-price`, { wholesale_unit_price: price }),
    updateLayerCostPrice: (productId, layerId, cost) =>
      request('PATCH', `/online/products/${productId}/stock-layers/${layerId}/cost-price`, { unit_cost: cost }),
    productBySku: (sku)               => request('GET',  `/online/products/sku/${encodeURIComponent(sku)}`),
    backfillBatchSkus: (productId)    => request('POST', `/online/products/${productId}/backfill-batch-skus`),
    checkout:     (body)              => request('POST', '/online/checkout', body),
    createProduct: (body)              => request('POST',   '/online/products', body),
    importProducts:(rows)             => request('POST',   '/online/products/import', { rows }),
    updateProduct: (id, body)          => request('PATCH',  `/online/products/${id}`, body),
    deleteProduct: (id)                => request('DELETE', `/online/products/${id}`),

    // Invoices
    invoices:       (q, status) => request('GET',    `/invoices?q=${encodeURIComponent(q||'')}&status=${status||'all'}`),
    invoice:        (id)        => request('GET',    `/invoices/${id}`),
    createInvoice:  (body)      => request('POST',   '/invoices', body),
    updateInvoice:  (id, body)  => request('PATCH',  `/invoices/${id}`, body),
    invoiceEnableShare: (id)    => request('POST',   `/invoices/${id}/enable-share`),
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

    // Sales Orders
    salesOrders:          (q, status) => request('GET',    `/sales-orders?q=${encodeURIComponent(q||'')}&status=${status||'all'}`),
    salesOrder:           (id)        => request('GET',    `/sales-orders/${id}`),
    createSalesOrder:     (body)      => request('POST',   '/sales-orders', body),
    updateSalesOrder:     (id, body)  => request('PATCH',  `/sales-orders/${id}`, body),
    confirmSalesOrder:    (id)        => request('POST',   `/sales-orders/${id}/confirm`),
    processSalesOrder:    (id)        => request('POST',   `/sales-orders/${id}/process`),
    completeSalesOrder:   (id)        => request('POST',   `/sales-orders/${id}/complete`),
    cancelSalesOrder:     (id)        => request('POST',   `/sales-orders/${id}/cancel`),
    deleteSalesOrder:     (id)        => request('DELETE', `/sales-orders/${id}`),

    // End of Day
    eodStatus:  () => request('GET',  '/eod'),
    eodSettle:  () => request('POST', '/eod/settle'),

    // Today Summary
    todaySummary: () => request('GET', '/today-summary'),

    // Sales
    sales:          (q, limit)        => request('GET',  `/sales?q=${encodeURIComponent(q)}${limit ? '&limit='+limit : ''}`),
    sale:           (id)              => request('GET',  `/sales/${id}`),
    pendingCredits: ()                => request('GET',  '/sales/pending-credits'),
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
    featureReviews:        (key)       => request('GET',  `/features/${encodeURIComponent(key)}/reviews`),
    submitFeatureReview:   (key, data) => request('POST', `/features/${encodeURIComponent(key)}/reviews`, data),
    featureReviewsSummary: ()          => request('GET',  '/features/reviews/summary'),
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
    designs:        (type)        => request('GET',    `/design-studio/designs${type ? '?type='+encodeURIComponent(type) : ''}`),
    createDesign:   (body)        => request('POST',   '/design-studio/designs', body),
    design:         (id)          => request('GET',    `/design-studio/designs/${id}`),
    updateDesign:   (id, body)    => request('PATCH',  `/design-studio/designs/${id}`, body),
    deleteDesign:   (id)          => request('DELETE', `/design-studio/designs/${id}`),
    designAiChat:   (message)     => request('POST',   '/design-studio/ai-chat', { message }),
    proposals:      ()            => request('GET',    '/design-studio/proposals'),
    proposalPages:  (group)       => request('GET',    `/design-studio/proposals/${encodeURIComponent(group)}/pages`),
    createProposal:     (body)    => request('POST',   '/design-studio/proposals', body),
    aiProposalContent:  (body)    => request('POST',   '/design-studio/proposals/ai-content', body),
    aiProposalFill:     (group, body) => request('POST', `/design-studio/proposals/${encodeURIComponent(group)}/ai-fill`, body),
    addProposalPage:    (group, body) => request('POST', `/design-studio/proposals/${encodeURIComponent(group)}/pages`, body),
    deleteProposal:     (group)   => request('DELETE', `/design-studio/proposals/${encodeURIComponent(group)}`),
    linkProposalToInvoice: (group, invoiceId) => request('POST', `/design-studio/proposals/${encodeURIComponent(group)}/link-invoice`, { invoice_id: invoiceId }),

    // Customers
    customers:      (q, page) => request('GET',  `/customers?q=${encodeURIComponent(q || '')}&page=${page || 1}`),
    customer:       (id)   => request('GET',  `/customers/${id}`),
    createCustomer: (body) => request('POST', '/customers', body),
    importCustomers:(rows) => request('POST', '/customers/import', { rows }),
    updateCustomer: (id, body) => request('PATCH',  `/customers/${id}`, body),
    deleteCustomer: (id)   => request('DELETE', `/customers/${id}`),

    // Customer Categories
    customerCategories:      () => request('GET',    '/customer-categories'),
    createCustomerCategory:  (body) => request('POST',   '/customer-categories', body),
    updateCustomerCategory:  (id, body) => request('PATCH',  `/customer-categories/${id}`, body),
    deleteCustomerCategory:  (id)   => request('DELETE', `/customer-categories/${id}`),

    // Suppliers
    suppliers:      (q, page)  => request('GET',    `/suppliers?q=${encodeURIComponent(q||'')}&page=${page||1}`),
    supplier:       (id)       => request('GET',    `/suppliers/${id}`),
    createSupplier: (body)     => request('POST',   '/suppliers', body),
    importSuppliers:(rows)     => request('POST',   '/suppliers/import', { rows }),
    updateSupplier: (id, body) => request('PATCH',  `/suppliers/${id}`, body),
    deleteSupplier: (id)       => request('DELETE', `/suppliers/${id}`),

    // Supplier Categories
    supplierCategories:      () => request('GET',    '/supplier-categories'),
    createSupplierCategory:  (body) => request('POST',   '/supplier-categories', body),
    updateSupplierCategory:  (id, body) => request('PATCH',  `/supplier-categories/${id}`, body),
    deleteSupplierCategory:  (id)   => request('DELETE', `/supplier-categories/${id}`),
    // Product Units
    units:          ()              => request('GET',    '/units'),
    createUnit:     (body)          => request('POST',   '/units', body),
    updateUnit:     (id, body)      => request('PATCH',  `/units/${id}`, body),
    deleteUnit:     (id)            => request('DELETE', `/units/${id}`),

    // File Manager
    fileManagerBrowse: (folderId, imagesOnly) => request('GET', `/online/file-manager?folder=${folderId||''}&images_only=${imagesOnly?1:0}`),

    // Product Brands
    productBrands:       (q, status)  => request('GET',    `/brands?q=${encodeURIComponent(q||'')}&status=${status||''}`),
    productBrandCreate:  (body)       => request('POST',   '/brands', body),
    productBrandUpdate:  (id, body)   => request('PATCH',  `/brands/${id}`, body),
    productBrandDelete:  (id)         => request('DELETE', `/brands/${id}`),

    // Product Discounts
    discounts:             (q, status)     => request('GET',    `/discounts?q=${encodeURIComponent(q||'')}&status=${status||''}`),
    discountProductOpts:   (q)             => request('GET',    `/discounts/product-options?q=${encodeURIComponent(q||'')}`),
    createDiscount:        (body)          => request('POST',   '/discounts', body),
    updateDiscount:        (id, body)      => request('PATCH',  `/discounts/${id}`, body),
    deleteDiscount:        (id)            => request('DELETE', `/discounts/${id}`),

    // Product Categories
    categories:             (q, status, page) => request('GET',    `/categories?q=${encodeURIComponent(q||'')}&status=${status||''}&page=${page||1}`),
    categoryParentOpts:     (excludeId)       => request('GET',    `/categories/parent-options${excludeId ? '?exclude='+excludeId : ''}`),
    generateAiCategories:   (description)     => request('POST',   '/categories/generate-ai', { description }),
    createCategory:         (body)            => request('POST',   '/categories', body),
    updateCategory:         (id, body)        => request('PATCH',  `/categories/${id}`, body),
    deleteCategory:         (id)              => request('DELETE', `/categories/${id}`),

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
    grns:              (q, payment)       => request('GET',  `/grns?q=${encodeURIComponent(q||'')}&payment=${payment||'all'}`),
    grn:               (id)               => request('GET',  `/grns/${id}`),
    payGrn:            (id, body)         => request('POST', `/grns/${id}/pay`, body),
    approveGrn:        (id)               => request('POST', `/grns/${id}/approve`, {}),
    rejectGrn:         (id)               => request('POST', `/grns/${id}/reject`,  {}),
    grnForm:           (purchaseId)       => request('GET',  `/purchase-orders/${purchaseId}/grn-form`),
    createGrn:         (purchaseId, body) => request('POST', `/purchase-orders/${purchaseId}/grns`, body),
    createDirectGrn:   (body)             => request('POST', `/grns`, body),
    grnPermissions:    ()                 => request('GET',  `/grn-permissions`),
    saveGrnPermissions:(body)             => request('PUT',  `/grn-permissions`, body),

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

    // User Management
    memberMe:    ()                  => request('GET',    '/me'),
    usersList:   ()                  => request('GET',    '/users'),
    usersAdd:    (body)              => request('POST',   '/users', body),
    usersUpdate: (memberId, body)    => request('PUT',    `/users/${memberId}`, body),
    usersRemove: (memberId)          => request('DELETE', `/users/${memberId}`),

    // Role Management
    rolesList:   ()               => request('GET',    '/roles'),
    rolesCreate: (body)           => request('POST',   '/roles', body),
    rolesUpdate: (id, body)       => request('PUT',    `/roles/${id}`, body),
    rolesDelete: (id)             => request('DELETE', `/roles/${id}`),

    // Branch Management
    branchesList: ()         => request('GET',    '/branches'),
    branchAdd:    (body)     => request('POST',   '/branches', body),
    branchUpdate: (id, body) => request('PUT',    `/branches/${id}`, body),
    branchRemove: (id)       => request('DELETE', `/branches/${id}`),

    // Service POS
    servicePosCatalog:  (q, catId) => request('GET',  `/service/pos/catalog?q=${encodeURIComponent(q || '')}&category=${catId || ''}`),
    servicePosCheckout: (body)     => request('POST', '/service/pos/checkout', body),

    // Service management (Services tab)
    serviceRequests:           (q, status) => request('GET',   `/service/requests?q=${encodeURIComponent(q || '')}&status=${encodeURIComponent(status || '')}`),
    createServiceRequest:      (body)      => request('POST',  '/service/requests', body),
    updateServiceRequestStatus:(id, status, projectId, createTask) => request('PATCH', `/service/requests/${id}/status`, { status, ...(projectId != null ? { project_id: projectId } : {}), ...(createTask === false ? { create_task: false } : {}) }),
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

    guideChat:  (message)             => request('POST', '/guide/chat',  { message }),
    guideVoice: (audio, mime_type)    => request('POST', '/guide/voice', { audio, mime_type }),

    // CRM
    crmProjects:     ()                   => request('GET',    '/crm/projects'),
    crmCreateProject:(body)               => request('POST',   '/crm/projects', body),
    crmPipeline:     (projectId)          => request('GET',    `/crm/projects/${projectId}/pipeline`),
    crmStages:       (projectId)          => request('GET',    `/crm/projects/${projectId}/stages`),
    crmCreateStage:  (projectId, body)    => request('POST',   `/crm/projects/${projectId}/stages`, body),
    crmUpdateStage:  (projectId, stageId, body) => request('PUT', `/crm/projects/${projectId}/stages/${stageId}`, body),
    crmDeleteStage:  (projectId, stageId) => request('DELETE', `/crm/projects/${projectId}/stages/${stageId}`),
    crmReorderStages:    (projectId, ids)              => request('POST',   `/crm/projects/${projectId}/stages/reorder`, { ids }),
    crmAutomations:      (projectId, stageId)          => request('GET',    `/crm/projects/${projectId}/stages/${stageId}/automations`),
    crmCreateAutomation: (projectId, stageId, body)    => request('POST',   `/crm/projects/${projectId}/stages/${stageId}/automations`, body),
    crmUpdateAutomation: (projectId, stageId, autoId, body) => request('PUT', `/crm/projects/${projectId}/stages/${stageId}/automations/${autoId}`, body),
    crmDeleteAutomation: (projectId, stageId, autoId)  => request('DELETE', `/crm/projects/${projectId}/stages/${stageId}/automations/${autoId}`),
    crmCreateLead:   (projectId, body)    => request('POST',   `/crm/projects/${projectId}/leads`, body),
    crmUpdateLead:   (leadId, body)       => request('PUT',    `/crm/leads/${leadId}`, body),
    crmMoveLead:     (leadId, stageId)    => request('POST',   `/crm/leads/${leadId}/move-stage`, { stage_id: stageId }),
    crmDeleteLead:   (leadId)             => request('DELETE', `/crm/leads/${leadId}`),
    crmContacts:     (q)                  => request('GET',    `/crm/contacts?q=${encodeURIComponent(q||'')}`),
    crmTasks:        (filter)             => request('GET',    `/crm/tasks?filter=${filter||'open'}`),
    crmCreateTask:   (body)               => request('POST',   '/crm/tasks', body),
    crmCompleteTask: (taskId)             => request('POST',   `/crm/tasks/${taskId}/complete`),
    crmReopenTask:   (taskId)             => request('POST',   `/crm/tasks/${taskId}/reopen`),
    crmDeleteTask:   (taskId)             => request('DELETE', `/crm/tasks/${taskId}`),
    // CRM Forms
    crmForms:             (projectId)                 => request('GET',    `/crm/projects/${projectId}/forms`),
    crmCreateForm:        (projectId, body)           => request('POST',   `/crm/projects/${projectId}/forms`, body),
    crmGetForm:           (projectId, formId)         => request('GET',    `/crm/projects/${projectId}/forms/${formId}`),
    crmUpdateForm:        (projectId, formId, body)   => request('PUT',    `/crm/projects/${projectId}/forms/${formId}`, body),
    crmPublishForm:       (projectId, formId)         => request('POST',   `/crm/projects/${projectId}/forms/${formId}/publish`),
    crmUnpublishForm:     (projectId, formId)         => request('POST',   `/crm/projects/${projectId}/forms/${formId}/unpublish`),
    crmDeleteForm:        (projectId, formId)         => request('DELETE', `/crm/projects/${projectId}/forms/${formId}`),
    crmFormTemplates:     ()                          => request('GET',    '/crm/form-templates'),
    // CRM Custom Fields
    crmCustomFields:      (projectId)                 => request('GET',    `/crm/projects/${projectId}/custom-fields`),
    crmCreateCustomField: (projectId, body)           => request('POST',   `/crm/projects/${projectId}/custom-fields`, body),

    // Cash Drawer
    cashDrawer:         ()               => request('GET',  '/cash-drawer'),
    cashDrawerOpen:     (amount)         => request('POST', '/cash-drawer/open',     { opening_float: amount }),
    cashDrawerWithdraw: (amount, note)   => request('POST', '/cash-drawer/withdraw', { amount, note }),

    // Mail
    mailSync:           ()               => request('POST', '/mail/sync'),
    mailThreads:        (box, q, status) => request('GET', `/mail/threads?box=${box||'inbox'}&q=${encodeURIComponent(q||'')}&status=${status||''}`),
    mailThread:         (contact, box)   => request('GET', `/mail/thread?contact=${encodeURIComponent(contact)}&box=${box||'inbox'}`),
    mailMessages:       (box, q, status) => request('GET', `/mail/messages?box=${box||'inbox'}&q=${encodeURIComponent(q||'')}&status=${status||''}`),
    mailMessage:        (id)             => request('GET', `/mail/messages/${id}`),
    mailMarkRead:       (id)             => request('POST', `/mail/messages/${id}/read`),
    mailSend:           (body)           => request('POST', '/mail/send', body),
    mailTemplates:      (q)              => request('GET', `/mail/templates?q=${encodeURIComponent(q||'')}`),
    mailCreateTemplate: (body)           => request('POST', '/mail/templates', body),
    mailDeleteTemplate: (id)             => request('DELETE', `/mail/templates/${id}`),
    mailScheduled:      ()               => request('GET', '/mail/scheduled'),
    mailCancelScheduled:(id)             => request('DELETE', `/mail/scheduled/${id}`),
    mailFilters:        ()               => request('GET', '/mail/filters'),
    mailSettingsGet:    ()               => request('GET', '/mail/settings'),
    mailSettingsSave:   (body)           => request('PATCH', '/mail/settings', body),
    mailTestSend:       (to)             => request('POST', '/mail/test', { to }),
    mailVerifyCreds:    ()               => request('POST', '/mail/verify-credentials'),

    // Register lock
    verifyPassword: (password) => request('POST', '/verify-password', { password }),

    // POS Counters
    counters:       (branchId)  => request('GET', `/counters${branchId ? `?branch_id=${branchId}` : ''}`),
    counterCreate:  (body)      => request('POST',   '/counters',           body),
    counterUpdate:  (id, body)  => request('PATCH',  `/counters/${id}`,     body),
    counterDelete:  (id)        => request('DELETE',  `/counters/${id}`),
    cashiers:       ()          => request('GET',    '/cashiers'),
    cashierCreate:  (body)      => request('POST',   '/cashiers',           body),
    cashierUpdate:  (id, body)  => request('PATCH',  `/cashiers/${id}`,     body),
    cashierDelete:  (id)        => request('DELETE',  `/cashiers/${id}`),

    // Automations
    automations:       ()        => request('GET',    '/automations'),
    automationGet:     (id)      => request('GET',    `/automations/${id}`),
    automationCreate:  (body)    => request('POST',   '/automations', body),
    automationUpdate:  (id,body) => request('PATCH',  `/automations/${id}`, body),
    automationDelete:  (id)      => request('DELETE', `/automations/${id}`),
    automationRuns:    (id)      => request('GET',    `/automations/${id}/runs`),
    automationTrigger: (id,body) => request('POST',   `/automations/${id}/trigger`, body || {}),
    automationNotifications: ()  => request('GET',    '/automation-notifications'),
    automationNotificationsRead: () => request('POST', '/automation-notifications/read-all', {}),

    // Project Management
    pmProjects:            (filter)    => request('GET',    `/pm/projects${filter ? `?filter=${filter}` : ''}`),
    pmProjectCreate:       (body)      => request('POST',   '/pm/projects', body),
    pmProjectUpdate:       (id, body)  => request('PATCH',  `/pm/projects/${id}`, body),
    pmProjectDelete:       (id)        => request('DELETE', `/pm/projects/${id}`),
    pmBoard:               (id)        => request('GET',    `/pm/projects/${id}/board`),
    pmTasks:               (id, qs)    => request('GET',    `/pm/projects/${id}/tasks${qs ? `?${qs}` : ''}`),
    pmTaskCreate:          (id, body)  => request('POST',   `/pm/projects/${id}/tasks`, body),
    pmTaskStatus:          (id, status)=> request('PATCH',  `/pm/tasks/${id}/status`, { status }),
    pmTaskComplete:        (id)        => request('POST',   `/pm/tasks/${id}/complete`, {}),
    pmTaskReopen:          (id)        => request('POST',   `/pm/tasks/${id}/reopen`, {}),
    pmTaskComment:         (id, body)  => request('POST',   `/pm/tasks/${id}/comments`, { body }),
    pmTaskTime:            (id, data)  => request('POST',   `/pm/tasks/${id}/time`, data),
    pmTaskDelete:          (id)        => request('DELETE', `/pm/tasks/${id}`),
    pmMyTasks:             (filter)    => request('GET',    `/pm/my-tasks${filter ? `?filter=${filter}` : ''}`),
    pmMilestones:          (pid)       => request('GET',    `/pm/projects/${pid}/milestones`),
    pmMilestoneCreate:     (pid, body) => request('POST',   `/pm/projects/${pid}/milestones`, body),
    pmMilestoneComplete:   (id)        => request('POST',   `/pm/milestones/${id}/complete`, {}),
    pmMilestoneDelete:     (id)        => request('DELETE', `/pm/milestones/${id}`),

    // Brand Management
    brands:       (q)          => request('GET',    `/brand-mgmt/brands?q=${encodeURIComponent(q||'')}`),
    brandCreate:  (body)       => request('POST',   '/brand-mgmt/brands', body),
    brandImport:  (body)       => request('POST',   '/brand-mgmt/brands/import', body),
    brandUpdate:  (id, body)   => request('PUT',    `/brand-mgmt/brands/${id}`, body),
    brandDelete:  (id)         => request('DELETE', `/brand-mgmt/brands/${id}`),

    // Reporters
    reporters:      (q)          => request('GET',    `/brand-mgmt/reporters?q=${encodeURIComponent(q||'')}`),
    reporterCreate: (body)       => request('POST',   '/brand-mgmt/reporters', body),
    reporterUpdate: (id, body)   => request('PUT',    `/brand-mgmt/reporters/${id}`, body),
    reporterDelete: (id)         => request('DELETE', `/brand-mgmt/reporters/${id}`),

    // Officers
    officers:      (q)          => request('GET',    `/brand-mgmt/officers?q=${encodeURIComponent(q||'')}`),
    officerCreate: (body)       => request('POST',   '/brand-mgmt/officers', body),
    officerUpdate: (id, body)   => request('PUT',    `/brand-mgmt/officers/${id}`, body),
    officerDelete: (id)         => request('DELETE', `/brand-mgmt/officers/${id}`),

    // Advertising Agencies
    agencies:       (q)        => request('GET',    `/brand-mgmt/agencies?q=${encodeURIComponent(q||'')}`),
    agencyCreate:   (body)     => request('POST',   '/brand-mgmt/agencies', body),
    agencyUpdate:   (id, body) => request('PUT',    `/brand-mgmt/agencies/${id}`, body),
    agencyDelete:   (id)       => request('DELETE', `/brand-mgmt/agencies/${id}`),

    // Salary Sheets
    salarySheetNextRef:  ()         => request('GET',    '/brand-mgmt/salary-sheets/next-ref'),
    salarySheets:        (q)        => request('GET',    `/brand-mgmt/salary-sheets?q=${encodeURIComponent(q||'')}`),
    salarySheetCreate:   (body)     => request('POST',   '/brand-mgmt/salary-sheets', body),
    salarySheetGet:      (id)       => request('GET',    `/brand-mgmt/salary-sheets/${id}`),
    salarySheetUpdate:   (id, body) => request('PUT',    `/brand-mgmt/salary-sheets/${id}`, body),
    salarySheetDelete:   (id)       => request('DELETE', `/brand-mgmt/salary-sheets/${id}`),
    salarySheetSaveRows: (id, body) => request('POST',   `/brand-mgmt/salary-sheets/${id}/save-rows`, body),

    // Coordinators
    coordinators:       (q)        => request('GET',    `/brand-mgmt/coordinators?q=${encodeURIComponent(q||'')}`),
    coordinatorCreate:  (body)     => request('POST',   '/brand-mgmt/coordinators', body),
    coordinatorUpdate:  (id, body) => request('PUT',    `/brand-mgmt/coordinators/${id}`, body),
    coordinatorDelete:  (id)       => request('DELETE', `/brand-mgmt/coordinators/${id}`),

    // Promoters
    promoters:       (q)        => request('GET',    `/brand-mgmt/promoters?q=${encodeURIComponent(q||'')}`),
    promoterCreate:  (body)     => request('POST',   '/brand-mgmt/promoters', body),
    promoterUpdate:  (id, body) => request('PUT',    `/brand-mgmt/promoters/${id}`, body),
    promoterDelete:  (id)       => request('DELETE', `/brand-mgmt/promoters/${id}`),

    // Promoter Positions
    promoterPositions:       (q)        => request('GET',    `/brand-mgmt/promoter-positions?q=${encodeURIComponent(q||'')}`),
    promoterPositionCreate:  (body)     => request('POST',   '/brand-mgmt/promoter-positions', body),
    promoterPositionUpdate:  (id, body) => request('PUT',    `/brand-mgmt/promoter-positions/${id}`, body),
    promoterPositionDelete:  (id)       => request('DELETE', `/brand-mgmt/promoter-positions/${id}`),

    // Jobs
    jobs:        (q, status)  => request('GET',    `/brand-mgmt/jobs?q=${encodeURIComponent(q||'')}&status=${status||''}`),
    jobCreate:   (body)       => request('POST',   '/brand-mgmt/jobs', body),
    jobImport:   (body)       => request('POST',   '/brand-mgmt/jobs/import', body),
    jobUpdate:   (id, body)   => request('PUT',    `/brand-mgmt/jobs/${id}`, body),
    jobDelete:   (id)         => request('DELETE', `/brand-mgmt/jobs/${id}`),

    // Raw passthrough for ad-hoc requests
    _raw: (method, path, body = null) => request(method, path, body),

    // Developers — API Keys
    devKeys:            ()       => request('GET',    '/developers/keys'),
    devKeyCreate:       (body)   => request('POST',   '/developers/keys', body),
    devKeyToggle:       (id)     => request('PATCH',  `/developers/keys/${id}/toggle`),
    devKeyDelete:       (id)     => request('DELETE', `/developers/keys/${id}`),

    // Developers — Webhooks
    devWebhooks:               ()        => request('GET',    '/developers/webhooks'),
    devWebhookCreate:          (body)    => request('POST',   '/developers/webhooks', body),
    devWebhookUpdate:          (id,body) => request('PATCH',  `/developers/webhooks/${id}`, body),
    devWebhookRegenerateSecret:(id)      => request('POST',   `/developers/webhooks/${id}/regenerate-secret`),
    devWebhookDelete:          (id)      => request('DELETE', `/developers/webhooks/${id}`),
  };
})();
