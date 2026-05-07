// Preload — exposes a minimal IPC bridge ke renderer (Blade page) sebagai
// window.kioskBridge. Sandbox-safe: hanya wrap ipcRenderer.

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('kioskBridge', {
  enterTimerMode: (payload) => ipcRenderer.send('kiosk:enter-timer', payload),
  exitTimerMode: () => ipcRenderer.send('kiosk:exit-timer'),
  shutdown: () => ipcRenderer.send('kiosk:shutdown'),
  sleep: () => ipcRenderer.send('kiosk:sleep'),
  openWifiSettings: () => ipcRenderer.send('kiosk:open-wifi'),

  // Offline support
  getNetworkStatus: () => ipcRenderer.invoke('network:status'),
  onNetworkChange: (cb) => {
    const handler = (_e, status) => cb(status);
    ipcRenderer.on('network:change', handler);
    return () => ipcRenderer.removeListener('network:change', handler);
  },

  queueOfflineCheckin: (payload) => ipcRenderer.invoke('queue:checkin', payload),
  queueOfflineLogout: (payload) => ipcRenderer.invoke('queue:logout', payload),
  getQueueSize: () => ipcRenderer.invoke('queue:size'),
  flushQueue: () => ipcRenderer.invoke('queue:flush'),
});
