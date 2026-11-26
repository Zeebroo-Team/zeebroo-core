'use strict';

/*
 * Free trial on the desktop app — mirrors the web "Choose your plan" page
 * (Modules/Package PlanController) via PosPlanApiController:
 *   GET  /auth/plan                     trial state + packages
 *   GET  /auth/plan/{id}/preview        features with data that get hidden, recommendations, seats
 *   POST /auth/plan/{id}                switch now (free) or get a Stripe Checkout URL (paid)
 *
 * While the trial runs: a "N days left" bar. Once it expired: the plan picker
 * opens as a lock screen (every gated API call returns 402 trial_expired).
 */
(function () {
  const tp = {
    info: null,        // last /auth/plan payload
    locked: false,     // trial expired → overlay can't be closed
    pending: null,     // { packageId, paymentId } while waiting for Stripe
    barDismissed: false,
  };

  const overlay = () => document.getElementById('trial-plan-overlay');
  const body = () => document.getElementById('trial-plan-body');
  const esc = (s) => (typeof escHtml === 'function' ? escHtml(s) : String(s ?? ''));

  function money(pkg) {
    const p = Number(pkg.effective_price || 0);
    if (pkg.is_free || p <= 0) return 'Free';
    return `${esc(pkg.currency_symbol || '')}${p.toFixed(2)}<small>/mo</small>`;
  }

  function daysText(n) {
    if (n === 0) return 'last day';
    return `${n} ${n === 1 ? 'day' : 'days'} left`;
  }

  // ── Entry point (called from showApp) ─────────────────────────────────
  async function check() {
    const res = await API.planInfo();
    if (res.status !== 200) return;
    tp.info = res.body?.data || null;
    const trial = tp.info?.trial;

    const bar = document.getElementById('trial-bar');
    if (!trial?.active) {
      bar.style.display = 'none';
      return;
    }

    if (trial.expired) {
      bar.style.display = 'none';
      openPicker(true);
      return;
    }

    if (!tp.barDismissed) {
      const ends = trial.ends_at ? new Date(trial.ends_at).toLocaleString() : '';
      document.getElementById('trial-bar-text').innerHTML =
        `<span class="trial-bar-title">You're on the free trial</span>
         <span class="trial-bar-pill${trial.days_left <= 1 ? ' is-last' : ''}">${esc(daysText(trial.days_left))}</span>
         <span class="trial-bar-sub">Ends ${esc(ends)}.${tp.info.can_change ? '' : ' Ask your business owner to choose a plan.'}</span>`;
      document.getElementById('trial-bar-btn').style.display = tp.info.can_change ? '' : 'none';
      bar.style.display = 'flex';
    }
  }

  // ── Step 1: package list ──────────────────────────────────────────────
  async function openPicker(locked) {
    tp.locked = !!locked;
    if (!tp.info) {
      const res = await API.planInfo();
      tp.info = res.body?.data || null;
    }
    document.getElementById('trial-plan-close').style.display = tp.locked ? 'none' : '';
    document.getElementById('trial-plan-close-btn').style.display = tp.locked ? 'none' : '';
    document.getElementById('trial-plan-title').textContent = tp.locked ? 'Your free trial has ended' : 'Choose your plan';
    document.getElementById('trial-plan-sub').textContent = tp.locked
      ? 'Choose a plan to keep using Zeebroo. Your data is safe — you continue right where you left off.'
      : 'Upgrade any time during your trial. You keep everything included in the plan you choose.';

    if (!tp.info?.can_change) {
      body().innerHTML = `
        <div class="trial-plan-box trial-plan-box--warn">
          <h4><i class="fa fa-user-lock"></i> Only the business owner can choose a plan</h4>
          <p>Ask the owner of this business to sign in and pick a plan.</p>
        </div>
        <div class="trial-plan-actions"><button class="trial-plan-link" data-tp="signout">Sign out</button></div>`;
    } else {
      const pkgs = tp.info.packages || [];
      body().innerHTML = pkgs.length
        ? `<div class="trial-plan-grid">${pkgs.map(p => pkgCard(p)).join('')}</div>
           ${tp.locked ? '<div class="trial-plan-actions" style="margin-top:14px"><button class="trial-plan-link" data-tp="signout">Sign out</button></div>' : ''}`
        : '<p>No plans are available right now — please contact support.</p>';
    }
    const ov = overlay();
    if (ov.style.display !== 'flex') {
      ov.classList.remove('mac-out');
      ov.style.display = 'flex';
      ov.classList.add('mac-in');
    }
  }

  // Price note for a recommended plan, relative to the plan the user picked.
  function recNote(rec, target, isCheapest) {
    const diff = Number(rec.effective_price || 0) - Number(target.effective_price || 0);
    const amt = `${esc(rec.currency_symbol || '')}${Math.abs(diff).toFixed(2)}`;
    const delta = diff > 0 ? `only ${amt}/mo more than ${esc(target.name)}`
      : diff < 0 ? `${amt}/mo less than ${esc(target.name)}`
      : `same price as ${esc(target.name)}`;
    return `<div style="font-size:12.5px;color:#15803d;font-weight:600">
      ${isCheapest ? '<span class="trial-plan-chip" style="background:#dcfce7;color:#15803d"><i class="fa fa-tag"></i> Best value</span> ' : ''}${delta}</div>`;
  }

  function pkgCard(pkg, recommended, note) {
    const feats = (pkg.feature_labels || []).map(l => `<span class="trial-plan-chip">${esc(l)}</span>`).join('');
    return `<div class="trial-plan-pkg${recommended ? ' trial-plan-pkg--rec' : ''}">
      <h3>${esc(pkg.name)}</h3>
      <div class="trial-plan-price">${money(pkg)}</div>
      ${note || ''}
      ${pkg.description ? `<div style="font-size:12.5px;color:#6b7280">${esc(pkg.description)}</div>` : ''}
      <div class="trial-plan-chips"><span class="trial-plan-chip"><i class="fa fa-users"></i> ${esc(pkg.user_limit_label || '')}</span></div>
      <div class="trial-plan-chips">${feats}</div>
      <button class="po-btn-primary" data-tp="choose" data-id="${pkg.id}">Choose ${esc(pkg.name)} <i class="fa fa-arrow-right"></i></button>
    </div>`;
  }

  // ── Step 2: review (warnings, recommendations, seats, confirm) ────────
  async function openReview(packageId) {
    body().innerHTML = '<p><i class="fa fa-spinner fa-spin"></i> Checking what changes…</p>';
    const res = await API.planPreview(packageId);
    if (res.status !== 200) {
      body().innerHTML = `<div class="trial-plan-box trial-plan-box--warn"><p>${esc(res.body?.message || 'Could not load this plan.')}</p></div>
        <div class="trial-plan-actions"><button class="trial-plan-link" data-tp="back">Back to plans</button></div>`;
      return;
    }
    const d = res.body.data;
    const pkg = d.package;
    const lost = d.lost_features || [];
    const seats = d.seats || {};

    let html = `<h3 style="margin:0 0 6px">Switch to ${esc(pkg.name)}</h3>
      <p style="margin:0 0 14px;font-size:13.5px;color:#6b7280">${d.requires_payment
        ? `${money(pkg)} — paid securely with Stripe. Your plan changes as soon as the payment goes through.`
        : 'This plan is free — you switch right away.'}</p>`;

    if (lost.length) {
      html += `<div class="trial-plan-box trial-plan-box--warn">
        <h4><i class="fa fa-triangle-exclamation"></i> ${esc(pkg.name)} doesn't include some features you used</h4>
        <p>After switching, this data will be <strong>hidden and you won't be able to access it</strong> on the ${esc(pkg.name)} plan.
           It isn't deleted — move to a plan with these features later and it comes back.</p>
        ${lost.map(f => `<div class="trial-plan-loss"><i class="fa fa-lock"></i><div><strong>${esc(f.label)}</strong><br>
          <span style="color:#6b7280">${(f.items || []).map(i => `${Number(i.count).toLocaleString()} ${esc(i.label)}`).join(' · ')}</span></div></div>`).join('')}
      </div>`;

      if ((d.recommended || []).length) {
        html += `<div class="trial-plan-box"><h4><i class="fa fa-thumbs-up"></i> Recommended for you</h4>
          <p>These plans keep everything you've been using — lowest price first:</p>
          <div class="trial-plan-grid">${d.recommended.map((p, i) => pkgCard(p, true, recNote(p, pkg, i === 0))).join('')}</div></div>`;
      }
    }

    if (seats.needs_selection) {
      html += `<div class="trial-plan-box trial-plan-box--warn">
        <h4><i class="fa fa-users"></i> ${esc(pkg.name)} allows ${seats.limit} user(s) — you have ${seats.used}</h4>
        <p>You keep your own account. Choose up to <strong>${seats.must_keep}</strong> team member(s) to keep.
           The others will be deactivated (not deleted) and pending invitations cancelled.</p>
        ${(seats.members || []).map(m => `<label class="trial-plan-member">
            <input type="checkbox" class="tp-keep" value="${m.id}">
            <span><strong>${esc(m.name)}</strong> <small>${esc(m.email)} · ${esc(m.role)}</small></span></label>`).join('')}
        <p id="tp-keep-hint"></p>
      </div>`;
    }

    if (d.requires_confirmation) {
      html += `<div class="trial-plan-box"><h4>Confirm</h4>
        <p>I understand that my ${lost.map(f => esc(f.label)).join(', ')} data will be hidden on the ${esc(pkg.name)} plan. Type <strong>CONFIRM</strong> to continue.</p>
        <input class="trial-plan-input" id="tp-confirm" placeholder="CONFIRM" autocomplete="off"></div>`;
    }

    html += `<div id="tp-alert" class="alert alert-error" style="display:none"></div>
      <div class="trial-plan-actions">
        <button class="po-btn-primary${lost.length ? ' trial-plan-btn-danger' : ''}" id="tp-submit" data-tp="submit" data-id="${pkg.id}">
          ${d.requires_payment ? '<i class="fa fa-lock"></i> Continue to payment' : `<i class="fa fa-check"></i> Switch to ${esc(pkg.name)}`}
        </button>
        <button class="trial-plan-link" data-tp="back">Back to plans</button>
      </div>`;

    body().innerHTML = html;

    const maxKeep = Number(seats.must_keep || 0);
    const refresh = () => {
      let ok = true;
      const c = document.getElementById('tp-confirm');
      if (c) ok = ok && c.value.trim().toUpperCase() === 'CONFIRM';
      if (seats.needs_selection) {
        const boxes = [...body().querySelectorAll('.tp-keep')];
        const picked = boxes.filter(b => b.checked).length;
        boxes.forEach(b => { b.disabled = !b.checked && picked >= maxKeep; });
        document.getElementById('tp-keep-hint').textContent = `${picked} of ${maxKeep} selected`;
      }
      document.getElementById('tp-submit').disabled = !ok;
    };
    body().querySelectorAll('.tp-keep').forEach(b => b.addEventListener('change', refresh));
    document.getElementById('tp-confirm')?.addEventListener('input', refresh);
    refresh();
  }

  // ── Step 3: submit ────────────────────────────────────────────────────
  async function submit(packageId) {
    const btn = document.getElementById('tp-submit');
    const alert = document.getElementById('tp-alert');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Please wait…';

    const res = await API.planChange(packageId, {
      keep_member_ids: [...body().querySelectorAll('.tp-keep:checked')].map(b => Number(b.value)),
      confirm_text: document.getElementById('tp-confirm')?.value || '',
      platform: 'desktop',
    });

    if (res.status !== 200) {
      const errs = res.body?.errors || {};
      alert.textContent = Object.keys(errs).length ? Object.values(errs).flat().join(' ') : (res.body?.message || 'Something went wrong.');
      alert.style.display = '';
      btn.disabled = false;
      btn.innerHTML = 'Try again';
      return;
    }

    const d = res.body.data || {};
    if (d.applied) {
      finished();
      return;
    }

    tp.pending = { packageId, paymentId: d.payment_id };
    window.electronAPI.openExternal(d.checkout_url);
    body().innerHTML = `<div class="trial-plan-box">
        <h4><i class="fa fa-spinner fa-spin"></i> Waiting for payment…</h4>
        <p>Finish the payment in the browser window that just opened. Your plan switches as soon as Stripe confirms it.</p>
        <p id="tp-wait-err" style="display:none;color:#b45309"></p>
      </div>
      <div class="trial-plan-actions">
        <button class="po-btn-primary" data-tp="verify"><i class="fa fa-rotate"></i> I've paid — check again</button>
        <button class="trial-plan-link" data-tp="back">Back to plans</button>
      </div>`;
  }

  async function verify() {
    if (!tp.pending?.paymentId) return;
    const res = await API.paymentStatus(tp.pending.paymentId);
    if (res.status === 200 && res.body?.data?.payment_status === 'succeeded') {
      finished();
      return;
    }
    const err = document.getElementById('tp-wait-err');
    if (err) {
      err.textContent = 'Payment not confirmed yet — finish it in the browser window, then check again.';
      err.style.display = '';
    }
  }

  function finished() {
    tp.pending = null;
    tp.locked = false;
    overlay().style.display = 'none';
    document.getElementById('trial-bar').style.display = 'none';
    if (typeof toast === 'function') toast('Your plan has been updated.', 'success');
    // Features, menus and seats changed — reload so every panel picks them up.
    setTimeout(() => window.location.reload(), 800);
  }

  // ── Wiring ────────────────────────────────────────────────────────────
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-tp]');
    if (!el || !overlay().contains(el)) return;
    const action = el.getAttribute('data-tp');
    if (action === 'choose') openReview(Number(el.getAttribute('data-id')));
    else if (action === 'back') openPicker(tp.locked);
    else if (action === 'submit') submit(Number(el.getAttribute('data-id')));
    else if (action === 'verify') verify();
    else if (action === 'signout' && typeof _performSignOut === 'function') {
      overlay().style.display = 'none';
      _performSignOut();
    }
  });

  const closePicker = () => {
    if (tp.locked) return;
    overlay().classList.remove('mac-in', 'mac-out');
    overlay().style.display = 'none';
  };
  document.getElementById('trial-plan-close').addEventListener('click', closePicker);
  document.getElementById('trial-plan-close-btn').addEventListener('click', closePicker);
  document.getElementById('trial-bar-btn').addEventListener('click', () => openPicker(false));
  document.getElementById('trial-bar-dismiss').addEventListener('click', () => {
    tp.barDismissed = true;
    document.getElementById('trial-bar').style.display = 'none';
  });

  // Stripe return (socibiz://payment) — only while this flow is waiting.
  window.electronAPI?.onPaymentDeepLink?.(({ status, paymentId }) => {
    if (!tp.pending) return;
    if (paymentId && tp.pending.paymentId && Number(paymentId) !== Number(tp.pending.paymentId)) return;
    if (status === 'success') verify();
    else openReview(tp.pending.packageId);
  });

  // Trial ran out mid-session: any gated call returns 402 trial_expired (api.js).
  window.addEventListener('api-trial-expired', () => {
    if (overlay().style.display === 'flex') return;
    tp.info = null;
    openPicker(true);
  });

  // A module outside the current package (403 feature_not_in_plan, api.js) — explain instead of failing silently.
  // Only when the user is on that module's own tab: background loads from other screens
  // (Home stats, badges, prefetch) just get no data - they shouldn't pop an error.
  const FEATURE_TABS = { crm: 'crm', ecommerce: 'ecommerce', mail: 'mail', automation_editor: 'automations' };
  let lastFeatureToast = 0;
  window.addEventListener('api-feature-not-in-plan', (e) => {
    const tab = FEATURE_TABS[e.detail?.feature];
    if (tab && typeof _activeTab === 'function' && _activeTab() !== tab) return;
    if (Date.now() - lastFeatureToast < 4000 || typeof toast !== 'function') return;
    lastFeatureToast = Date.now();
    toast(e.detail?.message || 'This feature is not included in your current plan.', 'error');
  });

  // Billing & Payments window: on a trial, show the current package, days left
  // and a "Change plan" button (works any time, not only after expiry).
  async function decorateBilling(container, closeBilling) {
    const res = await API.planInfo();
    if (res.status !== 200 || !container?.isConnected) return;
    tp.info = res.body?.data || null;
    const trial = tp.info?.trial;
    if (!trial?.active) return;

    container.querySelector('.tp-billing')?.remove();
    const pkgName = tp.info.current_package?.name || 'Free trial';
    const ends = trial.ends_at ? new Date(trial.ends_at).toLocaleString() : '';
    const status = trial.expired ? 'Trial ended' : daysText(trial.days_left);

    const box = document.createElement('div');
    box.className = 'tp-billing';
    box.innerHTML = `
      <div class="tp-billing-info">
        <i class="fa fa-gift"></i>
        <div>
          <div class="tp-billing-title">Current package: <strong>${esc(pkgName)}</strong>
            <span class="tp-billing-badge${trial.expired ? ' is-ended' : ''}">${esc(status)}</span></div>
          <div class="tp-billing-sub">${trial.expired ? 'Ended' : 'Ends'} ${esc(ends)}.
            ${tp.info.can_change ? 'You can move to another plan any time — you don\'t have to wait for the trial to end.' : 'Only the business owner can change the plan.'}</div>
        </div>
      </div>
      ${tp.info.can_change ? '<button class="po-btn-primary" data-tp-billing="change"><i class="fa fa-arrow-up"></i> Change plan</button>' : ''}`;
    container.prepend(box);

    box.querySelector('[data-tp-billing="change"]')?.addEventListener('click', () => {
      if (typeof closeBilling === 'function') closeBilling();
      openPicker(!!trial.expired);
    });
  }

  window.trialPlanDecorateBilling = decorateBilling;
  window.trialPlanCheck = check;
  window.trialPlanOpen = () => openPicker(false);
})();
