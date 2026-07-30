// ── Sidebar: collapsible module groups ──
function treeToggle(btn) {
  btn.closest('.tree-group').classList.toggle('open');
}
function subToggle(link) {
  const group = link.closest('.sb-chapter-group');
  if (group) group.classList.toggle('sub-open');
}

// ── Mark the current page's sidebar link, open its group ──
(function () {
  const page = document.body.getAttribute('data-page');
  if (!page) return;
  const link = document.querySelector('.sb-page-link[data-page="' + page + '"]');
  if (link) {
    link.classList.add('current');
    const group = link.closest('.tree-group');
    if (group) group.classList.add('open');
    const chapterGroup = link.closest('.sb-chapter-group');
    if (chapterGroup) chapterGroup.classList.add('sub-open');
  }
})();

// ── Active chapter/section tracking (in-page anchors only) ──
const chapters = document.querySelectorAll('.chapter');
const sections = document.querySelectorAll('h3[id], .endpoint[id]');
const chLinks  = document.querySelectorAll('.sb-link');
const subLinks = document.querySelectorAll('.sb-sub');

function setActive(id) {
  chLinks.forEach(l => {
    const active = l.getAttribute('href') === '#' + id;
    l.classList.toggle('active', active);
    if (active) l.closest('.tree-group')?.classList.add('open');
  });
  subLinks.forEach(l => {
    const active = l.getAttribute('href') === '#' + id;
    l.classList.toggle('active', active);
    if (active) {
      l.closest('.tree-group')?.classList.add('open');
      l.closest('.sb-chapter-group')?.classList.add('sub-open');
    }
  });
}

if (chapters.length) {
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id); });
  }, { rootMargin: '-10% 0px -75% 0px', threshold: 0 });
  chapters.forEach(s => obs.observe(s));
  sections.forEach(s => obs.observe(s));
}

// ── Reading progress bar ──
const bar = document.getElementById('progress-bar');
if (bar) {
  window.addEventListener('scroll', () => {
    const h = document.documentElement.scrollHeight - window.innerHeight;
    bar.style.width = (h > 0 ? (window.scrollY / h) * 100 : 0) + '%';
  }, { passive: true });
}

// ── Theme toggle (shared key with the main user guide) ──
function toggleTheme() {
  const root = document.documentElement;
  const cur  = root.getAttribute('data-theme');
  const next = cur === 'dark' ? 'light' : 'dark';
  root.setAttribute('data-theme', next);
  try { localStorage.setItem('zeebroo-guide-theme', next); } catch (_) {}
}
try {
  const saved = localStorage.getItem('zeebroo-guide-theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
} catch (_) {}

// ── Mobile sidebar toggle ──
(function () {
  const hamburger = document.getElementById('sidebarHamburgerBtn');
  const sidebar = document.getElementById('sidebar');
  if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => sidebar.classList.toggle('open'));
  }
})();

// ── In-page search (searches this page's chapters/sections/endpoints) ──
(function () {
  const input  = document.getElementById('tb-search-input');
  const drop   = document.getElementById('tb-search-drop');
  const clear  = document.getElementById('tb-search-clear');
  if (!input || !drop) return;
  let focusIdx = -1;

  const index = [];
  document.querySelectorAll('.chapter').forEach(ch => {
    const h2 = ch.querySelector('h2');
    if (!h2) return;
    const chTitle = h2.textContent.trim();
    index.push({ id: ch.id, title: chTitle, ctx: 'Chapter', type: 'chapter' });
    ch.querySelectorAll('.endpoint[id]').forEach(ep => {
      const pathEl = ep.querySelector('.endpoint-path');
      const nameEl = ep.querySelector('.endpoint-name');
      const title = (pathEl ? pathEl.textContent.trim() : ep.id) + (nameEl ? ' — ' + nameEl.textContent.trim() : '');
      index.push({ id: ep.id, title, ctx: chTitle, type: 'endpoint' });
    });
  });

  function highlight(text, q) {
    if (!q) return text;
    const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return text.replace(re, '<mark class="tb-sr-hl">$1</mark>');
  }

  function render(q) {
    const qt = q.trim().toLowerCase();
    if (!qt) { drop.classList.remove('open'); drop.innerHTML = ''; return; }
    const matches = index.filter(e => e.title.toLowerCase().includes(qt) || e.ctx.toLowerCase().includes(qt)).slice(0, 12);
    if (!matches.length) {
      drop.innerHTML = '<div class="tb-sr-none">No results on this page for "' + q.replace(/</g, '&lt;') + '"</div>';
      drop.classList.add('open');
      focusIdx = -1;
      return;
    }
    drop.innerHTML = matches.map((m, i) =>
      `<div class="tb-sr" role="option" data-id="${m.id}" data-idx="${i}">
        <span class="tb-sr-title">${highlight(m.title, qt)}</span>
        <span class="tb-sr-ctx">${highlight(m.ctx, qt)}</span>
       </div>`
    ).join('') + `<div class="tb-sr-kbd"><kbd>&uarr;&darr; navigate</kbd><kbd>&crarr; jump</kbd><kbd>Esc close</kbd></div>`;
    drop.classList.add('open');
    focusIdx = -1;
  }

  function jump(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    input.value = '';
    clear.style.display = 'none';
    drop.classList.remove('open');
    drop.innerHTML = '';
    input.blur();
  }

  function setFocus(idx) {
    const items = drop.querySelectorAll('.tb-sr[data-idx]');
    items.forEach(r => r.classList.remove('focused'));
    if (idx >= 0 && idx < items.length) {
      focusIdx = idx;
      items[idx].classList.add('focused');
      items[idx].scrollIntoView({ block: 'nearest' });
    } else {
      focusIdx = -1;
    }
  }

  input.addEventListener('input', () => {
    const q = input.value;
    clear.style.display = q ? 'block' : 'none';
    render(q);
  });

  input.addEventListener('keydown', e => {
    const items = drop.querySelectorAll('.tb-sr[data-idx]');
    if (e.key === 'ArrowDown') { e.preventDefault(); setFocus(Math.min(focusIdx + 1, items.length - 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setFocus(Math.max(focusIdx - 1, 0)); }
    else if (e.key === 'Enter') {
      e.preventDefault();
      if (focusIdx >= 0 && items[focusIdx]) jump(items[focusIdx].getAttribute('data-id'));
      else if (items.length) jump(items[0].getAttribute('data-id'));
    } else if (e.key === 'Escape') {
      drop.classList.remove('open'); input.blur();
    }
  });

  drop.addEventListener('click', e => {
    const row = e.target.closest('.tb-sr[data-id]');
    if (row) jump(row.getAttribute('data-id'));
  });

  clear.addEventListener('click', () => {
    input.value = '';
    clear.style.display = 'none';
    drop.classList.remove('open');
    drop.innerHTML = '';
    input.focus();
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('#tb-search')) { drop.classList.remove('open'); }
  });
})();
