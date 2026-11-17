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
    { key: 'reverts', icon: 'fa-ban', title: 'Reverted Bills', desc: 'Bills undone because they were entered by mistake, with the reason.', accent: '#dc2626' },
    { key: 'cheques', icon: 'fa-money-check', title: 'Cheques', desc: 'Customer cheques taken at checkout — claim them once deposited.', accent: '#0284c7' },
    { key: 'credits', icon: 'fa-hand-holding-dollar', title: 'Pending Credits', desc: 'Unpaid credit sales by customer — receive their payments.', accent: '#ea580c' },
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

  // ── Cheque status helpers (shared by Transactions, History, sale detail, Cheques) ──
  const CHEQUE_BADGE = { pending: 'amber', due: 'blue', cleared: 'green', bounced: 'red', cancelled: 'gray' };
  const CHEQUE_LABEL = { pending: 'Not claimed', due: 'Due to claim', cleared: 'Claimed', bounced: 'Bounced', cancelled: 'Cancelled' };

  // A pending cheque whose date has arrived shows as "Due to claim"
  function chequeBadge(status, isDue = false) {
    const key = status === 'pending' && isDue ? 'due' : status;
    return `<span class="salm-badge ${CHEQUE_BADGE[key] || 'gray'}">${t(CHEQUE_LABEL[key] || key)}</span>`;
  }

  // Payment column: "cheque" plus its claim status for cheque sales; for credit
  // sales still owing money, the due amount and a Pay button
  function paymentCell(s) {
    if (s.payment_method === 'cheque' && s.cheque_status && s.status !== 'void') {
      return `${t('Cheque')} ${chequeBadge(s.cheque_status)}`;
    }
    const due = Number(s.due_amount) || 0;
    if (s.payment_method !== 'credit' || due <= 0.005 || s.status === 'void') return esc(s.payment_method) || '—';
    return `${t('Credit')} <span class="salm-badge ${s.is_overdue ? 'red' : 'amber'}">${t('Due {amount}', { amount: money(due) })}</span>
      ${s.pos_customer_id ? `<button type="button" class="salm-pay-btn" data-pay="${s.id}"><i class="fa-solid fa-hand-holding-dollar"></i> ${t('Pay')}</button>` : ''}`;
  }

  // Pay buttons inside a sales list → receive a payment against that one bill
  function wirePayButtons(rowsEl, list, onDone) {
    rowsEl.querySelectorAll('[data-pay]').forEach((btn) => btn.addEventListener('click', (e) => {
      e.stopPropagation(); // don't open the sale detail
      const s = list.find((x) => x.id === Number(btn.dataset.pay));
      if (s) {
        openCreditPayForm({
          customerId: s.pos_customer_id, customerName: s.customer_name,
          saleId: s.id, saleNumber: s.sale_number, due: s.due_amount, onDone,
        });
      }
    }));
  }

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
    reverts: renderReverts,
    cheques: renderCheques,
    credits: renderCredits,
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
          <option value="void">${t('Reverted')}</option>
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
        <td class="salm-muted-cell">${paymentCell(s)}</td>
        <td><span class="salm-badge ${badgeClass(s.status)}">${s.status === 'void' ? t('Reverted') : t('Completed')}</span></td>
        <td class="salm-amt-cell">${money(s.total)}</td>
        <td><div class="salm-row-actions"><button data-view title="${t('View')}"><i class="fa-solid fa-eye"></i></button></div></td>
      </tr>`).join('');

    rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
      const id = Number(tr.dataset.id);
      tr.addEventListener('click', () => openSaleDetail(id));
    });
    wirePayButtons(rowsEl, list, () => { closeDetail(); loadTransactions(); });
  }

  async function openSaleDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.sale(id);
    if (res.status !== 200) {
      openDetail({ title: t('Sale'), bodyHtml: `<div class="salm-empty">${t('Could not load sale ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const s = res.body.data;
    const isCredit = s.payment_method === 'credit';

    const itemsHtml = (s.items || []).map((it) => `
      <div class="salm-item-row">
        <div><div class="salm-item-name">${esc(it.product_name)}</div><div class="salm-item-meta">${it.quantity} × ${money(it.unit_sell_price)}</div></div>
        <div class="salm-item-total">${money(it.line_total)}</div>
      </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    const rev = s.revert;
    const revertNotice = s.status === 'void' && rev ? `
      <div class="salm-revert-notice">
        <div><i class="fa-solid fa-ban"></i> <strong>${t('Reverted')}</strong> — ${esc(t(rev.reason_label))} <span class="salm-muted-inline">(${esc(rev.revert_number)})</span></div>
        ${rev.notes ? `<div class="salm-revert-note">“${esc(rev.notes)}”</div>` : ''}
        <div class="salm-revert-meta">${fmtDateTime(rev.reverted_at)}${rev.reverted_by ? ` · ${esc(rev.reverted_by)}` : ''} · ${t('Refund due')} ${money(rev.amount_refunded)}</div>
      </div>` : '';

    const bodyHtml = `
      ${revertNotice}
      <div class="salm-view-row"><span>${t('Status')}</span><span><span class="salm-badge ${badgeClass(s.status)}">${s.status === 'void' ? t('Reverted') : t('Completed')}</span></span></div>
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
        ${parseFloat(s.coupon_discount || 0) > 0 ? `<div class="salm-view-row"><span>${t('Coupon')}${s.coupon?.code ? ` (${esc(s.coupon.code)})` : ''}</span><span>-${money(s.coupon_discount)}</span></div>` : ''}
        <div class="salm-view-row grand"><span>${t('Total')}</span><span>${money(s.total)}</span></div>
        ${parseFloat(s.gift_card_amount || 0) > 0 ? `<div class="salm-view-row"><span>${t('Gift card')}${s.gift_card?.code ? ` (${esc(s.gift_card.code)})` : ''}</span><span>-${money(s.gift_card_amount)}</span></div>` : ''}
        ${isCredit ? creditTotalsHtml(s) : `<div class="salm-view-row"><span>${t('Amount paid')}</span><span>${money(s.amount_paid)}</span></div>`}
      </div>
      ${s.cheque ? chequeDetailsHtml(s.cheque) : ''}
      ${isCredit ? creditPaymentsHtml(s.credit_payments || []) : ''}`;

    const canClaim = s.cheque?.is_claimable && s.status !== 'void';
    const canPay = isCredit && s.status !== 'void' && s.pos_customer_id && (Number(s.due_amount) || 0) > 0.005;
    const footHtml = s.status !== 'void'
      ? `${canClaim ? `<button class="salm-btn-primary" id="tx-chq-claim-btn" type="button"><i class="fa-solid fa-building-columns"></i> ${t('Claim Cheque')}</button>` : ''}
         ${canPay ? `<button class="salm-btn-primary" id="tx-credit-pay-btn" type="button"><i class="fa-solid fa-hand-holding-dollar"></i> ${t('Receive Payment')}</button>` : ''}
         <button class="salm-btn-ghost danger" id="tx-void-btn" type="button" title="${t('Undo a bill entered by mistake — restores stock and payment')}"><i class="fa-solid fa-ban"></i> ${t('Revert Bill')}</button>`
      : '';

    openDetail({ title: s.sale_number || `#${s.id}`, bodyHtml, footHtml });
    document.getElementById('tx-void-btn')?.addEventListener('click', () => openRevertForm(s));
    document.getElementById('tx-credit-pay-btn')?.addEventListener('click', () => openCreditPayForm({
      customerId: s.pos_customer_id, customerName: s.customer_name,
      saleId: s.id, saleNumber: s.sale_number, due: s.due_amount,
      onDone: () => openSaleDetail(s.id),
      onCancel: () => openSaleDetail(s.id),
    }));
    // The cheque inside a sale payload omits the bill/customer — fill them in for the claim form
    document.getElementById('tx-chq-claim-btn')?.addEventListener('click', () => openChequeForm(
      { ...s.cheque, sale_number: s.sale_number, customer: s.customer_name ? { name: s.customer_name } : null },
      'claim',
      () => openSaleDetail(s.id),
    ));
  }

  // Credit sale totals: what's been received since the sale, and what's still owed
  function creditTotalsHtml(s) {
    // amount_paid = gift card + payments received later
    const paidLater = Math.max(0, parseFloat(s.amount_paid || 0) - parseFloat(s.gift_card_amount || 0));
    const dueLabel = s.credit_due_date
      ? t('Balance due (by {date})', { date: fmtDate(s.credit_due_date + 'T00:00:00') })
      : t('Balance due');
    return `
      <div class="salm-view-row"><span>${t('Paid')}</span><span class="salm-amt-in">${money(paidLater)}</span></div>
      <div class="salm-view-row grand${s.is_overdue ? ' salm-overdue' : ''}"><span>${dueLabel}${s.is_overdue ? ` <span class="salm-badge red">${t('Overdue')}</span>` : ''}</span><span>${money(s.due_amount)}</span></div>`;
  }

  function creditPaymentsHtml(payments) {
    const rows = payments.map((p) => `
      <div class="salm-view-row">
        <span>${fmtDateTime(p.paid_at)}<div class="salm-chq-who">${esc((p.payment_method || '').toUpperCase())}${p.received_by ? ` · ${esc(p.received_by)}` : ''}</div></span>
        <span class="salm-amt-in">${money(p.amount)}</span>
      </div>`).join('');
    return `
      <div class="salm-section-label"><i class="fa-solid fa-hand-holding-dollar"></i> ${t('Credit Payments')}</div>
      <div class="salm-chq-box">${rows || `<div class="salm-view-row"><span>${t('No payments received yet.')}</span></div>`}</div>`;
  }

  // ════════════════════════════════════════════════════════════════════════
  // Receive Credit Payment — shown in the detail popup.
  // opts: { customerId, customerName, due, saleId?, saleNumber?, onDone?, onCancel? }
  // With saleId the payment goes to that one bill; without it the server applies
  // it to the customer's oldest unpaid credit sales first.
  // ════════════════════════════════════════════════════════════════════════
  function openCreditPayForm(opts) {
    if (!opts?.customerId) { showToast(t('This credit sale has no customer to collect from.'), 'error'); return; }
    const due = Number(opts.due) || 0;
    let method = 'cash';

    const bodyHtml = `
      <div class="salm-totals" style="border-top:none;padding-top:0">
        <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(opts.customerName) || '—'}</span></div>
        ${opts.saleNumber ? `<div class="salm-view-row"><span>${t('Bill #')}</span><span>${esc(opts.saleNumber)}</span></div>` : ''}
        <div class="salm-view-row grand"><span>${t(opts.saleId ? 'Due on this sale' : 'Total outstanding')}</span><span>${money(due)}</span></div>
      </div>
      <div class="salm-section-label">${t('Amount')} <span style="color:var(--danger)">*</span></div>
      <div class="salm-pay-amt">
        <input type="text" inputmode="decimal" id="cpf-amount" class="salm-chq-input" placeholder="0.00" autocomplete="off">
        <button type="button" class="salm-btn-ghost" id="cpf-full">${t('Pay full')}</button>
      </div>
      <div class="salm-section-label">${t('Payment method')}</div>
      <div class="salm-pay-methods">
        <button type="button" class="salm-pay-method active" data-method="cash"><i class="fa-solid fa-money-bill-wave"></i> ${t('Cash')}</button>
        <button type="button" class="salm-pay-method" data-method="card"><i class="fa-solid fa-credit-card"></i> ${t('Card')}</button>
      </div>
      <div class="salm-revert-error" id="cpf-error" style="display:none"></div>`;

    const footHtml = `
      <button class="salm-btn-ghost" id="cpf-cancel" type="button">${t('Cancel')}</button>
      <button class="salm-btn-primary" id="cpf-confirm" type="button"><i class="fa-solid fa-check"></i> ${t('Receive Payment')}</button>`;

    openDetail({ title: t('Receive Credit Payment'), bodyHtml, footHtml });

    const amtEl = document.getElementById('cpf-amount');
    const errEl = document.getElementById('cpf-error');
    const btn = document.getElementById('cpf-confirm');
    const fail = (msg) => { errEl.textContent = msg; errEl.style.display = ''; amtEl.focus(); };
    setTimeout(() => amtEl.focus(), 50);

    document.getElementById('cpf-cancel').addEventListener('click', () => (opts.onCancel ? opts.onCancel() : closeDetail()));
    document.getElementById('cpf-full').addEventListener('click', () => { amtEl.value = due.toFixed(2); amtEl.focus(); });
    document.querySelectorAll('.salm-pay-method').forEach((b) => b.addEventListener('click', () => {
      document.querySelectorAll('.salm-pay-method').forEach((x) => x.classList.toggle('active', x === b));
      method = b.dataset.method;
    }));

    const submit = async () => {
      const amount = Number(amtEl.value.trim());
      if (!Number.isFinite(amount) || amount <= 0) { fail(t('Enter a payment amount.')); return; }
      if (amount > due + 0.005) { fail(t('Payment is more than the amount due ({amount}).', { amount: money(due) })); return; }

      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Saving…')}`;
      const res = await API.customerCreditPayment(opts.customerId, {
        amount,
        payment_method: method,
        ...(opts.saleId ? { pos_sale_id: opts.saleId } : {}),
      });
      btn.disabled = false;
      btn.innerHTML = `<i class="fa-solid fa-check"></i> ${t('Receive Payment')}`;

      if (res.status !== 200) {
        fail(t(res.body?.errors?.amount?.[0] || res.body?.message || 'Could not record the payment.'));
        return;
      }
      showToast(t(res.body?.message || 'Payment received'), 'success');
      if (typeof opts.onDone === 'function') opts.onDone(); else closeDetail();
    };

    btn.addEventListener('click', submit);
    amtEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
  }

  // Everything recorded about a cheque: who took it, who claimed it and where the money went
  function chequeDetailsHtml(c) {
    const row = (label, val) => `<div class="salm-view-row"><span>${label}</span><span>${val}</span></div>`;
    const by = (name) => (name ? `<div class="salm-chq-who">${t('by {name}', { name: esc(name) })}</div>` : '');
    const rows = [
      row(t('Status'), chequeBadge(c.status, c.is_due)),
      row(t('Amount'), `<strong>${money(c.amount)}</strong>`),
      c.bank_name ? row(t('Bank'), esc(c.bank_name)) : '',
      row(t('Cheque date'), fmtDate(c.cheque_date ? c.cheque_date + 'T00:00:00' : null)),
      row(t('Received'), `${fmtDateTime(c.created_at)}${by(c.received_by)}`),
    ];
    if (c.status === 'cleared') {
      rows.push(
        row(t('Claimed'), `${fmtDateTime(c.claimed_at)}${by(c.claimed_by)}`),
        row(t('Deposited into'), esc(c.deposit_account?.label) || '—'),
      );
    }
    if (c.bounced_at) rows.push(row(t('Bounced'), `${fmtDateTime(c.bounced_at)}${c.bounce_reason ? `<div class="salm-chq-who">${esc(c.bounce_reason)}</div>` : ''}`));
    if (c.status === 'cancelled') rows.push(row(t('Cancelled'), t('Bill reverted — cheque handed back')));
    if (c.notes) rows.push(row(t('Note'), `<em>${esc(c.notes)}</em>`));

    return `
      <div class="salm-section-label"><i class="fa-solid fa-money-check"></i> ${t('Cheque #{number}', { number: esc(c.cheque_number) })}</div>
      <div class="salm-chq-box">${rows.join('')}</div>`;
  }

  // ════════════════════════════════════════════════════════════════════════
  // Revert Bill — undo a bill entered by mistake. The server voids it,
  // restocks what wasn't already returned, takes the payment back out of the
  // deposit account and keeps the reason (see Reverted Bills below).
  // ════════════════════════════════════════════════════════════════════════
  let revertReasons = null;

  async function loadRevertReasons() {
    if (revertReasons) return revertReasons;
    const res = await API.saleRevertReasons();
    revertReasons = res.status === 200 && res.body?.data ? res.body.data : { other: 'Other' };
    return revertReasons;
  }

  async function openRevertForm(s) {
    const reasons = await loadRevertReasons();

    // Mirrors SaleRevertService so the cashier sees the refund before confirming
    const items = s.items || [];
    const restockQty = items.reduce((n, it) => n + Math.max(0, parseFloat(it.quantity || 0) - parseFloat(it.returned_quantity || 0)), 0);
    const gift = parseFloat(s.gift_card_amount || 0);
    const returnedAmt = items.reduce((n, it) => n + parseFloat(it.returned_quantity || 0) * parseFloat(it.unit_sell_price || 0), 0);
    // An unclaimed cheque is handed back instead of refunding money
    const chqBack = !!s.cheque && ['pending', 'bounced'].includes(s.cheque.status);
    const refundDue = chqBack ? 0 : Math.max(0, Math.max(0, parseFloat(s.amount_paid || 0) - gift) - returnedAmt);
    const qty = (q) => (q % 1 === 0 ? String(q) : q.toFixed(3));

    const bodyHtml = `
      <div class="salm-revert-warn">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>${t('This cancels the whole bill. Stock goes back into inventory, the payment is taken back out of the deposit account, and the bill is removed from sales totals. This can\'t be undone.')}</div>
      </div>
      <div class="salm-totals" style="border-top:none;padding-top:0">
        <div class="salm-view-row"><span>${t('Bill total')}</span><span>${money(s.total)}</span></div>
        <div class="salm-view-row"><span>${t('Items back to stock')}</span><span>${qty(restockQty)}</span></div>
        ${returnedAmt > 0.005 ? `<div class="salm-view-row"><span>${t('Already refunded via Returns')}</span><span>-${money(returnedAmt)}</span></div>` : ''}
        ${gift > 0.005 ? `<div class="salm-view-row"><span>${t('Back to gift card')}</span><span>${money(gift)}</span></div>` : ''}
        ${chqBack ? `<div class="salm-view-row"><span>${t('Hand back cheque #{number} (not claimed)', { number: esc(s.cheque.cheque_number) })}</span><span>${money(s.cheque.amount)}</span></div>` : ''}
        <div class="salm-view-row grand"><span>${t('Give back to customer')}</span><span>${money(refundDue)}</span></div>
      </div>
      <div class="salm-section-label">${t('Why is this bill being reverted?')} <span style="color:var(--danger)">*</span></div>
      <div class="salm-revert-reasons">
        ${Object.entries(reasons).map(([k, v]) => `
          <label class="salm-revert-reason"><input type="radio" name="salm-revert-reason" value="${esc(k)}"> <span>${esc(t(v))}</span></label>`).join('')}
      </div>
      <div class="salm-section-label" id="rv-notes-label">${t('Details (optional)')}</div>
      <textarea id="rv-notes" class="salm-revert-notes" rows="3" maxlength="1000" placeholder="${t('e.g. Cashier scanned the item twice')}"></textarea>
      <div class="salm-revert-error" id="rv-error" style="display:none"></div>`;

    const footHtml = `
      <button class="salm-btn-ghost" id="rv-cancel" type="button">${t('Cancel')}</button>
      <button class="salm-btn-primary salm-btn-danger" id="rv-confirm" type="button"><i class="fa-solid fa-ban"></i> ${t('Revert Bill')}</button>`;

    openDetail({ title: t('Revert {label}', { label: s.sale_number || `#${s.id}` }), bodyHtml, footHtml });

    const errEl = document.getElementById('rv-error');
    const showErr = (msg) => { errEl.textContent = msg; errEl.style.display = ''; };

    detailBody.querySelectorAll('input[name="salm-revert-reason"]').forEach((inp) => inp.addEventListener('change', () => {
      document.getElementById('rv-notes-label').innerHTML = inp.value === 'other'
        ? `${t('Details')} <span style="color:var(--danger)">*</span>`
        : t('Details (optional)');
      errEl.style.display = 'none';
    }));

    document.getElementById('rv-cancel').addEventListener('click', () => openSaleDetail(s.id));
    document.getElementById('rv-confirm').addEventListener('click', async () => {
      const reason = detailBody.querySelector('input[name="salm-revert-reason"]:checked')?.value;
      const notes = document.getElementById('rv-notes').value.trim();
      if (!reason) { showErr(t('Choose why this bill is being reverted.')); return; }
      if (reason === 'other' && !notes) { showErr(t('Describe why this bill is being reverted.')); return; }

      const btn = document.getElementById('rv-confirm');
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Reverting…')}`;
      const res = await API.revertSale(s.id, { reason, notes });
      if (res.status >= 400) {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-ban"></i> ${t('Revert Bill')}`;
        const errs = res.body?.errors ? Object.values(res.body.errors).flat() : [];
        showErr(t(errs[0] || res.body?.message || 'Could not revert this bill.'));
        return;
      }

      const refunded = res.body?.data?.revert?.amount_refunded;
      showToast(t('{label} reverted — give back {amount}.', { label: s.sale_number, amount: money(refunded) }), 'success');
      if (currentSectionKey === 'transactions') loadTransactions();
      if (currentSectionKey === 'history') loadHistory(histPage);
      openSaleDetail(s.id);
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // Reverted Bills — read-only list of reverts with reason and amounts
  // ════════════════════════════════════════════════════════════════════════
  let revPage = 1;

  async function renderReverts() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="rev-search" type="text" placeholder="${t('Search bill #, customer, note…')}"></div>
        <select class="salm-select" id="rev-reason"><option value="all">${t('All reasons')}</option></select>
        <input type="date" class="salm-date-input" id="rev-from" title="${t('From date')}">
        <input type="date" class="salm-date-input" id="rev-to" title="${t('To date')}">
        <button class="salm-icon-btn" id="rev-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <span class="salm-count-pill" id="rev-count"></span>
      </div>
      <div class="salm-kpi-bar" id="rev-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Reverted Bills')}</span><span class="salm-kpi-val" id="rev-kpi-count">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Bill Value Reverted')}</span><span class="salm-kpi-val" id="rev-kpi-total" style="color:var(--danger)">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Refunded to Customers')}</span><span class="salm-kpi-val accent" id="rev-kpi-refunded">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Units Restocked')}</span><span class="salm-kpi-val" id="rev-kpi-units">0</span></div>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Revert #')}</th><th>${t('Bill #')}</th><th>${t('Reverted')}</th><th>${t('Customer')}</th><th>${t('Reason')}</th><th>${t('By')}</th><th style="text-align:right">${t('Bill Total')}</th><th style="text-align:right">${t('Refunded')}</th></tr></thead>
        <tbody id="rev-rows"><tr><td colspan="8" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>
      <div class="salm-pagination" id="rev-pagination"></div>`;

    document.getElementById('rev-search').addEventListener('input', debounce(() => loadReverts(1), 350));
    ['rev-reason', 'rev-from', 'rev-to'].forEach((id) => document.getElementById(id).addEventListener('change', () => loadReverts(1)));
    document.getElementById('rev-refresh').addEventListener('click', () => loadReverts(revPage));

    loadReverts(1);
    const reasons = await loadRevertReasons();
    const sel = document.getElementById('rev-reason');
    if (sel) sel.insertAdjacentHTML('beforeend', Object.entries(reasons).map(([k, v]) => `<option value="${esc(k)}">${esc(t(v))}</option>`).join(''));
  }

  async function loadReverts(page) {
    revPage = page;
    const rowsEl = document.getElementById('rev-rows');
    rowsEl.innerHTML = `<tr><td colspan="8" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();

    const res = await API.saleReverts({
      q: document.getElementById('rev-search').value.trim(),
      reason: document.getElementById('rev-reason').value,
      dateFrom: document.getElementById('rev-from').value,
      dateTo: document.getElementById('rev-to').value,
      page,
    });

    if (res.status !== 200) {
      rowsEl.innerHTML = `<tr><td colspan="8" class="salm-empty">${t('Could not load reverted bills ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      document.getElementById('rev-kpi').style.display = 'none';
      document.getElementById('rev-count').textContent = '';
      document.getElementById('rev-pagination').innerHTML = '';
      return;
    }

    const list = res.body.data || [];
    const summary = res.body.summary || {};
    const kpi = document.getElementById('rev-kpi');
    if (summary.count) {
      kpi.style.display = '';
      document.getElementById('rev-kpi-count').textContent = summary.count;
      document.getElementById('rev-kpi-total').textContent = money(summary.sale_total);
      document.getElementById('rev-kpi-refunded').textContent = money(summary.amount_refunded);
      document.getElementById('rev-kpi-units').textContent = parseFloat(summary.stock_restored_qty || 0);
    } else {
      kpi.style.display = 'none';
    }

    const total = res.body.meta?.total || 0;
    document.getElementById('rev-count').textContent = total
      ? t(total === 1 ? '{n} reverted bill' : '{n} reverted bills', { n: total })
      : t('No reverted bills');

    if (!list.length) {
      rowsEl.innerHTML = `<tr><td colspan="8" class="salm-empty">${t('No reverted bills found.')}</td></tr>`;
    } else {
      rowsEl.innerHTML = list.map((r) => `
        <tr data-id="${r.id}">
          <td class="salm-ref"><i class="fa-solid fa-ban"></i>${esc(r.revert_number)}</td>
          <td class="salm-muted-cell">${esc(r.sale_number) || '—'}</td>
          <td class="salm-muted-cell">${fmtDateTime(r.reverted_at)}</td>
          <td class="salm-muted-cell">${esc(r.customer_name) || '—'}</td>
          <td><span class="salm-badge red">${esc(t(r.reason_label))}</span>${r.notes ? `<div class="salm-revert-row-note" title="${esc(r.notes)}">${esc(r.notes)}</div>` : ''}</td>
          <td class="salm-muted-cell">${esc(r.reverted_by?.name) || '—'}</td>
          <td class="salm-amt-cell">${money(r.sale_total)}</td>
          <td class="salm-amt-cell">${money(r.amount_refunded)}</td>
        </tr>`).join('');
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openRevertDetail(Number(tr.dataset.id))));
    }

    renderPagination(document.getElementById('rev-pagination'), res.body.meta, loadReverts);
  }

  async function openRevertDetail(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.saleRevert(id);
    if (res.status !== 200) {
      openDetail({ title: t('Reverted Bill'), bodyHtml: `<div class="salm-empty">${t('Could not load reverted bill ({reason}).', { reason: t(res.body?.message) || res.status })}</div>` });
      return;
    }
    const r = res.body.data;
    const qty = (v) => { const q = parseFloat(v || 0); return q % 1 === 0 ? String(q) : q.toFixed(3); };

    const itemsHtml = (r.items || []).map((it) => {
      const returned = parseFloat(it.returned_quantity || 0);
      const restocked = parseFloat(it.restocked_quantity || 0);
      return `
      <div class="salm-item-row">
        <div>
          <div class="salm-item-name">${esc(it.product_name)}${it.type === 'service' ? ` <span class="salm-muted-inline">(${t('service')})</span>` : ''}</div>
          <div class="salm-item-meta">${qty(it.quantity)} × ${money(it.unit_sell_price)}${returned ? ` · ${t('returned before')} ${qty(returned)}` : ''} · ${t('restocked')} ${restocked ? qty(restocked) : '—'}</div>
        </div>
        <div class="salm-item-total">${money(it.line_total)}</div>
      </div>`;
    }).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No items.')}</span></div>`;

    const bodyHtml = `
      <div class="salm-revert-notice">
        <div><i class="fa-solid fa-ban"></i> <strong>${esc(t(r.reason_label))}</strong></div>
        ${r.notes ? `<div class="salm-revert-note">“${esc(r.notes)}”</div>` : ''}
      </div>
      <div class="salm-view-row"><span>${t('Bill #')}</span><span>${esc(r.sale_number) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Bill date')}</span><span>${fmtDateTime(r.sale_sold_at)}</span></div>
      <div class="salm-view-row"><span>${t('Reverted')}</span><span>${fmtDateTime(r.reverted_at)}</span></div>
      <div class="salm-view-row"><span>${t('Reverted by')}</span><span>${esc(r.reverted_by?.name) || '—'}</span></div>
      <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(r.customer_name) || t('Walk-in')}</span></div>
      <div class="salm-view-row"><span>${t('Payment method')}</span><span>${esc(r.payment_method) || '—'}</span></div>
      <div class="salm-section-label">${t('Items')}</div>
      <div class="salm-item-list">${itemsHtml}</div>
      <div class="salm-totals">
        <div class="salm-view-row"><span>${t('Bill total')}</span><span>${money(r.sale_total)}</span></div>
        <div class="salm-view-row"><span>${t('Amount paid')}</span><span>${money(r.amount_paid)}</span></div>
        ${parseFloat(r.gift_card_refunded) > 0 ? `<div class="salm-view-row"><span>${t('Returned to gift card')}</span><span>${money(r.gift_card_refunded)}</span></div>` : ''}
        ${parseFloat(r.returned_total) > 0 ? `<div class="salm-view-row"><span>${t('Refunded earlier via Returns')}</span><span>${money(r.returned_total)}</span></div>` : ''}
        <div class="salm-view-row"><span>${t('Taken back from deposit account')}</span><span>${money(r.account_reversed)}</span></div>
        <div class="salm-view-row"><span>${t('Units restocked')}</span><span>${qty(r.stock_restored_qty)}</span></div>
        <div class="salm-view-row grand"><span>${t('Refunded to customer')}</span><span>${money(r.amount_refunded)}</span></div>
      </div>`;

    openDetail({ title: `${r.revert_number} · ${r.sale_number || ''}`, bodyHtml });
  }

  // ════════════════════════════════════════════════════════════════════════
  // Cheques — customer cheques taken at checkout. A cheque stays pending
  // until it is claimed (deposited — the money goes into an account) or
  // marked bounced. Pending cheques whose date has arrived are "due to claim".
  // ════════════════════════════════════════════════════════════════════════
  let chqPage = 1;
  let chqList = [];
  let chqAccounts = null;

  function renderCheques() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="chq-search" type="text" placeholder="${t('Search cheque #, bank, bill #, customer…')}"></div>
        <select class="salm-select" id="chq-status">
          <option value="all">${t('All cheques')}</option>
          <option value="due">${t('Due to claim')}</option>
          <option value="pending">${t('Pending')}</option>
          <option value="cleared">${t('Claimed')}</option>
          <option value="bounced">${t('Bounced')}</option>
          <option value="cancelled">${t('Cancelled')}</option>
        </select>
        <input type="date" class="salm-date-input" id="chq-from" title="${t('Cheque date from')}">
        <input type="date" class="salm-date-input" id="chq-to" title="${t('Cheque date to')}">
        <button class="salm-icon-btn" id="chq-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <span class="salm-count-pill" id="chq-count"></span>
      </div>
      <div class="salm-kpi-bar" id="chq-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label" id="chq-kpi-due-label">${t('Due to Claim')}</span><span class="salm-kpi-val accent" id="chq-kpi-due">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label" id="chq-kpi-pending-label">${t('All Pending')}</span><span class="salm-kpi-val" id="chq-kpi-pending">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label" id="chq-kpi-cleared-label">${t('Claimed')}</span><span class="salm-kpi-val" id="chq-kpi-cleared" style="color:var(--success, #16a34a)">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label" id="chq-kpi-bounced-label">${t('Bounced')}</span><span class="salm-kpi-val" id="chq-kpi-bounced" style="color:var(--danger)">0.00</span></div>
      </div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Cheque #')}</th><th>${t('Cheque Date')}</th><th>${t('Customer')}</th><th>${t('Bill #')}</th><th>${t('Status')}</th><th style="text-align:right">${t('Amount')}</th><th></th></tr></thead>
        <tbody id="chq-rows"><tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>
      <div class="salm-pagination" id="chq-pagination"></div>`;

    document.getElementById('chq-search').addEventListener('input', debounce(() => loadCheques(1), 350));
    ['chq-status', 'chq-from', 'chq-to'].forEach((id) => document.getElementById(id).addEventListener('change', () => loadCheques(1)));
    document.getElementById('chq-refresh').addEventListener('click', () => loadCheques(chqPage));
    loadCheques(1);
  }

  async function loadCheques(page) {
    chqPage = page;
    const rowsEl = document.getElementById('chq-rows');
    if (!rowsEl) return; // section closed meanwhile
    rowsEl.innerHTML = `<tr><td colspan="7" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();

    const res = await API.saleCheques({
      q: document.getElementById('chq-search').value.trim(),
      status: document.getElementById('chq-status').value,
      dateFrom: document.getElementById('chq-from').value,
      dateTo: document.getElementById('chq-to').value,
      page,
    });

    if (res.status !== 200) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('Could not load cheques ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
      document.getElementById('chq-kpi').style.display = 'none';
      document.getElementById('chq-count').textContent = '';
      document.getElementById('chq-pagination').innerHTML = '';
      return;
    }

    chqList = res.body.data || [];
    const sum = res.body.summary || {};
    const kpi = document.getElementById('chq-kpi');
    if (sum.pending_count || sum.cleared_count || sum.bounced_count) {
      kpi.style.display = '';
      document.getElementById('chq-kpi-due-label').textContent = `${t('Due to Claim')} (${sum.due_count || 0})`;
      document.getElementById('chq-kpi-due').textContent = money(sum.due_total);
      document.getElementById('chq-kpi-pending-label').textContent = `${t('All Pending')} (${sum.pending_count || 0})`;
      document.getElementById('chq-kpi-pending').textContent = money(sum.pending_total);
      document.getElementById('chq-kpi-cleared-label').textContent = `${t('Claimed')} (${sum.cleared_count || 0})`;
      document.getElementById('chq-kpi-cleared').textContent = money(sum.cleared_total);
      document.getElementById('chq-kpi-bounced-label').textContent = `${t('Bounced')} (${sum.bounced_count || 0})`;
      document.getElementById('chq-kpi-bounced').textContent = money(sum.bounced_total);
    } else {
      kpi.style.display = 'none';
    }

    const total = res.body.meta?.total || 0;
    document.getElementById('chq-count').textContent = total
      ? t(total === 1 ? '{n} cheque' : '{n} cheques', { n: total })
      : t('No cheques');

    if (!chqList.length) {
      rowsEl.innerHTML = `<tr><td colspan="7" class="salm-empty">${t('No cheques found. Cheques taken at checkout show up here.')}</td></tr>`;
    } else {
      rowsEl.innerHTML = chqList.map((c) => {
        let sub = '';
        if (c.status === 'cleared') sub = `${t('Claimed')} ${fmtDateTime(c.claimed_at)}${c.claimed_by ? ' · ' + t('by {name}', { name: esc(c.claimed_by) }) : ''}${c.deposit_account ? ' → ' + esc(c.deposit_account.label) : ''}`;
        else if (c.status === 'bounced') sub = `${t('Bounced')} ${fmtDateTime(c.bounced_at)}${c.bounce_reason ? ' — ' + esc(c.bounce_reason) : ''}`;
        else if (c.status === 'cancelled') sub = t('Bill reverted — hand the cheque back');
        else if (c.received_by) sub = t('Received by {name}', { name: esc(c.received_by) });
        return `
        <tr data-id="${c.id}">
          <td class="salm-ref"><i class="fa-solid fa-money-check"></i>${esc(c.cheque_number)}${c.bank_name ? `<div class="salm-chq-sub">${esc(c.bank_name)}</div>` : ''}</td>
          <td class="salm-muted-cell">${fmtDate(c.cheque_date ? c.cheque_date + 'T00:00:00' : null)}</td>
          <td class="salm-muted-cell">${esc(c.customer?.name) || '—'}${c.customer?.phone ? `<div class="salm-chq-sub">${esc(c.customer.phone)}</div>` : ''}</td>
          <td class="salm-muted-cell">${esc(c.sale_number) || '—'}</td>
          <td>${chequeBadge(c.status, c.is_due)}${sub ? `<div class="salm-chq-sub">${sub}</div>` : ''}</td>
          <td class="salm-amt-cell">${money(c.amount)}</td>
          <td>${c.is_claimable ? `<div class="salm-chq-actions">
            <button type="button" class="salm-chq-btn claim" data-claim="${c.id}"><i class="fa-solid fa-building-columns"></i> ${c.status === 'bounced' ? t('Claim again') : t('Claim')}</button>
            ${c.status === 'pending' ? `<button type="button" class="salm-chq-btn bounce" data-bounce="${c.id}" title="${t('Bank returned it unpaid')}"><i class="fa-solid fa-ban"></i></button>` : ''}
          </div>` : ''}</td>
        </tr>`;
      }).join('');

      rowsEl.querySelectorAll('[data-claim]').forEach((b) => b.addEventListener('click', (e) => {
        e.stopPropagation();
        const c = chqList.find((x) => x.id === Number(b.dataset.claim));
        if (c) openChequeForm(c, 'claim', () => { closeDetail(); loadCheques(chqPage); });
      }));
      rowsEl.querySelectorAll('[data-bounce]').forEach((b) => b.addEventListener('click', (e) => {
        e.stopPropagation();
        const c = chqList.find((x) => x.id === Number(b.dataset.bounce));
        if (c) openChequeForm(c, 'bounce', () => { closeDetail(); loadCheques(chqPage); });
      }));
      // Click a row to open its bill
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => {
        const c = chqList.find((x) => x.id === Number(tr.dataset.id));
        if (c?.sale_id) openSaleDetail(c.sale_id);
      }));
    }

    renderPagination(document.getElementById('chq-pagination'), res.body.meta, loadCheques);
  }

  // Claim (deposit into an account) or mark a cheque bounced, in the detail popup
  async function openChequeForm(c, mode, onDone) {
    const isClaim = mode === 'claim';
    const summary = `
      <div class="salm-totals" style="border-top:none;padding-top:0">
        <div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(c.customer?.name) || '—'}</span></div>
        <div class="salm-view-row"><span>${t('Bill #')}</span><span>${esc(c.sale_number) || '—'}</span></div>
        ${c.bank_name ? `<div class="salm-view-row"><span>${t('Bank')}</span><span>${esc(c.bank_name)}</span></div>` : ''}
        <div class="salm-view-row"><span>${t('Cheque date')}</span><span>${fmtDate(c.cheque_date ? c.cheque_date + 'T00:00:00' : null)}</span></div>
        <div class="salm-view-row grand"><span>${t('Amount')}</span><span>${money(c.amount)}</span></div>
      </div>`;

    const postDated = isClaim && c.status === 'pending' && c.cheque_date && !c.is_due;
    const bodyHtml = isClaim ? `
      ${summary}
      ${postDated ? `<div class="salm-revert-warn"><i class="fa-solid fa-triangle-exclamation"></i><div>${t('This cheque is dated {date} — the bank won\'t pay it before then. Claim it only if it has already cleared.', { date: fmtDate(c.cheque_date + 'T00:00:00') })}</div></div>` : ''}
      <div class="salm-section-label">${t('Deposit into account')} <span style="color:var(--danger)">*</span></div>
      <select id="chqf-account" class="salm-chq-input"><option value="">${t('Loading accounts…')}</option></select>
      <div class="salm-section-label">${t('Note (optional)')}</div>
      <textarea id="chqf-notes" class="salm-revert-notes" rows="2" maxlength="1000" placeholder="${t('e.g. Deposited at BOC Kandy branch')}"></textarea>
      <div class="salm-revert-error" id="chqf-error" style="display:none"></div>` : `
      ${summary}
      <div class="salm-revert-warn"><i class="fa-solid fa-triangle-exclamation"></i><div>${t('Mark this cheque as returned unpaid by the bank. You can still claim it later if the customer\'s bank pays it on re-presentation.')}</div></div>
      <div class="salm-section-label">${t('Why did it bounce?')} <span style="color:var(--danger)">*</span></div>
      <input type="text" id="chqf-reason" class="salm-chq-input" maxlength="255" placeholder="${t('e.g. Insufficient funds')}">
      <div class="salm-revert-error" id="chqf-error" style="display:none"></div>`;

    const footHtml = `
      <button class="salm-btn-ghost" id="chqf-cancel" type="button">${t('Cancel')}</button>
      <button class="salm-btn-primary${isClaim ? '' : ' salm-btn-danger'}" id="chqf-confirm" type="button">
        <i class="fa-solid ${isClaim ? 'fa-building-columns' : 'fa-ban'}"></i> ${isClaim ? t('Claim Cheque') : t('Mark Bounced')}
      </button>`;

    openDetail({ title: t(isClaim ? 'Claim Cheque #{number}' : 'Bounced Cheque #{number}', { number: c.cheque_number }), bodyHtml, footHtml });

    const errEl = document.getElementById('chqf-error');
    const showErr = (msg) => { errEl.textContent = msg; errEl.style.display = ''; };
    document.getElementById('chqf-cancel').addEventListener('click', () => (c.sale_id ? openSaleDetail(c.sale_id) : closeDetail()));

    if (isClaim) {
      await loadSettings();
      if (!chqAccounts) {
        const aRes = await API.accounts();
        chqAccounts = aRes.status === 200 ? (aRes.body?.data || []) : [];
      }
      const sel = document.getElementById('chqf-account');
      if (sel) {
        sel.innerHTML = chqAccounts.length
          ? chqAccounts.map((a) => `<option value="${a.id}"${String(a.id) === String(posSettings.default_deposit_account_id) ? ' selected' : ''}>${esc(a.account_name || a.name)}${a.bank_name ? ' · ' + esc(a.bank_name) : ''}</option>`).join('')
          : `<option value="">${t('No accounts — add one in Finance first')}</option>`;
      }
    } else {
      document.getElementById('chqf-reason').focus();
    }

    document.getElementById('chqf-confirm').addEventListener('click', async () => {
      const btn = document.getElementById('chqf-confirm');
      let res;
      if (isClaim) {
        const accountId = parseInt(document.getElementById('chqf-account').value, 10) || null;
        if (!accountId) { showErr(t('Choose the account this cheque is deposited into.')); return; }
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Claiming…')}`;
        res = await API.claimSaleCheque(c.id, { deposit_account_id: accountId, notes: document.getElementById('chqf-notes').value.trim() || undefined });
      } else {
        const reason = document.getElementById('chqf-reason').value.trim();
        if (!reason) { showErr(t('Say why the cheque bounced.')); return; }
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Saving…')}`;
        res = await API.bounceSaleCheque(c.id, { reason });
      }

      if (res.status >= 400) {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid ${isClaim ? 'fa-building-columns' : 'fa-ban'}"></i> ${isClaim ? t('Claim Cheque') : t('Mark Bounced')}`;
        const errs = res.body?.errors ? Object.values(res.body.errors).flat() : [];
        showErr(t(errs[0] || res.body?.message || 'Could not update this cheque.'));
        return;
      }

      showToast(isClaim
        ? t('Cheque {number} claimed.', { number: c.cheque_number })
        : t('Cheque {number} marked as bounced.', { number: c.cheque_number }), 'success');
      if (currentSectionKey === 'transactions') loadTransactions();
      if (currentSectionKey === 'history') loadHistory(histPage);
      if (currentSectionKey === 'cheques' && c.sale_id && document.getElementById('chq-rows')) loadCheques(chqPage);
      if (typeof onDone === 'function') onDone();
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // Pending Credits — unpaid credit sales grouped by customer. Pay a whole
  // customer's balance (oldest bills first) or one bill at a time.
  // ════════════════════════════════════════════════════════════════════════
  let pcAll = [];

  function renderCredits() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="pc-search" type="text" placeholder="${t('Search customer or sale number…')}"></div>
        <button class="salm-icon-btn" id="pc-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-kpi-bar" id="pc-kpi" style="display:none"></div>
      <div id="pc-body"><div class="salm-loading">${t('Loading…')}</div></div>`;

    document.getElementById('pc-search').addEventListener('input', debounce(renderCreditGroups, 200));
    document.getElementById('pc-refresh').addEventListener('click', () => {
      document.getElementById('pc-search').value = '';
      loadCredits();
    });
    loadCredits();
  }

  async function loadCredits() {
    const body = document.getElementById('pc-body');
    if (!body) return;
    body.innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    document.getElementById('pc-kpi').style.display = 'none';
    await loadSettings();
    const res = await API.pendingCredits();
    if (!document.getElementById('pc-body')) return; // section changed meanwhile
    if (res.status !== 200) {
      body.innerHTML = `<div class="salm-empty">${t('Could not load pending credits ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
      return;
    }
    pcAll = res.body.data || [];
    renderCreditGroups();
  }

  function renderCreditGroups() {
    const body = document.getElementById('pc-body');
    const kpi = document.getElementById('pc-kpi');
    if (!body) return;
    const q = document.getElementById('pc-search').value.trim().toLowerCase();
    const groups = pcAll.filter((g) => !q
      || (g.customer_name || '').toLowerCase().includes(q)
      || (g.customer_phone || '').toLowerCase().includes(q)
      || g.sales.some((s) => (s.sale_number || '').toLowerCase().includes(q)));

    const totalOwed = groups.reduce((n, g) => n + Number(g.total_owed || 0), 0);
    const totalOverdue = groups.reduce((n, g) => n + Number(g.overdue_amount || 0), 0);
    kpi.style.display = '';
    kpi.innerHTML = `
      <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Customers')}</span><span class="salm-kpi-val">${groups.length}</span></div>
      <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Total Outstanding')}</span><span class="salm-kpi-val accent">${money(totalOwed)}</span></div>
      <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Overdue')}</span><span class="salm-kpi-val salm-overdue-val">${money(totalOverdue)}</span></div>
      <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Overdue Customers')}</span><span class="salm-kpi-val salm-overdue-val">${groups.filter((g) => g.has_overdue).length}</span></div>`;

    if (!groups.length) {
      body.innerHTML = `<div class="salm-empty"><i class="fa-solid fa-circle-check" style="color:var(--success, #16a34a)"></i> ${
        q ? t('No results for "{q}"', { q: esc(q) }) : t('No pending credit sales — all clear!')}</div>`;
      return;
    }

    body.innerHTML = groups.map((g, gi) => {
      const rows = g.sales.map((s) => {
        const paid = Number(s.amount_paid) || 0;
        return `
        <tr${s.is_overdue ? ' class="salm-row-overdue"' : ''}>
          <td class="salm-ref">${esc(s.sale_number || `#${s.id}`)}</td>
          <td class="salm-muted-cell">${fmtDate(s.sold_at)}</td>
          <td class="salm-muted-cell">${s.credit_due_date ? fmtDate(s.credit_due_date + 'T00:00:00') : '—'}${s.is_overdue ? ` <span class="salm-badge red">${t('Overdue')}</span>` : ''}</td>
          <td class="salm-amt-cell">${money(s.total)}</td>
          <td class="salm-amt-cell salm-amt-in">${paid > 0 ? money(paid) : '—'}</td>
          <td class="salm-amt-cell"><strong>${money(s.due_amount)}</strong></td>
          <td style="text-align:right">${g.customer_id ? `<button type="button" class="salm-pay-btn" data-pc-sale="${gi}:${s.id}"><i class="fa-solid fa-hand-holding-dollar"></i> ${t('Pay')}</button>` : ''}</td>
        </tr>`;
      }).join('');

      return `
      <div class="salm-pc-card${g.has_overdue ? ' has-overdue' : ''}">
        <div class="salm-pc-head" data-pc-toggle>
          <div class="salm-pc-avatar">${esc((g.customer_name || '?')[0].toUpperCase())}</div>
          <div class="salm-pc-info">
            <div class="salm-pc-name">${esc(g.customer_name)}</div>
            <div class="salm-pc-meta">${g.customer_phone ? `${esc(g.customer_phone)} · ` : ''}${t(g.sale_count === 1 ? '{n} sale' : '{n} sales', { n: g.sale_count })}</div>
          </div>
          <div class="salm-pc-right">
            <div class="salm-pc-total">${money(g.total_owed)}</div>
            ${g.has_overdue ? `<div class="salm-pc-overdue"><i class="fa-solid fa-triangle-exclamation"></i> ${t('Overdue {amount}', { amount: money(g.overdue_amount) })}</div>` : ''}
          </div>
          ${g.customer_id ? `<button type="button" class="salm-pay-btn" data-pc-cust="${gi}" title="${t('Applied to the oldest bills first')}"><i class="fa-solid fa-hand-holding-dollar"></i> ${t('Pay')}</button>` : ''}
          <i class="fa-solid fa-chevron-down salm-pc-chevron"></i>
        </div>
        <div class="salm-pc-sales">
          <table class="salm-table">
            <thead><tr><th>${t('Sale #')}</th><th>${t('Date')}</th><th>${t('Due Date')}</th><th style="text-align:right">${t('Total')}</th><th style="text-align:right">${t('Paid')}</th><th style="text-align:right">${t('Due')}</th><th></th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>`;
    }).join('');

    body.querySelectorAll('[data-pc-toggle]').forEach((head) => head.addEventListener('click', () => head.parentElement.classList.toggle('expanded')));
    body.querySelectorAll('[data-pc-cust]').forEach((btn) => btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const g = groups[Number(btn.dataset.pcCust)];
      if (g) openCreditPayForm({ customerId: g.customer_id, customerName: g.customer_name, due: g.total_owed, onDone: () => { closeDetail(); loadCredits(); } });
    }));
    body.querySelectorAll('[data-pc-sale]').forEach((btn) => btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const [gi, saleId] = btn.dataset.pcSale.split(':').map(Number);
      const g = groups[gi];
      const s = g?.sales.find((x) => x.id === saleId);
      if (s) {
        openCreditPayForm({
          customerId: g.customer_id, customerName: g.customer_name,
          saleId: s.id, saleNumber: s.sale_number, due: s.due_amount,
          onDone: () => { closeDetail(); loadCredits(); },
        });
      }
    }));
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
          <option value="void">${t('Reverted')}</option>
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
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Reverted')}</span><span class="salm-kpi-val" id="hist-kpi-void">0</span></div>
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
          <td class="salm-muted-cell">${paymentCell(s)}</td>
          <td><span class="salm-badge ${badgeClass(s.status)}">${s.status === 'void' ? t('Reverted') : t('Completed')}</span></td>
          <td class="salm-amt-cell">${money(s.total)}</td>
        </tr>`).join('');
      rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', () => openSaleDetail(Number(tr.dataset.id))));
      wirePayButtons(rowsEl, list, () => { closeDetail(); loadHistory(histPage); });
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
