/*
 * Zeebroo POS — browser shim
 * ---------------------------------------------------------------------------
 * Lets the desktop renderer (electron_app/renderer/*.html) run in a normal web
 * browser. Laravel's /pos-app route serves those pages and injects this script
 * at the top of <head>. It provides a browser version of window.electronAPI
 * (normally exposed by electron_app/preload.js) built on fetch(), localStorage,
 * <input type="file">, browser downloads and the browser print dialog.
 *
 * It is NOT loaded by the Electron app, POS Lite or the mobile apps, and it
 * does nothing when a real window.electronAPI already exists.
 */
(function () {
  'use strict';

  if (window.electronAPI) return;

  // ── Settings passed by the Laravel route (data-* attributes on this tag) ──
  var script      = document.currentScript;
  var API_BASE    = ((script && script.dataset.apiBase) || (location.origin + '/api/v1/pos')).replace(/\/$/, '');
  var APP_VERSION = (script && script.dataset.appVersion) || '0.0.0';
  var WEB_LOGOUT_URL = (script && script.dataset.webLogoutUrl) || '';
  var CSRF_TOKEN  = (script && script.dataset.csrf) || '';
  var API_ORIGIN  = new URL(API_BASE, location.href).origin;

  var CONFIG_KEY  = 'zb_web_config';
  var PRINTER_KEY = 'zb_web_printer';
  var CHILD_KEY   = 'zb_web_child_';
  var DEFAULT_CONFIG = { device_name: 'pos-web-1', token: null, business_id: null, branch_id: null, dark_mode: false };
  var PREVIEW_MAX_BYTES = 25 * 1024 * 1024;

  // ── Small helpers ─────────────────────────────────────────────────────────
  function readJson(key, fallback) {
    try { var v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch (_) { return fallback; }
  }
  function writeJson(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch (_) { return false; }
  }
  function readConfig() { return Object.assign({}, DEFAULT_CONFIG, readJson(CONFIG_KEY, {})); }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var b = new Uint8Array(16);
    crypto.getRandomValues(b);
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    var h = Array.prototype.map.call(b, function (x) { return (x + 0x100).toString(16).slice(1); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }

  function notify(message, type) {
    if (typeof window.toast === 'function') { try { window.toast(message, type || 'warning'); return; } catch (_) {} }
    try { window.alert(message); } catch (_) {}
  }

  // Event bus — stands in for ipcRenderer.on(...) channels.
  var listeners = {};
  function on(channel, cb) {
    (listeners[channel] = listeners[channel] || []).push(cb);
    return function off() {
      listeners[channel] = (listeners[channel] || []).filter(function (fn) { return fn !== cb; });
    };
  }
  function emit(channel, payload) {
    (listeners[channel] || []).slice().forEach(function (fn) { try { fn(payload); } catch (e) { console.error(e); } });
  }

  // ── HTTP (same headers the Electron main process sends) ───────────────────
  function authHeaders(extra) {
    var c = readConfig();
    var h = Object.assign({ Accept: 'application/json' }, extra || {});
    if (c.token)       h['Authorization'] = 'Bearer ' + c.token;
    if (c.business_id) h['X-Business-Id'] = String(c.business_id);
    if (c.branch_id)   h['X-Branch-Id']   = String(c.branch_id);
    return h;
  }

  async function parseBody(res) {
    var text = await res.text();
    try { return JSON.parse(text); } catch (_) { return text; }
  }

  function withTimeout(ms) {
    var ctrl = new AbortController();
    var t = setTimeout(function () { ctrl.abort(); }, ms);
    return { signal: ctrl.signal, done: function () { clearTimeout(t); } };
  }

  function netError(err, timeoutMs) {
    if (err && err.name === 'AbortError') return 'Request timed out after ' + Math.round(timeoutMs / 1000) + 's';
    return (err && err.message) || 'Network error';
  }

  async function jsonRequest(method, path, body, timeoutMs) {
    timeoutMs = timeoutMs || 120000;
    method = String(method || 'GET').toUpperCase();
    var t = withTimeout(timeoutMs);
    try {
      var init = {
        method: method,
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        signal: t.signal,
        credentials: 'omit',
      };
      if (body && method !== 'GET' && method !== 'HEAD') init.body = JSON.stringify(body);
      var res = await fetch(API_BASE + path, init);
      return { status: res.status, body: await parseBody(res) };
    } catch (err) {
      return { status: 0, body: { message: netError(err, timeoutMs) } };
    } finally {
      t.done();
    }
  }

  // Offline-only keys the desktop strips before sending a sale (electron_app/offline.js).
  var OFFLINE_ONLY_BODY_KEYS = ['offline_summary'];
  var OFFLINE_ONLY_ITEM_KEYS = ['offline_unit_price', 'offline_name'];

  function apiRequest(method, path, body) {
    var m = String(method || 'GET').toUpperCase();
    var pathname = String(path || '').split('?')[0];
    if (m === 'POST' && pathname === '/online/checkout') {
      var out = Object.assign({}, body || {});
      out.client_uuid = out.client_uuid || uuid();
      OFFLINE_ONLY_BODY_KEYS.forEach(function (k) { delete out[k]; });
      out.items = (out.items || []).map(function (i) {
        var copy = Object.assign({}, i);
        OFFLINE_ONLY_ITEM_KEYS.forEach(function (k) { delete copy[k]; });
        return copy;
      });
      return jsonRequest(m, path, out, 30000);
    }
    return jsonRequest(m, path, body);
  }

  async function fetchBinary(path, accept) {
    var res = await fetch(API_BASE + path, { headers: authHeaders({ Accept: accept || 'application/pdf' }), credentials: 'omit' });
    return { status: res.status, res: res };
  }

  async function errorMessage(res, fallback) {
    try { var b = await parseBody(res); return (b && b.message) || fallback; } catch (_) { return fallback; }
  }

  function saveBlob(blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'download';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
  }

  var EXT_MIME = {
    jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp',
    svg: 'image/svg+xml', pdf: 'application/pdf', txt: 'text/plain', csv: 'text/csv', json: 'application/json',
  };
  function extMime(p) {
    var ext = String(p || '').split('?')[0].split('.').pop().toLowerCase();
    return EXT_MIME[ext] || 'application/octet-stream';
  }

  function blobToDataUrl(blob) {
    return new Promise(function (resolve, reject) {
      var r = new FileReader();
      r.onload = function () { resolve(r.result); };
      r.onerror = function () { reject(r.error); };
      r.readAsDataURL(blob);
    });
  }

  // ── Local files: browsers have no file paths, so picked / dropped files are
  // kept here and handed to the app as "browser-file://<n>/<name>" strings. The
  // app only splits these for the name / extension and passes them back to
  // apiUpload / readTextFile / backupUpload, which look the File up again.
  var files = new Map();
  var fileSeq = 0;
  function registerFile(file) {
    var id = 'browser-file://' + (++fileSeq) + '/' + file.name;
    files.set(id, file);
    return id;
  }
  function fileFor(p) { return files.get(p) || null; }

  function acceptFromFilters(filters) {
    var exts = [];
    (filters || []).forEach(function (f) {
      (f.extensions || []).forEach(function (e) { if (e && e !== '*' && exts.indexOf(e) === -1) exts.push(e); });
    });
    var any = (filters || []).some(function (f) { return (f.extensions || []).indexOf('*') !== -1; });
    return any || !exts.length ? '' : exts.map(function (e) { return '.' + e; }).join(',');
  }

  function pickFiles(opts) {
    return new Promise(function (resolve) {
      var input = document.createElement('input');
      input.type = 'file';
      input.multiple = !!opts.multiple;
      if (opts.accept) input.accept = opts.accept;
      input.style.display = 'none';
      document.body.appendChild(input);
      var settled = false;
      function finish(list) {
        if (settled) return;
        settled = true;
        input.remove();
        resolve(list);
      }
      input.addEventListener('change', function () { finish(Array.prototype.slice.call(input.files || [])); });
      input.addEventListener('cancel', function () { finish([]); });
      input.click();
    });
  }

  async function showOpenDialog(options) {
    options = options || {};
    var props = options.properties || [];
    var list = await pickFiles({ multiple: props.indexOf('multiSelections') !== -1, accept: acceptFromFilters(options.filters) });
    return { canceled: !list.length, filePaths: list.map(registerFile) };
  }

  async function apiUpload(apiPath, filePath) {
    var file = fileFor(filePath);
    if (!file) return { status: 0, body: { message: 'File not found — please choose it again.' } };
    try {
      var fd = new FormData();
      fd.append('files[]', file, file.name);
      var res = await fetch(API_BASE + apiPath, { method: 'POST', headers: authHeaders(), body: fd, credentials: 'omit' });
      return { status: res.status, body: await parseBody(res) };
    } catch (err) {
      return { status: 0, body: { message: netError(err, 0) } };
    }
  }

  async function downloadFile(path, suggestedFilename) {
    try {
      var r = await fetchBinary(path, 'application/pdf');
      if (r.status !== 200) return { status: r.status, message: await errorMessage(r.res, 'Download failed (HTTP ' + r.status + ').') };
      var name = suggestedFilename || 'download.pdf';
      saveBlob(await r.res.blob(), name);
      return { status: 200, savedPath: name };
    } catch (err) {
      return { status: 0, message: netError(err, 0) };
    }
  }

  async function fetchDataUrl(path) {
    try {
      var r = await fetchBinary(path, 'application/pdf');
      if (r.status !== 200) return { status: r.status, message: 'Preview failed (HTTP ' + r.status + ').' };
      var blob = await r.res.blob();
      if (blob.size > PREVIEW_MAX_BYTES) return { status: 413, message: 'File is too large to preview.' };
      var mime = String(r.res.headers.get('content-type') || '').split(';')[0].trim();
      if (!mime || mime === 'application/octet-stream') mime = extMime(path);
      return { status: 200, dataUrl: await blobToDataUrl(new Blob([blob], { type: mime })) };
    } catch (err) {
      return { status: 0, message: netError(err, 0) };
    }
  }

  async function fetchJson(url) {
    try {
      var res = await fetch(url, { headers: { Accept: 'application/json' } });
      return { status: res.status, body: await parseBody(res) };
    } catch (err) {
      return { status: 0, body: { message: netError(err, 0) } };
    }
  }

  // ── Printing (browser print dialog in a hidden iframe) ────────────────────
  var printFrame = null;
  function printDocument(html, title) {
    return new Promise(function (resolve) {
      if (printFrame) printFrame.remove();
      var iframe = document.createElement('iframe');
      iframe.setAttribute('aria-hidden', 'true');
      iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:1000px;height:1400px;border:0';
      document.body.appendChild(iframe);
      printFrame = iframe;

      var doc = iframe.contentDocument;
      doc.open();
      doc.write(html);
      doc.close();
      if (title) doc.title = title;

      var started = false;
      function go() {
        if (started) return;
        started = true;
        try {
          if (typeof iframe.__beforePrint === 'function') iframe.__beforePrint(doc);
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
          resolve({ success: true });
        } catch (err) {
          resolve({ success: false, error: (err && err.message) || 'Print failed' });
        }
      }
      // Wait for images (logos, letterheads) before opening the dialog.
      var imgs = Array.prototype.slice.call(doc.images || []);
      var pending = imgs.filter(function (img) { return !img.complete; });
      if (!pending.length) { setTimeout(go, 50); return; }
      var left = pending.length;
      pending.forEach(function (img) {
        var done = function () { if (--left <= 0) go(); };
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
      });
      setTimeout(go, 4000);
    });
  }

  // Receipt CSS — mirrors _thermalHtml(..., { leftAlign: true }) in electron_app/main.js,
  // i.e. the direct-print layout the desktop app sends to the thermal driver.
  // No custom @page size: thermal drivers (e.g. EPSON TM-T81III) reject a custom
  // paper length, so the browser centres/scales the page on the driver's paper.
  // The receipt is pinned to the left edge so it sits under the print head on any
  // page width, and @page margin 0 leaves no room for browser headers/footers.
  var THERMAL_HEAD_MM = { 80: 72, 58: 48 };
  function receiptMargins(m) {
    m = m || {};
    function v(x) { return Math.min(20, Math.max(0, parseFloat(x) || 0)); }
    return { top: v(m.top), right: v(m.right), bottom: v(m.bottom), left: v(m.left) };
  }
  function receiptHtml(html, paperWidth, margins) {
    var paperMm = paperWidth === 58 ? 58 : 80;
    var contentMm = THERMAL_HEAD_MM[paperMm];
    var mg = receiptMargins(margins);
    // Top/bottom margins are spacers ending in a 0.5 mm dot so the driver's
    // "Paper Reduction" doesn't trim them away as blank paper.
    var top = mg.top ? '<div class="mg-spacer top" style="height:' + mg.top + 'mm"></div>' : '';
    var bottom = mg.bottom ? '<div class="mg-spacer bottom" style="height:' + mg.bottom + 'mm"></div>' : '';
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt</title><style>' +
      '*{margin:0;padding:0;box-sizing:border-box}' +
      'html{width:100%;background:#fff}' +
      'body{width:100%;margin:0;max-width:' + (contentMm + mg.left) + 'mm;padding:0 ' + mg.right + 'mm 0 ' + mg.left + 'mm;background:#fff;color:#000;font-family:"Courier New",Courier,monospace;font-size:10pt;line-height:1.45;overflow-wrap:anywhere}' +
      '@page{margin:0}' +
      '.rcpt-paper{width:100%}' +
      '.rcpt-logo-wrap{text-align:center;margin-bottom:8px}' +
      '.rcpt-logo-img{max-width:min(100%, calc(100px * var(--rcpt-logo-scale, 1)));max-height:calc(48px * var(--rcpt-logo-scale, 1));object-fit:contain;display:block;margin:0 auto}' +
      '.rcpt-biz-name{text-align:center;font-size:12pt;font-weight:700;margin-bottom:2px}' +
      '.rcpt-biz-sub{text-align:center;font-size:8.5pt;color:#555;margin-bottom:8px}' +
      '.rcpt-divider{border:none;border-top:1px dashed #999;margin:6px 0}' +
      '.rcpt-divider-solid{border:none;border-top:1px solid #555;margin:6px 0}' +
      '.rcpt-meta{display:flex;justify-content:space-between;gap:6px;font-size:8.5pt;color:#333;margin:1px 0}' +
      '.rcpt-meta>*:last-child,.rcpt-customer>*:last-child,.rcpt-total-row>*:last-child{text-align:right;min-width:0}' +
      '.rcpt-items-header{display:flex;font-size:8pt;font-weight:700;text-transform:uppercase;color:#333;padding:3px 0;border-top:1px dashed #aaa;border-bottom:1px dashed #aaa;margin:4px 0}' +
      '.rcpt-items-header .ih-name{flex:1;min-width:0}' +
      '.rcpt-items-header .ih-qty{width:28px;text-align:right;flex-shrink:0}' +
      '.rcpt-items-header .ih-price{width:52px;text-align:right;flex-shrink:0}' +
      '.rcpt-items-header .ih-total{width:58px;text-align:right;flex-shrink:0}' +
      '.rcpt-item{display:flex;align-items:center;padding:2px 0;border-bottom:1px dotted #ccc}' +
      '.rcpt-item:last-child{border-bottom:none}' +
      '.rcpt-item .ri-name{flex:1;font-size:9pt;padding-right:4px;min-width:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}' +
      '.rcpt-item .ri-qty{width:28px;text-align:right;font-size:8.5pt;flex-shrink:0}' +
      '.rcpt-item .ri-price{width:52px;text-align:right;font-size:8.5pt;flex-shrink:0}' +
      '.rcpt-item .ri-total{width:58px;text-align:right;font-size:9pt;font-weight:600;flex-shrink:0}' +
      '.rcpt-totals{margin-top:4px}' +
      '.rcpt-total-row{display:flex;justify-content:space-between;padding:1px 0;font-size:9pt}' +
      '.rcpt-total-row.grand{font-size:11pt;font-weight:700;padding:4px 0 3px;border-top:1px solid #555}' +
      '.rcpt-total-row.change{color:#000;font-weight:700}' +
      '.rcpt-payment-badge{display:inline-block;padding:1px 8px;border:1px solid #333;font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.04em}' +
      '.rcpt-footer{text-align:center;font-size:8pt;color:#555;margin-top:10px;line-height:1.5}' +
      '.rcpt-thank{text-align:center;font-size:10pt;font-weight:700;margin:8px 0 3px}' +
      '.rcpt-note{font-size:8.5pt;color:#444;margin:4px 0;font-style:italic;white-space:pre-wrap}' +
      '.rcpt-customer{display:flex;justify-content:space-between;gap:6px;font-size:8.5pt;color:#333;margin:1px 0}' +
      '.rcpt-item-disc{display:flex;font-size:8pt;font-style:italic;padding:0 0 2px 8px}' +
      '.rcpt-item-disc .rid-label{flex:1}' +
      '.rcpt-item-disc .rid-per{width:52px;text-align:right}' +
      '.rcpt-item-disc .rid-total{width:58px;text-align:right}' +
      (paperMm === 58 ? 'html,body{font-size:9pt}.ih-price,.ri-price,.rid-per{display:none}.ih-qty,.ri-qty{width:22px}' : '') +
      '.mg-spacer{position:relative}' +
      '.mg-spacer::after{content:"";position:absolute;left:50%;width:0.5mm;height:0;border-top:0.5mm solid #000}' +
      '.mg-spacer.top::after{top:0}' +
      '.mg-spacer.bottom::after{bottom:0}' +
      '</style></head><body>' + top + (html || '') + bottom + '</body></html>';
  }

  function printReceiptHtml(opts) {
    opts = opts || {};
    return printDocument(receiptHtml(opts.html, opts.paperWidth, opts.margins), 'Receipt');
  }

  function printerConfig() {
    var tp = readJson(PRINTER_KEY, {});
    return {
      name:        tp.name || '',
      silent:      false,
      paperWidth:  tp.paperWidth === 58 ? 58 : 80,
      mode:        'html', // ESC/POS raw printing needs the desktop app
      showPreview: tp.showPreview !== false,
    };
  }

  async function saveHtmlPdf(opts) {
    opts = opts || {};
    emit('save-html-pdf-progress', 'loading');
    var html = String(opts.html || '');
    var pageCss = '<style>@page{size:A4 ' + (opts.landscape ? 'landscape' : 'portrait') + ';margin:11mm 11mm 14mm 11mm}</style>';
    html = /<head[^>]*>/i.test(html) ? html.replace(/<head[^>]*>/i, function (m) { return m + pageCss; }) : pageCss + html;
    emit('save-html-pdf-progress', 'rendering');
    var title = String(opts.filename || 'report.pdf').replace(/\.pdf$/i, '');
    emit('save-html-pdf-progress', 'saving-dialog');
    var res = await printDocument(html, title);
    return res.success ? { success: true, path: opts.filename || 'report.pdf' } : res;
  }

  // ── Fabric rendering (Design Studio / letterheads) ────────────────────────
  var fabricLoading = null;
  function loadFabric() {
    if (window.fabric) return Promise.resolve(window.fabric);
    if (fabricLoading) return fabricLoading;
    fabricLoading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = new URL('js/fabric.min.js', document.baseURI).href;
      s.onload = function () { resolve(window.fabric); };
      s.onerror = function () { fabricLoading = null; reject(new Error('Could not load fabric.js')); };
      document.head.appendChild(s);
    });
    return fabricLoading;
  }

  function renderFabric(canvasJson, pxW, pxH, zoom, timeoutMs) {
    return loadFabric().then(function (fabric) {
      return new Promise(function (resolve) {
        var settled = false;
        function finish(v) { if (!settled) { settled = true; resolve(v); } }
        setTimeout(function () { finish(null); }, timeoutMs);
        try {
          var raw  = canvasJson || {};
          var data = typeof raw === 'string' ? JSON.parse(raw) : raw;
          var el   = document.createElement('canvas');
          el.width = pxW;
          el.height = pxH;
          var c = new fabric.StaticCanvas(el, { width: pxW, height: pxH, enableRetinaScaling: false });
          c.loadFromJSON(data, function () {
            if (zoom && zoom !== 1) c.setZoom(zoom);
            c.setWidth(pxW);
            c.setHeight(pxH);
            c.renderAll();
            setTimeout(function () { finish({ canvas: el, data: data }); }, 150);
          });
        } catch (_) {
          finish(null);
        }
      });
    }).catch(function () { return null; });
  }

  async function renderCanvasToDataUrl(canvasJson, width, height, scale) {
    var w  = width || 794;
    var h  = height || 1123;
    var sc = Math.max(1, Math.min(4, parseFloat(scale) || 2));
    var r = await renderFabric(canvasJson, Math.round(w * sc), Math.round(h * sc), sc, 8000);
    if (!r) return null;
    try {
      var maxY = 0;
      (r.data.objects || []).forEach(function (o) {
        var bottom = (o.top || 0) + (o.height || 0) * (o.scaleY || 1);
        if (bottom > maxY) maxY = bottom;
      });
      return { dataUrl: r.canvas.toDataURL('image/png'), contentHeight: maxY };
    } catch (_) { return null; }
  }

  function canvasToJpeg(src, quality) {
    var t = document.createElement('canvas');
    t.width = src.width;
    t.height = src.height;
    var ctx = t.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, t.width, t.height);
    ctx.drawImage(src, 0, 0);
    return t.toDataURL('image/jpeg', quality || 0.92);
  }

  function base64ToBytes(b64) {
    var bin = atob(b64);
    var out = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
  }
  function bytesToBase64(bytes) {
    var bin = '';
    for (var i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    return btoa(bin);
  }

  // Minimal single-page PDF holding one JPEG — port of buildPdfFromJpeg() in main.js.
  function buildPdfFromJpeg(jpeg, imgW, imgH, pageWidthPt, pageHeightPt) {
    var pW = pageWidthPt.toFixed(2);
    var pH = pageHeightPt.toFixed(2);
    var cs = 'q ' + pW + ' 0 0 ' + pH + ' 0 0 cm /Im0 Do Q';
    var enc = new TextEncoder();
    var chunks = [];
    var off = {};
    var pos = 0;
    function wr(x) { var b = typeof x === 'string' ? enc.encode(x) : x; chunks.push(b); pos += b.length; }
    function obj(n, body) { off[n] = pos; wr(n + ' 0 obj\n' + body + '\nendobj\n'); }

    wr('%PDF-1.4\n');
    obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
    obj(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    obj(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pW + ' ' + pH + '] /Contents 4 0 R /Resources << /XObject << /Im0 5 0 R >> >> >>');
    obj(4, '<< /Length ' + cs.length + ' >>\nstream\n' + cs + '\nendstream');
    off[5] = pos;
    wr('5 0 obj\n<< /Type /XObject /Subtype /Image /Width ' + imgW + ' /Height ' + imgH + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpeg.length + ' >>\nstream\n');
    wr(jpeg);
    wr('\nendstream\nendobj\n');
    var xref = pos;
    wr('xref\n0 6\n');
    wr('0000000000 65535 f \n');
    [1, 2, 3, 4, 5].forEach(function (n) { wr(String(off[n]).padStart(10, '0') + ' 00000 n \n'); });
    wr('trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');

    var out = new Uint8Array(pos);
    var p = 0;
    chunks.forEach(function (c) { out.set(c, p); p += c.length; });
    return out;
  }

  async function renderDesignToPdf(canvasJson, width, height) {
    var w = width || 800;
    var h = height || 600;
    var r = await renderFabric(canvasJson, w, h, 1, 5000);
    if (!r) return null;
    try {
      var dataUrl = canvasToJpeg(r.canvas, 0.92);
      var jpeg = base64ToBytes(dataUrl.slice('data:image/jpeg;base64,'.length));
      return bytesToBase64(buildPdfFromJpeg(jpeg, w, h, w * 72 / 96, h * 72 / 96));
    } catch (_) { return null; }
  }

  // HTML → JPEG via an SVG <foreignObject>. Images inside must be data: URIs
  // (as the Design Studio invoice import already uses).
  async function renderHtmlToJpeg(html, width, height) {
    try {
      var doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
      doc.querySelectorAll('script').forEach(function (s) { s.remove(); });
      var xhtml = new XMLSerializer().serializeToString(doc.documentElement);
      var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '">' +
        '<foreignObject x="0" y="0" width="100%" height="100%">' + xhtml + '</foreignObject></svg>';
      var img = new Image();
      img.decoding = 'sync';
      await new Promise(function (resolve, reject) {
        img.onload = resolve;
        img.onerror = reject;
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
      });
      var c = document.createElement('canvas');
      c.width = width;
      c.height = height;
      c.getContext('2d').drawImage(img, 0, 0, width, height);
      return canvasToJpeg(c, 0.92).slice('data:image/jpeg;base64,'.length);
    } catch (_) {
      return null;
    }
  }

  // ── Child windows (print pages, Design Studio, Automation, Kitchen Display)
  // become browser tabs. Data goes over window.opener (no size limit), with a
  // localStorage copy so a reloaded tab still finds it.
  var CHILD_PAGES = {
    quote:      { page: 'print-quote.html',   refresh: 'quote-print-refresh' },
    invoice:    { page: 'print-invoice.html', refresh: 'invoice-print-refresh' },
    po:         { page: 'print-po.html',      refresh: 'po-print-refresh' },
    grn:        { page: 'print-grn.html',     refresh: 'grn-print-refresh' },
    editor:     { page: 'editor.html',        refresh: 'design-changed' },
    automation: { page: 'automation.html',    refresh: 'automation-flow-changed' },
    kds:        { page: 'kitchen.html',       refresh: null },
  };
  var childData = {};
  var childRefs = {};

  function openChild(kind, data) {
    var def = CHILD_PAGES[kind];
    childData[kind] = data === undefined ? null : data;
    writeJson(CHILD_KEY + kind, childData[kind]);

    var existing = childRefs[kind];
    if (existing && !existing.closed) {
      try { if (def.refresh && existing.__zbWebShim) existing.__zbWebShim.emit(def.refresh, childData[kind]); } catch (_) {}
      existing.focus();
      return Promise.resolve();
    }
    var w = window.open(new URL(def.page, document.baseURI).href, 'zeebroo-' + kind);
    if (!w) {
      notify('Your browser blocked the new window. Allow pop-ups for this site and try again.');
    } else {
      childRefs[kind] = w;
    }
    return Promise.resolve();
  }

  function readChildData(kind) {
    try {
      var shim = window.opener && window.opener.__zbWebShim;
      if (shim) return Promise.resolve(shim.childData(kind));
    } catch (_) {}
    return Promise.resolve(readJson(CHILD_KEY + kind, null));
  }

  // ── Fullscreen ────────────────────────────────────────────────────────────
  function setFullscreen(flag) {
    try {
      if (flag && !document.fullscreenElement) document.documentElement.requestFullscreen().catch(function () {});
      else if (!flag && document.fullscreenElement) document.exitFullscreen().catch(function () {});
    } catch (_) {}
  }

  // ── Clipboard ─────────────────────────────────────────────────────────────
  async function copyToClipboard(text) {
    text = String(text == null ? '' : text);
    try { await navigator.clipboard.writeText(text); return; } catch (_) {}
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px;top:0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (_) {}
    ta.remove();
  }

  // ── Database backup & import ──────────────────────────────────────────────
  async function backupDownload(path, suggestedFilename) {
    try {
      var res = await fetch(API_BASE + path, { headers: authHeaders({ Accept: 'application/zip, application/json' }), credentials: 'omit' });
      if (res.status !== 200) return { status: res.status, message: await errorMessage(res, 'Download failed (HTTP ' + res.status + ').') };
      var total = Number(res.headers.get('content-length') || 0);
      var done = 0;
      var parts = [];
      if (res.body && res.body.getReader) {
        var reader = res.body.getReader();
        for (;;) {
          var step = await reader.read();
          if (step.done) break;
          parts.push(step.value);
          done += step.value.length;
          emit('backup-transfer-progress', { kind: 'download', done: done, total: total });
        }
      } else {
        var buf = new Uint8Array(await res.arrayBuffer());
        parts.push(buf);
        done = buf.length;
      }
      var name = suggestedFilename || 'zeebroo-backup.zip';
      saveBlob(new Blob(parts, { type: 'application/zip' }), name);
      return { status: 200, savedPath: name, size: done };
    } catch (err) {
      return { status: 0, message: netError(err, 0) };
    }
  }

  async function backupPickZip() {
    var list = await pickFiles({ multiple: false, accept: '.zip' });
    if (!list.length) return { canceled: true };
    var f = list[0];
    return { canceled: false, path: registerFile(f), name: f.name, size: f.size };
  }

  async function backupUpload(opts) {
    opts = opts || {};
    var file = fileFor(opts.filePath);
    if (!file) return { status: 0, body: { message: 'File not found — please choose it again.' } };
    var size  = file.size;
    var chunk = Math.max(256 * 1024, Math.min(Number(opts.chunkBytes) || 4 * 1024 * 1024, 8 * 1024 * 1024));
    var index = Number(opts.startIndex) || 0;
    var offset = index * chunk;
    var last = null;
    try {
      while (offset < size) {
        var len = Math.min(chunk, size - offset);
        var res = await fetch(API_BASE + '/backup/imports/' + opts.importId + '/chunk?index=' + index, {
          method: 'POST',
          headers: authHeaders({ 'Content-Type': 'application/octet-stream' }),
          body: file.slice(offset, offset + len),
          credentials: 'omit',
        });
        last = { status: res.status, body: await parseBody(res) };
        if (last.status !== 200) return last;
        offset += len;
        index++;
        emit('backup-transfer-progress', { kind: 'upload', done: offset, total: size });
      }
      return last || { status: 0, body: { message: 'The file is empty.' } };
    } catch (err) {
      return { status: 0, body: { message: netError(err, 0) } };
    }
  }

  // ── Web-session handoff ───────────────────────────────────────────────────
  // "Continue to Dashboard" (GET /pos-app/launch) lands here with
  // ?handoff=<one-time code>. Swap it for a Bearer token so the user is signed
  // in as the same account / business as the web dashboard. getConfig() waits
  // for this, so the app's init() sees the new token on its first read.
  var handoffReady = (function () {
    var url;
    try { url = new URL(location.href); } catch (_) { return Promise.resolve(); }
    var code = url.searchParams.get('handoff');
    if (!code) return Promise.resolve();

    // Drop the code from the address bar / history straight away.
    url.searchParams.delete('handoff');
    try { history.replaceState(history.state, '', url.pathname + url.search + url.hash); } catch (_) {}

    return jsonRequest('POST', '/auth/web-handoff', { code: code, device_name: readConfig().device_name }, 30000)
      .then(function (res) {
        var b = res.body || {};
        if (res.status !== 200 || !b.access_token) {
          notify((b && b.message) || 'Could not sign you in automatically — please sign in.');
          return;
        }
        // Replace whatever account this browser was signed in to before.
        writeJson(CONFIG_KEY, Object.assign(readConfig(), {
          token: b.access_token,
          business_id: b.business_id || null,
          branch_id: null,
          web_session: true, // signed in from the website → sign out of both together
        }));
      })
      .catch(function () {});
  })();

  // ── Sign out ──────────────────────────────────────────────────────────────
  // The app signs out by clearing the token (setConfig({ token: null })). The
  // desktop just forgets it; here we also revoke it on the server and, when
  // the token came from the website (web_session), end the website session.
  // Revoke answering 401 means the token had already expired / been revoked
  // (e.g. the user signed out of the website first) — then there is nothing
  // more to do, and the website is left alone.
  async function signOut(old) {
    var res = await jsonRequest('POST', '/auth/revoke', null, 10000);
    if (res.status !== 200 || !old.web_session || !WEB_LOGOUT_URL || !CSRF_TOKEN) return;
    try {
      await fetch(WEB_LOGOUT_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
        credentials: 'same-origin',
        redirect: 'manual',
      });
    } catch (_) {}
  }

  function isSignOut(patch, old) {
    return !!patch && Object.prototype.hasOwnProperty.call(patch, 'token') && !patch.token && !!old.token;
  }

  // ── Offline mode is desktop-only; the browser always talks to the server ──
  function offlineStatus() {
    return { online: navigator.onLine !== false, pending: 0, failed: 0, browser: true };
  }

  var noop = function () {};
  var resolved = function (v) { return function () { return Promise.resolve(v); }; };

  // ── The browser electronAPI ───────────────────────────────────────────────
  window.electronAPI = {
    isBrowser: true,

    // Window controls — the browser owns the window, so these do nothing.
    minimize: noop,
    maximize: noop,
    close: noop,
    fullscreen: setFullscreen,
    expandWindow: noop,
    wideAuth: noop,
    narrowAuth: noop,
    onWindowState: function () { return noop; },
    onPaymentDeepLink: function () { return noop; },

    platform: 'web',
    arch: '',

    getConfig: function () {
      return handoffReady.then(function () {
        return Object.assign(readConfig(), { api_base_url: API_BASE, app_version: APP_VERSION });
      });
    },
    setConfig: function (patch) {
      return handoffReady.then(async function () {
        var old = readConfig();
        if (isSignOut(patch, old)) await signOut(old);
        var next = Object.assign(readConfig(), patch || {});
        // A token from the app's own sign-in form is not tied to the website.
        if (patch && Object.prototype.hasOwnProperty.call(patch, 'token')) next.web_session = false;
        writeJson(CONFIG_KEY, next);
        return next;
      });
    },

    apiRequest: apiRequest,
    downloadFile: downloadFile,
    fetchDataUrl: fetchDataUrl,
    printReceipt: function () { try { window.print(); return Promise.resolve({ success: true }); } catch (e) { return Promise.resolve({ success: false, failureReason: e.message }); } },
    getPrinters: resolved([{ name: 'Browser print dialog', displayName: 'Browser print dialog', isDefault: true }]),
    getPrinterConfig: function () { return Promise.resolve(printerConfig()); },
    setPrinterConfig: function (cfg) {
      cfg = cfg || {};
      writeJson(PRINTER_KEY, { name: String(cfg.name || ''), paperWidth: cfg.paperWidth === 58 ? 58 : 80, showPreview: cfg.showPreview !== false });
      return Promise.resolve(true);
    },
    printReceiptThermal: printReceiptHtml,
    printReceiptEscpos: resolved({ success: false, error: 'Direct receipt-printer (ESC/POS) printing needs the Zeebroo desktop app. In the browser, set the printer mode to HTML.' }),
    previewReceiptPdf: printReceiptHtml,
    saveHtmlPdf: saveHtmlPdf,
    onPdfProgress: function (cb) { return on('save-html-pdf-progress', cb); },
    fetchJson: fetchJson,
    apiUpload: apiUpload,
    pathForFile: function (file) { return file ? registerFile(file) : ''; },
    showOpenDialog: showOpenDialog,
    readTextFile: async function (filePath) {
      var f = fileFor(filePath);
      if (!f) return { ok: false, error: 'File not found — please choose it again.' };
      try { return { ok: true, content: await f.text() }; } catch (err) { return { ok: false, error: err.message }; }
    },

    backupApi: function (method, path, body) { return jsonRequest(method, path, body, 15 * 60 * 1000); },
    backupPickZip: backupPickZip,
    backupDownload: backupDownload,
    backupUpload: backupUpload,
    onBackupProgress: function (cb) { return on('backup-transfer-progress', cb); },

    checkForUpdate: async function () {
      try {
        var res = await fetch(API_ORIGIN + '/api/releases/latest', { headers: { Accept: 'application/json' } });
        var body = await parseBody(res);
        return { status: res.status, body: typeof body === 'object' ? body : null };
      } catch (_) { return { status: 0, body: null }; }
    },
    // The web app is always current; the update dialog's download just hands
    // the desktop installer to the browser's own downloader.
    downloadUpdate: function (opts) {
      opts = opts || {};
      if (opts.url) window.open(opts.url, '_blank', 'noopener');
      return Promise.resolve({ path: 'Your browser Downloads folder' + (opts.filename ? ' — ' + opts.filename : '') });
    },
    onDownloadProgress: function () { return noop; },
    openPath: resolved(''),
    showInFolder: resolved(undefined),
    restartApp: function () { location.reload(); return Promise.resolve(); },
    openExternal: function (url) { window.open(url, '_blank', 'noopener'); return Promise.resolve(); },
    copyToClipboard: copyToClipboard,

    openEditor: function (design) { return openChild('editor', design); },
    getEditorDesign: function () { return readChildData('editor'); },
    onDesignChanged: function (cb) { return on('design-changed', cb); },
    renderHtmlToJpeg: renderHtmlToJpeg,
    renderDesignToPdf: renderDesignToPdf,
    renderCanvasToDataUrl: renderCanvasToDataUrl,

    openAutomation: function (flow) { return openChild('automation', flow); },
    getAutomationFlow: function () { return readChildData('automation'); },
    onAutomationChanged: function (cb) { return on('automation-flow-changed', cb); },

    openQuotePrint: function (data) { return openChild('quote', data); },
    getQuotePrintData: function () { return readChildData('quote'); },
    onQuotePrintRefresh: function (cb) { return on('quote-print-refresh', cb); },

    openInvoicePrint: function (data) { return openChild('invoice', data); },
    getInvoicePrintData: function () { return readChildData('invoice'); },
    onInvoicePrintRefresh: function (cb) { return on('invoice-print-refresh', cb); },

    openPoPrint: function (data) { return openChild('po', data); },
    getPoPrintData: function () { return readChildData('po'); },
    onPoPrintRefresh: function (cb) { return on('po-print-refresh', cb); },

    openGrnPrint: function (data) { return openChild('grn', data); },
    getGrnPrintData: function () { return readChildData('grn'); },
    onGrnPrintRefresh: function (cb) { return on('grn-print-refresh', cb); },

    openKds: function () { return openChild('kds'); },
    kdsMinimize: noop,
    kdsClose: function () { window.close(); },
    kdsFullscreen: setFullscreen,
    kdsIsFullScreen: function () { return Promise.resolve(!!document.fullscreenElement); },

    offlineStatus: function () { return Promise.resolve(offlineStatus()); },
    offlineOutbox: resolved([]),
    offlineSync: function () { return Promise.resolve(offlineStatus()); },
    offlineRetry: resolved({ ok: false }),
    offlineDiscard: resolved({ ok: false }),
    offlineEvents: resolved([]),
    onOfflineStatus: function (cb) {
      var fire = function () { cb(offlineStatus()); };
      window.addEventListener('online', fire);
      window.addEventListener('offline', fire);
      return function () { window.removeEventListener('online', fire); window.removeEventListener('offline', fire); };
    },

    appVersion: function () { return Promise.resolve(APP_VERSION); },
  };

  // Used by tabs this window opens (see openChild / readChildData).
  window.__zbWebShim = {
    emit: emit,
    childData: function (kind) { return Object.prototype.hasOwnProperty.call(childData, kind) ? childData[kind] : readJson(CHILD_KEY + kind, null); },
  };

  // ── Browser-only look: hide desktop window buttons and the offline pill ──
  document.documentElement.classList.add('zb-web');
  var style = document.createElement('style');
  style.textContent =
    '.zb-web .wc-btn, .zb-web #btn-minimize, .zb-web [data-offline-pill] { display: none !important; }';
  (document.head || document.documentElement).appendChild(style);
})();
