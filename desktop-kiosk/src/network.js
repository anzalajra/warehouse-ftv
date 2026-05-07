// Network status probe. Polls server every 5s when offline, every 30s when online.
// Emits 'change' on transitions. Renderer subscribes via IPC.

const { EventEmitter } = require('events');

class NetworkMonitor extends EventEmitter {
  constructor(probeUrl) {
    super();
    this.probeUrl = probeUrl;
    this.status = 'connecting'; // connecting | online | offline
    this.timer = null;
    this.consecutiveFailures = 0;
  }

  start() {
    this._tick();
    this._reschedule();
  }

  stop() {
    if (this.timer) clearTimeout(this.timer);
  }

  _reschedule() {
    if (this.timer) clearTimeout(this.timer);
    const delay = this.status === 'online' ? 30_000 : 5_000;
    this.timer = setTimeout(() => this._tick().then(() => this._reschedule()), delay);
  }

  async _tick() {
    try {
      const ctrl = new AbortController();
      const t = setTimeout(() => ctrl.abort(), 5000);
      const res = await fetch(this.probeUrl, { method: 'HEAD', signal: ctrl.signal, cache: 'no-store' });
      clearTimeout(t);
      if (res.ok || (res.status >= 200 && res.status < 500)) {
        this._set('online');
        this.consecutiveFailures = 0;
        return;
      }
      throw new Error('HTTP ' + res.status);
    } catch (_) {
      this.consecutiveFailures++;
      // First failure → still 'connecting', after 2 → 'offline'
      if (this.consecutiveFailures >= 2) this._set('offline');
      else if (this.status !== 'online') this._set('connecting');
    }
  }

  _set(next) {
    if (this.status !== next) {
      this.status = next;
      this.emit('change', next);
    }
  }
}

module.exports = { NetworkMonitor };
