/**
 * Zeebroo POS — Guide Assistant Engine
 *
 * Reads walkthrough definitions from window.GUIDE_CONFIG (guide-config.js).
 * To add a new guided walkthrough, edit guide-config.js only — no changes here.
 */
(function () {
  'use strict';

  /* ════════════════════════════════════════════════════════════════════════
     STATE
     ════════════════════════════════════════════════════════════════════════ */
  let _dismissed   = false;
  let _initialized = false;
  let _bubbleOpen  = false;
  let _busy        = false;

  /* ════════════════════════════════════════════════════════════════════════
     TEMPLATE + CONFIG HELPERS
     ════════════════════════════════════════════════════════════════════════ */

  // Replace {{varName}} with vars[varName]
  function _t(str, vars) {
    if (!str) return str || '';
    return str.replace(/\{\{(\w+)\}\}/g, (_, k) => (vars && vars[k] != null ? vars[k] : ''));
  }

  // Resolve a user-typed field name to a field_map entry
  function _resolveField(raw) {
    if (!raw) return null;
    const cfg = (window.GUIDE_CONFIG || {}).field_map || {};
    const key = raw.toLowerCase().trim();
    if (cfg[key]) return cfg[key];
    for (const [k, v] of Object.entries(cfg)) {
      if (key.includes(k) || k.includes(key)) return v;
    }
    return null;
  }

  // Try parse_patterns in order; return { matched, vars } or null
  function _matchWalkthrough(msg) {
    const walkthroughs = (window.GUIDE_CONFIG || {}).walkthroughs || [];
    const lc = msg.toLowerCase();

    for (const wt of walkthroughs) {
      // Simple substring match
      if (wt.intent_patterns) {
        if (wt.intent_patterns.some(p => lc.includes(p.toLowerCase()))) {
          return { wt, vars: {} };
        }
      }
      // Regex with named groups
      if (wt.parse_patterns) {
        for (const pattern of wt.parse_patterns) {
          const m = msg.match(new RegExp(pattern, 'i'));
          if (m && m.groups) {
            const vars = {};
            for (const [k, v] of Object.entries(m.groups)) {
              if (v != null) vars[k] = v.trim();
            }
            // Auto-populate fieldLabel from field_map
            if (vars.fieldName) {
              const fi = _resolveField(vars.fieldName);
              vars.fieldLabel = fi ? fi.label : vars.fieldName;
            }
            return { wt, vars };
          }
        }
      }
    }
    return null;
  }

  /* ════════════════════════════════════════════════════════════════════════
     UTILITIES
     ════════════════════════════════════════════════════════════════════════ */
  const _sleep = ms => new Promise(r => setTimeout(r, ms));

  function _highlight(el)   { el?.classList.add('guide-target-pulse'); }
  function _unhighlight(el) { el?.classList.remove('guide-target-pulse'); }

  function _qs(selector) {
    if (!selector) return null;
    try { return document.querySelector(selector); } catch (e) { return null; }
  }

  function _waitVisible(el, timeout) {
    timeout = timeout || 2500;
    return new Promise(resolve => {
      if (!el) { resolve(false); return; }
      const start = Date.now();
      const check = () => {
        const s = window.getComputedStyle(el);
        if (s.display !== 'none' && s.visibility !== 'hidden') { resolve(true); return; }
        if (Date.now() - start > timeout) { resolve(false); return; }
        setTimeout(check, 80);
      };
      check();
    });
  }

  function _waitForRows(tbodySelector, timeout) {
    timeout = timeout || 3000;
    return new Promise(resolve => {
      const start = Date.now();
      const check = () => {
        const tbody = _qs(tbodySelector);
        const rows  = tbody ? tbody.querySelectorAll('tr[class]') : [];
        if (rows.length > 0) { resolve(rows); return; }
        if (Date.now() - start > timeout) { resolve([]); return; }
        setTimeout(check, 100);
      };
      check();
    });
  }

  /* ════════════════════════════════════════════════════════════════════════
     CLOSE ALL OPEN MODALS / DIALOGS
     ════════════════════════════════════════════════════════════════════════ */
  function _closeAllModals() {
    // Every standard modal-overlay
    document.querySelectorAll('.modal-overlay').forEach(m => {
      if (getComputedStyle(m).display !== 'none') m.style.display = 'none';
    });
    // Non-overlay modals that need individual handling
    [
      '#checkout-modal',
      '#bc-preview-modal',
      '#pos-layer-picker',
      '#search-suggest',
    ].forEach(sel => {
      const el = _qs(sel);
      if (el && getComputedStyle(el).display !== 'none') el.style.display = 'none';
    });
    // Remove any pulse highlights left over from a previous walkthrough
    document.querySelectorAll('.guide-target-pulse').forEach(el => {
      el.classList.remove('guide-target-pulse');
    });
  }

  /* ════════════════════════════════════════════════════════════════════════
     CHARACTER MOVEMENT
     ════════════════════════════════════════════════════════════════════════ */
  function _walkTo(el) {
    return new Promise(resolve => {
      const wrap = document.getElementById('guide-char-wrap');
      if (!wrap || !el) { setTimeout(resolve, 100); return; }

      const r    = el.getBoundingClientRect();
      const charW = 96;

      let top  = r.bottom + 14;
      let left = r.left + r.width / 2 - charW / 2;
      if (top + 110 > window.innerHeight) top = Math.max(4, r.top - 114);
      left = Math.max(4, Math.min(window.innerWidth - charW - 4, left));
      top  = Math.max(4, top);

      if (wrap.style.bottom !== 'auto') {
        const cr = wrap.getBoundingClientRect();
        wrap.style.top    = cr.top  + 'px';
        wrap.style.left   = cr.left + 'px';
        wrap.style.bottom = 'auto';
        wrap.style.right  = 'auto';
      }
      void wrap.offsetWidth;
      wrap.style.transition = 'top 0.6s cubic-bezier(0.4,0,0.2,1), left 0.6s cubic-bezier(0.4,0,0.2,1)';
      wrap.style.top  = top  + 'px';
      wrap.style.left = left + 'px';

      const img = document.getElementById('guide-char-img');
      img?.classList.add('guide-walk-bounce');
      setTimeout(() => { img?.classList.remove('guide-walk-bounce'); resolve(); }, 680);
    });
  }

  function _returnHome() {
    return new Promise(resolve => {
      const wrap = document.getElementById('guide-char-wrap');
      if (!wrap) { resolve(); return; }
      const t = window.innerHeight - 24 - 120;
      const l = window.innerWidth  - 24 - 96;
      wrap.style.transition = 'top 0.65s cubic-bezier(0.4,0,0.2,1), left 0.65s cubic-bezier(0.4,0,0.2,1)';
      wrap.style.top  = t + 'px';
      wrap.style.left = l + 'px';
      setTimeout(() => {
        wrap.style.transition = '';
        wrap.style.top = 'auto'; wrap.style.left   = 'auto';
        wrap.style.bottom = '24px'; wrap.style.right = '24px';
        resolve();
      }, 700);
    });
  }

  /* ════════════════════════════════════════════════════════════════════════
     WALKTHROUGH ENGINE  — executes a steps array
     Returns false if aborted early (e.g. product not found).
     ════════════════════════════════════════════════════════════════════════ */
  async function _runSteps(steps, vars) {
    for (const step of steps) {
      const sel = step.selector ? _t(step.selector, vars) : null;
      const el  = sel ? _qs(sel) : null;

      switch (step.type) {

        case 'walk_click': {
          if (!el) break;
          await _walkTo(el);
          _highlight(el);
          await _sleep(500);
          el.click();
          _unhighlight(el);
          if (step.wait) await _sleep(step.wait);
          break;
        }

        case 'walk_to': {
          if (el) await _walkTo(el);
          break;
        }

        case 'highlight': {
          if (el) _highlight(el);
          break;
        }

        case 'unhighlight': {
          if (el) _unhighlight(el);
          break;
        }

        case 'bubble': {
          _reopenWithReply(_t(step.text, vars));
          if (step.wait) {
            await _sleep(step.wait);
            _closeBubble();
            await _sleep(200);
          }
          break;
        }

        case 'wait_visible': {
          if (el) await _waitVisible(el, step.timeout || 2500);
          if (step.wait) await _sleep(step.wait);
          break;
        }

        case 'walk_search': {
          if (!el) break;
          await _walkTo(el);
          _highlight(el);
          await _sleep(300);
          el.value = _t(step.value, vars);
          el.dispatchEvent(new Event('input', { bubbles: true }));
          _unhighlight(el);
          if (step.wait) await _sleep(step.wait);
          break;
        }

        case 'find_click_row': {
          const tbody  = step.tbody ? _qs(step.tbody) : null;
          const needle = _t(step.value, vars).toLowerCase();
          const cell   = step.cell || '.name';

          await _waitForRows(step.tbody, 3000);

          let row = null;
          if (tbody) {
            for (const r of tbody.querySelectorAll('tr')) {
              const c = r.querySelector(cell);
              if (c && c.textContent.toLowerCase().includes(needle)) { row = r; break; }
            }
          }

          if (!row) {
            if (step.not_found) {
              _reopenWithReply(_t(step.not_found, vars));
              await _sleep(4000);
              _closeBubble();
            }
            // Clear the search field that was typed into
            const si = _qs('#inv-search');
            if (si) { si.value = ''; si.dispatchEvent(new Event('input', { bubbles: true })); }
            return false;   // abort walkthrough
          }

          await _walkTo(row);
          _highlight(row);
          await _sleep(600);
          row.click();
          _unhighlight(row);
          if (step.wait) await _sleep(step.wait);
          break;
        }

        case 'walk_to_field': {
          const fi = _resolveField(_t(step.field, vars));
          if (!fi) break;
          const fieldEl = document.getElementById(fi.id);
          if (!fieldEl) break;
          if (step.scroll) fieldEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          await _sleep(400);
          await _walkTo(fieldEl);
          break;
        }

        case 'highlight_field': {
          const fi = _resolveField(_t(step.field, vars));
          if (fi) _highlight(document.getElementById(fi.id));
          break;
        }

        case 'unhighlight_field': {
          const fi = _resolveField(_t(step.field, vars));
          if (fi) _unhighlight(document.getElementById(fi.id));
          break;
        }

        case 'wait': {
          await _sleep(step.ms || step.wait || 500);
          break;
        }
      }
    }
    return true;
  }

  /* ════════════════════════════════════════════════════════════════════════
     SHOW / HIDE CHARACTER
     ════════════════════════════════════════════════════════════════════════ */
  function _setGuideVisible(visible) {
    const wrap      = document.getElementById('guide-char-wrap');
    const toggleBtn = document.getElementById('guide-toggle-btn');
    if (!wrap) return;
    if (visible) {
      wrap.style.display = 'block';
      _dismissed = false;
      toggleBtn?.classList.add('guide-visible');
    } else {
      _closeBubble();
      wrap.style.display = 'none';
      _dismissed = true;
      toggleBtn?.classList.remove('guide-visible');
    }
  }

  /* ════════════════════════════════════════════════════════════════════════
     BUBBLE POSITIONING — keeps bubble inside the viewport at all times
     ════════════════════════════════════════════════════════════════════════ */
  function _positionBubble() {
    const wrap   = document.getElementById('guide-char-wrap');
    const bubble = document.getElementById('guide-bubble');
    if (!wrap || !bubble) return;

    const BUBBLE_W = 260;   // bubble width
    const BUBBLE_H = 150;   // approx rendered height
    const GAP      = 14;    // gap between character and bubble
    const MARGIN   = 8;     // min distance from viewport edge
    const CHAR_H   = bubble.closest('#guide-char-wrap')
                       ? wrap.getBoundingClientRect().height
                       : 100;

    const wRect = wrap.getBoundingClientRect();
    const winW  = window.innerWidth;
    const winH  = window.innerHeight;

    /* ── Vertical: flip below if not enough space above ── */
    const spaceAbove = wRect.top - GAP;
    if (spaceAbove < BUBBLE_H + MARGIN) {
      bubble.classList.add('guide-bubble-below');
    } else {
      bubble.classList.remove('guide-bubble-below');
    }

    /* ── Horizontal: shift left if bubble overflows right edge ── */
    // Default placement: right: -8px → bubble right edge is wRect.right + 8
    // bubble left edge is wRect.right + 8 - BUBBLE_W
    const bubbleLeftDefault = wRect.right + 8 - BUBBLE_W;
    if (bubbleLeftDefault < MARGIN) {
      bubble.classList.add('guide-bubble-left');
    } else {
      bubble.classList.remove('guide-bubble-left');
    }
  }

  /* ════════════════════════════════════════════════════════════════════════
     BUBBLE OPEN / CLOSE
     ════════════════════════════════════════════════════════════════════════ */
  function _openBubble(resetToInput) {
    const bubble = document.getElementById('guide-bubble');
    if (!bubble) return;
    _bubbleOpen = true;
    bubble.style.display = 'block';
    _positionBubble();
    bubble.classList.remove('guide-pop-out');
    void bubble.offsetWidth;
    bubble.classList.add('guide-pop-in');
    if (resetToInput !== false) {
      _showInputState();
      setTimeout(() => document.getElementById('guide-chat-input')?.focus(), 120);
    }
  }

  function _closeBubble() {
    const bubble = document.getElementById('guide-bubble');
    if (!bubble || !_bubbleOpen) return;
    _bubbleOpen = false;
    bubble.classList.remove('guide-pop-in');
    bubble.classList.add('guide-pop-out');
    setTimeout(() => { if (!_bubbleOpen) bubble.style.display = 'none'; }, 200);
  }

  function _toggleBubble() {
    if (_bubbleOpen) { _closeBubble(); } else { _openBubble(); }
  }

  function _reopenWithReply(text) {
    _showReplyState(text);
    const bubble = document.getElementById('guide-bubble');
    if (!bubble) return;
    _bubbleOpen = true;
    bubble.style.display = 'block';
    _positionBubble();
    bubble.classList.remove('guide-pop-out');
    void bubble.offsetWidth;
    bubble.classList.add('guide-pop-in');
  }

  /* ════════════════════════════════════════════════════════════════════════
     CHAT STATES
     ════════════════════════════════════════════════════════════════════════ */
  function _showInputState() {
    const iw = document.getElementById('guide-chat-input-wrap');
    const rw = document.getElementById('guide-chat-reply-wrap');
    const inp = document.getElementById('guide-chat-input');
    const btn = document.getElementById('guide-chat-send');
    if (iw) iw.style.display = 'flex';
    if (rw) rw.style.display = 'none';
    if (inp) inp.value = '';
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-paper-plane"></i> Ask'; }
    _busy = false;
  }

  function _showReplyState(text) {
    const iw = document.getElementById('guide-chat-input-wrap');
    const rw = document.getElementById('guide-chat-reply-wrap');
    const rt = document.getElementById('guide-chat-reply-text');
    if (iw) iw.style.display = 'none';
    if (rw) rw.style.display = 'flex';
    if (rt) rt.textContent = text;
  }

  /* ════════════════════════════════════════════════════════════════════════
     SEND / RECEIVE
     ════════════════════════════════════════════════════════════════════════ */
  async function _sendMessage() {
    if (_busy) return;
    const input   = document.getElementById('guide-chat-input');
    const message = input?.value.trim();
    if (!message) { input?.focus(); return; }

    const sendBtn = document.getElementById('guide-chat-send');
    _busy = true;
    if (sendBtn) { sendBtn.disabled = true; sendBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }

    _closeBubble();
    await _sleep(200);

    // Call Gemini — reply + optional walkthrough ID are both in the response
    let reply      = 'Sorry, I could not get a response right now. Please try again.';
    let match      = null;

    try {
      const res = await API.guideChat(message);
      if (res.status === 200 && res.body?.reply) {
        reply = res.body.reply;

        // Gemini may identify which walkthrough to run
        const wtId = res.body.walkthrough;
        if (wtId) {
          const wt = (window.GUIDE_CONFIG?.walkthroughs || []).find(w => w.id === wtId);
          if (wt) {
            const vars = {};
            if (res.body.productName) vars.productName = res.body.productName;
            if (res.body.fieldName) {
              vars.fieldName  = res.body.fieldName;
              const fi        = _resolveField(res.body.fieldName);
              vars.fieldLabel = fi ? fi.label : res.body.fieldName;
            }
            match = { wt, vars };
          }
        }
      }
    } catch (e) { /* use fallback */ }

    // If Gemini didn't identify a walkthrough, fall back to local pattern matching
    if (!match) match = _matchWalkthrough(message);

    _reopenWithReply(reply);
    _busy = false;

    // Run the walkthrough steps after the user has read the reply
    if (match) {
      await _sleep(2000);
      _closeBubble();
      await _sleep(200);
      _closeAllModals();   // clear any open dialogs before the character starts moving
      await _sleep(150);
      _busy = true;
      await _runSteps(match.wt.steps, match.vars);
      await _returnHome();
      _busy = false;
    }
  }

  /* ════════════════════════════════════════════════════════════════════════
     DRAGGABLE
     ════════════════════════════════════════════════════════════════════════ */
  function _makeDraggable(wrap, handle) {
    let active = false, startX, startY, startRight, startBottom;

    handle.addEventListener('mousedown', e => {
      if (e.target.closest('#guide-char-dismiss')) return;
      active = true;
      startX = e.clientX; startY = e.clientY;
      const r = wrap.getBoundingClientRect();
      startRight  = window.innerWidth  - r.right;
      startBottom = window.innerHeight - r.bottom;
      wrap.style.transition = 'none';
      document.body.style.cursor = 'grabbing';
      e.preventDefault();
    });

    document.addEventListener('mousemove', e => {
      if (!active) return;
      let r = startRight  - (e.clientX - startX);
      let b = startBottom - (e.clientY - startY);
      r = Math.max(8, Math.min(window.innerWidth  - 56, r));
      b = Math.max(8, Math.min(window.innerHeight - 56, b));
      wrap.style.right  = r + 'px';
      wrap.style.bottom = b + 'px';
    });

    document.addEventListener('mouseup', () => {
      if (!active) return;
      active = false;
      document.body.style.cursor = '';
      wrap.style.transition = '';
    });
  }

  /* ════════════════════════════════════════════════════════════════════════
     INIT
     ════════════════════════════════════════════════════════════════════════ */
  function initGuide() {
    if (_initialized) return;
    _initialized = true;

    const wrap       = document.getElementById('guide-char-wrap');
    const imgWrap    = document.getElementById('guide-char-img-wrap');
    const dismissBtn = document.getElementById('guide-char-dismiss');
    const toggleBtn  = document.getElementById('guide-toggle-btn');
    const sendBtn    = document.getElementById('guide-chat-send');
    const againBtn   = document.getElementById('guide-chat-again');
    const input      = document.getElementById('guide-chat-input');

    if (!wrap || !imgWrap) return;

    _setGuideVisible(true);

    imgWrap.addEventListener('click', e => {
      if (e.target.closest('#guide-char-dismiss')) return;
      _toggleBubble();
    });

    sendBtn?.addEventListener('click', e => { e.stopPropagation(); _sendMessage(); });

    input?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); _sendMessage(); }
    });

    againBtn?.addEventListener('click', e => {
      e.stopPropagation();
      _closeBubble();
      setTimeout(() => { _showInputState(); _openBubble(); }, 220);
    });

    dismissBtn?.addEventListener('click', e => { e.stopPropagation(); _setGuideVisible(false); });

    toggleBtn?.addEventListener('click', () => {
      if (_dismissed) { _setGuideVisible(true); } else { _setGuideVisible(false); }
    });

    _makeDraggable(wrap, imgWrap);
  }

  /* ════════════════════════════════════════════════════════════════════════
     WAIT FOR LOGIN
     ════════════════════════════════════════════════════════════════════════ */
  function watchForLogin() {
    const appShell = document.getElementById('app-shell');
    if (!appShell) { setTimeout(watchForLogin, 150); return; }
    if (appShell.style.display === 'flex') { initGuide(); return; }
    const obs = new MutationObserver(() => {
      if (appShell.style.display === 'flex') { obs.disconnect(); initGuide(); }
    });
    obs.observe(appShell, { attributes: true, attributeFilter: ['style'] });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watchForLogin);
  } else {
    watchForLogin();
  }

}());
