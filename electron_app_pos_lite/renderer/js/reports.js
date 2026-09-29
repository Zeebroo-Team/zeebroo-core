'use strict';

// Reports & Summaries — an in-page modal dialog, opened by calling
// window.openReportsModal() (see the dashboard's "Reports & Summaries" tile
// in js/dashboard.js). Mirrors js/sales.js and js/stock.js: builds its own
// DOM on open and tears it down on close, reuses the same .salm-* / .qf-*
// CSS classes from css/sales.css plus a small set of .rpt-* rules for the
// charts (also in css/sales.css). Talks to the same Laravel POS API
// (Modules/Pos/routes/api.php, prefix /api/v1/pos) via js/api.js — the
// /today-summary and /profit-report endpoints already exist there and are
// used by the full desktop app and the mobile app.
//
// Inside the dialog: a card-grid home (Today's Summary / Recent Activity /
// Analytics / Profit Report) and each section's own read-only report — see
// #rpt-home-view / #rpt-section-view below. Reports are read-only, so unlike
// Sales/Stock there's no record-detail popup layered on top.
//
// Recent Activity and Analytics have no dedicated Laravel endpoints (the
// full desktop app's equivalent tabs don't either — see electron_app's
// _homeActivityLoad/_homeAnalyticsLoad in js/app.js): they're built from the
// existing /sales and /sales/history endpoints, same as Sales Management.
(function () {
  let cardEl, homeView, sectionView;
  let sectionTitleEl, sectionDescEl, sectionBody;

  let posSettings = {};
  let settingsLoaded = false;
  let profitPeriod = 30;
  let analyticsDays = 30;

  const SECTIONS = [
    { key: 'today', icon: 'fa-calendar-day', title: "Today's Summary", desc: "Today's sales, payment split, and top sellers.", accent: '#0ea5e9' },
    { key: 'activity', icon: 'fa-clock-rotate-left', title: 'Recent Activity', desc: 'Your most recent sales, newest first.', accent: '#0d9488' },
    { key: 'analytics', icon: 'fa-chart-column', title: 'Analytics', desc: 'Revenue trend and sales patterns over time.', accent: '#d97706' },
    { key: 'profit', icon: 'fa-chart-pie', title: 'Profit Report', desc: 'Revenue, cost, and profit trend over time.', accent: '#7c3aed' },
  ];

  // ── Small shared helpers (same as js/sales.js / js/stock.js) ───────────
  function esc(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function rgb(hex) {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }
  function glow(hex, alpha) { const [r, g, b] = rgb(hex); return `rgba(${r}, ${g}, ${b}, ${alpha})`; }
  function lighten(hex, amount) {
    const [r, g, b] = rgb(hex).map((c) => Math.round(c + (255 - c) * amount));
    return `rgb(${r}, ${g}, ${b})`;
  }

  function money(n) {
    const amount = (Number(n) || 0).toFixed(2);
    const currency = (posSettings.currency || 'LKR').toUpperCase();
    return posSettings.currency_position === 'before' ? `${currency} ${amount}` : `${amount} ${currency}`;
  }

  function fmtTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleTimeString(i18n.locale, { hour: '2-digit', minute: '2-digit' });
  }

  function fmtDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleString(i18n.locale, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function fmtShortDate(dateStr) {
    const d = new Date(`${dateStr}T00:00:00`);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString(i18n.locale, { month: 'short', day: 'numeric' });
  }

  async function loadSettings() {
    if (settingsLoaded) return;
    const res = await API.settingsGet();
    if (res.status === 200) posSettings = res.body?.data || {};
    settingsLoaded = true;
  }

  function methodLabel(method) {
    return { cash: t('Cash'), card: t('Card'), credit: t('Credit') }[method] || esc(method) || t('Other');
  }

  // ── View switching (home ↔ section report) ──────────────────────────────
  function showView(name) {
    homeView.style.display = name === 'home' ? '' : 'none';
    sectionView.style.display = name === 'section' ? '' : 'none';
    cardEl.classList.toggle('salm-modal-card--wide', name === 'section');
    window.scrollTo(0, 0);
  }

  function goHome() {
    document.getElementById('rpt-grid').innerHTML = SECTIONS.map((s) => `
      <button class="salm-tile" data-key="${s.key}" style="--tile-accent:${s.accent};--tile-accent-2:${lighten(s.accent, 0.35)};--tile-glow:${glow(s.accent, 0.25)}">
        <div class="salm-tile-icon"><i class="fa-solid ${s.icon}"></i></div>
        <div class="salm-tile-title">${t(s.title)}</div>
        <p class="salm-tile-desc">${t(s.desc)}</p>
        <span class="salm-tile-open">${t('Open')} <i class="fa-solid fa-arrow-right"></i></span>
      </button>`).join('');

    document.querySelectorAll('#rpt-grid .salm-tile').forEach((el) => {
      el.addEventListener('click', () => openSection(el.dataset.key));
    });

    showView('home');
  }

  const VIEWS = { today: renderToday, activity: renderActivity, analytics: renderAnalytics, profit: renderProfit };

  function openSection(key) {
    const meta = SECTIONS.find((s) => s.key === key);
    sectionTitleEl.textContent = t(meta.title);
    sectionDescEl.textContent = t(meta.desc);
    VIEWS[key]();
    showView('section');
  }

  // ════════════════════════════════════════════════════════════════════════
  // Today's Summary
  // ════════════════════════════════════════════════════════════════════════
  function renderToday() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <span class="salm-count-pill" id="today-asof"></span>
        <button class="salm-icon-btn" id="today-refresh" style="margin-left:auto"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-kpi-bar" id="today-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Sales')}</span><span class="salm-kpi-val" id="today-kpi-count">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Revenue')}</span><span class="salm-kpi-val accent" id="today-kpi-revenue">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Gross Profit')}</span><span class="salm-kpi-val" id="today-kpi-profit">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Items Sold')}</span><span class="salm-kpi-val" id="today-kpi-items">0</span></div>
      </div>
      <div class="rpt-cols">
        <div class="rpt-col">
          <div class="salm-section-label">${t('By Payment Method')}</div>
          <div class="salm-item-list" id="today-methods"><div class="salm-loading">${t('Loading…')}</div></div>
        </div>
        <div class="rpt-col">
          <div class="salm-section-label">${t('Top Products')}</div>
          <div class="salm-item-list" id="today-top-products"><div class="salm-loading">${t('Loading…')}</div></div>
        </div>
      </div>
      <div class="salm-section-label">${t('Sales by Hour')}</div>
      <div class="rpt-bar-chart" id="today-hourly"><div class="salm-loading">${t('Loading…')}</div></div>
      <div class="salm-section-label">${t('Recent Sales')}</div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Sale #')}</th><th>${t('Time')}</th><th>${t('Payment')}</th><th>${t('Items')}</th><th style="text-align:right">${t('Total')}</th></tr></thead>
        <tbody id="today-recent-rows"><tr><td colspan="5" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>`;

    document.getElementById('today-refresh').addEventListener('click', loadToday);
    loadToday();
  }

  async function loadToday() {
    document.getElementById('today-kpi').style.display = 'none';
    document.getElementById('today-methods').innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    document.getElementById('today-top-products').innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    document.getElementById('today-hourly').innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    document.getElementById('today-recent-rows').innerHTML = `<tr><td colspan="5" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();

    const res = await API.todaySummary();
    document.getElementById('today-asof').textContent = t('As of {time}', { time: fmtTime(new Date().toISOString()) });

    if (res.status !== 200) {
      const msg = t('Could not load today’s summary ({reason}).', { reason: t(res.body?.message) || res.status });
      document.getElementById('today-methods').innerHTML = `<div class="salm-empty">${msg}</div>`;
      document.getElementById('today-top-products').innerHTML = '';
      document.getElementById('today-hourly').innerHTML = '';
      document.getElementById('today-recent-rows').innerHTML = `<tr><td colspan="5" class="salm-empty">${msg}</td></tr>`;
      return;
    }

    const d = res.body.data || {};
    const sales = d.sales || {};

    document.getElementById('today-kpi').style.display = '';
    document.getElementById('today-kpi-count').textContent = sales.count ?? 0;
    document.getElementById('today-kpi-revenue').textContent = money(sales.revenue);
    document.getElementById('today-kpi-profit').textContent = money(sales.gross_profit);
    document.getElementById('today-kpi-items').textContent = sales.items_sold ?? 0;

    const methodEntries = Object.entries(sales.by_method || {});
    document.getElementById('today-methods').innerHTML = methodEntries.length
      ? methodEntries.map(([method, m]) => `
        <div class="salm-item-row">
          <div><div class="salm-item-name">${esc(methodLabel(method))}</div><div class="salm-item-meta">${t('{n} sales', { n: m.count })}</div></div>
          <div class="salm-item-total">${money(m.total)}</div>
        </div>`).join('')
      : `<div class="salm-item-row"><span class="salm-item-meta">${t('No sales yet today.')}</span></div>`;

    const topProducts = d.top_products || [];
    document.getElementById('today-top-products').innerHTML = topProducts.length
      ? topProducts.map((p) => `
        <div class="salm-item-row">
          <div><div class="salm-item-name">${esc(p.name)}</div><div class="salm-item-meta">${t('{n} sold', { n: p.qty })}</div></div>
          <div class="salm-item-total">${money(p.revenue)}</div>
        </div>`).join('')
      : `<div class="salm-item-row"><span class="salm-item-meta">${t('No sales yet today.')}</span></div>`;

    document.getElementById('today-hourly').innerHTML = buildHourlyChart(sales.hourly || []);

    const recent = d.recent_sales || [];
    document.getElementById('today-recent-rows').innerHTML = recent.length
      ? recent.map((s) => `
        <tr>
          <td class="salm-ref"><i class="fa-solid fa-receipt"></i>${esc(s.sale_number || `#${s.id}`)}</td>
          <td class="salm-muted-cell">${fmtTime(s.sold_at)}</td>
          <td class="salm-muted-cell">${esc(methodLabel(s.payment_method))}</td>
          <td class="salm-muted-cell">${s.items_count}</td>
          <td class="salm-amt-cell">${money(s.total)}</td>
        </tr>`).join('')
      : `<tr><td colspan="5" class="salm-empty">${t('No sales recorded today yet.')}</td></tr>`;
  }

  function buildHourlyChart(hourly) {
    if (!hourly.length) return `<div class="salm-empty">${t('No data yet.')}</div>`;
    const max = Math.max(1, ...hourly.map((h) => h.revenue || 0));
    const bars = hourly.map((h, hr) => {
      const pct = Math.max(2, Math.round(((h.revenue || 0) / max) * 100));
      const label = hr === 0 ? '12a' : hr < 12 ? `${hr}a` : hr === 12 ? '12p' : `${hr - 12}p`;
      return `<div class="rpt-bar" title="${esc(label)}: ${esc(money(h.revenue))} (${h.count} ${t('sales')})">
        <div class="rpt-bar-fill" style="height:${pct}%"></div>
        ${hr % 3 === 0 ? `<span class="rpt-bar-label">${label}</span>` : ''}
      </div>`;
    }).join('');
    return `<div class="rpt-bars">${bars}</div>`;
  }

  // ════════════════════════════════════════════════════════════════════════
  // Recent Activity — a flat feed of the last 50 sales, newest first (same
  // idea as the full desktop app's Recent Activity tab / _homeActivityLoad,
  // just capped client-side via the existing /sales?limit= param).
  // ════════════════════════════════════════════════════════════════════════
  function renderActivity() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <span class="salm-count-pill" id="activity-count"></span>
        <button class="salm-icon-btn" id="activity-refresh" style="margin-left:auto"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-item-list" id="activity-rows"><div class="salm-loading">${t('Loading…')}</div></div>`;

    document.getElementById('activity-refresh').addEventListener('click', loadActivity);
    loadActivity();
  }

  async function loadActivity() {
    document.getElementById('activity-rows').innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    document.getElementById('activity-count').textContent = '';
    await loadSettings();

    const res = await API.sales({ limit: 50 });
    if (res.status !== 200) {
      document.getElementById('activity-rows').innerHTML = `<div class="salm-empty">${t('Could not load recent activity ({reason}).', { reason: t(res.body?.message) || res.status })}</div>`;
      return;
    }

    const list = res.body.data || [];
    document.getElementById('activity-count').textContent = list.length
      ? t(list.length === 1 ? '{n} recent sale' : '{n} recent sales', { n: list.length })
      : t('No recent sales');

    document.getElementById('activity-rows').innerHTML = list.length
      ? list.map((s) => `
        <div class="salm-item-row">
          <div>
            <div class="salm-item-name">${t('Sale')} ${esc(s.sale_number || `#${s.id}`)} ${s.status === 'void' ? `<span class="salm-badge red">${t('Voided')}</span>` : ''}</div>
            <div class="salm-item-meta">${fmtDateTime(s.sold_at)} · ${esc(methodLabel(s.payment_method))}${s.customer_name ? ' · ' + esc(s.customer_name) : ''}</div>
          </div>
          <div class="salm-item-total">${money(s.total)}</div>
        </div>`).join('')
      : `<div class="salm-item-row"><span class="salm-item-meta">${t('No sales recorded yet.')}</span></div>`;
  }

  // ════════════════════════════════════════════════════════════════════════
  // Analytics — revenue KPIs, a daily revenue trend, and a by-weekday
  // breakdown, all built from the /sales/history endpoint's `summary` and
  // `chart` fields for the selected date range (mirrors the full desktop
  // app's Analytics tab / _homeAnalyticsLoad, but lets the server do the
  // date-bucketing instead of pulling every sale down to aggregate in JS).
  // ════════════════════════════════════════════════════════════════════════
  function renderAnalytics() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <select class="salm-select" id="analytics-period">
          <option value="7">${t('Last 7 days')}</option>
          <option value="14">${t('Last 14 days')}</option>
          <option value="30">${t('Last 30 days')}</option>
          <option value="90">${t('Last 90 days')}</option>
        </select>
        <button class="salm-icon-btn" id="analytics-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-kpi-bar" id="analytics-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Completed Sales')}</span><span class="salm-kpi-val" id="analytics-kpi-count">0</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Revenue')}</span><span class="salm-kpi-val accent" id="analytics-kpi-revenue">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Average Sale')}</span><span class="salm-kpi-val" id="analytics-kpi-avg">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Voided')}</span><span class="salm-kpi-val" id="analytics-kpi-void">0</span></div>
      </div>
      <div class="salm-section-label">${t('Revenue Trend')}</div>
      <div class="rpt-trend-card" id="analytics-trend"><div class="salm-loading">${t('Loading…')}</div></div>
      <div class="salm-section-label">${t('Revenue by Day of Week')}</div>
      <div class="rpt-bar-chart" id="analytics-weekday"><div class="salm-loading">${t('Loading…')}</div></div>`;

    document.getElementById('analytics-period').value = String(analyticsDays);
    document.getElementById('analytics-period').addEventListener('change', (e) => { analyticsDays = Number(e.target.value); loadAnalytics(); });
    document.getElementById('analytics-refresh').addEventListener('click', loadAnalytics);
    loadAnalytics();
  }

  async function loadAnalytics() {
    document.getElementById('analytics-kpi').style.display = 'none';
    document.getElementById('analytics-trend').innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    document.getElementById('analytics-weekday').innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    await loadSettings();

    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (analyticsDays - 1));
    const iso = (d) => d.toISOString().slice(0, 10);

    const res = await API.salesHistory({ dateFrom: iso(from), dateTo: iso(to), page: 1 });
    if (res.status !== 200) {
      const msg = t('Could not load analytics ({reason}).', { reason: t(res.body?.message) || res.status });
      document.getElementById('analytics-trend').innerHTML = `<div class="salm-empty">${msg}</div>`;
      document.getElementById('analytics-weekday').innerHTML = '';
      return;
    }

    const summary = res.body.summary || {};
    const chart = res.body.chart || [];

    document.getElementById('analytics-kpi').style.display = '';
    document.getElementById('analytics-kpi-count').textContent = summary.completed_count ?? 0;
    document.getElementById('analytics-kpi-revenue').textContent = money(summary.completed_total);
    document.getElementById('analytics-kpi-avg').textContent = money(summary.completed_count ? summary.completed_total / summary.completed_count : 0);
    document.getElementById('analytics-kpi-void').textContent = summary.void_count ?? 0;

    document.getElementById('analytics-trend').innerHTML = buildLineChart(
      chart.map((c) => fmtShortDate(c.date)),
      [{ name: t('Revenue'), color: '#0ea5e9', data: chart.map((c) => c.total) }]
    );

    document.getElementById('analytics-weekday').innerHTML = buildWeekdayChart(chart);
  }

  function buildWeekdayChart(chart) {
    if (!chart.length) return `<div class="salm-empty">${t('No data yet.')}</div>`;
    const totals = [0, 0, 0, 0, 0, 0, 0];
    chart.forEach((c) => {
      const d = new Date(`${c.date}T00:00:00`);
      if (!isNaN(d.getTime())) totals[d.getDay()] += c.total || 0;
    });
    const labels = [t('Sun'), t('Mon'), t('Tue'), t('Wed'), t('Thu'), t('Fri'), t('Sat')];
    const max = Math.max(1, ...totals);
    const bars = totals.map((v, i) => {
      const pct = Math.max(2, Math.round((v / max) * 100));
      return `<div class="rpt-bar" title="${esc(labels[i])}: ${esc(money(v))}">
        <div class="rpt-bar-fill" style="height:${pct}%"></div>
        <span class="rpt-bar-label">${labels[i]}</span>
      </div>`;
    }).join('');
    return `<div class="rpt-bars">${bars}</div>`;
  }

  // ════════════════════════════════════════════════════════════════════════
  // Profit Report
  // ════════════════════════════════════════════════════════════════════════
  function renderProfit() {
    sectionBody.innerHTML = `
      <div class="salm-toolbar">
        <select class="salm-select" id="profit-period">
          <option value="7">${t('Last 7 days')}</option>
          <option value="30">${t('Last 30 days')}</option>
          <option value="90">${t('Last 90 days')}</option>
          <option value="365">${t('Last 12 months')}</option>
        </select>
        <button class="salm-icon-btn" id="profit-refresh"><i class="fa-solid fa-arrows-rotate"></i> ${t('Refresh')}</button>
      </div>
      <div class="salm-kpi-bar" id="profit-kpi" style="display:none">
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Revenue')}</span><span class="salm-kpi-val" id="profit-kpi-revenue">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('COGS')}</span><span class="salm-kpi-val" id="profit-kpi-cogs">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Gross Profit')}</span><span class="salm-kpi-val accent" id="profit-kpi-gp">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Margin')}</span><span class="salm-kpi-val" id="profit-kpi-margin">0%</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Net Profit')}</span><span class="salm-kpi-val accent" id="profit-kpi-net">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Returns')}</span><span class="salm-kpi-val" id="profit-kpi-returns">0.00</span></div>
        <div class="salm-kpi-item"><span class="salm-kpi-label">${t('Expenses')}</span><span class="salm-kpi-val" id="profit-kpi-expenses">0.00</span></div>
      </div>
      <div class="salm-section-label">${t('Trend')}</div>
      <div class="rpt-trend-card" id="profit-trend"><div class="salm-loading">${t('Loading…')}</div></div>
      <div class="salm-section-label">${t('Top Products by Gross Profit')}</div>
      <div class="salm-table-card"><table class="salm-table">
        <thead><tr><th>${t('Product')}</th><th style="text-align:right">${t('Qty')}</th><th style="text-align:right">${t('Revenue')}</th><th style="text-align:right">${t('COGS')}</th><th style="text-align:right">${t('Gross Profit')}</th><th style="text-align:right">${t('Margin')}</th></tr></thead>
        <tbody id="profit-products-rows"><tr><td colspan="6" class="salm-loading">${t('Loading…')}</td></tr></tbody>
      </table></div>`;

    document.getElementById('profit-period').value = String(profitPeriod);
    document.getElementById('profit-period').addEventListener('change', (e) => { profitPeriod = Number(e.target.value); loadProfit(); });
    document.getElementById('profit-refresh').addEventListener('click', loadProfit);
    loadProfit();
  }

  async function loadProfit() {
    document.getElementById('profit-kpi').style.display = 'none';
    document.getElementById('profit-trend').innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
    document.getElementById('profit-products-rows').innerHTML = `<tr><td colspan="6" class="salm-loading">${t('Loading…')}</td></tr>`;
    await loadSettings();

    const res = await API.profitReport(profitPeriod);
    if (res.status !== 200) {
      const msg = t('Could not load profit report ({reason}).', { reason: t(res.body?.message) || res.status });
      document.getElementById('profit-trend').innerHTML = `<div class="salm-empty">${msg}</div>`;
      document.getElementById('profit-products-rows').innerHTML = `<tr><td colspan="6" class="salm-empty">${msg}</td></tr>`;
      return;
    }

    const d = res.body.data || {};
    const summary = d.summary || {};

    document.getElementById('profit-kpi').style.display = '';
    document.getElementById('profit-kpi-revenue').textContent = money(summary.revenue);
    document.getElementById('profit-kpi-cogs').textContent = money(summary.cogs);
    document.getElementById('profit-kpi-gp').textContent = money(summary.gross_profit);
    document.getElementById('profit-kpi-margin').textContent = `${summary.gross_margin ?? 0}%`;
    document.getElementById('profit-kpi-net').textContent = money(summary.net_profit);
    document.getElementById('profit-kpi-returns').textContent = money(summary.returns);
    document.getElementById('profit-kpi-expenses').textContent = money(summary.expenses);

    const trend = d.trend || {};
    document.getElementById('profit-trend').innerHTML = buildLineChart(
      (trend.labels || []).map((lab) => lab),
      [
        { name: t('Expenses'), color: '#dc2626', data: trend.expenses || [] },
        { name: t('Gross Profit'), color: '#16a34a', data: trend.gross_profit || [] },
        { name: t('Revenue'), color: '#0ea5e9', data: trend.revenue || [] },
      ]
    );

    const products = d.top_products || [];
    document.getElementById('profit-products-rows').innerHTML = products.length
      ? products.map((p) => `
        <tr>
          <td>${esc(p.name)}</td>
          <td class="salm-amt-cell">${p.qty}</td>
          <td class="salm-amt-cell">${money(p.revenue)}</td>
          <td class="salm-amt-cell">${money(p.cogs)}</td>
          <td class="salm-amt-cell">${money(p.gp)}</td>
          <td class="salm-amt-cell">${p.margin}%</td>
        </tr>`).join('')
      : `<tr><td colspan="6" class="salm-empty">${t('No sales in this period.')}</td></tr>`;
  }

  // Generic multi-series SVG line chart — used for the Profit Report's
  // revenue/gross-profit/expenses trend (3 series) and Analytics' revenue
  // trend (1 series, no legend needed). `series` is drawn in array order, so
  // put the line that should sit on top last.
  function buildLineChart(labels, series) {
    if (!labels.length) return `<div class="salm-empty">${t('No data yet.')}</div>`;

    const W = 900, H = 220, padL = 8, padR = 8, padT = 14, padB = 26;
    const allValues = series.flatMap((s) => s.data);
    const max = Math.max(1, ...allValues);
    const stepX = labels.length > 1 ? (W - padL - padR) / (labels.length - 1) : 0;
    const scaleY = (v) => H - padB - (Math.max(0, v) / max) * (H - padT - padB);
    const scaleX = (i) => padL + i * stepX;

    function pathFor(arr) {
      return arr.map((v, i) => `${i === 0 ? 'M' : 'L'}${scaleX(i).toFixed(1)},${scaleY(v || 0).toFixed(1)}`).join(' ');
    }

    const showEvery = Math.max(1, Math.ceil(labels.length / 10));
    const labelEls = labels.map((lab, i) => (i % showEvery === 0 || i === labels.length - 1)
      ? `<text x="${scaleX(i).toFixed(1)}" y="${H - 6}" class="rpt-trend-label" text-anchor="middle">${esc(lab)}</text>` : '').join('');

    const lines = series.map((s) => `<path d="${pathFor(s.data)}" class="rpt-trend-line" style="stroke:${s.color}" />`).join('');
    const legend = series.length > 1
      ? `<div class="rpt-legend">${series.map((s) => `<span class="rpt-legend-item"><i style="background:${s.color}"></i>${esc(s.name)}</span>`).join('')}</div>`
      : '';

    return `
      <svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" class="rpt-trend-svg">
        <line x1="${padL}" y1="${H - padB}" x2="${W - padR}" y2="${H - padB}" class="rpt-trend-axis" />
        ${lines}
        ${labelEls}
      </svg>
      ${legend}`;
  }

  // ── Dialog shell: build the DOM, wire close/back handlers, show it ──────
  // Mirrors js/sales.js's openSalesModal / js/stock.js's openStockModal —
  // same .salm-modal-backdrop / .salm-modal-card shell so this dialog looks
  // and behaves the same as the other module dialogs.
  function openReportsModal() {
    if (document.getElementById('rpt-modal-backdrop')) return;

    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'salm-modal-backdrop';
    el.id = 'rpt-modal-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'rpt-modal-title');
    el.innerHTML = `
      <div class="salm-modal-card">
        <div class="salm-modal-head">
          <span class="salm-modal-head-icon"><i class="fa-solid fa-chart-line"></i></span>
          <div class="salm-modal-head-text">
            <h2 id="rpt-modal-title">${t('Reports & Summaries')}</h2>
            <p>${t('Daily summaries, profit, and sales reports.')}</p>
          </div>
          <button class="salm-modal-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="salm-modal-body">
          <div id="rpt-home-view">
            <div class="salm-grid" id="rpt-grid"></div>
          </div>

          <div id="rpt-section-view" style="display:none">
            <div class="inv-detail-header">
              <button class="inv-back-btn" id="rpt-section-back"><i class="fa-solid fa-arrow-left"></i> ${t('Reports & Summaries')}</button>
              <span class="inv-detail-breadcrumb" id="rpt-section-title"></span>
            </div>
            <p class="sales-section-desc" id="rpt-section-desc"></p>
            <div id="rpt-section-body"></div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);

    cardEl = el.querySelector('.salm-modal-card');
    homeView = document.getElementById('rpt-home-view');
    sectionView = document.getElementById('rpt-section-view');
    sectionTitleEl = document.getElementById('rpt-section-title');
    sectionDescEl = document.getElementById('rpt-section-desc');
    sectionBody = document.getElementById('rpt-section-body');

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function onKey(e) {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      e.stopPropagation();
      close();
    }

    el.querySelector('.salm-modal-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    document.getElementById('rpt-section-back').addEventListener('click', goHome);

    requestAnimationFrame(() => el.classList.add('open'));
    goHome();
  }

  window.openReportsModal = openReportsModal;
})();
