# Integrasi Mufise untuk Warehouse FTV

Mengikuti kontrak API v1 Mufise tanggal 3 November 2026, termasuk token tanpa batas waktu, diagnostik aman, onboarding organisasi dan `GET /me` (`next_action`). Seluruh komunikasi dilakukan server Laravel. Tidak perlu akses database Mufise atau konfigurasi CORS.

## Mengaktifkan

1. Terapkan migrasi dengan `php artisan migrate`. Cadangkan database sesuai prosedur deployment biasa.
2. Di Mufise, admin organisasi membuat koneksi eksternal Warehouse FTV dan mengatur izin tulis kru/lokasi sesuai kebutuhan.
3. Masuk sebagai super admin Warehouse, buka **Settings → Integrasi Mufise**. Isi URL dasar Mufise, Connection ID, API key dan aktifkan integrasi. URL wajib HTTPS kecuali localhost. Key disimpan terenkripsi; input kosong mempertahankan key lama. Admin yang didelegasikan memerlukan izin `Manage:Mufise` pada guard web.
4. Jika diperlukan, URL/Connection ID/API key dapat memakai fallback `MUFISE_URL`, `MUFISE_CONNECTION_ID`, `MUFISE_API_KEY` dari `config/services.php`. Setting panel mengambil prioritas. Setelah mengganti env, perbarui config cache sesuai deployment.
5. Pelanggan membuka **Profile → Koneksi akun Mufise** atau **Koneksi Mufise** pada menu akun. Klik **Connect Mufise Account** untuk langsung membuka tab persetujuan Mufise. Jika tab terblokir, gunakan **Halaman Mufise tidak muncul, klik di sini**. Masuk atau daftar akun, lalu selesaikan keanggotaan organisasi serta pembuatan/pemilihan proyek di Mufise. Halaman Warehouse menampilkan **waiting for syncronization** dan memeriksa persetujuan setiap minimal 5 detik, maksimal 10 menit. Tautan tidak memuat request secret.
6. Setelah terhubung, pelanggan dapat melihat proyek dan memilih **Gunakan proyek lain / Buat proyek baru**. Grant sebelumnya tetap dipakai sampai pemasangan baru berhasil. Token berlaku maksimal 90 hari tanpa refresh token. Proyek yang sebelumnya ditautkan ke rental harus tetap dipilih pada grant pengganti agar rental dapat diverifikasi.
7. Di checkout, gunakan **Hubungkan Akun Mufise** atau **Kelola Proyek Mufise**, lalu klik **Perbarui Proyek** setelah koneksi selesai. Pilihan diperbarui tanpa memuat ulang halaman atau menghapus isian checkout. Pilihan proyek tetap tersimpan selama proyek tersebut masih dibagikan.

Organisasi tujuan mengikuti Connection ID dan API key yang dipasang admin, bukan input organisasi dari browser pelanggan. Permintaan koneksi mengirim ID pengguna dari sesi Warehouse dan nama akun ke `POST /link-requests`; ID permintaan yang dihasilkan Mufise mengikat alur persetujuan/pendaftaran ke organisasi koneksi tersebut. Halaman Mufise mempertahankan konteks ini saat pengguna masuk atau mendaftar. Bergabung otomatis mengikuti aturan Mufise; verifikasi identitas/CSV dan persetujuan admin organisasi tetap berlaku. Warehouse tidak membuat akun atau keanggotaan langsung tanpa autentikasi Mufise.

## Perilaku peminjaman

- Integrasi hanya diwajibkan pada checkout **luar kelas baru** ketika diaktifkan. Jenis tugas tetap wajib. Kru dan lokasi manual diganti oleh pilihan proyek Mufise; tidak dibuat itinerary lokal dengan data NIM/jadwal buatan.
- Akun yang belum siap diarahkan melengkapi keanggotaan, membuat proyek atau memilih proyek melalui halaman persetujuan Mufise, sesuai `next_action`. Pembuatan proyek dan persetujuan organisasi mengikuti kewenangan Mufise.
- Sebelum pengajuan, server mengambil proyek memakai token milik peminjam dan memeriksa `readiness.can_submit`. Kebutuhan kru, jabatan, lokasi dan call sheet mengikuti kebijakan koneksi Mufise beserta syarat tambahan Warehouse. Keranjang tetap tersimpan apabila validasi gagal; akun/proyek dapat dipersiapkan sebagai draf sebelum pengajuan.
- Saat pengambilan, `Rental::validatePickup()` memeriksa ulang proyek, `can_collect`, serta keberadaan/lock **Day 1** hanya jika diwajibkan kebijakan. Dokumen JSON diperiksa terhadap Day ID, nomor hari dan revisi jadwal. Hasil pemeriksaan serta HTML call sheet disimpan terenkripsi pada rental bersama waktu pemeriksaan. Pemeriksaan ini merekam keadaan ketika diambil; bukan webhook atau jaminan data Mufise tidak berubah setelahnya.
- Gangguan jaringan, credential/grant dicabut, proyek tak dapat diakses, atau perubahan revisi menahan pengambilan. Tidak ada bypass otomatis. Hubungkan ulang akun atau perbaiki izin/koneksi dan ulangi validasi.
- Detail rental pelanggan dan admin menyediakan **Kru, lokasi & call sheet**. Petugas mengakses melalui policy transaksi dan token peminjam. Call sheet disajikan melalui proksi Warehouse dengan login, cache privat, CSP dan iframe sandbox.
- Tambah/edit kru dan lokasi mengikuti izin API. Perubahan profil kru dapat berdampak lintas proyek; kontak akun terhubung harus diperbarui pemilik di Mufise. Personel yang sudah ada dipilih melalui direktori organisasi dan jabatan dari katalog, sesuai izin editor yang disetujui.
- Setiap formulir mutasi mempunyai UUID operasi. Body, tujuan dan UUID disimpan terenkripsi sebelum panggilan API. Kirim ulang formulir yang sama setelah hasil tak diketahui untuk memakai key/body yang sama. Perubahan body dengan UUID yang sama ditolak. Setelah konflik versi atau perubahan input, muat ulang, tinjau data terbaru dan kirim formulir baru. Konfirmasi nama serupa hanya setelah memastikan orangnya berbeda.
- Mengganti URL/Connection ID membuat koneksi akun sebelumnya tidak dapat dipakai untuk tujuan baru. Rotasi API key pada koneksi yang sama mempertahankan grant pengguna.
- Mematikan setting membiarkan checkout baru memakai alur itinerary sebelumnya. Rental yang sudah bertanda `mufise_required` tetap wajib diverifikasi, sehingga pengambilannya tertahan selama koneksi dimatikan. Rental lama dan peminjaman dalam kelas tidak mendapat persyaratan baru. Jangan menghapus penanda tersebut untuk melewati validasi.
- Peminjam pada rental terhubung tidak dapat diganti di editor. Gunakan transaksi baru untuk peminjam lain. Pengaktifan status harus melalui proses pengambilan, bukan pemilihan status langsung pada editor.

## Keamanan dan verifikasi

Pertahankan APP_KEY dan cadangannya agar token, key dan snapshot dapat didekripsi. Token serta pairing disembunyikan dari serialisasi model. API client tidak meneruskan exception HTTP berisi header/body mentah. Tidak ada key/token pada URL dokumen, bundle JavaScript, VITE env atau Electron. Tidak ada retry otomatis untuk penukaran token sekali pakai atau mutasi yang belum diketahui hasilnya.

Tes lokal memakai `Http::fake()` dan SQLite in-memory, tanpa mengubah data Mufise:

```sh
php artisan test tests/Feature/MufiseIntegrationTest.php
node --test tests/js/mufise-ui.test.mjs
composer test:smoke
composer test
npm run build
```

Penerimaan langsung dengan server Mufise yang telah memiliki API terbaru dan kredensial nyata masih diperlukan sebelum deployment: uji editor/viewer, organisasi yang memerlukan persetujuan, proyek tanpa kru/lokasi, lock Day 2 saja, lock Day 1, pencabutan grant/izin, konflik versi dan koneksi putus. Jangan memasukkan key/token ke laporan pengujian atau log.


## Pembaruan token dan pemulihan koneksi (3 November 2026)

Token baru dengan `expires_in: null` disimpan sebagai `expires_at: null` dan tetap dapat dipakai setelah refresh/restart. Expiry positif dari server lama tetap dihormati; field expiry yang hilang, nol, negatif atau bukan integer ditolak. Masa pemasangan tetap maksimal 10 menit dan polling minimal 5 detik. Skema `mufise_accounts.expires_at` sejak awal sudah nullable sehingga tidak memerlukan migrasi Warehouse baru.

Sesudah kode dan aset terbaru diterapkan, pengguna yang terkena bug expiry lama dapat memulai pemasangan baru satu kali, menerima koneksi di Mufise, lalu menekan **Perbarui Proyek** pada checkout. Token yang benar-benar dicabut/kedaluwarsa juga memerlukan pemasangan baru. Tidak ada penghapusan token otomatis karena gangguan jaringan, konfigurasi salah, email belum diverifikasi atau akun ditangguhkan.

Untuk memulihkan token lama tanpa meminta semua pengguna pairing ulang:

1. Pastikan server Mufise tujuan sudah memakai kontrak token tanpa batas waktu/migrasi 051. Panduan atau kode lokal Mufise bukan bukti versi hosting; periksa build yang diterapkan lewat proses deployment Mufise. Jangan memindahkan token antarlingkungan.
2. Jalankan `php artisan mufise:repair-token-expiry` untuk pemeriksaan tanpa mengubah data. Command hanya memilih record URL/Connection ID saat ini dengan token dan expiry tersimpan, lalu memanggil `/me` langsung tanpa pemeriksaan expiry lokal. Pairing yang sedang berlangsung dilewati.
3. Setelah kontrak server dikonfirmasi, jalankan `php artisan mufise:repair-token-expiry --apply --confirmed-no-expiry`. Expiry hanya dikosongkan untuk token dengan respons `/me` valid. Token dicabut, timeout, konfigurasi salah dan respons tidak valid dipertahankan. Command mengembalikan status gagal bila ada token gagal diverifikasi, walaupun token lain berhasil dipulihkan. Keluaran hanya memuat jumlah, kategori gagal dan referensi.

Error mempertahankan pesan lama pada JSON controller dan menambahkan metadata aman `reason`, `action`, `request_id`, `warehouse_request_id`, `retryable`, `retry_after`. UI menampilkan tindakan serta referensi, membedakan masalah key server, token, email, penangguhan, izin dan gangguan sementara. Disconnect tetap memerlukan admin organisasi Mufise; penolakan tidak menghapus token lokal.

Log `mufise.request`, `mufise.transport_failed`, `mufise.configuration_failed` dan `mufise.local_problem` hanya memakai field yang diizinkan. Referensi Mufise dibaca dari header `X-Request-ID` dengan fallback JSON dan harus berupa UUID. Endpoint log memakai template tanpa ID proyek/personel. Body, token, API key, request secret dan exception transport asli tidak dicatat. Reporter/proxy hosting di luar client tetap perlu memakai penyamaran rahasia yang sama. APP_KEY harus dipertahankan; kegagalan dekripsi ditampilkan sebagai masalah penyimpanan/konfigurasi tanpa menghapus ciphertext.

## Kontrak editor dan kebijakan 4 November 2026

`readiness.can_submit` dan `can_collect` mengikuti `requirements` koneksi. Pesan kekurangan memakai `missing_by_stage` serta `missing_crew_roles`. Pengambilan mewarisi syarat pengajuan: call sheet Day 1 hanya diambil ketika salah satu tahap mewajibkan `exists`/`locked`. Mode `exists` tidak memaksa lock; mode `none` menyimpan snapshot tanpa dokumen. Pemeriksaan kedua membandingkan `policy_revision`, `sync_version`, revisi jadwal dan identitas Day 1 sebelum serah terima.

Pengaturan admin **Integrasi Mufise** menambahkan syarat lokal sebelum booking: kru, lokasi/alamat dan call sheet Day 1. Bawaan mengikuti Mufise. Syarat lokal hanya menambah kewajiban; API key layanan tidak dapat mengubah atau menurunkan kebijakan organisasi. Mengubah syarat dasar, cakupan tulis atau fitur editor tetap melalui **Organisasi → Koneksi eksternal** di Mufise. Tersedia kontrol edit ringkas checkout dan interval sinkronisasi 5–300 detik (bawaan 15). Tombol **Tes koneksi tersimpan** memeriksa `/me` tanpa token/pairing: `user_token_missing` membuktikan kredensial koneksi diterima, bukan kesiapan akun/proyek. Simpan pengaturan sebelum menguji. Kunci tersimpan tidak dikirim kembali ke browser.

Halaman proyek Warehouse dan checkout memakai editor bersama:

- Bootstrap `/editor` harus menyatakan `mufise.external-editor.v1`; fitur dan izin efektif dibaca terpisah. Server lama menampilkan pesan dukungan editor, tanpa mengasumsikan izin baru tersedia.
- Picker kru organisasi dengan pencarian tertunda, pagination, katalog jabatan dan konfirmasi penugasan. Kru baru melalui preview duplikat; konfirmasi orang berbeda dilakukan pengguna. Profil menghormati identitas tertaut, versi, alasan, foto, ketersediaan dan izin pelepasan.
- Lokasi memiliki titik syuting/parkir/rumah sakit, preview pin OpenStreetMap, input koordinat manual, pencarian alamat opsional, tahap Check → Recce → Lock dan ketersediaan. Versi teks berbeda dari `editor_version` detail.
- Editor hari call sheet memuat sumber scene/shot sesuai izin, kegiatan dengan UUID stabil, urutan keyboard/drag, kru harian, HOD, cuaca, waktu, catatan, preview/PDF dan tindakan lock/unlock/hapus terpisah dengan konfirmasi.
- Checkout menampilkan ringkasan proyek setelah dipilih, status syarat, tab data dan dialog edit ringkas; **Editor lengkap** membuka halaman proyek Warehouse. **Buka di Mufise** membuka halaman utama Mufise, sesuai pilihan pengguna; belum ada deep link proyek dalam kontrak UI Mufise yang ditinjau.

Route `*.api` dan `*.media` di ketiga konteks (proyek pelanggan, rental pelanggan, rental admin) selalu mengambil akun/token dari sesi atau rental yang lolos policy. Jalur proksi dibatasi; browser tidak dapat memilih host, kredensial atau proyek upstream lain. Perubahan rental hanya tersedia sebelum pickup. Dokumen HTML memakai sandbox/CSP, PDF attachment dan foto privat disaring MIME, `nosniff`, `no-store`; redirect/login HTML tidak diteruskan sebagai PDF/foto.

Operasi menyimpan UUID, target, method, body dan foto terenkripsi sebelum dikirim. Retry hasil tak pasti memakai operasi yang sama, termasuk file; setelah reload foto harus dipilih ulang identik. Hasil idempotensi tetap diminta ke Mufise untuk memeriksa izin terkini. PATCH mempertahankan field yang dihilangkan serta string/array kosong yang disengaja. Tidak ada autosave paralel jadwal.

Polling berhenti pada tab tersembunyi dan mengikuti jeda API. Client membatasi gabungan koneksi Warehouse menjadi 110 request/menit, di bawah batas Mufise 120. Draf dalam sessionStorage dibatasi per lingkungan/koneksi/akun/grant/proyek dengan HMAC server dan usia 24 jam, tanpa token. Polling tidak mengganti isian yang sedang diedit; konflik memperlihatkan data terbaru dan draf sebelum pengguna meninjau ulang. Kehilangan akses privat membuang data dan draf terkait. Logout menghapus seluruh draf editor pada tab tersebut. Data draf tetap sensitif: gunakan logout dan sesi browser pribadi pada perangkat bersama.

Verifikasi lokal: tes PHP integrasi dan tes JavaScript di atas mencakup kebijakan kru saja, call sheet `exists`, perubahan kebijakan/sync saat pickup, policy proksi, MIME, body kosong, multipart dan retry. Pemeriksaan browser memakai fixture terisolasi untuk picker kru, lokasi/koordinat, PATCH catatan call sheet, dialog mobile dan pilihan proyek checkout. Tetap lakukan pairing/pengujian akun organisasi di server tujuan setelah Mufise merilis migrasi 052/053; fixture tidak membuktikan deployment server tersebut.
