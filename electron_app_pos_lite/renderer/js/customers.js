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

function esc(s) {
  return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// ── Header + back/logout ────────────────────────────────────────────────
document.getElementById('back-btn').addEventListener('click', () => { window.location.href = 'dashboard.html'; });
document.getElementById('logout-btn').addEventListener('click', async () => { await window.electronAPI.logout(); });

(async () => {
  const cfg = await window.electronAPI.getConfig();
  document.getElementById('who-business').textContent = cfg.business_name || `#${cfg.business_id}`;
  document.getElementById('who-user').textContent = cfg.user?.name || cfg.user?.email || '—';
})();

// ── Category filter options ─────────────────────────────────────────────
async function loadCategories() {
  const res = await API.customerCategories();
  if (res.status !== 200) return;
  const cats = res.body.data || [];
  categoryFilter.innerHTML = '<option value="">All categories</option>' +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');

  const addSelect = document.getElementById('f-category');
  addSelect.innerHTML = '<option value="">None</option>' +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
}

// ── List ────────────────────────────────────────────────────────────────
function matchesTypeFilter(c) {
  const t = typeFilter.value;
  return !t || (c.customer_type || 'retail') === t;
}

function renderRows() {
  const visible = customers.filter(matchesTypeFilter);
  countPill.textContent = `${visible.length} customer${visible.length === 1 ? '' : 's'}`;

  if (!visible.length) {
    rowsEl.innerHTML = '<tr><td colspan="6" class="empty-state">No customers found.</td></tr>';
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
      <td><span class="type-badge ${c.customer_type || 'retail'}">${esc(c.customer_type || 'retail')}</span></td>
      <td>${esc(c.category_name) || '—'}</td>
      <td>${c.sales_count ?? 0}</td>
      <td>
        <div class="row-actions">
          <button data-action="view" title="View"><i class="fa-solid fa-eye"></i></button>
          <button data-action="delete" class="danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
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
  rowsEl.innerHTML = '<tr><td colspan="6" class="loading-state">Loading customers…</td></tr>';
  const res = await API.customers({ q: searchInput.value.trim(), categoryId: categoryFilter.value });
  if (res.status !== 200) {
    rowsEl.innerHTML = `<tr><td colspan="6" class="empty-state">Could not load customers (${res.body?.message || res.status}).</td></tr>`;
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
    addError.textContent = 'Name is required.';
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
  addSaveBtn.textContent = 'Saving…';
  try {
    const res = await API.createCustomer(payload);
    if (res.status !== 201) {
      const firstKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
      addError.textContent = firstKey ? res.body.errors[firstKey][0] : (res.body?.message || 'Could not save customer.');
      addError.classList.add('show');
      return;
    }
    closeModal('add-modal');
    showToast(`${res.body.data.name} added.`, 'success');
    loadCustomers();
  } finally {
    addSaveBtn.disabled = false;
    addSaveBtn.textContent = 'Save Customer';
  }
});

// ── View customer ───────────────────────────────────────────────────────
let viewingId = null;

async function openView(id) {
  viewingId = id;
  document.getElementById('view-name').textContent = 'Loading…';
  document.getElementById('view-body').innerHTML = '<div class="loading-state">Loading…</div>';
  openModal('view-modal');

  const res = await API.customer(id);
  if (res.status !== 200) {
    document.getElementById('view-body').innerHTML = `<div class="empty-state">Could not load customer (${res.body?.message || res.status}).</div>`;
    return;
  }

  const c = res.body.data;
  document.getElementById('view-name').textContent = c.name;

  const salesRows = (c.recent_sales || []).map((s) => `
    <div class="sales-list-item">
      <span>${esc(s.sale_number)} <span class="muted">· ${s.sold_at ? new Date(s.sold_at).toLocaleDateString() : ''}</span></span>
      <span>$${Number(s.total).toFixed(2)}</span>
    </div>`).join('') || '<div class="sales-list-item"><span class="muted">No sales yet.</span></div>';

  document.getElementById('view-body').innerHTML = `
    <div class="view-row"><span>Phone</span><span>${esc(c.phone) || '—'}</span></div>
    <div class="view-row"><span>Email</span><span>${esc(c.email) || '—'}</span></div>
    <div class="view-row"><span>Address</span><span>${esc(c.address) || '—'}</span></div>
    <div class="view-row"><span>Type</span><span>${esc(c.customer_type || 'retail')}</span></div>
    <div class="view-row"><span>Category</span><span>${esc(c.category_name) || '—'}</span></div>
    <div class="view-row"><span>Total sales</span><span>${c.sales_count ?? 0}</span></div>
    ${c.notes ? `<div class="view-row"><span>Notes</span><span>${esc(c.notes)}</span></div>` : ''}
    <div style="margin-top:6px;">
      <label style="font-size:12px;font-weight:600;color:var(--muted);">Recent sales</label>
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
  if (!confirm(`Delete ${label}? This cannot be undone.`)) return;

  const res = await API.deleteCustomer(id);
  if (res.status !== 200) {
    showToast(res.body?.message || 'Could not delete customer.', 'error');
    return;
  }
  if (fromModal) closeModal('view-modal');
  showToast(`${label} deleted.`, 'success');
  loadCustomers();
}

// ── Init ────────────────────────────────────────────────────────────────
loadCategories();
loadCustomers();
