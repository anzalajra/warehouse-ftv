// Auto-updater wrapper. Cek update saat startup + tiap 6 jam.
const { autoUpdater } = require('electron-updater');

const SIX_HOURS_MS = 6 * 60 * 60 * 1000;

function startAutoUpdater(updateUrl) {
  try {
    autoUpdater.autoDownload = true;
    autoUpdater.autoInstallOnAppQuit = true;
    autoUpdater.setFeedURL({ provider: 'generic', url: updateUrl });
  } catch (err) {
    // ignore — dev environment biasanya tidak ada feed
  }

  // Cek 30 detik setelah app ready (biar tidak compete dengan startup heartbeat)
  setTimeout(() => {
    autoUpdater.checkForUpdates().catch(() => {});
  }, 30_000);

  // Cek tiap 6 jam
  setInterval(() => {
    autoUpdater.checkForUpdates().catch(() => {});
  }, SIX_HOURS_MS);
}

module.exports = { startAutoUpdater };
