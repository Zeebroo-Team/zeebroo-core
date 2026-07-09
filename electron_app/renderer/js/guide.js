/**
 * Zeebroo POS — Guide Chat Assistant
 * Floating draggable character; click to open/close an AI chat bubble.
 */
(function () {
  'use strict';

  /* ════════════════════════════════════════════════════════════════════════
     STATE
     ════════════════════════════════════════════════════════════════════════ */
  let _dismissed   = false;
  let _initialized = false;
  let _bubbleOpen  = false;
  let _busy        = false;   // true while waiting for API reply

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
  function _openBubble() {
    const bubble = document.getElementById('guide-bubble');
    if (!bubble) return;
    _bubbleOpen = true;
    bubble.style.display = 'block';
    bubble.classList.remove('guide-pop-out');
    void bubble.offsetWidth;
    bubble.classList.add('guide-pop-in');
    // Reset to input state
    _showInputState();
    // Focus textarea
    setTimeout(() => document.getElementById('guide-chat-input')?.focus(), 120);
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
    if (_bubbleOpen) {
      _closeBubble();
    } else {
      _openBubble();
    }
  }

  /* ════════════════════════════════════════════════════════════════════════
     CHAT STATES
     ════════════════════════════════════════════════════════════════════════ */
  function _showInputState() {
    const inputWrap = document.getElementById('guide-chat-input-wrap');
    const replyWrap = document.getElementById('guide-chat-reply-wrap');
    const input     = document.getElementById('guide-chat-input');
    if (inputWrap) inputWrap.style.display = 'flex';
    if (replyWrap) replyWrap.style.display = 'none';
    if (input)     input.value = '';
    _busy = false;
    const sendBtn = document.getElementById('guide-chat-send');
    if (sendBtn) { sendBtn.disabled = false; sendBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Ask'; }
  }

  function _showReplyState(text) {
    const inputWrap = document.getElementById('guide-chat-input-wrap');
    const replyWrap = document.getElementById('guide-chat-reply-wrap');
    const replyText = document.getElementById('guide-chat-reply-text');
    if (inputWrap) inputWrap.style.display = 'none';
    if (replyWrap) replyWrap.style.display = 'flex';
    if (replyText) replyText.textContent = text;
    _busy = false;
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

    _busy = true;
    if (sendBtn) { sendBtn.disabled = true; sendBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }

    // Animate bubble out
    _closeBubble();

    // Wait for close animation then fetch
    setTimeout(async () => {
      let reply = 'Sorry, I could not get a response right now. Please try again.';
      try {
        const res = await API.guideChat(message);
        if (res.status === 200 && res.body?.reply) {
          reply = res.body.reply;
        }
      } catch (e) {
        // keep default error message
      }

      // Reopen bubble with reply
      const bubble    = document.getElementById('guide-bubble');
      const inputWrap = document.getElementById('guide-chat-input-wrap');
      const replyWrap = document.getElementById('guide-chat-reply-wrap');
      const replyText = document.getElementById('guide-chat-reply-text');
      if (!bubble) return;

      if (inputWrap) inputWrap.style.display = 'none';
      if (replyWrap) replyWrap.style.display = 'flex';
      if (replyText) replyText.textContent = reply;

      _bubbleOpen = true;
      bubble.style.display = 'block';
      bubble.classList.remove('guide-pop-out');
      void bubble.offsetWidth;
      bubble.classList.add('guide-pop-in');
      _busy = false;
    }, 240);
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

    /* "Chat" button in reply state → back to input */
    againBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _closeBubble();
      setTimeout(() => {
        _showInputState();
        _openBubble();
      }, 220);
    });

    /* Dismiss × on character */
    dismissBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _setGuideVisible(false);
    });

    /* Ribbon Guide toggle button */
    toggleBtn?.addEventListener('click', () => {
      if (_dismissed) {
        _setGuideVisible(true);
      } else {
        _setGuideVisible(false);
      }
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
