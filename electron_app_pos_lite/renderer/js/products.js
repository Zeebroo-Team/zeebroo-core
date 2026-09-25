'use strict';

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

const toastEl = document.getElementById('toast');

function showToast(message, type = '') {
  toastEl.textContent = message;
  toastEl.className = `toast show ${type}`;
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => { toastEl.className = 'toast'; }, 3000);
}

function esc(s) {
  return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function money(n) { return `$${(Number(n) || 0).toFixed(2)}`; }

function firstErrorMessage(res, fallback) {
  const firstKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
  return firstKey ? res.body.errors[firstKey][0] : (res.body?.message || fallback);
}

// ── Header ──────────────────────────────────────────────────────────────
document.getElementById('back-btn').addEventListener('click', () => { window.location.href = 'dashboard.html'; });
document.getElementById('reload-btn').addEventListener('click', () => { window.location.reload(); });
document.getElementById('restart-btn').addEventListener('click', async () => { await window.electronAPI.restartApp(); });
document.getElementById('logout-btn').addEventListener('click', async () => { await window.electronAPI.logout(); });

(async () => {
  const cfg = await window.electronAPI.getConfig();
  document.getElementById('who-business').textContent = cfg.business_name || `#${cfg.business_id}`;
  document.getElementById('who-user').textContent = cfg.user?.name || cfg.user?.email || '—';
})();

// ── Modal helpers ───────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

document.querySelectorAll('[data-close]').forEach((btn) => {
  btn.addEventListener('click', () => closeModal(btn.dataset.close));
});
document.querySelectorAll('.modal-backdrop').forEach((bg) => {
  bg.addEventListener('click', (e) => { if (e.target === bg) closeModal(bg.id); });
});

// ── Sub-nav panel switching ────────────────────────────────────────────
const subnavCards = document.querySelectorAll('.subnav-card');
subnavCards.forEach((card) => {
  card.addEventListener('click', () => {
    subnavCards.forEach((c) => c.classList.remove('active'));
    card.classList.add('active');
    document.querySelectorAll('.panel').forEach((p) => p.classList.remove('active'));
    document.getElementById(`panel-${card.dataset.panel}`).classList.add('active');
  });
});

// ── Shared caches ───────────────────────────────────────────────────────
let categories = [];    // current page of the Categories table
let allCategories = []; // full flat list, used by pickers (filter dropdown, parent select)
let brands = [];
let units = [];
let products = [];

function selectOptions(list) {
  return list.map((item) => `<option value="${item.id}">${esc(item.name)}</option>`).join('');
}

function refreshPickers() {
  const catFilter = document.getElementById('product-category-filter');
  const cParent = document.getElementById('c-parent');

  const prevFilter = catFilter.value;

  catFilter.innerHTML = '<option value="">All categories</option>' + selectOptions(allCategories);
  cParent.innerHTML = '<option value="">None</option>' + selectOptions(allCategories);

  catFilter.value = prevFilter;
}

async function loadAllCategories() {
  const res = await API.categoryParentOpts();
  if (res.status !== 200) return;
  allCategories = (res.body.data || []).map((c) => ({ id: c.id, name: c.name || (c.label || '').trim() }));
  refreshPickers();
}

// ── Pagination helper (shared by Products / Categories / Brands / Units) ──
function renderPaginationUI(prefix, page, lastPage, total) {
  const wrap = document.getElementById(`${prefix}-pagination`);
  const info = document.getElementById(`${prefix}-page-info`);
  const prevBtn = document.getElementById(`${prefix}-page-prev`);
  const nextBtn = document.getElementById(`${prefix}-page-next`);
  if (!total || lastPage <= 1) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'flex';
  info.textContent = `Page ${page} of ${lastPage} · ${total} item${total === 1 ? '' : 's'}`;
  prevBtn.disabled = page <= 1;
  nextBtn.disabled = page >= lastPage;
}

// ── Categories ──────────────────────────────────────────────────────────
const CATEGORIES_PER_PAGE = 10;
let categoriesPage = 1;
let categoriesMeta = { current_page: 1, last_page: 1, total: 0 };

async function loadCategories() {
  const res = await API.productCategories(document.getElementById('category-search').value.trim(), categoriesPage, CATEGORIES_PER_PAGE);
  if (res.status !== 200) { showToast(res.body?.message || 'Could not load categories.', 'error'); return; }
  categories = res.body.data || [];
  categoriesMeta = res.body.meta || { current_page: 1, last_page: 1, total: categories.length };
  categoriesPage = categoriesMeta.current_page;
  refreshPickers();
  renderCategoryRows();
  renderPaginationUI('category', categoriesMeta.current_page, categoriesMeta.last_page, categoriesMeta.total);
}

function renderCategoryRows() {
  const rowsEl = document.getElementById('category-rows');
  const total = categoriesMeta.total ?? categories.length;
  document.getElementById('category-count-pill').textContent = `${total} categor${total === 1 ? 'y' : 'ies'}`;
  if (!categories.length) {
    rowsEl.innerHTML = '<tr><td colspan="5" class="empty-state">No categories yet.</td></tr>';
    return;
  }
  rowsEl.innerHTML = categories.map((c) => `
    <tr data-id="${c.id}">
      <td class="row-title">${esc(c.name)}</td>
      <td>${esc(c.parent_name) || '—'}</td>
      <td>${c.products_count ?? 0}</td>
      <td><span class="status-badge ${c.is_active ? 'active' : 'inactive'}">${c.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><div class="row-actions"><button data-action="delete" class="danger" title="Delete"><i class="fa-solid fa-trash"></i></button></div></td>
    </tr>`).join('');

  rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    tr.querySelector('[data-action="delete"]').addEventListener('click', () => deleteCategory(id));
  });
}

document.getElementById('add-category-btn').addEventListener('click', () => {
  document.getElementById('c-name').value = '';
  document.getElementById('c-description').value = '';
  document.getElementById('c-parent').value = '';
  document.getElementById('c-active').checked = true;
  document.getElementById('category-error').classList.remove('show');
  openModal('category-modal');
  document.getElementById('c-name').focus();
});

document.getElementById('category-save-btn').addEventListener('click', async () => {
  const name = document.getElementById('c-name').value.trim();
  const errEl = document.getElementById('category-error');
  errEl.classList.remove('show');
  if (!name) {
    errEl.textContent = 'Category name is required.';
    errEl.classList.add('show');
    return;
  }

  const payload = {
    name,
    description: document.getElementById('c-description').value.trim() || null,
    parent_id: document.getElementById('c-parent').value || null,
    is_active: document.getElementById('c-active').checked,
  };

  const btn = document.getElementById('category-save-btn');
  btn.disabled = true; btn.textContent = 'Saving…';
  try {
    const res = await API.createProductCategory(payload);
    if (res.status !== 201) {
      errEl.textContent = firstErrorMessage(res, 'Could not save category.');
      errEl.classList.add('show');
      return;
    }
    closeModal('category-modal');
    showToast(`${res.body.data.name} added.`, 'success');
    await Promise.all([loadCategories(), loadAllCategories()]);
  } finally {
    btn.disabled = false; btn.textContent = 'Save Category';
  }
});

async function deleteCategory(id) {
  const cat = categories.find((c) => c.id === id);
  if (!confirm(`Delete ${cat ? cat.name : 'this category'}?`)) return;
  const res = await API.deleteProductCategory(id);
  if (res.status !== 200) { showToast(res.body?.message || 'Could not delete category.', 'error'); return; }
  showToast('Category deleted.', 'success');
  if (categories.length === 1 && categoriesPage > 1) categoriesPage--;
  loadCategories();
  loadAllCategories();
}

document.getElementById('category-search').addEventListener('input', debounce(() => { categoriesPage = 1; loadCategories(); }));
document.getElementById('category-page-prev').addEventListener('click', () => { if (categoriesPage > 1) { categoriesPage--; loadCategories(); } });
document.getElementById('category-page-next').addEventListener('click', () => { if (categoriesPage < categoriesMeta.last_page) { categoriesPage++; loadCategories(); } });

// ── Brands ──────────────────────────────────────────────────────────────
const BRANDS_PER_PAGE = 10;
let brandsPage = 1;
let brandsMeta = { current_page: 1, last_page: 1, total: 0 };

async function loadBrands() {
  const res = await API.productBrands(document.getElementById('brand-search').value.trim(), '', brandsPage, BRANDS_PER_PAGE);
  if (res.status !== 200) { showToast(res.body?.message || 'Could not load brands.', 'error'); return; }
  brands = res.body.data || [];
  brandsMeta = res.body.meta || { current_page: 1, last_page: 1, total: brands.length };
  brandsPage = brandsMeta.current_page;
  refreshPickers();
  renderBrandRows();
  renderPaginationUI('brand', brandsMeta.current_page, brandsMeta.last_page, brandsMeta.total);
}

function renderBrandRows() {
  const rowsEl = document.getElementById('brand-rows');
  const total = brandsMeta.total ?? brands.length;
  document.getElementById('brand-count-pill').textContent = `${total} brand${total === 1 ? '' : 's'}`;
  if (!brands.length) {
    rowsEl.innerHTML = '<tr><td colspan="5" class="empty-state">No brands yet.</td></tr>';
    return;
  }
  rowsEl.innerHTML = brands.map((b) => `
    <tr data-id="${b.id}">
      <td class="row-title">${esc(b.name)}</td>
      <td>${b.website ? `<a href="${esc(b.website)}" target="_blank" rel="noopener">${esc(b.website)}</a>` : '—'}</td>
      <td>${b.products_count ?? 0}</td>
      <td><span class="status-badge ${b.is_active ? 'active' : 'inactive'}">${b.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><div class="row-actions"><button data-action="delete" class="danger" title="Delete"><i class="fa-solid fa-trash"></i></button></div></td>
    </tr>`).join('');

  rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    tr.querySelector('[data-action="delete"]').addEventListener('click', () => deleteBrand(id));
  });
}

document.getElementById('add-brand-btn').addEventListener('click', () => {
  document.getElementById('b-name').value = '';
  document.getElementById('b-website').value = '';
  document.getElementById('b-description').value = '';
  document.getElementById('b-active').checked = true;
  document.getElementById('brand-error').classList.remove('show');
  openModal('brand-modal');
  document.getElementById('b-name').focus();
});

document.getElementById('brand-save-btn').addEventListener('click', async () => {
  const name = document.getElementById('b-name').value.trim();
  const errEl = document.getElementById('brand-error');
  errEl.classList.remove('show');
  if (!name) {
    errEl.textContent = 'Brand name is required.';
    errEl.classList.add('show');
    return;
  }

  const payload = {
    name,
    website: document.getElementById('b-website').value.trim() || null,
    description: document.getElementById('b-description').value.trim() || null,
    is_active: document.getElementById('b-active').checked,
  };

  const btn = document.getElementById('brand-save-btn');
  btn.disabled = true; btn.textContent = 'Saving…';
  try {
    const res = await API.createProductBrand(payload);
    if (res.status !== 201) {
      errEl.textContent = firstErrorMessage(res, 'Could not save brand.');
      errEl.classList.add('show');
      return;
    }
    closeModal('brand-modal');
    showToast(`${res.body.data.name} added.`, 'success');
    await loadBrands();
  } finally {
    btn.disabled = false; btn.textContent = 'Save Brand';
  }
});

async function deleteBrand(id) {
  const brand = brands.find((b) => b.id === id);
  if (!confirm(`Delete ${brand ? brand.name : 'this brand'}?`)) return;
  const res = await API.deleteProductBrand(id);
  if (res.status !== 200) { showToast(res.body?.message || 'Could not delete brand.', 'error'); return; }
  showToast('Brand deleted.', 'success');
  if (brands.length === 1 && brandsPage > 1) brandsPage--;
  loadBrands();
}

document.getElementById('brand-search').addEventListener('input', debounce(() => { brandsPage = 1; loadBrands(); }));
document.getElementById('brand-page-prev').addEventListener('click', () => { if (brandsPage > 1) { brandsPage--; loadBrands(); } });
document.getElementById('brand-page-next').addEventListener('click', () => { if (brandsPage < brandsMeta.last_page) { brandsPage++; loadBrands(); } });

// ── Units ───────────────────────────────────────────────────────────────
// The full unit list is fetched once (it also backs the Unit picker in the
// product form), then filtered and paginated entirely client-side.
const UNITS_PER_PAGE = 10;
let unitsPage = 1;

async function loadUnits() {
  const res = await API.productUnits(document.getElementById('unit-search').value.trim());
  if (res.status !== 200) { showToast(res.body?.message || 'Could not load units.', 'error'); return; }
  units = res.body.data || [];
  refreshPickers();
  renderUnitRows();
}

function renderUnitRows() {
  const rowsEl = document.getElementById('unit-rows');
  const search = document.getElementById('unit-search').value.trim().toLowerCase();
  const filtered = search
    ? units.filter((u) => u.name.toLowerCase().includes(search) || (u.abbreviation || '').toLowerCase().includes(search))
    : units;
  document.getElementById('unit-count-pill').textContent = `${filtered.length} unit${filtered.length === 1 ? '' : 's'}`;

  const lastPage = Math.max(1, Math.ceil(filtered.length / UNITS_PER_PAGE));
  if (unitsPage > lastPage) unitsPage = lastPage;
  const visible = filtered.slice((unitsPage - 1) * UNITS_PER_PAGE, unitsPage * UNITS_PER_PAGE);

  renderPaginationUI('unit', unitsPage, lastPage, filtered.length);

  if (!visible.length) {
    rowsEl.innerHTML = '<tr><td colspan="5" class="empty-state">No units yet.</td></tr>';
    return;
  }
  rowsEl.innerHTML = visible.map((u) => `
    <tr data-id="${u.id}">
      <td class="row-title">${esc(u.name)}</td>
      <td>${esc(u.abbreviation) || '—'}</td>
      <td>${u.products_count ?? 0}</td>
      <td><span class="status-badge ${u.is_active ? 'active' : 'inactive'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><div class="row-actions"><button data-action="delete" class="danger" title="Delete"><i class="fa-solid fa-trash"></i></button></div></td>
    </tr>`).join('');

  rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    tr.querySelector('[data-action="delete"]').addEventListener('click', () => deleteUnit(id));
  });
}

document.getElementById('add-unit-btn').addEventListener('click', () => {
  document.getElementById('u-name').value = '';
  document.getElementById('u-abbr').value = '';
  document.getElementById('u-active').checked = true;
  document.getElementById('unit-error').classList.remove('show');
  openModal('unit-modal');
  document.getElementById('u-name').focus();
});

document.getElementById('unit-save-btn').addEventListener('click', async () => {
  const name = document.getElementById('u-name').value.trim();
  const errEl = document.getElementById('unit-error');
  errEl.classList.remove('show');
  if (!name) {
    errEl.textContent = 'Unit name is required.';
    errEl.classList.add('show');
    return;
  }

  const payload = {
    name,
    abbreviation: document.getElementById('u-abbr').value.trim() || null,
    is_active: document.getElementById('u-active').checked,
  };

  const btn = document.getElementById('unit-save-btn');
  btn.disabled = true; btn.textContent = 'Saving…';
  try {
    const res = await API.createProductUnit(payload);
    if (res.status !== 201) {
      errEl.textContent = firstErrorMessage(res, 'Could not save unit.');
      errEl.classList.add('show');
      return;
    }
    closeModal('unit-modal');
    showToast(`${res.body.data.name} added.`, 'success');
    await loadUnits();
    const prodUnitSel = document.getElementById('prod-f-unit');
    if (prodUnitSel) {
      const u = res.body.data;
      const opt = document.createElement('option');
      opt.value = String(u.id);
      opt.textContent = u.abbreviation ? `${u.name} (${u.abbreviation})` : u.name;
      prodUnitSel.appendChild(opt);
      prodUnitSel.value = String(u.id);
    }
  } finally {
    btn.disabled = false; btn.textContent = 'Save Unit';
  }
});

async function deleteUnit(id) {
  const unit = units.find((u) => u.id === id);
  if (!confirm(`Delete ${unit ? unit.name : 'this unit'}?`)) return;
  const res = await API.deleteProductUnit(id);
  if (res.status !== 200) { showToast(res.body?.message || 'Could not delete unit.', 'error'); return; }
  showToast('Unit deleted.', 'success');
  loadUnits();
}

document.getElementById('unit-search').addEventListener('input', debounce(() => { unitsPage = 1; renderUnitRows(); }, 150));
document.getElementById('unit-page-prev').addEventListener('click', () => { if (unitsPage > 1) { unitsPage--; renderUnitRows(); } });
document.getElementById('unit-page-next').addEventListener('click', () => { unitsPage++; renderUnitRows(); });

// ── Quick-add "+" button inside the product form (Unit picker) ────────
document.querySelectorAll('.mini-add-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    if (btn.dataset.quick === 'unit') document.getElementById('add-unit-btn').click();
  });
});

// ── Product modal tabs ─────────────────────────────────────────────────
function switchProductTab(tab) {
  document.querySelectorAll('#product-tabs .modal-tab').forEach((t) => t.classList.toggle('active', t.dataset.tab === tab));
  document.querySelectorAll('.tab-pane').forEach((p) => p.classList.toggle('active', p.dataset.pane === tab));
  document.querySelector('.modal-body-tabs').scrollTop = 0;
}
document.querySelectorAll('#product-tabs .modal-tab').forEach((btn) => {
  btn.addEventListener('click', () => switchProductTab(btn.dataset.tab));
});

// ── Products ────────────────────────────────────────────────────────────
function categoryName(ids) {
  if (!ids || !ids.length) return '—';
  const cat = allCategories.find((c) => c.id === ids[0]);
  return cat ? cat.name : '—';
}

function renderProductRows() {
  const rowsEl = document.getElementById('product-rows');
  const total = productsMeta.total ?? products.length;
  document.getElementById('product-count-pill').textContent = `${total} product${total === 1 ? '' : 's'}`;
  if (!products.length) {
    rowsEl.innerHTML = '<tr><td colspan="7" class="empty-state">No products found.</td></tr>';
    return;
  }
  rowsEl.innerHTML = products.map((p) => {
    const stock = Number(p.stock_quantity) || 0;
    const stockClass = stock <= 0 ? 'out' : (stock <= 5 ? 'low' : 'active');
    const stockLabel = stock <= 0 ? 'Out of stock' : (stock <= 5 ? 'Low stock' : 'In stock');
    const badges = [
      p.has_warranty ? '<span class="row-flag" title="Warranty"><i class="fa-solid fa-shield-halved"></i></span>' : '',
      p.is_rental ? '<span class="row-flag" title="Rental"><i class="fa-solid fa-key"></i></span>' : '',
      p.is_subscription ? '<span class="row-flag" title="Subscription"><i class="fa-solid fa-repeat"></i></span>' : '',
      p.is_bundle ? '<span class="row-flag" title="Bundle"><i class="fa-solid fa-cubes"></i></span>' : '',
    ].join('');
    return `
    <tr data-id="${p.id}">
      <td>
        <div class="row-title">${esc(p.name)} ${badges}</div>
        ${p.sku ? `<div class="row-sub">${esc(p.sku)}</div>` : ''}
      </td>
      <td>${esc(categoryName(p.category_ids))}</td>
      <td>${esc(p.unit) || '—'}</td>
      <td>${money(p.discounted_sell_price ?? p.unit_sell_price)}</td>
      <td>${Math.floor(stock)}</td>
      <td><span class="status-badge ${stockClass}">${stockLabel}</span></td>
      <td>
        <div class="row-actions">
          <button data-action="edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
          <button data-action="delete" class="danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>
    </tr>`;
  }).join('');

  rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    const p = products.find((x) => x.id === id);
    tr.style.cursor = 'pointer';
    tr.addEventListener('click', (e) => { if (!e.target.closest('.row-actions')) openProductDetail(id, p?.name); });
    tr.querySelector('[data-action="edit"]').addEventListener('click', (e) => { e.stopPropagation(); openEditProduct(id); });
    tr.querySelector('[data-action="delete"]').addEventListener('click', (e) => { e.stopPropagation(); deleteProductRow(id); });
  });
}

const PRODUCTS_PER_PAGE = 20;
let productsPage = 1;
let productsMeta = { current_page: 1, last_page: 1, total: 0 };

async function loadProducts() {
  document.getElementById('product-rows').innerHTML = '<tr><td colspan="7" class="loading-state">Loading products…</td></tr>';
  const res = await API.productList({
    q: document.getElementById('product-search').value.trim(),
    categoryId: document.getElementById('product-category-filter').value,
    page: productsPage,
    perPage: PRODUCTS_PER_PAGE,
  });
  if (res.status !== 200) {
    document.getElementById('product-rows').innerHTML =
      `<tr><td colspan="7" class="empty-state">Could not load products (${res.body?.message || res.status}).</td></tr>`;
    return;
  }
  products = res.body.data || [];
  productsMeta = res.body.meta || { current_page: 1, last_page: 1, total: products.length };
  productsPage = productsMeta.current_page;
  renderProductRows();
  renderPaginationUI('product', productsMeta.current_page, productsMeta.last_page, productsMeta.total);
}

async function deleteProductRow(id) {
  const p = products.find((x) => x.id === id);
  if (!confirm(`Delete ${p ? p.name : 'this product'}? This cannot be undone.`)) return;
  const res = await API.deleteProduct(id);
  if (res.status !== 200) { showToast(res.body?.message || 'Could not delete product.', 'error'); return; }
  showToast('Product deleted.', 'success');
  if (products.length === 1 && productsPage > 1) productsPage--;
  loadProducts();
}

document.getElementById('product-search').addEventListener('input', debounce(() => { productsPage = 1; loadProducts(); }));
document.getElementById('product-category-filter').addEventListener('change', () => { productsPage = 1; loadProducts(); });
document.getElementById('product-page-prev').addEventListener('click', () => { if (productsPage > 1) { productsPage--; loadProducts(); } });
document.getElementById('product-page-next').addEventListener('click', () => { if (productsPage < productsMeta.last_page) { productsPage++; loadProducts(); } });

// ── Add / Edit product modal ───────────────────────────────────────────
const _DELIVERY_PARTNERS_META = {
  dhl:     { name: 'DHL Express',  icon: 'fa-earth-americas', desc: 'International express courier with time-definite delivery worldwide.' },
  fedex:   { name: 'FedEx',        icon: 'fa-box-open',       desc: 'Fast and reliable global shipping with real-time package tracking.' },
  uber:    { name: 'Uber',         icon: 'fa-car-side',       desc: 'On-demand local delivery via the Uber courier network.' },
  pickme:  { name: 'PickMe',       icon: 'fa-motorcycle',     desc: "Sri Lanka's leading ride-hailing platform with parcel delivery." },
  koobiyo: { name: 'Koobiyo',      icon: 'fa-bicycle',        desc: 'Last-mile e-commerce delivery built for Sri Lanka businesses.' },
  pronto:  { name: 'Pronto Lanka', icon: 'fa-truck-fast',     desc: 'Scheduled and same-day delivery across Sri Lanka.' },
};

const _prod = {
  editingId:      null,
  _optionsLoaded: false,
  _nextTempId:    -1,
  catOptions:     [],
  brandOptions:   [],
  selectedCats:   [],
  selectedBrands: [],
  tags:           [],
  imageFileId:        null,
  bundleItems:        [],
  _bundleSearchWired: false,
  batches:            [],
  batchPricing:       false,
};

// ── SKU generator ──────────────────────────────────────────────────────
function _prodGenerateSku() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let s = 'PRD-';
  for (let i = 0; i < 8; i++) s += chars[Math.floor(Math.random() * chars.length)];
  $('#prod-f-sku').value = s;
  if (!_prod.editingId) _prodBatchRender();
}
$('#prod-sku-gen')?.addEventListener('click', _prodGenerateSku);
$('#prod-f-sku')?.addEventListener('input', () => { if (!_prod.editingId) _prodBatchRender(); });

// ── Image picker (upload or browse the online file manager) ───────────
const _imgPicker = { _pendingFilePath: null, _folderId: null };

function _imgPickerOpen() {
  openModal('img-picker-modal');
  _imgPickerTabSwitch('upload');
}
function _imgPickerSelected(fileId, url) {
  _prodSetImage(fileId, url);
  _imgPickerClose();
}
function _imgPickerClose() {
  closeModal('img-picker-modal');
  _imgPicker._pendingFilePath = null;
  $('#img-upload-preview').style.display = 'none';
  $('#img-upload-drop').style.display = 'block';
}
function _imgPickerTabSwitch(tab) {
  $$('.img-tab-btn').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
  $('#img-tab-upload').style.display = tab === 'upload' ? 'block' : 'none';
  $('#img-tab-fm').style.display = tab === 'fm' ? 'block' : 'none';
  if (tab === 'fm') _imgPickerLoadFm(null);
}
async function _imgPickerBrowseFile() {
  const result = await window.electronAPI.showOpenDialog({
    title: 'Select Image',
    filters: [{ name: 'Images', extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp'] }],
    properties: ['openFile'],
  });
  if (result.canceled || !result.filePaths.length) return;
  const fp = result.filePaths[0];
  _imgPicker._pendingFilePath = fp;
  const name = fp.split(/[\\/]/).pop();
  $('#img-upload-preview-name').textContent = name;
  $('#img-upload-preview-img').src = 'file://' + fp;
  $('#img-upload-preview').style.display = 'block';
  $('#img-upload-drop').style.display = 'none';
}
async function _imgPickerUploadAndUse() {
  if (!_imgPicker._pendingFilePath) return;
  const btn = $('#img-upload-confirm');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading…';
  const res = await window.electronAPI.apiUpload('/online/file-manager/upload', _imgPicker._pendingFilePath);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa-solid fa-upload"></i> Upload &amp; Use';
  if (res.status === 201 && res.body?.data?.length) {
    const file = res.body.data[0];
    _imgPickerSelected(file.id, file.url);
  } else {
    showToast(res.body?.message || 'Upload failed', 'error');
  }
}
async function _imgPickerLoadFm(folderId) {
  _imgPicker._folderId = folderId;
  $('#img-fm-grid').innerHTML = '<div class="img-fm-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>';
  const res = await API.fileManagerBrowse(folderId, true);
  if (res.status !== 200) { $('#img-fm-grid').innerHTML = '<div class="img-fm-loading">Failed to load</div>'; return; }
  const { folders, files, breadcrumbs } = res.body;
  const bcParts = [{ id: null, name: 'Root' }, ...(breadcrumbs || [])];
  $('#img-fm-breadcrumb').innerHTML = bcParts.map((b, i) =>
    i < bcParts.length - 1
      ? `<button class="img-fm-bc-btn" data-folder="${b.id ?? ''}">${esc(b.name)}</button><i class="fa-solid fa-chevron-right" style="font-size:9px"></i>`
      : `<span>${esc(b.name)}</span>`
  ).join('');
  $('#img-fm-breadcrumb').querySelectorAll('.img-fm-bc-btn').forEach((btn) => {
    btn.addEventListener('click', () => _imgPickerLoadFm(btn.dataset.folder ? parseInt(btn.dataset.folder) : null));
  });
  const folderHtml = (folders || []).map((f) =>
    `<div class="img-fm-folder" data-folder-id="${f.id}"><i class="fa-solid fa-folder" style="font-size:24px;color:#f39c12"></i><span class="img-fm-file-name">${esc(f.name)}</span></div>`
  ).join('');
  const fileHtml = (files || []).map((f) =>
    `<div class="img-fm-file" data-file-id="${f.id}" data-url="${esc(f.url)}"><img src="${esc(f.url)}" alt="" loading="lazy"><span class="img-fm-file-name">${esc(f.name)}</span></div>`
  ).join('');
  $('#img-fm-grid').innerHTML = folderHtml + fileHtml || '<div class="img-fm-loading" style="color:var(--muted)">No images found</div>';
  $('#img-fm-grid').querySelectorAll('.img-fm-folder').forEach((el) => {
    el.addEventListener('click', () => _imgPickerLoadFm(parseInt(el.dataset.folderId)));
  });
  $('#img-fm-grid').querySelectorAll('.img-fm-file').forEach((el) => {
    el.addEventListener('click', () => _imgPickerSelected(parseInt(el.dataset.fileId), el.dataset.url));
  });
}
function _prodSetImage(fileId, url) {
  _prod.imageFileId = fileId;
  const thumb = $('#prod-img-thumb');
  thumb.innerHTML = url ? `<img src="${esc(url)}" alt="">` : '<i class="fa-regular fa-image" style="font-size:22px;color:var(--text-muted)"></i>';
  $('#prod-img-remove').style.display = url ? 'inline-flex' : 'none';
}
$('#prod-img-choose')?.addEventListener('click', _imgPickerOpen);
$('#prod-img-remove')?.addEventListener('click', () => _prodSetImage(null, null));
$('#img-upload-drop')?.addEventListener('click', _imgPickerBrowseFile);
$('#img-upload-confirm')?.addEventListener('click', _imgPickerUploadAndUse);
$$('.img-tab-btn').forEach((btn) => btn.addEventListener('click', () => _imgPickerTabSwitch(btn.dataset.tab)));

// ── Opening stock batches (create mode) ────────────────────────────────
function _prodBatchRender() {
  const wrap = $('#prod-batch-wrap');
  if (!wrap) return;
  const sku = ($('#prod-f-sku')?.value || '').trim();
  const pricing = _prod.batchPricing;
  if (!_prod.batches.length) {
    wrap.innerHTML = '<div class="prod-batch-empty"><i class="fa-solid fa-inbox"></i> No batches yet — click "Add Batch" to start</div>';
    return;
  }
  wrap.innerHTML = `<table class="prod-batch-table">
    <thead><tr>
      <th class="prod-batch-num">#</th>
      <th>Batch SKU</th>
      <th style="width:110px">Quantity</th>
      ${pricing ? `
      <th style="width:120px">Cost Price</th>
      <th style="width:120px">Selling Price</th>
      <th style="width:120px">Wholesale Price</th>
      ` : ''}
      <th style="width:36px"></th>
    </tr></thead>
    <tbody>${_prod.batches.map((b, i) => {
      const num = String(i + 1).padStart(2, '0');
      const batchSku = sku ? `${sku}-${num}` : '—';
      return `<tr>
        <td class="prod-batch-num">${num}</td>
        <td class="prod-batch-sku-cell">${esc(batchSku)}</td>
        <td><input type="number" class="prod-batch-qty po-field-input" data-idx="${i}" value="${esc(String(b.qty))}" min="0" step="0.001" placeholder="0"></td>
        ${pricing ? `
        <td><input type="number" class="prod-batch-cost po-field-input" data-idx="${i}" value="${esc(String(b.cost))}" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="prod-batch-selling po-field-input" data-idx="${i}" value="${esc(String(b.selling))}" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="prod-batch-wholesale po-field-input" data-idx="${i}" value="${esc(String(b.wholesale))}" min="0" step="0.01" placeholder="optional"></td>
        ` : ''}
        <td><button type="button" class="prod-batch-del" data-idx="${i}"><i class="fa-solid fa-trash"></i></button></td>
      </tr>`;
    }).join('')}</tbody>
  </table>`;
  wrap.querySelectorAll('.prod-batch-qty').forEach((inp) => inp.addEventListener('input', () => { _prod.batches[+inp.dataset.idx].qty = inp.value; }));
  wrap.querySelectorAll('.prod-batch-cost').forEach((inp) => inp.addEventListener('input', () => { _prod.batches[+inp.dataset.idx].cost = inp.value; }));
  wrap.querySelectorAll('.prod-batch-selling').forEach((inp) => inp.addEventListener('input', () => { _prod.batches[+inp.dataset.idx].selling = inp.value; }));
  wrap.querySelectorAll('.prod-batch-wholesale').forEach((inp) => inp.addEventListener('input', () => { _prod.batches[+inp.dataset.idx].wholesale = inp.value; }));
  wrap.querySelectorAll('.prod-batch-del').forEach((btn) => btn.addEventListener('click', () => { _prod.batches.splice(+btn.dataset.idx, 1); _prodBatchRender(); }));
}
$('#prod-batch-add-btn')?.addEventListener('click', () => { _prod.batches.push({ qty: '', cost: '', selling: '', wholesale: '' }); _prodBatchRender(); });
$('#prod-batch-pricing-chk')?.addEventListener('change', function () { _prod.batchPricing = this.checked; _prodBatchRender(); });

// ── Bundle items ────────────────────────────────────────────────────────
function _prodBundleRender() {
  const list = $('#prod-bundle-items');
  const count = _prod.bundleItems.length;
  const countEl = $('#prod-bundle-count');
  if (countEl) countEl.textContent = count === 1 ? '1 item in bundle' : `${count} items in bundle`;

  if (!count) {
    list.innerHTML = '<div class="prod-bundle-empty"><i class="fa-solid fa-box-open"></i> No items yet — search above to add products</div>';
    return;
  }
  list.innerHTML = _prod.bundleItems.map((item, i) => `
    <div class="prod-bundle-item" data-idx="${i}">
      <span class="prod-bundle-item-idx">${i + 1}</span>
      <span class="prod-bundle-item-name" title="${esc(item.name)}">${esc(item.name)}</span>
      <div class="prod-bundle-qty-ctrl">
        <button class="prod-bundle-qty-btn" data-idx="${i}" data-action="dec" type="button">−</button>
        <input type="number" class="prod-bundle-item-qty" min="0.001" step="1" value="${item.quantity}" data-idx="${i}">
        <button class="prod-bundle-qty-btn" data-idx="${i}" data-action="inc" type="button">+</button>
      </div>
      <button class="prod-bundle-item-rm" data-idx="${i}" type="button" title="Remove"><i class="fa-solid fa-xmark"></i></button>
    </div>`).join('');

  list.querySelectorAll('.prod-bundle-item-qty').forEach((inp) => {
    inp.addEventListener('change', () => {
      const idx = parseInt(inp.dataset.idx);
      if (_prod.bundleItems[idx]) {
        const v = parseFloat(inp.value);
        _prod.bundleItems[idx].quantity = v > 0 ? v : 1;
        inp.value = _prod.bundleItems[idx].quantity;
      }
    });
  });
  list.querySelectorAll('.prod-bundle-qty-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idx = parseInt(btn.dataset.idx);
      const item = _prod.bundleItems[idx];
      if (!item) return;
      const step = btn.dataset.action === 'inc' ? 1 : -1;
      item.quantity = Math.max(1, Math.round((item.quantity + step) * 1000) / 1000);
      const qtyInp = list.querySelector(`.prod-bundle-item-qty[data-idx="${idx}"]`);
      if (qtyInp) qtyInp.value = item.quantity;
    });
  });
  list.querySelectorAll('.prod-bundle-item-rm').forEach((btn) => {
    btn.addEventListener('click', () => {
      _prod.bundleItems.splice(parseInt(btn.dataset.idx), 1);
      _prodBundleRender();
      const inp = $('#prod-bundle-search');
      if (inp && inp.value.trim()) inp.dispatchEvent(new Event('input'));
    });
  });
}
function _prodBundleAddItem(id, name) {
  const existing = _prod.bundleItems.find((b) => b.product_id === id);
  if (existing) { existing.quantity += 1; _prodBundleRender(); }
  else { _prod.bundleItems.push({ product_id: id, name, quantity: 1 }); _prodBundleRender(); }
}
function _prodBundleWireSearch() {
  const inp = $('#prod-bundle-search');
  const dd = $('#prod-bundle-dd');
  if (!inp || !dd) return;
  const hide = () => { dd.style.display = 'none'; };
  let _timer = null;
  const refresh = async () => {
    const q = inp.value.trim();
    if (!q) { hide(); return; }
    const res = await API.productSearch(q, 20);
    if (res.status !== 200) { hide(); return; }
    if (inp.value.trim() !== q) return;
    const found = res.body?.data || [];
    if (!found.length) {
      _tagPositionDd(inp, dd);
      dd.innerHTML = '<div class="tag-dd-empty">No products found</div>';
      dd.style.display = 'block';
      return;
    }
    const addedIds = new Set(_prod.bundleItems.map((b) => b.product_id));
    dd.innerHTML = found.map((p, i) => {
      const added = addedIds.has(p.id);
      return `<div class="bundle-dd-item${i === 0 && !added ? ' focused' : ''}" data-id="${p.id}" data-name="${esc(p.name)}">
        <span class="bundle-dd-item-name">${esc(p.name)}</span>
        ${added ? '<span class="bundle-dd-item-added"><i class="fa-solid fa-check"></i> Added</span>' : '<button class="bundle-add-btn" type="button"><i class="fa-solid fa-plus"></i> Add</button>'}
      </div>`;
    }).join('');
    _tagPositionDd(inp, dd);
    dd.querySelectorAll('.bundle-dd-item').forEach((item) => {
      const addBtn = item.querySelector('.bundle-add-btn');
      if (addBtn) {
        addBtn.addEventListener('mousedown', (e) => {
          e.preventDefault();
          _prodBundleAddItem(parseInt(item.dataset.id), item.dataset.name);
          inp.dispatchEvent(new Event('input'));
          inp.focus();
        });
      }
    });
    dd.style.display = 'block';
  };
  inp.addEventListener('input', () => { clearTimeout(_timer); _timer = setTimeout(refresh, 250); });
  inp.addEventListener('focus', refresh);
  inp.addEventListener('blur', () => setTimeout(hide, 200));
  const scrollParent = inp.closest('.modal-body-tabs');
  if (scrollParent) scrollParent.addEventListener('scroll', hide, { passive: true });
}
$('#prod-f-bundle')?.addEventListener('change', function () {
  const on = this.checked;
  const sec = $('#prod-bundle-section');
  sec.style.display = on ? 'flex' : 'none';
  if (on) {
    if (!_prod._bundleSearchWired) { _prodBundleWireSearch(); _prod._bundleSearchWired = true; }
    setTimeout(() => { sec.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); $('#prod-bundle-search')?.focus(); }, 50);
  }
});

// ── Tag-input helpers (used for free-form tags, categories, brands) ────
function _tagRender(tagsId, selected, onRemove) {
  const el = $(`#${tagsId}`);
  el.innerHTML = selected.map((item) => {
    const display = item.label || item.name;
    return `<span class="tag-chip" title="${esc(display)}">
      <span style="overflow:hidden;text-overflow:ellipsis;max-width:160px">${esc(display)}</span>
      <button class="tag-chip-x" data-id="${item.id}" type="button"><i class="fa-solid fa-xmark"></i></button>
    </span>`;
  }).join('');
  el.querySelectorAll('.tag-chip-x').forEach((btn) => {
    btn.addEventListener('click', (e) => { e.stopPropagation(); onRemove(parseInt(btn.dataset.id)); });
  });
}
function _tagPositionDd(inputEl, ddEl) {
  const wrap = inputEl.closest('.tag-input-wrap, .prod-bundle-search-wrap') || inputEl;
  const r = wrap.getBoundingClientRect();
  ddEl.style.top = `${r.bottom + 3}px`;
  ddEl.style.left = `${r.left}px`;
  ddEl.style.width = `${r.width}px`;
}
function _tagShowDd(inputEl, ddEl, options, selectedIds, onSelect, onCreate) {
  const q = inputEl.value.trim();
  const qLower = q.toLowerCase();
  const filtered = options.filter((o) => !selectedIds.includes(o.id) && ((o.label || o.name).toLowerCase().includes(qLower) || o.name.toLowerCase().includes(qLower)));
  const rows = filtered.slice(0, 30).map((o, i) => {
    const display = o.label || o.name;
    return `<div class="tag-dd-item${i === 0 ? ' focused' : ''}" data-id="${o.id}" data-name="${esc(o.name)}" data-label="${esc(display)}">${esc(display)}</div>`;
  });
  const hasExact = options.some((o) => o.name.toLowerCase() === qLower);
  if (q && !hasExact && onCreate) {
    rows.push(`<div class="tag-dd-item tag-dd-create" data-create="${esc(q)}"><i class="fa-solid fa-plus"></i> Create "${esc(q)}"</div>`);
  }
  _tagPositionDd(inputEl, ddEl);
  if (!rows.length) {
    ddEl.innerHTML = `<div class="tag-dd-empty">${q ? 'No matches' : 'Type to search…'}</div>`;
    ddEl.style.display = 'block';
    return;
  }
  ddEl.innerHTML = rows.join('');
  ddEl.querySelectorAll('.tag-dd-item:not(.tag-dd-create)').forEach((item) => {
    item.addEventListener('mousedown', (e) => {
      e.preventDefault();
      onSelect({ id: parseInt(item.dataset.id), name: item.dataset.name, label: item.dataset.label });
      inputEl.value = '';
      ddEl.style.display = 'none';
    });
  });
  if (onCreate) {
    ddEl.querySelector('.tag-dd-create')?.addEventListener('mousedown', (e) => {
      e.preventDefault();
      onCreate(q);
      inputEl.value = '';
      ddEl.style.display = 'none';
    });
  }
  ddEl.style.display = 'block';
}
function _tagWireInput(inputId, ddId, getOptions, getSelected, onSelect, onCreate) {
  const inputEl = $(`#${inputId}`);
  const ddEl = $(`#${ddId}`);
  if (!inputEl || !ddEl) return;
  const hide = () => { ddEl.style.display = 'none'; };
  const refresh = () => _tagShowDd(inputEl, ddEl, getOptions(), getSelected().map((x) => x.id), onSelect, onCreate);
  inputEl.addEventListener('focus', refresh);
  inputEl.addEventListener('input', refresh);
  inputEl.addEventListener('blur', () => setTimeout(hide, 200));
  inputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { hide(); inputEl.blur(); return; }
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = ddEl.querySelector('.tag-dd-item');
      if (first) first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    }
  });
  const scrollParent = inputEl.closest('.modal-body-tabs');
  if (scrollParent) scrollParent.addEventListener('scroll', hide, { passive: true });
  window.addEventListener('resize', hide, { passive: true });
}

// ── Free-form product tags ─────────────────────────────────────────────
function _prodTagsRender() {
  const el = $('#prod-tags-tags');
  if (!el) return;
  el.innerHTML = _prod.tags.map((t, i) => `
    <span class="tag-chip" title="${esc(t)}">
      <span style="overflow:hidden;text-overflow:ellipsis;max-width:160px">${esc(t)}</span>
      <button class="tag-chip-x" data-idx="${i}" type="button"><i class="fa-solid fa-xmark"></i></button>
    </span>`).join('');
  el.querySelectorAll('.tag-chip-x').forEach((btn) => {
    btn.addEventListener('click', (e) => { e.stopPropagation(); _prod.tags.splice(parseInt(btn.dataset.idx), 1); _prodTagsRender(); });
  });
}
function _prodTagsAdd(raw) {
  const val = String(raw || '').trim();
  if (!val) return;
  if (_prod.tags.some((t) => t.toLowerCase() === val.toLowerCase())) return;
  _prod.tags.push(val);
  _prodTagsRender();
}
function _prodRemoveCat(id) {
  _prod.selectedCats = _prod.selectedCats.filter((x) => x.id !== id);
  _tagRender('prod-cat-tags', _prod.selectedCats, _prodRemoveCat);
}
function _prodRemoveBrand(id) {
  _prod.selectedBrands = _prod.selectedBrands.filter((x) => x.id !== id);
  _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand);
}
async function _prodLoadFormOptions() {
  const [unitRes, catRes, brandRes] = await Promise.all([
    API.productUnits(''),
    API.categoryParentOpts(),
    API.productBrands('', ''),
  ]);

  if (unitRes.status === 200) {
    const list = unitRes.body?.data || [];
    $('#prod-f-unit').innerHTML = '<option value="">— No unit —</option>' +
      list.map((u) => `<option value="${u.id}">${esc(u.name)}${u.abbreviation ? ` (${esc(u.abbreviation)})` : ''}</option>`).join('');
  }
  if (catRes.status === 200) {
    _prod.catOptions = (catRes.body?.data || []).map((c) => ({ id: c.id, name: c.name || (c.label || '').trim(), label: c.label }));
  }
  if (brandRes.status === 200) {
    _prod.brandOptions = (brandRes.body?.data || []).map((b) => ({ id: b.id, name: b.name }));
  }

  if (_prod._optionsLoaded) return;
  _prod._optionsLoaded = true;

  _tagWireInput(
    'prod-cat-input', 'prod-cat-dd',
    () => _prod.catOptions,
    () => _prod.selectedCats,
    (item) => { if (!_prod.selectedCats.find((x) => x.id === item.id)) { _prod.selectedCats.push(item); _tagRender('prod-cat-tags', _prod.selectedCats, _prodRemoveCat); } },
    (name) => {
      if (_prod.selectedCats.find((x) => x.name.toLowerCase() === name.toLowerCase())) return;
      _prod.selectedCats.push({ id: _prod._nextTempId--, name, label: name, _new: true });
      _tagRender('prod-cat-tags', _prod.selectedCats, _prodRemoveCat);
    },
  );
  _tagWireInput(
    'prod-brand-input', 'prod-brand-dd',
    () => _prod.brandOptions,
    () => _prod.selectedBrands,
    (item) => { if (!_prod.selectedBrands.find((x) => x.id === item.id)) { _prod.selectedBrands.push(item); _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand); } },
    (name) => {
      if (_prod.selectedBrands.find((x) => x.name.toLowerCase() === name.toLowerCase())) return;
      _prod.selectedBrands.push({ id: _prod._nextTempId--, name, _new: true });
      _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand);
    },
  );

  const tagsInput = $('#prod-tags-input');
  if (tagsInput) {
    tagsInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        _prodTagsAdd(tagsInput.value);
        tagsInput.value = '';
      } else if (e.key === 'Backspace' && !tagsInput.value && _prod.tags.length) {
        _prod.tags.pop();
        _prodTagsRender();
      }
    });
    tagsInput.addEventListener('blur', () => { if (tagsInput.value.trim()) { _prodTagsAdd(tagsInput.value); tagsInput.value = ''; } });
  }
}

// ── Warranty duration chips + reset-on-uncheck for Advanced toggles ────
function _prodSyncWarrantyChips() {
  const val = ($('#prod-f-warranty-duration')?.value || '').trim().toLowerCase();
  $$('#prod-warranty-duration-row .warranty-chip').forEach((c) => c.classList.toggle('active', c.dataset.val.toLowerCase() === val));
}
$('#prod-f-warranty-duration')?.addEventListener('input', _prodSyncWarrantyChips);
$$('#prod-warranty-duration-row .warranty-chip').forEach((chip) => {
  chip.addEventListener('click', () => { $('#prod-f-warranty-duration').value = chip.dataset.val; _prodSyncWarrantyChips(); });
});
$('#prod-f-warranty')?.addEventListener('change', function () {
  if (!this.checked) { $('#prod-f-warranty-duration').value = ''; $$('#prod-warranty-duration-row .warranty-chip').forEach((c) => c.classList.remove('active')); }
});
$('#prod-f-expiry')?.addEventListener('change', function () { if (!this.checked) $('#prod-f-exp-date').value = ''; });
$('#prod-f-rental')?.addEventListener('change', function () {
  if (!this.checked) {
    $('#prod-f-rental-daily-rate').value = '';
    $('#prod-f-rental-max-days').value = '';
    $('#prod-f-rental-late-fee-multiplier').value = '';
    $('#prod-f-rental-needs-cleaning').checked = false;
  }
});
$('#prod-f-subscription')?.addEventListener('change', function () {
  if (!this.checked) { $('#prod-f-subscription-period').value = 'monthly'; $('#prod-f-subscription-free-trial').checked = false; }
});
$('#prod-f-dynamic-pricing')?.addEventListener('change', function () { if (!this.checked) $('#prod-f-dynamic-linked').checked = false; });

// ── Delivery tab (dynamic partner cards from business settings) ────────
function _prodRenderDeliveryTab(deliveryEnabled, enabledKeys, selectedMethods) {
  const disabledBox = $('#prod-delivery-disabled-msg');
  const partnersBox = $('#prod-delivery-partners');
  const titleEl = $('#prod-delivery-disabled-title');
  const textEl = $('#prod-delivery-disabled-text');
  const selectedByKey = {};
  (Array.isArray(selectedMethods) ? selectedMethods : []).forEach((m) => { if (m && m.key) selectedByKey[m.key] = m; });

  if (!deliveryEnabled || !enabledKeys.length) {
    disabledBox.style.display = 'flex';
    partnersBox.style.display = 'none';
    partnersBox.innerHTML = '';
    if (!deliveryEnabled) {
      titleEl.textContent = 'Delivery methods are turned off';
      textEl.textContent = 'Enable delivery methods from the Zeebroo web dashboard to offer courier delivery for this product.';
    } else {
      titleEl.textContent = 'No delivery partners enabled';
      textEl.textContent = 'Enable at least one delivery partner from the Zeebroo web dashboard to offer courier delivery for this product.';
    }
    return;
  }

  disabledBox.style.display = 'none';
  partnersBox.style.display = 'flex';
  partnersBox.innerHTML = enabledKeys.map((key) => {
    const meta = _DELIVERY_PARTNERS_META[key] || { name: key, icon: 'fa-truck', desc: '' };
    const sel = selectedByKey[key];
    const price = sel && sel.price != null ? sel.price : '';
    return `
      <div class="prod-adv-card">
        <input type="checkbox" id="prod-delivery-chk-${key}" class="prod-adv-card-chk" data-delivery-key="${key}"${sel ? ' checked' : ''}>
        <label class="prod-adv-card-top" for="prod-delivery-chk-${key}">
          <div class="prod-adv-card-icon"><i class="fa-solid ${meta.icon}"></i></div>
          <div class="prod-adv-card-text">
            <span class="prod-adv-card-name">${esc(meta.name)}</span>
            <span class="prod-adv-card-desc">${esc(meta.desc)}</span>
          </div>
          <div class="prod-adv-card-sw"><div class="prod-adv-card-knob"></div></div>
        </label>
        <div class="prod-adv-card-fields">
          <div class="po-field-label" style="margin-bottom:6px"><i class="fa-solid fa-money-bill-wave" style="color:var(--accent);margin-right:4px"></i> Island Wide Delivery Price</div>
          <input type="number" min="0" step="0.01" id="prod-delivery-price-${key}" class="po-field-input" style="max-width:220px" placeholder="0.00" value="${price}">
        </div>
      </div>`;
  }).join('');
}

// ── Open modal (create or edit) ─────────────────────────────────────────
async function _prodOpenModal(editId) {
  _prod.editingId = editId || null;
  const isEdit = !!editId;
  document.getElementById('product-modal-title').innerHTML = isEdit ? '<i class="fa-solid fa-box"></i> Edit Product' : '<i class="fa-solid fa-box"></i> Add Product';

  // Reset fields
  ['prod-f-name', 'prod-f-sku', 'prod-f-model-no', 'prod-f-size', 'prod-f-mfg-date', 'prod-f-cost-price', 'prod-f-price', 'prod-f-wholesale-price', 'prod-f-stock', 'prod-f-description']
    .forEach((id) => { $(`#${id}`).value = ''; });
  _prod.tags = [];
  _prodTagsRender();
  $('#prod-tags-input').value = '';
  $('#prod-f-active').checked = true;
  $('#prod-f-unit').value = '';
  $('#prod-f-bundle').checked = false;
  $('#prod-bundle-section').style.display = 'none';
  $('#prod-f-warranty').checked = false;
  $('#prod-f-warranty-duration').value = '';
  $$('#prod-warranty-duration-row .warranty-chip').forEach((c) => c.classList.remove('active'));
  $('#prod-f-expiry').checked = false;
  $('#prod-f-exp-date').value = '';
  $('#prod-f-loyalty').checked = false;
  $('#prod-f-customer-required').checked = false;
  $('#prod-f-rental').checked = false;
  $('#prod-f-rental-daily-rate').value = '';
  $('#prod-f-rental-max-days').value = '';
  $('#prod-f-rental-late-fee-multiplier').value = '';
  $('#prod-f-rental-needs-cleaning').checked = false;
  $('#prod-f-subscription').checked = false;
  $('#prod-f-subscription-period').value = 'monthly';
  $('#prod-f-subscription-free-trial').checked = false;
  $('#prod-f-dynamic-pricing').checked = false;
  $('#prod-f-dynamic-linked').checked = false;
  $('#prod-f-item-tax').checked = false;
  $('#prod-f-item-discount').checked = false;

  _prod.imageFileId = null;
  _prodSetImage(null, null);

  _prod.bundleItems = [];
  _prodBundleRender();

  _prod.batches = [];
  _prod.batchPricing = false;
  const batchPricingChk = $('#prod-batch-pricing-chk');
  if (batchPricingChk) batchPricingChk.checked = false;

  _prod._nextTempId = -1;
  _prod.selectedCats = [];
  _prod.selectedBrands = [];
  _tagRender('prod-cat-tags', [], _prodRemoveCat);
  _tagRender('prod-brand-tags', [], _prodRemoveBrand);
  $('#prod-cat-input').value = '';
  $('#prod-brand-input').value = '';

  document.getElementById('product-error').classList.remove('show');
  switchProductTab('basic');
  openModal('product-modal');
  $('#prod-f-name').focus();

  await _prodLoadFormOptions();

  // Business delivery settings (global enable + enabled partner keys)
  let deliveryEnabled = false;
  let deliveryEnabledKeys = [];
  let editData = null;
  try {
    const sRes = await API.settingsGet();
    if (sRes.status === 200) {
      const s = sRes.body?.data ?? {};
      deliveryEnabled = !!s.delivery_enabled;
      deliveryEnabledKeys = Array.isArray(s.delivery_methods) ? s.delivery_methods : [];
    }
  } catch (_) { /* leave delivery tab in the disabled state */ }

  if (isEdit) {
    const res = await API.product(editId);
    if (res.status !== 200) {
      showToast(res.body?.message || 'Could not load product.', 'error');
      closeModal('product-modal');
      return;
    }
    editData = res.body.data;
  }

  _prodRenderDeliveryTab(deliveryEnabled, deliveryEnabledKeys, isEdit && editData ? editData.delivery_methods : []);

  if (isEdit && editData) {
    const p = editData;
    $('#prod-f-name').value = p.name || '';
    $('#prod-f-sku').value = p.sku || '';
    $('#prod-f-cost-price').value = p.cost_price != null ? p.cost_price : '';
    $('#prod-f-price').value = p.unit_price ?? p.price ?? '';
    $('#prod-f-wholesale-price').value = p.wholesale_price != null ? p.wholesale_price : '';
    $('#prod-f-stock').value = p.stock_quantity ?? p.total_stock ?? '';
    $('#prod-f-description').value = p.description || '';
    _prod.tags = Array.isArray(p.tags) ? p.tags.slice() : [];
    _prodTagsRender();
    $('#prod-f-active').checked = p.is_active !== false;
    if (p.product_unit_id) $('#prod-f-unit').value = p.product_unit_id;
    $('#prod-f-warranty').checked = !!p.has_warranty;
    $('#prod-f-warranty-duration').value = p.warranty_duration || '';
    _prodSyncWarrantyChips();
    $('#prod-f-expiry').checked = !!p.track_expiry;
    $('#prod-f-exp-date').value = p.exp_date || '';
    $('#prod-f-loyalty').checked = !!p.loyalty_redeemable;
    $('#prod-f-model-no').value = p.model_no || '';
    $('#prod-f-size').value = p.size || '';
    $('#prod-f-mfg-date').value = p.mfg_date || '';
    $('#prod-f-customer-required').checked = !!p.is_customer_required;
    $('#prod-f-rental').checked = !!p.is_rental;
    $('#prod-f-rental-daily-rate').value = p.rental_daily_rate != null ? p.rental_daily_rate : '';
    $('#prod-f-rental-max-days').value = p.rental_max_days != null ? p.rental_max_days : '';
    $('#prod-f-rental-late-fee-multiplier').value = p.rental_late_fee_multiplier != null ? p.rental_late_fee_multiplier : '';
    $('#prod-f-rental-needs-cleaning').checked = !!p.rental_needs_cleaning;
    $('#prod-f-subscription').checked = !!p.is_subscription;
    $('#prod-f-subscription-period').value = p.subscription_recurring_period || 'monthly';
    $('#prod-f-subscription-free-trial').checked = !!p.subscription_free_trial;
    $('#prod-f-dynamic-pricing').checked = !!p.is_dynamic_pricing;
    $('#prod-f-dynamic-linked').checked = !!p.dynamic_price_qty_linked;
    $('#prod-f-item-tax').checked = !!p.item_wise_tax;
    $('#prod-f-item-discount').checked = !!p.item_wise_discount;

    if (p.file_manager_file_id) {
      const imgUrl = p.image_url || p.images?.[0]?.url || null;
      _prodSetImage(p.file_manager_file_id, imgUrl);
    }

    if (p.is_bundle && p.bundle_items?.length) {
      $('#prod-f-bundle').checked = true;
      $('#prod-bundle-section').style.display = 'flex';
      _prod.bundleItems = p.bundle_items.map((bi) => ({ product_id: bi.product_id, name: bi.name, quantity: bi.quantity }));
      _prodBundleRender();
      if (!_prod._bundleSearchWired) { _prodBundleWireSearch(); _prod._bundleSearchWired = true; }
    }

    const catIds = p.category_ids || [];
    const brandIds = p.brand_ids || [];
    _prod.selectedCats = _prod.catOptions.filter((o) => catIds.includes(o.id));
    _prod.selectedBrands = _prod.brandOptions.filter((o) => brandIds.includes(o.id));
    _tagRender('prod-cat-tags', _prod.selectedCats, _prodRemoveCat);
    _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand);
  }

  const saveNewBtn = $('#prod-modal-save-new');
  if (isEdit) {
    $('#prod-stock-field').style.display = '';
    $('#prod-batch-section').style.display = 'none';
    if (saveNewBtn) saveNewBtn.style.display = 'none';
  } else {
    $('#prod-stock-field').style.display = 'none';
    $('#prod-batch-section').style.display = '';
    if (saveNewBtn) saveNewBtn.style.display = '';
    _prod.batches.push({ qty: '', cost: '', selling: '', wholesale: '' });
    _prodBatchRender();
  }
}

document.getElementById('add-product-btn').addEventListener('click', () => _prodOpenModal(null));
async function openEditProduct(id) { await _prodOpenModal(id); }

// ── Save ──────────────────────────────────────────────────────────────
async function _prodSave(andNew) {
  const name = $('#prod-f-name').value.trim();
  const price = parseFloat($('#prod-f-price').value);
  const errEl = document.getElementById('product-error');
  errEl.classList.remove('show');
  if (!name) { errEl.textContent = 'Product name is required.'; errEl.classList.add('show'); return; }
  if (isNaN(price) || price < 0) { errEl.textContent = 'Selling price must be 0 or more.'; errEl.classList.add('show'); return; }

  const btn = $('#prod-modal-save') || document.getElementById('product-save-btn');
  const btnNew = $('#prod-modal-save-new');
  btn.disabled = true;
  if (btnNew) btnNew.disabled = true;

  // Create any pending new categories/brands first (typed "create new" entries)
  const catIds = [];
  for (const cat of _prod.selectedCats) {
    if (cat._new) {
      const res = await API.createProductCategory({ name: cat.name, is_active: true });
      if (res.status !== 201) {
        errEl.textContent = firstErrorMessage(res, `Failed to create category "${cat.name}".`);
        errEl.classList.add('show');
        btn.disabled = false; if (btnNew) btnNew.disabled = false;
        return;
      }
      catIds.push(res.body.data.id);
    } else catIds.push(cat.id);
  }
  const brandIds = [];
  for (const brand of _prod.selectedBrands) {
    if (brand._new) {
      const res = await API.createProductBrand({ name: brand.name, is_active: true });
      if (res.status !== 201) {
        errEl.textContent = firstErrorMessage(res, `Failed to create brand "${brand.name}".`);
        errEl.classList.add('show');
        btn.disabled = false; if (btnNew) btnNew.disabled = false;
        return;
      }
      brandIds.push(res.body.data.id);
    } else brandIds.push(brand.id);
  }

  const isBundle = $('#prod-f-bundle').checked;
  const bundleItems = _prod.bundleItems.map((b) => ({ product_id: b.product_id, quantity: b.quantity }));
  if (isBundle && !bundleItems.length) {
    errEl.textContent = 'Add at least one product to the bundle.';
    errEl.classList.add('show');
    btn.disabled = false; if (btnNew) btnNew.disabled = false;
    return;
  }

  const payload = {
    name,
    sku: $('#prod-f-sku').value.trim() || null,
    unit_price: price,
    cost_price: parseFloat($('#prod-f-cost-price').value) || null,
    wholesale_price: parseFloat($('#prod-f-wholesale-price').value) || null,
    stock_quantity: _prod.editingId ? (parseFloat($('#prod-f-stock').value) || 0) : 0,
    description: $('#prod-f-description').value.trim() || null,
    tags: _prod.tags.slice(),
    product_unit_id: parseInt($('#prod-f-unit').value) || null,
    product_category_ids: catIds,
    product_brand_ids: brandIds,
    is_active: $('#prod-f-active').checked,
    is_bundle: isBundle,
    bundle_items: bundleItems,
    has_warranty: $('#prod-f-warranty').checked,
    warranty_duration: $('#prod-f-warranty').checked ? ($('#prod-f-warranty-duration').value.trim() || null) : null,
    track_expiry: $('#prod-f-expiry').checked,
    delivery_methods: $$('#prod-delivery-partners [data-delivery-key]')
      .filter((chk) => chk.checked)
      .map((chk) => ({ key: chk.dataset.deliveryKey, price: parseFloat($(`#prod-delivery-price-${chk.dataset.deliveryKey}`).value) || 0 })),
    loyalty_redeemable: $('#prod-f-loyalty').checked,
    model_no: $('#prod-f-model-no').value.trim() || null,
    size: $('#prod-f-size').value.trim() || null,
    mfg_date: $('#prod-f-mfg-date').value || null,
    exp_date: $('#prod-f-exp-date').value || null,
    is_customer_required: $('#prod-f-customer-required').checked,
    is_rental: $('#prod-f-rental').checked,
    rental_daily_rate: $('#prod-f-rental').checked ? (parseFloat($('#prod-f-rental-daily-rate').value) || null) : null,
    rental_max_days: $('#prod-f-rental').checked ? (parseInt($('#prod-f-rental-max-days').value, 10) || null) : null,
    rental_late_fee_multiplier: $('#prod-f-rental').checked ? (parseFloat($('#prod-f-rental-late-fee-multiplier').value) || null) : null,
    rental_needs_cleaning: $('#prod-f-rental').checked ? $('#prod-f-rental-needs-cleaning').checked : false,
    is_subscription: $('#prod-f-subscription').checked,
    subscription_recurring_period: $('#prod-f-subscription').checked ? $('#prod-f-subscription-period').value : null,
    subscription_free_trial: $('#prod-f-subscription').checked ? $('#prod-f-subscription-free-trial').checked : false,
    is_dynamic_pricing: $('#prod-f-dynamic-pricing').checked,
    dynamic_price_qty_linked: $('#prod-f-dynamic-pricing').checked ? $('#prod-f-dynamic-linked').checked : false,
    item_wise_tax: $('#prod-f-item-tax').checked,
    item_wise_discount: $('#prod-f-item-discount').checked,
  };
  if (_prod.imageFileId) payload.file_manager_file_ids = [_prod.imageFileId];
  if (!_prod.editingId) {
    payload.opening_batches = _prod.batches
      .filter((b) => parseFloat(b.qty) > 0)
      .map((b) => ({
        quantity: parseFloat(b.qty),
        cost_price: _prod.batchPricing ? (parseFloat(b.cost) || null) : null,
        selling_price: _prod.batchPricing ? (parseFloat(b.selling) || null) : null,
        wholesale_price: _prod.batchPricing ? (parseFloat(b.wholesale) || null) : null,
      }));
  }

  try {
    const res = _prod.editingId
      ? await API.updateProduct(_prod.editingId, payload)
      : await API.createProduct(payload);

    if (res.status !== 200 && res.status !== 201) {
      errEl.textContent = firstErrorMessage(res, 'Could not save product.');
      errEl.classList.add('show');
      return;
    }
    showToast(`${res.body.data?.name || name} ${_prod.editingId ? 'updated' : 'added'}.`, 'success');
    if (andNew && !_prod.editingId) {
      loadProducts();
      _prodOpenModal(null);
    } else if (_prod.editingId && _prodActiveId === _prod.editingId) {
      closeModal('product-modal');
      loadProducts();
      openProductDetail(_prod.editingId, res.body.data?.name || name);
    } else {
      closeModal('product-modal');
      loadProducts();
    }
  } finally {
    btn.disabled = false;
    if (btnNew) btnNew.disabled = false;
  }
}
document.getElementById('product-save-btn').addEventListener('click', () => _prodSave(false));
$('#prod-modal-save-new')?.addEventListener('click', () => _prodSave(true));

// ── Product Form Settings (gear icon: Tab/Single view + per-field show/hide) ──
const _PROD_VIEW_KEY = 'prod_modal_view';
const _PROD_FIELDS_KEY = 'prod_modal_fields';
let _prodViewMode = 'tab'; // 'tab' | 'single'
let _prodFieldHide = {};   // { 'field-id': true } → hidden

const _PROD_PANE_META = {
  basic: { label: 'Basic', icon: 'fa-circle-info' },
  pricing: { label: 'Pricing & Stock', icon: 'fa-tag' },
  media: { label: 'Media', icon: 'fa-image' },
  advanced: { label: 'Advanced', icon: 'fa-sliders' },
  delivery: { label: 'Delivery', icon: 'fa-truck' },
};

const _PROD_FIELD_MAP = [
  { id: 'sku', label: 'SKU / Barcode', icon: 'fa-barcode', section: 'Basic', getEl() { return document.getElementById('prod-f-sku')?.closest('.po-field'); } },
  { id: 'model-no', label: 'Model No', icon: 'fa-hashtag', section: 'Basic', getEl() { return document.getElementById('prod-f-model-no')?.closest('.po-field'); } },
  { id: 'size', label: 'Size', icon: 'fa-ruler', section: 'Basic', getEl() { return document.getElementById('prod-f-size')?.closest('.po-field'); } },
  { id: 'mfg-date', label: 'Mfg Date', icon: 'fa-calendar', section: 'Basic', getEl() { return document.getElementById('prod-f-mfg-date')?.closest('.po-field'); } },
  { id: 'description', label: 'Description', icon: 'fa-align-left', section: 'Basic', getEl() { return document.getElementById('prod-f-description')?.closest('.po-field'); } },
  { id: 'tags', label: 'Tags', icon: 'fa-tags', section: 'Basic', getEl() { return document.getElementById('prod-tags-wrap')?.closest('.po-field'); } },
  { id: 'active', label: 'Active flag', icon: 'fa-toggle-on', section: 'Basic', getEl() { return document.getElementById('prod-f-active')?.closest('.po-field'); } },
  { id: 'cost-price', label: 'Cost Price', icon: 'fa-coins', section: 'Pricing & Stock', getEl() { return document.getElementById('prod-f-cost-price')?.closest('.po-field'); } },
  { id: 'wholesale-price', label: 'Wholesale Price', icon: 'fa-tags', section: 'Pricing & Stock', getEl() { return document.getElementById('prod-f-wholesale-price')?.closest('.po-field'); } },
  { id: 'unit', label: 'Unit', icon: 'fa-weight-scale', section: 'Pricing & Stock', getEl() { return document.getElementById('prod-f-unit')?.closest('.po-field'); } },
  { id: 'opening-stock', label: 'Opening Stock', icon: 'fa-layer-group', section: 'Pricing & Stock', getEl() { return document.getElementById('prod-batch-section'); } },
  { id: 'image', label: 'Image', icon: 'fa-image', section: 'Media', getEl() { return document.getElementById('prod-img-thumb')?.closest('.po-field'); } },
  { id: 'categories', label: 'Categories', icon: 'fa-folder', section: 'Media', getEl() { return document.getElementById('prod-cat-wrap')?.closest('.po-field'); } },
  { id: 'brands', label: 'Brands', icon: 'fa-trademark', section: 'Media', getEl() { return document.getElementById('prod-brand-wrap')?.closest('.po-field'); } },
  { id: 'bundle', label: 'Bundle Product', icon: 'fa-cubes', section: 'Advanced', getEl() { return document.querySelector('#product-modal .prod-bundle-toggle-row'); } },
  { id: 'warranty', label: 'Warranty', icon: 'fa-shield-halved', section: 'Advanced', getEl() { return document.getElementById('prod-f-warranty')?.closest('.prod-adv-card'); } },
  { id: 'expiry', label: 'Expiration', icon: 'fa-calendar-xmark', section: 'Advanced', getEl() { return document.getElementById('prod-f-expiry')?.closest('.prod-adv-card'); } },
  { id: 'loyalty', label: 'Loyalty Redeemable', icon: 'fa-star', section: 'Advanced', getEl() { return document.getElementById('prod-f-loyalty')?.closest('.prod-adv-card'); } },
  { id: 'customer-required', label: 'Customer Required', icon: 'fa-user-check', section: 'Advanced', getEl() { return document.getElementById('prod-f-customer-required')?.closest('.prod-adv-card'); } },
  { id: 'rental', label: 'Rental', icon: 'fa-key', section: 'Advanced', getEl() { return document.getElementById('prod-f-rental')?.closest('.prod-adv-card'); } },
  { id: 'subscription', label: 'Subscription', icon: 'fa-repeat', section: 'Advanced', getEl() { return document.getElementById('prod-f-subscription')?.closest('.prod-adv-card'); } },
  { id: 'dynamic-pricing', label: 'Dynamic Pricing', icon: 'fa-wand-magic-sparkles', section: 'Advanced', getEl() { return document.getElementById('prod-f-dynamic-pricing')?.closest('.prod-adv-card'); } },
  { id: 'item-tax', label: 'Item Wise Tax', icon: 'fa-percent', section: 'Advanced', getEl() { return document.getElementById('prod-f-item-tax')?.closest('.prod-adv-card'); } },
  { id: 'item-discount', label: 'Item Wise Discount', icon: 'fa-tag', section: 'Advanced', getEl() { return document.getElementById('prod-f-item-discount')?.closest('.prod-adv-card'); } },
];

function _applyProdTabPrefs() {
  const modal = $('#product-modal');
  if (!modal) return;
  const isSingle = _prodViewMode === 'single';
  modal.classList.toggle('prod-view-single', isSingle);
  $$('#product-modal .tab-pane[data-pane]').forEach((pane) => {
    const existing = pane.querySelector('.prod-pane-section-hd');
    if (isSingle) {
      if (!existing) {
        const meta = _PROD_PANE_META[pane.dataset.pane] || {};
        const hd = document.createElement('div');
        hd.className = 'prod-pane-section-hd';
        hd.innerHTML = `<i class="fa-solid ${meta.icon || 'fa-layer-group'}"></i>&ensp;${esc(meta.label || pane.dataset.pane)}`;
        pane.insertBefore(hd, pane.firstChild);
      }
    } else {
      existing?.remove();
    }
  });
  _PROD_FIELD_MAP.forEach((f) => {
    const el = f.getEl();
    if (!el) return;
    el.style.display = _prodFieldHide[f.id] ? 'none' : '';
  });
}

let _pfsActiveOuter = 'general';
let _pfsPickedView = 'tab';
let _pfsPickedHide = {};

function _openProdViewSettings() {
  let ov = document.getElementById('prod-settings-overlay');

  if (!ov) {
    ov = document.createElement('div');
    ov.id = 'prod-settings-overlay';
    ov.className = 'modal-backdrop';
    ov.style.zIndex = '60';

    const genPanel = document.createElement('div');
    genPanel.className = 'pfs-panel pfs-gen-body';
    genPanel.id = 'pfs-panel-general';
    genPanel.innerHTML = `
      <div class="pfs-section-label">Display Mode</div>
      <div class="pfs-view-rows">
        <div class="pfs-view-row" data-view="tab">
          <div class="pfs-view-row-icon"><i class="fa-solid fa-table-columns"></i></div>
          <div class="pfs-view-row-body">
            <div class="pfs-view-row-title">Tab View</div>
            <div class="pfs-view-row-desc">Fields organized into sections — Basic, Pricing &amp; Stock, Media, and Advanced. Click the tabs to navigate.</div>
          </div>
          <i class="fa-solid fa-circle-check pfs-view-row-chk"></i>
        </div>
        <div class="pfs-view-row" data-view="single">
          <div class="pfs-view-row-icon"><i class="fa-solid fa-bars"></i></div>
          <div class="pfs-view-row-body">
            <div class="pfs-view-row-title">Single View</div>
            <div class="pfs-view-row-desc">All fields in one continuous scrollable form with section headings.</div>
          </div>
          <i class="fa-solid fa-circle-check pfs-view-row-chk"></i>
        </div>
      </div>`;

    const fieldsPanel = document.createElement('div');
    fieldsPanel.className = 'pfs-panel pfs-fields-body';
    fieldsPanel.id = 'pfs-panel-fields';
    fieldsPanel.style.display = 'none';

    const sectionOrder = [];
    const sectionMap = {};
    _PROD_FIELD_MAP.forEach((f) => {
      if (!sectionMap[f.section]) { sectionMap[f.section] = []; sectionOrder.push(f.section); }
      sectionMap[f.section].push(f);
    });
    sectionOrder.forEach((sec) => {
      const hd = document.createElement('div');
      hd.className = 'pfs-fields-sec-hd';
      hd.textContent = sec;
      fieldsPanel.appendChild(hd);
      sectionMap[sec].forEach((f) => {
        const row = document.createElement('div');
        row.className = 'pfs-field-row';
        row.dataset.fieldId = f.id;
        row.innerHTML = `
          <div class="pfs-field-icon"><i class="fa-solid ${f.icon}"></i></div>
          <span class="pfs-field-label">${esc(f.label)}</span>
          <label class="pfs-sw" title="Show / hide field">
            <input type="checkbox" class="pfs-sw-input" data-field="${f.id}" checked>
            <span class="pfs-sw-track"><span class="pfs-sw-knob"></span></span>
          </label>`;
        fieldsPanel.appendChild(row);
        row.querySelector('.pfs-sw-input').addEventListener('change', function () {
          if (this.checked) delete _pfsPickedHide[this.dataset.field];
          else _pfsPickedHide[this.dataset.field] = true;
          row.classList.toggle('pfs-field-hidden', !this.checked);
        });
      });
    });

    ov.innerHTML = `
      <div class="modal">
        <div class="modal-head">
          <h2><i class="fa-solid fa-gear"></i> Product Form Settings</h2>
          <button class="modal-close" id="prod-settings-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-tabs" id="pfs-outer-nav">
          <button type="button" class="modal-tab active" data-outer="general"><i class="fa-solid fa-sliders"></i> General</button>
          <button type="button" class="modal-tab" data-outer="fields"><i class="fa-solid fa-list-check"></i> Fields</button>
        </div>
        <div class="modal-body" id="pfs-panels" style="padding:0"></div>
        <div class="modal-foot">
          <button class="ghost-btn" id="prod-settings-cancel">Cancel</button>
          <button class="primary-btn" id="prod-settings-apply"><i class="fa-solid fa-check"></i> Apply</button>
        </div>
      </div>`;

    const panelsHost = ov.querySelector('#pfs-panels');
    panelsHost.appendChild(genPanel);
    panelsHost.appendChild(fieldsPanel);
    document.body.appendChild(ov);

    ov.addEventListener('mousedown', (e) => { if (e.target === ov) ov.classList.remove('show'); });
    ov.querySelector('#prod-settings-close').addEventListener('click', () => ov.classList.remove('show'));
    ov.querySelector('#prod-settings-cancel').addEventListener('click', () => ov.classList.remove('show'));

    ov.querySelector('#pfs-outer-nav').addEventListener('click', (e) => {
      const btn = e.target.closest('.modal-tab[data-outer]');
      if (!btn) return;
      _pfsActiveOuter = btn.dataset.outer;
      _syncPfsOuter(ov);
    });

    genPanel.addEventListener('click', (e) => {
      const row = e.target.closest('.pfs-view-row[data-view]');
      if (!row) return;
      _pfsPickedView = row.dataset.view;
      _syncPfsGeneral(genPanel);
    });

    ov.querySelector('#prod-settings-apply').addEventListener('click', async () => {
      _prodViewMode = _pfsPickedView;
      _prodFieldHide = Object.assign({}, _pfsPickedHide);
      ov.classList.remove('show');
      _applyProdTabPrefs();
      try {
        await window.electronAPI?.setConfig?.({ [_PROD_VIEW_KEY]: _prodViewMode, [_PROD_FIELDS_KEY]: _prodFieldHide });
      } catch (_) { /* ignore */ }
    });
  }

  _pfsActiveOuter = 'general';
  _pfsPickedView = _prodViewMode;
  _pfsPickedHide = Object.assign({}, _prodFieldHide);
  _syncPfsOuter(ov);
  _syncPfsGeneral(ov.querySelector('#pfs-panel-general'));
  _syncPfsFields(ov.querySelector('#pfs-panel-fields'));
  ov.classList.add('show');
}

function _syncPfsOuter(ov) {
  ov.querySelectorAll('#pfs-outer-nav .modal-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.outer === _pfsActiveOuter));
  const gp = ov.querySelector('#pfs-panel-general');
  const fp = ov.querySelector('#pfs-panel-fields');
  if (gp) gp.style.display = _pfsActiveOuter === 'general' ? '' : 'none';
  if (fp) fp.style.display = _pfsActiveOuter === 'fields' ? '' : 'none';
}
function _syncPfsGeneral(panel) {
  if (!panel) return;
  panel.querySelectorAll('.pfs-view-row[data-view]').forEach((row) => row.classList.toggle('selected', row.dataset.view === _pfsPickedView));
}
function _syncPfsFields(panel) {
  if (!panel) return;
  panel.querySelectorAll('.pfs-sw-input[data-field]').forEach((inp) => {
    const hidden = !!_pfsPickedHide[inp.dataset.field];
    inp.checked = !hidden;
    inp.closest('.pfs-field-row')?.classList.toggle('pfs-field-hidden', hidden);
  });
}

document.getElementById('prod-modal-settings-btn')?.addEventListener('click', (e) => { e.stopPropagation(); _openProdViewSettings(); });

// Load saved form-settings prefs on startup
(async () => {
  try {
    const cfg = await window.electronAPI.getConfig();
    const sv = cfg?.[_PROD_VIEW_KEY];
    if (sv === 'tab' || sv === 'single') _prodViewMode = sv;
    const sf = cfg?.[_PROD_FIELDS_KEY];
    if (sf && typeof sf === 'object') _prodFieldHide = sf;
  } catch (_) { /* ignore */ }
  _applyProdTabPrefs();
})();

// ── Product Detail view ─────────────────────────────────────────────────
let _prodActiveId = null;
let _prodActiveData = null;

async function openProductDetail(productId, productName) {
  _prodActiveId = productId;
  _prodActiveData = null;
  $('#product-list-view').style.display = 'none';
  $('#product-detail-view').style.display = 'block';
  $('#inv-detail-breadcrumb').textContent = productName || 'Product Detail';

  $$('#inv-tabs .inv-tab').forEach((t) => t.classList.remove('active'));
  $('#inv-tabs .inv-tab[data-tab="overview"]').classList.add('active');
  $$('.inv-tab-pane').forEach((p) => p.classList.remove('active'));
  $('#inv-pane-overview').classList.add('active');

  $('#inv-hero-name').textContent = productName || '…';
  $('#inv-hero-meta').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
  $('#inv-hero-badges').innerHTML = '';
  $('#inv-hero-img').innerHTML = '<div class="inv-thumb-ph inv-thumb-lg"><i class="fa-solid fa-box"></i></div>';
  $('#inv-tab-delivery').style.display = 'none';
  $('#inv-tab-rental').style.display = 'none';
  ['overview', 'pricing', 'stock', 'images', 'variants', 'delivery', 'rental'].forEach((t) => {
    $(`#inv-pane-${t}`).innerHTML = '<div class="inv-pane-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>';
  });

  const [res, histRes] = await Promise.all([
    API.product(productId),
    API.productStockHistory(productId),
  ]);
  const p = res.body?.data || res.body || null;
  if (!p || res.status !== 200) {
    $('#inv-hero-meta').innerHTML = '<span style="color:var(--danger)">Failed to load product</span>';
    return;
  }
  const stockHistory = histRes.status === 200 ? (histRes.body?.data || []) : [];
  renderProductDetail(p, stockHistory);
}

function renderProductDetail(p, stockHistory = []) {
  _prodActiveData = p;

  const images = p.images || p.product_images || (p.image ? [{ url: p.image }] : []);
  const firstImg = images[0]?.url || images[0]?.image_url || p.image_url || p.image || null;
  $('#inv-hero-img').innerHTML = firstImg
    ? `<img src="${esc(firstImg)}" class="inv-hero-img-el" alt="${esc(p.name)}">`
    : '<div class="inv-thumb-ph inv-thumb-lg"><i class="fa-solid fa-box"></i></div>';

  $('#inv-hero-name').textContent = p.name || '—';

  const metaParts = [];
  if (p.sku) metaParts.push(`<span><i class="fa-solid fa-barcode"></i> ${esc(p.sku)}</span>`);
  if (p.category?.name) metaParts.push(`<span><i class="fa-solid fa-tag"></i> ${esc(p.category.name)}</span>`);
  $('#inv-hero-meta').innerHTML = metaParts.join('<span class="inv-sep">·</span>');

  const stock = p.stock_quantity;
  const badges = [];
  badges.push(p.is_active === false ? '<span class="inv-badge inv-badge-gray">Inactive</span>' : '<span class="inv-badge inv-badge-green">Active</span>');
  if (stock != null) {
    if (stock <= 0) badges.push('<span class="inv-badge inv-badge-red">Out of stock</span>');
    else if (stock <= 5) badges.push('<span class="inv-badge inv-badge-amber">Low stock</span>');
    else badges.push('<span class="inv-badge inv-badge-blue">In stock</span>');
  }
  if (p.is_bundle) badges.push('<span class="inv-badge inv-badge-purple"><i class="fa-solid fa-cubes"></i> Bundle</span>');
  if (p.has_warranty) badges.push('<span class="inv-badge inv-badge-blue"><i class="fa-solid fa-shield-halved"></i> Warranty</span>');
  if (p.track_expiry) badges.push('<span class="inv-badge inv-badge-amber"><i class="fa-solid fa-calendar-xmark"></i> Expiry</span>');
  if (p.courier_delivery || p.delivery_methods?.length) badges.push('<span class="inv-badge inv-badge-purple"><i class="fa-solid fa-truck"></i> Courier</span>');
  if (p.loyalty_redeemable) badges.push('<span class="inv-badge inv-badge-green"><i class="fa-solid fa-star"></i> Loyalty</span>');
  if (p.is_rental) badges.push('<span class="inv-badge inv-badge-blue"><i class="fa-solid fa-key"></i> Rental</span>');
  if (p.is_subscription) badges.push('<span class="inv-badge inv-badge-purple"><i class="fa-solid fa-repeat"></i> Subscription</span>');
  $('#inv-hero-badges').innerHTML = badges.join('');

  // ── Overview tab ──
  const statusLabel = p.is_active === false ? 'Inactive' : 'Active';
  const statusClass = p.is_active === false ? 'inv-badge-gray' : 'inv-badge-green';
  const typeLabel = p.is_bundle ? 'Bundle' : 'Single';

  const cats = p.category ? [p.category] : (Array.isArray(p.category_ids) ? p.category_ids.map((id) => allCategories.find((c) => c.id === id) || { id, name: String(id) }) : []);
  const catChips = cats.length ? cats.map((c) => `<span class="inv-detail-chip">${esc(c.name)}</span>`).join('') : '<span class="inv-detail-none">—</span>';
  const brandName = p.brand?.name || p.brand_name || null;
  const brandChip = brandName ? `<span class="inv-detail-chip">${esc(brandName)}</span>` : '<span class="inv-detail-none">—</span>';
  const unitName = p.unit_name || p.unit?.name || p.unit || null;
  const tagChips = Array.isArray(p.tags) && p.tags.length ? p.tags.map((t) => `<span class="inv-detail-chip">${esc(t)}</span>`).join('') : '<span class="inv-detail-none">—</span>';

  const detailCells = [
    { label: 'SKU', value: p.sku ? esc(p.sku) : '<span class="inv-detail-none">—</span>' },
    { label: 'STATUS', value: `<span class="inv-badge ${statusClass}">${statusLabel}</span>` },
    { label: 'TYPE', value: `<strong>${typeLabel}</strong>` },
    { label: 'CATEGORIES', value: catChips },
    { label: 'BRANDS', value: brandChip },
    { label: 'TAGS', value: tagChips },
    { label: 'UNIT', value: unitName ? esc(unitName) : '<span class="inv-detail-none">—</span>' },
  ];

  const descHtml = p.description
    ? `<div class="inv-section" style="margin-top:0"><div class="inv-section-title"><i class="fa-solid fa-align-left"></i> Description</div><div class="inv-desc-body">${esc(p.description)}</div></div>`
    : '';
  const metaHtml = (p.created_at || p.updated_at)
    ? `<div class="inv-detail-meta-row">
        ${p.created_at ? `<span><i class="fa-solid fa-calendar-plus"></i> Created ${new Date(p.created_at).toLocaleDateString()}</span>` : ''}
        ${p.updated_at ? `<span><i class="fa-solid fa-calendar-check"></i> Updated ${new Date(p.updated_at).toLocaleDateString()}</span>` : ''}
       </div>`
    : '';

  $('#inv-pane-overview').innerHTML = `
    <div class="inv-section">
      <div class="inv-chart-header">
        <div class="inv-chart-title"><i class="fa-solid fa-chart-column"></i> Units sold</div>
        <div class="inv-chart-periods">
          <button class="inv-chart-period" data-period="daily">Daily</button>
          <button class="inv-chart-period active" data-period="weekly">Weekly</button>
          <button class="inv-chart-period" data-period="monthly">Monthly</button>
        </div>
      </div>
      <div id="inv-chart-container"></div>
    </div>
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa-solid fa-circle-info"></i> Details</div>
      <div class="inv-detail-grid">
        ${detailCells.map((c) => `<div><div class="inv-detail-cell-label">${c.label}</div><div class="inv-detail-cell-value">${c.value}</div></div>`).join('')}
      </div>
    </div>
    ${descHtml}
    ${metaHtml}`;

  loadAndRenderChart(p.id, 'weekly');
  $('#inv-pane-overview').addEventListener('click', (e) => {
    const btn = e.target.closest('.inv-chart-period');
    if (btn) loadAndRenderChart(p.id, btn.dataset.period);
  });

  // ── Pricing tab ──
  const sellingPrice = p.unit_price != null ? parseFloat(p.unit_price) : (p.unit_sell_price != null ? parseFloat(p.unit_sell_price) : null);
  const costPrice = p.cost_price != null ? parseFloat(p.cost_price) : null;
  const wholesalePrice = p.wholesale_price != null ? parseFloat(p.wholesale_price) : null;
  const profit = (sellingPrice != null && costPrice != null) ? (sellingPrice - costPrice) : null;
  const marginPct = (profit != null && costPrice > 0) ? ((profit / costPrice) * 100).toFixed(1) : null;

  const priceRows = [
    ['Cost Price', costPrice != null ? `<strong style="font-size:16px">${money(costPrice)}</strong>` : '<span class="inv-detail-none">—</span>'],
    ['Selling Price', sellingPrice != null ? `<strong style="font-size:18px;color:var(--accent)">${money(sellingPrice)}</strong>` : '<span class="inv-detail-none">—</span>'],
    ['Wholesale Price', wholesalePrice != null ? `<span style="color:#d97706;font-weight:600"><i class="fa-solid fa-tags"></i> ${money(wholesalePrice)}</span>` : '<span class="inv-detail-none">—</span>'],
    ['Profit', profit != null
      ? `<span style="font-weight:700;color:${profit >= 0 ? 'var(--accent)' : 'var(--danger)'}">${profit >= 0 ? '+' : ''}${money(profit)}${marginPct != null ? ` <span style="font-size:11px;opacity:.8">(${marginPct >= 0 ? '+' : ''}${marginPct}% margin)</span>` : ''}</span>`
      : '<span class="inv-detail-none">— set cost &amp; selling price</span>'],
  ];
  $('#inv-pane-pricing').innerHTML = `<div class="inv-section"><div class="inv-section-title"><i class="fa-solid fa-tag"></i> Pricing</div>
    <table class="inv-detail-table">${priceRows.map(([l, v]) => `<tr><td class="inv-dt-label">${l}</td><td class="inv-dt-val">${v}</td></tr>`).join('')}</table></div>`;

  // ── Stock tab ──
  const totalReceived = stockHistory.reduce((s, h) => s + parseFloat(h.quantity_received || 0), 0);
  const grnRemaining = stockHistory.reduce((s, h) => s + parseFloat(h.quantity_remaining || 0), 0);
  const displayStock = stock ?? '—';
  const stockRows = [
    ['Available Stock', displayStock !== '—' ? `<strong style="font-size:15px;color:var(--accent)">${Number(displayStock) % 1 === 0 ? Number(displayStock) : Number(displayStock).toFixed(3)}</strong>` : '—'],
    ['Low Stock Alert', p.low_stock_threshold ?? p.alert_quantity ?? '—'],
  ].filter(([, v]) => v != null);

  const stockNum = stock != null ? Number(stock) : null;
  const threshold = p.low_stock_threshold ?? p.alert_quantity ?? 5;
  const stockKpiClass = stockNum == null ? '' : stockNum <= 0 ? ' inv-stock-kpi--red' : stockNum <= threshold ? ' inv-stock-kpi--amber' : ' inv-stock-kpi--green';
  const stockDisp = stockNum != null ? (stockNum % 1 === 0 ? stockNum : stockNum.toFixed(3)) : '—';
  const stockSummaryHtml = `
    <div class="inv-stock-summary">
      <div class="inv-stock-kpi${stockKpiClass}"><div class="inv-stock-kpi-val">${stockDisp}</div><div class="inv-stock-kpi-label">Available Stock</div></div>
      <div class="inv-stock-kpi inv-stock-kpi--blue"><div class="inv-stock-kpi-val">${totalReceived % 1 === 0 ? totalReceived : totalReceived.toFixed(3)}</div><div class="inv-stock-kpi-label">Total Received</div></div>
      <div class="inv-stock-kpi inv-stock-kpi--gray"><div class="inv-stock-kpi-val">${grnRemaining % 1 === 0 ? grnRemaining : grnRemaining.toFixed(3)}</div><div class="inv-stock-kpi-label">Batches Remaining</div></div>
    </div>`;

  const batchHistoryHtml = stockHistory.length
    ? `<div class="inv-section"><div class="inv-section-title"><i class="fa-solid fa-layer-group"></i> Stock Receive History (${stockHistory.length})</div>
        ${stockHistory.map((h) => {
          const src = h.source_type || (h.grn_number ? 'grn' : 'opening');
          const srcLabel = src === 'opening' ? 'Opening Stock' : src === 'po' ? 'Purchase Order' : src === 'transfer' ? 'Stock Transfer' : 'Goods Receive';
          const date = h.received_at ? new Date(h.received_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
          const qtyIn = parseFloat(h.quantity_received || 0);
          const qtyLeft = parseFloat(h.quantity_remaining || 0);
          const ref = [h.po_number, h.grn_number, h.transfer_number, h.supplier_name].filter(Boolean).join(' · ');
          return `<div class="stock-batch-card">
            <span class="stock-batch-src">${esc(srcLabel)}</span>
            ${ref ? `<span class="stock-batch-ref">${esc(ref)}</span>` : ''}
            <span class="stock-batch-date">${esc(date)}</span>
            <div class="stock-batch-prices">
              <span>Qty: <b>${qtyLeft % 1 === 0 ? qtyLeft : qtyLeft.toFixed(2)}</b> / ${qtyIn % 1 === 0 ? qtyIn : qtyIn.toFixed(2)}</span>
              ${h.unit_cost != null ? `<span>Cost: <b>${money(h.unit_cost)}</b></span>` : ''}
              ${h.selling_unit_price != null ? `<span>Selling: <b>${money(h.selling_unit_price)}</b></span>` : ''}
              ${h.wholesale_unit_price != null ? `<span>Wholesale: <b>${money(h.wholesale_unit_price)}</b></span>` : ''}
            </div>
          </div>`;
        }).join('')}
      </div>`
    : '';

  $('#inv-pane-stock').innerHTML = `
    <div class="inv-section"><div class="inv-section-title"><i class="fa-solid fa-boxes-stacked"></i> Stock Information</div>
      <table class="inv-detail-table">${stockRows.map(([l, v]) => `<tr><td class="inv-dt-label">${esc(String(l))}</td><td class="inv-dt-val">${v}</td></tr>`).join('')}</table>
    </div>
    ${stockHistory.length ? stockSummaryHtml : ''}
    ${batchHistoryHtml}`;

  // ── Images tab ──
  $('#inv-pane-images').innerHTML = images.length
    ? `<div class="inv-section"><div class="inv-section-title"><i class="fa-solid fa-images"></i> Product Images (${images.length})</div>
        <div class="inv-images-grid">${images.map((img) => `<div class="inv-img-card"><img src="${esc(img.url || img.image_url || img)}" alt="Product image" loading="lazy"></div>`).join('')}</div></div>`
    : '<div class="inv-pane-empty"><i class="fa-regular fa-image"></i><p>No images uploaded</p></div>';

  // ── Variants tab ──
  const variants = p.variants || p.product_variants || [];
  $('#inv-pane-variants').innerHTML = variants.length
    ? `<div class="inv-section"><div class="inv-section-title"><i class="fa-solid fa-layer-group"></i> Variants (${variants.length})</div>
        <table class="inv-detail-table inv-variants-table"><thead><tr><th class="inv-dt-label">Variant</th><th class="inv-dt-val">SKU</th><th class="inv-dt-val">Price</th><th class="inv-dt-val">Stock</th><th class="inv-dt-val">Status</th></tr></thead>
        <tbody>${variants.map((v) => `<tr><td class="inv-dt-label">${esc(v.name || v.variant_name || '—')}</td><td class="inv-dt-val">${esc(v.sku || '—')}</td><td class="inv-dt-val">${v.price != null ? money(v.price) : '—'}</td><td class="inv-dt-val">${v.stock_quantity ?? v.stock ?? '—'}</td><td class="inv-dt-val"><span class="inv-badge ${v.is_active !== false ? 'inv-badge-green' : 'inv-badge-gray'}">${v.is_active !== false ? 'Active' : 'Inactive'}</span></td></tr>`).join('')}</tbody></table></div>`
    : '<div class="inv-pane-empty"><i class="fa-solid fa-layer-group"></i><p>No variants for this product</p></div>';

  // ── Delivery tab ──
  const deliveryMethods = Array.isArray(p.delivery_methods) ? p.delivery_methods : [];
  const deliveryTabBtn = $('#inv-tab-delivery');
  deliveryTabBtn.style.display = deliveryMethods.length ? '' : 'none';
  $('#inv-pane-delivery').innerHTML = deliveryMethods.length
    ? `<div class="inv-section"><div class="inv-section-title"><i class="fa-solid fa-truck"></i> Delivery Partners (${deliveryMethods.length})</div>
        <div class="inv-delivery-list">${deliveryMethods.map((m) => {
          const meta = _DELIVERY_PARTNERS_META[m.key] || { name: m.key, icon: 'fa-truck' };
          return `<div class="inv-delivery-row"><div class="inv-delivery-row-icon"><i class="fa-solid ${meta.icon}"></i></div><div class="inv-delivery-row-name">${esc(meta.name)}</div><div class="inv-delivery-row-price">${m.price != null ? money(m.price) : '—'}</div></div>`;
        }).join('')}</div></div>`
    : '<div class="inv-pane-empty"><i class="fa-solid fa-truck"></i><p>Courier delivery is not enabled for this product</p></div>';

  // ── Rental tab ──
  const rentalTabBtn = $('#inv-tab-rental');
  if (p.is_rental) {
    rentalTabBtn.style.display = '';
    const dailyRate = p.rental_daily_rate != null ? parseFloat(p.rental_daily_rate) : null;
    const maxDays = p.rental_max_days ?? null;
    const lateFeeMul = p.rental_late_fee_multiplier != null ? parseFloat(p.rental_late_fee_multiplier) : null;
    let lateFeeVal = '<span class="inv-detail-none">—</span>';
    if (lateFeeMul != null) {
      lateFeeVal = `${lateFeeMul}× daily rate`;
      if (dailyRate != null) lateFeeVal += ` <span style="color:var(--text-muted);font-size:12px">(${(dailyRate * lateFeeMul).toFixed(2)} per late day)</span>`;
    }
    const rentalRows = [
      ['Daily Rate', dailyRate != null ? money(dailyRate) : '<span class="inv-detail-none">—</span>'],
      ['Max Rental Days', maxDays != null ? maxDays : '<span class="inv-detail-none">—</span>'],
      ['Late Fee', lateFeeVal],
      ['Cleaning Required', p.rental_needs_cleaning ? '<span class="inv-badge inv-badge-amber"><i class="fa-solid fa-broom"></i> Required before next rental</span>' : '<span class="inv-badge inv-badge-green">Not required</span>'],
    ];
    $('#inv-pane-rental').innerHTML = `<div class="inv-section"><div class="inv-section-title"><i class="fa-solid fa-key"></i> Rental Terms</div>
      <table class="inv-detail-table">${rentalRows.map(([l, v]) => `<tr><td class="inv-dt-label">${l}</td><td class="inv-dt-val">${v}</td></tr>`).join('')}</table></div>`;
  } else {
    rentalTabBtn.style.display = 'none';
  }
}

function drawSalesChart(canvasId, labels, series) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  canvas.width = canvas.offsetWidth * dpr;
  canvas.height = canvas.offsetHeight * dpr;
  ctx.scale(dpr, dpr);
  const w = canvas.offsetWidth;
  const h = canvas.offsetHeight;

  const PAD = { top: 20, right: 12, bottom: 28, left: 40 };
  const chartW = w - PAD.left - PAD.right;
  const chartH = h - PAD.top - PAD.bottom;
  const colBar = 'rgba(22,163,74,0.65)';
  const colGrid = 'rgba(0,0,0,.07)';
  const colText = 'rgba(0,0,0,.4)';

  ctx.clearRect(0, 0, w, h);
  const maxVal = Math.max(...series, 1);
  const nice = (v) => (v === Math.floor(v) ? String(v) : v.toFixed(1));

  const yTicks = 4;
  ctx.font = '11px system-ui';
  ctx.textAlign = 'right';
  for (let i = 0; i <= yTicks; i++) {
    const val = (maxVal / yTicks) * i;
    const y = PAD.top + chartH - (chartH * i / yTicks);
    ctx.strokeStyle = colGrid;
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + chartW, y); ctx.stroke();
    ctx.fillStyle = colText;
    ctx.fillText(nice(val), PAD.left - 6, y + 4);
  }

  const n = labels.length || 1;
  const barW = Math.max(4, (chartW / n) * 0.55);
  const gap = chartW / n;
  series.forEach((val, i) => {
    const barH = chartH * (val / maxVal);
    const x = PAD.left + gap * i + (gap - barW) / 2;
    const y = PAD.top + chartH - barH;
    const r = Math.min(4, barW / 2);
    ctx.fillStyle = colBar;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + barW - r, y);
    ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
    ctx.lineTo(x + barW, y + barH);
    ctx.lineTo(x, y + barH);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
    ctx.fill();
  });

  const step = Math.ceil(n / 8);
  ctx.fillStyle = colText;
  ctx.textAlign = 'center';
  ctx.font = '10px system-ui';
  labels.forEach((lbl, i) => {
    if (i % step !== 0 && i !== n - 1) return;
    const x = PAD.left + gap * i + gap / 2;
    ctx.fillText(lbl, x, h - PAD.bottom + 16);
  });
}

async function loadAndRenderChart(productId, period) {
  const container = $('#inv-chart-container');
  if (!container) return;
  $$('.inv-chart-period').forEach((b) => b.classList.toggle('active', b.dataset.period === period));
  container.innerHTML = '<div class="inv-chart-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>';

  const res = await API.productSalesChart(productId, period);
  if (res.status !== 200) { container.innerHTML = ''; return; }
  const { labels = [], series = [], total = 0, subtitle = '' } = res.body?.data || {};
  container.innerHTML = `
    <div class="inv-chart-summary"><span class="inv-chart-total"><strong>${total % 1 === 0 ? total : total.toFixed(3)}</strong> units in this period</span><span>${esc(subtitle)}</span></div>
    <div class="inv-chart-wrap"><canvas id="inv-sales-canvas"></canvas></div>`;
  requestAnimationFrame(() => drawSalesChart('inv-sales-canvas', labels, series));
}

$('#inv-tabs')?.addEventListener('click', (e) => {
  const tab = e.target.closest('.inv-tab');
  if (!tab) return;
  $$('#inv-tabs .inv-tab').forEach((t) => t.classList.remove('active'));
  tab.classList.add('active');
  $$('.inv-tab-pane').forEach((p) => p.classList.remove('active'));
  $(`#inv-pane-${tab.dataset.tab}`).classList.add('active');
});

function closeProductDetail() {
  $('#product-detail-view').style.display = 'none';
  $('#product-list-view').style.display = 'block';
  _prodActiveId = null;
  _prodActiveData = null;
}
$('#inv-back-btn')?.addEventListener('click', closeProductDetail);
$('#prod-edit-btn')?.addEventListener('click', () => { if (_prodActiveId) _prodOpenModal(_prodActiveId); });
$('#prod-delete-btn')?.addEventListener('click', async () => {
  if (!_prodActiveId) return;
  const name = _prodActiveData?.name || 'this product';
  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
  const res = await API.deleteProduct(_prodActiveId);
  if (res.status !== 200) { showToast(res.body?.message || 'Could not delete product.', 'error'); return; }
  showToast('Product deleted.', 'success');
  closeProductDetail();
  loadProducts();
});

// ── Utils ───────────────────────────────────────────────────────────────
function debounce(fn, wait = 300) {
  let t;
  return () => { clearTimeout(t); t = setTimeout(fn, wait); };
}

// ── Init ────────────────────────────────────────────────────────────────
(async () => {
  await Promise.all([loadCategories(), loadAllCategories(), loadBrands(), loadUnits()]);
  loadProducts();
})();
