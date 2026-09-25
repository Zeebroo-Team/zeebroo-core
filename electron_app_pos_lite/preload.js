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

  // Laravel POS API (Modules/Pos/routes/api.php, prefix /api/v1/pos)
  apiRequest: (method, path, body) => ipcRenderer.invoke('api-request', { method, path, body }),

  // File upload (product images) + native "choose file" dialog
  apiUpload: (apiPath, filePath) => ipcRenderer.invoke('api-upload', { path: apiPath, filePath }),
  showOpenDialog: (options) => ipcRenderer.invoke('show-open-dialog', options),
});
