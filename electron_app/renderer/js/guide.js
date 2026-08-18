/**
 * Zeebroo POS — Guide Assistant Engine
 *
 * Reads walkthrough definitions from window.GUIDE_CONFIG (guide-config.js).
 * To add a new guided walkthrough, edit guide-config.js only — no changes here.
 *
 * Right-click context menu on the guide character exposes:
 *   • Voice Listening Worker — Web Speech API continuous voice input
 *   • Open Chat             — opens the chat bubble
 *   • Reset Position        — returns character to home corner
 *   • Dismiss Guide         — hides the character
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

  // Voice Listening Worker state (MediaRecorder-based — no SpeechRecognition)
  let _voiceStream      = null;   // MediaStream from getUserMedia
  let _voiceRecorder    = null;   // MediaRecorder instance
  let _voiceAudioCtx    = null;   // AudioContext for silence detection + waveform
  let _voiceAnalyser    = null;   // AnalyserNode
  let _voiceChunks      = [];     // collected audio chunks
  let _voiceMimeType    = 'audio/webm';
  let _voiceRecordStart = 0;
  let _voiceSilenceStart = null;
  let _voiceHasSpeech   = false;  // user has started speaking in this cycle
  let _voiceRafId       = null;   // requestAnimationFrame handle
  let _voiceActive      = false;
  let _voicePaused      = false;

  // Silence-detection thresholds
  const _VOICE_SILENCE_THRESH  = 10;    // avg amplitude (0–255) below = silence
  const _VOICE_SILENCE_DELAY   = 1500;  // ms of silence before auto-send
  const _VOICE_MIN_BLOB        = 2500;  // bytes — blobs smaller than this are ignored
  const _VOICE_MAX_MS          = 14000; // auto-send after this long regardless

  // Text-to-Speech state
  let _ttsUtterance    = null;

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
    ['#checkout-modal', '#bc-preview-modal', '#search-suggest'].forEach(sel => {
      const el = _qs(sel);
      if (el && getComputedStyle(el).display !== 'none') el.style.display = 'none';
    });
    // Layer picker uses visibility/opacity (not display:none) — close it properly
    const lp = _qs('#pos-layer-picker');
    if (lp) { lp.classList.remove('is-open'); lp.setAttribute('aria-hidden', 'true'); lp.style.display = ''; }
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

    const BUBBLE_W = 280;   // bubble width
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
    // Show voice listening bar if worker is active
    const bar = document.getElementById('guide-voice-listening-bar');
    if (bar) bar.classList.toggle('active', _voiceActive);
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
    // Hide listening bar when bubble closes
    const bar = document.getElementById('guide-voice-listening-bar');
    if (bar) bar.classList.remove('active');
  }

  function _toggleBubble() {
    if (_bubbleOpen) { _closeBubble(); } else { _openBubble(); }
  }

  function _reopenWithReply(text, isHtml) {
    _showReplyState(text, isHtml);
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

  function _showReplyState(text, isHtml) {
    const iw = document.getElementById('guide-chat-input-wrap');
    const rw = document.getElementById('guide-chat-reply-wrap');
    const rt = document.getElementById('guide-chat-reply-text');
    if (iw) iw.style.display = 'none';
    if (rw) rw.style.display = 'flex';
    if (rt) {
      if (isHtml) {
        rt.innerHTML = text;
        rt.classList.add('guide-reply-rich');
      } else {
        rt.textContent = text;
        rt.classList.remove('guide-reply-rich');
      }
    }
  }

  /* ════════════════════════════════════════════════════════════════════════
     SEND / RECEIVE
     fromVoice=true means the message came from the Voice Listening Worker.
     After Gemini replies the character will:
       1. show the reply in the bubble
       2. speak it aloud via TTS
       3. resume the voice recognition loop
     ════════════════════════════════════════════════════════════════════════ */
  async function _sendMessage(fromVoice) {
    if (_busy) return;
    const input   = document.getElementById('guide-chat-input');
    const message = input?.value.trim();
    if (!message) { input?.focus(); return; }

    const sendBtn = document.getElementById('guide-chat-send');
    _busy = true;
    if (sendBtn) { sendBtn.disabled = true; sendBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }

    // Hide the listening bar while we wait for Gemini
    const bar = document.getElementById('guide-voice-listening-bar');
    if (bar) bar.classList.remove('active');

    _closeBubble();
    await _sleep(200);

    // ── Call Gemini via server (/guide/chat → Gemini API) ──
    let reply        = null;
    let match        = null;
    let geminiWorked = false;
    let isHtml       = false;

    try {
      const res = await API.guideChat(message);
      if (res.status === 200 && res.body?.reply) {
        reply        = String(res.body.reply).trim();
        geminiWorked = reply.length > 0;
        isHtml       = !!res.body.isHtml;

        // Data-query HTML reply — show immediately, then handle voice resume
        if (isHtml) {
          _reopenWithReply(reply, true);
          _busy = false;
          if (fromVoice && _voiceActive) {
            await _ttsSpeak(_stripHtml(reply));
            _voiceResumeAfterReply();
          }
          return;
        }

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

    // Determine the reply text
    if (!geminiWorked) {
      reply = match
        ? _t(match.wt.reply || 'Sure! Follow me — I\'ll show you!', match.vars)
        : 'I\'m having trouble connecting right now. Please try again in a moment.';
    }

    _reopenWithReply(reply);
    _busy = false;

    // ── Voice mode: speak the Gemini reply, then resume listening ──
    if (fromVoice && _voiceActive) {
      if (!match) {
        // Simple reply — speak it then immediately resume
        await _ttsSpeak(reply);
        _voiceResumeAfterReply();
      }
      // If there's a walkthrough, TTS + resume happens after it finishes (below)
    }

    // ── Run walkthrough steps ──────────────────────────────────────
    if (match) {
      await _sleep(fromVoice ? 1000 : 2000); // shorter delay if voice already played reply
      _closeBubble();
      await _sleep(200);
      _closeAllModals();
      await _sleep(150);
      _busy = true;
      await _runSteps(match.wt.steps, match.vars);
      await _returnHome();
      _closeAllModals();
      _busy = false;

      // After walkthrough completes, resume voice if active
      if (fromVoice && _voiceActive) {
        _voiceResumeAfterReply();
      }
    }
  }

  /* ════════════════════════════════════════════════════════════════════════
     VOICE LISTENING WORKER  (MediaRecorder → Gemini multimodal audio)
     ─────────────────────────────────────────────────────────────────────
     Flow (avoids the "network" error that SpeechRecognition always throws
     inside Electron because Chromium ships without Google's Speech API key):

       1.  User right-clicks guide → "Start Voice Worker"
       2.  getUserMedia obtains mic stream; AudioContext analyses amplitude
       3.  MediaRecorder records chunks until silence (≥1.5 s below threshold)
           or the 14 s safety ceiling
       4.  Blob → base64 → POST /guide/voice (Gemini multimodal)
       5.  Server returns { transcript, reply, walkthrough?, productName?, … }
       6.  Transcript shown in chat input; reply shown in bubble + spoken via TTS
       7.  After TTS finishes → _beginRecording() again → back to step 3
     ════════════════════════════════════════════════════════════════════════ */

  /* ── Convert a Blob to a base-64 string ── */
  function _blobToBase64(blob) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onloadend = () => {
        // result is  "data:<mime>;base64,<data>"  — strip the prefix
        const b64 = reader.result.split(',')[1];
        resolve(b64);
      };
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  }

  /* ── Unified status / UI setter ── */
  // status: 'listening' | 'processing' | 'speaking' | 'off'
  function _voiceSetStatus(status) {
    const badge      = document.getElementById('guide-voice-badge');
    const bar        = document.getElementById('guide-voice-listening-bar');
    const statusTxt  = document.getElementById('guide-voice-status-text');
    const voiceItem  = document.getElementById('guide-ctx-voice');
    const voiceLabel = document.getElementById('guide-ctx-voice-label');

    // Badge classes
    if (badge) {
      badge.classList.toggle('voice-active',    status !== 'off');
      badge.classList.toggle('voice-speaking',  status === 'speaking');
      badge.classList.toggle('voice-processing',status === 'processing');
    }

    // Listening bar (only visible when bubble is open)
    if (bar) {
      bar.classList.toggle('active',   status === 'listening' && _bubbleOpen);
      bar.classList.toggle('speaking', status === 'speaking');
      bar.classList.toggle('processing', status === 'processing');
    }

    // Status label
    const labels = { listening: 'Listening…', processing: 'Processing…', speaking: 'Speaking…', off: '' };
    if (statusTxt) statusTxt.textContent = labels[status] || '';

    // Context-menu item
    const isOn = status !== 'off';
    if (voiceItem) {
      voiceItem.classList.toggle('voice-on', isOn);
      const ico = voiceItem.querySelector('.guide-ctx-icon i');
      if (ico) ico.className = isOn ? 'fa fa-microphone-slash' : 'fa fa-microphone';
    }
    if (voiceLabel) voiceLabel.textContent = isOn ? 'Stop Voice Worker' : 'Start Voice Worker';
  }

  /* ── Text-to-Speech: speaks Gemini's reply, returns a Promise ── */
  // lang: BCP-47 code (e.g. 'si-LK', 'en-US') — if omitted, uses system language
  function _ttsSpeak(text, lang) {
    return new Promise(resolve => {
      if (!window.speechSynthesis || !text) { resolve(); return; }
      _ttsCancelSpeak();
      _ttsUtterance = new SpeechSynthesisUtterance(text);
      _ttsUtterance.rate   = 1.0;
      _ttsUtterance.pitch  = 1.0;
      _ttsUtterance.volume = 1.0;
      _ttsUtterance.lang   = lang || navigator.language || 'en-US';
      _ttsUtterance.onend  = () => resolve();
      _ttsUtterance.onerror = () => resolve();   // missing voice falls back silently
      _voiceSetStatus('speaking');
      window.speechSynthesis.speak(_ttsUtterance);
    });
  }

  /* ── Cancel TTS ── */
  function _ttsCancelSpeak() {
    if (window.speechSynthesis) window.speechSynthesis.cancel();
    _ttsUtterance = null;
  }

  /* ── Strip HTML for TTS ── */
  function _stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
  }

  /* ── Show inline error then stop the worker ── */
  function _voiceShowError(msg) {
    _stopVoiceWorker();
    if (!_bubbleOpen) _openBubble(false);
    _reopenWithReply(msg);
  }

  /* ─────────────────────────────────────────────────────────────────────────
     SILENCE DETECTION — rAF loop that monitors AnalyserNode byte-time data.
     Waveform bars in the listening bar get their heights set from live audio.
     ───────────────────────────────────────────────────────────────────────── */
  function _startSilenceDetection() {
    if (!_voiceAnalyser) return;
    const buf       = new Uint8Array(_voiceAnalyser.fftSize);
    const bars      = document.querySelectorAll('#guide-voice-listening-bar .guide-voice-waves span');
    const barCount  = bars.length || 4;

    function tick() {
      if (!_voiceActive || !_voiceRecorder || _voiceRecorder.state === 'inactive') return;

      _voiceAnalyser.getByteTimeDomainData(buf);

      // RMS amplitude (0–128 range after subtracting 128 midpoint)
      let sum = 0;
      for (let i = 0; i < buf.length; i++) { const v = buf[i] - 128; sum += v * v; }
      const rms = Math.sqrt(sum / buf.length); // 0–128

      // Animate waveform bars with live audio level
      const norm = Math.min(rms / 40, 1);          // normalise to 0–1
      bars.forEach((bar, i) => {
        const phase = (i / barCount) * Math.PI;
        const h = 20 + Math.round(norm * 60 * Math.abs(Math.sin(Date.now() / 200 + phase)));
        bar.style.height = h + '%';
      });

      const now = Date.now();

      if (rms > _VOICE_SILENCE_THRESH) {
        // Sound detected
        _voiceHasSpeech    = true;
        _voiceSilenceStart = null;
      } else if (_voiceHasSpeech) {
        // Below threshold after speech
        if (!_voiceSilenceStart) _voiceSilenceStart = now;
        if (now - _voiceSilenceStart >= _VOICE_SILENCE_DELAY) {
          // Enough silence — finalize this utterance
          _stopAndProcess();
          return;
        }
      }

      // Safety ceiling
      if (now - _voiceRecordStart >= _VOICE_MAX_MS) {
        _stopAndProcess();
        return;
      }

      _voiceRafId = requestAnimationFrame(tick);
    }

    _voiceRafId = requestAnimationFrame(tick);
  }

  /* ── Trigger stop; onstop handler does the rest ── */
  function _stopAndProcess() {
    if (!_voiceRecorder || _voiceRecorder.state === 'inactive') return;
    cancelAnimationFrame(_voiceRafId);
    _voiceRafId = null;
    try { _voiceRecorder.stop(); } catch (_) {}   // onstop fires asynchronously
  }

  /* ── Called when MediaRecorder fires onstop ── */
  async function _onRecorderStop() {
    if (!_voiceActive) return;          // worker was stopped mid-recording
    if (!_voiceHasSpeech || _voiceChunks.length === 0) {
      // Nothing meaningful recorded — restart immediately
      _beginRecording();
      return;
    }

    // Minimum blob guard (avoid sending a tiny click-noise fragment)
    const blob = new Blob(_voiceChunks, { type: _voiceMimeType });
    if (blob.size < 2000) {             // < 2 KB is almost certainly silence
      _beginRecording();
      return;
    }

    _voiceSetStatus('processing');
    const inp = document.getElementById('guide-chat-input');
    if (inp) { inp.value = ''; inp.placeholder = 'Processing…'; }

    let b64;
    try {
      b64 = await _blobToBase64(blob);
    } catch (err) {
      console.warn('[VoiceWorker] base64 encode error:', err);
      _voiceShowError('Could not encode audio. Please try again.');
      return;
    }

    let res;
    try {
      res = await API.guideVoice(b64, _voiceMimeType);
    } catch (err) {
      console.warn('[VoiceWorker] API error:', err);
      _voiceShowError('Could not reach the server. Check your connection and try again.');
      return;
    }

    if (!_voiceActive) return;         // stopped while API was in-flight

    // API.guideVoice returns { status, body } just like every other API call
    if (!res || res.status >= 500) {
      _voiceShowError('The server could not process the audio. Please try again.');
      return;
    }

    const body       = res.body || {};
    const transcript = body.transcript || '';
    const reply      = body.reply      || '';
    const replyLang  = body.lang       || '';   // BCP-47 from Gemini, e.g. 'si-LK'

    // Show transcript in chat input
    if (inp && transcript) inp.value = transcript;

    if (!reply) {
      // Nothing to say — loop back
      _voiceSetStatus('listening');
      _beginRecording();
      return;
    }

    // Show reply in bubble
    if (!_bubbleOpen) _openBubble(false);
    _showInputState();
    _reopenWithReply(reply);

    // ── Resolve walkthrough — body.walkthrough is a string ID, not an object ──
    let match = null;
    const wtId = body.walkthrough;
    if (wtId) {
      const wt = (window.GUIDE_CONFIG?.walkthroughs || []).find(w => w.id === wtId);
      if (wt) {
        const vars = {};
        if (body.productName) vars.productName = body.productName;
        if (body.fieldName) {
          vars.fieldName  = body.fieldName;
          const fi        = _resolveField(body.fieldName);
          vars.fieldLabel = fi ? fi.label : body.fieldName;
        }
        match = { wt, vars };
      }
    }
    // Fall back to local pattern matching on the transcript if Gemini didn't resolve one
    if (!match && transcript) match = _matchWalkthrough(transcript);

    // TTS speaks the reply in the detected language first, then animate
    await _ttsSpeak(_stripHtml(reply), replyLang);

    if (!_voiceActive) return;         // stopped while TTS was playing

    // ── Run the walkthrough steps (guide character moves + highlights UI) ──
    if (match) {
      await _sleep(600);
      _closeBubble();
      await _sleep(200);
      _closeAllModals();
      await _sleep(150);
      _busy = true;
      await _runSteps(match.wt.steps, match.vars);
      await _returnHome();
      _closeAllModals();
      _busy = false;
    }

    if (!_voiceActive) return;

    // Resume next recording cycle
    _voiceResumeAfterReply();
  }

  /* ── Start one recording cycle ── */
  function _beginRecording() {
    if (!_voiceActive || !_voiceStream) return;

    // Reset per-cycle state
    _voiceChunks      = [];
    _voiceHasSpeech   = false;
    _voiceSilenceStart = null;
    _voiceRecordStart  = Date.now();

    // Pick best supported MIME
    const preferred = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
    _voiceMimeType = preferred.find(m => MediaRecorder.isTypeSupported(m)) || '';

    const opts = _voiceMimeType ? { mimeType: _voiceMimeType } : {};
    try {
      _voiceRecorder = new MediaRecorder(_voiceStream, opts);
    } catch (err) {
      console.warn('[VoiceWorker] MediaRecorder init failed:', err);
      _voiceShowError('Could not start recording. Please try again.');
      return;
    }

    _voiceRecorder.ondataavailable = e => {
      if (e.data && e.data.size > 0) _voiceChunks.push(e.data);
    };
    _voiceRecorder.onstop = _onRecorderStop;

    _voiceRecorder.start(200);         // collect chunks every 200 ms
    _voiceSetStatus('listening');

    // Reset input
    _showInputState();
    const inp = document.getElementById('guide-chat-input');
    if (inp) { inp.value = ''; inp.placeholder = 'Listening…'; }
    if (!_bubbleOpen) _openBubble(false);

    // Start silence/waveform monitor
    _startSilenceDetection();
  }

  /* ── Start the Voice Listening Worker ── */
  async function _startVoiceWorker() {
    if (_voiceActive) return;

    // ── Obtain mic stream (triggers OS permission dialog on first use) ──
    let stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    } catch (err) {
      const name = err?.name || '';
      if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        _voiceShowError(
          'Microphone access was denied. Please allow microphone access in System Settings → Privacy → Microphone, then try again.'
        );
      } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        _voiceShowError('No microphone found. Please connect a microphone and try again.');
      } else {
        _voiceShowError('Could not access the microphone (' + (err?.message || name || 'unknown error') + ').');
      }
      return;
    }

    _voiceStream = stream;
    _voiceActive = true;
    _voicePaused = false;

    // ── Build AudioContext + AnalyserNode for silence detection ──
    try {
      _voiceAudioCtx  = new (window.AudioContext || window.webkitAudioContext)();
      _voiceAnalyser  = _voiceAudioCtx.createAnalyser();
      _voiceAnalyser.fftSize = 256;
      const src = _voiceAudioCtx.createMediaStreamSource(stream);
      src.connect(_voiceAnalyser);
      // Don't connect to destination — we don't want mic playback
    } catch (err) {
      console.warn('[VoiceWorker] AudioContext setup failed:', err);
      // Non-fatal — we can record without amplitude analysis
    }

    _beginRecording();
  }

  /* ── Stop the Voice Listening Worker completely ── */
  function _stopVoiceWorker() {
    _voiceActive = false;
    _voicePaused = false;

    // Cancel rAF loop
    if (_voiceRafId) { cancelAnimationFrame(_voiceRafId); _voiceRafId = null; }

    // Stop recorder
    if (_voiceRecorder) {
      try { _voiceRecorder.stop(); } catch (_) {}
      _voiceRecorder = null;
    }
    _voiceChunks = [];

    // Close AudioContext
    if (_voiceAudioCtx) {
      try { _voiceAudioCtx.close(); } catch (_) {}
      _voiceAudioCtx = null;
      _voiceAnalyser = null;
    }

    // Release mic stream
    if (_voiceStream) {
      _voiceStream.getTracks().forEach(t => t.stop());
      _voiceStream = null;
    }

    // Cancel TTS
    _ttsCancelSpeak();

    // Reset UI
    _voiceSetStatus('off');
    const inp = document.getElementById('guide-chat-input');
    if (inp) inp.placeholder = 'Ask me anything…';
    const bar = document.getElementById('guide-voice-listening-bar');
    if (bar) { bar.classList.remove('active', 'speaking', 'processing'); }

    // Reset waveform bar heights
    document.querySelectorAll('#guide-voice-listening-bar .guide-voice-waves span')
      .forEach(b => (b.style.height = ''));
  }

  /* ── Resume a new recording cycle after Gemini/TTS cycle ── */
  function _voiceResumeAfterReply() {
    if (!_voiceActive) return;
    _voicePaused = false;
    _beginRecording();
  }

  function _toggleVoiceWorker() {
    if (_voiceActive) { _stopVoiceWorker(); }
    else              { _startVoiceWorker(); }
  }


  /* ════════════════════════════════════════════════════════════════════════
     CONTEXT MENU
     Right-click on the guide character image-wrap shows the context menu.
     ════════════════════════════════════════════════════════════════════════ */
  function _openCtxMenu(x, y) {
    const menu = document.getElementById('guide-ctx-menu');
    if (!menu) return;

    // Position: keep inside viewport
    menu.style.display = 'block';
    const mw = menu.offsetWidth  || 210;
    const mh = menu.offsetHeight || 160;
    let cx = x, cy = y;
    if (cx + mw > window.innerWidth  - 8) cx = window.innerWidth  - mw - 8;
    if (cy + mh > window.innerHeight - 8) cy = window.innerHeight - mh - 8;
    menu.style.left = cx + 'px';
    menu.style.top  = cy + 'px';
    menu.style.display = 'block';
  }

  function _closeCtxMenu() {
    const menu = document.getElementById('guide-ctx-menu');
    if (menu) menu.style.display = 'none';
  }

  function _initCtxMenu() {
    const menu     = document.getElementById('guide-ctx-menu');
    const imgWrap  = document.getElementById('guide-char-img-wrap');
    const badge    = document.getElementById('guide-voice-badge');
    if (!menu || !imgWrap) return;

    // Right-click on the character
    imgWrap.addEventListener('contextmenu', e => {
      e.preventDefault();
      e.stopPropagation();
      _openCtxMenu(e.clientX, e.clientY);
    });

    // Clicking the mic badge directly toggles the worker
    badge?.addEventListener('click', e => {
      e.stopPropagation();
      _stopVoiceWorker();
    });

    // Context menu item: Voice Worker
    document.getElementById('guide-ctx-voice')?.addEventListener('click', () => {
      _closeCtxMenu();
      _toggleVoiceWorker();
    });

    // Context menu item: Open Chat
    document.getElementById('guide-ctx-chat')?.addEventListener('click', () => {
      _closeCtxMenu();
      if (!_bubbleOpen) _openBubble();
    });

    // Context menu item: Reset Position
    document.getElementById('guide-ctx-reset')?.addEventListener('click', () => {
      _closeCtxMenu();
      _returnHome();
    });

    // Context menu item: Dismiss
    document.getElementById('guide-ctx-dismiss')?.addEventListener('click', () => {
      _closeCtxMenu();
      _setGuideVisible(false);
      _stopVoiceWorker();
    });

    // Close on outside click or Escape
    document.addEventListener('click', e => {
      if (!menu.contains(e.target)) _closeCtxMenu();
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') _closeCtxMenu();
    });
  }

  /* ════════════════════════════════════════════════════════════════════════
     DRAGGABLE
     ════════════════════════════════════════════════════════════════════════ */
  function _makeDraggable(wrap, handle) {
    let active = false, startX, startY, startRight, startBottom;

    handle.addEventListener('mousedown', e => {
      if (e.target.closest('#guide-char-dismiss')) return;
      if (e.target.closest('#guide-voice-badge'))  return;
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
    _initCtxMenu();
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
