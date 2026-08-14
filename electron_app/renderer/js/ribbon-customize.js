/* ══════════════════════════════════════════════════════════════════
   RIBBON CUSTOMIZER
   • Right-click a specific tab  →  tab-scoped context menu →
     dialog shows only THAT tab's buttons (no left panel)
   • Click the ⚙ button          →  full editor (all tabs + buttons)
   Prefs stored in electron config as "ribbon_prefs" per user.
   ══════════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  const PREFS_KEY = 'ribbon_prefs';

  // ── Local DOM helpers ──────────────────────────────────────────────
  const $q  = (s, p) => (p || document).querySelector(s);
  const $qa = (s, p) => [...(p || document).querySelectorAll(s)];

  // ── Module state ───────────────────────────────────────────────────
  let _defs    = null;   // snapped from DOM
  let _saved   = null;   // last persisted
  let _edit    = null;   // working copy while dialog open
  let _selTab  = null;   // tab selected in full-editor left panel
  let _ctxTab  = null;   // tab element that received the right-click
  let _dragSrc = null;   // id being dragged

  // ══════════════════════════════════════════════════════════════════
  // SNAPSHOT — read current ribbon DOM into _defs
  // ══════════════════════════════════════════════════════════════════
  function _snap() {
    const tabs = $qa('#ribbon-tabs .ribbon-tab[data-tab]').map(t => ({
      id:    t.dataset.tab,
      label: t.textContent.trim(),
    }));

    const buttons = {};
    $qa('#ribbon-body .ribbon-page[data-page]').forEach(page => {
      $qa('button[id]', page).forEach(btn => {
        if (!btn.id.startsWith('rb-')) return;
        const clone = btn.cloneNode(true);
        $qa('kbd', clone).forEach(k => k.remove());
        const label = clone.textContent.replace(/\s+/g, ' ').trim() || btn.title || btn.id;
        buttons[btn.id] = {
          id:    btn.id, label,
          defaultSize: btn.classList.contains('ribbon-btn-lg') ? 'lg' : 'sm',
          page:  page.dataset.page,
          group: btn.closest('.ribbon-group')
                   ?.querySelector('.ribbon-group-label')
                   ?.textContent?.trim() || '—',
        };
      });
    });

    _defs = { tabs, buttons };
  }

  // ══════════════════════════════════════════════════════════════════
  // PREFS — init / apply / save
  // ══════════════════════════════════════════════════════════════════
  function _freshPrefs() {
    if (!_defs) _snap();
    return { tab_order: _defs.tabs.map(t => t.id), tab_hidden: {}, btn_hidden: {}, btn_size: {} };
  }

  async function rbcInit() {
    try {
      _snap();
      let raw = null;
      try { const cfg = await window.electronAPI?.getConfig?.(); raw = cfg?.[PREFS_KEY] ?? null; } catch (_) {}

      const def = _freshPrefs();
      _saved = {
        tab_order:  Array.isArray(raw?.tab_order)          ? raw.tab_order : [...def.tab_order],
        tab_hidden: raw?.tab_hidden && typeof raw.tab_hidden === 'object' ? { ...raw.tab_hidden } : {},
        btn_hidden: raw?.btn_hidden && typeof raw.btn_hidden === 'object' ? { ...raw.btn_hidden } : {},
        btn_size:   raw?.btn_size   && typeof raw.btn_size   === 'object' ? { ...raw.btn_size   } : {},
      };
      def.tab_order.forEach(id => { if (!_saved.tab_order.includes(id)) _saved.tab_order.push(id); });
      _applyPrefs(_saved);
    } catch (e) { console.error('[RBC] init:', e); }
  }

  function _applyPrefs(p) {
    if (!p || !_defs) return;
    try {
      const tabRow = $q('#ribbon-tabs');
      if (!tabRow) return;

      // order
      p.tab_order.forEach(id => {
        const el = tabRow.querySelector(`.ribbon-tab[data-tab="${CSS.escape(id)}"]`);
        if (el) tabRow.appendChild(el);
      });
      // visibility (tabs)
      $qa('.ribbon-tab[data-tab]', tabRow).forEach(t => {
        t.style.display = p.tab_hidden?.[t.dataset.tab] ? 'none' : '';
      });
      // buttons
      Object.values(_defs.buttons).forEach(def => {
        const btn = document.getElementById(def.id);
        if (!btn) return;
        btn.style.display = p.btn_hidden?.[def.id] ? 'none' : '';
        const sz = p.btn_size?.[def.id] || def.defaultSize;
        btn.classList.toggle('ribbon-btn-lg', sz === 'lg');
        btn.classList.toggle('ribbon-btn-sm', sz === 'sm');
      });
    } catch (e) { console.error('[RBC] apply:', e); }
  }

  async function _persist(p) {
    _saved = { ...p };
    try { await window.electronAPI?.setConfig?.({ [PREFS_KEY]: p }); }
    catch (e) { console.error('[RBC] save:', e); }
  }

  // ══════════════════════════════════════════════════════════════════
  // CONTEXT MENU  — tab-scoped when right-clicking a tab element
  // ══════════════════════════════════════════════════════════════════
  function _buildCtx() {
    if ($q('#rbc-ctx')) return;
    const el = document.createElement('div');
    el.id = 'rbc-ctx';
    // items populated dynamically in _ctxShow()
    document.body.appendChild(el);

    // Close on outside pointerdown
    document.addEventListener('pointerdown', e => {
      if (el.classList.contains('open') && !el.contains(e.target)) _ctxHide();
    }, true);
  }

  function _ctxShow(x, y, tabEl) {
    _ctxTab = tabEl;  // may be null if right-clicking ribbon body
    const ctx = $q('#rbc-ctx');
    if (!ctx) return;

    // Build menu items based on context
    if (tabEl) {
      // ── Tab-specific menu ──────────────────────────────────────
      if (!_defs) _snap();
      if (!_saved) _saved = _freshPrefs();
      const tabId    = tabEl.dataset.tab;
      const tabLabel = tabEl.textContent.trim();
      const isHidden = !!_saved.tab_hidden[tabId];

      ctx.innerHTML = `
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
      // ── Generic ribbon menu ────────────────────────────────────
      ctx.innerHTML = `
        <div class="rbc-ctx-item" data-action="customize-all">
          <i class="fa fa-sliders"></i> Customize Ribbon…
        </div>
        <div class="rbc-ctx-sep"></div>
        <div class="rbc-ctx-item" data-action="collapse">
          <i class="fa fa-chevron-up"></i> Collapse Ribbon
        </div>`;
    }

    // Bind clicks on items
    $qa('.rbc-ctx-item[data-action]', ctx).forEach(item => {
      item.addEventListener('mousedown', e => {
        e.stopPropagation();
        const action = item.dataset.action;
        _ctxHide();

        if (action === 'toggle-tab')    _quickToggleTab(_ctxTab);
        if (action === 'customize-tab') _openTabDialog(_ctxTab);
        if (action === 'reorder')       rbcOpen();        // full editor
        if (action === 'customize-all') rbcOpen();        // full editor
        if (action === 'collapse')      $q('#ribbon-pin-btn')?.click();
      });
    });

    // Position
    ctx.classList.remove('open');
    void ctx.offsetWidth;
    ctx.style.left = '-9999px'; ctx.style.top = '-9999px';
    ctx.classList.add('open');
    const r = ctx.getBoundingClientRect();
    ctx.style.left = Math.min(x, window.innerWidth  - r.width  - 8) + 'px';
    ctx.style.top  = Math.min(y, window.innerHeight - r.height - 8) + 'px';
  }

  function _ctxHide() { $q('#rbc-ctx')?.classList.remove('open'); }

  // Quick toggle tab visibility without opening dialog
  async function _quickToggleTab(tabEl) {
    if (!tabEl) return;
    if (!_defs)  _snap();
    if (!_saved) _saved = _freshPrefs();
    const id = tabEl.dataset.tab;
    const newHidden = !_saved.tab_hidden[id];
    _saved.tab_hidden[id] = newHidden;
    if (!newHidden) delete _saved.tab_hidden[id];
    tabEl.style.display = newHidden ? 'none' : '';
    await _persist(_saved);
  }

  // ── Bind right-click listeners to every ribbon tab ────────────────
  function _bindTabContextMenus() {
    // Bind to existing tabs
    $qa('#ribbon-tabs .ribbon-tab[data-tab]').forEach(_attachTabCtx);

    // Observe future tabs added dynamically (e.g. features loaded late)
    const observer = new MutationObserver(muts => {
      muts.forEach(m => m.addedNodes.forEach(n => {
        if (n.nodeType === 1 && n.classList?.contains('ribbon-tab')) _attachTabCtx(n);
      }));
    });
    const tabsRow = $q('#ribbon-tabs');
    if (tabsRow) observer.observe(tabsRow, { childList: true });
  }

  function _attachTabCtx(tabEl) {
    if (tabEl._rbcCtxBound) return;
    tabEl._rbcCtxBound = true;
    tabEl.addEventListener('contextmenu', e => {
      e.preventDefault();
      e.stopPropagation();
      _ctxShow(e.clientX, e.clientY, tabEl);
    });
  }

  // ══════════════════════════════════════════════════════════════════
  // CUSTOMIZE BUTTON — ⚙ icon in the tab row (opens full editor)
  // ══════════════════════════════════════════════════════════════════
  function _injectCustomizeBtn() {
    if ($q('#rbc-open-btn')) return;
    const pinBtn = $q('#ribbon-pin-btn');
    if (!pinBtn) return;
    const btn = document.createElement('button');
    btn.id = 'rbc-open-btn';
    btn.title = 'Customize Ribbon';
    btn.style.cssText = 'background:transparent;border:none;cursor:pointer;padding:0 8px;height:100%;color:var(--text-muted);font-size:13px;display:inline-flex;align-items:center;gap:4px;transition:color .15s';
    btn.innerHTML = '<i class="fa fa-sliders"></i>';
    btn.addEventListener('mouseenter', () => btn.style.color = 'var(--accent)');
    btn.addEventListener('mouseleave', () => btn.style.color = 'var(--text-muted)');
    btn.addEventListener('click', rbcOpen);
    pinBtn.parentNode.insertBefore(btn, pinBtn);
  }

  // ══════════════════════════════════════════════════════════════════
  // DIALOG — shared for both modes
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
            <div class="rbc-head-sub"  id="rbc-head-sub">Drag tabs to reorder &middot; toggle visibility &middot; L / S for button size</div>
          </div>
          <button class="rbc-head-close" id="rbc-close" aria-label="Close">&#x2715;</button>
        </div>

        <div class="rbc-body">

          <!-- Left: tabs panel (hidden in single-tab mode) -->
          <div class="rbc-tabs-panel" id="rbc-tabs-panel">
            <div class="rbc-panel-hd"><i class="fa fa-layer-group"></i>&ensp;Tabs</div>
            <div class="rbc-tab-list" id="rbc-tab-list"></div>
          </div>

          <!-- Right: buttons panel -->
          <div class="rbc-btns-panel" id="rbc-btns-panel">
            <div class="rbc-btns-panel-hd" id="rbc-btns-hd" style="display:none">
              <div class="rbc-btns-panel-hd-top">
                <i class="fa fa-grip" style="color:var(--text-muted)"></i>
                <span class="rbc-btns-tab-name" id="rbc-btns-tab-name"></span>
              </div>
              <div class="rbc-btns-hint">Check = show &nbsp;|&nbsp; Uncheck = hide &nbsp;|&nbsp; <b>L</b> = large button &nbsp;|&nbsp; <b>S</b> = small button</div>
            </div>
            <div id="rbc-btns-empty" class="rbc-btns-empty">
              <i class="fa fa-hand-point-left" style="font-size:26px;opacity:.3"></i>
              <span>Select a tab on the left to edit its buttons</span>
            </div>
            <div class="rbc-btns-scroll" id="rbc-btns-scroll" style="display:none"></div>
          </div>

        </div>

        <div class="rbc-foot">
          <div class="rbc-foot-note" id="rbc-foot-note"><i class="fa fa-circle-info"></i>&ensp;Saved per user, restored on next launch.</div>
          <button class="rbc-btn-reset" id="rbc-reset"><i class="fa fa-rotate-left"></i> Reset</button>
          <button class="rbc-btn-save"  id="rbc-save"><i class="fa fa-floppy-disk"></i> Save</button>
        </div>

      </div>`;
    document.body.appendChild(ov);

    ov.addEventListener('pointerdown', e => { if (e.target === ov) rbcClose(); });
    $q('#rbc-close', ov).addEventListener('click', rbcClose);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && $q('#rbc-overlay.open')) rbcClose();
    });
    $q('#rbc-reset', ov).addEventListener('click', _resetCurrent);
    $q('#rbc-save',  ov).addEventListener('click', _saveAndClose);
  }

  // ── Show overlay ───────────────────────────────────────────────────
  function _openOverlay() {
    const ov = $q('#rbc-overlay');
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

  // ══════════════════════════════════════════════════════════════════
  // OPEN — TAB-SCOPED (right-click on a specific tab)
  // Only shows that tab's buttons; no left panel
  // ══════════════════════════════════════════════════════════════════
  function _openTabDialog(tabEl) {
    try {
      if (!tabEl) return;
      _snap();
      if (!_saved) _saved = _freshPrefs();
      _buildDialog();

      const tabId    = tabEl.dataset.tab;
      const tabLabel = tabEl.textContent.trim();

      _edit = {
        tab_order:  [..._saved.tab_order],
        tab_hidden: { ..._saved.tab_hidden },
        btn_hidden: { ..._saved.btn_hidden },
        btn_size:   { ..._saved.btn_size   },
      };

      // Title
      $q('#rbc-head-title').textContent = `Customize: ${tabLabel}`;
      $q('#rbc-head-sub').textContent   = 'Toggle visibility and size for buttons on this tab.';
      $q('#rbc-foot-note').innerHTML    = `<i class="fa fa-circle-info"></i>&ensp;Changes apply to the <strong>${_esc(tabLabel)}</strong> tab only.`;

      // Hide left tabs panel — single-tab mode
      $q('#rbc-tabs-panel').style.display = 'none';

      // Show button panel for this tab immediately
      _selTab = tabId;
      _renderBtnPanel(tabId);

      _openOverlay();
    } catch (e) { console.error('[RBC] _openTabDialog:', e); }
  }

  // ══════════════════════════════════════════════════════════════════
  // OPEN — FULL EDITOR (⚙ button or "Reorder all tabs…" menu item)
  // Shows left tabs panel + right buttons panel
  // ══════════════════════════════════════════════════════════════════
  function rbcOpen() {
    try {
      _snap();
      if (!_saved) _saved = _freshPrefs();
      _buildDialog();

      _edit = {
        tab_order:  [..._saved.tab_order],
        tab_hidden: { ..._saved.tab_hidden },
        btn_hidden: { ..._saved.btn_hidden },
        btn_size:   { ..._saved.btn_size   },
      };
      _selTab = null;

      // Full-editor titles
      $q('#rbc-head-title').textContent = 'Customize Ribbon';
      $q('#rbc-head-sub').textContent   = 'Drag tabs to reorder · toggle visibility · L / S for button size';
      $q('#rbc-foot-note').innerHTML    = '<i class="fa fa-circle-info"></i>&ensp;Saved per user, restored on next launch.';

      // Show left tabs panel
      $q('#rbc-tabs-panel').style.display = '';

      _renderTabList();
      _renderBtnPanel(null);
      _openOverlay();
    } catch (e) { console.error('[RBC] rbcOpen:', e); }
  }

  // ══════════════════════════════════════════════════════════════════
  // RENDER — left tabs panel
  // ══════════════════════════════════════════════════════════════════
  function _renderTabList() {
    const list = $q('#rbc-tab-list');
    if (!list || !_defs) return;
    list.innerHTML = '';

    const known    = new Set(_defs.tabs.map(t => t.id));
    const inOrder  = _edit.tab_order.filter(id => known.has(id));
    const missing  = _defs.tabs.filter(t => !inOrder.includes(t.id)).map(t => t.id);
    [...inOrder, ...missing].forEach(id => {
      const def = _defs.tabs.find(t => t.id === id);
      if (!def) return;
      const hidden   = !!_edit.tab_hidden[id];
      const selected = id === _selTab;
      const row = document.createElement('div');
      row.className = 'rbc-tab-row' + (hidden ? ' tab-hidden' : '') + (selected ? ' selected' : '');
      row.dataset.tabId = id;
      row.draggable = true;
      row.innerHTML = `
        <span class="rbc-drag-handle" title="Drag to reorder"><i class="fa fa-grip-lines"></i></span>
        <input type="checkbox" class="rbc-tab-check" ${hidden ? '' : 'checked'} title="Show / hide tab">
        <span class="rbc-tab-name">${_esc(def.label)}</span>`;

      row.addEventListener('click', e => {
        if (e.target.tagName === 'INPUT') return;
        _selTab = id;
        $qa('.rbc-tab-row.selected', list).forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        _renderBtnPanel(id);
      });

      row.querySelector('input').addEventListener('change', e => {
        _edit.tab_hidden[id] = !e.target.checked;
        row.classList.toggle('tab-hidden', !e.target.checked);
      });

      // Drag-to-reorder
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
        e.dataTransfer.dropEffect = 'move';
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
        const arr  = [..._edit.tab_order];
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

  // ══════════════════════════════════════════════════════════════════
  // RENDER — right buttons panel
  // ══════════════════════════════════════════════════════════════════
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

    const tabDef = _defs.tabs.find(t => t.id === tabId);
    title.textContent    = tabDef?.label || tabId;
    empty.style.display  = 'none';
    hd.style.display     = '';
    scroll.style.display = '';
    scroll.innerHTML     = '';

    const btns = Object.values(_defs.buttons).filter(b => b.page === tabId);
    if (!btns.length) {
      scroll.innerHTML = '<div style="padding:14px 6px;color:var(--text-muted);font-size:13px">No customisable buttons on this tab.</div>';
      return;
    }

    // Group by ribbon-group label
    const groups = {};
    btns.forEach(b => (groups[b.group] ??= []).push(b));

    Object.entries(groups).forEach(([groupLabel, groupBtns]) => {
      const sep = document.createElement('div');
      sep.className = 'rbc-btn-group-sep';
      sep.innerHTML = `<span class="rbc-btn-group-sep-label">${_esc(groupLabel)}</span><span class="rbc-btn-group-sep-line"></span>`;
      scroll.appendChild(sep);

      groupBtns.forEach(def => {
        const hidden  = !!_edit.btn_hidden[def.id];
        const curSize = _edit.btn_size[def.id] || def.defaultSize;
        const row = document.createElement('div');
        row.className = 'rbc-btn-row' + (hidden ? ' btn-hidden' : '');
        row.innerHTML = `
          <input type="checkbox" class="rbc-btn-check" ${hidden ? '' : 'checked'} title="Show / hide button">
          <span class="rbc-btn-label">${_esc(def.label)}</span>
          <div class="rbc-size-toggle" title="Button size">
            <button class="rbc-size-opt ${curSize === 'lg' ? 'active' : ''}" data-size="lg" type="button">L</button>
            <button class="rbc-size-opt ${curSize === 'sm' ? 'active' : ''}" data-size="sm" type="button">S</button>
          </div>`;

        row.querySelector('input').addEventListener('change', e => {
          _edit.btn_hidden[def.id] = !e.target.checked;
          row.classList.toggle('btn-hidden', !e.target.checked);
        });
        $qa('.rbc-size-opt', row).forEach(opt => {
          opt.addEventListener('click', () => {
            const sz = opt.dataset.size;
            if (sz === def.defaultSize) delete _edit.btn_size[def.id];
            else _edit.btn_size[def.id] = sz;
            $qa('.rbc-size-opt', row).forEach(o => o.classList.toggle('active', o.dataset.size === sz));
          });
        });
        scroll.appendChild(row);
      });
    });
  }

  // ── Reset ──────────────────────────────────────────────────────────
  function _resetCurrent() {
    if (!_defs) _snap();
    const fresh = _freshPrefs();
    // In single-tab mode reset only that tab's buttons
    if ($q('#rbc-tabs-panel').style.display === 'none' && _selTab) {
      Object.keys(_edit.btn_hidden).forEach(id => {
        if (_defs.buttons[id]?.page === _selTab) delete _edit.btn_hidden[id];
      });
      Object.keys(_edit.btn_size).forEach(id => {
        if (_defs.buttons[id]?.page === _selTab) delete _edit.btn_size[id];
      });
    } else {
      _edit = fresh;
      _selTab = null;
      _renderTabList();
    }
    _renderBtnPanel(_selTab);
  }

  // ── Save ───────────────────────────────────────────────────────────
  async function _saveAndClose() {
    const saveBtn = $q('#rbc-save');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…'; }
    await _persist(_edit);
    _applyPrefs(_edit);
    if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fa fa-floppy-disk"></i> Save'; }
    rbcClose();
  }

  // ── Utility ────────────────────────────────────────────────────────
  function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ══════════════════════════════════════════════════════════════════
  // BOOTSTRAP
  // ══════════════════════════════════════════════════════════════════
  function _boot() {
    try {
      _buildCtx();
      _bindTabContextMenus();
      _injectCustomizeBtn();
    } catch (e) { console.error('[RBC] boot:', e); }
  }

  window.rbcInit = rbcInit;
  window.rbcOpen = rbcOpen;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _boot);
  } else {
    _boot();
  }

})();
