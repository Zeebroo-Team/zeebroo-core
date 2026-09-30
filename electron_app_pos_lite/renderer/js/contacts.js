'use strict';

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

let posSettings = {};

function money(n) {
  const amount = (Number(n) || 0).toFixed(2);
  const currency = (posSettings.currency || 'LKR').toUpperCase();
  return posSettings.currency_position === 'before' ? `${currency} ${amount}` : `${amount} ${currency}`;
}

(async () => {
  const res = await API.settingsGet();
  if (res.status === 200) posSettings = res.body?.data || {};
})();

function firstErrorMessage(res, fallback) {
  const firstKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
  return t(firstKey ? res.body.errors[firstKey][0] : (res.body?.message || fallback));
}

// ── Header (back button; account menu lives in js/navbar.js) ──────────────
document.getElementById('back-btn').addEventListener('click', () => { window.location.href = 'dashboard.html'; });

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

/* ════════════════════════════════════════════════════════════════════
   CUSTOMERS
   ════════════════════════════════════════════════════════════════════ */

const custRowsEl = document.getElementById('cust-rows');
const custSearchInput = document.getElementById('cust-search-input');
const custCategoryFilter = document.getElementById('cust-category-filter');
const custTypeFilter = document.getElementById('cust-type-filter');
const custCountPill = document.getElementById('cust-count-pill');
const custSubnavCount = document.getElementById('cust-subnav-count');

let customers = [];
let custSearchDebounce = null;
let custEditingId = null; // null = creating

function typeLabel(type) { return t(type === 'wholesale' ? 'Wholesale' : 'Retail'); }

async function loadCustomerCategories(selectId = null) {
  const res = await API.customerCategories();
  if (res.status !== 200) return;
  const cats = res.body.data || [];
  custCategoryFilter.innerHTML = `<option value="">${t('All categories')}</option>` +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');

  const addSelect = document.getElementById('cf-category');
  addSelect.innerHTML = `<option value="">${t('None')}</option>` +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
  if (selectId) addSelect.value = String(selectId);
}

// New customer category (inline, from Add/Edit Customer modal)
const custCategoryError = document.getElementById('cust-category-error');
const custCategorySaveBtn = document.getElementById('cust-category-save-btn');

document.getElementById('cust-add-category-inline-btn').addEventListener('click', () => {
  document.getElementById('cust-cat-name').value = '';
  document.getElementById('cust-cat-description').value = '';
  custCategoryError.classList.remove('show');
  openModal('cust-category-modal');
  document.getElementById('cust-cat-name').focus();
});

custCategorySaveBtn.addEventListener('click', async () => {
  const name = document.getElementById('cust-cat-name').value.trim();
  if (!name) {
    custCategoryError.textContent = t('Category name is required.');
    custCategoryError.classList.add('show');
    return;
  }

  const payload = {
    name,
    description: document.getElementById('cust-cat-description').value.trim() || null,
  };

  custCategorySaveBtn.disabled = true;
  custCategorySaveBtn.textContent = t('Saving…');
  try {
    const res = await API.createCustomerCategory(payload);
    if (res.status !== 201) {
      custCategoryError.textContent = firstErrorMessage(res, 'Could not save category.');
      custCategoryError.classList.add('show');
      return;
    }
    closeModal('cust-category-modal');
    showToast(t('{name} added.', { name: res.body.data.name }), 'success');
    await loadCustomerCategories(res.body.data.id);
  } finally {
    custCategorySaveBtn.disabled = false;
    custCategorySaveBtn.textContent = t('Save Category');
  }
});

function matchesCustTypeFilter(c) {
  const type = custTypeFilter.value;
  return !type || (c.customer_type || 'retail') === type;
}

function renderCustRows() {
  const visible = customers.filter(matchesCustTypeFilter);
  custCountPill.textContent = t(visible.length === 1 ? '{n} customer' : '{n} customers', { n: visible.length });
  custSubnavCount.textContent = t('{n} customers', { n: customers.length });

  if (!visible.length) {
    custRowsEl.innerHTML = `<tr><td colspan="6" class="empty-state">${t('No customers found.')}</td></tr>`;
    return;
  }

  custRowsEl.innerHTML = visible.map((c) => `
    <tr data-id="${c.id}">
      <td>
        <div class="row-name">${esc(c.name)}</div>
        ${c.address ? `<div class="row-sub">${esc(c.address)}</div>` : ''}
      </td>
      <td>
        <div>${esc(c.phone) || '—'}</div>
        ${c.email ? `<div class="row-sub">${esc(c.email)}</div>` : ''}
      </td>
      <td><span class="type-badge ${c.customer_type || 'retail'}">${typeLabel(c.customer_type)}</span></td>
      <td>${esc(c.category_name) || '—'}</td>
      <td>${c.sales_count ?? 0}</td>
      <td>
        <div class="row-actions">
          <button data-action="view" title="${t('View')}"><i class="fa-solid fa-eye"></i></button>
          <button data-action="edit" title="${t('Edit')}"><i class="fa-solid fa-pen"></i></button>
          <button data-action="delete" class="danger" title="${t('Delete')}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>
    </tr>`).join('');

  custRowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    tr.querySelector('[data-action="view"]').addEventListener('click', () => openCustView(id));
    tr.querySelector('[data-action="edit"]').addEventListener('click', () => openCustEdit(id));
    tr.querySelector('[data-action="delete"]').addEventListener('click', () => deleteCustomer(id));
    tr.addEventListener('dblclick', () => openCustView(id));
  });
}

async function loadCustomers() {
  custRowsEl.innerHTML = `<tr><td colspan="6" class="loading-state">${t('Loading customers…')}</td></tr>`;
  const res = await API.customers({ q: custSearchInput.value.trim(), categoryId: custCategoryFilter.value });
  if (res.status !== 200) {
    custRowsEl.innerHTML = `<tr><td colspan="6" class="empty-state">${t('Could not load customers ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
    return;
  }
  customers = res.body.data || [];
  renderCustRows();
}

custSearchInput.addEventListener('input', () => {
  clearTimeout(custSearchDebounce);
  custSearchDebounce = setTimeout(loadCustomers, 300);
});
custCategoryFilter.addEventListener('change', loadCustomers);
custTypeFilter.addEventListener('change', renderCustRows);

// ── Add / Edit customer ────────────────────────────────────────────────
const custError = document.getElementById('cust-error');
const custSaveBtn = document.getElementById('cust-save-btn');

function openCustAdd() {
  custEditingId = null;
  document.getElementById('cust-modal-title').textContent = t('Add Customer');
  custSaveBtn.textContent = t('Save Customer');
  ['cf-name', 'cf-phone', 'cf-email', 'cf-address', 'cf-notes'].forEach((id) => { document.getElementById(id).value = ''; });
  document.getElementById('cf-type').value = 'retail';
  document.getElementById('cf-category').value = '';
  custError.classList.remove('show');
  openModal('cust-modal');
  document.getElementById('cf-name').focus();
}

function fillCustForm(c) {
  document.getElementById('cf-name').value = c.name || '';
  document.getElementById('cf-phone').value = c.phone || '';
  document.getElementById('cf-email').value = c.email || '';
  document.getElementById('cf-address').value = c.address || '';
  document.getElementById('cf-notes').value = c.notes || '';
  document.getElementById('cf-type').value = c.customer_type || 'retail';
  document.getElementById('cf-category').value = c.customer_category_id || '';
}

async function openCustEdit(id) {
  const res = await API.customer(id);
  if (res.status !== 200) {
    showToast(t(res.body?.message || 'Could not load customer.'), 'error');
    return;
  }
  custEditingId = id;
  document.getElementById('cust-modal-title').textContent = t('Edit Customer');
  custSaveBtn.textContent = t('Save Changes');
  fillCustForm(res.body.data);
  custError.classList.remove('show');
  openModal('cust-modal');
  document.getElementById('cf-name').focus();
}

document.getElementById('cust-add-btn').addEventListener('click', openCustAdd);

custSaveBtn.addEventListener('click', async () => {
  const name = document.getElementById('cf-name').value.trim();
  if (!name) {
    custError.textContent = t('Name is required.');
    custError.classList.add('show');
    return;
  }

  const payload = {
    name,
    phone: document.getElementById('cf-phone').value.trim() || null,
    email: document.getElementById('cf-email').value.trim() || null,
    address: document.getElementById('cf-address').value.trim() || null,
    notes: document.getElementById('cf-notes').value.trim() || null,
    customer_type: document.getElementById('cf-type').value,
    customer_category_id: document.getElementById('cf-category').value || null,
  };

  custSaveBtn.disabled = true;
  custSaveBtn.textContent = t('Saving…');
  try {
    const res = custEditingId
      ? await API.updateCustomer(custEditingId, payload)
      : await API.createCustomer(payload);
    const okStatus = custEditingId ? 200 : 201;
    if (res.status !== okStatus) {
      custError.textContent = firstErrorMessage(res, 'Could not save customer.');
      custError.classList.add('show');
      return;
    }
    closeModal('cust-modal');
    showToast(t('{name} saved.', { name: res.body.data.name }), 'success');
    loadCustomers();
  } finally {
    custSaveBtn.disabled = false;
    custSaveBtn.textContent = custEditingId ? t('Save Changes') : t('Save Customer');
  }
});

// ── View customer ───────────────────────────────────────────────────────
let custViewingId = null;

async function openCustView(id) {
  custViewingId = id;
  document.getElementById('cust-view-name').textContent = t('Loading…');
  document.getElementById('cust-view-body').innerHTML = `<div class="loading-state">${t('Loading…')}</div>`;
  openModal('cust-view-modal');

  const res = await API.customer(id);
  if (res.status !== 200) {
    document.getElementById('cust-view-body').innerHTML = `<div class="empty-state">${t('Could not load customer ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
    return;
  }

  const c = res.body.data;
  document.getElementById('cust-view-name').textContent = c.name;

  const salesRows = (c.recent_sales || []).map((s) => `
    <div class="mini-list-item">
      <span>${esc(s.sale_number)} <span class="muted">· ${s.sold_at ? new Date(s.sold_at).toLocaleDateString(i18n.locale) : ''}</span></span>
      <span>${money(s.total)}</span>
    </div>`).join('') || `<div class="mini-list-item"><span class="muted">${t('No sales yet.')}</span></div>`;

  document.getElementById('cust-view-body').innerHTML = `
    <div class="view-row"><span>${t('Phone')}</span><span>${esc(c.phone) || '—'}</span></div>
    <div class="view-row"><span>${t('Email')}</span><span>${esc(c.email) || '—'}</span></div>
    <div class="view-row"><span>${t('Address')}</span><span>${esc(c.address) || '—'}</span></div>
    <div class="view-row"><span>${t('Type')}</span><span>${typeLabel(c.customer_type)}</span></div>
    <div class="view-row"><span>${t('Category')}</span><span>${esc(c.category_name) || '—'}</span></div>
    <div class="view-row"><span>${t('Total sales')}</span><span>${c.sales_count ?? 0}</span></div>
    ${c.notes ? `<div class="view-row"><span>${t('Notes')}</span><span>${esc(c.notes)}</span></div>` : ''}
    <div style="margin-top:6px;">
      <label style="font-size:12px;font-weight:600;color:var(--muted);">${t('Recent sales')}</label>
      <div class="mini-list">${salesRows}</div>
    </div>
  `;
}

document.getElementById('cust-view-edit-btn').addEventListener('click', () => {
  if (custViewingId !== null) { closeModal('cust-view-modal'); openCustEdit(custViewingId); }
});
document.getElementById('cust-view-delete-btn').addEventListener('click', () => {
  if (custViewingId !== null) deleteCustomer(custViewingId, true);
});

// ── Delete customer ─────────────────────────────────────────────────────
async function deleteCustomer(id, fromModal = false) {
  const customer = customers.find((c) => c.id === id);
  const label = customer ? customer.name : `#${id}`;
  if (!await zeebrooConfirm(t('Delete {label}? This cannot be undone.', { label }), { okText: t('Delete'), tone: 'danger' })) return;

  const res = await API.deleteCustomer(id);
  if (res.status !== 200) {
    showToast(t(res.body?.message || 'Could not delete customer.'), 'error');
    return;
  }
  if (fromModal) closeModal('cust-view-modal');
  showToast(t('{label} deleted.', { label }), 'success');
  loadCustomers();
}

/* ════════════════════════════════════════════════════════════════════
   CSV IMPORT — shared line parser (handles quoted commas)
   ════════════════════════════════════════════════════════════════════ */
function csvParseLine(line) {
  const cells = [];
  let cur = '', inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (ch === '"') {
      if (inQuotes && line[i + 1] === '"') { cur += '"'; i++; }
      else inQuotes = !inQuotes;
    } else if (ch === ',' && !inQuotes) { cells.push(cur); cur = ''; }
    else cur += ch;
  }
  cells.push(cur);
  return cells;
}

/* ── Customer CSV import ─────────────────────────────────────────────── */
const CUST_CSV_SAMPLE = [
  'name,phone,email,address,notes,category,customer_type',
  'John Silva,0771234567,john.silva@example.com,"123 Main St, Colombo",Prefers SMS updates,VIP,retail',
  'Nimal Perera,0719876543,nimal.perera@example.com,"45 Galle Rd, Kandy",,Regular,retail',
  'Priya Fernando,0765555555,,,"Wholesale buyer",Wholesale,wholesale',
].join('\n');

const CUST_CSV_COL_MAP = {
  'name': 'name', 'customer name': 'name', 'customer': 'name', 'full name': 'name',
  'phone': 'phone', 'phone number': 'phone', 'mobile': 'phone', 'contact': 'phone', 'contact number': 'phone',
  'email': 'email', 'email address': 'email',
  'address': 'address',
  'notes': 'notes', 'note': 'notes', 'remarks': 'notes',
  'category': 'category', 'customer category': 'category',
  'customer_type': 'customer_type', 'type': 'customer_type', 'customer type': 'customer_type',
};

let custCsvValidRows = [];

function custCsvParseFile(text) {
  const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').filter((l) => l.trim());
  if (lines.length < 2) return { rows: [], error: t('File must have a header row and at least one data row.') };

  const headers = csvParseLine(lines[0]).map((h) => h.trim().toLowerCase());
  const colMap = {};
  headers.forEach((h, i) => { if (CUST_CSV_COL_MAP[h]) colMap[CUST_CSV_COL_MAP[h]] = i; });

  if (colMap['name'] === undefined) {
    return { rows: [], error: t('Missing required column: "name" (or "customer", "full name").') };
  }

  const rows = [];
  for (let i = 1; i < lines.length; i++) {
    const cells = csvParseLine(lines[i]);
    const row = {};
    Object.entries(colMap).forEach(([key, ci]) => { row[key] = (cells[ci] || '').trim(); });
    row._rowNum = i + 1;
    row._errors = [];

    if (!row.name) row._errors.push(t('Name is required'));
    if (row.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(row.email)) row._errors.push(t('Invalid email'));
    if (row.customer_type && !['retail', 'wholesale'].includes(row.customer_type.toLowerCase())) {
      row._errors.push(t('customer_type must be "retail" or "wholesale"'));
    } else {
      row.customer_type = (row.customer_type || 'retail').toLowerCase();
    }

    rows.push(row);
  }
  return { rows };
}

function custCsvSetStep(n) {
  [1, 2, 3].forEach((i) => {
    document.getElementById(`cust-csv-step-${i}`).style.display = i === n ? '' : 'none';
    document.getElementById(`cust-csv-footer-${i}`).style.display = i === n ? 'flex' : 'none';
    const ind = document.getElementById(`cust-csv-step-ind-${i}`);
    ind.classList.toggle('active', i === n);
    ind.classList.toggle('done', i < n);
  });
}

function custCsvShowPreview(rows) {
  const valid = rows.filter((r) => !r._errors.length);
  const bad = rows.filter((r) => r._errors.length);
  custCsvValidRows = valid;

  document.getElementById('cust-csv-preview-summary').innerHTML = `
    <b>${t('{n} rows found', { n: rows.length })}</b>
    <span class="csv-preview-badge ok"><i class="fa-solid fa-circle-check"></i> ${t('{n} valid', { n: valid.length })}</span>
    ${bad.length ? `<span class="csv-preview-badge error"><i class="fa-solid fa-triangle-exclamation"></i> ${t('{n} with errors', { n: bad.length })}</span>` : ''}
    ${rows.length > 100 ? `<span class="csv-preview-badge warn"><i class="fa-solid fa-eye"></i> ${t('Showing first 100 rows')}</span>` : ''}`;

  const display = rows.slice(0, 100);
  document.getElementById('cust-csv-preview-thead').innerHTML = `<tr>
    <th>#</th><th>${t('Name')}</th><th>${t('Phone')}</th><th>${t('Email')}</th><th>${t('Address')}</th>
    <th>${t('Category')}</th><th>${t('Type')}</th><th>${t('Status')}</th>
  </tr>`;
  document.getElementById('cust-csv-preview-tbody').innerHTML = display.map((r) => {
    const isErr = r._errors.length > 0;
    const status = isErr
      ? `<span class="csv-row-error-msg"><i class="fa-solid fa-triangle-exclamation"></i> ${esc(r._errors.join('; '))}</span>`
      : `<span style="color:var(--success);font-size:11px"><i class="fa-solid fa-circle-check"></i> ${t('OK')}</span>`;
    return `<tr class="${isErr ? 'csv-row-error' : ''}">
      <td style="color:var(--muted)">${r._rowNum}</td>
      <td><strong>${esc(r.name) || '—'}</strong></td>
      <td style="color:var(--muted)">${esc(r.phone) || '—'}</td>
      <td style="color:var(--muted)">${esc(r.email) || '—'}</td>
      <td style="color:var(--muted)">${esc(r.address) || '—'}</td>
      <td>${esc(r.category) || '—'}</td>
      <td>${typeLabel(r.customer_type)}</td>
      <td>${status}</td>
    </tr>`;
  }).join('');

  document.getElementById('cust-csv-import-count').textContent = valid.length;
  document.getElementById('cust-csv-import-btn').disabled = valid.length === 0;
  custCsvSetStep(2);
}

function custCsvReset() {
  custCsvValidRows = [];
  document.getElementById('cust-csv-file-input').value = '';
  custCsvSetStep(1);
}

function custCsvOpenModal() {
  custCsvReset();
  openModal('cust-csv-modal');
}

function custCsvCloseModal() {
  closeModal('cust-csv-modal');
  custCsvReset();
}

function custCsvDownloadSample() {
  const blob = new Blob([CUST_CSV_SAMPLE], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'customers-sample.csv';
  a.click();
  URL.revokeObjectURL(url);
}

function custCsvHandleFile(file) {
  if (!file || !file.name.match(/\.csv$/i)) { showToast(t('Please select a .csv file'), 'error'); return; }
  const reader = new FileReader();
  reader.onload = (e) => {
    const { rows, error } = custCsvParseFile(e.target.result);
    if (error) { showToast(error, 'error'); return; }
    if (rows.length > 500) showToast(t('CSV has {n} rows. Only the first 500 will be imported.', { n: rows.length }), 'error');
    custCsvShowPreview(rows.slice(0, 500));
  };
  reader.readAsText(file);
}

const custCsvDz = document.getElementById('cust-csv-dropzone');
custCsvDz.addEventListener('dragover', (e) => { e.preventDefault(); custCsvDz.classList.add('drag-over'); });
custCsvDz.addEventListener('dragleave', () => custCsvDz.classList.remove('drag-over'));
custCsvDz.addEventListener('drop', (e) => {
  e.preventDefault();
  custCsvDz.classList.remove('drag-over');
  custCsvHandleFile(e.dataTransfer.files[0]);
});
document.getElementById('cust-csv-file-input').addEventListener('change', (e) => custCsvHandleFile(e.target.files[0]));
document.getElementById('cust-csv-download-sample').addEventListener('click', custCsvDownloadSample);
document.getElementById('cust-csv-close').addEventListener('click', custCsvCloseModal);
document.getElementById('cust-csv-cancel-1').addEventListener('click', custCsvCloseModal);
document.getElementById('cust-csv-modal').addEventListener('click', (e) => { if (e.target.id === 'cust-csv-modal') custCsvCloseModal(); });
document.getElementById('cust-csv-back-btn').addEventListener('click', custCsvReset);
document.getElementById('cust-csv-done-btn').addEventListener('click', custCsvCloseModal);
document.getElementById('cust-csv-import-more-btn').addEventListener('click', custCsvReset);
document.getElementById('cst-import-csv-btn').addEventListener('click', custCsvOpenModal);

document.getElementById('cust-csv-import-btn').addEventListener('click', async () => {
  if (!custCsvValidRows.length) return;
  const btn = document.getElementById('cust-csv-import-btn');
  const icon = document.getElementById('cust-csv-import-icon');
  btn.disabled = true;
  icon.className = 'fa-solid fa-spinner fa-spin';

  const payload = custCsvValidRows.map((r) => ({
    name: r.name,
    phone: r.phone || undefined,
    email: r.email || undefined,
    address: r.address || undefined,
    notes: r.notes || undefined,
    category: r.category || undefined,
    customer_type: r.customer_type || 'retail',
  }));

  const res = await API.importCustomers(payload);
  btn.disabled = false;
  icon.className = 'fa-solid fa-file-import';

  if (res.status !== 200) { showToast(t(res.body?.message || 'Import failed'), 'error'); return; }

  const { imported, skipped, errors } = res.body;
  document.getElementById('cust-csv-result-summary').innerHTML = `
    <div class="csv-result-stat">
      <div class="csv-result-stat-num green">${imported}</div>
      <div class="csv-result-stat-label"><i class="fa-solid fa-circle-check"></i> ${t('Customers imported')}</div>
    </div>
    <div class="csv-result-stat">
      <div class="csv-result-stat-num ${skipped > 0 ? 'red' : ''}">${skipped}</div>
      <div class="csv-result-stat-label"><i class="fa-solid fa-triangle-exclamation"></i> ${t('Rows skipped')}</div>
    </div>`;

  const errWrap = document.getElementById('cust-csv-result-errors');
  if (errors && errors.length) {
    errWrap.style.display = '';
    document.getElementById('cust-csv-result-errors-list').innerHTML = errors.map((e) =>
      `<div class="csv-result-error-row"><b>${t('Row {n}', { n: e.row })}${e.name ? ' — ' + esc(e.name) : ''}:</b><span>${esc(e.message)}</span></div>`
    ).join('');
  } else {
    errWrap.style.display = 'none';
  }

  custCsvSetStep(3);
  if (imported > 0) { loadCustomers(); loadCustomerCategories(); }
});

/* ════════════════════════════════════════════════════════════════════
   SUPPLIERS
   ════════════════════════════════════════════════════════════════════ */

const supRowsEl = document.getElementById('sup-rows');
const supSearchInput = document.getElementById('sup-search-input');
const supCategoryFilter = document.getElementById('sup-category-filter');
const supStatusFilter = document.getElementById('sup-status-filter');
const supCountPill = document.getElementById('sup-count-pill');
const supSubnavCount = document.getElementById('sup-subnav-count');

let suppliers = [];
let supSearchDebounce = null;
let supEditingId = null; // null = creating

async function loadSupplierCategories(selectId = null) {
  const res = await API.supplierCategories();
  if (res.status !== 200) return;
  const cats = res.body.data || [];
  supCategoryFilter.innerHTML = `<option value="">${t('All categories')}</option>` +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');

  const addSelect = document.getElementById('sf-category');
  addSelect.innerHTML = `<option value="">${t('None')}</option>` +
    cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
  if (selectId) addSelect.value = String(selectId);
}

// New supplier category (inline, from Add/Edit Supplier modal)
const supCategoryError = document.getElementById('sup-category-error');
const supCategorySaveBtn = document.getElementById('sup-category-save-btn');

document.getElementById('sup-add-category-inline-btn').addEventListener('click', () => {
  document.getElementById('sup-cat-name').value = '';
  document.getElementById('sup-cat-description').value = '';
  supCategoryError.classList.remove('show');
  openModal('sup-category-modal');
  document.getElementById('sup-cat-name').focus();
});

supCategorySaveBtn.addEventListener('click', async () => {
  const name = document.getElementById('sup-cat-name').value.trim();
  if (!name) {
    supCategoryError.textContent = t('Category name is required.');
    supCategoryError.classList.add('show');
    return;
  }

  const payload = {
    name,
    description: document.getElementById('sup-cat-description').value.trim() || null,
  };

  supCategorySaveBtn.disabled = true;
  supCategorySaveBtn.textContent = t('Saving…');
  try {
    const res = await API.createSupplierCategory(payload);
    if (res.status !== 201) {
      supCategoryError.textContent = firstErrorMessage(res, 'Could not save category.');
      supCategoryError.classList.add('show');
      return;
    }
    closeModal('sup-category-modal');
    showToast(t('{name} added.', { name: res.body.data.name }), 'success');
    await loadSupplierCategories(res.body.data.id);
  } finally {
    supCategorySaveBtn.disabled = false;
    supCategorySaveBtn.textContent = t('Save Category');
  }
});

function renderSupRows() {
  supCountPill.textContent = t(suppliers.length === 1 ? '{n} supplier' : '{n} suppliers', { n: suppliers.length });
  supSubnavCount.textContent = t('{n} suppliers', { n: suppliers.length });

  if (!suppliers.length) {
    supRowsEl.innerHTML = `<tr><td colspan="6" class="empty-state">${t('No suppliers found.')}</td></tr>`;
    return;
  }

  supRowsEl.innerHTML = suppliers.map((s) => `
    <tr data-id="${s.id}">
      <td>
        <div class="row-name">${esc(s.name)}</div>
        ${s.address ? `<div class="row-sub">${esc(s.address)}</div>` : ''}
      </td>
      <td>
        <div>${esc(s.contact_name) || '—'}</div>
        ${s.phone || s.email ? `<div class="row-sub">${[esc(s.phone), esc(s.email)].filter(Boolean).join(' · ')}</div>` : ''}
      </td>
      <td>${esc(s.category_name) || '—'}</td>
      <td>${s.purchases_count ?? 0}</td>
      <td><span class="status-badge ${s.is_active ? 'active' : 'inactive'}">${s.is_active ? t('Active') : t('Inactive')}</span></td>
      <td>
        <div class="row-actions">
          <button data-action="view" title="${t('View')}"><i class="fa-solid fa-eye"></i></button>
          <button data-action="edit" title="${t('Edit')}"><i class="fa-solid fa-pen"></i></button>
          <button data-action="toggle" class="${s.is_active ? 'danger' : 'success'}" title="${s.is_active ? t('Deactivate') : t('Activate')}">
            <i class="fa-solid ${s.is_active ? 'fa-ban' : 'fa-rotate-left'}"></i>
          </button>
        </div>
      </td>
    </tr>`).join('');

  supRowsEl.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = Number(tr.dataset.id);
    tr.querySelector('[data-action="view"]').addEventListener('click', () => openSupView(id));
    tr.querySelector('[data-action="edit"]').addEventListener('click', () => openSupEdit(id));
    tr.querySelector('[data-action="toggle"]').addEventListener('click', () => toggleSupplier(id));
    tr.addEventListener('dblclick', () => openSupView(id));
  });
}

async function loadSuppliers() {
  supRowsEl.innerHTML = `<tr><td colspan="6" class="loading-state">${t('Loading suppliers…')}</td></tr>`;
  const res = await API.supplierList({
    q: supSearchInput.value.trim(),
    categoryId: supCategoryFilter.value,
    active: supStatusFilter.value,
  });
  if (res.status !== 200) {
    supRowsEl.innerHTML = `<tr><td colspan="6" class="empty-state">${t('Could not load suppliers ({reason}).', { reason: t(res.body?.message) || res.status })}</td></tr>`;
    return;
  }
  suppliers = res.body.data || [];
  renderSupRows();
}

supSearchInput.addEventListener('input', () => {
  clearTimeout(supSearchDebounce);
  supSearchDebounce = setTimeout(loadSuppliers, 300);
});
supCategoryFilter.addEventListener('change', loadSuppliers);
supStatusFilter.addEventListener('change', loadSuppliers);

// ── Add / Edit supplier ─────────────────────────────────────────────────
const supError = document.getElementById('sup-error');
const supSaveBtn = document.getElementById('sup-save-btn');
const supActiveRow = document.getElementById('sf-active-row');

function openSupAdd() {
  supEditingId = null;
  document.getElementById('sup-modal-title').textContent = t('Add Supplier');
  supSaveBtn.textContent = t('Save Supplier');
  ['sf-name', 'sf-contact-name', 'sf-phone', 'sf-email', 'sf-address', 'sf-notes'].forEach((id) => { document.getElementById(id).value = ''; });
  document.getElementById('sf-category').value = '';
  document.getElementById('sf-active').checked = true;
  supActiveRow.style.display = 'none';
  supError.classList.remove('show');
  openModal('sup-modal');
  document.getElementById('sf-name').focus();
}

function fillSupForm(s) {
  document.getElementById('sf-name').value = s.name || '';
  document.getElementById('sf-contact-name').value = s.contact_name || '';
  document.getElementById('sf-phone').value = s.phone || '';
  document.getElementById('sf-email').value = s.email || '';
  document.getElementById('sf-address').value = s.address || '';
  document.getElementById('sf-notes').value = s.notes || '';
  document.getElementById('sf-category').value = s.supplier_category_id || '';
  document.getElementById('sf-active').checked = !!s.is_active;
}

async function openSupEdit(id) {
  const res = await API.supplier(id);
  if (res.status !== 200) {
    showToast(t(res.body?.message || 'Could not load supplier.'), 'error');
    return;
  }
  supEditingId = id;
  document.getElementById('sup-modal-title').textContent = t('Edit Supplier');
  supSaveBtn.textContent = t('Save Changes');
  fillSupForm(res.body.data);
  supActiveRow.style.display = 'flex';
  supError.classList.remove('show');
  openModal('sup-modal');
  document.getElementById('sf-name').focus();
}

document.getElementById('sup-add-btn').addEventListener('click', openSupAdd);

supSaveBtn.addEventListener('click', async () => {
  const name = document.getElementById('sf-name').value.trim();
  if (!name) {
    supError.textContent = t('Name is required.');
    supError.classList.add('show');
    return;
  }

  const payload = {
    name,
    contact_name: document.getElementById('sf-contact-name').value.trim() || null,
    phone: document.getElementById('sf-phone').value.trim() || null,
    email: document.getElementById('sf-email').value.trim() || null,
    address: document.getElementById('sf-address').value.trim() || null,
    notes: document.getElementById('sf-notes').value.trim() || null,
    supplier_category_id: document.getElementById('sf-category').value || null,
  };
  if (supEditingId) payload.is_active = document.getElementById('sf-active').checked;

  supSaveBtn.disabled = true;
  supSaveBtn.textContent = t('Saving…');
  try {
    const res = supEditingId
      ? await API.updateSupplier(supEditingId, payload)
      : await API.createSupplier(payload);
    const okStatus = supEditingId ? 200 : 201;
    if (res.status !== okStatus) {
      supError.textContent = firstErrorMessage(res, 'Could not save supplier.');
      supError.classList.add('show');
      return;
    }
    closeModal('sup-modal');
    showToast(t('{name} saved.', { name: res.body.data.name }), 'success');
    loadSuppliers();
  } finally {
    supSaveBtn.disabled = false;
    supSaveBtn.textContent = supEditingId ? t('Save Changes') : t('Save Supplier');
  }
});

// ── View supplier ───────────────────────────────────────────────────────
let supViewingId = null;

async function openSupView(id) {
  supViewingId = id;
  document.getElementById('sup-view-name').textContent = t('Loading…');
  document.getElementById('sup-view-body').innerHTML = `<div class="loading-state">${t('Loading…')}</div>`;
  openModal('sup-view-modal');

  const res = await API.supplier(id);
  if (res.status !== 200) {
    document.getElementById('sup-view-body').innerHTML = `<div class="empty-state">${t('Could not load supplier ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
    return;
  }

  const s = res.body.data;
  document.getElementById('sup-view-name').textContent = s.name;

  const toggleBtn = document.getElementById('sup-view-toggle-btn');
  toggleBtn.innerHTML = s.is_active
    ? `<i class="fa-solid fa-ban"></i> ${t('Deactivate')}`
    : `<i class="fa-solid fa-rotate-left"></i> ${t('Activate')}`;
  toggleBtn.className = `ghost-btn ${s.is_active ? 'danger-text' : 'success-text'}`;

  const poRows = (s.recent_purchase_orders || []).map((p) => `
    <div class="mini-list-item">
      <span>${esc(p.po_number)} <span class="muted">· ${p.purchase_date ? new Date(p.purchase_date).toLocaleDateString(i18n.locale) : ''}</span></span>
      <span>${money(p.total)}</span>
    </div>`).join('') || `<div class="mini-list-item"><span class="muted">${t('No purchase orders yet.')}</span></div>`;

  document.getElementById('sup-view-body').innerHTML = `
    <div class="view-row"><span>${t('Contact Person')}</span><span>${esc(s.contact_name) || '—'}</span></div>
    <div class="view-row"><span>${t('Phone')}</span><span>${esc(s.phone) || '—'}</span></div>
    <div class="view-row"><span>${t('Email')}</span><span>${esc(s.email) || '—'}</span></div>
    <div class="view-row"><span>${t('Address')}</span><span>${esc(s.address) || '—'}</span></div>
    <div class="view-row"><span>${t('Category')}</span><span>${esc(s.category_name) || '—'}</span></div>
    <div class="view-row"><span>${t('Status')}</span><span>${s.is_active ? t('Active') : t('Inactive')}</span></div>
    <div class="view-row"><span>${t('Total purchases')}</span><span>${s.purchases_count ?? 0}</span></div>
    ${s.notes ? `<div class="view-row"><span>${t('Notes')}</span><span>${esc(s.notes)}</span></div>` : ''}
    <div style="margin-top:6px;">
      <label style="font-size:12px;font-weight:600;color:var(--muted);">${t('Recent purchase orders')}</label>
      <div class="mini-list">${poRows}</div>
    </div>
  `;
}

document.getElementById('sup-view-edit-btn').addEventListener('click', () => {
  if (supViewingId !== null) { closeModal('sup-view-modal'); openSupEdit(supViewingId); }
});
document.getElementById('sup-view-toggle-btn').addEventListener('click', () => {
  if (supViewingId !== null) toggleSupplier(supViewingId, true);
});

// ── Activate / Deactivate supplier ──────────────────────────────────────
// The API has no hard-delete for suppliers tied to purchase history — the
// "destroy" endpoint deactivates instead, mirroring the web admin's behaviour.
async function toggleSupplier(id, fromModal = false) {
  const supplier = suppliers.find((s) => s.id === id);
  const isActive = supplier ? !!supplier.is_active : true;
  const label = supplier?.name || `#${id}`;

  if (isActive) {
    if (!await zeebrooConfirm(t('Deactivate {label}? They will no longer appear in active supplier pickers.', { label }), { okText: t('Deactivate'), tone: 'danger' })) return;
    const res = await API.deactivateSupplier(id);
    if (res.status !== 200) { showToast(t(res.body?.message || 'Could not deactivate supplier.'), 'error'); return; }
    showToast(t('{label} deactivated.', { label }), 'success');
  } else {
    const res = await API.updateSupplier(id, { is_active: true });
    if (res.status !== 200) { showToast(t(res.body?.message || 'Could not activate supplier.'), 'error'); return; }
    showToast(t('{label} activated.', { label }), 'success');
  }

  if (fromModal) closeModal('sup-view-modal');
  loadSuppliers();
}

/* ── Supplier CSV import ─────────────────────────────────────────────── */
const SUP_CSV_SAMPLE = [
  'name,contact_name,phone,email,address,notes,category',
  'Acme Distributors,John Silva,0771234567,john@acme.com,"123 Main St, Colombo",Preferred vendor,Wholesale',
  'Global Traders,Nimal Perera,0719876543,nimal@globaltraders.com,"45 Galle Rd, Kandy",,Local',
  'Sunrise Imports,,0765555555,,,"Pays on 30-day terms",Import',
].join('\n');

const SUP_CSV_COL_MAP = {
  'name': 'name', 'supplier name': 'name', 'supplier': 'name', 'company': 'name', 'company name': 'name',
  'contact_name': 'contact_name', 'contact': 'contact_name', 'contact name': 'contact_name', 'contact person': 'contact_name',
  'phone': 'phone', 'phone number': 'phone', 'mobile': 'phone', 'contact number': 'phone',
  'email': 'email', 'email address': 'email',
  'address': 'address',
  'notes': 'notes', 'note': 'notes', 'remarks': 'notes',
  'category': 'category', 'supplier category': 'category',
};

let supCsvValidRows = [];

function supCsvParseFile(text) {
  const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').filter((l) => l.trim());
  if (lines.length < 2) return { rows: [], error: t('File must have a header row and at least one data row.') };

  const headers = csvParseLine(lines[0]).map((h) => h.trim().toLowerCase());
  const colMap = {};
  headers.forEach((h, i) => { if (SUP_CSV_COL_MAP[h]) colMap[SUP_CSV_COL_MAP[h]] = i; });

  if (colMap['name'] === undefined) {
    return { rows: [], error: t('Missing required column: "name" (or "supplier", "company").') };
  }

  const rows = [];
  for (let i = 1; i < lines.length; i++) {
    const cells = csvParseLine(lines[i]);
    const row = {};
    Object.entries(colMap).forEach(([key, ci]) => { row[key] = (cells[ci] || '').trim(); });
    row._rowNum = i + 1;
    row._errors = [];

    if (!row.name) row._errors.push(t('Name is required'));
    if (row.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(row.email)) row._errors.push(t('Invalid email'));

    rows.push(row);
  }
  return { rows };
}

function supCsvSetStep(n) {
  [1, 2, 3].forEach((i) => {
    document.getElementById(`sup-csv-step-${i}`).style.display = i === n ? '' : 'none';
    document.getElementById(`sup-csv-footer-${i}`).style.display = i === n ? 'flex' : 'none';
    const ind = document.getElementById(`sup-csv-step-ind-${i}`);
    ind.classList.toggle('active', i === n);
    ind.classList.toggle('done', i < n);
  });
}

function supCsvShowPreview(rows) {
  const valid = rows.filter((r) => !r._errors.length);
  const bad = rows.filter((r) => r._errors.length);
  supCsvValidRows = valid;

  document.getElementById('sup-csv-preview-summary').innerHTML = `
    <b>${t('{n} rows found', { n: rows.length })}</b>
    <span class="csv-preview-badge ok"><i class="fa-solid fa-circle-check"></i> ${t('{n} valid', { n: valid.length })}</span>
    ${bad.length ? `<span class="csv-preview-badge error"><i class="fa-solid fa-triangle-exclamation"></i> ${t('{n} with errors', { n: bad.length })}</span>` : ''}
    ${rows.length > 100 ? `<span class="csv-preview-badge warn"><i class="fa-solid fa-eye"></i> ${t('Showing first 100 rows')}</span>` : ''}`;

  const display = rows.slice(0, 100);
  document.getElementById('sup-csv-preview-thead').innerHTML = `<tr>
    <th>#</th><th>${t('Name')}</th><th>${t('Contact Person')}</th><th>${t('Phone')}</th><th>${t('Email')}</th><th>${t('Address')}</th>
    <th>${t('Category')}</th><th>${t('Status')}</th>
  </tr>`;
  document.getElementById('sup-csv-preview-tbody').innerHTML = display.map((r) => {
    const isErr = r._errors.length > 0;
    const status = isErr
      ? `<span class="csv-row-error-msg"><i class="fa-solid fa-triangle-exclamation"></i> ${esc(r._errors.join('; '))}</span>`
      : `<span style="color:var(--success);font-size:11px"><i class="fa-solid fa-circle-check"></i> ${t('OK')}</span>`;
    return `<tr class="${isErr ? 'csv-row-error' : ''}">
      <td style="color:var(--muted)">${r._rowNum}</td>
      <td><strong>${esc(r.name) || '—'}</strong></td>
      <td style="color:var(--muted)">${esc(r.contact_name) || '—'}</td>
      <td style="color:var(--muted)">${esc(r.phone) || '—'}</td>
      <td style="color:var(--muted)">${esc(r.email) || '—'}</td>
      <td style="color:var(--muted)">${esc(r.address) || '—'}</td>
      <td>${esc(r.category) || '—'}</td>
      <td>${status}</td>
    </tr>`;
  }).join('');

  document.getElementById('sup-csv-import-count').textContent = valid.length;
  document.getElementById('sup-csv-import-btn').disabled = valid.length === 0;
  supCsvSetStep(2);
}

function supCsvReset() {
  supCsvValidRows = [];
  document.getElementById('sup-csv-file-input').value = '';
  supCsvSetStep(1);
}

function supCsvOpenModal() {
  supCsvReset();
  openModal('sup-csv-modal');
}

function supCsvCloseModal() {
  closeModal('sup-csv-modal');
  supCsvReset();
}

function supCsvDownloadSample() {
  const blob = new Blob([SUP_CSV_SAMPLE], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'suppliers-sample.csv';
  a.click();
  URL.revokeObjectURL(url);
}

function supCsvHandleFile(file) {
  if (!file || !file.name.match(/\.csv$/i)) { showToast(t('Please select a .csv file'), 'error'); return; }
  const reader = new FileReader();
  reader.onload = (e) => {
    const { rows, error } = supCsvParseFile(e.target.result);
    if (error) { showToast(error, 'error'); return; }
    if (rows.length > 500) showToast(t('CSV has {n} rows. Only the first 500 will be imported.', { n: rows.length }), 'error');
    supCsvShowPreview(rows.slice(0, 500));
  };
  reader.readAsText(file);
}

const supCsvDz = document.getElementById('sup-csv-dropzone');
supCsvDz.addEventListener('dragover', (e) => { e.preventDefault(); supCsvDz.classList.add('drag-over'); });
supCsvDz.addEventListener('dragleave', () => supCsvDz.classList.remove('drag-over'));
supCsvDz.addEventListener('drop', (e) => {
  e.preventDefault();
  supCsvDz.classList.remove('drag-over');
  supCsvHandleFile(e.dataTransfer.files[0]);
});
document.getElementById('sup-csv-file-input').addEventListener('change', (e) => supCsvHandleFile(e.target.files[0]));
document.getElementById('sup-csv-download-sample').addEventListener('click', supCsvDownloadSample);
document.getElementById('sup-csv-close').addEventListener('click', supCsvCloseModal);
document.getElementById('sup-csv-cancel-1').addEventListener('click', supCsvCloseModal);
document.getElementById('sup-csv-modal').addEventListener('click', (e) => { if (e.target.id === 'sup-csv-modal') supCsvCloseModal(); });
document.getElementById('sup-csv-back-btn').addEventListener('click', supCsvReset);
document.getElementById('sup-csv-done-btn').addEventListener('click', supCsvCloseModal);
document.getElementById('sup-csv-import-more-btn').addEventListener('click', supCsvReset);
document.getElementById('sst-import-csv-btn').addEventListener('click', supCsvOpenModal);

document.getElementById('sup-csv-import-btn').addEventListener('click', async () => {
  if (!supCsvValidRows.length) return;
  const btn = document.getElementById('sup-csv-import-btn');
  const icon = document.getElementById('sup-csv-import-icon');
  btn.disabled = true;
  icon.className = 'fa-solid fa-spinner fa-spin';

  const payload = supCsvValidRows.map((r) => ({
    name: r.name,
    contact_name: r.contact_name || undefined,
    phone: r.phone || undefined,
    email: r.email || undefined,
    address: r.address || undefined,
    notes: r.notes || undefined,
    category: r.category || undefined,
  }));

  const res = await API.importSuppliers(payload);
  btn.disabled = false;
  icon.className = 'fa-solid fa-file-import';

  if (res.status !== 200) { showToast(t(res.body?.message || 'Import failed'), 'error'); return; }

  const { imported, skipped, errors } = res.body;
  document.getElementById('sup-csv-result-summary').innerHTML = `
    <div class="csv-result-stat">
      <div class="csv-result-stat-num green">${imported}</div>
      <div class="csv-result-stat-label"><i class="fa-solid fa-circle-check"></i> ${t('Suppliers imported')}</div>
    </div>
    <div class="csv-result-stat">
      <div class="csv-result-stat-num ${skipped > 0 ? 'red' : ''}">${skipped}</div>
      <div class="csv-result-stat-label"><i class="fa-solid fa-triangle-exclamation"></i> ${t('Rows skipped')}</div>
    </div>`;

  const errWrap = document.getElementById('sup-csv-result-errors');
  if (errors && errors.length) {
    errWrap.style.display = '';
    document.getElementById('sup-csv-result-errors-list').innerHTML = errors.map((e) =>
      `<div class="csv-result-error-row"><b>${t('Row {n}', { n: e.row })}${e.name ? ' — ' + esc(e.name) : ''}:</b><span>${esc(e.message)}</span></div>`
    ).join('');
  } else {
    errWrap.style.display = 'none';
  }

  supCsvSetStep(3);
  if (imported > 0) { loadSuppliers(); loadSupplierCategories(); }
});

/* ════════════════════════════════════════════════════════════════════
   Init
   ════════════════════════════════════════════════════════════════════ */
loadCustomerCategories();
loadCustomers();
loadSupplierCategories();
loadSuppliers();
