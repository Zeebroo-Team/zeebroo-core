/**
 * Zeebroo POS — Guide Assistant + Ribbon Tour
 *
 * Floating draggable business-man character with:
 *   • Contextual tab tips (click character or switch tabs)
 *   • Interactive ribbon tour — spotlight + bubble steps through each section
 */
(function () {
  'use strict';

  /* ════════════════════════════════════════════════════════════════════════
     TIPS  (contextual tips shown in the character bubble)
     ════════════════════════════════════════════════════════════════════════ */
  const TIPS = {
    home: [
      "Here's your business dashboard — daily revenue, top products, and activity all in one view.",
      "Switch to Analytics in the Home ribbon to see trend charts over any date range.",
      "Log daily expenses here to keep your profit figures accurate.",
    ],
    pos: [
      "Ring up a sale by searching products by name, SKU, or scanning a barcode.",
      "Click any product card to add it to the cart — tap the quantity to adjust.",
      "Apply item-level or whole-order discounts right in the cart before checkout.",
      "Park a sale to hold it and start a new one — come back to it any time!",
    ],
    sales: [
      "All past transactions are here. Filter by date range or payment method.",
      "Click any sale to see the full itemised receipt and payment breakdown.",
      "Export sales reports from here for accounting or tax records.",
    ],
    inventory: [
      "Manage products, stock levels, categories, and pricing all in one place.",
      "Set low-stock thresholds — you'll see a warning before items run out.",
      "Use Purchase Orders to log supplier deliveries and auto-update stock.",
      "Print or generate barcodes for any product directly from the list.",
    ],
    finance: [
      "Log income, expenses, bills, and property assets in the Finance tab.",
      "Schedule recurring bills so you never miss a payment deadline.",
      "Property and asset tracking helps with depreciation and valuation reports.",
    ],
    hr: [
      "Add team members, track attendance, and run payroll from here.",
      "Assign job titles and departments to keep your org structure clean.",
      "Generate payroll summary reports per period from the HR section.",
    ],
    services: [
      "Manage service jobs, bookings, and employee assignments in Services.",
      "Link jobs to employees and track time spent and completion status.",
    ],
    design: [
      "Create branded posters, receipt layouts, and marketing content here.",
      "Pick from templates to get a professional design up in minutes.",
    ],
    restaurant: [
      "Set up your menu, table layout, categories, and dietary labels here.",
      "Organise menu items into clear categories for faster order taking.",
    ],
    'rst-pos': [
      "Take live table orders and fire them to the kitchen from here.",
      "Tap a table to view active orders — add or remove items mid-service.",
      "Close a table to process payment and free up the seat.",
    ],
  };

  /* ════════════════════════════════════════════════════════════════════════
     TOUR STEPS  (one entry per ribbon section)
     ════════════════════════════════════════════════════════════════════════ */
  const TOUR_STEPS = [
    {
      selector: '[data-tab="home"]',
      icon: 'fa-house',
      title: 'Home Dashboard',
      text: 'Your command centre — live revenue, top products, expense summary, KPI cards, and a full analytics view. Start every day here.',
    },
    {
      selector: '[data-tab="pos"]',
      icon: 'fa-cash-register',
      title: 'Point of Sale',
      text: 'Ring up sales in seconds. Search by name or barcode, adjust quantities, split payments, apply discounts, and print or email receipts.',
    },
    {
      selector: '[data-tab="sales"]',
      icon: 'fa-receipt',
      title: 'Sales',
      text: 'Complete transaction history with date-range filters, itemised receipt preview, void & return tools, and export for accounting.',
    },
    {
      selector: '[data-tab="inventory"]',
      icon: 'fa-boxes-stacked',
      title: 'Inventory',
      text: 'Add products, manage stock levels and pricing, set reorder alerts, run purchase orders, and print barcodes — all from one panel.',
    },
    {
      selector: '[data-tab="finance"]',
      icon: 'fa-chart-line',
      title: 'Finance',
      text: 'Track income and expenses, schedule bills, manage subscriptions, and record property or investment assets with depreciation tracking.',
    },
    {
      selector: '[data-tab="hr"]',
      icon: 'fa-users',
      title: 'Human Resources',
      text: 'Manage your team — add employees, track attendance, handle leave, run payroll, and export payroll reports by period.',
    },
    {
      selector: '[data-tab="services"]',
      icon: 'fa-briefcase',
      title: 'Services',
      text: 'Assign service jobs to employees, track time and completion status, manage client bookings, and bill completed jobs.',
    },
    {
      selector: '[data-tab="design"]',
      icon: 'fa-palette',
      title: 'Design Studio',
      text: 'Build branded receipts, promotional posters, and marketing materials with a drag-and-drop editor and template library.',
    },
    {
      selector: '[data-tab="restaurant"]',
      icon: 'fa-utensils',
      title: 'Restaurant',
      text: 'Configure your full restaurant setup — menus, categories, table layout, dietary filters, ingredients, and kitchen workflow.',
    },
    {
      selector: '[data-tab="rst-pos"]',
      icon: 'fa-kitchen-set',
      title: 'Restaurant POS',
      text: 'Live order taking by table — tap a table, add items from the menu, fire to kitchen, and bill at close. Full table management.',
    },
  ];

  /* ════════════════════════════════════════════════════════════════════════
     STATE
     ════════════════════════════════════════════════════════════════════════ */
  let _currentTab  = null;
  let _tipIdx      = {};
  let _hideTimer   = null;
  let _dismissed   = false;
  let _initialized = false;

  let _tourActive  = false;
  let _tourStep    = 0;
  let _tourSteps   = [];   // filtered list of visible steps

  /* ════════════════════════════════════════════════════════════════════════
     HELPERS
     ════════════════════════════════════════════════════════════════════════ */
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

  /* ════════════════════════════════════════════════════════════════════════
     CHARACTER BUBBLE
     ════════════════════════════════════════════════════════════════════════ */
  function showBubble(text) {
    const bubble = document.getElementById('guide-bubble');
    const textEl = document.getElementById('guide-bubble-text');
    const label  = document.getElementById('guide-bubble-label-tab');
    if (!bubble || !textEl) return;
    if (label) {
      const tab = _activeTab();
      label.textContent = tab.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }
    textEl.textContent = text;
    bubble.style.display = 'block';
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
    setTimeout(() => {
      if (!bubble.classList.contains('guide-bubble-visible')) bubble.style.display = 'none';
    }, 200);
  }

  function onTabChange(tab) {
    if (_dismissed || _tourActive) return;
    if (tab === _currentTab) return;
    _currentTab = tab;
    setTimeout(() => showBubble(_nextTip(tab)), 500);
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
     TOUR
     ════════════════════════════════════════════════════════════════════════ */

  function _buildTourSteps() {
    return TOUR_STEPS.filter(step => {
      const el = document.querySelector(step.selector);
      return el && el.offsetWidth > 0 && el.offsetHeight > 0;
    });
  }

  function _positionTourBubble(tabEl) {
    const bubble = document.getElementById('guide-tour-bubble');
    const arrow  = document.getElementById('guide-tour-arrow');
    if (!bubble) return;

    const BUBBLE_W  = 310;
    const BUBBLE_GAP = 14;      // gap between spotlight bottom and bubble top
    const SPOTLIGHT_PAD = 6;    // padding around tab rect

    const rect = tabEl.getBoundingClientRect();

    // Spotlight
    const spot = document.getElementById('guide-tour-spotlight');
    if (spot) {
      spot.style.top    = (rect.top    - SPOTLIGHT_PAD) + 'px';
      spot.style.left   = (rect.left   - SPOTLIGHT_PAD) + 'px';
      spot.style.width  = (rect.width  + SPOTLIGHT_PAD * 2) + 'px';
      spot.style.height = (rect.height + SPOTLIGHT_PAD * 2) + 'px';
      spot.style.display = 'block';
    }

    // Bubble: try below the tab first
    const tabCentreX = rect.left + rect.width / 2;
    let bubbleLeft = tabCentreX - BUBBLE_W / 2;
    bubbleLeft = Math.max(12, Math.min(window.innerWidth - BUBBLE_W - 12, bubbleLeft));

    const bubbleTop  = rect.bottom + SPOTLIGHT_PAD + BUBBLE_GAP;
    const bubbleTopClamped = Math.min(bubbleTop, window.innerHeight - 220);

    bubble.style.left    = bubbleLeft + 'px';
    bubble.style.top     = bubbleTopClamped + 'px';
    bubble.style.bottom  = 'auto';

    // Arrow: offset within bubble so it points at tab centre
    if (arrow) {
      const arrowOffset = tabCentreX - bubbleLeft - 11;  // 11 = half arrow width
      arrow.style.left = Math.max(16, Math.min(BUBBLE_W - 38, arrowOffset)) + 'px';
      // If bubble is below tab show up-arrow; if pushed above tab show down-arrow
      const isAbove = bubbleTopClamped < rect.top;
      arrow.style.top    = isAbove ? 'auto' : '-10px';
      arrow.style.bottom = isAbove ? '-10px' : 'auto';
      arrow.style.borderTopColor    = isAbove ? '#4263eb' : 'transparent';
      arrow.style.borderBottomColor = isAbove ? 'transparent' : '#4263eb';
    }
  }

  function _showTourStep(i) {
    const step   = _tourSteps[i];
    const bubble = document.getElementById('guide-tour-bubble');
    const img    = document.getElementById('guide-char-img');
    if (!step || !bubble) return;

    const tabEl = document.querySelector(step.selector);
    if (!tabEl) { _endTour(); return; }

    // Update bubble content
    const iconEl    = document.getElementById('guide-tour-icon');
    const titleEl   = document.getElementById('guide-tour-title');
    const textEl    = document.getElementById('guide-tour-text');
    const counterEl = document.getElementById('guide-tour-counter');
    const nextBtn   = document.getElementById('guide-tour-next');

    if (iconEl)    iconEl.innerHTML  = `<i class="fa ${step.icon}"></i>`;
    if (titleEl)   titleEl.textContent = step.title;
    if (textEl)    textEl.textContent  = step.text;
    if (counterEl) counterEl.textContent = `${i + 1} / ${_tourSteps.length}`;
    if (nextBtn)   nextBtn.innerHTML  = i < _tourSteps.length - 1
      ? 'Next <i class="fa fa-arrow-right"></i>'
      : '<i class="fa fa-check"></i> Done';

    // Show & animate bubble
    bubble.style.display = 'block';
    bubble.style.animation = 'none';
    void bubble.offsetWidth;
    bubble.style.animation = '';

    // Highlight ribbon tab and position bubble
    _positionTourBubble(tabEl);

    // Make character bounce with excitement
    img?.classList.add('guide-touring');
  }

  function startTour() {
    _tourSteps = _buildTourSteps();
    if (!_tourSteps.length) return;

    _tourActive = true;
    _tourStep   = 0;
    hideBubble();

    // Ensure character is visible
    _setGuideVisible(true);

    _showTourStep(0);
  }

  function _advanceTour() {
    _tourStep++;
    if (_tourStep >= _tourSteps.length) {
      _endTour();
    } else {
      _showTourStep(_tourStep);
    }
  }

  function _endTour() {
    _tourActive = false;

    const spot   = document.getElementById('guide-tour-spotlight');
    const bubble = document.getElementById('guide-tour-bubble');
    const img    = document.getElementById('guide-char-img');

    if (spot)   spot.style.display   = 'none';
    if (bubble) bubble.style.display = 'none';
    img?.classList.remove('guide-touring');

    // Save that tour was seen
    try { localStorage.setItem('zeebroo_tour_seen', '1'); } catch (e) { /* ignore */ }

    // Show a closing message in the character bubble
    setTimeout(() => {
      showBubble("That's the full tour! Click me any time for tips, or hit \"Tour\" to run through it again.");
    }, 300);
  }

  /* ════════════════════════════════════════════════════════════════════════
     INIT
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
      wrap.style.display = 'none';
      _dismissed = true;
      toggleBtn?.classList.remove('guide-visible');
    }
  }

  function initGuide() {
    if (_initialized) return;
    _initialized = true;

    const wrap       = document.getElementById('guide-char-wrap');
    const imgWrap    = document.getElementById('guide-char-img-wrap');
    const bubble     = document.getElementById('guide-bubble');
    const dismissBtn = document.getElementById('guide-char-dismiss');
    const toggleBtn  = document.getElementById('guide-toggle-btn');
    const nextTipBtn = document.getElementById('guide-next-tip');
    const closeBtn   = document.getElementById('guide-close-bubble');
    const tourBtn    = document.getElementById('guide-start-tour');
    const tourNext   = document.getElementById('guide-tour-next');
    const tourSkip   = document.getElementById('guide-tour-skip');

    if (!wrap || !imgWrap) return;

    _setGuideVisible(true);

    /* ── Character bubble ── */
    imgWrap.addEventListener('click', (e) => {
      if (e.target.closest('#guide-char-dismiss')) return;
      if (_tourActive) return;
      if (bubble && bubble.style.display === 'block') {
        hideBubble();
      } else {
        showBubble(_nextTip(_activeTab()));
      }
    });

    nextTipBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      showBubble(_nextTip(_activeTab()));
    });

    closeBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      hideBubble();
    });

    /* ── Tour trigger ── */
    tourBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      hideBubble();
      startTour();
    });

    /* ── Tour navigation ── */
    tourNext?.addEventListener('click', () => _advanceTour());
    tourSkip?.addEventListener('click', () => _endTour());

    /* ── Dismiss × on character (hover button) ── */
    dismissBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      if (_tourActive) _endTour();
      hideBubble();
      _setGuideVisible(false);
    });

    /* ── Ribbon toggle button ── */
    toggleBtn?.addEventListener('click', () => {
      if (_dismissed) {
        _setGuideVisible(true);
        showBubble(_nextTip(_activeTab()));
      } else {
        if (_tourActive) _endTour();
        hideBubble();
        _setGuideVisible(false);
      }
    });

    /* ── Draggable ── */
    _makeDraggable(wrap, imgWrap);

    /* ── Tab-change tips ── */
    document.getElementById('ribbon-tabs')?.addEventListener('click', (e) => {
      const tab = e.target.closest('[data-tab]')?.dataset.tab;
      if (tab) onTabChange(tab);
    });

    /* ── First load: greet or auto-start tour ── */
    _currentTab = _activeTab();
    const tourSeen = (() => { try { return localStorage.getItem('zeebroo_tour_seen'); } catch (e) { return null; } })();

    if (!tourSeen) {
      // First-ever login: brief greeting then auto-start tour
      setTimeout(() => {
        showBubble("Welcome to Zeebroo POS! Let me show you around — I'll highlight each section so you know where everything is.");
        setTimeout(startTour, 3200);
      }, 1800);
    } else {
      // Returning user: show a tip after a short delay
      setTimeout(() => showBubble(_nextTip(_currentTab)), 2000);
    }
  }

  /* ════════════════════════════════════════════════════════════════════════
     WAIT FOR LOGIN
     Watch #app-shell becoming display:flex — fired by showApp() on login.
     This is a positive trigger so the guide never appears on the login or
     sign-up screens.
     ════════════════════════════════════════════════════════════════════════ */
  function watchForLogin() {
    const appShell = document.getElementById('app-shell');
    if (!appShell) { setTimeout(watchForLogin, 150); return; }

    if (appShell.style.display === 'flex') {
      initGuide();
      return;
    }

    const obs = new MutationObserver(() => {
      if (appShell.style.display === 'flex') {
        obs.disconnect();
        initGuide();
      }
    });
    obs.observe(appShell, { attributes: true, attributeFilter: ['style'] });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watchForLogin);
  } else {
    watchForLogin();
  }

  /* expose for external calls (e.g. help menu) */
  window.startGuideTour = startTour;

}());
