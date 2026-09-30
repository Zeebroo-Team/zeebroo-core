'use strict';

// Stock — an in-page modal dialog, opened by calling window.openStockModal()
// (see the dashboard's "Stock" tile in js/dashboard.js). Mirrors js/sales.js
// exactly: builds its own DOM on open and tears it down on close, reuses the
// same .salm-* / .qf-* CSS classes from css/sales.css (they were written
// generically, not "sales"-specific), and talks to the same Laravel POS API
// (Modules/Pos/routes/api.php, prefix /api/v1/pos) via js/api.js.
//
// Inside the dialog: a card-grid home (Purchase Orders / Goods Receive /
// Cheques / Stock Transfer) and each section's own list — see
// #stk-home-view / #stk-section-view below. A single record's detail
// (#stk-detail-view) is a popup layered on top of whichever is active.
(function () {
  let cardEl, homeView, sectionView, detailView;
  let sectionTitleEl, sectionDescEl, sectionBody;
  let detailTitleEl, detailBody, detailFoot;
  let toastEl;

  let posSettings = {};
  let settingsLoaded = false;

  const SECTIONS = [
    { key: 'purchase-orders', icon: 'fa-file-invoice', title: 'Purchase Orders', desc: 'Create and track supplier purchase orders.', accent: '#2563eb' },
    { key: 'goods-receive', icon: 'fa-dolly', title: 'Goods Receive', desc: 'Record incoming stock and supplier payments.', accent: '#059669' },
    { key: 'cheques', icon: 'fa-money-check-dollar', title: 'Cheques', desc: 'Track supplier cheque payments and due dates.', accent: '#d97706' },
    { key: 'stock-transfers', icon: 'fa-right-left', title: 'Stock Transfer', desc: 'Move stock between branches.', accent: '#7c3aed' },
  ];

  // ── Small shared helpers (same as js/sales.js) ──────────────────────────
  function esc(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function rgb(hex) {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }
  function glow(hex, alpha) { const [r, g, b] = rgb(hex); return `rgba(${r}, ${g}, ${b}, ${alpha})`; }
  function lighten(hex, amount) {
    const [r, g, b] = rgb(hex).map((c) => Math.round(c + (255 - c) * amount));
    return `rgb(${r}, ${g}, ${b})`;
  }

  function money(n) {
    const amount = (Number(n) || 0).toFixed(2);
    const currency = (posSettings.currency || 'LKR').toUpperCase();
    return posSettings.currency_position === 'before' ? `${currency} ${amount}` : `${amount} ${currency}`;
  }

  function fmtDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString(i18n.locale, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  // Fills a <select> with this business's branches and defaults it to the
  // given branch (e.g. a PO's own branch) or, failing that, the currently
  // active branch picked in the top navbar switcher.
  async function populateBranchSelect(selectEl, presetBranchId) {
    if (!selectEl) return;
    const res = await API.branches();
    const branches = res.status === 200 ? (res.body.data || []) : [];
    if (branches.length) {
      selectEl.insertAdjacentHTML('beforeend', branches.map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join(''));
    }
    let branchId = presetBranchId || null;
    if (!branchId) {
      try {
        const cfg = await window.electronAPI.getConfig();
        branchId = cfg.branch_id || null;
      } catch (_) { /* no active branch configured */ }
    }
    if (branchId && branches.some((b) => b.id === branchId)) {
      selectEl.value = String(branchId);
    }
  }

  function fmtDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleString(i18n.locale, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function debounce(fn, ms) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
  }

  function showToast(message, type = '') {
    toastEl.textContent = message;
    toastEl.className = `salm-toast show ${type}`;
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => { toastEl.className = 'salm-toast'; }, 3000);
  }

  const BADGE_CLASS = {
    completed: 'green', accepted: 'green', active: 'green', returned: 'green', received: 'green', approved: 'green', paid_full: 'green', cleared: 'green',
    void: 'red', rejected: 'red', cancelled: 'red', overdue: 'red',
    sent: 'blue', trial: 'blue', ordered: 'blue', in_transit: 'blue',
    draft: 'gray', paused: 'gray', pending: 'gray', no_amount: 'gray',
    expired: 'amber', partially_received: 'amber', paid_partial: 'amber', due: 'amber',
  };
  function badgeClass(status) { return BADGE_CLASS[status] || 'gray'; }

  // Some relations (`transferredBy`, `receivedBy`, `cancelledBy`) share a
  // snake-cased key with a plain `*_id`/`*_by` column on StockTransfer; when
  // Eloquent serializes both, the loaded relation object wins in the JSON.
  // Guard so a still-raw id doesn't get treated as a name.
  function personName(v) { return v && typeof v === 'object' ? (v.name || null) : null; }

  function renderPagination(el, meta, onPage) {
    if (!meta || meta.last_page <= 1) { el.innerHTML = ''; return; }
    el.innerHTML = `
      <button type="button" data-page="${meta.current_page - 1}" ${meta.current_page <= 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left"></i></button>
      <span>${t('Page {p} of {n}', { p: meta.current_page, n: meta.last_page })}</span>
      <button type="button" data-page="${meta.current_page + 1}" ${meta.current_page >= meta.last_page ? 'disabled' : ''}><i class="fa-solid fa-chevron-right"></i></button>`;
    el.querySelectorAll('button[data-page]').forEach((b) => b.addEventListener('click', () => onPage(Number(b.dataset.page))));
  }

  async function loadSettings() {
    if (settingsLoaded) return;
    const res = await API.settingsGet();
    if (res.status === 200) posSettings = res.body?.data || {};
    settingsLoaded = true;
  }

  // ── View switching (home ↔ section list) ────────────────────────────────
  function showView(name) {
    homeView.style.display = name === 'home' ? '' : 'none';
    sectionView.style.display = name === 'section' ? '' : 'none';
    cardEl.classList.toggle('salm-modal-card--wide', name === 'section');
    window.scrollTo(0, 0);
  }

  function goHome() {
    closeDetail();
    document.getElementById('stk-grid').innerHTML = SECTIONS.map((s) => `
      <button class="salm-tile" data-key="${s.key}" style="--tile-accent:${s.accent};--tile-accent-2:${lighten(s.accent, 0.35)};--tile-glow:${glow(s.accent, 0.25)}">
        <div class="salm-tile-icon"><i class="fa-solid ${s.icon}"></i></div>
        <div class="salm-tile-title">${t(s.title)}</div>
        <p class="salm-tile-desc">${t(s.desc)}</p>
        <span class="salm-tile-open">${t('Open')} <i class="fa-solid fa-arrow-right"></i></span>
      </button>`).join('');

    document.querySelectorAll('#stk-grid .salm-tile').forEach((el) => {
      el.addEventListener('click', () => openSection(el.dataset.key));
    });

    showView('home');
  }

  const VIEWS = {
    'purchase-orders': renderPurchaseOrders,
    'goods-receive': renderGoodsReceive,
    cheques: renderCheques,
    'stock-transfers': renderStockTransfers,
  };

  function openSection(key) {
    closeDetail();
    const meta = SECTIONS.find((s) => s.key === key);
    sectionTitleEl.textContent = t(meta.title);
    sectionDescEl.textContent = t(meta.desc);
    VIEWS[key]();
    showView('section');
  }

  function openDetail({ title, bodyHtml, footHtml }) {
    detailTitleEl.textContent = title;
    detailBody.innerHTML = bodyHtml;
    detailFoot.innerHTML = footHtml || '';
    detailView.classList.add('open');
  }

  function closeDetail() {
    detailView.classList.remove('open');
  }

  // ── Shared: payment fields block (used by GRN creation + Record Payment) ─
  function paymentFieldsHtml(prefix) {
    return `
      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-credit-card"></i> ${t('Payment')}</div>
        <div class="qf-grid">
          <label class="qf-field"><span>${t('Payment Method')}</span>
            <select id="${prefix}-method">
              <option value="cash">${t('Cash')}</option>
              <option value="credit">${t('Credit')}</option>
              <option value="cheque">${t('Cheque')}</option>
            </select>
          </label>
          <label class="qf-field" id="${prefix}-option-wrap"><span>${t('Amount')}</span>
            <select id="${prefix}-option">
              <option value="full">${t('Pay in full')}</option>
              <option value="partial">${t('Partial payment')}</option>
            </select>
          </label>
          <label class="qf-field" id="${prefix}-payamt-wrap" style="display:none"><span>${t('Pay Amount')}</span>
            <input type="number" id="${prefix}-payamt" min="0" step="0.01">
          </label>
          <label class="qf-field" id="${prefix}-account-wrap"><span>${t('Deduct From Account')}</span>
            <select id="${prefix}-account"><option value="">${t('— Select account —')}</option></select>
          </label>
          <label class="qf-field" id="${prefix}-terms-wrap" style="display:none"><span>${t('Credit Terms (days)')}</span>
            <input type="number" id="${prefix}-terms" min="1" step="1" value="30">
          </label>
          <label class="qf-field" id="${prefix}-chequedate-wrap" style="display:none"><span>${t('Cheque Due Date')}</span>
            <input type="date" id="${prefix}-chequedate">
          </label>
          <label class="qf-field"><span>${t('Payment Reference')}</span>
            <input type="text" id="${prefix}-ref" placeholder="${t('Optional reference / cheque no.')}">
          </label>
        </div>
      </div>`;
  }

  function wirePaymentFields(prefix) {
    const methodSel = document.getElementById(`${prefix}-method`);
    const optionSel = document.getElementById(`${prefix}-option`);
    // The business's Settings > Accounts "Default Pay From" decides whether a
    // cash GRN payment needs an account or is logged as a plain business
    // expense — there's no per-transaction override. Cheques always need a
    // real account regardless of that setting.
    function sync() {
      const method = methodSel.value;
      const option = optionSel.value;
      const needsAccount = method === 'cheque' || (posSettings.grn_payment_source || 'account') !== 'expense';
      document.getElementById(`${prefix}-terms-wrap`).style.display = method === 'credit' ? '' : 'none';
      document.getElementById(`${prefix}-chequedate-wrap`).style.display = method === 'cheque' ? '' : 'none';
      document.getElementById(`${prefix}-option-wrap`).style.display = method === 'credit' ? 'none' : '';
      document.getElementById(`${prefix}-account-wrap`).style.display = (method === 'credit' || !needsAccount) ? 'none' : '';
      document.getElementById(`${prefix}-payamt-wrap`).style.display = (method !== 'credit' && option === 'partial') ? '' : 'none';
    }
    methodSel.addEventListener('change', sync);
    optionSel.addEventListener('change', sync);
    sync();
    API.accounts().then((res) => {
      if (res.status !== 200) return;
      const sel = document.getElementById(`${prefix}-account`);
      if (sel) sel.insertAdjacentHTML('beforeend', (res.body.data || []).map((a) => `<option value="${a.id}">${esc(a.account_name)}${a.bank_name ? ' · ' + esc(a.bank_name) : ''}</option>`).join(''));
    });
  }

  function readPaymentFields(prefix) {
    const method = document.getElementById(`${prefix}-method`).value;
    const payload = {
      payment_method: method,
      payment_reference: document.getElementById(`${prefix}-ref`).value.trim() || null,
    };
    if (method === 'credit') {
      payload.payment_terms_days = parseInt(document.getElementById(`${prefix}-terms`).value, 10) || 30;
    } else {
      const option = document.getElementById(`${prefix}-option`).value;
      const needsAccount = document.getElementById(`${prefix}-account-wrap`).style.display !== 'none';
      const accountVal = document.getElementById(`${prefix}-account`).value;
      payload.payment_option = option;
      payload.deduct_account_id = needsAccount && accountVal ? Number(accountVal) : null;
      if (option === 'partial') payload.pay_amount = parseFloat(document.getElementById(`${prefix}-payamt`).value) || 0;
      if (method === 'cheque') payload.cheque_due_date = document.getElementById(`${prefix}-chequedate`).value || null;
    }
    return payload;
  }

  // ── Shared: product picker (mirrors js/sales.js's Quotation "Add Product"
  // dialog, generalised with a callback instead of a hard-coded target list)
  function openProductPicker(onPick) {
    if (document.getElementById('stk-picker-backdrop')) return;

    const el = document.createElement('div');
    el.className = 'qf-picker-backdrop';
    el.id = 'stk-picker-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML = `
      <div class="qf-picker-card">
        <div class="qf-picker-head">
          <span class="qf-picker-head-icon"><i class="fa-solid fa-box"></i></span>
          <span class="qf-picker-title">${t('Add Product')}</span>
          <button class="salm-modal-close" id="stk-picker-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="qf-picker-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="stk-picker-input" placeholder="${t('Search products…')}"></div>
        <div class="qf-picker-list" id="stk-picker-list"><div class="salm-loading">${t('Loading…')}</div></div>
        <div class="qf-picker-foot"><button class="salm-btn-ghost" id="stk-picker-cancel" type="button">${t('Cancel')}</button></div>
      </div>`;
    document.body.appendChild(el);

    function close() { el.remove(); }
    document.getElementById('stk-picker-close').addEventListener('click', close);
    document.getElementById('stk-picker-cancel').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });

    let lastResults = [];
    async function search(q) {
      const listEl = document.getElementById('stk-picker-list');
      listEl.innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
      const res = await API.products(q);
      if (res.status !== 200) { listEl.innerHTML = `<div class="salm-empty">${t('Could not load products.')}</div>`; return; }
      lastResults = res.body.data || [];
      if (!lastResults.length) { listEl.innerHTML = `<div class="salm-empty">${t('No products found.')}</div>`; return; }

      listEl.innerHTML = lastResults.map((p) => {
        const outOfStock = Number(p.stock_quantity) <= 0;
        const stockBadge = outOfStock
          ? `<span class="qf-picker-badge red">${t('Out of stock')}</span>`
          : `<span class="qf-picker-badge green">${t('{n} in stock', { n: Math.floor(p.stock_quantity) })}</span>`;
        const thumb = p.image_url ? `<img src="${p.image_url}" alt="">` : '<i class="fa-solid fa-box"></i>';
        return `
          <div class="qf-picker-row" data-id="${p.id}">
            <div class="qf-picker-thumb">${thumb}</div>
            <div class="qf-picker-info">
              <div class="qf-picker-name">${esc(p.name)}</div>
              <div class="qf-picker-sub">${p.sku ? esc(p.sku) + ' · ' : ''}${stockBadge}</div>
            </div>
            <div class="qf-picker-price">${money(p.unit_sell_price)}</div>
          </div>`;
      }).join('');

      listEl.querySelectorAll('.qf-picker-row').forEach((row) => {
        row.addEventListener('click', () => {
          const p = lastResults.find((x) => String(x.id) === row.dataset.id);
          if (!p) return;
          onPick(p);
          close();
        });
      });
    }

    const input = document.getElementById('stk-picker-input');
    let debounceTimer;
    input.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => search(input.value.trim()), 300); });
    search('');
    setTimeout(() => input.focus(), 30);
  }

  // ── Shared: "New X" chooser popup (used by Goods Receive: From PO / Direct)
  function openChoicePopup(title, choices) {
    if (document.getElementById('stk-choice-backdrop')) return;
    const el = document.createElement('div');
    el.className = 'qf-picker-backdrop';
    el.id = 'stk-choice-backdrop';
    el.innerHTML = `
      <div class="qf-picker-card" style="max-width:420px">
        <div class="qf-picker-head">
          <span class="qf-picker-head-icon"><i class="fa-solid fa-dolly"></i></span>
          <span class="qf-picker-title">${esc(title)}</span>
          <button class="salm-modal-close" id="stk-choice-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:6px 18px 18px;display:flex;flex-direction:column;gap:10px;">
          ${choices.map((c, i) => `
            <button type="button" class="salm-tile" data-i="${i}" style="width:100%;min-height:auto;flex-direction:row;text-align:left;gap:12px;padding:14px 16px;--tile-accent:${c.accent || '#2563eb'};--tile-accent-2:${lighten(c.accent || '#2563eb', 0.35)};--tile-glow:${glow(c.accent || '#2563eb', 0.25)}">
              <div class="salm-tile-icon" style="flex-shrink:0"><i class="fa-solid ${c.icon}"></i></div>
              <div style="text-align:left">
                <div class="salm-tile-title">${esc(c.label)}</div>
                <p class="salm-tile-desc" style="margin:2px 0 0">${esc(c.desc || '')}</p>
              </div>
            </button>`).join('')}
        </div>
      </div>`;
    document.body.appendChild(el);
    function close() { el.remove(); }
    document.getElementById('stk-choice-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    el.querySelectorAll('button[data-i]').forEach((btn) => {
      btn.addEventListener('click', () => { close(); choices[Number(btn.dataset.i)].onSelect(); });
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // Purchase Orders
  // ════════════════════════════════════════════════════════════════════════
  function renderPurchaseOrders() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="po-search" type="text" placeholder="${t('Search PO #, supplier…')}"></div>
        <select class="salm-select" id="po-status">
          <option value="all">${t('All statuses')}</option>
          <option value="draft">${t('Draft')}</option>
          <option value="ordered">${t('Ordered')}</option>
          <option value="partially_received">${t('Partially Received')}</option>
          <option value="received">${t('Received')}</option>
          <option value="cancelled">${t('Cancelled')}</option>
        </select>
        <button class="salm-icon-btn" id="po-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <button class="salm-btn-primary" id="po-new-btn" type="button"><i class="fa-solid fa-plus"></i> ${t('New Purchase Order')}</button>
        <span class="salm-count-pill" id="po-count"></span>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('PO #')}</th><th>${t('Supplier')}</th><th>${t('Purchase Date')}</th><th>${t('Expected Delivery')}</th><th>${t('Items')}</th><th>${t('Status')}</th><th style="text-align:right">${t('Total')}</th><th></th></tr></thead>
        <tbody id="po-rows"><tr><td colspan="8" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>`;

    document.getElementById('po-search').addEventListener('input', debounce(loadPurchaseOrders, 300));
    document.getElementById('po-status').addEventListener('change', loadPurchaseOrders);
    document.getElementById('po-refresh').addEventListener('click', loadPurchaseOrders);
    document.getElementById('po-new-btn').addEventListener('click', renderPurchaseOrderForm);
    loadPurchaseOrders();
  }

  async function loadPurchaseOrders() {
    document.getElementById('po-rows').innerHTML = `<tr><td colspan="8" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();
    const status = document.getElementById('po-status').value;
    const res = await API.purchaseOrders({ q: document.getElementById('po-search').value.trim(), status: status === 'all' ? '' : status });
    if (res.status !== 200) {
      document.getElementById('po-rows').innerHTML = `<tr><td colspan="8" class="salm-empty">${t('Could not load purchase orders ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      return;
    }
    const list = res.body.data || [];
    document.getElementById('po-count').textContent = list.length ? t(list.length === 1 ? '{n} purchase order' : '{n} purchase orders', { n: list.length }) : t('No purchase orders');

    const rowsEl = document.getElementById('po-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="8" class="salm-empty">${t('No purchase orders found.')}</td></tr>`;
      return;
    }
    rowsEl.innerHTML = list.map((p) => `
      <tr data-id="${p.id}">
        <td class="salm-ref"><i class="fa-solid fa-file-invoice"></i>${esc(p.po_number)}</td>
        <td class="salm-muted-cell">${esc(p.supplier_name) || '—'}</td>
        <td class="salm-muted-cell">${fmtDate(p.purchase_date)}</td>
        <td class="salm-muted-cell">${fmtDate(p.expected_delivery_date)}</td>
        <td class="salm-muted-cell">${p.items_count ?? 0}</td>
        <td><span class="salm-badge ${badgeClass(p.status)}">${esc(p.status_label || p.status)}</span></td>
        <td class="salm-amt-cell">${money(p.total)}</td>
        <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
      </tr>`).join('');
    rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openPurchaseOrderDetail(Number(tr.dataset.id))));
  }

  async function openPurchaseOrderDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.purchaseOrder(id);
    if (res.status !== 200) {
      openDetail({ title: t('Purchase Order'), bodyHtml: `<div class="salm-empty">${t('Could not load purchase order ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const p = res.body.data;

    const itemsHtml = (p.items || []).map((it) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(it.product_name)}</div><div class="salm-item-meta">${it.quantity} × ${money(it.unit_cost)}</div></div>
        <div class="salm-item-total">${money(it.line_total)}</div>
      </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(p.status)}">${esc(p.status_label || p.status)}</span></span></div>
      <div class="salm-view-row"><span>${t('Supplier')}</span><span>${esc(p.supplier_name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Purchase date')}</span><span>${fmtDate(p.purchase_date)}</span></div>
      <div class="salm-view-row"><span>${t('Expected delivery')}</span><span>${fmtDate(p.expected_delivery_date)}</span></div>
      ${p.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(p.notes)}</span></div>` : ''}
      <div class="salm-section-label">${t('Items')}</div>
      <div class="salm-item-list">${itemsHtml}</div>
      <div class="salm-totals">
        <div class="salm-view-row"><span>${t('Subtotal')}</span><span>${money(p.subtotal)}</span></div>
        <div class="salm-view-row grand"><span>${t('Total')}</span><span>${money(p.total)}</span></div>
      </div>`;

    const actions = [];
    if (p.status === 'draft') {
      actions.push(`<button class="salm-btn-ghost danger" id="po-cancel-btn" type="button"><i class="fa-solid fa-ban"></i> ${t('Cancel')}</button>`);
      actions.push(`<button class="salm-btn-primary" id="po-place-btn" type="button"><i class="fa-solid fa-paper-plane"></i> ${t('Place Order')}</button>`);
    } else if (p.status === 'ordered' || p.status === 'partially_received') {
      actions.push(`<button class="salm-btn-ghost danger" id="po-cancel-btn" type="button"><i class="fa-solid fa-ban"></i> ${t('Cancel')}</button>`);
    }

    openDetail({ title: p.po_number, bodyHtml, footHtml: actions.join('') });

    document.getElementById('po-place-btn')?.addEventListener('click', async () => {
      const res2 = await API.placePurchaseOrder(id);
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not place order.'), 'error'); return; }
      closeDetail();
      showToast(t('{label} has been placed.', { label: p.po_number }), 'success');
      loadPurchaseOrders();
    });
    document.getElementById('po-cancel-btn')?.addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Cancel {label}? This cannot be undone.', { label: p.po_number }), { okText: t('Cancel Order'), tone: 'danger' })) return;
      const res2 = await API.cancelPurchaseOrder(id);
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not cancel order.'), 'error'); return; }
      closeDetail();
      showToast(t('{label} has been cancelled.', { label: p.po_number }), 'success');
      loadPurchaseOrders();
    });
  }

  // ── New Purchase Order form ──────────────────────────────────────────────
  let poLines = [];

  function renderPoLines() {
    const rowsEl = document.getElementById('po-line-rows');
    if (!poLines.length) {
      rowsEl.innerHTML = `<div class="qf-line-empty">${t('No items yet — add a line to include products.')}</div>`;
      return;
    }
    rowsEl.innerHTML = poLines.map((line, idx) => `
      <div class="qf-line-row" data-idx="${idx}">
        <div class="qf-line-desc-wrap">
          <input type="text" class="qf-line-desc" value="${esc(line.name)}" disabled>
        </div>
        <input type="number" class="qf-line-qty" min="0.001" step="0.001" value="${line.quantity}">
        <input type="number" class="qf-line-price" min="0" step="0.01" value="${line.unit_cost}">
        <span class="qf-line-total">${money(line.quantity * line.unit_cost)}</span>
        <button type="button" class="qf-line-remove" title="${t('Remove')}"><i class="fa-solid fa-trash"></i></button>
      </div>`).join('');

    rowsEl.querySelectorAll('.qf-line-row').forEach((row) => {
      const idx = Number(row.dataset.idx);
      row.querySelector('.qf-line-qty').addEventListener('input', (e) => {
        poLines[idx].quantity = parseFloat(e.target.value) || 0;
        row.querySelector('.qf-line-total').textContent = money(poLines[idx].quantity * poLines[idx].unit_cost);
        recalcPoTotal();
      });
      row.querySelector('.qf-line-price').addEventListener('input', (e) => {
        poLines[idx].unit_cost = parseFloat(e.target.value) || 0;
        row.querySelector('.qf-line-total').textContent = money(poLines[idx].quantity * poLines[idx].unit_cost);
        recalcPoTotal();
      });
      row.querySelector('.qf-line-remove').addEventListener('click', () => { poLines.splice(idx, 1); renderPoLines(); recalcPoTotal(); });
    });
  }

  function recalcPoTotal() {
    const total = poLines.reduce((sum, l) => sum + (l.quantity * l.unit_cost), 0);
    const el = document.getElementById('po-total');
    if (el) el.textContent = money(total);
  }

  async function renderPurchaseOrderForm() {
    closeDetail();
    poLines = [];
    const today = new Date().toISOString().slice(0, 10);

    sectionBody.innerHTML = `
      <div class="inv-detail-header qf-form-header">
        <button class="inv-back-btn" id="po-form-back"><i class="fa-solid fa-arrow-left"></i> ${t('Back')}</button>
        <span class="inv-detail-breadcrumb">${t('New Purchase Order')}</span>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-circle-info"></i> ${t('Order Details')}</div>
        <div class="qf-grid">
          <label class="qf-field"><span>${t('Supplier')}</span>
            <select id="po-supplier"><option value="">${t('— No supplier —')}</option></select>
          </label>
          <label class="qf-field"><span>${t('Branch')}</span>
            <select id="po-branch"><option value="">${t('— Unassigned —')}</option></select>
          </label>
          <label class="qf-field"><span>${t('Reference')}</span>
            <input type="text" id="po-reference" placeholder="${t('e.g. REQ-123')}">
          </label>
          <label class="qf-field"><span>${t('Purchase Date')}</span>
            <input type="date" id="po-date" value="${today}">
          </label>
          <label class="qf-field"><span>${t('Expected Delivery')}</span>
            <input type="date" id="po-expected">
          </label>
          <label class="qf-field"><span>${t('Save As')}</span>
            <select id="po-save-status">
              <option value="draft">${t('Draft')}</option>
              <option value="ordered">${t('Ordered (sent to supplier)')}</option>
            </select>
          </label>
        </div>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-list"></i> ${t('Line Items')}</div>
        <div class="qf-line-table">
          <div class="qf-line-head"><span>${t('Product')}</span><span>${t('Qty')}</span><span>${t('Unit Cost')}</span><span>${t('Total')}</span><span></span></div>
          <div id="po-line-rows"></div>
        </div>
        <div class="qf-add-line-row">
          <button class="qf-add-line" id="po-add-line" type="button"><i class="fa-solid fa-plus"></i> ${t('Add Product')}</button>
        </div>
      </div>

      <div class="qf-bottom">
        <div class="qf-card qf-notes-card">
          <div class="qf-card-title"><i class="fa-solid fa-note-sticky"></i> ${t('Notes')}</div>
          <textarea id="po-notes" placeholder="${t('Notes for this purchase order…')}"></textarea>
        </div>
        <div class="qf-card qf-summary-card">
          <div class="qf-card-title"><i class="fa-solid fa-receipt"></i> ${t('Summary')}</div>
          <div class="qf-summary-row qf-total"><span>${t('Total')}</span><span id="po-total">0.00</span></div>
        </div>
      </div>

      <div class="qf-actions">
        <button class="salm-btn-ghost" id="po-cancel-form" type="button">${t('Cancel')}</button>
        <button class="salm-btn-primary" id="po-save" type="button"><i class="fa-solid fa-check"></i> ${t('Save Purchase Order')}</button>
      </div>`;

    document.getElementById('po-form-back').addEventListener('click', renderPurchaseOrders);
    document.getElementById('po-cancel-form').addEventListener('click', renderPurchaseOrders);
    document.getElementById('po-add-line').addEventListener('click', () => openProductPicker((p) => {
      poLines.push({ product_id: p.id, name: p.name, quantity: 1, unit_cost: p.cost_price ?? p.unit_sell_price ?? 0 });
      renderPoLines();
      recalcPoTotal();
    }));
    document.getElementById('po-save').addEventListener('click', submitPurchaseOrderForm);

    renderPoLines();
    await loadSettings();
    recalcPoTotal();

    const supRes = await API.suppliers();
    if (supRes.status === 200) {
      const sel = document.getElementById('po-supplier');
      if (sel) sel.insertAdjacentHTML('beforeend', (supRes.body.data || []).map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join(''));
    }
    await populateBranchSelect(document.getElementById('po-branch'));
  }

  async function submitPurchaseOrderForm() {
    const purchaseDate = document.getElementById('po-date').value;
    if (!purchaseDate) { await zeebrooAlert(t('Purchase date is required.'), { tone: 'warning', title: t('Missing information') }); return; }
    if (!poLines.length) { await zeebrooAlert(t('Add at least one line item.'), { tone: 'warning', title: t('Missing information') }); return; }

    const supplierId = document.getElementById('po-supplier').value;
    const payload = {
      supplier_id: supplierId ? Number(supplierId) : null,
      branch_id: Number(document.getElementById('po-branch').value) || null,
      reference: document.getElementById('po-reference').value.trim() || null,
      purchase_date: purchaseDate,
      expected_delivery_date: document.getElementById('po-expected').value || null,
      status: document.getElementById('po-save-status').value,
      notes: document.getElementById('po-notes').value.trim() || null,
      items: poLines.filter((l) => l.quantity > 0).map((l) => ({ product_id: l.product_id, quantity: l.quantity, unit_cost: l.unit_cost })),
    };

    const saveBtn = document.getElementById('po-save');
    saveBtn.disabled = true;
    const res = await API.createPurchaseOrder(payload);
    saveBtn.disabled = false;

    if (res.status !== 201) {
      const firstError = res.body?.errors ? Object.values(res.body.errors)[0]?.[0] : null;
      await zeebrooAlert(t(firstError || res.body?.message || 'Could not create purchase order.'), { tone: 'danger', title: t('Could not save order') });
      return;
    }
    await zeebrooAlert(t(res.body?.message || 'Purchase order created.'), { tone: 'success', title: t('Purchase order saved') });
    renderPurchaseOrders();
  }

  // ════════════════════════════════════════════════════════════════════════
  // Goods Receive (GRNs)
  // ════════════════════════════════════════════════════════════════════════
  function renderGoodsReceive() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="grn-search" type="text" placeholder="${t('Search GRN #, PO #, supplier…')}"></div>
        <select class="salm-select" id="grn-payment">
          <option value="all">${t('All payment statuses')}</option>
          <option value="pending">${t('Payment pending')}</option>
          <option value="paid_partial">${t('Partially paid')}</option>
          <option value="paid_full">${t('Paid in full')}</option>
          <option value="no_amount">${t('No amount')}</option>
        </select>
        <button class="salm-icon-btn" id="grn-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <button class="salm-btn-primary" id="grn-new-btn" type="button"><i class="fa-solid fa-plus"></i> ${t('New Goods Receive')}</button>
        <span class="salm-count-pill" id="grn-count"></span>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('GRN #')}</th><th>${t('PO #')}</th><th>${t('Supplier')}</th><th>${t('Received')}</th><th>${t('Approval')}</th><th>${t('Payment')}</th><th style="text-align:right">${t('Total')}</th><th></th></tr></thead>
        <tbody id="grn-rows"><tr><td colspan="8" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>`;

    document.getElementById('grn-search').addEventListener('input', debounce(loadGrns, 300));
    document.getElementById('grn-payment').addEventListener('change', loadGrns);
    document.getElementById('grn-refresh').addEventListener('click', loadGrns);
    document.getElementById('grn-new-btn').addEventListener('click', openNewGrnChoice);
    loadGrns();
  }

  async function loadGrns() {
    document.getElementById('grn-rows').innerHTML = `<tr><td colspan="8" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();
    const payment = document.getElementById('grn-payment').value;
    const res = await API.grns({ q: document.getElementById('grn-search').value.trim(), payment: payment === 'all' ? '' : payment });
    if (res.status !== 200) {
      document.getElementById('grn-rows').innerHTML = `<tr><td colspan="8" class="salm-empty">${t('Could not load goods receive notes ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      return;
    }
    const list = res.body.data || [];
    document.getElementById('grn-count').textContent = list.length ? t(list.length === 1 ? '{n} goods receive note' : '{n} goods receive notes', { n: list.length }) : t('No goods receive notes');

    const rowsEl = document.getElementById('grn-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="8" class="salm-empty">${t('No goods receive notes found.')}</td></tr>`;
      return;
    }
    rowsEl.innerHTML = list.map((g) => `
      <tr data-id="${g.id}">
        <td class="salm-ref"><i class="fa-solid fa-dolly"></i>${esc(g.grn_number)}</td>
        <td class="salm-muted-cell">${esc(g.po_number) || '—'}</td>
        <td class="salm-muted-cell">${esc(g.supplier_name) || '—'}</td>
        <td class="salm-muted-cell">${fmtDate(g.received_date)}</td>
        <td><span class="salm-badge ${badgeClass(g.approval_status)}">${esc(g.approval_status_label || g.approval_status)}</span></td>
        <td><span class="salm-badge ${badgeClass(g.payment_status)}">${esc(g.payment_status_label || g.payment_status)}</span></td>
        <td class="salm-amt-cell">${money(g.total)}</td>
        <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
      </tr>`).join('');
    rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openGrnDetail(Number(tr.dataset.id))));
  }

  async function openGrnDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.grn(id);
    if (res.status !== 200) {
      openDetail({ title: t('Goods Receive Note'), bodyHtml: `<div class="salm-empty">${t('Could not load GRN ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const g = res.body.data;

    const itemsHtml = (g.items || []).map((it) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(it.product_name)}</div><div class="salm-item-meta">${it.quantity_received} × ${money(it.unit_cost)}</div></div>
        <div class="salm-item-total">${money(it.line_total)}</div>
      </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    const paymentsHtml = (g.payments || []).map((p) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(p.account) || (p.is_expense ? t('Business Expense') : t('Payment'))}</div><div class="salm-item-meta">${fmtDate(p.date)}</div></div>
        <div class="salm-item-total">${money(p.amount)}</div>
      </div>`).join('');

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Approval')}</span><span><span class="salm-badge ${badgeClass(g.approval_status)}">${esc(g.approval_status_label || g.approval_status)}</span></span></div>
      <div class="salm-view-row"><span>${t('Payment status')}</span><span><span class="salm-badge ${badgeClass(g.payment_status)}">${esc(g.payment_status_label || g.payment_status)}</span></span></div>
      <div class="salm-view-row"><span>${t('Supplier')}</span><span>${esc(g.supplier_name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Received date')}</span><span>${fmtDate(g.received_date)}</span></div>
      ${g.po_number ? `<div class="salm-view-row"><span>${t('Purchase order')}</span><span>${esc(g.po_number)}</span></div>` : ''}
      ${g.reference ? `<div class="salm-view-row"><span>${t('Reference')}</span><span>${esc(g.reference)}</span></div>` : ''}
      ${g.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(g.notes)}</span></div>` : ''}
      <div class="salm-section-label">${t('Items')}</div>
      <div class="salm-item-list">${itemsHtml}</div>
      <div class="salm-totals">
        <div class="salm-view-row"><span>${t('Subtotal')}</span><span>${money(g.subtotal)}</span></div>
        <div class="salm-view-row grand"><span>${t('Total')}</span><span>${money(g.total)}</span></div>
        <div class="salm-view-row"><span>${t('Amount paid')}</span><span>${money(g.amount_paid)}</span></div>
        <div class="salm-view-row"><span>${t('Outstanding')}</span><span>${money(g.amount_outstanding)}</span></div>
      </div>
      ${paymentsHtml ? `<div class="salm-section-label">${t('Payments')}</div><div class="salm-item-list">${paymentsHtml}</div>` : ''}
      <div id="grn-pay-form"></div>`;

    const actions = [];
    if (g.approval_status === 'pending') {
      actions.push(`<button class="salm-btn-ghost danger" id="grn-reject-btn" type="button"><i class="fa-solid fa-xmark"></i> ${t('Reject')}</button>`);
      actions.push(`<button class="salm-btn-primary" id="grn-approve-btn" type="button"><i class="fa-solid fa-check"></i> ${t('Approve')}</button>`);
    }
    if ((g.amount_outstanding || 0) > 0.005) {
      actions.push(`<button class="salm-btn-primary" id="grn-pay-btn" type="button"><i class="fa-solid fa-money-bill-wave"></i> ${t('Record Payment')}</button>`);
    }

    openDetail({ title: g.grn_number, bodyHtml, footHtml: actions.join('') });

    document.getElementById('grn-approve-btn')?.addEventListener('click', async () => {
      const res2 = await API.approveGrn(id);
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not approve GRN.'), 'error'); return; }
      closeDetail();
      showToast(t('{label} approved — stock has been applied.', { label: g.grn_number }), 'success');
      loadGrns();
    });
    document.getElementById('grn-reject-btn')?.addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Reject {label}?', { label: g.grn_number }), { okText: t('Reject'), tone: 'danger' })) return;
      const res2 = await API.rejectGrn(id);
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not reject GRN.'), 'error'); return; }
      closeDetail();
      showToast(t('{label} rejected.', { label: g.grn_number }), 'success');
      loadGrns();
    });
    document.getElementById('grn-pay-btn')?.addEventListener('click', () => {
      const wrap = document.getElementById('grn-pay-form');
      wrap.innerHTML = `
        ${paymentFieldsHtml('paygrn')}
        <div class="qf-actions"><button class="salm-btn-primary" id="paygrn-submit" type="button"><i class="fa-solid fa-check"></i> ${t('Confirm Payment')}</button></div>`;
      document.getElementById('paygrn-method').innerHTML = `<option value="cash">${t('Cash')}</option><option value="cheque">${t('Cheque')}</option>`;
      wirePaymentFields('paygrn');
      document.getElementById('grn-pay-btn').disabled = true;
      document.getElementById('paygrn-submit').addEventListener('click', async () => {
        const payload = readPaymentFields('paygrn');
        const needsAccount = document.getElementById('paygrn-account-wrap').style.display !== 'none';
        if (needsAccount && !payload.deduct_account_id) { await zeebrooAlert(t('Select an account to deduct from.'), { tone: 'warning', title: t('Missing information') }); return; }
        const res2 = await API.payGrn(id, payload);
        if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not record payment.'), 'error'); return; }
        closeDetail();
        showToast(t('Payment recorded.'), 'success');
        loadGrns();
      });
    });
  }

  // ── New Goods Receive: choose flow ───────────────────────────────────────
  function openNewGrnChoice() {
    openChoicePopup(t('New Goods Receive'), [
      { icon: 'fa-file-invoice', accent: '#2563eb', label: t('From Purchase Order'), desc: t('Receive goods against an existing placed order.'), onSelect: openGrnPoPicker },
      { icon: 'fa-dolly', accent: '#059669', label: t('Direct (No PO)'), desc: t('Record stock received without a purchase order.'), onSelect: renderGrnDirectForm },
    ]);
  }

  async function openGrnPoPicker() {
    if (document.getElementById('stk-picker-backdrop')) return;
    const el = document.createElement('div');
    el.className = 'qf-picker-backdrop';
    el.id = 'stk-picker-backdrop';
    el.innerHTML = `
      <div class="qf-picker-card">
        <div class="qf-picker-head">
          <span class="qf-picker-head-icon"><i class="fa-solid fa-file-invoice"></i></span>
          <span class="qf-picker-title">${t('Select Purchase Order')}</span>
          <button class="salm-modal-close" id="stk-picker-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="qf-picker-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="stk-po-picker-input" placeholder="${t('Search by PO number or supplier…')}"></div>
        <div class="qf-picker-list" id="stk-po-picker-list"><div class="salm-loading">${t('Loading…')}</div></div>
        <div class="qf-picker-foot"><button class="salm-btn-ghost" id="stk-picker-cancel" type="button">${t('Cancel')}</button></div>
      </div>`;
    document.body.appendChild(el);
    function close() { el.remove(); }
    document.getElementById('stk-picker-close').addEventListener('click', close);
    document.getElementById('stk-picker-cancel').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });

    const listEl = document.getElementById('stk-po-picker-list');
    let lastResults = [];

    async function search(q) {
      listEl.innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
      const res = await API.purchaseOrders({ q });
      if (res.status !== 200) { listEl.innerHTML = `<div class="salm-empty">${t('Could not load purchase orders.')}</div>`; return; }
      const receivable = (res.body.data || []).filter((p) => p.status === 'ordered' || p.status === 'partially_received');
      // Newest-created first, so a freshly placed order always surfaces at the top.
      receivable.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0) || b.id - a.id);
      lastResults = receivable;
      if (!receivable.length) {
        listEl.innerHTML = `<div class="salm-empty">${t('No purchase orders are ready to receive. Place an order first.')}</div>`;
        return;
      }
      const newestId = receivable[0].id;
      const isRecent = (p) => {
        if (p.id !== newestId || !p.created_at) return false;
        return (Date.now() - new Date(p.created_at).getTime()) < 24 * 60 * 60 * 1000;
      };
      listEl.innerHTML = receivable.map((p) => {
        const highlight = isRecent(p);
        return `
      <div class="qf-picker-row${highlight ? ' qf-picker-row-new' : ''}" data-id="${p.id}">
        <div class="qf-picker-thumb"><i class="fa-solid fa-file-invoice"></i></div>
        <div class="qf-picker-info">
          <div class="qf-picker-name">${esc(p.po_number)} ${highlight ? `<span class="qf-picker-badge new">${t('New')}</span>` : ''}</div>
          <div class="qf-picker-sub">${esc(p.supplier_name) || t('No supplier')} · ${esc(p.status_label || p.status)}</div>
        </div>
        <div class="qf-picker-price">${money(p.total)}</div>
      </div>`;
      }).join('');
      listEl.querySelectorAll('.qf-picker-row').forEach((row) => {
        row.addEventListener('click', () => {
          const p = lastResults.find((x) => String(x.id) === row.dataset.id);
          if (!p) return;
          close();
          renderGrnFromPoForm(p.id);
        });
      });
    }

    const input = document.getElementById('stk-po-picker-input');
    let debounceTimer;
    input.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => search(input.value.trim()), 300); });
    search('');
    setTimeout(() => input.focus(), 30);
  }

  async function renderGrnFromPoForm(purchaseId) {
    closeDetail();
    sectionBody.innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    const res = await API.grnFormForPurchase(purchaseId);
    if (res.status !== 200) {
      sectionBody.innerHTML = `<div class="salm-empty">${t('Could not load purchase order ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
      return;
    }
    const { purchase, items } = res.body.data;
    const today = new Date().toISOString().slice(0, 10);

    sectionBody.innerHTML = `
      <div class="inv-detail-header qf-form-header">
        <button class="inv-back-btn" id="grnpo-back"><i class="fa-solid fa-arrow-left"></i> ${t('Back')}</button>
        <span class="inv-detail-breadcrumb">${t('Receive Goods — {po}', { po: purchase.po_number })}</span>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-circle-info"></i> ${t('Receipt Details')}</div>
        <div class="qf-grid">
          <label class="qf-field"><span>${t('Supplier')}</span><input type="text" value="${esc(purchase.supplier_name) || '—'}" disabled></label>
          <label class="qf-field"><span>${t('Branch')}</span><select id="grnpo-branch"><option value="">${t('— Unassigned —')}</option></select></label>
          <label class="qf-field"><span>${t('Received Date')}</span><input type="date" id="grnpo-date" value="${today}"></label>
          <label class="qf-field"><span>${t('Reference')}</span><input type="text" id="grnpo-reference"></label>
        </div>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-list"></i> ${t('Items to Receive')}</div>
        <div class="qf-line-table">
          <div class="qf-line-head"><span>${t('Product')}</span><span>${t('Qty to Receive')}</span><span>${t('Unit Cost')}</span><span>${t('Total')}</span><span></span></div>
          <div id="grnpo-line-rows"></div>
        </div>
      </div>

      <div class="qf-bottom">
        <div class="qf-card qf-notes-card">
          <div class="qf-card-title"><i class="fa-solid fa-note-sticky"></i> ${t('Notes')}</div>
          <textarea id="grnpo-notes"></textarea>
        </div>
        <div class="qf-card qf-summary-card">
          <div class="qf-card-title"><i class="fa-solid fa-receipt"></i> ${t('Summary')}</div>
          <div class="qf-summary-row qf-total"><span>${t('Total')}</span><span id="grnpo-total">0.00</span></div>
        </div>
      </div>

      ${paymentFieldsHtml('grnpo-pay')}

      <div class="qf-actions">
        <button class="salm-btn-ghost" id="grnpo-cancel" type="button">${t('Cancel')}</button>
        <button class="salm-btn-primary" id="grnpo-save" type="button"><i class="fa-solid fa-check"></i> ${t('Save Goods Receive')}</button>
      </div>`;

    function recalc() {
      let total = 0;
      document.querySelectorAll('#grnpo-line-rows .qf-line-row').forEach((row) => {
        const qty = parseFloat(row.querySelector('.qf-line-qty').value) || 0;
        const cost = parseFloat(row.querySelector('.qf-line-price').value) || 0;
        row.querySelector('.qf-line-total').textContent = money(qty * cost);
        total += qty * cost;
      });
      document.getElementById('grnpo-total').textContent = money(total);
    }

    document.getElementById('grnpo-line-rows').innerHTML = items.map((it) => `
      <div class="qf-line-row" data-purchase-item-id="${it.id}">
        <div class="qf-line-desc-wrap"><input type="text" class="qf-line-desc" value="${esc(it.product_name)} (${t('{n} remaining', { n: it.quantity_remaining })})" disabled></div>
        <input type="number" class="qf-line-qty" min="0" max="${it.quantity_remaining}" step="0.001" value="${it.quantity_remaining}">
        <input type="number" class="qf-line-price" min="0" step="0.01" value="${it.unit_cost}">
        <span class="qf-line-total">${money(it.quantity_remaining * it.unit_cost)}</span>
        <span></span>
      </div>`).join('');

    document.querySelectorAll('#grnpo-line-rows .qf-line-qty, #grnpo-line-rows .qf-line-price').forEach((inp) => inp.addEventListener('input', recalc));
    recalc();

    document.getElementById('grnpo-back').addEventListener('click', renderGoodsReceive);
    document.getElementById('grnpo-cancel').addEventListener('click', renderGoodsReceive);
    document.getElementById('grnpo-pay-method').innerHTML = `<option value="cash">${t('Cash')}</option><option value="credit">${t('Credit')}</option><option value="cheque">${t('Cheque')}</option>`;
    wirePaymentFields('grnpo-pay');
    await populateBranchSelect(document.getElementById('grnpo-branch'), purchase.branch_id);

    document.getElementById('grnpo-save').addEventListener('click', async () => {
      const lines = [...document.querySelectorAll('#grnpo-line-rows .qf-line-row')].map((row) => ({
        purchase_item_id: Number(row.dataset.purchaseItemId),
        quantity_received: parseFloat(row.querySelector('.qf-line-qty').value) || 0,
      })).filter((l) => l.quantity_received > 0);

      if (!lines.length) { await zeebrooAlert(t('Enter a quantity for at least one item.'), { tone: 'warning', title: t('Missing information') }); return; }

      const payload = {
        received_date: document.getElementById('grnpo-date').value,
        branch_id: Number(document.getElementById('grnpo-branch').value) || null,
        reference: document.getElementById('grnpo-reference').value.trim() || null,
        notes: document.getElementById('grnpo-notes').value.trim() || null,
        items: lines,
        ...readPaymentFields('grnpo-pay'),
      };

      const saveBtn = document.getElementById('grnpo-save');
      saveBtn.disabled = true;
      const res2 = await API.createGrnForPurchase(purchaseId, payload);
      saveBtn.disabled = false;

      if (res2.status !== 201) {
        const firstError = res2.body?.errors ? Object.values(res2.body.errors)[0]?.[0] : null;
        await zeebrooAlert(t(firstError || res2.body?.message || 'Could not save goods receive.'), { tone: 'danger', title: t('Could not save') });
        return;
      }
      await zeebrooAlert(t(res2.body?.message || 'Goods receive note recorded.'), { tone: 'success', title: t('Saved') });
      renderGoodsReceive();
    });
  }

  // ── Direct Goods Receive (no PO) form ────────────────────────────────────
  let grnDirectLines = [];

  function renderGrnDirectLines() {
    const rowsEl = document.getElementById('grndirect-line-rows');
    if (!grnDirectLines.length) {
      rowsEl.innerHTML = `<div class="qf-line-empty">${t('No items yet — add a line to include products.')}</div>`;
      return;
    }
    rowsEl.innerHTML = grnDirectLines.map((line, idx) => `
      <div class="qf-line-row" data-idx="${idx}">
        <div class="qf-line-desc-wrap"><input type="text" class="qf-line-desc" value="${esc(line.name)}" disabled></div>
        <input type="number" class="qf-line-qty" min="0.001" step="0.001" value="${line.quantity}">
        <input type="number" class="qf-line-price" min="0" step="0.01" value="${line.unit_cost}">
        <span class="qf-line-total">${money(line.quantity * line.unit_cost)}</span>
        <button type="button" class="qf-line-remove" title="${t('Remove')}"><i class="fa-solid fa-trash"></i></button>
      </div>`).join('');

    rowsEl.querySelectorAll('.qf-line-row').forEach((row) => {
      const idx = Number(row.dataset.idx);
      row.querySelector('.qf-line-qty').addEventListener('input', (e) => {
        grnDirectLines[idx].quantity = parseFloat(e.target.value) || 0;
        row.querySelector('.qf-line-total').textContent = money(grnDirectLines[idx].quantity * grnDirectLines[idx].unit_cost);
        recalcGrnDirectTotal();
      });
      row.querySelector('.qf-line-price').addEventListener('input', (e) => {
        grnDirectLines[idx].unit_cost = parseFloat(e.target.value) || 0;
        row.querySelector('.qf-line-total').textContent = money(grnDirectLines[idx].quantity * grnDirectLines[idx].unit_cost);
        recalcGrnDirectTotal();
      });
      row.querySelector('.qf-line-remove').addEventListener('click', () => { grnDirectLines.splice(idx, 1); renderGrnDirectLines(); recalcGrnDirectTotal(); });
    });
  }

  function recalcGrnDirectTotal() {
    const total = grnDirectLines.reduce((sum, l) => sum + (l.quantity * l.unit_cost), 0);
    const el = document.getElementById('grndirect-total');
    if (el) el.textContent = money(total);
  }

  async function renderGrnDirectForm() {
    closeDetail();
    grnDirectLines = [];
    const today = new Date().toISOString().slice(0, 10);

    sectionBody.innerHTML = `
      <div class="inv-detail-header qf-form-header">
        <button class="inv-back-btn" id="grndirect-back"><i class="fa-solid fa-arrow-left"></i> ${t('Back')}</button>
        <span class="inv-detail-breadcrumb">${t('New Goods Receive (Direct)')}</span>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-circle-info"></i> ${t('Receipt Details')}</div>
        <div class="qf-grid">
          <label class="qf-field"><span>${t('Supplier')}</span>
            <select id="grndirect-supplier"><option value="">${t('— No supplier —')}</option></select>
          </label>
          <label class="qf-field"><span>${t('Branch')}</span><select id="grndirect-branch"><option value="">${t('— Unassigned —')}</option></select></label>
          <label class="qf-field"><span>${t('Received Date')}</span><input type="date" id="grndirect-date" value="${today}"></label>
          <label class="qf-field"><span>${t('Reference')}</span><input type="text" id="grndirect-reference"></label>
        </div>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-list"></i> ${t('Line Items')}</div>
        <div class="qf-line-table">
          <div class="qf-line-head"><span>${t('Product')}</span><span>${t('Qty')}</span><span>${t('Unit Cost')}</span><span>${t('Total')}</span><span></span></div>
          <div id="grndirect-line-rows"></div>
        </div>
        <div class="qf-add-line-row">
          <button class="qf-add-line" id="grndirect-add-line" type="button"><i class="fa-solid fa-plus"></i> ${t('Add Product')}</button>
        </div>
      </div>

      <div class="qf-bottom">
        <div class="qf-card qf-notes-card">
          <div class="qf-card-title"><i class="fa-solid fa-note-sticky"></i> ${t('Notes')}</div>
          <textarea id="grndirect-notes"></textarea>
        </div>
        <div class="qf-card qf-summary-card">
          <div class="qf-card-title"><i class="fa-solid fa-receipt"></i> ${t('Summary')}</div>
          <div class="qf-summary-row qf-total"><span>${t('Total')}</span><span id="grndirect-total">0.00</span></div>
        </div>
      </div>

      ${paymentFieldsHtml('grndirect-pay')}

      <div class="qf-actions">
        <button class="salm-btn-ghost" id="grndirect-cancel" type="button">${t('Cancel')}</button>
        <button class="salm-btn-primary" id="grndirect-save" type="button"><i class="fa-solid fa-check"></i> ${t('Save Goods Receive')}</button>
      </div>`;

    document.getElementById('grndirect-back').addEventListener('click', renderGoodsReceive);
    document.getElementById('grndirect-cancel').addEventListener('click', renderGoodsReceive);
    document.getElementById('grndirect-add-line').addEventListener('click', () => openProductPicker((p) => {
      grnDirectLines.push({ product_id: p.id, name: p.name, quantity: 1, unit_cost: p.cost_price ?? p.unit_sell_price ?? 0 });
      renderGrnDirectLines();
      recalcGrnDirectTotal();
    }));
    document.getElementById('grndirect-pay-method').innerHTML = `<option value="cash">${t('Cash')}</option><option value="credit">${t('Credit')}</option><option value="cheque">${t('Cheque')}</option>`;
    wirePaymentFields('grndirect-pay');
    document.getElementById('grndirect-save').addEventListener('click', submitGrnDirectForm);

    renderGrnDirectLines();
    await loadSettings();
    recalcGrnDirectTotal();

    const supRes = await API.suppliers();
    if (supRes.status === 200) {
      const sel = document.getElementById('grndirect-supplier');
      if (sel) sel.insertAdjacentHTML('beforeend', (supRes.body.data || []).map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join(''));
    }
    await populateBranchSelect(document.getElementById('grndirect-branch'));
  }

  async function submitGrnDirectForm() {
    if (!grnDirectLines.length) { await zeebrooAlert(t('Add at least one line item.'), { tone: 'warning', title: t('Missing information') }); return; }

    const supplierId = document.getElementById('grndirect-supplier').value;
    const payload = {
      supplier_id: supplierId ? Number(supplierId) : null,
      branch_id: Number(document.getElementById('grndirect-branch').value) || null,
      received_date: document.getElementById('grndirect-date').value,
      reference: document.getElementById('grndirect-reference').value.trim() || null,
      notes: document.getElementById('grndirect-notes').value.trim() || null,
      items: grnDirectLines.filter((l) => l.quantity > 0).map((l) => ({ product_id: l.product_id, quantity_received: l.quantity, unit_cost: l.unit_cost })),
      ...readPaymentFields('grndirect-pay'),
    };

    const saveBtn = document.getElementById('grndirect-save');
    saveBtn.disabled = true;
    const res = await API.createGrnDirect(payload);
    saveBtn.disabled = false;

    if (res.status !== 201) {
      const firstError = res.body?.errors ? Object.values(res.body.errors)[0]?.[0] : null;
      await zeebrooAlert(t(firstError || res.body?.message || 'Could not save goods receive.'), { tone: 'danger', title: t('Could not save') });
      return;
    }
    await zeebrooAlert(t(res.body?.message || 'Goods receive note recorded.'), { tone: 'success', title: t('Saved') });
    renderGoodsReceive();
  }

  // ════════════════════════════════════════════════════════════════════════
  // Cheques
  // ════════════════════════════════════════════════════════════════════════
  function renderCheques() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <select class="salm-select" id="chq-filter">
          <option value="all">${t('All cheques')}</option>
          <option value="pending">${t('Pending')}</option>
          <option value="due">${t('Due')}</option>
          <option value="overdue">${t('Overdue')}</option>
          <option value="cleared">${t('Cleared')}</option>
        </select>
        <button class="salm-icon-btn" id="chq-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <span class="salm-count-pill" id="chq-count"></span>
      </div>
      <div class="salm-kpi-bar" id="chq-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Pending')}</span><span class="salm-kpi-val" id="chq-kpi-pending">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Overdue')}</span><span class="salm-kpi-val" id="chq-kpi-overdue">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Cleared')}</span><span class="salm-kpi-val" id="chq-kpi-cleared">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Outstanding Amount')}</span><span class="salm-kpi-val accent" id="chq-kpi-amount">0.00</span></div>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Cheque #')}</th><th>${t('Due Date')}</th><th>${t('Supplier')}</th><th>${t('GRN #')}</th><th style="text-align:right">${t('Amount')}</th><th>${t('Status')}</th><th></th></tr></thead>
        <tbody id="chq-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>`;

    document.getElementById('chq-filter').addEventListener('change', loadCheques);
    document.getElementById('chq-refresh').addEventListener('click', loadCheques);
    loadCheques();
  }

  let chqCache = [];

  async function loadCheques() {
    document.getElementById('chq-rows').innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    document.getElementById('chq-kpi').style.display = 'none';
    await loadSettings();
    const filter = document.getElementById('chq-filter').value;
    const res = await API.cheques(filter);
    if (res.status !== 200) {
      document.getElementById('chq-rows').innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load cheques ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      return;
    }
    chqCache = res.body.data || [];
    const summary = res.body.summary || {};
    const kpi = document.getElementById('chq-kpi');
    kpi.style.display = '';
    document.getElementById('chq-kpi-pending').textContent = summary.pending ?? 0;
    document.getElementById('chq-kpi-overdue').textContent = summary.overdue ?? 0;
    document.getElementById('chq-kpi-cleared').textContent = summary.cleared ?? 0;
    document.getElementById('chq-kpi-amount').textContent = money(summary.pending_amount);

    document.getElementById('chq-count').textContent = chqCache.length ? t(chqCache.length === 1 ? '{n} cheque' : '{n} cheques', { n: chqCache.length }) : t('No cheques');

    const rowsEl = document.getElementById('chq-rows');
    if (!chqCache.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No cheques found.')}</td></tr>`;
      return;
    }
    rowsEl.innerHTML = chqCache.map((c) => `
      <tr data-id="${c.id}">
        <td class="salm-ref"><i class="fa-solid fa-money-check-dollar"></i>${esc(c.cheque_number)}</td>
        <td class="salm-muted-cell">${fmtDate(c.due_date)}</td>
        <td class="salm-muted-cell">${esc(c.supplier_name) || '—'}</td>
        <td class="salm-muted-cell">${esc(c.grn_number) || '—'}</td>
        <td class="salm-amt-cell">${money(c.amount)}</td>
        <td><span class="salm-badge ${badgeClass(c.status)}">${esc(c.status_label || c.status)}</span></td>
        <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
      </tr>`).join('');
    rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openChequeDetail(Number(tr.dataset.id))));
  }

  function openChequeDetail(id) {
    const c = chqCache.find((x) => x.id === id);
    if (!c) return;

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(c.status)}">${esc(c.status_label || c.status)}</span></span></div>
      <div class="salm-view-row"><span>${t('Due date')}</span><span>${fmtDate(c.due_date)}</span></div>
      <div class="salm-view-row"><span>${t('Amount')}</span><span>${money(c.amount)}</span></div>
      <div class="salm-view-row"><span>${t('Supplier')}</span><span>${esc(c.supplier_name) || '—'}</span></div>
      ${c.po_number ? `<div class="salm-view-row"><span>${t('Purchase order')}</span><span>${esc(c.po_number)}</span></div>` : ''}
      ${c.grn_number ? `<div class="salm-view-row"><span>${t('Goods receive note')}</span><span>${esc(c.grn_number)}</span></div>` : ''}
      ${c.account ? `<div class="salm-view-row"><span>${t('Account')}</span><span>${esc(c.account)}</span></div>` : ''}
      ${c.cleared_at ? `<div class="salm-view-row"><span>${t('Cleared on')}</span><span>${fmtDate(c.cleared_at)}</span></div>` : ''}
      <div id="chq-clear-form"></div>`;

    const footHtml = c.status !== 'cleared'
      ? `<button class="salm-btn-primary" id="chq-clear-btn" type="button"><i class="fa-solid fa-check"></i> ${t('Clear Cheque')}</button>`
      : '';

    openDetail({ title: c.cheque_number, bodyHtml, footHtml });

    document.getElementById('chq-clear-btn')?.addEventListener('click', () => {
      const wrap = document.getElementById('chq-clear-form');
      wrap.innerHTML = `
        <div class="qf-card">
          <div class="qf-card-title"><i class="fa-solid fa-credit-card"></i> ${t('Deduct From Account')}</div>
          <label class="qf-field"><select id="chq-clear-account"><option value="">${t('— Select account —')}</option></select></label>
        </div>
        <div class="qf-actions"><button class="salm-btn-primary" id="chq-clear-confirm" type="button"><i class="fa-solid fa-check"></i> ${t('Confirm')}</button></div>`;
      API.accounts().then((res) => {
        if (res.status !== 200) return;
        const sel = document.getElementById('chq-clear-account');
        if (sel) sel.insertAdjacentHTML('beforeend', (res.body.data || []).map((a) => `<option value="${a.id}">${esc(a.account_name)}${a.bank_name ? ' · ' + esc(a.bank_name) : ''}</option>`).join(''));
      });
      document.getElementById('chq-clear-btn').disabled = true;
      document.getElementById('chq-clear-confirm').addEventListener('click', async () => {
        const accountVal = document.getElementById('chq-clear-account').value;
        const res2 = await API.clearCheque(id, { deduct_account_id: accountVal ? Number(accountVal) : null });
        if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not clear cheque.'), 'error'); return; }
        closeDetail();
        showToast(t('{label} cleared.', { label: c.cheque_number }), 'success');
        loadCheques();
      });
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // Stock Transfers
  // ════════════════════════════════════════════════════════════════════════
  let transferPage = 1;

  function renderStockTransfers() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="tr-search" type="text" placeholder="${t('Search transfer #…')}"></div>
        <button class="salm-icon-btn" id="tr-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <button class="salm-btn-primary" id="tr-new-btn" type="button"><i class="fa-solid fa-plus"></i> ${t('New Stock Transfer')}</button>
        <span class="salm-count-pill" id="tr-count"></span>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Transfer #')}</th><th>${t('From')}</th><th>${t('To')}</th><th>${t('Lines')}</th><th>${t('Status')}</th><th>${t('Transferred')}</th><th></th></tr></thead>
        <tbody id="tr-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>
      <div class="salm-pagination" id="tr-pagination"></div>`;

    document.getElementById('tr-search').addEventListener('input', debounce(() => loadStockTransfers(1), 350));
    document.getElementById('tr-refresh').addEventListener('click', () => loadStockTransfers(transferPage));
    document.getElementById('tr-new-btn').addEventListener('click', renderStockTransferForm);
    loadStockTransfers(1);
  }

  async function loadStockTransfers(page) {
    transferPage = page;
    document.getElementById('tr-rows').innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    const res = await API.stockTransfers({ q: document.getElementById('tr-search').value.trim(), page });
    if (res.status !== 200) {
      document.getElementById('tr-rows').innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load stock transfers ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      document.getElementById('tr-pagination').innerHTML = '';
      return;
    }
    const list = res.body.data || [];
    document.getElementById('tr-count').textContent = list.length ? t(list.length === 1 ? '{n} transfer' : '{n} transfers', { n: list.length }) : t('No transfers');

    const rowsEl = document.getElementById('tr-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No stock transfers found.')}</td></tr>`;
    } else {
      rowsEl.innerHTML = list.map((tr) => `
        <tr data-id="${tr.id}">
          <td class="salm-ref"><i class="fa-solid fa-right-left"></i>${esc(tr.transfer_number)}</td>
          <td class="salm-muted-cell">${esc(tr.from_branch?.name) || '—'}</td>
          <td class="salm-muted-cell">${esc(tr.to_branch?.name) || '—'}</td>
          <td class="salm-muted-cell">${tr.lines_count ?? 0}</td>
          <td><span class="salm-badge ${badgeClass(tr.status)}">${esc(statusLabelForTransfer(tr.status))}</span></td>
          <td class="salm-muted-cell">${fmtDateTime(tr.transferred_at)}</td>
          <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
        </tr>`).join('');
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr2) => tr2.addEventListener('click', () => openStockTransferDetail(Number(tr2.dataset.id))));
    }
    renderPagination(document.getElementById('tr-pagination'), res.body.meta, loadStockTransfers);
  }

  function statusLabelForTransfer(status) {
    if (status === 'completed') return t('Completed');
    if (status === 'cancelled') return t('Cancelled');
    return t('In Transit');
  }

  async function openStockTransferDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.stockTransfer(id);
    if (res.status !== 200) {
      openDetail({ title: t('Stock Transfer'), bodyHtml: `<div class="salm-empty">${t('Could not load transfer ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const tr = res.body.data;

    const linesHtml = (tr.lines || []).map((l) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(l.product_name)}</div><div class="salm-item-meta">${esc(l.sku) || ''}</div></div>
        <div class="salm-item-total">${l.quantity}</div>
      </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(tr.status)}">${esc(statusLabelForTransfer(tr.status))}</span></span></div>
      <div class="salm-view-row"><span>${t('From branch')}</span><span>${esc(tr.from_branch?.name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('To branch')}</span><span>${esc(tr.to_branch?.name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Transferred by')}</span><span>${esc(personName(tr.transferred_by)) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Transferred at')}</span><span>${fmtDateTime(tr.transferred_at)}</span></div>
      ${tr.received_at ? `<div class="salm-view-row"><span>${t('Received by')}</span><span>${esc(personName(tr.received_by)) || '—'}</span></div>` : ''}
      ${tr.received_at ? `<div class="salm-view-row"><span>${t('Received at')}</span><span>${fmtDateTime(tr.received_at)}</span></div>` : ''}
      ${tr.cancelled_at ? `<div class="salm-view-row"><span>${t('Cancelled at')}</span><span>${fmtDateTime(tr.cancelled_at)}</span></div>` : ''}
      ${tr.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(tr.notes)}</span></div>` : ''}
      <div class="salm-section-label">${t('Items')}</div>
      <div class="salm-item-list">${linesHtml}</div>`;

    const actions = [];
    if (tr.status === 'in_transit') {
      actions.push(`<button class="salm-btn-ghost danger" id="tr-cancel-btn" type="button"><i class="fa-solid fa-ban"></i> ${t('Cancel')}</button>`);
      actions.push(`<button class="salm-btn-primary" id="tr-receive-btn" type="button"><i class="fa-solid fa-check"></i> ${t('Mark Received')}</button>`);
    }

    openDetail({ title: tr.transfer_number, bodyHtml, footHtml: actions.join('') });

    document.getElementById('tr-receive-btn')?.addEventListener('click', async () => {
      const res2 = await API.receiveStockTransfer(id);
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not mark as received.'), 'error'); return; }
      closeDetail();
      showToast(t('{label} marked as received.', { label: tr.transfer_number }), 'success');
      loadStockTransfers(transferPage);
    });
    document.getElementById('tr-cancel-btn')?.addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Cancel {label}?', { label: tr.transfer_number }), { okText: t('Cancel Transfer'), tone: 'danger' })) return;
      const res2 = await API.cancelStockTransfer(id);
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not cancel transfer.'), 'error'); return; }
      closeDetail();
      showToast(t('{label} cancelled.', { label: tr.transfer_number }), 'success');
      loadStockTransfers(transferPage);
    });
  }

  // ── New Stock Transfer form ──────────────────────────────────────────────
  let trLines = [];

  function renderTrLines() {
    const rowsEl = document.getElementById('tr-line-rows');
    if (!trLines.length) {
      rowsEl.innerHTML = `<div class="qf-line-empty">${t('No items yet — add a line to include products.')}</div>`;
      return;
    }
    rowsEl.innerHTML = trLines.map((line, idx) => `
      <div class="qf-line-row" data-idx="${idx}" style="grid-template-columns:1fr 120px 40px">
        <div class="qf-line-desc-wrap"><input type="text" class="qf-line-desc" value="${esc(line.name)}" disabled></div>
        <input type="number" class="qf-line-qty" min="0.001" step="0.001" value="${line.quantity}">
        <button type="button" class="qf-line-remove" title="${t('Remove')}"><i class="fa-solid fa-trash"></i></button>
      </div>`).join('');

    rowsEl.querySelectorAll('.qf-line-row').forEach((row) => {
      const idx = Number(row.dataset.idx);
      row.querySelector('.qf-line-qty').addEventListener('input', (e) => { trLines[idx].quantity = parseFloat(e.target.value) || 0; });
      row.querySelector('.qf-line-remove').addEventListener('click', () => { trLines.splice(idx, 1); renderTrLines(); });
    });
  }

  async function renderStockTransferForm() {
    closeDetail();
    trLines = [];

    sectionBody.innerHTML = `
      <div class="inv-detail-header qf-form-header">
        <button class="inv-back-btn" id="tr-form-back"><i class="fa-solid fa-arrow-left"></i> ${t('Back')}</button>
        <span class="inv-detail-breadcrumb">${t('New Stock Transfer')}</span>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-circle-info"></i> ${t('Transfer Details')}</div>
        <div class="qf-grid">
          <label class="qf-field"><span>${t('From Branch')}</span><select id="tr-from"><option value="">${t('— Select —')}</option></select></label>
          <label class="qf-field"><span>${t('To Branch')}</span><select id="tr-to"><option value="">${t('— Select —')}</option></select></label>
        </div>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-list"></i> ${t('Items')}</div>
        <div class="qf-line-table">
          <div class="qf-line-head" style="grid-template-columns:1fr 120px 40px"><span>${t('Product')}</span><span>${t('Qty')}</span><span></span></div>
          <div id="tr-line-rows"></div>
        </div>
        <div class="qf-add-line-row">
          <button class="qf-add-line" id="tr-add-line" type="button"><i class="fa-solid fa-plus"></i> ${t('Add Product')}</button>
        </div>
      </div>

      <div class="qf-card qf-notes-card">
        <div class="qf-card-title"><i class="fa-solid fa-note-sticky"></i> ${t('Notes')}</div>
        <textarea id="tr-notes"></textarea>
      </div>

      <div class="qf-actions">
        <button class="salm-btn-ghost" id="tr-form-cancel" type="button">${t('Cancel')}</button>
        <button class="salm-btn-primary" id="tr-save" type="button"><i class="fa-solid fa-check"></i> ${t('Save Transfer')}</button>
      </div>`;

    document.getElementById('tr-form-back').addEventListener('click', renderStockTransfers);
    document.getElementById('tr-form-cancel').addEventListener('click', renderStockTransfers);
    document.getElementById('tr-add-line').addEventListener('click', () => openProductPicker((p) => {
      trLines.push({ product_id: p.id, name: p.name, quantity: 1 });
      renderTrLines();
    }));
    document.getElementById('tr-save').addEventListener('click', submitStockTransferForm);

    renderTrLines();

    const branchRes = await API.branches();
    if (branchRes.status === 200) {
      const opts = (branchRes.body.data || []).map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join('');
      document.getElementById('tr-from').insertAdjacentHTML('beforeend', opts);
      document.getElementById('tr-to').insertAdjacentHTML('beforeend', opts);
    }
  }

  async function submitStockTransferForm() {
    const fromId = document.getElementById('tr-from').value;
    const toId = document.getElementById('tr-to').value;
    if (!fromId || !toId) { await zeebrooAlert(t('Select both a source and destination branch.'), { tone: 'warning', title: t('Missing information') }); return; }
    if (fromId === toId) { await zeebrooAlert(t('The destination branch must be different from the source branch.'), { tone: 'warning', title: t('Invalid selection') }); return; }
    if (!trLines.length) { await zeebrooAlert(t('Add at least one line item.'), { tone: 'warning', title: t('Missing information') }); return; }

    const payload = {
      from_branch_id: Number(fromId),
      to_branch_id: Number(toId),
      notes: document.getElementById('tr-notes').value.trim() || null,
      lines: trLines.filter((l) => l.quantity > 0).map((l) => ({ product_id: l.product_id, quantity: l.quantity })),
    };

    const saveBtn = document.getElementById('tr-save');
    saveBtn.disabled = true;
    const res = await API.createStockTransfer(payload);
    saveBtn.disabled = false;

    if (res.status !== 201) {
      const firstError = res.body?.errors ? Object.values(res.body.errors)[0]?.[0] : null;
      await zeebrooAlert(t(firstError || res.body?.message || 'Could not create stock transfer.'), { tone: 'danger', title: t('Could not save transfer') });
      return;
    }
    await zeebrooAlert(t('Stock transfer created.'), { tone: 'success', title: t('Saved') });
    renderStockTransfers();
  }

  // ── Dialog shell: build the DOM, wire close/back handlers, show it ──────
  function openStockModal() {
    if (document.getElementById('stkm-modal-backdrop')) return;

    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'salm-modal-backdrop';
    el.id = 'stkm-modal-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'stkm-modal-title');
    el.innerHTML = `
      <div class="salm-modal-card">
        <div class="salm-modal-head">
          <span class="salm-modal-head-icon"><i class="fa-solid fa-warehouse"></i></span>
          <div class="salm-modal-head-text">
            <h2 id="stkm-modal-title">${t('Stock')}</h2>
            <p>${t('Track stock levels, batches, and adjustments.')}</p>
          </div>
          <button class="salm-modal-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="salm-modal-body">
          <div id="stk-home-view">
            <div class="salm-grid" id="stk-grid"></div>
          </div>

          <div id="stk-section-view" style="display:none">
            <div class="inv-detail-header">
              <button class="inv-back-btn" id="stk-section-back"><i class="fa-solid fa-arrow-left"></i> ${t('Stock')}</button>
              <span class="inv-detail-breadcrumb" id="stk-section-title"></span>
            </div>
            <p class="sales-section-desc" id="stk-section-desc"></p>
            <div id="stk-section-body"></div>
          </div>
        </div>

        <div class="salm-detail-popup" id="stk-detail-view">
          <div class="salm-detail-popup-card" role="dialog" aria-labelledby="stk-detail-title">
            <div class="salm-detail-popup-head">
              <span class="salm-detail-popup-title" id="stk-detail-title"></span>
              <button class="salm-modal-close" id="stk-detail-back" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="stk-detail-body"></div>
            <div class="salm-detail-foot" id="stk-detail-foot"></div>
          </div>
        </div>
      </div>
      <div class="salm-toast" id="stkm-toast"></div>`;
    document.body.appendChild(el);

    cardEl = el.querySelector('.salm-modal-card');
    homeView = document.getElementById('stk-home-view');
    sectionView = document.getElementById('stk-section-view');
    detailView = document.getElementById('stk-detail-view');
    sectionTitleEl = document.getElementById('stk-section-title');
    sectionDescEl = document.getElementById('stk-section-desc');
    sectionBody = document.getElementById('stk-section-body');
    detailTitleEl = document.getElementById('stk-detail-title');
    detailBody = document.getElementById('stk-detail-body');
    detailFoot = document.getElementById('stk-detail-foot');
    toastEl = document.getElementById('stkm-toast');

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function onKey(e) {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      e.stopPropagation();
      if (detailView.classList.contains('open')) { closeDetail(); return; }
      close();
    }

    el.querySelector('.salm-modal-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    document.getElementById('stk-section-back').addEventListener('click', goHome);
    document.getElementById('stk-detail-back').addEventListener('click', closeDetail);
    detailView.addEventListener('mousedown', (e) => { if (e.target === detailView) closeDetail(); });

    requestAnimationFrame(() => el.classList.add('open'));
    goHome();
  }

  window.openStockModal = openStockModal;
  // Called by the Settings dialog after saving, so a changed "Default Pay
  // From" (or currency, etc.) takes effect next time Stock is opened,
  // instead of staying cached for the rest of the app session.
  window.invalidateStockSettingsCache = () => { settingsLoaded = false; };
})();
