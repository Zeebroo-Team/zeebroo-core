/**
 * Zeebroo POS — Guide Chat Assistant
 * Floating draggable character with AI chat + intent-driven guided walkthroughs.
 */
(function () {
  'use strict';

  /* ════════════════════════════════════════════════════════════════════════
     FIELD MAP  — maps user-mentioned field names → product modal input IDs
     ════════════════════════════════════════════════════════════════════════ */
  const FIELD_MAP = {
    'name':          { id: 'prod-f-name',        label: 'Name' },
    'title':         { id: 'prod-f-name',        label: 'Name' },
    'sku':           { id: 'prod-f-sku',         label: 'SKU' },
    'barcode':       { id: 'prod-f-sku',         label: 'SKU / Barcode' },
    'code':          { id: 'prod-f-sku',         label: 'SKU / Barcode' },
    'price':         { id: 'prod-f-price',       label: 'Selling Price' },
    'cost':          { id: 'prod-f-price',       label: 'Selling Price' },
    'selling price': { id: 'prod-f-price',       label: 'Selling Price' },
    'amount':        { id: 'prod-f-price',       label: 'Selling Price' },
    'stock':         { id: 'prod-f-stock',       label: 'Stock Quantity' },
    'quantity':      { id: 'prod-f-stock',       label: 'Stock Quantity' },
    'qty':           { id: 'prod-f-stock',       label: 'Stock Quantity' },
    'unit':          { id: 'prod-f-unit',        label: 'Unit' },
    'description':   { id: 'prod-f-description', label: 'Description' },
    'desc':          { id: 'prod-f-description', label: 'Description' },
    'details':       { id: 'prod-f-description', label: 'Description' },
    'category':      { id: 'prod-cat-input',     label: 'Category' },
    'categories':    { id: 'prod-cat-input',     label: 'Category' },
    'brand':         { id: 'prod-brand-input',   label: 'Brand' },
    'image':         { id: 'prod-img-choose',    label: 'Image' },
    'photo':         { id: 'prod-img-choose',    label: 'Image' },
    'status':        { id: 'prod-f-active',      label: 'Active Status' },
    'active':        { id: 'prod-f-active',      label: 'Active Status' },
  };

  /* ════════════════════════════════════════════════════════════════════════
     INTENT PARSING
     ════════════════════════════════════════════════════════════════════════ */

  // Parse "edit product <name> change <field>" or "<name> edit product change <field>"
  function _parseEditIntent(msg) {
    let m;

    // "<name> edit product change <field>"
    m = msg.match(/^(.+?)\s+edit\s+product\s+change\s+(.+)$/i);
    if (m) return { productName: m[1].trim(), fieldName: m[2].trim() };

    // "edit product <name> change <field>"
    m = msg.match(/edit\s+product\s+(.+?)\s+change\s+(.+)$/i);
    if (m) return { productName: m[1].trim(), fieldName: m[2].trim() };

    // "<name> edit product change <field>" (looser)
    m = msg.match(/(.+?)\s+edit\s+product\s+change\s+(.+)/i);
    if (m) return { productName: m[1].trim(), fieldName: m[2].trim() };

    // "update product <name> change <field>" / "change product <name> <field>"
    m = msg.match(/(?:update|change)\s+product\s+(.+?)\s+(?:change|update)?\s*(.+)/i);
    if (m) return { productName: m[1].trim(), fieldName: m[2].trim() };

    // "edit product <name>" — no field specified
    m = msg.match(/edit\s+product\s+(.+)/i);
    if (m) return { productName: m[1].trim(), fieldName: null };

    return null;
  }

  function _resolveField(rawName) {
    if (!rawName) return null;
    const key = rawName.toLowerCase().trim();
    if (FIELD_MAP[key]) return FIELD_MAP[key];
    // Partial match
    for (const [k, v] of Object.entries(FIELD_MAP)) {
      if (key.includes(k) || k.includes(key)) return v;
    }
    return null;
  }

  /* ════════════════════════════════════════════════════════════════════════
     INTENT MAP  — matched before API call; triggers guided walkthrough
     ════════════════════════════════════════════════════════════════════════ */
  const INTENT_MAP = [
    {
      test: msg => /add\s*(a\s*)?new\s*product|new\s*product|create\s*product|add\s*product/i.test(msg),
      reply: "Sure! Follow me — I'll walk you through adding a new product right now.",
      action: () => _walkthroughAddProduct(),
    },
    {
      test: msg => !!_parseEditIntent(msg),
      reply: null,   // reply built dynamically
      action: msg => {
        const parsed = _parseEditIntent(msg);
        const field  = _resolveField(parsed?.fieldName);
        const replyText = parsed?.fieldName
          ? `Got it! I'll find "${parsed.productName}" and take you to the ${field?.label || parsed.fieldName} field.`
          : `Got it! Let me find "${parsed?.productName}" and open it for editing.`;
        return { replyText, run: () => _walkthroughEditProduct(parsed.productName, parsed.fieldName) };
      },
    },
  ];

  /* ════════════════════════════════════════════════════════════════════════
     STATE
     ════════════════════════════════════════════════════════════════════════ */
  let _dismissed   = false;
  let _initialized = false;
  let _bubbleOpen  = false;
  let _busy        = false;

  /* ════════════════════════════════════════════════════════════════════════
     HELPERS
     ════════════════════════════════════════════════════════════════════════ */
  const _sleep = ms => new Promise(r => setTimeout(r, ms));

  function _highlight(el) { el?.classList.add('guide-target-pulse'); }
  function _unhighlight(el) { el?.classList.remove('guide-target-pulse'); }

  function _waitVisible(el, maxMs) {
    maxMs = maxMs || 2500;
    return new Promise(resolve => {
      if (!el) { resolve(false); return; }
      const start = Date.now();
      const check = () => {
        const s = window.getComputedStyle(el);
        if (s.display !== 'none' && s.visibility !== 'hidden') { resolve(true); return; }
        if (Date.now() - start > maxMs) { resolve(false); return; }
        setTimeout(check, 80);
      };
      check();
    });
  }

  function _waitForProductRows(maxMs) {
    maxMs = maxMs || 3000;
    return new Promise(resolve => {
      const start = Date.now();
      const check = () => {
        const rows = document.querySelectorAll('#inv-tbody .inv-row');
        if (rows.length > 0) { resolve(rows); return; }
        if (Date.now() - start > maxMs) { resolve([]); return; }
        setTimeout(check, 100);
      };
      check();
    });
  }

  function _findProductRow(productName) {
    const nameLower = productName.toLowerCase();
    const rows = document.querySelectorAll('#inv-tbody .inv-row');
    for (const row of rows) {
      const rowName = (row.querySelector('.inv-name')?.textContent || '').toLowerCase();
      if (rowName.includes(nameLower)) return row;
    }
    return null;
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

      // Prefer below; fall back to above when near the bottom of the viewport
      let top  = r.bottom + 14;
      let left = r.left + r.width / 2 - charW / 2;

      if (top + 110 > window.innerHeight) top = Math.max(4, r.top - 114);
      left = Math.max(4, Math.min(window.innerWidth - charW - 4, left));
      top  = Math.max(4, top);

      // First call: convert from bottom/right anchor to top/left
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

      const img = document.getElementById('guide-char-img');
      img?.classList.add('guide-walk-bounce');
      setTimeout(() => { img?.classList.remove('guide-walk-bounce'); resolve(); }, 680);
    });
  }

  function _returnHome() {
    return new Promise(resolve => {
      const wrap = document.getElementById('guide-char-wrap');
      if (!wrap) { resolve(); return; }

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

  function _reopenWithReply(text) {
    _showReplyState(text);
    const bubble = document.getElementById('guide-bubble');
    if (!bubble) return;
    _bubbleOpen = true;
    bubble.style.display = 'block';
    bubble.classList.remove('guide-pop-out');
    void bubble.offsetWidth;
    bubble.classList.add('guide-pop-in');
  }

  /* ════════════════════════════════════════════════════════════════════════
     SEND / RECEIVE
     ════════════════════════════════════════════════════════════════════════ */
  async function _sendMessage() {
    if (_busy) return;
    const input   = document.getElementById('guide-chat-input');
    const message = input?.value.trim();
    if (!message) { input?.focus(); return; }

    // ── Intent 1: add new product ────────────────────────────────────────
    const addIntent = INTENT_MAP[0];
    if (addIntent.test(message)) {
      _busy = true;
      _closeBubble();
      await _sleep(240);
      _reopenWithReply(addIntent.reply);
      await _sleep(1800);
      _closeBubble();
      await _sleep(300);
      addIntent.action(message);
      return;
    }

    // ── Intent 2: edit product ────────────────────────────────────────────
    const editIntent = INTENT_MAP[1];
    if (editIntent.test(message)) {
      const { replyText, run } = editIntent.action(message);
      _busy = true;
      _closeBubble();
      await _sleep(240);
      _reopenWithReply(replyText);
      await _sleep(1800);
      _closeBubble();
      await _sleep(300);
      run();
      return;
    }

    // ── Normal AI chat ────────────────────────────────────────────────────
    const sendBtn = document.getElementById('guide-chat-send');
    _busy = true;
    if (sendBtn) { sendBtn.disabled = true; sendBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }

    _closeBubble();

    setTimeout(async () => {
      let reply = 'Sorry, I could not get a response right now. Please try again.';
      try {
        const res = await API.guideChat(message);
        if (res.status === 200 && res.body?.reply) reply = res.body.reply;
      } catch (e) { /* use default */ }

      _reopenWithReply(reply);
      _busy = false;
    }, 240);
  }

  /* ════════════════════════════════════════════════════════════════════════
     WALKTHROUGH — Add New Product
     ════════════════════════════════════════════════════════════════════════ */
  async function _walkthroughAddProduct() {
    _busy = true;

    // Step 1: Inventory tab
    const invTab = document.querySelector('[data-tab="inventory"]');
    if (!invTab) { _busy = false; await _returnHome(); return; }
    await _walkTo(invTab);
    _highlight(invTab);
    await _sleep(550);
    invTab.click();
    _unhighlight(invTab);
    await _sleep(750);

    // Step 2: Products sub-tab
    const prodSubTab = document.querySelector('.inv-subnav-btn[data-inv-view="products"]');
    if (prodSubTab) {
      await _walkTo(prodSubTab);
      _highlight(prodSubTab);
      await _sleep(550);
      prodSubTab.click();
      _unhighlight(prodSubTab);
      await _sleep(900);
    }

    // Step 3: New Product button
    const newProdBtn = document.getElementById('inv-new-product-btn');
    if (newProdBtn) {
      await _walkTo(newProdBtn);
      _highlight(newProdBtn);
      await _sleep(700);
      newProdBtn.click();
      _unhighlight(newProdBtn);
      await _sleep(450);
    }

    // Step 4: Save Product button in modal
    const modal = document.getElementById('product-modal');
    if (modal) {
      const visible = await _waitVisible(modal);
      if (visible) {
        await _sleep(250);
        const saveBtn = document.getElementById('prod-modal-save');
        if (saveBtn) {
          await _walkTo(saveBtn);
          _highlight(saveBtn);
          _reopenWithReply("Fill in the product details, then click Save Product when you're done!");
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
     WALKTHROUGH — Edit Existing Product
     ════════════════════════════════════════════════════════════════════════ */
  async function _walkthroughEditProduct(productName, fieldName) {
    _busy = true;
    const field = _resolveField(fieldName);

    // Step 1: Inventory tab
    const invTab = document.querySelector('[data-tab="inventory"]');
    if (!invTab) { _busy = false; await _returnHome(); return; }
    await _walkTo(invTab);
    _highlight(invTab);
    await _sleep(550);
    invTab.click();
    _unhighlight(invTab);
    await _sleep(750);

    // Step 2: Products sub-tab
    const prodSubTab = document.querySelector('.inv-subnav-btn[data-inv-view="products"]');
    if (prodSubTab) {
      await _walkTo(prodSubTab);
      _highlight(prodSubTab);
      await _sleep(550);
      prodSubTab.click();
      _unhighlight(prodSubTab);
      await _sleep(800);
    }

    // Step 3: Search for product
    const searchInput = document.getElementById('inv-search');
    if (searchInput) {
      await _walkTo(searchInput);
      _highlight(searchInput);
      await _sleep(400);
      searchInput.value = productName;
      searchInput.dispatchEvent(new Event('input', { bubbles: true }));
      _unhighlight(searchInput);
    }

    // Step 4: Wait for search results and click the product row
    await _sleep(1200);   // debounce (350ms) + API round-trip
    const rows = await _waitForProductRows(3000);

    const targetRow = _findProductRow(productName);
    if (!targetRow) {
      // Product not found
      _reopenWithReply(`I couldn't find a product named "${productName}". Try checking the exact name in Inventory → Products.`);
      await _sleep(4000);
      _closeBubble();
      if (searchInput) { searchInput.value = ''; searchInput.dispatchEvent(new Event('input', { bubbles: true })); }
      await _returnHome();
      _busy = false;
      return;
    }

    await _walkTo(targetRow);
    _highlight(targetRow);
    await _sleep(600);
    targetRow.click();
    _unhighlight(targetRow);
    await _sleep(700);

    // Step 5: Click the Edit button in the detail view
    const editBtn = document.getElementById('prod-edit-btn');
    const detailView = document.getElementById('inv-detail-view');
    if (detailView) await _waitVisible(detailView);
    await _sleep(300);

    if (editBtn) {
      await _walkTo(editBtn);
      _highlight(editBtn);
      await _sleep(600);
      editBtn.click();
      _unhighlight(editBtn);
      await _sleep(400);
    }

    // Step 6: Wait for modal, scroll to the target field and highlight it
    const modal = document.getElementById('product-modal');
    if (!modal) { await _returnHome(); _busy = false; return; }

    const modalVisible = await _waitVisible(modal);
    if (!modalVisible) { await _returnHome(); _busy = false; return; }
    await _sleep(250);

    if (field) {
      const fieldEl = document.getElementById(field.id);
      if (fieldEl) {
        // Scroll the modal body to show the field
        fieldEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        await _sleep(400);

        await _walkTo(fieldEl);
        _highlight(fieldEl);
        await _sleep(500);

        // Show hint bubble
        _reopenWithReply(`Update the "${field.label}" here, then click Save Product when you're done!`);
        await _sleep(3500);
        _closeBubble();
        await _sleep(200);
        _unhighlight(fieldEl);
      }
    }

    // Step 7: Point to Save Product button
    const saveBtn = document.getElementById('prod-modal-save');
    if (saveBtn) {
      await _walkTo(saveBtn);
      _highlight(saveBtn);
      _reopenWithReply('Click Save Product to save your changes.');
      await _sleep(3000);
      _unhighlight(saveBtn);
      _closeBubble();
      await _sleep(300);
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

    imgWrap.addEventListener('click', (e) => {
      if (e.target.closest('#guide-char-dismiss')) return;
      _toggleBubble();
    });

    sendBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _sendMessage();
    });

    input?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); _sendMessage(); }
    });

    againBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _closeBubble();
      setTimeout(() => { _showInputState(); _openBubble(); }, 220);
    });

    dismissBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _setGuideVisible(false);
    });

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
