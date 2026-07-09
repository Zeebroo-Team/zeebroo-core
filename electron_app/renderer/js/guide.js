/**
 * Zeebroo POS — Guide Chat Assistant
 * Floating draggable character with AI chat + intent-driven guided walkthroughs.
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
     INTENT MAP  — matched before API call; triggers guided walkthrough
     ════════════════════════════════════════════════════════════════════════ */
  const INTENT_MAP = [
    {
      test:   msg => /add\s*(a\s*)?new\s*product|new\s*product|create\s*product|add\s*product/i.test(msg),
      reply:  "Sure! Follow me — I'll walk you through adding a new product right now.",
      action: _walkthroughAddProduct,
    },
  ];

  /* ════════════════════════════════════════════════════════════════════════
     HELPERS
     ════════════════════════════════════════════════════════════════════════ */
  const _sleep = ms => new Promise(r => setTimeout(r, ms));

  function _highlight(el) {
    el?.classList.add('guide-target-pulse');
  }
  function _unhighlight(el) {
    el?.classList.remove('guide-target-pulse');
  }

  function _waitVisible(el, maxMs = 2500) {
    return new Promise(resolve => {
      if (!el) { resolve(false); return; }
      const start = Date.now();
      const check = () => {
        const s = window.getComputedStyle(el);
        if (s.display !== 'none' && s.visibility !== 'hidden' && s.opacity !== '0') {
          resolve(true); return;
        }
        if (Date.now() - start > maxMs) { resolve(false); return; }
        setTimeout(check, 80);
      };
      check();
    });
  }

  /* ════════════════════════════════════════════════════════════════════════
     CHARACTER MOVEMENT
     ════════════════════════════════════════════════════════════════════════ */
  function _walkTo(el) {
    return new Promise(resolve => {
      const wrap = document.getElementById('guide-char-wrap');
      if (!wrap || !el) { setTimeout(resolve, 100); return; }

      const r     = el.getBoundingClientRect();
      const charW = 96;

      // Prefer below the element; fall back to above if near the bottom
      let top  = r.bottom + 14;
      let left = r.left + r.width / 2 - charW / 2;

      if (top + 110 > window.innerHeight) top = Math.max(4, r.top - 114);
      left = Math.max(4, Math.min(window.innerWidth - charW - 4, left));
      top  = Math.max(4, top);

      // First call: convert from bottom/right anchoring to top/left
      if (wrap.style.bottom !== 'auto') {
        const cr      = wrap.getBoundingClientRect();
        wrap.style.top    = cr.top  + 'px';
        wrap.style.left   = cr.left + 'px';
        wrap.style.bottom = 'auto';
        wrap.style.right  = 'auto';
      }

      void wrap.offsetWidth;
      wrap.style.transition = 'top 0.6s cubic-bezier(0.4,0,0.2,1), left 0.6s cubic-bezier(0.4,0,0.2,1)';
      wrap.style.top  = top  + 'px';
      wrap.style.left = left + 'px';

      // Bounce during travel
      const img = document.getElementById('guide-char-img');
      img?.classList.add('guide-walk-bounce');
      setTimeout(() => { img?.classList.remove('guide-walk-bounce'); resolve(); }, 680);
    });
  }

  function _returnHome() {
    return new Promise(resolve => {
      const wrap = document.getElementById('guide-char-wrap');
      if (!wrap) { resolve(); return; }

      // Slide to the bottom-right corner, then re-anchor with bottom/right
      const targetTop  = window.innerHeight - 24 - 120;
      const targetLeft = window.innerWidth  - 24 - 96;

      wrap.style.transition = 'top 0.65s cubic-bezier(0.4,0,0.2,1), left 0.65s cubic-bezier(0.4,0,0.2,1)';
      wrap.style.top  = targetTop  + 'px';
      wrap.style.left = targetLeft + 'px';

      setTimeout(() => {
        wrap.style.transition = '';
        wrap.style.top    = 'auto';
        wrap.style.left   = 'auto';
        wrap.style.bottom = '24px';
        wrap.style.right  = '24px';
        resolve();
      }, 700);
    });
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
     BUBBLE OPEN / CLOSE
     ════════════════════════════════════════════════════════════════════════ */
  function _openBubble(resetToInput) {
    const bubble = document.getElementById('guide-bubble');
    if (!bubble) return;
    _bubbleOpen = true;
    bubble.style.display = 'block';
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
    setTimeout(() => {
      if (!_bubbleOpen) bubble.style.display = 'none';
    }, 200);
  }

  function _toggleBubble() {
    if (_bubbleOpen) { _closeBubble(); } else { _openBubble(); }
  }

  /* ════════════════════════════════════════════════════════════════════════
     CHAT STATES
     ════════════════════════════════════════════════════════════════════════ */
  function _showInputState() {
    const inputWrap = document.getElementById('guide-chat-input-wrap');
    const replyWrap = document.getElementById('guide-chat-reply-wrap');
    const input     = document.getElementById('guide-chat-input');
    const sendBtn   = document.getElementById('guide-chat-send');
    if (inputWrap) inputWrap.style.display = 'flex';
    if (replyWrap) replyWrap.style.display = 'none';
    if (input)     input.value = '';
    if (sendBtn) { sendBtn.disabled = false; sendBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Ask'; }
    _busy = false;
  }

  function _showReplyState(text) {
    const inputWrap = document.getElementById('guide-chat-input-wrap');
    const replyWrap = document.getElementById('guide-chat-reply-wrap');
    const replyText = document.getElementById('guide-chat-reply-text');
    if (inputWrap) inputWrap.style.display = 'none';
    if (replyWrap) replyWrap.style.display = 'flex';
    if (replyText) replyText.textContent = text;
  }

  /* ════════════════════════════════════════════════════════════════════════
     SEND / RECEIVE
     ════════════════════════════════════════════════════════════════════════ */
  async function _sendMessage() {
    if (_busy) return;
    const input   = document.getElementById('guide-chat-input');
    const sendBtn = document.getElementById('guide-chat-send');
    const message = input?.value.trim();
    if (!message) { input?.focus(); return; }

    // Intent detection — no API call needed
    const intent = INTENT_MAP.find(i => i.test(message));
    if (intent) {
      _busy = true;
      _closeBubble();
      await _sleep(240);
      _showReplyState(intent.reply);
      _openBubble(false);   // open in reply state
      await _sleep(1800);
      _closeBubble();
      await _sleep(300);
      intent.action();
      return;
    }

    // Normal AI chat flow
    _busy = true;
    if (sendBtn) { sendBtn.disabled = true; sendBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }

    _closeBubble();

    setTimeout(async () => {
      let reply = 'Sorry, I could not get a response right now. Please try again.';
      try {
        const res = await API.guideChat(message);
        if (res.status === 200 && res.body?.reply) reply = res.body.reply;
      } catch (e) { /* use default */ }

      _showReplyState(reply);

      const bubble = document.getElementById('guide-bubble');
      if (bubble) {
        _bubbleOpen = true;
        bubble.style.display = 'block';
        bubble.classList.remove('guide-pop-out');
        void bubble.offsetWidth;
        bubble.classList.add('guide-pop-in');
      }
      _busy = false;
    }, 240);
  }

  /* ════════════════════════════════════════════════════════════════════════
     GUIDED WALKTHROUGH — Add New Product
     ════════════════════════════════════════════════════════════════════════ */
  async function _walkthroughAddProduct() {
    _busy = true;

    // ── Step 1: Inventory tab ──────────────────────────────────────────────
    const invTab = document.querySelector('[data-tab="inventory"]');
    if (!invTab) { _busy = false; await _returnHome(); return; }

    await _walkTo(invTab);
    _highlight(invTab);
    await _sleep(550);
    invTab.click();
    _unhighlight(invTab);
    await _sleep(750);

    // ── Step 2: Products sub-tab ───────────────────────────────────────────
    const prodSubTab = document.querySelector('.inv-subnav-btn[data-inv-view="products"]');
    if (prodSubTab) {
      await _walkTo(prodSubTab);
      _highlight(prodSubTab);
      await _sleep(550);
      prodSubTab.click();
      _unhighlight(prodSubTab);
      await _sleep(900);
    }

    // ── Step 3: New Product button ─────────────────────────────────────────
    const newProdBtn = document.getElementById('inv-new-product-btn');
    if (newProdBtn) {
      await _walkTo(newProdBtn);
      _highlight(newProdBtn);
      await _sleep(700);
      newProdBtn.click();
      _unhighlight(newProdBtn);
      await _sleep(450);
    }

    // ── Step 4: Wait for product modal, then point to Save Product ─────────
    const modal = document.getElementById('product-modal');
    if (modal) {
      const visible = await _waitVisible(modal);
      if (visible) {
        await _sleep(250);
        const saveBtn = document.getElementById('prod-modal-save');
        if (saveBtn) {
          await _walkTo(saveBtn);
          _highlight(saveBtn);

          // Show a closing hint in the bubble
          _showReplyState("Fill in the product details, then click Save Product when you're done!");
          _openBubble(false);

          await _sleep(4000);
          _unhighlight(saveBtn);
          _closeBubble();
          await _sleep(300);
        }
      }
    }

    await _returnHome();
    _busy = false;
  }

  /* ════════════════════════════════════════════════════════════════════════
     DRAGGABLE
     ════════════════════════════════════════════════════════════════════════ */
  function _makeDraggable(wrap, handle) {
    let active = false, startX, startY, startRight, startBottom;

    handle.addEventListener('mousedown', (e) => {
      if (e.target.closest('#guide-char-dismiss')) return;
      active = true;
      startX = e.clientX;
      startY = e.clientY;
      const r = wrap.getBoundingClientRect();
      startRight  = window.innerWidth  - r.right;
      startBottom = window.innerHeight - r.bottom;
      wrap.style.transition = 'none';
      document.body.style.cursor = 'grabbing';
      e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
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

    /* Character image click → toggle bubble */
    imgWrap.addEventListener('click', (e) => {
      if (e.target.closest('#guide-char-dismiss')) return;
      _toggleBubble();
    });

    /* Send button */
    sendBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _sendMessage();
    });

    /* Enter in textarea (Shift+Enter = newline) */
    input?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        _sendMessage();
      }
    });

    /* "Chat" button in reply → back to input */
    againBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _closeBubble();
      setTimeout(() => { _showInputState(); _openBubble(); }, 220);
    });

    /* Dismiss × on character */
    dismissBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _setGuideVisible(false);
    });

    /* Ribbon Guide toggle button */
    toggleBtn?.addEventListener('click', () => {
      if (_dismissed) { _setGuideVisible(true); } else { _setGuideVisible(false); }
    });

    /* Draggable */
    _makeDraggable(wrap, imgWrap);
  }

  /* ════════════════════════════════════════════════════════════════════════
     WAIT FOR LOGIN
     Watch #app-shell becoming display:flex (set by showApp() after auth).
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
