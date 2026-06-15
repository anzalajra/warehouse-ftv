/**
 * LuckPrinter — Web Bluetooth driver untuk printer label Luck Jingle.
 *
 * Hasil reverse-engineering app "Luck Jingle" (com.dingdang.newprint, SDK com.luckprinter).
 * Mendukung seri "normal" Luck Jingle (L10/L12/L13/L15/C16/MPL.. dst) yang memakai
 * GATT service 0xFF00 + protokol raster ESC/POS "GS v 0" dengan flow-control credit.
 *
 * Zero-dependency, ES module. Hanya butuh Chrome/Edge (Android/Windows/Mac).
 * iOS/iPadOS & Safari TIDAK mendukung Web Bluetooth.
 *
 * Lihat L12_PROTOCOL.md (project LuckjingleWeb) untuk detail byte protokol.
 */

// ---- UUID GATT (service 0xFF00) ----
export const SERVICE_UUID = 0xff00; // 0000ff00-0000-1000-8000-00805f9b34fb
const UUID_NOTIFY_STATUS = '0000ff01-0000-1000-8000-00805f9b34fb';
const UUID_NOTIFY_CREDIT = '0000ff03-0000-1000-8000-00805f9b34fb';

// Ukuran paket BLE default (sama dengan firmware: f9335o = 20). Aman untuk semua MTU.
const PACKET_SIZE = 20;

// Mode "enablePrinter" (enablePrinterMode = 3 di app).
const ENABLE_MODE = 3;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Apakah browser ini mendukung Web Bluetooth sama sekali. */
export function isSupported() {
  return typeof navigator !== 'undefined' && !!navigator.bluetooth;
}

/**
 * Driver satu koneksi printer. Pakai sebagai EventTarget:
 *   p.addEventListener('log', e => console.log(e.detail));
 *   p.addEventListener('status', e => ...);       // PrinterStatus
 *   p.addEventListener('disconnected', e => ...);
 */
export class LuckPrinter extends EventTarget {
  constructor(options = {}) {
    super();
    this.device = null;
    this.server = null;
    this.service = null;
    this.writeChar = null;
    this.statusChar = null;
    this.creditChar = null;

    this.credits = 0;
    this._creditWaiters = [];
    this._ackWaiters = [];
    this.lastStatus = null;

    // Jika printer tidak pernah mengirim credit (firmware berbeda),
    // setelah timeout ini kita anggap boleh kirim (best-effort).
    this.assumeCreditsAfterMs = options.assumeCreditsAfterMs ?? 1500;
    this._connected = false;
  }

  get connected() {
    return this._connected && this.device?.gatt?.connected;
  }

  _log(...args) {
    const msg = args
      .map((a) => (a instanceof Uint8Array ? hex(a) : typeof a === 'object' ? JSON.stringify(a) : String(a)))
      .join(' ');
    this.dispatchEvent(new CustomEvent('log', { detail: msg }));
  }

  /**
   * Minta user memilih printer & connect.
   * @param {object} opts
   * @param {string[]} [opts.namePrefixes] daftar prefix nama BLE (mis. ['L12_','MPL12_']).
   *        Jika kosong → tampilkan semua perangkat yang punya service 0xFF00.
   */
  async connect(opts = {}) {
    if (!isSupported()) {
      throw new Error('Browser ini tidak mendukung Web Bluetooth. Gunakan Chrome/Edge di Android, Windows, atau Mac (bukan iPhone/iPad/Safari).');
    }

    const filters = [];
    const prefixes = opts.namePrefixes || [];
    for (const p of prefixes) filters.push({ namePrefix: p });

    const requestOptions = filters.length
      ? { filters, optionalServices: [SERVICE_UUID] }
      : { acceptAllDevices: true, optionalServices: [SERVICE_UUID] };

    this._log('Membuka pemilih perangkat Bluetooth…');
    this.device = await navigator.bluetooth.requestDevice(requestOptions);
    this._log('Perangkat dipilih:', this.device.name || '(tanpa nama)');

    this.device.addEventListener('gattserverdisconnected', () => this._onDisconnected());

    await this._openGatt();
    return { name: this.device.name, id: this.device.id };
  }

  async _openGatt() {
    this._log('Menghubungkan GATT…');
    this.server = await this.device.gatt.connect();
    this.service = await this.server.getPrimaryService(SERVICE_UUID);

    const chars = await this.service.getCharacteristics();
    // Write = karakteristik dengan property write/writeWithoutResponse (app memilih by property).
    this.writeChar =
      chars.find((c) => c.properties.writeWithoutResponse) ||
      chars.find((c) => c.properties.write);
    this.statusChar = chars.find((c) => c.uuid === UUID_NOTIFY_STATUS) || null;
    this.creditChar = chars.find((c) => c.uuid === UUID_NOTIFY_CREDIT) || null;

    if (!this.writeChar) throw new Error('Karakteristik write tidak ditemukan pada service 0xFF00.');

    // Subscribe notifikasi status (ff01) dulu, lalu credit (ff03) — sama urutan dengan app.
    if (this.statusChar) {
      this.statusChar.addEventListener('characteristicvaluechanged', (e) =>
        this._onStatusData(new Uint8Array(e.target.value.buffer))
      );
      await this.statusChar.startNotifications();
    }
    if (this.creditChar) {
      this.creditChar.addEventListener('characteristicvaluechanged', (e) =>
        this._onCreditData(new Uint8Array(e.target.value.buffer))
      );
      await this.creditChar.startNotifications();
    }

    this._connected = true;
    this.credits = 0;

    // Tunggu credit awal dari printer (atau fallback bila tak ada).
    this._log('Menunggu credit awal dari printer…');
    await this._waitInitialCredit();
    this._log('Printer siap. credit awal =', this.credits);
  }

  async _waitInitialCredit() {
    const start = performance.now();
    while (this.credits <= 0) {
      if (performance.now() - start > this.assumeCreditsAfterMs) {
        // Firmware mungkin tak pakai credit → anggap ada buffer.
        this.credits = 8;
        this._log('Tidak ada credit dari printer, memakai fallback credit=8.');
        return;
      }
      await sleep(50);
    }
  }

  // ff03: '01 n' = tambah n credit; '02 lo hi' = MTU printer (diabaikan, kita pakai paket 20B).
  _onCreditData(data) {
    if (data.length === 2 && data[0] === 0x01) {
      const add = data[1];
      this.credits += add;
      this._resolveCreditWaiters();
      this._log('Credit +' + add + ' (total ' + this.credits + ')');
    } else if (data.length === 3 && data[0] === 0x02) {
      const mtu = data[1] | (data[2] << 8);
      this._log('Printer melaporkan MTU=' + mtu + ' (diabaikan, paket tetap ' + PACKET_SIZE + 'B)');
    }
  }

  // ff01: respons perintah / status. ACK stop = byte0 0xAA atau teks "OK".
  _onStatusData(data) {
    this._log('Notify status:', data);
    // bila ada penunggu ACK, selesaikan
    const text = asciiSafe(data);
    const isAck = data.length > 0 && (data[0] === 0xaa || text.startsWith('OK'));
    if (isAck) this._resolveAck(true, data);

    // status printer 1-byte (10 FF 40)
    if (data.length >= 1 && !isAck) {
      const b = data[0];
      this.lastStatus = parseStatus(b);
      this.dispatchEvent(new CustomEvent('status', { detail: this.lastStatus }));
    }
  }

  _resolveCreditWaiters() {
    const w = this._creditWaiters;
    this._creditWaiters = [];
    w.forEach((fn) => fn());
  }

  _resolveAck(ok, data) {
    const w = this._ackWaiters;
    this._ackWaiters = [];
    w.forEach((fn) => fn(ok, data));
  }

  async _waitForCredit(timeoutMs = 30000) {
    if (this.credits > 0) return;
    await new Promise((resolve, reject) => {
      const t = setTimeout(() => {
        this._creditWaiters = this._creditWaiters.filter((f) => f !== onCredit);
        reject(new Error('Timeout menunggu credit dari printer.'));
      }, timeoutMs);
      const onCredit = () => {
        clearTimeout(t);
        resolve();
      };
      this._creditWaiters.push(onCredit);
    });
  }

  /** Kirim byte mentah ke printer, dipecah jadi paket & dibatasi credit. */
  async sendRaw(bytes) {
    if (!this.connected) throw new Error('Printer tidak terhubung.');
    const data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let offset = 0;
    while (offset < data.length) {
      await this._waitForCredit();
      const end = Math.min(offset + PACKET_SIZE, data.length);
      const chunk = data.subarray(offset, end);
      if (this.writeChar.properties.writeWithoutResponse) {
        await this.writeChar.writeValueWithoutResponse(chunk);
      } else {
        await this.writeChar.writeValueWithResponse(chunk);
      }
      this.credits -= 1;
      offset = end;
    }
  }

  /** Tunggu ACK (0xAA / "OK") dari printer setelah stop. */
  _waitAck(timeoutMs = 8000) {
    return new Promise((resolve) => {
      const t = setTimeout(() => {
        this._ackWaiters = this._ackWaiters.filter((f) => f !== onAck);
        resolve(false); // tidak fatal: sebagian firmware tak mengirim ACK
      }, timeoutMs);
      const onAck = (ok) => {
        clearTimeout(t);
        resolve(ok);
      };
      this._ackWaiters.push(onAck);
    });
  }

  // ---- Perintah protokol "Luck" ----
  async cmdEnable() {
    await this.sendRaw([0x10, 0xff, 0xf1, ENABLE_MODE]);
  }
  async cmdWakeup() {
    await this.sendRaw([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
  }
  async cmdPosition() {
    await this.sendRaw([0x1d, 0x0c]); // GS FF (tag positioning)
  }
  async cmdFeed(dots) {
    await this.sendRaw([0x1b, 0x4a, dots & 0xff]); // ESC J n
  }
  async cmdStop() {
    await this.sendRaw([0x10, 0xff, 0xf1, 0x45]);
    return this._waitAck();
  }
  async cmdDensity(level /*0..2*/) {
    await this.sendRaw([0x10, 0xff, 0x10, 0x00, level & 0xff]);
  }

  /** Minta status printer (hasil datang via event 'status'). */
  async requestStatus() {
    await this.sendRaw([0x10, 0xff, 0x40]);
  }

  /**
   * Cetak satu gambar raster (sudah dirender ke ukuran printer).
   * @param {Uint8Array} rasterBytes hasil buildRaster()
   * @param {object} opts
   * @param {number} [opts.copies=1]
   * @param {'label'|'roll'} [opts.mode='label']
   * @param {number} [opts.feedDots] jarak feed di akhir (default 40 label / 120 roll)
   */
  async printRaster(rasterBytes, opts = {}) {
    const copies = Math.max(1, opts.copies || 1);
    const mode = opts.mode || 'label';
    const feedDots = opts.feedDots ?? (mode === 'roll' ? 120 : 40);

    for (let i = 1; i <= copies; i++) {
      await this.cmdEnable();
      await this.cmdWakeup();
      await this.sendRaw(rasterBytes);
      if (mode === 'label') await this.cmdPosition();
      if (feedDots > 0 && i === copies) await this.cmdFeed(feedDots);
      const ok = await this.cmdStop();
      this._log(`Salinan ${i}/${copies} selesai (ack=${ok}).`);
      this.dispatchEvent(new CustomEvent('progress', { detail: { copy: i, copies } }));
    }
  }

  async disconnect() {
    try {
      if (this.device?.gatt?.connected) this.device.gatt.disconnect();
    } catch (_) {}
    this._onDisconnected();
  }

  _onDisconnected() {
    if (!this._connected) return;
    this._connected = false;
    this._resolveCreditWaiters();
    this._resolveAck(false, null);
    this._log('Printer terputus.');
    this.dispatchEvent(new CustomEvent('disconnected'));
  }
}

// ---------- util raster ----------

/**
 * Bangun perintah raster ESC/POS "GS v 0" dari ImageData / canvas.
 * Lebar gambar HARUS sama dengan printWidth printer (mis. 96 px untuk L12).
 * Piksel dianggap hitam bila rata-rata RGB < threshold dan alpha cukup.
 *
 * @param {ImageData|HTMLCanvasElement} source
 * @param {object} [opts]
 * @param {number} [opts.threshold=128]
 * @returns {Uint8Array}
 */
export function buildRaster(source, opts = {}) {
  const threshold = opts.threshold ?? 128;
  const img = source instanceof ImageData ? source : canvasToImageData(source);
  const { width, height, data } = img;
  const widthBytes = Math.ceil(width / 8);

  const header = new Uint8Array([
    0x1d, 0x76, 0x30, 0x00,
    widthBytes & 0xff, (widthBytes >> 8) & 0xff,
    height & 0xff, (height >> 8) & 0xff,
  ]);

  const body = new Uint8Array(widthBytes * height);
  for (let y = 0; y < height; y++) {
    for (let xb = 0; xb < widthBytes; xb++) {
      let b = 0;
      for (let bit = 0; bit < 8; bit++) {
        const x = xb * 8 + bit;
        if (x < width) {
          const idx = (y * width + x) * 4;
          const r = data[idx], g = data[idx + 1], bl = data[idx + 2], a = data[idx + 3];
          const dark = a >= 128 && (r + g + bl) / 3 < threshold;
          if (dark) b |= 0x80 >> bit;
        }
      }
      body[y * widthBytes + xb] = b;
    }
  }

  const out = new Uint8Array(header.length + body.length);
  out.set(header, 0);
  out.set(body, header.length);
  return out;
}

function canvasToImageData(canvas) {
  const ctx = canvas.getContext('2d');
  return ctx.getImageData(0, 0, canvas.width, canvas.height);
}

function parseStatus(b) {
  return {
    raw: b,
    isPrinting: !!(b & 0x01),
    isCoverOpen: !!(b & 0x02),
    isLackPaper: !!(b & 0x04),
    isLowBattery: !!(b & 0x08),
    isOverheat: !!((b >> 4) & 1) || !!((b >> 6) & 1),
    isCharging: !!((b >> 5) & 1),
  };
}

function hex(u8) {
  return Array.from(u8).map((x) => x.toString(16).padStart(2, '0')).join(' ');
}
function asciiSafe(u8) {
  let s = '';
  for (const c of u8) s += c >= 32 && c < 127 ? String.fromCharCode(c) : '';
  return s;
}
