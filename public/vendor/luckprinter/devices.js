/**
 * Registry model printer Luck Jingle.
 * Dibuat dari `printer_model_list.json` app + lebar cetak dari kelas SDK (getPrintWidth).
 *
 * Catatan: driver di driver.js mengimplementasi protokol seri "normal/Luck"
 * (service 0xFF00 + raster GS v 0). Semua model di SUPPORTED_MODELS memakai protokol ini.
 * Seri lain (aiyin/hanyin/zijiang/wifi/sheetlabel) BELUM didukung driver ini.
 */

// Seri label mini (lebar cetak 96 dots / 12 mm). Termasuk L12.
export const SUPPORTED_MODELS = [
  {
    name: 'L10', printWidthDots: 96, dpi: 203, miniLabel: true,
    labels: ['30*14', '40*14', '50*14'],
    prefixes: ['MPL10_'],
  },
  {
    name: 'L12', printWidthDots: 96, dpi: 203, miniLabel: true,
    labels: ['30*14', '40*14', '50*14'],
    prefixes: ['L12_', 'DP_L12_', 'MPL12_', 'BTW Identi-Express_', 'Y12_', 'A12_', 'D12_', 'MPD12_'],
  },
  {
    name: 'L13', printWidthDots: 96, dpi: 203, miniLabel: true,
    labels: ['30*14', '40*14', '50*14'],
    prefixes: ['L13_', 'MPL13_', 'MPL13K_', 'ML Printer'],
  },
  {
    name: 'L15', printWidthDots: 96, dpi: 203, miniLabel: true,
    labels: ['30*14', '40*14', '50*14'],
    prefixes: ['MPL15_', 'P15_'],
  },
  {
    name: 'C16', printWidthDots: 96, dpi: 203, miniLabel: true,
    labels: ['30*14', '40*14', '50*14'],
    prefixes: ['MPC16_', '15P3Pro_'],
  },
  {
    name: 'MPL11', printWidthDots: 96, dpi: 203, miniLabel: true,
    labels: ['30*14', '40*14', '50*14'],
    prefixes: ['MPL11_', 'D11s_', 'FICHERO_5836', 'MULLER_6473'],
  },
  // Seri lebih lebar (label/roll/tattoo) — protokol sama, lebar cetak berbeda.
  {
    name: 'D1', printWidthDots: 576, dpi: 203, miniLabel: false,
    labels: ['50*15', '50*30', '50*40', '50*60', '50*80', '50*100'],
    prefixes: ['LuckP_D1_', 'DP_D1_', 'APC03_', 'DP_D2_', 'PPD1_', 'Mini Pocket Printer'],
  },
  {
    name: 'L3', printWidthDots: 824, dpi: 203, miniLabel: false,
    labels: ['50*30', '50*40', '50*60', '50*80', '50*100'],
    prefixes: ['L3_', 'LuckP_L3_', 'LuckP_L3H_', 'LuckP_L4_', 'DP_A3_', 'PPL3H_'],
  },
  {
    name: 'C3', printWidthDots: 576, dpi: 203, miniLabel: false,
    labels: [],
    prefixes: ['LPC3_', 'DP_ITP07_'],
  },
];

/** Prefix BLE untuk seri label mini (default filter di UI cetak label 14 mm). */
export const LABEL_SERIES_PREFIXES = SUPPORTED_MODELS.filter((m) => m.miniLabel).flatMap((m) => m.prefixes);

/** Prefix BLE untuk semua model yang didukung driver ini. */
export const SUPPORTED_PREFIXES = SUPPORTED_MODELS.flatMap((m) => m.prefixes);

/**
 * Cari profil model dari nama BLE perangkat.
 * @param {string} bleName
 * @returns {object|null} profil model atau null bila tak dikenal.
 */
export function matchModel(bleName) {
  if (!bleName) return null;
  for (const m of SUPPORTED_MODELS) {
    if (m.prefixes.some((p) => bleName.startsWith(p))) return m;
  }
  return null;
}

/**
 * Parse string ukuran "panjang*lebar" (mm) → {lengthMm, widthMm}.
 * Contoh: "40*14" → { lengthMm:40, widthMm:14 }.
 */
export function parseLabelSize(str) {
  const [a, b] = String(str).split('*').map((x) => parseFloat(x));
  return { lengthMm: a, widthMm: b };
}

/** mm → dots pada DPI tertentu (203 dpi = 8 dots/mm). */
export function mmToDots(mm, dpi = 203) {
  return Math.round((mm * dpi) / 25.4);
}
