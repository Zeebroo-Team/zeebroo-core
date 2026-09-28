'use strict';

// Tiny i18n layer. English is the source language: the English text itself is
// the key, so untranslated strings simply show in English. Other languages are
// dictionaries in js/lang/<code>.js that register on window.I18N_DICTS.
//
//   t('Save Customer')                      -> translated string
//   t('{name} added.', { name })            -> {placeholders} are substituted
//
// Static HTML is translated once, right here, by walking the text nodes that
// are already parsed — so this must be the FIRST script at the end of <body>
// (before any script that renders data, otherwise user data could be
// translated by accident). Changing language reloads the page.
(function () {
  const STORAGE_KEY = 'pos_lite_lang';

  const LANGUAGES = [
    { code: 'en', native: 'English', english: 'English', badge: 'EN', locale: undefined },
    { code: 'si', native: 'සිංහල', english: 'Sinhala', badge: 'සි', locale: 'si-LK' },
  ];

  function readLang() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (LANGUAGES.some((l) => l.code === saved)) return saved;
    } catch (_) { /* storage unavailable -> default */ }
    return 'en';
  }

  const lang = readLang();
  const dict = (window.I18N_DICTS && window.I18N_DICTS[lang]) || null;
  const has = (key) => !!dict && Object.prototype.hasOwnProperty.call(dict, key);

  // Messages with variable parts (server validation errors, network errors):
  // a dictionary may carry __patterns = [[RegExp, (...groups) => string], ...]
  function fromPatterns(key) {
    const rules = dict && dict.__patterns;
    if (!rules) return null;
    for (const [re, build] of rules) {
      const m = key.match(re);
      if (m) return build(...m.slice(1));
    }
    return null;
  }

  function t(key, params) {
    if (typeof key !== 'string') return key; // e.g. a missing server message: let callers `||` a fallback
    let s = has(key) ? dict[key] : (fromPatterns(key) ?? key);
    if (params) s = s.replace(/\{(\w+)\}/g, (m, k) => (k in params ? params[k] : m));
    return s;
  }

  // ── Static DOM translation ─────────────────────────────────────────────
  const ATTRS = ['placeholder', 'title', 'aria-label', 'alt'];
  const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'TEXTAREA']);

  function translateStatic(root) {
    if (!dict || !root) return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        const p = node.parentElement;
        if (!p || SKIP_TAGS.has(p.tagName) || p.closest('[data-i18n-skip]')) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      },
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);

    for (const node of nodes) {
      const raw = node.nodeValue;
      const key = raw.replace(/\s+/g, ' ').trim();
      if (!key || !has(key)) continue;
      const lead = raw.match(/^\s*/)[0];
      const trail = raw.match(/\s*$/)[0];
      node.nodeValue = lead + dict[key] + trail;
    }

    root.querySelectorAll(ATTRS.map((a) => `[${a}]`).join(',')).forEach((el) => {
      if (el.closest('[data-i18n-skip]')) return;
      for (const a of ATTRS) {
        const v = el.getAttribute(a);
        if (v && has(v.trim())) el.setAttribute(a, dict[v.trim()]);
      }
    });
  }

  function translateTitle() {
    // "Customers — Zeebroo POS Lite" -> translate each part separately
    document.title = document.title.split(' — ').map((part) => t(part.trim())).join(' — ');
  }

  // ── Sinhala typography ─────────────────────────────────────────────────
  // The OS Sinhala faces (Sinhala Sangam MN on macOS) render small and are
  // missing on some Linux machines, so a Noto Sans Sinhala is bundled
  // (renderer/fonts, SIL OFL). Per-glyph fallback keeps Latin text in the UI
  // font. Negative letter-spacing also breaks Indic conjuncts, so it's reset.
  function injectScriptStyles() {
    if (lang !== 'si') return;
    const style = document.createElement('style');
    style.id = 'i18n-si-typography';
    style.textContent = `
      @font-face {
        font-family: 'Noto Sans Sinhala';
        src: url('fonts/NotoSansSinhala.ttf') format('truetype');
        font-weight: 100 900;
        font-stretch: 62.5% 100%;
        font-display: swap;
      }
      body, button, input, select, textarea {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Noto Sans Sinhala',
                     'Nirmala UI', 'Iskoola Pota', 'Sinhala Sangam MN', sans-serif;
      }
      *, *::before, *::after { letter-spacing: normal !important; }
      body { line-height: 1.5; }`;
    document.head.appendChild(style);
  }

  function run() {
    document.documentElement.lang = lang;
    injectScriptStyles();
    translateStatic(document.body);
    translateTitle();
  }
  if (document.body) run();
  else document.addEventListener('DOMContentLoaded', run);

  window.i18n = {
    lang,
    languages: LANGUAGES,
    t,
    current: () => LANGUAGES.find((l) => l.code === lang),
    // undefined for English keeps the system default formatting; 'si-LK' for Sinhala
    get locale() { return LANGUAGES.find((l) => l.code === lang).locale; },
    setLang(code) {
      if (!LANGUAGES.some((l) => l.code === code)) return false;
      try { localStorage.setItem(STORAGE_KEY, code); } catch (_) { return false; }
      return true;
    },
    // "Sunday 27 September" / "ඉරිදා, සැප්තැම්බර් 27"
    formatLongDate(date) {
      const n = dict && dict.__dateNames;
      if (n) return n.longDate(n.weekdays[date.getDay()], date.getDate(), n.months[date.getMonth()]);
      return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });
    },
    // "27 Sept 2026" / "2026 සැප්තැම්බර් 27"
    formatShortDate(date) {
      const n = dict && dict.__dateNames;
      if (n) return n.shortDate(date.getFullYear(), date.getDate(), n.months[date.getMonth()]);
      return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    // translate a container built from *static* markup (never one that holds user data)
    apply: translateStatic,
  };
  window.t = t;
})();
