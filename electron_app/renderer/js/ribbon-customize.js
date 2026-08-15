/* ══════════════════════════════════════════════════════════════════
   RIBBON CUSTOMIZER  — role-wise + user-wise
   ══════════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  const PREFS_KEY = 'ribbon_prefs';
  const ROLES = [
    { key: 'cashier', label: 'Cashier' },
    { key: 'officer', label: 'Officer' },
    { key: 'admin',   label: 'Admin'   },
    { key: 'owner',   label: 'Owner'   },
  ];

  const $q  = (s, p) => (p || document).querySelector(s);
  const $qa = (s, p) => [...(p || document).querySelectorAll(s)];

  let _defs   = null;
  let _raw    = null;
  let _edit   = null;
  let _selTab = null;
  let _scope  = null;
  let _ctxTab = null;
  let _dragSrc = null;

  // ── User context (bridged from app.js) ─────────────────────────────
  function _uctx() {
    try {
      return window.getRibbonUserCtx?.() ?? { userKey: null, userName: null, role: null, isAdmin: false };
    } catch (_) {
      return { userKey: null, userName: null, role: null, isAdmin: false };
    }
  }

  // ── Snapshot ribbon DOM ────────────────────────────────────────────
  // Only captures tabs/buttons that are currently enabled by feature flags.
  // Tabs hidden by applyFeatureVisibility() carry data-rbc-feature="0" and
  // are excluded. Buttons whose ribbon-group is display:none are also excluded.
  function _snap() {
    try {
      // Skip tabs marked as feature-disabled by applyFeatureVisibility()
      const tabs = $qa('#ribbon-tabs .ribbon-tab[data-tab]')
        .filter(t => t.dataset.rbcFeature !== '0')
        .map(t => ({ id: t.dataset.tab, label: t.textContent.trim() }));

      const enabledTabIds = new Set(tabs.map(t => t.id));

      const buttons = {};
      $qa('#ribbon-body .ribbon-page[data-page]').forEach(page => {
        // Only process pages whose tab is feature-enabled
        if (!enabledTabIds.has(page.dataset.page)) return;
        $qa('button[id]', page).forEach(btn => {
          if (!btn.id.startsWith('rb-')) return;
          // Skip buttons inside a ribbon-group that is hidden by feature flags
          const grpEl = btn.closest('.ribbon-group');
          if (grpEl && grpEl.style.display === 'none') return;
          const clone = btn.cloneNode(true);
          $qa('kbd', clone).forEach(k => k.remove());
          const label = clone.textContent.replace(/\s+/g, ' ').trim() || btn.title || btn.id;
          buttons[btn.id] = {
            id: btn.id, label,
            defaultSize: btn.classList.contains('ribbon-btn-lg') ? 'lg' : 'sm',
            page: page.dataset.page,
            group: grpEl?.querySelector('.ribbon-group-label')?.textContent?.trim() || '—',
          };
        });
      });
      _defs = { tabs, buttons };
    } catch (e) {
      console.error('[RBC] _snap error:', e);
      _defs = { tabs: [], buttons: {} };
    }
  }

  // ── Prefs helpers ──────────────────────────────────────────────────
  function _factory() {
    if (!_defs) _snap();
    return { tab_order: (_defs.tabs || []).map(t => t.id), tab_hidden: {}, btn_hidden: {}, btn_size: {} };
  }

  function _norm(raw) {
    const def = _factory();
    if (!raw || typeof raw !== 'object') return { ...def };
    return {
      tab_order:  Array.isArray(raw.tab_order) ? [...raw.tab_order] : [...def.tab_order],
      tab_hidden: (raw.tab_hidden && typeof raw.tab_hidden === 'object') ? { ...raw.tab_hidden } : {},
      btn_hidden: (raw.btn_hidden && typeof raw.btn_hidden === 'object') ? { ...raw.btn_hidden } : {},
      btn_size:   (raw.btn_size   && typeof raw.btn_size   === 'object') ? { ...raw.btn_size   } : {},
    };
  }

  function _effective() {
    if (!_defs) _snap();
    if (!_raw)  _raw = { roles: {}, users: {} };
    const uc   = _uctx();
    const base = _factory();
    const role = _norm(_raw.roles?.[uc.role]);
    const user = _norm(_raw.users?.[uc.userKey]);
    const known = new Set((_defs.tabs || []).map(t => t.id));
    const order = (user.tab_order.length ? user.tab_order : role.tab_order.length ? role.tab_order : base.tab_order)
                    .filter(id => known.has(id));
    const extra = [...known].filter(id => !order.includes(id));
    return {
      tab_order:  [...order, ...extra],
      tab_hidden: { ...role.tab_hidden, ...user.tab_hidden },
      btn_hidden: { ...role.btn_hidden, ...user.btn_hidden },
      btn_size:   { ...role.btn_size,   ...user.btn_size   },
    };
  }

  function _scopePrefs() {
    if (!_raw)  _raw = { roles: {}, users: {} };
    if (!_scope) return _factory();
    if (_scope.type === 'role') return _norm(_raw.roles?.[_scope.roleKey]);
    return _norm(_raw.users?.[_uctx().userKey]);
  }

  function _commitEdit() {
    if (!_raw) _raw = { roles: {}, users: {} };
    if (_scope?.type === 'role') {
      _raw.roles[_scope.roleKey] = { ..._edit };
    } else {
      const uk = _uctx().userKey;
      if (uk) _raw.users[uk] = { ..._edit };
    }
  }

  // ── Apply to ribbon ────────────────────────────────────────────────
  function _apply(p) {
    if (!p) return;
    try {
      const tabRow = $q('#ribbon-tabs');
      if (!tabRow || !_defs) return;
      (p.tab_order || []).forEach(id => {
        const el = tabRow.querySelector(`.ribbon-tab[data-tab="${CSS.escape(id)}"]`);
        if (el) tabRow.appendChild(el);
      });
      $qa('.ribbon-tab[data-tab]', tabRow).forEach(t => {
        // Never un-hide a tab that is feature-disabled by applyFeatureVisibility()
        if (t.dataset.rbcFeature === '0') return;
        t.style.display = p.tab_hidden?.[t.dataset.tab] ? 'none' : '';
      });
      Object.values(_defs.buttons || {}).forEach(def => {
        const btn = document.getElementById(def.id);
        if (!btn) return;
        btn.style.display = p.btn_hidden?.[def.id] ? 'none' : '';
        const sz = p.btn_size?.[def.id] || def.defaultSize;
        btn.classList.toggle('ribbon-btn-lg', sz === 'lg');
        btn.classList.toggle('ribbon-btn-sm', sz === 'sm');
      });
    } catch (e) { console.error('[RBC] _apply error:', e); }
  }

  async function _persist() {
    try { await window.electronAPI?.setConfig?.({ [PREFS_KEY]: _raw }); }
    catch (e) { console.error('[RBC] persist error:', e); }
  }

  // ══════════════════════════════════════════════════════════════════
  // INIT — called by showApp() after ribbon DOM exists
  // ══════════════════════════════════════════════════════════════════
  async function rbcInit() {
    console.log('[RBC] rbcInit start');
    try {
      _snap();

      // Load stored prefs
      let stored = null;
      try {
        const cfg = await window.electronAPI?.getConfig?.();
        stored = cfg?.[PREFS_KEY] ?? null;
      } catch (e) {
        console.warn('[RBC] getConfig failed:', e);
      }

      // Upgrade flat format → new role/user format
      if (stored && !stored.roles && !stored.users) {
        const uk = _uctx().userKey;
        stored = { roles: {}, users: uk ? { [uk]: _norm(stored) } : {} };
      }

      _raw = {
        roles: (stored?.roles && typeof stored.roles === 'object') ? { ...stored.roles } : {},
        users: (stored?.users && typeof stored.users === 'object') ? { ...stored.users } : {},
      };

      console.log('[RBC] prefs loaded, applying...');
    } catch (e) {
      console.error('[RBC] init (load) error:', e);
      _raw = { roles: {}, users: {} };
    }

    // Apply prefs — in its own try so DOM setup below always runs
    try { _apply(_effective()); }
    catch (e) { console.error('[RBC] init (apply) error:', e); }

    // DOM setup — MUST run even if apply threw
    try {
      _buildCtx();
      _injectCustomizeBtn();
      console.log('[RBC] DOM setup done');
    } catch (e) { console.error('[RBC] init (DOM) error:', e); }
  }

  // ══════════════════════════════════════════════════════════════════
  // CONTEXT MENU
  // ══════════════════════════════════════════════════════════════════
  function _buildCtx() {
    if ($q('#rbc-ctx')) return;
    const el = document.createElement('div');
    el.id = 'rbc-ctx';
    el.style.cssText = 'display:none;position:fixed;z-index:99999;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 28px rgba(0,0,0,.25);padding:4px;min-width:220px;user-select:none';
    document.body.appendChild(el);

    // Close on any non-right-click outside
    document.addEventListener('mousedown', e => {
      if (e.button === 2) return;
      if (el.style.display === 'none') return;
      if (!el.contains(e.target)) _ctxHide();
    });
  }

  function _ctxBuild(tabEl) {
    const el = $q('#rbc-ctx');
    if (!el) return;

    if (!_defs) _snap();
    if (!_raw)  _raw = { roles: {}, users: {} };

    let eff;
    try { eff = _effective(); }
    catch (e) { console.error('[RBC] _ctxBuild effective() error:', e); eff = _factory(); }

    if (tabEl) {
      const tabId    = tabEl.dataset.tab;
      const tabLabel = tabEl.textContent.trim();
      const isHidden = !!eff.tab_hidden[tabId];
      el.innerHTML = `
        <div class="rbc-ctx-head">${_esc(tabLabel)}</div>
        <div class="rbc-ctx-item" data-action="toggle-tab">
          <i class="fa ${isHidden ? 'fa-eye' : 'fa-eye-slash'}"></i>
          ${isHidden ? 'Show tab' : 'Hide tab'}
        </div>
        <div class="rbc-ctx-item" data-action="customize-tab">
          <i class="fa fa-sliders"></i> Customize buttons…
        </div>
        <div class="rbc-ctx-sep"></div>
        <div class="rbc-ctx-item" data-action="reorder">
          <i class="fa fa-grip-lines"></i> Reorder / manage all tabs…
        </div>`;
    } else {
      el.innerHTML = `
        <div class="rbc-ctx-item" data-action="customize-all">
          <i class="fa fa-sliders"></i> Customize Ribbon…
        </div>`;
    }

    $qa('.rbc-ctx-item[data-action]', el).forEach(item => {
      item.addEventListener('mousedown', e => {
        e.stopPropagation();
        const action = item.dataset.action;
        _ctxHide();
        if (action === 'toggle-tab')    _quickToggleTab(_ctxTab);
        if (action === 'customize-tab') _openTabDialog(_ctxTab);
        if (action === 'reorder')       rbcOpen();
        if (action === 'customize-all') rbcOpen();
      });
    });
  }

  function _ctxShow(x, y, tabEl) {
    console.log('[RBC] _ctxShow called', x, y, tabEl?.dataset?.tab);
    _ctxTab = tabEl;
    _buildCtx();
    _ctxBuild(tabEl);

    const el = $q('#rbc-ctx');
    if (!el) { console.error('[RBC] #rbc-ctx not found after _buildCtx'); return; }

    // Position (clamp to viewport using estimates)
    const mw = 230, mh = 180;
    el.style.left = Math.min(x, window.innerWidth  - mw - 8) + 'px';
    el.style.top  = Math.min(y, window.innerHeight - mh - 8) + 'px';
    el.style.display = 'block';
    console.log('[RBC] context menu shown at', el.style.left, el.style.top);
  }

  function _ctxHide() {
    const el = $q('#rbc-ctx');
    if (el) el.style.display = 'none';
  }

  async function _quickToggleTab(tabEl) {
    if (!tabEl) return;
    if (!_defs) _snap();
    if (!_raw)  _raw = { roles: {}, users: {} };
    const id  = tabEl.dataset.tab;
    const eff = _effective();
    const hide = !eff.tab_hidden[id];
    const uk = _uctx().userKey;
    if (uk) {
      if (!_raw.users[uk]) _raw.users[uk] = _factory();
      _raw.users[uk].tab_hidden[id] = hide;
      if (!hide) delete _raw.users[uk].tab_hidden[id];
    }
    tabEl.style.display = hide ? 'none' : '';
    await _persist();
  }

  // ══════════════════════════════════════════════════════════════════
  // ⚙ BUTTON
  // ══════════════════════════════════════════════════════════════════
  function _injectCustomizeBtn() {
    if ($q('#rbc-open-btn')) return;
    const pinBtn = $q('#ribbon-pin-btn');
    if (!pinBtn) { console.warn('[RBC] #ribbon-pin-btn not found'); return; }
    const btn = document.createElement('button');
    btn.id = 'rbc-open-btn';
    btn.title = 'Customize Ribbon';
    btn.style.cssText = 'background:transparent;border:none;cursor:pointer;padding:0 8px;height:100%;color:var(--text-muted);font-size:13px;display:inline-flex;align-items:center;gap:4px;transition:color .15s';
    btn.innerHTML = '<i class="fa fa-sliders"></i>';
    btn.addEventListener('mouseenter', () => btn.style.color = 'var(--accent)');
    btn.addEventListener('mouseleave', () => btn.style.color = 'var(--text-muted)');
    btn.addEventListener('click', rbcOpen);
    pinBtn.parentNode.insertBefore(btn, pinBtn);
    console.log('[RBC] ⚙ button injected');
  }

  // ══════════════════════════════════════════════════════════════════
  // DIALOG
  // ══════════════════════════════════════════════════════════════════
  function _buildDialog() {
    if ($q('#rbc-overlay')) return;
    const ov = document.createElement('div');
    ov.id = 'rbc-overlay';
    ov.setAttribute('role', 'dialog');
    ov.setAttribute('aria-modal', 'true');
    ov.innerHTML = `
      <div id="rbc-dialog">
        <div class="rbc-head">
          <div class="rbc-head-icon"><i class="fa fa-sliders"></i></div>
          <div style="flex:1">
            <div class="rbc-head-title" id="rbc-head-title">Customize Ribbon</div>
            <div class="rbc-head-sub"   id="rbc-head-sub">Drag tabs to reorder · toggle visibility · L / S for button size</div>
          </div>
          <button class="rbc-head-close" id="rbc-close" aria-label="Close">&#x2715;</button>
        </div>

        <div class="rbc-scope-bar" id="rbc-scope-bar">
          <span class="rbc-scope-for"><i class="fa fa-user-gear"></i> Editing for:</span>
          <button class="rbc-scope-pill active" data-scope-type="user">
            <i class="fa fa-user"></i>
            <span id="rbc-scope-my-label">My Settings</span>
          </button>
          <div class="rbc-scope-div"   id="rbc-scope-div"   style="display:none"></div>
          <div class="rbc-scope-roles" id="rbc-scope-roles" style="display:none"></div>
        </div>

        <div class="rbc-body">
          <div class="rbc-tabs-panel" id="rbc-tabs-panel">
            <div class="rbc-panel-hd"><i class="fa fa-layer-group"></i>&ensp;Tabs</div>
            <div class="rbc-tab-list"  id="rbc-tab-list"></div>
          </div>
          <div class="rbc-btns-panel" id="rbc-btns-panel">
            <div class="rbc-btns-panel-hd" id="rbc-btns-hd" style="display:none">
              <div class="rbc-btns-panel-hd-top">
                <i class="fa fa-grip" style="color:var(--text-muted)"></i>
                <span class="rbc-btns-tab-name" id="rbc-btns-tab-name"></span>
              </div>
              <div class="rbc-btns-hint">Check = show &nbsp;|&nbsp; Uncheck = hide &nbsp;|&nbsp; <b>L</b> = large &nbsp;|&nbsp; <b>S</b> = small</div>
            </div>
            <div id="rbc-btns-empty" class="rbc-btns-empty">
              <i class="fa fa-hand-point-left" style="font-size:26px;opacity:.3"></i>
              <span>Select a tab on the left to edit its buttons</span>
            </div>
            <div class="rbc-btns-scroll" id="rbc-btns-scroll" style="display:none"></div>
          </div>
        </div>

        <div class="rbc-foot">
          <div class="rbc-foot-note" id="rbc-foot-note">
            <i class="fa fa-circle-info"></i>&ensp;Saved per user, restored on next launch.
          </div>
          <button class="rbc-btn-reset" id="rbc-reset"><i class="fa fa-rotate-left"></i> Reset</button>
          <button class="rbc-btn-save"  id="rbc-save" ><i class="fa fa-floppy-disk"></i> Save</button>
        </div>
      </div>`;
    document.body.appendChild(ov);

    // Bind fixed controls
    ov.addEventListener('pointerdown', e => { if (e.target === ov) rbcClose(); });
    $q('#rbc-close', ov).addEventListener('click', rbcClose);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && ov.classList.contains('open')) rbcClose();
    });
    $q('#rbc-reset', ov).addEventListener('click', _resetScope);
    $q('#rbc-save',  ov).addEventListener('click', _saveAndClose);
    $q('[data-scope-type="user"]', ov).addEventListener('click', () => _switchScope('user', null));
  }

  function _openOverlay() {
    const ov = $q('#rbc-overlay');
    if (!ov) return;
    ov.style.display = 'flex';
    void ov.offsetWidth;
    ov.classList.add('open');
  }

  function rbcClose() {
    const ov = $q('#rbc-overlay');
    if (!ov) return;
    ov.classList.remove('open');
    setTimeout(() => { if (!ov.classList.contains('open')) ov.style.display = 'none'; }, 200);
  }

  // ── Scope bar ──────────────────────────────────────────────────────
  function _renderScopeBar() {
    const uc = _uctx();
    const myLabel = $q('#rbc-scope-my-label');
    if (myLabel) myLabel.textContent = uc.userName || 'My Settings';

    const divEl   = $q('#rbc-scope-div');
    const rolesEl = $q('#rbc-scope-roles');
    if (!divEl || !rolesEl) return;

    if (!uc.isAdmin) {
      divEl.style.display = rolesEl.style.display = 'none';
      return;
    }

    divEl.style.display   = '';
    rolesEl.style.display = '';
    rolesEl.innerHTML = '';
    ROLES.forEach(r => {
      const btn = document.createElement('button');
      btn.className = 'rbc-scope-pill';
      btn.dataset.scopeType = 'role';
      btn.dataset.scopeRole = r.key;
      btn.innerHTML = `<i class="fa fa-users"></i> ${_esc(r.label)}`;
      btn.addEventListener('click', () => _switchScope('role', r.key));
      rolesEl.appendChild(btn);
    });
    _highlightScopePill();
  }

  function _highlightScopePill() {
    $qa('.rbc-scope-pill').forEach(p => {
      const ok = (_scope?.type === 'user' && p.dataset.scopeType === 'user')
              || (_scope?.type === 'role' && p.dataset.scopeType === 'role' && p.dataset.scopeRole === _scope.roleKey);
      p.classList.toggle('active', ok);
    });
  }

  function _switchScope(type, roleKey) {
    _scope = { type, roleKey };
    _edit  = _scopePrefs();
    _selTab = null;
    _highlightScopePill();
    _updateFootNote();
    _renderTabList();
    _renderBtnPanel(null);
  }

  function _updateFootNote() {
    const el = $q('#rbc-foot-note');
    if (!el) return;
    if (_scope?.type === 'role') {
      const lbl = ROLES.find(r => r.key === _scope.roleKey)?.label || _scope.roleKey;
      el.innerHTML = `<i class="fa fa-circle-info"></i>&ensp;Editing defaults for <strong>${_esc(lbl)}</strong> role — applies to all users with that role.`;
    } else {
      const who = _uctx().userName ? `<strong>${_esc(_uctx().userName)}</strong>` : 'your account';
      el.innerHTML = `<i class="fa fa-circle-info"></i>&ensp;Personal settings for ${who} — overrides role defaults.`;
    }
  }

  // ══════════════════════════════════════════════════════════════════
  // OPEN — tab-scoped dialog (right-click → Customize buttons)
  // ══════════════════════════════════════════════════════════════════
  function _openTabDialog(tabEl) {
    try {
      if (!tabEl) return;
      _snap(); // always re-snap to pick up current feature-flag state
      if (!_raw)  _raw = { roles: {}, users: {} };
      _buildDialog();

      _scope  = { type: 'user', roleKey: null };
      _edit   = _scopePrefs();
      _selTab = tabEl.dataset.tab;

      $q('#rbc-head-title').textContent = `Customize: ${tabEl.textContent.trim()}`;
      $q('#rbc-head-sub').textContent   = 'Toggle visibility and size for buttons on this tab.';
      $q('#rbc-tabs-panel').style.display = 'none';

      _renderScopeBar();
      _updateFootNote();
      _renderBtnPanel(_selTab);
      _openOverlay();
    } catch (e) { console.error('[RBC] _openTabDialog error:', e); }
  }

  // ══════════════════════════════════════════════════════════════════
  // OPEN — full editor (⚙ button)
  // ══════════════════════════════════════════════════════════════════
  function rbcOpen() {
    try {
      _snap(); // always re-snap to pick up current feature-flag state
      if (!_raw)  _raw = { roles: {}, users: {} };
      _buildDialog();

      _scope  = { type: 'user', roleKey: null };
      _edit   = _scopePrefs();
      _selTab = null;

      $q('#rbc-head-title').textContent = 'Customize Ribbon';
      $q('#rbc-head-sub').textContent   = 'Drag tabs to reorder · toggle visibility · L / S for button size';
      $q('#rbc-tabs-panel').style.display = '';

      _renderScopeBar();
      _updateFootNote();
      _renderTabList();
      _renderBtnPanel(null);
      _openOverlay();
      console.log('[RBC] full dialog opened');
    } catch (e) { console.error('[RBC] rbcOpen error:', e); }
  }

  // ── Tab list ───────────────────────────────────────────────────────
  function _renderTabList() {
    const list = $q('#rbc-tab-list');
    if (!list || !_defs) return;
    list.innerHTML = '';
    const known   = new Set((_defs.tabs || []).map(t => t.id));
    const inOrder = (_edit?.tab_order || []).filter(id => known.has(id));
    const missing = (_defs.tabs || []).filter(t => !inOrder.includes(t.id)).map(t => t.id);

    [...inOrder, ...missing].forEach(id => {
      const def     = (_defs.tabs || []).find(t => t.id === id);
      if (!def) return;
      const hidden   = !!_edit?.tab_hidden?.[id];
      const selected = id === _selTab;
      const row = document.createElement('div');
      row.className = 'rbc-tab-row' + (hidden ? ' tab-hidden' : '') + (selected ? ' selected' : '');
      row.dataset.tabId = id;
      row.draggable = true;
      row.innerHTML = `
        <span class="rbc-drag-handle"><i class="fa fa-grip-lines"></i></span>
        <input type="checkbox" class="rbc-tab-check" ${hidden ? '' : 'checked'}>
        <span class="rbc-tab-name">${_esc(def.label)}</span>`;

      row.addEventListener('click', e => {
        if (e.target.tagName === 'INPUT') return;
        _selTab = id;
        $qa('.rbc-tab-row.selected', list).forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        _renderBtnPanel(id);
      });
      row.querySelector('input').addEventListener('change', e => {
        if (!_edit.tab_hidden) _edit.tab_hidden = {};
        _edit.tab_hidden[id] = !e.target.checked;
        row.classList.toggle('tab-hidden', !e.target.checked);
      });
      row.addEventListener('dragstart', e => {
        _dragSrc = id;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', id);
        setTimeout(() => row.classList.add('dragging'), 0);
      });
      row.addEventListener('dragend', () => {
        row.classList.remove('dragging');
        $qa('.drag-over', list).forEach(r => r.classList.remove('drag-over'));
        _dragSrc = null;
      });
      row.addEventListener('dragover', e => {
        e.preventDefault();
        if (_dragSrc && _dragSrc !== id) {
          $qa('.drag-over', list).forEach(r => r.classList.remove('drag-over'));
          row.classList.add('drag-over');
        }
      });
      row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
      row.addEventListener('drop', e => {
        e.preventDefault();
        row.classList.remove('drag-over');
        if (!_dragSrc || _dragSrc === id) return;
        const arr  = [...(_edit.tab_order || [])];
        const from = arr.indexOf(_dragSrc);
        let   to   = arr.indexOf(id);
        if (from !== -1) arr.splice(from, 1);
        if (to === -1) to = arr.length; else if (from < to) to--;
        arr.splice(to, 0, _dragSrc);
        _edit.tab_order = arr;
        _dragSrc = null;
        _renderTabList();
      });
      list.appendChild(row);
    });
  }

  // ── Button panel ───────────────────────────────────────────────────
  function _renderBtnPanel(tabId) {
    const empty  = $q('#rbc-btns-empty');
    const scroll = $q('#rbc-btns-scroll');
    const hd     = $q('#rbc-btns-hd');
    const title  = $q('#rbc-btns-tab-name');
    if (!empty || !scroll || !hd) return;

    if (!tabId || !_defs) {
      empty.style.display  = '';
      scroll.style.display = 'none';
      hd.style.display     = 'none';
      return;
    }

    const tabDef = (_defs.tabs || []).find(t => t.id === tabId);
    if (title) title.textContent = tabDef?.label || tabId;
    empty.style.display  = 'none';
    hd.style.display     = '';
    scroll.style.display = '';
    scroll.innerHTML     = '';

    const btns = Object.values(_defs.buttons || {}).filter(b => b.page === tabId);
    if (!btns.length) {
      scroll.innerHTML = '<div style="padding:14px 6px;color:var(--text-muted);font-size:13px">No customisable buttons on this tab.</div>';
      return;
    }

    const groups = {};
    btns.forEach(b => (groups[b.group] ??= []).push(b));

    Object.entries(groups).forEach(([grpLbl, grpBtns]) => {
      const sep = document.createElement('div');
      sep.className = 'rbc-btn-group-sep';
      sep.innerHTML = `<span class="rbc-btn-group-sep-label">${_esc(grpLbl)}</span><span class="rbc-btn-group-sep-line"></span>`;
      scroll.appendChild(sep);

      grpBtns.forEach(def => {
        const hidden  = !!_edit?.btn_hidden?.[def.id];
        const curSize = _edit?.btn_size?.[def.id] || def.defaultSize;
        const row = document.createElement('div');
        row.className = 'rbc-btn-row' + (hidden ? ' btn-hidden' : '');
        row.innerHTML = `
          <input type="checkbox" class="rbc-btn-check" ${hidden ? '' : 'checked'}>
          <span class="rbc-btn-label">${_esc(def.label)}</span>
          <div class="rbc-size-toggle">
            <button class="rbc-size-opt ${curSize === 'lg' ? 'active' : ''}" data-size="lg" type="button">L</button>
            <button class="rbc-size-opt ${curSize === 'sm' ? 'active' : ''}" data-size="sm" type="button">S</button>
          </div>`;
        row.querySelector('input').addEventListener('change', e => {
          if (!_edit.btn_hidden) _edit.btn_hidden = {};
          _edit.btn_hidden[def.id] = !e.target.checked;
          row.classList.toggle('btn-hidden', !e.target.checked);
        });
        $qa('.rbc-size-opt', row).forEach(opt => {
          opt.addEventListener('click', () => {
            const sz = opt.dataset.size;
            if (!_edit.btn_size) _edit.btn_size = {};
            if (sz === def.defaultSize) delete _edit.btn_size[def.id];
            else _edit.btn_size[def.id] = sz;
            $qa('.rbc-size-opt', row).forEach(o => o.classList.toggle('active', o.dataset.size === sz));
          });
        });
        scroll.appendChild(row);
      });
    });
  }

  // ── Reset / Save ───────────────────────────────────────────────────
  function _resetScope() {
    if (!_defs) _snap();
    const isSingle = $q('#rbc-tabs-panel')?.style.display === 'none' && _selTab;
    if (isSingle) {
      if (_edit.btn_hidden) Object.keys(_edit.btn_hidden).forEach(id => { if (_defs.buttons[id]?.page === _selTab) delete _edit.btn_hidden[id]; });
      if (_edit.btn_size)   Object.keys(_edit.btn_size).forEach(id   => { if (_defs.buttons[id]?.page === _selTab) delete _edit.btn_size[id];   });
    } else {
      _edit = _factory();
      _selTab = null;
      _renderTabList();
    }
    _renderBtnPanel(_selTab);
  }

  async function _saveAndClose() {
    const saveBtn = $q('#rbc-save');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…'; }
    try {
      _commitEdit();
      await _persist();
      _apply(_effective());
      rbcClose();
    } catch (e) {
      console.error('[RBC] save error:', e);
    } finally {
      if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fa fa-floppy-disk"></i> Save'; }
    }
  }

  function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ══════════════════════════════════════════════════════════════════
  // BOOTSTRAP
  // ══════════════════════════════════════════════════════════════════
  function _boot() {
    document.addEventListener('contextmenu', e => {
      try {
        // Log every contextmenu so we can see what element is targeted
        console.log('[RBC] contextmenu on:', e.target.tagName,
          '| id:', e.target.id || '(none)',
          '| class:', (e.target.className || '').toString().slice(0, 60));

        // Use simple class selector — no ancestor requirement that might fail
        const tabEl = e.target.closest('.ribbon-tab[data-tab]');
        console.log('[RBC] tabEl:', tabEl ? tabEl.dataset.tab : 'null');

        if (!tabEl) return;
        e.preventDefault();
        e.stopPropagation();
        _ctxShow(e.clientX, e.clientY, tabEl);
      } catch (err) { console.error('[RBC] contextmenu handler error:', err); }
    }, true);
    console.log('[RBC] ready — contextmenu delegation active');
  }

  window.rbcInit = rbcInit;
  window.rbcOpen = rbcOpen;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _boot);
  } else {
    _boot();
  }

})();
