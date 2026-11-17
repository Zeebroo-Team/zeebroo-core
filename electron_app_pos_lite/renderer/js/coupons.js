'use strict';

// Coupons — an in-page modal dialog, opened by calling window.openCouponsModal()
// (see the "Coupons" tile in js/stock.js). Same .salm-modal-* shell and
// .salm-* / .qf-* building blocks as js/giftcards.js, plus a few .cpm-* rules
// in css/coupons.css. Talks to the Laravel POS API (Modules/Pos/routes/api.php,
// prefix /api/v1/pos) — the same /coupons endpoints the full desktop app uses.
//
// A coupon is one discount offer (e.g. "Avurudu 10% OFF") with ONE shared
// code. "Number of coupons" is how many times that code can be used; each sale
// that uses it takes one use, and voiding the sale hands it back.
//
// Views swapped inside the dialog body:
//   list — every coupon with its code, discount and uses left
//   form — new coupon / edit coupon
// A single coupon's detail (usage history) is a popup layered on top.
(function () {
  let bodyEl, detailView, detailTitleEl, detailBody, detailFoot, toastEl;

  let posSettings = {};
  let settingsLoaded = false;

  const state = { q: '', status: '', list: [] };

  const STATUS = {
    active:    { label: 'Active',    badge: 'green' },
    scheduled: { label: 'Scheduled', badge: 'blue' },
    expired:   { label: 'Expired',   badge: 'amber' },
    used:      { label: 'Used up',   badge: 'gray' },
    disabled:  { label: 'Disabled',  badge: 'red' },
  };

  // ── Small shared helpers (same as js/giftcards.js) ──────────────────────
  function esc(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function money(n) {
    const amount = (Number(n) || 0).toFixed(2);
    const currency = (posSettings.currency || 'LKR').toUpperCase();
    return posSettings.currency_position === 'before' ? `${currency} ${amount}` : `${amount} ${currency}`;
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

  function firstError(res, fallback) {
    const errors = res.body?.errors;
    const first = errors ? Object.values(errors)[0]?.[0] : null;
    return t(first || res.body?.message || fallback);
  }

  async function loadSettings() {
    if (settingsLoaded) return;
    const res = await API.settingsGet();
    if (res.status === 200) posSettings = res.body?.data || {};
    settingsLoaded = true;
  }

  async function copyText(text, message) {
    try { await navigator.clipboard.writeText(text); showToast(message, 'success'); }
    catch (_) { showToast(t('Could not copy.'), 'error'); }
  }

  function statusBadge(status) {
    const s = STATUS[status] || STATUS.disabled;
    return `<span class="salm-badge ${s.badge}">${t(s.label)}</span>`;
  }

  function validityText(x) {
    if (x.valid_from && x.expires_at) return `${x.valid_from} → ${x.expires_at}`;
    if (x.expires_at) return t('Until {date}', { date: x.expires_at });
    if (x.valid_from) return t('From {date} · no expiry', { date: x.valid_from });
    return t('No expiry');
  }

  function offText(c) {
    return c.discount_type === 'percent'
      ? t('{pct}% OFF', { pct: Number(c.discount_value) })
      : t('{amount} OFF', { amount: money(c.discount_value) });
  }

  // ════════════════════════════════════════════════════════════════════════
  // List view
  // ════════════════════════════════════════════════════════════════════════
  function renderList() {
    closeDetail();
    bodyEl.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="cpm-search" type="text" placeholder="${t('Search by name or code…')}" value="${esc(state.q)}"></div>
        <select class="salm-select" id="cpm-status">
          <option value="">${t('All coupons')}</option>
          ${Object.entries(STATUS).filter(([k]) => k !== 'scheduled').map(([k, s]) => `<option value="${k}" ${state.status === k ? 'selected' : ''}>${t(s.label)}</option>`).join('')}
        </select>
        <button class="salm-icon-btn" id="cpm-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <span class="salm-count-pill" id="cpm-count"></span>
        <button class="salm-btn-primary" id="cpm-new" type="button"><i class="fa-solid fa-plus"></i> ${t('New Coupon')}</button>
      </div>
      <div id="cpm-list"><div class="salm-loading">${t('Loading coupons…')}</div></div>`;

    document.getElementById('cpm-search').addEventListener('input', debounce((e) => { state.q = e.target.value.trim(); loadList(); }, 300));
    document.getElementById('cpm-status').addEventListener('change', (e) => { state.status = e.target.value; loadList(); });
    document.getElementById('cpm-refresh').addEventListener('click', loadList);
    document.getElementById('cpm-new').addEventListener('click', () => renderForm());
    loadList();
  }

  async function loadList() {
    const wrap = document.getElementById('cpm-list');
    if (!wrap) return;
    await loadSettings();
    const res = await API.coupons(state.q, state.status);
    if (!document.getElementById('cpm-list')) return; // view changed while loading
    if (res.status !== 200) {
      wrap.innerHTML = `<div class="salm-empty">${t('Could not load coupons ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
      return;
    }
    state.list = res.body.data || [];
    paintList();
  }

  function paintList() {
    const wrap = document.getElementById('cpm-list');
    document.getElementById('cpm-count').textContent = state.list.length ? t('{n} coupons', { n: state.list.length }) : '';

    if (!state.list.length) {
      wrap.innerHTML = `
        <div class="gcm-empty cpm-empty">
          <i class="fa-solid fa-ticket"></i>
          <div>${state.q || state.status ? t('No coupons match your search.') : t('No coupons yet.')}</div>
          ${state.q || state.status ? '' : `<p>${t('Create a coupon like "Avurudu 10% OFF" with one code, and choose how many times it can be used.')}</p>`}
        </div>`;
      return;
    }

    wrap.innerHTML = `
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Coupon')}</th><th>${t('Code')}</th><th>${t('Discount')}</th><th>${t('Uses left')}</th><th>${t('Valid')}</th><th>${t('Status')}</th><th></th></tr></thead>
        <tbody>${state.list.map((c) => `
          <tr data-id="${c.id}">
            <td><strong>${esc(c.name)}</strong></td>
            <td><span class="gcm-code">${esc(c.code)}</span></td>
            <td><span class="cpm-off">${esc(offText(c))}</span></td>
            <td>${c.remaining} <small class="gcm-muted">/ ${c.quantity}</small></td>
            <td class="salm-muted-cell">${esc(validityText(c))}</td>
            <td>${statusBadge(c.status)}</td>
            <td><div class="salm-row-actions"><button data-copy="${esc(c.code)}" title="${t('Copy code')}"><i class="fa-solid fa-copy"></i></button></div></td>
          </tr>`).join('')}
        </tbody>
      </table></div>`;

    wrap.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', (e) => {
      const copyBtn = e.target.closest('[data-copy]');
      if (copyBtn) { copyText(copyBtn.dataset.copy, t('Code copied')); return; }
      openCoupon(Number(tr.dataset.id));
    }));
  }

  // ════════════════════════════════════════════════════════════════════════
  // Form — new coupon or edit an existing one
  // ════════════════════════════════════════════════════════════════════════
  async function renderForm(record = null) {
    closeDetail();
    await loadSettings();
    const today = new Date().toISOString().slice(0, 10);
    const title = record ? t('Edit Coupon {code}', { code: record.code }) : t('New Coupon');
    const noExpiry = record ? !record.expires_at : false;
    let type = record?.discount_type || 'percent';

    bodyEl.innerHTML = `
      <div class="inv-detail-header qf-form-header">
        <button class="inv-back-btn" id="cpf-back"><i class="fa-solid fa-arrow-left"></i> ${t('Back')}</button>
        <span class="inv-detail-breadcrumb">${esc(title)}</span>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-ticket"></i> ${t('Coupon Details')}</div>
        <div class="qf-grid">
          <label class="qf-field gcf-span2"><span>${t('Coupon name')} *</span>
            <input type="text" id="cpf-name" maxlength="191" placeholder="${t('e.g. Avurudu 10% OFF')}" value="${esc(record?.name || '')}">
          </label>
          <div class="qf-field"><span>${t('Discount type')} *</span>
            <div class="cpf-type">
              <button type="button" data-type="percent">${t('Percentage %')}</button>
              <button type="button" data-type="flat">${t('Flat amount')}</button>
            </div>
          </div>
          <label class="qf-field"><span id="cpf-value-label">${t('Discount')} *</span>
            <input type="number" id="cpf-value" min="0.01" step="0.01" value="${record?.discount_value ?? ''}">
          </label>
          <label class="qf-field gcf-span2"><span>${t('Coupon code')} *</span>
            <div class="gcf-code-row">
              <input type="text" id="cpf-code" maxlength="40" class="gcm-code-input" placeholder="${t('Auto-generated — or type your own')}" value="${esc(record?.code || '')}">
              <button type="button" class="salm-icon-btn" id="cpf-generate"><i class="fa-solid fa-wand-magic-sparkles"></i> ${t('Generate')}</button>
            </div>
          </label>
          <label class="qf-field gcf-span2"><span>${t('Number of coupons')} *</span>
            <input type="number" id="cpf-qty" min="${record ? Math.max(1, record.used_count) : 1}" max="100000" step="1" value="${record?.quantity ?? 100}">
          </label>
          <label class="qf-field"><span>${t('Valid from')}</span>
            <input type="date" id="cpf-from" value="${esc(record?.valid_from || (record ? '' : today))}">
          </label>
          <label class="qf-field" id="cpf-expires-wrap"><span>${t('Valid until')}</span>
            <input type="date" id="cpf-expires" value="${esc(record?.expires_at || '')}">
          </label>
          <label class="gcf-check gcf-span2"><input type="checkbox" id="cpf-no-expiry" ${noExpiry ? 'checked' : ''}> ${t('No expiry date')}</label>
          <label class="qf-field gcf-span2"><span>${t('Notes')}</span>
            <textarea id="cpf-notes" rows="2" placeholder="${t('Where is this coupon promoted? (optional)')}">${esc(record?.notes || '')}</textarea>
          </label>
          <label class="gcf-check gcf-span2"><input type="checkbox" id="cpf-active" ${!record || record.is_active ? 'checked' : ''}> ${t('Active')}</label>
        </div>
        <div class="gcf-hint cpf-hint" id="cpf-hint"></div>
      </div>

      <div class="qf-actions">
        <button class="salm-btn-ghost" id="cpf-cancel" type="button">${t('Cancel')}</button>
        <button class="salm-btn-primary" id="cpf-save" type="button"><i class="fa-solid fa-check"></i> ${t('Save Coupon')}</button>
      </div>`;

    const back = () => { renderList(); if (record) openCoupon(record.id); };
    document.getElementById('cpf-back').addEventListener('click', back);
    document.getElementById('cpf-cancel').addEventListener('click', back);

    const hintEl = document.getElementById('cpf-hint');
    const syncHint = () => {
      const qty = parseInt(document.getElementById('cpf-qty').value, 10) || 0;
      const parts = [t('One code for this coupon — every customer uses the same code.')];
      if (qty > 0) parts.push(t('It can be used {n} times in total.', { n: qty }));
      if (record?.used_count) parts.push(t('Already used {n}× — the number can’t go below that.', { n: record.used_count }));
      hintEl.textContent = parts.join(' ');
    };
    document.getElementById('cpf-qty').addEventListener('input', syncHint);
    syncHint();

    const valueEl = document.getElementById('cpf-value');
    const setType = (next) => {
      type = next;
      bodyEl.querySelectorAll('.cpf-type button').forEach((b) => b.classList.toggle('active', b.dataset.type === type));
      document.getElementById('cpf-value-label').textContent = `${type === 'percent' ? t('Discount %') : t('Discount amount')} *`;
      valueEl.placeholder = type === 'percent' ? '10' : '500';
      if (type === 'percent') valueEl.max = '100'; else valueEl.removeAttribute('max');
    };
    bodyEl.querySelectorAll('.cpf-type button').forEach((b) => b.addEventListener('click', () => setType(b.dataset.type)));
    setType(type);

    const noExpiryEl = document.getElementById('cpf-no-expiry');
    const syncExpiry = () => {
      document.getElementById('cpf-expires-wrap').style.display = noExpiryEl.checked ? 'none' : '';
      if (noExpiryEl.checked) document.getElementById('cpf-expires').value = '';
    };
    noExpiryEl.addEventListener('change', syncExpiry);
    syncExpiry();

    const codeEl = document.getElementById('cpf-code');
    codeEl.addEventListener('input', () => { codeEl.value = codeEl.value.toUpperCase(); });
    const generate = async () => {
      const res = await API.couponGenerateCode();
      if (res.status === 200) codeEl.value = res.body.data.code;
      else showToast(t('Could not generate a code.'), 'error');
    };
    document.getElementById('cpf-generate').addEventListener('click', generate);
    if (!record) generate();

    document.getElementById('cpf-save').addEventListener('click', () => submitForm(record, type));
    setTimeout(() => document.getElementById('cpf-name')?.focus(), 60);
  }

  async function submitForm(record, type) {
    const val = (id) => document.getElementById(id)?.value ?? '';
    const name = val('cpf-name').trim();
    const value = parseFloat(val('cpf-value'));
    const qty = parseInt(val('cpf-qty'), 10);
    const code = val('cpf-code').trim().toUpperCase();
    const noExpiry = document.getElementById('cpf-no-expiry').checked;
    const from = val('cpf-from') || null;
    const expires = noExpiry ? null : (val('cpf-expires') || null);

    if (!name) { showToast(t('Coupon name is required.'), 'error'); return; }
    if (!(value > 0)) { showToast(t('Discount must be greater than 0.'), 'error'); return; }
    if (type === 'percent' && value > 100) { showToast(t('A percentage discount can be at most 100%.'), 'error'); return; }
    if (!code) { showToast(t('Coupon code is required — type one or click Generate.'), 'error'); return; }
    if (!/^[A-Z0-9\- ]{3,40}$/.test(code)) { showToast(t('Code must be 3–40 characters: letters, numbers and dashes only.'), 'error'); return; }
    if (!(qty >= 1)) { showToast(t('Number of coupons must be at least 1.'), 'error'); return; }
    if (!noExpiry && !expires) { showToast(t('Set a "Valid until" date, or tick "No expiry date".'), 'error'); return; }
    if (from && expires && expires < from) { showToast(t('"Valid until" must be on or after "Valid from".'), 'error'); return; }

    const payload = {
      name,
      code,
      discount_type: type,
      discount_value: value,
      quantity: qty,
      valid_from: from,
      expires_at: expires,
      notes: val('cpf-notes').trim() || null,
      is_active: document.getElementById('cpf-active').checked,
    };

    const btn = document.getElementById('cpf-save');
    btn.disabled = true;
    const res = record ? await API.updateCoupon(record.id, payload) : await API.createCoupon(payload);
    btn.disabled = false;

    if (res.status !== 200 && res.status !== 201) { showToast(firstError(res, 'Could not save coupon.'), 'error'); return; }
    showToast(t(res.body?.message || 'Saved.'), 'success');
    renderList();
    openCoupon(res.body.data.id);
  }

  // ════════════════════════════════════════════════════════════════════════
  // Coupon detail popup
  // ════════════════════════════════════════════════════════════════════════
  async function openCoupon(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    await loadSettings();
    const res = await API.coupon(id);
    if (res.status !== 200) {
      openDetail({ title: t('Coupon'), bodyHtml: `<div class="salm-empty">${firstError(res, 'Could not load coupon.')}</div>` });
      return;
    }
    const c = res.body.data;
    const pct = c.quantity > 0 ? Math.max(0, Math.min(100, (c.remaining / c.quantity) * 100)) : 0;
    const uses = c.redemptions || [];

    const bodyHtml = `
      <div class="gcm-hero cpm-hero${c.status === 'active' ? '' : ' off'}">
        <div class="gcm-hero-top"><span>${esc(c.name)}</span>${statusBadge(c.status)}</div>
        <div class="gcm-hero-code">${esc(c.code)}</div>
        <div class="gcm-hero-label">${t('Discount')}</div>
        <div class="gcm-hero-bal">${esc(offText(c))}</div>
        <div class="gcm-bar"><span style="width:${pct.toFixed(1)}%"></span></div>
        <div class="gcm-hero-foot">
          <span>${t('{left} of {total} left · used {used}×', { left: c.remaining, total: c.quantity, used: c.used_count })}</span>
          <span>${esc(validityText(c))}</span>
        </div>
      </div>
      ${c.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(c.notes)}</span></div>` : ''}
      <div class="salm-view-row"><span>${t('Total discount given')}</span><span>${money(c.total_discount || 0)}</span></div>
      <div class="salm-section-label">${t('Usage history')}</div>
      <div class="salm-item-list cpm-uses">
        ${uses.map((x) => `
          <div class="salm-item-row">
            <div>
              <div class="salm-item-name">${esc(x.sale_number || t('Sale'))}${x.reversed ? ` · ${t('voided')}` : ''}</div>
              <div class="salm-item-meta">${esc(fmtDateTime(x.created_at))}${x.user_name ? ` · ${esc(x.user_name)}` : ''}</div>
            </div>
            <div class="gcm-txn"><span class="${x.reversed ? 'pos' : 'neg'}">${x.reversed ? '' : '−'}${money(x.discount_amount)}</span></div>
          </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('Not used yet.')}</span></div>`}
      </div>`;

    const footHtml = `
      <button class="salm-btn-ghost danger" id="cpd-delete" type="button"><i class="fa-solid fa-trash"></i> ${t('Delete')}</button>
      <button class="salm-btn-ghost" id="cpd-copy" type="button"><i class="fa-solid fa-copy"></i> ${t('Copy code')}</button>
      <button class="salm-btn-primary" id="cpd-edit" type="button"><i class="fa-solid fa-pen"></i> ${t('Edit')}</button>`;

    openDetail({ title: c.code, bodyHtml, footHtml });
    document.getElementById('cpd-copy').addEventListener('click', () => copyText(c.code, t('Code copied')));
    document.getElementById('cpd-edit').addEventListener('click', () => renderForm(c));
    document.getElementById('cpd-delete').addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Delete coupon {code}?', { code: c.code }), { okText: t('Delete'), tone: 'danger' })) return;
      const del = await API.deleteCoupon(c.id);
      if (del.status !== 200) { showToast(firstError(del, 'Could not delete coupon.'), 'error'); return; }
      showToast(t('Coupon deleted.'), 'success');
      renderList();
    });
  }

  function openDetail({ title, bodyHtml, footHtml }) {
    detailTitleEl.textContent = title;
    detailBody.innerHTML = bodyHtml;
    detailFoot.innerHTML = footHtml || '';
    detailView.classList.add('open');
  }

  function closeDetail() {
    detailView?.classList.remove('open');
  }

  // ── Dialog shell: build the DOM, wire close handlers, show it ───────────
  function openCouponsModal() {
    if (document.getElementById('cpm-modal-backdrop')) return;

    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'salm-modal-backdrop';
    el.id = 'cpm-modal-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'cpm-modal-title');
    el.innerHTML = `
      <div class="salm-modal-card salm-modal-card--wide">
        <div class="salm-modal-head">
          <span class="salm-modal-head-icon cpm-head-icon"><i class="fa-solid fa-ticket"></i></span>
          <div class="salm-modal-head-text">
            <h2 id="cpm-modal-title">${t('Coupons')}</h2>
            <p>${t('Create discount coupons, choose how many times each code can be used, and see where they were used.')}</p>
          </div>
          <button class="salm-modal-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="salm-modal-body"><div id="cpm-body"></div></div>

        <div class="salm-detail-popup" id="cpm-detail-view">
          <div class="salm-detail-popup-card" role="dialog" aria-labelledby="cpm-detail-title">
            <div class="salm-detail-popup-head">
              <span class="salm-detail-popup-title gcm-mono" id="cpm-detail-title"></span>
              <button class="salm-modal-close" id="cpm-detail-back" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="cpm-detail-body" class="gcm-detail-body"></div>
            <div class="salm-detail-foot" id="cpm-detail-foot"></div>
          </div>
        </div>
      </div>
      <div class="salm-toast" id="cpm-toast"></div>`;
    document.body.appendChild(el);

    bodyEl = document.getElementById('cpm-body');
    detailView = document.getElementById('cpm-detail-view');
    detailTitleEl = document.getElementById('cpm-detail-title');
    detailBody = document.getElementById('cpm-detail-body');
    detailFoot = document.getElementById('cpm-detail-foot');
    toastEl = document.getElementById('cpm-toast');

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function onKey(e) {
      if (e.key !== 'Escape') return;
      if (document.querySelector('.zc-backdrop.open, .zc-overlay.open')) return; // let a confirm dialog handle it
      e.preventDefault();
      e.stopPropagation();
      if (detailView.classList.contains('open')) { closeDetail(); return; }
      close();
    }

    el.querySelector('.salm-modal-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);
    document.getElementById('cpm-detail-back').addEventListener('click', closeDetail);
    detailView.addEventListener('mousedown', (e) => { if (e.target === detailView) closeDetail(); });

    state.q = ''; state.status = '';
    requestAnimationFrame(() => el.classList.add('open'));
    renderList();
  }

  window.openCouponsModal = openCouponsModal;
})();
