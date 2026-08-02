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
  printReceipt:    ()                   => ipcRenderer.invoke('print-receipt'),
  fetchJson:       (url)                => ipcRenderer.invoke('fetch-json', url),
  apiUpload:       (apiPath, filePath)  => ipcRenderer.invoke('api-upload', { path: apiPath, filePath }),
  showOpenDialog:  (options)            => ipcRenderer.invoke('show-open-dialog', options),
  checkForUpdate:   ()                    => ipcRenderer.invoke('check-for-update'),
  downloadUpdate:   (opts)               => ipcRenderer.invoke('download-update', opts),
  onDownloadProgress: (cb)              => ipcRenderer.on('download-progress', (_e, pct) => cb(pct)),
  openPath:         (p)                  => ipcRenderer.invoke('open-path', p),
  showInFolder:     (p)                  => ipcRenderer.invoke('show-in-folder', p),
  restartApp:       ()                   => ipcRenderer.invoke('restart-app'),
  openExternal:    (url)                => ipcRenderer.invoke('open-external', url),

  // Design Studio editor window
  openEditor:        (design)            => ipcRenderer.invoke('open-editor', design),
  getEditorDesign:   ()                  => ipcRenderer.invoke('get-editor-design'),
  renderHtmlToJpeg:     (html, w, h)       => ipcRenderer.invoke('render-html-to-jpeg', { html, width: w, height: h }),
  renderDesignToPdf:    (canvasJson, w, h) => ipcRenderer.invoke('render-design-to-pdf', { canvasJson, width: w, height: h }),
  renderCanvasToDataUrl:(canvasJson, w, h) => ipcRenderer.invoke('render-canvas-to-dataurl', { canvasJson, width: w, height: h }),

  // Automation Editor window
  openAutomation:      (flow) => ipcRenderer.invoke('open-automation', flow),
  getAutomationFlow:   ()     => ipcRenderer.invoke('get-automation-flow'),
  onAutomationChanged: (cb)   => ipcRenderer.on('automation-flow-changed', (_e, flow) => cb(flow)),

  // Quotation print window
  openQuotePrint:    (data) => ipcRenderer.invoke('open-quote-print', data),
  getQuotePrintData: ()     => ipcRenderer.invoke('get-quote-print-data'),
  onQuotePrintRefresh: (cb) => ipcRenderer.on('quote-print-refresh', (_e, data) => cb(data)),

  // Invoice print window (renders full template layout)
  openInvoicePrint:     (data) => ipcRenderer.invoke('open-invoice-print', data),
  getInvoicePrintData:  ()     => ipcRenderer.invoke('get-invoice-print-data'),
  onInvoicePrintRefresh:(cb)   => ipcRenderer.on('invoice-print-refresh', (_e, data) => cb(data)),

  // Purchase Order print window
  openPoPrint:       (data) => ipcRenderer.invoke('open-po-print', data),
  getPoPrintData:    ()     => ipcRenderer.invoke('get-po-print-data'),
  onPoPrintRefresh:  (cb)   => ipcRenderer.on('po-print-refresh', (_e, data) => cb(data)),

  // Goods Receive Note print window
  openGrnPrint:      (data) => ipcRenderer.invoke('open-grn-print', data),
  getGrnPrintData:   ()     => ipcRenderer.invoke('get-grn-print-data'),
  onGrnPrintRefresh: (cb)   => ipcRenderer.on('grn-print-refresh', (_e, data) => cb(data)),

  // Kitchen Display window
  openKds:          ()       => ipcRenderer.invoke('open-kds'),
  kdsMinimize:      ()       => ipcRenderer.send('kds-minimize'),
  kdsClose:         ()       => ipcRenderer.send('kds-close'),
  kdsFullscreen:    (flag)   => ipcRenderer.send('kds-fullscreen', flag),
  kdsIsFullScreen:  ()       => ipcRenderer.invoke('kds-is-fullscreen'),
});
