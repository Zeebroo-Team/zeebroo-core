(function () {
  // ===== SETTINGS: match these to your app =====
  const USER_KEYS = ['user', 'currentUser', 'auth_user', 'zeebroo_user']; // where login saves the user
  const POS_TILE_TEXT = 'pos';                       // title text of the tile that stays enabled
  const ALLOWED_PAGES = ['pos.html', 'dashboard.html', 'index.html', 'auth.html']; // pages a cashier may open
  const DASHBOARD_PAGE = 'dashboard.html';
  // =============================================

  function getUser() {
    for (const key of USER_KEYS) {
      for (const store of [localStorage, sessionStorage]) {
        try {
          const raw = store.getItem(key);
          if (!raw) continue;
          const obj = JSON.parse(raw);
          if (obj) return obj.user || obj;
        } catch (e) {}
      }
    }
    return null;
  }

  function isCashier(u) {
    if (!u) return false;
    return String(u.role || u.type || u.user_type || '').toLowerCase() === 'cashier'
      || u.is_cashier === true || u.isCashier === true;
  }

  const user = getUser();
  if (!isCashier(user)) return;

  // Block other pages (direct URL access)
  const page = (location.pathname.split('/').pop() || 'index.html').toLowerCase();
  if (!ALLOWED_PAGES.includes(page)) {
    location.replace(DASHBOARD_PAGE);
    return;
  }

  // Lock every dashboard tile except POS
  const style = document.createElement('style');
  style.textContent = `
    .tile-locked { opacity: .4; filter: grayscale(1); pointer-events: none; cursor: not-allowed; user-select: none; }
  `;
  document.head.appendChild(style);

  function tileTitle(tile) {
    const el = tile.querySelector('h1,h2,h3,h4,h5,.tile-title,.title,b,strong');
    return (el ? el.textContent : tile.textContent).trim().toLowerCase();
  }

  function lockTiles() {
    const grid = document.getElementById('tile-grid');
    if (!grid) return;
    Array.from(grid.children).forEach(tile => {
      if (tileTitle(tile) === POS_TILE_TEXT) {
        tile.classList.remove('tile-locked');
      } else {
        tile.classList.add('tile-locked');
        tile.setAttribute('aria-disabled', 'true');
        tile.setAttribute('tabindex', '-1');
      }
    });
    const meta = document.getElementById('section-meta');
    if (meta) meta.textContent = '1 of 8 modules available';
  }

  // Stop clicks on locked tiles (capture phase, runs before dashboard.js handlers)
  document.addEventListener('click', e => {
    if (e.target.closest && e.target.closest('.tile-locked')) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  // Tiles are rendered by dashboard.js, so re-apply whenever the grid changes
  function start() {
    lockTiles();
    const grid = document.getElementById('tile-grid');
    if (grid) new MutationObserver(lockTiles).observe(grid, { childList: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();