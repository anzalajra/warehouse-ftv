// Persistent kiosk config (slug + token + endpoints) stored di userData.
const fs = require('fs');
const path = require('path');
const { app } = require('electron');

// SERVER_BASE: hard-coded di build, atau override via env saat dev.
const SERVER_BASE = process.env.WAREHOUSE_BASE_URL || 'https://warehouse.ftvupi.id';

const CONFIG_FILENAME = 'kiosk-config.json';

function configPath() {
  return path.join(app.getPath('userData'), CONFIG_FILENAME);
}

function readConfig() {
  try {
    const raw = fs.readFileSync(configPath(), 'utf8');
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function writeConfig(data) {
  const dir = app.getPath('userData');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(configPath(), JSON.stringify(data, null, 2), 'utf8');
}

function clearConfig() {
  try {
    fs.unlinkSync(configPath());
  } catch {
    // ignore
  }
}

module.exports = { SERVER_BASE, readConfig, writeConfig, clearConfig, configPath };
