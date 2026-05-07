// Main entry — kiosk shell + heartbeat + auto-update + offline queue + network monitor.

const { app, BrowserWindow, Menu, ipcMain, globalShortcut } = require('electron');
const path = require('path');
const { exec } = require('child_process');

const { SERVER_BASE, readConfig, writeConfig, clearConfig } = require('./config');
const { HeartbeatService } = require('./heartbeat');
const { startAutoUpdater } = require('./auto-update');
const { NetworkMonitor } = require('./network');
const offlineQueue = require('./offline-queue');

const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
  process.exit(0);
}

app.commandLine.appendSwitch('disable-gpu');
app.commandLine.appendSwitch('disable-software-rasterizer');
app.disableHardwareAcceleration();
Menu.setApplicationMenu(null);

let mainWindow = null;
let pairingWindow = null;
let adminWindow = null;
let heartbeat = null;
let network = null;
let kioskConfig = null;
let timerMode = false;

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
    skipTaskbar: true,
    autoHideMenuBar: true,
    closable: false,
    title: 'Warehouse FTV Kiosk',
    backgroundColor: '#000000',
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      backgroundThrottling: true,
      spellcheck: false,
      sandbox: false,
      devTools: false,
      preload: path.join(__dirname, 'preload.js'),
    },
  });

  mainWindow.setAlwaysOnTop(true, 'screen-saver');
  mainWindow.loadURL(config.kiosk_url);

  mainWindow.on('close', (e) => { e.preventDefault(); });
  mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
  mainWindow.webContents.on('did-fail-load', () => {
    setTimeout(() => {
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.loadURL(config.kiosk_url).catch(() => {});
      }
    }, 5000);
  });
}

// ============= Shortcut blocking =============

const BLOCKED_SHORTCUTS = [
  'Alt+Tab', 'Alt+F4', 'Alt+Esc', 'Alt+Space',
  'Ctrl+Esc', 'Ctrl+Shift+Esc',
  'Super+L', 'Super+D', 'Super+E', 'Super+R', 'Super+Tab', 'Super+S', 'Super+A',
  'F11',
];

function registerBlockedShortcuts() {
  for (const accel of BLOCKED_SHORTCUTS) {
    try { globalShortcut.register(accel, () => {}); } catch (_) {}
  }
}

function registerAdminShortcut() {
  try { globalShortcut.register('Ctrl+Shift+W', openAdminCloseWindow); } catch (_) {}
}

// ============= Admin close popup =============

function openAdminCloseWindow() {
  if (adminWindow && !adminWindow.isDestroyed()) { adminWindow.focus(); return; }
  adminWindow = new BrowserWindow({
    width: 380, height: 240,
    resizable: false, minimizable: false, maximizable: false,
    frame: false, alwaysOnTop: true, skipTaskbar: true,
    parent: mainWindow ?? undefined, modal: !!mainWindow,
    title: 'Admin Close',
    webPreferences: { contextIsolation: false, nodeIntegration: true, devTools: false },
  });
  adminWindow.loadFile(path.join(__dirname, 'admin-close.html'));
  adminWindow.on('closed', () => { adminWindow = null; });
}

ipcMain.handle('admin:verify-pin', (_e, pin) => {
  const expected = (heartbeat && heartbeat.getAdminPin && heartbeat.getAdminPin()) || (kioskConfig && kioskConfig.admin_pin) || '9999';
  return String(pin) === String(expected);
});

ipcMain.on('admin:close-confirmed', () => {
  if (adminWindow && !adminWindow.isDestroyed()) adminWindow.close();
  forceQuit();
});

ipcMain.on('admin:close-cancelled', () => {
  if (adminWindow && !adminWindow.isDestroyed()) adminWindow.close();
});

ipcMain.on('admin:unpair', () => {
  if (adminWindow && !adminWindow.isDestroyed()) adminWindow.close();
  unpairAndRestart();
});

function unpairAndRestart() {
  if (heartbeat) heartbeat.stop();
  if (network) network.stop();
  globalShortcut.unregisterAll();
  clearConfig();
  app.relaunch();
  app.exit(0);
}

function forceQuit() {
  globalShortcut.unregisterAll();
  if (heartbeat) heartbeat.stop();
  if (network) network.stop();
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.removeAllListeners('close');
    mainWindow.destroy();
  }
  app.exit(0);
}

// ============= Floating-timer mode =============

ipcMain.on('kiosk:enter-timer', () => {
  if (!mainWindow || mainWindow.isDestroyed() || timerMode) return;
  timerMode = true;
  mainWindow.setKiosk(false);
  mainWindow.setFullScreen(false);
  mainWindow.setAlwaysOnTop(true, 'floating');
  mainWindow.setSkipTaskbar(true);
  mainWindow.setResizable(true);
  mainWindow.setMovable(true);
  mainWindow.setMinimumSize(220, 160);
  mainWindow.setSize(320, 220);
  mainWindow.setPosition(40, 40);
});

ipcMain.on('kiosk:exit-timer', () => {
  if (!mainWindow || mainWindow.isDestroyed()) return;
  timerMode = false;
  mainWindow.setAlwaysOnTop(true, 'screen-saver');
  mainWindow.setKiosk(true);
  mainWindow.setFullScreen(true);
  mainWindow.setResizable(false);
});

// ============= Power: shutdown, sleep, wifi =============

ipcMain.on('kiosk:shutdown', () => {
  if (process.platform === 'win32') exec('shutdown /s /t 1', () => {});
  else if (process.platform === 'darwin') exec('osascript -e \'tell app "System Events" to shut down\'', () => {});
  else exec('systemctl poweroff', () => {});
});

ipcMain.on('kiosk:sleep', () => {
  if (process.platform === 'win32') {
    // standby (sleep, not hibernate). 0,1,0 = no force, allow wake events, no hibernate.
    exec('rundll32.exe powrprof.dll,SetSuspendState 0,1,0', () => {});
  } else if (process.platform === 'darwin') {
    exec('pmset sleepnow', () => {});
  } else {
    exec('systemctl suspend', () => {});
  }
});

ipcMain.on('kiosk:open-wifi', () => {
  if (process.platform === 'win32') {
    // Native Windows network flyout / Settings page
    exec('start ms-availablenetworks:', () => {});
    exec('start ms-settings:network-wifi', () => {});
  } else if (process.platform === 'darwin') {
    exec('open "/System/Library/PreferencePanes/Network.prefPane"', () => {});
  } else {
    exec('nm-connection-editor', () => {});
  }
});

// ============= Network monitor =============

ipcMain.handle('network:status', () => network ? network.status : 'connecting');

function broadcastNetwork(status) {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send('network:change', status);
  }
}

// ============= Offline queue =============

ipcMain.handle('queue:checkin', (_e, payload) => {
  // payload: { name, purpose, started_at } — uuid auto-assigned
  const uuid = offlineQueue.uuid();
  offlineQueue.enqueue('offline_checkin', { ...payload, uuid });
  return { ok: true, uuid };
});

ipcMain.handle('queue:logout', (_e, payload) => {
  // payload: { booking_id?, uuid?, started_at, ended_at }
  offlineQueue.enqueue('logout', payload);
  return { ok: true };
});

ipcMain.handle('queue:size', () => offlineQueue.size());

ipcMain.handle('queue:flush', async () => {
  if (!kioskConfig || !kioskConfig.heartbeat_url) return { error: 'not_paired' };
  const syncUrl = kioskConfig.heartbeat_url.replace(/\/heartbeat$/, '/sync');
  return offlineQueue.flush(syncUrl, kioskConfig.token);
});

// ============= Pairing IPC =============

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
      admin_pin: data.admin_pin || '9999',
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

  kioskConfig = config;
  createKioskWindow(config);
  registerBlockedShortcuts();
  registerAdminShortcut();

  heartbeat = new HeartbeatService(config);
  heartbeat.onUnpaired = () => unpairAndRestart();
  heartbeat.start();

  // Network monitor — probe SERVER_BASE root
  network = new NetworkMonitor(SERVER_BASE);
  network.on('change', async (status) => {
    broadcastNetwork(status);
    // On regain, flush offline queue
    if (status === 'online' && config.heartbeat_url) {
      const syncUrl = config.heartbeat_url.replace(/\/heartbeat$/, '/sync');
      await offlineQueue.flush(syncUrl, config.token);
    }
  });
  network.start();

  if (config.update_url) startAutoUpdater(config.update_url);
});

app.on('window-all-closed', () => {
  if (heartbeat) heartbeat.stop();
  if (network) network.stop();
  app.quit();
});

app.on('will-quit', () => globalShortcut.unregisterAll());
app.on('browser-window-focus', () => { registerBlockedShortcuts(); registerAdminShortcut(); });
