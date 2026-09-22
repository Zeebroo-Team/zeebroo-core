'use strict';

const grid = document.getElementById('product-grid');
const searchInput = document.getElementById('search-input');
const cartItemsEl = document.getElementById('cart-items');
const sumItemsEl = document.getElementById('sum-items');
const sumTotalEl = document.getElementById('sum-total');
const checkoutBtn = document.getElementById('checkout-btn');
const toastEl = document.getElementById('toast');

let products = [];
let cart = []; // { product_id, name, price, qty, stock }
let paymentMethod = 'cash';
let searchDebounce = null;

function money(n) { return `$${(Number(n) || 0).toFixed(2)}`; }

function showToast(message, type = '') {
  toastEl.textContent = message;
  toastEl.className = `toast show ${type}`;
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => { toastEl.className = 'toast'; }, 3200);
}

// ── Header (business / user) ───────────────────────────────────────────
async function loadHeader() {
  const cfg = await window.electronAPI.getConfig();
  document.getElementById('who-business').textContent = cfg.business_name || `#${cfg.business_id}`;
  document.getElementById('who-user').textContent = cfg.user?.name || cfg.user?.email || '—';
}

document.getElementById('back-btn').addEventListener('click', () => {
  window.location.href = 'dashboard.html';
});

document.getElementById('logout-btn').addEventListener('click', async () => {
  await window.electronAPI.logout();
});

// ── Products ────────────────────────────────────────────────────────────
function unitPrice(p) {
  return p.discounted_sell_price !== null && p.discounted_sell_price !== undefined
    ? p.discounted_sell_price
    : p.unit_sell_price;
}

function renderProducts() {
  if (!products.length) {
    grid.innerHTML = '<div class="empty-state">No products found.</div>';
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
          <span class="product-stock">${outOfStock ? 'Out of stock' : Math.floor(p.stock_quantity) + ' left'}</span>
        </div>
      </div>`;
  }).join('');

  grid.querySelectorAll('.product-card:not(.out-of-stock)').forEach((card) => {
    card.addEventListener('click', () => addToCart(Number(card.dataset.id)));
  });
}

async function loadProducts(query = '') {
  grid.innerHTML = '<div class="loading-state">Loading products…</div>';
  const res = await API.products(query);
  if (res.status !== 200) {
    grid.innerHTML = `<div class="empty-state">Could not load products (${res.body?.message || res.status}).</div>`;
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
    else showToast('No more stock available for this item.', 'error');
  } else {
    cart.push({
      product_id: product.id,
      name: product.name,
      price: unitPrice(product),
      qty: 1,
      stock: product.stock_quantity,
    });
  }
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
    cartItemsEl.innerHTML = '<div class="cart-empty">Cart is empty. Click a product to add it.</div>';
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
  const total = cart.reduce((sum, c) => sum + c.price * c.qty, 0);
  sumItemsEl.textContent = itemCount;
  sumTotalEl.textContent = money(total);
  checkoutBtn.disabled = cart.length === 0;
}

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

  checkoutBtn.disabled = true;
  checkoutBtn.textContent = 'Processing…';
  try {
    const res = await API.checkout({
      items: cart.map((c) => ({ item_type: 'product', product_id: c.product_id, quantity: c.qty })),
      payment_method: paymentMethod,
    });

    if (res.status !== 201) {
      showToast(res.body?.message || 'Checkout failed.', 'error');
      return;
    }

    const sale = res.body.data;
    showToast(`Sale ${sale.sale_number} completed — total ${money(sale.total)}`, 'success');
    cart = [];
    renderCart();
    loadProducts(searchInput.value.trim()); // refresh stock counts
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    checkoutBtn.textContent = 'Complete Sale';
    checkoutBtn.disabled = cart.length === 0;
  }
});

// ── Init ────────────────────────────────────────────────────────────────
loadHeader();
loadProducts();
renderCart();
