'use strict';

// Each tile: { key, icon, title, desc, accent, active, href }
// `active: true` tiles navigate to a real screen; the rest are flagged
// "Coming soon" and just show a toast — tell me which one to build next.
const TILES = [
  {
    key: 'products',
    icon: 'fa-tags',
    title: 'Products & Categories',
    desc: 'Browse, add, and organize your product catalog.',
    accent: '#16a34a',
    active: true,
    href: 'products.html',
  },
  {
    key: 'barcodes',
    icon: 'fa-barcode',
    title: 'Barcodes',
    desc: 'Generate and print barcode labels for products.',
    accent: '#d97706',
  },
  {
    key: 'stock',
    icon: 'fa-warehouse',
    title: 'Stock',
    desc: 'Track stock levels, batches, and adjustments.',
    accent: '#0284c7',
    active: true,
    modal: 'openStockModal', // opens as an in-page dialog (js/stock.js) instead of a full-page navigation
  },
  {
    key: 'reports',
    icon: 'fa-chart-line',
    title: 'Reports & Summaries',
    desc: 'Daily summaries, profit, and sales reports.',
    accent: '#7c3aed',
  },
  {
    key: 'cashiers',
    icon: 'fa-id-badge',
    title: 'Cashiers',
    desc: 'Manage cashier accounts and register access.',
    accent: '#e11d48',
    active: true,
    href: 'cashiers.html',
  },
  {
    key: 'customers',
    icon: 'fa-users',
    title: 'Customers',
    desc: 'Manage customer profiles and purchase history.',
    accent: '#ea580c',
    active: true,
    href: 'customers.html',
  },
  {
    key: 'sales',
    icon: 'fa-receipt',
    title: 'Sales Management',
    desc: 'View, manage, and track past sales and returns.',
    accent: '#0d9488',
    active: true,
    modal: 'openSalesModal', // opens as an in-page dialog (js/sales.js) instead of a full-page navigation
  },
  {
    key: 'pos',
    icon: 'fa-cash-register',
    title: 'POS',
    desc: 'Ring up sales, manage the cart, and take payments.',
    accent: '#4f46e5',
    active: true,
    href: 'pos.html',
  },
];

const grid = document.getElementById('tile-grid');
const toastEl = document.getElementById('toast');

function showToast(message) {
  document.getElementById('toast-text').textContent = message;
  toastEl.classList.add('show');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toastEl.classList.remove('show'), 2600);
}

// hex -> rgba(), and a lighter companion colour for the icon gradient
function rgb(hex) {
  const n = parseInt(hex.slice(1), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}
function glow(hex, alpha) { const [r, g, b] = rgb(hex); return `rgba(${r}, ${g}, ${b}, ${alpha})`; }
function lighten(hex, amount) {
  const [r, g, b] = rgb(hex).map((c) => Math.round(c + (255 - c) * amount));
  return `rgb(${r}, ${g}, ${b})`;
}

grid.innerHTML = TILES.map((tile, i) => `
  <button class="tile ${tile.active ? 'active' : 'soon'}" data-key="${tile.key}" aria-label="${t(tile.title)}"
    style="--i:${i}; --tile-accent:${tile.accent}; --tile-accent-2:${lighten(tile.accent, 0.38)}; --tile-glow:${glow(tile.accent, 0.26)};">
    <span class="tile-bar"></span>
    <div class="tile-icon"><i class="fa-solid ${tile.icon}"></i></div>
    <div class="tile-title">${t(tile.title)}</div>
    <p class="tile-desc">${t(tile.desc)}</p>
    <div class="tile-footer">
      ${tile.active
        ? `<span class="tile-open">${t('Open')} <i class="fa-solid fa-arrow-right"></i></span>`
        : `<span class="badge-soon"><i class="fa-regular fa-clock"></i> ${t('Coming soon')}</span>`}
    </div>
  </button>
`).join('');

const liveCount = TILES.filter((tile) => tile.active).length;
document.getElementById('section-meta').textContent = t('{n} of {total} modules available', { n: liveCount, total: TILES.length });

grid.querySelectorAll('.tile').forEach((el) => {
  const tile = TILES.find((x) => x.key === el.dataset.key);

  el.addEventListener('click', () => {
    if (!tile.active) { showToast(t('{title} is coming soon.', { title: t(tile.title) })); return; }
    if (tile.modal) window[tile.modal]();
    else window.location.href = tile.href;
  });

  // feed the cursor position to the CSS spotlight
  el.addEventListener('mousemove', (e) => {
    const r = el.getBoundingClientRect();
    el.style.setProperty('--mx', `${e.clientX - r.left}px`);
    el.style.setProperty('--my', `${e.clientY - r.top}px`);
  });
});

// ── Hero: greeting, clock, business ─────────────────────────────────────
let firstName = '';

function tick() {
  const now = new Date();
  const h = now.getHours();
  const part = t(h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening');
  document.getElementById('greeting').textContent = firstName ? `${part}, ${firstName}` : part;
  document.getElementById('hero-time').textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  document.getElementById('hero-date').textContent = i18n.formatLongDate(now);
}
tick();
setInterval(tick, 15000);

(async () => {
  const cfg = await window.electronAPI.getConfig();
  firstName = (cfg.user?.name || '').split(' ')[0];
  document.getElementById('hero-biz').textContent = cfg.business_name || `#${cfg.business_id}`;
  tick();
})();
