// Main entry — kiosk shell + heartbeat + auto-update.
// Goal: minimal RAM/CPU. Tidak ada framework UI di renderer; satu BrowserWindow.

const { app, BrowserWindow, Menu, ipcMain, globalShortcut } = require('electron');
const path = require('path');

const { SERVER_BASE, readConfig, writeConfig, clearConfig } = require('./config');
const { HeartbeatService } = require('./heartbeat');
const { startAutoUpdater } = require('./auto-update');

// Single instance lock — hindari dobel app jalan.
const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
  process.exit(0);
}

// Optimasi RAM/CPU: nonaktifkan GPU hardware acceleration karena halaman cuma HTML/CSS.
app.commandLine.appendSwitch('disable-gpu');
app.commandLine.appendSwitch('disable-software-rasterizer');
app.disableHardwareAcceleration();

// Hapus default menu (hemat memory + cegah keyboard shortcut bawaan).
Menu.setApplicationMenu(null);

let mainWindow = null;
let pairingWindow = null;
let heartbeat = null;

function createPairingWindow() {
  pairingWindow = new BrowserWindow({
    width: 420,
    height: 380,
    resizable: false,
    minimizable: false,
    maximizable: false,
    autoHideMenuBar: true,
    title: 'Pairing Kiosk',
    webPreferences: {
      contextIsolation: false,
      nodeIntegration: true,
      backgroundThrottling: true,
      spellcheck: false,
      devTools: false,
    },
  });
  pairingWindow.loadFile(path.join(__dirname, 'pairing.html'));
}

function createKioskWindow(config) {
  mainWindow = new BrowserWindow({
    fullscreen: true,
    kiosk: true,
    frame: false,
    autoHideMenuBar: true,
    title: 'Warehouse FTV Kiosk',
    backgroundColor: '#000000',
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      backgroundThrottling: true,
      spellcheck: false,
      sandbox: true,
      devTools: false,
    },
  });

  mainWindow.loadURL(config.kiosk_url);

  // Cegah Alt+F4 menutup app
  mainWindow.on('close', (e) => {
    e.preventDefault();
  });

  // Cegah opens new windows
  mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));

  // Auto reload kalau halaman gagal load (server down sementara)
  mainWindow.webContents.on('did-fail-load', () => {
    setTimeout(() => {
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.loadURL(config.kiosk_url).catch(() => {});
      }
    }, 5000);
  });
}

// IPC untuk pairing window
ipcMain.handle('pairing:server-base', () => SERVER_BASE);

ipcMain.handle('pairing:submit', async (_e, code) => {
  try {
    const res = await fetch(SERVER_BASE + '/api/kiosk/pair', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ code }),
    });
    if (!res.ok) {
      const errBody = await res.json().catch(() => ({}));
      return { ok: false, error: errBody.error || ('HTTP ' + res.status) };
    }
    const data = await res.json();
    writeConfig({
      slug: data.slug,
      token: data.token,
      kiosk_url: data.kiosk_url,
      heartbeat_url: data.heartbeat_url,
      update_url: data.update_url,
      heartbeat_interval: data.heartbeat_interval,
      running_apps_whitelist: data.running_apps_whitelist || [],
      computer_name: data.computer_name,
      paired_at: new Date().toISOString(),
    });
    return { ok: true };
  } catch (err) {
    return { ok: false, error: err.message };
  }
});

ipcMain.handle('pairing:relaunch', () => {
  app.relaunch();
  app.exit(0);
});

app.whenReady().then(() => {
  const config = readConfig();

  if (!config || !config.token || !config.kiosk_url) {
    createPairingWindow();
    return;
  }

  createKioskWindow(config);

  // Heartbeat di main process — tidak terganggu kalau renderer crash
  heartbeat = new HeartbeatService(config);
  heartbeat.start();

  // Auto-updater
  if (config.update_url) {
    startAutoUpdater(config.update_url);
  }
});

app.on('window-all-closed', () => {
  // Di kiosk mode tidak ada window-all-closed dalam kondisi normal — tapi guard aja
  if (heartbeat) heartbeat.stop();
  app.quit();
});

app.on('will-quit', () => {
  globalShortcut.unregisterAll();
});
