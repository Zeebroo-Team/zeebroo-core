'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  // Config (token, business_id, user) persisted to userData/config.json
  getConfig: () => ipcRenderer.invoke('config-get'),
  setConfig: (patch) => ipcRenderer.invoke('config-set', patch),

  // Window flow
  authSuccess: () => ipcRenderer.invoke('auth-success'), // auth window -> main window
  logout: () => ipcRenderer.invoke('logout'),            // main window -> auth window
  restartApp: () => ipcRenderer.invoke('app-restart'),   // quit + relaunch the whole app
  toggleFullscreen: () => ipcRenderer.invoke('toggle-fullscreen'), // F11 shortcut

  // Onboarding payment step: opens Stripe Checkout in the system browser,
  // then listens for the zeebroopos://payment deep link it returns via (see main.js).
  openExternal: (url) => ipcRenderer.invoke('open-external', url),
  onPaymentDeepLink: (cb) => ipcRenderer.on('payment-deep-link', (_e, payload) => cb(payload)),

  // Laravel POS API (Modules/Pos/routes/api.php, prefix /api/v1/pos)
  apiRequest: (method, path, body) => ipcRenderer.invoke('api-request', { method, path, body }),

  // Binary download (e.g. a billing receipt PDF) -> native "Save As" dialog
  downloadFile: (path, suggestedFilename) => ipcRenderer.invoke('api-download-file', { path, suggestedFilename }),

  // Sale receipt / invoice: open the OS print dialog on a built HTML document,
  // or render it to a PDF and let the user save it (js/pos.js sale-completed modal).
  printHtml: (html) => ipcRenderer.invoke('print-html', { html }),
  savePdf: (html, suggestedFilename) => ipcRenderer.invoke('save-html-as-pdf', { html, suggestedFilename }),

  // App updates (js/navbar.js "Check for updates") — latest Lite release from
  // /api/releases/latest?app=lite, installer download with % progress.
  platform: process.platform,
  checkForUpdate: () => ipcRenderer.invoke('check-for-update'),
  downloadUpdate: (opts) => ipcRenderer.invoke('download-update', opts),
  onDownloadProgress: (cb) => {
    const handler = (_e, pct) => cb(pct);
    ipcRenderer.on('download-progress', handler);
    return () => ipcRenderer.removeListener('download-progress', handler);
  },
  openPath: (filePath) => ipcRenderer.invoke('open-path', filePath),
  showInFolder: (filePath) => ipcRenderer.invoke('show-in-folder', filePath),
  quit: () => ipcRenderer.invoke('app-quit'),

  // File upload (product images) + native "choose file" dialog
  apiUpload: (apiPath, filePath) => ipcRenderer.invoke('api-upload', { path: apiPath, filePath }),
  showOpenDialog: (options) => ipcRenderer.invoke('show-open-dialog', options),
});
