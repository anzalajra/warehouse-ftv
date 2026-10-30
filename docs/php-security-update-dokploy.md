# Pembaruan keamanan PHP pada Dokploy

Target: PHP **8.5.12 atau patch 8.5.x yang lebih baru**. Jika builder produksi belum mendukung PHP 8.5, proyek juga menerima **8.4.27 atau patch 8.4.x yang lebih baru**. Perbaikan CVE-2026-109618 dan CVE-2026-109619 tersedia sejak [21 Oktober 2026](https://www.php.net/archive/2026.php#2026-10-21-5).

Pembaruan lokal tidak memperbarui VPS. Pada Dokploy, PHP aplikasi biasanya berada di image/container. Memperbarui PHP melalui `apt` di host VPS tidak mengganti PHP dalam container aplikasi.

## 1. Catat deployment yang sedang aktif

Di Dokploy, buka proyek → aplikasi Warehouse FTV. Catat commit/image aktif dan jenis **Build Type**: Dockerfile, Docker Compose, Nixpacks, atau Railpack. Periksa juga service queue dan scheduler yang terpisah. Catat konfigurasi volume dan pastikan backup database serta unggahan tersedia sebelum mengganti deployment.

Gunakan terminal container Dokploy atau **Advanced → Run Command** untuk menjalankan perintah berikut satu per satu dari direktori aplikasi:

```sh
php -v
php --ini
composer check-platform-reqs --no-dev
```

Jika terminal Dokploy tidak tersedia, SSH ke VPS dengan akun yang biasa digunakan:

```sh
ssh USER@HOST
docker ps --format 'table {{.ID}}\t{{.Names}}\t{{.Image}}'
```

Ganti `CONTAINER_ID` di bawah dengan ID container **aplikasi Warehouse FTV**, bukan database atau Dokploy:

```sh
docker exec CONTAINER_ID php -v
docker inspect --format '{{.Config.WorkingDir}}' CONTAINER_ID
docker exec -it CONTAINER_ID sh
```

Di shell container, masuk ke direktori aplikasi yang berisi `artisan` dan `composer.json`, lalu jalankan perintah pengecekan di atas. Jangan mengubah atau memasang PHP langsung di container aktif: perubahan itu hilang saat deployment berikutnya.

## 2. Sediakan perubahan proyek untuk sumber deployment

Perubahan lokal mencakup batas versi PHP yang sudah ditambal pada `composer.json`, pembaruan `composer.lock` untuk OpenSpout 4.32.0, pemeriksaan setup, dan contoh konfigurasi `deploy/php/99-warehouse-security.ini`. Pastikan perubahan sudah ditinjau dan tersedia pada commit/branch sumber yang dibaca Dokploy sebelum deploy. Tugas ini tidak otomatis melakukan commit atau push.

Gunakan `composer install` dari lockfile saat build; jangan menjalankan `composer update` atau mengabaikan persyaratan platform di produksi.

## 3. Perbarui runtime sesuai Build Type

### Dockerfile atau Docker Compose

Edit Dockerfile atau definisi image yang **sudah dipakai** oleh service aplikasi. Jika memakai official PHP image, pertahankan varian server/distribusinya dan ganti tag ke versi bertambal. Contoh khusus image FPM:

```dockerfile
FROM php:8.5.12-fpm
```

Contoh tersebut hanya mengganti baris `FROM` pada Dockerfile lengkap yang sudah ada; bukan Dockerfile siap deploy. Jika service memakai FrankenPHP, Laravel Octane, atau image vendor lain, pilih rilis vendor yang memuat PHP bertambal dengan arsitektur server yang sama. Perbarui juga stage PHP untuk build Composer dan service queue/scheduler yang memakai image PHP terpisah.

Untuk official PHP image, tambahkan konfigurasi ini di stage runtime:

```dockerfile
COPY deploy/php/99-warehouse-security.ini /usr/local/etc/php/conf.d/99-warehouse-security.ini
```

Jangan menyalin ke path tersebut pada image lain tanpa memeriksa `php --ini`. Direktif perlu dimuat oleh PHP yang melayani web sebelum parsing request; `.user.ini` dan `ini_set()` di Laravel tidak memadai. PHP [merekomendasikan menonaktifkan session upload progress sementara](https://www.php.net/archive/2026.php#2026-10-23-1). Ini tidak menonaktifkan unggahan Laravel/Livewire.

### Nixpacks

[Dokumentasi Nixpacks](https://nixpacks.com/docs/providers/php) saat pemeriksaan mencantumkan dukungan sampai PHP 8.4. Versi dibaca dari `composer.json`; mengubah environment variable yang tidak didokumentasikan tidak menjamin upgrade PHP.

Gunakan builder/snapshot paket yang menyediakan PHP minimal 8.4.27 dan periksa versi pada build log. Nonaktifkan cache sementara dengan `NIXPACKS_NO_CACHE=1` melalui Environment Variables di Dokploy, lalu lakukan build baru. Menghapus cache saja tidak memperbarui snapshot paket Nix yang dipatok. Jika builder tetap menghasilkan versi lama atau Composer menolak versi PHP, hentikan deployment tersebut dan perbarui builder/snapshot atau siapkan Dockerfile yang sesuai. Jangan menambahkan `--ignore-platform-reqs`.

### Railpack

[Railpack](https://railpack.com/languages/php/) membaca versi PHP dari `composer.json`. Dokploy menyediakan kolom [Railpack Version](https://docs.dokploy.com/docs/core/applications/build-type) untuk memilih rilis builder. Gunakan rilis yang menyediakan patch PHP yang diperlukan, kemudian periksa versi nyata pada build log dan container hasilnya. Dukungan versi minor saja tidak membuktikan patch terbaru telah dipakai.

Untuk konfigurasi tambahan, gunakan mekanisme `php.ini` Railpack yang terdokumentasi dan gabungkan `session.upload_progress.enabled=Off` dengan konfigurasi yang sudah ada; jangan menimpa seluruh konfigurasi lama hanya dengan contoh satu baris. Jangan mengganti Build Type produksi tanpa menyesuaikan port, document root, start command, volume, queue, dan scheduler yang sebelumnya digunakan.

## 4. Build dan deploy container baru

Di Dokploy, jalankan build/deploy baru dari sumber yang diperbarui. Pastikan build menarik image atau paket PHP bertambal, dan `composer install` berhasil tanpa mengabaikan persyaratan PHP. Restart container lama saja tetap menjalankan image lama. Perubahan ini tidak memerlukan migrasi database atau penggantian APP_KEY/VAPID.

Ganti semua replica web dan deploy ulang service queue/scheduler yang terpisah menggunakan image bertambal. Periksa deployment dan log aplikasi sampai container baru sehat. Jika build gagal, simpan log kesalahannya; jangan menganggap server sudah ditambal.

## 5. Verifikasi setelah deployment

Jalankan di **setiap container baru** dari direktori aplikasi:

```sh
php -v
composer check-platform-reqs --no-dev
php -r 'echo "PHP=", PHP_VERSION, PHP_EOL, "upload_progress=", ini_get("session.upload_progress.enabled"), PHP_EOL;'
```

Versi harus 8.5.12+ pada cabang 8.5, atau 8.4.27+ pada cabang 8.4. Nilai `upload_progress` harus kosong atau `0` jika konfigurasi tambahan diterapkan. Jika memakai PHP-FPM, periksa juga konfigurasi FPM dari image yang sama, karena konfigurasi CLI dan web dapat berbeda:

```sh
php-fpm -i | grep -E 'PHP Version|Loaded Configuration File|Scan this dir|session.upload_progress.enabled'
```

Untuk FrankenPHP/Apache, periksa versi dan konfigurasi runtime server tersebut pada log/alat administrasinya. Pastikan semua worker berasal dari container baru; hasil `php -v` saja hanya membuktikan versi CLI.

Buka storefront dan `/admin`, lalu periksa login, katalog/jadwal, unggahan, ekspor laporan, queue, serta scheduler. Jangan membuat halaman `phpinfo()` publik untuk verifikasi.

## Status pekerjaan

Panduan ini tidak membuktikan deployment produksi sudah dilakukan. Server baru dinyatakan diperbarui setelah versi dan fungsi aplikasi pada container produksi terverifikasi. Audit Composer juga melaporkan advisori pada dependensi lain; upgrade PHP ini bukan penyelesaian seluruh advisori paket aplikasi.
