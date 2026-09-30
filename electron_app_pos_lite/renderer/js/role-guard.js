'use strict';

// Cashier sessions are POS-only (signed in via the login screen's
// "Sign in as a cashier" — see auth.js; config.is_cashier is set there).
//   • dashboard: every tile except POS is locked
//   • POS screen: owner-only actions (Add Product) are hidden
//   • any other screen bounces back to the dashboard
// main.js also blocks navigation to other screens, and navbar.js hides
// Billing / Settings from the profile menu.
(function () {
  const CASHIER_PAGES = ['dashboard.html', 'pos.html'];
  const ALLOWED_TILES = ['pos'];

  const style = document.createElement('style');
  style.textContent = `
    body.cashier-mode .tile.tile-locked { opacity: .45; filter: grayscale(1); cursor: not-allowed; }
    body.cashier-mode .tile.tile-locked .tile-open { display: none; }
    body.cashier-mode #add-product-btn { display: none !important; }
  `;
  document.head.appendChild(style);

  function lockTiles() {
    const grid = document.getElementById('tile-grid');
    if (!grid) return;
    const tiles = Array.from(grid.querySelectorAll('.tile'));
    tiles.forEach((tile) => {
      if (ALLOWED_TILES.includes(tile.dataset.key)) return;
      if (tile.classList.contains('tile-locked')) return;
      tile.classList.add('tile-locked');
      tile.setAttribute('aria-disabled', 'true');
      tile.setAttribute('tabindex', '-1');
      tile.title = t('Cashier accounts can only use the POS.');
      const footer = tile.querySelector('.tile-footer');
      if (footer && !footer.querySelector('.badge-soon')) {
        footer.insertAdjacentHTML('beforeend', `<span class="badge-soon"><i class="fa-solid fa-lock"></i> ${t('Owner only')}</span>`);
      }
    });
    const meta = document.getElementById('section-meta');
    if (meta && tiles.length) {
      meta.textContent = t('{n} of {total} modules available', { n: ALLOWED_TILES.length, total: tiles.length });
    }
  }

  // Swallow clicks/keys on locked tiles before dashboard.js's own handlers run.
  function blockLocked(e) {
    if (!document.body.classList.contains('cashier-mode')) return;
    if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
    if (e.target.closest && e.target.closest('.tile-locked')) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }
  document.addEventListener('click', blockLocked, true);
  document.addEventListener('keydown', blockLocked, true);

  window.electronAPI.getConfig().then((cfg) => {
    if (!cfg.is_cashier) return;

    const page = (location.pathname.split('/').pop() || '').toLowerCase();
    if (!CASHIER_PAGES.includes(page)) {
      location.replace('dashboard.html');
      return;
    }

    document.body.classList.add('cashier-mode');
    lockTiles();
    const grid = document.getElementById('tile-grid');
    if (grid) new MutationObserver(lockTiles).observe(grid, { childList: true });
  });
})();
