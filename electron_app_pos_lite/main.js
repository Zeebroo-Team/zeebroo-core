'use strict';

const path = require('path');
const fs = require('fs');
const http = require('http');
const https = require('https');
const { app, BrowserWindow, ipcMain } = require('electron');
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

  mainWindow.loadFile(path.join(__dirname, 'renderer', 'dashboard.html'));
  mainWindow.once('ready-to-show', () => mainWindow.show());
  mainWindow.on('closed', () => { mainWindow = null; });
}

app.whenReady().then(() => {
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
