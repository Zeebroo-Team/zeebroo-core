'use strict';

const path = require('path');
const fs   = require('fs');
const { app, BrowserWindow, ipcMain, dialog } = require('electron');
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
let editorWindow  = null;
let editorDesign  = null;
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
ipcMain.handle('config-get', () => ({ ...config, api_base_url: API_BASE_URL }));
ipcMain.handle('config-set', (_e, patch) => {
  config = { ...config, ...patch };
  saveConfig(config);
  return config;
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
    }, (res) => {
      let data = '';
      res.on('data', chunk => { data += chunk; });
      res.on('end', () => {
        try { resolve({ status: res.statusCode, body: JSON.parse(data) }); }
        catch (_) { resolve({ status: res.statusCode, body: data }); }
      });
    });

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
