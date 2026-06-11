/**
 * Barcode generator ringan (tanpa dependency) — CODE128 (auto) & EAN-13.
 * Mengembalikan string bit "1010..." (1 = bar hitam, 0 = spasi) + teks tampilan.
 *
 * Dipakai oleh editor label untuk menggambar barcode ke <canvas>.
 */

// ---- CODE128 ----
// 107 pola (0..106). Tiap pola = lebar modul bergantian (bar,spasi,bar,...).
const C128 = [
  '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
  '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
  '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
  '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
  '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
  '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
  '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
  '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
  '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
  '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
  '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
  '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
  '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
  '211214', '211232', '233111', // 103=StartA 104=StartB 105=StartC 106=Stop(2331112)
];
const C128_STOP = '2331112';
const START_B = 104;

function widthsToBits(w) {
  let bits = '';
  let on = true;
  for (const ch of w) {
    bits += (on ? '1' : '0').repeat(+ch);
    on = !on;
  }
  return bits;
}

/** Encode string (ASCII 32..126) ke CODE128-B. */
export function code128(text) {
  const data = String(text);
  const values = [START_B];
  for (let i = 0; i < data.length; i++) {
    const code = data.charCodeAt(i);
    const v = (code >= 32 && code <= 126) ? code - 32 : 0; // fallback spasi
    values.push(v);
  }
  // checksum
  let sum = START_B;
  for (let i = 1; i < values.length; i++) sum += values[i] * i;
  values.push(sum % 103);

  let bits = '';
  for (const v of values) bits += widthsToBits(C128[v]);
  bits += widthsToBits(C128_STOP);
  return { bits, text: data, ok: true };
}

// ---- EAN-13 ----
const EAN_L = ['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
const EAN_G = ['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
const EAN_R = ['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];
const EAN_PARITY = ['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];

function ean13Check(d12) {
  let s = 0;
  for (let i = 0; i < 12; i++) s += (+d12[i]) * (i % 2 === 0 ? 1 : 3);
  return (10 - (s % 10)) % 10;
}

/** Encode EAN-13. Terima 12 atau 13 digit (check dihitung bila 12). */
export function ean13(input) {
  let digits = String(input).replace(/\D/g, '');
  if (digits.length === 12) digits += String(ean13Check(digits));
  if (digits.length !== 13 || !/^\d{13}$/.test(digits)) {
    return { bits: '', text: digits, ok: false, error: 'EAN-13 butuh 12/13 digit angka' };
  }
  const first = +digits[0];
  const left = digits.slice(1, 7);
  const right = digits.slice(7, 13);
  const parity = EAN_PARITY[first];

  let bits = '101'; // guard kiri
  for (let i = 0; i < 6; i++) {
    const d = +left[i];
    bits += parity[i] === 'L' ? EAN_L[d] : EAN_G[d];
  }
  bits += '01010'; // guard tengah
  for (let i = 0; i < 6; i++) bits += EAN_R[+right[i]];
  bits += '101'; // guard kanan
  return { bits, text: digits, ok: true, first, left, right };
}

/**
 * Encode menurut format. format: 'code128' | 'ean13'.
 * @returns {{bits:string, text:string, ok:boolean, error?:string, format:string}}
 */
export function encodeBarcode(text, format = 'code128') {
  const fmt = (format || 'code128').toLowerCase();
  let r;
  if (fmt === 'ean13') r = ean13(text);
  else r = code128(text);
  return { ...r, format: fmt };
}
