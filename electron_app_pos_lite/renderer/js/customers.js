'use strict';

const rowsEl = document.getElementById('customer-rows');
const searchInput = document.getElementById('search-input');
const categoryFilter = document.getElementById('category-filter');
const typeFilter = document.getElementById('type-filter');
const countPill = document.getElementById('count-pill');
const toastEl = document.getElementById('toast');

let customers = [];
let searchDebounce = null;

function showToast(message, type = '') {
  toastEl.textContent = message;
  toastEl.className = `toast show ${type}`;
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => { toastEl.className = 'toast'; }, 3000);
}

function typeLabel(type) { return t(type === 'wholesale' ? 'Wholesale' : 'Retail'); }

function esc(s) {
  return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// ── Header (back button; account menu lives in js/navbar.js) ──────────────
document.getElementById('back-btn').addEventListener('click', () => { window.location.href = 'dashboard.html'; });


// ── Category filter options ─────────────────────────────────────────────
async function loadCategories(selectId = null) {
  const res = await API.customerCategories();
  if (res.status !== 200) return;
  const cats = res.body.data || [];
  categoryFilter.innerHTML = `<option value="">${t('All categories')}</option>` +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');

  const addSelect = document.getElementById('f-category');
  addSelect.innerHTML = `<option value="">${t('None')}</option>` +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
  if (selectId) addSelect.value = String(selectId);
}

// ── New category (inline, from Add Customer modal) ────────────────────────
const categoryError = document.getElementById('category-error');
const categorySaveBtn = document.getElementById('category-save-btn');

document.getElementById('add-category-inline-btn').addEventListener('click', () => {
  document.getElementById('cat-name').value = '';
  document.getElementById('cat-description').value = '';
  categoryError.classList.remove('show');
  openModal('category-modal');
  document.getElementById('cat-name').focus();
});

categorySaveBtn.addEventListener('click', async () => {
  const name = document.getElementById('cat-name').value.trim();
  if (!name) {
    categoryError.textContent = t('Category name is required.');
    categoryError.classList.add('show');
    return;
  }

  const payload = {
    name,
    description: document.getElementById('cat-description').value.trim() || null,
  };

  categorySaveBtn.disabled = true;
  categorySaveBtn.textContent = t('Saving…');
  try {
    const res = await API.createCustomerCategory(payload);
    if (res.status !== 201) {
      const firstKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
      categoryError.textContent = t(firstKey ? res.body.errors[firstKey][0] : (res.body?.message || 'Could not save category.'));
      categoryError.classList.add('show');
      return;
    }
    closeModal('category-modal');
    showToast(t('{name} added.', { name: res.body.data.name }), 'success');
    await loadCategories(res.body.data.id);
  } finally {
    categorySaveBtn.disabled = false;
    categorySaveBtn.textContent = t('Save Category');
  }
});

// ── List ────────────────────────────────────────────────────────────────
function matchesTypeFilter(c) {
  const type = typeFilter.value;
  return !type || (c.customer_type || 'retail') === type;
}

function renderRows() {
  const visible = customers.filter(matchesTypeFilter);
  countPill.textContent = t(visible.length === 1 ? '{n} customer' : '{n} customers', { n: visible.length });

  if (!visible.length) {
    rowsEl.innerHTML = `<tr><td colspan="6" class="empty-state">${t('No customers found.')}</td></tr>`;
    return;
  }

  rowsEl.innerHTML = visible.map((c) => `
    <tr data-id="${c.id}">
      <td>
        <div class="cust-name">${esc(c.name)}</div>
        ${c.address ? `<div class="cust-sub">${esc(c.address)}</div>` : ''}
      </td>
      <td>
        <div>${esc(c.phone) || '—'}</div>
        ${c.email ? `<div class="cust-sub">${esc(c.email)}</div>` : ''}
      </td>
      <td><span class="type-badge ${c.customer_type || 'retail'}">${typeLabel(c.customer_type)}</span></td>
      <td>${esc(c.category_name) || '—'}</td>
      <td>${c.sales_count ?? 0}</td>
      <td>
        <div class="row-actions">
          <button data-action="view" title="${t('View')}"><i class="fa-solid fa-eye"></i></button>
          <button data-action="delete" class="danger" title="${t('Delete')}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>
    </tr>`).join('');

  rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    tr.querySelector('[data-action="view"]').addEventListener('click', () => openView(id));
    tr.querySelector('[data-action="delete"]').addEventListener('click', () => deleteCustomer(id));
    tr.addEventListener('dblclick', () => openView(id));
  });
}

async function loadCustomers() {
  rowsEl.innerHTML = `<tr><td colspan="6" class="loading-state">${t('Loading customers…')}</td></tr>`;
  const res = await API.customers({ q: searchInput.value.trim(), categoryId: categoryFilter.value });
  if (res.status !== 200) {
    rowsEl.innerHTML = `<tr><td colspan="6" class="empty-state">${t('Could not load customers ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
    return;
  }
  customers = res.body.data || [];
  renderRows();
}

searchInput.addEventListener('input', () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(loadCustomers, 300);
});
categoryFilter.addEventListener('change', loadCustomers);
typeFilter.addEventListener('change', renderRows);

// ── Modal helpers ───────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

document.querySelectorAll('[data-close]').forEach((btn) => {
  btn.addEventListener('click', () => closeModal(btn.dataset.close));
});
document.querySelectorAll('.modal-backdrop').forEach((bg) => {
  bg.addEventListener('click', (e) => { if (e.target === bg) closeModal(bg.id); });
});

// ── Add customer ────────────────────────────────────────────────────────
const addError = document.getElementById('add-error');
const addSaveBtn = document.getElementById('add-save-btn');

document.getElementById('add-btn').addEventListener('click', () => {
  ['f-name', 'f-phone', 'f-email', 'f-address', 'f-notes'].forEach((id) => { document.getElementById(id).value = ''; });
  document.getElementById('f-type').value = 'retail';
  document.getElementById('f-category').value = '';
  addError.classList.remove('show');
  openModal('add-modal');
  document.getElementById('f-name').focus();
});

addSaveBtn.addEventListener('click', async () => {
  const name = document.getElementById('f-name').value.trim();
  if (!name) {
    addError.textContent = t('Name is required.');
    addError.classList.add('show');
    return;
  }

  const payload = {
    name,
    phone: document.getElementById('f-phone').value.trim() || null,
    email: document.getElementById('f-email').value.trim() || null,
    address: document.getElementById('f-address').value.trim() || null,
    notes: document.getElementById('f-notes').value.trim() || null,
    customer_type: document.getElementById('f-type').value,
    customer_category_id: document.getElementById('f-category').value || null,
  };

  addSaveBtn.disabled = true;
  addSaveBtn.textContent = t('Saving…');
  try {
    const res = await API.createCustomer(payload);
    if (res.status !== 201) {
      const firstKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
      addError.textContent = t(firstKey ? res.body.errors[firstKey][0] : (res.body?.message || 'Could not save customer.'));
      addError.classList.add('show');
      return;
    }
    closeModal('add-modal');
    showToast(t('{name} added.', { name: res.body.data.name }), 'success');
    loadCustomers();
  } finally {
    addSaveBtn.disabled = false;
    addSaveBtn.textContent = t('Save Customer');
  }
});

// ── View customer ───────────────────────────────────────────────────────
let viewingId = null;

async function openView(id) {
  viewingId = id;
  document.getElementById('view-name').textContent = t('Loading…');
  document.getElementById('view-body').innerHTML = `<div class="loading-state">${t('Loading…')}</div>`;
  openModal('view-modal');

  const res = await API.customer(id);
  if (res.status !== 200) {
    document.getElementById('view-body').innerHTML = `<div class="empty-state">${t('Could not load customer ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
    return;
  }

  const c = res.body.data;
  document.getElementById('view-name').textContent = c.name;

  const salesRows = (c.recent_sales || []).map((s) => `
    <div class="sales-list-item">
      <span>${esc(s.sale_number)} <span class="muted">· ${s.sold_at ? new Date(s.sold_at).toLocaleDateString(i18n.locale) : ''}</span></span>
      <span>$${Number(s.total).toFixed(2)}</span>
    </div>`).join('') || `<div class="sales-list-item"><span class="muted">${t('No sales yet.')}</span></div>`;

  document.getElementById('view-body').innerHTML = `
    <div class="view-row"><span>${t('Phone')}</span><span>${esc(c.phone) || '—'}</span></div>
    <div class="view-row"><span>${t('Email')}</span><span>${esc(c.email) || '—'}</span></div>
    <div class="view-row"><span>${t('Address')}</span><span>${esc(c.address) || '—'}</span></div>
    <div class="view-row"><span>${t('Type')}</span><span>${typeLabel(c.customer_type)}</span></div>
    <div class="view-row"><span>${t('Category')}</span><span>${esc(c.category_name) || '—'}</span></div>
    <div class="view-row"><span>${t('Total sales')}</span><span>${c.sales_count ?? 0}</span></div>
    ${c.notes ? `<div class="view-row"><span>${t('Notes')}</span><span>${esc(c.notes)}</span></div>` : ''}
    <div style="margin-top:6px;">
      <label style="font-size:12px;font-weight:600;color:var(--muted);">${t('Recent sales')}</label>
      <div class="sales-list">${salesRows}</div>
    </div>
  `;
}

document.getElementById('view-delete-btn').addEventListener('click', () => {
  if (viewingId !== null) deleteCustomer(viewingId, true);
});

// ── Delete ──────────────────────────────────────────────────────────────
async function deleteCustomer(id, fromModal = false) {
  const customer = customers.find((c) => c.id === id);
  const label = customer ? customer.name : `#${id}`;
  if (!await zeebrooConfirm(t('Delete {label}? This cannot be undone.', { label }), { okText: t('Delete'), tone: 'danger' })) return;

  const res = await API.deleteCustomer(id);
  if (res.status !== 200) {
    showToast(t(res.body?.message || 'Could not delete customer.'), 'error');
    return;
  }
  if (fromModal) closeModal('view-modal');
  showToast(t('{label} deleted.', { label }), 'success');
  loadCustomers();
}

// ── Init ────────────────────────────────────────────────────────────────
loadCategories();
loadCustomers();
