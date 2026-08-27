/* ══════════════════════════════════════════════════════════════════
   HOME SUB-NAV TAB CUSTOMIZER
   Lets the current user personally show/hide the Home page sub-nav
   tabs (Business Flow, Today, Recent Activity, ...). This sits on top
   of the admin-managed permission gate in applyFeatureVisibility() —
   a tab already hidden by permissions can't be re-enabled here.
   ══════════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  const PREFS_KEY = 'home_subnav_prefs';

  const DESCRIPTIONS = {
    flow:      'Visual diagram of money & goods flow',
    today:     "Today's sales, revenue and KPIs",
    activity:  'Latest actions across the business',
    analytics: 'Sales trends and performance charts',
    expenses:  'Recent and upcoming expenses',
    profit:    'Profit and margin breakdown',
    payroll:   'Staff payroll overview',
    orders:    'Order status and history',
  };

  const $q  = (s, p) => (p || document).querySelector(s);
  const $qa = (s, p) => [...(p || document).querySelectorAll(s)];

  let _raw = null; // { users: { [userKey]: { hidden: {tabId:true} } } }

  function _uctx() {
    try {
      return window.getRibbonUserCtx?.() ?? { userKey: null, userName: null, role: null, isAdmin: false };
    } catch (_) {
      return { userKey: null, userName: null, role: null, isAdmin: false };
    }
  }

  function _scopeKey() {
    return _uctx().userKey || '_default';
  }

  function _hiddenMap() {
    if (!_raw) _raw = { users: {} };
    const k = _scopeKey();
    return (_raw.users[k] && typeof _raw.users[k].hidden === 'object') ? _raw.users[k].hidden : {};
  }

  function _setHidden(tabId, hidden) {
    if (!_raw) _raw = { users: {} };
    const k = _scopeKey();
    if (!_raw.users[k]) _raw.users[k] = { hidden: {} };
    if (!_raw.users[k].hidden) _raw.users[k].hidden = {};
    if (hidden) _raw.users[k].hidden[tabId] = true;
    else delete _raw.users[k].hidden[tabId];
  }

  async function _persist() {
    try { await window.electronAPI?.setConfig?.({ [PREFS_KEY]: _raw }); }
    catch (e) { console.error('[HSC] persist error:', e); }
  }

  // ── Read tab buttons currently in the DOM ───────────────────────────
  function _tabBtns() {
    return $qa('.home-subnav .home-tab-btn[data-home-view]');
  }

  function _permitted(btn) {
    return btn.dataset.hscPerm !== '0';
  }

  // ── Apply hidden-state to the sub-nav (called on init and after save) ──
  function hscApply() {
    try {
      const hidden = _hiddenMap();
      const btns = _tabBtns();
      let activeOk = false, firstVisible = null;

      btns.forEach(btn => {
        if (!_permitted(btn)) return; // leave permission-hidden tabs alone
        const v = btn.dataset.homeView;
        const show = !hidden[v];
        btn.style.display = show ? '' : 'none';
        if (show) {
          if (!firstVisible) firstVisible = v;
          if (btn.classList.contains('active')) activeOk = true;
        }
      });

      if (!activeOk && firstVisible) {
        btns.forEach(b => b.classList.remove('active'));
        const fb = $q(`.home-tab-btn[data-home-view="${firstVisible}"]`);
        if (fb) fb.classList.add('active');
        $qa('.home-view').forEach(v => v.style.display = 'none');
        const fp = $q(`#home-view-${firstVisible}`);
        if (fp) fp.style.display = 'flex';
      }
    } catch (e) { console.error('[HSC] apply error:', e); }
  }

  // ── Init — load prefs then apply + inject the gear button ───────────
  async function hscInit() {
    try {
      const cfg = await window.electronAPI?.getConfig?.();
      const stored = cfg?.[PREFS_KEY];
      _raw = (stored && typeof stored === 'object' && stored.users) ? stored : { users: {} };
    } catch (e) {
      console.warn('[HSC] getConfig failed:', e);
      _raw = { users: {} };
    }
    hscApply();
    _bindGearBtn();
  }

  function _bindGearBtn() {
    const btn = $q('#home-subnav-gear');
    if (!btn || btn.dataset.hscBound) return;
    btn.dataset.hscBound = '1';
    btn.addEventListener('click', hscOpen);
  }

  // ══════════════════════════════════════════════════════════════════
  // DIALOG
  // ══════════════════════════════════════════════════════════════════
  function _buildDialog() {
    if ($q('#hsc-overlay')) return;
    const ov = document.createElement('div');
    ov.id = 'hsc-overlay';
    ov.setAttribute('role', 'dialog');
    ov.setAttribute('aria-modal', 'true');
    ov.innerHTML = `
      <div id="hsc-dialog">
        <div class="hsc-head">
          <div class="hsc-head-icon"><i class="fa fa-gear"></i></div>
          <div style="flex:1">
            <div class="hsc-head-title">Customize Home Tabs</div>
            <div class="hsc-head-sub">Choose which tabs show up on your Home page</div>
          </div>
          <button class="hsc-head-close" id="hsc-close" aria-label="Close">&#x2715;</button>
        </div>
        <div class="hsc-body">
          <div class="hsc-grid" id="hsc-grid"></div>
        </div>
        <div class="hsc-foot">
          <div class="hsc-foot-note"><i class="fa fa-circle-info"></i>&ensp;Saved for your account only.</div>
          <button class="hsc-btn-done" id="hsc-done">Done</button>
        </div>
      </div>`;
    document.body.appendChild(ov);

    ov.addEventListener('pointerdown', e => { if (e.target === ov) hscClose(); });
    $q('#hsc-close', ov).addEventListener('click', hscClose);
    $q('#hsc-done', ov).addEventListener('click', hscClose);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && ov.classList.contains('open')) hscClose();
    });
  }

  function _renderGrid() {
    const grid = $q('#hsc-grid');
    if (!grid) return;
    grid.innerHTML = '';

    const hidden = _hiddenMap();
    const btns = _tabBtns().filter(_permitted);
    const visibleCount = btns.filter(b => !hidden[b.dataset.homeView]).length;

    btns.forEach(btn => {
      const v = btn.dataset.homeView;
      const label = btn.textContent.trim();
      const iconClass = btn.querySelector('i')?.className || 'fa fa-square';
      const isOn = !hidden[v];
      const isOnlyOneLeft = isOn && visibleCount <= 1;

      const card = document.createElement('div');
      card.className = 'hsc-card' + (isOn ? ' on' : '');
      card.innerHTML = `
        <div class="hsc-card-icon"><i class="${_esc(iconClass)}"></i></div>
        <div class="hsc-card-body">
          <div class="hsc-card-name">${_esc(label)}</div>
          <div class="hsc-card-desc">${_esc(DESCRIPTIONS[v] || '')}</div>
          ${isOnlyOneLeft ? '<div class="hsc-card-lockednote">Keep at least one tab visible</div>' : ''}
        </div>
        <label class="hsc-switch">
          <input type="checkbox" data-tab="${_esc(v)}" ${isOn ? 'checked' : ''} ${isOnlyOneLeft ? 'disabled' : ''}>
          <span class="hsc-switch-track"></span>
        </label>`;

      $q('input', card).addEventListener('change', async e => {
        _setHidden(v, !e.target.checked);
        hscApply();
        await _persist();
        _renderGrid();
      });

      grid.appendChild(card);
    });
  }

  function hscOpen() {
    try {
      _buildDialog();
      _renderGrid();
      const ov = $q('#hsc-overlay');
      ov.style.display = 'flex';
      void ov.offsetWidth;
      ov.classList.add('open');
    } catch (e) { console.error('[HSC] open error:', e); }
  }

  function hscClose() {
    const ov = $q('#hsc-overlay');
    if (!ov) return;
    ov.classList.remove('open');
    setTimeout(() => { if (!ov.classList.contains('open')) ov.style.display = 'none'; }, 200);
  }

  function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  window.hscInit  = hscInit;
  window.hscApply = hscApply;
  window.hscOpen  = hscOpen;

  // ══════════════════════════════════════════════════════════════════
  // BOOTSTRAP — bind the gear button as soon as this script runs,
  // instead of waiting for app.js to call hscInit() from showApp().
  // (That call can race against this file's own <script> load, since
  // the config IPC round-trip in init() may resolve before the later
  // <script> tags finish parsing, silently skipping the wire-up.)
  // ══════════════════════════════════════════════════════════════════
  function _boot() {
    _bindGearBtn();
    hscInit();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _boot);
  } else {
    _boot();
  }

})();