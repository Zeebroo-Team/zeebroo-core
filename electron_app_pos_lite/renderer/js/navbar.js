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
