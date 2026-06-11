/**
 * LuckPrinter — public API.
 *
 * Import dari sini:
 *   import { connectLuckPrinter, printLabel, renderLabel } from '/luckprinter/luckprinter.js';
 */
import { LuckPrinter, buildRaster, isSupported } from './driver.js';
import {
  SUPPORTED_MODELS, SUPPORTED_PREFIXES, LABEL_SERIES_PREFIXES,
  matchModel, parseLabelSize, mmToDots,
} from './devices.js';
import { renderLabel, createLabelCanvas, drawText, drawQR, drawImage, ditherCanvas, loadImage } from './label.js';

export {
  LuckPrinter, buildRaster, isSupported,
  SUPPORTED_MODELS, SUPPORTED_PREFIXES, LABEL_SERIES_PREFIXES,
  matchModel, parseLabelSize, mmToDots,
  renderLabel, createLabelCanvas, drawText, drawQR, drawImage, ditherCanvas, loadImage,
};

/**
 * Hubungkan ke printer Luck Jingle (menampilkan dialog pemilih Bluetooth).
 * @param {object} [opts]
 * @param {'label'|'all'|'any'} [opts.scope='label']
 *        'label' = hanya seri label mini (L10/L12/L13/L15/C16/MPL11),
 *        'all'   = semua model yang didukung driver,
 *        'any'   = tampilkan semua perangkat (acceptAllDevices).
 * @param {LuckPrinter} [opts.printer] pakai instance yang sudah ada.
 * @returns {Promise<{printer:LuckPrinter, name:string, model:object|null}>}
 */
export async function connectLuckPrinter(opts = {}) {
  const scope = opts.scope || 'label';
  const printer = opts.printer || new LuckPrinter(opts);

  let namePrefixes = [];
  if (scope === 'label') namePrefixes = LABEL_SERIES_PREFIXES;
  else if (scope === 'all') namePrefixes = SUPPORTED_PREFIXES;
  else namePrefixes = []; // 'any'

  const info = await printer.connect({ namePrefixes });
  const model = matchModel(info.name);
  return { printer, name: info.name, model };
}

/**
 * Render spec label lalu cetak ke printer terhubung.
 * @param {LuckPrinter} printer
 * @param {object} spec spec untuk renderLabel() (widthDots, lengthMm/heightDots, elements)
 * @param {object} [printOpts] { copies, mode, feedDots, threshold }
 * @returns {Promise<HTMLCanvasElement>} canvas yang dicetak (berguna untuk preview)
 */
export async function printLabel(printer, spec, printOpts = {}) {
  const canvas = renderLabel(spec);
  const raster = buildRaster(canvas, { threshold: printOpts.threshold });
  await printer.printRaster(raster, printOpts);
  return canvas;
}

/** Cetak canvas yang sudah jadi (mis. dari preview Anda sendiri). */
export async function printCanvas(printer, canvas, printOpts = {}) {
  const raster = buildRaster(canvas, { threshold: printOpts.threshold });
  await printer.printRaster(raster, printOpts);
  return canvas;
}
