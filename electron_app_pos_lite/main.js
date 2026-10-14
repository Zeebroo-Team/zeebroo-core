'use strict';

const path = require('path');
const fs = require('fs');
const http = require('http');
const https = require('https');
const { app, BrowserWindow, Menu, ipcMain, dialog, shell } = require('electron');
const { API_BASE_URL } = require('./config');

// ── Local config (token + selected business) ───────────────────────────────
let CONFIG_PATH;
function getConfigPath() {
  if (!CONFIG_PATH) CONFIG_PATH = path.join(app.getPath('userData'), 'config.json');
  return CONFIG_PATH;
}

function loadConfig() {
  try {
    const p = getConfigPath();
    if (fs.existsSync(p)) return JSON.parse(fs.readFileSync(p, 'utf8'));
  } catch (_) {}
  return {
    device_name: 'pos-lite-1',
    token: null,
    business_id: null,
    branch_id: null,
    user: null,
  };
}

function saveConfig(data) {
  fs.writeFileSync(getConfigPath(), JSON.stringify(data, null, 2), 'utf8');
}

let config = loadConfig();
let authWindow = null;
let mainWindow = null;

// ── Menu bar ────────────────────────────────────────────────────────────
// No application menu: on Windows/Linux this removes the menu bar from every
// window. macOS always shows the system bar, but this drops File/Edit/View/…
// With no menu, macOS also loses the Cmd+C/V/X/A/Z, Cmd+W/M and Cmd+Q
// shortcuts (they come from the menu), so restore them by hand — otherwise
// you can't paste into the login or search fields.
function attachMacShortcuts(win) {
  if (process.platform !== 'darwin') return;
  win.webContents.on('before-input-event', (event, input) => {
    if (input.type !== 'keyDown' || !input.meta || input.control || input.alt) return;
    const wc = win.webContents;
    switch (input.key.toLowerCase()) {
      case 'c': wc.copy(); break;
      case 'v': wc.paste(); break;
      case 'x': wc.cut(); break;
      case 'a': wc.selectAll(); break;
      case 'z': if (input.shift) wc.redo(); else wc.undo(); break;
      case 'w': win.close(); break;
      case 'm': win.minimize(); break;
      case 'q': app.quit(); break;
      default: return;
    }
    event.preventDefault();
  });
}

// ── Cashier sessions: POS only ──────────────────────────────────────────
// A cashier (signed in via /cashier/login) may only use the POS screen; the
// dashboard stays reachable as the home screen with every other tile locked
// (renderer/js/role-guard.js). This blocks navigating to any other screen
// even if the renderer-side lock is bypassed.
const CASHIER_PAGES = ['dashboard.html', 'pos.html'];

function attachCashierGuard(win) {
  win.webContents.on('will-navigate', (event, url) => {
    if (!config.is_cashier) return;
    let page = '';
    try { page = path.basename(new URL(url).pathname).toLowerCase(); } catch (_) {}
    if (!CASHIER_PAGES.includes(page)) event.preventDefault();
  });
}

// ── Windows ─────────────────────────────────────────────────────────────
function createAuthWindow() {
  if (mainWindow) { mainWindow.close(); mainWindow = null; }

  authWindow = new BrowserWindow({
    width: 440,
    height: 640,
    resizable: false,
    center: true,
    title: 'Zeebroo POS Lite',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
    show: false,
  });

  attachMacShortcuts(authWindow);
  authWindow.loadFile(path.join(__dirname, 'renderer', 'auth.html'));
  authWindow.once('ready-to-show', () => authWindow.show());
  authWindow.on('closed', () => { authWindow = null; });
}

function createMainWindow() {
  if (authWindow) { authWindow.close(); authWindow = null; }

  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    minWidth: 960,
    minHeight: 600,
    center: true,
    title: 'Zeebroo POS Lite',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
    show: false,
  });

  attachMacShortcuts(mainWindow);
  attachCashierGuard(mainWindow);
  mainWindow.loadFile(path.join(__dirname, 'renderer', 'dashboard.html'));
  mainWindow.once('ready-to-show', () => mainWindow.show());
  mainWindow.on('closed', () => { mainWindow = null; });
}

// ── Deep-link: zeebroopos://payment?status=success|cancel|failed&payment_id=… ──
// Fired after the system browser returns from Stripe Checkout during the
// onboarding wizard's payment step. Own scheme, distinct from the full
// desktop app's `socibiz://`, since both could be installed side by side.
// Running unpackaged (`electron .`), Electron sets process.defaultApp —
// without passing execPath + the app dir explicitly, Windows registers the
// protocol as bare "electron.exe %1", so the OS can't hand the URL back
// correctly. Packaged builds don't need this — the installer's exe path is
// already correct.
if (process.defaultApp) {
  if (process.argv.length >= 2) {
    app.setAsDefaultProtocolClient('zeebroopos', process.execPath, [path.resolve(process.argv[1])]);
  }
} else {
  app.setAsDefaultProtocolClient('zeebroopos');
}

// Forwards the payment result to whichever window is open — the onboarding
// wizard lives in the auth window, so that's the one that needs the event.
// Never trust the link's status alone; the renderer re-verifies against the
// API before proceeding (see auth.js's onPaymentDeepLink handler).
function handleDeepLink(url) {
  try {
    const parsed = new URL(url);
    if (parsed.host !== 'payment') return;
    const status = parsed.searchParams.get('status');
    const paymentId = parsed.searchParams.get('payment_id');
    const target = authWindow || mainWindow;
    if (target) {
      if (target.isMinimized()) target.restore();
      target.focus();
      target.webContents.send('payment-deep-link', {
        status,
        paymentId: paymentId ? Number(paymentId) : null,
      });
    }
  } catch (e) { console.error('[deep-link]', e); }
}

app.on('open-url', (event, url) => { event.preventDefault(); handleDeepLink(url); });

const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
} else {
  app.on('second-instance', (_event, argv) => {
    const deepUrl = argv.find((a) => a.startsWith('zeebroopos://'));
    if (deepUrl) handleDeepLink(deepUrl);
  });
}

app.whenReady().then(() => {
  Menu.setApplicationMenu(null);

  const alreadyLoggedIn = !!(config.token && config.business_id);
  if (alreadyLoggedIn) createMainWindow();
  else createAuthWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      const stillLoggedIn = !!(config.token && config.business_id);
      if (stillLoggedIn) createMainWindow(); else createAuthWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

// ── Config IPC ──────────────────────────────────────────────────────────
ipcMain.handle('config-get', () => ({ ...config, api_base_url: API_BASE_URL, app_version: app.getVersion() }));
ipcMain.handle('open-external', (_e, url) => shell.openExternal(url));

ipcMain.handle('config-set', (_e, patch) => {
  config = { ...config, ...patch };
  saveConfig(config);
  return { ...config, api_base_url: API_BASE_URL, app_version: app.getVersion() };
});

// ── App updates ─────────────────────────────────────────────────────────
// Releases are managed in the admin panel (Release Management → Zeebroo POS
// Lite tab) and served by Modules/AppConnection/routes/api.php. ?app=lite keeps
// this client from picking up the full desktop app's releases.
ipcMain.handle('check-for-update', () => new Promise((resolve) => {
  const url = new URL(new URL(API_BASE_URL).origin + '/api/releases/latest?app=lite');
  const lib = url.protocol === 'https:' ? https : http;
  const req = lib.get(url.toString(), { headers: { Accept: 'application/json' }, timeout: 15000 }, (res) => {
    let d = '';
    res.on('data', (c) => { d += c; });
    res.on('end', () => {
      try { resolve({ status: res.statusCode, body: JSON.parse(d) }); }
      catch (_) { resolve({ status: res.statusCode, body: null }); }
    });
  });
  req.on('timeout', () => req.destroy(new Error('timeout')));
  req.on('error', () => resolve({ status: 0, body: null }));
}));

// Downloads the installer into the user's Downloads folder, following
// redirects (SourceForge/GitHub links redirect) and reporting % progress.
ipcMain.handle('download-update', (e, { url, filename }) => new Promise((resolve) => {
  const dest = path.join(app.getPath('downloads'), path.basename(String(filename || 'ZeebrooPOSLite-update')));
  const fail = (file, error) => {
    if (file) file.close(() => fs.unlink(dest, () => {}));
    resolve({ error });
  };

  function fetchUrl(currentUrl, redirectsLeft) {
    let parsed;
    try { parsed = new URL(currentUrl); } catch (_) { return fail(null, 'Invalid download URL'); }
    if (!['http:', 'https:'].includes(parsed.protocol)) return fail(null, 'Invalid download URL');
    const lib = parsed.protocol === 'https:' ? https : http;

    const req = lib.get(parsed.toString(), (res) => {
      if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        res.resume();
        if (redirectsLeft <= 0) return fail(null, 'Too many redirects');
        return fetchUrl(new URL(res.headers.location, currentUrl).toString(), redirectsLeft - 1);
      }
      if (res.statusCode >= 400) {
        res.resume();
        return fail(null, `Server responded with ${res.statusCode}`);
      }

      const file = fs.createWriteStream(dest);
      const total = parseInt(res.headers['content-length'] || '0', 10);
      let received = 0;
      let lastPct = -1;
      res.on('data', (chunk) => {
        received += chunk.length;
        if (total > 0) {
          const pct = Math.round((received / total) * 100);
          if (pct !== lastPct && !e.sender.isDestroyed()) { lastPct = pct; e.sender.send('download-progress', pct); }
        }
      });
      res.on('error', (err) => fail(file, err.message));
      file.on('error', (err) => fail(file, err.message));
      file.on('finish', () => resolve({ path: dest }));
      res.pipe(file);
    });
    req.on('error', (err) => fail(null, err.message));
  }

  fetchUrl(url, 5);
}));

ipcMain.handle('open-path', (_e, filePath) => shell.openPath(filePath));
ipcMain.handle('show-in-folder', (_e, filePath) => shell.showItemInFolder(filePath));
ipcMain.handle('app-quit', () => app.quit());

// Called by the auth renderer once login/register + business resolution succeed.
ipcMain.handle('auth-success', () => {
  createMainWindow();
  return true;
});

// Called by the main renderer's Logout button.
ipcMain.handle('logout', () => {
  config = { ...config, token: null, business_id: null, branch_id: null, user: null, is_cashier: false };
  saveConfig(config);
  createAuthWindow();
  return true;
});

// Fully quits and relaunches the app (used by the Restart App button during dev).
ipcMain.handle('app-restart', () => {
  app.relaunch();
  app.exit(0);
});

// Toggles OS-level fullscreen for the main window (F11 shortcut) — with no
// application menu (see attachMacShortcuts above), Electron has no built-in
// accelerator for this, so the renderer asks the main process to do it.
ipcMain.handle('toggle-fullscreen', () => {
  if (!mainWindow) return false;
  const next = !mainWindow.isFullScreen();
  mainWindow.setFullScreen(next);
  return next;
});

// ── API proxy (runs in the main process so the renderer never needs Node
//    integration or has to fight the browser's CORS policy) ────────────────
function apiRequest(method, path_, body, token, businessId, branchId) {
  return new Promise((resolve, reject) => {
    const base = API_BASE_URL.replace(/\/$/, '');
    const url = new URL(base + path_);
    const isHttps = url.protocol === 'https:';
    const lib = isHttps ? https : http;

    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (businessId) headers['X-Business-Id'] = String(businessId);
    if (branchId) headers['X-Branch-Id'] = String(branchId);

    const payload = body ? JSON.stringify(body) : null;
    if (payload) headers['Content-Length'] = Buffer.byteLength(payload);

    const req = lib.request({
      hostname: url.hostname,
      port: url.port || (isHttps ? 443 : 80),
      path: url.pathname + url.search,
      method,
      headers,
      timeout: 30000,
    }, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try { resolve({ status: res.statusCode, body: JSON.parse(data) }); }
        catch (_) { resolve({ status: res.statusCode, body: data }); }
      });
    });

    req.on('timeout', () => { req.destroy(new Error('Request timed out after 30s')); });
    req.on('error', (err) => resolve({ status: 0, body: { message: err.message } }));
    if (payload) req.write(payload);
    req.end();
  });
}

ipcMain.handle('api-request', async (_e, { method, path: p, body }) => {
  try {
    return await apiRequest(method, p, body, config.token, config.business_id, config.branch_id);
  } catch (err) {
    return { status: 0, body: { message: err.message } };
  }
});

// ── Multipart file upload (e.g. product image → file manager) ──────────────
const MIME_EXT = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml', pdf: 'application/pdf' };
function extMime(filePath) {
  const ext = path.extname(filePath).slice(1).toLowerCase();
  return MIME_EXT[ext] || 'application/octet-stream';
}
function buildMultipart(boundary, files) {
  const CRLF = '\r\n';
  const parts = [];
  for (const { fieldName, filePath, fileName, mime } of files) {
    parts.push(Buffer.from(`--${boundary}${CRLF}Content-Disposition: form-data; name="${fieldName}"; filename="${fileName}"${CRLF}Content-Type: ${mime}${CRLF}${CRLF}`));
    parts.push(fs.readFileSync(filePath));
    parts.push(Buffer.from(CRLF));
  }
  parts.push(Buffer.from(`--${boundary}--${CRLF}`));
  return Buffer.concat(parts);
}
ipcMain.handle('api-upload', async (_e, { path: apiPath, filePath }) => {
  try {
    const base = API_BASE_URL.replace(/\/$/, '');
    const url = new URL(base + apiPath);
    const lib = url.protocol === 'https:' ? https : http;
    const boundary = 'PosBoundary' + Date.now();
    const fileName = path.basename(filePath);
    const body = buildMultipart(boundary, [{ fieldName: 'files[]', filePath, fileName, mime: extMime(filePath) }]);
    const headers = {
      'Content-Type': `multipart/form-data; boundary=${boundary}`,
      'Content-Length': body.length,
      'Accept': 'application/json',
    };
    if (config.token) headers['Authorization'] = `Bearer ${config.token}`;
    if (config.business_id) headers['X-Business-Id'] = String(config.business_id);
    if (config.branch_id) headers['X-Branch-Id'] = String(config.branch_id);
    return await new Promise((resolve, reject) => {
      const req = lib.request({ hostname: url.hostname, port: url.port || (url.protocol === 'https:' ? 443 : 80), path: url.pathname + url.search, method: 'POST', headers }, (res) => {
        let data = '';
        res.on('data', (c) => { data += c; });
        res.on('end', () => { try { resolve({ status: res.statusCode, body: JSON.parse(data) }); } catch (_) { resolve({ status: res.statusCode, body: data }); } });
      });
      req.on('error', reject);
      req.write(body);
      req.end();
    });
  } catch (err) {
    return { status: 0, body: { message: err.message } };
  }
});

ipcMain.handle('show-open-dialog', async (_e, options) => {
  if (!mainWindow) return { canceled: true, filePaths: [] };
  return dialog.showOpenDialog(mainWindow, options);
});

// Downloads a binary file (e.g. a billing receipt PDF) from the API and lets
// the user save it to disk — apiRequest() always JSON.parses the response,
// which isn't usable for binary payloads, so this builds the request separately.
function apiDownloadFile(path_, token, businessId, branchId) {
  return new Promise((resolve, reject) => {
    const base = API_BASE_URL.replace(/\/$/, '');
    const url = new URL(base + path_);
    const isHttps = url.protocol === 'https:';
    const lib = isHttps ? https : http;

    const headers = { Accept: 'application/pdf' };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (businessId) headers['X-Business-Id'] = String(businessId);
    if (branchId) headers['X-Branch-Id'] = String(branchId);

    const req = lib.request({
      hostname: url.hostname,
      port: url.port || (isHttps ? 443 : 80),
      path: url.pathname + url.search,
      method: 'GET',
      headers,
      timeout: 120000,
    }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => resolve({ status: res.statusCode, buffer: Buffer.concat(chunks) }));
    });

    req.on('timeout', () => { req.destroy(new Error('Request timed out after 120s')); });
    req.on('error', reject);
    req.end();
  });
}

// Opens a native window loaded with the given HTML and triggers the OS print
// dialog on it — used for receipt/invoice printing. window.open() from the
// renderer is denied by default (no setWindowOpenHandler is registered, and
// Electron 14+ denies popups by default), so printing has to go through a
// real BrowserWindow created here rather than a popup document.write().
ipcMain.handle('print-html', (_e, { html }) => {
  return new Promise((resolve) => {
    const win = new BrowserWindow({
      width: 480,
      height: 720,
      title: 'Print',
      show: false,
      webPreferences: { sandbox: true },
    });
    win.once('ready-to-show', () => {
      win.show();
      win.webContents.print({ printBackground: true }, () => {
        try { win.close(); } catch (_) {}
      });
      resolve(true);
    });
    win.loadURL('data:text/html;charset=UTF-8,' + encodeURIComponent(html));
  });
});

// Renders the given HTML to a PDF (via Electron's printToPDF) and lets the
// user save it — same save-dialog + fs.writeFileSync shape as api-download-file
// below, just generating the PDF locally instead of fetching one from the API.
ipcMain.handle('save-html-as-pdf', async (_e, { html, suggestedFilename }) => {
  let win;
  try {
    win = new BrowserWindow({ show: false, webPreferences: { sandbox: true } });
    await win.loadURL('data:text/html;charset=UTF-8,' + encodeURIComponent(html));
    const buffer = await win.webContents.printToPDF({ printBackground: true });
    win.destroy();
    win = null;

    const { canceled, filePath } = await dialog.showSaveDialog(mainWindow, {
      defaultPath: suggestedFilename || 'document.pdf',
      filters: [{ name: 'PDF', extensions: ['pdf'] }],
    });
    if (canceled || !filePath) {
      return { status: 0, canceled: true };
    }

    fs.writeFileSync(filePath, buffer);
    return { status: 200, savedPath: filePath };
  } catch (err) {
    if (win) { try { win.destroy(); } catch (_) {} }
    return { status: 0, message: err.message };
  }
});

// ── Thermal printer support (Settings → Printer) ────────────────────────
// Same shape as the full desktop app (electron_app/main.js). The printer is a
// per-device choice, so it lives in the local config.json, not on the server.
ipcMain.handle('get-printers', async (e) => {
  try {
    const win = BrowserWindow.fromWebContents(e.sender);
    const list = await win.webContents.getPrintersAsync();
    return list.map((p) => ({ name: p.name, displayName: p.displayName || p.name, isDefault: p.isDefault || false }));
  } catch (_) { return []; }
});

ipcMain.handle('get-printer-config', () => {
  config = loadConfig();
  const tp = config.thermalPrinter || {};
  return {
    name:        tp.name || '',
    silent:      tp.silent !== false,
    paperWidth:  tp.paperWidth || 80,
    mode:        tp.mode || 'escpos',
    showPreview: tp.showPreview !== false,
  };
});

ipcMain.handle('set-printer-config', (_e, cfg) => {
  config = loadConfig();
  config.thermalPrinter = {
    name:        String(cfg.name || ''),
    silent:      cfg.silent !== false,
    paperWidth:  cfg.paperWidth === 58 ? 58 : 80,
    mode:        cfg.mode === 'html' ? 'html' : 'escpos',
    showPreview: cfg.showPreview !== false,
  };
  saveConfig(config);
  return true;
});

// User-set receipt margins (mm) from the Receipt Layout Editor — each side clamped to 0–20.
const RECEIPT_MARGIN_MAX_MM = 20;
function _receiptMargins(m = {}) {
  const v = (x) => Math.min(RECEIPT_MARGIN_MAX_MM, Math.max(0, parseFloat(x) || 0));
  return { top: v(m.top), right: v(m.right), bottom: v(m.bottom), left: v(m.left) };
}

// ESC/POS builder — pure Node.js, no native modules
function _escposBuffer(receipt, paperWidth, margins = {}) {
  // Font A is 12 dots wide (1.5 mm at 203 dpi): 80mm heads print 576 dots (48 cols), 58mm heads 384 dots (32 cols)
  const MM_PER_COL = 1.5;
  const DOTS_PER_MM = 8;
  const mg   = _receiptMargins(margins);
  const LPAD = Math.round(mg.left  / MM_PER_COL);  // left margin, in columns
  const RPAD = Math.round(mg.right / MM_PER_COL);  // right margin, in columns
  const COL  = Math.max(20, (paperWidth === 58 ? 32 : 48) - LPAD - RPAD);
  const QTYF = 4;                              // qty column
  const PRCF = paperWidth === 58 ?  0 : 10;  // price column (skipped on 58mm)
  const TOTF = paperWidth === 58 ? 12 : 10;  // total column
  const NAMW = Math.max(6, COL - QTYF - PRCF - TOTF);  // item name column takes what's left

  const buf = [];
  const push = (...b) => buf.push(...b);
  const text = (s) => {
    for (const ch of String(s || '')) {
      const c = ch.charCodeAt(0);
      buf.push(c < 128 ? c : 0x3F);  // non-ASCII → '?'
    }
  };
  const println = (s = '') => { text(' '.repeat(LPAD) + s); push(0x0A); };

  // Centering is done with spaces (ctr) inside the margin-reduced width, so the printer
  // always stays left-aligned — ESC a 1 would re-centre the already padded line.
  const alignL  = () => push(0x1B, 0x61, 0x00);
  const alignC  = alignL;
  const boldOn  = () => push(0x1B, 0x45, 0x01);
  const boldOff = () => push(0x1B, 0x45, 0x00);
  const feed    = (n = 1) => push(0x1B, 0x64, n);
  const feedMm  = (mm) => { const d = Math.round(mm * DOTS_PER_MM); if (d > 0) push(0x1B, 0x4A, Math.min(255, d)); };
  const cut     = () => push(0x1D, 0x56, 0x42, 0x03);  // partial cut + 3-line feed

  const line  = () => println('-'.repeat(COL));
  const dline = () => println('='.repeat(COL));
  const ctr   = (s) => {
    s = String(s || '').slice(0, COL);
    return ' '.repeat(Math.max(0, Math.floor((COL - s.length) / 2))) + s;
  };
  const rowLR = (left, right) => {
    left = String(left || ''); right = String(right || '');
    const gap = COL - left.length - right.length;
    if (gap <= 0) println(left.slice(0, Math.max(0, COL - right.length - 1)) + ' ' + right);
    else println(left + ' '.repeat(gap) + right);
  };
  const money = (amount) => {
    const num = parseFloat(amount || 0).toFixed(2);
    if (!receipt.currency) return num;
    return receipt.currencyPosition === 'before' ? `${receipt.currency} ${num}` : `${num} ${receipt.currency}`;
  };

  push(0x1B, 0x40);  // ESC @ — initialize printer
  feedMm(mg.top);

  if (receipt.businessName) { alignC(); boldOn(); println(ctr(receipt.businessName)); boldOff(); }
  if (receipt.address) {
    alignC();
    for (const l of receipt.address.split('\n')) if (l.trim()) println(ctr(l.trim()));
  }
  if (receipt.header) { alignC(); println(ctr(receipt.header)); }

  alignL(); line();
  rowLR('Receipt No.:', String(receipt.saleNumber || ''));
  rowLR('Date:',        String(receipt.date || ''));
  if (receipt.customer) rowLR('Customer:', String(receipt.customer));
  if (receipt.cashier)  rowLR('Cashier:',  String(receipt.cashier));
  line();

  const ih = 'ITEM'.padEnd(NAMW) + 'QTY'.padStart(QTYF) + (PRCF ? 'PRICE'.padStart(PRCF) : '') + 'TOTAL'.padStart(TOTF);
  boldOn(); println(ih.slice(0, COL)); boldOff();
  line();

  for (const item of (receipt.items || [])) {
    const name  = String(item.name || '').slice(0, NAMW).padEnd(NAMW);
    const qtyN  = parseFloat(item.qty) || 0;
    const qty   = (qtyN % 1 === 0 ? String(Math.round(qtyN)) : qtyN.toFixed(2)).padStart(QTYF);
    const price = PRCF ? parseFloat(item.price).toFixed(2).padStart(PRCF) : '';
    const total = parseFloat(item.total).toFixed(2).padStart(TOTF);
    println((name + qty + price + total).slice(0, COL));
    if (parseFloat(item.discount) > 0) println(('  Discount: -' + parseFloat(item.discount).toFixed(2)).slice(0, COL));
  }
  line();

  if (parseFloat(receipt.discount) > 0 || parseFloat(receipt.couponDiscount) > 0.005) rowLR('Subtotal:', money(receipt.subtotal));
  if (parseFloat(receipt.discount) > 0) {
    rowLR('Discount' + (receipt.discountPct ? ' (' + receipt.discountPct + '%)' : '') + ':', '-' + money(receipt.discount));
  }
  if (parseFloat(receipt.couponDiscount) > 0.005) {
    rowLR('Coupon' + (receipt.couponCode ? ' ' + receipt.couponCode : '') + ':', '-' + money(receipt.couponDiscount));
  }

  dline();
  boldOn(); rowLR('TOTAL:', money(receipt.total)); boldOff();

  if (parseFloat(receipt.giftCardAmount) > 0.005) {
    rowLR('Gift card' + (receipt.giftCardCode ? ' ' + receipt.giftCardCode : '') + ':', '-' + money(receipt.giftCardAmount));
    if (receipt.giftCardBalance != null) rowLR('Gift card balance:', money(receipt.giftCardBalance));
  }
  if (receipt.tendered != null) rowLR('Cash received:', money(receipt.tendered));
  else rowLR('Paid (' + (receipt.paymentMethod || 'Cash') + '):', money(receipt.paid));
  if (parseFloat(receipt.change) > 0.005) rowLR('Change:', money(receipt.change));

  if (receipt.notes) {
    line(); alignL();
    for (const l of receipt.notes.split('\n')) println(l.slice(0, COL));
  }

  feed(1);
  alignC(); boldOn();
  println(ctr(String(receipt.footer || 'Thank you for your purchase!')));
  boldOff();
  println(ctr('[ ' + String(receipt.paymentMethod || 'CASH').toUpperCase() + ' ]'));

  feed(3);
  feedMm(mg.bottom);
  cut();
  return Buffer.from(buf);
}

// Send raw ESC/POS bytes to a printer.
// Windows: PowerShell → winspool.drv RAW datatype (bypasses GDI completely). Mac/Linux: lp -o raw
async function _sendRawToPrinter(printerName, buffer) {
  const os = require('os');
  const { execFile } = require('child_process');

  const tmpBin = path.join(os.tmpdir(), `escpos-${Date.now()}.bin`);
  fs.writeFileSync(tmpBin, buffer);

  return new Promise((resolve) => {
    const cleanup = (psFile) => {
      try { fs.unlinkSync(tmpBin); } catch (_) {}
      if (psFile) try { fs.unlinkSync(psFile); } catch (_) {}
    };

    if (process.platform === 'win32') {
      const psScript = `param([string]$N, [string]$F)
$ErrorActionPreference = 'Stop'
try {
  $b = [IO.File]::ReadAllBytes($F)
  Add-Type -TypeDefinition @"
using System; using System.Runtime.InteropServices;
[StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
public struct DOCINFOA { public string pDocName; public string pOutputFile; public string pDataType; }
public static class WP {
  [DllImport("winspool.drv", CharSet=CharSet.Ansi, ExactSpelling=true)]
  public static extern bool OpenPrinterA(string n, out IntPtr h, IntPtr d);
  [DllImport("winspool.drv")] public static extern bool ClosePrinter(IntPtr h);
  [DllImport("winspool.drv", CharSet=CharSet.Ansi, ExactSpelling=true)]
  public static extern int StartDocPrinterA(IntPtr h, int lv, ref DOCINFOA di);
  [DllImport("winspool.drv")] public static extern bool EndDocPrinter(IntPtr h);
  [DllImport("winspool.drv")] public static extern bool StartPagePrinter(IntPtr h);
  [DllImport("winspool.drv")] public static extern bool EndPagePrinter(IntPtr h);
  [DllImport("winspool.drv")] public static extern bool WritePrinter(IntPtr h, byte[] b, int n, out int w);
}
"@
  $h = [IntPtr]::Zero
  if (-not [WP]::OpenPrinterA($N, [ref]$h, [IntPtr]::Zero)) { throw "Cannot open printer '$N'" }
  $di = New-Object DOCINFOA; $di.pDocName = 'Receipt'; $di.pDataType = 'RAW'
  if ([WP]::StartDocPrinterA($h, 1, [ref]$di) -le 0) { [WP]::ClosePrinter($h); throw 'StartDocPrinter failed' }
  [WP]::StartPagePrinter($h)  | Out-Null
  $w = 0; [WP]::WritePrinter($h, $b, $b.Length, [ref]$w) | Out-Null
  [WP]::EndPagePrinter($h)   | Out-Null
  [WP]::EndDocPrinter($h)    | Out-Null
  [WP]::ClosePrinter($h)     | Out-Null
  Write-Output "OK:$w"
} catch { Write-Output "ERR:$($_.Exception.Message)"; exit 1 }`;

      const tmpPs = path.join(os.tmpdir(), `escpos-${Date.now()}.ps1`);
      fs.writeFileSync(tmpPs, psScript, 'utf8');

      execFile(
        'powershell.exe',
        ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', tmpPs, '-N', printerName, '-F', tmpBin],
        { timeout: 12000 },
        (err, stdout) => {
          cleanup(tmpPs);
          if (err) return resolve({ success: false, error: err.message });
          const out = String(stdout || '').trim();
          if (out.startsWith('ERR:')) return resolve({ success: false, error: out.slice(4) });
          resolve({ success: true });
        }
      );
    } else {
      execFile('lp', ['-d', printerName, '-o', 'raw', tmpBin], { timeout: 10000 }, (err) => {
        cleanup(null);
        if (err) return resolve({ success: false, error: err.message });
        resolve({ success: true });
      });
    }
  });
}

ipcMain.handle('print-receipt-escpos', async (_e, opts = {}) => {
  const { receipt, name, paperWidth = 80, margins = {} } = opts;
  if (!name) return { success: false, error: 'No printer specified' };
  try {
    return await _sendRawToPrinter(name, _escposBuffer(receipt || {}, paperWidth, margins));
  } catch (err) {
    return { success: false, error: err.message || String(err) };
  }
});

// Print a complete HTML receipt document straight to a printer (no dialog when
// silent). Empty name → the Windows default printer.
ipcMain.handle('print-receipt-silent', (_e, opts = {}) => {
  const { html = '', name = '', silent = true } = opts;
  return new Promise((resolve) => {
    const win = new BrowserWindow({ width: 400, height: 600, show: false, skipTaskbar: true, webPreferences: { sandbox: true } });
    let resolved = false;
    const done = (result) => {
      if (resolved) return;
      resolved = true;
      if (!win.isDestroyed()) win.destroy();
      resolve(result);
    };
    const guard = setTimeout(() => done({ success: false, error: 'Print timeout' }), 15000);

    win.webContents.once('did-finish-load', () => {
      setTimeout(() => {  // let fonts / images settle
        const printOpts = { silent, printBackground: true, margins: { marginType: 'printableArea' } };
        if (name) printOpts.deviceName = name;
        win.webContents.print(printOpts, (success, failureReason) => {
          clearTimeout(guard);
          done({ success, error: failureReason || null });
        });
      }, 400);
    });
    win.loadURL('data:text/html;charset=UTF-8,' + encodeURIComponent(html));
  });
});

// Windows' system print dialog can't preview Electron pages ("This app doesn't
// support print preview"), so with no printer configured the receipt is rendered
// to a PDF at the exact roll width and opened in Chromium's PDF viewer, which
// shows a true-size preview and has its own Print button.
ipcMain.handle('preview-receipt-pdf', async (e, opts = {}) => {
  const { html = '', paperWidth = 80 } = opts;
  const os = require('os');
  const paperMm = paperWidth === 58 ? 58 : 80;
  const MM_PER_IN = 25.4;
  const MARGIN_MM = 4;  // matches the receipt document's @page margin

  const renderWin = new BrowserWindow({ width: 400, height: 600, show: false, skipTaskbar: true, webPreferences: { sandbox: true } });
  try {
    await renderWin.loadURL('data:text/html;charset=UTF-8,' + encodeURIComponent(html));
    await new Promise((r) => setTimeout(r, 300));

    // Roll paper has no fixed length — size the page to the receipt's content height
    const contentPx = await renderWin.webContents.executeJavaScript('Math.ceil(document.body.getBoundingClientRect().height)');
    const heightMm = (contentPx * MM_PER_IN / 96) + MARGIN_MM * 2 + 2;
    const marginIn = MARGIN_MM / MM_PER_IN;

    const pdf = await renderWin.webContents.printToPDF({
      pageSize: { width: paperMm / MM_PER_IN, height: heightMm / MM_PER_IN },
      margins: { top: marginIn, bottom: marginIn, left: marginIn, right: marginIn },
      printBackground: true,
    });

    const pdfPath = path.join(os.tmpdir(), `zeebroo-lite-receipt-${Date.now()}.pdf`);
    fs.writeFileSync(pdfPath, pdf);

    const previewWin = new BrowserWindow({
      width: 520, height: 820, title: `Receipt preview — ${paperMm} mm`,
      parent: BrowserWindow.fromWebContents(e.sender) || undefined,
      autoHideMenuBar: true,
      webPreferences: { plugins: true, sandbox: true },
    });
    previewWin.on('page-title-updated', (ev) => ev.preventDefault());  // keep our title, not the file path
    previewWin.once('closed', () => { try { fs.unlinkSync(pdfPath); } catch (_) {} });
    await previewWin.loadFile(pdfPath);
    return { success: true };
  } catch (err) {
    return { success: false, error: err.message || String(err) };
  } finally {
    if (!renderWin.isDestroyed()) renderWin.destroy();
  }
});

ipcMain.handle('api-download-file', async (_e, { path: p, suggestedFilename }) => {
  try {
    const res = await apiDownloadFile(p, config.token, config.business_id, config.branch_id);
    if (res.status !== 200) {
      let message = `Download failed (HTTP ${res.status}).`;
      try { message = JSON.parse(res.buffer.toString('utf8'))?.message || message; } catch (_) {}
      return { status: res.status, message };
    }

    const { canceled, filePath } = await dialog.showSaveDialog(mainWindow, {
      defaultPath: suggestedFilename || 'download.pdf',
    });
    if (canceled || !filePath) {
      return { status: 0, canceled: true };
    }

    fs.writeFileSync(filePath, res.buffer);
    return { status: 200, savedPath: filePath };
  } catch (err) {
    return { status: 0, message: err.message };
  }
});
