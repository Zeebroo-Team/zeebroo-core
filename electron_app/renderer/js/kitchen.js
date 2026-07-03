'use strict';

// ── Helpers ───────────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);
function esc(str) {
  return String(str ?? '').replace(/[&<>"']/g, c =>
    ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])
  );
}

// ── State ─────────────────────────────────────────────────────────────────────
const kds = {
  orders:        [],
  filterStatus:  'open',
  refreshTimer:  null,
  tickerTimer:   null,
  currency:      '',
};

// ── API helper ────────────────────────────────────────────────────────────────
async function apiGet(path) {
  try {
    return await window.electronAPI.apiRequest('GET', path, null);
  } catch (e) {
    return { status: 0, body: null };
  }
}
async function apiPatch(path, body) {
  try {
    return await window.electronAPI.apiRequest('PATCH', path, body);
  } catch (e) {
    return { status: 0, body: null };
  }
}

// ── Init ──────────────────────────────────────────────────────────────────────
async function kdsInit() {
  await kdsFetch();
  kds.refreshTimer = setInterval(kdsFetch, 30000);
  kds.tickerTimer  = setInterval(kdsTickTimers, 15000);
}

// ── Fetch open orders ─────────────────────────────────────────────────────────
async function kdsFetch() {
  const btn = $('btn-refresh');
  if (btn) { btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; btn.disabled = true; }

  const res = await apiGet('/restaurant/orders?status=open&per_page=100');

  if (btn) { btn.innerHTML = '<i class="fa fa-rotate-right"></i>'; btn.disabled = false; }
  if (res.status !== 200) return;

  kds.orders   = res.body?.data || [];
  kds.currency = res.body?.currency || '';

  kdsUpdateCounts();
  kdsRender();

  const el = $('kds-last-update');
  if (el) el.textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ── Count badges ──────────────────────────────────────────────────────────────
function kdsUpdateCounts() {
  const c = { pending: 0, preparing: 0, ready: 0 };
  kds.orders.forEach(o => { if (c[o.status] !== undefined) c[o.status]++; });
  ['pending','preparing','ready'].forEach(s => {
    const el = $(`cnt-${s}`);
    if (!el) return;
    el.textContent   = c[s] || '';
    el.style.display = c[s] ? '' : 'none';
  });
}

// ── Render tickets ────────────────────────────────────────────────────────────
function kdsRender() {
  const grid    = $('kds-grid');
  const loading = $('kds-loading');
  if (!grid) return;

  // Remove existing tickets
  grid.querySelectorAll('.kds-ticket').forEach(el => el.remove());

  const status = kds.filterStatus;
  const orders = status === 'open'
    ? kds.orders
    : kds.orders.filter(o => o.status === status);

  if (!orders.length) {
    if (loading) {
      loading.style.display = '';
      loading.innerHTML = `
        <i class="fa fa-check-circle" style="color:#22c55e"></i>
        <p>All clear — no active orders</p>`;
    }
    return;
  }

  if (loading) loading.style.display = 'none';
  orders.forEach(o => grid.appendChild(kdsTicket(o)));
}

// ── Build one ticket element ──────────────────────────────────────────────────
function kdsTicket(order) {
  const el   = document.createElement('div');
  const mins = kdsAge(order.created_at);
  const urgCls = mins >= 15 ? 'urg-urgent' : mins >= 8 ? 'urg-warn' : '';
  el.className  = `kds-ticket status-${order.status} ${urgCls}`.trim();
  el.dataset.orderId   = order.id;
  el.dataset.createdAt = order.created_at || '';

  const typeLabel = order.order_type === 'dine_in' ? 'Dine In'
    : order.order_type === 'takeaway' ? 'Takeaway' : 'Delivery';

  const metaHtml =
    (order.table        ? `<div class="kds-meta"><i class="fa fa-table-cells-large"></i> ${esc(order.table.name)}</div>` : '') +
    (order.customer_name ? `<div class="kds-meta"><i class="fa fa-user"></i> ${esc(order.customer_name)}</div>` : '');

  const itemsHtml = (order.items || []).map(i =>
    `<div class="kds-item">
       <span class="kds-qty">×${i.quantity}</span>
       <span class="kds-name">${esc(i.name)}</span>
     </div>${i.notes ? `<div class="kds-note">${esc(i.notes)}</div>` : ''}`
  ).join('');

  const timerCls = mins >= 15 ? 'urgent' : mins >= 8 ? 'warn' : 'ok';

  let actionHtml = '';
  if (order.status === 'pending') {
    actionHtml = `<button class="kds-action start" data-id="${order.id}" data-next="preparing"><i class="fa fa-fire"></i> Start Preparing</button>`;
  } else if (order.status === 'preparing') {
    actionHtml = `<button class="kds-action ready" data-id="${order.id}" data-next="ready"><i class="fa fa-bell"></i> Mark Ready</button>`;
  } else if (order.status === 'ready') {
    actionHtml = `<button class="kds-action served" data-id="${order.id}" data-next="served"><i class="fa fa-circle-check"></i> Served</button>`;
  }

  el.innerHTML = `
    <div class="kds-ticket-hdr">
      <span class="kds-order-num">${esc(order.order_number)}</span>
      <span class="kds-type-badge ${order.order_type}">${esc(typeLabel)}</span>
      <span class="kds-timer ${timerCls}" data-created="${esc(order.created_at||'')}">${mins}m</span>
    </div>
    ${metaHtml}
    <div class="kds-items">${itemsHtml}</div>
    <div class="kds-footer">${actionHtml}</div>`;

  return el;
}

function kdsAge(createdAt) {
  if (!createdAt) return 0;
  return Math.max(0, Math.floor((Date.now() - new Date(createdAt).getTime()) / 60000));
}

// ── Tick timers in-place (no network) ────────────────────────────────────────
function kdsTickTimers() {
  document.querySelectorAll('#kds-grid .kds-timer').forEach(el => {
    const mins = kdsAge(el.dataset.created);
    el.textContent = `${mins}m`;
    el.className   = `kds-timer ${mins >= 15 ? 'urgent' : mins >= 8 ? 'warn' : 'ok'}`;
    const ticket = el.closest('.kds-ticket');
    if (ticket) {
      ticket.classList.remove('urg-warn', 'urg-urgent');
      if (mins >= 15)     ticket.classList.add('urg-urgent');
      else if (mins >= 8) ticket.classList.add('urg-warn');
    }
  });
}

// ── Status transition (delegated) ────────────────────────────────────────────
document.getElementById('kds-grid').addEventListener('click', async e => {
  const btn = e.target.closest('[data-next]');
  if (!btn) return;

  const orderId   = Number(btn.dataset.id);
  const newStatus = btn.dataset.next;
  const origHtml  = btn.innerHTML;

  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

  const res = await apiPatch(`/restaurant/orders/${orderId}/status`, { status: newStatus });

  if (res.status !== 200) {
    btn.disabled = false;
    btn.innerHTML = origHtml;
    return;
  }

  const order = kds.orders.find(o => o.id === orderId);
  if (order) {
    if (newStatus === 'served') kds.orders = kds.orders.filter(o => o.id !== orderId);
    else order.status = newStatus;
  }
  kdsUpdateCounts();
  kdsRender();
});

// ── Filter tabs ───────────────────────────────────────────────────────────────
document.querySelectorAll('.kds-filter-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.kds-filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    kds.filterStatus = btn.dataset.status;
    kdsRender();
  });
});

// ── Window controls ───────────────────────────────────────────────────────────
document.getElementById('btn-refresh').addEventListener('click', kdsFetch);

document.getElementById('btn-minimize').addEventListener('click', () => {
  window.electronAPI.kdsMinimize();
});

document.getElementById('btn-close').addEventListener('click', () => {
  window.electronAPI.kdsClose();
});

let _isFullScreen = false;
document.getElementById('btn-fullscreen').addEventListener('click', async () => {
  _isFullScreen = !_isFullScreen;
  window.electronAPI.kdsFullscreen(_isFullScreen);
  const icon = document.getElementById('fs-icon');
  if (icon) icon.className = _isFullScreen ? 'fa fa-compress' : 'fa fa-expand';
});

// ── Start ─────────────────────────────────────────────────────────────────────
kdsInit();
