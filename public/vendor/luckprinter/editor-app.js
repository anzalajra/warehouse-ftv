/**
 * Editor label WYSIWYG — meniru editor "Luck Jingle" asli.
 *
 * Kanvas bebas: tambah/geser/resize/putar banyak elemen (teks, QR, barcode,
 * gambar, garis, kotak), panel properti per-elemen, template, simpan/muat,
 * lalu cetak ke printer lewat pipeline yang sudah ada (buildRaster + printRaster).
 *
 * Koordinat semua dalam DOTS (1 dot = 1 px desain). 203 dpi = 8 dots/mm.
 * Desain dibuat LANDSCAPE (sumbu-X = panjang label, sumbu-Y = lebar head 96 dot).
 * Saat cetak, desain diputar 90° agar lebar = lebar head printer.
 */
import { createLabelCanvas, drawQR, drawBarcode, drawImage } from './label.js';
import { mmToDots, matchModel, SUPPORTED_MODELS, LABEL_SERIES_PREFIXES, SUPPORTED_PREFIXES } from './devices.js';
import { LuckPrinter, buildRaster, isSupported } from './driver.js';

const $ = (id) => document.getElementById(id);
const DPI = 203;

// ---- integrasi "import dari sistem" (opsional) ----
// Halaman host (Filament) menyisipkan ?dataUrl=/admin/label-printer/units agar
// editor bisa menarik data unit (serial + payload PREFIX:serial) tanpa diketik.
// Tanpa dataUrl, fitur ini tidak aktif (editor tetap berfungsi standalone).
const PARAMS = new URLSearchParams(location.search);
// dataUrl boleh datang dari query (?dataUrl=) atau di-inject host via window global.
const SYS_URL = PARAMS.get('dataUrl') || (typeof window !== 'undefined' && window.LUCKPRINTER_DATA_URL) || '';
// Antrian unit yang sudah di-resolve server-side (dari klik "Print Label" / bulk).
// Tiap item: { serial, name, payload, type }. Dipakai untuk prefill + cetak massal.
const QUEUE = (typeof window !== 'undefined' && Array.isArray(window.LUCKPRINTER_QUEUE)) ? window.LUCKPRINTER_QUEUE : [];
// Logo terdaftar di sistem (settings + brand). Tiap item: { name, url }.
const LOGOS = (typeof window !== 'undefined' && Array.isArray(window.LUCKPRINTER_LOGOS)) ? window.LUCKPRINTER_LOGOS : [];
// Template desain tersimpan di server. Tiap item: { id, name, is_default, design }.
let SRV_TEMPLATES = (typeof window !== 'undefined' && Array.isArray(window.LUCKPRINTER_TEMPLATES)) ? window.LUCKPRINTER_TEMPLATES : [];
const TPL_URL = (typeof window !== 'undefined' && window.LUCKPRINTER_TEMPLATES_URL) || '';
// Desain default (serialize() dari template is_default) untuk auto-fill antrian.
const DEFAULT_TEMPLATE = (typeof window !== 'undefined' && window.LUCKPRINTER_DEFAULT_TEMPLATE) || null;

// Elemen yang "terikat data" (bind) akan otomatis berganti isi per item antrian:
//   bind:'payload' → QR/Barcode .data (atau teks) = item.payload (PREFIX:serial)
//   bind:'name'    → teks = item.name
//   bind:'serial'  → teks = item.serial
function bindElement(el, item) {
  if (!el.bind || !item) return el;
  const copy = { ...el, _cache: null, _cacheKey: null };
  if (el.bind === 'payload') {
    if (el.type === 'qr' || el.type === 'barcode') copy.data = item.payload;
    else copy.text = item.payload;
  } else if (el.bind === 'name') copy.text = item.name;
  else if (el.bind === 'serial') copy.text = item.serial;
  return copy;
}

// ---------------- State ----------------
const state = {
  label: { kind: 'normal', lengthMm: 40, widthMm: 14, printWidthDots: 96, cable: null },
  orientation: 'rot90',          // rot90 | rot270
  calib: { x: 0, y: 0 },         // kalibrasi geser cetak (mm): x=panjang/feed, y=lebar/head
  elements: [],
  selectedId: null,
  zoom: 4,
};
let printer = null;
let uid = 1;
const nextId = () => 'e' + (uid++);

// ---- preset ukuran kertas/label standar (panjang × lebar, mm) ----
const PAPER_SIZES = [
  { l: 30, w: 14 }, { l: 40, w: 14 }, { l: 50, w: 14 },
  { l: 22, w: 12 }, { l: 22, w: 14 }, { l: 26, w: 15 },
  { l: 30, w: 12 }, { l: 40, w: 12 }, { l: 50, w: 15 },
];

// ---- preset label kabel (flag): panjang dibagi depan + deadzone (lilit) + belakang ----
const CABLE_LABELS = [
  { name: 'Kabel 12.5 × 109 mm', l: 109, w: 12.5, front: 37, dead: 35, back: 37 },
];

// ---- ukuran desain (dots) ----
const designW = () => mmToDots(state.label.lengthMm, DPI);   // panjang (horizontal)
const designH = () => mmToDots(state.label.widthMm, DPI);    // lebar label penuh (vertikal)

// margin atas/bawah yang TIDAK tercetak (label lebih lebar dari head). Bisa negatif → head>label.
const vMargin = () => Math.round((designH() - state.label.printWidthDots) / 2);
// margin aman tepi kiri/kanan & atas/bawah (rekomendasi, supaya konten tak terpotong di ujung).
const SAFE_X = () => Math.round(mmToDots(1, DPI));
const SAFE_Y = () => Math.round(mmToDots(0.5, DPI));

// ---------------- Fonts ----------------
const FONTS = ['sans-serif', 'serif', 'monospace', 'Arial', 'Times New Roman', 'Courier New', 'Impact', 'Georgia'];

// ---------------- Default tiap elemen ----------------
function makeElement(type) {
  const W = designW(), H = designH();
  const base = { id: nextId(), type, x: Math.round(W * 0.1), y: Math.round(H * 0.2), rotation: 0, locked: false };
  switch (type) {
    case 'text':
      return { ...base, w: Math.min(140, W - base.x - 4), h: 28, text: 'Teks', fontFamily: 'sans-serif',
        fontSize: 22, bold: true, italic: false, align: 'left', lineHeight: 1.15 };
    case 'qr':
      return { ...base, y: Math.round(H * 0.1), w: 64, h: 64, data: 'https://contoh.id', ecLevel: 'M' };
    case 'barcode':
      return { ...base, w: Math.min(200, W - base.x - 4), h: 50, data: '012345678905', format: 'code128', showText: true };
    case 'image':
      // Dithering default OFF: logo/line-art tampak pecah (noise) bila di-dither di
      // resolusi dots yang kecil. Untuk foto, user bisa nyalakan via properti.
      return { ...base, w: 64, h: 64, src: null, img: null, fit: 'contain', dither: false, invert: false };
    case 'line':
      return { ...base, x: 8, y: Math.round(H / 2), w: W - 16, h: 3, rotation: 0 };
    case 'rect':
      return { ...base, w: 100, h: 50, fill: false, strokeWidth: 2 };
    default:
      return base;
  }
}

// ---------------- Rendering ----------------
function renderToCtx(ctx, elements = state.elements) {
  const W = designW(), H = designH();
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);
  for (const el of elements) drawElement(ctx, el);
}

function drawElement(ctx, el) {
  ctx.save();
  ctx.translate(el.x + el.w / 2, el.y + el.h / 2);
  if (el.rotation) ctx.rotate((el.rotation * Math.PI) / 180);
  ctx.translate(-el.w / 2, -el.h / 2);
  ctx.fillStyle = '#000';
  ctx.strokeStyle = '#000';
  drawElementLocal(ctx, el);
  ctx.restore();
}

function drawElementLocal(ctx, el) {
  switch (el.type) {
    case 'text': return drawTextLocal(ctx, el);
    case 'qr': {
      const size = Math.min(el.w, el.h);
      drawQR(ctx, el.data || '', { x: (el.w - size) / 2, y: (el.h - size) / 2, size, ecLevel: el.ecLevel });
      return;
    }
    case 'barcode':
      drawBarcode(ctx, el.data || '', { x: 0, y: 0, w: el.w, h: el.h, format: el.format, showText: el.showText });
      return;
    case 'image': {
      const c = imageCache(el);
      if (c) ctx.drawImage(c, 0, 0);
      else { ctx.strokeStyle = '#94a3b8'; ctx.setLineDash([4, 4]); ctx.strokeRect(0.5, 0.5, el.w - 1, el.h - 1); ctx.setLineDash([]); }
      return;
    }
    case 'line':
      ctx.fillRect(0, 0, el.w, el.h);
      return;
    case 'rect':
      if (el.fill) ctx.fillRect(0, 0, el.w, el.h);
      else { ctx.lineWidth = el.strokeWidth || 2; ctx.strokeRect((el.strokeWidth||2)/2, (el.strokeWidth||2)/2, el.w - (el.strokeWidth||2), el.h - (el.strokeWidth||2)); }
      return;
  }
}

function drawTextLocal(ctx, el) {
  const weight = el.bold ? 'bold ' : '';
  const style = el.italic ? 'italic ' : '';
  ctx.font = `${style}${weight}${el.fontSize}px ${el.fontFamily}`;
  ctx.textBaseline = 'top';
  ctx.fillStyle = '#000';
  const lh = Math.round(el.fontSize * (el.lineHeight || 1.15));
  const lines = wrapText(ctx, el.text || '', el.w);
  let y = 0;
  for (const line of lines) {
    let lx = 0;
    const tw = ctx.measureText(line).width;
    if (el.align === 'center') lx = (el.w - tw) / 2;
    else if (el.align === 'right') lx = el.w - tw;
    ctx.fillText(line, lx, y);
    y += lh;
  }
}

function wrapText(ctx, text, maxWidth) {
  const out = [];
  for (const raw of String(text).split('\n')) {
    const words = raw.split(' ');
    let line = '';
    for (const w of words) {
      const test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > maxWidth && line) { out.push(line); line = w; }
      else line = test;
    }
    out.push(line);
  }
  return out;
}

// cache canvas gambar (agar dither/invert benar, tak terpengaruh transform)
function imageCache(el) {
  if (!el.img) return null;
  const key = `${el.w}x${el.h}|${el.fit}|${el.dither}|${el.invert}|${el._imgv || 0}`;
  if (el._cacheKey === key && el._cache) return el._cache;
  const { canvas, ctx } = createLabelCanvas(Math.max(1, Math.round(el.w)), Math.max(1, Math.round(el.h)));
  drawImage(ctx, el.img, { x: 0, y: 0, w: el.w, h: el.h, fit: el.fit, dither: el.dither });
  if (el.invert) {
    const im = ctx.getImageData(0, 0, canvas.width, canvas.height);
    for (let i = 0; i < im.data.length; i += 4) { im.data[i] = 255 - im.data[i]; im.data[i+1] = 255 - im.data[i+1]; im.data[i+2] = 255 - im.data[i+2]; }
    ctx.putImageData(im, 0, 0);
  }
  el._cacheKey = key; el._cache = canvas;
  return canvas;
}

// ---------------- Stage / preview ----------------
let labelCanvas, labelCtx, overlay;

function render() {
  const W = designW(), H = designH(), z = state.zoom;
  if (labelCanvas.width !== W) labelCanvas.width = W;
  if (labelCanvas.height !== H) labelCanvas.height = H;
  labelCanvas.style.width = W * z + 'px';
  labelCanvas.style.height = H * z + 'px';
  renderToCtx(labelCtx);
  syncOverlay();
  renderPrintPreview();
}

function syncOverlay() {
  const W = designW(), H = designH(), z = state.zoom;
  overlay.style.width = W * z + 'px';
  overlay.style.height = H * z + 'px';
  overlay.innerHTML = '';

  // --- panduan area cetak (tidak ikut tercetak) ---
  // Panduan ini mewakili pita cetak printer pada label (selalu di dalam kanvas).
  // Efek kalibrasi posisi cetak ditampilkan di "Pratinjau Cetak", bukan di sini,
  // agar kotak area aman tidak meleset keluar dari area edit.
  const vm = Math.max(0, vMargin());            // strip tak tercetak atas/bawah
  const headTop = vm, headH = H - 2 * vm;       // area yang dilewati head
  const addDiv = (cls, x, y, w, h) => {
    const d = document.createElement('div');
    d.className = cls;
    d.style.left = x * z + 'px'; d.style.top = y * z + 'px';
    d.style.width = w * z + 'px'; d.style.height = h * z + 'px';
    overlay.appendChild(d); return d;
  };
  if (vm > 0) {                                 // arsir bagian tak tercetak (atas/bawah head)
    addDiv('nonprint', 0, 0, W, vm);
    addDiv('nonprint', 0, H - vm, W, vm);
  }
  const sx = SAFE_X(), sy = SAFE_Y();           // zona aman (dash)
  const addSafe = (x0, x1, label) => {
    const d = addDiv('safezone', x0 + sx, headTop + sy, Math.max(1, (x1 - x0) - 2 * sx), Math.max(1, headH - 2 * sy));
    d.innerHTML = `<span class="safelbl">${label}</span>`;
  };
  const addDead = (x0, x1, label) => {
    const d = addDiv('deadzone', x0, 0, x1 - x0, H);
    d.innerHTML = `<span class="deadlbl">${label}</span>`;
  };
  if (state.label.kind === 'cable' && state.label.cable) {
    const c = state.label.cable;
    // Depan & belakang menyambung di kiri (area cetak), deadzone (lilit) di paling kanan.
    const fx = mmToDots(c.front, DPI), bEnd = mmToDots(c.front + c.back, DPI);
    addSafe(0, fx, 'Depan');
    addSafe(fx, bEnd, 'Belakang');
    addDead(bEnd, W, '↻ Lilit kabel · tak dicetak');
  } else {
    addSafe(0, W, 'area aman cetak');
  }

  state.elements.forEach((el, i) => {
    const box = document.createElement('div');
    box.className = 'el-box' + (el.id === state.selectedId ? ' selected' : '') + (el.locked ? ' locked' : '');
    box.style.left = el.x * z + 'px';
    box.style.top = el.y * z + 'px';
    box.style.width = el.w * z + 'px';
    box.style.height = el.h * z + 'px';
    box.style.transform = el.rotation ? `rotate(${el.rotation}deg)` : '';
    box.style.zIndex = (el.id === state.selectedId ? 1000 : i + 1);
    box.dataset.id = el.id;
    if (el.id === state.selectedId && !el.locked) {
      const rh = document.createElement('div'); rh.className = 'handle resize'; box.appendChild(rh);
      const rot = document.createElement('div'); rot.className = 'handle rotate'; box.appendChild(rot);
    }
    overlay.appendChild(box);
  });
}

// rotasi → kanvas cetak (lebar = head printer). `elements` opsional (untuk cetak antrian).
function makePrintCanvas(elements = state.elements) {
  const W = designW(), full = designH(), head = state.label.printWidthDots;
  // 1) render desain ukuran label penuh
  const design = document.createElement('canvas');
  design.width = W; design.height = full;
  renderToCtx(design.getContext('2d', { willReadFrequently: true }), elements);

  // 2) ambil hanya strip selebar head (di tengah label) → crop W × head
  const crop = document.createElement('canvas');
  crop.width = W; crop.height = head;
  const cctx = crop.getContext('2d');
  cctx.fillStyle = '#fff'; cctx.fillRect(0, 0, W, head);
  // geser strip cetak ke 0..head; tambah kalibrasi posisi (mm) untuk koreksi offset fisik printer.
  cctx.drawImage(design, mmToDots(state.calib.x, DPI), -vMargin() + mmToDots(state.calib.y, DPI));

  // 3) putar 90° → lebar kanvas = head printer
  const out = document.createElement('canvas');
  out.width = crop.height;   // = head
  out.height = crop.width;   // = lengthDots
  const ctx = out.getContext('2d');
  if (state.orientation === 'rot270') { ctx.translate(0, out.height); ctx.rotate(-Math.PI / 2); }
  else { ctx.translate(out.width, 0); ctx.rotate(Math.PI / 2); }
  ctx.drawImage(crop, 0, 0);
  return out;
}

function renderPrintPreview() {
  const c = makePrintCanvas();
  const pv = $('printPreview');
  // Skalakan agar mengisi lebar panel (bukan menempel kiri), lalu dipusatkan via CSS.
  const wrap = pv.closest('.ppwrap');
  const availW = Math.max(96, (wrap ? wrap.clientWidth : 200));
  let scale = Math.max(1, Math.floor(availW / Math.max(1, c.width)));
  const maxH = Math.max(260, availW * 2.6);     // batasi tinggi label panjang
  while (scale > 1 && c.height * scale > maxH) scale--;
  pv.width = c.width * scale; pv.height = c.height * scale;
  const ctx = pv.getContext('2d');
  ctx.imageSmoothingEnabled = false;
  ctx.clearRect(0, 0, pv.width, pv.height);
  ctx.drawImage(c, 0, 0, pv.width, pv.height);
}

// Hitung zoom agar seluruh label muat di area kanvas (fit to page).
function fitToPage() {
  const center = document.querySelector('.center');
  if (!center) return;
  const pad = 56; // ruang napas di sekeliling label
  const availW = Math.max(40, center.clientWidth - pad);
  const availH = Math.max(40, center.clientHeight - pad);
  const z = Math.min(availW / designW(), availH / designH());
  state.zoom = Math.max(0.25, Math.round(z * 20) / 20); // bulatkan ke kelipatan 0.05
  const slider = $('zoom'); if (slider) slider.value = state.zoom;
  const lbl = $('zoomLabel'); if (lbl) lbl.textContent = state.zoom + '×';
  render();
}

// ---------------- Selection & element ops ----------------
function selectElement(id) { state.selectedId = id; buildProps(); syncOverlay(); }
function getSelected() { return state.elements.find((e) => e.id === state.selectedId) || null; }

function addElement(type) {
  if (type === 'image') { $('fileImage').click(); return; }
  const el = makeElement(type);
  state.elements.push(el);
  selectElement(el.id);
  pushHistory();
  render();
}

function deleteSelected() {
  const el = getSelected(); if (!el) return;
  state.elements = state.elements.filter((e) => e.id !== el.id);
  state.selectedId = null;
  pushHistory(); buildProps(); render();
}

function duplicateSelected() {
  const el = getSelected(); if (!el) return;
  const copy = { ...el, id: nextId(), x: el.x + 8, y: el.y + 8, _cache: null, _cacheKey: null };
  state.elements.push(copy);
  selectElement(copy.id); pushHistory(); render();
}

function reorder(dir) {
  const el = getSelected(); if (!el) return;
  const i = state.elements.indexOf(el);
  if (dir === 'front' && i < state.elements.length - 1) { state.elements.splice(i, 1); state.elements.push(el); }
  if (dir === 'back' && i > 0) { state.elements.splice(i, 1); state.elements.unshift(el); }
  pushHistory(); render();
}

// ---------------- Pointer interaction ----------------
let drag = null;
function onPointerDown(e) {
  const box = e.target.closest('.el-box');
  if (!box) { selectElement(null); return; }
  const id = box.dataset.id;
  const el = state.elements.find((x) => x.id === id);
  if (!el) return;
  selectElement(id);
  if (el.locked) return;
  const mode = e.target.classList.contains('resize') ? 'resize'
    : e.target.classList.contains('rotate') ? 'rotate' : 'move';
  drag = {
    mode, el, sx: e.clientX, sy: e.clientY,
    x0: el.x, y0: el.y, w0: el.w, h0: el.h, r0: el.rotation,
  };
  overlay.setPointerCapture?.(e.pointerId);
  e.preventDefault();
}

function onPointerMove(e) {
  if (!drag) return;
  const z = state.zoom;
  const el = drag.el;
  if (drag.mode === 'move') {
    const dx = (e.clientX - drag.sx) / z, dy = (e.clientY - drag.sy) / z;
    el.x = clamp(Math.round(drag.x0 + dx), -el.w + 10, designW() - 10);
    el.y = clamp(Math.round(drag.y0 + dy), -el.h + 10, designH() - 10);
  } else if (drag.mode === 'resize') {
    const sdx = (e.clientX - drag.sx) / z, sdy = (e.clientY - drag.sy) / z;
    const t = (el.rotation || 0) * Math.PI / 180, c = Math.cos(t), s = Math.sin(t);
    const ldx = sdx * c + sdy * s, ldy = -sdx * s + sdy * c; // R(-θ)·delta
    el.w = Math.max(8, Math.round(drag.w0 + ldx));
    el.h = Math.max(el.type === 'line' ? 1 : 8, Math.round(drag.h0 + ldy));
    el._cache = null;
  } else if (drag.mode === 'rotate') {
    const r = overlay.getBoundingClientRect();
    const cx = r.left + (el.x + el.w / 2) * z, cy = r.top + (el.y + el.h / 2) * z;
    let deg = Math.atan2(e.clientY - cy, e.clientX - cx) * 180 / Math.PI + 90;
    if (e.shiftKey) deg = Math.round(deg / 15) * 15;
    el.rotation = Math.round((deg % 360 + 360) % 360);
  }
  render();
  syncPropFields();
}

function onPointerUp() {
  if (drag) { drag = null; pushHistory(); }
}

// ---------------- Property panel ----------------
function field(label, inner) {
  return `<label class="pf"><span>${label}</span>${inner}</label>`;
}
function buildProps() {
  const el = getSelected();
  const p = $('propPanel');
  if (!el) { p.innerHTML = '<div class="muted small">Pilih elemen di kanvas, atau tambah dari toolbar kiri.</div>'; return; }
  let html = `<div class="prop-head">${typeLabel(el.type)} <button class="x" data-act="del" title="Hapus">🗑</button></div>`;

  // tipe-spesifik
  // Pilihan "Data terikat" — saat dipakai sebagai template antrian, isi elemen
  // ini otomatis berganti per unit (lihat bindElement). Kosong = isi statis.
  const bindField = (opts) => field('Data terikat',
    `<select data-k="bind">${opts.map(([v, t]) => `<option value="${v}" ${(el.bind || '') === v ? 'selected' : ''}>${t}</option>`).join('')}</select>`);

  if (el.type === 'text') {
    html += field('Teks', `<textarea data-k="text" rows="2">${escapeHtml(el.text)}</textarea>`);
    html += `<div class="grid2">`
      + field('Font', `<select data-k="fontFamily">${FONTS.map(f => `<option ${f===el.fontFamily?'selected':''}>${f}</option>`).join('')}</select>`)
      + field('Ukuran', `<input type="number" data-k="fontSize" value="${el.fontSize}" min="6" max="120">`)
      + `</div>`;
    html += `<div class="grid2">`
      + field('Perataan', `<select data-k="align">${['left','center','right'].map(a=>`<option value="${a}" ${a===el.align?'selected':''}>${({left:'Kiri',center:'Tengah',right:'Kanan'})[a]}</option>`).join('')}</select>`)
      + field('Tinggi baris', `<input type="number" step="0.05" data-k="lineHeight" value="${el.lineHeight}" min="0.8" max="2">`)
      + `</div>`;
    html += `<div class="seg2">`
      + `<label><input type="checkbox" data-k="bold" ${el.bold?'checked':''}> Tebal</label>`
      + `<label><input type="checkbox" data-k="italic" ${el.italic?'checked':''}> Miring</label>`
      + `</div>`;
    html += bindField([['', '— Statis —'], ['name', 'Nama unit'], ['serial', 'Serial']]);
  } else if (el.type === 'qr') {
    html += field('Isi data', `<textarea data-k="data" rows="2">${escapeHtml(el.data)}</textarea>`);
    if (SYS_URL) html += `<button class="btn ghost sm" data-act="sys" style="width:100%;margin-top:6px">📥 Ambil dari sistem</button>`;
    html += field('Koreksi error', `<select data-k="ecLevel">${['L','M','Q','H'].map(l=>`<option ${l===el.ecLevel?'selected':''}>${l}</option>`).join('')}</select>`);
    html += bindField([['', '— Statis —'], ['payload', 'Kode unit (PREFIX:serial)']]);
  } else if (el.type === 'barcode') {
    html += field('Isi data', `<input type="text" data-k="data" value="${escapeHtml(el.data)}">`);
    if (SYS_URL) html += `<button class="btn ghost sm" data-act="sys" style="width:100%;margin-top:6px">📥 Ambil dari sistem</button>`;
    html += field('Format', `<select data-k="format">${[['code128','CODE 128'],['ean13','EAN-13']].map(([v,t])=>`<option value="${v}" ${v===el.format?'selected':''}>${t}</option>`).join('')}</select>`);
    html += `<label class="chk"><input type="checkbox" data-k="showText" ${el.showText?'checked':''}> Tampilkan teks</label>`;
    html += bindField([['', '— Statis —'], ['payload', 'Kode unit (PREFIX:serial)']]);
  } else if (el.type === 'image') {
    html += field('Ganti gambar', `<button class="btn ghost sm" data-act="reimg">📁 Pilih file…</button>`);
    html += field('Mode', `<select data-k="fit">${[['contain','Muat (contain)'],['cover','Penuh (cover)'],['fill','Regang (fill)']].map(([v,t])=>`<option value="${v}" ${v===el.fit?'selected':''}>${t}</option>`).join('')}</select>`);
    html += `<div class="seg2">`
      + `<label><input type="checkbox" data-k="dither" ${el.dither?'checked':''}> Dithering</label>`
      + `<label><input type="checkbox" data-k="invert" ${el.invert?'checked':''}> Negatif</label>`
      + `</div>`;
  } else if (el.type === 'rect') {
    html += `<label class="chk"><input type="checkbox" data-k="fill" ${el.fill?'checked':''}> Isi penuh</label>`;
    html += field('Tebal garis', `<input type="number" data-k="strokeWidth" value="${el.strokeWidth}" min="1" max="20">`);
  } else if (el.type === 'line') {
    html += field('Tebal', `<input type="number" data-k="h" value="${el.h}" min="1" max="40">`);
  }

  // umum: posisi/ukuran/rotasi
  html += `<hr><div class="grid2">`
    + field('X', `<input type="number" data-k="x" value="${Math.round(el.x)}">`)
    + field('Y', `<input type="number" data-k="y" value="${Math.round(el.y)}">`)
    + field('Lebar', `<input type="number" data-k="w" value="${Math.round(el.w)}" min="1">`)
    + field('Tinggi', `<input type="number" data-k="h" value="${Math.round(el.h)}" min="1">`)
    + `</div>`;
  html += field('Rotasi (°)', `<input type="number" data-k="rotation" value="${el.rotation}" step="1">`);
  html += `<div class="quickrot">${[0,90,180,270].map(d=>`<button class="btn ghost sm" data-rot="${d}">${d}°</button>`).join('')}</div>`;
  html += `<div class="rowbtn">`
    + `<button class="btn ghost sm" data-act="dup">⧉ Duplikat</button>`
    + `<button class="btn ghost sm" data-act="front">⬆ Depan</button>`
    + `<button class="btn ghost sm" data-act="back">⬇ Belakang</button>`
    + `<label class="chk"><input type="checkbox" data-k="locked" ${el.locked?'checked':''}> 🔒 Kunci</label>`
    + `</div>`;

  p.innerHTML = html;
  wireProps(p, el);
}

function wireProps(p, el) {
  p.querySelectorAll('[data-k]').forEach((inp) => {
    const k = inp.dataset.k;
    const handler = () => {
      let v;
      if (inp.type === 'checkbox') v = inp.checked;
      else if (inp.type === 'number') v = parseFloat(inp.value) || 0;
      else v = inp.value;
      el[k] = v;
      if (['w','h','fit','dither','invert'].includes(k)) el._cache = null;
      render();
    };
    inp.addEventListener('input', handler);
    inp.addEventListener('change', () => { handler(); pushHistory(); });
  });
  p.querySelectorAll('[data-rot]').forEach((b) => b.addEventListener('click', () => {
    el.rotation = +b.dataset.rot; render(); buildProps(); pushHistory();
  }));
  p.querySelectorAll('[data-act]').forEach((b) => b.addEventListener('click', () => {
    const a = b.dataset.act;
    if (a === 'del') deleteSelected();
    else if (a === 'dup') duplicateSelected();
    else if (a === 'front') reorder('front');
    else if (a === 'back') reorder('back');
    else if (a === 'sys') openSystemPicker((item) => applyUnitToElement(el, item));
    else if (a === 'reimg') { pendingImageEl = el; $('fileImage').click(); }
  }));
}

// perbarui nilai input posisi/ukuran saat drag (tanpa rebuild penuh)
function syncPropFields() {
  const el = getSelected(); if (!el) return;
  const p = $('propPanel');
  const set = (k, v) => { const i = p.querySelector(`[data-k="${k}"]`); if (i && document.activeElement !== i) i.value = v; };
  set('x', Math.round(el.x)); set('y', Math.round(el.y));
  set('w', Math.round(el.w)); set('h', Math.round(el.h));
  set('rotation', el.rotation);
}

// ---------------- History (undo/redo) ----------------
let history = [], hi = -1;
function snapshot() {
  return JSON.stringify(state.elements.map(stripEl));
}
function stripEl(el) {
  const { _cache, _cacheKey, img, ...rest } = el; // jangan simpan objek Image/canvas
  return rest;
}
function pushHistory() {
  history = history.slice(0, hi + 1);
  history.push(snapshot());
  hi = history.length - 1;
  if (history.length > 60) { history.shift(); hi--; }
  updateUndoButtons();
}
function restore(json) {
  const arr = JSON.parse(json);
  // pertahankan objek Image yang sudah dimuat (berdasar id)
  const imgs = new Map(state.elements.map((e) => [e.id, e.img]));
  state.elements = arr.map((e) => ({ ...e, img: imgs.get(e.id) || null, _cache: null, _cacheKey: null }));
  // muat ulang gambar yang belum ada objeknya
  state.elements.forEach((e) => { if (e.type === 'image' && e.src && !e.img) loadElImage(e, e.src); });
  if (!state.elements.find((e) => e.id === state.selectedId)) state.selectedId = null;
  buildProps(); render();
}
function undo() { if (hi > 0) { hi--; restore(history[hi]); updateUndoButtons(); } }
function redo() { if (hi < history.length - 1) { hi++; restore(history[hi]); updateUndoButtons(); } }
function updateUndoButtons() { $('btnUndo').disabled = hi <= 0; $('btnRedo').disabled = hi >= history.length - 1; }

// ---------------- Image loading ----------------
let pendingImageEl = null;
function loadElImage(el, src) {
  const img = new Image();
  img.onload = () => { el.img = img; el._cache = null; el._imgv = (el._imgv||0)+1; render(); };
  img.src = src;
}
$('fileImage').addEventListener('change', (e) => {
  const file = e.target.files[0]; e.target.value = '';
  if (!file) { pendingImageEl = null; return; }
  const reader = new FileReader();
  reader.onload = () => {
    const src = reader.result;
    if (pendingImageEl) { pendingImageEl.src = src; loadElImage(pendingImageEl, src); pendingImageEl = null; pushHistory(); }
    else { const el = makeElement('image'); el.src = src; state.elements.push(el); loadElImage(el, src); selectElement(el.id); pushHistory(); }
  };
  reader.readAsDataURL(file);
});

// ---------------- Import dari sistem (inventaris) ----------------
async function fetchUnits(params) {
  if (!SYS_URL) return [];
  const url = SYS_URL + (SYS_URL.includes('?') ? '&' : '?') + new URLSearchParams(params).toString();
  const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) throw new Error('Gagal memuat data sistem (' + res.status + ')');
  const json = await res.json();
  return Array.isArray(json.data) ? json.data : [];
}

function openSystemPicker(onPick) {
  if (!SYS_URL) { log('Fitur import sistem tidak aktif (buka via halaman admin).'); return; }
  const back = document.createElement('div');
  back.className = 'syspick-back';
  back.innerHTML =
    '<div class="syspick">'
    + '<div class="syspick-head"><strong>Import data unit dari sistem</strong><button class="x" data-close>✕</button></div>'
    + '<input type="text" class="syspick-search" placeholder="Cari serial / nama produk…">'
    + '<div class="syspick-list"><div class="muted small" style="padding:10px">Memuat…</div></div>'
    + '</div>';
  document.body.appendChild(back);
  const listEl = back.querySelector('.syspick-list');
  const searchEl = back.querySelector('.syspick-search');
  const close = () => back.remove();
  back.addEventListener('click', (e) => { if (e.target === back || e.target.hasAttribute('data-close')) close(); });

  let timer = null;
  const doSearch = async () => {
    const q = searchEl.value.trim();
    listEl.innerHTML = '<div class="muted small" style="padding:10px">Memuat…</div>';
    try {
      const rows = await fetchUnits(q ? { q } : {});
      if (!rows.length) { listEl.innerHTML = '<div class="muted small" style="padding:10px">Tidak ada hasil.</div>'; return; }
      listEl.innerHTML = '';
      for (const r of rows) {
        const it = document.createElement('button');
        it.className = 'syspick-item';
        it.innerHTML = `<span class="nm">${escapeHtml(r.name)} <em>${r.type === 'kit' ? '· kit' : ''}</em></span><span class="sr">${escapeHtml(r.serial)}</span>`;
        it.addEventListener('click', () => { onPick(r); close(); });
        listEl.appendChild(it);
      }
    } catch (err) {
      listEl.innerHTML = `<div class="muted small" style="padding:10px;color:#b91c1c">${escapeHtml(err.message)}</div>`;
    }
  };
  searchEl.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(doSearch, 250); });
  doSearch();
  searchEl.focus();
}

// Isi data sebuah elemen QR/barcode dengan payload sistem (PREFIX:serial) + tandai bind.
function applyUnitToElement(el, item) {
  el.data = item.payload;
  if (el.type === 'barcode') el.format = 'code128'; // payload alfanumerik → CODE128
  el.bind = 'payload';                              // ikut berganti saat cetak massal
  buildProps(); render(); pushHistory();
  log(`📥 ${item.name} (${item.serial}) diterapkan ke ${typeLabel(el.type)}.`);
}

// Bangun label unit lengkap (nama + serial + QR berisi payload) menggantikan desain.
// Elemen ditandai `bind` agar bisa dipakai ulang untuk semua item antrian (cetak massal).
function insertUnitLabel(item) {
  const W = designW(), H = designH();
  state.elements = [
    { ...makeElement('text'), id: nextId(), x: 6, y: 6, w: W - 78, h: 24, text: item.name, fontSize: 18, bold: true, align: 'left', bind: 'name' },
    { ...makeElement('text'), id: nextId(), x: 6, y: 32, w: W - 78, h: 18, text: item.serial, fontSize: 12, bold: false, align: 'left', bind: 'serial' },
    { ...makeElement('qr'), id: nextId(), x: W - 70, y: Math.round((H - 64) / 2), w: 64, h: 64, data: item.payload, ecLevel: 'M', bind: 'payload' },
  ];
  state.selectedId = null;
  pushHistory(); buildProps(); render();
  log(`📥 Label unit disisipkan: ${item.name} (${item.serial}).`);
}

// ---------------- Antrian cetak (bulk) ----------------
function renderQueuePanel() {
  const sec = $('queueSec'); if (!sec) return;
  if (!QUEUE.length) { sec.style.display = 'none'; return; }
  sec.style.display = 'block';
  $('queueCount').textContent = QUEUE.length;
  const list = $('queueList');
  list.innerHTML = QUEUE.map((it, i) =>
    `<div class="queue-item" data-qjump="${i}" style="cursor:pointer"><span class="nm">${escapeHtml(it.name)}${it.type === 'kit' ? ' <em>· kit</em>' : ''}</span><span class="sr">${escapeHtml(it.serial)}</span></div>`
  ).join('');
  list.querySelectorAll('[data-qjump]').forEach((d) => d.addEventListener('click', () => previewQueueItem(+d.dataset.qjump)));
}

// Pastikan template antrian punya elemen terikat agar isinya berganti per unit
// (mencegah semua label identik). Diperiksa PER PERAN — jadi kalau penulis sudah
// mengikat teks nama tapi LUPA QR, QR-nya tetap diikat otomatis ke payload.
function ensureQueueBindings() {
  const notes = [];
  // QR/Barcode → kode unit (payload). Ini sumber masalah "semua QR sama".
  if (!state.elements.some((e) => e.bind === 'payload')) {
    const code = state.elements.find((e) => e.type === 'qr' || e.type === 'barcode');
    if (code) { code.bind = 'payload'; notes.push('QR/Barcode → kode unit'); }
  }
  // Teks → nama / serial, hanya bila belum ada teks terikat sama sekali.
  if (!state.elements.some((e) => e.bind === 'name' || e.bind === 'serial')) {
    const texts = state.elements.filter((e) => e.type === 'text');
    if (texts[0]) { texts[0].bind = 'name'; notes.push('teks pertama → nama'); }
    if (texts[1]) { texts[1].bind = 'serial'; notes.push('teks kedua → serial'); }
  }
  if (notes.length) {
    log('ℹ Data terikat otomatis dipasang (' + notes.join(', ') + '). Atur manual di panel properti & simpan ulang template bila perlu.');
  }
}

// ---- navigasi pratinjau antrian (bar ‹ i/N › di bawah kanvas) ----
let queueIdx = 0;
function updateQueueNav() {
  const nav = $('queueNav'); if (!nav) return;
  nav.style.display = QUEUE.length ? 'flex' : 'none';
  if (!QUEUE.length) return;
  const it = QUEUE[queueIdx];
  $('qnPos').textContent = `${queueIdx + 1} / ${QUEUE.length}`;
  $('qnName').textContent = it ? `${it.name} · ${it.serial}` : '—';
  $('qnPrev').disabled = queueIdx <= 0;
  $('qnNext').disabled = queueIdx >= QUEUE.length - 1;
}
// Tampilkan label antrian ke-i di kanvas: elemen ber-bind diisi data unit tsb,
// elemen statis (logo/teks tetap) tidak berubah. bindElement idempoten terhadap
// peran bind, jadi maju-mundur aman dan printQueue tetap konsisten.
function previewQueueItem(i) {
  if (!QUEUE.length) return;
  queueIdx = clamp(i, 0, QUEUE.length - 1);
  state.elements = state.elements.map((e) => bindElement(e, QUEUE[queueIdx]));
  state.selectedId = null;
  buildProps(); render();
  updateQueueNav();
}

async function printQueue() {
  if (!printer || !QUEUE.length) return;
  $('btnPrintQueue').disabled = true; $('btnPrint').disabled = true;
  try {
    const density = +document.querySelector('input[name=d]:checked').value;
    await printer.cmdDensity(density);
    const copies = +$('copies').value;
    for (let i = 0; i < QUEUE.length; i++) {
      const item = QUEUE[i];
      const isLast = i === QUEUE.length - 1;
      const els = state.elements.map((e) => bindElement(e, item));
      const raster = buildRaster(makePrintCanvas(els), { threshold: 128 });
      // feedDots=0 untuk item non-terakhir: GS FF sudah posisikan ke label berikutnya,
      // extra feed hanya dibutuhkan setelah label terakhir (agar mudah diambil).
      await printer.printRaster(raster, { copies, mode: 'label', feedDots: isLast ? 40 : 0 });
      log(`✅ ${i + 1}/${QUEUE.length}: ${item.name} (${item.serial})`);
    }
    log('✅ Semua antrian selesai dicetak.');
  } catch (err) { log('❌ ' + err.message); }
  $('btnPrintQueue').disabled = false; $('btnPrint').disabled = false;
}

// ---------------- Logo dari sistem ----------------
function addLogoElement(logo) {
  const el = makeElement('image');
  el.src = logo.url;
  el.dither = false; // logo: ambang bersih, bukan dithering
  el.w = 80; el.h = 64;
  state.elements.push(el);
  loadElImage(el, logo.url);
  selectElement(el.id);
  pushHistory();
  log(`🖼 Logo "${logo.name}" ditambahkan.`);
}

function openLogoPicker() {
  if (!LOGOS.length) { log('Tidak ada logo terdaftar di sistem.'); return; }
  if (LOGOS.length === 1) { addLogoElement(LOGOS[0]); return; }
  const back = document.createElement('div');
  back.className = 'syspick-back';
  back.innerHTML =
    '<div class="syspick">'
    + '<div class="syspick-head"><strong>Pilih logo sistem</strong><button class="x" data-close>✕</button></div>'
    + '<div class="syspick-list logo-grid"></div></div>';
  document.body.appendChild(back);
  const list = back.querySelector('.syspick-list');
  const close = () => back.remove();
  back.addEventListener('click', (e) => { if (e.target === back || e.target.hasAttribute('data-close')) close(); });
  for (const lg of LOGOS) {
    const b = document.createElement('button');
    b.className = 'logo-pick';
    b.innerHTML = `<img src="${lg.url}" alt=""><span>${escapeHtml(lg.name)}</span>`;
    b.addEventListener('click', () => { addLogoElement(lg); close(); });
    list.appendChild(b);
  }
}

// ---------------- Save / Load ----------------
const LS_KEY = 'luckjingle_label_design';
function serialize() {
  return { v: 1, label: state.label, orientation: state.orientation, elements: state.elements.map(stripEl) };
}
function deserialize(obj) {
  if (!obj || !obj.elements) return;
  state.label = obj.label || state.label;
  state.orientation = obj.orientation || 'rot90';
  state.elements = obj.elements.map((e) => ({ ...e, id: nextId(), img: null, _cache: null }));
  state.elements.forEach((e) => { if (e.type === 'image' && e.src) loadElImage(e, e.src); });
  state.selectedId = null;
  syncLabelControls();
  pushHistory(); buildProps(); render();
}

// ---------------- Template tersimpan di server ----------------
// CRUD meniru saveCalibToServer: fetch + X-CSRF-TOKEN dari window.LUCKPRINTER_CSRF.
// Ukuran kertas template (label) ditampilkan agar beda layout = beda template jelas.
function tplSizeLabel(design) {
  const L = design && design.label;
  if (!L || !L.lengthMm || !L.widthMm) return '';
  return (L.kind === 'cable' ? 'kabel ' : '') + `${L.lengthMm}×${L.widthMm}mm`;
}
function renderServerTemplatePanel() {
  const list = $('srvTplList'); if (!list) return;
  if (!TPL_URL) { const sec = $('srvTplSec'); if (sec) sec.style.display = 'none'; return; }
  if (!SRV_TEMPLATES.length) {
    list.innerHTML = '<div class="muted small">Belum ada template tersimpan.</div>';
    return;
  }
  list.innerHTML = SRV_TEMPLATES.map((t) =>
    `<div class="queue-item" style="flex-wrap:wrap;gap:6px">
       <span class="nm">${escapeHtml(t.name)}<span class="tpl-size">${tplSizeLabel(t.design)}</span>${t.is_default ? ' <em>· default</em>' : ''}</span>
       <span style="display:flex;gap:6px;width:100%;margin-top:4px">
         <button class="btn ghost sm" data-tpl-load="${t.id}">Muat</button>
         <button class="btn ghost sm" data-tpl-default="${t.id}" ${t.is_default ? 'disabled' : ''}>Jadikan Default</button>
         <button class="btn ghost sm" data-tpl-del="${t.id}">Hapus</button>
       </span>
     </div>`
  ).join('');
  list.querySelectorAll('[data-tpl-load]').forEach((b) => b.addEventListener('click', () => applyServerTemplate(+b.dataset.tplLoad)));
  list.querySelectorAll('[data-tpl-default]').forEach((b) => b.addEventListener('click', () => setDefaultServerTemplate(+b.dataset.tplDefault)));
  list.querySelectorAll('[data-tpl-del]').forEach((b) => b.addEventListener('click', () => deleteServerTemplate(+b.dataset.tplDel)));
}

async function refreshServerTemplates() {
  if (!TPL_URL) return;
  try {
    const res = await fetch(TPL_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const json = await res.json();
    SRV_TEMPLATES = Array.isArray(json.data) ? json.data : [];
    renderServerTemplatePanel();
  } catch (_) { /* biarkan daftar lama */ }
}

async function postTemplate(body) {
  const res = await fetch(TPL_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': window.LUCKPRINTER_CSRF || '' },
    credentials: 'same-origin',
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

function applyServerTemplate(id) {
  const t = SRV_TEMPLATES.find((x) => x.id === id); if (!t) return;
  deserialize(t.design);       // memuat elemen + ukuran kertas template
  fitToPage();                 // sesuaikan zoom ke ukuran kertas template
  log(`📂 Template "${t.name}" dimuat (${tplSizeLabel(t.design) || 'ukuran kustom'}).`);
}

async function saveServerTemplate({ id = null, name } = {}) {
  if (!TPL_URL) return;
  try {
    const json = await postTemplate({ id, name, design: serialize() });
    log(`💾 Template "${json.template?.name || name}" disimpan ke server.`);
    await refreshServerTemplates();
  } catch (e) { log('❌ Gagal simpan template: ' + e.message); }
}

async function setDefaultServerTemplate(id) {
  const t = SRV_TEMPLATES.find((x) => x.id === id); if (!t) return;
  try {
    await postTemplate({ id, name: t.name, design: t.design, is_default: true });
    log(`⭐ "${t.name}" jadi template default.`);
    await refreshServerTemplates();
  } catch (e) { log('❌ Gagal set default: ' + e.message); }
}

async function deleteServerTemplate(id) {
  const t = SRV_TEMPLATES.find((x) => x.id === id); if (!t) return;
  if (!confirm(`Hapus template "${t.name}"?`)) return;
  try {
    const res = await fetch(TPL_URL + '/' + id, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': window.LUCKPRINTER_CSRF || '' },
      credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    log(`🗑 Template "${t.name}" dihapus.`);
    await refreshServerTemplates();
  } catch (e) { log('❌ Gagal hapus template: ' + e.message); }
}

// ---------------- Printing ----------------
function setConnected(on) {
  $('connPill').textContent = on ? 'Terhubung' : 'Belum terhubung';
  $('connPill').className = 'pill ' + (on ? 'ok' : '');
  $('btnPrint').disabled = !on; $('btnDisconnect').disabled = !on; $('btnStatus').disabled = !on;
  const pq = $('btnPrintQueue'); if (pq) pq.disabled = !on || !QUEUE.length;
}
function log(msg) { const l = $('log'); l.textContent += msg + '\n'; l.scrollTop = l.scrollHeight; }

async function connect() {
  try {
    printer = new LuckPrinter();
    printer.addEventListener('log', (e) => log(e.detail));
    printer.addEventListener('disconnected', () => setConnected(false));
    printer.addEventListener('status', (e) => showStatus(e.detail));
    printer.addEventListener('progress', (e) => log(`Salinan ${e.detail.copy}/${e.detail.copies}`));
    const scope = $('scope').value;
    const prefixes = scope === 'label' ? LABEL_SERIES_PREFIXES : scope === 'all' ? SUPPORTED_PREFIXES : [];
    const info = await printer.connect({ namePrefixes: prefixes });
    const model = matchModel(info.name);
    setConnected(true);
    $('modelInfo').innerHTML = `Terhubung: <b>${info.name || '?'}</b>` + (model ? ` — <b>${model.name}</b> (${model.printWidthDots} dots)` : ' — model tak dikenal (default 96)');
    if (model) { state.label.printWidthDots = model.printWidthDots; syncLabelControls(); render(); }
  } catch (err) { log('❌ ' + err.message); }
}

async function print() {
  if (!printer) return;
  $('btnPrint').disabled = true;
  try {
    const density = +document.querySelector('input[name=d]:checked').value;
    await printer.cmdDensity(density);
    const canvas = makePrintCanvas();
    // threshold otomatis: 128 sudah tepat untuk teks/QR/barcode (murni hitam-putih);
    // gambar foto ditangani oleh dithering di properti elemen gambar.
    const raster = buildRaster(canvas, { threshold: 128 });
    await printer.printRaster(raster, { copies: +$('copies').value, mode: 'label' });
    log('✅ Selesai mencetak.');
  } catch (err) { log('❌ ' + err.message); }
  $('btnPrint').disabled = false;
}

function showStatus(s) {
  const parts = [];
  if (s.isCoverOpen) parts.push('penutup terbuka');
  if (s.isLackPaper) parts.push('kehabisan kertas');
  if (s.isLowBattery) parts.push('baterai lemah');
  if (s.isOverheat) parts.push('panas berlebih');
  if (s.isCharging) parts.push('mengisi daya');
  $('statusBox').textContent = 'Status: ' + (parts.length ? parts.join(', ') : 'normal');
}

// ---------------- Label controls ----------------
function buildPaperOptions() {
  const sel = $('paperSize');
  const std = PAPER_SIZES.map((p) => `<option value="n:${p.l}*${p.w}">${p.l} × ${p.w} mm</option>`).join('');
  const cab = CABLE_LABELS.map((p) => `<option value="c:${p.l}*${p.w}*${p.front}*${p.dead}*${p.back}">${p.name}</option>`).join('');
  sel.innerHTML = `<optgroup label="Label standar">${std}</optgroup>`
    + `<optgroup label="Label kabel (flag)">${cab}</optgroup>`
    + `<option value="custom">Custom…</option>`;
}
function syncLabelControls() {
  const L = state.label;
  let key = 'custom';
  if (L.kind === 'cable' && L.cable) {
    const c = L.cable, ck = `c:${L.lengthMm}*${L.widthMm}*${c.front}*${c.dead}*${c.back}`;
    if (CABLE_LABELS.some((p) => `c:${p.l}*${p.w}*${p.front}*${p.dead}*${p.back}` === ck)) key = ck;
  } else {
    const nk = `n:${L.lengthMm}*${L.widthMm}`;
    if (PAPER_SIZES.some((p) => `n:${p.l}*${p.w}` === nk)) key = nk;
  }
  $('paperSize').value = key;
  $('lblLength').value = L.lengthMm;
  $('lblWidth').value = L.widthMm;
  $('lblPrintWidth').value = L.printWidthDots;
  $('orientation').value = state.orientation;
  $('zoom').value = state.zoom;
  $('zoomLabel').textContent = state.zoom + '×';
}

// ---------------- Util ----------------
function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
function typeLabel(t) { return ({ text: '🅣 Teks', qr: '🔳 QR Code', barcode: '▏▎ Barcode', image: '🖼 Gambar', line: '— Garis', rect: '▭ Kotak' })[t] || t; }
function escapeHtml(s) { return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

// ---------------- Wiring ----------------
function init() {
  labelCanvas = $('labelCanvas');
  labelCtx = labelCanvas.getContext('2d', { willReadFrequently: true });
  overlay = $('overlay');

  // dukungan browser
  if (!isSupported()) {
    $('supportBanner').textContent = '⚠️ Browser ini tidak mendukung Web Bluetooth — gunakan Chrome/Edge di Android/Windows/Mac (bukan iPhone/iPad).';
    $('supportBanner').style.display = 'block';
    $('btnConnect').disabled = true;
  }

  // toolbar tambah elemen
  document.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => addElement(b.dataset.add)));

  // tombol logo sistem (hanya tampil bila ada logo terdaftar)
  const logoBtn = $('btnLogo');
  if (logoBtn && LOGOS.length) { logoBtn.style.display = ''; logoBtn.addEventListener('click', openLogoPicker); }

  // overlay pointer
  overlay.addEventListener('pointerdown', onPointerDown);
  window.addEventListener('pointermove', onPointerMove);
  window.addEventListener('pointerup', onPointerUp);

  // pan: drag area kosong (di luar elemen) untuk menggeser kanvas
  const center = document.querySelector('.center');
  let pan = null;
  center.addEventListener('pointerdown', (e) => {
    if (e.target.closest('.el-box')) return;          // klik elemen → bukan pan
    pan = { x: e.clientX, y: e.clientY, sl: center.scrollLeft, st: center.scrollTop };
    center.style.cursor = 'grabbing';
  });
  window.addEventListener('pointermove', (e) => {
    if (!pan) return;
    center.scrollLeft = pan.sl - (e.clientX - pan.x);
    center.scrollTop = pan.st - (e.clientY - pan.y);
  });
  window.addEventListener('pointerup', () => { if (pan) { pan = null; center.style.cursor = ''; } });

  // keyboard
  window.addEventListener('keydown', (e) => {
    const typing = /input|textarea|select/i.test(document.activeElement?.tagName || '');
    if (typing) return;
    const el = getSelected();
    if ((e.key === 'Delete' || e.key === 'Backspace') && el) { e.preventDefault(); deleteSelected(); }
    else if (e.key === 'ArrowLeft' && el) { el.x -= e.shiftKey ? 10 : 1; render(); syncPropFields(); }
    else if (e.key === 'ArrowRight' && el) { el.x += e.shiftKey ? 10 : 1; render(); syncPropFields(); }
    else if (e.key === 'ArrowUp' && el) { el.y -= e.shiftKey ? 10 : 1; render(); syncPropFields(); }
    else if (e.key === 'ArrowDown' && el) { el.y += e.shiftKey ? 10 : 1; render(); syncPropFields(); }
    else if (e.key.toLowerCase() === 'd' && (e.ctrlKey || e.metaKey) && el) { e.preventDefault(); duplicateSelected(); }
    else if (e.key.toLowerCase() === 'z' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); undo(); }
    else if (e.key.toLowerCase() === 'y' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); redo(); }
  });

  // kontrol label
  buildPaperOptions();
  $('paperSize').addEventListener('change', () => {
    const v = $('paperSize').value;
    if (v.startsWith('c:')) {
      const [l, w, front, dead, back] = v.slice(2).split('*').map(Number);
      state.label.kind = 'cable'; state.label.lengthMm = l; state.label.widthMm = w;
      state.label.cable = { front, dead, back };
    } else if (v.startsWith('n:')) {
      const [l, w] = v.slice(2).split('*').map(Number);
      state.label.kind = 'normal'; state.label.cable = null; state.label.lengthMm = l; state.label.widthMm = w;
    }
    syncLabelControls(); fitToPage(); pushHistory();
  });
  $('lblLength').addEventListener('change', () => { state.label.kind = 'normal'; state.label.cable = null; state.label.lengthMm = clamp(+$('lblLength').value || 40, 5, 300); syncLabelControls(); fitToPage(); pushHistory(); });
  $('lblWidth').addEventListener('change', () => { state.label.kind = 'normal'; state.label.cable = null; state.label.widthMm = clamp(+$('lblWidth').value || 14, 8, 40); syncLabelControls(); fitToPage(); pushHistory(); });
  $('lblPrintWidth').addEventListener('change', () => { state.label.printWidthDots = clamp(+$('lblPrintWidth').value || 96, 8, 1024); render(); });
  $('orientation').addEventListener('change', () => { state.orientation = $('orientation').value; renderPrintPreview(); });
  $('zoom').addEventListener('input', () => { state.zoom = +$('zoom').value; $('zoomLabel').textContent = state.zoom + '×'; render(); });
  $('btnFit').addEventListener('click', fitToPage);

  // kalibrasi posisi cetak — server (DB) adalah sumber utama, localStorage sebagai fallback.
  const CALIB_KEY = 'luckjingle_print_calib';
  const serverCalib = (window.LUCKPRINTER_CALIB && (window.LUCKPRINTER_CALIB.x || window.LUCKPRINTER_CALIB.y))
    ? { x: +window.LUCKPRINTER_CALIB.x || 0, y: +window.LUCKPRINTER_CALIB.y || 0 }
    : null;
  try {
    const c = serverCalib || JSON.parse(localStorage.getItem(CALIB_KEY) || 'null');
    if (c) state.calib = { x: +c.x || 0, y: +c.y || 0 };
  } catch (_) {}
  $('calibX').value = state.calib.x; $('calibY').value = state.calib.y;

  let _calibSaveTimer = null;
  function saveCalibToServer(calib) {
    const url = window.LUCKPRINTER_CALIB_URL;
    if (!url) return;
    clearTimeout(_calibSaveTimer);
    _calibSaveTimer = setTimeout(() => {
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.LUCKPRINTER_CSRF || '' },
        body: JSON.stringify(calib),
      }).catch(() => {});
    }, 800);
  }

  const onCalib = () => {
    state.calib = { x: +$('calibX').value || 0, y: +$('calibY').value || 0 };
    localStorage.setItem(CALIB_KEY, JSON.stringify(state.calib));
    saveCalibToServer(state.calib);
    render(); // perbarui panduan area aman di editor + pratinjau sekaligus
  };
  $('calibX').addEventListener('input', onCalib);
  $('calibY').addEventListener('input', onCalib);

  // undo/redo & clear
  $('btnUndo').addEventListener('click', undo);
  $('btnRedo').addEventListener('click', redo);
  $('btnClear').addEventListener('click', () => { if (confirm('Hapus semua elemen?')) { state.elements = []; state.selectedId = null; pushHistory(); buildProps(); render(); } });

  // save/load
  $('btnSave').addEventListener('click', () => { localStorage.setItem(LS_KEY, JSON.stringify(serialize())); log('💾 Desain disimpan ke browser.'); });
  $('btnLoad').addEventListener('click', () => { const s = localStorage.getItem(LS_KEY); if (s) deserialize(JSON.parse(s)); else log('Tidak ada desain tersimpan.'); });
  $('btnExport').addEventListener('click', () => {
    const blob = new Blob([JSON.stringify(serialize(), null, 2)], { type: 'application/json' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'label-design.json'; a.click();
  });
  $('fileImport').addEventListener('change', (e) => {
    const f = e.target.files[0]; e.target.value = ''; if (!f) return;
    const r = new FileReader(); r.onload = () => { try { deserialize(JSON.parse(r.result)); } catch { log('❌ File tidak valid.'); } }; r.readAsText(f);
  });

  // template tersimpan di server
  if (TPL_URL) {
    renderServerTemplatePanel();
    $('btnTplSaveNew')?.addEventListener('click', () => {
      const name = (prompt('Nama template:') || '').trim();
      if (name) saveServerTemplate({ name });
    });
  } else {
    const sec = $('srvTplSec'); if (sec) sec.style.display = 'none';
  }

  // koneksi & cetak
  $('btnConnect').addEventListener('click', connect);
  $('btnDisconnect').addEventListener('click', () => printer?.disconnect());
  $('btnPrint').addEventListener('click', print);
  $('btnStatus').addEventListener('click', () => printer?.requestStatus());
  $('btnPrintQueue')?.addEventListener('click', printQueue);

  // navigasi pratinjau antrian
  $('qnPrev')?.addEventListener('click', () => previewQueueItem(queueIdx - 1));
  $('qnNext')?.addEventListener('click', () => previewQueueItem(queueIdx + 1));

  // import dari sistem (hanya aktif bila host memberi ?dataUrl=…)
  if (SYS_URL) {
    const sysSec = $('sysSec'); if (sysSec) sysSec.style.display = 'block';
    const sysBtn = $('btnSysImport');
    if (sysBtn) sysBtn.addEventListener('click', () => {
      const sel = getSelected();
      openSystemPicker((item) => {
        if (sel && (sel.type === 'qr' || sel.type === 'barcode')) applyUnitToElement(sel, item);
        else insertUnitLabel(item);
      });
    });
  }

  // mulai dengan kanvas kosong (template bawaan dihapus — pakai Template Tersimpan)
  syncLabelControls();
  state.elements = [];
  pushHistory();
  buildProps();
  requestAnimationFrame(fitToPage); // pas-kan label ke layar saat pertama muat

  // prefill dari antrian yang sudah di-resolve server-side (klik Print Label / bulk)
  if (QUEUE.length) {
    if (DEFAULT_TEMPLATE && Array.isArray(DEFAULT_TEMPLATE.elements)) {
      // Pakai template default (logo + elemen terikat). deserialize() memuat ulang
      // gambar dari src + ukuran kertas template.
      deserialize(DEFAULT_TEMPLATE);
      fitToPage();
      ensureQueueBindings(); // jaga-jaga bila penulis template lupa set "Data terikat"
      log('📄 Template default dipakai untuk antrian.');
    } else {
      // Tidak ada template default → layout bawaan (nama + serial + QR).
      insertUnitLabel(QUEUE[0]);
    }
    renderQueuePanel();
    previewQueueItem(0); // isi kanvas dengan item pertama + munculkan navigasi ‹ ›
    if (QUEUE.length > 1) log(`ℹ ${QUEUE.length} item di antrian. Pakai ‹ › di bawah kanvas untuk pratinjau tiap label, atau klik item di "Antrian Cetak". Klik "Cetak semua antrian" untuk mencetak semuanya.`);
  } else {
    updateQueueNav(); // pastikan bar navigasi tersembunyi
  }
}

document.addEventListener('DOMContentLoaded', init);
