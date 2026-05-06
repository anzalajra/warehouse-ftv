================================================================
  PANDUAN SETUP LAB KOMPUTER - WAREHOUSE FTV UPI
  Versi singkat untuk admin lab
================================================================

Dokumen ini panduan praktis untuk:
  A. Setup komputer baru (Windows / Mac)
  B. Update aplikasi
  C. Cara reset / troubleshoot

Untuk dokumentasi lengkap dengan command developer, lihat file
"setup_komputer.md".


================================================================
  YANG ANDA BUTUHKAN
================================================================

- Laptop admin yang bisa akses Filament:
  https://warehouse.ftvupi.id/admin
  (login pakai akun super_admin)

- Untuk PC Windows lab:
  * Flashdisk berisi file installer "Warehouse FTV Kiosk-Setup-X.X.X.exe"
  * Akun Administrator di PC tersebut

- Untuk Mac lab:
  * Sudah ada Google Chrome (kalau belum, download dari
    https://www.google.com/chrome/)


================================================================
  A1. SETUP PC WINDOWS BARU (10 MENIT)
================================================================

LANGKAH 1 - DAFTARKAN DI FILAMENT
----------------------------------
1. Buka https://warehouse.ftvupi.id/admin
2. Sidebar: "Computer Booking" -> "Rooms"
3. Klik "Create" - isi nama ruangan, save
   (skip kalau ruangan sudah ada)
4. Sidebar: "Computer Booking" -> "Computers"
5. Klik "Create":
   - Room: pilih ruangan
   - Name: contoh "PC Editing 1"
   - Brand: Asus / dll
   - Status: Available
   - Specs (opsional): tambah CPU, RAM, GPU
6. Save


LANGKAH 2 - GENERATE PAIRING CODE
----------------------------------
1. Di Filament, masih di halaman Edit Computer tadi
2. Klik tombol "Kiosk App" (di kanan atas)
3. Klik "Pair Kiosk App"
4. Notifikasi muncul dengan KODE 6 ANGKA
   Contoh: "Pairing code: 482917"
5. Catat kode itu - berlaku 5 MENIT saja


LANGKAH 3 - INSTALL DI PC LAB
------------------------------
1. Tancap flashdisk ke PC lab
2. Double-click "Warehouse FTV Kiosk-Setup-1.0.0.exe"
3. Windows akan munculkan peringatan
   "Windows protected your PC"
   -> Klik "More info"
   -> Klik "Run anyway"
4. Installer minta password Administrator
5. Pilih "Install for all users" -> Install
6. Setelah selesai, aplikasi auto-launch
7. Window kecil muncul: "Pairing Aplikasi Kiosk"


LANGKAH 4 - PAIRING
--------------------
1. Ketik 6 angka dari Langkah 2 ke kolom input
2. Klik "Pair"
3. Tunggu - app akan restart sendiri (1-2 detik)
4. Setelah restart, halaman full-screen muncul
   menampilkan nama komputer + QR code + booking hari ini
5. SELESAI


LANGKAH 5 - VERIFIKASI
-----------------------
Di laptop admin:
1. Filament -> Computers
2. Cari komputer yang baru di-setup
3. Kolom "Online" harus hijau (centang)
4. Kolom "Last seen" harus "few seconds ago"

Kalau OFFLINE setelah 1 menit:
- Cek Task Manager di PC lab, "Warehouse FTV Kiosk.exe" jalan?
- Cek koneksi internet PC lab (buka warehouse.ftvupi.id di browser)
- Kalau perlu, lakukan RE-PAIR (Langkah A4 di bawah)


LANGKAH 6 - TEST AUTO-START
----------------------------
1. Restart PC lab
2. Login ke Windows
3. Dalam 5-10 detik, kiosk page harus muncul full-screen
   otomatis tanpa di-klik manual

Kalau tidak auto-launch: hubungi developer.


================================================================
  A2. SETUP MAC BARU (5 MENIT)
================================================================

Mac TIDAK PAKAI installer aplikasi - cuma pakai Chrome dengan
mode kiosk. Tidak ada pairing code untuk Mac.


LANGKAH 1 - DAFTARKAN DI FILAMENT
----------------------------------
Sama persis dengan Windows Langkah 1 (lihat A1 di atas).


LANGKAH 2 - SALIN URL KIOSK
----------------------------
1. Di Filament: Computers -> klik Mac tersebut -> Edit
2. Klik tombol "Check-in Page" (buka di tab baru)
3. Salin URL lengkap di address bar, contoh:

   https://warehouse.ftvupi.id/kiosk/checkin/aB7xK2nP9vQ3mR8sT4uV5wX6


LANGKAH 3 - INSTALL CHROME
---------------------------
Kalau Chrome belum ada di Mac:
1. Buka Safari -> google.com/chrome
2. Download -> drag Google Chrome.app ke folder Applications


LANGKAH 4 - BUAT SHORTCUT KIOSK
--------------------------------
1. Di Mac itu, buka aplikasi "Terminal"
   (Cmd+Space -> ketik "Terminal" -> Enter)

2. Copy-paste command berikut SEMUA SEKALIGUS:

cat > ~/Documents/launch-kiosk.command << 'EOF'
#!/bin/bash
KIOSK_URL="https://warehouse.ftvupi.id/kiosk/checkin/PASTE_SLUG_DI_SINI"
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
  --kiosk \
  --no-first-run \
  --disable-pinch \
  --noerrdialogs \
  --disable-translate \
  --user-data-dir="$HOME/.chrome-kiosk" \
  --app="$KIOSK_URL"
EOF
chmod +x ~/Documents/launch-kiosk.command

3. Tekan Enter

4. Buka Finder -> Documents -> klik kanan "launch-kiosk.command"
   -> "Open With" -> "TextEdit"

5. Cari teks "PASTE_SLUG_DI_SINI"
   Ganti dengan URL dari Langkah 2

   Hasil akhir baris itu kira-kira seperti ini:
   KIOSK_URL="https://warehouse.ftvupi.id/kiosk/checkin/aB7xK2nP9vQ3mR8sT4uV5wX6"

6. Save (Cmd+S) - tutup TextEdit


LANGKAH 5 - TEST
-----------------
1. Buka Finder -> Documents
2. Double-click "launch-kiosk.command"
3. Chrome harus buka FULL-SCREEN langsung ke halaman kiosk
4. Cek halaman menampilkan nama Mac dengan benar
5. Untuk keluar saat testing: Cmd+Q


LANGKAH 6 - AUTO-START
-----------------------
1. System Settings (apple icon kiri atas -> System Settings)
2. General -> "Login Items & Extensions"
3. Tab "Open at Login" -> klik tombol "+"
4. Cari folder Documents -> pilih "launch-kiosk.command"
5. Klik "Add"


LANGKAH 7 - VERIFIKASI
-----------------------
Reboot Mac:
1. Apple icon -> Restart
2. Login ke Mac
3. Setelah login, Chrome kiosk harus auto-launch dalam 5 detik

Di laptop admin:
1. Filament -> Computers
2. Mac tersebut harus "Online" hijau dalam < 1 menit


LANGKAH 8 (OPSIONAL) - LOCKDOWN AKUN
-------------------------------------
Untuk lab dengan banyak mahasiswa, bikin akun Mac terpisah:

1. System Settings -> Users & Groups
2. Klik "Add User" -> Standard (BUKAN Admin)
3. Nama: "Lab Kiosk", password: bebas (tidak penting)
4. Klik "Automatically log in as" -> pilih "Lab Kiosk"
5. Logout dari akun admin, login ke "Lab Kiosk"
6. Ulangi Langkah 4-6 di akun Lab Kiosk
7. Reboot

Sekarang kalau mahasiswa Cmd+Q, mereka tetap di akun Lab Kiosk
yang tidak punya akses ke file pribadi admin.


================================================================
  B. UPDATE APLIKASI - WORKFLOW HARIAN
================================================================

Sebagian besar update DILAKUKAN OLEH DEVELOPER DI SERVER, dan
admin lab tidak perlu menyentuh komputer lab. Berikut yang
mungkin Anda alami:


B1. UPDATE TAMPILAN HALAMAN / FITUR BARU
-----------------------------------------
Developer push update ke server -> selesai.

Komputer Windows: HTML auto-refresh dalam beberapa detik.
Komputer Mac: Chrome auto-reload halaman.

ANDA TIDAK PERLU APA-APA.


B2. UPDATE APLIKASI ELECTRON (PC WINDOWS)
------------------------------------------
Jarang terjadi - hanya kalau developer ubah behavior aplikasi
desktop (misal: cara deteksi running apps berubah).

Cara kerja:
1. Developer build versi baru, upload ke server
2. PC lab Windows otomatis cek update SETIAP 6 JAM
3. Kalau ada versi baru, di-download diam-diam (background)
4. Update DIPASANG SAAT NEXT REBOOT komputer

ANDA TIDAK PERLU APA-APA. Mahasiswa tidak akan terganggu - update
muncul setelah komputer di-restart, biasanya esok harinya.


B3. UPDATE PAKSA SEKARANG
--------------------------
Kalau ada bug critical dan harus update segera, bukan tunggu
6 jam:

CARA TERCEPAT:
1. Restart PC lab tersebut
2. Saat boot, app cek update dalam 30 detik -> langsung pasang

KALAU PC SEDANG DIPAKAI MAHASISWA:
1. Tunggu mahasiswa selesai
2. Ctrl+Shift+Esc -> Task Manager
3. End task "Warehouse FTV Kiosk.exe"
4. Start Menu -> klik "Warehouse FTV Kiosk" untuk launch ulang


B4. UPDATE MAC
---------------
Mac tidak punya aplikasi - jadi tidak ada "update aplikasi".
Kalau halaman web di-update oleh developer, Mac otomatis dapat
versi baru saat Chrome reload.

Kalau URL kiosk berubah (jarang sekali), edit shortcut:
1. Finder -> Documents -> klik kanan "launch-kiosk.command"
2. Open With -> TextEdit
3. Ganti URL di baris KIOSK_URL=
4. Save -> Cmd+Q Chrome -> double-click shortcut lagi


================================================================
  C. RESET & TROUBLESHOOTING
================================================================


C1. RESET PAIRING WINDOWS
--------------------------
Kalau PC lab tidak online lagi, atau pindah komputer, atau
tokennya rusak:

DI LAPTOP ADMIN:
1. Filament -> Computers -> Edit -> "Kiosk App" -> "Re-pair Kiosk App"
2. Catat pairing code baru (6 angka)

DI PC LAB:
1. Ctrl+Shift+Esc -> Task Manager
2. Cari "Warehouse FTV Kiosk.exe" -> End task
3. Tekan Win+R -> ketik: %APPDATA%
4. Buka folder "Warehouse FTV Kiosk"
5. Hapus file "kiosk-config.json"
6. Restart komputer (atau Start Menu -> Warehouse FTV Kiosk)
7. Window pairing muncul -> input kode baru -> Pair


C2. RESET MAC
--------------
Kalau slug berubah / pindah komputer / butuh refresh:

1. Tutup Chrome (Cmd+Q)
2. Edit shortcut ~/Documents/launch-kiosk.command
   -> ganti URL kiosk
3. Save
4. Double-click shortcut lagi (atau reboot)


C3. MASALAH UMUM
-----------------

PC Windows OFFLINE walau aplikasi jalan
  -> Cek koneksi internet (buka warehouse.ftvupi.id di browser)
  -> Kalau halaman kiosk blank putih, server lagi down
  -> Kalau biasa lagi, lakukan RE-PAIR (C1)

Mac OFFLINE walau Chrome kiosk jalan
  -> Cek slug di shortcut benar (lihat URL di Filament Edit Computer)
  -> Kalau salah, edit shortcut lagi (C2)

Pairing code "invalid_or_expired_code"
  -> Kode TTL 5 menit, mungkin expired
  -> Klik "Re-pair Kiosk App" lagi di Filament untuk kode baru

Halaman kiosk blank putih
  -> Server down sementara
  -> Tunggu 30 detik, app auto-retry sendiri
  -> Kalau lebih dari 5 menit, hubungi developer

Windows SmartScreen blok installer
  -> "More info" -> "Run anyway"
  -> Ini normal karena installer belum di-code-sign

Mac: Cmd+Q masih bisa keluar
  -> Limit macOS, tidak bisa di-disable tanpa MDM
  -> Workaround: bikin akun "Lab Kiosk" Standard User (Langkah 8)

Anti-virus blok installer Windows
  -> Whitelist file di software anti-virus
  -> Atau hubungi developer untuk solusi code-signing


================================================================
  D. KONTAK
================================================================

Untuk masalah yang tidak bisa diselesaikan dengan dokumen ini:

- Developer / IT support: [isi kontak]
- Dokumentasi lengkap (untuk developer): setup_komputer.md
- Repo source code: [isi URL repo]


================================================================
  CATATAN AKHIR
================================================================

- Pairing code TTL: 5 menit
- Auto-update Electron cek tiap: 6 jam
- Heartbeat dari komputer lab tiap: 30 detik
- Komputer dianggap OFFLINE kalau tidak ada heartbeat: 60 detik
  (semua angka ini bisa diubah di Filament -> Settings ->
   Computer Booking)

Setiap komputer punya URL kiosk unik (slug) yang permanent dan
TIDAK BERUBAH - kecuali admin manual hapus & buat ulang
komputer di Filament.

Selamat mengelola lab! :)
================================================================
