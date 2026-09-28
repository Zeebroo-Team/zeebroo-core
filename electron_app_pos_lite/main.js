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
ipcMain.handle('config-get', () => ({ ...config, api_base_url: API_BASE_URL }));
ipcMain.handle('open-external', (_e, url) => shell.openExternal(url));

ipcMain.handle('config-set', (_e, patch) => {
  config = { ...config, ...patch };
  saveConfig(config);
  return { ...config, api_base_url: API_BASE_URL };
});

// Called by the auth renderer once login/register + business resolution succeed.
ipcMain.handle('auth-success', () => {
  createMainWindow();
  return true;
});

// Called by the main renderer's Logout button.
ipcMain.handle('logout', () => {
  config = { ...config, token: null, business_id: null, branch_id: null, user: null };
  saveConfig(config);
  createAuthWindow();
  return true;
});

// Fully quits and relaunches the app (used by the Restart App button during dev).
ipcMain.handle('app-restart', () => {
  app.relaunch();
  app.exit(0);
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
