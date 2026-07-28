'use strict';

const path = require('path');
const fs   = require('fs');
const { app, BrowserWindow, ipcMain, dialog, shell } = require('electron');
const { API_BASE_URL } = require('./config');

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
    device_name: 'pos-desktop-1',
    token: null,
    business_id: null,
    branch_id: null,
    dark_mode: false,
  };
}

function saveConfig(data) {
  fs.writeFileSync(getConfigPath(), JSON.stringify(data, null, 2), 'utf8');
}

let mainWindow;
let editorWindow       = null;
let editorDesign       = null;
let automationWindow   = null;
let kdsWindow          = null;
let config;

function createWindow() {
  const alreadyLoggedIn = !!(config.token && config.business_id);

  mainWindow = new BrowserWindow({
    width:     alreadyLoggedIn ? 1280 : 440,
    height:    alreadyLoggedIn ? 800  : 720,
    minWidth:  alreadyLoggedIn ? 960  : 440,
    minHeight: alreadyLoggedIn ? 600  : 720,
    resizable: alreadyLoggedIn,
    center: true,
    frame: false,
    backgroundColor: config.dark_mode ? '#1a1a2e' : '#f0f2f5',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
    show: false,
  });

  mainWindow.loadFile(path.join(__dirname, 'renderer', 'index.html'));

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  mainWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[renderer:${prefix}] ${msg}`);
  });

  mainWindow.on('maximize',   () => mainWindow.webContents.send('window-state', 'maximized'));
  mainWindow.on('unmaximize', () => mainWindow.webContents.send('window-state', 'normal'));
  mainWindow.on('closed', () => { mainWindow = null; });
}

app.whenReady().then(() => { config = loadConfig(); createWindow(); });
app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
app.on('activate', () => { if (!mainWindow) createWindow(); });

// ── Window controls ───────────────────────────────────────────────────────
ipcMain.on('window-minimize',  () => mainWindow?.minimize());
ipcMain.on('window-maximize',  () => mainWindow?.isMaximized() ? mainWindow.unmaximize() : mainWindow.maximize());
ipcMain.on('window-close',     () => mainWindow?.close());
ipcMain.on('window-fullscreen',(_e, flag) => mainWindow?.setFullScreen(flag));

ipcMain.on('window-expand', () => {
  if (!mainWindow) return;
  mainWindow.setResizable(true);
  mainWindow.setMinimumSize(960, 600);
  mainWindow.setSize(1280, 820, true);
  mainWindow.center();
});

ipcMain.on('window-wide-auth', () => {
  if (!mainWindow) return;
  mainWindow.setResizable(true);
  mainWindow.setMinimumSize(900, 580);
  mainWindow.setSize(960, 620, true);
  mainWindow.center();
});

ipcMain.on('window-narrow-auth', () => {
  if (!mainWindow) return;
  mainWindow.setMinimumSize(440, 720);
  mainWindow.setSize(440, 720, true);
  mainWindow.setResizable(false);
  mainWindow.center();
});

// ── Config ────────────────────────────────────────────────────────────────
// Always include the compiled-in API URL so the renderer can display/debug it
ipcMain.handle('config-get', () => ({ ...config, api_base_url: API_BASE_URL, app_version: app.getVersion() }));
ipcMain.handle('open-external', (_e, url) => shell.openExternal(url));
ipcMain.handle('check-for-update', () => new Promise(resolve => {
  const https = require('https');
  https.get('https://zeebroo.com/api/releases/latest', { headers: { Accept: 'application/json' } }, res => {
    let d = '';
    res.on('data', c => { d += c; });
    res.on('end', () => {
      try { resolve({ status: res.statusCode, body: JSON.parse(d) }); }
      catch (_) { resolve({ status: res.statusCode, body: null }); }
    });
  }).on('error', () => resolve({ status: 0, body: null }));
}));
ipcMain.handle('config-set', (_e, patch) => {
  config = { ...config, ...patch };
  saveConfig(config);
  return config;
});

// ── Receipt printing ──────────────────────────────────────────────────────
ipcMain.handle('print-receipt', (e) => {
  const win = BrowserWindow.fromWebContents(e.sender);
  if (!win) return { success: false };
  return new Promise(resolve => {
    win.webContents.print(
      { silent: false, printBackground: false },
      (success, failureReason) => resolve({ success, failureReason: failureReason || null })
    );
  });
});

// ── API proxy (avoids CORS in renderer) ──────────────────────────────────
const https = require('https');
const http  = require('http');

function apiRequest(method, path_, body, token, businessId, branchId) {
  return new Promise((resolve, reject) => {
    const base = API_BASE_URL.replace(/\/$/, '');
    const url  = new URL(base + path_);
    const isHttps = url.protocol === 'https:';
    const lib  = isHttps ? https : http;

    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (token)      headers['Authorization'] = `Bearer ${token}`;
    if (businessId) headers['X-Business-Id'] = String(businessId);
    if (branchId)   headers['X-Branch-Id']   = String(branchId);

    const payload = body ? JSON.stringify(body) : null;
    if (payload) headers['Content-Length'] = Buffer.byteLength(payload);

    const req = lib.request({
      hostname: url.hostname,
      port: url.port || (isHttps ? 443 : 80),
      path: url.pathname + url.search,
      method,
      headers,
      timeout: 120000,
    }, (res) => {
      let data = '';
      res.on('data', chunk => { data += chunk; });
      res.on('end', () => {
        try { resolve({ status: res.statusCode, body: JSON.parse(data) }); }
        catch (_) { resolve({ status: res.statusCode, body: data }); }
      });
    });

    req.on('timeout', () => { req.destroy(new Error('Request timed out after 120s')); });
    req.on('error', reject);
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

// Fetch a static JSON file from the configured server (for template loading)
ipcMain.handle('fetch-json', async (_e, url) => {
  return new Promise((resolve, reject) => {
    try {
      const parsed = new URL(url);
      const lib = parsed.protocol === 'https:' ? https : http;
      const req = lib.request({
        hostname: parsed.hostname,
        port:     parsed.port || (parsed.protocol === 'https:' ? 443 : 80),
        path:     parsed.pathname + parsed.search,
        method:   'GET',
        headers:  { 'Accept': 'application/json' },
      }, (res) => {
        let data = '';
        res.on('data', chunk => { data += chunk; });
        res.on('end', () => {
          try { resolve({ status: res.statusCode, body: JSON.parse(data) }); }
          catch (_) { resolve({ status: res.statusCode, body: data }); }
        });
      });
      req.on('error', (err) => resolve({ status: 0, body: { message: err.message } }));
      req.end();
    } catch (err) {
      resolve({ status: 0, body: { message: err.message } });
    }
  });
});

// ── Multipart file upload ─────────────────────────────────────────────────
const MIME_EXT = { jpg:'image/jpeg', jpeg:'image/jpeg', png:'image/png', gif:'image/gif', webp:'image/webp', svg:'image/svg+xml', pdf:'application/pdf' };
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
    const url  = new URL(base + apiPath);
    const lib  = url.protocol === 'https:' ? https : http;
    const boundary = 'PosBoundary' + Date.now();
    const fileName = path.basename(filePath);
    const body = buildMultipart(boundary, [{ fieldName: 'files[]', filePath, fileName, mime: extMime(filePath) }]);
    const headers = {
      'Content-Type': `multipart/form-data; boundary=${boundary}`,
      'Content-Length': body.length,
      'Accept': 'application/json',
    };
    if (config.token)       headers['Authorization'] = `Bearer ${config.token}`;
    if (config.business_id) headers['X-Business-Id'] = String(config.business_id);
    if (config.branch_id)   headers['X-Branch-Id']   = String(config.branch_id);
    return await new Promise((resolve, reject) => {
      const req = lib.request({ hostname: url.hostname, port: url.port || (url.protocol === 'https:' ? 443 : 80), path: url.pathname + url.search, method: 'POST', headers }, (res) => {
        let data = '';
        res.on('data', c => { data += c; });
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

// ── Design Studio editor window ───────────────────────────────────────────
ipcMain.handle('open-editor', (_e, design) => {
  editorDesign = design;

  if (editorWindow && !editorWindow.isDestroyed()) {
    editorDesign = design;
    editorWindow.webContents.send('design-changed', design);
    editorWindow.focus();
    return;
  }

  editorWindow = new BrowserWindow({
    width: 1440,
    height: 900,
    minWidth: 1024,
    minHeight: 640,
    frame: true,
    title: (design && design.title) ? design.title + ' — Design Studio' : 'Design Studio',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
    show: false,
  });

  editorWindow.loadFile(path.join(__dirname, 'renderer', 'editor.html'));

  editorWindow.once('ready-to-show', () => {
    editorWindow.show();
  });

  editorWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[editor:${prefix}] ${msg}`);
  });

  editorWindow.on('closed', () => {
    editorWindow = null;
    editorDesign = null;
  });
});

ipcMain.handle('get-editor-design', () => editorDesign);

// ── Render arbitrary HTML to a JPEG (used by Design Studio invoice import) ──
ipcMain.handle('render-html-to-jpeg', async (_e, { html, width, height }) => {
  const os = require('os');
  // Write to a temp file so embedded data: URIs load correctly and URL length is unlimited
  const tmpFile = path.join(os.tmpdir(), 'sbiz-inv-' + Date.now() + '.html');
  fs.writeFileSync(tmpFile, html, 'utf8');

  return new Promise((resolve) => {
    const cleanup = () => { try { fs.unlinkSync(tmpFile); } catch (_) {} };

    const win = new BrowserWindow({
      show: false,
      webPreferences: {
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: false,
      },
    });

    // Force the content area to be exactly width × height (no chrome offset)
    win.setContentSize(width, height);

    win.loadFile(tmpFile);

    win.webContents.once('did-finish-load', () => {
      // Wait for images (letterhead data: URI) and paint to complete
      setTimeout(() => {
        win.webContents.capturePage({ x: 0, y: 0, width, height })
          .then(image => {
            cleanup();
            // Resize to target px in case of HiDPI/Retina display
            const out = image.resize({ width, height, quality: 'best' });
            resolve(out.toJPEG(92).toString('base64'));
            win.destroy();
          })
          .catch(() => { cleanup(); resolve(null); win.destroy(); });
      }, 500);
    });

    win.webContents.on('did-fail-load', () => { cleanup(); resolve(null); win.destroy(); });
  });
});

// ── Automation Editor window ──────────────────────────────────────────────
ipcMain.handle('open-automation', (_e, flow) => {
  if (automationWindow && !automationWindow.isDestroyed()) {
    automationWindow.webContents.send('automation-flow-changed', flow);
    automationWindow.focus();
    return;
  }

  automationWindow = new BrowserWindow({
    width: 1440,
    height: 900,
    minWidth: 1100,
    minHeight: 680,
    frame: true,
    title: flow?.name ? flow.name + ' — Automation Editor' : 'Automation Editor',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
    show: false,
  });

  automationWindow._flowData = flow || null;
  automationWindow.loadFile(path.join(__dirname, 'renderer', 'automation.html'));

  automationWindow.once('ready-to-show', () => { automationWindow.show(); });

  automationWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[automation:${prefix}] ${msg}`);
  });

  automationWindow.on('closed', () => { automationWindow = null; });
});

ipcMain.handle('get-automation-flow', e => {
  const win = BrowserWindow.fromWebContents(e.sender);
  return win?._flowData || null;
});

// ── Quotation print window ────────────────────────────────────────────────
let printQuoteWindow = null;
let printQuoteData   = null;

ipcMain.handle('open-quote-print', (_e, data) => {
  printQuoteData = data;

  if (printQuoteWindow && !printQuoteWindow.isDestroyed()) {
    printQuoteWindow.webContents.send('quote-print-refresh', data);
    printQuoteWindow.focus();
    return;
  }

  printQuoteWindow = new BrowserWindow({
    width:     860,
    height:    1060,
    minWidth:  600,
    minHeight: 700,
    title:     'Print Quotation',
    webPreferences: {
      preload:          path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration:  false,
      sandbox:          false,
    },
    show: false,
  });

  printQuoteWindow.loadFile(path.join(__dirname, 'renderer', 'print-quote.html'));

  printQuoteWindow.once('ready-to-show', () => { printQuoteWindow.show(); });

  printQuoteWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[print-quote:${prefix}] ${msg}`);
  });

  printQuoteWindow.on('closed', () => {
    printQuoteWindow = null;
    printQuoteData   = null;
  });
});

ipcMain.handle('get-quote-print-data', () => printQuoteData);

// ── Kitchen Display window ────────────────────────────────────────────────
ipcMain.handle('open-kds', () => {
  if (kdsWindow && !kdsWindow.isDestroyed()) { kdsWindow.focus(); return; }

  const { screen } = require('electron');
  const displays   = screen.getAllDisplays();
  const secondary  = displays.find(d => d.id !== screen.getPrimaryDisplay().id);
  const target     = secondary || screen.getPrimaryDisplay();
  const { x, y, width, height } = target.workArea;

  kdsWindow = new BrowserWindow({
    x, y, width, height,
    minWidth:  800,
    minHeight: 500,
    frame:     false,
    backgroundColor: '#0f172a',
    webPreferences: {
      preload:          path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration:  false,
      sandbox:          false,
    },
    show: false,
  });

  kdsWindow.loadFile(path.join(__dirname, 'renderer', 'kitchen.html'));
  kdsWindow.once('ready-to-show', () => {
    kdsWindow.show();
    if (secondary) kdsWindow.setFullScreen(true);
  });
  kdsWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[kds:${prefix}] ${msg}`);
  });
  kdsWindow.on('closed', () => { kdsWindow = null; });
});

ipcMain.on('kds-minimize',   e => { BrowserWindow.fromWebContents(e.sender)?.minimize(); });
ipcMain.on('kds-close',      e => { BrowserWindow.fromWebContents(e.sender)?.close(); });
ipcMain.on('kds-fullscreen', (e, flag) => { BrowserWindow.fromWebContents(e.sender)?.setFullScreen(flag); });
ipcMain.handle('kds-is-fullscreen', e => BrowserWindow.fromWebContents(e.sender)?.isFullScreen() ?? false);
