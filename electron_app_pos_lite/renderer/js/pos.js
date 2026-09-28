'use strict';

const grid = document.getElementById('product-grid');
const searchInput = document.getElementById('search-input');
const cartItemsEl = document.getElementById('cart-items');
const sumItemsEl = document.getElementById('sum-items');
const sumTotalEl = document.getElementById('sum-total');
const discountRow = document.getElementById('discount-row');
const discountInput = document.getElementById('discount-input');
const checkoutBtn = document.getElementById('checkout-btn');
const toastEl = document.getElementById('toast');

let products = [];
let cart = []; // { product_id, name, price, customPrice, qty, stock }
let paymentMethod = 'cash';
let searchDebounce = null;
let posSettings = {}; // POS/Sale settings from the General tab (Settings → General)

function money(n) { return `$${(Number(n) || 0).toFixed(2)}`; }

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

// ── Products ────────────────────────────────────────────────────────────
function unitPrice(p) {
  return p.discounted_sell_price !== null && p.discounted_sell_price !== undefined
    ? p.discounted_sell_price
    : p.unit_sell_price;
}

function renderProducts() {
  if (!products.length) {
    grid.innerHTML = `<div class="empty-state">${t('No products found.')}</div>`;
    return;
  }

  grid.innerHTML = products.map((p) => {
    const outOfStock = Number(p.stock_quantity) <= 0;
    const thumb = p.image_url
      ? `<img src="${p.image_url}" alt="">`
      : '<i class="fa-solid fa-box"></i>';
    return `
      <div class="product-card ${outOfStock ? 'out-of-stock' : ''}" data-id="${p.id}">
        <div class="product-thumb">${thumb}</div>
        <div class="product-name">${p.name}</div>
        <div class="product-meta">
          <span class="product-price">${money(unitPrice(p))}</span>
          <span class="product-stock">${outOfStock ? t('Out of stock') : t('{n} left', { n: Math.floor(p.stock_quantity) })}</span>
        </div>
      </div>`;
  }).join('');

  grid.querySelectorAll('.product-card:not(.out-of-stock)').forEach((card) => {
    card.addEventListener('click', () => addToCart(Number(card.dataset.id)));
  });
}

async function loadProducts(query = '') {
  grid.innerHTML = `<div class="loading-state">${t('Loading products…')}</div>`;
  const res = await API.products(query);
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

// ── Cart ────────────────────────────────────────────────────────────────
function addToCart(productId) {
  const product = products.find((p) => p.id === productId);
  if (!product) return;

  const existing = cart.find((c) => c.product_id === productId);
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

  cart.push({
    product_id: product.id,
    name: product.name,
    price,
    customPrice,
    qty: 1,
    stock: product.stock_quantity,
  });
  renderCart();
}

function changeQty(productId, delta) {
  const item = cart.find((c) => c.product_id === productId);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) cart = cart.filter((c) => c.product_id !== productId);
  else if (item.qty > item.stock) item.qty = item.stock;
  renderCart();
}

function removeFromCart(productId) {
  cart = cart.filter((c) => c.product_id !== productId);
  renderCart();
}

document.getElementById('clear-cart').addEventListener('click', () => {
  cart = [];
  renderCart();
});

function renderCart() {
  if (!cart.length) {
    cartItemsEl.innerHTML = `<div class="cart-empty">${t('Cart is empty. Click a product to add it.')}</div>`;
  } else {
    cartItemsEl.innerHTML = cart.map((c) => `
      <div class="cart-item" data-id="${c.product_id}">
        <div class="cart-item-info">
          <div class="cart-item-name">${c.name}</div>
          <div class="cart-item-price">${money(c.price)} × ${c.qty} = ${money(c.price * c.qty)}</div>
        </div>
        <div class="qty-stepper">
          <button data-action="dec">−</button>
          <span>${c.qty}</span>
          <button data-action="inc">+</button>
        </div>
        <div class="cart-item-remove" data-action="remove"><i class="fa-solid fa-trash"></i></div>
      </div>`).join('');

    cartItemsEl.querySelectorAll('.cart-item').forEach((row) => {
      const id = Number(row.dataset.id);
      row.querySelector('[data-action="inc"]').addEventListener('click', () => changeQty(id, 1));
      row.querySelector('[data-action="dec"]').addEventListener('click', () => changeQty(id, -1));
      row.querySelector('[data-action="remove"]').addEventListener('click', () => removeFromCart(id));
    });
  }

  const itemCount = cart.reduce((sum, c) => sum + c.qty, 0);
  const subtotal = cart.reduce((sum, c) => sum + c.price * c.qty, 0);
  const total = subtotal - (subtotal * discountPercent() / 100);
  sumItemsEl.textContent = itemCount;
  sumTotalEl.textContent = money(total);
  checkoutBtn.disabled = cart.length === 0;
}

function discountPercent() {
  if (!posSettings.discount_field_enabled) return 0;
  return Math.min(100, Math.max(0, Number(discountInput.value) || 0));
}

discountInput.addEventListener('input', renderCart);

// ── Payment method ──────────────────────────────────────────────────────
document.querySelectorAll('.pm-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.pm-btn').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    paymentMethod = btn.dataset.method;
  });
});

// ── Checkout ────────────────────────────────────────────────────────────
checkoutBtn.addEventListener('click', async () => {
  if (!cart.length) return;

  const discountPct = discountPercent();

  if (posSettings.checkout_modal_enabled) {
    const itemCount = cart.reduce((sum, c) => sum + c.qty, 0);
    const subtotal = cart.reduce((sum, c) => sum + c.price * c.qty, 0);
    const total = subtotal - (subtotal * discountPct / 100);
    const lines = cart.map((c) => `${c.name} × ${c.qty} = ${money(c.price * c.qty)}`).join('\n');
    const summary = `${lines}\n\n${t('Items')}: ${itemCount}` +
      (discountPct ? `\n${t('Discount')}: ${discountPct}%` : '') +
      `\n${t('Payment method')}: ${t(paymentMethod === 'cash' ? 'Cash' : 'Card')}` +
      `\n${t('Total')}: ${money(total)}`;
    const ok = await zeebrooConfirm(summary, {
      title: t('Confirm sale?'),
      okText: t('Complete Sale'),
      icon: 'fa-cash-register',
    });
    if (!ok) return;
  }

  checkoutBtn.disabled = true;
  checkoutBtn.textContent = t('Processing…');
  try {
    const res = await API.checkout({
      items: cart.map((c) => ({
        item_type: 'product',
        product_id: c.product_id,
        quantity: c.qty,
        ...(c.customPrice != null ? { custom_unit_price: c.customPrice } : {}),
      })),
      payment_method: paymentMethod,
      ...(discountPct ? { discount_percent: discountPct } : {}),
    });

    if (res.status !== 201) {
      showToast(t(res.body?.message || 'Checkout failed.'), 'error');
      return;
    }

    const sale = res.body.data;
    showToast(t('Sale {number} completed — total {total}', { number: sale.sale_number, total: money(sale.total) }), 'success');
    cart = [];
    renderCart();
    loadProducts(searchInput.value.trim()); // refresh stock counts
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    checkoutBtn.textContent = t('Complete Sale');
    checkoutBtn.disabled = cart.length === 0;
  }
});

// ── Settings ────────────────────────────────────────────────────────────
async function loadSettings() {
  const res = await API.settingsGet();
  if (res.status === 200) posSettings = res.body?.data || {};
  discountRow.style.display = posSettings.discount_field_enabled ? '' : 'none';
  renderCart();
}

// ── Init ────────────────────────────────────────────────────────────────
loadSettings();
loadProducts();
renderCart();
