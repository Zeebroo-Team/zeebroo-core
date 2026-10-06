'use strict';

const grid = document.getElementById('product-grid');
const searchInput = document.getElementById('search-input');
const chipRowEl = document.getElementById('chip-row');
const cartItemsEl = document.getElementById('cart-items');
const sumItemsEl = document.getElementById('sum-items');
const sumTotalEl = document.getElementById('sum-total');
const discountRow = document.getElementById('discount-row');
const discountInput = document.getElementById('discount-input');
const discountTypeToggle = document.getElementById('discount-type-toggle');
const discountTypeFlatBtn = document.getElementById('discount-type-flat-btn');
let discountType = 'percent'; // 'percent' | 'flat' — which unit discount-input's value is in
const checkoutBtn = document.getElementById('checkout-btn');
const toastEl = document.getElementById('toast');

// Add Product modal
const addProductBtn = document.getElementById('add-product-btn');
const addProductModal = document.getElementById('add-product-modal');
const apNameInput = document.getElementById('ap-name');
const apSkuInput = document.getElementById('ap-sku');
const apSkuGenBtn = document.getElementById('ap-sku-gen');
const apStockInput = document.getElementById('ap-stock');
const apCostInput = document.getElementById('ap-cost');
const apPriceInput = document.getElementById('ap-price');
const apErrorEl = document.getElementById('ap-error');
const apSaveBtn = document.getElementById('ap-save-btn');

// Customer select (lives inside the customer-picker modal)
const customerPickerModal = document.getElementById('customer-picker-modal');
const customerChipEl = document.getElementById('customer-chip');
const customerChipLabelEl = document.getElementById('customer-chip-label');
const customerClearBtn = document.getElementById('customer-clear');
const customerSearchWrapEl = document.getElementById('customer-search-wrap');
const customerSearchInputEl = document.getElementById('customer-search-input');
const customerDropdownEl = document.getElementById('customer-dropdown');
const customerQuickaddEl = document.getElementById('customer-quickadd');
const qaNameInput = document.getElementById('qa-name');
const qaPhoneInput = document.getElementById('qa-phone');
const qaCancelBtn = document.getElementById('qa-cancel');
const qaSaveBtn = document.getElementById('qa-save');

// Checkout modal
const checkoutModal = document.getElementById('checkout-modal');
const coSubtotalEl = document.getElementById('co-subtotal');
const coItemDiscountRowEl = document.getElementById('co-item-discount-row');
const coItemDiscountEl = document.getElementById('co-item-discount');
const coSaveRowEl = document.getElementById('co-save-row');
const coSaveEl = document.getElementById('co-save');
const coGrandTotalEl = document.getElementById('co-grand-total');
const coItemCountEl = document.getElementById('co-item-count');
const orderItemsBodyEl = document.getElementById('order-items-body');
const coSubtotalFootEl = document.getElementById('co-subtotal-foot');
const checkoutCustomerEmptyEl = document.getElementById('checkout-customer-empty');
const checkoutCustomerChipEl = document.getElementById('checkout-customer-chip');
const checkoutCustomerChipLabelEl = document.getElementById('checkout-customer-chip-label');
const checkoutCustomerSelectBtn = document.getElementById('checkout-customer-select-btn');
const checkoutCustomerChangeBtn = document.getElementById('checkout-customer-change-btn');
const checkoutCustomerRemoveBtn = document.getElementById('checkout-customer-remove-btn');
const cartCustomerEmptyEl = document.getElementById('cart-customer-empty');
const cartCustomerChipEl = document.getElementById('cart-customer-chip');
const cartCustomerChipLabelEl = document.getElementById('cart-customer-chip-label');
const cartCustomerSelectBtn = document.getElementById('cart-customer-select-btn');
const cartCustomerChangeBtn = document.getElementById('cart-customer-change-btn');
const cartCustomerRemoveBtn = document.getElementById('cart-customer-remove-btn');
const tenderSection = document.getElementById('tender-section');
const amountReceivedInput = document.getElementById('amount-received-input');
const coAmountDueEl = document.getElementById('co-amount-due');
const coChangeEl = document.getElementById('co-change');
const numpadEl = document.getElementById('numpad');
const exactAmountBtn = document.getElementById('exact-amount-btn');
const clearAmountBtn = document.getElementById('clear-amount-btn');
const checkoutNoteInput = document.getElementById('checkout-note-input');
const checkoutCompleteBtn = document.getElementById('checkout-complete-btn');

// Rental picker
const rentalModal = document.getElementById('rental-modal');
const rentalSubtitleEl = document.getElementById('rental-subtitle');
const rentalDateInput = document.getElementById('rental-date-input');
const rentalDaysLabel = document.getElementById('rental-days-label');
const rentalTotalLabel = document.getElementById('rental-total-label');
const rentalErrorEl = document.getElementById('rental-error');
const rentalConfirmBtn = document.getElementById('rental-confirm-btn');

// Sale-completed receipt/invoice modal
const receiptModal = document.getElementById('receipt-modal');
const receiptSaleNumberEl = document.getElementById('receipt-sale-number');
const receiptPreviewEl = document.getElementById('receipt-preview');
const receiptPrintBtn = document.getElementById('receipt-print-btn');
const receiptDownloadBtn = document.getElementById('receipt-download-btn');

// Dynamic pricing picker
const dynamicModal = document.getElementById('dynamic-modal');
const dynamicSubtitleEl = document.getElementById('dynamic-subtitle');
const dynamicLabelEl = document.getElementById('dynamic-input-label');
const dynamicAmountInput = document.getElementById('dynamic-amount-input');
const dynamicHintEl = document.getElementById('dynamic-hint');
const dynamicErrorEl = document.getElementById('dynamic-error');
const dynamicConfirmBtn = document.getElementById('dynamic-confirm-btn');

// Return & Refund
const returnRefundBtn = document.getElementById('return-refund-btn');
const refundModal = document.getElementById('refund-modal');
const rfndSearchInput = document.getElementById('rfnd-search');
const rfndSaleListEl = document.getElementById('rfnd-sale-list');
const rfndDetailEmptyEl = document.getElementById('rfnd-detail-empty');
const rfndDetailViewEl = document.getElementById('rfnd-detail-view');
const rfndSaleSummaryEl = document.getElementById('rfnd-sale-summary');
const rfndSelectAllInput = document.getElementById('rfnd-select-all');
const rfndItemsListEl = document.getElementById('rfnd-items-list');
const rfndAccountRow = document.getElementById('rfnd-account-row');
const rfndAccountSelect = document.getElementById('rfnd-account-select');
const rfndReasonSelect = document.getElementById('rfnd-reason');
const rfndTotalDisplayEl = document.getElementById('rfnd-total-display');
const rfndAlertEl = document.getElementById('rfnd-alert');
const rfndSubmitBtn = document.getElementById('rfnd-submit');

// Cash Drawer: Open Shift / End of Day / Withdraw Cash
const endOfDayBtn = document.getElementById('end-of-day-btn');
const shiftOpenModal = document.getElementById('shift-open-modal');
const shiftOpenAmountInput = document.getElementById('shift-open-amount');
const shiftOpenErrorEl = document.getElementById('shift-open-error');
const shiftOpenSkipBtn = document.getElementById('shift-open-skip-btn');
const shiftOpenConfirmBtn = document.getElementById('shift-open-confirm-btn');
const eodModal = document.getElementById('eod-modal');
const eodModalBodyEl = document.getElementById('eod-modal-body');
const cashWithdrawModal = document.getElementById('cash-withdraw-modal');
const cashWithdrawBalanceHintEl = document.getElementById('cash-withdraw-balance-hint');
const cashWithdrawAmountInput = document.getElementById('cash-withdraw-amount');
const cashWithdrawNoteInput = document.getElementById('cash-withdraw-note');
const cashWithdrawErrorEl = document.getElementById('cash-withdraw-error');
const cashWithdrawConfirmBtn = document.getElementById('cash-withdraw-confirm-btn');
const cashWithdrawAllBtn = document.getElementById('cash-withdraw-all-btn');
const passwordConfirmModal = document.getElementById('password-confirm-modal');
const passwordConfirmInput = document.getElementById('password-confirm-input');
const passwordConfirmErrorEl = document.getElementById('password-confirm-error');
const passwordConfirmBtn = document.getElementById('password-confirm-btn');

let products = [];
let cart = []; // { cartKey, product_id, name, price, customPrice, qty, stock, isRental?, isDynamic?, ... }
let paymentMethod = 'cash';
let searchDebounce = null;
let posSettings = {}; // POS/Sale settings from the General tab (Settings → General)
let posInvoiceSetup = {}; // Template/paper/margins/accent color from Settings → Invoice Setup

let currentMode = 'products'; // 'products' | 'rental' | 'dynamic'
let categories = [];
let currentCategoryId = '';

let selectedCustomer = null; // { id, label }
let customerSearchDebounce = null;
let customerFocusedIndex = -1;
let lastCustomerResults = [];

function money(n) {
  const amount = (Number(n) || 0).toFixed(2);
  const currency = (posSettings.currency || 'LKR').toUpperCase();
  return posSettings.currency_position === 'before' ? `${currency} ${amount}` : `${amount} ${currency}`;
}

function esc(s) {
  return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function showToast(message, type = '') {
  toastEl.textContent = message;
  toastEl.className = `toast show ${type}`;
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => { toastEl.className = 'toast'; }, 3200);
}

// ── Header (account menu lives in js/navbar.js) ─────────────────────────
document.getElementById('back-btn').addEventListener('click', () => {
  window.location.href = 'dashboard.html';
});

// ── Mode tabs (Products / Rental / Dynamic) ─────────────────────────────
document.querySelectorAll('.mode-tab').forEach((btn) => {
  btn.addEventListener('click', () => {
    if (btn.classList.contains('active')) return;
    document.querySelectorAll('.mode-tab').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    currentMode = btn.dataset.mode;
    loadProducts(searchInput.value.trim());
  });
});

// ── Category chips ──────────────────────────────────────────────────────
async function loadCategories() {
  const res = await API.posCategories();
  if (res.status !== 200) return;
  categories = res.body.data || [];
  renderChips();
}

function renderChips() {
  const chips = [`<button class="chip ${currentCategoryId === '' ? 'active' : ''}" data-category="">${t('All')}</button>`]
    .concat(categories.map((c) => `<button class="chip ${String(currentCategoryId) === String(c.id) ? 'active' : ''}" data-category="${c.id}">${esc(c.name)}</button>`));
  chipRowEl.innerHTML = chips.join('');
  chipRowEl.querySelectorAll('.chip').forEach((btn) => {
    btn.addEventListener('click', () => {
      currentCategoryId = btn.dataset.category || '';
      renderChips();
      loadProducts(searchInput.value.trim());
    });
  });
  updateChipArrows();
}

// Lets mouse users scroll the category row sideways (vertical wheel + click-drag),
// since touch already scrolls it natively.
function enableHorizontalScroll(el) {
  el.addEventListener('wheel', (e) => {
    if (e.deltaY === 0) return;
    el.scrollLeft += e.deltaY;
    e.preventDefault();
  }, { passive: false });

  let isDown = false;
  let dragMoved = false;
  let startX = 0;
  let startScrollLeft = 0;
  let capturedPointerId = null;

  el.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'touch') return;
    isDown = true;
    dragMoved = false;
    startX = e.clientX;
    startScrollLeft = el.scrollLeft;
  });
  el.addEventListener('pointermove', (e) => {
    if (!isDown) return;
    const dx = e.clientX - startX;
    if (!dragMoved && Math.abs(dx) > 3) {
      // Only start capturing once real dragging begins — capturing on every
      // pointerdown (even plain clicks) redirects the click to `el` instead
      // of the chip button, breaking category selection.
      dragMoved = true;
      capturedPointerId = e.pointerId;
      el.setPointerCapture(capturedPointerId);
    }
    if (dragMoved) el.scrollLeft = startScrollLeft - dx;
  });
  const endDrag = (e) => {
    if (!isDown) return;
    isDown = false;
    if (capturedPointerId !== null) {
      el.releasePointerCapture(capturedPointerId);
      capturedPointerId = null;
    }
    if (dragMoved) {
      // Suppress the click that follows a drag so it doesn't switch category.
      const suppressClick = (ev) => { ev.stopPropagation(); ev.preventDefault(); el.removeEventListener('click', suppressClick, true); };
      el.addEventListener('click', suppressClick, true);
    }
  };
  el.addEventListener('pointerup', endDrag);
  el.addEventListener('pointercancel', endDrag);
}
enableHorizontalScroll(chipRowEl);

// Left/right arrow buttons for scrolling the category row.
const chipArrowLeftEl = document.getElementById('chip-arrow-left');
const chipArrowRightEl = document.getElementById('chip-arrow-right');
function updateChipArrows() {
  const maxScroll = chipRowEl.scrollWidth - chipRowEl.clientWidth;
  chipArrowLeftEl.disabled = chipRowEl.scrollLeft <= 1;
  chipArrowRightEl.disabled = chipRowEl.scrollLeft >= maxScroll - 1;
}
chipArrowLeftEl.addEventListener('click', () => {
  chipRowEl.scrollBy({ left: -160, behavior: 'smooth' });
});
chipArrowRightEl.addEventListener('click', () => {
  chipRowEl.scrollBy({ left: 160, behavior: 'smooth' });
});
chipRowEl.addEventListener('scroll', updateChipArrows);
window.addEventListener('resize', updateChipArrows);
const chipRowResizeObserver = new ResizeObserver(updateChipArrows);
chipRowResizeObserver.observe(chipRowEl);
updateChipArrows();

// ── Add Product modal (quick-add without leaving the register) ─────────
function openAddProductModal() {
  apNameInput.value = '';
  apSkuInput.value = '';
  apStockInput.value = '';
  apCostInput.value = '';
  apPriceInput.value = '';
  apErrorEl.classList.remove('show');
  addProductModal.classList.add('show');
  apNameInput.focus();
}
function closeAddProductModal() {
  addProductModal.classList.remove('show');
}
addProductBtn.addEventListener('click', openAddProductModal);
apSkuGenBtn.addEventListener('click', () => {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let s = 'PRD-';
  for (let i = 0; i < 8; i++) s += chars[Math.floor(Math.random() * chars.length)];
  apSkuInput.value = s;
});

async function saveAddProduct() {
  const name = apNameInput.value.trim();
  const price = parseFloat(apPriceInput.value);
  const cost = parseFloat(apCostInput.value);
  const stock = parseFloat(apStockInput.value);

  apErrorEl.classList.remove('show');
  if (!name) {
    apErrorEl.textContent = t('Product name is required.');
    apErrorEl.classList.add('show');
    return;
  }
  if (isNaN(price) || price < 0) {
    apErrorEl.textContent = t('Selling price must be 0 or more.');
    apErrorEl.classList.add('show');
    return;
  }

  const payload = {
    name,
    sku: apSkuInput.value.trim() || null,
    unit_price: price,
    cost_price: isNaN(cost) ? null : cost,
    stock_quantity: isNaN(stock) || stock < 0 ? 0 : stock,
  };

  apSaveBtn.disabled = true;
  try {
    const res = await API.createProduct(payload);
    if (res.status !== 200 && res.status !== 201) {
      const firstKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
      apErrorEl.textContent = t(firstKey ? res.body.errors[firstKey][0] : (res.body?.message || 'Could not save product.'));
      apErrorEl.classList.add('show');
      return;
    }
    closeAddProductModal();
    showToast(t('{name} added.', { name: res.body.data?.name || name }), 'success');
    loadProducts(searchInput.value.trim());
  } finally {
    apSaveBtn.disabled = false;
  }
}
apSaveBtn.addEventListener('click', saveAddProduct);

// ── Products ────────────────────────────────────────────────────────────
function unitPrice(p) {
  return p.discounted_sell_price !== null && p.discounted_sell_price !== undefined
    ? p.discounted_sell_price
    : p.unit_sell_price;
}

function priceLabel(p) {
  if (p.is_rental) return `${money(p.rental_daily_rate)}/${t('day')}`;
  if (p.is_dynamic_pricing) return p.dynamic_price_qty_linked ? t('Enter amount') : t('Set price');
  return money(unitPrice(p));
}

// Resolves a configured warranty duration string (e.g. "1 Year", "30 Days", "Lifetime")
// into a { type, date } pair — mirrors the web app's posResolveWarrantyFromDuration
// (Modules/Pos/resources/views/partials/pos-cart-layers-script.blade.php).
function resolveWarrantyFromDuration(duration) {
  const d = String(duration || '').trim();
  if (!d || d.toLowerCase() === 'lifetime') return { type: 'lifetime', date: null };
  const m = d.match(/^(\d+(?:\.\d+)?)\s*(day|days|week|weeks|month|months|year|years)$/i);
  if (m) {
    const n = parseFloat(m[1]);
    const unit = m[2].toLowerCase();
    const exp = new Date();
    if (unit.startsWith('day')) exp.setDate(exp.getDate() + Math.round(n));
    else if (unit.startsWith('week')) exp.setDate(exp.getDate() + Math.round(n * 7));
    else if (unit.startsWith('month')) exp.setMonth(exp.getMonth() + Math.round(n));
    else if (unit.startsWith('year')) exp.setFullYear(exp.getFullYear() + Math.round(n));
    return { type: 'date', date: exp.toISOString().slice(0, 10) };
  }
  return { type: 'lifetime', date: null };
}

function emptyProductsMessage() {
  if (currentMode === 'rental') return t('No rental products found.');
  if (currentMode === 'dynamic') return t('No dynamic-priced products found.');
  return t('No products found.');
}

function renderProducts() {
  if (!products.length) {
    grid.innerHTML = `<div class="empty-state">${emptyProductsMessage()}</div>`;
    return;
  }

  grid.innerHTML = products.map((p) => {
    const outOfStock = Number(p.stock_quantity) <= 0;
    const thumb = p.image_url
      ? `<img src="${p.image_url}" alt="">`
      : '<i class="fa-solid fa-box"></i>';
    const badge = p.is_rental
      ? `<span class="product-badge rental">${t('Rental')}</span>`
      : p.is_dynamic_pricing
        ? `<span class="product-badge dynamic">${t('Dynamic')}</span>`
        : '';

    const tags = [];
    if (p.has_warranty) {
      const resolved = resolveWarrantyFromDuration(p.warranty_duration);
      const durationLabel = p.warranty_duration || t('Lifetime');
      const tooltip = resolved.type === 'date'
        ? t('Valid until {date}', { date: resolved.date })
        : t('Lifetime warranty');
      tags.push(`<span class="tag-pill warranty" title="${esc(tooltip)}"><i class="fa-solid fa-shield-halved"></i> ${esc(durationLabel)}</span>`);
    }
    if (p.is_rental) {
      const rate = money(p.rental_daily_rate);
      const maxDays = p.rental_max_days ? t('max {n}d', { n: p.rental_max_days }) : '';
      tags.push(`<span class="tag-pill rental-info" title="${esc(t('Rental: {rate}/day', { rate }))}"><i class="fa-solid fa-calendar-days"></i> ${esc(rate)}/${t('day')}${maxDays ? ' · ' + esc(maxDays) : ''}</span>`);
    }
    const tagsRow = tags.length ? `<div class="product-tags">${tags.join('')}</div>` : '';

    return `
      <div class="product-card ${outOfStock ? 'out-of-stock' : ''}" data-id="${p.id}">
        ${badge}
        <div class="product-thumb">${thumb}</div>
        <div class="product-name">${p.name}</div>
        ${tagsRow}
        <div class="product-meta">
          <span class="product-price">${priceLabel(p)}</span>
          <span class="product-stock">${outOfStock ? t('Out of stock') : t('{n} left', { n: Math.floor(p.stock_quantity) })}</span>
        </div>
      </div>`;
  }).join('');

  grid.querySelectorAll('.product-card:not(.out-of-stock)').forEach((card) => {
    card.addEventListener('click', () => routeAddToCart(Number(card.dataset.id)));
  });
}

async function loadProducts(query = '') {
  grid.innerHTML = `<div class="loading-state">${t('Loading products…')}</div>`;
  const filter = currentMode === 'rental' ? 'rental' : currentMode === 'dynamic' ? 'dynamic' : null;
  const res = await API.products(query, { filter, categoryId: currentCategoryId || null });
  if (res.status !== 200) {
    grid.innerHTML = `<div class="empty-state">${t('Could not load products ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
    return;
  }
  products = res.body.data || [];
  renderProducts();
}

searchInput.addEventListener('input', () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => loadProducts(searchInput.value.trim()), 300);
});

// ── Add-to-cart routing (plain product vs. rental vs. dynamic pricing) ──
function routeAddToCart(productId) {
  const product = products.find((p) => p.id === productId);
  if (!product) return;
  if (product.is_rental) return void addRentalToCart(product);
  if (product.is_dynamic_pricing) return void addDynamicToCart(product);
  return addToCart(productId);
}

// ── Cart: plain products ─────────────────────────────────────────────────
const _beep = new Audio('sounds/beep.wav');
function playBeep() {
  _beep.currentTime = 0;
  _beep.play().catch(() => {});
}

function addToCart(productId) {
  const product = products.find((p) => p.id === productId);
  if (!product) return;

  playBeep();

  const cartKey = `p-${productId}`;
  const existing = cart.find((c) => c.cartKey === cartKey);
  if (existing) {
    if (existing.qty < product.stock_quantity) existing.qty += 1;
    else showToast(t('No more stock available for this item.'), 'error');
    renderCart();
    return;
  }

  let price = unitPrice(product);
  let customPrice = null;
  if (posSettings.choose_price) {
    const input = window.prompt(t('Enter unit price for {name}:', { name: product.name }), price.toFixed(2));
    if (input === null) return; // cashier cancelled — don't add the item
    const parsed = parseFloat(input);
    if (!isNaN(parsed) && parsed > 0) {
      price = parsed;
      customPrice = parsed;
    }
  }

  let hasWarranty = false;
  let warrantyType = null;
  let warrantyDate = null;
  if (product.has_warranty) {
    const resolved = resolveWarrantyFromDuration(product.warranty_duration);
    hasWarranty = true;
    warrantyType = resolved.type;
    warrantyDate = resolved.date;
  }

  cart.push({
    cartKey,
    product_id: product.id,
    name: product.name,
    price,
    customPrice,
    qty: 1,
    stock: product.stock_quantity,
    hasWarranty,
    warrantyType,
    warrantyDate,
    itemDiscountPercent: 0,
  });
  renderCart();
}

// ── Cart: rental products ────────────────────────────────────────────────
function requireCustomerForFlow(message) {
  if (selectedCustomer) return true;
  showToast(message, 'error');
  openCustomerPickerModal();
  return false;
}

async function addRentalToCart(product) {
  if (!requireCustomerForFlow(t('Select a customer before renting a product.'))) return;

  const result = await pickRentalDetails(product);
  if (!result) return;

  playBeep();

  const dailyRate = Number(product.rental_daily_rate) || 0;
  const cartKey = `rental-${product.id}-${result.returnDate}`;
  const existing = cart.find((c) => c.cartKey === cartKey);

  if (existing) {
    if (existing.qty < product.stock_quantity) existing.qty += 1;
    else showToast(t('No more stock available for this rental.'), 'error');
  } else {
    cart.push({
      cartKey,
      product_id: product.id,
      name: product.name,
      price: Math.round(dailyRate * result.days * 100) / 100,
      customPrice: null,
      qty: 1,
      stock: product.stock_quantity,
      isRental: true,
      rentalReturnDate: result.returnDate,
      rentalDays: result.days,
      itemDiscountPercent: 0,
    });
  }
  renderCart();
}

// ── Cart: dynamic-pricing products ───────────────────────────────────────
async function addDynamicToCart(product) {
  const linked = !!product.dynamic_price_qty_linked;
  const result = await pickDynamicPrice(product);
  if (!result) return;

  playBeep();

  cart.push({
    cartKey: `dyn-${product.id}-${Date.now()}`,
    product_id: product.id,
    name: product.name,
    price: linked ? 1 : result.amount,
    customPrice: null,
    qty: linked ? result.amount : 1,
    stock: product.stock_quantity,
    isDynamic: true,
    dynamicLinked: linked,
    customUnitPrice: linked ? null : result.amount,
    itemDiscountPercent: 0,
  });
  renderCart();
}

function changeQty(cartKey, delta) {
  const item = cart.find((c) => c.cartKey === cartKey);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) cart = cart.filter((c) => c.cartKey !== cartKey);
  else if (item.qty > item.stock) item.qty = item.stock;
  renderCart();
}

function removeFromCart(cartKey) {
  cart = cart.filter((c) => c.cartKey !== cartKey);
  renderCart();
}

document.getElementById('clear-cart').addEventListener('click', () => {
  cart = [];
  renderCart();
});

function itemDiscountPercent(c) {
  return Math.min(100, Math.max(0, Number(c.itemDiscountPercent) || 0));
}

function lineTotal(c) {
  return c.price * c.qty * (1 - itemDiscountPercent(c) / 100);
}

function renderCart() {
  if (!cart.length) {
    cartItemsEl.innerHTML = `<div class="cart-empty">${t('Cart is empty. Click a product to add it.')}</div>`;
  } else {
    cartItemsEl.innerHTML = cart.map((c) => {
      let sub = `${money(c.price)} × ${c.qty} = ${money(lineTotal(c))}`;
      if (c.isRental) sub += ` · ${t('Return')} ${c.rentalReturnDate} (${c.rentalDays}d)`;
      if (c.isDynamic) sub += ` · ${t('Dynamic')}`;
      if (itemDiscountPercent(c) > 0) sub += ` · ${t('{pct}% off', { pct: itemDiscountPercent(c) })}`;
      return `
      <div class="cart-item" data-key="${c.cartKey}">
        <div class="cart-item-info">
          <div class="cart-item-name">${c.name}</div>
          <div class="cart-item-price">${sub}</div>
        </div>
        <div class="qty-stepper">
          <button data-action="dec">−</button>
          <span>${c.qty}</span>
          <button data-action="inc">+</button>
        </div>
        <div class="cart-item-remove" data-action="remove"><i class="fa-solid fa-trash"></i></div>
      </div>`;
    }).join('');

    cartItemsEl.querySelectorAll('.cart-item').forEach((row) => {
      const key = row.dataset.key;
      row.querySelector('[data-action="inc"]').addEventListener('click', () => changeQty(key, 1));
      row.querySelector('[data-action="dec"]').addEventListener('click', () => changeQty(key, -1));
      row.querySelector('[data-action="remove"]').addEventListener('click', () => removeFromCart(key));
    });
  }

  const itemCount = cart.reduce((sum, c) => sum + c.qty, 0);
  const subtotal = cart.reduce((sum, c) => sum + lineTotal(c), 0);
  const total = subtotal - discountAmountFor(subtotal);
  sumItemsEl.textContent = itemCount;
  sumTotalEl.textContent = money(total);
  checkoutBtn.disabled = cart.length === 0;
}

// Order-level discount, entered either as a % of the subtotal or as a flat
// currency amount — discountType tracks which one discount-input's value means.
function discountAmountFor(subtotal) {
  if (!posSettings.discount_field_enabled) return 0;
  const raw = Math.max(0, Number(discountInput.value) || 0);
  if (discountType === 'flat') return Math.min(subtotal, raw);
  return subtotal * Math.min(100, raw) / 100;
}

discountTypeToggle.addEventListener('click', (e) => {
  const btn = e.target.closest('.discount-type-btn');
  if (!btn || btn.classList.contains('active')) return;
  discountType = btn.dataset.type;
  discountTypeToggle.querySelectorAll('.discount-type-btn').forEach((b) => b.classList.toggle('active', b === btn));
  if (discountType === 'percent') {
    discountInput.setAttribute('max', '100');
    discountInput.value = Math.min(100, Math.max(0, Number(discountInput.value) || 0));
  } else {
    discountInput.removeAttribute('max');
  }
  renderCart();
  refreshCheckoutSummary();
});

// ── Payment method ──────────────────────────────────────────────────────
document.querySelectorAll('.pm-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.pm-btn').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    paymentMethod = btn.dataset.method;
    tenderSection.style.display = paymentMethod === 'cash' ? '' : 'none';
    updateCheckoutTender();
  });
});

// ── Customer select ──────────────────────────────────────────────────────
function refreshCheckoutCustomerBox() {
  if (selectedCustomer) {
    checkoutCustomerChipLabelEl.textContent = selectedCustomer.label;
    checkoutCustomerChipEl.hidden = false;
    checkoutCustomerEmptyEl.hidden = true;
    cartCustomerChipLabelEl.textContent = selectedCustomer.label;
    cartCustomerChipEl.hidden = false;
    cartCustomerEmptyEl.hidden = true;
  } else {
    checkoutCustomerChipEl.hidden = true;
    checkoutCustomerEmptyEl.hidden = false;
    cartCustomerChipEl.hidden = true;
    cartCustomerEmptyEl.hidden = false;
  }
}

function selectCustomer(customer) {
  selectedCustomer = {
    id: customer.id,
    label: customer.name + (customer.phone ? ` · ${customer.phone}` : ''),
  };
  customerChipLabelEl.textContent = selectedCustomer.label;
  customerChipEl.hidden = false;
  customerSearchWrapEl.hidden = true;
  customerDropdownEl.hidden = true;
  customerDropdownEl.innerHTML = '';
  customerQuickaddEl.hidden = true;
  customerSearchInputEl.value = '';
  refreshCheckoutCustomerBox();
  closeCustomerPickerModal();
}

function clearCustomerSelection() {
  selectedCustomer = null;
  customerChipEl.hidden = true;
  customerChipLabelEl.textContent = '';
  customerSearchWrapEl.hidden = false;
  customerSearchInputEl.value = '';
  customerDropdownEl.hidden = true;
  customerDropdownEl.innerHTML = '';
  customerQuickaddEl.hidden = true;
  refreshCheckoutCustomerBox();
}

// ── Customer picker modal ───────────────────────────────────────────────
function openCustomerPickerModal() {
  customerPickerModal.classList.add('show');
  setTimeout(() => customerSearchInputEl?.focus(), 50);
}

function closeCustomerPickerModal() {
  customerPickerModal.classList.remove('show');
}

checkoutCustomerSelectBtn.addEventListener('click', openCustomerPickerModal);
checkoutCustomerChangeBtn.addEventListener('click', openCustomerPickerModal);
checkoutCustomerRemoveBtn.addEventListener('click', clearCustomerSelection);
cartCustomerSelectBtn.addEventListener('click', openCustomerPickerModal);
cartCustomerChangeBtn.addEventListener('click', openCustomerPickerModal);
cartCustomerRemoveBtn.addEventListener('click', clearCustomerSelection);

function openCustomerQuickAdd(prefillName) {
  customerDropdownEl.hidden = true;
  qaNameInput.value = prefillName || '';
  qaPhoneInput.value = '';
  customerQuickaddEl.hidden = false;
  qaNameInput.focus();
}

function renderCustomerDropdown(items, query) {
  lastCustomerResults = items;
  customerFocusedIndex = -1;

  const rows = items.map((c) => `
    <div class="customer-option" data-id="${c.id}">
      <div class="customer-option-name">${esc(c.name)}</div>
      ${(c.phone || c.email) ? `<div class="customer-option-sub">${esc(c.phone || '')}${c.phone && c.email ? ' · ' : ''}${esc(c.email || '')}</div>` : ''}
    </div>`).join('');
  const addRow = `<div class="customer-option add" data-add="1"><i class="fa-solid fa-plus"></i> ${t('Add customer')}${query ? ': ' + esc(query) : ''}</div>`;

  customerDropdownEl.innerHTML = rows + addRow;
  customerDropdownEl.hidden = false;

  customerDropdownEl.querySelectorAll('.customer-option[data-id]').forEach((el) => {
    el.addEventListener('mousedown', (e) => {
      e.preventDefault();
      const c = items.find((x) => String(x.id) === el.dataset.id);
      if (c) selectCustomer(c);
    });
  });
  customerDropdownEl.querySelector('[data-add]')?.addEventListener('mousedown', (e) => {
    e.preventDefault();
    openCustomerQuickAdd(query);
  });
}

async function fetchCustomers(q) {
  const res = await API.customers({ q });
  if (res.status !== 200) return;
  renderCustomerDropdown(res.body.data || [], q);
}

customerSearchInputEl.addEventListener('input', () => {
  clearTimeout(customerSearchDebounce);
  const q = customerSearchInputEl.value.trim();
  customerSearchDebounce = setTimeout(() => fetchCustomers(q), 250);
});

customerSearchInputEl.addEventListener('focus', () => fetchCustomers(customerSearchInputEl.value.trim()));

customerSearchInputEl.addEventListener('keydown', (e) => {
  if (customerDropdownEl.hidden) return;
  const opts = customerDropdownEl.querySelectorAll('.customer-option');
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    customerFocusedIndex = Math.min(customerFocusedIndex + 1, opts.length - 1);
    opts.forEach((o, i) => o.classList.toggle('focused', i === customerFocusedIndex));
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    customerFocusedIndex = Math.max(customerFocusedIndex - 1, 0);
    opts.forEach((o, i) => o.classList.toggle('focused', i === customerFocusedIndex));
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const opt = opts[customerFocusedIndex];
    if (!opt) return;
    if (opt.dataset.add) openCustomerQuickAdd(customerSearchInputEl.value.trim());
    else {
      const c = lastCustomerResults.find((x) => String(x.id) === opt.dataset.id);
      if (c) selectCustomer(c);
    }
  } else if (e.key === 'Escape') {
    customerDropdownEl.hidden = true;
  }
});

customerClearBtn.addEventListener('click', clearCustomerSelection);

qaCancelBtn.addEventListener('click', () => { customerQuickaddEl.hidden = true; });

qaSaveBtn.addEventListener('click', async () => {
  const name = qaNameInput.value.trim();
  if (!name) { qaNameInput.focus(); return; }
  const phone = qaPhoneInput.value.trim();

  qaSaveBtn.disabled = true;
  try {
    const res = await API.createCustomer({ name, phone: phone || null });
    if (res.status !== 201) {
      showToast(t(res.body?.message || 'Could not save customer.'), 'error');
      return;
    }
    selectCustomer(res.body.data);
  } finally {
    qaSaveBtn.disabled = false;
  }
});

document.addEventListener('click', (e) => {
  if (customerDropdownEl.hidden) return;
  if (!customerSearchWrapEl.contains(e.target)) customerDropdownEl.hidden = true;
});

// ── Rental details picker (modal) ────────────────────────────────────────
let pendingRentalResolve = null;
let currentRentalProduct = null;

function rentalDaysBetween(dateStr) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(dateStr + 'T00:00:00');
  return Math.max(1, Math.round((target - today) / 86400000));
}

function updateRentalHint() {
  if (!rentalDateInput.value) {
    rentalDaysLabel.textContent = '—';
    rentalTotalLabel.textContent = money(0);
    return;
  }
  const days = rentalDaysBetween(rentalDateInput.value);
  const rate = currentRentalProduct ? Number(currentRentalProduct.rental_daily_rate) || 0 : 0;
  rentalDaysLabel.textContent = days === 1 ? t('1 day') : t('{n} days', { n: days });
  rentalTotalLabel.textContent = money(rate * days);
  rentalErrorEl.classList.remove('show');
}

rentalDateInput.addEventListener('input', updateRentalHint);

function closeRentalModal() {
  rentalModal.classList.remove('show');
  if (pendingRentalResolve) {
    const resolve = pendingRentalResolve;
    pendingRentalResolve = null;
    resolve(null);
  }
}

rentalConfirmBtn.addEventListener('click', () => {
  if (!currentRentalProduct) return;
  const maxDays = Number(currentRentalProduct.rental_max_days) || 1;
  if (!rentalDateInput.value) {
    rentalErrorEl.textContent = t('Choose a return date.');
    rentalErrorEl.classList.add('show');
    return;
  }
  const days = rentalDaysBetween(rentalDateInput.value);
  if (days > maxDays) {
    rentalErrorEl.textContent = t('Return date exceeds the maximum rental period ({n} days).', { n: maxDays });
    rentalErrorEl.classList.add('show');
    return;
  }
  const resolve = pendingRentalResolve;
  pendingRentalResolve = null;
  rentalModal.classList.remove('show');
  resolve({ returnDate: rentalDateInput.value, days });
});

function pickRentalDetails(product) {
  return new Promise((resolve) => {
    currentRentalProduct = product;
    const dailyRate = Number(product.rental_daily_rate) || 0;
    const maxDays = Number(product.rental_max_days) || 1;
    rentalSubtitleEl.textContent = `${product.name} — ${money(dailyRate)}/${t('day')} · ${t('max {n} days', { n: maxDays })}`;

    const today = new Date();
    const minStr = today.toISOString().slice(0, 10);
    const maxDate = new Date(today);
    maxDate.setDate(maxDate.getDate() + maxDays);
    rentalDateInput.min = minStr;
    rentalDateInput.max = maxDate.toISOString().slice(0, 10);
    rentalDateInput.value = minStr;
    rentalErrorEl.classList.remove('show');
    updateRentalHint();

    pendingRentalResolve = resolve;
    rentalModal.classList.add('show');
  });
}

// ── Dynamic price picker (modal) ─────────────────────────────────────────
let pendingDynamicResolve = null;
let currentDynamicProduct = null;

function updateDynamicHint() {
  dynamicErrorEl.classList.remove('show');
  const amount = parseFloat(dynamicAmountInput.value);
  if (!Number.isFinite(amount) || amount <= 0) {
    dynamicHintEl.textContent = currentDynamicProduct?.dynamic_price_qty_linked
      ? t('Enter the amount sold — deducts that many units from stock.')
      : t('Enter the price to charge for 1 unit.');
    return;
  }
  if (currentDynamicProduct?.dynamic_price_qty_linked) {
    const stock = Number(currentDynamicProduct.stock_quantity) || 0;
    dynamicHintEl.textContent = t('{amount} units deducted · balance after: {balance}', {
      amount: amount.toFixed(2),
      balance: Math.max(0, stock - amount).toFixed(2),
    });
  } else {
    dynamicHintEl.textContent = t('Sell 1 × {price}', { price: money(amount) });
  }
}

dynamicAmountInput.addEventListener('input', updateDynamicHint);

function closeDynamicModal() {
  dynamicModal.classList.remove('show');
  if (pendingDynamicResolve) {
    const resolve = pendingDynamicResolve;
    pendingDynamicResolve = null;
    resolve(null);
  }
}

dynamicConfirmBtn.addEventListener('click', () => {
  const amount = parseFloat(dynamicAmountInput.value);
  if (!Number.isFinite(amount) || amount <= 0) {
    dynamicErrorEl.textContent = t('Enter an amount greater than zero.');
    dynamicErrorEl.classList.add('show');
    return;
  }
  if (currentDynamicProduct?.dynamic_price_qty_linked && amount > (Number(currentDynamicProduct.stock_quantity) || 0)) {
    dynamicErrorEl.textContent = t('Amount exceeds available stock.');
    dynamicErrorEl.classList.add('show');
    return;
  }
  const resolve = pendingDynamicResolve;
  pendingDynamicResolve = null;
  dynamicModal.classList.remove('show');
  resolve({ amount });
});

function pickDynamicPrice(product) {
  return new Promise((resolve) => {
    currentDynamicProduct = product;
    dynamicSubtitleEl.textContent = product.name || '';
    dynamicLabelEl.textContent = product.dynamic_price_qty_linked ? t('Amount') : t('Price');
    dynamicAmountInput.value = '';
    dynamicErrorEl.classList.remove('show');
    updateDynamicHint();

    pendingDynamicResolve = resolve;
    dynamicModal.classList.add('show');
    setTimeout(() => dynamicAmountInput.focus(), 50);
  });
}

// ── Shared modal chrome (close button / backdrop click / Escape) ───────────
document.querySelectorAll('.modal-backdrop [data-close]').forEach((btn) => {
  btn.addEventListener('click', () => {
    if (btn.dataset.close === 'rental-modal') closeRentalModal();
    if (btn.dataset.close === 'dynamic-modal') closeDynamicModal();
    if (btn.dataset.close === 'receipt-modal') closeReceiptModal();
    if (btn.dataset.close === 'add-product-modal') closeAddProductModal();
    if (btn.dataset.close === 'customer-picker-modal') closeCustomerPickerModal();
    if (btn.dataset.close === 'checkout-modal') closeCheckoutModal();
    if (btn.dataset.close === 'refund-modal') closeRefundModal();
    if (btn.dataset.close === 'eod-modal') closeEodModal();
    if (btn.dataset.close === 'cash-withdraw-modal') closeCashWithdrawModal();
    if (btn.dataset.close === 'password-confirm-modal') closePasswordConfirmModal();
  });
});
[rentalModal, dynamicModal, receiptModal, addProductModal, customerPickerModal, checkoutModal, refundModal, eodModal, cashWithdrawModal, passwordConfirmModal].forEach((bg) => {
  bg.addEventListener('click', (e) => {
    if (e.target !== bg) return;
    if (bg === rentalModal) closeRentalModal();
    else if (bg === dynamicModal) closeDynamicModal();
    else if (bg === addProductModal) closeAddProductModal();
    else if (bg === customerPickerModal) closeCustomerPickerModal();
    else if (bg === checkoutModal) closeCheckoutModal();
    else if (bg === refundModal) closeRefundModal();
    else if (bg === eodModal) closeEodModal();
    else if (bg === cashWithdrawModal) closeCashWithdrawModal();
    else if (bg === passwordConfirmModal) closePasswordConfirmModal();
    else closeReceiptModal();
  });
});
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  const giftCheck = document.getElementById('gift-check-modal');
  const couponCheck = document.getElementById('coupon-check-modal');
  if (couponCheck.classList.contains('show')) couponCheck.classList.remove('show');
  else if (giftCheck.classList.contains('show')) giftCheck.classList.remove('show');
  else if (rentalModal.classList.contains('show')) closeRentalModal();
  else if (dynamicModal.classList.contains('show')) closeDynamicModal();
  else if (customerPickerModal.classList.contains('show')) closeCustomerPickerModal();
  else if (checkoutModal.classList.contains('show')) closeCheckoutModal();
  else if (refundModal.classList.contains('show')) closeRefundModal();
  else if (receiptModal.classList.contains('show')) closeReceiptModal();
  else if (passwordConfirmModal.classList.contains('show')) closePasswordConfirmModal();
  else if (cashWithdrawModal.classList.contains('show')) closeCashWithdrawModal();
  else if (eodModal.classList.contains('show')) closeEodModal();
});

// ── Return & Refund modal ───────────────────────────────────────────────
// Opened from the cart header's Return button (or F9). Search a past sale,
// pick returnable items and a refund method, then post to the same
// /sales/{id}/return endpoint the full desktop app uses.
const _rfnd = {
  q: '', list: [], activeSaleId: null, activeSale: null,
  accountsLoaded: false, reasonsLoaded: false,
};
let _rfndSearchTimer;

function openRefundModal() {
  refundModal.classList.add('show');
  _rfnd.q = '';
  _rfnd.activeSaleId = null;
  _rfnd.activeSale = null;
  rfndSearchInput.value = '';
  rfndReasonSelect.value = '';
  const cashOpt = document.querySelector('input[name="rfnd-method"][value="cash"]');
  if (cashOpt) cashOpt.checked = true;
  rfndAccountRow.hidden = true;
  _rfndShowDetail(false);
  _rfndLoadSales();
  _rfndLoadAccounts();
  _rfndLoadReasons();
  setTimeout(() => rfndSearchInput.focus(), 80);
}

function closeRefundModal() {
  refundModal.classList.remove('show');
  _rfnd.activeSaleId = null;
  _rfnd.activeSale = null;
}

async function _rfndLoadAccounts() {
  if (_rfnd.accountsLoaded) return;
  const res = await API.accounts();
  if (res.status !== 200) return;
  const list = res.body?.data || res.body || [];
  rfndAccountSelect.innerHTML = list.map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
  _rfnd.accountsLoaded = true;
}

async function _rfndLoadReasons() {
  if (_rfnd.reasonsLoaded) return;
  const res = await API.returnReasons();
  if (res.status !== 200) return;
  const list = res.body?.data || [];
  rfndReasonSelect.innerHTML = `<option value="">${t('— Select a reason —')}</option>` +
    list.map((r) => `<option value="${esc(r.key)}">${esc(t(r.label))}</option>`).join('');
  _rfnd.reasonsLoaded = true;
}

async function _rfndLoadSales(replace = true) {
  if (replace) rfndSaleListEl.innerHTML = '<div class="rfnd-placeholder"><i class="fa-solid fa-spinner fa-spin"></i></div>';
  const res = await API.sales({ q: _rfnd.q, limit: 10 });
  if (res.status !== 200) {
    rfndSaleListEl.innerHTML = '<div class="rfnd-placeholder"><i class="fa-solid fa-triangle-exclamation"></i></div>';
    return;
  }
  const items = res.body?.data || res.body || [];
  _rfnd.list = Array.isArray(items) ? items : [];
  _rfndRenderList();
}

function _rfndRenderList() {
  if (!_rfnd.list.length) {
    rfndSaleListEl.innerHTML = `<div class="rfnd-placeholder" style="font-size:12px;padding:24px 10px">${t('No sales found')}</div>`;
    return;
  }
  rfndSaleListEl.innerHTML = _rfnd.list.map((s) => {
    const date = s.sold_at ? s.sold_at.substring(0, 10) : '';
    const num = s.sale_number || `#${s.id}`;
    const total = parseFloat(s.total || 0);
    const active = s.id === _rfnd.activeSaleId ? ' active' : '';
    return `<div class="rfnd-sale-item${active}" data-id="${s.id}">
      <div class="rfnd-si-num">${esc(num)} <span class="rfnd-si-total">${money(total)}</span></div>
      <div class="rfnd-si-meta">${esc(date)} &bull; ${esc(s.customer_name || t('Walk-in'))}</div>
    </div>`;
  }).join('');
  rfndSaleListEl.querySelectorAll('.rfnd-sale-item').forEach((row) => {
    row.addEventListener('click', () => _rfndSelectSale(parseInt(row.dataset.id, 10)));
  });
}

function _rfndShowDetail(show) {
  rfndDetailEmptyEl.style.display = show ? 'none' : 'flex';
  rfndDetailViewEl.classList.toggle('show', show);
  if (!show) {
    rfndItemsListEl.innerHTML = '';
    rfndSaleSummaryEl.innerHTML = '';
    rfndTotalDisplayEl.textContent = '0.00';
    rfndAlertEl.classList.remove('show');
    rfndSelectAllInput.checked = false;
  }
}

async function _rfndSelectSale(id) {
  _rfnd.activeSaleId = id;
  _rfndRenderList();
  _rfndShowDetail(false);
  rfndDetailEmptyEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:28px;opacity:.4"></i>';
  rfndDetailEmptyEl.style.display = 'flex';

  const res = await API.sale(id);
  if (res.status !== 200) { showToast(t('Failed to load sale'), 'error'); return; }
  const sale = res.body?.data || res.body;
  _rfnd.activeSale = sale;
  _rfndRenderDetail(sale);
  _rfndShowDetail(true);
}

function _rfndRenderDetail(sale) {
  const num = sale.sale_number || `#${sale.id}`;
  const date = (sale.sold_at || '').substring(0, 10);

  rfndSaleSummaryEl.innerHTML = `
    <div><div class="rfnd-sum-label">${t('Sale')}</div><div class="rfnd-sum-val">${esc(num)}</div></div>
    <div><div class="rfnd-sum-label">${t('Date')}</div><div class="rfnd-sum-val">${esc(date)}</div></div>
    <div><div class="rfnd-sum-label">${t('Total')}</div><div class="rfnd-sum-val amount">${money(parseFloat(sale.total || 0))}</div></div>
    <div><div class="rfnd-sum-label">${t('Customer')}</div><div class="rfnd-sum-val">${esc(sale.customer_name || t('Walk-in'))}</div></div>
    <div><div class="rfnd-sum-label">${t('Payment')}</div><div class="rfnd-sum-val">${esc(sale.payment_method_label || sale.payment_method || '—')}</div></div>
    <div><div class="rfnd-sum-label">${t('Status')}</div><div class="rfnd-sum-val">${esc(sale.status || '—')}</div></div>
  `;

  const items = sale.items || [];
  if (!items.length) {
    rfndItemsListEl.innerHTML = `<div style="color:var(--muted);font-size:12px;padding:10px 0">${t('No line items found')}</div>`;
    _rfndCalcTotal();
    return;
  }

  rfndItemsListEl.innerHTML = items.map((it) => {
    const returnable = (it.quantity || 0) - (it.returned_quantity || 0);
    const exhausted = returnable <= 0 ? ' exhausted' : '';
    const price = money(parseFloat(it.unit_sell_price || 0));
    return `<div class="rfnd-item-row${exhausted}" data-id="${it.id}" data-max="${returnable}" data-price="${it.unit_sell_price || 0}">
      <input type="checkbox" class="rfnd-item-check" ${returnable <= 0 ? 'disabled' : ''}>
      <div style="flex:1;min-width:0">
        <div class="rfnd-item-name">${esc(it.product_name || t('Item'))}</div>
        <div class="rfnd-item-meta">${t('Qty')}: ${fmtQty(it.quantity)}  ${t('Returned')}: ${fmtQty(it.returned_quantity || 0)}  ${t('Available')}: ${fmtQty(returnable > 0 ? returnable : 0)}</div>
      </div>
      <div class="rfnd-item-qty-wrap">
        <span class="rfnd-item-qty-label">${t('Qty')}</span>
        <input type="number" class="rfnd-item-qty" value="${returnable > 0 ? 1 : 0}" min="1" max="${returnable}" ${returnable <= 0 ? 'disabled' : ''}>
      </div>
      <div class="rfnd-item-price">${price}</div>
    </div>`;
  }).join('');

  rfndItemsListEl.querySelectorAll('.rfnd-item-row').forEach((row) => {
    const cb = row.querySelector('.rfnd-item-check');
    const qty = row.querySelector('.rfnd-item-qty');
    cb.addEventListener('change', () => { row.classList.toggle('selected', cb.checked); _rfndCalcTotal(); });
    qty.addEventListener('input', () => { if (cb.checked) _rfndCalcTotal(); });
    qty.addEventListener('change', () => {
      const max = parseInt(row.dataset.max, 10);
      const v = parseInt(qty.value, 10) || 1;
      qty.value = Math.min(max, Math.max(1, v));
      if (cb.checked) _rfndCalcTotal();
    });
  });

  _rfndCalcTotal();
}

function _rfndCalcTotal() {
  let total = 0;
  rfndItemsListEl.querySelectorAll('.rfnd-item-row').forEach((row) => {
    const cb = row.querySelector('.rfnd-item-check');
    const qty = row.querySelector('.rfnd-item-qty');
    if (cb && cb.checked) {
      const price = parseFloat(row.dataset.price || 0);
      const q = parseInt(qty?.value, 10) || 1;
      total += price * q;
    }
  });
  rfndTotalDisplayEl.textContent = money(total);
  rfndSubmitBtn.disabled = total <= 0;
}

function _rfndGetSelectedItems() {
  const result = [];
  rfndItemsListEl.querySelectorAll('.rfnd-item-row').forEach((row) => {
    const cb = row.querySelector('.rfnd-item-check');
    const qty = row.querySelector('.rfnd-item-qty');
    if (cb && cb.checked) {
      result.push({ sale_item_id: parseInt(row.dataset.id, 10), quantity: parseInt(qty?.value, 10) || 1 });
    }
  });
  return result;
}

async function _rfndSubmit() {
  const items = _rfndGetSelectedItems();
  if (!items.length) { _rfndShowAlert(t('Select at least one item to return.')); return; }

  const method = document.querySelector('input[name="rfnd-method"]:checked')?.value || 'cash';
  const creditAccId = method === 'credit' ? (parseInt(rfndAccountSelect.value, 10) || null) : null;
  const reason = rfndReasonSelect.value.trim();

  const body = { items, refund_method: method, refund_reason: reason || null };
  if (creditAccId) body.credit_account_id = creditAccId;

  rfndSubmitBtn.disabled = true;
  rfndSubmitBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Processing…')}`;

  const res = await API.processReturn(_rfnd.activeSaleId, body);

  rfndSubmitBtn.innerHTML = `<i class="fa-solid fa-rotate-left"></i> ${t('Process Return')}`;

  if (res.status === 200 || res.status === 201) {
    showToast(t('Return processed successfully'), 'success');
    closeRefundModal();
  } else {
    const msg = res.body?.message || res.body?.error || t('Return failed');
    _rfndShowAlert(msg);
    _rfndCalcTotal();
  }
}

function _rfndShowAlert(msg) {
  rfndAlertEl.textContent = msg;
  rfndAlertEl.classList.add('show');
  setTimeout(() => { if (rfndAlertEl.textContent === msg) rfndAlertEl.classList.remove('show'); }, 4000);
}

returnRefundBtn.addEventListener('click', openRefundModal);
rfndSubmitBtn.addEventListener('click', _rfndSubmit);

rfndSelectAllInput.addEventListener('change', (e) => {
  rfndItemsListEl.querySelectorAll('.rfnd-item-row:not(.exhausted) .rfnd-item-check').forEach((cb) => {
    cb.checked = e.target.checked;
    cb.closest('.rfnd-item-row').classList.toggle('selected', e.target.checked);
  });
  _rfndCalcTotal();
});

document.querySelectorAll('input[name="rfnd-method"]').forEach((r) => {
  r.addEventListener('change', () => {
    rfndAccountRow.hidden = !(r.value === 'credit' && r.checked);
  });
});

rfndSearchInput.addEventListener('input', (e) => {
  clearTimeout(_rfndSearchTimer);
  _rfnd.q = e.target.value.trim();
  _rfndSearchTimer = setTimeout(() => _rfndLoadSales(), 350);
});

// ── Sale-completed receipt/invoice preview + print/download ─────────────
function fmtQty(n) {
  return (Number(n) || 0).toFixed(3).replace(/\.?0+$/, '');
}

function warrantyLabel(item) {
  if (!item.warranty_type) return null;
  if (item.warranty_type === 'lifetime') return t('Lifetime warranty');
  return item.warranty_expires_at ? t('Warranty until {date}', { date: item.warranty_expires_at }) : t('Warranty');
}

function rentalLabel(item) {
  if (!item.rental_return_date) return null;
  let s = t('Return {date}', { date: item.rental_return_date }) + ' · ' + t('Daily {rate}', { rate: money(item.rental_daily_rate) });
  if (item.rental_late_fee_multiplier > 0) s += ' · ' + t('Late {mult}× rate/day', { mult: item.rental_late_fee_multiplier });
  return s;
}

// The API returns the per-unit discount amount and the already-discounted
// unit price — back out the percentage for display (receipt/invoice only;
// the checkout modal already knows the percentage the cashier typed).
function itemDiscountPctFromSale(item) {
  const perUnitDiscount = Number(item.discount_amount) || 0;
  if (perUnitDiscount <= 0.001) return 0;
  const original = Number(item.unit_sell_price) + perUnitDiscount;
  return original > 0 ? (perUnitDiscount / original) * 100 : 0;
}

function fmtPct(pct) {
  return pct % 1 === 0 ? String(pct) : pct.toFixed(1);
}

function buildReceiptData(sale) {
  const soldAtDate = sale.sold_at ? new Date(sale.sold_at) : null;
  return {
    saleNumber: sale.sale_number,
    soldAt: soldAtDate ? soldAtDate.toLocaleString() : '',
    soldAtDate: soldAtDate ? soldAtDate.toLocaleDateString() : '',
    paymentLabel: sale.payment_method_label || sale.payment_method,
    customerName: sale.customer_name || '',
    cashierName: sale.cashier?.name || '',
    notes: sale.notes || '',
    subtotal: sale.subtotal,
    discountPercent: sale.discount_percent,
    discountAmount: sale.discount_amount,
    couponDiscount: Number(sale.coupon_discount) || 0,
    couponCode: sale.coupon?.code || '',
    total: sale.total,
    amountPaid: sale.amount_paid,
    amountTendered: sale.amount_tendered,
    changeAmount: sale.change_amount,
    giftCardAmount: Number(sale.gift_card_amount) || 0,
    giftCardCode: sale.gift_card?.code || '',
    giftCardBalance: sale.gift_card ? Number(sale.gift_card.balance) : null,
    items: (sale.items || []).map((it) => ({
      name: it.product_name,
      sku: it.sku,
      qty: it.quantity,
      unit: it.unit_sell_price,
      line: it.line_total,
      discountPct: itemDiscountPctFromSale(it),
      warranty: warrantyLabel(it),
      rental: rentalLabel(it),
    })),
  };
}

function renderBillHtml(data) {
  const showName = posSettings.show_business_name !== false;
  const showAddr = !!posSettings.show_business_address;
  const businessName = posSettings.business_name || '';
  const addr = posSettings.receipt_address_line || '';
  const header = posSettings.receipt_header || '';
  const footer = posSettings.receipt_footer || t('Thank you for your purchase!');

  const rows = data.items.map((it) => {
    let row = `<tr>
      <td><div class="rd-item-name">${esc(it.name)}</div>${it.sku ? `<div class="rd-item-sub">${esc(it.sku)}</div>` : ''}</td>
      <td class="rd-center-col">${esc(fmtQty(it.qty))}</td>
      <td class="rd-right">${esc(money(it.unit))}</td>
      <td class="rd-center-col${it.discountPct > 0.05 ? ' rd-discount' : ''}">${it.discountPct > 0.05 ? esc(fmtPct(it.discountPct)) + '%' : '—'}</td>
      <td class="rd-right"><strong>${esc(money(it.line))}</strong></td>
    </tr>`;
    if (it.warranty) row += `<tr><td colspan="5" class="rd-note rd-warranty">${esc(it.warranty)}</td></tr>`;
    if (it.rental) row += `<tr><td colspan="5" class="rd-note rd-rental">${esc(it.rental)}</td></tr>`;
    return row;
  }).join('');

  let totals = '';
  if (data.discountAmount > 0.001 || data.couponDiscount > 0.001) {
    totals += `<div class="rd-row"><span>${t('Subtotal')}</span><span>${esc(money(data.subtotal))}</span></div>`;
  }
  if (data.discountAmount > 0.001) {
    const pctLabel = data.discountPercent ? ` (${data.discountPercent}%)` : '';
    totals += `<div class="rd-row"><span>${t('Discount')}${pctLabel}</span><span>&minus;${esc(money(data.discountAmount))}</span></div>`;
  }
  if (data.couponDiscount > 0.001) {
    totals += `<div class="rd-row"><span>${t('Coupon')}${data.couponCode ? ` ${esc(data.couponCode)}` : ''}</span><span>&minus;${esc(money(data.couponDiscount))}</span></div>`;
  }
  totals += `<div class="rd-row rd-total"><span>${t('Total')}</span><span>${esc(money(data.total))}</span></div>`;
  if (data.giftCardAmount > 0.001) {
    totals += `<div class="rd-row"><span>${t('Gift card')}${data.giftCardCode ? ` ${esc(data.giftCardCode)}` : ''}</span><span>&minus;${esc(money(data.giftCardAmount))}</span></div>`;
    if (data.giftCardBalance != null) totals += `<div class="rd-row"><span>${t('Gift card balance')}</span><span>${esc(money(data.giftCardBalance))}</span></div>`;
  }
  if (data.amountTendered != null) {
    totals += `<div class="rd-row"><span>${t('Cash Received')}</span><span>${esc(money(data.amountTendered))}</span></div>`;
    totals += `<div class="rd-row"><span>${t('Change')}</span><span>${esc(money(data.changeAmount || 0))}</span></div>`;
  }

  return `
    <div class="rd-center">
      ${showName ? `<div class="rd-business">${esc(businessName)}</div>` : ''}
      ${showAddr && addr ? `<div class="rd-meta">${esc(addr)}</div>` : ''}
      ${header ? `<div class="rd-meta" style="font-style:italic">${esc(header)}</div>` : ''}
    </div>
    <hr>
    <div class="rd-row"><span>${t('Receipt #')}</span><strong>${esc(data.saleNumber)}</strong></div>
    <div class="rd-row"><span>${t('Date & Time')}</span><strong>${esc(data.soldAt)}</strong></div>
    <div class="rd-row"><span>${t('Payment')}</span><strong>${esc(data.paymentLabel)}</strong></div>
    ${data.customerName ? `<div class="rd-row"><span>${t('Customer')}</span><strong>${esc(data.customerName)}</strong></div>` : ''}
    ${data.cashierName ? `<div class="rd-row"><span>${t('Cashier')}</span><strong>${esc(data.cashierName)}</strong></div>` : ''}
    <hr>
    <table>
      <thead><tr><th>${t('Item')}</th><th class="rd-center-col">${t('Qty')}</th><th class="rd-right">${t('Price')}</th><th class="rd-center-col">${t('Disc')}</th><th class="rd-right">${t('Amount')}</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <hr>
    ${totals}
    ${data.notes ? `<hr><div class="rd-note">${t('Notes')}: ${esc(data.notes)}</div>` : ''}
    <hr>
    <div class="rd-footer">${esc(footer)}</div>
  `;
}

function receiptDocStyles() {
  const widthMm = posSettings.receipt_paper_width === '58' ? 58 : 80;
  return `
    @page { size: ${widthMm}mm auto; margin: 4mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.5; color:#000; background:#fff; margin:0; padding:0; width:${widthMm}mm; }
    .rd-center { text-align:center; }
    .rd-business { font-weight:bold; font-size:13px; }
    .rd-meta { font-size:10px; margin:1px 0; }
    hr { border:none; border-top:1px dashed #000; margin:8px 0; }
    table { width:100%; border-collapse:collapse; font-size:10px; margin:6px 0; }
    th { text-align:left; font-size:9px; padding:2px 2px 4px; border-bottom:1px solid #000; }
    td { padding:3px 2px; vertical-align:top; }
    .rd-item-name { font-weight:bold; font-size:11px; }
    .rd-item-sub { font-size:9px; color:#555; margin-top:1px; }
    .rd-right { text-align:right; }
    .rd-center-col { text-align:center; }
    .rd-row { display:flex; justify-content:space-between; margin:2px 0; font-size:11px; }
    .rd-total { font-weight:bold; font-size:13px; border-top:1px solid #000; padding-top:4px; margin-top:4px; }
    .rd-footer { text-align:center; font-size:10px; margin-top:6px; }
    .rd-note { font-size:9px; padding:1px 0 4px; }
    .rd-warranty { color:#0369a1; }
    .rd-rental { color:#0f766e; }
    .rd-discount { color:#b45309; }
  `;
}

function buildFullReceiptDocument(data) {
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${esc(data.saleNumber || '')}</title><style>${receiptDocStyles()}</style></head><body>${renderBillHtml(data)}</body></html>`;
}

// Builds the "Invoice" mode document using the SAME 5 real templates (Classic /
// Bold Banner / Minimal / Compact / Executive) and paper/margin/accent-color
// settings as the Settings → Invoice Setup wizard (window.InvoiceTemplates,
// exported from js/navbar.js) — fed with this sale's real data instead of that
// wizard's own hard-coded demo content. Returns null if the template engine
// isn't available (navbar.js failed to load), so callers can fall back to the
// thermal-receipt layout.
function buildInvoiceDocument(data) {
  const IT = window.InvoiceTemplates;
  if (!IT) return null;

  const setup = posInvoiceSetup || {};
  const template = (IT.templates || []).some((tp) => tp.id === setup.template) ? setup.template : 'classic';
  const tplMeta = IT.byId(template);
  const accent = setup.accent_color || tplMeta.accent;
  const paperSize = ['a4', 'a5', 'letter', 'legal'].includes(setup.paper_size) ? setup.paper_size : 'a4';
  const orientation = setup.orientation === 'landscape' ? 'landscape' : 'portrait';
  const geom = IT.geom(paperSize, orientation);
  const mg = {
    top: Number.isFinite(setup.margin_top) ? setup.margin_top : 20,
    bot: Number.isFinite(setup.margin_bottom) ? setup.margin_bottom : 20,
    left: Number.isFinite(setup.margin_left) ? setup.margin_left : 15,
    right: Number.isFinite(setup.margin_right) ? setup.margin_right : 15,
  };
  const headerLayout = ['num-left', 'num-right', 'num-center'].includes(setup.header_layout) ? setup.header_layout : 'num-left';

  const items = data.items.map((it) => ({
    name: esc(it.name),
    desc: esc([it.sku, it.discountPct > 0.05 ? t('{pct}% off', { pct: fmtPct(it.discountPct) }) : null, it.warranty, it.rental].filter(Boolean).join(' · ')),
    qty: esc(fmtQty(it.qty)),
    price: esc(money(it.unit)),
    total: esc(money(it.line)),
  }));

  const totalsLines = [{ label: esc(t('Subtotal')), value: esc(money(data.subtotal)) }];
  if (data.discountAmount > 0.001) {
    const pctLabel = data.discountPercent ? ` (${data.discountPercent}%)` : '';
    totalsLines.push({ label: esc(t('Discount') + pctLabel), value: '−' + esc(money(data.discountAmount)), color: '#ef4444' });
  }
  if (data.couponDiscount > 0.001) {
    totalsLines.push({ label: esc(`${t('Coupon')} ${data.couponCode}`.trim()), value: '−' + esc(money(data.couponDiscount)), color: '#ef4444' });
  }
  if (data.giftCardAmount > 0.001) {
    totalsLines.push({ label: esc(`${t('Paid by gift card')} ${data.giftCardCode}`.trim()), value: esc(money(data.giftCardAmount)) });
  }

  const build = (IT.builders || {})[template];
  if (!build) return null;

  const ctx = {
    a: accent,
    mg,
    biz: posSettings.business_name || '',
    addr: posSettings.receipt_address_line || '',
    bizContact: '',
    geomW: geom.wPx,
    geomMinH: `min-height:${geom.hPx}px;`,
    hdrCss: IT.hdrCss(headerLayout) + IT.pageAtCss(geom),
    invoiceNumber: esc(`${posSettings.invoice_prefix || 'INV'}-${data.saleNumber}`),
    issueDate: esc(data.soldAtDate),
    dueDate: esc(data.soldAtDate),
    statusLabel: esc(t('Paid')),
    billToName: esc(data.customerName || t('Walk-in Customer')),
    billToLines: '',
    items,
    totalsLines,
    grandLabel: esc(t('Total')),
    grandValue: esc(money(data.total)),
    notesHtml: data.notes ? esc(data.notes) : '',
    footerRight: '',
  };

  return { html: build(ctx), width: geom.wPx, height: geom.hPx };
}

let currentReceiptDoc = null; // { mode, data, html } for the open receipt modal

function renderInvoicePreview(built) {
  const maxW = 400; // preview column budget inside the modal
  const scale = Math.min(1, maxW / built.width);
  const w = Math.round(built.width * scale);
  const h = Math.round(built.height * scale);
  receiptPreviewEl.innerHTML = `<div class="receipt-invoice-frame-wrap" style="width:${w}px;height:${h}px;"><iframe class="receipt-invoice-frame" style="width:${built.width}px;height:${built.height}px;transform:scale(${scale});" scrolling="no"></iframe></div>`;
  receiptPreviewEl.querySelector('iframe').srcdoc = built.html;
}

function showReceiptModal(sale) {
  const mode = posSettings.receipt_mode === 'invoice' ? 'invoice' : 'bill';
  const data = buildReceiptData(sale);
  receiptSaleNumberEl.textContent = sale.sale_number || '';

  const built = mode === 'invoice' ? buildInvoiceDocument(data) : null;
  if (built) {
    renderInvoicePreview(built);
    currentReceiptDoc = { mode: 'invoice', data, html: built.html };
  } else {
    receiptPreviewEl.innerHTML = `<div class="receipt-doc receipt-doc--bill">${renderBillHtml(data)}</div>`;
    currentReceiptDoc = { mode: 'bill', data, html: buildFullReceiptDocument(data) };
  }
  receiptModal.classList.add('show');
}

function closeReceiptModal() {
  receiptModal.classList.remove('show');
  currentReceiptDoc = null;
}

receiptPrintBtn.addEventListener('click', async () => {
  if (!currentReceiptDoc) return;
  receiptPrintBtn.disabled = true;
  try {
    await window.electronAPI.printHtml(currentReceiptDoc.html);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    receiptPrintBtn.disabled = false;
  }
});

receiptDownloadBtn.addEventListener('click', async () => {
  if (!currentReceiptDoc) return;
  receiptDownloadBtn.disabled = true;
  try {
    const filename = `${currentReceiptDoc.data.saleNumber || 'receipt'}.pdf`;
    const res = await window.electronAPI.savePdf(currentReceiptDoc.html, filename);
    if (res.status === 200) showToast(t('Saved to {path}', { path: res.savedPath }), 'success');
    else if (!res.canceled) showToast(res.message || t('Could not save PDF.'), 'error');
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    receiptDownloadBtn.disabled = false;
  }
});

// ── Checkout modal ───────────────────────────────────────────────────────
function currentOrderTotals() {
  const itemCount = cart.reduce((sum, c) => sum + c.qty, 0);
  const rawSubtotal = cart.reduce((sum, c) => sum + c.price * c.qty, 0);
  const subtotal = cart.reduce((sum, c) => sum + lineTotal(c), 0); // net of per-item discounts
  const itemDiscountTotal = rawSubtotal - subtotal;
  const discountAmount = discountAmountFor(subtotal);
  const discountValue = Math.max(0, Number(discountInput.value) || 0);
  const couponAmount = couponDiscountFor(subtotal - discountAmount);
  const total = subtotal - discountAmount - couponAmount;
  return { itemCount, rawSubtotal, subtotal, itemDiscountTotal, discountType, discountValue, discountAmount, couponAmount, total };
}

// ── Coupon at checkout ───────────────────────────────────────────────────
// One shared code per coupon; it takes a % or flat amount off the bill after
// the order discount. The server works the discount out again and uses up one
// of the coupon's uses (Modules/Pos CouponService::redeemForSale).
let checkoutCoupon = null; // { code, name, discount_type, discount_value }

function couponDiscountFor(amount) {
  if (!checkoutCoupon) return 0;
  const base = Math.max(0, amount);
  const v = Number(checkoutCoupon.discount_value) || 0;
  const d = checkoutCoupon.discount_type === 'percent' ? base * Math.min(100, Math.max(0, v)) / 100 : v;
  return Math.round(Math.min(base, Math.max(0, d)) * 100) / 100;
}

function couponOffText(c) {
  return c.discount_type === 'percent' ? t('{pct}% off', { pct: Number(c.discount_value) }) : t('{amount} off', { amount: money(c.discount_value) });
}

function couponBlockedReason(c) {
  return {
    expired: t('expired on {date}', { date: c.expires_at }),
    scheduled: t('is not valid until {date}', { date: c.valid_from }),
    used: t('has been fully used — no uses left'),
    disabled: t('is disabled'),
  }[c.status] || t('cannot be used');
}

function renderCheckoutCoupon() {
  const { couponAmount } = currentOrderTotals();
  const entryOpen = !document.getElementById('co-cp-entry').hidden;
  document.getElementById('co-cp-toggle').hidden = !!checkoutCoupon || entryOpen;
  document.getElementById('co-cp-applied').hidden = !checkoutCoupon;
  if (!checkoutCoupon) return;
  document.getElementById('co-cp-applied-code').textContent = checkoutCoupon.code;
  document.getElementById('co-cp-applied-sub').textContent = `${checkoutCoupon.name} · ${couponOffText(checkoutCoupon)}`;
  document.getElementById('co-cp-applied-amt').textContent = `−${money(couponAmount)}`;
}

function showCouponEntry(show) {
  document.getElementById('co-cp-entry').hidden = !show;
  if (show) {
    document.getElementById('co-cp-code').value = '';
    setTimeout(() => document.getElementById('co-cp-code').focus(), 30);
  }
  renderCheckoutCoupon();
}

function applyCouponToCheckout(c) {
  checkoutCoupon = { code: c.code, name: c.name, discount_type: c.discount_type, discount_value: Number(c.discount_value) };
  document.getElementById('co-cp-entry').hidden = true;
  refreshCheckoutSummary();
  amountReceivedInput.value = amountDueAfterGiftCard().toFixed(2);
  updateCheckoutTender();
}

async function submitCouponCode() {
  const input = document.getElementById('co-cp-code');
  const code = input.value.trim();
  if (!code) { input.focus(); return; }
  const btn = document.getElementById('co-cp-apply');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
  const res = await API.couponLookup(code);
  btn.disabled = false;
  btn.textContent = t('Apply');
  if (res.status !== 200) { showToast(t(res.body?.message || 'Coupon not found.'), 'error'); return; }
  const c = res.body.data;
  if (!c.is_redeemable) { showToast(t('Coupon {code} {reason}.', { code: c.code, reason: couponBlockedReason(c) }), 'error'); return; }
  applyCouponToCheckout(c);
  showToast(t('Coupon applied — {amount} off', { amount: money(currentOrderTotals().couponAmount) }), 'success');
}

document.getElementById('co-cp-toggle').addEventListener('click', () => showCouponEntry(true));
document.getElementById('co-cp-cancel').addEventListener('click', () => showCouponEntry(false));
document.getElementById('co-cp-apply').addEventListener('click', submitCouponCode);
document.getElementById('co-cp-code').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); submitCouponCode(); }
  if (e.key === 'Escape') { e.stopPropagation(); showCouponEntry(false); }
});
document.getElementById('co-cp-remove').addEventListener('click', () => {
  checkoutCoupon = null;
  refreshCheckoutSummary();
  amountReceivedInput.value = amountDueAfterGiftCard().toFixed(2);
  updateCheckoutTender();
});

function renderOrderItemsTable() {
  orderItemsBodyEl.innerHTML = cart.map((c) => {
    let sub = '';
    if (c.isRental) sub = `${t('Return')} ${c.rentalReturnDate} (${c.rentalDays}d)`;
    else if (c.isDynamic) sub = t('Dynamic');
    return `
      <tr data-key="${c.cartKey}">
        <td>
          <div class="oi-name">${esc(c.name)}</div>
          ${sub ? `<div class="oi-sub">${esc(sub)}</div>` : ''}
        </td>
        <td>${c.qty}</td>
        <td class="oi-right">${money(c.price)}</td>
        <td class="oi-disc"><input type="number" class="oi-disc-input" data-key="${c.cartKey}" min="0" max="100" step="0.1" value="${itemDiscountPercent(c)}">%</td>
        <td class="oi-right"><strong class="oi-total-value">${money(lineTotal(c))}</strong></td>
      </tr>`;
  }).join('');
}

orderItemsBodyEl.addEventListener('input', (e) => {
  const input = e.target.closest('.oi-disc-input');
  if (!input) return;
  const item = cart.find((c) => c.cartKey === input.dataset.key);
  if (!item) return;
  item.itemDiscountPercent = Math.min(100, Math.max(0, parseFloat(input.value) || 0));
  const row = input.closest('tr');
  row.querySelector('.oi-total-value').textContent = money(lineTotal(item));
  refreshCheckoutSummary();
});

function refreshCheckoutSummary() {
  const { itemCount, rawSubtotal, subtotal, itemDiscountTotal, discountAmount, total } = currentOrderTotals();
  coSubtotalEl.textContent = money(rawSubtotal);
  coSubtotalFootEl.textContent = money(subtotal);
  coItemCountEl.textContent = itemCount;
  coGrandTotalEl.textContent = money(total);
  if (itemDiscountTotal > 0.001) {
    coItemDiscountRowEl.style.display = '';
    coItemDiscountEl.textContent = `−${money(itemDiscountTotal)}`;
  } else {
    coItemDiscountRowEl.style.display = 'none';
  }
  if (discountAmount > 0.001) {
    coSaveRowEl.style.display = '';
    coSaveEl.textContent = `−${money(discountAmount)}`;
  } else {
    coSaveRowEl.style.display = 'none';
  }
  sumItemsEl.textContent = itemCount;
  sumTotalEl.textContent = money(total);
  checkoutBtn.disabled = cart.length === 0;
  updateCheckoutTender();
}

// ── Gift card at checkout ────────────────────────────────────────────────
// Applied on top of any payment method: the card covers up to its balance,
// the selected method pays what's left. The server re-checks and locks the
// card when the sale is submitted (Modules/Pos GiftCardService::redeemForSale).
let checkoutGiftCard = null; // { code, name, balance }

function giftCardApplied(total) {
  if (!checkoutGiftCard) return 0;
  return Math.round(Math.min(checkoutGiftCard.balance, total) * 100) / 100;
}

function amountDueAfterGiftCard() {
  const { total } = currentOrderTotals();
  return Math.max(0, Math.round((total - giftCardApplied(total)) * 100) / 100);
}

function renderCheckoutGiftCard() {
  const { total } = currentOrderTotals();
  const applied = giftCardApplied(total);
  const entryOpen = !document.getElementById('co-gc-entry').hidden;
  document.getElementById('co-gc-toggle').hidden = !!checkoutGiftCard || entryOpen;
  document.getElementById('co-gc-applied').hidden = !checkoutGiftCard;
  if (!checkoutGiftCard) return;
  document.getElementById('co-gc-applied-code').textContent = checkoutGiftCard.code;
  document.getElementById('co-gc-applied-sub').textContent = t('Balance {from} → {to} left', {
    from: money(checkoutGiftCard.balance),
    to: money(Math.max(0, checkoutGiftCard.balance - applied)),
  });
  document.getElementById('co-gc-applied-amt').textContent = `−${money(applied)}`;
}

function showGiftCardEntry(show) {
  const entry = document.getElementById('co-gc-entry');
  entry.hidden = !show;
  if (show) {
    document.getElementById('co-gc-code').value = '';
    setTimeout(() => document.getElementById('co-gc-code').focus(), 30);
  }
  renderCheckoutGiftCard();
}

// Reason text for a card the server says can't be redeemed right now.
function giftCardBlockedReason(c) {
  return {
    expired: t('expired on {date}', { date: c.expires_at }),
    scheduled: t('is not valid until {date}', { date: c.valid_from }),
    used: t('has no remaining balance'),
    disabled: t('is disabled'),
  }[c.status] || t('cannot be used');
}

function applyGiftCardToCheckout(c) {
  checkoutGiftCard = { code: c.code, name: c.name, balance: Number(c.balance) };
  document.getElementById('co-gc-entry').hidden = true;
  amountReceivedInput.value = amountDueAfterGiftCard().toFixed(2);
  updateCheckoutTender();
}

async function submitGiftCardCode() {
  const input = document.getElementById('co-gc-code');
  const code = input.value.trim();
  if (!code) { input.focus(); return; }
  const btn = document.getElementById('co-gc-apply');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
  const res = await API.giftCardLookup(code);
  btn.disabled = false;
  btn.textContent = t('Apply');
  if (res.status !== 200) { showToast(t(res.body?.message || 'Gift card not found.'), 'error'); return; }
  const c = res.body.data;
  if (!c.is_redeemable) { showToast(t('Gift card {code} {reason}.', { code: c.code, reason: giftCardBlockedReason(c) }), 'error'); return; }
  applyGiftCardToCheckout(c);
  showToast(t('Gift card applied — {amount} covered', { amount: money(giftCardApplied(currentOrderTotals().total)) }), 'success');
}

document.getElementById('co-gc-toggle').addEventListener('click', () => showGiftCardEntry(true));
document.getElementById('co-gc-cancel').addEventListener('click', () => showGiftCardEntry(false));
document.getElementById('co-gc-apply').addEventListener('click', submitGiftCardCode);
document.getElementById('co-gc-code').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); submitGiftCardCode(); }
  if (e.key === 'Escape') { e.stopPropagation(); showGiftCardEntry(false); }
});
document.getElementById('co-gc-remove').addEventListener('click', () => {
  checkoutGiftCard = null;
  amountReceivedInput.value = amountDueAfterGiftCard().toFixed(2);
  updateCheckoutTender();
});

function updateCheckoutTender() {
  const total = amountDueAfterGiftCard();
  coAmountDueEl.textContent = money(total);
  renderCheckoutCoupon();
  renderCheckoutGiftCard();

  const received = parseFloat(amountReceivedInput.value) || 0;
  const change = received - total;
  coChangeEl.textContent = money(Math.abs(change));
  coChangeEl.classList.toggle('negative', change < 0);

  const insufficientCash = paymentMethod === 'cash' && change < -0.001;
  checkoutCompleteBtn.disabled = cart.length === 0 || insufficientCash;
}

discountInput.addEventListener('input', () => { renderCart(); refreshCheckoutSummary(); });

numpadEl.addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-key]');
  if (!btn) return;
  const key = btn.dataset.key;
  let val = amountReceivedInput.value;
  if (key === 'back') val = val.slice(0, -1);
  else if (key === '.') { if (!val.includes('.')) val += '.'; }
  else val += key;
  amountReceivedInput.value = val;
  updateCheckoutTender();
});

amountReceivedInput.addEventListener('input', updateCheckoutTender);

exactAmountBtn.addEventListener('click', () => {
  amountReceivedInput.value = amountDueAfterGiftCard().toFixed(2);
  updateCheckoutTender();
});

clearAmountBtn.addEventListener('click', () => {
  amountReceivedInput.value = '';
  updateCheckoutTender();
});

function openCheckoutModal() {
  if (!cart.length) return;
  if (!selectedCustomer && cart.some((c) => c.isRental)) {
    showToast(t('Select a customer before completing a sale with rental products.'), 'error');
    openCustomerPickerModal();
    return;
  }

  document.querySelectorAll('#checkout-payment-methods .pm-btn').forEach((b) => b.classList.remove('active'));
  document.querySelector('#checkout-payment-methods .pm-btn[data-method="cash"]').classList.add('active');
  paymentMethod = 'cash';
  tenderSection.style.display = '';
  checkoutNoteInput.value = '';
  checkoutGiftCard = null;
  document.getElementById('co-gc-entry').hidden = true;
  checkoutCoupon = null;
  document.getElementById('co-cp-entry').hidden = true;

  renderOrderItemsTable();
  refreshCheckoutCustomerBox();
  refreshCheckoutSummary();
  amountReceivedInput.value = amountDueAfterGiftCard().toFixed(2);
  updateCheckoutTender();

  checkoutModal.classList.add('show');
  return true;
}

function closeCheckoutModal() {
  checkoutModal.classList.remove('show');
  // A coupon only lives for one checkout — drop it so the cart total goes back to normal.
  if (checkoutCoupon) {
    checkoutCoupon = null;
    refreshCheckoutSummary();
  }
}

checkoutBtn.addEventListener('click', openCheckoutModal);

checkoutCompleteBtn.addEventListener('click', async () => {
  if (!cart.length) return;

  if (!selectedCustomer && cart.some((c) => c.isRental)) {
    showToast(t('Select a customer before completing a sale with rental products.'), 'error');
    openCustomerPickerModal();
    return;
  }
  if (paymentMethod === 'credit' && !selectedCustomer) {
    showToast(t('Select a customer for credit payment.'), 'error');
    openCustomerPickerModal();
    return;
  }

  const { discountType: orderDiscountType, discountValue: orderDiscountValue, total: orderTotal } = currentOrderTotals();
  const giftAmount = giftCardApplied(orderTotal);
  const notes = checkoutNoteInput.value.trim();
  const amountTendered = parseFloat(amountReceivedInput.value) || 0;

  checkoutCompleteBtn.disabled = true;
  checkoutCompleteBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('Processing…')}`;
  try {
    const res = await API.checkout({
      items: cart.map((c) => {
        const item = {
          item_type: 'product',
          product_id: c.product_id,
          quantity: c.qty,
        };
        if (c.customPrice != null) item.custom_unit_price = c.customPrice;
        if (c.isDynamic && !c.dynamicLinked && c.customUnitPrice != null) item.custom_unit_price = c.customUnitPrice;
        if (c.isRental && c.rentalReturnDate) item.rental_return_date = c.rentalReturnDate;
        if (c.hasWarranty) {
          item.warranty_type = c.warrantyType;
          if (c.warrantyType === 'date' && c.warrantyDate) item.warranty_date = c.warrantyDate;
        }
        if (itemDiscountPercent(c) > 0) item.item_discount_percent = itemDiscountPercent(c);
        return item;
      }),
      payment_method: paymentMethod,
      ...(orderDiscountValue > 0 && orderDiscountType === 'flat' ? { discount_flat: orderDiscountValue } : {}),
      ...(orderDiscountValue > 0 && orderDiscountType === 'percent' ? { discount_percent: orderDiscountValue } : {}),
      ...(selectedCustomer ? { pos_customer_id: selectedCustomer.id } : {}),
      ...(paymentMethod === 'cash' ? { amount_tendered: amountTendered } : {}),
      ...(checkoutGiftCard && giftAmount > 0 ? { gift_card_code: checkoutGiftCard.code, gift_card_amount: giftAmount } : {}),
      ...(checkoutCoupon ? { coupon_code: checkoutCoupon.code } : {}),
      ...(notes ? { notes } : {}),
    });

    if (res.status !== 201) {
      showToast(t(res.body?.message || 'Checkout failed.'), 'error');
      return;
    }

    const sale = res.body.data;
    showToast(t('Sale {number} completed — total {total}', { number: sale.sale_number, total: money(sale.total) }), 'success');
    cart = [];
    renderCart();
    clearCustomerSelection();
    loadProducts(searchInput.value.trim()); // refresh stock counts
    closeCheckoutModal();
    showReceiptModal(sale);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    checkoutCompleteBtn.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${t('Complete Sale')}`;
    checkoutCompleteBtn.disabled = cart.length === 0;
  }
});

// ── Gift card balance check (cart header button) ─────────────────────────
const giftCheckModal = document.getElementById('gift-check-modal');
const giftCheckCodeInput = document.getElementById('gift-check-code');
const giftCheckResultEl = document.getElementById('gift-check-result');
const giftCheckRunBtn = document.getElementById('gift-check-run');

function openGiftCheckModal() {
  giftCheckCodeInput.value = '';
  giftCheckResultEl.innerHTML = '';
  giftCheckModal.classList.add('show');
  setTimeout(() => giftCheckCodeInput.focus(), 60);
}

function closeGiftCheckModal() {
  giftCheckModal.classList.remove('show');
}

function giftDaysFromToday(ymd) {
  const today = new Date(); today.setHours(0, 0, 0, 0);
  return Math.round((new Date(`${ymd}T00:00:00`) - today) / 86400000);
}

function fmtGiftDateTime(iso) {
  const d = iso ? new Date(iso) : null;
  return d && !isNaN(d.getTime())
    ? d.toLocaleString(i18n.locale, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—';
}

function renderGiftCheckResult(c) {
  let until = t('No expiry');
  if (c.expires_at) {
    const days = giftDaysFromToday(c.expires_at);
    const rel = days > 0 ? t(days === 1 ? '{n} day left' : '{n} days left', { n: days })
      : days === 0 ? t('last day today')
      : t(days === -1 ? '{n} day ago' : '{n} days ago', { n: -days });
    until = `${esc(c.expires_at)} <small>(${esc(rel)})</small>`;
  }
  const txns = c.transactions || [];
  const redeems = txns.filter((x) => x.type === 'redeem');
  const canApply = c.is_redeemable && cart.length > 0;
  const typeLabel = { issue: t('Issued'), redeem: t('Used'), refund: t('Refunded'), adjust: t('Adjusted') };

  const rows = [
    [t('Gift card'), esc(c.name)],
    [t('Code'), `<span class="gc-mono">${esc(c.code)}</span>`],
    [t('Value'), esc(money(c.initial_value))],
    [t('Used'), esc(money(c.used_amount))],
    [t('Valid from'), c.valid_from ? esc(c.valid_from) : '—'],
    [t('Valid until'), until],
    [t('Times used'), String(redeems.length)],
    [t('Last used'), redeems.length ? esc(fmtGiftDateTime(redeems[0].created_at)) : t('Never')],
  ];
  if (c.customer_name) rows.push([t('Customer'), esc(c.customer_name)]);

  giftCheckResultEl.innerHTML = `
    <div class="gc-verdict ${c.is_redeemable ? 'ok' : 'bad'}">
      <i class="fa-solid ${c.is_redeemable ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>
      <div class="gc-verdict-body">
        <div class="gc-verdict-title">${c.is_redeemable ? t('Valid — can be used') : t('Cannot be used')}</div>
        <div class="gc-verdict-sub">${c.is_redeemable ? t('Available balance') : esc(t('This gift card {reason}.', { reason: giftCardBlockedReason(c) }))}</div>
      </div>
      <div class="gc-verdict-amt">${esc(money(c.balance))}</div>
    </div>
    <div class="gc-check-grid">
      ${rows.map(([k, v]) => `<div class="gc-check-k">${k}</div><div class="gc-check-v">${v}</div>`).join('')}
    </div>
    <details class="gc-check-history">
      <summary><i class="fa-solid fa-clock-rotate-left"></i> ${t('Usage history')} (${txns.length})</summary>
      ${txns.map((x) => `
        <div class="gc-hist-row">
          <span>${esc(typeLabel[x.type] || x.type)}${x.sale_number ? ` · ${esc(x.sale_number)}` : ''}<small>${esc(fmtGiftDateTime(x.created_at))}</small></span>
          <span class="${x.amount < 0 ? 'neg' : 'pos'}">${x.amount < 0 ? '−' : '+'}${esc(money(Math.abs(x.amount)))}</span>
        </div>`).join('')}
    </details>
    ${canApply ? `<button type="button" class="checkout-btn gc-use-btn" id="gift-check-use"><i class="fa-solid fa-cash-register"></i> ${t('Use for this sale')}</button>` : ''}`;

  document.getElementById('gift-check-use')?.addEventListener('click', () => {
    closeGiftCheckModal();
    if (openCheckoutModal()) applyGiftCardToCheckout(c);
  });
}

async function runGiftCheck() {
  const code = giftCheckCodeInput.value.trim();
  if (!code) { giftCheckCodeInput.focus(); return; }
  giftCheckRunBtn.disabled = true;
  giftCheckResultEl.innerHTML = `<div class="gc-check-msg"><i class="fa-solid fa-spinner fa-spin"></i> ${t('Checking…')}</div>`;
  const res = await API.giftCardLookup(code);
  giftCheckRunBtn.disabled = false;
  if (res.status !== 200) {
    giftCheckResultEl.innerHTML = `<div class="gc-check-msg err"><i class="fa-solid fa-circle-exclamation"></i> ${esc(t(res.body?.message || 'Gift card not found.'))}</div>`;
    return;
  }
  renderGiftCheckResult(res.body.data);
}

document.getElementById('gift-check-btn').addEventListener('click', openGiftCheckModal);
giftCheckRunBtn.addEventListener('click', runGiftCheck);
giftCheckCodeInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') runGiftCheck(); });
giftCheckModal.querySelector('[data-close="gift-check-modal"]').addEventListener('click', closeGiftCheckModal);
giftCheckModal.addEventListener('mousedown', (e) => { if (e.target === giftCheckModal) closeGiftCheckModal(); });

// ── Coupon check (cart header button) ────────────────────────────────────
const couponCheckModal = document.getElementById('coupon-check-modal');
const couponCheckCodeInput = document.getElementById('coupon-check-code');
const couponCheckResultEl = document.getElementById('coupon-check-result');
const couponCheckRunBtn = document.getElementById('coupon-check-run');

function openCouponCheckModal() {
  couponCheckCodeInput.value = '';
  couponCheckResultEl.innerHTML = '';
  couponCheckModal.classList.add('show');
  setTimeout(() => couponCheckCodeInput.focus(), 60);
}

function closeCouponCheckModal() {
  couponCheckModal.classList.remove('show');
}

function renderCouponCheckResult(c) {
  let until = t('No expiry');
  if (c.expires_at) {
    const days = giftDaysFromToday(c.expires_at);
    const rel = days > 0 ? t(days === 1 ? '{n} day left' : '{n} days left', { n: days })
      : days === 0 ? t('last day today')
      : t(days === -1 ? '{n} day ago' : '{n} days ago', { n: -days });
    until = `${esc(c.expires_at)} <small>(${esc(rel)})</small>`;
  }
  const uses = c.redemptions || [];
  const canApply = c.is_redeemable && cart.length > 0;

  const rows = [
    [t('Coupon'), esc(c.name)],
    [t('Code'), `<span class="gc-mono">${esc(c.code)}</span>`],
    [t('Discount'), esc(couponOffText(c))],
    [t('Uses left'), `${c.remaining} <small>${esc(t('of {n}', { n: c.quantity }))}</small>`],
    [t('Valid from'), c.valid_from ? esc(c.valid_from) : '—'],
    [t('Valid until'), until],
  ];

  couponCheckResultEl.innerHTML = `
    <div class="gc-verdict ${c.is_redeemable ? 'ok' : 'bad'}">
      <i class="fa-solid ${c.is_redeemable ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>
      <div class="gc-verdict-body">
        <div class="gc-verdict-title">${c.is_redeemable ? t('Valid — can be used') : t('Cannot be used')}</div>
        <div class="gc-verdict-sub">${c.is_redeemable ? t('Discount on this bill') : esc(t('This coupon {reason}.', { reason: couponBlockedReason(c) }))}</div>
      </div>
      <div class="gc-verdict-amt">${esc(couponOffText(c))}</div>
    </div>
    <div class="gc-check-grid">
      ${rows.map(([k, v]) => `<div class="gc-check-k">${k}</div><div class="gc-check-v">${v}</div>`).join('')}
    </div>
    <details class="gc-check-history">
      <summary><i class="fa-solid fa-clock-rotate-left"></i> ${t('Usage history')} (${uses.length})</summary>
      ${uses.map((x) => `
        <div class="gc-hist-row">
          <span>${esc(x.sale_number || t('Sale'))}${x.reversed ? ` · ${t('voided')}` : ''}<small>${esc(fmtGiftDateTime(x.created_at))}</small></span>
          <span class="${x.reversed ? 'pos' : 'neg'}">${x.reversed ? '' : '−'}${esc(money(x.discount_amount))}</span>
        </div>`).join('')}
    </details>
    ${canApply ? `<button type="button" class="checkout-btn gc-use-btn cp-use-btn" id="coupon-check-use"><i class="fa-solid fa-cash-register"></i> ${t('Use for this sale')}</button>` : ''}`;

  document.getElementById('coupon-check-use')?.addEventListener('click', () => {
    closeCouponCheckModal();
    if (openCheckoutModal()) applyCouponToCheckout(c);
  });
}

async function runCouponCheck() {
  const code = couponCheckCodeInput.value.trim();
  if (!code) { couponCheckCodeInput.focus(); return; }
  couponCheckRunBtn.disabled = true;
  couponCheckResultEl.innerHTML = `<div class="gc-check-msg"><i class="fa-solid fa-spinner fa-spin"></i> ${t('Checking…')}</div>`;
  const res = await API.couponLookup(code);
  couponCheckRunBtn.disabled = false;
  if (res.status !== 200) {
    couponCheckResultEl.innerHTML = `<div class="gc-check-msg err"><i class="fa-solid fa-circle-exclamation"></i> ${esc(t(res.body?.message || 'Coupon not found.'))}</div>`;
    return;
  }
  renderCouponCheckResult(res.body.data);
}

document.getElementById('coupon-check-btn').addEventListener('click', openCouponCheckModal);
couponCheckRunBtn.addEventListener('click', runCouponCheck);
couponCheckCodeInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') runCouponCheck(); });
couponCheckModal.querySelector('[data-close="coupon-check-modal"]').addEventListener('click', closeCouponCheckModal);
couponCheckModal.addEventListener('mousedown', (e) => { if (e.target === couponCheckModal) closeCouponCheckModal(); });

// ── Cash Drawer: Open Shift ──────────────────────────────────────────────
// Checked once when the POS screen loads. If today's opening float hasn't
// been recorded yet, prompt for it; cashiers can also skip (e.g. businesses
// that don't track a cash drawer). Talks to the same /cash-drawer endpoints
// the full desktop app uses.
async function _checkShiftOpen() {
  const res = await API.cashDrawer();
  if (res.status !== 200) return; // fail open — don't block POS use
  const d = res.body?.data || {};
  if (d.is_opened) return;

  shiftOpenAmountInput.value = '';
  shiftOpenErrorEl.classList.remove('show');
  shiftOpenModal.classList.add('show');
  setTimeout(() => shiftOpenAmountInput.focus(), 80);
}

shiftOpenConfirmBtn.addEventListener('click', async () => {
  const amount = parseFloat(shiftOpenAmountInput.value);
  if (isNaN(amount) || amount < 0) {
    shiftOpenErrorEl.textContent = t('Enter a valid opening amount.');
    shiftOpenErrorEl.classList.add('show');
    return;
  }
  shiftOpenErrorEl.classList.remove('show');
  shiftOpenConfirmBtn.disabled = true;
  shiftOpenConfirmBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;

  const res = await API.cashDrawerOpen(amount);
  shiftOpenConfirmBtn.disabled = false;
  shiftOpenConfirmBtn.innerHTML = `<i class="fa-solid fa-check"></i> ${t('Start Shift')}`;

  if (res.status === 200) {
    shiftOpenModal.classList.remove('show');
    showToast(t('Shift started — opening float {amount}.', { amount: money(amount) }), 'success');
  } else {
    shiftOpenErrorEl.textContent = t(res.body?.message || 'Failed to start shift.');
    shiftOpenErrorEl.classList.add('show');
  }
});

shiftOpenSkipBtn.addEventListener('click', () => {
  shiftOpenModal.classList.remove('show');
});

// ── Cash Drawer: End of Day ───────────────────────────────────────────────
// Opened from the topbar's "End of Day" button. Combines /today-summary
// (sales totals + payment split) with /cash-drawer (opening float, cash
// sales, withdrawals, running balance) into one read-only overview, plus the
// Withdraw Cash action (password-gated — see below).
function methodLabel(method) {
  return { cash: t('Cash'), card: t('Card'), credit: t('Credit') }[method] || esc(method);
}

function openEodModal() {
  eodModal.classList.add('show');
  eodModalBodyEl.innerHTML = `<div class="eod-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>`;
  _loadEodData();
}

function closeEodModal() {
  eodModal.classList.remove('show');
}

let _eodDrawer = null; // last-loaded /cash-drawer response, so the withdraw modal knows the balance

async function _loadEodData() {
  const [summaryRes, drawerRes] = await Promise.all([API.todaySummary(), API.cashDrawer()]);

  if (summaryRes.status !== 200) {
    eodModalBodyEl.innerHTML = `<div class="eod-empty">${t('Could not load today’s summary.')}</div>`;
    return;
  }

  const sales = summaryRes.body?.data?.sales || {};
  const drawer = drawerRes.status === 200 ? (drawerRes.body?.data || null) : null;
  _eodDrawer = drawer;

  const byMethod = sales.by_method || {};
  const methodTabs = ['cash', 'card', 'credit'].map((m) => {
    const entry = byMethod[m] || { count: 0, total: 0 };
    return `<div class="eod-method-tab">
      <div class="eod-method-name">${esc(methodLabel(m))}</div>
      <div class="eod-method-meta">${t('{n} sales', { n: entry.count })} · ${money(entry.total)}</div>
    </div>`;
  }).join('');

  let drawerHtml;
  if (!drawer || !drawer.is_opened) {
    drawerHtml = `
      <p class="eod-empty">${t('Shift not started yet — start it to track opening float and withdrawals.')}</p>
      <div class="eod-actions"><button type="button" class="ghost-btn primary" id="eod-start-shift-btn"><i class="fa-solid fa-cash-register"></i> ${t('Start Shift')}</button></div>`;
  } else {
    const withdrawalsHtml = (drawer.withdrawals || []).length
      ? drawer.withdrawals.map((w) => `
        <div class="eod-withdraw-row">
          <div>
            <div>${esc(w.note || t('Cash withdrawal'))}</div>
            <div class="eod-withdraw-time">${new Date(w.time).toLocaleTimeString(i18n.locale, { hour: '2-digit', minute: '2-digit' })}</div>
          </div>
          <div class="eod-withdraw-amt">−${money(w.amount)}</div>
        </div>`).join('')
      : `<div class="eod-empty">${t('No withdrawals yet today.')}</div>`;

    drawerHtml = `
      <div class="eod-stat-grid">
        <div class="eod-stat"><div class="eod-stat-label">${t('Opening Float')}</div><div class="eod-stat-value">${money(drawer.opening_float)}</div></div>
        <div class="eod-stat"><div class="eod-stat-label">${t('Withdrawals')}</div><div class="eod-stat-value neg">${money(drawer.total_withdrawals)}</div></div>
        <div class="eod-stat accent"><div class="eod-stat-label">${t('Cash Balance')}</div><div class="eod-stat-value pos">${money(drawer.balance)}</div></div>
      </div>
      <div class="eod-section-label">${t('Withdrawals')}</div>
      ${withdrawalsHtml}
      <div class="eod-actions"><button type="button" class="ghost-btn primary" id="eod-withdraw-btn"><i class="fa-solid fa-arrow-up-from-bracket"></i> ${t('Withdraw Cash')}</button></div>`;
  }

  eodModalBodyEl.innerHTML = `
    <div class="eod-section-label">${t("Today's Sales Summary")}</div>
    <div class="eod-stat-grid">
      <div class="eod-stat"><div class="eod-stat-label">${t('Sales')}</div><div class="eod-stat-value">${sales.count ?? 0}</div></div>
      <div class="eod-stat accent"><div class="eod-stat-label">${t('Total Revenue')}</div><div class="eod-stat-value">${money(sales.revenue)}</div></div>
      <div class="eod-stat"><div class="eod-stat-label">${t('Gross Profit')}</div><div class="eod-stat-value">${money(sales.gross_profit)}</div></div>
    </div>
    <div class="eod-section-label">${t('Sales by Payment Method')}</div>
    <div class="eod-method-row">${methodTabs}</div>
    <div class="eod-section-label">${t('Cash Drawer')}</div>
    ${drawerHtml}`;

  const withdrawBtn = document.getElementById('eod-withdraw-btn');
  if (withdrawBtn) withdrawBtn.addEventListener('click', () => openCashWithdrawModal(drawer?.balance ?? null));

  const startShiftBtn = document.getElementById('eod-start-shift-btn');
  if (startShiftBtn) startShiftBtn.addEventListener('click', () => { closeEodModal(); _checkShiftOpen(); });
}

endOfDayBtn.addEventListener('click', openEodModal);

// ── Cash Drawer: Withdraw Cash (password-gated) ──────────────────────────
function openCashWithdrawModal(balance) {
  cashWithdrawAmountInput.value = '';
  cashWithdrawNoteInput.value = '';
  cashWithdrawErrorEl.classList.remove('show');
  cashWithdrawBalanceHintEl.textContent = balance !== null
    ? t('Available balance: {amount}', { amount: money(balance) })
    : '';
  cashWithdrawModal.classList.add('show');
  setTimeout(() => cashWithdrawAmountInput.focus(), 80);
}

function closeCashWithdrawModal() {
  cashWithdrawModal.classList.remove('show');
}

cashWithdrawAllBtn.addEventListener('click', () => {
  const balance = _eodDrawer?.balance ?? null;
  if (balance === null || balance <= 0) return;
  cashWithdrawAmountInput.value = balance.toFixed(2);
  cashWithdrawErrorEl.classList.remove('show');
});

cashWithdrawConfirmBtn.addEventListener('click', () => {
  const amount = parseFloat(cashWithdrawAmountInput.value);
  const balance = _eodDrawer?.balance ?? null;

  if (isNaN(amount) || amount <= 0) {
    cashWithdrawErrorEl.textContent = t('Enter a valid amount.');
    cashWithdrawErrorEl.classList.add('show');
    return;
  }
  if (balance !== null && amount > balance) {
    cashWithdrawErrorEl.textContent = t('Amount exceeds the available cash balance.');
    cashWithdrawErrorEl.classList.add('show');
    return;
  }
  cashWithdrawErrorEl.classList.remove('show');

  const note = cashWithdrawNoteInput.value.trim() || null;
  openPasswordConfirmModal(t('Enter your password to confirm this cash withdrawal.'), async () => {
    const res = await API.cashDrawerWithdraw(amount, note);
    if (res.status === 200) {
      closeCashWithdrawModal();
      showToast(t('Withdrawal of {amount} recorded.', { amount: money(amount) }), 'success');
      _loadEodData();
    } else {
      cashWithdrawErrorEl.textContent = t(res.body?.message || 'Failed to record withdrawal.');
      cashWithdrawErrorEl.classList.add('show');
    }
  });
});

// ── Generic password confirmation gate ────────────────────────────────────
// Reusable for any sensitive action — currently just cash withdrawal.
// Verifies against the logged-in account's own password via /verify-password.
let _passwordConfirmAction = null;

function openPasswordConfirmModal(subtitle, onConfirmed) {
  _passwordConfirmAction = onConfirmed;
  document.getElementById('password-confirm-subtitle').textContent = subtitle;
  passwordConfirmInput.value = '';
  passwordConfirmErrorEl.classList.remove('show');
  passwordConfirmModal.classList.add('show');
  setTimeout(() => passwordConfirmInput.focus(), 80);
}

function closePasswordConfirmModal() {
  passwordConfirmModal.classList.remove('show');
  passwordConfirmInput.value = '';
  _passwordConfirmAction = null;
}

passwordConfirmBtn.addEventListener('click', async () => {
  const password = passwordConfirmInput.value;
  if (!password) {
    passwordConfirmErrorEl.textContent = t('Enter your password.');
    passwordConfirmErrorEl.classList.add('show');
    return;
  }

  passwordConfirmBtn.disabled = true;
  passwordConfirmBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
  const res = await API.verifyPassword(password);
  passwordConfirmBtn.disabled = false;
  passwordConfirmBtn.innerHTML = `<i class="fa-solid fa-check"></i> ${t('Confirm')}`;

  if (res.status !== 200) {
    passwordConfirmErrorEl.textContent = t(res.body?.message || 'Incorrect password.');
    passwordConfirmErrorEl.classList.add('show');
    passwordConfirmInput.value = '';
    passwordConfirmInput.focus();
    return;
  }

  const action = _passwordConfirmAction;
  passwordConfirmModal.classList.remove('show');
  passwordConfirmInput.value = '';
  _passwordConfirmAction = null;
  if (action) await action();
});

passwordConfirmInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); passwordConfirmBtn.click(); }
});

// ── Settings ────────────────────────────────────────────────────────────
async function loadSettings() {
  const [settingsRes, invoiceSetupRes] = await Promise.all([API.settingsGet(), API.invoiceSetupGet()]);
  if (settingsRes.status === 200) posSettings = settingsRes.body?.data || {};
  if (invoiceSetupRes.status === 200) posInvoiceSetup = invoiceSetupRes.body?.data || {};
  discountRow.style.display = posSettings.discount_field_enabled ? '' : 'none';
  discountTypeFlatBtn.textContent = (posSettings.currency || 'LKR').toUpperCase();
  renderProducts();
  renderCart();
}

// ── Keyboard shortcuts (see js/navbar.js for the app-wide F1/F11 ones) ──
function isAnyPosModalOpen() {
  return [rentalModal, dynamicModal, customerPickerModal, checkoutModal, receiptModal, refundModal,
    shiftOpenModal, eodModal, cashWithdrawModal, passwordConfirmModal, addProductModal,
    giftCheckModal, couponCheckModal]
    .some((m) => m.classList.contains('show'));
}

document.addEventListener('keydown', (e) => {
  if (isAnyPosModalOpen()) return;
  const mod = e.ctrlKey || e.metaKey;

  // Ctrl+G gift card balance, Ctrl+K coupon check
  if (mod && !e.shiftKey && !e.altKey) {
    const k = e.key.toLowerCase();
    if (k === 'g') { e.preventDefault(); openGiftCheckModal(); return; }
    if (k === 'k') { e.preventDefault(); openCouponCheckModal(); return; }
  }

  // Alt+1…3 switch Products / Rental / Dynamic mode tabs
  if (e.altKey && !mod && !e.shiftKey) {
    const mode = { '1': 'products', '2': 'rental', '3': 'dynamic' }[e.key];
    if (mode) {
      e.preventDefault();
      document.querySelector(`.mode-tab[data-mode="${mode}"]`)?.click();
      return;
    }
  }

  switch (e.key) {
    case 'F2':
      e.preventDefault();
      searchInput.focus();
      searchInput.select();
      break;
    case 'F5':
      e.preventDefault();
      loadCategories();
      loadProducts(searchInput.value.trim());
      break;
    case 'F8':
      e.preventDefault();
      cart = [];
      renderCart();
      break;
    case 'F9':
      e.preventDefault();
      openRefundModal();
      break;
    case 'F10':
      e.preventDefault();
      openCustomerPickerModal();
      break;
    case 'F12':
      e.preventDefault();
      openCheckoutModal();
      break;
    default:
      return;
  }
});

// ── Init ────────────────────────────────────────────────────────────────
loadSettings();
loadCategories();
loadProducts();
_checkShiftOpen();
renderCart();
