# Warehouse FTV Kiosk (Electron)

Aplikasi desktop kiosk untuk komputer lab editing FTV UPI. Bungkus halaman web `/kiosk/checkin/{slug}` jadi full-screen kiosk + kirim heartbeat ke server warehouse tiap 30 detik.

## Goals

- **Ringan**: idle ~120 MB RAM, ~0% CPU. Tidak mengganggu render Premiere/DaVinci.
- **Auto-start** saat Windows boot.
- **Auto-update** via server warehouse tanpa intervensi manual.
- **Telemetry**: status online + app version + uptime + list aplikasi editing yang sedang berjalan (whitelist).

## Develop

```bash
cd desktop-kiosk
npm install
# Override server saat dev (default https://warehouse.ftvupi.id)
WAREHOUSE_BASE_URL=http://localhost:8000 npm start
```

First-run akan tampilkan window pairing 6-digit. Generate code-nya di Filament:

`/admin/computers/{id}/edit` → tombol **Kiosk App** → **Pair Kiosk App**.

## Build installer (Windows)

```bash
npm run dist
```

Output di `dist/Warehouse FTV Kiosk-Setup-{version}.exe` + `latest.yml` + `*.blockmap`.

## Release flow

1. Bump `version` di `package.json`.
2. `npm run dist`.
3. Upload `dist/*.exe`, `dist/latest.yml`, `dist/*.blockmap` ke `storage/app/kiosk-releases/` di server.
4. Update Setting `computer_kiosk_latest_version` di Filament (untuk display).
5. Komputer lab akan auto-download & install update saat next reboot.

## Config tersimpan

`%APPDATA%/Warehouse FTV Kiosk/kiosk-config.json` (Windows) — berisi slug + bearer token. Hapus file ini untuk reset pairing.

## File struktur

```
src/
├── main.js              ← entry main process (BrowserWindow + IPC + lifecycle)
├── config.js            ← read/write kiosk-config.json
├── heartbeat.js         ← HeartbeatService (POST /api/kiosk/heartbeat tiap N detik)
├── running-apps.js      ← Windows tasklist + whitelist filter
├── auto-update.js       ← electron-updater wrapper (cek tiap 6 jam)
├── pairing.html         ← UI pairing first-run
└── pairing.js           ← logic pairing (IPC ke main)
build/installer.nsh      ← NSIS hook untuk register HKLM Run autostart
electron-builder.yml     ← build config
```

## Optimasi yang dipakai

- `app.disableHardwareAcceleration()` + `--disable-gpu` — halaman cuma HTML/CSS, tidak butuh GPU process.
- Single BrowserWindow, no preload script, no renderer framework.
- Heartbeat di main process (bukan renderer) — survive renderer crash.
- `tasklist` Windows native, bukan library node — overhead ~10ms tiap 30 detik.
- `Menu.setApplicationMenu(null)` — hemat memory native menu.
- DevTools disabled di production (`devTools: false`).

## Reset pairing dari komputer

Sebagai admin lab dengan Task Manager:
1. End task `Warehouse FTV Kiosk.exe`.
2. Hapus `%APPDATA%/Warehouse FTV Kiosk/kiosk-config.json`.
3. Restart komputer atau jalankan ulang shortcut.

---

# Setup untuk komputer Mac

Lab Mac **tidak pakai Electron app ini** — pakai browser kiosk mode + halaman web kiosk yang sama. Heartbeat di-handle oleh JavaScript di halaman (`checkin.blade.php`) yang POST ke `/api/kiosk/heartbeat-web/{slug}` tiap 30 detik. Status online tetap muncul di Filament + storefront sama seperti Windows, bedanya `last_heartbeat_data.source = "web"` dan tidak ada `running_apps` (browser tidak punya akses ke proses OS).

## URL kiosk per komputer Mac

Buka Filament admin → Computers → klik komputer Mac → tombol **Check-in Page**. Salin URL-nya, contoh:
```
https://warehouse.ftvupi.id/kiosk/checkin/abc123def456...
```

Itu URL yang perlu dibuka browser kiosk di Mac tersebut.

## Setup Chrome kiosk mode (rekomendasi)

Chrome lebih reliabel di kiosk mode dibanding Safari, dan Cmd+Q-nya lebih sulit diakali. Install Chrome dulu kalau belum ada.

### 1. Buat shortcut launch dengan flags

Buat file `~/Documents/launch-kiosk.command`:

```bash
#!/bin/bash
KIOSK_URL="https://warehouse.ftvupi.id/kiosk/checkin/PASTE_SLUG_DI_SINI"
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
  --kiosk \
  --no-first-run \
  --disable-pinch \
  --noerrdialogs \
  --disable-translate \
  --disable-features=TranslateUI \
  --user-data-dir="$HOME/.chrome-kiosk" \
  --app="$KIOSK_URL"
```

Lalu di Terminal: `chmod +x ~/Documents/launch-kiosk.command`.

Test: double-click file itu — Chrome harus full-screen, tidak ada toolbar.

### 2. Auto-start saat login

System Settings → General → **Login Items & Extensions** → tambahkan `launch-kiosk.command` ke "Open at Login".

### 3. (Opsional) Lock Cmd+Q

Mac default izinkan Cmd+Q tutup app. Untuk lab terkelola dengan **MDM** (Jamf, Mosyle, Apple School Manager), deploy Configuration Profile dengan key `com.apple.symbolichotkeys` untuk disable Cmd+Q. Tanpa MDM, mahasiswa bisa Cmd+Q keluar — tapi shortcut akan auto-relaunch saat login berikutnya.

Workaround mudah tanpa MDM: bikin akun macOS terpisah untuk kiosk (misal `lab-kiosk`) yang auto-login dan tidak punya admin privilege. Mahasiswa pakai akun ini; kalau Cmd+Q keluar tinggal restart Mac.

## Setup Safari kiosk (alternatif — butuh macOS Ventura+)

Safari sebenarnya punya mode kiosk built-in via Configuration Profile (perlu MDM atau impor manual via System Settings → Privacy & Security → Profiles). Lebih native tapi setup lebih ribet. Untuk lab tanpa MDM, **gunakan Chrome saja**.

## Verifikasi heartbeat web jalan

1. Buka kiosk URL di Mac (biar bisa lihat) — Cmd+Option+I untuk DevTools sebelum kunci kiosk.
2. Tab Network → cari request `heartbeat-web` tiap 30 detik dengan response `{"ok":true,"heartbeat_interval":30}`.
3. Buka Filament admin → list Computers → komputer Mac tampil **Online** dalam < 1 menit.
4. Tutup browser → tunggu 60 detik → komputer jadi Offline.

## Catatan

- **Tidak ada auto-update** untuk komputer Mac — halaman web yang berubah, Mac tinggal reload (Chrome auto-reload).
- **Walk-in check-in tetap jalan**: tombol "Check-in & Isi Data" di halaman pakai existing flow `POST /kiosk/checkin/{slug}`, tidak perlu Electron-specific code.
- **Tidak deteksi running apps** di Mac. Kalau nanti dibutuhkan (lihat Final Cut/DaVinci yang sedang dipakai), upgrade ke full Electron app + Apple Developer cert.
- Komputer Windows yang sudah pakai Electron app **juga ikut kirim heartbeat web** karena renderer load page yang sama. Server menerima dari 2 source untuk komputer Windows — yang terakhir saja yang dipakai (last write wins). Tidak masalah; kalau mau hemat request, di v2 bisa conditionally skip script via `window.process?.versions?.electron`.
