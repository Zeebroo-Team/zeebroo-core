'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  // Window controls
  minimize:     () => ipcRenderer.send('window-minimize'),
  maximize:     () => ipcRenderer.send('window-maximize'),
  close:        () => ipcRenderer.send('window-close'),
  fullscreen:   (flag) => ipcRenderer.send('window-fullscreen', flag),
  expandWindow: () => ipcRenderer.send('window-expand'),
  wideAuth:     () => ipcRenderer.send('window-wide-auth'),
  narrowAuth:   () => ipcRenderer.send('window-narrow-auth'),
  onWindowState: (cb) => ipcRenderer.on('window-state', (_e, state) => cb(state)),

  // Platform
  platform: process.platform,

  // Config
  getConfig: ()       => ipcRenderer.invoke('config-get'),
  setConfig: (patch)  => ipcRenderer.invoke('config-set', patch),

  // API
  apiRequest:      (method, path, body) => ipcRenderer.invoke('api-request', { method, path, body }),
  apiUpload:       (apiPath, filePath)  => ipcRenderer.invoke('api-upload', { path: apiPath, filePath }),
  showOpenDialog:  (options)            => ipcRenderer.invoke('show-open-dialog', options),
  checkForUpdate:  ()                   => ipcRenderer.invoke('check-for-update'),
  openExternal:    (url)                => ipcRenderer.invoke('open-external', url),

  // Design Studio editor window
  openEditor:      (design) => ipcRenderer.invoke('open-editor', design),
  getEditorDesign: ()       => ipcRenderer.invoke('get-editor-design'),

  // Quotation print window
  openQuotePrint:    (data) => ipcRenderer.invoke('open-quote-print', data),
  getQuotePrintData: ()     => ipcRenderer.invoke('get-quote-print-data'),
  onQuotePrintRefresh: (cb) => ipcRenderer.on('quote-print-refresh', (_e, data) => cb(data)),

  // Kitchen Display window
  openKds:          ()       => ipcRenderer.invoke('open-kds'),
  kdsMinimize:      ()       => ipcRenderer.send('kds-minimize'),
  kdsClose:         ()       => ipcRenderer.send('kds-close'),
  kdsFullscreen:    (flag)   => ipcRenderer.send('kds-fullscreen', flag),
  kdsIsFullScreen:  ()       => ipcRenderer.invoke('kds-is-fullscreen'),
});
