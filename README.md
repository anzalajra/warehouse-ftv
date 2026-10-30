# Warehouse FTV / Gearent

Aplikasi rental peralatan berbasis Laravel 12, Filament 4, dan Vite. Panduan ini untuk pengembangan dan pengujian **lokal** di macOS.

Untuk produksi, lihat [panduan deploy Dokploy](docs/dokploy-deployment.md) dan wrapper startup `scripts/start-container.sh` agar migrasi serta pembersihan cache kode berjalan sebelum server dimulai.

## Persiapan pertama

Instal [Homebrew](https://brew.sh/) jika belum tersedia, lalu:

```bash
brew install php composer node
brew upgrade php
php -v
composer --version
node -v
```

PHP harus memiliki ekstensi `pdo_sqlite`, `sqlite3`, `mbstring`, `bcmath`, `gd`, dan `zip`. Dari akar repo, jalankan:

Target lokal adalah PHP **8.5.12 atau patch 8.5.x yang lebih baru**. PHP 8.4 tetap didukung mulai **8.4.27**. Jika PHP 8.4 masih aktif melalui Homebrew, jalankan `brew unlink php@8.4` lalu `brew link php`, kemudian periksa `php -v`. OpenSpout pada lockfile telah diperbarui untuk mendukung PHP 8.5. Script setup memeriksa versi PHP dan ekstensi terhadap lockfile sebelum menyiapkan aplikasi.

Versi minimum tersebut memuat perbaikan CVE-2026-109618 dan CVE-2026-109619 pada [rilis keamanan PHP 21 Oktober 2026](https://www.php.net/archive/2026.php#2026-10-21-5). Ikuti [panduan pembaruan Dokploy](docs/php-security-update-dokploy.md) untuk lingkungan produksi.

```bash
bash scripts/setup-local.sh
composer test
```

Script menyiapkan `.env` dan database SQLite lokal, memasang dependensi dari lockfile, menjalankan migrasi, mengisi data awal bila database baru atau data awal belum lengkap, dan mengaktifkan rute aplikasi lewat `storage/installed`. Script menolak `.env` yang bukan `APP_ENV=local` dan `DB_CONNECTION=sqlite`. File `.env`, database, dependensi, dan marker instalasi diabaikan Git. Jangan gunakan database produksi untuk langkah ini.

Seeder lokal membuat admin `admin@gearent.com` dengan kata sandi `password`. Ubah kata sandi setelah login, terutama bila server dapat diakses dari jaringan lain.

## Menjalankan aplikasi

```bash
composer dev
```

Buka `http://127.0.0.1:8000` untuk storefront atau `http://127.0.0.1:8000/admin` untuk panel admin. `composer dev` juga menjalankan Vite dan queue worker.

## Menguji perubahan

```bash
composer test                         # seluruh suite PHP, SQLite in-memory
composer test:smoke                   # tes inti yang lulus pada checkout ini
php artisan test --filter=NamaTest    # tes tertentu
npm run build                         # periksa kompilasi aset frontend
```

`npm run build` mengubah file `public/build` yang terlacak Git; periksa diff sebelum menyimpan perubahan. Tes memakai `phpunit.xml` dan database SQLite `:memory:`, terpisah dari `database/database.sqlite` lokal. Mode testing memuat rute aplikasi walaupun marker `storage/installed` tidak ada.

Jika dependensi sudah terpasang, jalankan `composer test` langsung; tidak perlu mengulang `composer install` untuk setiap perubahan. Untuk perubahan PHP, jalankan `php -l path/ke/file.php` dan tes terkait. Untuk perubahan frontend, jalankan `npm run build`.

Pada checkout saat panduan ini ditulis, suite penuh menghasilkan **11 lulus, 21 gagal**. Kegagalan tersisa berasal dari tes auth/profil lama yang masih memakai rute Breeze dan dua asersi finance yang mengharapkan pemetaan jurnal lama. Gunakan `composer test:smoke` untuk pemeriksaan awal yang hijau, lalu jalankan tes terkait perubahan dan `composer test` agar kegagalan baru tetap terlihat. Jangan menganggap hasil smoke sebagai bukti seluruh fitur telah teruji.
