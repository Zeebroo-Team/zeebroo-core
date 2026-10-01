'use strict';

// Sales Management — an in-page modal dialog, opened by calling
// window.openSalesModal() (see the dashboard's "Sales Management" tile in
// js/dashboard.js). Builds its own DOM on open and tears it down on close,
// the same pattern js/navbar.js uses for the Settings dialog
// (openSettingsWindow / .sm-backdrop), so the two look and behave alike.
//
// Inside the dialog: two swapped views — a card-grid home (Transactions /
// History / Quotations / Recurring Sales / Rental) and each section's own
// list — see #sales-home-view / #sales-section-view below. A single record's
// detail (#sales-detail-view) is a popup layered on top of whichever of
// those is active, so opening it never navigates the user away from the list.
//
// Talks to the same Laravel POS API as every other POS Lite module
// (Modules/Pos/routes/api.php, prefix /api/v1/pos) via js/api.js.
(function () {
  let cardEl, homeView, sectionView, detailView;
  let sectionTitleEl, sectionDescEl, sectionBody;
  let detailTitleEl, detailBody, detailFoot;
  let toastEl;

  let posSettings = {};
  let settingsLoaded = false;
  let currentSectionKey = null;

  const SECTIONS = [
    { key: 'transactions', icon: 'fa-receipt', title: 'Transactions', desc: 'Every completed and voided POS sale.', accent: '#0d9488' },
    { key: 'history', icon: 'fa-clock-rotate-left', title: 'History', desc: 'Full sales history with dates and summaries.', accent: '#4f46e5' },
    { key: 'quotations', icon: 'fa-file-lines', title: 'Quotations', desc: 'Quotes sent to customers awaiting approval.', accent: '#d97706' },
    { key: 'subscriptions', icon: 'fa-repeat', title: 'Recurring Sales', desc: 'Subscription sales and their billing cycles.', accent: '#7c3aed' },
    { key: 'rentals', icon: 'fa-calendar-days', title: 'Rental', desc: 'Rented items, due dates, and returns.', accent: '#e11d48' },
    { key: 'returns', icon: 'fa-rotate-left', title: 'Returns', desc: 'Processed refunds and returned items (view only).', accent: '#64748b' },
  ];

  // ── Small shared helpers ──────────────────────────────────────────────────
  function esc(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  // hex -> rgba(), and a lighter companion colour for the icon gradient
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
    completed: 'green', accepted: 'green', active: 'green', returned: 'green',
    void: 'red', rejected: 'red', cancelled: 'red', overdue: 'red',
    sent: 'blue', trial: 'blue',
    draft: 'gray', paused: 'gray',
    expired: 'amber',
  };
  function badgeClass(status) { return BADGE_CLASS[status] || 'gray'; }

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
  // A record's detail is a popup layered on top of whichever view is
  // active, not a third swapped view — see openDetail()/closeDetail() below.
  // The card itself widens to near-fullscreen for the section list views
  // (Transactions / History / Quotations / Recurring Sales / Rental) and
  // shrinks back to the compact tile grid on the home view.
  function showView(name) {
    homeView.style.display = name === 'home' ? '' : 'none';
    sectionView.style.display = name === 'section' ? '' : 'none';
    cardEl.classList.toggle('salm-modal-card--wide', name === 'section');
    window.scrollTo(0, 0);
  }

  // ── Home: card grid ─────────────────────────────────────────────────────
  function goHome() {
    closeDetail();
    currentSectionKey = null;
    document.getElementById('sales-grid').innerHTML = SECTIONS.map((s) => `
      <button class="salm-tile" data-key="${s.key}" style="--tile-accent:${s.accent};--tile-accent-2:${lighten(s.accent, 0.35)};--tile-glow:${glow(s.accent, 0.25)}">
        <div class="salm-tile-icon"><i class="fa-solid ${s.icon}"></i></div>
        <div class="salm-tile-title">${t(s.title)}</div>
        <p class="salm-tile-desc">${t(s.desc)}</p>
        <span class="salm-tile-open">${t('Open')} <i class="fa-solid fa-arrow-right"></i></span>
      </button>`).join('');

    document.querySelectorAll('#sales-grid .salm-tile').forEach((el) => {
      el.addEventListener('click', () => openSection(el.dataset.key));
    });

    showView('home');
  }

  const VIEWS = {
    transactions: renderTransactions,
    history: renderHistory,
    quotations: renderQuotations,
    subscriptions: renderSubscriptions,
    rentals: renderRentals,
    returns: renderReturns,
  };

  function openSection(key) {
    closeDetail();
    const meta = SECTIONS.find((s) => s.key === key);
    currentSectionKey = key;
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

  // ════════════════════════════════════════════════════════════════════════
  // Transactions
  // ════════════════════════════════════════════════════════════════════════
  let txAll = [];

  function renderTransactions() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="tx-search" type="text" placeholder="${t('Search sale #, customer, or payment…')}"></div>
        <select class="salm-select" id="tx-channel">
          <option value="">${t('All channels')}</option>
          <option value="retail">${t('POS')}</option>
          <option value="online">${t('Online')}</option>
        </select>
        <select class="salm-select" id="tx-status">
          <option value="">${t('All statuses')}</option>
          <option value="completed">${t('Completed')}</option>
          <option value="void">${t('Voided')}</option>
        </select>
        <button class="salm-icon-btn" id="tx-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <span class="salm-count-pill" id="tx-count"></span>
      </div>
      <div class="salm-kpi-bar" id="tx-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Total Transactions')}</span><span class="salm-kpi-val" id="tx-kpi-total">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Completed')}</span><span class="salm-kpi-val" id="tx-kpi-completed">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Revenue')}</span><span class="salm-kpi-val accent" id="tx-kpi-revenue">0.00</span></div>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Sale #')}</th><th>${t('Date / Time')}</th><th>${t('Customer')}</th><th>${t('Channel')}</th><th>${t('Payment')}</th><th>${t('Status')}</th><th style="text-align:right">${t('Total')}</th><th></th></tr></thead>
        <tbody id="tx-rows"><tr><td colspan="8" class="salm-loading">${t('Loading sales…')}</td></tr></tbody>
      </table></div>`;

    document.getElementById('tx-search').addEventListener('input', debounce(applyTxFilters, 300));
    document.getElementById('tx-channel').addEventListener('change', applyTxFilters);
    document.getElementById('tx-status').addEventListener('change', applyTxFilters);
    document.getElementById('tx-refresh').addEventListener('click', loadTransactions);
    loadTransactions();
  }

  async function loadTransactions() {
    document.getElementById('tx-rows').innerHTML = `<tr><td colspan="8" class="salm-loading">${t('Loading sales…')}</td></tr>`;
    document.getElementById('tx-kpi').style.display = 'none';
    await loadSettings();
    const res = await API.sales();
    if (res.status !== 200) {
      document.getElementById('tx-rows').innerHTML = `<tr><td colspan="8" class="salm-empty">${t('Could not load sales ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      return;
    }
    txAll = res.body.data || [];
    applyTxFilters();
  }

  function applyTxFilters() {
    const q = document.getElementById('tx-search').value.trim().toLowerCase();
    const channel = document.getElementById('tx-channel').value;
    const status = document.getElementById('tx-status').value;

    const list = txAll.filter((s) => {
      if (channel && s.channel !== channel) return false;
      if (status && s.status !== status) return false;
      if (q) {
        const hay = `${s.sale_number || ''} ${s.customer_name || ''} ${s.payment_method || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    const kpi = document.getElementById('tx-kpi');
    if (list.length) {
      const completed = list.filter((s) => s.status !== 'void');
      const revenue = completed.reduce((sum, s) => sum + parseFloat(s.total || 0), 0);
      kpi.style.display = '';
      document.getElementById('tx-kpi-total').textContent = list.length;
      document.getElementById('tx-kpi-completed').textContent = completed.length;
      document.getElementById('tx-kpi-revenue').textContent = money(revenue);
    } else {
      kpi.style.display = 'none';
    }

    document.getElementById('tx-count').textContent = list.length
      ? t(list.length === 1 ? '{n} transaction' : '{n} transactions', { n: list.length })
      : t('No transactions');

    const rowsEl = document.getElementById('tx-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="8" class="salm-empty">${t('No sales found.')}</td></tr>`;
      return;
    }

    rowsEl.innerHTML = list.map((s) => `
      <tr data-id="${s.id}">
        <td class="salm-ref"><i class="fa-solid fa-receipt"></i>${esc(s.sale_number || `#${s.id}`)}</td>
        <td class="salm-muted-cell">${fmtDateTime(s.sold_at)}</td>
        <td class="salm-muted-cell">${esc(s.customer_name) || '—'}</td>
        <td><span class="salm-chip ${s.channel === 'online' ? 'online' : ''}">${s.channel === 'online' ? t('Online') : t('POS')}</span></td>
        <td class="salm-muted-cell">${esc(s.payment_method) || '—'}</td>
        <td><span class="salm-badge ${badgeClass(s.status)}">${s.status === 'void' ? t('Voided') : t('Completed')}</span></td>
        <td class="salm-amt-cell">${money(s.total)}</td>
        <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
      </tr>`).join('');

    rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
      const id = Number(tr.dataset.id);
      tr.addEventListener('click', () => openSaleDetail(id));
    });
  }

  async function openSaleDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.sale(id);
    if (res.status !== 200) {
      openDetail({ title: t('Sale'), bodyHtml: `<div class="salm-empty">${t('Could not load sale ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const s = res.body.data;

    const itemsHtml = (s.items || []).map((it) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(it.product_name)}</div><div class="salm-item-meta">${it.quantity} × ${money(it.unit_sell_price)}</div></div>
        <div class="salm-item-total">${money(it.line_total)}</div>
      </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(s.status)}">${s.status === 'void' ? t('Voided') : t('Completed')}</span></span></div>
      <div class="salm-view-row"><span>${t('Date')}</span><span>${fmtDateTime(s.sold_at)}</span></div>
      <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(s.customer_name) || t('Walk-in')}</span></div>
      <div class="salm-view-row"><span>${t('Cashier')}</span><span>${esc(s.cashier?.name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Channel')}</span><span>${s.channel === 'online' ? t('Online') : t('POS')}</span></div>
      <div class="salm-view-row"><span>${t('Payment method')}</span><span>${esc(s.payment_method_label || s.payment_method) || '—'}</span></div>
      ${s.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(s.notes)}</span></div>` : ''}
      <div class="salm-section-label">${t('Items')}</div>
      <div class="salm-item-list">${itemsHtml}</div>
      <div class="salm-totals">
        <div class="salm-view-row"><span>${t('Subtotal')}</span><span>${money(s.subtotal)}</span></div>
        ${parseFloat(s.discount_amount || 0) > 0 ? `<div class="salm-view-row"><span>${t('Discount')}</span><span>-${money(s.discount_amount)}</span></div>` : ''}
        <div class="salm-view-row grand"><span>${t('Total')}</span><span>${money(s.total)}</span></div>
        ${parseFloat(s.gift_card_amount || 0) > 0 ? `<div class="salm-view-row"><span>${t('Gift card')}${s.gift_card?.code ? ` (${esc(s.gift_card.code)})` : ''}</span><span>-${money(s.gift_card_amount)}</span></div>` : ''}
        <div class="salm-view-row"><span>${t('Amount paid')}</span><span>${money(s.amount_paid)}</span></div>
      </div>`;

    const footHtml = s.status !== 'void'
      ? `<button class="salm-btn-ghost danger" id="tx-void-btn" type="button"><i class="fa-solid fa-ban"></i> ${t('Void Sale')}</button>`
      : '';

    openDetail({ title: s.sale_number || `#${s.id}`, bodyHtml, footHtml });
    document.getElementById('tx-void-btn')?.addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Void {label}? This cannot be undone.', { label: s.sale_number }), { okText: t('Void Sale'), tone: 'danger' })) return;
      const voidRes = await API.voidSale(id);
      if (voidRes.status !== 200) { showToast(t(voidRes.body?.message || 'Could not void sale.'), 'error'); return; }
      closeDetail();
      showToast(t('{label} has been voided.', { label: s.sale_number }), 'success');
      loadTransactions();
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // History
  // ════════════════════════════════════════════════════════════════════════
  let histPage = 1;

  function renderHistory() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="hist-search" type="text" placeholder="${t('Search sale #, customer…')}"></div>
        <select class="salm-select" id="hist-status">
          <option value="all">${t('All statuses')}</option>
          <option value="completed">${t('Completed')}</option>
          <option value="void">${t('Voided')}</option>
        </select>
        <select class="salm-select" id="hist-channel">
          <option value="all">${t('All channels')}</option>
          <option value="retail">${t('POS')}</option>
          <option value="online">${t('Online')}</option>
        </select>
        <input type="date" class="salm-date-input" id="hist-from" title="${t('From date')}">
        <input type="date" class="salm-date-input" id="hist-to" title="${t('To date')}">
        <button class="salm-icon-btn" id="hist-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-kpi-bar" id="hist-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Total Sales')}</span><span class="salm-kpi-val" id="hist-kpi-count">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Completed')}</span><span class="salm-kpi-val" id="hist-kpi-completed">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Voided')}</span><span class="salm-kpi-val" id="hist-kpi-void">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Completed Revenue')}</span><span class="salm-kpi-val accent" id="hist-kpi-revenue">0.00</span></div>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Sale #')}</th><th>${t('Date / Time')}</th><th>${t('Customer')}</th><th>${t('Channel')}</th><th>${t('Payment')}</th><th>${t('Status')}</th><th style="text-align:right">${t('Total')}</th></tr></thead>
        <tbody id="hist-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>
      <div class="salm-pagination" id="hist-pagination"></div>`;

    document.getElementById('hist-search').addEventListener('input', debounce(() => loadHistory(1), 350));
    document.getElementById('hist-status').addEventListener('change', () => loadHistory(1));
    document.getElementById('hist-channel').addEventListener('change', () => loadHistory(1));
    document.getElementById('hist-from').addEventListener('change', () => loadHistory(1));
    document.getElementById('hist-to').addEventListener('change', () => loadHistory(1));
    document.getElementById('hist-refresh').addEventListener('click', () => loadHistory(histPage));
    loadHistory(1);
  }

  async function loadHistory(page) {
    histPage = page;
    document.getElementById('hist-rows').innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();

    const res = await API.salesHistory({
      q: document.getElementById('hist-search').value.trim(),
      status: document.getElementById('hist-status').value,
      channel: document.getElementById('hist-channel').value,
      dateFrom: document.getElementById('hist-from').value,
      dateTo: document.getElementById('hist-to').value,
      page,
    });

    if (res.status !== 200) {
      document.getElementById('hist-rows').innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load history ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      document.getElementById('hist-kpi').style.display = 'none';
      document.getElementById('hist-pagination').innerHTML = '';
      return;
    }

    const list = res.body.data || [];
    const summary = res.body.summary || {};
    const kpi = document.getElementById('hist-kpi');
    if (summary.count) {
      kpi.style.display = '';
      document.getElementById('hist-kpi-count').textContent = summary.count ?? 0;
      document.getElementById('hist-kpi-completed').textContent = summary.completed_count ?? 0;
      document.getElementById('hist-kpi-void').textContent = summary.void_count ?? 0;
      document.getElementById('hist-kpi-revenue').textContent = money(summary.completed_total);
    } else {
      kpi.style.display = 'none';
    }

    const rowsEl = document.getElementById('hist-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No sales found.')}</td></tr>`;
    } else {
      rowsEl.innerHTML = list.map((s) => `
        <tr data-id="${s.id}">
          <td class="salm-ref"><i class="fa-solid fa-receipt"></i>${esc(s.sale_number || `#${s.id}`)}</td>
          <td class="salm-muted-cell">${fmtDateTime(s.sold_at)}</td>
          <td class="salm-muted-cell">${esc(s.customer_name) || '—'}</td>
          <td><span class="salm-chip ${s.channel === 'online' ? 'online' : ''}">${s.channel === 'online' ? t('Online') : t('POS')}</span></td>
          <td class="salm-muted-cell">${esc(s.payment_method) || '—'}</td>
          <td><span class="salm-badge ${badgeClass(s.status)}">${s.status === 'void' ? t('Voided') : t('Completed')}</span></td>
          <td class="salm-amt-cell">${money(s.total)}</td>
        </tr>`).join('');
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openSaleDetail(Number(tr.dataset.id))));
    }

    renderPagination(document.getElementById('hist-pagination'), res.body.meta, loadHistory);
  }

  // ════════════════════════════════════════════════════════════════════════
  // Quotations
  // ════════════════════════════════════════════════════════════════════════
  let quoAll = [];

  function renderQuotations() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="quo-search" type="text" placeholder="${t('Search quote #, customer…')}"></div>
        <select class="salm-select" id="quo-status">
          <option value="all">${t('All statuses')}</option>
          <option value="draft">${t('Draft')}</option>
          <option value="sent">${t('Sent')}</option>
          <option value="accepted">${t('Accepted')}</option>
          <option value="rejected">${t('Rejected')}</option>
          <option value="expired">${t('Expired')}</option>
        </select>
        <button class="salm-icon-btn" id="quo-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <button class="salm-btn-primary" id="quo-new-btn" type="button"><i class="fa-solid fa-plus"></i> ${t('New Quote')}</button>
        <span class="salm-count-pill" id="quo-count"></span>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Quote #')}</th><th>${t('Customer')}</th><th>${t('Quote Date')}</th><th>${t('Expiry')}</th><th>${t('Status')}</th><th style="text-align:right">${t('Total')}</th><th></th></tr></thead>
        <tbody id="quo-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>`;

    document.getElementById('quo-search').addEventListener('input', debounce(loadQuotations, 350));
    document.getElementById('quo-status').addEventListener('change', loadQuotations);
    document.getElementById('quo-refresh').addEventListener('click', loadQuotations);
    document.getElementById('quo-new-btn').addEventListener('click', renderQuotationForm);
    loadQuotations();
  }

  async function loadQuotations() {
    document.getElementById('quo-rows').innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();
    const res = await API.quotations({ q: document.getElementById('quo-search').value.trim(), status: document.getElementById('quo-status').value });
    if (res.status !== 200) {
      document.getElementById('quo-rows').innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load quotations ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      return;
    }
    quoAll = res.body.data || [];
    document.getElementById('quo-count').textContent = quoAll.length ? t(quoAll.length === 1 ? '{n} quotation' : '{n} quotations', { n: quoAll.length }) : t('No quotations');

    const rowsEl = document.getElementById('quo-rows');
    if (!quoAll.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No quotations found.')}</td></tr>`;
      return;
    }
    rowsEl.innerHTML = quoAll.map((q) => `
      <tr data-id="${q.id}">
        <td class="salm-ref"><i class="fa-solid fa-file-lines"></i>${esc(q.quote_number)}</td>
        <td class="salm-muted-cell">${esc(q.customer_name) || '—'}</td>
        <td class="salm-muted-cell">${fmtDate(q.quote_date)}</td>
        <td class="salm-muted-cell">${fmtDate(q.expiry_date)}</td>
        <td><span class="salm-badge ${badgeClass(q.status)}">${esc(q.status_label || q.status)}</span></td>
        <td class="salm-amt-cell">${money(q.total)}</td>
        <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
      </tr>`).join('');
    rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openQuotationDetail(Number(tr.dataset.id))));
  }

  async function openQuotationDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.quotation(id);
    if (res.status !== 200) {
      openDetail({ title: t('Quotation'), bodyHtml: `<div class="salm-empty">${t('Could not load quotation ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const q = res.body.data;

    const itemsHtml = (q.items || []).map((it) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(it.description) || t('Item')}</div><div class="salm-item-meta">${it.quantity} × ${money(it.unit_price)}</div></div>
        <div class="salm-item-total">${money(it.line_total)}</div>
      </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(q.status)}">${esc(q.status_label || q.status)}</span></span></div>
      <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(q.customer_name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Quote date')}</span><span>${fmtDate(q.quote_date)}</span></div>
      <div class="salm-view-row"><span>${t('Expiry date')}</span><span>${fmtDate(q.expiry_date)}</span></div>
      ${q.reference ? `<div class="salm-view-row"><span>${t('Reference')}</span><span>${esc(q.reference)}</span></div>` : ''}
      ${q.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(q.notes)}</span></div>` : ''}
      <div class="salm-section-label">${t('Items')}</div>
      <div class="salm-item-list">${itemsHtml}</div>
      <div class="salm-totals">
        <div class="salm-view-row"><span>${t('Subtotal')}</span><span>${money(q.subtotal)}</span></div>
        ${parseFloat(q.discount_amount || 0) > 0 ? `<div class="salm-view-row"><span>${t('Discount')}</span><span>-${money(q.discount_amount)}</span></div>` : ''}
        ${parseFloat(q.tax_amount || 0) > 0 ? `<div class="salm-view-row"><span>${t('Tax')}</span><span>${money(q.tax_amount)}</span></div>` : ''}
        <div class="salm-view-row grand"><span>${t('Total')}</span><span>${money(q.total)}</span></div>
      </div>`;

    const actions = [];
    if (q.status === 'draft') actions.push(`<button class="salm-btn-primary" id="quo-sent-btn" type="button"><i class="fa-solid fa-paper-plane"></i> ${t('Mark Sent')}</button>`);
    if (q.status === 'sent') {
      actions.push(`<button class="salm-btn-ghost danger" id="quo-reject-btn" type="button"><i class="fa-solid fa-xmark"></i> ${t('Reject')}</button>`);
      actions.push(`<button class="salm-btn-primary" id="quo-accept-btn" type="button"><i class="fa-solid fa-check"></i> ${t('Accept')}</button>`);
    }

    openDetail({ title: q.quote_number, bodyHtml, footHtml: actions.join('') });

    async function runAction(apiCall, successMsg) {
      const res2 = await apiCall();
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not update quotation.'), 'error'); return; }
      closeDetail();
      showToast(successMsg, 'success');
      loadQuotations();
    }
    document.getElementById('quo-sent-btn')?.addEventListener('click', () => runAction(() => API.markQuotationSent(id), t('Quotation marked as sent.')));
    document.getElementById('quo-accept-btn')?.addEventListener('click', () => runAction(() => API.markQuotationAccepted(id), t('Quotation accepted.')));
    document.getElementById('quo-reject-btn')?.addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Reject {label}?', { label: q.quote_number }), { okText: t('Reject'), tone: 'danger' })) return;
      runAction(() => API.markQuotationRejected(id), t('Quotation rejected.'));
    });
  }

  // ── New Quotation form ──────────────────────────────────────────────────
  let qfLines = [];

  function blankQfLine() { return { product_id: null, description: '', quantity: 1, unit_price: 0 }; }
  function qfUnitPrice(p) {
    return (p.discounted_sell_price !== null && p.discounted_sell_price !== undefined) ? p.discounted_sell_price : p.unit_sell_price;
  }

  async function renderQuotationForm() {
    closeDetail();
    qfLines = [];
    const today = new Date().toISOString().slice(0, 10);

    sectionBody.innerHTML = `
      <div class="inv-detail-header qf-form-header">
        <button class="inv-back-btn" id="qf-back"><i class="fa-solid fa-arrow-left"></i> ${t('Back')}</button>
        <span class="inv-detail-breadcrumb">${t('New Quotation')}</span>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-circle-info"></i> ${t('Quote Details')}</div>
        <div class="qf-grid">
          <label class="qf-field"><span>${t('Customer')}</span>
            <select id="qf-customer"><option value="">${t('— Walk-in —')}</option></select>
          </label>
          <label class="qf-field"><span>${t('Reference No.')}</span>
            <input type="text" id="qf-reference" placeholder="${t('e.g. PO-123')}">
          </label>
          <label class="qf-field"><span>${t('Quote Date')}</span>
            <input type="date" id="qf-quote-date" value="${today}">
          </label>
          <label class="qf-field"><span>${t('Valid Until')}</span>
            <input type="date" id="qf-expiry-date">
          </label>
        </div>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-list"></i> ${t('Line Items')}</div>
        <div class="qf-line-table">
          <div class="qf-line-head">
            <span>${t('Description')}</span><span>${t('Qty')}</span><span>${t('Unit Price')}</span><span>${t('Total')}</span><span></span>
          </div>
          <div id="qf-line-rows"></div>
        </div>
        <div class="qf-add-line-row">
          <button class="qf-add-line" id="qf-add-line" type="button"><i class="fa-solid fa-plus"></i> ${t('Add Line')}</button>
          <button class="qf-add-line qf-add-line-custom" id="qf-add-custom-line" type="button"><i class="fa-solid fa-pen"></i> ${t('Custom item')}</button>
        </div>
      </div>

      <div class="qf-bottom">
        <div class="qf-card qf-notes-card">
          <div class="qf-card-title"><i class="fa-solid fa-note-sticky"></i> ${t('Notes')}</div>
          <textarea id="qf-notes" placeholder="${t('Terms, conditions or notes for this quotation…')}"></textarea>
        </div>
        <div class="qf-card qf-summary-card">
          <div class="qf-card-title"><i class="fa-solid fa-receipt"></i> ${t('Summary')}</div>
          <div class="qf-summary-row"><span>${t('Subtotal')}</span><span id="qf-subtotal">0.00</span></div>
          <div class="qf-summary-row"><span>${t('Discount')}</span><input type="number" id="qf-discount" min="0" step="0.01" value="0"></div>
          <div class="qf-summary-row"><span>${t('Tax')}</span><input type="number" id="qf-tax" min="0" step="0.01" value="0"></div>
          <div class="qf-summary-row qf-total"><span>${t('Total')}</span><span id="qf-total">0.00</span></div>
        </div>
      </div>

      <div class="qf-actions">
        <button class="salm-btn-ghost" id="qf-cancel" type="button">${t('Cancel')}</button>
        <button class="salm-btn-primary" id="qf-save" type="button"><i class="fa-solid fa-check"></i> ${t('Save Quotation')}</button>
      </div>`;

    document.getElementById('qf-back').addEventListener('click', renderQuotations);
    document.getElementById('qf-cancel').addEventListener('click', renderQuotations);
    document.getElementById('qf-add-line').addEventListener('click', openQfProductPicker);
    document.getElementById('qf-add-custom-line').addEventListener('click', () => { qfLines.push(blankQfLine()); renderQfLines(); recalcQfSummary(); });
    document.getElementById('qf-discount').addEventListener('input', recalcQfSummary);
    document.getElementById('qf-tax').addEventListener('input', recalcQfSummary);
    document.getElementById('qf-save').addEventListener('click', submitQuotationForm);

    renderQfLines();
    await loadSettings();
    recalcQfSummary();

    const custRes = await API.customers();
    if (custRes.status === 200) {
      const sel = document.getElementById('qf-customer');
      if (sel) {
        sel.insertAdjacentHTML('beforeend', (custRes.body.data || []).map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join(''));
      }
    }
  }

  function renderQfLines() {
    const rowsEl = document.getElementById('qf-line-rows');
    if (!qfLines.length) {
      rowsEl.innerHTML = `<div class="qf-line-empty">${t('No items yet — add a line to include products or charges.')}</div>`;
      return;
    }

    rowsEl.innerHTML = qfLines.map((line, idx) => `
      <div class="qf-line-row" data-idx="${idx}">
        <div class="qf-line-desc-wrap">
          <input type="text" class="qf-line-desc" placeholder="${t('Item description')}" value="${esc(line.description)}">
          ${line.product_id ? `<span class="qf-line-tag"><i class="fa-solid fa-box"></i> ${t('Catalog item')}</span>` : ''}
        </div>
        <input type="number" class="qf-line-qty" min="0" step="0.001" value="${line.quantity}">
        <input type="number" class="qf-line-price" min="0" step="0.01" value="${line.unit_price}">
        <span class="qf-line-total">${money(line.quantity * line.unit_price)}</span>
        <button type="button" class="qf-line-remove" title="${t('Remove')}"><i class="fa-solid fa-trash"></i></button>
      </div>`).join('');

    rowsEl.querySelectorAll('.qf-line-row').forEach((row) => {
      const idx = Number(row.dataset.idx);
      row.querySelector('.qf-line-desc').addEventListener('input', (e) => { qfLines[idx].description = e.target.value; });
      row.querySelector('.qf-line-qty').addEventListener('input', (e) => {
        qfLines[idx].quantity = parseFloat(e.target.value) || 0;
        row.querySelector('.qf-line-total').textContent = money(qfLines[idx].quantity * qfLines[idx].unit_price);
        recalcQfSummary();
      });
      row.querySelector('.qf-line-price').addEventListener('input', (e) => {
        qfLines[idx].unit_price = parseFloat(e.target.value) || 0;
        row.querySelector('.qf-line-total').textContent = money(qfLines[idx].quantity * qfLines[idx].unit_price);
        recalcQfSummary();
      });
      row.querySelector('.qf-line-remove').addEventListener('click', () => {
        qfLines.splice(idx, 1);
        renderQfLines();
        recalcQfSummary();
      });
    });
  }

  // ── Product picker for "Add Line" (mirrors the full desktop app's
  // "Add Product" dialog) — searches the catalog and adds the picked
  // product as a new quotation line, pre-filled with its price. ──────────
  function openQfProductPicker() {
    if (document.getElementById('qf-picker-backdrop')) return;

    const el = document.createElement('div');
    el.className = 'qf-picker-backdrop';
    el.id = 'qf-picker-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML = `
      <div class="qf-picker-card">
        <div class="qf-picker-head">
          <span class="qf-picker-head-icon"><i class="fa-solid fa-box"></i></span>
          <span class="qf-picker-title">${t('Add Product')}</span>
          <button class="salm-modal-close" id="qf-picker-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="qf-picker-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="qf-picker-input" placeholder="${t('Search products…')}"></div>
        <div class="qf-picker-list" id="qf-picker-list"><div class="salm-loading">${t('Loading…')}</div></div>
        <div class="qf-picker-foot"><button class="salm-btn-ghost" id="qf-picker-cancel" type="button">${t('Cancel')}</button></div>
      </div>`;
    document.body.appendChild(el);

    function close() { el.remove(); }
    document.getElementById('qf-picker-close').addEventListener('click', close);
    document.getElementById('qf-picker-cancel').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });

    let lastResults = [];
    async function search(q) {
      const listEl = document.getElementById('qf-picker-list');
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
            <div class="qf-picker-price">${money(qfUnitPrice(p))}</div>
          </div>`;
      }).join('');

      listEl.querySelectorAll('.qf-picker-row').forEach((row) => {
        row.addEventListener('click', () => {
          const p = lastResults.find((x) => String(x.id) === row.dataset.id);
          if (!p) return;
          qfLines.push({ product_id: p.id, description: p.name, quantity: 1, unit_price: qfUnitPrice(p) });
          renderQfLines();
          recalcQfSummary();
          close();
        });
      });
    }

    const input = document.getElementById('qf-picker-input');
    let debounceTimer;
    input.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => search(input.value.trim()), 300); });
    search('');
    setTimeout(() => input.focus(), 30);
  }

  function recalcQfSummary() {
    const subtotal = qfLines.reduce((sum, l) => sum + (l.quantity * l.unit_price), 0);
    const discount = parseFloat(document.getElementById('qf-discount')?.value) || 0;
    const tax = parseFloat(document.getElementById('qf-tax')?.value) || 0;
    const total = Math.max(0, subtotal - discount + tax);
    document.getElementById('qf-subtotal').textContent = money(subtotal);
    document.getElementById('qf-total').textContent = money(total);
  }

  async function submitQuotationForm() {
    const quoteDate = document.getElementById('qf-quote-date').value;
    if (!quoteDate) { await zeebrooAlert(t('Quote date is required.'), { tone: 'warning', title: t('Missing information') }); return; }

    const items = qfLines
      .filter((l) => l.quantity > 0)
      .map((l) => ({
        item_type: l.product_id ? 'product' : 'custom',
        product_id: l.product_id || null,
        description: l.description.trim() || null,
        quantity: l.quantity,
        unit_price: l.unit_price,
      }));
    if (!items.length) { await zeebrooAlert(t('Add at least one line item.'), { tone: 'warning', title: t('Missing information') }); return; }

    const customerId = document.getElementById('qf-customer').value;
    const payload = {
      customer_id: customerId ? Number(customerId) : null,
      reference: document.getElementById('qf-reference').value.trim() || null,
      quote_date: quoteDate,
      expiry_date: document.getElementById('qf-expiry-date').value || null,
      notes: document.getElementById('qf-notes').value.trim() || null,
      discount_amount: parseFloat(document.getElementById('qf-discount').value) || 0,
      tax_amount: parseFloat(document.getElementById('qf-tax').value) || 0,
      items,
    };

    const saveBtn = document.getElementById('qf-save');
    saveBtn.disabled = true;
    const res = await API.createQuotation(payload);
    saveBtn.disabled = false;

    if (res.status !== 201) {
      const firstError = res.body?.errors ? Object.values(res.body.errors)[0]?.[0] : null;
      await zeebrooAlert(t(firstError || res.body?.message || 'Could not create quotation.'), { tone: 'danger', title: t('Could not save quotation') });
      return;
    }
    await zeebrooAlert(t(res.body?.message || 'Quotation created.'), { tone: 'success', title: t('Quotation saved') });
    renderQuotations();
  }

  // ════════════════════════════════════════════════════════════════════════
  // Recurring Sales (Customer Subscriptions)
  // ════════════════════════════════════════════════════════════════════════
  let subPage = 1;

  function renderSubscriptions() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="sub-search" type="text" placeholder="${t('Search customer, product…')}"></div>
        <select class="salm-select" id="sub-status">
          <option value="all">${t('All statuses')}</option>
          <option value="trial">${t('Trial')}</option>
          <option value="active">${t('Active')}</option>
          <option value="paused">${t('Paused')}</option>
          <option value="cancelled">${t('Cancelled')}</option>
        </select>
        <button class="salm-icon-btn" id="sub-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Customer')}</th><th>${t('Product')}</th><th>${t('Period')}</th><th style="text-align:right">${t('Price')}</th><th>${t('Status')}</th><th>${t('Next Billing')}</th><th></th></tr></thead>
        <tbody id="sub-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>
      <div class="salm-pagination" id="sub-pagination"></div>`;

    document.getElementById('sub-search').addEventListener('input', debounce(() => loadSubscriptions(1), 350));
    document.getElementById('sub-status').addEventListener('change', () => loadSubscriptions(1));
    document.getElementById('sub-refresh').addEventListener('click', () => loadSubscriptions(subPage));
    loadSubscriptions(1);
  }

  async function loadSubscriptions(page) {
    subPage = page;
    document.getElementById('sub-rows').innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();
    const res = await API.subscriptions({ q: document.getElementById('sub-search').value.trim(), status: document.getElementById('sub-status').value, page });
    if (res.status !== 200) {
      document.getElementById('sub-rows').innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load recurring sales ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      document.getElementById('sub-pagination').innerHTML = '';
      return;
    }
    const list = res.body.data || [];
    const rowsEl = document.getElementById('sub-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No recurring sales found.')}</td></tr>`;
    } else {
      rowsEl.innerHTML = list.map((s) => `
        <tr data-id="${s.id}">
          <td>${esc(s.customer_name) || '—'}</td>
          <td class="salm-muted-cell">${esc(s.product_name) || '—'}</td>
          <td class="salm-muted-cell">${esc(s.recurring_period) || '—'}</td>
          <td class="salm-amt-cell">${money(s.price)}</td>
          <td><span class="salm-badge ${badgeClass(s.status)}">${esc(s.status_label || s.status)}</span></td>
          <td class="salm-muted-cell">${fmtDate(s.next_billing_at)}</td>
          <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
        </tr>`).join('');
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openSubscriptionDetail(Number(tr.dataset.id))));
    }
    renderPagination(document.getElementById('sub-pagination'), res.body.meta, loadSubscriptions);
  }

  async function openSubscriptionDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.subscription(id);
    if (res.status !== 200) {
      openDetail({ title: t('Recurring Sale'), bodyHtml: `<div class="salm-empty">${t('Could not load recurring sale ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const s = res.body.data;

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(s.status)}">${esc(s.status_label || s.status)}</span></span></div>
      <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(s.customer_name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Product')}</span><span>${esc(s.product_name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Quantity')}</span><span>${s.quantity}</span></div>
      <div class="salm-view-row"><span>${t('Price')}</span><span>${money(s.price)}</span></div>
      <div class="salm-view-row"><span>${t('Billing period')}</span><span>${esc(s.recurring_period) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Started')}</span><span>${fmtDate(s.started_at)}</span></div>
      <div class="salm-view-row"><span>${t('Next billing')}</span><span>${fmtDate(s.next_billing_at)}</span></div>
      <div class="salm-view-row"><span>${t('Last renewed')}</span><span>${fmtDate(s.last_renewed_at)}</span></div>
      ${s.sale_number ? `<div class="salm-view-row"><span>${t('Originating sale')}</span><span>${esc(s.sale_number)}</span></div>` : ''}`;

    const actions = [];
    if (s.status !== 'cancelled') {
      actions.push(s.status === 'paused'
        ? `<button class="salm-btn-ghost" id="sub-resume-btn" type="button"><i class="fa-solid fa-play"></i> ${t('Resume')}</button>`
        : `<button class="salm-btn-ghost" id="sub-pause-btn" type="button"><i class="fa-solid fa-pause"></i> ${t('Pause')}</button>`);
      actions.push(`<button class="salm-btn-ghost" id="sub-renew-btn" type="button"><i class="fa-solid fa-rotate"></i> ${t('Renew')}</button>`);
      actions.push(`<button class="salm-btn-ghost danger" id="sub-cancel-btn" type="button"><i class="fa-solid fa-ban"></i> ${t('Cancel')}</button>`);
    }

    openDetail({ title: `${s.product_name || t('Recurring Sale')} — ${s.customer_name || ''}`, bodyHtml, footHtml: actions.join('') });

    async function runAction(apiCall, successMsg) {
      const res2 = await apiCall();
      if (res2.status !== 200) { showToast(t(res2.body?.message || 'Could not update subscription.'), 'error'); return; }
      closeDetail();
      showToast(successMsg, 'success');
      loadSubscriptions(subPage);
    }
    document.getElementById('sub-pause-btn')?.addEventListener('click', () => runAction(() => API.pauseSubscription(id), t('Subscription paused.')));
    document.getElementById('sub-resume-btn')?.addEventListener('click', () => runAction(() => API.resumeSubscription(id), t('Subscription resumed.')));
    document.getElementById('sub-renew-btn')?.addEventListener('click', () => runAction(() => API.renewSubscription(id), t('Subscription marked as renewed.')));
    document.getElementById('sub-cancel-btn')?.addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Cancel this recurring sale?'), { okText: t('Cancel Subscription'), tone: 'danger' })) return;
      runAction(() => API.cancelSubscription(id), t('Subscription cancelled.'));
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // Rentals (Product Rentals)
  // ════════════════════════════════════════════════════════════════════════
  let rentPage = 1;

  function renderRentals() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="rent-search" type="text" placeholder="${t('Search customer, product…')}"></div>
        <select class="salm-select" id="rent-status">
          <option value="all">${t('All statuses')}</option>
          <option value="active">${t('Active')}</option>
          <option value="overdue">${t('Overdue')}</option>
          <option value="returned">${t('Returned')}</option>
          <option value="cancelled">${t('Cancelled')}</option>
        </select>
        <button class="salm-icon-btn" id="rent-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Customer')}</th><th>${t('Product')}</th><th>${t('Rented')}</th><th>${t('Due')}</th><th>${t('Status')}</th><th style="text-align:right">${t('Total')}</th><th></th></tr></thead>
        <tbody id="rent-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>
      <div class="salm-pagination" id="rent-pagination"></div>`;

    document.getElementById('rent-search').addEventListener('input', debounce(() => loadRentals(1), 350));
    document.getElementById('rent-status').addEventListener('change', () => loadRentals(1));
    document.getElementById('rent-refresh').addEventListener('click', () => loadRentals(rentPage));
    loadRentals(1);
  }

  async function loadRentals(page) {
    rentPage = page;
    document.getElementById('rent-rows').innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();
    const res = await API.productRentals({ q: document.getElementById('rent-search').value.trim(), status: document.getElementById('rent-status').value, page });
    if (res.status !== 200) {
      document.getElementById('rent-rows').innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load rentals ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      document.getElementById('rent-pagination').innerHTML = '';
      return;
    }
    const list = res.body.data || [];
    const rowsEl = document.getElementById('rent-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No rentals found.')}</td></tr>`;
    } else {
      rowsEl.innerHTML = list.map((r) => `
        <tr data-id="${r.id}">
          <td>${esc(r.customer_name) || '—'}</td>
          <td class="salm-muted-cell">${esc(r.product_name) || '—'}</td>
          <td class="salm-muted-cell">${fmtDate(r.rented_at)}</td>
          <td class="salm-muted-cell">${fmtDate(r.due_at)}</td>
          <td><span class="salm-badge ${badgeClass(r.status)}">${esc(r.status_label || r.status)}</span></td>
          <td class="salm-amt-cell">${money(r.total_amount)}</td>
          <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
        </tr>`).join('');
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openRentalDetail(Number(tr.dataset.id))));
    }
    renderPagination(document.getElementById('rent-pagination'), res.body.meta, loadRentals);
  }

  async function openRentalDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.productRental(id);
    if (res.status !== 200) {
      openDetail({ title: t('Rental'), bodyHtml: `<div class="salm-empty">${t('Could not load rental ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const r = res.body.data;

    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(r.status)}">${esc(r.status_label || r.status)}</span></span></div>
      <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(r.customer_name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Product')}</span><span>${esc(r.product_name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Quantity')}</span><span>${r.quantity}</span></div>
      <div class="salm-view-row"><span>${t('Daily rate')}</span><span>${money(r.daily_rate)}</span></div>
      <div class="salm-view-row"><span>${t('Rented on')}</span><span>${fmtDate(r.rented_at)}</span></div>
      <div class="salm-view-row"><span>${t('Due on')}</span><span>${fmtDate(r.due_at)}</span></div>
      ${r.returned_at ? `<div class="salm-view-row"><span>${t('Returned on')}</span><span>${fmtDate(r.returned_at)}</span></div>` : ''}
      <div class="salm-view-row"><span>${t('Duration')}</span><span>${t(r.duration_days === 1 ? '{n} day' : '{n} days', { n: r.duration_days })}</span></div>
      ${r.days_late > 0 ? `<div class="salm-view-row"><span>${t('Days late')}</span><span>${r.days_late}</span></div>` : ''}
      ${r.days_remaining > 0 ? `<div class="salm-view-row"><span>${t('Days remaining')}</span><span>${r.days_remaining}</span></div>` : ''}
      ${r.sale_number ? `<div class="salm-view-row"><span>${t('Originating sale')}</span><span>${esc(r.sale_number)}</span></div>` : ''}
      <div class="salm-totals">
        <div class="salm-view-row"><span>${t('Base total')}</span><span>${money(r.base_total)}</span></div>
        ${parseFloat(r.late_fee || r.projected_late_fee || 0) > 0 ? `<div class="salm-view-row"><span>${t('Late fee')}</span><span>${money(r.late_fee || r.projected_late_fee)}</span></div>` : ''}
        <div class="salm-view-row grand"><span>${t('Total')}</span><span>${money(r.total_amount)}</span></div>
      </div>`;

    const footHtml = (r.status === 'active' || r.status === 'overdue')
      ? `<button class="salm-btn-primary" id="rent-return-btn" type="button"><i class="fa-solid fa-rotate-left"></i> ${t('Mark Returned')}</button>`
      : '';

    openDetail({ title: `${r.product_name || t('Rental')} — ${r.customer_name || ''}`, bodyHtml, footHtml });
    document.getElementById('rent-return-btn')?.addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Mark this rental as returned?'), { okText: t('Mark Returned') })) return;
      const retRes = await API.returnProductRental(id);
      if (retRes.status !== 200) { showToast(t(retRes.body?.message || 'Could not update rental.'), 'error'); return; }
      closeDetail();
      showToast(t('Rental marked as returned.'), 'success');
      loadRentals(rentPage);
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // Returns — read-only list of processed refunds. Processing a new return
  // happens from the POS screen's Return button (F9), not here.
  // ════════════════════════════════════════════════════════════════════════
  let retPage = 1;

  function renderReturns() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="ret-search" type="text" placeholder="${t('Search return #, sale #, customer…')}"></div>
        <button class="salm-icon-btn" id="ret-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <span class="salm-count-pill" id="ret-count"></span>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Return #')}</th><th>${t('Date')}</th><th>${t('Sale #')}</th><th>${t('Customer')}</th><th>${t('Method')}</th><th>${t('Reason')}</th><th style="text-align:right">${t('Total')}</th></tr></thead>
        <tbody id="ret-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>
      <div class="salm-pagination" id="ret-pagination"></div>`;

    document.getElementById('ret-search').addEventListener('input', debounce(() => loadReturns(1), 350));
    document.getElementById('ret-refresh').addEventListener('click', () => loadReturns(retPage));
    loadReturns(1);
  }

  async function loadReturns(page) {
    retPage = page;
    document.getElementById('ret-rows').innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();

    const res = await API.saleReturns({ q: document.getElementById('ret-search').value.trim(), page });

    if (res.status !== 200) {
      document.getElementById('ret-rows').innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load returns ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      document.getElementById('ret-count').textContent = '';
      document.getElementById('ret-pagination').innerHTML = '';
      return;
    }

    const list = res.body.data || [];
    document.getElementById('ret-count').textContent = res.body.meta?.total
      ? t(res.body.meta.total === 1 ? '{n} return' : '{n} returns', { n: res.body.meta.total })
      : t('No returns');

    const rowsEl = document.getElementById('ret-rows');
    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No returns found.')}</td></tr>`;
    } else {
      rowsEl.innerHTML = list.map((r) => `
        <tr data-id="${r.id}">
          <td class="salm-ref"><i class="fa-solid fa-rotate-left"></i>${esc(r.return_number || `#${r.id}`)}</td>
          <td class="salm-muted-cell">${fmtDateTime(r.returned_at)}</td>
          <td class="salm-muted-cell">${esc(r.sale_number) || '—'}</td>
          <td class="salm-muted-cell">${esc(r.customer_name) || '—'}</td>
          <td class="salm-muted-cell">${esc(r.refund_method_label || r.refund_method) || '—'}</td>
          <td class="salm-muted-cell">${esc(r.refund_reason_label) || '—'}</td>
          <td class="salm-amt-cell">${money(r.total)}</td>
        </tr>`).join('');
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openReturnDetail(Number(tr.dataset.id))));
    }

    renderPagination(document.getElementById('ret-pagination'), res.body.meta, loadReturns);
  }

  async function openReturnDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.saleReturn(id);
    if (res.status !== 200) {
      openDetail({ title: t('Return'), bodyHtml: `<div class="salm-empty">${t('Could not load return ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const r = res.body.data;

    const itemsHtml = (r.items || []).map((it) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(it.product_name)}</div><div class="salm-item-meta">${it.quantity} × ${money(it.unit_sell_price)}</div></div>
        <div class="salm-item-total">${money(it.line_total)}</div>
      </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    // View-only: no footHtml/actions — processing returns happens from the POS screen.
    const bodyHtml = `
      <div class="salm-view-row"><span>${t('Date')}</span><span>${fmtDateTime(r.returned_at)}</span></div>
      <div class="salm-view-row"><span>${t('Original sale')}</span><span>${esc(r.sale_number) || t('Walk-in return')}</span></div>
      <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(r.customer_name) || t('Walk-in')}</span></div>
      <div class="salm-view-row"><span>${t('Processed by')}</span><span>${esc(r.cashier?.name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Refund method')}</span><span>${esc(r.refund_method_label || r.refund_method) || '—'}</span></div>
      ${r.refund_reason_label ? `<div class="salm-view-row"><span>${t('Reason')}</span><span>${esc(r.refund_reason_label)}</span></div>` : ''}
      ${r.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(r.notes)}</span></div>` : ''}
      <div class="salm-section-label">${t('Items returned')}</div>
      <div class="salm-item-list">${itemsHtml}</div>
      <div class="salm-totals">
        <div class="salm-view-row grand"><span>${t('Refund Total')}</span><span>${money(r.total)}</span></div>
      </div>`;

    openDetail({ title: r.return_number || `#${r.id}`, bodyHtml });
  }

  // ── Dialog shell: build the DOM, wire close/back handlers, show it ──────
  // Mirrors js/navbar.js's openSettingsWindow (same .salm-modal-backdrop /
  // .salm-modal-card shell as .sm-backdrop / .sm-card) so this dialog looks
  // and behaves the same as the Settings dialog.
  function openSalesModal() {
    if (document.getElementById('salm-modal-backdrop')) return;

    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'salm-modal-backdrop';
    el.id = 'salm-modal-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'salm-modal-title');
    el.innerHTML = `
      <div class="salm-modal-card">
        <div class="salm-modal-head">
          <span class="salm-modal-head-icon"><i class="fa-solid fa-receipt"></i></span>
          <div class="salm-modal-head-text">
            <h2 id="salm-modal-title">${t('Sales Management')}</h2>
            <p>${t('View, manage, and track past sales and returns.')}</p>
          </div>
          <button class="salm-modal-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="salm-modal-body">
          <div id="sales-home-view">
            <div class="salm-grid" id="sales-grid"></div>
          </div>

          <div id="sales-section-view" style="display:none">
            <div class="inv-detail-header">
              <button class="inv-back-btn" id="sales-section-back"><i class="fa-solid fa-arrow-left"></i> ${t('Sales Management')}</button>
              <span class="inv-detail-breadcrumb" id="sales-section-title"></span>
            </div>
            <p class="sales-section-desc" id="sales-section-desc"></p>
            <div id="sales-section-body"></div>
          </div>
        </div>

        <div class="salm-detail-popup" id="sales-detail-view">
          <div class="salm-detail-popup-card" role="dialog" aria-labelledby="sales-detail-title">
            <div class="salm-detail-popup-head">
              <span class="salm-detail-popup-title" id="sales-detail-title"></span>
              <button class="salm-modal-close" id="sales-detail-back" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="sales-detail-body"></div>
            <div class="salm-detail-foot" id="sales-detail-foot"></div>
          </div>
        </div>
      </div>
      <div class="salm-toast" id="salm-toast"></div>`;
    document.body.appendChild(el);

    cardEl = el.querySelector('.salm-modal-card');
    homeView = document.getElementById('sales-home-view');
    sectionView = document.getElementById('sales-section-view');
    detailView = document.getElementById('sales-detail-view');
    sectionTitleEl = document.getElementById('sales-section-title');
    sectionDescEl = document.getElementById('sales-section-desc');
    sectionBody = document.getElementById('sales-section-body');
    detailTitleEl = document.getElementById('sales-detail-title');
    detailBody = document.getElementById('sales-detail-body');
    detailFoot = document.getElementById('sales-detail-foot');
    toastEl = document.getElementById('salm-toast');

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

    document.getElementById('sales-section-back').addEventListener('click', goHome);
    document.getElementById('sales-detail-back').addEventListener('click', closeDetail);
    detailView.addEventListener('mousedown', (e) => { if (e.target === detailView) closeDetail(); });

    requestAnimationFrame(() => el.classList.add('open'));
    goHome();
  }

  window.openSalesModal = openSalesModal;
})();
