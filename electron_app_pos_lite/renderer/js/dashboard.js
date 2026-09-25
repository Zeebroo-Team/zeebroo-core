'use strict';

// Each tile: { key, icon, title, desc, tint, accent, active, href }
// `active: true` (POS only, for now) navigates to a real screen.
// Everything else is flagged "Coming soon" and just shows a toast —
// tell me which one to build next and I'll wire it up to the API.
const TILES = [
  {
    key: 'products',
    icon: 'fa-tags',
    title: 'Products & Categories',
    desc: 'Browse, add, and organize your product catalog.',
    tint: '#e7f6ee', accent: '#16a34a',
    active: true,
    href: 'products.html',
  },
  {
    key: 'barcodes',
    icon: 'fa-barcode',
    title: 'Barcodes',
    desc: 'Generate and print barcode labels for products.',
    tint: '#fef3e0', accent: '#d97706',
  },
  {
    key: 'stock',
    icon: 'fa-warehouse',
    title: 'Stock',
    desc: 'Track stock levels, batches, and adjustments.',
    tint: '#e6f3ff', accent: '#0284c7',
  },
  {
    key: 'reports',
    icon: 'fa-chart-line',
    title: 'Reports & Summaries',
    desc: 'Daily summaries, profit, and sales reports.',
    tint: '#f2ecff', accent: '#7c3aed',
  },
  {
    key: 'cashiers',
    icon: 'fa-id-badge',
    title: 'Cashiers',
    desc: 'Manage cashier accounts and register access.',
    tint: '#ffe9ef', accent: '#e11d48',
    active: true,
    href: 'cashiers.html',
  },
  {
    key: 'customers',
    icon: 'fa-users',
    title: 'Customers',
    desc: 'Manage customer profiles and purchase history.',
    tint: '#fff1e0', accent: '#ea580c',
    active: true,
    href: 'customers.html',
  },
  {
    key: 'sales',
    icon: 'fa-receipt',
    title: 'Sales Management',
    desc: 'View, manage, and track past sales and returns.',
    tint: '#e2f9f5', accent: '#0d9488',
  },
  {
    key: 'pos',
    icon: 'fa-cash-register',
    title: 'POS',
    desc: 'Ring up sales, manage the cart, and take payments.',
    tint: '#eef0ff', accent: '#4f46e5',
    active: true,
    href: 'pos.html',
  },
];

const grid = document.getElementById('tile-grid');
const toastEl = document.getElementById('toast');

function showToast(message) {
  toastEl.textContent = message;
  toastEl.classList.add('show');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toastEl.classList.remove('show'), 2600);
}

grid.innerHTML = TILES.map((t) => `
  <button class="tile ${t.active ? 'active' : ''}" data-key="${t.key}"
    style="--tile-tint:${t.tint}; --tile-accent:${t.accent};">
    <div class="tile-icon"><i class="fa-solid ${t.icon}"></i></div>
    <div class="tile-title">${t.title}</div>
    <p class="tile-desc">${t.desc}</p>
    <div class="tile-footer">
      ${t.active ? '<span>Open</span><i class="fa-solid fa-arrow-right"></i>' : '<span class="badge-soon">Coming soon</span>'}
    </div>
  </button>
`).join('');

grid.querySelectorAll('.tile').forEach((el) => {
  const tile = TILES.find((t) => t.key === el.dataset.key);
  el.addEventListener('click', () => {
    if (tile.active) window.location.href = tile.href;
    else showToast(`${tile.title} is coming soon.`);
  });
});

document.getElementById('reload-btn').addEventListener('click', () => {
  window.location.reload();
});

document.getElementById('restart-btn').addEventListener('click', async () => {
  await window.electronAPI.restartApp();
});

document.getElementById('logout-btn').addEventListener('click', async () => {
  await window.electronAPI.logout();
});

(async () => {
  const cfg = await window.electronAPI.getConfig();
  document.getElementById('who-business').textContent = cfg.business_name || `#${cfg.business_id}`;
  document.getElementById('who-user').textContent = cfg.user?.name || cfg.user?.email || '—';
  const firstName = (cfg.user?.name || '').split(' ')[0];
  document.getElementById('greeting').textContent = firstName ? `Welcome back, ${firstName}` : 'Welcome back';
})();
