// Detect running editing apps via Windows tasklist. Filter via whitelist before kirim.
const { exec } = require('child_process');

function listProcesses() {
  return new Promise((resolve) => {
    exec('tasklist /fo csv /nh', { windowsHide: true, maxBuffer: 4 * 1024 * 1024 }, (err, stdout) => {
      if (err) {
        resolve([]);
        return;
      }
      const names = new Set();
      const lines = stdout.split(/\r?\n/);
      for (const line of lines) {
        // CSV: "Image Name","PID","Session Name","Session#","Mem Usage"
        const m = line.match(/^"([^"]+)"/);
        if (m && m[1]) {
          names.add(m[1]);
        }
      }
      resolve(Array.from(names));
    });
  });
}

async function getRunningApps(whitelist) {
  if (!whitelist || whitelist.length === 0) return [];
  const all = await listProcesses();
  if (all.length === 0) return [];
  const lowered = new Set(whitelist.map((w) => w.toLowerCase()));
  return all.filter((name) => lowered.has(name.toLowerCase()));
}

module.exports = { getRunningApps };
