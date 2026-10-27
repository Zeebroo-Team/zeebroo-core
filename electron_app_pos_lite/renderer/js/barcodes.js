'use strict';

// Barcodes — an in-page modal dialog, opened by calling window.openBarcodesModal()
// (see the dashboard's "Barcodes" tile in js/dashboard.js). Mirrors js/stock.js:
// builds its own DOM on open, tears it down on close, reuses the .salm-* /
// .qf-* CSS shell from css/sales.css plus the .bc-* rules added alongside it,
// and talks to the same Laravel POS API (Modules/Pos/routes/api.php, prefix
// /api/v1/pos) via js/api.js — GET online/barcodes/products (every sellable
// product for the picker, unpaginated) and the stock-history endpoint for
// per-batch labels. Barcode labels are generated entirely client-side with
// JsBarcode (js/jsbarcode.min.js).
//
// Modelled on electron_app's "Barcode Generator" (Inventory → Barcodes):
// a print-queue tab (this dialog's home view) plus a "Generate Barcode"
// builder overlay — browse / search products, tick them (or Select all),
// label count defaults to stock, add them, choose paper size + custom text,
// queue one entry per product, then print or save the sheet as a PDF.
(function () {
  let toastEl, queueContentEl;
  let posSettings = {};
  let settingsLoaded = false;

  // Current label builder (cleared each time the builder overlay opens) and
  // the queue of products added to print (cleared only when the dialog closes).
  // Queue entry: { item, labelCount, items, topText, bottomText, paper, customW, customH, productName }
  const _bc = { items: [], queue: [], selected: new Set() };

  const BC_PICK_CHUNK = 200;  // picker rows rendered per scroll step
  const BC_MAX_LABELS = 9999; // per product
  const BC_SHEET_CHUNK = 400; // labels built per frame when preparing a sheet
  // Jobs above this are split into parts — tens of thousands of labels in one
  // sheet freeze the app and can crash the print spooler / PDF engine.
  const BC_PART_LABELS = 1000;

  // Product picker — whole catalog fetched once per builder open, search filters locally
  const _pick = { all: [], list: [], rendered: 0, selected: new Map(), qty: new Map(), reqId: 0 };

  const LABEL_SIZES = [
    { key: '1x50x25', label: '1 Label (50×25mm)', cols: 1, w: 50, h: 25 },
    { key: '2x50x25', label: '2 Label (50×25mm)', cols: 2, w: 50, h: 25 },
    { key: '1x100x50', label: '1 Label (100×50mm)', cols: 1, w: 100, h: 50 },
    { key: '2x38x25', label: '2 Label (38×25mm)', cols: 2, w: 38, h: 25 },
  ];
  const _print = { printerType: 'roll', labelSize: '1x50x25', a4Cols: 3, a4Rows: 8 };

  // ── Small shared helpers (same as js/stock.js) ──────────────────────────
  function esc(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function money(n) {
    const amount = (Number(n) || 0).toFixed(2);
    const currency = (posSettings.currency || 'LKR').toUpperCase();
    return posSettings.currency_position === 'before' ? `${currency} ${amount}` : `${amount} ${currency}`;
  }

  function fmtDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString(i18n.locale, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function fmtNum(n) { return Number(n || 0).toLocaleString(i18n.locale); }

  function debounce(fn, ms) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
  }

  function showToast(message, type = '') {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.className = `salm-toast show ${type}`;
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => { toastEl.className = 'salm-toast'; }, 3000);
  }

  async function loadSettings() {
    if (settingsLoaded) return;
    const res = await API.settingsGet();
    if (res.status === 200) posSettings = res.body?.data || {};
    settingsLoaded = true;
  }

  function currentLabelSize() { return LABEL_SIZES.find((s) => s.key === _print.labelSize) || LABEL_SIZES[0]; }

  function printConfig() {
    if (_print.printerType === 'a4') return { cols: _print.a4Cols, rowsPerPage: _print.a4Rows, mmW: null, mmH: null };
    const sz = currentLabelSize();
    return { cols: sz.cols, rowsPerPage: null, mmW: sz.w, mmH: sz.h };
  }

  // ── Busy state for heavy actions ─────────────────────────────────────
  // Large selections (thousands of labels) block the UI thread, so: disable the
  // button, show a spinner, and yield one frame so it paints before the work runs.
  const nextFrame = () => new Promise((r) => requestAnimationFrame(() => setTimeout(r, 0)));

  async function busy(btn, label, fn, after) {
    if (!btn || btn.dataset.busy) return;
    btn.dataset.busy = '1';
    const html = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('bc-busy');
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${label}`;
    try {
      await nextFrame();
      await fn();
    } finally {
      delete btn.dataset.busy;
      btn.classList.remove('bc-busy');
      if (btn.isConnected) { btn.innerHTML = html; btn.disabled = false; }
      if (after) after(); // let the owner re-apply its normal enabled/disabled state
    }
  }

  // ── Barcode rendering (JsBarcode → detached SVG → markup string) ───────
  // Copies share the same code, so each code/height is generated once.
  const svgCache = new Map();
  function renderBarcodeSvg(code, heightPx) {
    if (!code) return '<div style="font-size:9px;color:#dc2626;text-align:center;padding:4px 0">No SKU</div>';
    const key = `${heightPx || 46}|${code}`;
    const hit = svgCache.get(key);
    if (hit) return hit;
    let out;
    try {
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      JsBarcode(svg, code, { format: 'CODE128', width: 1.8, height: heightPx || 46, displayValue: false, margin: 4 });
      out = svg.outerHTML;
    } catch (_) {
      out = `<div style="font-size:10px;text-align:center;letter-spacing:2px;font-family:monospace;padding:6px 0">${esc(code)}</div>`;
    }
    svgCache.set(key, out);
    return out;
  }

  function labelMarkup(it) {
    return `<div class="bc-plabel">
      ${it.topText ? `<div class="bc-ptop">${esc(it.topText)}</div>` : ''}
      ${renderBarcodeSvg(it.code, it._h || 46)}
      <div class="bc-pname">${esc(it.name).substring(0, 32)}</div>
      <div class="bc-psku">${esc(it.code || '')}</div>
      ${it.price ? `<div class="bc-pprice">${esc(it.price)}</div>` : ''}
      ${it.bottomText ? `<div class="bc-pbottom">${esc(it.bottomText)}</div>` : ''}
    </div>`;
  }

  function sheetDocument(bodyHtml, cfg) {
    const cols = cfg.cols || 1;
    return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${esc(t('Barcode Labels'))}</title>
<style>
  * { box-sizing: border-box; }
  @page { margin: 6mm; }
  body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .bc-print-sheet { display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 3mm; }
  .bc-plabel { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; border: 1px dashed #999; border-radius: 3px; text-align: center; overflow: hidden; padding: 2mm; page-break-inside: avoid; break-inside: avoid; }
  .bc-plabel .bc-ptop, .bc-plabel .bc-pbottom { font-size: 8px; color: #444; }
  .bc-plabel .bc-pname { font-size: 9.5px; font-weight: 700; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .bc-plabel .bc-psku { font-size: 8.5px; font-family: monospace; color: #444; }
  .bc-plabel .bc-pprice { font-size: 9.5px; font-weight: 800; }
  .bc-plabel svg { width: 100%; max-width: ${cfg.mmW ? cfg.mmW - 6 : 46}mm; }
</style></head><body><div class="bc-print-sheet">${bodyHtml}</div></body></html>`;
  }

  // Same markup/styles as the print sheet, shown on a "paper" backdrop. Roll
  // labels are pinned to their real mm size so the preview matches the stock.
  function previewDocument(bodyHtml, cfg) {
    const cols = cfg.cols || 1;
    const sheetW = cfg.mmW ? `width: ${cols * cfg.mmW + (cols - 1) * 3}mm;` : '';
    const labelH = cfg.mmH ? `.bc-plabel { min-height: ${cfg.mmH}mm; }` : '';
    const extra = `<style>
  html { background: #eef0f5; }
  body { padding: 12px; }
  .bc-print-sheet { ${sheetW} max-width: 100%; margin: 0 auto; padding: 3mm; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
  ${labelH}
</style>`;
    return sheetDocument(bodyHtml, cfg).replace('</head>', `${extra}</head>`);
  }

  // ── Sheet job: prepare (progress) → ready (Print / Save PDF) ─────────
  // Print and Save PDF only appear once every label is built, so nothing can
  // print a half-prepared sheet. PDF generation reports per-stage progress.
  let jobToken = 0;

  const PDF_STEPS = [
    ['prepare', 'Preparing labels', 8],
    ['loading', 'Loading into PDF engine', 20],
    ['rendering', 'Generating PDF pages', 55],
    ['saving-dialog', 'Choose where to save', 90],
    ['writing', 'Saving file', 97],
  ];

  // Labels shown in the ready-state preview — enough to check the layout
  // without rendering a whole (possibly 1000-label) sheet a second time.
  const BC_PREVIEW_LABELS = 12;

  function jobPanel({ icon, iconCls = '', title, pct, working = false, text = '', steps = null, hint = '', footer = false, preview = null }) {
    const $ = (id) => document.getElementById(id);
    if (!$('bcm-job')) return;
    const prev = $('bcm-job-preview');
    $('bcm-job-card').classList.toggle('bc-job-card-wide', !!preview);
    prev.style.display = preview ? '' : 'none';
    if (preview) {
      const frame = $('bcm-job-preview-frame');
      if (frame.dataset.src !== preview.html) { frame.dataset.src = preview.html; frame.srcdoc = preview.html; }
      $('bcm-job-preview-note').textContent = preview.note || '';
    }
    $('bcm-job-ico').className = `fa-solid ${icon} bc-job-ico ${iconCls}`;
    $('bcm-job-title').textContent = title;
    $('bcm-job-bar').classList.toggle('bc-working', working);
    $('bcm-job-fill').style.width = `${pct}%`;
    $('bcm-job-text').textContent = text;
    $('bcm-job-steps').style.display = steps ? '' : 'none';
    $('bcm-job-steps').innerHTML = steps || '';
    $('bcm-job-hint').textContent = hint;
    $('bcm-job-foot').style.display = footer ? '' : 'none';
  }

  // [start, end) ranges of at most BC_PART_LABELS; A4 parts hold whole pages only
  function splitParts(n, cfg) {
    let size = BC_PART_LABELS;
    if (cfg.rowsPerPage) {
      const perPage = (cfg.cols || 1) * cfg.rowsPerPage;
      size = Math.max(perPage, Math.floor(size / perPage) * perPage);
    }
    const parts = [];
    for (let i = 0; i < n; i += size) parts.push([i, Math.min(i + size, n)]);
    return parts;
  }

  async function openSheetJob(allItems, cfg) {
    if (!allItems.length || document.getElementById('bcm-job')) return;
    const parts = splitParts(allItems.length, cfg);
    const multi = parts.length > 1;
    const done = new Set();
    let part = 0;
    let items = [];
    let total = 0;
    let html = null;
    let previewHtml = null;
    const el = document.createElement('div');
    el.className = 'bc-job-backdrop';
    el.id = 'bcm-job';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML = `
      <div class="bc-job-card" id="bcm-job-card">
        <div class="qf-picker-head">
          <span class="qf-picker-head-icon"><i class="fa-solid fa-barcode"></i></span>
          <div style="flex:1;min-width:0"><div class="qf-picker-title" style="margin-bottom:0">${t('Barcode Labels')}</div></div>
          <button class="salm-modal-close" id="bcm-job-x" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="bc-job-parts" id="bcm-job-parts" style="display:none">
          <div class="bc-job-parts-msg"><i class="fa-solid fa-triangle-exclamation"></i> <span id="bcm-job-parts-msg"></span></div>
          <div class="bc-job-parts-nav">
            <button class="salm-btn-ghost" id="bcm-job-part-prev" type="button" title="${t('Previous part')}"><i class="fa-solid fa-chevron-left"></i></button>
            <select class="bc-job-part-sel" id="bcm-job-part-sel"></select>
            <button class="salm-btn-ghost" id="bcm-job-part-next" type="button" title="${t('Next part')}"><i class="fa-solid fa-chevron-right"></i></button>
          </div>
        </div>
        <div class="bc-job-body">
          <i class="fa-solid fa-barcode bc-job-ico" id="bcm-job-ico"></i>
          <div class="bc-job-title" id="bcm-job-title"></div>
          <div class="bc-job-bar" id="bcm-job-bar"><div class="bc-job-fill" id="bcm-job-fill"></div></div>
          <div class="bc-job-text" id="bcm-job-text"></div>
          <ul class="bc-job-steps" id="bcm-job-steps" style="display:none"></ul>
          <div class="bc-job-hint" id="bcm-job-hint"></div>
          <div class="bc-job-preview" id="bcm-job-preview" style="display:none">
            <div class="bc-job-preview-head"><i class="fa-solid fa-eye"></i> ${t('Preview')}<span class="bc-job-preview-note" id="bcm-job-preview-note"></span></div>
            <iframe class="bc-job-preview-frame" id="bcm-job-preview-frame" title="${t('Preview')}" sandbox></iframe>
          </div>
        </div>
        <div class="bc-job-foot" id="bcm-job-foot" style="display:none">
          <button class="salm-btn-ghost" id="bcm-job-close" type="button"><i class="fa-solid fa-xmark"></i> ${t('Close')}</button>
          <button class="salm-btn-ghost" id="bcm-job-pdf" type="button"><i class="fa-solid fa-file-pdf"></i> ${t('Save PDF')}</button>
          <button class="salm-btn-primary" id="bcm-job-print" type="button"><i class="fa-solid fa-print"></i> ${t('Print')}</button>
        </div>
      </div>`;
    document.body.appendChild(el);

    let pdfRunning = false;
    function close() {
      if (pdfRunning) return; // the PDF engine is mid-run — let it finish
      jobToken++; // stops an in-progress prepare
      el.remove();
    }
    document.getElementById('bcm-job-x').addEventListener('click', close);
    document.getElementById('bcm-job-close').addEventListener('click', close);

    const partTitle = (title) => (multi ? `${title} — ${t('Part {p} of {n}', { p: part + 1, n: parts.length })}` : title);
    const renderingPanel = (n) => {
      const pct = Math.floor(n / total * 100);
      jobPanel({
        icon: 'fa-barcode', iconCls: 'bc-pulse', title: partTitle(t('Rendering barcodes…')), pct,
        text: t('{done} / {total} labels · {pct}%', { done: fmtNum(n), total: fmtNum(total), pct }),
        hint: t('Print and Save PDF will be available when rendering finishes'),
      });
    };
    const readyPanel = (hint = '') => {
      const perPage = cfg.rowsPerPage ? cfg.cols * cfg.rowsPerPage : 0;
      jobPanel({
        icon: 'fa-circle-check', iconCls: 'bc-ok', title: partTitle(t('Sheet ready')), pct: 100,
        text: perPage
          ? t('{n} labels · about {p} pages', { n: fmtNum(total), p: fmtNum(Math.ceil(total / perPage)) })
          : t('{n} labels', { n: fmtNum(total) }),
        hint, footer: true,
        preview: previewHtml ? {
          html: previewHtml,
          note: total > BC_PREVIEW_LABELS
            ? t('first {n} of {total}', { n: BC_PREVIEW_LABELS, total: fmtNum(total) }) : '',
        } : null,
      });
    };

    // ── Part navigator (large jobs only) ──
    const partSel = document.getElementById('bcm-job-part-sel');
    const partPrev = document.getElementById('bcm-job-part-prev');
    const partNext = document.getElementById('bcm-job-part-next');
    function syncParts() {
      if (!multi) return;
      document.getElementById('bcm-job-parts').style.display = '';
      document.getElementById('bcm-job-parts-msg').textContent = t(
        'Large job: {total} labels split into {n} parts — print or save each part separately ({d} of {n} done)',
        { total: fmtNum(allItems.length), n: parts.length, d: done.size });
      partSel.innerHTML = parts.map(([a, b], i) => `<option value="${i}"${i === part ? ' selected' : ''}>${done.has(i) ? '✓ ' : ''}${esc(
        t('Part {p} of {n} · labels {a}–{b}', { p: i + 1, n: parts.length, a: fmtNum(a + 1), b: fmtNum(b) }))}</option>`).join('');
    }
    function lockParts(locked) {
      partSel.disabled = locked;
      partPrev.disabled = locked || part <= 0;
      partNext.disabled = locked || part >= parts.length - 1;
    }
    function markDone() { done.add(part); syncParts(); }

    // Build the current part in chunks so the window stays responsive
    const h = cfg.mmH ? Math.max(24, Math.round(cfg.mmH * 1.7)) : 46;
    async function buildPart() {
      const token = ++jobToken;
      const [from, to] = parts[part];
      items = allItems.slice(from, to);
      total = items.length;
      html = null;
      previewHtml = null;
      syncParts();
      lockParts(true);
      renderingPanel(0);
      const out = [];
      for (let i = 0; i < total; i += BC_SHEET_CHUNK) {
        await nextFrame();
        if (token !== jobToken) return;
        const end = Math.min(i + BC_SHEET_CHUNK, total);
        for (let k = i; k < end; k++) out.push(labelMarkup({ ...items[k], _h: h }));
        renderingPanel(end);
      }
      if (token !== jobToken) return;
      html = sheetDocument(out.join(''), cfg);
      previewHtml = previewDocument(out.slice(0, BC_PREVIEW_LABELS).join(''), cfg);
      lockParts(false);
      readyPanel();
    }
    const goPart = (i) => {
      if (pdfRunning || i < 0 || i >= parts.length || i === part) return;
      part = i;
      buildPart();
    };
    partSel.addEventListener('change', () => goPart(parseInt(partSel.value, 10)));
    partPrev.addEventListener('click', () => goPart(part - 1));
    partNext.addEventListener('click', () => goPart(part + 1));

    const printBtn = document.getElementById('bcm-job-print');
    const pdfBtn = document.getElementById('bcm-job-pdf');

    printBtn.addEventListener('click', () => {
      if (!html) return;
      lockParts(true);
      busy(printBtn, t('Printing…'), async () => {
        if (await window.electronAPI.printHtml(html)) markDone();
      }, () => lockParts(false));
    });

    pdfBtn.addEventListener('click', async () => {
      if (pdfRunning || !html) return;
      pdfRunning = true;
      lockParts(true);
      const startedAt = Date.now();
      let stage = 'prepare';
      const paint = () => {
        const idx = Math.max(0, PDF_STEPS.findIndex((s) => s[0] === stage));
        const secs = Math.floor((Date.now() - startedAt) / 1000);
        const steps = PDF_STEPS.map(([, label], i) => {
          const cls = i < idx ? 'done' : i === idx ? 'active' : '';
          const ico = i < idx ? 'fa-circle-check' : i === idx ? 'fa-spinner fa-spin' : 'fa-circle';
          return `<li class="${cls}"><i class="fa-solid ${ico}"></i> ${esc(t(label))}</li>`;
        }).join('');
        const waitingOnUser = stage === 'saving-dialog';
        jobPanel({
          icon: 'fa-file-pdf', iconCls: 'bc-pulse', title: partTitle(t('Generating PDF…')), pct: PDF_STEPS[idx][2],
          working: !waitingOnUser, steps,
          text: t('{n} labels · {s}s elapsed', { n: fmtNum(total), s: secs }),
          hint: waitingOnUser ? t('Pick a file name in the save window to finish')
            : t('Large sheets can take a minute — please keep this window open'),
        });
      };
      paint();
      const timer = setInterval(paint, 1000);
      const off = window.electronAPI.onPdfProgress ? window.electronAPI.onPdfProgress((s) => { stage = s; paint(); }) : null;
      let res;
      try {
        await nextFrame();
        const pad = String(parts.length).length;
        const suffix = multi ? `-part-${String(part + 1).padStart(pad, '0')}-of-${parts.length}` : '';
        res = await window.electronAPI.savePdf(html, `barcodes-${new Date().toISOString().slice(0, 10)}${suffix}.pdf`);
      } finally {
        clearInterval(timer);
        if (off) off();
        pdfRunning = false;
        lockParts(false);
      }
      if (!document.getElementById('bcm-job')) return;
      if (res?.status === 200) {
        markDone();
        readyPanel(t('PDF saved: {path}', { path: res.savedPath }));
        showToast(t('Barcode PDF saved.'), 'success');
      } else if (res?.canceled) {
        readyPanel();
      } else {
        readyPanel(res?.message || t('Could not save PDF.'));
      }
    });

    if (multi) showToast(t('{n} labels — split into {p} parts to keep the system stable', { n: fmtNum(allItems.length), p: parts.length }));
    buildPart();
  }

  // ── Home view: the print queue (one entry per product) ───────────────
  function setBatchCopies(batch, n) {
    batch.labelCount = Math.max(1, Math.min(BC_MAX_LABELS, n));
    batch.items = Array.from({ length: batch.labelCount }, () => batch.item);
  }

  function refreshQueue() {
    const batchCount = _bc.queue.length;
    const totalLabels = _bc.queue.reduce((s, b) => s + b.labelCount, 0);
    const uniqueProducts = new Set(_bc.queue.map((b) => b.productName)).size;
    const cfg = printConfig();
    const labelsPerPage = cfg.rowsPerPage ? cfg.cols * cfg.rowsPerPage : cfg.cols * 10;
    const estPages = totalLabels ? Math.ceil(totalLabels / labelsPerPage) : 0;

    const badgeEl = document.getElementById('bcm-queue-badge');
    if (badgeEl) badgeEl.innerHTML = totalLabels ? `<span class="bc-queue-badge">${fmtNum(totalLabels)}</span>` : '';
    const printAllBtn = document.getElementById('bcm-print-all');
    if (printAllBtn && !printAllBtn.dataset.busy) printAllBtn.disabled = !batchCount;
    const clearAllBtn = document.getElementById('bcm-clear-all');
    if (clearAllBtn) clearAllBtn.disabled = !batchCount;

    // drop selections for batches no longer in the queue
    _bc.selected.forEach((b) => { if (!_bc.queue.includes(b)) _bc.selected.delete(b); });
    const selCount = _bc.selected.size;
    const selBar = document.getElementById('bcm-sel-bar');
    if (selBar) selBar.style.display = batchCount ? '' : 'none';
    const selAll = document.getElementById('bcm-sel-all');
    if (selAll) {
      selAll.checked = batchCount > 0 && selCount === batchCount;
      selAll.indeterminate = selCount > 0 && selCount < batchCount;
    }
    const selCountEl = document.getElementById('bcm-sel-count');
    if (selCountEl) selCountEl.textContent = selCount ? t('{sel} of {total} selected', { sel: fmtNum(selCount), total: fmtNum(batchCount) }) : '';
    const selRemove = document.getElementById('bcm-sel-remove');
    if (selRemove) selRemove.disabled = !selCount;

    document.getElementById('bcm-tf-batches').textContent = batchCount;
    document.getElementById('bcm-tf-labels').textContent = fmtNum(totalLabels);
    document.getElementById('bcm-tf-products').textContent = uniqueProducts;
    document.getElementById('bcm-tf-pages').textContent = fmtNum(estPages);
    const statusEl = document.getElementById('bcm-tf-status');
    statusEl.className = `bc-tf-status${batchCount ? ' ready' : ''}`;
    statusEl.innerHTML = batchCount
      ? `<i class="fa-solid fa-circle-check"></i> ${t('{n} labels ready to print', { n: fmtNum(totalLabels) })}`
      : `<i class="fa-solid fa-circle-dot"></i> ${t('Queue empty')}`;

    if (!_bc.queue.length) {
      queueContentEl.innerHTML = `
        <div class="salm-empty" style="padding:56px 0">
          <div style="font-size:30px;color:var(--accent);opacity:.5;margin-bottom:10px"><i class="fa-solid fa-barcode"></i></div>
          <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px">${t('No Labels Yet')}</div>
          <div>${t('Click "Generate Barcode" to add products to your print queue')}</div>
        </div>`;
      return;
    }

    queueContentEl.innerHTML = _bc.queue.map((batch, i) => {
      const paperLabel = batch.paper === 'single' ? t('Single Row') : batch.paper === 'double' ? t('Double Row') : t('{w}×{h} mm', { w: batch.customW, h: batch.customH });
      const sel = _bc.selected.has(batch);
      return `<div class="bc-batch-card${sel ? ' bc-batch-selected' : ''}">
        <input type="checkbox" class="bc-batch-chk" data-sel="${i}"${sel ? ' checked' : ''} title="${t('Select')}">
        <div class="bc-batch-card-icon"><i class="fa-solid fa-barcode"></i></div>
        <div class="bc-batch-card-info">
          <div class="bc-batch-card-name">${esc(batch.productName)}</div>
          <div class="bc-batch-card-meta">
            ${batch.item.code ? `<span class="bc-batch-meta-tag" style="font-family:monospace"><i class="fa-solid fa-barcode"></i> ${esc(batch.item.code)}</span>` : ''}
            ${batch.item.price ? `<span class="bc-batch-meta-tag"><i class="fa-solid fa-tag"></i> ${esc(batch.item.price)}</span>` : ''}
            ${batch.topText ? `<span class="bc-batch-meta-tag"><i class="fa-solid fa-arrow-up"></i> ${esc(batch.topText)}</span>` : ''}
            ${batch.bottomText ? `<span class="bc-batch-meta-tag"><i class="fa-solid fa-arrow-down"></i> ${esc(batch.bottomText)}</span>` : ''}
            <span class="bc-batch-meta-tag"><i class="fa-solid fa-ruler-combined"></i> ${esc(paperLabel)}</span>
          </div>
        </div>
        <div class="bc-batch-card-actions">
          <label class="bc-batch-copies">${t('Copies')}
            <input type="number" class="bc-batch-copies-inp" data-copies="${i}" value="${batch.labelCount}" min="1" max="${BC_MAX_LABELS}">
          </label>
          <button class="salm-icon-btn" data-print="${i}" type="button"><i class="fa-solid fa-print"></i> ${t('Print')}</button>
          <button class="salm-icon-btn" data-del="${i}" type="button"><i class="fa-solid fa-trash"></i></button>
        </div>
      </div>`;
    }).join('');

    queueContentEl.querySelectorAll('input[data-copies]').forEach((inp) => {
      inp.addEventListener('change', () => {
        const batch = _bc.queue[Number(inp.dataset.copies)];
        if (!batch) return;
        setBatchCopies(batch, parseInt(inp.value) || 1);
        refreshQueue();
      });
    });
    queueContentEl.querySelectorAll('input[data-sel]').forEach((chk) => {
      chk.addEventListener('change', () => {
        const batch = _bc.queue[Number(chk.dataset.sel)];
        if (!batch) return;
        if (chk.checked) _bc.selected.add(batch); else _bc.selected.delete(batch);
        refreshQueue();
      });
    });
    queueContentEl.querySelectorAll('button[data-del]').forEach((btn) => {
      btn.addEventListener('click', () => { _bc.queue.splice(Number(btn.dataset.del), 1); refreshQueue(); });
    });
    queueContentEl.querySelectorAll('button[data-print]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const batch = _bc.queue[Number(btn.dataset.print)];
        if (batch) openSheetJob(batch.items, printConfig());
      });
    });
  }

  // ── Print-settings strip (Printer Type / Label Size or A4 layout) ─────
  function renderPrintSettings() {
    const el = document.getElementById('bcm-print-settings');
    el.innerHTML = `
      <div class="bc-ps-group">
        <span class="bc-ps-lbl"><i class="fa-solid fa-print"></i> ${t('Printer Type')}</span>
        <div class="bc-ps-seg" id="bcm-ps-type">
          <button type="button" class="bc-ps-seg-btn${_print.printerType === 'roll' ? ' active' : ''}" data-val="roll"><i class="fa-solid fa-tag"></i> ${t('Label Printer')}</button>
          <button type="button" class="bc-ps-seg-btn${_print.printerType === 'a4' ? ' active' : ''}" data-val="a4"><i class="fa-solid fa-file-lines"></i> ${t('Regular')}</button>
        </div>
      </div>
      <div class="bc-ps-vsep"></div>
      ${_print.printerType === 'roll' ? `
      <div class="bc-ps-group">
        <span class="bc-ps-lbl"><i class="fa-solid fa-ruler-combined"></i> ${t('Label Size')}</span>
        <div class="bc-ps-seg" id="bcm-ps-size">
          ${LABEL_SIZES.map((s) => `<button type="button" class="bc-ps-seg-btn${_print.labelSize === s.key ? ' active' : ''}" data-val="${s.key}">${esc(t(s.label))}</button>`).join('')}
        </div>
      </div>` : `
      <div class="bc-ps-group">
        <span class="bc-ps-lbl"><i class="fa-solid fa-table-cells"></i> ${t('Layout')}</span>
        <div class="bc-ps-field">
          <label class="bc-ps-field-lbl">${t('Cols')}</label>
          <input type="number" id="bcm-a4-cols" class="bc-ps-dim-inp" value="${_print.a4Cols}" min="1" max="10">
        </div>
        <div class="bc-ps-field">
          <label class="bc-ps-field-lbl">${t('Rows / page')}</label>
          <input type="number" id="bcm-a4-rows" class="bc-ps-dim-inp" value="${_print.a4Rows}" min="1" max="30">
        </div>
      </div>`}`;

    document.getElementById('bcm-ps-type').querySelectorAll('button').forEach((btn) => {
      btn.addEventListener('click', () => { _print.printerType = btn.dataset.val; renderPrintSettings(); refreshQueue(); });
    });
    document.getElementById('bcm-ps-size')?.querySelectorAll('button').forEach((btn) => {
      btn.addEventListener('click', () => { _print.labelSize = btn.dataset.val; renderPrintSettings(); refreshQueue(); });
    });
    document.getElementById('bcm-a4-cols')?.addEventListener('input', (e) => { _print.a4Cols = Math.max(1, Math.min(10, parseInt(e.target.value) || 3)); refreshQueue(); });
    document.getElementById('bcm-a4-rows')?.addEventListener('input', (e) => { _print.a4Rows = Math.max(1, Math.min(30, parseInt(e.target.value) || 8)); refreshQueue(); });
  }

  // ── Stock-layers popup (choose a batch + qty, layered above the builder) ─
  async function openStockLayersPopup(product) {
    if (document.getElementById('bcm-layers-backdrop')) return;
    const el = document.createElement('div');
    el.className = 'bc-layers-backdrop';
    el.id = 'bcm-layers-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML = `
      <div class="bc-layers-card">
        <div class="qf-picker-head">
          <span class="qf-picker-head-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
          <div style="flex:1;min-width:0">
            <div class="qf-picker-title" style="margin-bottom:1px">${esc(product.name)}</div>
            <div style="font-size:10.5px;color:var(--muted);font-family:monospace">${esc(product.sku || '')}</div>
          </div>
          <button class="salm-modal-close" id="bcm-layers-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="bc-layers-list" id="bcm-layers-list"><div class="salm-loading">${t('Loading…')}</div></div>
      </div>`;
    document.body.appendChild(el);

    function close() { el.remove(); }
    document.getElementById('bcm-layers-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });

    const listEl = document.getElementById('bcm-layers-list');
    const res = await API.productStockHistory(product.id);
    let batches = res.status === 200 ? (res.body?.data ?? []) : [];

    // Synthesise an opening-stock row when no stock layers exist yet.
    if (!batches.length) {
      batches = [{
        id: null, batch_sku: product.sku || null,
        quantity_received: parseFloat(product.stock_quantity) || 0,
        quantity_remaining: parseFloat(product.stock_quantity) || 0,
        selling_unit_price: product.unit_sell_price != null ? parseFloat(product.unit_sell_price) : null,
        source_type: 'opening', received_at: null, grn_number: null, po_number: null,
      }];
    }

    listEl.innerHTML = batches.map((h, i) => {
      const src = h.source_type || (h.grn_number ? 'grn' : 'opening');
      const srcLabel = t(src === 'opening' ? 'Opening Stock' : src === 'po' ? 'Purchase Order' : src === 'transfer' ? 'Stock Transfer' : 'Goods Receive');
      const qtyLeft = parseFloat(h.quantity_remaining || 0);
      const qtyIn = parseFloat(h.quantity_received || 0);
      const batchSku = h.batch_sku || product.sku || String(product.id);
      const sell = h.selling_unit_price != null ? money(h.selling_unit_price) : '';
      return `<div class="bc-layer-row" data-i="${i}">
        <div class="bc-layer-info">
          <div class="bc-layer-badges">
            <span class="bc-layer-src">${esc(srcLabel)}</span>
            <span class="bc-layer-sku"><i class="fa-solid fa-barcode"></i> ${esc(batchSku)}</span>
            <span style="font-size:10.5px;color:var(--muted)">${esc(fmtDate(h.received_at))}</span>
          </div>
          <div class="bc-layer-stats">
            <span>${t('Remaining')}: <b>${qtyLeft % 1 === 0 ? qtyLeft : qtyLeft.toFixed(2)}</b></span>
            <span>${t('Received')}: <b>${qtyIn % 1 === 0 ? qtyIn : qtyIn.toFixed(2)}</b></span>
            ${sell ? `<span>${t('Sell Price')}: <b>${sell}</b></span>` : ''}
          </div>
        </div>
        <div class="bc-layer-action">
          <input type="number" class="bc-layer-qty-inp" value="${Math.max(1, Math.floor(qtyLeft)) || 1}" min="1" max="500">
          <button class="bc-layer-add-btn" type="button" data-sku="${esc(batchSku)}" data-price="${sell ? esc(sell) : ''}"><i class="fa-solid fa-plus"></i> ${t('Add')}</button>
        </div>
      </div>`;
    }).join('');

    listEl.querySelectorAll('.bc-layer-add-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const row = btn.closest('.bc-layer-row');
        const qtyInp = row.querySelector('.bc-layer-qty-inp');
        const qty = Math.max(1, Math.min(500, parseInt(qtyInp.value) || 1));
        for (let n = 0; n < qty; n++) {
          _bc.items.push({ name: product.name, code: btn.dataset.sku, price: btn.dataset.price || '' });
        }
        close();
        refreshBuilderPreview();
        setBuilderView('preview');
      });
    });
  }

  // ── Product picker (browse / search, multi-select, select all) ───────
  // Label count defaults to stock on hand (at least 1)
  function pickDefaultQty(p) { return Math.max(1, Math.min(BC_MAX_LABELS, Math.floor(parseFloat(p.stock_quantity) || 0))); }
  function pickQty(p) { return _pick.qty.has(p.id) ? _pick.qty.get(p.id) : pickDefaultQty(p); }
  function pickPrice(p) { return p.unit_sell_price != null && p.unit_sell_price !== '' ? money(p.unit_sell_price) : ''; }
  // Labels for a picked product, split across its in-stock batches (FIFO) so each
  // label carries that batch's own barcode and price. Labels beyond the batch
  // stock (qty typed higher) fall back to the product SKU / price.
  function pickItems(p, qty) {
    const productCode = p.sku || String(p.id);
    const productPrice = pickPrice(p);
    const items = [];
    for (const b of (p.batches || [])) {
      if (items.length >= qty) break;
      const code = b.batch_sku || productCode;
      const price = b.selling_unit_price != null ? money(b.selling_unit_price) : productPrice;
      const n = Math.min(qty - items.length, Math.floor(parseFloat(b.quantity_remaining) || 0));
      for (let i = 0; i < n; i++) items.push({ name: p.name, code, price });
    }
    while (items.length < qty) items.push({ name: p.name, code: productCode, price: productPrice });
    return items;
  }

  async function pickLoad() {
    const listEl = document.getElementById('bcm-pick-list');
    if (!listEl) return;
    const reqId = ++_pick.reqId;
    _pick.all = []; _pick.list = []; _pick.rendered = 0;
    listEl.innerHTML = `<div class="salm-loading"><i class="fa-solid fa-spinner fa-spin"></i> ${t('Loading all products…')}</div>`;
    pickSyncToolbar();
    await loadSettings(); // prices are formatted with the business currency
    const res = await API.barcodeProducts();
    if (reqId !== _pick.reqId || !document.getElementById('bcm-pick-list')) return;
    if (res.status !== 200) {
      listEl.innerHTML = `<div class="salm-empty">${t('Could not load products.')}</div>`;
      return;
    }
    _pick.all = res.body?.data || [];
    pickFilter(document.getElementById('bcm-search-inp')?.value.trim() || '');
  }

  function pickFilter(q) {
    const needle = q.toLowerCase();
    _pick.list = needle
      ? _pick.all.filter((p) => p.name.toLowerCase().includes(needle) || (p.sku || '').toLowerCase().includes(needle))
      : _pick.all;
    pickRender();
  }

  function pickRowHtml(p) {
    const checked = _pick.selected.has(p.id);
    const stock = parseFloat(p.stock_quantity || 0);
    return `<div class="bc-pick-row${checked ? ' selected' : ''}" data-pid="${p.id}">
      <input type="checkbox" class="bc-pick-chk"${checked ? ' checked' : ''}>
      <div class="bc-pick-info">
        <div class="bc-pick-name">${esc(p.name)}</div>
        <div class="bc-pick-meta">
          ${p.sku ? `<span class="bc-layer-sku"><i class="fa-solid fa-barcode"></i> ${esc(p.sku)}</span>` : `<span style="opacity:.6">${t('No SKU')}</span>`}
          <span>${t('Stock: {n}', { n: stock % 1 === 0 ? stock : stock.toFixed(2) })}</span>
        </div>
      </div>
      <div class="bc-pick-price">${esc(pickPrice(p))}</div>
      <input type="number" class="bc-pick-qty" value="${pickQty(p)}" min="1" max="${BC_MAX_LABELS}" title="${t('Labels to print')}">
      <button type="button" class="bc-pick-layers" title="${t('Pick from stock layers')}"><i class="fa-solid fa-layer-group"></i></button>
    </div>`;
  }

  function pickRender() {
    const listEl = document.getElementById('bcm-pick-list');
    if (!listEl) return;
    listEl.scrollTop = 0;
    _pick.rendered = 0;
    if (!_pick.list.length) {
      listEl.innerHTML = `<div class="salm-empty">${t('No products found.')}</div>`;
      pickSyncToolbar();
      return;
    }
    listEl.innerHTML = '';
    pickRenderMore();
    pickSyncToolbar();
  }

  // Rows are rendered in chunks as the list scrolls so thousands stay snappy
  function pickRenderMore() {
    const listEl = document.getElementById('bcm-pick-list');
    if (!listEl || _pick.rendered >= _pick.list.length) return;
    const next = _pick.list.slice(_pick.rendered, _pick.rendered + BC_PICK_CHUNK);
    listEl.insertAdjacentHTML('beforeend', next.map(pickRowHtml).join(''));
    _pick.rendered += next.length;
  }

  function pickSyncToolbar() {
    const n = _pick.selected.size;
    const shown = _pick.list.length;
    const inView = n ? _pick.list.filter((p) => _pick.selected.has(p.id)).length : 0;
    const allChk = document.getElementById('bcm-pick-all');
    if (allChk) {
      allChk.checked = shown > 0 && inView === shown;
      allChk.indeterminate = inView > 0 && inView < shown;
      allChk.disabled = !shown;
    }
    const labels = n ? [..._pick.selected.values()].reduce((s, p) => s + pickQty(p), 0) : 0;
    const countEl = document.getElementById('bcm-pick-count');
    if (countEl) {
      countEl.textContent = n
        ? t('{sel} of {shown} selected · {labels} labels', { sel: fmtNum(n), shown: fmtNum(shown), labels: fmtNum(labels) })
        : (shown ? t('{n} products', { n: fmtNum(shown) }) : '');
    }
    const addBtn = document.getElementById('bcm-pick-add');
    if (addBtn && !addBtn.dataset.busy) {
      addBtn.disabled = !n;
      addBtn.innerHTML = `<i class="fa-solid fa-plus"></i> ${n ? t('Add Selected ({n})', { n: fmtNum(n) }) : t('Add Selected')}`;
    }
  }

  function pickToggle(pid, on) {
    const p = _pick.all.find((x) => x.id === pid);
    if (!p) return;
    if (on) _pick.selected.set(pid, p); else _pick.selected.delete(pid);
    const row = document.getElementById('bcm-pick-list')?.querySelector(`.bc-pick-row[data-pid="${pid}"]`);
    if (row) {
      row.classList.toggle('selected', on);
      row.querySelector('.bc-pick-chk').checked = on;
    }
  }

  function wirePicker() {
    const listEl = document.getElementById('bcm-pick-list');
    listEl.addEventListener('scroll', () => {
      if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 300) pickRenderMore();
    });
    listEl.addEventListener('click', (e) => {
      const row = e.target.closest('.bc-pick-row');
      if (!row) return;
      const pid = Number(row.dataset.pid);
      if (e.target.closest('.bc-pick-layers')) {
        const p = _pick.all.find((x) => x.id === pid);
        if (p) openStockLayersPopup(p);
        return;
      }
      if (e.target.closest('.bc-pick-qty')) return;
      const on = e.target.classList.contains('bc-pick-chk') ? e.target.checked : !_pick.selected.has(pid);
      pickToggle(pid, on);
      pickSyncToolbar();
    });
    listEl.addEventListener('input', (e) => {
      if (!e.target.classList.contains('bc-pick-qty')) return;
      const pid = Number(e.target.closest('.bc-pick-row').dataset.pid);
      _pick.qty.set(pid, Math.max(1, Math.min(BC_MAX_LABELS, parseInt(e.target.value) || 1)));
      // typing a label count implies the product should be included
      if (!_pick.selected.has(pid)) pickToggle(pid, true);
      pickSyncToolbar();
    });

    // Select all = every product matching the current search (not only rendered rows)
    document.getElementById('bcm-pick-all').addEventListener('change', (e) => {
      const on = e.target.checked;
      _pick.list.forEach((p) => { if (on) _pick.selected.set(p.id, p); else _pick.selected.delete(p.id); });
      listEl.querySelectorAll('.bc-pick-row').forEach((row) => {
        row.classList.toggle('selected', on);
        row.querySelector('.bc-pick-chk').checked = on;
      });
      pickSyncToolbar();
    });

    const addBtn = document.getElementById('bcm-pick-add');
    addBtn.addEventListener('click', () => {
      if (!_pick.selected.size) return;
      const total = [..._pick.selected.values()].reduce((s, p) => s + pickQty(p), 0);
      if (total > 2000 && !confirm(t('This will add {n} labels (label count follows stock). Large sheets can take a while to render. Continue?', { n: fmtNum(total) }))) return;
      busy(addBtn, t('Adding…'), () => {
        let added = 0;
        _pick.selected.forEach((p) => {
          const items = pickItems(p, pickQty(p));
          for (const it of items) _bc.items.push(it);
          added += items.length;
        });
        const products = _pick.selected.size;
        _pick.selected.clear();
        _pick.qty.clear();
        pickRender();
        refreshBuilderPreview();
        setBuilderView('preview');
        showToast(t('{n} labels added from {p} products', { n: fmtNum(added), p: fmtNum(products) }), 'success');
      }, pickSyncToolbar);
    });
  }

  // ── Builder overlay: "Generate Barcode" ─────────────────────────────
  function labelDims(paper) {
    if (paper === 'single') return { w: 220, h: null, barcodeH: 42 };
    if (paper === 'double') return { w: 220, h: null, barcodeH: 76 };
    const wMm = Math.max(10, parseInt(document.getElementById('bcm-custom-w')?.value) || 60);
    const hMm = Math.max(10, parseInt(document.getElementById('bcm-custom-h')?.value) || 30);
    const scale = Math.min(240 / wMm, 200 / hMm);
    const w = Math.round(wMm * scale);
    const h = Math.round(hMm * scale);
    return { w, h, barcodeH: Math.max(26, Math.round(h * 0.46)) };
  }

  function setBuilderView(view) {
    document.querySelectorAll('.bc-view-tab').forEach((tab) => tab.classList.toggle('active', tab.dataset.view === view));
    const pick = document.getElementById('bcm-pick-wrap');
    const prev = document.getElementById('bcm-preview-body');
    if (pick) pick.style.display = view === 'products' ? '' : 'none';
    if (prev) prev.style.display = view === 'preview' ? '' : 'none';
    // the label can only be measured once its tab is visible
    const labelEl = view === 'preview' && prev?.querySelector('.bc-plabel');
    if (labelEl && labelEl.style.height) fitLabelBarcode(labelEl);
  }

  function refreshBuilderPreview() {
    const body = document.getElementById('bcm-preview-body');
    const countEl = document.getElementById('bcm-preview-count');
    const footInfo = document.getElementById('bcm-foot-info');
    const addBtn = document.getElementById('bcm-add-to-print');
    const printBtn = document.getElementById('bcm-print-sheet');
    if (!body) return;
    const n = _bc.items.length;
    if (countEl) countEl.textContent = n ? fmtNum(n) : '';
    if (footInfo) footInfo.innerHTML = `<i class="fa-solid fa-tags"></i> <span>${n ? t('{n} labels added', { n: fmtNum(n) }) : t('No labels added')}</span>`;
    if (addBtn && !addBtn.dataset.busy) addBtn.disabled = !n;
    if (printBtn && !printBtn.dataset.busy) printBtn.disabled = !n;

    if (!n) {
      body.innerHTML = `<div class="bc-preview-ph"><i class="fa-solid fa-barcode"></i><span>${t('Select products in the Products tab, then add barcode labels')}</span></div>`;
      return;
    }
    const paper = document.querySelector('input[name="bcm-paper"]:checked')?.value || 'single';
    const dims = labelDims(paper);
    const it = _bc.items[_bc.items.length - 1];
    const topText = document.getElementById('bcm-top-text')?.value.trim() || '';
    const bottomText = document.getElementById('bcm-bottom-text')?.value.trim() || '';
    body.innerHTML = labelMarkup({ ...it, topText, bottomText, _h: dims.barcodeH });
    const labelEl = body.querySelector('.bc-plabel');
    if (labelEl) { labelEl.style.width = `${dims.w}px`; if (dims.h) labelEl.style.height = `${dims.h}px`; }
    if (labelEl && dims.h) fitLabelBarcode(labelEl);
  }

  // A fixed-height (custom size) label can't hold a full-height barcode plus
  // every text line — shrink the bars into whatever height the text leaves.
  function fitLabelBarcode(labelEl) {
    const svg = labelEl.querySelector('svg');
    if (!svg) return;
    // Sum the children — centred flex content overflows both edges, so
    // scrollHeight would under-report it.
    const cs = getComputedStyle(labelEl);
    const kids = [...labelEl.children];
    const used = kids.reduce((s, k) => s + k.getBoundingClientRect().height, 0)
      + (parseFloat(cs.rowGap) || 0) * Math.max(0, kids.length - 1);
    const avail = labelEl.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
    const overflow = Math.ceil(used - avail);
    if (overflow <= 0) return;
    const barH = svg.getBoundingClientRect().height;
    svg.setAttribute('preserveAspectRatio', 'none'); // keep full width, squash height only
    svg.style.height = `${Math.max(12, Math.floor(barH - overflow))}px`;
  }

  // One queue entry per product/barcode, so the home view lists every product with its copy count
  function addBuilderItemsToQueue() {
    const copies = Math.max(1, Math.min(100, parseInt(document.getElementById('bcm-copies').value) || 1));
    const topText = document.getElementById('bcm-top-text').value.trim();
    const bottomText = document.getElementById('bcm-bottom-text').value.trim();
    const paper = document.querySelector('input[name="bcm-paper"]:checked')?.value || 'single';
    const customW = parseInt(document.getElementById('bcm-custom-w').value) || 60;
    const customH = parseInt(document.getElementById('bcm-custom-h').value) || 30;

    const groups = new Map();
    _bc.items.forEach((it) => {
      const key = `${it.code}\u0000${it.name}\u0000${it.price || ''}`;
      const g = groups.get(key);
      if (g) g.count++; else groups.set(key, { item: { ...it, topText, bottomText }, count: 1 });
    });
    groups.forEach(({ item, count }) => {
      const labelCount = count * copies;
      const existing = _bc.queue.find((b) =>
        b.item.code === item.code && b.item.name === item.name && (b.item.price || '') === (item.price || '') &&
        b.topText === topText && b.bottomText === bottomText && b.paper === paper &&
        (paper !== 'custom' || (b.customW === customW && b.customH === customH)));
      if (existing) {
        setBatchCopies(existing, existing.labelCount + labelCount);
      } else {
        const batch = { item, topText, bottomText, paper, customW, customH, productName: item.name, items: [], labelCount: 0 };
        setBatchCopies(batch, labelCount);
        _bc.queue.push(batch);
      }
    });
    return groups.size;
  }

  function openBuilderModal() {
    if (document.getElementById('bcm-builder-backdrop')) return;
    _bc.items = [];
    _pick.selected.clear();
    _pick.qty.clear();

    const el = document.createElement('div');
    el.className = 'bc-builder-backdrop';
    el.id = 'bcm-builder-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML = `
      <div class="bc-builder-card">
        <div class="bc-builder-head">
          <span class="bc-builder-head-icon"><i class="fa-solid fa-barcode"></i></span>
          <div class="bc-builder-head-text">
            <h3>${t('Generate Barcode')}</h3>
            <p>${t('Select products (or search) to add barcode labels')}</p>
          </div>
          <button class="salm-modal-close" id="bcm-builder-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="bc-builder-search-wrap">
          <div class="bc-builder-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="bcm-search-inp" placeholder="${t('Search or browse products below by name or SKU…')}" autocomplete="off"></div>
        </div>

        <div class="bc-builder-body">
          <div class="bc-builder-preview">
            <div class="bc-builder-preview-head">
              <div class="bc-view-tabs">
                <button type="button" class="bc-view-tab active" data-view="products"><i class="fa-solid fa-list-check"></i> ${t('Products')}</button>
                <button type="button" class="bc-view-tab" data-view="preview"><i class="fa-solid fa-eye"></i> ${t('Preview')} <span id="bcm-preview-count" class="bc-view-count"></span></button>
              </div>
            </div>

            <div class="bc-pick-wrap" id="bcm-pick-wrap">
              <div class="bc-pick-toolbar">
                <label class="bc-pick-all"><input type="checkbox" id="bcm-pick-all"> ${t('Select all')}</label>
                <span class="bc-pick-count" id="bcm-pick-count"></span>
                <button type="button" class="salm-btn-primary" id="bcm-pick-add" disabled><i class="fa-solid fa-plus"></i> ${t('Add Selected')}</button>
              </div>
              <div class="bc-pick-list" id="bcm-pick-list"></div>
            </div>

            <div class="bc-builder-preview-body" id="bcm-preview-body" style="display:none"></div>
          </div>

          <div class="bc-builder-options">
            <div>
              <div class="bc-opt-title"><i class="fa-solid fa-ruler-combined"></i> ${t('Paper Size')}</div>
              <div class="bc-paper-opts">
                <label class="bc-paper-opt">
                  <input type="radio" name="bcm-paper" value="single" checked>
                  <div class="bc-paper-opt-card"><i class="fa-solid fa-grip-lines" style="color:var(--accent)"></i> ${t('Single Row')}</div>
                </label>
                <label class="bc-paper-opt">
                  <input type="radio" name="bcm-paper" value="double">
                  <div class="bc-paper-opt-card"><i class="fa-solid fa-table-list" style="color:var(--accent)"></i> ${t('Double Row')}</div>
                </label>
                <label class="bc-paper-opt">
                  <input type="radio" name="bcm-paper" value="custom">
                  <div class="bc-paper-opt-card"><i class="fa-solid fa-up-right-and-down-left-from-center" style="color:var(--accent)"></i> ${t('Custom Size')}</div>
                </label>
              </div>
              <div class="bc-custom-size" id="bcm-custom-size" style="display:none">
                <label>${t('Width (mm)')}<input type="number" id="bcm-custom-w" value="60" min="10" max="300"></label>
                <label>${t('Height (mm)')}<input type="number" id="bcm-custom-h" value="30" min="10" max="300"></label>
              </div>
            </div>

            <div>
              <div class="bc-opt-title"><i class="fa-solid fa-font"></i> ${t('Custom Text')}</div>
              <div class="bc-text-row"><label><i class="fa-solid fa-arrow-up"></i> ${t('Top')}</label><input type="text" id="bcm-top-text" maxlength="60" placeholder="${t('e.g. Store name…')}"></div>
              <div class="bc-text-row"><label><i class="fa-solid fa-arrow-down"></i> ${t('Bottom')}</label><input type="text" id="bcm-bottom-text" maxlength="60" placeholder="${t('e.g. Phone, website…')}"></div>
            </div>
          </div>
        </div>

        <div class="bc-builder-foot">
          <div class="bc-builder-foot-info" id="bcm-foot-info"><i class="fa-solid fa-tags"></i> <span>${t('No labels added')}</span></div>
          <div class="bc-builder-foot-actions">
            <label style="font-size:11px;color:var(--muted);font-weight:600">${t('Copies')}</label>
            <input type="number" id="bcm-copies" class="bc-copies-inp" value="1" min="1" max="100">
            <button class="salm-btn-ghost" id="bcm-print-sheet" type="button" disabled><i class="fa-solid fa-print"></i> ${t('Print Sheet')}</button>
            <button class="salm-btn-primary" id="bcm-add-to-print" type="button" disabled><i class="fa-solid fa-layer-group"></i> ${t('Add to Print')}</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);

    function close() { _pick.reqId++; el.remove(); }
    document.getElementById('bcm-builder-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });

    el.querySelectorAll('.bc-view-tab').forEach((tab) => tab.addEventListener('click', () => setBuilderView(tab.dataset.view)));

    // search filters the already-loaded catalog
    const searchInp = document.getElementById('bcm-search-inp');
    const runFilter = debounce((q) => { setBuilderView('products'); pickFilter(q); }, 150);
    searchInp.addEventListener('input', () => runFilter(searchInp.value.trim()));
    setTimeout(() => searchInp.focus(), 60);

    wirePicker();
    pickLoad();

    // paper size + text → live preview
    el.querySelectorAll('input[name="bcm-paper"]').forEach((r) => {
      r.addEventListener('change', () => {
        document.getElementById('bcm-custom-size').style.display = r.value === 'custom' && r.checked ? 'flex' : 'none';
        refreshBuilderPreview();
      });
    });
    document.getElementById('bcm-custom-w').addEventListener('input', refreshBuilderPreview);
    document.getElementById('bcm-custom-h').addEventListener('input', refreshBuilderPreview);
    document.getElementById('bcm-top-text').addEventListener('input', refreshBuilderPreview);
    document.getElementById('bcm-bottom-text').addEventListener('input', refreshBuilderPreview);

    document.getElementById('bcm-print-sheet').addEventListener('click', () => {
      if (!_bc.items.length) return;
      const paper = document.querySelector('input[name="bcm-paper"]:checked')?.value || 'single';
      const cols = paper === 'double' ? 2 : 1;
      const topText = document.getElementById('bcm-top-text').value.trim();
      const bottomText = document.getElementById('bcm-bottom-text').value.trim();
      openSheetJob(_bc.items.map((it) => ({ ...it, topText, bottomText })), { cols, rowsPerPage: null, mmW: null, mmH: null });
    });

    const addToPrintBtn = document.getElementById('bcm-add-to-print');
    addToPrintBtn.addEventListener('click', () => {
      if (!_bc.items.length) return;
      busy(addToPrintBtn, t('Adding to print…'), () => {
        const products = addBuilderItemsToQueue();
        close();
        refreshQueue();
        showToast(t('{n} products added to the print list', { n: fmtNum(products) }), 'success');
      });
    });

    refreshBuilderPreview();
  }

  // ── Dialog shell: build the DOM, wire close handlers, show it ──────────
  function openBarcodesModal() {
    if (document.getElementById('bcm-modal-backdrop')) return;
    const returnFocusTo = document.activeElement;

    const el = document.createElement('div');
    el.className = 'salm-modal-backdrop';
    el.id = 'bcm-modal-backdrop';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'bcm-modal-title');
    el.innerHTML = `
      <div class="salm-modal-card salm-modal-card--wide">
        <div class="salm-modal-head">
          <span class="salm-modal-head-icon"><i class="fa-solid fa-barcode"></i></span>
          <div class="salm-modal-head-text">
            <h2 id="bcm-modal-title">${t('Barcode Generator')}</h2>
            <p>${t('Generate and print barcode labels for products.')}</p>
          </div>
          <button class="salm-modal-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="salm-modal-body">
          <div class="bc-toolbar">
            <div class="bc-toolbar-left" id="bcm-queue-badge"></div>
            <div class="bc-toolbar-right">
              <button class="salm-icon-btn bc-danger-btn" id="bcm-clear-all" type="button" disabled><i class="fa-solid fa-broom"></i> ${t('Clear All')}</button>
              <button class="salm-icon-btn" id="bcm-print-all" type="button" disabled><i class="fa-solid fa-print"></i> ${t('Print All')}</button>
              <button class="salm-btn-primary" id="bcm-generate" type="button"><i class="fa-solid fa-barcode"></i> ${t('Generate Barcode')}</button>
            </div>
          </div>
          <div class="bc-print-settings" id="bcm-print-settings"></div>
          <div class="bc-sel-bar" id="bcm-sel-bar" style="display:none">
            <label class="bc-sel-all"><input type="checkbox" id="bcm-sel-all"> <span>${t('Select all')}</span></label>
            <span class="bc-sel-count" id="bcm-sel-count"></span>
            <button class="salm-icon-btn bc-danger-btn" id="bcm-sel-remove" type="button" disabled><i class="fa-solid fa-trash"></i> ${t('Remove Selected')}</button>
          </div>
          <div id="bcm-queue-content"></div>
          <div class="bc-footer-stats">
            <div class="bc-tf-stats">
              <div class="bc-tf-stat"><i class="fa-solid fa-layer-group"></i> <span class="bc-tf-val" id="bcm-tf-batches">0</span> ${t('Batches')}</div>
              <div class="bc-tf-stat"><i class="fa-solid fa-tags"></i> <span class="bc-tf-val" id="bcm-tf-labels">0</span> ${t('Labels Total')}</div>
              <div class="bc-tf-stat"><i class="fa-solid fa-box"></i> <span class="bc-tf-val" id="bcm-tf-products">0</span> ${t('Products')}</div>
              <div class="bc-tf-stat"><i class="fa-solid fa-print"></i> <span class="bc-tf-val" id="bcm-tf-pages">0</span> ${t('Print Pages')}</div>
            </div>
            <div class="bc-tf-status" id="bcm-tf-status"><i class="fa-solid fa-circle-dot"></i> ${t('Queue empty')}</div>
          </div>
        </div>
      </div>
      <div class="salm-toast" id="bcm-toast"></div>`;
    document.body.appendChild(el);

    queueContentEl = document.getElementById('bcm-queue-content');
    toastEl = document.getElementById('bcm-toast');

    function close() {
      document.removeEventListener('keydown', onKey, true);
      el.classList.remove('open');
      svgCache.clear();
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }
    function onKey(e) {
      if (e.key !== 'Escape') return;
      // Esc closes the top-most layer first (sheet job → stock layers → builder → dialog)
      const job = document.getElementById('bcm-job');
      if (job) { e.preventDefault(); job.querySelector('#bcm-job-x')?.click(); return; }
      if (document.getElementById('bcm-layers-backdrop')) { e.preventDefault(); document.getElementById('bcm-layers-backdrop').remove(); return; }
      if (document.getElementById('bcm-builder-backdrop')) { e.preventDefault(); document.getElementById('bcm-builder-close').click(); return; }
      e.preventDefault();
      close();
    }

    el.querySelector('.salm-modal-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    document.getElementById('bcm-generate').addEventListener('click', openBuilderModal);
    document.getElementById('bcm-print-all').addEventListener('click', () => {
      if (!_bc.queue.length) return;
      openSheetJob(_bc.queue.flatMap((b) => b.items), printConfig());
    });
    document.getElementById('bcm-clear-all').addEventListener('click', () => {
      const n = _bc.queue.length;
      if (!n || !confirm(t('Remove all {n} products from the print list?', { n: fmtNum(n) }))) return;
      _bc.queue.length = 0;
      _bc.selected.clear();
      refreshQueue();
    });
    document.getElementById('bcm-sel-all').addEventListener('change', (e) => {
      _bc.selected.clear();
      if (e.target.checked) _bc.queue.forEach((b) => _bc.selected.add(b));
      refreshQueue();
    });
    document.getElementById('bcm-sel-remove').addEventListener('click', () => {
      const n = _bc.selected.size;
      if (!n || !confirm(t('Remove {n} selected products from the print list?', { n: fmtNum(n) }))) return;
      _bc.queue = _bc.queue.filter((b) => !_bc.selected.has(b));
      _bc.selected.clear();
      refreshQueue();
    });

    requestAnimationFrame(() => el.classList.add('open'));
    renderPrintSettings();
    refreshQueue();
    loadSettings();
  }

  window.openBarcodesModal = openBarcodesModal;
})();
