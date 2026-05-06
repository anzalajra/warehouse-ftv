// Heartbeat service — runs in main process, kirim status tiap N detik.
const { app } = require('electron');
const { getRunningApps } = require('./running-apps');

const DEFAULT_INTERVAL = 30 * 1000;

class HeartbeatService {
  constructor(config) {
    this.config = config;
    this.intervalMs = (config.heartbeat_interval || 30) * 1000;
    this.whitelist = config.running_apps_whitelist || [];
    this.timer = null;
    this.failureCount = 0;
  }

  start() {
    if (this.timer) return;
    // jalankan sekali segera, lalu tiap interval
    this._tick();
    this.timer = setInterval(() => this._tick(), this.intervalMs);
  }

  stop() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  async _tick() {
    try {
      const runningApps = await getRunningApps(this.whitelist);
      const payload = {
        app_version: app.getVersion(),
        uptime_seconds: Math.round(process.uptime()),
        running_apps: runningApps,
      };

      const res = await fetch(this.config.heartbeat_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer ' + this.config.token,
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }

      const data = await res.json().catch(() => ({}));
      this.failureCount = 0;

      // server boleh override interval & whitelist
      if (data && data.settings) {
        const newInterval = (data.settings.heartbeat_interval || 30) * 1000;
        if (newInterval !== this.intervalMs && newInterval >= 10_000) {
          this.intervalMs = newInterval;
          if (this.timer) {
            clearInterval(this.timer);
            this.timer = setInterval(() => this._tick(), this.intervalMs);
          }
        }
        if (Array.isArray(data.settings.running_apps_whitelist)) {
          this.whitelist = data.settings.running_apps_whitelist;
        }
      }
    } catch (err) {
      this.failureCount++;
      // exponential backoff bukan dengan retry tambahan, cukup loncati tick
      // (jaga agar tidak ada flood request kalau server down).
      // Setelah backoff, interval normal kembali otomatis.
      if (this.failureCount > 1) {
        const backoffMs = Math.min(60_000, this.intervalMs * Math.pow(2, this.failureCount - 1));
        if (this.timer) {
          clearInterval(this.timer);
          this.timer = setTimeout(() => {
            this.timer = setInterval(() => this._tick(), this.intervalMs);
            this._tick();
          }, backoffMs);
        }
      }
    }
  }
}

module.exports = { HeartbeatService, DEFAULT_INTERVAL };
