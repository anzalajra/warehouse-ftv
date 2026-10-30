# Integrasi Mufise untuk Warehouse FTV

Mengikuti kontrak API v1 Mufise tanggal 30 Oktober 2026, termasuk onboarding organisasi dan `GET /me` (`next_action`). Seluruh komunikasi dilakukan server Laravel. Tidak perlu akses database Mufise atau konfigurasi CORS.

## Mengaktifkan

1. Terapkan migrasi dengan `php artisan migrate`. Cadangkan database sesuai prosedur deployment biasa.
2. Di Mufise, admin organisasi membuat koneksi eksternal Warehouse FTV dan mengatur izin tulis kru/lokasi sesuai kebutuhan.
3. Masuk sebagai super admin Warehouse, buka **Settings → Integrasi Mufise**. Isi URL dasar Mufise, Connection ID, API key dan aktifkan integrasi. URL wajib HTTPS kecuali localhost. Key disimpan terenkripsi; input kosong mempertahankan key lama. Admin yang didelegasikan memerlukan izin `Manage:Mufise` pada guard web.
4. Jika diperlukan, URL/Connection ID/API key dapat memakai fallback `MUFISE_URL`, `MUFISE_CONNECTION_ID`, `MUFISE_API_KEY` dari `config/services.php`. Setting panel mengambil prioritas. Setelah mengganti env, perbarui config cache sesuai deployment.
5. Pelanggan membuka **Profile → Koneksi akun Mufise** atau **Koneksi Mufise** pada menu akun. Klik **Connect Mufise Account**, buka tautan persetujuan, login dan selesaikan keanggotaan organisasi serta pembuatan/pemilihan proyek di Mufise. Halaman Warehouse memeriksa persetujuan setiap minimal 5 detik, maksimal 10 menit. Tautan tidak memuat request secret.
6. Setelah terhubung, pelanggan dapat melihat proyek dan memilih **Gunakan proyek lain / Buat proyek baru**. Grant sebelumnya tetap dipakai sampai pemasangan baru berhasil. Token berlaku maksimal 90 hari tanpa refresh token. Proyek yang sebelumnya ditautkan ke rental harus tetap dipilih pada grant pengganti agar rental dapat diverifikasi.
7. Muat ulang checkout setelah selesai memasangkan akun agar pilihan proyek diperbarui.

## Perilaku peminjaman

- Integrasi hanya diwajibkan pada checkout **luar kelas baru** ketika diaktifkan. Jenis tugas tetap wajib. Kru dan lokasi manual diganti oleh pilihan proyek Mufise; tidak dibuat itinerary lokal dengan data NIM/jadwal buatan.
- Akun yang belum siap diarahkan melengkapi keanggotaan, membuat proyek atau memilih proyek melalui halaman persetujuan Mufise, sesuai `next_action`. Pembuatan proyek dan persetujuan organisasi mengikuti kewenangan Mufise.
- Sebelum pengajuan, server mengambil proyek memakai token milik peminjam dan memeriksa `readiness.can_submit`. Proyek harus aktif, mempunyai produser atau sutradara dan lokasi bernama serta beralamat. Keranjang tetap tersimpan apabila validasi gagal; akun/proyek dapat dipersiapkan sebagai draf sebelum pengajuan.
- Saat pengambilan, `Rental::validatePickup()` memeriksa ulang proyek, `can_collect`, keberadaan dan lock **Day 1**. Dokumen JSON diperiksa terhadap Day ID, nomor hari dan revisi jadwal. Hasil pemeriksaan serta HTML call sheet disimpan terenkripsi pada rental bersama waktu pemeriksaan. Pemeriksaan ini merekam keadaan ketika diambil; bukan webhook atau jaminan data Mufise tidak berubah setelahnya.
- Gangguan jaringan, credential/grant dicabut, proyek tak dapat diakses, atau perubahan revisi menahan pengambilan. Tidak ada bypass otomatis. Hubungkan ulang akun atau perbaiki izin/koneksi dan ulangi validasi.
- Detail rental pelanggan dan admin menyediakan **Kru, lokasi & call sheet**. Petugas mengakses melalui policy transaksi dan token peminjam. Call sheet disajikan melalui proksi Warehouse dengan login, cache privat, CSP dan iframe sandbox.
- Tambah/edit kru dan lokasi mengikuti izin API. Perubahan profil kru dapat berdampak lintas proyek; kontak akun terhubung harus diperbarui pemilik di Mufise. Personel yang sudah ada dapat ditugaskan dengan Person ID Mufise yang diketahui secara sah; Warehouse tidak menyediakan pencarian direktori organisasi.
- Setiap formulir mutasi mempunyai UUID operasi. Body, tujuan dan UUID disimpan terenkripsi sebelum panggilan API. Kirim ulang formulir yang sama setelah hasil tak diketahui untuk memakai key/body yang sama. Perubahan body dengan UUID yang sama ditolak. Setelah konflik versi atau perubahan input, muat ulang, tinjau data terbaru dan kirim formulir baru. Konfirmasi nama serupa hanya setelah memastikan orangnya berbeda.
- Mengganti URL/Connection ID membuat koneksi akun sebelumnya tidak dapat dipakai untuk tujuan baru. Rotasi API key pada koneksi yang sama mempertahankan grant pengguna.
- Mematikan setting membiarkan checkout baru memakai alur itinerary sebelumnya. Rental yang sudah bertanda `mufise_required` tetap wajib diverifikasi, sehingga pengambilannya tertahan selama koneksi dimatikan. Rental lama dan peminjaman dalam kelas tidak mendapat persyaratan baru. Jangan menghapus penanda tersebut untuk melewati validasi.
- Peminjam pada rental terhubung tidak dapat diganti di editor. Gunakan transaksi baru untuk peminjam lain. Pengaktifan status harus melalui proses pengambilan, bukan pemilihan status langsung pada editor.

## Keamanan dan verifikasi

Pertahankan APP_KEY dan cadangannya agar token, key dan snapshot dapat didekripsi. Token serta pairing disembunyikan dari serialisasi model. API client tidak meneruskan exception HTTP berisi header/body mentah. Tidak ada key/token pada URL dokumen, bundle JavaScript, VITE env atau Electron. Tidak ada retry otomatis untuk penukaran token sekali pakai atau mutasi yang belum diketahui hasilnya.

Tes lokal memakai `Http::fake()` dan SQLite in-memory, tanpa mengubah data Mufise:

```sh
php artisan test tests/Feature/MufiseIntegrationTest.php
composer test:smoke
composer test
npm run build
```

Penerimaan langsung dengan server Mufise yang telah memiliki API terbaru dan kredensial nyata masih diperlukan sebelum deployment: uji editor/viewer, organisasi yang memerlukan persetujuan, proyek tanpa kru/lokasi, lock Day 2 saja, lock Day 1, pencabutan grant/izin, konflik versi dan koneksi putus. Jangan memasukkan key/token ke laporan pengujian atau log.
