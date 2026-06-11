/**
 * Label composer — render teks, QR, dan gambar ke <canvas> berukuran printer.
 * Output canvas siap diberikan ke buildRaster()/printCanvas().
 *
 * Koordinat & ukuran dalam DOTS (1 dot = 1 px canvas). 203 dpi = 8 dots/mm.
 */
import qrcode from './vendor/qrcode-generator.js';
import { mmToDots } from './devices.js';

/**
 * Buat canvas label kosong (latar putih).
 * @param {number} widthDots lebar cetak printer (mis. 96)
 * @param {number} heightDots tinggi label dalam dots
 */
export function createLabelCanvas(widthDots, heightDots) {
  const canvas = document.createElement('canvas');
  canvas.width = widthDots;
  canvas.height = heightDots;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, widthDots, heightDots);
  ctx.fillStyle = '#000';
  ctx.strokeStyle = '#000';
  ctx.imageSmoothingEnabled = false;
  return { canvas, ctx };
}

/** Gambar teks dengan word-wrap sederhana. Mengembalikan y setelah baris terakhir. */
export function drawText(ctx, text, opts = {}) {
  const x = opts.x ?? 0;
  let y = opts.y ?? 0;
  const size = opts.size ?? 18;
  const font = opts.font ?? 'sans-serif';
  const weight = opts.bold ? 'bold ' : '';
  const align = opts.align ?? 'left';
  const lineHeight = opts.lineHeight ?? Math.round(size * 1.25);
  const maxWidth = opts.maxWidth ?? ctx.canvas.width - x;

  ctx.font = `${weight}${size}px ${font}`;
  ctx.textBaseline = 'top';
  ctx.fillStyle = '#000';

  const lines = wrapText(ctx, String(text), maxWidth);
  for (const line of lines) {
    let lx = x;
    if (align === 'center') lx = x + (maxWidth - ctx.measureText(line).width) / 2;
    else if (align === 'right') lx = x + maxWidth - ctx.measureText(line).width;
    ctx.fillText(line, lx, y);
    y += lineHeight;
  }
  return y;
}

function wrapText(ctx, text, maxWidth) {
  const out = [];
  for (const raw of text.split('\n')) {
    const words = raw.split(' ');
    let line = '';
    for (const w of words) {
      const test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > maxWidth && line) {
        out.push(line);
        line = w;
      } else {
        line = test;
      }
    }
    out.push(line);
  }
  return out;
}

/**
 * Gambar QR code. Ukuran digambar agar setiap modul = bilangan bulat px (tajam).
 * @returns {{size:number}} ukuran sisi QR aktual (dots)
 */
export function drawQR(ctx, text, opts = {}) {
  const x = opts.x ?? 0;
  const y = opts.y ?? 0;
  const target = opts.size ?? 80; // sisi target (dots)
  const ec = opts.ecLevel ?? 'M'; // L M Q H
  const typeNumber = opts.typeNumber ?? 0; // 0 = auto

  const qr = qrcode(typeNumber, ec);
  qr.addData(String(text));
  qr.make();
  const count = qr.getModuleCount();
  const quiet = opts.quietZone ?? 2; // modul margin
  const total = count + quiet * 2;
  const cell = Math.max(1, Math.floor(target / total));
  const sizePx = cell * total;

  // latar putih (quiet zone)
  ctx.fillStyle = '#fff';
  ctx.fillRect(x, y, sizePx, sizePx);
  ctx.fillStyle = '#000';
  for (let r = 0; r < count; r++) {
    for (let c = 0; c < count; c++) {
      if (qr.isDark(r, c)) {
        ctx.fillRect(x + (c + quiet) * cell, y + (r + quiet) * cell, cell, cell);
      }
    }
  }
  return { size: sizePx };
}

/**
 * Gambar gambar (HTMLImageElement / HTMLCanvasElement / ImageBitmap) ke area,
 * mode 'contain' (default) menjaga rasio.
 */
export function drawImage(ctx, img, opts = {}) {
  const x = opts.x ?? 0;
  const y = opts.y ?? 0;
  const w = opts.w ?? ctx.canvas.width;
  const h = opts.h ?? ctx.canvas.height;
  const fit = opts.fit ?? 'contain';
  const iw = img.naturalWidth || img.width;
  const ih = img.naturalHeight || img.height;
  if (!iw || !ih) return;

  let dw = w, dh = h, dx = x, dy = y;
  if (fit === 'contain') {
    const scale = Math.min(w / iw, h / ih);
    dw = iw * scale; dh = ih * scale;
    dx = x + (w - dw) / 2; dy = y + (h - dh) / 2;
  } else if (fit === 'cover') {
    const scale = Math.max(w / iw, h / ih);
    dw = iw * scale; dh = ih * scale;
    dx = x + (w - dw) / 2; dy = y + (h - dh) / 2;
  }
  ctx.drawImage(img, dx, dy, dw, dh);
  if (opts.dither) ditherRegion(ctx, Math.floor(dx), Math.floor(dy), Math.ceil(dw), Math.ceil(dh));
}

/** Floyd–Steinberg dithering (untuk foto) pada seluruh canvas atau region. */
export function ditherCanvas(ctx) {
  ditherRegion(ctx, 0, 0, ctx.canvas.width, ctx.canvas.height);
}

function ditherRegion(ctx, x0, y0, w, h) {
  x0 = Math.max(0, x0); y0 = Math.max(0, y0);
  w = Math.min(w, ctx.canvas.width - x0);
  h = Math.min(h, ctx.canvas.height - y0);
  if (w <= 0 || h <= 0) return;
  const img = ctx.getImageData(x0, y0, w, h);
  const d = img.data;
  const gray = new Float32Array(w * h);
  for (let i = 0; i < w * h; i++) {
    const p = i * 4;
    gray[i] = 0.299 * d[p] + 0.587 * d[p + 1] + 0.114 * d[p + 2];
  }
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = y * w + x;
      const old = gray[i];
      const nw = old < 128 ? 0 : 255;
      const err = old - nw;
      gray[i] = nw;
      if (x + 1 < w) gray[i + 1] += (err * 7) / 16;
      if (y + 1 < h) {
        if (x > 0) gray[i + w - 1] += (err * 3) / 16;
        gray[i + w] += (err * 5) / 16;
        if (x + 1 < w) gray[i + w + 1] += (err * 1) / 16;
      }
    }
  }
  for (let i = 0; i < w * h; i++) {
    const p = i * 4;
    const v = gray[i] < 128 ? 0 : 255;
    d[p] = d[p + 1] = d[p + 2] = v;
    d[p + 3] = 255;
  }
  ctx.putImageData(img, x0, y0);
}

/**
 * Render label deklaratif → canvas.
 * @param {object} spec
 * @param {number} spec.widthDots  lebar cetak (mis. 96)
 * @param {number} [spec.heightDots]  tinggi eksplisit (dots)
 * @param {number} [spec.lengthMm]  panjang label (mm) → heightDots = lengthMm * dpi/25.4
 * @param {number} [spec.dpi=203]
 * @param {Array}  spec.elements  daftar elemen (lihat README)
 * @returns {HTMLCanvasElement}
 */
export function renderLabel(spec) {
  const dpi = spec.dpi ?? 203;
  const widthDots = spec.widthDots;
  const heightDots = spec.heightDots ?? mmToDots(spec.lengthMm ?? 40, dpi);
  const { canvas, ctx } = createLabelCanvas(widthDots, heightDots);

  for (const el of spec.elements || []) {
    switch (el.type) {
      case 'text':
        drawText(ctx, el.text, el);
        break;
      case 'qr':
        drawQR(ctx, el.text, el);
        break;
      case 'image':
        drawImage(ctx, el.img, el);
        break;
      case 'rect':
        if (el.fill) ctx.fillRect(el.x, el.y, el.w, el.h);
        else ctx.strokeRect(el.x + 0.5, el.y + 0.5, el.w, el.h);
        break;
      case 'line':
        ctx.lineWidth = el.width ?? 1;
        ctx.beginPath();
        ctx.moveTo(el.x1, el.y1 + 0.5);
        ctx.lineTo(el.x2, el.y2 + 0.5);
        ctx.stroke();
        break;
    }
  }
  return canvas;
}

/** Util: muat URL/dataURL gambar → HTMLImageElement (Promise). */
export function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
  });
}
