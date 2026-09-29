'use strict';

// Barcodes — an in-page modal dialog, opened by calling window.openBarcodesModal()
// (see the dashboard's "Barcodes" tile in js/dashboard.js). Mirrors js/stock.js:
// builds its own DOM on open, tears it down on close, reuses the .salm-* /
// .qf-* CSS shell from css/sales.css plus the .bc-* rules added alongside it,
// and talks to the same Laravel POS API (Modules/Pos/routes/api.php, prefix
// /api/v1/pos) via js/api.js — the existing product search + stock-history
// endpoints already used by js/products.js and js/stock.js. Barcode labels
// are generated entirely client-side with JsBarcode (js/jsbarcode.min.js),
// so no new endpoint is needed for this feature itself.
//
// Modelled on electron_app's "Barcode Generator" (Inventory → Barcodes):
// a print-queue tab (this dialog's home view) plus a "Generate Barcode"
// builder overlay — search a product, pick a stock batch, add labels, choose
// paper size + custom text, queue the batch, then print.
(function () {
  let toastEl, queueContentEl;
  let posSettings = {};
  let settingsLoaded = false;

  // Current label builder (cleared each time the builder overlay opens) and
  // the queue of batches added to print (cleared only when the dialog closes).
  const _bc = { items: [], queue: [] };

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

  function debounce(fn, ms) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
  }

  function showToast(message, type = '') {
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

  // ── Barcode rendering (JsBarcode → detached SVG → markup string) ───────
  function renderBarcodeSvg(code, heightPx) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    if (!code) return '<div style="font-size:9px;color:#dc2626;text-align:center;padding:4px 0">No SKU</div>';
    try {
      JsBarcode(svg, code, { format: 'CODE128', width: 1.8, height: heightPx || 46, displayValue: false, margin: 4 });
      return svg.outerHTML;
    } catch (_) {
      return `<div style="font-size:10px;text-align:center;letter-spacing:2px;font-family:monospace;padding:6px 0">${esc(code)}</div>`;
    }
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

  // ── Print ────────────────────────────────────────────────────────────
  function printLabels(items, cfg) {
    if (!items.length) return;
    const cols = cfg.cols || 1;
    const sheetHtml = items.map((it) => labelMarkup({ ...it, _h: cfg.mmH ? Math.max(24, Math.round(cfg.mmH * 1.7)) : 46 })).join('');
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${esc(t('Barcode Labels'))}</title>
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
</style></head><body><div class="bc-print-sheet">${sheetHtml}</div></body></html>`;
    window.electronAPI.printHtml(html);
  }

  // ── Home view: the print queue ──────────────────────────────────────
  function refreshQueue() {
    const batchCount = _bc.queue.length;
    const totalLabels = _bc.queue.reduce((s, b) => s + b.items.length, 0);
    const uniqueProducts = new Set(_bc.queue.map((b) => b.productName)).size;
    const cfg = printConfig();
    const labelsPerPage = cfg.rowsPerPage ? cfg.cols * cfg.rowsPerPage : cfg.cols * 10;
    const estPages = totalLabels ? Math.ceil(totalLabels / labelsPerPage) : 0;

    const badgeEl = document.getElementById('bcm-queue-badge');
    if (badgeEl) badgeEl.innerHTML = totalLabels ? `<span class="bc-queue-badge">${totalLabels}</span>` : '';
    const printAllBtn = document.getElementById('bcm-print-all');
    if (printAllBtn) printAllBtn.disabled = !batchCount;

    document.getElementById('bcm-tf-batches').textContent = batchCount;
    document.getElementById('bcm-tf-labels').textContent = totalLabels;
    document.getElementById('bcm-tf-products').textContent = uniqueProducts;
    document.getElementById('bcm-tf-pages').textContent = estPages;
    const statusEl = document.getElementById('bcm-tf-status');
    statusEl.className = `bc-tf-status${batchCount ? ' ready' : ''}`;
    statusEl.innerHTML = batchCount
      ? `<i class="fa-solid fa-circle-check"></i> ${t('{n} labels ready to print', { n: totalLabels })}`
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
      return `<div class="bc-batch-card">
        <div class="bc-batch-card-icon"><i class="fa-solid fa-barcode"></i></div>
        <div class="bc-batch-card-info">
          <div class="bc-batch-card-name">${esc(batch.productName)}</div>
          <div class="bc-batch-card-meta">
            <span class="bc-queue-badge">${t('{n} labels', { n: batch.items.length })}</span>
            ${batch.topText ? `<span class="bc-batch-meta-tag"><i class="fa-solid fa-arrow-up"></i> ${esc(batch.topText)}</span>` : ''}
            ${batch.bottomText ? `<span class="bc-batch-meta-tag"><i class="fa-solid fa-arrow-down"></i> ${esc(batch.bottomText)}</span>` : ''}
            <span class="bc-batch-meta-tag"><i class="fa-solid fa-ruler-combined"></i> ${esc(paperLabel)}</span>
          </div>
        </div>
        <div class="bc-batch-card-actions">
          <button class="salm-icon-btn" data-print="${i}" type="button"><i class="fa-solid fa-print"></i> ${t('Print')}</button>
          <button class="salm-icon-btn" data-del="${i}" type="button"><i class="fa-solid fa-trash"></i></button>
        </div>
      </div>`;
    }).join('');

    queueContentEl.querySelectorAll('button[data-del]').forEach((btn) => {
      btn.addEventListener('click', () => { _bc.queue.splice(Number(btn.dataset.del), 1); refreshQueue(); });
    });
    queueContentEl.querySelectorAll('button[data-print]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const batch = _bc.queue[Number(btn.dataset.print)];
        if (batch) printLabels(batch.items, printConfig());
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
      });
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

  function refreshBuilderPreview() {
    const body = document.getElementById('bcm-preview-body');
    const countEl = document.getElementById('bcm-preview-count');
    const footInfo = document.getElementById('bcm-foot-info');
    const addBtn = document.getElementById('bcm-add-to-print');
    const printBtn = document.getElementById('bcm-print-sheet');
    if (!body) return;
    const n = _bc.items.length;
    if (countEl) countEl.textContent = n ? String(n) : '';
    if (footInfo) footInfo.textContent = n ? t('{n} labels added', { n }) : t('No labels added');
    if (addBtn) addBtn.disabled = !n;
    if (printBtn) printBtn.disabled = !n;

    if (!n) {
      body.innerHTML = `<div class="bc-preview-ph"><i class="fa-solid fa-barcode"></i><span>${t('Search for a product above, then add barcode labels from its stock layers')}</span></div>`;
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
  }

  function openBuilderModal() {
    if (document.getElementById('bcm-builder-backdrop')) return;
    _bc.items = [];

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
            <p>${t('Search a product to add barcode labels')}</p>
          </div>
          <button class="salm-modal-close" id="bcm-builder-close" type="button" aria-label="${t('Close')}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="bc-builder-search-wrap">
          <div class="bc-builder-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="bcm-search-inp" placeholder="${t('Search product by name or SKU…')}" autocomplete="off"></div>
          <div class="bc-builder-results" id="bcm-search-results"></div>
        </div>

        <div class="bc-builder-body">
          <div class="bc-builder-preview">
            <div class="bc-builder-preview-head">
              <span class="bc-builder-preview-title"><i class="fa-solid fa-eye"></i> ${t('Barcode Preview')} <span id="bcm-preview-count" style="color:var(--accent);font-weight:800"></span></span>
            </div>
            <div class="bc-builder-preview-body" id="bcm-preview-body">
              <div class="bc-preview-ph"><i class="fa-solid fa-barcode"></i><span>${t('Search for a product above, then add barcode labels from its stock layers')}</span></div>
            </div>
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

    function close() { el.remove(); }
    document.getElementById('bcm-builder-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });

    // product search
    const searchInp = document.getElementById('bcm-search-inp');
    const resultsEl = document.getElementById('bcm-search-results');
    const runSearch = debounce(async (q) => {
      if (!q) { resultsEl.classList.remove('show'); resultsEl.innerHTML = ''; return; }
      resultsEl.classList.add('show');
      resultsEl.innerHTML = `<div class="salm-loading">${t('Loading…')}</div>`;
      const res = await API.products(q);
      if (res.status !== 200) { resultsEl.innerHTML = `<div class="salm-empty">${t('Could not load products.')}</div>`; return; }
      const list = res.body?.data || [];
      if (!list.length) { resultsEl.innerHTML = `<div class="salm-empty">${t('No products found.')}</div>`; return; }
      resultsEl.innerHTML = list.map((p) => {
        const outOfStock = Number(p.stock_quantity) <= 0;
        const stockBadge = outOfStock
          ? `<span class="qf-picker-badge red">${t('Out of stock')}</span>`
          : `<span class="qf-picker-badge green">${t('{n} in stock', { n: Math.floor(p.stock_quantity) })}</span>`;
        const thumb = p.image_url ? `<img src="${esc(p.image_url)}" alt="">` : '<i class="fa-solid fa-box"></i>';
        return `<div class="qf-picker-row" data-id="${p.id}">
          <div class="qf-picker-thumb">${thumb}</div>
          <div class="qf-picker-info">
            <div class="qf-picker-name">${esc(p.name)}</div>
            <div class="qf-picker-sub">${p.sku ? esc(p.sku) + ' · ' : ''}${stockBadge}</div>
          </div>
        </div>`;
      }).join('');
      resultsEl.querySelectorAll('.qf-picker-row').forEach((row) => {
        row.addEventListener('click', () => {
          const p = list.find((x) => String(x.id) === row.dataset.id);
          if (!p) return;
          resultsEl.classList.remove('show');
          searchInp.value = '';
          openStockLayersPopup(p);
        });
      });
    }, 300);
    searchInp.addEventListener('input', () => runSearch(searchInp.value.trim()));
    setTimeout(() => searchInp.focus(), 60);

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
      printLabels(_bc.items.map((it) => ({ ...it, topText, bottomText })), { cols });
    });

    document.getElementById('bcm-add-to-print').addEventListener('click', () => {
      if (!_bc.items.length) return;
      const copies = Math.max(1, Math.min(100, parseInt(document.getElementById('bcm-copies').value) || 1));
      const topText = document.getElementById('bcm-top-text').value.trim();
      const bottomText = document.getElementById('bcm-bottom-text').value.trim();
      const paper = document.querySelector('input[name="bcm-paper"]:checked')?.value || 'single';
      const customW = parseInt(document.getElementById('bcm-custom-w').value) || 60;
      const customH = parseInt(document.getElementById('bcm-custom-h').value) || 30;
      const batchItems = [];
      for (let c = 0; c < copies; c++) {
        _bc.items.forEach((it) => batchItems.push({ ...it, topText, bottomText }));
      }
      _bc.queue.push({ items: batchItems, topText, bottomText, paper, customW, customH, productName: _bc.items[0]?.name || t('Labels') });
      close();
      refreshQueue();
      showToast(t('Added to print queue.'), 'success');
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
              <button class="salm-icon-btn" id="bcm-print-all" type="button" disabled><i class="fa-solid fa-print"></i> ${t('Print All')}</button>
              <button class="salm-btn-primary" id="bcm-generate" type="button"><i class="fa-solid fa-barcode"></i> ${t('Generate Barcode')}</button>
            </div>
          </div>
          <div class="bc-print-settings" id="bcm-print-settings"></div>
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
      setTimeout(() => el.remove(), 200);
      if (returnFocusTo && returnFocusTo.focus) returnFocusTo.focus();
    }
    function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); close(); } }

    el.querySelector('.salm-modal-close').addEventListener('click', close);
    el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
    document.addEventListener('keydown', onKey, true);

    document.getElementById('bcm-generate').addEventListener('click', openBuilderModal);
    document.getElementById('bcm-print-all').addEventListener('click', () => {
      const allItems = _bc.queue.flatMap((b) => b.items);
      printLabels(allItems, printConfig());
    });

    requestAnimationFrame(() => el.classList.add('open'));
    renderPrintSettings();
    refreshQueue();
    loadSettings();
  }

  window.openBarcodesModal = openBarcodesModal;
})();
