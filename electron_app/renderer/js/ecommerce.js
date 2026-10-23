'use strict';

// ── E-Commerce tab: online store back office ────────────────────────────────
// Dashboard · Web Orders · Online Products · Store Settings · Website Template ·
// Homepage Slider · Payment Gateways.
// Server side: Modules/Ecommerce (routes/api.php → /api/v1/pos/ecom/*), public
// storefront at /shop/{slug}. Relies on app.js globals ($, $$, API, escHtml,
// formatMoney, toast, activateTab).
const Ecom = (() => {
  const STATUS = {
    pending:    { label: 'Pending',    icon: 'fa-hourglass-half' },
    confirmed:  { label: 'Confirmed',  icon: 'fa-circle-check' },
    processing: { label: 'Processing', icon: 'fa-gears' },
    shipped:    { label: 'Shipped',    icon: 'fa-truck-fast' },
    delivered:  { label: 'Delivered',  icon: 'fa-box-open' },
    cancelled:  { label: 'Cancelled',  icon: 'fa-ban' },
  };
  const PAY_STATUS = {
    unpaid:   { label: 'Unpaid',   icon: 'fa-clock' },
    paid:     { label: 'Paid',     icon: 'fa-check' },
    refunded: { label: 'Refunded', icon: 'fa-rotate-left' },
  };
  const PAY_METHOD = { cod: 'Cash on delivery', bank_transfer: 'Bank transfer', stripe: 'Stripe (card)', payhere: 'PayHere' };
  const SWATCHES   = ['#7c3aed', '#2563eb', '#0891b2', '#059669', '#ca8a04', '#ea580c', '#dc2626', '#db2777', '#111827'];
  const GW_HELP    = {
    stripe:  { url: 'https://dashboard.stripe.com/apikeys', text: 'Find your keys in Stripe Dashboard → Developers → API keys.' },
    payhere: { url: 'https://www.payhere.lk/merchant/', text: 'Merchant ID & secret: PayHere → Settings → Domains & Credentials (add your store domain).' },
  };

  const s = {
    view: 'dashboard',
    store: null,
    ordQ: '', ordStatus: '', ordPage: 1,
    lstQ: '', lstState: '', listings: [],
    candidates: [], picked: new Set(),
    order: null,
    logoUrl: null, bannerUrl: null,
    tplSelected: null,
    slides: [], slideImg: null,
    gateways: [],
  };

  // ── Helpers ───────────────────────────────────────────────────────────────
  const esc   = v => escHtml(v ?? '');
  const money = n => formatMoney(n, { currency: s.store?.currency || state.currency });
  const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const fmtDate = iso => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' ' +
           d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
  };
  const pill = (map, key) => {
    const m = map[key] || { label: key, icon: 'fa-circle' };
    return `<span class="ecom-st ecom-st-${esc(key)}"><i class="fa ${m.icon}"></i> ${esc(m.label)}</span>`;
  };
  const errMsg = (res, fallback) => {
    const errs = res.body?.errors;
    return (errs && Object.values(errs)[0]?.[0]) || res.body?.message || fallback;
  };
  function alertEl(sel, msg, ok) {
    const el = $(sel);
    if (!el) return;
    el.textContent   = msg || '';
    el.style.display = msg ? '' : 'none';
    el.style.color   = ok ? '#10b981' : '#ef4444';
  }
  const numOrNull = sel => { const v = ($(sel).value || '').trim(); return v === '' ? null : Number(v); };
  const strOrNull = sel => ($(sel).value || '').trim() || null;

  // ── Store header (status pill, website link) ────────────────────────────
  function applyStore(store) {
    s.store = store;
    const pillEl = $('#ecom-status-pill');
    if (pillEl) {
      pillEl.classList.toggle('live', !!store?.is_published);
      pillEl.innerHTML = `<i class="fa fa-circle" style="font-size:7px"></i> ${store?.is_published ? 'Live' : 'Offline'}`;
    }
    const pub = $('#ecom-hero-publish');
    if (pub) {
      pub.innerHTML = store?.is_published ? '<i class="fa fa-eye-slash"></i> Take Offline' : '<i class="fa fa-rocket"></i> Publish Store';
      pub.classList.toggle('svc-form-btn--primary', !store?.is_published);
    }
    if ($('#ecom-hero-name'))    $('#ecom-hero-name').textContent    = store?.name || 'Your online store';
    if ($('#ecom-hero-tagline')) $('#ecom-hero-tagline').textContent = store?.tagline || 'Sell your catalog products on your own website and manage every web order from here.';
    if ($('#ecom-hero-url'))     $('#ecom-hero-url').textContent     = store?.url || '';
  }

  async function ensureStore() {
    if (s.store) return s.store;
    const res = await API.ecomStore();
    if (res.status === 200) applyStore(res.body.data);
    return s.store;
  }

  async function visitWebsite() {
    const store = await ensureStore();
    if (!store?.url) { toast('Store website is not available yet.', 'error'); return; }
    if (!store.is_published) toast('Your store is offline — visitors will see a "coming soon" page until you publish it.', 'info');
    window.electronAPI.openExternal(store.url);
  }

  async function copyLink() {
    const store = await ensureStore();
    if (!store?.url) return;
    await window.electronAPI.copyToClipboard(store.url);
    toast('Store link copied to clipboard.', 'info');
  }

  async function togglePublish() {
    const store = await ensureStore();
    if (!store) return;
    const res = await API.ecomStoreUpdate({ is_published: !store.is_published });
    if (res.status !== 200) { toast(errMsg(res, 'Could not update the store.'), 'error'); return; }
    applyStore(res.body.data);
    toast(res.body.data.is_published ? 'Your store is now live.' : 'Your store is now offline.', 'info');
    if (s.view === 'settings') fillSettings(res.body.data);
  }

  // ── View switching ────────────────────────────────────────────────────────
  function switchView(view) {
    s.view = view;
    const views = { dashboard: '#ecom-dashboard-view', orders: '#ecom-orders-view', products: '#ecom-products-view', settings: '#ecom-settings-view',
                    template: '#ecom-template-view', slider: '#ecom-slider-view', payments: '#ecom-payments-view' };
    Object.entries(views).forEach(([k, sel]) => $(sel)?.classList.toggle('active', k === view));
    $$('[data-ecomview]').forEach(b => b.classList.toggle('active', b.dataset.ecomview === view));
    if (typeof _sbSubActivate === 'function') _sbSubActivate('ecommerce', view);
    if (view === 'dashboard') loadDashboard();
    if (view === 'orders')    loadOrders();
    if (view === 'products')  loadListings();
    if (view === 'settings')  loadSettings();
    if (view === 'template')  loadTemplates();
    if (view === 'slider')    loadSlides();
    if (view === 'payments')  loadGateways();
  }

  function open(view, extra) {
    activateTab('ecommerce');
    switchView(view);
    extra?.();
  }

  // ═══════════════════════════ DASHBOARD ═══════════════════════════════════
  async function loadDashboard() {
    const res = await API.ecomDashboard();
    if (res.status !== 200) {
      $('#ecom-recent-body').innerHTML = `<tr><td colspan="5" class="ecom-empty" style="color:#ef4444">${esc(errMsg(res, 'Failed to load the store dashboard.'))}</td></tr>`;
      return;
    }
    const d = res.body.data;
    applyStore(d.store);
    $('#ecom-kpi-rev30').textContent   = money(d.revenue_30d);
    $('#ecom-kpi-rev').textContent     = money(d.revenue_total);
    $('#ecom-kpi-today').textContent   = d.orders_today;
    $('#ecom-kpi-pending').textContent = d.pending_orders;
    $('#ecom-kpi-listed').textContent  = d.listed_products;
    setPendingBadge(d.pending_orders);

    renderChart(d.series || []);

    $('#ecom-status-list').innerHTML = Object.keys(STATUS).map(k =>
      `<div class="ecom-status-row">${pill(STATUS, k)}<strong>${d.status_counts?.[k] ?? 0}</strong></div>`).join('');

    const top = d.top_products || [];
    $('#ecom-top-list').innerHTML = top.length
      ? top.map((p, i) => `<div class="ecom-status-row"><span>${i + 1}. ${esc(p.name)} <span style="color:var(--text-muted)">× ${+p.quantity}</span></span><strong>${money(p.revenue)}</strong></div>`).join('')
      : '<div class="ecom-empty" style="padding:12px">No sales yet.</div>';

    const recent = d.recent_orders || [];
    const tbody  = $('#ecom-recent-body');
    tbody.innerHTML = recent.length
      ? recent.map(o => `
        <tr data-order="${o.id}" style="cursor:pointer">
          <td style="font-weight:600;font-size:12px">${esc(o.order_no)}</td>
          <td style="font-size:12px">${esc(o.customer_name)}</td>
          <td>${pill(STATUS, o.status)}</td>
          <td style="font-size:12px;color:var(--text-muted)">${fmtDate(o.placed_at)}</td>
          <td class="ecom-num" style="font-weight:600">${money(o.total)}</td>
        </tr>`).join('')
      : `<tr><td colspan="5" class="ecom-empty">${d.store?.is_published ? 'No web orders yet — share your store link to get your first sale.' : 'Publish your store and add products to start taking orders.'}</td></tr>`;
    tbody.querySelectorAll('[data-order]').forEach(tr => tr.addEventListener('click', () => openOrder(tr.dataset.order)));
  }

  function renderChart(series) {
    const chart = $('#ecom-chart');
    const tip   = $('#ecom-tip');
    const max   = Math.max(0, ...series.map(p => p.revenue));
    chart.querySelectorAll('.ecom-bar-hit').forEach(el => el.remove());
    series.forEach(p => {
      const hit = document.createElement('div');
      hit.className = 'ecom-bar-hit';
      const pct = max > 0 ? Math.max(2, (p.revenue / max) * 100) : 0;
      hit.innerHTML = `<div class="ecom-bar${p.revenue > 0 ? '' : ' zero'}" style="height:${pct}%"></div>`;
      hit.addEventListener('mouseenter', () => {
        const date = new Date(p.date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
        tip.innerHTML = `<b>${money(p.revenue)}</b>${date} · ${p.orders} order${p.orders === 1 ? '' : 's'}`;
        const r = hit.getBoundingClientRect(), c = chart.getBoundingClientRect();
        tip.style.left = (r.left - c.left + r.width / 2) + 'px';
        tip.style.top  = '0px';
        tip.style.display = 'block';
      });
      hit.addEventListener('mouseleave', () => { tip.style.display = 'none'; });
      chart.appendChild(hit);
    });
    const fmt = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';
    $('#ecom-axis-start').textContent = fmt(series[0]?.date);
    $('#ecom-axis-end').textContent   = fmt(series[series.length - 1]?.date);
    $('#ecom-chart-sum').textContent  = `${series.reduce((a, p) => a + p.orders, 0)} orders`;
  }

  function setPendingBadge(n) {
    const b = $('#ecom-pending-badge');
    if (!b) return;
    b.textContent   = n;
    b.style.display = n > 0 ? '' : 'none';
  }

  // ═══════════════════════════ ORDERS ══════════════════════════════════════
  async function loadOrders() {
    const tbody = $('#ecom-ord-body');
    tbody.innerHTML = '<tr><td colspan="9" class="ecom-empty">Loading…</td></tr>';
    $$('#ecom-ord-chips .ecom-chip').forEach(c => c.classList.toggle('active', c.dataset.status === s.ordStatus));

    const res = await API.ecomOrders(s.ordQ, s.ordStatus, s.ordPage);
    if (res.status !== 200) {
      tbody.innerHTML = `<tr><td colspan="9" class="ecom-empty" style="color:#ef4444">${esc(errMsg(res, 'Failed to load orders.'))}</td></tr>`;
      return;
    }
    const rows = res.body.data || [];
    const meta = res.body.meta || {};
    tbody.innerHTML = rows.length
      ? rows.map(o => `
        <tr>
          <td style="font-weight:600;font-size:12px">${esc(o.order_no)}</td>
          <td style="font-size:12px">${esc(o.customer_name)}${o.shipping_city ? `<div style="color:var(--text-muted);font-size:11px">${esc(o.shipping_city)}</div>` : ''}</td>
          <td style="font-size:12px;color:var(--text-muted)">${esc(o.customer_phone)}</td>
          <td style="font-size:12px">${o.items_count ?? '—'}</td>
          <td style="font-size:12px">${esc(PAY_METHOD[o.payment_method] || o.payment_method)}<div style="margin-top:2px">${pill(PAY_STATUS, o.payment_status)}</div></td>
          <td>${pill(STATUS, o.status)}</td>
          <td style="font-size:12px;color:var(--text-muted)">${fmtDate(o.placed_at)}</td>
          <td class="ecom-num" style="font-weight:600">${money(o.total)}</td>
          <td style="text-align:right"><button class="crm-card-btn" data-open="${o.id}" title="Open order"><i class="fa fa-eye"></i></button></td>
        </tr>`).join('')
      : `<tr><td colspan="9" class="ecom-empty">No ${s.ordStatus ? esc(STATUS[s.ordStatus]?.label.toLowerCase()) + ' ' : ''}orders found.</td></tr>`;
    tbody.querySelectorAll('[data-open]').forEach(b => b.addEventListener('click', () => openOrder(b.dataset.open)));

    const pager = $('#ecom-ord-pager');
    pager.innerHTML = (meta.last_page || 1) > 1
      ? `<span>Page ${meta.current_page} of ${meta.last_page} · ${meta.total} orders</span>
         <button class="svc-form-btn" data-pg="-1" ${meta.current_page <= 1 ? 'disabled' : ''} style="padding:3px 9px"><i class="fa fa-chevron-left"></i></button>
         <button class="svc-form-btn" data-pg="1" ${meta.current_page >= meta.last_page ? 'disabled' : ''} style="padding:3px 9px"><i class="fa fa-chevron-right"></i></button>`
      : (meta.total ? `<span>${meta.total} order${meta.total === 1 ? '' : 's'}</span>` : '');
    pager.querySelectorAll('[data-pg]').forEach(b => b.addEventListener('click', () => { s.ordPage += Number(b.dataset.pg); loadOrders(); }));
  }

  async function openOrder(id) {
    const modal = $('#ecom-order-modal');
    alertEl('#ecom-om-alert', '');
    $('#ecom-om-title').textContent = 'Loading…';
    $('#ecom-om-items').innerHTML = '';
    modal.style.display = '';
    const res = await API.ecomOrder(id);
    if (res.status !== 200) { alertEl('#ecom-om-alert', errMsg(res, 'Failed to load order.')); return; }
    fillOrder(res.body.data);
  }

  function fillOrder(o) {
    s.order = o;
    $('#ecom-om-title').innerHTML = `Order ${esc(o.order_no)} &nbsp;${pill(STATUS, o.status)}`;
    const field = (label, val, full) => `<div class="po-field${full ? ' full' : ''}"><label class="po-field-label">${label}</label><div style="font-size:12px;color:var(--text)">${val || '—'}</div></div>`;
    $('#ecom-om-customer').innerHTML =
      field('Customer', esc(o.customer_name)) +
      field('Placed', fmtDate(o.placed_at)) +
      field('Phone', esc(o.customer_phone)) +
      field('Email', o.customer_email ? `<a href="#" data-mail="${esc(o.customer_email)}">${esc(o.customer_email)}</a>` : '') +
      field('Delivery Address', esc([o.shipping_address, o.shipping_city].filter(Boolean).join(', ')), true) +
      field('Payment', esc(PAY_METHOD[o.payment_method] || o.payment_method) + ' ' + pill(PAY_STATUS, o.payment_status) +
        (o.payment_reference ? `<div style="color:var(--text-muted);font-size:11px;margin-top:2px">Ref: ${esc(o.payment_reference)}</div>` : '')) +
      (o.notes ? field('Customer Note', esc(o.notes)) : '');
    $('#ecom-om-customer').querySelector('[data-mail]')?.addEventListener('click', e => {
      e.preventDefault();
      window.electronAPI.openExternal('mailto:' + e.currentTarget.dataset.mail + '?subject=' + encodeURIComponent('Your order ' + o.order_no));
    });
    $('#ecom-om-items').innerHTML = (o.items || []).map(i => `
      <tr><td style="font-size:12px">${esc(i.name)}</td><td style="font-size:12px;color:var(--text-muted)">${esc(i.sku || '—')}</td>
      <td class="ecom-num">${money(i.unit_price)}</td><td class="ecom-num">${+i.quantity}</td><td class="ecom-num" style="font-weight:600">${money(i.line_total)}</td></tr>`).join('');
    $('#ecom-om-totals').innerHTML = `
      <div class="ecom-status-row"><span>Subtotal</span><span>${money(o.subtotal)}</span></div>
      <div class="ecom-status-row"><span>Shipping</span><span>${o.shipping_fee ? money(o.shipping_fee) : 'Free'}</span></div>
      <div class="ecom-status-row" style="font-size:14px;font-weight:800;border-top:1px solid var(--border-light);margin-top:4px;padding-top:6px"><span>Total</span><span>${money(o.total)}</span></div>`;
    $('#ecom-om-status').value   = o.status;
    $('#ecom-om-pay').value      = o.payment_status;
    $('#ecom-om-tracking').value = o.tracking_no || '';
    $('#ecom-om-note').value     = o.internal_note || '';
  }

  async function saveOrder() {
    if (!s.order) return;
    const btn = $('#ecom-om-save');
    btn.disabled = true;
    const res = await API.ecomOrderUpdate(s.order.id, {
      status:         $('#ecom-om-status').value,
      payment_status: $('#ecom-om-pay').value,
      tracking_no:    strOrNull('#ecom-om-tracking'),
      internal_note:  strOrNull('#ecom-om-note'),
    });
    btn.disabled = false;
    if (res.status !== 200) { alertEl('#ecom-om-alert', errMsg(res, 'Update failed.')); return; }
    fillOrder(res.body.data);
    alertEl('#ecom-om-alert', 'Order updated.', true);
    if (s.view === 'orders') loadOrders(); else if (s.view === 'dashboard') loadDashboard();
  }

  const closeOrder = () => { $('#ecom-order-modal').style.display = 'none'; s.order = null; };

  // ═══════════════════════════ ONLINE PRODUCTS ═════════════════════════════
  async function loadListings() {
    const tbody = $('#ecom-lst-body');
    tbody.innerHTML = '<tr><td colspan="9" class="ecom-empty">Loading…</td></tr>';
    await ensureStore();
    const res = await API.ecomListings(s.lstQ, s.lstState);
    if (res.status !== 200) {
      tbody.innerHTML = `<tr><td colspan="9" class="ecom-empty" style="color:#ef4444">${esc(errMsg(res, 'Failed to load online products.'))}</td></tr>`;
      return;
    }
    s.listings = res.body.data || [];
    if (!s.listings.length) {
      tbody.innerHTML = `<tr><td colspan="9" class="ecom-empty">${s.lstQ || s.lstState ? 'No matching online products.' : 'No products on your website yet. Click <strong>Add Products</strong> to publish items from your catalog.'}</td></tr>`;
      return;
    }
    tbody.innerHTML = s.listings.map(l => `
      <tr>
        <td>${l.image_url ? `<img class="ecom-thumb" src="${esc(l.image_url)}" alt="">` : '<span class="ecom-thumb"><i class="fa fa-image"></i></span>'}</td>
        <td style="font-size:12px;font-weight:600">${esc(l.title)}${l.custom_title ? `<div style="font-weight:400;color:var(--text-muted);font-size:11px">${esc(l.product_name)}</div>` : ''}${l.product_active ? '' : '<div style="color:#ef4444;font-size:11px"><i class="fa fa-triangle-exclamation"></i> Product inactive — hidden on website</div>'}</td>
        <td style="font-size:12px;color:var(--text-muted)">${esc(l.sku || '—')}</td>
        <td class="ecom-num" style="font-size:12px;color:var(--text-muted)">${money(l.base_price)}</td>
        <td class="ecom-num" style="font-size:12px;font-weight:600">${money(l.price)}${l.compare_at_price ? `<div style="font-weight:400;color:var(--text-muted);text-decoration:line-through;font-size:11px">${money(l.compare_at_price)}</div>` : ''}</td>
        <td class="ecom-num" style="font-size:12px;${l.stock_quantity > 0 ? '' : 'color:#ef4444'}">${+l.stock_quantity}</td>
        <td style="text-align:center"><label class="ecom-switch"><input type="checkbox" data-toggle="is_published" data-id="${l.id}" ${l.is_published ? 'checked' : ''}><span></span></label></td>
        <td style="text-align:center"><label class="ecom-switch"><input type="checkbox" data-toggle="is_featured" data-id="${l.id}" ${l.is_featured ? 'checked' : ''}><span></span></label></td>
        <td style="text-align:right;white-space:nowrap">
          <button class="crm-card-btn" data-edit="${l.id}" title="Edit"><i class="fa fa-pencil"></i></button>
          <button class="crm-card-btn" style="color:#ef4444" data-remove="${l.id}" title="Remove from website"><i class="fa fa-trash"></i></button>
        </td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-toggle]').forEach(cb => cb.addEventListener('change', async () => {
      const res = await API.ecomListingUpdate(cb.dataset.id, { [cb.dataset.toggle]: cb.checked });
      if (res.status !== 200) { cb.checked = !cb.checked; toast(errMsg(res, 'Update failed.'), 'error'); }
    }));
    tbody.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openListing(s.listings.find(l => String(l.id) === b.dataset.edit))));
    tbody.querySelectorAll('[data-remove]').forEach(b => b.addEventListener('click', async () => {
      if (!confirm('Remove this product from your website? It stays in your catalog.')) return;
      const res = await API.ecomListingDelete(b.dataset.remove);
      if (res.status === 200) loadListings(); else toast(errMsg(res, 'Remove failed.'), 'error');
    }));
  }

  function openListing(l) {
    if (!l) return;
    $('#ecom-lm-id').value         = l.id;
    $('#ecom-lm-product').innerHTML = `Catalog product: <strong>${esc(l.product_name)}</strong> · catalog price ${money(l.base_price)}`;
    $('#ecom-lm-title').value      = l.custom_title || '';
    $('#ecom-lm-price').value      = l.online_price ?? '';
    $('#ecom-lm-compare').value    = l.compare_at_price ?? '';
    $('#ecom-lm-desc').value       = l.description || '';
    $('#ecom-lm-published').checked = l.is_published;
    $('#ecom-lm-featured').checked  = l.is_featured;
    alertEl('#ecom-lm-alert', '');
    $('#ecom-lst-modal').style.display = '';
    setTimeout(() => $('#ecom-lm-price')?.focus(), 80);
  }

  async function saveListing() {
    const id   = $('#ecom-lm-id').value;
    const body = {
      title:            strOrNull('#ecom-lm-title'),
      online_price:     numOrNull('#ecom-lm-price'),
      compare_at_price: numOrNull('#ecom-lm-compare'),
      description:      strOrNull('#ecom-lm-desc'),
      is_published:     $('#ecom-lm-published').checked,
      is_featured:      $('#ecom-lm-featured').checked,
    };
    const effective = body.online_price ?? s.listings.find(l => String(l.id) === id)?.base_price ?? 0;
    if (body.compare_at_price !== null && body.compare_at_price <= effective) {
      alertEl('#ecom-lm-alert', 'Compare-at price should be higher than the web price.');
      return;
    }
    const btn = $('#ecom-lm-save');
    btn.disabled = true;
    const res = await API.ecomListingUpdate(id, body);
    btn.disabled = false;
    if (res.status !== 200) { alertEl('#ecom-lm-alert', errMsg(res, 'Save failed.')); return; }
    $('#ecom-lst-modal').style.display = 'none';
    loadListings();
  }

  // ── Add products picker ──────────────────────────────────────────────────
  function openAdd() {
    s.picked = new Set();
    $('#ecom-add-search').value = '';
    $('#ecom-add-all').checked  = false;
    alertEl('#ecom-add-alert', '');
    updateAddBtn();
    $('#ecom-add-modal').style.display = '';
    loadCandidates();
    setTimeout(() => $('#ecom-add-search')?.focus(), 80);
  }

  async function loadCandidates() {
    const list = $('#ecom-add-list');
    list.innerHTML = '<div class="ecom-empty">Loading…</div>';
    const res = await API.ecomListingCandidates($('#ecom-add-search').value.trim());
    if (res.status !== 200) { list.innerHTML = `<div class="ecom-empty" style="color:#ef4444">${esc(errMsg(res, 'Failed to load products.'))}</div>`; return; }
    s.candidates = res.body.data || [];
    $('#ecom-add-all').checked = s.candidates.length > 0 && s.candidates.every(p => s.picked.has(p.id));
    list.innerHTML = s.candidates.length
      ? s.candidates.map(p => `
        <label class="ecom-pick-row">
          <input type="checkbox" data-pick="${p.id}" ${s.picked.has(p.id) ? 'checked' : ''}>
          ${p.image_url ? `<img class="ecom-thumb" src="${esc(p.image_url)}" alt="">` : '<span class="ecom-thumb"><i class="fa fa-image"></i></span>'}
          <span style="flex:1;min-width:0">${esc(p.name)}<div style="color:var(--text-muted);font-size:11px">${esc(p.sku || 'No SKU')} · stock ${+p.stock_quantity}</div></span>
          <strong class="ecom-num">${money(p.unit_price)}</strong>
        </label>`).join('')
      : '<div class="ecom-empty">All active catalog products are already on your website.</div>';
    list.querySelectorAll('[data-pick]').forEach(cb => cb.addEventListener('change', () => {
      const id = Number(cb.dataset.pick);
      cb.checked ? s.picked.add(id) : s.picked.delete(id);
      updateAddBtn();
    }));
  }

  function updateAddBtn() {
    const btn = $('#ecom-add-save');
    btn.disabled  = s.picked.size === 0;
    btn.innerHTML = `<i class="fa fa-cloud-arrow-up"></i> Publish ${s.picked.size} Product${s.picked.size === 1 ? '' : 's'}`;
  }

  async function saveAdd() {
    if (!s.picked.size) return;
    const btn = $('#ecom-add-save');
    btn.disabled = true;
    const res = await API.ecomListingsAdd([...s.picked]);
    btn.disabled = false;
    if (res.status !== 201) { alertEl('#ecom-add-alert', errMsg(res, 'Could not add products.')); return; }
    $('#ecom-add-modal').style.display = 'none';
    toast(`${res.body.data.added} product${res.body.data.added === 1 ? '' : 's'} published to your website.`, 'info');
    if (s.view === 'products') loadListings(); else open('products');
  }

  // ═══════════════════════════ STORE SETTINGS ══════════════════════════════
  async function loadSettings() {
    alertEl('#ecom-s-alert', '');
    const res = await API.ecomStore();
    if (res.status !== 200) { alertEl('#ecom-s-alert', errMsg(res, 'Failed to load store settings.')); return; }
    applyStore(res.body.data);
    fillSettings(res.body.data);
  }

  function setImage(kind, url) {
    if (kind === 'logo') s.logoUrl = url; else s.bannerUrl = url;
    const prev = $(`#ecom-s-${kind}-prev`);
    prev.style.backgroundImage = url ? `url("${url.replace(/"/g, '%22')}")` : '';
    prev.innerHTML = url ? '' : `<i class="fa ${kind === 'logo' ? 'fa-image' : 'fa-panorama'}"></i>`;
  }

  function fillSettings(st) {
    $('#ecom-s-published').checked = !!st.is_published;
    $('#ecom-s-name').value        = st.name || '';
    $('#ecom-s-slug').value        = st.slug || '';
    const prefix = (st.url || '').replace(/[^/]+$/, '') || '/shop/';
    $('#ecom-s-slug-prefix').textContent = prefix.replace(/^https?:\/\//, '');
    $('#ecom-s-slug-prefix').title = prefix;
    $('#ecom-s-tagline').value     = st.tagline || '';
    $('#ecom-s-about').value       = st.about || '';
    $('#ecom-s-announcement').value = st.announcement || '';
    $('#ecom-s-color').value       = st.theme_color || '#7c3aed';
    $('#ecom-s-color-hex').textContent = $('#ecom-s-color').value;
    setImage('logo', st.logo_url);
    setImage('banner', st.banner_url);
    $('#ecom-s-email').value       = st.contact_email || '';
    $('#ecom-s-phone').value       = st.contact_phone || '';
    $('#ecom-s-address').value     = st.address || '';
    $('#ecom-s-currency').value    = st.currency || '';
    $('#ecom-s-min').value         = st.min_order_amount ?? '';
    $('#ecom-s-ship').value        = st.shipping_flat_rate ?? 0;
    $('#ecom-s-free').value        = st.free_shipping_over ?? '';
    $('#ecom-s-seo-title').value   = st.seo_title || '';
    $('#ecom-s-seo-desc').value    = st.seo_description || '';
    const soc = st.social_links || {};
    $('#ecom-s-fb').value = soc.facebook || '';
    $('#ecom-s-ig').value = soc.instagram || '';
    $('#ecom-s-tt').value = soc.tiktok || '';
    $('#ecom-s-yt').value = soc.youtube || '';
  }

  async function uploadImage(kind) {
    const pick = await window.electronAPI.showOpenDialog({
      title: kind === 'logo' ? 'Choose store logo' : 'Choose store banner',
      filters: [{ name: 'Images', extensions: ['png', 'jpg', 'jpeg', 'webp', 'gif'] }],
      properties: ['openFile'],
    });
    if (pick?.canceled || !pick?.filePaths?.length) return;
    const btn = $(`#ecom-s-${kind}-up`);
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading…';
    const res = await window.electronAPI.apiUpload(API.ecomImageUploadPath, pick.filePaths[0]);
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-upload"></i> Upload';
    if (res.status === 201 && res.body?.data?.length) setImage(kind, res.body.data[0].url);
    else toast(errMsg(res, 'Upload failed.'), 'error');
  }

  async function saveSettings() {
    const name = ($('#ecom-s-name').value || '').trim();
    const slug = ($('#ecom-s-slug').value || '').trim().toLowerCase();
    if (!name) { alertEl('#ecom-s-alert', 'Store name is required.'); return; }
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) { alertEl('#ecom-s-alert', 'Web address may only contain lowercase letters, numbers and single dashes.'); return; }

    const social = {};
    [['facebook', '#ecom-s-fb'], ['instagram', '#ecom-s-ig'], ['tiktok', '#ecom-s-tt'], ['youtube', '#ecom-s-yt']]
      .forEach(([k, sel]) => { const v = strOrNull(sel); if (v) social[k] = v; });

    const body = {
      is_published:          $('#ecom-s-published').checked,
      name, slug,
      tagline:               strOrNull('#ecom-s-tagline'),
      about:                 strOrNull('#ecom-s-about'),
      announcement:          strOrNull('#ecom-s-announcement'),
      theme_color:           $('#ecom-s-color').value,
      logo_url:              s.logoUrl || null,
      banner_url:            s.bannerUrl || null,
      contact_email:         strOrNull('#ecom-s-email'),
      contact_phone:         strOrNull('#ecom-s-phone'),
      address:               strOrNull('#ecom-s-address'),
      currency:              strOrNull('#ecom-s-currency')?.toUpperCase() ?? null,
      min_order_amount:      numOrNull('#ecom-s-min'),
      shipping_flat_rate:    numOrNull('#ecom-s-ship') ?? 0,
      free_shipping_over:    numOrNull('#ecom-s-free'),
      seo_title:             strOrNull('#ecom-s-seo-title'),
      seo_description:       strOrNull('#ecom-s-seo-desc'),
      social_links:          social,
    };
    const btn = $('#ecom-s-save');
    btn.disabled = true;
    const res = await API.ecomStoreUpdate(body);
    btn.disabled = false;
    if (res.status !== 200) { alertEl('#ecom-s-alert', errMsg(res, 'Save failed.')); return; }
    applyStore(res.body.data);
    fillSettings(res.body.data);
    alertEl('#ecom-s-alert', 'Store settings saved.', true);
  }

  // ═══════════════════════════ WEBSITE TEMPLATE ════════════════════════════
  async function loadTemplates() {
    alertEl('#ecom-tpl-alert', '');
    const grid = $('#ecom-tpl-grid');
    const [tplRes] = await Promise.all([API.ecomTemplates(), ensureStore()]);
    if (tplRes.status !== 200) { grid.innerHTML = `<div class="ecom-empty" style="color:#ef4444">${esc(errMsg(tplRes, 'Failed to load templates.'))}</div>`; return; }
    const { current, templates } = tplRes.body.data;
    s.tplSelected = current;
    const color = s.store?.theme_color || '#7c3aed';
    $('#ecom-tpl-color').value = color;
    grid.innerHTML = templates.map(t => `
      <div class="ecom-tpl${t.key === current ? ' selected' : ''}" data-tpl="${esc(t.key)}" role="button" tabindex="0">
        <span class="ecom-tpl-check"><i class="fa fa-check"></i></span>
        ${t.key === current ? '<span class="ecom-tpl-current">Current</span>' : ''}
        <div class="tplp ${esc(t.key)}" style="--c:${esc(color)}">
          <div class="tplp-head"><i></i><u></u></div>
          <div class="tplp-hero"></div>
          <div class="tplp-grid">${'<span></span>'.repeat(t.key === 'modern' || t.key === 'bold' ? 3 : 4)}</div>
        </div>
        <div class="ecom-tpl-info"><b>${esc(t.name)}</b><p>${esc(t.description)}</p></div>
      </div>`).join('');
    grid.querySelectorAll('[data-tpl]').forEach(card => {
      const pick = () => {
        s.tplSelected = card.dataset.tpl;
        grid.querySelectorAll('.ecom-tpl').forEach(c => c.classList.toggle('selected', c === card));
      };
      card.addEventListener('click', pick);
      card.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); } });
    });
    $('#ecom-tpl-swatches').innerHTML = SWATCHES.map(c => `<button type="button" data-swatch="${c}" style="background:${c}" title="${c}"></button>`).join('');
    $$('#ecom-tpl-swatches [data-swatch]').forEach(b => b.addEventListener('click', () => setTplColor(b.dataset.swatch)));
  }

  function setTplColor(c) {
    $('#ecom-tpl-color').value = c;
    $$('#ecom-tpl-grid .tplp').forEach(p => p.style.setProperty('--c', c));
  }

  async function saveTemplate() {
    if (!s.tplSelected) return;
    const btn = $('#ecom-tpl-save');
    btn.disabled = true;
    const res = await API.ecomStoreUpdate({ template: s.tplSelected, theme_color: $('#ecom-tpl-color').value });
    btn.disabled = false;
    if (res.status !== 200) { alertEl('#ecom-tpl-alert', errMsg(res, 'Could not apply template.')); return; }
    applyStore(res.body.data);
    alertEl('#ecom-tpl-alert', 'Template applied to your website.', true);
    loadTemplates().then(() => alertEl('#ecom-tpl-alert', 'Template applied to your website.', true));
  }

  // ═══════════════════════════ HOMEPAGE SLIDER ═════════════════════════════
  async function loadSlides() {
    const list = $('#ecom-sld-list');
    list.innerHTML = '<div class="ecom-empty">Loading…</div>';
    const res = await API.ecomSlides();
    if (res.status !== 200) { list.innerHTML = `<div class="ecom-empty" style="color:#ef4444">${esc(errMsg(res, 'Failed to load slides.'))}</div>`; return; }
    s.slides = res.body.data || [];
    renderSlides();
  }

  function renderSlides() {
    const list = $('#ecom-sld-list');
    $('#ecom-sld-add').disabled = s.slides.length >= 10;
    if (!s.slides.length) {
      list.innerHTML = '<div class="ecom-empty"><i class="fa fa-images" style="font-size:28px;display:block;margin-bottom:8px"></i>No slides yet. Without slides your store shows the banner from Store Settings.<br>Click <strong>Add Slide</strong> to create a rotating homepage slider.</div>';
      return;
    }
    list.innerHTML = s.slides.map((sl, i) => `
      <div class="ecom-slide-row${sl.is_active ? '' : ' off'}">
        <div class="ecom-slide-thumb" style="background-image:url('${esc(sl.image_url).replace(/'/g, '%27')}')"></div>
        <div class="ecom-slide-meta">
          <b>${esc(sl.title || 'Untitled slide')}</b>
          ${sl.subtitle ? esc(sl.subtitle) + '<br>' : ''}
          ${sl.button_text ? `<i class="fa fa-arrow-pointer"></i> ${esc(sl.button_text)}${sl.button_link ? ' → ' + esc(sl.button_link) : ''}` : ''}
        </div>
        <div class="ecom-slide-actions">
          <label class="ecom-switch" title="Show on website"><input type="checkbox" data-sl-active="${sl.id}" ${sl.is_active ? 'checked' : ''}><span></span></label>
          <button class="crm-card-btn" data-sl-move="${i}" data-dir="-1" title="Move up" ${i === 0 ? 'disabled' : ''}><i class="fa fa-arrow-up"></i></button>
          <button class="crm-card-btn" data-sl-move="${i}" data-dir="1" title="Move down" ${i === s.slides.length - 1 ? 'disabled' : ''}><i class="fa fa-arrow-down"></i></button>
          <button class="crm-card-btn" data-sl-edit="${sl.id}" title="Edit"><i class="fa fa-pencil"></i></button>
          <button class="crm-card-btn" style="color:#ef4444" data-sl-del="${sl.id}" title="Delete"><i class="fa fa-trash"></i></button>
        </div>
      </div>`).join('');

    list.querySelectorAll('[data-sl-active]').forEach(cb => cb.addEventListener('change', async () => {
      const res = await API.ecomSlideUpdate(cb.dataset.slActive, { is_active: cb.checked });
      if (res.status !== 200) { cb.checked = !cb.checked; toast(errMsg(res, 'Update failed.'), 'error'); return; }
      Object.assign(s.slides.find(x => String(x.id) === cb.dataset.slActive), res.body.data);
      renderSlides();
    }));
    list.querySelectorAll('[data-sl-move]').forEach(b => b.addEventListener('click', async () => {
      const i = Number(b.dataset.slMove), j = i + Number(b.dataset.dir);
      [s.slides[i], s.slides[j]] = [s.slides[j], s.slides[i]];
      renderSlides();
      const res = await API.ecomSlidesReorder(s.slides.map(x => x.id));
      if (res.status === 200) s.slides = res.body.data; else { toast(errMsg(res, 'Reorder failed.'), 'error'); loadSlides(); }
    }));
    list.querySelectorAll('[data-sl-edit]').forEach(b => b.addEventListener('click', () => openSlide(s.slides.find(x => String(x.id) === b.dataset.slEdit))));
    list.querySelectorAll('[data-sl-del]').forEach(b => b.addEventListener('click', async () => {
      if (!confirm('Delete this slide?')) return;
      const res = await API.ecomSlideDelete(b.dataset.slDel);
      if (res.status === 200) loadSlides(); else toast(errMsg(res, 'Delete failed.'), 'error');
    }));
  }

  function setSlideImage(url) {
    s.slideImg = url;
    $('#ecom-sm-prev').style.backgroundImage = url ? `url("${url.replace(/"/g, '%22')}")` : '';
    $('#ecom-sm-prev-ph').style.display = url ? 'none' : '';
  }
  function syncSlidePreview() {
    $('#ecom-sm-prev-title').textContent = $('#ecom-sm-title-in').value;
    $('#ecom-sm-prev-sub').textContent   = $('#ecom-sm-sub').value;
  }

  function openSlide(sl) {
    $('#ecom-sm-title').textContent = sl ? 'Edit Slide' : 'Add Slide';
    $('#ecom-sm-id').value       = sl?.id ?? '';
    $('#ecom-sm-title-in').value = sl?.title ?? '';
    $('#ecom-sm-sub').value      = sl?.subtitle ?? '';
    $('#ecom-sm-btn').value      = sl?.button_text ?? '';
    $('#ecom-sm-link').value     = sl?.button_link ?? '';
    $('#ecom-sm-active').checked = sl ? !!sl.is_active : true;
    setSlideImage(sl?.image_url || null);
    syncSlidePreview();
    alertEl('#ecom-sm-alert', '');
    $('#ecom-sld-modal').style.display = '';
  }

  async function pickAndUpload(title, btn) {
    const pick = await window.electronAPI.showOpenDialog({
      title, filters: [{ name: 'Images', extensions: ['png', 'jpg', 'jpeg', 'webp', 'gif'] }], properties: ['openFile'],
    });
    if (pick?.canceled || !pick?.filePaths?.length) return null;
    const html = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading…';
    const res = await window.electronAPI.apiUpload(API.ecomImageUploadPath, pick.filePaths[0]);
    btn.disabled = false;
    btn.innerHTML = html;
    if (res.status === 201 && res.body?.data?.length) return res.body.data[0].url;
    toast(errMsg(res, 'Upload failed.'), 'error');
    return null;
  }

  async function saveSlide() {
    const id = $('#ecom-sm-id').value;
    if (!s.slideImg) { alertEl('#ecom-sm-alert', 'Upload a slide image first.'); return; }
    const body = {
      image_url:   s.slideImg,
      title:       strOrNull('#ecom-sm-title-in'),
      subtitle:    strOrNull('#ecom-sm-sub'),
      button_text: strOrNull('#ecom-sm-btn'),
      button_link: strOrNull('#ecom-sm-link'),
      is_active:   $('#ecom-sm-active').checked,
    };
    if (body.button_link && !/^https?:\/\//i.test(body.button_link)) { alertEl('#ecom-sm-alert', 'Button link must start with http:// or https://'); return; }
    const btn = $('#ecom-sm-save');
    btn.disabled = true;
    const res = id ? await API.ecomSlideUpdate(id, body) : await API.ecomSlideCreate(body);
    btn.disabled = false;
    if (res.status !== 200 && res.status !== 201) { alertEl('#ecom-sm-alert', errMsg(res, 'Save failed.')); return; }
    $('#ecom-sld-modal').style.display = 'none';
    loadSlides();
  }

  // ═══════════════════════════ PAYMENT GATEWAYS ════════════════════════════
  async function loadGateways() {
    alertEl('#ecom-pay-offline-alert', '');
    const box = $('#ecom-pay-gateways');
    box.innerHTML = '<div class="ecom-empty">Loading…</div>';
    const res = await API.ecomGateways();
    if (res.status !== 200) { box.innerHTML = `<div class="ecom-empty" style="color:#ef4444">${esc(errMsg(res, 'Failed to load payment settings.'))}</div>`; return; }
    const d = res.body.data;
    $('#ecom-pay-cod').checked = !!d.offline.cod_enabled;
    $('#ecom-pay-bank').checked = !!d.offline.bank_transfer_enabled;
    $('#ecom-pay-bank-details').value = d.offline.bank_transfer_details || '';
    $('#ecom-pay-bank-wrap').style.display = d.offline.bank_transfer_enabled ? '' : 'none';

    const warn = $('#ecom-pay-currency-warn');
    const isoOk = /^[A-Z]{3}$/.test(String(d.currency || '').toUpperCase());
    warn.style.display = isoOk ? 'none' : '';
    warn.innerHTML = '<i class="fa fa-triangle-exclamation"></i> Online payments need a 3-letter currency code (e.g. <b>LKR</b> or <b>USD</b>). Set it in <a href="#" data-ecom-goto="settings">Store Settings</a> first.';

    s.gateways = d.gateways || [];
    box.innerHTML = s.gateways.map(g => {
      const status = g.is_enabled && g.configured
        ? `<span class="ecom-st ecom-st-paid"><i class="fa fa-circle-check"></i> Active${g.is_sandbox ? ' · Sandbox' : ''}</span>`
        : (g.configured ? '<span class="ecom-st ecom-st-unpaid"><i class="fa fa-pause"></i> Disabled</span>' : '<span class="ecom-st ecom-st-unpaid"><i class="fa fa-plug"></i> Not connected</span>');
      const fields = g.fields.map(f => `
        <div class="po-field">
          <label class="po-field-label">${esc(f.label)}${f.secret && f.is_set ? ` <span style="font-weight:400;color:var(--text-muted)">(saved ${esc(f.hint)})</span>` : ''}</label>
          <input type="${f.secret ? 'password' : 'text'}" class="po-field-input" data-gw-field="${esc(f.key)}" autocomplete="off" spellcheck="false"
            value="${f.secret ? '' : esc(f.value || '')}" placeholder="${f.secret && f.is_set ? 'Leave blank to keep the saved value' : ''}">
        </div>`).join('');
      return `
        <div class="ecom-gw" data-gw="${esc(g.key)}">
          <div class="ecom-gw-head">
            <div class="ecom-gw-logo ${esc(g.key)}"><i class="${g.key === 'stripe' ? 'fa-brands fa-stripe-s' : 'fa fa-credit-card'}"></i></div>
            <div class="ecom-gw-title"><b>${esc(g.name)}</b> ${status}<p>${esc(g.description)}</p></div>
            <label class="ecom-check" title="Offer at checkout"><span class="ecom-switch"><input type="checkbox" data-gw-enabled ${g.is_enabled ? 'checked' : ''}><span></span></span> Enabled</label>
          </div>
          <div class="ecom-gw-body">
            <div class="ecom-grid2">
              ${fields}
              <label class="ecom-check full"><span class="ecom-switch"><input type="checkbox" data-gw-sandbox ${g.is_sandbox ? 'checked' : ''}><span></span></span> Sandbox / test mode — no real money is charged</label>
            </div>
            <div class="ecom-gw-foot">
              <span class="ecom-gw-help"><i class="fa fa-circle-question"></i> ${esc(GW_HELP[g.key]?.text || '')} <a href="#" data-gw-help="${esc(g.key)}">Open</a></span>
              <span class="svc-form-alert" data-gw-alert style="display:none"></span>
              <button class="po-btn-primary" data-gw-save><i class="fa fa-floppy-disk"></i> Save ${esc(g.name)}</button>
            </div>
          </div>
        </div>`;
    }).join('');

    box.querySelectorAll('[data-gw]').forEach(card => {
      const key = card.dataset.gw;
      card.querySelector('[data-gw-save]').addEventListener('click', () => saveGateway(card, key));
      card.querySelector('[data-gw-help]').addEventListener('click', e => { e.preventDefault(); window.electronAPI.openExternal(GW_HELP[key].url); });
    });
  }

  async function saveGateway(card, key) {
    const alertBox = card.querySelector('[data-gw-alert]');
    const show = (msg, ok) => { alertBox.textContent = msg; alertBox.style.display = msg ? '' : 'none'; alertBox.style.color = ok ? '#10b981' : '#ef4444'; };
    const body = {
      is_enabled: card.querySelector('[data-gw-enabled]').checked,
      is_sandbox: card.querySelector('[data-gw-sandbox]').checked,
    };
    card.querySelectorAll('[data-gw-field]').forEach(inp => {
      const v = inp.value.trim();
      if (inp.type !== 'password' || v) body[inp.dataset.gwField] = v;
    });
    const btn = card.querySelector('[data-gw-save]');
    btn.disabled = true;
    const res = await API.ecomGatewayUpdate(key, body);
    btn.disabled = false;
    if (res.status !== 200) { show(errMsg(res, 'Save failed.')); return; }
    await loadGateways();
    const fresh = $(`#ecom-pay-gateways [data-gw="${key}"] [data-gw-alert]`);
    if (fresh) { fresh.textContent = 'Saved.'; fresh.style.display = ''; fresh.style.color = '#10b981'; }
  }

  async function saveOfflinePayments() {
    const body = {
      cod_enabled:           $('#ecom-pay-cod').checked,
      bank_transfer_enabled: $('#ecom-pay-bank').checked,
      bank_transfer_details: strOrNull('#ecom-pay-bank-details'),
    };
    if (body.bank_transfer_enabled && !body.bank_transfer_details) { alertEl('#ecom-pay-offline-alert', 'Add your bank account details so customers know where to pay.'); return; }
    const btn = $('#ecom-pay-offline-save');
    btn.disabled = true;
    const res = await API.ecomStoreUpdate(body);
    btn.disabled = false;
    if (res.status !== 200) { alertEl('#ecom-pay-offline-alert', errMsg(res, 'Save failed.')); return; }
    applyStore(res.body.data);
    alertEl('#ecom-pay-offline-alert', 'Saved.', true);
  }

  // ═══════════════════════════ LISTENERS ═══════════════════════════════════
  function wire() {
    $$('[data-ecomview]').forEach(b => b.addEventListener('click', () => switchView(b.dataset.ecomview)));

    // Ribbon
    $('#rb-ecom-dashboard')?.addEventListener('click',      () => open('dashboard'));
    $('#rb-ecom-orders')?.addEventListener('click',         () => { s.ordStatus = ''; s.ordPage = 1; open('orders'); });
    $('#rb-ecom-orders-pending')?.addEventListener('click', () => { s.ordStatus = 'pending'; s.ordPage = 1; open('orders'); });
    $('#rb-ecom-orders-shipped')?.addEventListener('click', () => { s.ordStatus = 'shipped'; s.ordPage = 1; open('orders'); });
    $('#rb-ecom-products')?.addEventListener('click',       () => open('products'));
    $('#rb-ecom-add-products')?.addEventListener('click',   () => open('products', openAdd));
    $('#rb-ecom-settings')?.addEventListener('click',       () => open('settings'));
    $('#rb-ecom-visit')?.addEventListener('click',          visitWebsite);
    $('#rb-ecom-copy-link')?.addEventListener('click',      copyLink);
    $('#rb-ecom-template')?.addEventListener('click',       () => open('template'));
    $('#rb-ecom-slider')?.addEventListener('click',         () => open('slider'));
    $('#rb-ecom-payments')?.addEventListener('click',       () => open('payments'));
    $('#rb-ecom-refresh')?.addEventListener('click',        () => { s.store = null; switchView(s.view); });

    // In-panel links between views (e.g. Settings → Payment Gateways)
    $('#panel-ecommerce')?.addEventListener('click', e => {
      const a = e.target.closest('[data-ecom-goto]');
      if (a) { e.preventDefault(); switchView(a.dataset.ecomGoto); }
    });

    // Website template
    $('#ecom-tpl-save')?.addEventListener('click', saveTemplate);
    $('#ecom-tpl-preview')?.addEventListener('click', visitWebsite);
    $('#ecom-tpl-color')?.addEventListener('input', e => setTplColor(e.target.value));

    // Slider
    $('#ecom-sld-add')?.addEventListener('click', () => openSlide(null));
    const closeSld = () => { $('#ecom-sld-modal').style.display = 'none'; };
    $('#ecom-sm-close')?.addEventListener('click',  closeSld);
    $('#ecom-sm-cancel')?.addEventListener('click', closeSld);
    $('#ecom-sm-save')?.addEventListener('click',   saveSlide);
    $('#ecom-sld-modal')?.addEventListener('click', e => { if (e.target.id === 'ecom-sld-modal') closeSld(); });
    $('#ecom-sm-upload')?.addEventListener('click', async e => {
      const url = await pickAndUpload('Choose slide image', e.currentTarget);
      if (url) setSlideImage(url);
    });
    ['#ecom-sm-title-in', '#ecom-sm-sub'].forEach(sel => $(sel)?.addEventListener('input', syncSlidePreview));

    // Payments
    $('#ecom-pay-offline-save')?.addEventListener('click', saveOfflinePayments);
    $('#ecom-pay-bank')?.addEventListener('change', e => { $('#ecom-pay-bank-wrap').style.display = e.target.checked ? '' : 'none'; });

    // Panel header + dashboard hero
    $('#ecom-visit-btn')?.addEventListener('click',     visitWebsite);
    $('#ecom-hero-publish')?.addEventListener('click',  togglePublish);
    $('#ecom-hero-products')?.addEventListener('click', () => { switchView('products'); openAdd(); });
    $('#ecom-hero-settings')?.addEventListener('click', () => switchView('settings'));
    $('#ecom-hero-copy')?.addEventListener('click',     copyLink);
    $('#ecom-see-orders')?.addEventListener('click', e => { e.preventDefault(); s.ordStatus = ''; switchView('orders'); });

    // Orders
    $('#ecom-ord-search')?.addEventListener('input', debounce(e => { s.ordQ = e.target.value.trim(); s.ordPage = 1; loadOrders(); }));
    $$('#ecom-ord-chips .ecom-chip').forEach(c => c.addEventListener('click', () => { s.ordStatus = c.dataset.status; s.ordPage = 1; loadOrders(); }));
    $('#ecom-om-close')?.addEventListener('click',  closeOrder);
    $('#ecom-om-cancel')?.addEventListener('click', closeOrder);
    $('#ecom-om-save')?.addEventListener('click',   saveOrder);
    $('#ecom-order-modal')?.addEventListener('click', e => { if (e.target.id === 'ecom-order-modal') closeOrder(); });

    // Online products
    $('#ecom-lst-search')?.addEventListener('input', debounce(e => { s.lstQ = e.target.value.trim(); loadListings(); }));
    $('#ecom-lst-state')?.addEventListener('change', e => { s.lstState = e.target.value; loadListings(); });
    $('#ecom-lst-add')?.addEventListener('click', openAdd);
    const closeLst = () => { $('#ecom-lst-modal').style.display = 'none'; };
    $('#ecom-lm-close')?.addEventListener('click',  closeLst);
    $('#ecom-lm-cancel')?.addEventListener('click', closeLst);
    $('#ecom-lm-save')?.addEventListener('click',   saveListing);
    $('#ecom-lst-modal')?.addEventListener('click', e => { if (e.target.id === 'ecom-lst-modal') closeLst(); });

    // Add products picker
    const closeAdd = () => { $('#ecom-add-modal').style.display = 'none'; };
    $('#ecom-add-search')?.addEventListener('input', debounce(loadCandidates));
    $('#ecom-add-all')?.addEventListener('change', e => {
      s.candidates.forEach(p => e.target.checked ? s.picked.add(p.id) : s.picked.delete(p.id));
      $$('#ecom-add-list [data-pick]').forEach(cb => { cb.checked = e.target.checked; });
      updateAddBtn();
    });
    $('#ecom-add-close')?.addEventListener('click',  closeAdd);
    $('#ecom-add-cancel')?.addEventListener('click', closeAdd);
    $('#ecom-add-save')?.addEventListener('click',   saveAdd);
    $('#ecom-add-modal')?.addEventListener('click', e => { if (e.target.id === 'ecom-add-modal') closeAdd(); });

    // Settings
    $('#ecom-s-save')?.addEventListener('click', saveSettings);
    $('#ecom-s-slug')?.addEventListener('input', e => { e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-{2,}/g, '-'); });
    $('#ecom-s-color')?.addEventListener('input', (e) => { $('#ecom-s-color-hex').textContent = e.target.value; });
    $('#ecom-s-logo-up')?.addEventListener('click',    () => uploadImage('logo'));
    $('#ecom-s-banner-up')?.addEventListener('click',  () => uploadImage('banner'));
    $('#ecom-s-logo-clear')?.addEventListener('click',   () => setImage('logo', null));
    $('#ecom-s-banner-clear')?.addEventListener('click', () => setImage('banner', null));
  }

  wire();

  return { view: () => s.view, switchView, open, openOrder };
})();

window.Ecom           = Ecom;
window.switchEcomView = Ecom.switchView;
