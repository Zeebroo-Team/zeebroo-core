'use strict';

// Shared top-bar chrome (needs js/i18n.js loaded first):
//   • the profile dropdown — avatar, account details, Language / Reload / Restart / Log out
//     (a screen just puts <div class="user-menu" id="user-menu"></div> in its .topbar)
//   • the language switch window, opened from the dropdown or from any element
//     carrying [data-open-language] (the login screen uses that)
(function () {
  const $ = (id) => document.getElementById(id);

  // ── Language switch window ─────────────────────────────────────────────
  function openLanguageWindow() {
    if ($('lw-backdrop')) return;

    const current = i18n.lang;
    let selected = current;
    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'lw-backdrop';
    el.id = 'lw-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'lw-title');
    el.innerHTML = `
      <div class="lw-card">
        <div class="lw-head">
          <span class="lw-head-icon"><i class="fa-solid fa-language"></i></span>
          <div class="lw-head-text">
            <h2 id="lw-title">${t('Language')}</h2>
            <p>${t('Choose the language used across the app.')}</p>
          </div>
          <button class="lw-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="lw-options" role="radiogroup" aria-labelledby="lw-title">
          ${i18n.languages.map((l) => `
            <button class="lw-option" type="button" role="radio" data-lang="${l.code}" aria-checked="false">
              <span class="lw-badge">${l.badge}</span>
              <span class="lw-names"><b>${l.native}</b><small>${t(l.english)}</small></span>
              <i class="fa-solid fa-circle-check lw-check"></i>
            </button>`).join('')}
        </div>
        <div class="lw-note"><i class="fa-solid fa-circle-info"></i><span>${t('The page will reload to apply the change.')}</span></div>
        <div class="lw-foot">
          <button class="lw-btn ghost" type="button" data-lw="cancel">${t('Cancel')}</button>
          <button class="lw-btn primary" type="button" data-lw="apply">${t('Apply')}</button>
        </div>
      </div>`;
    document.body.appendChild(el);

    const options = Array.from(el.querySelectorAll('.lw-option'));
    const applyBtn = el.querySelector('[data-lw="apply"]');

    function render() {
      options.forEach((o) => {
        const on = o.dataset.lang === selected;
        o.classList.toggle('selected', on);
        o.setAttribute('aria-checked', String(on));
        o.tabIndex = on ? 0 : -1;
      });
      applyBtn.disabled = selected === current;
    }

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }

    function apply() {
      if (selected === current) return close();
      if (i18n.setLang(selected)) window.location.reload();
    }

    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); return; }
      if (e.key === 'Enter' && document.activeElement && document.activeElement.classList.contains('lw-option')) {
        e.preventDefault();
        apply();
        return;
      }
      if (['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft'].includes(e.key)) {
        e.preventDefault();
        const step = e.key === 'ArrowDown' || e.key === 'ArrowRight' ? 1 : -1;
        const next = options[(options.findIndex((o) => o.dataset.lang === selected) + step + options.length) % options.length];
        selected = next.dataset.lang;
        render();
        next.focus();
      }
    }

    options.forEach((o) => o.addEventListener('click', () => { selected = o.dataset.lang; render(); }));
    el.querySelector('.lw-close').addEventListener('click', close);
    el.querySelector('[data-lw="cancel"]').addEventListener('click', close);
    applyBtn.addEventListener('click', apply);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    render();
    requestAnimationFrame(() => {
      el.classList.add('open');
      const on = options.find((o) => o.dataset.lang === selected);
      if (on) on.focus();
    });
  }

  window.openLanguageWindow = openLanguageWindow;

  // any element can open the window (login screen's globe button)
  document.querySelectorAll('[data-open-language]').forEach((btn) => btn.addEventListener('click', openLanguageWindow));
  document.querySelectorAll('[data-language-label]').forEach((el) => { el.textContent = i18n.current().native; });

  // ── Profile dropdown ───────────────────────────────────────────────────
  const mount = $('user-menu');
  if (!mount) return;

  mount.innerHTML = `
    <button class="um-trigger" id="um-trigger" type="button" aria-haspopup="menu" aria-expanded="false" title="${t('Account')}">
      <span class="um-avatar" id="um-avatar"><i class="fa-solid fa-user"></i></span>
      <span class="um-trigger-text">
        <span class="um-name" id="um-name">${t('Account')}</span>
        <span class="um-biz" id="um-biz"></span>
      </span>
      <i class="fa-solid fa-chevron-down um-chevron"></i>
    </button>
    <div class="um-panel" id="um-panel" role="menu">
      <div class="um-head">
        <span class="um-avatar lg" id="um-avatar-lg"><i class="fa-solid fa-user"></i></span>
        <div class="um-head-text">
          <div class="um-head-name" id="um-head-name">—</div>
          <div class="um-head-email" id="um-head-email"></div>
        </div>
      </div>
      <div class="um-biz-row">
        <i class="fa-solid fa-store"></i>
        <div><small>${t('Business')}</small><span id="um-biz-name">—</span></div>
      </div>
      <div class="um-sep"></div>
      <button class="um-item" id="um-language" type="button" role="menuitem">
        <i class="fa-solid fa-language"></i> <span>${t('Language')}</span>
        <span class="um-item-value">${i18n.current().native}</span>
      </button>
      <div class="um-sep"></div>
      <button class="um-item" id="um-reload" type="button" role="menuitem"><i class="fa-solid fa-rotate-right"></i> ${t('Reload page')}</button>
      <button class="um-item" id="um-restart" type="button" role="menuitem"><i class="fa-solid fa-power-off"></i> ${t('Restart app')}</button>
      <div class="um-sep"></div>
      <button class="um-item danger" id="um-logout" type="button" role="menuitem"><i class="fa-solid fa-right-from-bracket"></i> ${t('Log out')}</button>
    </div>`;

  const trigger = $('um-trigger');

  function setOpen(open) {
    mount.classList.toggle('open', open);
    trigger.setAttribute('aria-expanded', String(open));
  }

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    setOpen(!mount.classList.contains('open'));
  });
  document.addEventListener('click', (e) => {
    if (!mount.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mount.classList.contains('open')) {
      setOpen(false);
      trigger.focus();
    }
  });

  $('um-language').addEventListener('click', () => { setOpen(false); openLanguageWindow(); });
  $('um-reload').addEventListener('click', () => { window.location.reload(); });
  $('um-restart').addEventListener('click', async () => { setOpen(false); await window.electronAPI.restartApp(); });
  $('um-logout').addEventListener('click', async () => { setOpen(false); await window.electronAPI.logout(); });

  // Initials must respect grapheme clusters (Sinhala vowel signs are separate code points)
  const segmenter = typeof Intl !== 'undefined' && Intl.Segmenter ? new Intl.Segmenter(undefined, { granularity: 'grapheme' }) : null;
  const graphemes = (s) => (segmenter ? Array.from(segmenter.segment(s), (x) => x.segment) : Array.from(s));

  function initials(name, email) {
    const source = (name || '').trim() || (email || '').split('@')[0];
    const parts = source.split(/[\s._-]+/).filter(Boolean);
    if (!parts.length) return '';
    const letters = parts.length === 1
      ? graphemes(parts[0]).slice(0, 2).join('')
      : graphemes(parts[0])[0] + graphemes(parts[parts.length - 1])[0];
    return letters.toUpperCase();
  }

  (async () => {
    const cfg = await window.electronAPI.getConfig();
    const name = cfg.user?.name || '';
    const email = cfg.user?.email || '';
    const business = cfg.business_name || (cfg.business_id ? `#${cfg.business_id}` : '');
    const letters = initials(name, email);

    if (letters) {
      $('um-avatar').textContent = letters;
      $('um-avatar-lg').textContent = letters;
    }
    $('um-name').textContent = name || email || t('Account');
    $('um-biz').textContent = business;
    $('um-head-name').textContent = name || email || t('Signed in');
    $('um-head-email').textContent = name ? email : '';
    $('um-head-email').title = email; // full address on hover when it's truncated
    $('um-biz-name').textContent = business || '—';
  })();
})();
