# LuckPrinter — modul cetak label Luck Jingle (Web Bluetooth)

Modul JavaScript untuk mencetak label langsung dari browser ke printer **Luck Jingle**
(seri L10/L12/L13/L15/C16/MPL11 dan lainnya) lewat **Web Bluetooth** — tanpa app Android,
tanpa driver, tanpa server perantara.

Hasil reverse-engineering app resmi "Luck Jingle". Protokol lengkap: lihat `L12_PROTOCOL.md`
di project `LuckjingleWeb`.

---

## ✅ Dukungan perangkat

| Perangkat | Browser | Status |
|-----------|---------|--------|
| Android | Chrome / Edge | ✅ |
| Windows | Chrome / Edge | ✅ |
| Mac | Chrome / Edge | ✅ |
| Mac | Safari | ❌ |
| **iPhone / iPad** | semua | ❌ (iOS tak punya Web Bluetooth) |

**Wajib HTTPS** (kecuali `localhost`). Web Bluetooth diblokir di `http://` biasa.

Model yang didukung driver ini (protokol seri "normal/Luck", service `0xFF00`):
- **Label mini 96 dots (12 mm):** L10, L12, L13, L15, C16, MPL11 (+ kembarannya: Y12, A12, MPD12, P15, dst)
- **Lebih lebar:** D1 (576), L3/L4 (824), C3 (576)

> Seri lain (aiyin/hanyin/zijiang/wifi) belum diimplementasi — strukturnya sudah disiapkan untuk ditambah nanti.

---

## 🚀 Coba sekarang (tanpa setup)

Semua file di `public/vendor/luckprinter/` adalah **ES module biasa** — tidak perlu `npm install`
atau build Vite. Cukup akses lewat Laravel:

```
https://<domain-anda>/vendor/luckprinter/test.html
```

Nyalakan printer L12, klik **Hubungkan**, pilih perangkat (mis. `L12_xxxx`), lalu **Cetak**.

---

## 🔌 Integrasi ke webapp (Laravel / Filament)

### 1. Pemakaian paling sederhana (mana saja di Blade)

```html
<button id="cetak">Cetak Label</button>

<script type="module">
import { connectLuckPrinter, printLabel } from '/vendor/luckprinter/luckprinter.js';

let printer = null;

document.getElementById('cetak').addEventListener('click', async () => {
  if (!printer) {
    const res = await connectLuckPrinter({ scope: 'label' }); // hanya seri label mini
    printer = res.printer;
  }
  await printLabel(printer, {
    widthDots: 96,          // L12 = 96
    lengthMm: 40,           // panjang label
    elements: [
      { type: 'text', text: 'FILM TELEVISI', x: 4, y: 4, size: 18, align: 'center', bold: true, maxWidth: 88 },
      { type: 'text', text: 'Rak A-12', x: 4, y: 26, size: 14, align: 'center', maxWidth: 88 },
      { type: 'qr',   text: 'https://app/item/A-12', x: 16, y: 44, size: 64 },
    ],
  }, { copies: 1, mode: 'label' });
});
</script>
```

> Koneksi **harus** dipicu oleh klik user (syarat Web Bluetooth). Simpan instance `printer`
> agar tidak minta pilih perangkat tiap cetak.

### 2. Contoh Filament v4 (Alpine sudah tersedia)

Buat sebuah `ViewField`/`Placeholder` atau halaman kustom dengan blade:

```blade
{{-- resources/views/filament/luckprinter-button.blade.php --}}
<div x-data="luckLabel()" class="flex gap-2">
    <x-filament::button x-on:click="connect()" x-text="connected ? 'Terhubung' : 'Hubungkan Printer'"></x-filament::button>
    <x-filament::button color="success" x-on:click="print()" x-bind:disabled="!connected">Cetak</x-filament::button>

    <script type="module">
        import { connectLuckPrinter, printLabel } from '/vendor/luckprinter/luckprinter.js';
        window.luckLabel = () => ({
            connected: false,
            printer: null,
            async connect() {
                const r = await connectLuckPrinter({ scope: 'label' });
                this.printer = r.printer; this.connected = true;
                r.printer.addEventListener('disconnected', () => this.connected = false);
            },
            async print() {
                // data dari record Filament bisa diinject via @js(...)
                await printLabel(this.printer, {
                    widthDots: 96, lengthMm: 40,
                    elements: [
                        { type:'text', text: @js($getRecord()->name ?? 'ITEM'), x:4, y:4, size:16, align:'center', bold:true, maxWidth:88 },
                        { type:'qr', text: @js(url('/item/'.($getRecord()->id ?? 0))), x:16, y:30, size:60 },
                    ],
                }, { copies: 1 });
            },
        });
    </script>
</div>
```

### 3. Mencetak gambar yang dirender server (QR/barcode dari PHP)

Webapp Anda sudah punya `simplesoftwareio/simple-qrcode` & `picqer/php-barcode-generator`.
Anda bisa render PNG di server lalu cetak gambarnya:

```js
import { connectLuckPrinter, printCanvas, loadImage, createLabelCanvas, drawImage } from '/vendor/luckprinter/luckprinter.js';

const img = await loadImage('/label/preview/123.png'); // PNG dari Laravel
const { canvas, ctx } = createLabelCanvas(96, 320);     // 96 x (40mm)
drawImage(ctx, img, { x:0, y:0, w:96, h:320, fit:'contain', dither:true });

const { printer } = await connectLuckPrinter({ scope:'label' });
await printCanvas(printer, canvas, { copies:1 });
```

---

## 📚 API ringkas

`luckprinter.js` (barrel):

| Fungsi | Keterangan |
|--------|-----------|
| `isSupported()` | `true` bila browser mendukung Web Bluetooth |
| `connectLuckPrinter({scope})` | dialog pilih printer. `scope`: `'label'` \| `'all'` \| `'any'`. Return `{printer, name, model}` |
| `printLabel(printer, spec, opts)` | render `spec` → cetak. Return canvas (untuk preview) |
| `printCanvas(printer, canvas, opts)` | cetak canvas yang sudah jadi |
| `renderLabel(spec)` | render spec → `<canvas>` (tanpa cetak) |
| `createLabelCanvas(w,h)`, `drawText`, `drawQR`, `drawImage`, `ditherCanvas`, `loadImage` | helper komposisi |
| `mmToDots(mm, dpi)`, `parseLabelSize`, `matchModel(name)` | util |

**`spec` untuk renderLabel / printLabel:**
```
{
  widthDots: 96,            // lebar cetak printer
  lengthMm: 40,             // ATAU heightDots: 320
  dpi: 203,                 // opsional
  elements: [
    { type:'text', text, x, y, size, font, align:'left|center|right', bold, maxWidth, lineHeight },
    { type:'qr',   text, x, y, size, ecLevel:'L|M|Q|H', quietZone },
    { type:'image', img, x, y, w, h, fit:'contain|cover|fill', dither },
    { type:'rect', x, y, w, h, fill },
    { type:'line', x1, y1, x2, y2, width },
  ]
}
```

**`opts` cetak:** `{ copies=1, mode='label'|'roll', feedDots, threshold=128 }`

**Event pada `printer` (LuckPrinter extends EventTarget):**
`log`, `status` (PrinterStatus), `progress` ({copy,copies}), `disconnected`.

Perintah tambahan: `printer.cmdDensity(0..2)`, `printer.requestStatus()`, `printer.disconnect()`.

---

## 🧩 Menambah model baru

Edit `devices.js` → tambah entri di `SUPPORTED_MODELS`:
```js
{ name:'X99', printWidthDots:96, dpi:203, miniLabel:true, labels:['40*14'], prefixes:['X99_'] }
```
`printWidthDots` = nilai `getPrintWidth()` dari kelas SDK model tsb.

---

## 🛠️ Troubleshooting

- **Tombol Hubungkan nonaktif / "tidak didukung"** → bukan Chrome/Edge, atau perangkat iOS, atau bukan HTTPS.
- **Perangkat tidak muncul di dialog** → coba `scope:'any'` (tampilkan semua), pastikan printer ON & belum tersambung ke HP lain.
- **Tersambung tapi tak mencetak** → cek panel Log di `test.html`. Pastикан muncul "Credit +n". Bila tak ada credit, driver pakai fallback otomatis.
- **Hasil terlalu tipis/tebal** → atur density (0–2) atau `threshold`.
- **Gambar foto jelek** → aktifkan `dither:true`.

---

## 📁 Struktur file

```
public/vendor/luckprinter/
├── driver.js        # transport BLE + protokol cetak (inti, zero-dep)
├── devices.js       # registry model + util ukuran
├── label.js         # render teks/QR/gambar ke canvas
├── luckprinter.js   # API publik (import dari sini)
├── test.html        # halaman uji & referensi UI
├── vendor/
│   └── qrcode-generator.js   # QR encoder (MIT, Kazuhiko Arase)
└── README.md
```
