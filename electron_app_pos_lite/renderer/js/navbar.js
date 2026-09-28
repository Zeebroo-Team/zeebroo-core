'use strict';

// Shared top-bar chrome (needs js/i18n.js loaded first):
//   • the profile dropdown — avatar, account details, Language / Reload / Restart / Log out
//     (a screen just puts <div class="user-menu" id="user-menu"></div> in its .topbar)
//   • the language switch window, opened from the dropdown or from any element
//     carrying [data-open-language] (the login screen uses that)
(function () {
  const $ = (id) => document.getElementById(id);

  // ── Language switch window ─────────────────────────────────────────────
  function openLanguageWindow() {
    if ($('lw-backdrop')) return;

    const current = i18n.lang;
    let selected = current;
    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'lw-backdrop';
    el.id = 'lw-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'lw-title');
    el.innerHTML = `
      <div class="lw-card">
        <div class="lw-head">
          <span class="lw-head-icon"><i class="fa-solid fa-language"></i></span>
          <div class="lw-head-text">
            <h2 id="lw-title">${t('Language')}</h2>
            <p>${t('Choose the language used across the app.')}</p>
          </div>
          <button class="lw-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="lw-options" role="radiogroup" aria-labelledby="lw-title">
          ${i18n.languages.map((l) => `
            <button class="lw-option" type="button" role="radio" data-lang="${l.code}" aria-checked="false">
              <span class="lw-badge">${l.badge}</span>
              <span class="lw-names"><b>${l.native}</b><small>${t(l.english)}</small></span>
              <i class="fa-solid fa-circle-check lw-check"></i>
            </button>`).join('')}
        </div>
        <div class="lw-note"><i class="fa-solid fa-circle-info"></i><span>${t('The page will reload to apply the change.')}</span></div>
        <div class="lw-foot">
          <button class="lw-btn ghost" type="button" data-lw="cancel">${t('Cancel')}</button>
          <button class="lw-btn primary" type="button" data-lw="apply">${t('Apply')}</button>
        </div>
      </div>`;
    document.body.appendChild(el);

    const options = Array.from(el.querySelectorAll('.lw-option'));
    const applyBtn = el.querySelector('[data-lw="apply"]');

    function render() {
      options.forEach((o) => {
        const on = o.dataset.lang === selected;
        o.classList.toggle('selected', on);
        o.setAttribute('aria-checked', String(on));
        o.tabIndex = on ? 0 : -1;
      });
      applyBtn.disabled = selected === current;
    }

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function apply() {
      if (selected === current) return close();
      if (i18n.setLang(selected)) window.location.reload();
    }

    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); return; }
      if (e.key === 'Enter' && document.activeElement && document.activeElement.classList.contains('lw-option')) {
        e.preventDefault();
        apply();
        return;
      }
      if (['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft'].includes(e.key)) {
        e.preventDefault();
        const step = e.key === 'ArrowDown' || e.key === 'ArrowRight' ? 1 : -1;
        const next = options[(options.findIndex((o) => o.dataset.lang === selected) + step + options.length) % options.length];
        selected = next.dataset.lang;
        render();
        next.focus();
      }
    }

    options.forEach((o) => o.addEventListener('click', () => { selected = o.dataset.lang; render(); }));
    el.querySelector('.lw-close').addEventListener('click', close);
    el.querySelector('[data-lw="cancel"]').addEventListener('click', close);
    applyBtn.addEventListener('click', apply);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    render();
    requestAnimationFrame(() => {
      el.classList.add('open');
      const on = options.find((o) => o.dataset.lang === selected);
      if (on) on.focus();
    });
  }

  window.openLanguageWindow = openLanguageWindow;

  // any element can open the window (login screen's globe button)
  document.querySelectorAll('[data-open-language]').forEach((btn) => btn.addEventListener('click', openLanguageWindow));
  document.querySelectorAll('[data-language-label]').forEach((el) => { el.textContent = i18n.current().native; });

  function esc(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  // ── Confirm / Alert dialog (replaces native confirm()/alert()) ─────────
  // Styled to match the rest of the app chrome; queued so stacked calls
  // (e.g. delete-then-error) show one at a time instead of overlapping.
  let zcQueue = Promise.resolve();

  function zcShow({ title, message, okText, cancelText, tone = 'primary', icon }) {
    return new Promise((resolve) => {
      const returnFocusTo = document.activeElement;
      const showCancel = cancelText !== null;
      const defaultIcon = tone === 'danger' ? 'fa-trash-can' : tone === 'warning' ? 'fa-triangle-exclamation' : tone === 'success' ? 'fa-circle-check' : 'fa-circle-question';

      const el = document.createElement('div');
      el.className = 'zc-backdrop';
      el.setAttribute('role', 'alertdialog');
      el.setAttribute('aria-modal', 'true');
      el.setAttribute('aria-labelledby', 'zc-title');
      el.innerHTML = `
        <div class="zc-card">
          <span class="zc-icon ${tone}"><i class="fa-solid ${icon || defaultIcon}"></i></span>
          <h2 id="zc-title">${esc(title || (tone === 'danger' ? t('Are you sure?') : t('Please confirm')))}</h2>
          <p>${esc(message)}</p>
          <div class="zc-foot">
            ${showCancel ? `<button class="zc-btn ghost" type="button" data-zc="cancel">${esc(cancelText || t('Cancel'))}</button>` : ''}
            <button class="zc-btn ${tone === 'danger' ? 'danger' : 'primary'}" type="button" data-zc="ok">${esc(okText || t('OK'))}</button>
          </div>
        </div>`;
      document.body.appendChild(el);

      function close(result) {
        document.removeEventListener('keydown', onKey, true);
        el.classList.remove('open');
        setTimeout(() => el.remove(), 200);
        if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
        resolve(result);
      }

      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(false); }
      }

      el.querySelector('[data-zc="ok"]').addEventListener('click', () => close(true));
      el.querySelector('[data-zc="cancel"]')?.addEventListener('click', () => close(false));
      el.addEventListener('mousedown', (e) => { if (e.target === el && showCancel) close(false); });
      document.addEventListener('keydown', onKey, true);

      requestAnimationFrame(() => {
        el.classList.add('open');
        // for a destructive confirm, default focus to Cancel so a stray Enter can't delete
        const focusSelector = tone === 'danger' && showCancel ? '[data-zc="cancel"]' : '[data-zc="ok"]';
        el.querySelector(focusSelector)?.focus();
      });
    });
  }

  // zeebrooConfirm(message, { title, okText, cancelText, tone: 'primary'|'danger'|'warning', icon }) → Promise<boolean>
  function zeebrooConfirm(message, opts = {}) {
    const run = () => zcShow({ cancelText: t('Cancel'), okText: t('OK'), tone: 'primary', ...opts, message });
    const p = zcQueue.then(run);
    zcQueue = p.catch(() => {});
    return p;
  }

  // zeebrooAlert(message, { title, okText, tone, icon }) → Promise<void> (resolves once dismissed)
  function zeebrooAlert(message, opts = {}) {
    const run = () => zcShow({ okText: t('OK'), tone: 'primary', ...opts, message, cancelText: null });
    const p = zcQueue.then(run);
    zcQueue = p.catch(() => {});
    return p;
  }

  window.zeebrooConfirm = zeebrooConfirm;
  window.zeebrooAlert = zeebrooAlert;

  // ── Billing & Payments window ───────────────────────────────────────────
  // Subscription status + invoice history/detail, backed by the same
  // Modules/Pos billing endpoints (Modules/Pos/routes/api.php) the full
  // desktop app's Billing & Payments modal uses.
  function openBillingWindow() {
    if ($('bm-backdrop')) return;

    const returnFocusTo = document.activeElement;
    const bm = { items: [], tab: null };
    const dueStatuses = ['pending', 'failed', 'canceled'];
    const statusMeta = {
      succeeded: { cls: 'success', label: t('Paid') },
      pending: { cls: 'warning', label: t('Pending') },
      processing: { cls: 'warning', label: t('Processing') },
      failed: { cls: 'danger', label: t('Failed') },
      canceled: { cls: 'danger', label: t('Canceled') },
      refunded: { cls: 'info', label: t('Refunded') },
    };
    const rowIcons = { succeeded: 'fa-circle-check', failed: 'fa-triangle-exclamation', canceled: 'fa-xmark', pending: 'fa-clock', processing: 'fa-clock', refunded: 'fa-rotate-left' };

    function money(amount, currency) {
      return `${(Number(amount) || 0).toFixed(2)} ${String(currency || '').toUpperCase()}`;
    }
    function fmtDate(str, withTime) {
      if (!str) return '—';
      const d = new Date(str);
      if (isNaN(d.getTime())) return '—';
      return withTime
        ? d.toLocaleString(i18n.locale, { dateStyle: 'medium', timeStyle: 'short' })
        : d.toLocaleDateString(i18n.locale, { year: 'numeric', month: 'long', day: 'numeric' });
    }
    function badgeHtml(status) {
      const meta = statusMeta[status] || { cls: 'info', label: status || t('Unknown') };
      return `<span class="pm-badge ${meta.cls}">${esc(meta.label)}</span>`;
    }
    function showAlert(target, msg) {
      target.textContent = msg;
      target.style.display = 'block';
    }

    const el = document.createElement('div');
    el.className = 'bm-backdrop';
    el.id = 'bm-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'bm-title');
    el.innerHTML = `
      <div class="bm-card">
        <div class="bm-head">
          <span class="bm-head-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
          <div class="bm-head-text">
            <h2 id="bm-title">${t('Billing & Payments')}</h2>
            <p>${t('View your subscription, invoices, and receipts.')}</p>
          </div>
          <button class="bm-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="bm-body">
          <div id="bm-alert" class="bm-alert" style="display:none"></div>
          <div id="bm-list-panel">
            <div id="bm-summary"></div>
            <div id="bm-tabs" class="pm-tabs"></div>
            <div id="bm-list" class="pm-list"></div>
          </div>
          <div id="bm-detail-panel" style="display:none">
            <button class="bm-back-btn" id="bm-detail-back" type="button"><i class="fa-solid fa-arrow-left"></i> ${t('Back to history')}</button>
            <div id="bm-detail-body"></div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
    }

    el.querySelector('.bm-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);
    $('bm-detail-back').addEventListener('click', () => {
      $('bm-detail-panel').style.display = 'none';
      $('bm-list-panel').style.display = '';
    });

    async function refresh() {
      try {
        const res = await API.paymentHistory();
        if (res.status !== 200) {
          $('bm-list').innerHTML = '';
          showAlert($('bm-alert'), res.body?.message || t('Could not load billing history.'));
          return;
        }
        const data = res.body?.data || {};
        bm.items = data.items || [];
        renderSummary(data);
        const hasDue = bm.items.some((p) => dueStatuses.includes(p.payment_status));
        if (!bm.tab) bm.tab = hasDue ? 'due' : 'paid';
        renderTabs();
        renderList();
      } catch (e) {
        $('bm-list').innerHTML = '';
        showAlert($('bm-alert'), t('Could not load billing history.'));
        console.error('[billing] refresh failed', e);
      }
    }

    function renderSummary(data) {
      const summary = $('bm-summary');
      if (!data.subscription_status && !data.next_renewal_at) {
        summary.innerHTML = '';
        return;
      }
      const rawStatus = data.subscription_status || 'unknown';
      const statusLabel = rawStatus.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
      const cancelling = !!data.cancel_at_period_end;
      const accessUntil = data.access_until || data.next_renewal_at;
      const canManage = !!data.can_manage_subscription && rawStatus === 'active';

      let noteHtml = '';
      if (cancelling) {
        noteHtml = `<div class="pm-summary-cancel"><i class="fa-solid fa-triangle-exclamation"></i> ${t('You’ve cancelled your subscription. You can use it until {date} — you won’t be charged again.', { date: fmtDate(accessUntil) })}</div>`;
      } else if (data.next_renewal_at) {
        noteHtml = `<div class="pm-summary-renewal">${t('Next renewal on {date}', { date: fmtDate(data.next_renewal_at) })}</div>`;
      }

      let actionHtml = '';
      if (canManage) {
        actionHtml = cancelling
          ? `<button class="bm-btn bm-btn-primary" id="bm-resume-sub" type="button"><i class="fa-solid fa-rotate-left"></i> ${t('Keep subscription')}</button>`
          : `<button class="bm-btn bm-btn-outline bm-cancel-sub-btn" id="bm-cancel-sub" type="button"><i class="fa-solid fa-ban"></i> ${t('Cancel subscription')}</button>`;
      }

      summary.innerHTML = `
        <div class="pm-summary">
          <div class="pm-summary-left">
            <div class="pm-summary-plan">${t('Subscription')}: ${esc(cancelling ? t('Cancels on {date}', { date: fmtDate(accessUntil) }) : statusLabel)}</div>
            ${noteHtml}
          </div>
          ${actionHtml}
        </div>`;

      $('bm-cancel-sub')?.addEventListener('click', () => changeCancellation(true, accessUntil));
      $('bm-resume-sub')?.addEventListener('click', () => changeCancellation(false, accessUntil));
    }

    async function changeCancellation(cancel, accessUntil) {
      const ok = cancel
        ? await zeebrooConfirm(t('Cancel your subscription? You’ll keep access until {date}. You won’t be charged again, and you can undo this any time before then.', { date: fmtDate(accessUntil) }), {
          title: t('Cancel subscription?'),
          okText: t('Cancel subscription'),
          cancelText: t('Keep subscription'),
          tone: 'danger',
        })
        : await zeebrooConfirm(t('Keep your subscription? It will continue and renew on {date} as normal.', { date: fmtDate(accessUntil) }), {
          title: t('Keep subscription?'),
          okText: t('Keep subscription'),
          cancelText: t('Never mind'),
          tone: 'primary',
          icon: 'fa-rotate-left',
        });
      if (!ok) return;

      const res = cancel ? await API.cancelBillingSubscription() : await API.resumeBillingSubscription();
      if (res.status !== 200) {
        showAlert($('bm-alert'), res.body?.message || t('Could not update your subscription. Please try again.'));
        return;
      }
      $('bm-alert').style.display = 'none';
      await refresh();
    }

    function renderTabs() {
      const dueCount = bm.items.filter((p) => dueStatuses.includes(p.payment_status)).length;
      const paidCount = bm.items.length - dueCount;

      $('bm-tabs').innerHTML = `
        <button class="pm-tab ${bm.tab === 'paid' ? 'active' : ''}" data-bm-tab="paid" type="button">
          ${t('Paid')} <span class="pm-tab-count">${paidCount}</span>
        </button>
        <button class="pm-tab ${bm.tab === 'due' ? 'active' : ''}" data-bm-tab="due" type="button">
          ${t('Due & Upcoming')} <span class="pm-tab-count">${dueCount}</span>
        </button>`;

      $('bm-tabs').querySelectorAll('[data-bm-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
          bm.tab = btn.dataset.bmTab;
          renderTabs();
          renderList();
        });
      });
    }

    function renderList() {
      const list = $('bm-list');
      const items = bm.items.filter((p) => (bm.tab === 'due' ? dueStatuses.includes(p.payment_status) : !dueStatuses.includes(p.payment_status)));

      if (!items.length) {
        list.innerHTML = `<div class="pm-empty">${bm.tab === 'due' ? t('Nothing due right now — you’re all settled.') : t('No paid payments yet.')}</div>`;
        return;
      }

      list.innerHTML = items.map((p) => {
        const icon = rowIcons[p.payment_status] || 'fa-file-invoice-dollar';
        const cls = (statusMeta[p.payment_status] || {}).cls || 'info';
        const showPayBtn = dueStatuses.includes(p.payment_status);
        return `
          <div class="pm-row" data-bm-id="${p.id}">
            <div class="pm-row-icon ${cls}"><i class="fa-solid ${icon}"></i></div>
            <div class="pm-row-body">
              <div class="pm-row-plan">${esc(p.plan || t('Subscription'))}</div>
              <div class="pm-row-meta">${fmtDate(p.created_at)}${p.billing_cycle ? ' · ' + esc(p.billing_cycle) : ''}</div>
            </div>
            ${badgeHtml(p.payment_status)}
            <div class="pm-row-amount">${money(p.amount, p.currency)}</div>
            ${showPayBtn
              ? `<button class="bm-btn bm-btn-primary pm-row-pay-btn" data-bm-pay="${p.id}" type="button"><i class="fa-solid fa-credit-card"></i> ${t('Pay')}</button>`
              : `<div class="pm-row-chevron"><i class="fa-solid fa-chevron-right"></i></div>`}
          </div>`;
      }).join('');

      list.querySelectorAll('[data-bm-id]').forEach((row) => {
        row.addEventListener('click', (e) => {
          if (e.target.closest('[data-bm-pay]')) return;
          showDetail(Number(row.dataset.bmId));
        });
      });
      list.querySelectorAll('[data-bm-pay]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          rowPayNow(Number(btn.dataset.bmPay), btn);
        });
      });
    }

    async function rowPayNow(id, btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

      const res = await API.startPaymentCheckout(id);
      const checkoutUrl = res.body?.data?.checkout_url;
      if (res.status !== 200 || !checkoutUrl) {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-credit-card"></i> ${t('Pay')}`;
        showAlert($('bm-alert'), res.body?.message || t('Could not start checkout. Please try again.'));
        return;
      }

      window.electronAPI.openExternal(checkoutUrl);
      btn.disabled = false;
      btn.innerHTML = `<i class="fa-solid fa-arrows-rotate"></i> ${t('Check status')}`;
      btn.onclick = async (e) => {
        e.stopPropagation();
        const chk = await API.paymentStatus(id);
        if (chk.status === 200 && chk.body?.data?.payment_status === 'succeeded') {
          await refresh();
        } else {
          showAlert($('bm-alert'), t('Payment not confirmed yet — finish it in the browser window, then check again.'));
        }
      };
    }

    async function showDetail(id) {
      $('bm-list-panel').style.display = 'none';
      $('bm-detail-panel').style.display = '';
      $('bm-detail-body').innerHTML = `<div class="pm-loading"><i class="fa-solid fa-spinner fa-spin"></i> ${t('Loading…')}</div>`;

      const res = await API.paymentDetail(id);
      if (res.status !== 200) {
        $('bm-detail-body').innerHTML = `<div class="pm-empty">${esc(res.body?.message || t('Could not load this payment.'))}</div>`;
        return;
      }
      renderDetail(res.body?.data || {});
    }

    function renderDetail(p) {
      const rows = [
        [t('Plan'), esc(p.plan || t('Subscription'))],
        [t('Status'), badgeHtml(p.payment_status)],
        [t('Amount'), esc(money(p.amount, p.currency))],
        [t('Billing cycle'), esc(p.billing_cycle ? p.billing_cycle[0].toUpperCase() + p.billing_cycle.slice(1) : '—')],
        [t('Payment method'), esc(p.gateway ? p.gateway[0].toUpperCase() + p.gateway.slice(1) : '—')],
        [t('Paid on'), esc(p.paid_at ? fmtDate(p.paid_at, true) : '—')],
      ];
      if (p.current_period_end) rows.push([t('Next renewal'), esc(fmtDate(p.current_period_end))]);
      if (p.failure_reason) rows.push([t('Failure reason'), esc(p.failure_reason)]);
      rows.push([t('Created'), esc(fmtDate(p.created_at, true))]);

      const rowsHtml = rows.map(([label, value]) => `
        <div class="pm-detail-row">
          <span class="pm-detail-label">${label}</span>
          <span class="pm-detail-value">${value}</span>
        </div>`).join('');

      const canPay = dueStatuses.includes(p.payment_status);
      const canDownload = p.payment_status === 'succeeded';

      $('bm-detail-body').innerHTML = `
        ${rowsHtml}
        <div id="bm-detail-alert" class="bm-alert" style="display:none;margin-top:14px"></div>
        <div class="pm-detail-actions">
          ${canPay ? `<button class="bm-btn bm-btn-primary" id="bm-pay-now" type="button"><i class="fa-solid fa-credit-card"></i> ${t('Pay Now')}</button>` : ''}
          ${canDownload ? `<button class="bm-btn bm-btn-outline" id="bm-download-receipt" type="button"><i class="fa-solid fa-download"></i> ${t('Download Invoice')}</button>` : ''}
        </div>`;

      if (canPay) $('bm-pay-now').addEventListener('click', () => payNow(p.id));
      if (canDownload) $('bm-download-receipt').addEventListener('click', () => downloadReceipt(p.id));
    }

    async function payNow(id) {
      const btn = $('bm-pay-now');
      const alertEl = $('bm-detail-alert');
      alertEl.style.display = 'none';
      if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Starting checkout…')}`; }

      const res = await API.startPaymentCheckout(id);
      const checkoutUrl = res.body?.data?.checkout_url;
      if (res.status !== 200 || !checkoutUrl) {
        showAlert(alertEl, res.body?.message || t('Could not start checkout. Please try again.'));
        if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fa-solid fa-credit-card"></i> ${t('Pay Now')}`; }
        return;
      }

      window.electronAPI.openExternal(checkoutUrl);
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-arrows-rotate"></i> ${t('Check payment status')}`;
        btn.onclick = () => checkPaymentStatus(id);
      }
      showAlert(alertEl, t('Complete the payment in your browser, then click "Check payment status".'));
    }

    async function checkPaymentStatus(id) {
      const alertEl = $('bm-detail-alert');
      const res = await API.paymentStatus(id);
      if (res.status === 200 && res.body?.data?.payment_status === 'succeeded') {
        await refresh();
        await showDetail(id);
        return;
      }
      showAlert(alertEl, t('Payment not confirmed yet — finish it in the browser window, then check again.'));
    }

    async function downloadReceipt(id) {
      const btn = $('bm-download-receipt');
      const alertEl = $('bm-detail-alert');
      if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Downloading…')}`; }

      const res = await window.electronAPI.downloadFile(`/auth/payment/${id}/receipt`, `invoice-${id}.pdf`);

      if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fa-solid fa-download"></i> ${t('Download Invoice')}`; }
      if (res.canceled) return;
      if (res.status !== 200) showAlert(alertEl, res.message || t('Could not download the invoice.'));
    }

    requestAnimationFrame(() => el.classList.add('open'));
    $('bm-list').innerHTML = `<div class="pm-loading"><i class="fa-solid fa-spinner fa-spin"></i> ${t('Loading billing history…')}</div>`;
    refresh();
  }

  window.openBillingWindow = openBillingWindow;

  // ── Settings window (Business profile + Accounts) ───────────────────────
  // Trimmed version of the full desktop app's Settings modal, backed by the
  // same Modules/Pos `online/settings` endpoint (Modules/Pos/routes/api.php).
  function openSettingsWindow() {
    if ($('sm-backdrop')) return;

    const returnFocusTo = document.activeElement;
    let logoUrl = '';
    let logoBusy = false;

    const el = document.createElement('div');
    el.className = 'sm-backdrop';
    el.id = 'sm-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'sm-title');
    el.innerHTML = `
      <div class="sm-card">
        <div class="sm-head">
          <span class="sm-head-icon"><i class="fa-solid fa-gear"></i></span>
          <div class="sm-head-text">
            <h2 id="sm-title">${t('Settings')}</h2>
            <p>${t('Manage your business and POS settings')}</p>
          </div>
          <button class="sm-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="sm-alert" class="bm-alert" style="display:none"></div>
        <div class="sm-body">
          <nav class="sm-nav">
            <button class="sm-nav-item active" type="button" data-sm-tab="business"><i class="fa-solid fa-building"></i><span>${t('Business')}</span></button>
            <button class="sm-nav-item" type="button" data-sm-tab="general"><i class="fa-solid fa-sliders"></i><span>${t('General')}</span></button>
            <button class="sm-nav-item" type="button" data-sm-tab="accounts"><i class="fa-solid fa-building-columns"></i><span>${t('Accounts')}</span></button>
            <button class="sm-nav-item" type="button" data-sm-tab="receipt"><i class="fa-solid fa-receipt"></i><span>${t('Receipt Setting')}</span></button>
            <button class="sm-nav-item" type="button" data-sm-tab="invoice-setup"><i class="fa-solid fa-file-invoice"></i><span>${t('Invoice Setup')}</span></button>
          </nav>
          <div class="sm-content">
            <div class="sm-panel" id="sm-tab-business">
              <div class="sm-section-label"><i class="fa-solid fa-building"></i> ${t('Business Profile')}</div>
              <div class="sm-field-row">
                <label class="sm-field-label" for="sm-biz-name">${t('Business Name')}</label>
                <input type="text" id="sm-biz-name" class="sm-input" placeholder="${t('Your business name')}">
              </div>
              <div class="sm-field-row">
                <label class="sm-field-label">${t('Business Logo')}</label>
                <div class="sm-logo-row">
                  <div class="sm-logo-preview" id="sm-logo-preview"><i class="fa-solid fa-image"></i></div>
                  <div class="sm-logo-btns">
                    <button class="bm-btn bm-btn-outline" id="sm-logo-choose" type="button"><i class="fa-solid fa-folder-open"></i> ${t('Choose image')}</button>
                    <button class="bm-btn bm-btn-outline sm-logo-remove" id="sm-logo-remove" type="button" title="${t('Remove logo')}" style="display:none"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </div>
              </div>
              <div class="sm-field-2col">
                <div>
                  <label class="sm-field-label" for="sm-currency">${t('Currency')}</label>
                  <input type="text" id="sm-currency" class="sm-input" maxlength="10" placeholder="e.g. USD, EUR, LKR">
                  <span class="sm-hint">${t('3-letter currency code')}</span>
                </div>
                <div>
                  <label class="sm-field-label" for="sm-timezone">${t('Timezone')}</label>
                  <input type="text" id="sm-timezone" class="sm-input" placeholder="e.g. Asia/Colombo">
                  <span class="sm-hint">${t('IANA timezone identifier')}</span>
                </div>
              </div>
              <div class="sm-field-row">
                <label class="sm-field-label">${t('Currency Position')}</label>
                <div class="sm-currpos-row">
                  <label class="sm-radio-card" id="sm-currpos-before-card">
                    <input type="radio" name="sm-currency-position" id="sm-currpos-before" value="before">
                    <span class="sm-radio-card-label">${t('Before amount')}</span>
                    <span class="sm-radio-card-preview" id="sm-currpos-before-preview">LKR 400.00</span>
                  </label>
                  <label class="sm-radio-card" id="sm-currpos-after-card">
                    <input type="radio" name="sm-currency-position" id="sm-currpos-after" value="after" checked>
                    <span class="sm-radio-card-label">${t('After amount')}</span>
                    <span class="sm-radio-card-preview" id="sm-currpos-after-preview">400.00 LKR</span>
                  </label>
                </div>
                <span class="sm-hint">${t('Where the currency code appears on prices, receipts and invoices')}</span>
              </div>
            </div>
            <div class="sm-panel" id="sm-tab-general" style="display:none">
              <div class="sm-section">
                <div class="sm-section-label"><i class="fa-solid fa-receipt"></i> ${t('Receipts')}</div>
                <div class="sm-field-row">
                  <label class="sm-field-label" for="sm-receipt-mode">${t('POS Receipt / Invoice Mode')}</label>
                  <select id="sm-receipt-mode" class="sm-select">
                    <option value="bill">${t('Bill Printing — thermal receipt after each sale')}</option>
                    <option value="invoice">${t('Invoice — create & print a formal invoice after each sale')}</option>
                  </select>
                  <span class="sm-hint">${t('Controls what is generated after a POS sale is completed')}</span>
                </div>
              </div>
              <div class="sm-section">
                <div class="sm-section-label"><i class="fa-solid fa-star"></i> ${t('Featured Items')}</div>
                <div class="sm-field-2col">
                  <div>
                    <label class="sm-field-label" for="sm-featured-products">${t('Featured products limit')}</label>
                    <input type="number" id="sm-featured-products" class="sm-input" min="0" max="200" placeholder="0">
                    <span class="sm-hint">${t('0 = disabled')}</span>
                  </div>
                  <div>
                    <label class="sm-field-label" for="sm-featured-categories">${t('Featured categories limit')}</label>
                    <input type="number" id="sm-featured-categories" class="sm-input" min="0" max="200" placeholder="0">
                    <span class="sm-hint">${t('0 = disabled')}</span>
                  </div>
                </div>
              </div>
              <div class="sm-section">
                <div class="sm-section-label"><i class="fa-solid fa-cash-register"></i> ${t('Checkout')}</div>
                <div class="sm-toggle-row">
                  <div class="sm-toggle-info">
                    <span class="sm-toggle-name">${t('Discount field')}</span>
                    <span class="sm-toggle-desc">${t('Show a discount % input in the checkout panel')}</span>
                  </div>
                  <label class="sm-switch"><input type="checkbox" id="sm-discount-field"><span class="sm-switch-track"></span></label>
                </div>
                <div class="sm-toggle-row">
                  <div class="sm-toggle-info">
                    <span class="sm-toggle-name">${t('Checkout confirmation')}</span>
                    <span class="sm-toggle-desc">${t('Show a summary dialog to confirm before completing every sale')}</span>
                  </div>
                  <label class="sm-switch"><input type="checkbox" id="sm-checkout-modal"><span class="sm-switch-track"></span></label>
                </div>
                <div class="sm-field-row">
                  <label class="sm-field-label" for="sm-stock-mode">${t('Stock batch selection')}</label>
                  <select id="sm-stock-mode" class="sm-select">
                    <option value="fifo">${t('Automatic — First In, First Out (FIFO)')}</option>
                    <option value="choose">${t('Choose batch manually at checkout')}</option>
                    <option value="last_price">${t('Use latest batch (last price update)')}</option>
                  </select>
                  <span class="sm-hint">${t('How the system picks which stock batch to deduct when a product has multiple batches')}</span>
                </div>
                <div class="sm-toggle-row">
                  <div class="sm-toggle-info">
                    <span class="sm-toggle-name">${t('Set price at checkout')}</span>
                    <span class="sm-toggle-desc">${t('Ask for a custom unit price before each product is added to the cart')}</span>
                  </div>
                  <label class="sm-switch"><input type="checkbox" id="sm-choose-price"><span class="sm-switch-track"></span></label>
                </div>
              </div>
            </div>
            <div class="sm-panel" id="sm-tab-accounts" style="display:none">
              <div class="sm-section-label"><i class="fa-solid fa-coins"></i> ${t('Payment Accounts')}</div>
              <div class="sm-field-row">
                <label class="sm-field-label" for="sm-settlement-mode">${t('Payment settlement mode')}</label>
                <select id="sm-settlement-mode" class="sm-select" disabled>
                  <option value="immediate">${t('Immediate — settle per sale')}</option>
                  <option value="end_of_day" selected>${t('End of day — batch settlement')}</option>
                </select>
                <span class="sm-hint">${t('Payments are settled at the end of day. This setting cannot be changed.')}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="sm-foot">
          <button class="bm-btn bm-btn-outline" id="sm-cancel" type="button">${t('Cancel')}</button>
          <button class="bm-btn bm-btn-primary" id="sm-save" type="button"><i class="fa-solid fa-floppy-disk"></i> ${t('Save settings')}</button>
        </div>
      </div>`;
    document.body.appendChild(el);

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
    }

    el.querySelector('.sm-close').addEventListener('click', close);
    el.querySelector('#sm-cancel').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    const tabs = Array.from(el.querySelectorAll('.sm-nav-item'));
    tabs.forEach((btn) => btn.addEventListener('click', () => {
      if (btn.dataset.smTab === 'receipt') {
        close();
        openReceiptLayoutWindow();
        return;
      }
      if (btn.dataset.smTab === 'invoice-setup') {
        close();
        openInvoiceSetupWindow();
        return;
      }
      tabs.forEach((b) => b.classList.toggle('active', b === btn));
      el.querySelectorAll('.sm-panel').forEach((p) => { p.style.display = 'none'; });
      $(`sm-tab-${btn.dataset.smTab}`).style.display = '';
    }));

    function currencyCode() {
      return ($('sm-currency').value || 'LKR').trim().toUpperCase() || 'LKR';
    }
    function renderCurrPos() {
      const before = $('sm-currpos-before').checked;
      $('sm-currpos-before-card').classList.toggle('selected', before);
      $('sm-currpos-after-card').classList.toggle('selected', !before);
      $('sm-currpos-before-preview').textContent = `${currencyCode()} 400.00`;
      $('sm-currpos-after-preview').textContent = `400.00 ${currencyCode()}`;
    }
    el.querySelectorAll('input[name="sm-currency-position"]').forEach((r) => r.addEventListener('change', renderCurrPos));
    $('sm-currency').addEventListener('input', renderCurrPos);

    function renderLogo() {
      $('sm-logo-preview').innerHTML = logoUrl ? `<img src="${esc(logoUrl)}" alt="${t('Logo')}">` : '<i class="fa-solid fa-image"></i>';
      $('sm-logo-remove').style.display = logoUrl ? '' : 'none';
    }

    function showAlert(msg, tone) {
      const a = $('sm-alert');
      a.textContent = msg;
      a.style.background = tone === 'success' ? '#dcfce7' : '#fef2f2';
      a.style.color = tone === 'success' ? '#15803d' : '#b91c1c';
      a.style.display = 'block';
    }

    async function chooseLogo() {
      if (logoBusy) return;
      const result = await window.electronAPI.showOpenDialog({
        title: t('Select Image'),
        filters: [{ name: t('Images'), extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp'] }],
        properties: ['openFile'],
      });
      if (result.canceled || !result.filePaths.length) return;

      logoBusy = true;
      const btn = $('sm-logo-choose');
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Uploading…')}`;

      const res = await window.electronAPI.apiUpload('/online/file-manager/upload', result.filePaths[0]);

      btn.disabled = false;
      btn.innerHTML = originalHtml;
      logoBusy = false;

      if (res.status === 201 && res.body?.data?.length) {
        logoUrl = res.body.data[0].url;
        renderLogo();
      } else {
        showAlert(res.body?.message || t('Could not upload the logo.'));
      }
    }

    $('sm-logo-choose').addEventListener('click', chooseLogo);
    $('sm-logo-remove').addEventListener('click', () => { logoUrl = ''; renderLogo(); });

    async function load() {
      const res = await API.settingsGet();
      if (res.status !== 200) {
        showAlert(res.body?.message || t('Could not load settings.'));
        return;
      }
      const d = res.body?.data || {};
      $('sm-biz-name').value = d.business_name || '';
      $('sm-currency').value = d.currency || '';
      $('sm-timezone').value = d.timezone || '';
      $(d.currency_position === 'before' ? 'sm-currpos-before' : 'sm-currpos-after').checked = true;
      logoUrl = d.business_logo_url || '';
      renderLogo();
      renderCurrPos();
      $('sm-settlement-mode').value = 'end_of_day';
      $('sm-receipt-mode').value = d.receipt_mode || 'bill';
      $('sm-featured-products').value = d.featured_products_limit || 0;
      $('sm-featured-categories').value = d.featured_categories_limit || 0;
      $('sm-discount-field').checked = !!d.discount_field_enabled;
      $('sm-checkout-modal').checked = !!d.checkout_modal_enabled;
      $('sm-stock-mode').value = d.stock_selection_mode || 'fifo';
      $('sm-choose-price').checked = !!d.choose_price;
    }

    async function save() {
      const btn = $('sm-save');
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Saving…')}`;
      $('sm-alert').style.display = 'none';

      const payload = {
        business_name: $('sm-biz-name').value.trim(),
        currency: $('sm-currency').value.trim(),
        timezone: $('sm-timezone').value.trim(),
        currency_position: $('sm-currpos-before').checked ? 'before' : 'after',
        business_logo_url: logoUrl || '',
        payment_settlement_mode: 'end_of_day',
        receipt_mode: $('sm-receipt-mode').value,
        featured_products_limit: Number($('sm-featured-products').value) || 0,
        featured_categories_limit: Number($('sm-featured-categories').value) || 0,
        discount_field_enabled: $('sm-discount-field').checked,
        checkout_modal_enabled: $('sm-checkout-modal').checked,
        stock_selection_mode: $('sm-stock-mode').value,
        choose_price: $('sm-choose-price').checked,
      };

      const res = await API.settingsUpdate(payload);
      btn.disabled = false;
      btn.innerHTML = originalHtml;

      if (res.status !== 200) {
        showAlert(res.body?.message || t('Could not save settings.'));
        return;
      }
      if (payload.business_name) {
        await window.electronAPI.setConfig({ business_name: payload.business_name });
        $('um-biz').textContent = payload.business_name;
        $('um-biz-name').textContent = payload.business_name;
      }
      showAlert(t('Settings saved.'), 'success');
    }

    $('sm-save').addEventListener('click', save);

    requestAnimationFrame(() => el.classList.add('open'));
    load();
  }

  window.openSettingsWindow = openSettingsWindow;

  // ── Receipt Layout Editor window ────────────────────────────────────────
  // Design/update the printed-receipt layout: what shows on it, its logo,
  // header/footer text and print language. Backed by the same Modules/Pos
  // `online/settings` endpoint (Modules/Pos/routes/api.php) as the Business tab.
  const RECEIPT_PRINT_LANGUAGES = [
    { code: 'en', label: 'English' },
    { code: 'si', label: 'සිංහල' },
    { code: 'ta', label: 'தமிழ்' },
  ];

  // Fixed receipt labels per print language, so switching the Print Language
  // buttons visibly re-renders the preview (business text the user typed —
  // header/footer/address — is left as-is; only the receipt's own chrome changes).
  const RECEIPT_PREVIEW_STRINGS = {
    en: { receiptNo: 'Receipt #', date: 'Date', customer: 'Customer', cashier: 'Cashier', walkIn: 'Walk-in Customer', item: 'ITEM', qty: 'QTY', price: 'PRICE', total: 'TOTAL', paid: 'Paid (Cash)' },
    si: { receiptNo: 'රිසිට් #', date: 'දිනය', customer: 'පාරිභෝගිකයා', cashier: 'මුදල් අයකැමි', walkIn: 'සාමාන්‍ය පාරිභෝගිකයා', item: 'අයිතමය', qty: 'ගණන', price: 'මිල', total: 'එකතුව', paid: 'ගෙවූ මුදල' },
    ta: { receiptNo: 'ரசீது #', date: 'தேதி', customer: 'வாடிக்கையாளர்', cashier: 'காசாளர்', walkIn: 'பொது வாடிக்கையாளர்', item: 'பொருள்', qty: 'எண்', price: 'விலை', total: 'மொத்தம்', paid: 'செலுத்தியது' },
  };

  function openReceiptLayoutWindow() {
    if ($('rle-backdrop')) return;

    const returnFocusTo = document.activeElement;
    const state = {
      logoUrl: '',
      logoBusy: false,
      lang: 'en',
      businessName: '',
      currency: 'LKR',
      currencyPosition: 'after',
    };

    const el = document.createElement('div');
    el.className = 'rle-backdrop';
    el.id = 'rle-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'rle-title');
    el.innerHTML = `
      <div class="rle-card">
        <div class="rle-head">
          <span class="rle-head-icon"><i class="fa-solid fa-pen-to-square"></i></span>
          <div class="rle-head-text">
            <h2 id="rle-title">${t('Receipt Layout Editor')}</h2>
            <p>${t('Design and update how your printed receipts look.')}</p>
          </div>
          <button class="rle-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="rle-alert" class="bm-alert" style="display:none"></div>
        <div class="rle-body">
          <div class="rle-form">
            <div class="sm-field-row">
              <label class="sm-field-label">${t('Receipt Logo')}</label>
              <div class="sm-logo-row">
                <div class="sm-logo-preview" id="rle-logo-preview"><i class="fa-solid fa-image"></i></div>
                <div class="sm-logo-btns">
                  <button class="bm-btn bm-btn-outline" id="rle-logo-choose" type="button"><i class="fa-solid fa-folder-open"></i> ${t('Choose image')}</button>
                  <button class="bm-btn bm-btn-outline sm-logo-remove" id="rle-logo-remove" type="button" title="${t('Remove logo')}" style="display:none"><i class="fa-solid fa-trash"></i></button>
                </div>
              </div>
            </div>

            <div class="sm-section-label"><i class="fa-solid fa-building"></i> ${t('Business Info')}</div>
            <div class="sm-toggle-row">
              <div class="sm-toggle-info"><span class="sm-toggle-name">${t('Show business name')}</span></div>
              <label class="sm-switch"><input type="checkbox" id="rle-show-biz"><span class="sm-switch-track"></span></label>
            </div>
            <div class="sm-toggle-row">
              <div class="sm-toggle-info"><span class="sm-toggle-name">${t('Show address')}</span></div>
              <label class="sm-switch"><input type="checkbox" id="rle-show-addr"><span class="sm-switch-track"></span></label>
            </div>
            <div class="sm-field-row">
              <textarea id="rle-address" class="sm-input rle-textarea" rows="2" placeholder="${t('Street, City, Phone...')}"></textarea>
            </div>

            <div class="sm-section-label"><i class="fa-solid fa-heading"></i> ${t('Header Text')}</div>
            <div class="sm-field-row">
              <textarea id="rle-header" class="sm-input rle-textarea" rows="2" placeholder="${t('Tagline, website, hours...')}"></textarea>
            </div>

            <div class="sm-section-label"><i class="fa-solid fa-comment"></i> ${t('Footer / Thank-you')}</div>
            <div class="sm-field-row">
              <textarea id="rle-footer" class="sm-input rle-textarea" rows="2" placeholder="${t('Thank you for your purchase!')}"></textarea>
            </div>

            <div class="sm-section-label"><i class="fa-solid fa-eye"></i> ${t('Show on every receipt')}</div>
            <div class="sm-toggle-row">
              <div class="sm-toggle-info">
                <span class="sm-toggle-name">${t('Cashier name')}</span>
                <span class="sm-toggle-desc">${t('Always included on receipts')}</span>
              </div>
              <label class="sm-switch"><input type="checkbox" id="rle-show-cashier" checked disabled><span class="sm-switch-track"></span></label>
            </div>
            <div class="sm-toggle-row">
              <div class="sm-toggle-info"><span class="sm-toggle-name">${t('Payment details')}</span></div>
              <label class="sm-switch"><input type="checkbox" id="rle-show-payment"><span class="sm-switch-track"></span></label>
            </div>

            <div class="sm-section-label"><i class="fa-solid fa-language"></i> ${t('Print Language')}</div>
            <div class="rle-lang-row" id="rle-lang-row">
              ${RECEIPT_PRINT_LANGUAGES.map((l) => `<button class="rle-lang-btn" type="button" data-lang="${l.code}">${esc(l.label)}</button>`).join('')}
            </div>
          </div>

          <div class="rle-preview-pane">
            <div class="rle-preview-label">${t('Live Preview')}</div>
            <div class="rle-paper" id="rle-paper"></div>
          </div>
        </div>
        <div class="rle-foot">
          <button class="bm-btn bm-btn-outline" id="rle-cancel" type="button">${t('Cancel')}</button>
          <button class="bm-btn bm-btn-primary" id="rle-save" type="button"><i class="fa-solid fa-floppy-disk"></i> ${t('Save layout')}</button>
        </div>
      </div>`;
    document.body.appendChild(el);

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
    }

    el.querySelector('.rle-close').addEventListener('click', close);
    $('rle-cancel').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    function showAlert(msg, tone) {
      const a = $('rle-alert');
      a.textContent = msg;
      a.style.background = tone === 'success' ? '#dcfce7' : '#fef2f2';
      a.style.color = tone === 'success' ? '#15803d' : '#b91c1c';
      a.style.display = 'block';
    }

    function renderLogo() {
      $('rle-logo-preview').innerHTML = state.logoUrl ? `<img src="${esc(state.logoUrl)}" alt="${t('Logo')}">` : '<i class="fa-solid fa-image"></i>';
      $('rle-logo-remove').style.display = state.logoUrl ? '' : 'none';
    }

    async function chooseLogo() {
      if (state.logoBusy) return;
      const result = await window.electronAPI.showOpenDialog({
        title: t('Select Image'),
        filters: [{ name: t('Images'), extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp'] }],
        properties: ['openFile'],
      });
      if (result.canceled || !result.filePaths.length) return;

      state.logoBusy = true;
      const btn = $('rle-logo-choose');
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Uploading…')}`;

      const res = await window.electronAPI.apiUpload('/online/file-manager/upload', result.filePaths[0]);

      btn.disabled = false;
      btn.innerHTML = originalHtml;
      state.logoBusy = false;

      if (res.status === 201 && res.body?.data?.length) {
        state.logoUrl = res.body.data[0].url;
        renderLogo();
        renderPreview();
      } else {
        showAlert(res.body?.message || t('Could not upload the logo.'));
      }
    }

    $('rle-logo-choose').addEventListener('click', chooseLogo);
    $('rle-logo-remove').addEventListener('click', () => { state.logoUrl = ''; renderLogo(); renderPreview(); });

    function setLang(code) {
      state.lang = code;
      Array.from(el.querySelectorAll('.rle-lang-btn')).forEach((b) => b.classList.toggle('active', b.dataset.lang === code));
      renderPreview();
    }
    $('rle-lang-row').addEventListener('click', (e) => {
      const btn = e.target.closest('.rle-lang-btn');
      if (!btn) return;
      setLang(btn.dataset.lang);
    });

    function money(amount) {
      const v = (Number(amount) || 0).toFixed(2);
      return state.currencyPosition === 'before' ? `${state.currency} ${v}` : `${v} ${state.currency}`;
    }

    function renderPreview() {
      const showBiz = $('rle-show-biz').checked;
      const showAddr = $('rle-show-addr').checked;
      const address = $('rle-address').value.trim();
      const header = $('rle-header').value.trim();
      const footer = $('rle-footer').value.trim();
      const showPayment = $('rle-show-payment').checked;
      const L = RECEIPT_PREVIEW_STRINGS[state.lang] || RECEIPT_PREVIEW_STRINGS.en;
      const dateLocale = { en: 'en-US', si: 'si-LK', ta: 'ta-LK' }[state.lang] || undefined;

      const rows = [
        ['Product One', 2, 25, 50],
        ['Product Two', 1, 75, 75],
        ['Product Three', 3, 10, 30],
      ];
      const subtotal = rows.reduce((sum, r) => sum + r[3], 0);

      const itemsHtml = rows.map(([name, qty, price, total]) => `
        <div class="rle-item-row">
          <span class="rle-item-name">${esc(name)}</span>
          <span>${qty}</span>
          <span>${price.toFixed(2)}</span>
          <span>${total.toFixed(2)}</span>
        </div>`).join('');

      $('rle-paper').innerHTML = `
        ${state.logoUrl ? `<div class="rle-paper-logo"><img src="${esc(state.logoUrl)}" alt=""></div>` : ''}
        ${showBiz ? `<div class="rle-paper-biz">${esc(state.businessName || t('Your Business'))}</div>` : ''}
        ${showAddr && address ? `<div class="rle-paper-addr">${esc(address)}</div>` : ''}
        ${header ? `<div class="rle-paper-header">${esc(header)}</div>` : ''}
        <div class="rle-paper-rule"></div>
        <div class="rle-kv"><span>${esc(L.receiptNo)}</span><span>INV-0001</span></div>
        <div class="rle-kv"><span>${esc(L.date)}</span><span>${new Date().toLocaleString(dateLocale, { dateStyle: 'short', timeStyle: 'short' })}</span></div>
        <div class="rle-kv"><span>${esc(L.customer)}</span><span>${esc(L.walkIn)}</span></div>
        <div class="rle-kv"><span>${esc(L.cashier)}</span><span>${esc(L.cashier)}</span></div>
        <div class="rle-paper-rule"></div>
        <div class="rle-item-row rle-item-head">
          <span class="rle-item-name">${esc(L.item)}</span><span>${esc(L.qty)}</span><span>${esc(L.price)}</span><span>${esc(L.total)}</span>
        </div>
        ${itemsHtml}
        <div class="rle-paper-rule"></div>
        <div class="rle-kv rle-kv-total"><span>${esc(L.total)}</span><span>${money(subtotal)}</span></div>
        ${showPayment ? `<div class="rle-kv"><span>${esc(L.paid)}</span><span>${money(subtotal)}</span></div>` : ''}
        ${footer ? `<div class="rle-paper-footer">${esc(footer)}</div>` : ''}
      `;
    }

    ['rle-address', 'rle-header', 'rle-footer'].forEach((id) => $(id).addEventListener('input', renderPreview));
    ['rle-show-biz', 'rle-show-addr', 'rle-show-payment'].forEach((id) => $(id).addEventListener('change', renderPreview));

    async function load() {
      const res = await API.settingsGet();
      if (res.status !== 200) {
        showAlert(res.body?.message || t('Could not load settings.'));
        return;
      }
      const d = res.body?.data || {};
      state.logoUrl = d.receipt_logo_url || '';
      state.businessName = d.business_name || '';
      state.currency = (d.currency || 'LKR').toUpperCase();
      state.currencyPosition = d.currency_position === 'before' ? 'before' : 'after';
      renderLogo();

      $('rle-show-biz').checked = d.show_business_name !== false;
      $('rle-show-addr').checked = !!d.show_business_address;
      $('rle-address').value = d.receipt_address_line || '';
      $('rle-header').value = d.receipt_header || '';
      $('rle-footer').value = d.receipt_footer || '';
      $('rle-show-payment').checked = d.show_account_info !== false;
      setLang(['en', 'si', 'ta'].includes(d.receipt_language) ? d.receipt_language : 'en');
    }

    async function save() {
      const btn = $('rle-save');
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Saving…')}`;
      $('rle-alert').style.display = 'none';

      const payload = {
        receipt_logo_url: state.logoUrl || '',
        show_business_name: $('rle-show-biz').checked,
        show_business_address: $('rle-show-addr').checked,
        receipt_address_line: $('rle-address').value.trim(),
        receipt_header: $('rle-header').value.trim(),
        receipt_footer: $('rle-footer').value.trim(),
        show_account_info: $('rle-show-payment').checked,
        receipt_language: state.lang,
      };

      const res = await API.settingsUpdate(payload);
      btn.disabled = false;
      btn.innerHTML = originalHtml;

      if (res.status !== 200) {
        showAlert(res.body?.message || t('Could not save the receipt layout.'));
        return;
      }
      showAlert(t('Receipt layout saved.'), 'success');
    }

    $('rle-save').addEventListener('click', save);

    requestAnimationFrame(() => el.classList.add('open'));
    load();
  }

  window.openReceiptLayoutWindow = openReceiptLayoutWindow;

  // ── Invoice Setup window ────────────────────────────────────────────────
  // Mirrors the full desktop app's Invoice Setup screen (electron_app
  // renderer/js/app.js `_isetup*` + `_iTPL*` functions, renderer/index.html
  // `#isetup-modal`): same 3-column layout (adjustments left, zoomable live
  // preview centre, templates + accent colour right) and the same 5 real
  // template renderers, ported here verbatim minus letterhead and the
  // printer-type/thermal-roll option (POS Lite invoices always print on a
  // sheet — thermal is already covered by the separate Receipt Setting for
  // Bill mode). Backed by the Modules/Pos `online/invoice-setup` endpoint,
  // which delegates to Modules\Sales\Services\InvoiceAppearanceService.
  const INV_TEMPLATES = [
    { id: 'classic',   name: 'Classic',     desc: 'Traditional bordered table',                swatch: '#1d4ed8', accent: '#1d4ed8' },
    { id: 'bold',      name: 'Bold Banner', desc: 'Full-width colour header, large number',     swatch: '#e11d48', accent: '#e11d48' },
    { id: 'minimal',   name: 'Minimal',     desc: 'Pure typography, no fills or colour blocks', swatch: '#374151', accent: '#374151' },
    { id: 'compact',   name: 'Compact',     desc: 'Card-style info grid with teal accent',      swatch: '#0891b2', accent: '#0891b2' },
    { id: 'executive', name: 'Executive',   desc: 'Dark luxury header with gold accent trim',   swatch: '#1e1b4b', accent: '#c7a84f' },
  ];
  const INV_COLOR_PRESETS = ['#1d4ed8', '#e11d48', '#0891b2', '#059669', '#7c3aed', '#c7a84f', '#ea580c', '#0f172a', '#374151', '#db2777'];
  const INV_PAPER_MM = {
    a4:     { w: 210, h: 297 },
    a5:     { w: 148, h: 210 },
    letter: { w: 216, h: 279 },
    legal:  { w: 216, h: 356 },
  };
  const INV_MM_PX = 96 / 25.4;

  function iswTplById(id) {
    return INV_TEMPLATES.find((tp) => tp.id === id) || INV_TEMPLATES[0];
  }

  function iswGeom(paperSize, orientation) {
    const base = INV_PAPER_MM[paperSize] || INV_PAPER_MM.a4;
    const land = orientation === 'landscape';
    const wMm = land ? base.h : base.w;
    const hMm = land ? base.w : base.h;
    return { wMm, hMm, wPx: Math.round(wMm * INV_MM_PX), hPx: Math.round(hMm * INV_MM_PX) };
  }

  // Shared header-layout override, applied uniformly to every template's
  // two-column header container (business block + invoice-number block).
  function iswHdrCss(headerLayout) {
    const sel = '.top,.banner,.hdr-top,.topbar';
    if (headerLayout === 'num-left') return `${sel}{flex-direction:row-reverse}`;
    if (headerLayout === 'num-center') return `${sel}{justify-content:center;gap:48px}`;
    return '';
  }

  // @page rule so the actual print output matches the configured paper size.
  function iswPageAtCss(geom) {
    return `@page{size:${geom.wMm}mm ${geom.hMm}mm;margin:0}`;
  }

  function iswDummy(ctx) {
    return {
      biz: esc(ctx.biz || t('Your Business')),
      addr: esc(ctx.addr || '10 Innovation Way, Floor 4'),
      c: ctx.cur ? ' ' + ctx.cur : '',
    };
  }

  // Normalizes the real-invoice content a template needs (invoice number, dates,
  // bill-to, item rows, totals, notes, footer), falling back to this file's
  // original hard-coded demo content field-by-field. `!= null` (not `||`) is used
  // throughout so a real caller passing an explicit '' (e.g. "no notes on this
  // sale") suppresses the field instead of falling back to demo filler text —
  // only an omitted (undefined) field pulls in the demo default. This keeps the
  // Invoice Setup wizard's own live preview (which never sets these ctx fields)
  // pixel-identical to before, while POS Lite's real sale-completion invoice
  // (js/pos.js) supplies every field explicitly.
  function iswDocData(ctx, c, opts = {}) {
    const nz = (v, def) => (v != null ? v : def);
    const idx = (n) => (opts.italicIndex ? `<i>${n}</i>` : `${n}`);

    const defaultItems = [
      { name: 'Brand Identity Design', desc: 'Logo, colour palette, typography kit', qty: '1', price: `1,800.00${c}`, total: `1,800.00${c}` },
      { name: 'UI / UX Design', desc: '10 screens, mobile-first, Figma source files', qty: '1', price: `3,500.00${c}`, total: `3,500.00${c}` },
      { name: 'Frontend Development', desc: 'React, Next.js, Tailwind CSS — 40 hrs', qty: '40', price: `85.00${c}`, total: `3,400.00${c}` },
      { name: 'SEO Optimisation', desc: 'On-page audit + 3-month strategy', qty: '1', price: `650.00${c}`, total: `650.00${c}` },
      { name: 'Monthly Hosting &amp; Support', desc: 'VPS, monitoring, daily backups', qty: '3', price: `120.00${c}`, total: `360.00${c}` },
    ];
    const items = ctx.items || defaultItems;
    const itemRows = items.map((it, i) => `<tr><td class="n">${idx(i + 1)}</td><td><b>${it.name}</b>${it.desc ? `<span class="ds">${it.desc}</span>` : ''}</td><td class="r">${it.qty}</td><td class="r">${it.price}</td><td class="r b">${it.total}</td></tr>`).join('');

    const defaultTotalsLines = [
      { label: 'Subtotal', value: `9,710.00${c}` },
      { label: 'Discount (5%)', value: `−485.50${c}`, color: '#ef4444' },
      { label: 'Tax (15%)', value: `+1,383.67${c}` },
    ];
    const totalsLines = ctx.totalsLines || defaultTotalsLines;
    const totalsRows = totalsLines.map((l) => `<div class="tr"><span>${l.label}</span><span${l.color ? ` style="color:${l.color}"` : ''}>${l.value}</span></div>`).join('');

    return {
      invNo: nz(ctx.invoiceNumber, 'INV-0024'),
      issueDate: nz(ctx.issueDate, '01 Aug 2026'),
      dueDate: nz(ctx.dueDate, '15 Aug 2026'),
      statusLabel: nz(ctx.statusLabel, 'Paid'),
      statusColor: nz(ctx.statusColor, nz(opts.statusColorDefault, '#15803d')),
      billToName: nz(ctx.billToName, 'Acme Corporation'),
      billToLines: nz(ctx.billToLines, 'Jennifer Walters<br>45 Commerce Drive, Suite 3, New York NY 10001'),
      bizContact: nz(ctx.bizContact, 'invoices@example.com · +1 555 000-0001'),
      itemRows,
      totalsRows,
      grandLabel: nz(ctx.grandLabel, nz(opts.grandLabelDefault, 'Total Due')),
      grandValue: nz(ctx.grandValue, `10,608.17${c}`),
      notesHtml: nz(ctx.notesHtml, nz(opts.notesDefault, 'Payment due within 14 days.<br>Bank transfer only — details on file.<br>Thank you for your business!')),
      footerRight: nz(ctx.footerRight, ''),
    };
  }

  // ── Template 1: Classic ── traditional bordered table ────────────────────
  function iTplClassic(ctx) {
    const a = ctx.a, mg = ctx.mg, { biz, addr, c } = iswDummy(ctx);
    const d = iswDocData(ctx, c);
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:${ctx.geomW}px;position:relative;padding:${mg.top}mm ${mg.right}mm ${mg.bot}mm ${mg.left}mm}
.top{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:20px;border-bottom:2.5px solid ${a};margin-bottom:24px}
.bn{font-size:21px;font-weight:900;color:${a}}.bi{font-size:10px;color:#64748b;margin-top:5px;line-height:1.6}
.it{font-size:30px;font-weight:900;text-transform:uppercase;color:${a};text-align:right}
.in{font-size:12px;color:#64748b;text-align:right;margin-top:4px}
.meta{display:grid;grid-template-columns:1fr auto;gap:24px;margin-bottom:22px}
.btl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:6px}
.btn{font-size:14px;font-weight:800}.bti{font-size:11px;color:#64748b;margin-top:3px;line-height:1.5}
.dts{min-width:205px}
.dr{display:flex;justify-content:space-between;font-size:11px;padding:6px 0;border-bottom:1px dashed #e2e8f0}
.dr:last-child{border-bottom:none}.dk{color:#94a3b8}.dv{font-weight:700}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
thead th{background:${a};color:#fff;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:9px 10px}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:8px 10px;border-bottom:1px solid #e2e8f0}
tbody tr:nth-child(even) td{background:#f8fafc}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 248px;gap:20px}
.nb{padding:13px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:5px 0;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{font-size:15px;font-weight:900;border-top:2.5px solid ${a};margin-top:4px;padding-top:9px}
.gr span:last-child{color:${a}}
.ft{margin-top:22px;padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:10px;color:#94a3b8}
${ctx.hdrCss}</style></head><body><div class="pg">
<div class="top">
  <div><div class="bn">${biz}</div><div class="bi">${addr}${d.bizContact ? '<br>' + d.bizContact : ''}</div></div>
  <div><div class="it">Invoice</div><div class="in">${d.invNo} · ${d.issueDate}</div></div>
</div>
<div class="meta">
  <div><div class="btl">Billed To</div><div class="btn">${d.billToName}</div><div class="bti">${d.billToLines}</div></div>
  <div class="dts">
    <div class="dr"><span class="dk">Issue Date</span><span class="dv">${d.issueDate}</span></div>
    <div class="dr"><span class="dk">Due Date</span><span class="dv">${d.dueDate}</span></div>
    <div class="dr"><span class="dk">Status</span><span class="dv" style="color:${d.statusColor}">${d.statusLabel}</span></div>
  </div>
</div>
<table>
  <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:100px">Unit Price</th><th class="r" style="width:100px">Total</th></tr></thead>
  <tbody>${d.itemRows}</tbody>
</table>
<div class="bot">
  <div class="nb"><div class="nl">Notes &amp; Terms</div><div class="nt">${d.notesHtml}</div></div>
  <div>
    ${d.totalsRows}
    <div class="tr gr"><span>${d.grandLabel}</span><span>${d.grandValue}</span></div>
  </div>
</div>
<div class="ft"><span>${d.invNo} · ${biz}</span><span>${d.footerRight || biz}</span></div>
</div></body></html>`;
  }

  // ── Template 2: Bold Banner ── full-width colour header ──────────────────
  function iTplBold(ctx) {
    const a = ctx.a, mg = ctx.mg, { biz, addr, c } = iswDummy(ctx);
    const d = iswDocData(ctx, c);
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:${ctx.geomW}px;${ctx.geomMinH}position:relative;display:flex;flex-direction:column}
.banner{background:${a};padding:28px ${mg.right}mm 26px ${mg.left}mm;display:flex;justify-content:space-between;align-items:flex-end}
.b-biz{font-size:20px;font-weight:900;color:#fff}
.b-addr{font-size:10px;color:rgba(255,255,255,.7);margin-top:4px;line-height:1.5}
.b-num{font-size:38px;font-weight:900;color:#fff;letter-spacing:-.02em;line-height:1;text-align:right}
.b-lbl{font-size:10px;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.15em;font-weight:700;margin-bottom:4px;text-align:right}
.body{padding:22px ${mg.right}mm ${mg.bot}mm ${mg.left}mm;flex:1}
.cards{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
.card{padding:13px 15px;border:1.5px solid #e2e8f0;border-radius:7px}
.cl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.cn{font-size:14px;font-weight:800;margin-bottom:3px}
.ci{font-size:11px;color:#64748b;line-height:1.5}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
thead th{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;padding:8px 0;border-bottom:2px solid ${a}}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:9px 0;border-bottom:1px solid #f1f5f9}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 255px;gap:18px}
.nb{padding:13px;border:1.5px solid #e2e8f0;border-radius:7px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55}
.tc{border:1.5px solid #e2e8f0;border-radius:7px;overflow:hidden}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:8px 13px;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{background:${a};color:#fff!important;font-weight:900;font-size:14px}
.gr span{color:#fff!important}
${ctx.hdrCss}</style></head><body><div class="pg">
<div class="banner">
  <div><div class="b-biz">${biz}</div><div class="b-addr">${addr}${d.bizContact ? '<br>' + d.bizContact : ''}</div></div>
  <div><div class="b-lbl">Invoice</div><div class="b-num">${d.invNo}</div></div>
</div>
<div class="body">
  <div class="cards">
    <div class="card"><div class="cl">Billed To</div><div class="cn">${d.billToName}</div><div class="ci">${d.billToLines}</div></div>
    <div class="card"><div class="cl">Invoice Details</div><div class="ci" style="line-height:1.9"><b>Issue Date</b> &nbsp; ${d.issueDate}<br><b>Due Date</b> &nbsp;&nbsp; ${d.dueDate}<br><b>Status</b> &nbsp;&nbsp;&nbsp;&nbsp; <span style="color:${d.statusColor};font-weight:700">${d.statusLabel}</span></div></div>
  </div>
  <table>
    <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:100px">Unit Price</th><th class="r" style="width:100px">Total</th></tr></thead>
    <tbody>${d.itemRows}</tbody>
  </table>
  <div class="bot">
    <div class="nb"><div class="nl">Notes &amp; Terms</div><div class="nt">${d.notesHtml}</div></div>
    <div class="tc">
      ${d.totalsRows}
      <div class="tr gr"><span>${d.grandLabel}</span><span>${d.grandValue}</span></div>
    </div>
  </div>
</div>
</div></body></html>`;
  }

  // ── Template 3: Minimal ── typography-only, serif, no fills ──────────────
  function iTplMinimal(ctx) {
    const a = ctx.a, mg = ctx.mg, { biz, addr, c } = iswDummy(ctx);
    const d = iswDocData(ctx, c, {
      italicIndex: true,
      notesDefault: 'Payment due within 14 days of invoice date.<br>Bank transfer only — account details on file.<br>Late payments may incur a 1.5% monthly fee.',
      grandLabelDefault: 'Total',
    });
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,'Times New Roman',serif;font-size:12px;color:#1a1a1a;background:#fff}
.pg{width:${ctx.geomW}px;position:relative;padding:${mg.top}mm ${mg.right}mm ${mg.bot}mm ${mg.left}mm}
.top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px}
.bn{font-size:18px;font-weight:700;font-family:Georgia,serif}
.bi{font-size:10px;color:#6b7280;margin-top:5px;line-height:1.7;font-family:Arial,sans-serif}
.inv-word{font-size:46px;font-weight:700;font-family:Georgia,serif;color:#e5e7eb;line-height:1;text-align:right;letter-spacing:-.02em}
.inv-ref{font-size:11px;color:#6b7280;text-align:right;margin-top:5px;font-family:Arial,sans-serif}
.rule{height:1.5px;background:#1a1a1a;margin-bottom:20px}
.meta{display:flex;justify-content:space-between;margin-bottom:28px}
.btl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.16em;color:#9ca3af;margin-bottom:5px;font-family:Arial,sans-serif}
.btn{font-size:15px;font-weight:700;font-family:Georgia,serif;margin-bottom:3px}
.bti{font-size:10px;color:#6b7280;line-height:1.6;font-family:Arial,sans-serif}
.dts{text-align:right}
.dr{display:flex;gap:18px;justify-content:flex-end;font-size:11px;padding:3px 0;font-family:Arial,sans-serif}
.dk{color:#9ca3af}.dv{font-weight:700;color:#1a1a1a}
table{width:100%;border-collapse:collapse;margin-bottom:24px;font-family:Arial,sans-serif}
thead th{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:#9ca3af;padding:0 0 8px;border-bottom:1.5px solid #1a1a1a}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:9px 0;border-bottom:1px solid #e5e7eb;vertical-align:top}
td.n{color:#d1d5db;text-align:center;width:26px;font-style:italic}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#9ca3af;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 215px;gap:24px}
.nt{font-size:11px;color:#6b7280;line-height:1.65;font-family:Arial,sans-serif;border-top:1px solid #e5e7eb;padding-top:12px}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:5px 0;color:#6b7280;font-family:Arial,sans-serif}
.rule2{height:1px;background:#e5e7eb;margin:4px 0}
.gr{display:flex;justify-content:space-between;font-size:16px;font-weight:700;padding-top:8px;font-family:Georgia,serif;color:#1a1a1a;border-top:1.5px solid #1a1a1a;margin-top:4px}
.ft{margin-top:28px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:9px;color:#9ca3af;text-align:center;letter-spacing:.06em;font-family:Arial,sans-serif;text-transform:uppercase}
${ctx.hdrCss}</style></head><body><div class="pg">
<div class="top">
  <div><div class="bn">${biz}</div><div class="bi">${addr}${d.bizContact ? '<br>' + d.bizContact : ''}</div></div>
  <div><div class="inv-word">INVOICE</div><div class="inv-ref">${d.invNo} / ${d.issueDate}</div></div>
</div>
<div class="rule"></div>
<div class="meta">
  <div><div class="btl">Billed To</div><div class="btn">${d.billToName}</div><div class="bti">${d.billToLines}</div></div>
  <div class="dts">
    <div class="dr"><span class="dk">Issued</span><span class="dv">${d.issueDate}</span></div>
    <div class="dr"><span class="dk">Due</span><span class="dv">${d.dueDate}</span></div>
    <div class="dr"><span class="dk">Status</span><span class="dv">${d.statusLabel}</span></div>
  </div>
</div>
<table>
  <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:100px">Rate</th><th class="r" style="width:100px">Amount</th></tr></thead>
  <tbody>${d.itemRows}</tbody>
</table>
<div class="bot">
  <div class="nt">${d.notesHtml}</div>
  <div>
    ${d.totalsRows}
    <div class="rule2"></div>
    <div class="gr"><span>${d.grandLabel}</span><span>${d.grandValue}</span></div>
  </div>
</div>
<div class="ft">Invoice ${d.invNo} &nbsp;·&nbsp; ${biz} &nbsp;·&nbsp; ${d.issueDate}</div>
</div></body></html>`;
  }

  // ── Template 4: Compact ── card info grid, teal accent chips ─────────────
  function iTplCompact(ctx) {
    const a = ctx.a, mg = ctx.mg, { biz, addr, c } = iswDummy(ctx);
    const d = iswDocData(ctx, c);
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:${ctx.geomW}px;position:relative;padding:${mg.top}mm ${mg.right}mm ${mg.bot}mm ${mg.left}mm}
.topbar{background:${a};border-radius:9px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.tb-biz{font-size:18px;font-weight:900;color:#fff}
.tb-addr{font-size:10px;color:rgba(255,255,255,.72);margin-top:3px}
.tb-il{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.65);margin-bottom:3px;text-align:right}
.tb-in{font-size:22px;font-weight:900;color:#fff;text-align:right}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.gc{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:7px;border-left:3px solid ${a}}
.gcl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#94a3b8;margin-bottom:4px}
.gcv{font-size:12px;font-weight:800;color:#0f172a}
.bt-cell{grid-column:span 2;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:7px;border-left:3px solid ${a}}
table{width:100%;border-collapse:collapse;margin-bottom:18px}
thead th{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:${a};padding:8px 10px;border-bottom:2px solid ${a};background:${a}14}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:8px 10px;border-bottom:1px solid #f1f5f9}
tbody tr:nth-child(even) td{background:${a}08}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 255px;gap:16px}
.nb{padding:12px;border:1.5px solid #e2e8f0;border-radius:7px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55}
.tc{border:1.5px solid #e2e8f0;border-radius:7px;overflow:hidden}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:8px 12px;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{background:${a};font-weight:900;font-size:14px}
.gr span{color:#fff!important}
.ft{margin-top:16px;padding-top:10px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:10px;color:#94a3b8}
${ctx.hdrCss}</style></head><body><div class="pg">
<div class="topbar">
  <div><div class="tb-biz">${biz}</div><div class="tb-addr">${addr}</div></div>
  <div><div class="tb-il">Invoice</div><div class="tb-in">${d.invNo}</div></div>
</div>
<div class="grid4">
  <div class="bt-cell" style="grid-column:span 2">
    <div class="gcl">Billed To</div>
    <div style="font-size:14px;font-weight:800;margin-bottom:3px">${d.billToName}</div>
    <div style="font-size:11px;color:#64748b">${d.billToLines}</div>
  </div>
  <div class="gc"><div class="gcl">Issue Date</div><div class="gcv">${d.issueDate}</div></div>
  <div class="gc"><div class="gcl">Due Date</div><div class="gcv">${d.dueDate}</div></div>
  <div class="gc"><div class="gcl">Status</div><div class="gcv" style="color:${d.statusColor}">${d.statusLabel} ✓</div></div>
  <div class="gc"><div class="gcl">Amount</div><div class="gcv" style="color:${a}">${d.grandValue}</div></div>
</div>
<table>
  <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:100px">Unit Price</th><th class="r" style="width:100px">Total</th></tr></thead>
  <tbody>${d.itemRows}</tbody>
</table>
<div class="bot">
  <div class="nb"><div class="nl">Notes &amp; Terms</div><div class="nt">${d.notesHtml}</div></div>
  <div class="tc">
    ${d.totalsRows}
    <div class="tr gr"><span>${d.grandLabel}</span><span>${d.grandValue}</span></div>
  </div>
</div>
<div class="ft"><span>${d.invNo} · ${biz}</span><span>${d.issueDate}</span></div>
</div></body></html>`;
  }

  // ── Template 5: Executive ── dark navy header, gold accent, premium ──────
  function iTplExecutive(ctx) {
    const a = ctx.a, dk = '#0f172a', mg = ctx.mg, { biz, addr, c } = iswDummy(ctx);
    const d = iswDocData(ctx, c, { statusColorDefault: '#4ade80' });
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:${ctx.geomW}px;${ctx.geomMinH}position:relative;display:flex;flex-direction:column}
.hdr{background:${dk};padding:30px ${mg.right}mm 26px ${mg.left}mm}
.hdr-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px}
.biz-n{font-size:22px;font-weight:900;color:#fff;letter-spacing:-.01em}
.biz-i{font-size:10px;color:rgba(255,255,255,.45);margin-top:5px;line-height:1.7}
.il{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:${a};margin-bottom:5px;text-align:right}
.in{font-size:28px;font-weight:900;color:#fff;text-align:right;letter-spacing:-.01em}
.hdr-rule{height:1px;background:${a};opacity:.45;margin-bottom:16px}
.hdr-meta{display:flex;gap:0}
.hm{border-left:2px solid ${a};padding:0 0 0 12px;margin-right:24px}
.hml{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.4);margin-bottom:3px}
.hmv{font-size:12px;font-weight:700;color:#fff}
.body{flex:1;padding:22px ${mg.right}mm ${mg.bot}mm ${mg.left}mm}
.bt{margin-bottom:20px;padding:13px 15px;border:1px solid #e2e8f0;border-radius:6px;border-left:3px solid ${a}}
.btl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.btn{font-size:14px;font-weight:800;margin-bottom:3px}
.bti{font-size:11px;color:#64748b;line-height:1.5}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
thead th{background:${dk};color:${a};font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:9px 10px}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:9px 10px;border-bottom:1px solid #e2e8f0}
tbody tr:nth-child(even) td{background:#f8fafc}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 248px;gap:20px}
.nb{padding:13px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:5px 0;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{font-size:15px;font-weight:900;border-top:2px solid ${a};margin-top:4px;padding-top:9px}
.gr span:last-child{color:${a}}
.ft{margin-top:22px;padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:10px;color:#94a3b8}
${ctx.hdrCss}</style></head><body><div class="pg">
<div class="hdr">
  <div class="hdr-top">
    <div><div class="biz-n">${biz}</div><div class="biz-i">${addr}${d.bizContact ? '<br>' + d.bizContact : ''}</div></div>
    <div><div class="il">Invoice</div><div class="in">${d.invNo}</div></div>
  </div>
  <div class="hdr-rule"></div>
  <div class="hdr-meta">
    <div class="hm"><div class="hml">Issue Date</div><div class="hmv">${d.issueDate}</div></div>
    <div class="hm"><div class="hml">Due Date</div><div class="hmv">${d.dueDate}</div></div>
    <div class="hm"><div class="hml">Status</div><div class="hmv" style="color:${d.statusColor}">${d.statusLabel}</div></div>
  </div>
</div>
<div class="body">
  <div class="bt"><div class="btl">Billed To</div><div class="btn">${d.billToName}</div><div class="bti">${d.billToLines}</div></div>
  <table>
    <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:100px">Unit Price</th><th class="r" style="width:100px">Total</th></tr></thead>
    <tbody>${d.itemRows}</tbody>
  </table>
  <div class="bot">
    <div class="nb"><div class="nl">Notes &amp; Terms</div><div class="nt">${d.notesHtml}</div></div>
    <div>
      ${d.totalsRows}
      <div class="tr gr"><span>${d.grandLabel}</span><span>${d.grandValue}</span></div>
    </div>
  </div>
  <div class="ft"><span>${d.invNo} · ${biz}</span><span>${d.footerRight || biz}</span></div>
</div>
</div></body></html>`;
  }

  const INV_TPL_BUILDERS = { classic: iTplClassic, bold: iTplBold, minimal: iTplMinimal, compact: iTplCompact, executive: iTplExecutive };

  // Exposes the invoice template engine to js/pos.js (loaded after this file),
  // so the sale-completed "Invoice" preview/print/download can render with the
  // same template/accent/paper/margins the business configured here, fed with
  // the real sale's data instead of this wizard's own hard-coded demo content.
  window.InvoiceTemplates = {
    builders: INV_TPL_BUILDERS,
    templates: INV_TEMPLATES,
    byId: iswTplById,
    geom: iswGeom,
    hdrCss: iswHdrCss,
    pageAtCss: iswPageAtCss,
  };

  function openInvoiceSetupWindow() {
    if ($('isw-backdrop')) return;

    const returnFocusTo = document.activeElement;
    const state = { template: 'classic', accentColor: '', businessName: '', address: '', currency: '' };
    const view = { fitScale: 0.5, zoom: 1, panX: 0, panY: 0, lastGeom: { wPx: 794, hPx: 1123 } };

    const el = document.createElement('div');
    el.className = 'isw-backdrop';
    el.id = 'isw-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'isw-title');
    el.innerHTML = `
      <div class="isw-modal">
        <div class="isw-hdr">
          <div class="isw-hdr-left">
            <div class="isw-hdr-icon"><i class="fa-solid fa-file-invoice"></i></div>
            <div>
              <div class="isw-hdr-title" id="isw-title">${t('Invoice Setup')}</div>
              <div class="isw-hdr-sub">${t('Configure template, paper size and print layout')}</div>
            </div>
          </div>
          <button class="isw-close" type="button" id="isw-close" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="isw-alert" class="bm-alert" style="display:none;margin:0 20px 12px"></div>
        <div class="isw-body">
          <div class="isw-left">
            <div class="isw-sec-title"><i class="fa-solid fa-print"></i> ${t('Printer & Paper')}</div>
            <div class="isw-field">
              <label class="isw-lbl">${t('Paper Size')}</label>
              <select id="isw-paper" class="isw-sel">
                <option value="a4">A4  (210 × 297 mm)</option>
                <option value="a5">A5  (148 × 210 mm)</option>
                <option value="letter">${t('Letter')} (216 × 279 mm)</option>
                <option value="legal">${t('Legal')}  (216 × 356 mm)</option>
              </select>
            </div>
            <div class="isw-field">
              <label class="isw-lbl">${t('Orientation')}</label>
              <div class="isw-radio-row">
                <label class="isw-radio"><input type="radio" name="isw-orient" value="portrait" checked> <i class="fa-solid fa-file"></i> ${t('Portrait')}</label>
                <label class="isw-radio"><input type="radio" name="isw-orient" value="landscape"> <i class="fa-solid fa-file" style="display:inline-block;rotate:90deg"></i> ${t('Landscape')}</label>
              </div>
            </div>

            <div class="isw-sec-title" style="margin-top:20px"><i class="fa-solid fa-border-all"></i> ${t('Margins')} <span class="isw-unit">(mm)</span></div>
            <div class="isw-margin-grid">
              <div class="isw-margin-field"><label class="isw-lbl">${t('Top')}</label><input type="number" id="isw-mg-top" class="isw-num" value="20" min="0" max="80" step="1"></div>
              <div class="isw-margin-field"><label class="isw-lbl">${t('Bottom')}</label><input type="number" id="isw-mg-bot" class="isw-num" value="20" min="0" max="80" step="1"></div>
              <div class="isw-margin-field"><label class="isw-lbl">${t('Left')}</label><input type="number" id="isw-mg-left" class="isw-num" value="15" min="0" max="80" step="1"></div>
              <div class="isw-margin-field"><label class="isw-lbl">${t('Right')}</label><input type="number" id="isw-mg-right" class="isw-num" value="15" min="0" max="80" step="1"></div>
            </div>

            <div class="isw-sec-title" style="margin-top:20px"><i class="fa-solid fa-table-columns"></i> ${t('Arrangement')}</div>
            <div class="isw-field">
              <label class="isw-lbl">${t('Header layout')}</label>
              <select id="isw-hdr-layout" class="isw-sel">
                <option value="num-left">${t('Number left, status right')}</option>
                <option value="num-center">${t('Centered number & title')}</option>
                <option value="num-right">${t('Number right')}</option>
              </select>
            </div>
          </div>

          <div class="isw-center">
            <div class="isw-prev-topbar">
              <span class="isw-prev-label"><i class="fa-solid fa-eye"></i> ${t('Preview')}</span>
              <div class="isw-zoom-bar">
                <button class="isw-zoom-btn" type="button" id="isw-zoom-out" title="${t('Zoom out')}"><i class="fa-solid fa-minus"></i></button>
                <span id="isw-zoom-label" class="isw-zoom-label">100%</span>
                <button class="isw-zoom-btn" type="button" id="isw-zoom-in" title="${t('Zoom in')}"><i class="fa-solid fa-plus"></i></button>
                <button class="isw-zoom-btn" type="button" id="isw-zoom-fit" title="${t('Fit to screen')}"><i class="fa-solid fa-compress"></i></button>
              </div>
            </div>
            <div class="isw-prev-bg" id="isw-prev-bg">
              <div id="isw-prev-scaler" class="isw-prev-scaler">
                <iframe id="isw-prev-frame" class="isw-prev-frame" scrolling="no"></iframe>
              </div>
            </div>
          </div>

          <div class="isw-right">
            <div class="isw-sec-title"><i class="fa-solid fa-palette"></i> ${t('Templates')}</div>
            <div id="isw-tpl-list" class="isw-tpl-list"></div>
            <div class="isw-sec-title" style="margin-top:20px"><i class="fa-solid fa-fill-drip"></i> ${t('Accent Color')}</div>
            <div id="isw-color-list" class="isw-color-list"></div>
          </div>
        </div>
        <div class="isw-footer">
          <button class="isw-btn-cancel" type="button" id="isw-cancel"><i class="fa-solid fa-xmark"></i> ${t('Cancel')}</button>
          <button class="isw-btn-save" type="button" id="isw-save"><i class="fa-solid fa-floppy-disk"></i> ${t('Save Setup')}</button>
        </div>
      </div>`;
    document.body.appendChild(el);

    function close() {
      document.removeEventListener('keydown', onKey, true);
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
      window.removeEventListener('resize', onResize);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
    }
    $('isw-close').addEventListener('click', close);
    $('isw-cancel').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    function showAlert(msg, tone) {
      const a = $('isw-alert');
      a.textContent = msg;
      a.style.background = tone === 'success' ? '#dcfce7' : '#fef2f2';
      a.style.color = tone === 'success' ? '#15803d' : '#b91c1c';
      a.style.display = 'block';
    }

    function effectiveAccent() {
      return state.accentColor || iswTplById(state.template).accent;
    }

    // ── Pan / zoom ───────────────────────────────────────────────────────
    function applyTransform() {
      const sc = $('isw-prev-scaler');
      if (!sc) return;
      sc.style.transform = `translate(${view.panX}px,${view.panY}px) scale(${view.fitScale * view.zoom})`;
    }
    function updateZoomLabel() {
      const lbl = $('isw-zoom-label');
      if (lbl) lbl.textContent = Math.round(view.zoom * 100) + '%';
    }
    function resetView() {
      const bg = $('isw-prev-bg');
      if (!bg) return;
      const w = bg.clientWidth, h = bg.clientHeight;
      if (!w || !h) return;
      const pw = view.lastGeom.wPx, ph = view.lastGeom.hPx;
      view.fitScale = Math.min((w - 40) / pw, (h - 40) / ph);
      view.zoom = 1;
      view.panX = (w - pw * view.fitScale) / 2;
      view.panY = (h - ph * view.fitScale) / 2;
      applyTransform();
      updateZoomLabel();
    }
    function zoomAt(newZoom, pivotX, pivotY) {
      const bg = $('isw-prev-bg');
      if (!bg) return;
      newZoom = Math.max(0.25, Math.min(6, newZoom));
      if (pivotX === undefined) { pivotX = bg.clientWidth / 2; pivotY = bg.clientHeight / 2; }
      const ratio = newZoom / view.zoom;
      view.panX = pivotX - (pivotX - view.panX) * ratio;
      view.panY = pivotY - (pivotY - view.panY) * ratio;
      view.zoom = newZoom;
      applyTransform();
      updateZoomLabel();
    }
    function onResize() { resetView(); }

    function readMargins() {
      return {
        top: Number($('isw-mg-top').value) || 0,
        bot: Number($('isw-mg-bot').value) || 0,
        left: Number($('isw-mg-left').value) || 0,
        right: Number($('isw-mg-right').value) || 0,
      };
    }
    function readLive() {
      return {
        paperSize: $('isw-paper').value || 'a4',
        orientation: el.querySelector('input[name="isw-orient"]:checked')?.value || 'portrait',
        headerLayout: $('isw-hdr-layout').value || 'num-left',
        mg: readMargins(),
      };
    }

    function updatePreview() {
      const live = readLive();
      const geom = iswGeom(live.paperSize, live.orientation);
      const ctx = {
        a: effectiveAccent(),
        mg: live.mg,
        cur: state.currency,
        biz: state.businessName,
        addr: state.address,
        geomW: geom.wPx,
        geomMinH: `min-height:${geom.hPx}px;`,
        hdrCss: iswHdrCss(live.headerLayout) + iswPageAtCss(geom),
      };
      const build = INV_TPL_BUILDERS[state.template] || iTplClassic;

      view.lastGeom = geom;
      const scaler = $('isw-prev-scaler');
      const iframe = $('isw-prev-frame');
      scaler.style.width = geom.wPx + 'px';
      scaler.style.height = geom.hPx + 'px';
      iframe.style.width = geom.wPx + 'px';
      iframe.style.height = geom.hPx + 'px';
      iframe.srcdoc = build(ctx);

      renderTemplates();
      renderColors();
      setTimeout(resetView, 40);
    }

    function renderTemplates() {
      $('isw-tpl-list').innerHTML = INV_TEMPLATES.map((tpl) => `
        <div class="isw-tpl-card${tpl.id === state.template ? ' active' : ''}" data-tpl-id="${tpl.id}">
          <div class="isw-tpl-swatch" style="background:${tpl.swatch}"></div>
          <div class="isw-tpl-info">
            <div class="isw-tpl-name">${esc(t(tpl.name))}</div>
            <div class="isw-tpl-desc">${esc(t(tpl.desc))}</div>
          </div>
          ${tpl.id === state.template ? '<i class="fa-solid fa-circle-check isw-tpl-check"></i>' : ''}
        </div>`).join('');
    }

    function renderColors() {
      const tpl = iswTplById(state.template);
      const active = state.accentColor || '';
      const opts = [{ val: '', hex: tpl.accent, icon: 'fa-rotate-left', title: t('Template default') },
        ...INV_COLOR_PRESETS.map((hex) => ({ val: hex, hex, icon: '', title: hex }))];
      $('isw-color-list').innerHTML = `
        <div class="isw-color-grid">
          ${opts.map((o) => `
            <button type="button" class="isw-color-swatch${active === o.val ? ' active' : ''}" data-color="${o.val}" style="background:${o.hex}" title="${esc(o.title)}">
              ${o.icon ? `<i class="fa-solid ${o.icon}"></i>` : ''}
            </button>`).join('')}
        </div>
        <label class="isw-color-custom-row">
          <input type="color" id="isw-color-custom" value="${active || tpl.accent}">
          <span>${t('Custom color')}</span>
        </label>`;
      $('isw-color-custom').addEventListener('input', (e) => {
        state.accentColor = e.target.value;
        $('isw-color-list').querySelectorAll('.isw-color-swatch').forEach((s) => s.classList.remove('active'));
        updatePreview();
      });
    }

    $('isw-tpl-list').addEventListener('click', (e) => {
      const card = e.target.closest('[data-tpl-id]');
      if (!card) return;
      state.template = card.dataset.tplId;
      updatePreview();
    });
    $('isw-color-list').addEventListener('click', (e) => {
      const btn = e.target.closest('.isw-color-swatch');
      if (!btn) return;
      state.accentColor = btn.dataset.color || '';
      updatePreview();
    });

    ['isw-paper', 'isw-mg-top', 'isw-mg-bot', 'isw-mg-left', 'isw-mg-right', 'isw-hdr-layout'].forEach((id) => {
      $(id).addEventListener('change', updatePreview);
      $(id).addEventListener('input', updatePreview);
    });
    el.querySelectorAll('input[name="isw-orient"]').forEach((r) => r.addEventListener('change', updatePreview));

    $('isw-zoom-in').addEventListener('click', () => zoomAt(view.zoom * 1.25));
    $('isw-zoom-out').addEventListener('click', () => zoomAt(view.zoom / 1.25));
    $('isw-zoom-fit').addEventListener('click', resetView);
    window.addEventListener('resize', onResize);

    let dragging = false, dsx = 0, dsy = 0, dpx = 0, dpy = 0;
    function onMouseMove(e) {
      if (!dragging) return;
      view.panX = dpx + (e.clientX - dsx);
      view.panY = dpy + (e.clientY - dsy);
      applyTransform();
    }
    function onMouseUp() {
      if (dragging) { dragging = false; $('isw-prev-bg')?.classList.remove('isw-dragging'); }
    }
    (function bindPanZoom() {
      const bg = $('isw-prev-bg');
      bg.addEventListener('wheel', (e) => {
        e.preventDefault();
        const rect = bg.getBoundingClientRect();
        zoomAt(view.zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12), e.clientX - rect.left, e.clientY - rect.top);
      }, { passive: false });
      bg.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        dragging = true; dsx = e.clientX; dsy = e.clientY; dpx = view.panX; dpy = view.panY;
        bg.classList.add('isw-dragging');
        e.preventDefault();
      });
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
    })();

    async function load() {
      const [settingsRes, setupRes] = await Promise.all([API.settingsGet(), API.invoiceSetupGet()]);

      if (settingsRes.status === 200) {
        const sd = settingsRes.body?.data || {};
        state.businessName = sd.business_name || '';
        state.address = sd.receipt_address_line || '';
        state.currency = (sd.currency || '').toUpperCase();
      }

      let d = {};
      if (setupRes.status !== 200) {
        showAlert(setupRes.body?.message || t('Could not load invoice setup.'));
      } else {
        d = setupRes.body?.data || {};
        state.template = INV_TEMPLATES.some((tp) => tp.id === d.template) ? d.template : 'classic';
        state.accentColor = d.accent_color || '';
      }

      $('isw-paper').value = INV_PAPER_MM[d.paper_size] ? d.paper_size : 'a4';
      $('isw-mg-top').value = Number.isFinite(d.margin_top) ? d.margin_top : 20;
      $('isw-mg-bot').value = Number.isFinite(d.margin_bottom) ? d.margin_bottom : 20;
      $('isw-mg-left').value = Number.isFinite(d.margin_left) ? d.margin_left : 15;
      $('isw-mg-right').value = Number.isFinite(d.margin_right) ? d.margin_right : 15;
      $('isw-hdr-layout').value = ['num-left', 'num-right', 'num-center'].includes(d.header_layout) ? d.header_layout : 'num-left';
      const orientEl = el.querySelector(`input[name="isw-orient"][value="${d.orientation === 'landscape' ? 'landscape' : 'portrait'}"]`);
      if (orientEl) orientEl.checked = true;

      updatePreview();
    }

    async function save() {
      const btn = $('isw-save');
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Saving…')}`;
      $('isw-alert').style.display = 'none';

      const live = readLive();
      const payload = {
        template: state.template,
        accent_color: state.accentColor || '',
        paper_size: live.paperSize,
        orientation: live.orientation,
        margin_top: live.mg.top,
        margin_bottom: live.mg.bot,
        margin_left: live.mg.left,
        margin_right: live.mg.right,
        header_layout: live.headerLayout,
      };

      const res = await API.invoiceSetupUpdate(payload);
      btn.disabled = false;
      btn.innerHTML = originalHtml;

      if (res.status !== 200) {
        showAlert(res.body?.message || t('Could not save the invoice setup.'));
        return;
      }
      showAlert(t('Invoice setup saved.'), 'success');
    }

    $('isw-save').addEventListener('click', save);

    requestAnimationFrame(() => el.classList.add('open'));
    load();
  }

  window.openInvoiceSetupWindow = openInvoiceSetupWindow;

  // ── Profile dropdown ───────────────────────────────────────────────────
  const mount = $('user-menu');
  if (!mount) return;

  mount.innerHTML = `
    <button class="um-trigger" id="um-trigger" type="button" aria-haspopup="menu" aria-expanded="false" title="${t('Account')}">
      <span class="um-avatar" id="um-avatar"><i class="fa-solid fa-user"></i></span>
      <span class="um-trigger-text">
        <span class="um-name" id="um-name">${t('Account')}</span>
        <span class="um-biz" id="um-biz"></span>
      </span>
      <i class="fa-solid fa-chevron-down um-chevron"></i>
    </button>
    <div class="um-panel" id="um-panel" role="menu">
      <div class="um-head">
        <span class="um-avatar lg" id="um-avatar-lg"><i class="fa-solid fa-user"></i></span>
        <div class="um-head-text">
          <div class="um-head-name" id="um-head-name">—</div>
          <div class="um-head-email" id="um-head-email"></div>
        </div>
      </div>
      <div class="um-biz-row">
        <i class="fa-solid fa-store"></i>
        <div><small>${t('Business')}</small><span id="um-biz-name">—</span></div>
      </div>
      <div class="um-sep"></div>
      <button class="um-item" id="um-language" type="button" role="menuitem">
        <i class="fa-solid fa-language"></i> <span>${t('Language')}</span>
        <span class="um-item-value">${i18n.current().native}</span>
      </button>
      <div class="um-sep"></div>
      <button class="um-item" id="um-billing" type="button" role="menuitem"><i class="fa-solid fa-file-invoice-dollar"></i> ${t('Billing & Payments')}</button>
      <div class="um-sep"></div>
      <button class="um-item" id="um-settings" type="button" role="menuitem"><i class="fa-solid fa-gear"></i> ${t('Settings')}</button>
      <div class="um-sep"></div>
      <button class="um-item" id="um-reload" type="button" role="menuitem"><i class="fa-solid fa-rotate-right"></i> ${t('Reload page')}</button>
      <button class="um-item" id="um-restart" type="button" role="menuitem"><i class="fa-solid fa-power-off"></i> ${t('Restart app')}</button>
      <div class="um-sep"></div>
      <button class="um-item danger" id="um-logout" type="button" role="menuitem"><i class="fa-solid fa-right-from-bracket"></i> ${t('Log out')}</button>
    </div>`;

  const trigger = $('um-trigger');

  function setOpen(open) {
    mount.classList.toggle('open', open);
    trigger.setAttribute('aria-expanded', String(open));
  }

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    setOpen(!mount.classList.contains('open'));
  });
  document.addEventListener('click', (e) => {
    if (!mount.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mount.classList.contains('open')) {
      setOpen(false);
      trigger.focus();
    }
  });

  $('um-language').addEventListener('click', () => { setOpen(false); openLanguageWindow(); });
  $('um-billing').addEventListener('click', () => { setOpen(false); openBillingWindow(); });
  $('um-settings').addEventListener('click', () => { setOpen(false); openSettingsWindow(); });
  $('um-reload').addEventListener('click', () => { window.location.reload(); });
  $('um-restart').addEventListener('click', async () => { setOpen(false); await window.electronAPI.restartApp(); });
  $('um-logout').addEventListener('click', async () => { setOpen(false); await window.electronAPI.logout(); });

  // Initials must respect grapheme clusters (Sinhala vowel signs are separate code points)
  const segmenter = typeof Intl !== 'undefined' && Intl.Segmenter ? new Intl.Segmenter(undefined, { granularity: 'grapheme' }) : null;
  const graphemes = (s) => (segmenter ? Array.from(segmenter.segment(s), (x) => x.segment) : Array.from(s));

  function initials(name, email) {
    const source = (name || '').trim() || (email || '').split('@')[0];
    const parts = source.split(/[\s._-]+/).filter(Boolean);
    if (!parts.length) return '';
    const letters = parts.length === 1
      ? graphemes(parts[0]).slice(0, 2).join('')
      : graphemes(parts[0])[0] + graphemes(parts[parts.length - 1])[0];
    return letters.toUpperCase();
  }

  (async () => {
    const cfg = await window.electronAPI.getConfig();
    const name = cfg.user?.name || '';
    const email = cfg.user?.email || '';
    const business = cfg.business_name || (cfg.business_id ? `#${cfg.business_id}` : '');
    const letters = initials(name, email);

    if (letters) {
      $('um-avatar').textContent = letters;
      $('um-avatar-lg').textContent = letters;
    }
    $('um-name').textContent = name || email || t('Account');
    $('um-biz').textContent = business;
    $('um-head-name').textContent = name || email || t('Signed in');
    $('um-head-email').textContent = name ? email : '';
    $('um-head-email').title = email; // full address on hover when it's truncated
    $('um-biz-name').textContent = business || '—';
  })();
})();
