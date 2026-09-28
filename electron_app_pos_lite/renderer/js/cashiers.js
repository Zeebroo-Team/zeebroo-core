'use strict';

const rowsEl = document.getElementById('cashier-rows');
const searchInput = document.getElementById('search-input');
const statusFilter = document.getElementById('status-filter');
const countPill = document.getElementById('count-pill');
const toastEl = document.getElementById('toast');

let cashiers = [];
let editingId = null;

function showToast(message, type = '') {
  toastEl.textContent = message;
  toastEl.className = `toast show ${type}`;
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => { toastEl.className = 'toast'; }, 3000);
}

function esc(s) {
  return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// ── Header (back button; account menu lives in js/navbar.js) ──────────────
document.getElementById('back-btn').addEventListener('click', () => { window.location.href = 'dashboard.html'; });


// ── List ────────────────────────────────────────────────────────────────
function matchesFilters(c) {
  const q = searchInput.value.trim().toLowerCase();
  const matchesSearch = !q || c.name.toLowerCase().includes(q) || c.username.toLowerCase().includes(q);
  const s = statusFilter.value;
  const matchesStatus = !s || (s === 'active' ? c.is_active : !c.is_active);
  return matchesSearch && matchesStatus;
}

function renderRows() {
  const visible = cashiers.filter(matchesFilters);
  countPill.textContent = t(visible.length === 1 ? '{n} cashier' : '{n} cashiers', { n: visible.length });

  if (!visible.length) {
    rowsEl.innerHTML = `<tr><td colspan="5" class="empty-state">${t('No cashiers found.')}</td></tr>`;
    return;
  }

  rowsEl.innerHTML = visible.map((c) => `
    <tr data-id="${c.id}">
      <td><div class="cash-name">${esc(c.name)}</div></td>
      <td>${esc(c.username)}</td>
      <td><span class="status-badge ${c.is_active ? 'active' : 'inactive'}">${c.is_active ? t('Active') : t('Inactive')}</span></td>
      <td>${c.created_at ? new Date(c.created_at).toLocaleDateString(i18n.locale) : '—'}</td>
      <td>
        <div class="row-actions">
          <button data-action="edit" title="${t('Edit')}"><i class="fa-solid fa-pen"></i></button>
          <button data-action="delete" class="danger" title="${t('Delete')}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>
    </tr>`).join('');

  rowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    tr.querySelector('[data-action="edit"]').addEventListener('click', () => openEdit(id));
    tr.querySelector('[data-action="delete"]').addEventListener('click', () => deleteCashier(id));
    tr.addEventListener('dblclick', () => openEdit(id));
  });
}

async function loadCashiers() {
  rowsEl.innerHTML = `<tr><td colspan="5" class="loading-state">${t('Loading cashiers…')}</td></tr>`;
  const res = await API.cashiers();
  if (res.status !== 200) {
    rowsEl.innerHTML = `<tr><td colspan="5" class="empty-state">${t('Could not load cashiers ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
    return;
  }
  cashiers = res.body.data || [];
  renderRows();
}

searchInput.addEventListener('input', renderRows);
statusFilter.addEventListener('change', renderRows);

// ── Modal helpers ───────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

document.querySelectorAll('[data-close]').forEach((btn) => {
  btn.addEventListener('click', () => closeModal(btn.dataset.close));
});
document.querySelectorAll('.modal-backdrop').forEach((bg) => {
  bg.addEventListener('click', (e) => { if (e.target === bg) closeModal(bg.id); });
});

// ── Add / Edit cashier ──────────────────────────────────────────────────
const cashierError = document.getElementById('cashier-error');
const cashierSaveBtn = document.getElementById('cashier-save-btn');
const cashierDeleteBtn = document.getElementById('cashier-delete-btn');
const activeRow = document.getElementById('f-active-row');
const passwordHint = document.getElementById('f-password-hint');
const passwordLabel = document.getElementById('f-password-label');

document.getElementById('add-btn').addEventListener('click', () => {
  editingId = null;
  document.getElementById('cashier-modal-title').textContent = t('Add Cashier');
  document.getElementById('f-name').value = '';
  document.getElementById('f-username').value = '';
  document.getElementById('f-password').value = '';
  document.getElementById('f-active').checked = true;
  passwordLabel.textContent = t('Password *');
  passwordHint.style.display = 'none';
  activeRow.style.display = 'none';
  cashierDeleteBtn.style.display = 'none';
  cashierError.classList.remove('show');
  openModal('cashier-modal');
  document.getElementById('f-name').focus();
});

function openEdit(id) {
  const c = cashiers.find((x) => x.id === id);
  if (!c) return;
  editingId = id;
  document.getElementById('cashier-modal-title').textContent = t('Edit Cashier');
  document.getElementById('f-name').value = c.name;
  document.getElementById('f-username').value = c.username;
  document.getElementById('f-password').value = '';
  document.getElementById('f-active').checked = !!c.is_active;
  passwordLabel.textContent = t('Password');
  passwordHint.style.display = 'block';
  activeRow.style.display = 'flex';
  cashierDeleteBtn.style.display = 'inline-flex';
  cashierError.classList.remove('show');
  openModal('cashier-modal');
  document.getElementById('f-name').focus();
}

cashierSaveBtn.addEventListener('click', async () => {
  const name = document.getElementById('f-name').value.trim();
  const username = document.getElementById('f-username').value.trim();
  const password = document.getElementById('f-password').value;

  if (!name) {
    cashierError.textContent = t('Name is required.');
    cashierError.classList.add('show');
    return;
  }
  if (!username) {
    cashierError.textContent = t('Username is required.');
    cashierError.classList.add('show');
    return;
  }
  if (!editingId && !password) {
    cashierError.textContent = t('Password is required.');
    cashierError.classList.add('show');
    return;
  }

  cashierSaveBtn.disabled = true;
  cashierSaveBtn.textContent = t('Saving…');
  try {
    let res;
    if (editingId) {
      const payload = { name, username, is_active: document.getElementById('f-active').checked };
      if (password) payload.password = password;
      res = await API.updateCashier(editingId, payload);
    } else {
      res = await API.createCashier({ name, username, password });
    }

    if (res.status !== 200 && res.status !== 201) {
      const firstKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
      cashierError.textContent = t(firstKey ? res.body.errors[firstKey][0] : (res.body?.message || 'Could not save cashier.'));
      cashierError.classList.add('show');
      return;
    }
    closeModal('cashier-modal');
    showToast(t(editingId ? '{name} updated.' : '{name} added.', { name: res.body.data.name }), 'success');
    loadCashiers();
  } finally {
    cashierSaveBtn.disabled = false;
    cashierSaveBtn.textContent = t('Save Cashier');
  }
});

cashierDeleteBtn.addEventListener('click', () => {
  if (editingId !== null) deleteCashier(editingId, true);
});

// ── Delete ──────────────────────────────────────────────────────────────
async function deleteCashier(id, fromModal = false) {
  const cashier = cashiers.find((c) => c.id === id);
  const label = cashier ? cashier.name : `#${id}`;
  if (!await zeebrooConfirm(t('Delete {label}? This cannot be undone.', { label }), { okText: t('Delete'), tone: 'danger' })) return;

  const res = await API.deleteCashier(id);
  if (res.status !== 200) {
    showToast(t(res.body?.message || 'Could not delete cashier.'), 'error');
    return;
  }
  if (fromModal) closeModal('cashier-modal');
  showToast(t('{label} deleted.', { label }), 'success');
  loadCashiers();
}

// ── Init ────────────────────────────────────────────────────────────────
loadCashiers();
