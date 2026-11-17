'use strict';

// Gift Cards — an in-page modal dialog, opened by calling
// window.openGiftCardsModal() (see the dashboard's "Gift Cards" tile in
// js/dashboard.js). Same .salm-modal-* shell and .salm-* / .qf-* building
// blocks as js/sales.js and js/reports.js, plus a few .gcm-* rules in
// css/giftcards.css. Talks to the Laravel POS API (Modules/Pos/routes/api.php,
// prefix /api/v1/pos) — the same /gift-card-groups and /gift-cards endpoints
// the full desktop app uses.
//
// A *group* is one kind of gift card (e.g. "Birthday Gift Card", 5000 each);
// it holds one or many *cards*, each with its own unique code and balance.
// A card can be spent across several sales until its balance reaches zero.
//
// Views swapped inside the dialog body:
//   list  — groups, each expandable to show its cards
//   group — one group's summary + every card in it (generate more, copy codes)
//   form  — new gift cards (name, value, how many) / edit group / edit card
// A single card's detail is a popup layered on top (like Sales' sale detail).
(function () {
  let cardEl, bodyEl, detailView, detailTitleEl, detailBody, detailFoot, toastEl;

  let posSettings = {};
  let settingsLoaded = false;

  const state = {
    q: '',
    status: '',
    groups: [],
    expanded: new Set(),
    group: null, // group currently open in the group view
  };

  const STATUS = {
    active:    { label: 'Active',    badge: 'green' },
    scheduled: { label: 'Scheduled', badge: 'blue' },
    expired:   { label: 'Expired',   badge: 'amber' },
    used:      { label: 'Used up',   badge: 'gray' },
    disabled:  { label: 'Disabled',  badge: 'red' },
  };

  // ── Small shared helpers (same as js/sales.js / js/reports.js) ─────────
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

  function statusLine(g) {
    const c = g.status_counts || {};
    return ['active', 'used', 'expired', 'disabled', 'scheduled']
      .filter((k) => c[k])
      .map((k) => `${c[k]} ${t(STATUS[k].label).toLowerCase()}`)
      .join(' · ');
  }

  // ════════════════════════════════════════════════════════════════════════
  // List view — groups, expandable to their cards
  // ════════════════════════════════════════════════════════════════════════
  function renderList() {
    closeDetail();
    cardEl.classList.add('salm-modal-card--wide');
    bodyEl.innerHTML = `
      <div class="salm-toolbar">
        <div class="salm-search"><i class="fa-solid fa-magnifying-glass"></i><input id="gcm-search" type="text" placeholder="${t('Search by name or code…')}" value="${esc(state.q)}"></div>
        <select class="salm-select" id="gcm-status">
          <option value="">${t('All cards')}</option>
          ${Object.entries(STATUS).filter(([k]) => k !== 'scheduled').map(([k, s]) => `<option value="${k}" ${state.status === k ? 'selected' : ''}>${t(s.label)}</option>`).join('')}
        </select>
        <button class="salm-icon-btn" id="gcm-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
        <span class="salm-count-pill" id="gcm-count"></span>
        <button class="salm-btn-primary" id="gcm-new" type="button"><i class="fa-solid fa-plus"></i> ${t('New Gift Cards')}</button>
      </div>
      <div id="gcm-groups" class="gcm-groups"><div class="salm-loading">${t('Loading gift cards…')}</div></div>`;

    document.getElementById('gcm-search').addEventListener('input', debounce((e) => { state.q = e.target.value.trim(); loadGroups(); }, 300));
    document.getElementById('gcm-status').addEventListener('change', (e) => { state.status = e.target.value; loadGroups(); });
    document.getElementById('gcm-refresh').addEventListener('click', loadGroups);
    document.getElementById('gcm-new').addEventListener('click', () => renderForm('new'));
    loadGroups();
  }

  async function loadGroups() {
    const wrap = document.getElementById('gcm-groups');
    if (!wrap) return;
    await loadSettings();
    const res = await API.giftCardGroups(state.q, state.status);
    if (!document.getElementById('gcm-groups')) return; // view changed while loading
    if (res.status !== 200) {
      wrap.innerHTML = `<div class="salm-empty">${t('Could not load gift cards ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
      return;
    }
    state.groups = res.body.data || [];
    // While searching / filtering, open every group so the matching cards show.
    if (state.q || state.status) state.groups.forEach((g) => state.expanded.add(g.id));
    paintGroups();
  }

  function paintGroups() {
    const wrap = document.getElementById('gcm-groups');
    const cards = state.groups.reduce((n, g) => n + g.cards.length, 0);
    document.getElementById('gcm-count').textContent = state.groups.length
      ? t('{g} groups · {c} cards', { g: state.groups.length, c: cards })
      : '';

    if (!state.groups.length) {
      wrap.innerHTML = `
        <div class="gcm-empty">
          <i class="fa-solid fa-gift"></i>
          <div>${state.q || state.status ? t('No gift cards match your search.') : t('No gift cards yet.')}</div>
          ${state.q || state.status ? '' : `<p>${t('Create a group like "Birthday Gift Card" and generate as many cards as you need — each gets its own code.')}</p>`}
        </div>`;
      return;
    }

    wrap.innerHTML = state.groups.map((g) => {
      const open = state.expanded.has(g.id);
      return `
        <div class="gcm-group${open ? ' open' : ''}">
          <div class="gcm-group-head" data-group="${g.id}">
            <button class="gcm-caret" data-toggle="${g.id}" title="${open ? t('Collapse') : t('Show cards')}"><i class="fa-solid fa-chevron-${open ? 'down' : 'right'}"></i></button>
            <span class="gcm-group-icon"><i class="fa-solid fa-gift"></i></span>
            <div class="gcm-group-text">
              <div class="gcm-group-name">${esc(g.name)}</div>
              <div class="gcm-group-sub">${t('{value} each', { value: money(g.initial_value) })} · ${esc(statusLine(g) || t('no cards'))}</div>
            </div>
            <div class="gcm-group-bal"><span>${t('Remaining')}</span><strong>${money(g.total_balance)}</strong></div>
            <span class="gcm-count">${t(g.card_count === 1 ? '{n} card' : '{n} cards', { n: g.card_count })}</span>
            <i class="fa-solid fa-chevron-right gcm-open-icon"></i>
          </div>
          ${open ? `<div class="gcm-cards">${g.cards.map((c) => `
            <div class="gcm-card-row" data-card="${c.id}">
              <span class="gcm-code">${esc(c.code)}</span>
              <span class="gcm-card-bal">${money(c.balance)} <small>/ ${money(c.initial_value)}</small></span>
              ${statusBadge(c.status)}
            </div>`).join('') || `<div class="gcm-card-row gcm-muted">${t('No cards in this group.')}</div>`}</div>` : ''}
        </div>`;
    }).join('');

    wrap.querySelectorAll('[data-toggle]').forEach((btn) => btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = Number(btn.dataset.toggle);
      if (state.expanded.has(id)) state.expanded.delete(id); else state.expanded.add(id);
      paintGroups();
    }));
    wrap.querySelectorAll('.gcm-group-head').forEach((el) => el.addEventListener('click', () => renderGroup(Number(el.dataset.group))));
    wrap.querySelectorAll('.gcm-card-row[data-card]').forEach((el) => el.addEventListener('click', () => openCard(Number(el.dataset.card))));
  }

  // ════════════════════════════════════════════════════════════════════════
  // Group view — summary + every card in the group
  // ════════════════════════════════════════════════════════════════════════
  async function renderGroup(id) {
    closeDetail();
    bodyEl.innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    await loadSettings();
    const res = await API.giftCardGroup(id);
    if (res.status !== 200) {
      showToast(firstError(res, 'Could not load gift card group.'), 'error');
      renderList();
      return;
    }
    const g = res.body.data;
    state.group = g;
    const pct = g.total_value > 0 ? Math.max(0, Math.min(100, (g.total_balance / g.total_value) * 100)) : 0;

    bodyEl.innerHTML = `
      <div class="inv-detail-header">
        <button class="inv-back-btn" id="gcm-back"><i class="fa-solid fa-arrow-left"></i> ${t('Gift Cards')}</button>
        <span class="inv-detail-breadcrumb">${esc(g.name)}</span>
      </div>

      <div class="gcm-hero${g.is_active ? '' : ' off'}">
        <div class="gcm-hero-top">
          <span>${t('Gift card group')}</span>
          ${g.is_active ? statusBadge('active') : statusBadge('disabled')}
        </div>
        <div class="gcm-hero-name">${esc(g.name)}</div>
        <div class="gcm-hero-label">${t('{n} cards × {value} · remaining across all cards', { n: g.card_count, value: money(g.initial_value) })}</div>
        <div class="gcm-hero-bal">${money(g.total_balance)}</div>
        <div class="gcm-bar"><span style="width:${pct.toFixed(1)}%"></span></div>
        <div class="gcm-hero-foot">
          <span>${t('Issued {amount}', { amount: money(g.total_value) })} · ${esc(statusLine(g))}</span>
          <span>${esc(validityText(g))}</span>
        </div>
        ${g.notes ? `<div class="gcm-hero-notes"><i class="fa-solid fa-note-sticky"></i> ${esc(g.notes)}</div>` : ''}
      </div>

      <div class="salm-toolbar">
        <span class="salm-section-label" style="margin:0">${t('Cards in this group')}</span>
        <div class="gcm-add">
          <input type="number" id="gcm-add-qty" min="1" max="500" value="1">
          <button class="salm-icon-btn" id="gcm-add-btn"><i class="fa-solid fa-plus"></i> ${t('Generate more')}</button>
        </div>
        <button class="salm-icon-btn" id="gcm-copy-all"><i class="fa-solid fa-copy"></i> ${t('Copy all codes')}</button>
        <button class="salm-icon-btn" id="gcm-edit-group"><i class="fa-solid fa-pen"></i> ${t('Edit group')}</button>
        <button class="salm-btn-ghost danger" id="gcm-delete-group" style="margin-right:0"><i class="fa-solid fa-trash"></i> ${t('Delete group')}</button>
      </div>

      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>#</th><th>${t('Code')}</th><th>${t('Balance')}</th><th>${t('Valid')}</th><th>${t('Status')}</th><th></th></tr></thead>
        <tbody>${g.cards.map((c, i) => `
          <tr data-id="${c.id}">
            <td class="salm-muted-cell">${i + 1}</td>
            <td><span class="gcm-code">${esc(c.code)}</span></td>
            <td class="salm-amt-cell" style="text-align:left">${money(c.balance)} <small class="gcm-muted">/ ${money(c.initial_value)}</small></td>
            <td class="salm-muted-cell">${esc(validityText(c))}</td>
            <td>${statusBadge(c.status)}</td>
            <td><div class="salm-row-actions"><button data-copy="${esc(c.code)}" title="${t('Copy code')}"><i class="fa-solid fa-copy"></i></button></div></td>
          </tr>`).join('') || `<tr><td colspan="6" class="salm-empty">${t('No cards in this group.')}</td></tr>`}
        </tbody>
      </table></div>`;

    document.getElementById('gcm-back').addEventListener('click', renderList);
    document.getElementById('gcm-add-btn').addEventListener('click', () => addCards(g));
    document.getElementById('gcm-copy-all').addEventListener('click', () => {
      if (!g.cards.length) return;
      copyText(g.cards.map((c) => c.code).join('\n'), t('{n} codes copied', { n: g.cards.length }));
    });
    document.getElementById('gcm-edit-group').addEventListener('click', () => renderForm('group', g));
    document.getElementById('gcm-delete-group').addEventListener('click', () => deleteGroup(g));
    bodyEl.querySelectorAll('tr[data-id]').forEach((tr) => tr.addEventListener('click', (e) => {
      const copyBtn = e.target.closest('[data-copy]');
      if (copyBtn) { copyText(copyBtn.dataset.copy, t('Code copied')); return; }
      openCard(Number(tr.dataset.id));
    }));
  }

  async function addCards(g) {
    const qty = parseInt(document.getElementById('gcm-add-qty').value, 10) || 0;
    if (qty < 1 || qty > 500) { showToast(t('Enter between 1 and 500 cards.'), 'error'); return; }
    const ok = await zeebrooConfirm(t('Generate {n} more "{name}" cards worth {value} each?', { n: qty, name: g.name, value: money(g.initial_value) }), { okText: t('Generate') });
    if (!ok) return;
    const btn = document.getElementById('gcm-add-btn');
    btn.disabled = true;
    const res = await API.addGiftCardsToGroup(g.id, qty);
    btn.disabled = false;
    if (res.status !== 201) { showToast(firstError(res, 'Could not add cards.'), 'error'); return; }
    showToast(t(res.body.message), 'success');
    renderGroup(g.id);
  }

  async function deleteGroup(g) {
    const ok = await zeebrooConfirm(t('Delete "{name}" and all {n} cards in it?', { name: g.name, n: g.card_count }), { okText: t('Delete'), tone: 'danger' });
    if (!ok) return;
    const res = await API.deleteGiftCardGroup(g.id);
    if (res.status !== 200) { showToast(firstError(res, 'Could not delete group.'), 'error'); return; }
    showToast(t('Gift card group deleted.'), 'success');
    state.group = null;
    renderList();
  }

  // ════════════════════════════════════════════════════════════════════════
  // Form — mode: 'new' (group + N cards) | 'group' (edit group) | 'card' (edit card)
  // ════════════════════════════════════════════════════════════════════════
  async function renderForm(mode, record = null) {
    closeDetail();
    await loadSettings();
    const today = new Date().toISOString().slice(0, 10);
    const title = { new: t('New Gift Cards'), group: t('Edit Gift Card Group'), card: t('Edit Card {code}', { code: record?.code || '' }) }[mode];
    const noExpiry = mode === 'new' ? false : !record?.expires_at;

    bodyEl.innerHTML = `
      <div class="inv-detail-header qf-form-header">
        <button class="inv-back-btn" id="gcf-back"><i class="fa-solid fa-arrow-left"></i> ${t('Back')}</button>
        <span class="inv-detail-breadcrumb">${esc(title)}</span>
      </div>

      <div class="qf-card">
        <div class="qf-card-title"><i class="fa-solid fa-gift"></i> ${t('Gift Card Details')}</div>
        <div class="qf-grid">
          ${mode !== 'card' ? `
          <label class="qf-field gcf-span2"><span>${t('Gift card name')} *</span>
            <input type="text" id="gcf-name" maxlength="191" placeholder="${t('e.g. Birthday Gift Card')}" value="${esc(record?.name || '')}">
          </label>` : ''}
          ${mode !== 'group' ? `
          <label class="qf-field"><span>${t('Value per card')} *</span>
            <input type="number" id="gcf-value" min="0.01" step="0.01" placeholder="5000" value="${record?.initial_value ?? ''}">
          </label>` : ''}
          ${mode === 'new' ? `
          <label class="qf-field"><span>${t('How many cards?')} *</span>
            <input type="number" id="gcf-qty" min="1" max="500" step="1" value="1">
          </label>` : ''}
          ${mode !== 'group' ? `
          <label class="qf-field gcf-span2" id="gcf-code-wrap"><span>${t('Gift card code')} *</span>
            <div class="gcf-code-row">
              <input type="text" id="gcf-code" maxlength="40" class="gcm-code-input" placeholder="${t('Auto-generated — or type your own')}" value="${esc(mode === 'card' ? record.code : '')}">
              <button type="button" class="salm-icon-btn" id="gcf-generate"><i class="fa-solid fa-wand-magic-sparkles"></i> ${t('Generate')}</button>
            </div>
          </label>` : ''}
          <label class="qf-field"><span>${t('Valid from')}</span>
            <input type="date" id="gcf-from" value="${esc(record?.valid_from || (mode === 'new' ? today : ''))}">
          </label>
          <label class="qf-field" id="gcf-expires-wrap"><span>${t('Valid until')}</span>
            <input type="date" id="gcf-expires" value="${esc(record?.expires_at || '')}">
          </label>
          <label class="gcf-check gcf-span2"><input type="checkbox" id="gcf-no-expiry" ${noExpiry ? 'checked' : ''}> ${t('No expiry date')}</label>
          <label class="qf-field gcf-span2"><span>${t('Notes')}</span>
            <textarea id="gcf-notes" rows="2" placeholder="${t('Who is this gift card for? (optional)')}">${esc(record?.notes || '')}</textarea>
          </label>
          <label class="gcf-check gcf-span2"><input type="checkbox" id="gcf-active" ${!record || record.is_active ? 'checked' : ''}> ${t('Active')}</label>
        </div>
        <div class="gcf-hint" id="gcf-hint"></div>
      </div>

      <div class="qf-actions">
        <button class="salm-btn-ghost" id="gcf-cancel" type="button">${t('Cancel')}</button>
        <button class="salm-btn-primary" id="gcf-save" type="button"><i class="fa-solid fa-check"></i> ${mode === 'group' ? t('Save Group') : t('Save Gift Card')}</button>
      </div>`;

    const back = () => {
      if (mode === 'new') renderList();
      else if (mode === 'group') renderGroup(record.id);
      else if (record.group_id) renderGroup(record.group_id);
      else renderList();
    };
    document.getElementById('gcf-back').addEventListener('click', back);
    document.getElementById('gcf-cancel').addEventListener('click', back);

    const noExpiryEl = document.getElementById('gcf-no-expiry');
    const syncExpiry = () => {
      document.getElementById('gcf-expires-wrap').style.display = noExpiryEl.checked ? 'none' : '';
      if (noExpiryEl.checked) document.getElementById('gcf-expires').value = '';
    };
    noExpiryEl.addEventListener('change', syncExpiry);
    syncExpiry();

    const hintEl = document.getElementById('gcf-hint');
    // With more than one card every code is auto-generated, so hide the code field.
    const syncQty = () => {
      if (mode !== 'new') return;
      const qty = Math.max(1, parseInt(document.getElementById('gcf-qty').value, 10) || 1);
      const value = parseFloat(document.getElementById('gcf-value').value) || 0;
      document.getElementById('gcf-code-wrap').style.display = qty > 1 ? 'none' : '';
      hintEl.textContent = qty > 1
        ? t('{n} cards will be created, each with its own unique code', { n: qty }) + (value > 0 ? ` — ${t('{amount} in total', { amount: money(value * qty) })}` : '') + '.'
        : '';
    };
    if (mode === 'new') {
      document.getElementById('gcf-qty').addEventListener('input', syncQty);
      document.getElementById('gcf-value').addEventListener('input', syncQty);
    }
    if (mode === 'card' && record.used_amount > 0) {
      hintEl.textContent = t('{amount} has already been used — changing the value moves the remaining balance by the same amount.', { amount: money(record.used_amount) });
    }

    const codeEl = document.getElementById('gcf-code');
    if (codeEl) {
      codeEl.addEventListener('input', () => { codeEl.value = codeEl.value.toUpperCase(); });
      const generate = async () => {
        const res = await API.giftCardGenerateCode();
        if (res.status === 200) codeEl.value = res.body.data.code;
        else showToast(t('Could not generate a code.'), 'error');
      };
      document.getElementById('gcf-generate').addEventListener('click', generate);
      if (mode === 'new') generate();
    }

    document.getElementById('gcf-save').addEventListener('click', () => submitForm(mode, record));
    setTimeout(() => (document.getElementById(mode === 'card' ? 'gcf-code' : 'gcf-name'))?.focus(), 60);
  }

  async function submitForm(mode, record) {
    const val = (id) => document.getElementById(id)?.value ?? '';
    const name = val('gcf-name').trim();
    const value = parseFloat(val('gcf-value'));
    const qty = mode === 'new' ? Math.max(1, parseInt(val('gcf-qty'), 10) || 1) : 1;
    const code = val('gcf-code').trim().toUpperCase();
    const noExpiry = document.getElementById('gcf-no-expiry').checked;
    const from = val('gcf-from') || null;
    const expires = noExpiry ? null : (val('gcf-expires') || null);
    const useCode = mode === 'card' || (mode === 'new' && qty === 1);

    if (mode !== 'card' && !name) { showToast(t('Gift card name is required.'), 'error'); return; }
    if (mode !== 'group' && !(value > 0)) { showToast(t('Gift card value must be greater than 0.'), 'error'); return; }
    if (qty > 500) { showToast(t('You can generate up to 500 cards at a time.'), 'error'); return; }
    if (useCode && !code) { showToast(t('Gift card code is required — type one or click Generate.'), 'error'); return; }
    if (useCode && !/^[A-Z0-9\- ]{4,40}$/.test(code)) { showToast(t('Code must be 4–40 characters: letters, numbers and dashes only.'), 'error'); return; }
    if (!noExpiry && !expires) { showToast(t('Set a "Valid until" date, or tick "No expiry date".'), 'error'); return; }
    if (from && expires && expires < from) { showToast(t('"Valid until" must be on or after "Valid from".'), 'error'); return; }

    const common = {
      valid_from: from,
      expires_at: expires,
      notes: val('gcf-notes').trim() || null,
      is_active: document.getElementById('gcf-active').checked,
    };

    const btn = document.getElementById('gcf-save');
    btn.disabled = true;
    let res;
    if (mode === 'new') res = await API.createGiftCards({ ...common, name, initial_value: value, quantity: qty, ...(useCode ? { code } : {}) });
    if (mode === 'group') res = await API.updateGiftCardGroup(record.id, { ...common, name });
    if (mode === 'card') res = await API.updateGiftCard(record.id, { ...common, code, initial_value: value });
    btn.disabled = false;

    if (res.status !== 200 && res.status !== 201) { showToast(firstError(res, 'Could not save gift card.'), 'error'); return; }
    showToast(t(res.body?.message || 'Saved.'), 'success');

    const saved = res.body.data;
    if (mode === 'card') {
      if (saved.group_id) await renderGroup(saved.group_id); else renderList();
      openCard(saved.id);
    } else {
      renderGroup(saved.id);
    }
  }

  // ════════════════════════════════════════════════════════════════════════
  // Card detail popup
  // ════════════════════════════════════════════════════════════════════════
  async function openCard(id) {
    openDetail({ title: t('Loading…'), bodyHtml: `<div class="salm-loading">${t('Loading…')}</div>` });
    const res = await API.giftCard(id);
    if (res.status !== 200) {
      openDetail({ title: t('Gift card'), bodyHtml: `<div class="salm-empty">${firstError(res, 'Could not load gift card.')}</div>` });
      return;
    }
    const c = res.body.data;
    const pct = c.initial_value > 0 ? Math.max(0, Math.min(100, (c.balance / c.initial_value) * 100)) : 0;
    const typeLabel = { issue: t('Issued'), redeem: t('Used'), refund: t('Refunded'), adjust: t('Adjusted') };
    const txns = c.transactions || [];

    const bodyHtml = `
      <div class="gcm-hero gcm-hero-card${c.status === 'active' ? '' : ' off'}">
        <div class="gcm-hero-top"><span>${esc(c.name)}</span>${statusBadge(c.status)}</div>
        <div class="gcm-hero-code">${esc(c.code)}</div>
        <div class="gcm-hero-label">${t('Available balance')}</div>
        <div class="gcm-hero-bal">${money(c.balance)}</div>
        <div class="gcm-bar"><span style="width:${pct.toFixed(1)}%"></span></div>
        <div class="gcm-hero-foot">
          <span>${t('Value {value} · Used {used}', { value: money(c.initial_value), used: money(c.used_amount) })}</span>
          <span>${esc(validityText(c))}</span>
        </div>
      </div>
      ${c.customer_name ? `<div class="salm-view-row"><span>${t('Customer')}</span><span>${esc(c.customer_name)}</span></div>` : ''}
      ${c.notes ? `<div class="salm-view-row"><span>${t('Notes')}</span><span>${esc(c.notes)}</span></div>` : ''}
      <div class="salm-section-label">${t('Usage history')}</div>
      <div class="salm-item-list">
        ${txns.map((x) => `
          <div class="salm-item-row">
            <div>
              <div class="salm-item-name">${esc(typeLabel[x.type] || x.type)}${x.sale_number ? ` · ${esc(x.sale_number)}` : ''}</div>
              <div class="salm-item-meta">${esc(fmtDateTime(x.created_at))}${x.user_name ? ` · ${esc(x.user_name)}` : ''}</div>
            </div>
            <div class="gcm-txn">
              <span class="${x.amount < 0 ? 'neg' : 'pos'}">${x.amount < 0 ? '−' : '+'}${money(Math.abs(x.amount))}</span>
              <small>${t('Balance {amount}', { amount: money(x.balance_after) })}</small>
            </div>
          </div>`).join('') || `<div class="salm-item-row"><span class="salm-item-meta">${t('No activity yet.')}</span></div>`}
      </div>`;

    const footHtml = `
      <button class="salm-btn-ghost danger" id="gcd-delete" type="button"><i class="fa-solid fa-trash"></i> ${t('Delete')}</button>
      <button class="salm-btn-ghost" id="gcd-copy" type="button"><i class="fa-solid fa-copy"></i> ${t('Copy code')}</button>
      <button class="salm-btn-primary" id="gcd-edit" type="button"><i class="fa-solid fa-pen"></i> ${t('Edit')}</button>`;

    openDetail({ title: c.code, bodyHtml, footHtml });
    document.getElementById('gcd-copy').addEventListener('click', () => copyText(c.code, t('Code copied')));
    document.getElementById('gcd-edit').addEventListener('click', () => renderForm('card', c));
    document.getElementById('gcd-delete').addEventListener('click', async () => {
      if (!await zeebrooConfirm(t('Delete gift card {code}?', { code: c.code }), { okText: t('Delete'), tone: 'danger' })) return;
      const del = await API.deleteGiftCard(c.id);
      if (del.status !== 200) { showToast(firstError(del, 'Could not delete gift card.'), 'error'); return; }
      showToast(t('Gift card deleted.'), 'success');
      closeDetail();
      // Deleting a group's last card removes the group too — fall back to the list then.
      if (state.group && state.group.id === c.group_id && state.group.card_count > 1) renderGroup(c.group_id);
      else renderList();
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
  function openGiftCardsModal() {
    if (document.getElementById('gcm-modal-backdrop')) return;

    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'salm-modal-backdrop';
    el.id = 'gcm-modal-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'gcm-modal-title');
    el.innerHTML = `
      <div class="salm-modal-card salm-modal-card--wide">
        <div class="salm-modal-head">
          <span class="salm-modal-head-icon"><i class="fa-solid fa-gift"></i></span>
          <div class="salm-modal-head-text">
            <h2 id="gcm-modal-title">${t('Gift Cards')}</h2>
            <p>${t('Create gift cards, track balances, and see where each card was used.')}</p>
          </div>
          <button class="salm-modal-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="salm-modal-body"><div id="gcm-body"></div></div>

        <div class="salm-detail-popup" id="gcm-detail-view">
          <div class="salm-detail-popup-card" role="dialog" aria-labelledby="gcm-detail-title">
            <div class="salm-detail-popup-head">
              <span class="salm-detail-popup-title gcm-mono" id="gcm-detail-title"></span>
              <button class="salm-modal-close" id="gcm-detail-back" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="gcm-detail-body" class="gcm-detail-body"></div>
            <div class="salm-detail-foot" id="gcm-detail-foot"></div>
          </div>
        </div>
      </div>
      <div class="salm-toast" id="gcm-toast"></div>`;
    document.body.appendChild(el);

    cardEl = el.querySelector('.salm-modal-card');
    bodyEl = document.getElementById('gcm-body');
    detailView = document.getElementById('gcm-detail-view');
    detailTitleEl = document.getElementById('gcm-detail-title');
    detailBody = document.getElementById('gcm-detail-body');
    detailFoot = document.getElementById('gcm-detail-foot');
    toastEl = document.getElementById('gcm-toast');

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
    document.getElementById('gcm-detail-back').addEventListener('click', closeDetail);
    detailView.addEventListener('mousedown', (e) => { if (e.target === detailView) closeDetail(); });

    state.q = ''; state.status = ''; state.expanded = new Set(); state.group = null;
    requestAnimationFrame(() => el.classList.add('open'));
    renderList();
  }

  window.openGiftCardsModal = openGiftCardsModal;
})();
