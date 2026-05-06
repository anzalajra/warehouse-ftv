# Panduan Setup & Update Lab Komputer Warehouse FTV

Dokumen ini berisi step-by-step setup lab komputer (Windows + Mac) untuk fitur Computer Booking, plus workflow update aplikasi setelah lab berjalan.

---

## Daftar Isi

1. [Bagian 0: Persiapan Server Laravel](#bagian-0-persiapan-server-laravel)
2. [Bagian 1: Build Installer Electron (sekali)](#bagian-1-build-installer-electron-sekali)
3. [Bagian 2: Daftarkan Komputer di Filament](#bagian-2-daftarkan-komputer-di-filament)
4. [Bagian 3a: Setup Komputer WINDOWS](#bagian-3a-setup-komputer-windows)
5. [Bagian 3b: Setup Komputer MAC](#bagian-3b-setup-komputer-mac)
6. [Bagian 4: Workflow Harian](#bagian-4-workflow-harian)
7. [Bagian 5: Update Aplikasi](#bagian-5-update-aplikasi)
8. [Troubleshooting](#troubleshooting)

---

## Bagian 0: Persiapan Server Laravel

Lakukan **satu kali** di mesin server (VPS / hosting yang menjalankan `warehouse.ftvupi.id`).

### Prasyarat

- Laravel 12 sudah jalan, Filament admin bisa diakses di `/admin`
- File `storage/installed` sudah ada (kalau belum, jalankan `/setup` wizard dulu)
- Sudah `git pull` perubahan iterasi 1, 2, 3

### Eksekusi

```bash
cd /path/to/warehouse-ftv

composer install --no-dev
php artisan migrate
php artisan db:seed                # opsional, kalau database masih kosong
php artisan storage:link           # kalau belum
php artisan config:cache
php artisan route:cache
php artisan view:clear

mkdir -p storage/app/kiosk-releases
chmod -R 775 storage/app/kiosk-releases
```

### Verifikasi

1. Buka `/admin/computers` di browser → harus muncul kolom **Online**, **Last seen**, **Sedang Dipakai**
2. `php artisan tinker` → `App\Models\KioskPairingCode::count()` harus mengembalikan `0`
3. Akses `https://warehouse.ftvupi.id/api/kiosk/update/latest.yml` → harus mengembalikan **404** (file belum ada — normal, akan ada setelah upload installer pertama)

---

## Bagian 1: Build Installer Electron (sekali)

Lakukan **sekali** di komputer dev (Windows direkomendasikan supaya build native untuk Windows).

### Prasyarat

- [Node.js 18+](https://nodejs.org/)
- Internet untuk download Electron binary (~250 MB)

### Eksekusi

```bash
cd "d:\6. Kints\Projects\warehouse-ftv\desktop-kiosk"

npm install                        # 3-5 menit pertama kali
npm run dist                       # 2-3 menit
```

### Output

File hasil build di `desktop-kiosk/dist/`:

| File | Fungsi |
|------|--------|
| `Warehouse FTV Kiosk-Setup-1.0.0.exe` | Installer NSIS untuk distribusi ke PC lab |
| `latest.yml` | Manifest auto-updater (wajib di-upload ke server) |
| `Warehouse FTV Kiosk-Setup-1.0.0.exe.blockmap` | Delta-update map (hemat bandwidth saat update) |

### Test cepat di komputer dev

Double-click `.exe` → installer jalan → setelah install, app auto-launch → window pairing 6-digit muncul. Belum bisa selesai pairing kalau belum ada komputer di Filament — lanjut ke bagian 2.

### Upload ke server (untuk auto-updater)

Setelah build pertama, upload file ke server agar PC lab nanti bisa cek update:

```bash
scp dist/Warehouse\ FTV\ Kiosk-Setup-1.0.0.exe \
    dist/latest.yml \
    dist/*.blockmap \
    user@warehouse.ftvupi.id:/var/www/warehouse-ftv/storage/app/kiosk-releases/
```

Verifikasi: buka `https://warehouse.ftvupi.id/api/kiosk/update/latest.yml` di browser → harus tampil isi YAML.

---

## Bagian 2: Daftarkan Komputer di Filament

Lakukan **per komputer** yang akan didaftarkan. Bisa dari laptop admin (browser).

### Step 1: Buat Room (sekali per ruangan)

1. Buka `https://warehouse.ftvupi.id/admin`
2. Sidebar → **Computer Booking** → **Rooms** → **Create**
3. Isi: Name (contoh "Lab Editing 1"), Description, Image (opsional), Active = ON
4. Save

### Step 2: Buat Computer (per PC/Mac)

1. **Computer Booking** → **Computers** → **Create**
2. Isi:
   - **Room**: pilih dari dropdown
   - **Name**: contoh "PC Editing 1" / "Mac Color 1"
   - **Brand**: Asus / Apple / dll
   - **Status**: Available
   - **Image**: foto komputer (opsional)
   - **Specs**: KeyValue → CPU/RAM/GPU/Storage
   - **Notes**: catatan internal
3. Save

Setelah save, sistem otomatis generate `checkin_slug` (24 karakter random). Slug ini permanent dan unik per komputer.

### Step 3: Atur Slot Operasional (sekali untuk seluruh lab)

1. **Computer Booking** → **Slot Management** → **Create**
2. Set per hari: Day, Start time, End time, Active = ON
3. Toggle **Jam Malam** (ON) untuk slot yang lewat jam operasional kampus normal — akan memunculkan banner perizinan menginap di form booking customer

Contoh setup default Senin–Jumat:
- 08:00–10:00, 10:00–12:00, 13:00–15:00, 15:00–17:00 (regular)
- 19:00–22:00, 22:00–01:00 (Jam Malam = ON)

---

## Bagian 3a: Setup Komputer WINDOWS

Lakukan **per PC Windows** yang sudah didaftarkan di Filament.

### Step 1: Generate pairing code

Di laptop admin (atau komputer mana saja yang bisa akses Filament):

1. `https://warehouse.ftvupi.id/admin` → **Computers** → klik PC tersebut → **Edit**
2. Klik tombol header **Kiosk App** → **Pair Kiosk App**
3. Notifikasi muncul: **"Pairing code: 123456 — berlaku hingga HH:MM"**
4. Catat kode 6-digit-nya

> **Penting:** kode TTL 5 menit. Kalau lewat, klik tombol lagi untuk generate baru.

### Step 2: Install aplikasi di PC lab

1. Copy `Warehouse FTV Kiosk-Setup-1.0.0.exe` ke flashdisk → tancap ke PC lab
2. Double-click `.exe`
3. Windows SmartScreen muncul **"Windows protected your PC"** → klik **More info** → **Run anyway**
4. Installer NSIS:
   - Pilih **"Install for all users"** (perlu admin password)
   - Pilih lokasi install (default `C:\Program Files\Warehouse FTV Kiosk\`)
   - **Install**
5. Aplikasi auto-launch → window kecil pairing 6-digit muncul

> Catatan: warning SmartScreen muncul karena installer belum di-code-sign. Aman diabaikan dengan "Run anyway". Untuk hilangkan warning, butuh code-signing certificate (~$200/tahun) — di-skip untuk v1.

### Step 3: Pair

1. Di window pairing, ketik 6-digit code dari Step 1
2. Klik **Pair**
3. Sukses → notifikasi hijau → app restart otomatis dalam 1.5 detik
4. Setelah restart: **full-screen kiosk page muncul** — menampilkan nama komputer di header, QR code, info booking hari ini

### Step 4: Verifikasi

Di laptop admin → Filament → **Computers** → cek PC tersebut:
- ✓ Kolom **Online** hijau
- ✓ **Last seen**: "few seconds ago"
- ✓ Klik **Edit** → **Kiosk App** → **Show Kiosk Status** → modal menampilkan app version, uptime, list app berjalan (kosong di awal)

Buka Adobe Premiere Pro di PC lab → tunggu 30 detik → refresh **Show Kiosk Status** → **Adobe Premiere Pro.exe** muncul di list "Aplikasi berjalan".

### Step 5: Verifikasi auto-start

Restart PC lab. Setelah login Windows, kiosk app harus auto-launch dalam 5–10 detik. Kalau tidak:

1. Buka `regedit` (Run as Administrator)
2. Navigasi: `HKEY_LOCAL_MACHINE\Software\Microsoft\Windows\CurrentVersion\Run`
3. Pastikan ada entry `WarehouseFTVKiosk` dengan path `C:\Program Files\Warehouse FTV Kiosk\Warehouse FTV Kiosk.exe`

### Reset pairing (kalau perlu re-pair)

**Di laptop admin:**
1. Filament → Edit Computer → **Kiosk App** → **Re-pair Kiosk App**
2. Token lama otomatis invalid + pairing code baru ter-generate

**Di PC lab:**
1. Ctrl+Shift+Esc → Task Manager → cari `Warehouse FTV Kiosk.exe` → End task
2. Win+R → ketik `%APPDATA%` → folder `Warehouse FTV Kiosk` → hapus `kiosk-config.json`
3. Restart komputer (atau jalankan Start Menu → Warehouse FTV Kiosk)
4. Window pairing muncul lagi → input code baru

---

## Bagian 3b: Setup Komputer MAC

Lakukan **per Mac** yang sudah didaftarkan di Filament. Mac tidak pakai Electron app — pakai Chrome kiosk mode + heartbeat via JavaScript.

### Step 1: Salin URL kiosk

Di laptop admin: Filament → **Computers** → klik Mac tersebut → **Edit** → tombol header **Check-in Page** (buka tab baru).

Salin URL lengkap, contoh:
```
https://warehouse.ftvupi.id/kiosk/checkin/aB7xK2nP9vQ3mR8sT4uV5wX6
```

### Step 2: Install Chrome di Mac (kalau belum)

Download dari [google.com/chrome](https://www.google.com/chrome/) → drag ke Applications.

### Step 3: Buat shortcut launch kiosk

Di Mac tersebut, buka **Terminal** dan jalankan:

```bash
cat > ~/Documents/launch-kiosk.command << 'EOF'
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
EOF
chmod +x ~/Documents/launch-kiosk.command
```

Lalu **edit `~/Documents/launch-kiosk.command`** dengan TextEdit → ganti `PASTE_SLUG_DI_SINI` dengan URL lengkap dari Step 1 → save.

### Step 4: Test

Double-click `launch-kiosk.command` di Finder → Chrome harus buka full-screen ke halaman kiosk Mac tersebut. Cek halaman menampilkan nama Mac di header.

**Tombol untuk keluar saat testing:** `Cmd+Q` atau `Esc`.

### Step 5: Auto-start saat login

System Settings → **General** → **Login Items & Extensions** → tab **Open at Login** → klik **+** → pilih `~/Documents/launch-kiosk.command` → **Add**.

Reboot Mac → setelah login, Chrome kiosk auto-launch.

### Step 6: Verifikasi heartbeat

Sebelum kiosk mode terkunci, buka DevTools dengan `Cmd+Option+I` → tab **Network** → cari request bernama `heartbeat-web` muncul tiap 30 detik dengan response `{"ok":true,"heartbeat_interval":30}`.

Lalu di laptop admin → Filament → Computers → cek Mac tersebut:
- ✓ **Online** hijau dalam < 1 menit
- ✓ **Last seen** "few seconds ago"

Tutup browser → tunggu 60 detik → komputer berubah menjadi **Offline**.

### Step 7 (opsional): Lockdown akun macOS

Untuk lab dengan banyak mahasiswa, buat akun macOS terpisah agar Cmd+Q tidak memberi akses ke file admin:

1. System Settings → **Users & Groups** → **Add User** → "Lab Kiosk" (Standard user, bukan Admin)
2. System Settings → **Users & Groups** → klik akun "Lab Kiosk" → **Automatic login as** = ON
3. Login ke akun "Lab Kiosk" → ulangi Step 3 + Step 5 di akun ini
4. Reboot → otomatis login ke "Lab Kiosk" → kiosk auto-launch

Mahasiswa Cmd+Q akan tetap di akun Lab Kiosk tanpa akses file admin / akun pribadi.

### Reset Mac (kalau slug berubah / pindah komputer)

1. Edit `~/Documents/launch-kiosk.command` → ganti URL baru → save
2. Cmd+Q Chrome
3. Double-click shortcut lagi (atau reboot)

**Tidak perlu pairing code untuk Mac.**

---

## Bagian 4: Workflow Harian

### Untuk admin (di Filament `/admin`)

| Tugas | Cara |
|-------|------|
| Lihat semua status komputer real-time | Computers → kolom **Online** + **Sedang Dipakai** live |
| Filter komputer yang aktif | Klik filter **Online only** |
| Detail teknis 1 komputer | Klik komputer → **Show Kiosk Status** (Windows only) |
| Generate pairing code baru | Edit Computer → **Pair / Re-pair Kiosk App** |
| Atur quota / slot / T&C | Settings → **Computer Booking** |
| Kelola booking masuk | Computer Booking → **Bookings** |
| Lihat kalender visual | Computer Booking → **Calendar** |
| Toggle maintenance | Edit Computer → **Set Maintenance** dengan alasan |

### Untuk mahasiswa (di komputer lab)

1. Datang ke komputer → kiosk page sudah terbuka full-screen
2. Lihat info booking sendiri di panel "Booking saat ini" → klik **Check-in**
3. Login akun warehouse di redirect screen (sekali per session)
4. Selesai — silakan pakai komputer

**Walk-in (datang tanpa booking):**
- Klik **Check-in & Isi Data** → login → sistem otomatis bikin booking untuk slot saat ini

### Untuk reservasi (di mana saja, browser)

1. `https://warehouse.ftvupi.id/computers` → pilih ruangan → komputer
2. Wizard 3-langkah: tanggal → slot (boleh multi-slot) → konfirmasi (purpose + T&C)
3. **My Computer Bookings** di profile dropdown → lihat / cancel booking

---

## Bagian 5: Update Aplikasi

Ada 4 jenis update — kebanyakan **tidak perlu menyentuh komputer lab sama sekali**.

### A. Update halaman web / Laravel (sering — 90% kasus)

Misal: ubah tampilan check-in, fix bug, tambah fitur Filament.

**Eksekusi (di server saja):**

```bash
cd /path/to/warehouse-ftv
git pull
composer install --no-dev          # kalau ada dependency PHP baru
php artisan migrate                # kalau ada migrasi baru
npm run build                      # kalau ada perubahan CSS/JS frontend
php artisan config:cache
php artisan route:cache
php artisan view:clear
```

**Komputer lab:**
- ✗ **Tidak perlu sentuh apa-apa**
- Mac: Chrome auto-reload halaman dalam 30 detik berikutnya
- Windows: Electron renderer otomatis pakai HTML baru saat halaman re-render

### B. Update aplikasi Electron (jarang — kalau ubah `desktop-kiosk/`)

Hanya perlu kalau modifikasi:
- `desktop-kiosk/src/main.js` — kiosk behavior, IPC, lifecycle
- `desktop-kiosk/src/heartbeat.js` — interval, payload format
- `desktop-kiosk/src/running-apps.js` — whitelist, OS detection
- `desktop-kiosk/src/auto-update.js`
- `package.json` dependencies

**Eksekusi (di komputer dev):**

```bash
cd desktop-kiosk

# 1. Bump version di package.json — WAJIB
#    "version": "1.0.0" → "1.0.1"

# 2. Build installer baru
npm run dist
```

**Upload ke server:**

```bash
scp dist/Warehouse\ FTV\ Kiosk-Setup-1.0.1.exe \
    dist/latest.yml \
    dist/*.blockmap \
    user@warehouse.ftvupi.id:/var/www/warehouse-ftv/storage/app/kiosk-releases/
```

**Tidak perlu hapus file lama** — `latest.yml` yang menentukan versi mana yang dipakai. File lama tetap di folder, berguna untuk rollback.

(Opsional) Update Setting di Filament → Settings → Computer Booking → **Latest App Version**: `1.0.1`. Ini hanya untuk display; tidak mempengaruhi proses update.

**Komputer lab Windows:**
- Dalam 6 jam berikutnya, electron-updater di tiap PC GET `latest.yml`
- Version baru terdeteksi → background download installer
- Install **saat next reboot** (tidak ganggu mahasiswa yang lagi pakai)
- Mahasiswa tidak melihat apa-apa — seamless

**Mac:** ✗ tidak terpengaruh (tidak pakai Electron).

### C. Update database schema

Sama seperti A, tambahan migrasi:

```bash
php artisan migrate
```

Kalau perubahan kolom dipakai di Blade kiosk (misal field baru di `computers`), Mac & Windows otomatis dapat dari halaman web yang re-render.

Kalau migrasi mengubah signature endpoint heartbeat (jarang), perlu juga bump Electron app version (lihat B).

### D. Force update Electron segera (tidak mau tunggu 6 jam)

Skenario: bug critical, harus update sekarang.

**Cara cepat:** restart komputer lab. Saat boot, Electron cek update dalam 30 detik setelah app ready → langsung download → apply.

**Tanpa restart (mahasiswa lagi pakai):**
- Tunggu komputer idle
- Ctrl+Shift+Esc → End task `Warehouse FTV Kiosk.exe`
- Start Menu → Warehouse FTV Kiosk → klik manual untuk relaunch

### Rollback Electron (kalau update rusak)

Kalau v1.0.1 ternyata bug:

1. Edit `latest.yml` di server → ganti version `1.0.1` → `1.0.0`
2. PC yang sudah update ke 1.0.1 **tidak otomatis downgrade** (electron-updater hanya forward)
3. Manual rollback per PC:
   - Settings → Apps → Uninstall "Warehouse FTV Kiosk"
   - Install ulang `Warehouse FTV Kiosk-Setup-1.0.0.exe` dari flashdisk
   - Hapus `%APPDATA%\Warehouse FTV Kiosk\kiosk-config.json`
   - Pair ulang

> **Pelajaran:** test build Electron di 1 PC sebelum upload ke server.

### E. Update Mac kiosk script

Kalau perlu ubah Chrome flags atau URL kiosk:

1. Edit `~/Documents/launch-kiosk.command` di tiap Mac (manual, tidak ada auto-update)
2. Save → Cmd+Q Chrome → double-click shortcut

Kalau lab Mac banyak: taruh script di iCloud Drive yang di-symlink dari `~/Documents/launch-kiosk.command` di tiap Mac → update sentral di satu tempat.

### Ringkasan apa yang perlu dilakukan

| Yang berubah | `git pull` server | `npm run dist` Electron | Sentuh PC lab |
|--------------|:-:|:-:|:-:|
| Blade view (UI kiosk, halaman booking) | ✓ | ✗ | ✗ |
| Controller / Service / Model | ✓ | ✗ | ✗ |
| Migrasi database | ✓ + `migrate` | ✗ | ✗ |
| Filament admin (resource, page, action) | ✓ | ✗ | ✗ |
| CSS / JS frontend | ✓ + `npm run build` | ✗ | ✗ |
| Endpoint heartbeat (signature payload) | ✓ | ✓ kalau payload berubah | ✗ (auto-update 6h) |
| `desktop-kiosk/src/*.js` | ✗ | ✓ | ✗ (auto-update 6h) |
| `desktop-kiosk/package.json` deps | ✗ | ✓ | ✗ (auto-update 6h) |
| Rollback Electron version | ✗ | ✗ | ✓ (manual reinstall) |
| `launch-kiosk.command` di Mac | ✗ | ✗ | ✓ (per Mac) |

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| PC Windows muncul Offline padahal app jalan | Cek Task Manager `Warehouse FTV Kiosk.exe` running. Cek halaman kiosk bisa kebuka di app (kalau blank → server tidak reachable). Cek **Show Kiosk Status** di Filament: `last_heartbeat_data` ada? Kalau kosong = token salah, **Re-pair** |
| Mac muncul Offline | Buka DevTools (Cmd+Option+I) sebelum kiosk terkunci → Network tab → cari `heartbeat-web` ada response 200. Kalau 404 = slug salah di shortcut, edit ulang. Kalau 429 = throttle (max 6/menit, jangan polling lebih cepat dari 10dtk) |
| Pairing code "invalid_or_expired_code" | Code TTL 5 menit. Klik **Re-pair** di Filament untuk generate baru |
| Halaman kiosk blank putih di Electron | Server down — app auto-retry tiap 5 detik. Cek `php artisan pail` di server |
| Mac: Cmd+Q masih bisa keluar | Limit macOS tanpa MDM. Mitigasi: bikin akun Lab Kiosk Standard User terpisah (Step 7 Bagian 3b) |
| Windows SmartScreen blok installer | "More info" → "Run anyway". Permanent fix: beli code-signing cert ($200-400/tahun) |
| Auto-update Electron tidak jalan | Test `https://warehouse.ftvupi.id/api/kiosk/update/latest.yml` di browser harus tampil YAML. Kalau 404, file belum di-upload ke `storage/app/kiosk-releases/` |
| App `running_apps` tidak tertangkap di Filament | Cek `computer_kiosk_running_apps_whitelist` di Settings → nama proses harus persis sama dengan output `tasklist` (case-insensitive). Contoh `Adobe Premiere Pro.exe`, bukan `Premiere.exe` |
| Banyak PC tidak online setelah server restart | Heartbeat retry tiap 30 detik. Tunggu 1-2 menit. Kalau masih tidak, cek firewall server tidak block `/api/kiosk/heartbeat` |
| File installer `.exe` ditolak antivirus lab | Submit ke vendor antivirus untuk whitelist (Symantec / Kaspersky / dll punya self-service portal). Atau code-sign installer |

---

## Kontak & Dokumentasi Lain

- **Dokumentasi developer**: lihat `CLAUDE.md` di root repo
- **Plan iterasi 1-3**: `komputer_book.md` (planning awal) + `C:\Users\danga\.claude\plans\` (eksekusi detail)
- **Source Electron app**: `desktop-kiosk/README.md`
