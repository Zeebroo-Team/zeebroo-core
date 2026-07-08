/**
 * Zeebroo POS — Guide Assistant
 * Floating, draggable business-man character with contextual tab tips.
 */
(function () {
  'use strict';

  /* ── Tip bank ──────────────────────────────────────────────────────────── */

  const TIPS = {
    home: [
      "Welcome back! Here's your business overview — check today's revenue, top products, and activity at a glance.",
      "Switch to Analytics in the Home ribbon to see trend charts and date-range comparisons.",
      "Log daily expenses here to keep your profit figures accurate.",
      "The KPI cards update in real time as sales come in throughout the day.",
    ],
    pos: [
      "Ring up a sale by searching products by name, SKU, or scanning a barcode.",
      "Click any product card to add it to the cart — tap the quantity field to adjust.",
      "Apply item-level or whole-order discounts right in the cart before checkout.",
      "Enable the Checkout Modal in Settings for a guided, step-by-step payment flow.",
      "Park a sale to hold it and start a new one — come back to it any time!",
    ],
    sales: [
      "All your past transactions live here. Filter by date range or payment method.",
      "Click any sale row to see the full itemised receipt and payment breakdown.",
      "Generate and export sales reports from here for accounting or tax records.",
      "The Sales History view shows totals and trends over your selected period.",
    ],
    inventory: [
      "Manage products, stock levels, categories, and pricing all in one place.",
      "Set low-stock thresholds — you'll get a warning before items run out.",
      "Use Purchase Orders to log supplier deliveries and auto-update your stock.",
      "Print or generate barcodes for any product directly from the product list.",
      "Variants let you handle different sizes, colours, and options for one product.",
    ],
    finance: [
      "Log income, expenses, bills, and property assets in the Finance tab.",
      "Schedule recurring bills so you never miss a payment deadline.",
      "Property and asset tracking helps with depreciation and valuation reports.",
      "The Finance dashboard shows a running balance across all accounts.",
    ],
    hr: [
      "Add and manage team members, track attendance, and run payroll from here.",
      "Assign job titles and departments to keep your org structure organised.",
      "Generate payroll summary reports per pay period from the HR section.",
      "Employee profiles store contact info, documents, and performance notes.",
    ],
    services: [
      "Manage service jobs, bookings, and employee assignments in Services.",
      "Link jobs to employees and track time spent and completion status.",
      "Create service packages to bundle tasks and quote clients quickly.",
    ],
    design: [
      "Create branded posters, receipt layouts, and marketing content here.",
      "Start from a template to get a professional design up in minutes.",
      "Save your designs and reuse them for consistent branding across materials.",
    ],
    restaurant: [
      "Set up your menu, table layout, categories, and dietary labels here.",
      "Organise menu items into clear categories for faster order taking.",
      "Configure table groups to match your restaurant's actual floor plan.",
    ],
    'rst-pos': [
      "Take live table orders and fire them to the kitchen from this screen.",
      "Tap a table to view active orders — add or remove items mid-service.",
      "Close a table to bill the customer and free up the seat for the next guest.",
      "The kitchen display updates instantly when you confirm an order.",
    ],
  };

  /* ── State ─────────────────────────────────────────────────────────────── */

  let _currentTab  = null;
  let _tipIdx      = {};
  let _hideTimer   = null;
  let _dismissed   = false;
  let _initialized = false;

  /* ── Helpers ───────────────────────────────────────────────────────────── */

  function _activeTab() {
    return document.querySelector('.ribbon-tab.active')?.dataset.tab || 'home';
  }

  function _nextTip(tab) {
    const list = TIPS[tab] || TIPS.home;
    if (_tipIdx[tab] == null) _tipIdx[tab] = 0;
    const tip    = list[_tipIdx[tab] % list.length];
    _tipIdx[tab] = (_tipIdx[tab] + 1) % list.length;
    return tip;
  }

  /* ── Bubble ─────────────────────────────────────────────────────────────── */

  function showBubble(text) {
    const bubble = document.getElementById('guide-bubble');
    const textEl = document.getElementById('guide-bubble-text');
    const label  = document.getElementById('guide-bubble-label-tab');
    if (!bubble || !textEl) return;

    // Update label
    if (label) {
      const tab = _activeTab();
      label.textContent = tab.replace('-', ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    textEl.textContent = text;
    bubble.style.display = 'block';
    // Restart CSS animation
    bubble.classList.remove('guide-bubble-visible');
    void bubble.offsetWidth;
    bubble.classList.add('guide-bubble-visible');

    clearTimeout(_hideTimer);
    _hideTimer = setTimeout(hideBubble, 10000);
  }

  function hideBubble() {
    clearTimeout(_hideTimer);
    const bubble = document.getElementById('guide-bubble');
    if (!bubble) return;
    bubble.classList.remove('guide-bubble-visible');
    // hide after fade
    setTimeout(() => { if (!bubble.classList.contains('guide-bubble-visible')) bubble.style.display = 'none'; }, 200);
  }

  /* ── Tab change ─────────────────────────────────────────────────────────── */

  function onTabChange(tab) {
    if (_dismissed) return;
    if (tab === _currentTab) return;
    _currentTab = tab;
    setTimeout(() => showBubble(_nextTip(tab)), 500);
  }

  /* ── Drag ───────────────────────────────────────────────────────────────── */

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

  /* ── Init ───────────────────────────────────────────────────────────────── */

  function initGuide() {
    if (_initialized) return;
    _initialized = true;

    const wrap       = document.getElementById('guide-char-wrap');
    const imgWrap    = document.getElementById('guide-char-img-wrap');
    const bubble     = document.getElementById('guide-bubble');
    const dismissBtn = document.getElementById('guide-char-dismiss');
    const reopenBtn  = document.getElementById('guide-reopen-btn');
    const nextBtn    = document.getElementById('guide-next-tip');
    const closeBtn   = document.getElementById('guide-close-bubble');

    if (!wrap || !imgWrap) return;

    wrap.style.display = 'block';

    // Toggle bubble on character click
    imgWrap.addEventListener('click', (e) => {
      if (e.target.closest('#guide-char-dismiss')) return;
      if (bubble && bubble.style.display === 'block') {
        hideBubble();
      } else {
        showBubble(_nextTip(_activeTab()));
      }
    });

    // Next tip
    nextBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      showBubble(_nextTip(_activeTab()));
    });

    // Close bubble
    closeBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      hideBubble();
    });

    // Dismiss character entirely
    dismissBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      _dismissed = true;
      hideBubble();
      wrap.style.display = 'none';
      if (reopenBtn) reopenBtn.style.display = 'flex';
    });

    // Reopen
    reopenBtn?.addEventListener('click', () => {
      _dismissed = false;
      wrap.style.display = 'block';
      reopenBtn.style.display = 'none';
      showBubble(_nextTip(_activeTab()));
    });

    // Draggable
    _makeDraggable(wrap, imgWrap);

    // Listen for ribbon tab clicks
    document.getElementById('ribbon-tabs')?.addEventListener('click', (e) => {
      const tab = e.target.closest('[data-tab]')?.dataset.tab;
      if (tab) onTabChange(tab);
    });

    // Greet on first load
    _currentTab = _activeTab();
    setTimeout(() => showBubble(_nextTip(_currentTab)), 2000);
  }

  /* ── Wait for login ─────────────────────────────────────────────────────── */

  function watchForLogin() {
    const loginScreen = document.getElementById('login-screen');
    if (!loginScreen) { setTimeout(watchForLogin, 150); return; }

    if (loginScreen.style.display === 'none') {
      // Already logged in (dev reload)
      initGuide();
      return;
    }

    const obs = new MutationObserver(() => {
      if (loginScreen.style.display === 'none') {
        obs.disconnect();
        initGuide();
      }
    });
    obs.observe(loginScreen, { attributes: true, attributeFilter: ['style'] });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watchForLogin);
  } else {
    watchForLogin();
  }
}());
