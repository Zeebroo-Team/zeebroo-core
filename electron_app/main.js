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
  const http  = require('http');
  const base  = API_BASE_URL.replace(/\/$/, '');
  const url   = new URL(base + '/api/releases/latest');
  const lib   = url.protocol === 'https:' ? https : http;
  lib.get(url.toString(), { headers: { Accept: 'application/json' } }, res => {
    let d = '';
    res.on('data', c => { d += c; });
    res.on('end', () => {
      try { resolve({ status: res.statusCode, body: JSON.parse(d) }); }
      catch (_) { resolve({ status: res.statusCode, body: null }); }
    });
  }).on('error', () => resolve({ status: 0, body: null }));
}));

// ── Update download with progress ─────────────────────────────────────────
ipcMain.handle('download-update', async (_e, { url, filename }) => {
  const https  = require('https');
  const http   = require('http');
  const os     = require('os');

  const dest = path.join(app.getPath('downloads'), filename);
  const lib  = url.startsWith('https') ? https : http;

  return new Promise((resolve) => {
    const file = fs.createWriteStream(dest);

    const request = lib.get(url, (res) => {
      if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        // Follow redirect
        file.close(() => fs.unlink(dest, () => {}));
        ipcMain.emit('download-update', _e, { url: res.headers.location, filename });
        resolve(null); // caller retries via redirect — handled client-side
        return;
      }
      const total = parseInt(res.headers['content-length'] || '0', 10);
      let received = 0;
      res.on('data', chunk => {
        received += chunk.length;
        if (total > 0) {
          const pct = Math.round((received / total) * 100);
          _e.sender.send('download-progress', pct);
        }
        file.write(chunk);
      });
      res.on('end', () => {
        file.end();
        resolve({ path: dest });
      });
    });

    request.on('error', (err) => {
      file.close(() => fs.unlink(dest, () => {}));
      resolve({ error: err.message });
    });
  });
});

ipcMain.handle('open-path', (_e, filePath) => shell.openPath(filePath));
ipcMain.handle('show-in-folder', (_e, filePath) => shell.showItemInFolder(filePath));
ipcMain.handle('restart-app', () => { app.relaunch(); app.exit(0); });
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

ipcMain.handle('read-text-file', async (_e, filePath) => {
  try {
    return { ok: true, content: fs.readFileSync(filePath, 'utf8') };
  } catch (err) {
    return { ok: false, error: err.message };
  }
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

// ── Build a minimal single-page PDF containing one JPEG image ─────────────
function buildPdfFromJpeg(jpegBuf, imgW, imgH, pageWidthPt, pageHeightPt) {
  const pW = pageWidthPt.toFixed(2);
  const pH = pageHeightPt.toFixed(2);
  const cs = `q ${pW} 0 0 ${pH} 0 0 cm /Im0 Do Q`;

  const chunks = [];
  const off    = {};
  let pos = 0;

  const wr = (s) => {
    const b = Buffer.isBuffer(s) ? s : Buffer.from(s, 'binary');
    chunks.push(b);
    pos += b.length;
  };
  const obj = (n, body) => {
    off[n] = pos;
    wr(`${n} 0 obj\n${body}\nendobj\n`);
  };

  wr('%PDF-1.4\n');
  obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
  obj(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
  obj(3, `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pW} ${pH}] /Contents 4 0 R /Resources << /XObject << /Im0 5 0 R >> >> >>`);
  obj(4, `<< /Length ${cs.length} >>\nstream\n${cs}\nendstream`);

  off[5] = pos;
  wr(`5 0 obj\n<< /Type /XObject /Subtype /Image /Width ${imgW} /Height ${imgH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${jpegBuf.length} >>\nstream\n`);
  wr(jpegBuf);
  wr('\nendstream\nendobj\n');

  const xref = pos;
  wr('xref\n0 6\n');
  wr('0000000000 65535 f \n');
  [1, 2, 3, 4, 5].forEach(n => wr(String(off[n]).padStart(10, '0') + ' 00000 n \n'));
  wr(`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`);

  return Buffer.concat(chunks);
}

// ── Render a design (canvas_json) to a base64 PDF ─────────────────────────
ipcMain.handle('render-design-to-pdf', async (_e, { canvasJson, width, height }) => {
  const os = require('os');

  const w = width  || 800;
  const h = height || 600;

  const fabricSrc = path.join(__dirname, 'renderer', 'js', 'fabric.min.js');
  if (!fs.existsSync(fabricSrc)) return null;
  const fabricCode = fs.readFileSync(fabricSrc, 'utf8');

  // Escape </script> so the HTML parser doesn't close the script block early
  const canvasData = JSON.stringify(canvasJson || {})
    .replace(/<\/script>/gi, '<\\/script>');

  const html = `<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; overflow:hidden }
  html, body { width:${w}px; height:${h}px; background:#fff }
  canvas { display:block }
</style>
</head>
<body>
<canvas id="c" width="${w}" height="${h}"></canvas>
<script>${fabricCode}</script>
<script>
try {
  var raw  = ${canvasData};
  var data = typeof raw === 'string' ? JSON.parse(raw) : raw;
  var c = new fabric.StaticCanvas('c');
  c.setWidth(${w});
  c.setHeight(${h});
  c.loadFromJSON(data, function () {
    c.renderAll();
    document.title = '__ready__';
  });
} catch (e) {
  document.title = '__error__';
}
</script>
</body></html>`;

  const tmpFile = path.join(os.tmpdir(), 'sbiz-ds-' + Date.now() + '.html');
  fs.writeFileSync(tmpFile, html, 'utf8');

  return new Promise((resolve) => {
    const cleanup = () => { try { fs.unlinkSync(tmpFile); } catch (_) {} };

    let done = false;
    const finish = (val) => {
      if (done) return;
      done = true;
      cleanup();
      if (!win.isDestroyed()) win.destroy();
      resolve(val);
    };

    const win = new BrowserWindow({
      show: false,
      webPreferences: { contextIsolation: true, nodeIntegration: false, sandbox: false },
    });
    win.setContentSize(w, h);
    win.loadFile(tmpFile);

    const exportPdf = () => {
      // Read pixels directly from the canvas DOM element via executeJavaScript.
      // capturePage/printToPDF both rely on the GPU compositor which doesn't flush
      // canvas textures for hidden windows on macOS — giving a blank result.
      // canvas.toDataURL() reads from the canvas buffer in JS memory, bypassing
      // the compositor entirely, so it always returns the actual rendered pixels.
      // Composite the fabric canvas onto a white background before JPEG export,
      // otherwise transparent pixels become black in JPEG (no alpha channel).
      win.webContents.executeJavaScript(
        "(function(){ var src=document.getElementById('c'); if(!src) return null; var t=document.createElement('canvas'); t.width=src.width; t.height=src.height; var ctx=t.getContext('2d'); ctx.fillStyle='#ffffff'; ctx.fillRect(0,0,t.width,t.height); ctx.drawImage(src,0,0); return t.toDataURL('image/jpeg',0.92); })()"
      ).then(dataUrl => {
        if (typeof dataUrl !== 'string' || !dataUrl.startsWith('data:image/jpeg;base64,')) {
          finish(null);
          return;
        }
        const jpegBuf = Buffer.from(dataUrl.slice('data:image/jpeg;base64,'.length), 'base64');
        const pW = w * 72 / 96;
        const pH = h * 72 / 96;
        const pdfBuf = buildPdfFromJpeg(jpegBuf, w, h, pW, pH);
        finish(pdfBuf.toString('base64'));
      }).catch(() => finish(null));
    };

    // __ready__ = fabric rendered; __error__ = something threw in the page
    win.webContents.on('page-title-updated', (_ev, title) => {
      if      (title === '__ready__') setTimeout(exportPdf, 150);
      else if (title === '__error__') finish(null);
    });

    // Hard timeout — fail cleanly; never export a blank page
    win.webContents.once('did-finish-load', () => {
      setTimeout(() => { if (!done) finish(null); }, 5000);
    });

    win.webContents.on('did-fail-load', () => finish(null));
  });
});

// ── Render a Fabric.js canvas_json to a high-res PNG data URL ─────────────
ipcMain.handle('render-canvas-to-dataurl', async (_e, { canvasJson, width, height, scale }) => {
  const w  = width  || 794;
  const h  = height || 1123;
  // Render at sc× resolution so the embedded image is sharp (default 2×)
  const sc = Math.max(1, Math.min(4, parseFloat(scale) || 2));
  const pw = Math.round(w * sc); // pixel canvas width
  const ph = Math.round(h * sc); // pixel canvas height

  const fabricSrc = path.join(__dirname, 'renderer', 'js', 'fabric.min.js');
  if (!fs.existsSync(fabricSrc)) return null;
  const fabricCode = fs.readFileSync(fabricSrc, 'utf8');

  const canvasData = JSON.stringify(canvasJson || {})
    .replace(/<\/script>/gi, '<\\/script>');

  const html = `<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>*{margin:0;padding:0;box-sizing:border-box;overflow:hidden}html,body{width:${w}px;height:${h}px;background:transparent}canvas{display:block;width:${w}px;height:${h}px}</style>
</head><body>
<canvas id="c" width="${pw}" height="${ph}"></canvas>
<script>${fabricCode}</script>
<script>
try{
  var raw=${canvasData};
  var data=typeof raw==='string'?JSON.parse(raw):raw;
  var c=new fabric.Canvas('c',{width:${pw},height:${ph},enableRetinaScaling:false});
  c.loadFromJSON(data,function(){
    c.setZoom(${sc});
    c.setWidth(${pw});
    c.setHeight(${ph});
    c.renderAll();
    document.title='__ready__';
  });
}catch(e){document.title='__error__';}
</script>
</body></html>`;

  const tmpFile = path.join(require('os').tmpdir(), 'sbiz-lh-' + Date.now() + '.html');
  fs.writeFileSync(tmpFile, html, 'utf8');

  return new Promise((resolve) => {
    const cleanup = () => { try { fs.unlinkSync(tmpFile); } catch (_) {} };
    let done = false;
    const finish = (val) => {
      if (done) return;
      done = true;
      cleanup();
      if (!win.isDestroyed()) win.destroy();
      resolve(val);
    };

    const win = new BrowserWindow({
      show: false,
      webPreferences: { contextIsolation: true, nodeIntegration: false, sandbox: false },
    });
    win.setContentSize(w, h);
    win.loadFile(tmpFile);

    const getDataUrl = () => {
      win.webContents.executeJavaScript(
        "(function(){var c=document.getElementById('c');if(!c)return null;return c.toDataURL('image/png');})()"
      ).then(dataUrl => {
        finish(typeof dataUrl === 'string' && dataUrl.startsWith('data:image/png;base64,') ? dataUrl : null);
      }).catch(() => finish(null));
    };

    win.webContents.on('page-title-updated', (_ev, title) => {
      if      (title === '__ready__') setTimeout(getDataUrl, 200);
      else if (title === '__error__') finish(null);
    });
    win.webContents.once('did-finish-load', () => setTimeout(() => { if (!done) finish(null); }, 8000));
    win.webContents.on('did-fail-load', () => finish(null));
  });
});

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

// ── Invoice print window (template-based layout) ──────────────────────────
let printInvWindow = null;
let printInvData   = null;

ipcMain.handle('open-invoice-print', (_e, data) => {
  printInvData = data;

  if (printInvWindow && !printInvWindow.isDestroyed()) {
    printInvWindow.webContents.send('invoice-print-refresh', data);
    printInvWindow.focus();
    return;
  }

  printInvWindow = new BrowserWindow({
    width:     900,
    height:    1060,
    minWidth:  620,
    minHeight: 700,
    title:     'Print Invoice',
    webPreferences: {
      preload:          path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration:  false,
      sandbox:          false,
    },
    show: false,
  });

  printInvWindow.loadFile(path.join(__dirname, 'renderer', 'print-invoice.html'));
  printInvWindow.once('ready-to-show', () => { printInvWindow.show(); });
  printInvWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[print-invoice:${prefix}] ${msg}`);
  });
  printInvWindow.on('closed', () => { printInvWindow = null; printInvData = null; });
});

ipcMain.handle('get-invoice-print-data', () => printInvData);

// ── Purchase Order print window ───────────────────────────────────────────
let printPoWindow = null;
let printPoData   = null;

ipcMain.handle('open-po-print', (_e, data) => {
  printPoData = data;

  if (printPoWindow && !printPoWindow.isDestroyed()) {
    printPoWindow.webContents.send('po-print-refresh', data);
    printPoWindow.focus();
    return;
  }

  printPoWindow = new BrowserWindow({
    width:     860,
    height:    1060,
    minWidth:  600,
    minHeight: 700,
    title:     'Print Purchase Order',
    webPreferences: {
      preload:          path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration:  false,
      sandbox:          false,
    },
    show: false,
  });

  printPoWindow.loadFile(path.join(__dirname, 'renderer', 'print-po.html'));
  printPoWindow.once('ready-to-show', () => { printPoWindow.show(); });
  printPoWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[print-po:${prefix}] ${msg}`);
  });
  printPoWindow.on('closed', () => { printPoWindow = null; printPoData = null; });
});

ipcMain.handle('get-po-print-data', () => printPoData);

// ── Goods Receive Note print window ──────────────────────────────────────
let printGrnWindow = null;
let printGrnData   = null;

ipcMain.handle('open-grn-print', (_e, data) => {
  printGrnData = data;

  if (printGrnWindow && !printGrnWindow.isDestroyed()) {
    printGrnWindow.webContents.send('grn-print-refresh', data);
    printGrnWindow.focus();
    return;
  }

  printGrnWindow = new BrowserWindow({
    width:     860,
    height:    1060,
    minWidth:  600,
    minHeight: 700,
    title:     'Print Goods Receive Note',
    webPreferences: {
      preload:          path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration:  false,
      sandbox:          false,
    },
    show: false,
  });

  printGrnWindow.loadFile(path.join(__dirname, 'renderer', 'print-grn.html'));
  printGrnWindow.once('ready-to-show', () => { printGrnWindow.show(); });
  printGrnWindow.webContents.on('console-message', (_e, level, msg) => {
    const prefix = ['VERBOSE','INFO','WARN','ERROR'][level] || level;
    console.log(`[print-grn:${prefix}] ${msg}`);
  });
  printGrnWindow.on('closed', () => { printGrnWindow = null; printGrnData = null; });
});

ipcMain.handle('get-grn-print-data', () => printGrnData);

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
